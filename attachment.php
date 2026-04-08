<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Flash;
use App\Repositories\AttachmentRepository;
use App\Core\SecurityLogger;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();
$userId = (int) Auth::userId();
$attachmentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$attachment = (new AttachmentRepository())->findForUser($attachmentId, $userId);
if ($attachment === null) {
    Flash::set('error', t('error.attachment_not_found'));
    redirect('/houses.php');
}

$path = __DIR__ . '/storage/uploads/' . $attachment['stored_name'];
$uploadsDir = realpath(__DIR__ . '/storage/uploads');
$realPath = realpath($path);
if ($realPath === false || $uploadsDir === false || !str_starts_with($realPath, $uploadsDir . DIRECTORY_SEPARATOR)) {
    SecurityLogger::pathTraversalAttempt($attachment['stored_name']);
    Flash::set('error', t('error.file_missing_on_server'));
    redirect('/houses.php');
}
if (!is_file($realPath)) {
    Flash::set('error', t('error.file_missing_on_server'));
    redirect('/houses.php');
}

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) filesize($realPath));
header('Content-Disposition: attachment; filename="' . rawurlencode((string) $attachment['original_name']) . '"');
readfile($realPath);
exit;
