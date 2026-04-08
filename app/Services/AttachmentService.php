<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Language;
use App\Core\SecurityLogger;
use App\Repositories\AttachmentRepository;
use RuntimeException;

final class AttachmentService
{
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
    ];

    public function __construct(
        private readonly AttachmentRepository $repository = new AttachmentRepository()
    ) {
    }

    /**
     * @param array<string,mixed> $files
     */
    public function handleUploads(int $materialId, array $files): void
    {
        if (!isset($files['name']) || !is_array($files['name'])) {
            return;
        }

        $maxMb = (int) ($_ENV['UPLOAD_MAX_MB'] ?? 10);
        $maxBytes = max(1, $maxMb) * 1024 * 1024;

        $total = count($files['name']);
        for ($i = 0; $i < $total; $i++) {
            $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException(Language::translate('error.attachment_upload_failed'));
            }

            $tmpName = (string) ($files['tmp_name'][$i] ?? '');
            $size = (int) ($files['size'][$i] ?? 0);
            $originalName = (string) ($files['name'][$i] ?? 'bestand');

            if (!is_uploaded_file($tmpName)) {
                SecurityLogger::invalidUpload('is_uploaded_file() failed', context: ['filename' => $originalName]);
                throw new RuntimeException(Language::translate('error.invalid_upload_file'));
            }

            if ($size < 1 || $size > $maxBytes) {
                SecurityLogger::invalidUpload('file size invalid', context: ['filename' => $originalName, 'size' => $size, 'max_bytes' => $maxBytes]);
                throw new RuntimeException(Language::translate('error.file_too_large_or_empty'));
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = (string) $finfo->file($tmpName);
            if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
                SecurityLogger::invalidUpload('mime type not allowed', context: ['filename' => $originalName, 'mime_type' => $mimeType]);
                throw new RuntimeException(Language::translate('error.file_type_not_allowed'));
            }

            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', (string) $ext) ?: 'bin';
            $storedName = bin2hex(random_bytes(16)) . '.' . strtolower($safeExt);

            $targetPath = dirname(__DIR__, 2) . '/storage/uploads/' . $storedName;
            if (!move_uploaded_file($tmpName, $targetPath)) {
                throw new RuntimeException(Language::translate('error.cannot_store_upload_file'));
            }

            $sanitizedOriginal = mb_substr(str_replace(["\r", "\n"], '', $originalName), 0, 255);
            $this->repository->create($materialId, $sanitizedOriginal, $storedName, $mimeType, $size);
        }
    }

    /** @param list<array<string,mixed>> $attachments */
    public function deleteStoredFiles(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $storedName = (string) ($attachment['stored_name'] ?? '');
            if ($storedName === '') {
                continue;
            }

            $targetPath = dirname(__DIR__, 2) . '/storage/uploads/' . $storedName;
            if (is_file($targetPath)) {
                @unlink($targetPath);
            }
        }
    }
}
