<?php
// modules/clinical/ui/viewer.php

function get_api_base(): string
{
    $env = trim((string)getenv('CLINICAL_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $env = trim((string)getenv('MXMED_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $env = trim((string)getenv('MXMED_TIMELINE_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
    $proto = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $proto = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = (string)$_SERVER['HTTP_X_FORWARDED_PROTO'];
    }

    $hostOnly = preg_replace('/:\d+$/', '', $host) ?: '127.0.0.1';
    return $proto . '://' . $hostOnly . ':8091';
}

function normalize_clinical_api_base(string $base): string
{
    $normalized = rtrim(trim($base), '/');
    if ($normalized === '') {
        return '';
    }
    $suffix = '/api/clinical/index.php';
    if (substr($normalized, -strlen($suffix)) === $suffix) {
        $normalized = rtrim(substr($normalized, 0, -strlen($suffix)), '/');
    }
    $parts = parse_url($normalized);
    if (is_array($parts)) {
        $host = strtolower((string)($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        $requestHostRaw = (string)($_SERVER['HTTP_HOST'] ?? '');
        $requestHost = strtolower((string)(parse_url('http://' . $requestHostRaw, PHP_URL_HOST) ?? ''));
        $requestPort = (int)(parse_url('http://' . $requestHostRaw, PHP_URL_PORT) ?? 0);
        $requestIsLoopback = in_array($requestHost, ['127.0.0.1', 'localhost'], true);
        $targetIsLoopback = in_array($host, ['127.0.0.1', 'localhost'], true);
        if ($requestIsLoopback && $requestPort === 8092 && $targetIsLoopback) {
            $scheme = (string)($parts['scheme'] ?? 'http');
            $path = (string)($parts['path'] ?? '');
            $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
            $fragment = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';
            $normalized = $scheme . '://127.0.0.1:8091' . $path . $query . $fragment;
        }
    }
    return $normalized;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (!function_exists('clinical_doc_format_date')) {
    function clinical_doc_format_date(string $value, bool $includeTime = false): string
    {
        $safe = trim($value);
        if ($safe === '') {
            return '';
        }
        $ts = strtotime($safe);
        if ($ts === false) {
            return $safe;
        }
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
        $month = (string)($months[(int)date('n', $ts)] ?? date('m', $ts));
        $datePart = date('d', $ts) . '-' . $month . '-' . date('Y', $ts);
        // Viewer standard: always show date only (dd-Mes-aaaa), no time.
        return $datePart;
    }
}

if (!function_exists('clinical_doc_header_mode')) {
    function clinical_doc_header_mode(string $mode): array
    {
        $normalized = strtolower(trim($mode));
        if (!in_array($normalized, ['branded', 'standard', 'legal'], true)) {
            $normalized = 'standard';
        }
        return [
            'mode' => $normalized,
            'allow_logo' => ($normalized === 'branded'),
            'compact' => ($normalized === 'legal'),
            'class_name' => 'clinical-doc-head--' . $normalized,
        ];
    }
}

function validate_return_to(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if ($value[0] === '/') {
        return (strpos($value, '//') === 0) ? null : $value;
    }

    if (!preg_match('/^https?:\/\//i', $value)) {
        return null;
    }

    $parts = parse_url($value);
    if (!is_array($parts)) {
        return null;
    }

    $urlPath = (string)($parts['path'] ?? '');
    if ($urlPath !== '' && strpos($urlPath, '/modules/clinical/ui/') === 0) {
        return $value;
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $port = isset($parts['port']) ? (int)$parts['port'] : null;

    $currentHostRaw = (string)($_SERVER['HTTP_HOST'] ?? '');
    $currentHostParts = parse_url('http://' . $currentHostRaw);
    $currentHost = strtolower((string)($currentHostParts['host'] ?? ''));
    $currentPort = isset($currentHostParts['port']) ? (int)$currentHostParts['port'] : null;
    $currentScheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';

    if ($host === '' || $currentHost === '') {
        return null;
    }

    if ($host !== $currentHost || $scheme !== $currentScheme) {
        return null;
    }

    if (($port ?? ($scheme === 'https' ? 443 : 80)) !== ($currentPort ?? ($currentScheme === 'https' ? 443 : 80))) {
        return null;
    }

    return $value;
}

function normalize_return_to(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if ($value[0] === '/') {
        $parts = parse_url($value);
        if (!is_array($parts)) {
            return $value;
        }
        $path = (string)($parts['path'] ?? '/');
        $query = [];
        if (isset($parts['query'])) {
            parse_str((string)$parts['query'], $query);
            if (is_array($query)) {
                unset($query['doc_uuid'], $query['uuid']);
            } else {
                $query = [];
            }
        }
        $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return $path . ($qs !== '' ? ('?' . $qs) : '');
    }

    if (!preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }

    $url = parse_url($value);
    if (!is_array($url)) {
        return $value;
    }
    $query = [];
    if (isset($url['query'])) {
        parse_str((string)$url['query'], $query);
        if (is_array($query)) {
            unset($query['doc_uuid'], $query['uuid']);
        } else {
            $query = [];
        }
    }
    $scheme = (string)($url['scheme'] ?? 'http');
    $host = (string)($url['host'] ?? '');
    $port = isset($url['port']) ? (':' . (int)$url['port']) : '';
    $path = (string)($url['path'] ?? '/');
    $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    return $scheme . '://' . $host . $port . $path . ($qs !== '' ? ('?' . $qs) : '');
}

function render_embed_css(bool $embed): void
{
    if (!$embed) {
        return;
    }

    echo '<link rel="stylesheet" href="/assets/css/style.css">' . "\n";
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">' . "\n";
}

function http_get_json(string $url, int $timeoutSeconds = 8): array
{
    $fetchOnce = static function (string $requestUrl, int $timeout) : array {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($requestUrl, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $line) {
            if (is_string($line) && preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})/i', trim($line), $m)) {
                $status = (int)$m[1];
                break;
            }
        }
        return ['raw' => $raw, 'status' => $status];
    };

    $attempts = [$url];
    $parts = parse_url($url);
    if (is_array($parts)) {
        $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
        $host = strtolower((string)($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        $path = (string)($parts['path'] ?? '');
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
        $fragment = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            $altHost = $host === 'localhost' ? '127.0.0.1' : 'localhost';
            $altPort = $port !== null ? (':' . $port) : '';
            $attempts[] = $scheme . '://' . $altHost . $altPort . $path . $query . $fragment;
            if ($scheme === 'https') {
                $attempts[] = 'http://' . $host . $altPort . $path . $query . $fragment;
                $attempts[] = 'http://' . $altHost . $altPort . $path . $query . $fragment;
            }
        }
    }
    $attempts = array_values(array_unique(array_filter(array_map('strval', $attempts))));

    $raw = false;
    $status = 0;
    foreach ($attempts as $requestUrl) {
        $result = $fetchOnce($requestUrl, $timeoutSeconds);
        $raw = $result['raw'];
        $status = (int)$result['status'];
        if ($raw !== false) {
            break;
        }
        if ($status !== 0) {
            break;
        }
    }
    if ($raw === false) {
        return ['ok' => false, 'error' => 'fetch_failed', 'message' => 'No se pudo consultar el documento. status=' . $status];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'invalid_json', 'message' => 'Respuesta inválida del endpoint de documentos. status=' . $status];
    }

    if ($status !== 0 && $status !== 200 && (($decoded['ok'] ?? false) !== true)) {
        $msg = trim((string)($decoded['message'] ?? ''));
        return [
            'ok' => false,
            'error' => (string)($decoded['error'] ?? 'http_error'),
            'message' => ($msg !== '' ? $msg : 'Error consultando documento.') . ' status=' . $status,
        ];
    }

    return $decoded;
}

function build_viewer_self_href(array $params): string
{
    $query = [];
    foreach ($params as $key => $value) {
        if ($value === null) {
            continue;
        }
        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            continue;
        }
        $query[$key] = $stringValue;
    }
    return '/modules/clinical/ui/viewer.php' . ($query !== [] ? ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)) : '');
}

function first_non_empty_string(array $sources, array $keys): string
{
    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }
        foreach ($keys as $key) {
            $value = trim((string)($source[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
    }
    return '';
}

function is_allowed_external_url(string $url, array $allowedHosts, string $currentHost): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '') {
        return false;
    }

    if ($currentHost !== '' && $host === $currentHost) {
        return true;
    }

    return in_array($host, $allowedHosts, true);
}

function is_relative_media_url(string $url): bool
{
    $url = trim($url);
    return $url !== '' && $url[0] === '/';
}

function is_same_origin_media_url(string $url, string $currentHost): bool
{
    $url = trim($url);
    if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
        return false;
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return $host !== '' && $currentHost !== '' && $host === strtolower($currentHost);
}

function sort_bundle_documents_for_viewer(array $items): array
{
    $decorated = [];
    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }
        $item['_idx'] = (int)$idx;
        $decorated[] = $item;
    }

    usort($decorated, static function ($left, $right): int {
        $leftItem = is_array($left) ? $left : [];
        $rightItem = is_array($right) ? $right : [];

        $leftDt = trim((string)($leftItem['event_datetime'] ?? ''));
        $rightDt = trim((string)($rightItem['event_datetime'] ?? ''));
        if ($leftDt !== $rightDt) {
            return strcmp($leftDt, $rightDt);
        }

        $leftId = (int)($leftItem['id'] ?? 0);
        $rightId = (int)($rightItem['id'] ?? 0);
        if ($leftId > 0 || $rightId > 0) {
            if ($leftId !== $rightId) {
                return $leftId <=> $rightId;
            }
        }

        $leftIdx = (int)($leftItem['_idx'] ?? 0);
        $rightIdx = (int)($rightItem['_idx'] ?? 0);
        if ($leftIdx !== $rightIdx) {
            return $leftIdx <=> $rightIdx;
        }

        return strcmp(
            trim((string)($leftItem['document_uuid'] ?? '')),
            trim((string)($rightItem['document_uuid'] ?? ''))
        );
    });

    foreach ($decorated as &$item) {
        unset($item['_idx']);
    }
    unset($item);

    return $decorated;
}

$uuid = trim((string)($_GET['uuid'] ?? ''));
if ($uuid === '') {
    $uuid = trim((string)($_GET['doc_uuid'] ?? ''));
}
$bundleId = trim((string)($_GET['bundle_id'] ?? ''));
$patientId = trim((string)($_GET['patient_id'] ?? ''));
$returnTo = validate_return_to((string)($_GET['return_to'] ?? ''));
$returnToClean = $returnTo !== null ? normalize_return_to($returnTo) : '';
$backHref = $returnToClean !== '' ? $returnToClean : 'javascript:history.back()';
$embedQueryFlag = trim((string)($_GET['embed'] ?? '')) === '1' ? '1' : '';
$debugView = trim((string)($_GET['debug'] ?? '')) === '1';
$errorMessage = '';
$document = null;
$bundleData = null;
$bundleItems = [];
$bundleClinicalBlock = null;
$bundleTitle = '';
$bundleNote = '';
$selectedBundleIndex = 0;
$apiBase = normalize_clinical_api_base((string)getenv('CLINICAL_API_BASE'));
if ($apiBase === '') {
    $apiBase = normalize_clinical_api_base(get_api_base());
}
$apiIndexBase = ($apiBase !== '') ? ($apiBase . '/api/clinical/index.php') : '';

if ($bundleId !== '' && $apiIndexBase !== '') {
    $bundleUrl = $apiIndexBase . '/bundles/' . rawurlencode($bundleId) . '/documents';
    if ($patientId !== '') {
        $bundleUrl .= '?patient_id=' . rawurlencode($patientId);
    }
    $bundleDecoded = http_get_json($bundleUrl, 8);
    if (($bundleDecoded['ok'] ?? false) !== true) {
        $errorMessage = (string)($bundleDecoded['message'] ?? 'Error consultando bundle.');
    } else {
        $bundlePayload = is_array($bundleDecoded['data'] ?? null) ? $bundleDecoded['data'] : [];
        $bundleItemsRaw = is_array($bundlePayload['items'] ?? null) ? $bundlePayload['items'] : [];
        $bundleItems = [];
        foreach ($bundleItemsRaw as $bundleItemRaw) {
            if (!is_array($bundleItemRaw)) {
                continue;
            }
            if (trim((string)($bundleItemRaw['document_type'] ?? '')) === 'bundle_clinical') {
                if ($bundleClinicalBlock === null) {
                    $bundleClinicalCandidate = is_array($bundleItemRaw['bundle_clinical'] ?? null) ? $bundleItemRaw['bundle_clinical'] : [];
                    $bundleClinicalBlock = [
                        'summary' => trim((string)($bundleClinicalCandidate['summary'] ?? ($bundleItemRaw['summary'] ?? ''))),
                        'interpretation' => trim((string)($bundleClinicalCandidate['interpretation'] ?? '')),
                        'observations' => trim((string)($bundleClinicalCandidate['observations'] ?? '')),
                    ];
                }
                continue;
            }
            $bundleItems[] = $bundleItemRaw;
        }
        $bundleItems = sort_bundle_documents_for_viewer($bundleItems);
        $bundleTitle = trim((string)($bundlePayload['bundle_title'] ?? ''));
        $bundleNote = trim((string)($bundlePayload['bundle_note'] ?? ''));
        $bundleData = $bundlePayload;
        if ($bundleItems === [] && $bundleClinicalBlock === null) {
            $errorMessage = 'Bundle sin documentos.';
        } elseif ($bundleItems !== []) {
            $selectedBundleIndex = 0;
            if ($uuid !== '') {
                foreach ($bundleItems as $idx => $bundleItem) {
                    if (!is_array($bundleItem)) {
                        continue;
                    }
                    if (trim((string)($bundleItem['document_uuid'] ?? '')) === $uuid) {
                        $selectedBundleIndex = $idx;
                        break;
                    }
                }
            }
            $selectedItem = is_array($bundleItems[$selectedBundleIndex] ?? null) ? $bundleItems[$selectedBundleIndex] : [];
            $uuid = trim((string)($selectedItem['document_uuid'] ?? $uuid));
            if ($patientId === '') {
                $patientId = trim((string)($bundlePayload['patient_id'] ?? ''));
            }
            if ($bundleTitle === '') {
                $bundleTitle = trim((string)($selectedItem['media_bundle_title'] ?? ''));
            }
            if ($bundleNote === '') {
                $bundleNote = trim((string)($selectedItem['media_bundle_note'] ?? ''));
            }
        }
    }
}

if ($uuid !== '') {
    if ($apiIndexBase === '') {
        $errorMessage = 'CLINICAL_API_BASE no configurado y get_api_base() vacío.';
    } else {
        $url = $apiIndexBase . '/documents/' . rawurlencode($uuid);
        $decoded = http_get_json($url, 8);

        if (($decoded['ok'] ?? false) !== true) {
            $errorMessage = (string)($decoded['message'] ?? 'Error consultando documento.');
        } else {
            $doc = $decoded['data']['document'] ?? null;
            $document = is_array($doc) ? $doc : null;
            if (!$document) {
                $errorMessage = 'Documento no disponible.';
            }
        }
    }
}

$docType = $document ? (string)($document['document_type'] ?? '-') : '-';
$title = $document ? (string)($document['title'] ?? '-') : '-';
$displayTitle = $bundleTitle !== '' ? $bundleTitle : $title;
$date = $document ? (string)($document['ui']['event_datetime'] ?? ($document['timestamps']['created_at'] ?? '-')) : '-';
$dateFormatted = clinical_doc_format_date($date, false);
$summary = $document ? (string)($document['content']['summary'] ?? '-') : '-';
$content = $document && is_array($document['content'] ?? null) ? $document['content'] : [];
$payload = $document && is_array($content['payload'] ?? null) ? $content['payload'] : [];
$mode = strtolower(trim((string)($_GET['mode'] ?? '')));
$isFullscreenMode = ($mode === 'fullscreen');
$payloadJson = $payload ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
$renderedText = $document ? (string)($content['rendered_text'] ?? '') : '';
$docTypeNorm = strtolower(trim($docType));
$isConsentDoc = ($docTypeNorm === 'consentimiento_informado');
$isInformeDoc = ($docTypeNorm === 'informe_medico');
$isPrescriptionDoc = in_array($docTypeNorm, ['prescription', 'rx'], true);
$prescriptionContext = $isPrescriptionDoc && is_array($payload['context'] ?? null) ? $payload['context'] : [];
$prescriptionLegacy = $isPrescriptionDoc && is_array($payload['prescription'] ?? null) ? $payload['prescription'] : [];
$prescriptionItems = [];
if ($isPrescriptionDoc) {
    if (is_array($payload['medications'] ?? null)) {
        $prescriptionItems = $payload['medications'];
    } elseif (is_array($prescriptionLegacy['items'] ?? null)) {
        $prescriptionItems = $prescriptionLegacy['items'];
    }
}
$prescriptionAnalysis = $isPrescriptionDoc && is_array($payload['analysis'] ?? null) ? $payload['analysis'] : [];
$prescriptionPatientName = trim((string)($prescriptionContext['patient_name'] ?? ''));
$prescriptionDoctorName = trim((string)($prescriptionContext['doctor_name'] ?? ''));
$prescriptionDate = trim((string)($prescriptionContext['date'] ?? ''));
$prescriptionDateFormatted = clinical_doc_format_date($prescriptionDate, false);
$prescriptionEncounter = trim((string)($prescriptionContext['encounter_id'] ?? ''));
$prescriptionAllergies = is_array($prescriptionContext['allergies'] ?? null) ? $prescriptionContext['allergies'] : [];
$prescriptionCurrentMeds = is_array($prescriptionContext['current_medications'] ?? null) ? $prescriptionContext['current_medications'] : [];
$prescriptionAnalysisObservations = [];
if (is_array($prescriptionAnalysis['results']['observations'] ?? null)) {
    $prescriptionAnalysisObservations = $prescriptionAnalysis['results']['observations'];
}
$prescriptionAnalysisStatus = trim((string)($prescriptionAnalysis['status'] ?? 'idle'));
$consentBlock = is_array($payload['consent'] ?? null) ? $payload['consent'] : [];
$consentPatientSnapshot = is_array($payload['patient_snapshot'] ?? null) ? $payload['patient_snapshot'] : [];
$consentActorSnapshot = is_array($payload['actor_snapshot'] ?? null) ? $payload['actor_snapshot'] : [];
$consentTemplateSnapshot = is_array($payload['template_snapshot'] ?? null) ? $payload['template_snapshot'] : [];
$consentLegal = is_array($payload['legal'] ?? null) ? $payload['legal'] : [];
$consentSignatures = is_array($payload['signatures'] ?? null) ? $payload['signatures'] : [];
$consentObservations = trim((string)($payload['observations'] ?? ''));
$consentLegalDetails = is_array($payload['consent_legal'] ?? null) ? $payload['consent_legal'] : [];
$consentSigner = is_array($payload['firmante'] ?? null) ? $payload['firmante'] : [];
$consentWitnesses = is_array($payload['testigos'] ?? null) ? $payload['testigos'] : [];
$consentRenderedText = trim((string)($payload['rendered_text'] ?? ($payload['text'] ?? $renderedText)));
$consentFrozenSnapshot = is_array($payload['frozen_snapshot'] ?? null) ? $payload['frozen_snapshot'] : [];
$consentFrozenHtml = trim((string)($consentFrozenSnapshot['html'] ?? ''));
$consentStatus = trim((string)($payload['status'] ?? ($consentBlock['status'] ?? 'draft')));
$consentPrintableHref = ($uuid !== '') ? ('/modules/clinical/ui/document.php?uuid=' . rawurlencode($uuid) . ($embedQueryFlag !== '' ? '&embed=1' : '')) : '';
$consentPatientSignature = is_array($consentSignatures['patient'] ?? null) ? $consentSignatures['patient'] : [];
$consentPatientSignatureImage = trim((string)($consentPatientSignature['image_data'] ?? ''));
$consentPatientSignatureSignerName = trim((string)($consentPatientSignature['signer_name'] ?? ''));
$consentPatientSignatureSignedAt = trim((string)($consentPatientSignature['signed_at'] ?? ''));
$consentPatientSignatureSource = trim((string)($consentPatientSignature['source'] ?? ''));
$consentIdentityAttachments = [];
if (is_array($payload['signer_identity_attachments'] ?? null)) {
    $consentIdentityAttachments = $payload['signer_identity_attachments'];
} elseif (is_array($payload['attachments']['signer_identity'] ?? null)) {
    $consentIdentityAttachments = $payload['attachments']['signer_identity'];
}

$consentBoolLabel = static function ($value): string {
    return $value ? 'Sí' : 'No';
};
$docContext = is_array($content['context'] ?? null) ? $content['context'] : [];
$payloadContext = is_array($payload['context'] ?? null) ? $payload['context'] : [];
$payloadMeta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
$documentUi = is_array($document['ui'] ?? null) ? $document['ui'] : [];
$informeReport = is_array($payload['report'] ?? null) ? $payload['report'] : [];
$informeContent = is_array($payload['content'] ?? null) ? $payload['content'] : [];
$informeBranding = is_array($payload['branding'] ?? null) ? $payload['branding'] : [];
$informePatientSnapshot = is_array($payload['patient_snapshot'] ?? null) ? $payload['patient_snapshot'] : [];
$informeActorSnapshot = is_array($payload['actor_snapshot'] ?? null) ? $payload['actor_snapshot'] : [];
$informeSignatures = is_array($payload['signatures'] ?? null) ? $payload['signatures'] : [];
$informeDoctorSignature = is_array($informeSignatures['doctor'] ?? null) ? $informeSignatures['doctor'] : [];
$informeDoctorSignatureImage = trim((string)($informeDoctorSignature['image_data'] ?? ''));
$informeDoctorSignatureSignerName = trim((string)($informeDoctorSignature['signer_name'] ?? ''));
$informeDoctorSignatureSignedAt = trim((string)($informeDoctorSignature['signed_at'] ?? ''));
$informeDoctorSignatureSource = trim((string)($informeDoctorSignature['source'] ?? ''));
$informePatientNameContext = first_non_empty_string(
    [$informePatientSnapshot, $payloadContext, $docContext, $documentUi, $payloadMeta],
    ['full_name', 'patient_name', 'name', 'nombre_completo', 'display_name']
);
$informePatientAgeContext = first_non_empty_string(
    [$informePatientSnapshot, $payloadContext, $docContext, $documentUi],
    ['age', 'edad', 'patient_age']
);
$informePatientSexContext = first_non_empty_string(
    [$informePatientSnapshot, $payloadContext, $docContext, $documentUi],
    ['sex', 'sexo', 'patient_sex']
);
$informeDoctorNameContext = first_non_empty_string(
    [$informeActorSnapshot, $payloadContext, $docContext, $documentUi, $payloadMeta],
    ['full_name', 'doctor_name', 'physician_name', 'name', 'doctor_full_name']
);
$informeDoctorSpecialtyContext = first_non_empty_string(
    [$informeActorSnapshot, $payloadContext, $docContext, $documentUi, $payloadMeta],
    ['specialty', 'specialty_name', 'especialidad', 'doctor_specialty']
);
$informeDoctorLicenseContext = first_non_empty_string(
    [$informeActorSnapshot, $payloadContext, $docContext, $documentUi, $payloadMeta],
    ['license', 'doctor_license', 'cedula_profesional', 'cedula']
);
$informeDoctorSpecialtyLicenseContext = first_non_empty_string(
    [$informeActorSnapshot, $payloadContext, $docContext, $documentUi, $payloadMeta],
    ['specialty_license', 'doctor_specialty_license', 'cedula_especialidad']
);
$informeDoctorPlaceContext = first_non_empty_string(
    [$informeActorSnapshot, $payloadContext, $docContext, $documentUi, $payloadMeta],
    ['place', 'location', 'city', 'doctor_place']
);
$informeDoctorInstitutionContext = first_non_empty_string(
    [$informeActorSnapshot, $payloadContext, $docContext, $documentUi, $payloadMeta],
    ['institution', 'institution_name', 'doctor_institution']
);
$informeDoctorFacilityContext = first_non_empty_string(
    [$informeActorSnapshot, $payloadContext, $docContext, $documentUi, $payloadMeta],
    ['facility', 'facility_name', 'clinic', 'clinic_name', 'consultorio', 'doctor_facility']
);
$informePrintableHref = ($uuid !== '') ? ('/modules/clinical/ui/document.php?uuid=' . rawurlencode($uuid) . ($embedQueryFlag !== '' ? '&embed=1' : '')) : '';

$fileMeta = is_array($payload['file'] ?? null) ? $payload['file'] : [];
$optimizedMeta = is_array($fileMeta['optimized'] ?? null) ? $fileMeta['optimized'] : [];
$thumbMeta = is_array($fileMeta['thumb'] ?? null) ? $fileMeta['thumb'] : [];
$originalMeta = is_array($fileMeta['original'] ?? null) ? $fileMeta['original'] : [];
$renderMode = strtolower(trim((string)($fileMeta['render_mode'] ?? '')));
$mimeType = strtolower(first_non_empty_string([$optimizedMeta, $fileMeta, $document, $content, $payload], ['mime', 'mime_type', 'content_type', 'type', 'media_type']));
$mediaSrc = first_non_empty_string([$optimizedMeta, $originalMeta, $fileMeta, $payload, $content], ['path', 'url', 'src', 'file_url', 'pdf_url', 'image_url']);
$thumbSrc = first_non_empty_string([$thumbMeta], ['path', 'url', 'src']);
$htmlInline = trim((string)($payload['html'] ?? ''));

$currentHost = strtolower((string)parse_url(get_api_base(), PHP_URL_HOST));
$externalIframeAllowlist = []; // viewer v0.1: dominios explícitos para iframe externo.
$externalAllowed = $mediaSrc !== '' ? is_allowed_external_url($mediaSrc, $externalIframeAllowlist, $currentHost) : false;
$isRelativeMediaSrc = is_relative_media_url($mediaSrc);
$isSameOriginMediaSrc = is_same_origin_media_url($mediaSrc, $currentHost);
$externalBlockedMessage = '';

$detectedMode = 'json';
if ($renderMode === 'image') {
    $detectedMode = 'image';
} elseif ($htmlInline !== '') {
    $detectedMode = 'html_inline';
} elseif ($mediaSrc !== '') {
    $srcLower = strtolower($mediaSrc);
    if (strpos($mimeType, 'image/') === 0 || preg_match('/\.(png|jpe?g|gif|webp|svg)(\?.*)?$/i', $srcLower)) {
        $detectedMode = 'image';
    } elseif ($mimeType === 'application/pdf' || preg_match('/\.pdf(\?.*)?$/i', $srcLower)) {
        $detectedMode = 'pdf';
    } elseif ($mimeType === 'text/html' || preg_match('/\.html?(\?.*)?$/i', $srcLower)) {
        $detectedMode = 'html_external';
    }
}

if (($detectedMode === 'pdf' || $detectedMode === 'html_external') && $mediaSrc !== '' && !$externalAllowed && !$isRelativeMediaSrc && !$isSameOriginMediaSrc) {
    $externalBlockedMessage = 'La URL externa no está permitida por la allowlist del viewer.';
    $detectedMode = 'json';
}

$isPdf = ($renderMode === 'pdf' || $mimeType === 'application/pdf' || $detectedMode === 'pdf');
$openInNewHref = $mediaSrc !== '' && $externalAllowed ? $mediaSrc : ((string)($_SERVER['REQUEST_URI'] ?? '/modules/clinical/ui/viewer.php'));
$showDownloadAction = ($mediaSrc !== '' && ($isRelativeMediaSrc || $isSameOriginMediaSrc || $externalAllowed));
$downloadIsRelative = $isRelativeMediaSrc;
$bundlePrevHref = '';
$bundleNextHref = '';
if ($bundleItems !== []) {
    $prevIndex = ($selectedBundleIndex > 0) ? ($selectedBundleIndex - 1) : -1;
    $nextIndex = ($selectedBundleIndex < (count($bundleItems) - 1)) ? ($selectedBundleIndex + 1) : -1;
    if ($prevIndex >= 0 && is_array($bundleItems[$prevIndex] ?? null)) {
        $bundlePrevHref = build_viewer_self_href([
            'bundle_id' => $bundleId,
            'patient_id' => $patientId,
            'uuid' => (string)($bundleItems[$prevIndex]['document_uuid'] ?? ''),
            'return_to' => $returnToClean,
            'embed' => $embedQueryFlag,
        ]);
    }
    if ($nextIndex >= 0 && is_array($bundleItems[$nextIndex] ?? null)) {
        $bundleNextHref = build_viewer_self_href([
            'bundle_id' => $bundleId,
            'patient_id' => $patientId,
            'uuid' => (string)($bundleItems[$nextIndex]['document_uuid'] ?? ''),
            'return_to' => $returnToClean,
            'embed' => $embedQueryFlag,
        ]);
    }
}

require_once __DIR__ . '/../../_partials/clinical_embed.php';
$embed = is_embed_request();

if (!$embed) {
    $pageTitle = 'Document Viewer';
    require_once __DIR__ . '/../../_partials/mm_shell_top.php';
} else {
    render_embed_css($embed);
    clinical_embed_start();
}
?>
<style>
  html,body{height:100%;}
  .mm-viewer-shell{height:100vh;display:flex;flex-direction:column;}
  .mm-viewer-actions{flex:0 0 auto;}
  .mm-viewer-frame{flex:1 1 auto;min-height:0;}
  .mm-viewer-frame iframe{width:100%;height:100%;border:0;}
  .clinical-viewer .viewer-sticky-head{
    position: sticky;
    top: 0;
    z-index: 3;
    background: #fff;
    border-bottom: 1px solid rgba(0,0,0,.08);
    padding-bottom: .5rem;
    margin-bottom: .75rem;
  }
  .clinical-viewer.is-fullscreen{
    background: #111827;
    min-height: 100vh;
    padding: 0 !important;
  }
  .clinical-viewer.is-fullscreen .viewer-sticky-head{
    display: none;
  }
  .clinical-viewer.is-fullscreen .fullscreen-image-wrap{
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 1rem;
  }
  .clinical-viewer.is-fullscreen .fullscreen-image-wrap img{
    max-width: 100%;
    max-height: 96vh;
    object-fit: contain;
    border: 0;
  }
  .consent-doc-sheet{
    background:#fff;
    border:1px solid #d7eaf2;
    border-radius:14px;
    padding:16px;
    display:grid;
    gap:12px;
  }
  .consent-doc-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    border-bottom:1px solid #edf4f8;
    padding-bottom:8px;
  }
  .consent-doc-title{font-size:1rem;font-weight:800;color:#0a405f;line-height:1.3;}
  .consent-doc-meta{font-size:.84rem;color:#5d6b74;}
  .consent-doc-section-title{
    font-size:.8rem;
    font-weight:800;
    color:#0a405f;
    text-transform:uppercase;
    letter-spacing:.02em;
    margin-bottom:4px;
  }
  .consent-doc-text{font-size:.9rem;color:#273b47;white-space:pre-wrap;line-height:1.45;}
  .consent-doc-sign-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
  }
  .consent-doc-sign-item{
    border:1px solid #e8f0f5;
    border-radius:10px;
    padding:10px;
  }
  .consent-doc-sign-image{
    max-width:220px;
    max-height:90px;
    width:100%;
    object-fit:contain;
    border:1px dashed #d0dde6;
    border-radius:8px;
    background:#fff;
    margin-top:8px;
  }
  .consent-doc-sign-line{margin-top:28px;border-top:1px solid #9fb6c4;}
  .document-sheet-frame{
    background:#eef2f5;
    border-radius:14px;
    padding:16px;
  }
  .document-sheet{
    box-sizing:border-box;
    width:min(21.59cm, 100%);
    margin:0 auto;
    background:#fff;
    padding:2.5cm 2.2cm;
    box-shadow:0 10px 24px rgba(15, 23, 42, 0.08);
  }
  .informe-doc-sheet{
    display:grid;
    gap:8px;
  }
  .clinical-doc-head{
    display:flex;
    align-items:flex-start;
    gap:14px;
    border-bottom:none;
    padding-bottom:2px;
  }
  .clinical-doc-head-main{
    display:grid;
    gap:1px;
    min-width:0;
    flex:1 1 auto;
  }
  .clinical-doc-head-doctor{
    display:flex;
    align-items:center;
    gap:10px;
  }
  .clinical-doc-head-logo-slot{
    width:170px;
    height:86px;
    min-height:86px;
    border:1px dashed #cbd5e1;
    border-radius:10px;
    background:#f8fafc;
    display:flex;
    align-items:center;
    justify-content:center;
    flex:0 0 auto;
    overflow:hidden;
  }
  .clinical-doc-head-logo-slot img{
    width:auto;
    max-width:100%;
    height:auto;
    max-height:70px;
    object-fit:contain;
    background:#fff;
  }
  .clinical-doc-logo-fallback{display:none;}
  .clinical-doc-head-logo-slot.is-empty{
    color:#94a3b8;
    font-size:.66rem;
    font-weight:600;
    letter-spacing:.01em;
  }
  .clinical-doc-head-logo-slot.is-empty .clinical-doc-logo-fallback{display:block;}
  .clinical-doc-head--standard .clinical-doc-head-logo-slot.is-empty,
  .clinical-doc-head--legal .clinical-doc-head-logo-slot.is-empty{
    opacity:.65;
  }
  .clinical-doc-head--legal .clinical-doc-head-logo-slot{
    width:32px;
    height:32px;
    border-radius:6px;
  }
  .clinical-doc-doctor-name{font-size:1.45rem;font-weight:800;color:#0a5168;line-height:1.15;}
  .clinical-doc-doctor-specialty{font-size:.95rem;color:#2a4b5b;font-weight:600;}
  .clinical-doc-doctor-license{font-size:.84rem;color:#3f5564;}
  .clinical-doc-doctor-site{font-size:.84rem;color:#3f5564;}
  .clinical-doc-group-logo{
    margin-top:4px;
    display:flex;
    align-items:center;
    gap:8px;
    opacity:.85;
  }
  .clinical-doc-group-logo img{
    max-height:28px;
    width:auto;
    object-fit:contain;
    border:1px solid #d9e7ef;
    border-radius:6px;
    background:#fff;
    padding:2px 4px;
  }
  .clinical-doc-group-logo span{
    font-size:.78rem;
    color:#5d6b74;
    text-transform:uppercase;
    letter-spacing:.02em;
    font-weight:700;
  }
  .informe-doc-head{
    /* Alias class preserved for compatibility with existing layout selectors. */
    justify-content:center;
  }
  .informe-doc-head > .clinical-doc-head-main{
    flex:0 1 920px;
    width:100%;
    max-width:920px;
    margin:0 auto;
  }
  .informe-doc-head .clinical-doc-head-doctor{
    align-items:flex-start;
    gap:10px;
  }
  .informe-doc-head .clinical-doc-head-logo-slot{
    width:250px;
    height:104px;
    min-height:104px;
    border:none;
    border-radius:0;
    background:transparent;
    justify-content:flex-start;
    align-items:flex-start;
    overflow:visible;
    padding-top:2px;
  }
  .informe-doc-head .clinical-doc-head-logo-slot img{
    width:auto;
    max-width:100%;
    height:auto;
    max-height:92px;
    object-fit:contain;
    background:transparent;
  }
  .informe-doc-head .clinical-doc-head-logo-slot.is-empty{
    width:150px;
    height:74px;
    min-height:74px;
    border:1px dashed #cbd5e1;
    border-radius:10px;
    background:#f8fafc;
    justify-content:center;
    align-items:center;
    overflow:hidden;
  }
  .informe-doc-head .clinical-doc-head-logo-slot.is-empty .clinical-doc-logo-fallback{
    display:block;
  }
  .informe-doc-title{font-size:1.35rem;font-weight:900;color:#0a5168;line-height:1.06;letter-spacing:.012em;text-align:center;}
  .informe-doc-title-block{
    border-bottom:none;
    padding-top:2px;
    padding-bottom:2px;
    display:grid;
    gap:2px;
    margin-bottom:20px;
  }
  .informe-doc-date{
    font-size:.9rem;
    color:#203848;
    text-align:right;
    justify-self:end;
    white-space:nowrap;
  }
  .informe-doc-date strong{color:#0a5168;}
  .informe-doc-patient-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
  }
  .informe-doc-patient-head .informe-doc-patient-line{
    flex:1 1 auto;
    min-width:0;
  }
  .informe-doc-patient-head .informe-doc-date{
    flex:0 0 auto;
    margin-left:auto;
  }
  .informe-doc-patient-block{
    display:grid;
    gap:1px;
    border-bottom:none;
    padding-top:1px;
    padding-bottom:1px;
    margin-bottom:10px;
  }
  .informe-doc-patient-line{
    font-size:.9rem;
    color:#213645;
  }
  .informe-doc-patient-line strong{color:#0a5168;}
  .informe-doc-meta{font-size:.84rem;color:#5d6b74;}
  .informe-doc-section-title{
    font-size:.84rem;
    font-weight:800;
    color:#0a5168;
    text-transform:uppercase;
    letter-spacing:.03em;
    margin-bottom:2px;
  }
  .informe-doc-text{font-size:.84rem;color:#273b47;white-space:pre-wrap;line-height:1.35;}
  .informe-doc-body-section{
    border-bottom:none;
    padding-bottom:0;
    margin-bottom:6px;
  }
  .informe-doc-body-section:last-of-type{
    margin-bottom:0;
  }
  .informe-doc-sign{
    border-top:none;
    padding-top:6px;
    display:grid;
    justify-items:center;
    gap:3px;
    text-align:center;
  }
  .informe-doc-sign-image{
    max-width:240px;
    max-height:90px;
    width:100%;
    object-fit:contain;
    border:none;
    border-radius:0;
    background:transparent;
    margin-top:2px;
  }
  .informe-doc-sign-line{margin-top:8px;border-top:1px solid #9fb6c4;min-width:220px;}
  .informe-doc-sign-meta{
    font-size:.84rem;
    color:#243746;
    text-align:center;
  }
  .rx-view-sheet{
    background:#fff;
    border:1px solid #d7eaf2;
    border-radius:14px;
    padding:16px;
    display:grid;
    gap:12px;
  }
  .rx-view-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    border-bottom:1px solid #edf4f8;
    padding-bottom:8px;
  }
  .rx-view-title{font-size:1rem;font-weight:800;color:#0a405f;line-height:1.3;}
  .rx-view-meta{font-size:.84rem;color:#5d6b74;}
  .rx-view-section-title{
    font-size:.8rem;
    font-weight:800;
    color:#0a405f;
    text-transform:uppercase;
    letter-spacing:.02em;
    margin-bottom:4px;
  }
  .rx-view-text{font-size:.9rem;color:#273b47;line-height:1.45;white-space:pre-wrap;}
  .rx-view-list{
    margin:0;
    padding-left:1rem;
    display:grid;
    gap:8px;
  }
  .rx-view-item-title{font-weight:700;color:#12344d;}
  .rx-view-item-meta{font-size:.84rem;color:#546670;}
  .rx-view-item-note{font-size:.84rem;color:#273b47;}
  @media (max-width: 767.98px){
    .consent-doc-head{flex-direction:column;}
    .consent-doc-sign-grid{grid-template-columns:1fr;}
    .clinical-doc-head{flex-direction:column;}
    .clinical-doc-head-logo-slot{
      width:128px;
      height:64px;
      min-height:64px;
    }
    .clinical-doc-head-logo-slot img{
      max-height:52px;
    }
    .informe-doc-head .clinical-doc-head-logo-slot{
      width:180px;
      height:82px;
      min-height:82px;
      padding-top:0;
    }
    .informe-doc-head .clinical-doc-head-logo-slot img{
      max-height:76px;
    }
    .informe-doc-head .clinical-doc-head-logo-slot.is-empty{
      width:110px;
      height:52px;
      min-height:52px;
    }
    .informe-doc-patient-head{
      flex-wrap:wrap;
      gap:4px;
    }
    .informe-doc-patient-head .informe-doc-date{
      width:100%;
      text-align:left;
      margin-left:0;
    }
    .document-sheet-frame{
      padding:8px;
      border-radius:10px;
    }
    .document-sheet{
      width:100%;
      padding:1.1rem 1rem;
      box-shadow:none;
    }
    .rx-view-head{flex-direction:column;}
  }
  @page{
    size: Letter portrait;
    margin: 0;
  }
  @media print{
    html, body{
      height:auto;
      min-height:auto !important;
      overflow:visible !important;
      background:#fff !important;
      color:#000;
      -webkit-print-color-adjust:exact;
      print-color-adjust:exact;
    }
    /* Hide shell/navigation chrome explicitly; keep document container visible. */
    .header-top,
    .header-mid,
    .header-bottom,
    .mm-wrap{
      display:none !important;
      visibility:hidden !important;
    }
    .container,
    .container-fluid,
    .py-4,
    .py-1{
      margin:0 !important;
      padding:0 !important;
      max-width:none !important;
    }
    .clinical-viewer,
    .mm-viewer-shell{
      height:auto !important;
      min-height:0 !important;
      background:#fff !important;
      display:block !important;
      overflow:visible !important;
    }
    .viewer-sticky-head,
    .mm-viewer-actions,
    [data-role="viewer-print"],
    .btn,
    .modal,
    .offcanvas,
    .toast,
    .clinical-embed-floating{
      display:none !important;
      visibility:hidden !important;
    }
    .document-sheet-frame{
      display:block !important;
      visibility:visible !important;
      opacity:1 !important;
      position:static !important;
      background:transparent !important;
      border:none !important;
      border-radius:0 !important;
      box-shadow:none !important;
      padding:0 !important;
      margin:0 !important;
    }
    .document-sheet{
      display:block !important;
      visibility:visible !important;
      opacity:1 !important;
      position:static !important;
      width:auto !important;
      max-width:none !important;
      margin:0 !important;
      padding:2.2cm 2cm !important;
      box-shadow:none !important;
      border:none !important;
      border-radius:0 !important;
      background:#fff !important;
      overflow:visible !important;
      page-break-before:auto !important;
      page-break-after:auto !important;
      break-before:auto !important;
      break-after:auto !important;
      min-height:auto !important;
      height:auto !important;
    }
    .informe-doc-sheet{
      display:block !important;
      visibility:visible !important;
      opacity:1 !important;
      position:static !important;
      page-break-inside:auto !important;
      break-inside:auto !important;
      page-break-before:auto !important;
      page-break-after:auto !important;
      break-before:auto !important;
      break-after:auto !important;
      min-height:auto !important;
      height:auto !important;
    }
    .informe-doc-head,
    .informe-doc-title-block,
    .informe-doc-patient-block{
      page-break-inside:avoid;
      break-inside:avoid;
    }
    .informe-doc-sign{
      visibility:visible !important;
      opacity:1 !important;
      page-break-inside:auto !important;
      break-inside:auto !important;
      page-break-after:auto !important;
      break-after:auto !important;
      min-height:auto !important;
      height:auto !important;
      margin-bottom:0 !important;
      padding-bottom:0 !important;
    }
    .informe-doc-head .clinical-doc-head-logo-slot,
    .informe-doc-sign-image{
      overflow:visible !important;
      break-inside:avoid;
      page-break-inside:avoid;
    }
    .informe-doc-title{font-size:15.5pt;}
    .informe-doc-title-block{margin-bottom:14px;}
    .informe-doc-patient-block{margin-bottom:8px;}
    .clinical-doc-doctor-name{font-size:16pt;}
    .clinical-doc-doctor-specialty{font-size:11pt;}
    .clinical-doc-doctor-license,
    .clinical-doc-doctor-site,
    .informe-doc-date,
    .informe-doc-patient-line,
    .informe-doc-section-title,
    .informe-doc-text,
    .informe-doc-sign-meta{
      font-size:10.8pt;
      line-height:1.28;
    }
    .informe-doc-head .clinical-doc-head-logo-slot{
      width:7cm !important;
      min-height:2.6cm !important;
      height:2.6cm !important;
    }
    .informe-doc-head .clinical-doc-head-logo-slot img{
      max-height:2.3cm !important;
    }
    img{
      max-width:100% !important;
      height:auto !important;
    }
  }
</style>
<div class="<?php echo $embed ? 'py-1' : 'container py-4'; ?>">
  <div class="clinical-viewer mm-viewer-shell <?php echo $isFullscreenMode ? 'is-fullscreen' : ''; ?>">
  <div class="viewer-sticky-head">
    <div class="d-flex justify-content-between align-items-center mt-2">
      <div>
        <?php if ($debugView): ?>
          <h1 class="h5 mb-0">Document Viewer</h1>
        <?php elseif ($displayTitle !== '-' && ($uuid !== '' || $bundleId !== '')): ?>
          <h1 class="h6 mb-0"><?php echo h($displayTitle); ?></h1>
        <?php endif; ?>
        <?php if ($debugView && $displayTitle !== '-' && ($uuid !== '' || $bundleId !== '')): ?>
          <div class="text-secondary small"><?php echo h($displayTitle); ?></div>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-2 mm-viewer-actions">
        <?php if ($returnToClean !== ''): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($backHref); ?>">Volver</a>
        <?php endif; ?>
        <?php if ($showDownloadAction): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($mediaSrc); ?>"<?php echo $downloadIsRelative ? ' download' : ''; ?>>Descargar</a>
        <?php endif; ?>
        <?php if ($isPdf || $isConsentDoc || $isInformeDoc): ?>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-role="viewer-print">Imprimir</button>
        <?php endif; ?>
        <?php if (($isConsentDoc && $consentPrintableHref !== '') || ($isInformeDoc && $informePrintableHref !== '')): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($isConsentDoc ? $consentPrintableHref : $informePrintableHref); ?>" target="_blank" rel="noopener" download>Descargar</a>
          <?php // TODO(DOCS-UX): agregar botón "Compartir" cuando exista flujo canónico de distribución segura. ?>
        <?php endif; ?>
        <?php if ($bundlePrevHref !== ''): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($bundlePrevHref); ?>">Anterior</a>
        <?php endif; ?>
        <?php if ($bundleNextHref !== ''): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($bundleNextHref); ?>">Siguiente</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($debugView): ?>
    <?php if ($bundleId !== ''): ?>
      <p class="text-secondary mb-3">bundle_id: <code><?php echo h($bundleId); ?></code></p>
    <?php else: ?>
      <p class="text-secondary mb-3">uuid: <code><?php echo h($uuid !== '' ? $uuid : '-'); ?></code></p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($uuid === '' && $bundleId === ''): ?>
    <div class="alert alert-warning">uuid o bundle_id requerido.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php else: ?>
    <?php
    $bundleClinicalSummary = trim((string)($bundleClinicalBlock['summary'] ?? ''));
    $bundleClinicalInterpretation = trim((string)($bundleClinicalBlock['interpretation'] ?? ''));
    $bundleClinicalObservations = trim((string)($bundleClinicalBlock['observations'] ?? ''));
    ?>
    <?php if ($bundleItems !== []): ?>
      <div class="mm-card mb-3">
        <div class="body">
          <div class="fw-semibold"><?php echo h($bundleTitle !== '' ? $bundleTitle : 'Bundle de imágenes'); ?></div>
          <?php if ($bundleNote !== ''): ?>
            <div class="text-secondary small mt-1"><?php echo h($bundleNote); ?></div>
          <?php endif; ?>
          <div class="mt-2 vstack gap-2">
            <?php foreach ($bundleItems as $bundleIndex => $bundleItem): ?>
              <?php
              $bundleItemUuid = trim((string)($bundleItem['document_uuid'] ?? ''));
              $bundleItemCaption = trim((string)($bundleItem['media_caption'] ?? ''));
              $bundleItemTag = trim((string)($bundleItem['media_tag_label'] ?? ''));
              $bundleItemLabel = $bundleItemCaption !== '' ? $bundleItemCaption : ($bundleItemTag !== '' ? $bundleItemTag : 'Imagen');
              $bundleItemHref = build_viewer_self_href([
                  'bundle_id' => $bundleId,
                  'patient_id' => $patientId,
                  'uuid' => $bundleItemUuid,
                  'return_to' => $returnToClean,
                  'embed' => $embed ? '1' : '',
              ]);
              ?>
              <a class="btn btn-sm <?php echo $bundleIndex === $selectedBundleIndex ? 'btn-primary' : 'btn-outline-secondary'; ?> text-start" href="<?php echo h($bundleItemHref); ?>">
                <?php echo h($bundleItemLabel); ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($bundleClinicalSummary !== '' || $bundleClinicalInterpretation !== '' || $bundleClinicalObservations !== ''): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Notas clínicas del estudio</h5></div>
        <div class="body">
          <?php if ($bundleClinicalSummary !== ''): ?>
            <div class="mb-3">
              <div class="fw-semibold small text-uppercase text-secondary">Descripción</div>
              <div><?php echo nl2br(h($bundleClinicalSummary)); ?></div>
            </div>
          <?php endif; ?>
          <?php if ($bundleClinicalInterpretation !== ''): ?>
            <div class="mb-3">
              <div class="fw-semibold small text-uppercase text-secondary">Interpretación</div>
              <div><?php echo nl2br(h($bundleClinicalInterpretation)); ?></div>
            </div>
          <?php endif; ?>
          <?php if ($bundleClinicalObservations !== ''): ?>
            <div>
              <div class="fw-semibold small text-uppercase text-secondary">Observaciones</div>
              <div><?php echo nl2br(h($bundleClinicalObservations)); ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($debugView): ?>
      <div class="mm-card mb-3">
        <div class="body small">
          <div><strong>Tipo:</strong> <?php echo h($docType); ?></div>
          <div><strong>Fecha:</strong> <?php echo h($dateFormatted !== '' ? $dateFormatted : $date); ?></div>
          <?php if ($summary !== '' && $summary !== '-'): ?>
            <div><strong>Summary:</strong> <?php echo h($summary); ?></div>
          <?php endif; ?>
          <div><strong>Viewer mode:</strong> <?php echo h($detectedMode); ?></div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($isPrescriptionDoc): ?>
      <article class="rx-view-sheet mb-3">
        <header class="rx-view-head">
          <div>
            <div class="rx-view-title"><?php echo h($displayTitle !== '-' ? $displayTitle : 'Receta médica'); ?></div>
            <?php if ($prescriptionPatientName !== ''): ?>
              <div class="rx-view-meta">Paciente: <?php echo h($prescriptionPatientName); ?></div>
            <?php endif; ?>
            <?php if ($prescriptionDoctorName !== ''): ?>
              <div class="rx-view-meta">Médico: <?php echo h($prescriptionDoctorName); ?></div>
            <?php endif; ?>
          </div>
          <div class="text-end">
            <span class="badge text-bg-secondary"><?php echo h($prescriptionAnalysisStatus !== '' ? $prescriptionAnalysisStatus : 'idle'); ?></span>
            <?php if ($prescriptionDate !== ''): ?>
              <div class="rx-view-meta mt-2">Fecha: <?php echo h($prescriptionDateFormatted !== '' ? $prescriptionDateFormatted : $prescriptionDate); ?></div>
            <?php endif; ?>
            <?php if ($prescriptionEncounter !== ''): ?>
              <div class="rx-view-meta">Consulta: <?php echo h($prescriptionEncounter); ?></div>
            <?php endif; ?>
          </div>
        </header>
        <section>
          <div class="rx-view-section-title">Medicamentos</div>
          <?php if ($prescriptionItems !== []): ?>
            <ol class="rx-view-list">
              <?php foreach ($prescriptionItems as $rxItem): ?>
                <?php
                if (!is_array($rxItem)) {
                    continue;
                }
                $rxName = trim((string)($rxItem['name'] ?? ($rxItem['medicamento'] ?? '')));
                $rxPresentation = trim((string)($rxItem['presentation'] ?? ''));
                $rxDose = trim((string)($rxItem['dose'] ?? ($rxItem['dosis'] ?? '')));
                $rxRoute = trim((string)($rxItem['route'] ?? ($rxItem['via'] ?? '')));
                $rxFrequency = trim((string)($rxItem['frequency'] ?? ($rxItem['frecuencia'] ?? ($rxItem['periodicidad'] ?? ''))));
                $rxDuration = trim((string)($rxItem['duration'] ?? ($rxItem['duracion'] ?? '')));
                $rxQuantity = trim((string)($rxItem['quantity'] ?? ''));
                $rxInstructions = trim((string)($rxItem['instructions'] ?? ($rxItem['indicaciones'] ?? '')));
                $rxPrivateNotes = trim((string)($rxItem['private_notes'] ?? ''));
                $rxMetaParts = array_values(array_filter([
                    $rxPresentation !== '' ? ('Presentación: ' . $rxPresentation) : '',
                    $rxDose !== '' ? ('Dosis: ' . $rxDose) : '',
                    $rxRoute !== '' ? ('Vía: ' . $rxRoute) : '',
                    $rxFrequency !== '' ? ('Frecuencia: ' . $rxFrequency) : '',
                    $rxDuration !== '' ? ('Duración: ' . $rxDuration) : '',
                    $rxQuantity !== '' ? ('Cantidad: ' . $rxQuantity) : '',
                ]));
                ?>
                <li>
                  <div class="rx-view-item-title"><?php echo h($rxName !== '' ? $rxName : 'Medicamento'); ?></div>
                  <?php if ($rxMetaParts !== []): ?>
                    <div class="rx-view-item-meta"><?php echo h(implode(' · ', $rxMetaParts)); ?></div>
                  <?php endif; ?>
                  <?php if ($rxInstructions !== ''): ?>
                    <div class="rx-view-item-note"><?php echo h($rxInstructions); ?></div>
                  <?php endif; ?>
                  <?php if ($rxPrivateNotes !== ''): ?>
                    <div class="rx-view-item-note text-muted">Nota médica: <?php echo h($rxPrivateNotes); ?></div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php else: ?>
            <div class="rx-view-text text-muted">Sin medicamentos registrados.</div>
          <?php endif; ?>
        </section>
        <section>
          <div class="rx-view-section-title">Contexto clínico</div>
          <div class="rx-view-text"><?php echo h('Alergias: ' . ($prescriptionAllergies !== [] ? implode(' · ', array_map('strval', $prescriptionAllergies)) : 'Sin alergias registradas')); ?></div>
          <div class="rx-view-text"><?php echo h('Medicamentos actuales: ' . ($prescriptionCurrentMeds !== [] ? implode(' · ', array_map('strval', $prescriptionCurrentMeds)) : 'Sin medicamentos registrados')); ?></div>
        </section>
        <section>
          <div class="rx-view-section-title">Análisis clínico local</div>
          <div class="rx-view-text">
            <?php if ($prescriptionAnalysisObservations !== []): ?>
              <?php echo h(implode(' ', array_map('strval', $prescriptionAnalysisObservations))); ?>
            <?php else: ?>
              Sin observaciones locales. El análisis asistido por IA queda preparado para ejecución bajo demanda.
            <?php endif; ?>
          </div>
        </section>
      </article>
    <?php endif; ?>

    <?php if ($isConsentDoc): ?>
      <?php
      $consentDocTitle = trim((string)($consentBlock['document_title'] ?? $title));
      $consentPatientName = trim((string)($consentPatientSnapshot['full_name'] ?? 'Paciente'));
      $consentDoctorName = trim((string)($consentActorSnapshot['full_name'] ?? 'Médico tratante'));
      $consentDoctorLicense = trim((string)($consentActorSnapshot['license'] ?? ''));
      $consentProcedure = trim((string)($consentBlock['document_title'] ?? ''));
      $consentBenefits = trim((string)($consentLegalDetails['beneficios_esperados'] ?? ''));
      $consentAlternatives = trim((string)($consentLegalDetails['alternativas'] ?? ''));
      $consentNoAccept = trim((string)($consentLegalDetails['consecuencias_no_aceptar'] ?? ''));
      $consentRiskProfile = is_array($consentLegalDetails['risk_profile'] ?? null) ? $consentLegalDetails['risk_profile'] : [];
      $consentRiskComunes = trim((string)(
        $consentRiskProfile['comunes']['value']
        ?? $consentRiskProfile['comunes']['ui_phrase']
        ?? $consentRiskProfile['comunes']['legal_phrase']
        ?? ''
      ));
      $consentRiskPocoFrecuentes = trim((string)(
        $consentRiskProfile['poco_frecuentes']['value']
        ?? $consentRiskProfile['poco_frecuentes']['ui_phrase']
        ?? $consentRiskProfile['poco_frecuentes']['legal_phrase']
        ?? ''
      ));
      $consentRiskRarosGraves = trim((string)(
        $consentRiskProfile['raros_graves']['value']
        ?? $consentRiskProfile['raros_graves']['ui_phrase']
        ?? $consentRiskProfile['raros_graves']['legal_phrase']
        ?? ''
      ));
      $consentHasStructuredRiskProfile = ($consentRiskComunes !== '' || $consentRiskPocoFrecuentes !== '' || $consentRiskRarosGraves !== '');
      $consentContingency = !empty($consentLegalDetails['autorizacion_contingencias']);
      $consentSignerType = trim((string)($consentSigner['tipo'] ?? 'paciente'));
      $consentSignerName = trim((string)($consentSigner['nombre'] ?? $consentPatientName));
      $consentSignerRelationRaw = trim((string)($consentSigner['relacion'] ?? ($consentSigner['parentesco'] ?? '')));
      $consentRelationLabels = [
        'self' => 'Paciente',
        'padre' => 'Padre',
        'madre' => 'Madre',
        'conyuge' => 'Cónyuge',
        'hijo' => 'Hijo',
        'hija' => 'Hija',
        'hermano' => 'Hermano',
        'hermana' => 'Hermana',
        'abuelo' => 'Abuelo',
        'abuela' => 'Abuela',
        'otro_familiar' => 'Otro familiar',
        'otro' => 'Otro',
      ];
      $consentSignerRelation = (string)($consentRelationLabels[$consentSignerRelationRaw] ?? $consentSignerRelationRaw);
      $consentSignerTypeLabels = [
        'paciente' => 'Paciente',
        'tutor' => 'Tutor',
        'representante_legal' => 'Representante legal',
        'familiar_mas_cercano' => 'Familiar más cercano en vínculo',
      ];
      $consentSignerTypeLabel = (string)($consentSignerTypeLabels[$consentSignerType] ?? ucfirst(str_replace('_', ' ', $consentSignerType)));
      $consentSignatureSourceLabelMap = [
        'local_canvas' => 'Firma local',
        'remote_qr' => 'Firma remota',
      ];
      $consentSignatureSourceLabel = (string)($consentSignatureSourceLabelMap[$consentPatientSignatureSource] ?? '');
      $consentWitness1 = trim((string)((is_array($consentWitnesses[0] ?? null) ? ($consentWitnesses[0]['nombre'] ?? '') : '')));
      $consentWitness2 = trim((string)((is_array($consentWitnesses[1] ?? null) ? ($consentWitnesses[1]['nombre'] ?? '') : '')));
      $consentBodyText = trim((string)($consentTemplateSnapshot['body_text'] ?? ''));
      $consentStatusBadgeClass = ($consentStatus === 'granted') ? 'text-bg-success' : 'text-bg-secondary';
      ?>
      <?php if ($consentFrozenHtml !== ''): ?>
        <div class="mb-3"><?php echo $consentFrozenHtml; ?></div>
      <?php else: ?>
      <article class="consent-doc-sheet mb-3">
        <header class="consent-doc-head">
          <div>
            <div class="consent-doc-title"><?php echo h($consentDocTitle !== '' ? $consentDocTitle : 'Consentimiento informado'); ?></div>
            <div class="consent-doc-meta">Paciente: <?php echo h($consentPatientName); ?></div>
            <div class="consent-doc-meta">Médico: <?php echo h($consentDoctorName); ?><?php echo $consentDoctorLicense !== '' ? h(' · Cédula: ' . $consentDoctorLicense) : ''; ?></div>
          </div>
          <div class="text-end">
            <span class="badge <?php echo h($consentStatusBadgeClass); ?>"><?php echo h($consentStatus !== '' ? $consentStatus : 'draft'); ?></span>
            <?php
            $consentDateRaw = (string)($consentBlock['granted_at'] ?? $date);
            $consentDateOut = clinical_doc_format_date($consentDateRaw, false);
            ?>
            <div class="consent-doc-meta mt-2">Fecha: <?php echo h($consentDateOut !== '' ? $consentDateOut : $consentDateRaw); ?></div>
          </div>
        </header>

        <section>
          <div class="consent-doc-section-title">Acto autorizado</div>
          <div class="consent-doc-text"><?php echo nl2br(h($consentProcedure !== '' ? $consentProcedure : 'Sin descripción de procedimiento.')); ?></div>
        </section>

        <section>
          <div class="consent-doc-section-title">Riesgos y beneficios esperados</div>
          <div class="consent-doc-text">
            <?php echo nl2br(h($consentBodyText !== '' ? $consentBodyText : 'Riesgos explicados conforme a criterio médico.')); ?>
            <?php if ($consentBenefits !== ''): ?><br><br><?php echo nl2br(h('Beneficios esperados: ' . $consentBenefits)); ?><?php endif; ?>
          </div>
        </section>
        <?php if ($consentHasStructuredRiskProfile): ?>
          <section>
            <div class="consent-doc-section-title">Riesgos del procedimiento</div>
            <?php if ($consentRiskComunes !== ''): ?>
              <div class="consent-doc-text"><strong>Riesgos comunes:</strong> <?php echo nl2br(h($consentRiskComunes)); ?></div>
            <?php endif; ?>
            <?php if ($consentRiskPocoFrecuentes !== ''): ?>
              <div class="consent-doc-text"><strong>Riesgos poco frecuentes:</strong> <?php echo nl2br(h($consentRiskPocoFrecuentes)); ?></div>
            <?php endif; ?>
            <?php if ($consentRiskRarosGraves !== ''): ?>
              <div class="consent-doc-text"><strong>Complicaciones raras pero graves:</strong> <?php echo nl2br(h($consentRiskRarosGraves)); ?></div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($consentAlternatives !== ''): ?>
          <section>
            <div class="consent-doc-section-title">Alternativas</div>
            <div class="consent-doc-text"><?php echo nl2br(h($consentAlternatives)); ?></div>
          </section>
        <?php endif; ?>

        <?php if ($consentNoAccept !== ''): ?>
          <section>
            <div class="consent-doc-section-title">Consecuencias de no realizarlo</div>
            <div class="consent-doc-text"><?php echo nl2br(h($consentNoAccept)); ?></div>
          </section>
        <?php endif; ?>

        <section>
          <div class="consent-doc-section-title">Declaraciones</div>
          <div class="consent-doc-text">Autorización de contingencias y urgencias: <?php echo h($consentContingency ? 'Sí' : 'No'); ?></div>
          <div class="consent-doc-text">Se explicó en lenguaje claro y se resolvieron dudas: <?php echo h($consentBoolLabel((bool)($consentLegal['questions_resolved'] ?? false))); ?></div>
        </section>

        <section class="consent-doc-sign-grid">
          <div class="consent-doc-sign-item">
            <div class="consent-doc-meta"><?php echo h($consentSignerTypeLabel); ?></div>
            <div class="consent-doc-text"><?php echo h($consentSignerName !== '' ? $consentSignerName : '________________'); ?><?php echo $consentSignerRelation !== '' ? h(' · ' . $consentSignerRelation) : ''; ?></div>
            <?php if ($consentPatientSignatureImage !== ''): ?>
              <img class="consent-doc-sign-image" src="<?php echo h($consentPatientSignatureImage); ?>" alt="Firma del paciente o representante">
              <?php if ($consentPatientSignatureSignerName !== '' || $consentPatientSignatureSignedAt !== ''): ?>
                <div class="consent-doc-meta mt-1">
                  <?php echo h($consentPatientSignatureSignerName !== '' ? $consentPatientSignatureSignerName : $consentSignerName); ?>
                  <?php if ($consentPatientSignatureSignedAt !== ''): ?>
                    <?php echo h(' · ' . $consentPatientSignatureSignedAt); ?>
                  <?php endif; ?>
                  <?php if ($consentSignatureSourceLabel !== ''): ?>
                    <?php echo h(' · ' . $consentSignatureSourceLabel); ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="consent-doc-sign-line"></div>
            <?php endif; ?>
          </div>
          <div class="consent-doc-sign-item">
            <div class="consent-doc-meta">Médico responsable</div>
            <div class="consent-doc-text"><?php echo h($consentDoctorName); ?><?php echo $consentDoctorLicense !== '' ? h(' · Cédula: ' . $consentDoctorLicense) : ''; ?></div>
            <div class="consent-doc-sign-line"></div>
          </div>
          <div class="consent-doc-sign-item">
            <div class="consent-doc-meta">Testigo 1</div>
            <div class="consent-doc-text"><?php echo h($consentWitness1 !== '' ? $consentWitness1 : '________________'); ?></div>
            <div class="consent-doc-sign-line"></div>
          </div>
          <div class="consent-doc-sign-item">
            <div class="consent-doc-meta">Testigo 2</div>
            <div class="consent-doc-text"><?php echo h($consentWitness2 !== '' ? $consentWitness2 : '________________'); ?></div>
            <div class="consent-doc-sign-line"></div>
          </div>
        </section>

        <?php if ($consentRenderedText !== ''): ?>
          <section>
            <div class="consent-doc-section-title">Redacción legal ensamblada</div>
            <div class="consent-doc-text"><?php echo nl2br(h($consentRenderedText)); ?></div>
          </section>
        <?php endif; ?>

        <?php if (is_array($consentIdentityAttachments) && $consentIdentityAttachments !== []): ?>
          <section>
            <div class="consent-doc-section-title">Anexos de identidad del firmante</div>
            <div class="vstack gap-1">
              <?php foreach ($consentIdentityAttachments as $index => $att): ?>
                <?php
                $attUuid = trim((string)($att['document_uuid'] ?? ''));
                $attTitle = trim((string)($att['title'] ?? 'Anexo de identidad'));
                $attSource = trim((string)($att['source'] ?? ''));
                $attKind = trim((string)($att['identity_doc_label'] ?? ($att['identity_doc_kind'] ?? '')));
                $attSourceLabelMap = [
                  'consentimiento_identidad_qr_v1' => 'Captura por celular',
                  'consentimiento_identidad_local' => 'Carga local',
                ];
                $attSourceLabel = (string)($attSourceLabelMap[$attSource] ?? '');
                $attMetaParts = array_values(array_filter([
                  $attKind !== '' ? $attKind : '',
                  $attSourceLabel !== '' ? $attSourceLabel : '',
                ]));
                $attHref = $attUuid !== '' ? ('/modules/clinical/ui/viewer.php?uuid=' . rawurlencode($attUuid) . ($embed ? '&embed=1' : '')) : '';
                ?>
                <?php if ($attHref !== ''): ?>
                  <a class="btn btn-sm btn-outline-secondary text-start" href="<?php echo h($attHref); ?>" target="_blank" rel="noopener"><?php echo h(($index + 1) . '. ' . $attTitle); ?></a>
                <?php else: ?>
                  <div class="consent-doc-text"><?php echo h(($index + 1) . '. ' . $attTitle); ?></div>
                <?php endif; ?>
                <?php if ($attMetaParts !== []): ?>
                  <div class="consent-doc-meta ps-1"><?php echo h(implode(' · ', $attMetaParts)); ?></div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($consentObservations !== ''): ?>
          <section>
            <div class="consent-doc-section-title">Observaciones</div>
            <div class="consent-doc-text"><?php echo nl2br(h($consentObservations)); ?></div>
          </section>
        <?php endif; ?>
      </article>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($isInformeDoc): ?>
      <?php
      $informeDateOut = clinical_doc_format_date((string)($informeReport['emission_date'] ?? ($informeReport['issued_at'] ?? $date)), false);
      $informePatientName = trim((string)($informePatientNameContext !== '' ? $informePatientNameContext : 'Paciente'));
      $informePatientAge = trim((string)$informePatientAgeContext);
      $informePatientSex = trim((string)$informePatientSexContext);
      $informePatientMeta = implode(' · ', array_values(array_filter([
        $informePatientAge !== '' ? ('Edad: ' . $informePatientAge) : '',
        $informePatientSex !== '' ? ('Sexo: ' . $informePatientSex) : '',
      ])));
      $informeDoctorName = trim((string)($informeDoctorNameContext !== '' ? $informeDoctorNameContext : 'Médico tratante'));
      $informeDoctorSpecialty = trim((string)$informeDoctorSpecialtyContext);
      $informeDoctorLicense = trim((string)$informeDoctorLicenseContext);
      $informeDoctorSpecialtyLicense = trim((string)$informeDoctorSpecialtyLicenseContext);
      $informeDoctorInstitution = trim((string)$informeDoctorInstitutionContext);
      $informeDoctorFacility = trim((string)$informeDoctorFacilityContext);
      $informeDoctorPlace = trim((string)$informeDoctorPlaceContext);
      $informeBrandingMode = trim((string)($informeBranding['mode'] ?? ''));
      $informeBrandingLogo = trim((string)($informeBranding['logo_url_resolved'] ?? ($informeBranding['logo_url'] ?? '')));
      $informeBrandingGroupLogo = trim((string)($informeBranding['group_logo_url_resolved'] ?? ($informeBranding['group_logo_url'] ?? '')));
      $informeBrandingFacility = trim((string)($informeBranding['facility_visible'] ?? ''));
      $informeBrandingLocationLine = trim((string)($informeBranding['location_line_visible'] ?? ''));
      if ($informeBrandingFacility !== '') {
        $informeDoctorFacility = $informeBrandingFacility;
      }
      if ($informeBrandingLocationLine !== '') {
        $informeDoctorPlace = $informeBrandingLocationLine;
      }
      $informeDoctorSite = $informeBrandingLocationLine !== ''
        ? $informeBrandingLocationLine
        : implode(' · ', array_values(array_filter([
          $informeDoctorFacility !== '' ? $informeDoctorFacility : '',
          $informeDoctorPlace !== '' ? $informeDoctorPlace : '',
        ])));
      $informeDoctorCedulas = implode(' · ', array_values(array_filter([
        $informeDoctorLicense !== '' ? ('Cédula: ' . $informeDoctorLicense) : '',
        $informeDoctorSpecialtyLicense !== '' ? ('Cédula esp.: ' . $informeDoctorSpecialtyLicense) : '',
      ])));
      // Base reusable header modes: branded | standard | legal
      $informeHeaderMode = 'standard';
      if (in_array($informeBrandingMode, ['branded', 'standard', 'legal'], true)) {
        $informeHeaderMode = $informeBrandingMode;
      }
      $informeTestLogoUrl = '/uploads/doctors/1/logo.png';
      $informeHeaderCfg = clinical_doc_header_mode($informeHeaderMode);
      // Temporary controlled visual test: fallback to validated local URL when branding logo is not resolved yet.
      $informeHeaderLogoUrl = $informeBrandingLogo;
      if (
        $informeHeaderLogoUrl === ''
        || strpos($informeHeaderLogoUrl, '/storage/clinical_uploads/branding/') !== false
      ) {
        $informeHeaderLogoUrl = $informeTestLogoUrl;
      }
      if ($informeHeaderMode !== 'legal' && $informeHeaderLogoUrl !== '') {
        $informeHeaderMode = 'branded';
        $informeHeaderCfg = clinical_doc_header_mode($informeHeaderMode);
      }
      $informeShowLogo = ($informeHeaderCfg['allow_logo'] && $informeHeaderLogoUrl !== '');
      $informeShowGroupLogo = ($informeBrandingGroupLogo !== '');
      $informeReason = trim((string)($informeContent['reason'] ?? ''));
      $informeCurrentIllness = trim((string)($informeContent['current_illness'] ?? ''));
      $informeRelevantHistory = trim((string)($informeContent['relevant_history'] ?? ''));
      $informeClinicalSummary = trim((string)($informeContent['clinical_summary'] ?? ''));
      $informeFindings = trim((string)($informeContent['findings'] ?? ''));
      $informeDiagnostic = trim((string)($informeContent['diagnostic_impression'] ?? ''));
      $informePlan = trim((string)($informeContent['plan'] ?? ''));
      $informePrognosisRaw = trim((string)($informeContent['prognosis'] ?? ''));
      $informePrognosisLabelMap = [
        'favorable' => 'Favorable',
        'reservado' => 'Reservado',
        'en_evolucion' => 'En evolución',
        'condicionado' => 'Condicionado',
      ];
      $informePrognosis = (string)($informePrognosisLabelMap[strtolower($informePrognosisRaw)] ?? $informePrognosisRaw);
      $informeClosing = trim((string)($informeContent['closing_statement'] ?? ''));
      ?>
      <div class="document-sheet-frame mb-3">
      <article class="informe-doc-sheet document-sheet">
        <header class="clinical-doc-head informe-doc-head <?php echo h($informeHeaderCfg['class_name']); ?>">
          <div class="clinical-doc-head-main">
            <div class="clinical-doc-head-doctor">
              <div class="clinical-doc-head-logo-slot <?php echo $informeShowLogo ? '' : 'is-empty'; ?>">
                <?php if ($informeShowLogo): ?>
                  <img src="<?php echo h($informeHeaderLogoUrl); ?>" alt="Logo médico" onerror="this.remove();this.parentElement.classList.add('is-empty');">
                  <span class="clinical-doc-logo-fallback">Logo</span>
                <?php else: ?>
                  <span class="clinical-doc-logo-fallback">Logo</span>
                <?php endif; ?>
              </div>
              <div class="clinical-doc-head-main">
                <div class="clinical-doc-doctor-name"><?php echo h($informeDoctorName); ?></div>
                <?php if ($informeDoctorSpecialty !== ''): ?><div class="clinical-doc-doctor-specialty"><?php echo h($informeDoctorSpecialty); ?></div><?php endif; ?>
                <?php if ($informeDoctorCedulas !== ''): ?><div class="clinical-doc-doctor-license"><?php echo h($informeDoctorCedulas); ?></div><?php endif; ?>
                <?php if ($informeDoctorSite !== ''): ?><div class="clinical-doc-doctor-site"><?php echo h($informeDoctorSite); ?></div><?php endif; ?>
                <?php if ($informeShowGroupLogo): ?>
                  <div class="clinical-doc-group-logo">
                    <img src="<?php echo h($informeBrandingGroupLogo); ?>" alt="Logo grupo médico" onerror="var wrap=this.closest('.clinical-doc-group-logo'); if(wrap){wrap.remove();}">
                    <span>Grupo médico</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </header>
        <section class="informe-doc-title-block">
          <div class="informe-doc-title"><?php echo h(trim((string)($title !== '' ? $title : 'Informe médico'))); ?></div>
        </section>
        <section class="informe-doc-patient-block">
          <div class="informe-doc-patient-head">
            <div class="informe-doc-patient-line"><strong>Paciente:</strong> <?php echo h($informePatientName); ?></div>
            <div class="informe-doc-date"><strong>Fecha:</strong> <?php echo h($informeDateOut !== '' ? $informeDateOut : $date); ?></div>
          </div>
          <div class="informe-doc-patient-line"><strong>Edad:</strong> <?php echo h($informePatientAge !== '' ? ($informePatientAge . ' años') : 'No registrada'); ?> · <strong>Sexo:</strong> <?php echo h($informePatientSex !== '' ? $informePatientSex : 'No especificado'); ?></div>
        </section>
        <?php if ($informeReason !== ''): ?>
          <section class="informe-doc-body-section">
            <div class="informe-doc-section-title">Motivo del informe</div>
            <div class="informe-doc-text"><?php echo nl2br(h($informeReason)); ?></div>
          </section>
        <?php endif; ?>
        <?php if ($informeCurrentIllness !== ''): ?>
          <section class="informe-doc-body-section">
            <div class="informe-doc-section-title">Motivo de atención</div>
            <div class="informe-doc-text"><?php echo nl2br(h($informeCurrentIllness)); ?></div>
          </section>
        <?php endif; ?>
        <?php if ($informeRelevantHistory !== ''): ?>
          <section class="informe-doc-body-section">
            <div class="informe-doc-section-title">Antecedentes relevantes</div>
            <div class="informe-doc-text"><?php echo nl2br(h($informeRelevantHistory)); ?></div>
          </section>
        <?php endif; ?>
        <?php if ($informeClinicalSummary !== ''): ?>
          <section class="informe-doc-body-section">
            <div class="informe-doc-section-title">Resumen clínico</div>
            <div class="informe-doc-text"><?php echo nl2br(h($informeClinicalSummary)); ?></div>
          </section>
        <?php endif; ?>
        <?php if ($informeFindings !== ''): ?>
          <section class="informe-doc-body-section">
            <div class="informe-doc-section-title">Hallazgos / valoración médica</div>
            <div class="informe-doc-text"><?php echo nl2br(h($informeFindings)); ?></div>
          </section>
        <?php endif; ?>
        <?php if ($informeDiagnostic !== ''): ?>
          <section class="informe-doc-body-section">
            <div class="informe-doc-section-title">Impresión diagnóstica / diagnóstico</div>
            <div class="informe-doc-text"><?php echo nl2br(h($informeDiagnostic)); ?></div>
          </section>
        <?php endif; ?>
        <?php if ($informePlan !== ''): ?>
          <section class="informe-doc-body-section">
            <div class="informe-doc-section-title">Plan / manejo / recomendaciones</div>
            <div class="informe-doc-text"><?php echo nl2br(h($informePlan)); ?></div>
          </section>
        <?php endif; ?>
        <?php if ($informePrognosis !== ''): ?>
          <section class="informe-doc-body-section">
            <div class="informe-doc-section-title">Pronóstico</div>
            <div class="informe-doc-text"><?php echo nl2br(h($informePrognosis)); ?></div>
          </section>
        <?php endif; ?>
        <?php if ($informeClosing !== ''): ?>
          <section class="informe-doc-body-section">
            <div class="informe-doc-section-title">Declaración de cierre</div>
            <div class="informe-doc-text"><?php echo nl2br(h($informeClosing)); ?></div>
          </section>
        <?php endif; ?>
        <section class="informe-doc-sign">
          <div class="informe-doc-section-title">Firma del médico</div>
          <?php if ($informeDoctorSignatureImage !== ''): ?>
            <img class="informe-doc-sign-image" src="<?php echo h($informeDoctorSignatureImage); ?>" alt="Firma del médico">
            <div class="informe-doc-sign-meta">
              <?php echo h($informeDoctorSignatureSignerName !== '' ? $informeDoctorSignatureSignerName : $informeDoctorName); ?>
            </div>
          <?php else: ?>
            <div class="informe-doc-sign-line"></div>
            <div class="informe-doc-sign-meta">
              <?php echo h($informeDoctorName); ?>
            </div>
          <?php endif; ?>
        </section>
      </article>
      </div>
    <?php endif; ?>

    <?php if ($externalBlockedMessage !== ''): ?>
      <div class="alert alert-warning"><?php echo h($externalBlockedMessage); ?></div>
    <?php endif; ?>

    <?php if ($detectedMode === 'image' && $mediaSrc !== ''): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Vista previa</h5></div>
        <div class="body <?php echo $isFullscreenMode ? 'fullscreen-image-wrap' : ''; ?>">
          <img src="<?php echo h($mediaSrc); ?>" alt="Documento" class="img-fluid border rounded">
          <?php if ($thumbSrc !== ''): ?>
            <div class="small text-secondary mt-2">Miniatura disponible: <?php echo h($thumbSrc); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php elseif ($renderMode === 'image' && $mediaSrc === ''): ?>
      <div class="alert alert-warning">Archivo no disponible.</div>
    <?php elseif (($detectedMode === 'pdf' || $detectedMode === 'html_external') && $mediaSrc !== ''): ?>
      <div class="mm-card mb-3 mm-viewer-frame">
        <div class="head"><h5>Vista previa</h5></div>
        <div class="body">
          <div data-role="viewer-loader" class="small text-secondary mb-2">Cargando…</div>
        </div>
        <div class="body p-0 mm-viewer-frame">
          <iframe data-role="viewer-iframe" sandbox="allow-same-origin allow-scripts allow-forms allow-downloads" src="<?php echo h($mediaSrc); ?>"></iframe>
        </div>
      </div>
    <?php elseif ($detectedMode === 'html_inline' && $htmlInline !== ''): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Vista previa</h5></div>
        <div class="body">
          <div data-role="viewer-loader" class="small text-secondary mb-2">Cargando…</div>
        </div>
        <div class="body p-0">
          <iframe data-role="viewer-iframe" sandbox="allow-same-origin allow-scripts allow-forms" srcdoc="<?php echo h($htmlInline); ?>" style="width:100%;height:72vh;border:0;"></iframe>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($renderedText !== '' && (!$isInformeDoc || $debugView)): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5><?php echo $isInformeDoc ? 'Texto renderizado (debug)' : 'Texto renderizado'; ?></h5></div>
        <div class="body">
          <pre class="mb-0 small"><?php echo h($renderedText); ?></pre>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($payloadJson !== '' && ((!$isConsentDoc && !$isInformeDoc) || $debugView)): ?>
      <div class="mm-card">
        <div class="head"><h5><?php echo ($isConsentDoc || $isInformeDoc) ? 'Datos técnicos (debug)' : 'Payload (JSON)'; ?></h5></div>
        <div class="body">
          <pre class="mb-0 small"><?php echo h($payloadJson); ?></pre>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  </div>
</div>
<script>
  (function () {
    var iframe = document.querySelector('[data-role="viewer-iframe"]');
    var loader = document.querySelector('[data-role="viewer-loader"]');
    if (iframe && loader) {
      iframe.addEventListener('load', function () {
        loader.classList.add('d-none');
      });
    }
    document.addEventListener('click', function (event) {
      var printBtn = event.target && event.target.closest ? event.target.closest('[data-role="viewer-print"]') : null;
      if (!printBtn) return;
      event.preventDefault();
      var frame = document.querySelector('[data-role="viewer-iframe"]');
      if (!frame) {
        window.print();
        return;
      }
      try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
      } catch (e) {
        window.open(<?php echo json_encode($openInNewHref, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, '_blank', 'noopener');
      }
    }, true);
  })();
</script>
<?php if ($embed): ?>
<?php clinical_embed_end(); ?>
<?php else: ?>
<?php require_once __DIR__ . '/../../_partials/mm_shell_bottom.php'; ?>
<?php endif; ?>
