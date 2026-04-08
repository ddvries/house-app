<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repositories\AttachmentRepository;
use App\Repositories\MaterialRepository;
use App\Services\AttachmentService;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();

if (!isPost()) {
    redirect('/houses.php');
}

$userId = (int) Auth::userId();
$materialId = (int) ($_POST['material_id'] ?? 0);
$houseId = (int) ($_POST['house_id'] ?? 0);
$roomId = (int) ($_POST['room_id'] ?? 0);
$returnTo = (string) ($_POST['return_to'] ?? '');

try {
    Csrf::validate($_POST['_csrf'] ?? null);

    $materialRepo = new MaterialRepository();
    $attachmentRepo = new AttachmentRepository();

    $material = $materialRepo->findForUser($materialId, $userId);
    if ($material === null) {
        throw new RuntimeException(t('error.material_not_found'));
    }

    $attachments = $attachmentRepo->listForMaterial($materialId);
    (new AttachmentService())->deleteStoredFiles($attachments);
    $materialRepo->delete($materialId);

    Flash::set('success', t('flash.material_deleted'));
} catch (Throwable $e) {
    Flash::set('error', $e->getMessage());
}

if ($returnTo === 'materials') {
    redirect('/materials.php');
}

if ($roomId > 0) {
    redirect('/room.php?id=' . $roomId);
}

redirect('/house.php?id=' . $houseId);