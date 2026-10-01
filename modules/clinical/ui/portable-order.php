<?php
declare(strict_types=1);

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../../api/_lib/clinical_portable_order.php';

$uuid = is_string($_GET['uuid'] ?? null) ? trim($_GET['uuid']) : '';
$doctorId = is_string($_GET['doctor_id'] ?? null) ? trim($_GET['doctor_id']) : '';
$error = 'No se pudo abrir esta orden para impresión.';
$order = null;
$scope = clinical_portable_doctor_context();
header('Cache-Control: private, no-store, max-age=0');
if ($scope === null) {
    $error = 'Inicia sesión para consultar esta orden.';
} elseif ($doctorId !== '' && preg_match('/^[A-Za-z0-9._:-]{1,64}$/D', $doctorId) === 1
    && hash_equals($scope['doctor_id'], $doctorId)) {
    try {
        $order = clinical_portable_order_read(mxmed_pdo(), $uuid, $doctorId, $scope['user_id']);
    } catch (ClinicalPortableOrderException $exception) {
        $error = $exception->reason;
    } catch (Throwable) {
        $error = 'No se pudo preparar la orden para impresión.';
    }
}
if ($order === null) http_response_code(403);
define('MXMED_PORTABLE_ORDER_TEMPLATE_ALLOWED', true);
require __DIR__ . '/portable-order-template.php';
