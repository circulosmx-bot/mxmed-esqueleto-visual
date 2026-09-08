<?php
declare(strict_types=1);
require_once __DIR__ . '/../services/PublicProfilePortrait.php';
use Profiles\Services\PublicProfilePortrait;

$count = 0;
$check = static function (array $identity, string $plan, ?string $expected) use (&$count): void {
    if (PublicProfilePortrait::resolve($identity) !== $expected) {
        throw new RuntimeException('Portrait selection failed: ' . json_encode([$identity, $plan]));
    }
    $count++;
};
foreach (['male', 'masculine', 'hombre', 'masculino', 'M'] as $gender) {
    $check(['gender' => $gender], 'free', '/assets/img/doctors/avatars/dr-male.png');
}
foreach (['female', 'feminine', 'mujer', 'femenino', 'F'] as $gender) {
    $check(['gender' => $gender], 'free', '/assets/img/doctors/avatars/dr-female.png');
}
$check(['gender_label' => ' Femenino '], 'free', '/assets/img/doctors/avatars/dr-female.png');
$check(['gender' => 'male', 'gender_label' => 'Femenino'], 'free', '/assets/img/doctors/avatars/dr-male.png');
foreach ([null, '', 'unknown', 'otro', 'ambiguous'] as $gender) {
    $check(['gender' => $gender, 'display_name' => 'Dra. María', 'prefix' => 'Dra.'], 'free', null);
}
$check(['gender' => 'unknown', 'gender_label' => 'Femenino'], 'free', null);
foreach (['male', 'female'] as $gender) {
    foreach (['free', 'basic', 'standard', 'optimal', 'professional'] as $plan) {
        $check(['gender' => $gender, 'photo_url' => '/uploaded/portrait.jpg'], $plan, '/uploaded/portrait.jpg');
        $check(['gender' => $gender], $plan, '/assets/img/doctors/avatars/dr-' . $gender . '.png');
        $check(['gender' => 'unknown'], $plan, null);
    }
}
$check(['gender' => 'female', 'photo_url' => 'https://invalid.example/missing.jpg'], 'free', 'https://invalid.example/missing.jpg');
$check(['avatar_url' => '/system/placeholder.png'], 'free', null);
echo "PublicProfilePortraitTest PASS ($count cases; no database writes)\n";
