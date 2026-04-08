<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Language;
use function App\Core\e;

final class PdfExportService
{
    /** @return array<string,mixed>|null */
    public function housePayload(int $houseId, int $userId): ?array
    {
        $pdo = Database::connection();

        $houseStmt = $pdo->prepare('SELECT id, name, city, notes FROM houses WHERE id = :id AND owner_user_id = :user_id LIMIT 1');
        $houseStmt->execute(['id' => $houseId, 'user_id' => $userId]);
        $house = $houseStmt->fetch();

        if (!is_array($house)) {
            return null;
        }

        $floorOrder = "FIELD(floor,'Kelder','Begane Grond','Eerste Verdieping','Tweede Verdieping','Zolder')";
        $roomsStmt = $pdo->prepare('SELECT id, name, floor, notes FROM rooms WHERE house_id = :house_id ORDER BY ' . $floorOrder . ', name ASC');
        $roomsStmt->execute(['house_id' => $houseId]);
        $rooms = $roomsStmt->fetchAll();

        $materialsStmt = $pdo->prepare('SELECT m.id, m.room_id, m.type, m.name, m.color_hex, m.description, r.name AS room_name
                                        FROM materials m
                                        INNER JOIN rooms r ON r.id = m.room_id
                                        WHERE r.house_id = :house_id
                                        ORDER BY r.name ASC, m.name ASC');
        $materialsStmt->execute(['house_id' => $houseId]);
        $materials = $materialsStmt->fetchAll();

        $linksStmt = $pdo->prepare('SELECT l.material_id, l.url FROM material_store_links l INNER JOIN materials m ON m.id = l.material_id INNER JOIN rooms r ON r.id = m.room_id WHERE r.house_id = :house_id ORDER BY l.id ASC');
        $linksStmt->execute(['house_id' => $houseId]);
        $links = $linksStmt->fetchAll();

        $attachmentsStmt = $pdo->prepare('SELECT a.material_id, a.original_name, a.stored_name, a.mime_type, a.size_bytes
                                          FROM attachments a
                                          INNER JOIN materials m ON m.id = a.material_id
                                          INNER JOIN rooms r ON r.id = m.room_id
                                          WHERE r.house_id = :house_id
                                          ORDER BY a.uploaded_at DESC');
        $attachmentsStmt->execute(['house_id' => $houseId]);
        $attachments = $attachmentsStmt->fetchAll();

        return [
            'house' => $house,
            'rooms' => $rooms,
            'materials' => $materials,
            'links' => $links,
            'attachments' => $attachments,
        ];
    }

    /** @param array<string,mixed> $payload */
    public function renderHtml(array $payload, bool $includeLinks, bool $includeAttachments, bool $includeNotes): string
    {
        $house = $payload['house'];
        $rooms = $payload['rooms'];
        $materials = $payload['materials'];
        $links = $payload['links'];
        $attachments = $payload['attachments'];

        $linkMap = [];
        foreach ($links as $link) {
            $linkMap[(int) $link['material_id']][] = (string) $link['url'];
        }

        $attachmentMap = [];
        foreach ($attachments as $attachment) {
            $attachmentMap[(int) $attachment['material_id']][] = $attachment;
        }

        $materialsByRoom = [];
        foreach ($materials as $material) {
            $materialsByRoom[(int) $material['room_id']][] = $material;
        }

        $floorNames = ['Kelder', 'Begane Grond', 'Eerste Verdieping', 'Tweede Verdieping', 'Zolder'];

        // Group rooms by floor in sorted order
        $roomsByFloor = [];
        foreach ($rooms as $room) {
            $roomsByFloor[(string) $room['floor']][] = $room;
        }

        $roomSections = '';
        foreach ($floorNames as $floorName) {
            if (!isset($roomsByFloor[$floorName])) {
                continue;
            }

            $roomSections .= '<section class="floor-section"><h2>' . htmlspecialchars($this->floorLabel($floorName)) . '</h2>';

            foreach ($roomsByFloor[$floorName] as $room) {
                $roomId = (int) $room['id'];
                $roomMaterials = $materialsByRoom[$roomId] ?? [];
                $roomNotes = $includeNotes && trim((string) ($room['notes'] ?? '')) !== ''
                    ? '<p class="room-notes"><strong>' . htmlspecialchars(Language::translate('common.notes')) . ':</strong><br>' . nl2br(htmlspecialchars((string) $room['notes'])) . '</p>'
                    : '';

                $materialRows = '';
                foreach ($roomMaterials as $material) {
                    $materialId = (int) $material['id'];
                    $notes = $includeNotes && trim((string) ($material['description'] ?? '')) !== ''
                        ? nl2br(htmlspecialchars((string) $material['description']))
                        : '';

                    $linksHtml = '';
                    if ($includeLinks) {
                        $urls = $linkMap[$materialId] ?? [];
                        if ($urls !== []) {
                            $linksHtml = '<div><strong>Links:</strong><ul>';
                            foreach ($urls as $url) {
                                $safe = htmlspecialchars($url);
                                $linksHtml .= '<li><a href="' . $safe . '">' . $safe . '</a></li>';
                            }
                            $linksHtml .= '</ul></div>';
                        }
                    }

                    $attachmentHtml = '';
                    if ($includeAttachments) {
                        $rows = $attachmentMap[$materialId] ?? [];
                        if ($rows !== []) {
                            $nonImageItems = [];
                            $imageItems = [];
                            foreach ($rows as $file) {
                                $imageTag = $this->renderAttachmentImage($file);
                                if ($imageTag !== '') {
                                    $imageItems[] = $imageTag;
                                } else {
                                    $nonImageItems[] = htmlspecialchars((string) $file['original_name']) . ' (' . htmlspecialchars((string) $file['mime_type']) . ')';
                                }
                            }

                            if ($nonImageItems !== [] || $imageItems !== []) {
                                $attachmentHtml = '<div>';
                                if ($nonImageItems !== []) {
                                    $attachmentHtml .= '<strong>' . htmlspecialchars(Language::translate('common.attachments')) . ':</strong><ul>';
                                    foreach ($nonImageItems as $item) {
                                        $attachmentHtml .= '<li>' . $item . '</li>';
                                    }
                                    $attachmentHtml .= '</ul>';
                                }
                                if ($imageItems !== []) {
                                    $attachmentHtml .= '<div class="image-grid">' . implode('', $imageItems) . '</div>';
                                }
                                $attachmentHtml .= '</div>';
                            }
                        }
                    }

                    $details = trim($linksHtml . $attachmentHtml) !== ''
                        ? '<div class="meta-stack">' . $linksHtml . $attachmentHtml . '</div>'
                        : '';

                    $materialRows .= '<tr>'
                        . '<td>' . htmlspecialchars($this->materialTypeLabel((string) $material['type'])) . '</td>'
                        . '<td>' . htmlspecialchars((string) $material['name']) . '</td>'
                        . '<td>' . htmlspecialchars((string) $material['color_hex']) . '</td>'
                        . '<td>' . $notes . $details . '</td>'
                        . '</tr>';
                }

                if ($materialRows === '') {
                    $materialRows = '<tr><td colspan="4">' . htmlspecialchars(Language::translate('room.no_materials')) . '</td></tr>';
                }

                $roomSections .= '<section class="room-section">'
                    . '<h3>' . htmlspecialchars((string) $room['name']) . '</h3>'
                    . $roomNotes
                    . '<table><thead><tr><th>' . htmlspecialchars(Language::translate('common.type')) . '</th><th>' . htmlspecialchars(Language::translate('common.name')) . '</th><th>' . htmlspecialchars(Language::translate('common.color')) . '</th><th>' . htmlspecialchars(Language::translate('common.details')) . '</th></tr></thead><tbody>' . $materialRows . '</tbody></table>'
                    . '</section>';
            }

            $roomSections .= '</section>';
        }

        $houseName = htmlspecialchars((string) $house['name']);
        $city = htmlspecialchars((string) $house['city']);
        $houseNotes = $includeNotes ? nl2br(htmlspecialchars((string) $house['notes'])) : '';

        return '<!DOCTYPE html><html lang="' . htmlspecialchars(Language::current()) . '"><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222}table{width:100%;border-collapse:collapse;margin-bottom:14px}th,td{border:1px solid #bbb;padding:6px;text-align:left;vertical-align:top}h1{margin:12px 0 8px}h2{margin:18px 0 4px;padding:6px 8px;background:#eee;border-left:4px solid #888}h3{margin:10px 0 4px}h4{margin:8px 0 4px}.floor-section{margin-top:24px;page-break-before:auto}.room-section{margin-top:12px;page-break-inside:avoid}.room-notes{margin:6px 0 10px}.meta-stack{margin-top:8px}.meta-stack ul{margin:4px 0 8px 16px;padding:0}.image-grid{margin-top:8px}.image-card{display:inline-block;width:150px;margin:0 8px 8px 0;vertical-align:top}.image-card img{width:150px;height:auto;border:1px solid #bbb}</style></head><body>'
            . '<h1>' . htmlspecialchars(Language::translate('export.house_export')) . ': ' . $houseName . '</h1>'
            . '<p><strong>' . htmlspecialchars(Language::translate('common.location')) . ':</strong> ' . $city . '</p>'
            . ($includeNotes ? '<p><strong>' . htmlspecialchars(Language::translate('common.notes')) . ':</strong><br>' . $houseNotes . '</p>' : '')
            . $roomSections
            . '</body></html>';
    }

    private function floorLabel(string $value): string
    {
        return match ($value) {
            'Kelder' => Language::translate('floor.basement'),
            'Begane Grond' => Language::translate('floor.ground'),
            'Eerste Verdieping' => Language::translate('floor.first'),
            'Tweede Verdieping' => Language::translate('floor.second'),
            'Zolder' => Language::translate('floor.attic'),
            default => $value,
        };
    }

    private function materialTypeLabel(string $value): string
    {
        return match ($value) {
            'Verf' => Language::translate('material_type.paint'),
            'Tegel' => Language::translate('material_type.tile'),
            'Hout' => Language::translate('material_type.wood'),
            'Behang' => Language::translate('material_type.wallpaper'),
            'Overig' => Language::translate('material_type.other'),
            default => $value,
        };
    }

    /** @param array<string,mixed> $attachment */
    private function renderAttachmentImage(array $attachment): string
    {
        $mimeType = (string) ($attachment['mime_type'] ?? '');
        if (strpos($mimeType, 'image/') !== 0) {
            return '';
        }

        $storedName = (string) ($attachment['stored_name'] ?? '');
        if ($storedName === '') {
            return '';
        }

        $path = dirname(__DIR__, 2) . '/storage/uploads/' . $storedName;
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return '';
        }

        $src = 'data:' . $mimeType . ';base64,' . base64_encode($contents);

        return '<div class="image-card"><img src="' . $src . '" alt="' . htmlspecialchars((string) ($attachment['original_name'] ?? Language::translate('common.image'))) . '" /></div>';
    }
}
