<?php

declare(strict_types=1);

namespace App\Core;

use function App\Core\t;

final class Validator
{
    /**
     * @return array<string,string>
     */
    public static function house(array $data): array
    {
        $errors = [];

        if (mb_strlen(trim((string) ($data['name'] ?? ''))) < 2) {
            $errors['name'] = t('validation.house_name_required');
        }

        if (mb_strlen(trim((string) ($data['city'] ?? ''))) < 2) {
            $errors['city'] = t('validation.city_required');
        }

        return $errors;
    }

    /**
     * @return array<string,string>
     */
    public static function room(array $data): array
    {
        $errors = [];
        if (mb_strlen(trim((string) ($data['name'] ?? ''))) < 2) {
            $errors['name'] = t('validation.room_name_required');
        }

        if (!in_array((string) ($data['floor'] ?? ''), ['Kelder', 'Begane Grond', 'Eerste Verdieping', 'Tweede Verdieping', 'Zolder'], true)) {
            $errors['floor'] = t('validation.invalid_floor');
        }

        return $errors;
    }

    /**
     * @return array<string,string>
     */
    public static function material(array $data): array
    {
        $errors = [];

        if (mb_strlen(trim((string) ($data['name'] ?? ''))) < 2) {
            $errors['name'] = t('validation.material_name_required');
        }

        if (!in_array((string) ($data['type'] ?? ''), ['Verf', 'Tegel', 'Hout', 'Behang', 'Overig'], true)) {
            $errors['type'] = t('validation.invalid_material_type');
        }

        $hex = strtoupper(trim((string) ($data['color_hex'] ?? '')));
        if ($hex !== '' && preg_match('/^#[A-F0-9]{6}$/', $hex) !== 1) {
            $errors['color_hex'] = t('validation.invalid_hex_color');
        }

        return $errors;
    }
}
