<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/PublicBookingDoctorReference.php';

use Profiles\Services\PublicBookingDoctorReference;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$assert(PublicBookingDoctorReference::question([
    'display_name' => 'Dra. Leticia Muñoz Romo', 'gender_label' => 'Femenino',
]) === '¿Es su primera consulta con la Dra. Muñoz?', 'female reference');
$assert(PublicBookingDoctorReference::question([
    'display_name' => 'Dr. Juan Pérez López', 'gender_label' => 'M',
]) === '¿Es su primera consulta con el Dr. Pérez?', 'male reference');
$assert(PublicBookingDoctorReference::question([
    'display_name' => 'Alex Rivera Soto', 'gender_label' => 'No especifica',
]) === '¿Es su primera consulta con este especialista?', 'unsupported gender fallback');
$assert(PublicBookingDoctorReference::question([
    'display_name' => 'Dra. Nombre Visible Incorrecto', 'first_surname' => 'Autoritativo', 'gender_label' => 'female',
]) === '¿Es su primera consulta con la Dra. Autoritativo?', 'structured surname takes precedence');

echo "PublicBookingDoctorReferenceTest PASS\n";
