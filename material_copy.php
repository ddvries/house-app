<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repositories\MaterialRepository;
use App\Repositories\RoomRepository;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();

if (!isPost()) {
    redirect('/materials.php');
}

$userId = (int) Auth::userId();
$materialId = (int) ($_POST['material_id'] ?? 0);
$targetRoomId = (int) ($_POST['room_id'] ?? 0);

try {
    Csrf::validate($_POST['_csrf'] ?? null);

    if ($materialId < 1 || $targetRoomId < 1) {
        throw new RuntimeException(t('error.invalid_selection'));
    }

    $roomRepo = new RoomRepository();
    $targetRoom = $roomRepo->findForUser($targetRoomId, $userId);
    if ($targetRoom === null) {
        throw new RuntimeException(t('error.room_not_found'));
    }

    $materialRepo = new MaterialRepository();
    $materialRepo->copyToRoom($materialId, $targetRoomId, $userId);

    Flash::set('success', t('flash.material_assigned_to') . ' ' . $targetRoom['name'] . '.');
} catch (Throwable $e) {
    Flash::set('error', $e->getMessage());
}

redirect('/materials.php');
