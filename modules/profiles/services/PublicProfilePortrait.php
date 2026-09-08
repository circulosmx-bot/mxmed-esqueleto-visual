<?php
declare(strict_types=1);

namespace Profiles\Services;

final class PublicProfilePortrait
{
    public static function resolve(array $identity, string $planCode): ?string
    {
        $photo = trim((string)($identity['photo_url'] ?? ''));
        if ($photo !== '') {
            return $photo;
        }
        if ($planCode !== 'free') {
            return null;
        }
        // An explicit unsupported code remains unknown; never infer from a name/title.
        $gender = trim((string)($identity['gender'] ?? ''));
        if ($gender === '') {
            $gender = trim((string)($identity['gender_label'] ?? ''));
        }
        $gender = mb_strtolower($gender, 'UTF-8');
        if (in_array($gender, ['m', 'male', 'masculine', 'hombre', 'masculino'], true)) {
            return '/assets/img/doctors/avatars/dr-male.png';
        }
        if (in_array($gender, ['f', 'female', 'feminine', 'mujer', 'femenino'], true)) {
            return '/assets/img/doctors/avatars/dr-female.png';
        }
        return null;
    }
}
