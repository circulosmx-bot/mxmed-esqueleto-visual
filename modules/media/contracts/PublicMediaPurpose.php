<?php
declare(strict_types=1);

namespace Media\Contracts;

final class PublicMediaPurpose
{
    public const PHYSICIAN_PERSONAL_LOGO = 'PHYSICIAN_PERSONAL_LOGO';
    public const CONSULTORIO_GROUP_LOGO = 'CONSULTORIO_GROUP_LOGO';
    public const DOCTOR_GALLERY = 'DOCTOR_GALLERY';

    public const DOCTOR_PROFILE_PHOTO = 'DOCTOR_PROFILE_PHOTO';

    public static function all(): array
    {
        return [
            self::PHYSICIAN_PERSONAL_LOGO,
            self::CONSULTORIO_GROUP_LOGO,
            self::DOCTOR_GALLERY,
            self::DOCTOR_PROFILE_PHOTO,
        ];
    }

    public static function assertAllowed(string $purpose): string
    {
        if (!in_array($purpose, self::all(), true)) {
            throw new \InvalidArgumentException('unsupported_public_media_purpose');
        }
        return $purpose;
    }
}
