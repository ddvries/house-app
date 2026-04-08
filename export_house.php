<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repositories\HouseRepository;
use App\Services\PdfExportService;
use function App\Core\e;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();
$userId = (int) Auth::userId();

$houseRepo = new HouseRepository();
$houses = $houseRepo->allForUser($userId);

if ($houses === []) {
  Flash::set('error', t('error.no_houses_to_export'));
    redirect('/house_form.php');
}

$defaultHouseId = isset($_GET['house_id']) ? (int) $_GET['house_id'] : (int) $houses[0]['id'];

if (isPost()) {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);

        $houseId = (int) ($_POST['house_id'] ?? 0);
        $includeLinks = isset($_POST['include_links']);
        $includeAttachments = isset($_POST['include_attachments']);
        $includeNotes = isset($_POST['include_notes']);

        $service = new PdfExportService();
        $payload = $service->housePayload($houseId, $userId);
        if ($payload === null) {
          throw new RuntimeException(t('error.house_not_found_for_export'));
        }

        $html = $service->renderHtml($payload, $includeLinks, $includeAttachments, $includeNotes);

        if (class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = 'house-export-' . date('Ymd-His') . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $dompdf->output();
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    } catch (Throwable $e) {
        Flash::set('error', $e->getMessage());
        redirect('/export_house.php?house_id=' . $defaultHouseId);
    }
}

$title = t('export.title');
$active = 'houses';
require __DIR__ . '/partials/layout_start.php';
?>
<section class="page-title">
  <div>
    <h2><?= e(t('export.heading')) ?></h2>
    <p><?= e(t('export.subtitle')) ?></p>
  </div>
</section>

<section class="card" style="margin-bottom: 1rem;">
  <form action="" method="post">
    <?= Csrf::field() ?>

    <div class="grid cols-2">
      <div class="field">
        <label for="house_id"><?= e(t('common.house')) ?></label>
        <select id="house_id" name="house_id">
          <?php foreach ($houses as $house): ?>
            <option value="<?= (int) $house['id'] ?>" <?= $defaultHouseId === (int) $house['id'] ? 'selected' : '' ?>>
              <?= e((string) $house['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label><?= e(t('export.options')) ?></label>
        <div class="grid cols-3">
          <label class="chip" for="include_links">
            <input id="include_links" name="include_links" type="checkbox" checked />
            <?= e(t('export.include_links')) ?>
          </label>
          <label class="chip" for="include_attachments">
            <input id="include_attachments" name="include_attachments" type="checkbox" checked />
            <?= e(t('export.include_attachments')) ?>
          </label>
          <label class="chip" for="include_notes">
            <input id="include_notes" name="include_notes" type="checkbox" checked />
            <?= e(t('export.include_notes')) ?>
          </label>
        </div>
      </div>
    </div>

    <div style="display: flex; gap: 0.6rem; flex-wrap: wrap; margin-top: 0.7rem;">
      <button class="btn primary" type="submit"><?= e(t('export.generate')) ?></button>
      <a class="btn ghost" href="/house.php?id=<?= $defaultHouseId ?>"><?= e(t('common.back_to_house')) ?></a>
    </div>
  </form>
</section>
<?php require __DIR__ . '/partials/layout_end.php';
