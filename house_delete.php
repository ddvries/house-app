<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repositories\AttachmentRepository;
use App\Repositories\HouseRepository;
use App\Services\AttachmentService;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();

if (!isPost()) {
    redirect('/houses.php');
}

$userId = (int) Auth::userId();
$houseId = (int) ($_POST['house_id'] ?? 0);

try {
    Csrf::validate($_POST['_csrf'] ?? null);

    if ($houseId < 1) {
        throw new RuntimeException(t('error.invalid_house_selection'));
    }

    $houseRepo = new HouseRepository();
    $attachmentRepo = new AttachmentRepository();

    $house = $houseRepo->findForUser($houseId, $userId);
    if ($house === null) {
        throw new RuntimeException(t('error.house_not_found'));
    }

    $attachments = $attachmentRepo->listForHouseForUser($houseId, $userId);
    (new AttachmentService())->deleteStoredFiles($attachments);

    $houseRepo->deleteForUser($houseId, $userId);
    Flash::set('success', t('flash.house_deleted_with_children'));
} catch (Throwable $e) {
    Flash::set('error', $e->getMessage());
}

redirect('/houses.php');