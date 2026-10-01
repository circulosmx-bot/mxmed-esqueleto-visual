<?php
declare(strict_types=1);

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../../api/_lib/clinical_portable_order.php';
require_once __DIR__ . '/../../../api/_lib/clinical_portable_order_pdf.php';

$scope = clinical_portable_doctor_context();
$uuid = is_string($_GET['uuid'] ?? null) ? trim($_GET['uuid']) : '';
$doctorId = is_string($_GET['doctor_id'] ?? null) ? trim($_GET['doctor_id']) : '';
if ($scope === null || $doctorId === '' || preg_match('/^[A-Za-z0-9._:-]{1,64}$/D', $doctorId) !== 1
    || !hash_equals($scope['doctor_id'], $doctorId)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Esta orden no está disponible para descarga.';
    exit;
}

try {
    $order = clinical_portable_order_read(mxmed_pdo(), $uuid, $doctorId, $scope['user_id']);
    $filename = clinical_portable_pdf_filename($order);
    $pdf = clinical_portable_pdf_generate($order);
} catch (ClinicalPortableOrderException $error) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Esta orden no está disponible para descarga.';
    exit;
} catch (Throwable $error) {
    error_log('PORTABLE_PDF_RENDER_FAILED');
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo preparar el PDF. Intenta de nuevo.';
    exit;
}

http_response_code(200);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
