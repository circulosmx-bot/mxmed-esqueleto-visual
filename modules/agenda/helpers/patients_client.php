<?php
declare(strict_types=1);

use Patients\Controllers\CreatePatientController;

require_once __DIR__ . '/../../patients/controllers/CreatePatientController.php';

function agenda_patients_create(array $patientPayload): array
{
    $controller = new CreatePatientController();
    return $controller->handle($patientPayload);
}
