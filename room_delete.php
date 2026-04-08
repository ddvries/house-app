<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repositories\AttachmentRepository;
use App\Repositories\RoomRepository;
use App\Services\AttachmentService;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();

if (!isPost()) {
    redirect('/houses.php');
}

$userId = (int) Auth::userId();
$roomId = (int) ($_POST['room_id'] ?? 0);
$houseId = (int) ($_POST['house_id'] ?? 0);

try {
    Csrf::validate($_POST['_csrf'] ?? null);

    if ($roomId < 1) {
        throw new RuntimeException(t('error.invalid_room_selection'));
    }

    $roomRepo = new RoomRepository();
    $attachmentRepo = new AttachmentRepository();

    $room = $roomRepo->findForUser($roomId, $userId);
    if ($room === null) {
        throw new RuntimeException(t('error.room_not_found'));
    }

    $attachments = $attachmentRepo->listForRoomForUser($roomId, $userId);
    (new AttachmentService())->deleteStoredFiles($attachments);

    $roomRepo->deleteForUser($roomId, $userId);
    Flash::set('success', t('flash.room_deleted_with_children'));
} catch (Throwable $e) {
    Flash::set('error', $e->getMessage());
}

redirect('/house.php?id=' . $houseId);