<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/timeline_catalog.php';

function get_api_base(): string
{
    $env = trim((string)getenv('CLINICAL_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }
    return '/api/clinical/index.php';
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
    return $normalized;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function appointment_id_from_encounter_key(string $encounterKey): string
{
    $value = trim($encounterKey);
    if ($value === '' || strpos($value, 'appt:') !== 0) {
        return '';
    }
    $value = substr($value, 5);
    if ($value === false || $value === '') {
        return '';
    }
    $hashPos = strpos($value, '#enc:');
    if ($hashPos !== false) {
        $value = substr($value, 0, $hashPos);
    }
    return trim((string)$value);
}

function timeline_date_only(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    return substr($value, 0, 10);
}

function timeline_day_label(string $dayKey): string
{
    $normalized = trim($dayKey);
    if ($normalized === '') {
        return 'Sin fecha';
    }
    $ts = strtotime($normalized . ' 00:00:00');
    if ($ts === false) {
        return $normalized;
    }
    $weekdays = [
        'Sunday' => 'Domingo',
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miercoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sabado',
    ];
    $months = [
        '01' => 'Enero',
        '02' => 'Febrero',
        '03' => 'Marzo',
        '04' => 'Abril',
        '05' => 'Mayo',
        '06' => 'Junio',
        '07' => 'Julio',
        '08' => 'Agosto',
        '09' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre',
    ];
    $weekday = $weekdays[date('l', $ts)] ?? date('l', $ts);
    $month = $months[date('m', $ts)] ?? date('m', $ts);
    $day = (int)date('j', $ts);
    return $weekday . ' ' . $day . ' de ' . $month;
}

function timeline_item_catalog_meta(array $item): array
{
    $classification = classify_timeline_item($item);
    $catalogV11 = classify_catalog_v11($item);

    return array_merge($classification, $catalogV11, [
        'chip_text' => mxmed_clinical_timeline_chip_text($item),
    ]);
}

function timeline_item_uid(array $item): string
{
    $itemType = trim((string)($item['item_type'] ?? ''));
    $ref = trim((string)($item['ref'] ?? ''));
    if ($itemType !== '' && $ref !== '') {
        return $itemType . '|' . $ref;
    }

    $links = is_array($item['links'] ?? null) ? $item['links'] : [];
    $documentUuid = trim((string)($links['document_uuid'] ?? ''));
    if ($documentUuid !== '') {
        return 'document|doc:' . $documentUuid;
    }

    $encounterKey = trim((string)($item['encounter_key'] ?? ''));
    if ($itemType !== '' && $encounterKey !== '') {
        return $itemType . '|' . $encounterKey;
    }

    return md5(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($item));
}

function timeline_category_summary(array $entries, int $limit = 3): array
{
    $summary = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $meta = is_array($entry['category_meta'] ?? null) ? $entry['category_meta'] : [];
        $group = trim((string)($meta['catalog_group'] ?? ''));
        if ($group === '') {
            continue;
        }
        if (!isset($summary[$group])) {
            $summary[$group] = [
                'catalog_group' => $group,
                'label' => (string)($meta['catalog_group_label'] ?? $group),
                'priority' => (int)($meta['catalog_priority'] ?? 999),
            ];
        }
    }
    usort($summary, static function (array $a, array $b): int {
        if ((int)$a['priority'] === (int)$b['priority']) {
            return strcmp((string)$a['label'], (string)$b['label']);
        }
        return (int)$a['priority'] <=> (int)$b['priority'];
    });
    return array_slice($summary, 0, $limit);
}

function timeline_activity_taxonomy_label(array $item, array $meta): string
{
    $groupLabel = trim((string)($meta['catalog_group_label'] ?? ''));
    $phaseLabel = trim((string)($meta['catalog_phase_label'] ?? ''));
    $subtypeLabel = trim((string)($meta['subtype_label'] ?? ''));
    $document = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $mediaTagLabel = trim((string)($item['media_tag_label'] ?? ($document['media_tag_label'] ?? '')));
    $normalize = static function (string $value): string {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        return strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ä' => 'a',
            'ë' => 'e',
            'ï' => 'i',
            'ö' => 'o',
            'ü' => 'u',
        ]);
    };
    $parts = [];
    if ($groupLabel !== '') {
        $parts[] = $groupLabel;
    }
    if ($phaseLabel !== '') {
        $parts[] = $phaseLabel;
    } elseif ($mediaTagLabel !== '' && $normalize($mediaTagLabel) !== $normalize($groupLabel)) {
        $parts[] = $mediaTagLabel;
    } elseif ($subtypeLabel !== '' && $normalize($subtypeLabel) !== $normalize($groupLabel)) {
        $parts[] = $subtypeLabel;
    }
    return implode(' · ', $parts);
}

function timeline_normalize_label(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }
    return strtr($value, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ä' => 'a',
        'ë' => 'e',
        'ï' => 'i',
        'ö' => 'o',
        'ü' => 'u',
    ]);
}

function timeline_activity_title(array $item, array $meta): string
{
    $itemType = trim((string)($item['item_type'] ?? ''));
    $subtype = trim((string)($meta['subtype'] ?? ''));
    $group = trim((string)($meta['catalog_group'] ?? ''));
    $phase = trim((string)($meta['catalog_phase'] ?? ''));
    $document = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $documentType = strtolower(trim((string)($document['document_type'] ?? '')));
    $mediaTagLabel = trim((string)($item['media_tag_label'] ?? ($document['media_tag_label'] ?? '')));
    $mediaCaption = trim((string)($item['media_caption'] ?? ($document['media_caption'] ?? '')));
    $mediaBundleTitle = trim((string)($item['media_bundle_title'] ?? ($document['media_bundle_title'] ?? '')));

    if ($group === 'attention' && $subtype === 'appointment') {
        return 'Cita';
    }
    if ($group === 'attention' && $subtype === 'encounter') {
        return 'Atención';
    }
    if ($group === 'clinical' && ($subtype === 'note' || $subtype === 'note_evolution' || $documentType === 'note' || $documentType === 'nota_evolucion')) {
        return 'Nota de evolución';
    }
    if ($group === 'studies' && $phase === 'order') {
        return 'Orden de estudio';
    }
    if ($group === 'studies' && $phase === 'result') {
        return 'Resultado de estudio';
    }
    if ($group === 'multimedia') {
        if ($documentType === 'image') {
            if ($mediaBundleTitle !== '') {
                return $mediaBundleTitle;
            }
            if ($mediaCaption !== '') {
                return $mediaCaption;
            }
            if ($mediaTagLabel !== '') {
                return $mediaTagLabel;
            }
            return 'Imagen';
        }
        return 'Archivo';
    }
    if ($group === 'clinical') {
        return 'Documento clínico';
    }
    if ($group === 'documents') {
        return 'Archivo';
    }

    $fallback = trim((string)($meta['subtype_label'] ?? $meta['catalog_group_label'] ?? 'Evento clinico'));
    return $fallback !== '' ? $fallback : 'Evento clinico';
}

function timeline_activity_icon(array $item, array $meta): string
{
    $itemType = trim((string)($item['item_type'] ?? ''));
    $group = trim((string)($meta['catalog_group'] ?? ''));
    $phase = trim((string)($meta['catalog_phase'] ?? ''));
    $document = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $documentType = strtolower(trim((string)($document['document_type'] ?? '')));

    if ($itemType === 'appointment') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M8 3v4M16 3v4M4 9h16"></path></svg>';
    }
    if ($itemType === 'encounter' || $group === 'attention' || $group === 'clinical') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 4h6l1 2h2a2 2 0 0 1 2 2v9a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V8a2 2 0 0 1 2-2h2z"></path><path d="M12 10v6M9 13h6"></path></svg>';
    }
    if ($group === 'studies' && $phase === 'result') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 4h10v6l-3 3v5a2 2 0 0 1-4 0v-5l-3-3z"></path><path d="M9 4v2M15 4v2"></path></svg>';
    }
    if ($group === 'studies') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 4v7l-4 7a2 2 0 0 0 1.8 3h8.4A2 2 0 0 0 18 18l-4-7V4"></path><path d="M8 4h8"></path></svg>';
    }
    if ($group === 'multimedia' || $documentType === 'image') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><circle cx="9" cy="10" r="1.5"></circle><path d="M21 16l-5-5-7 7"></path></svg>';
    }
    if ($group === 'documents') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="2"></rect><path d="M8 9h8M8 13h8M8 17h5"></path></svg>';
    }

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="2"></rect><path d="M8 9h8M8 13h8M8 17h5"></path></svg>';
}

function timeline_activity_tooltip_lines(array $item, array $meta): array
{
    $lines = [];
    $taxonomy = timeline_activity_taxonomy_label($item, $meta);
    $document = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $mediaBundleTitle = trim((string)($item['media_bundle_title'] ?? ($document['media_bundle_title'] ?? '')));
    if ($taxonomy !== '') {
        $lines[] = $taxonomy;
    }
    if ($mediaBundleTitle !== '' && timeline_normalize_label($mediaBundleTitle) !== timeline_normalize_label($taxonomy)) {
        $lines[] = $mediaBundleTitle;
    }
    $eventDatetime = trim((string)($item['event_datetime'] ?? ''));
    if ($eventDatetime !== '') {
        $lines[] = $eventDatetime;
    }
    $caseTitle = trim((string)($item['case_title'] ?? ''));
    if ($caseTitle !== '') {
        $lines[] = 'Caso: ' . $caseTitle;
    }
    return array_slice($lines, 0, 3);
}

function timeline_is_bundleable_image(array $item, array $meta): bool
{
    if (trim((string)($item['item_type'] ?? '')) !== 'document') {
        return false;
    }
    $document = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $documentType = strtolower(trim((string)($document['document_type'] ?? '')));
    $bundleId = trim((string)($item['media_bundle_id'] ?? ($document['media_bundle_id'] ?? '')));
    return $documentType === 'image' && $bundleId !== '';
}

function render_embed_css(bool $embed): void
{
    if (!$embed) {
        return;
    }

    echo '<link rel="stylesheet" href="/assets/css/style.css">' . "\n";
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">' . "\n";
    echo '<link rel="stylesheet" href="/modules/_partials/mxmed-ui.css?v=1">' . "\n";
}

function fetch_http_json(string $url, int $timeoutSeconds = 4, int $maxAttempts = 2): array
{
    $last = [
        'ok' => false,
        'raw' => false,
        'status' => 0,
        'headers' => [],
        'error' => '',
        'body_snippet' => '',
        'attempts' => 0,
    ];

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: MXMed\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $headers = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : [];
        if (!is_array($headers)) {
            $headers = [];
        }
        $status = 0;
        if (!empty($headers) && preg_match('/^HTTP\/\S+\s+(\d{3})\b/', (string)$headers[0], $m) === 1) {
            $status = (int)$m[1];
        }
        $lastError = error_get_last();
        $errMsg = is_array($lastError) ? trim((string)($lastError['message'] ?? '')) : '';
        $snippet = is_string($raw) ? substr(trim($raw), 0, 600) : '';
        $isOk = is_string($raw) && $status >= 200 && $status < 300;

        $last = [
            'ok' => $isOk,
            'raw' => $raw,
            'status' => $status,
            'headers' => $headers,
            'error' => $errMsg,
            'body_snippet' => $snippet,
            'attempts' => $attempt,
        ];

        if ($isOk) {
            break;
        }
    }

    return $last;
}

function build_demo_timeline_items(): array
{
    // Fixtures demo para UX (sin dependencia de DB/API)
    return [
        [
            'item_type' => 'appointment',
            'encounter_key' => 'appt:9001',
            'event_datetime' => '2026-02-19 09:00:00',
            'agenda' => [
                'status' => 'confirmed',
                'start_at' => '2026-02-19 09:00:00',
                'end_at' => '2026-02-19 09:30:00',
                'modality' => 'presencial',
                'channel_origin' => 'public_web',
            ],
            'links' => ['appointment_id' => '9001'],
        ],
        [
            'item_type' => 'encounter',
            'encounter_key' => 'appt:9001',
            'event_datetime' => '2026-02-19 09:35:00',
            'clinical' => [
                'has_vitals' => true,
                'has_note' => true,
                'has_prescription' => true,
                'has_orders' => true,
                'has_results' => false,
                'documents' => [
                    ['document_type' => 'vitals'],
                    ['document_type' => 'note'],
                    ['document_type' => 'prescription'],
                    ['document_type' => 'orders'],
                ],
            ],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-19 09:12:00',
            'clinical_document' => ['document_type' => 'vitals', 'summary' => 'TA 120/80, FC 72, Temp 36.6'],
            'links' => ['appointment_id' => '9001', 'document_uuid' => 'demo-doc-9001-vitals'],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-19 09:18:00',
            'clinical_document' => ['document_type' => 'note', 'summary' => 'Dolor faríngeo de 3 días, sin fiebre.'],
            'links' => ['appointment_id' => '9001', 'document_uuid' => 'demo-doc-9001-note'],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-19 09:24:00',
            'clinical_document' => ['document_type' => 'prescription', 'summary' => 'Paracetamol 500mg cada 8h por 3 días.'],
            'links' => ['appointment_id' => '9001', 'document_uuid' => 'demo-doc-9001-rx'],
        ],
        [
            'item_type' => 'appointment',
            'encounter_key' => 'appt:9002',
            'event_datetime' => '2026-02-17 16:00:00',
            'agenda' => [
                'status' => 'completed',
                'start_at' => '2026-02-17 16:00:00',
                'end_at' => '2026-02-17 16:40:00',
                'modality' => 'teleconsulta',
                'channel_origin' => 'call_center',
            ],
            'links' => ['appointment_id' => '9002'],
        ],
        [
            'item_type' => 'encounter',
            'encounter_key' => 'appt:9002',
            'event_datetime' => '2026-02-17 16:45:00',
            'clinical' => [
                'has_vitals' => false,
                'has_note' => true,
                'has_prescription' => false,
                'has_orders' => true,
                'has_results' => true,
                'documents' => [
                    ['document_type' => 'note'],
                    ['document_type' => 'orders'],
                    ['document_type' => 'results'],
                ],
            ],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-17 16:20:00',
            'clinical_document' => ['document_type' => 'note', 'summary' => 'Control metabólico con mejoría parcial.'],
            'links' => ['appointment_id' => '9002', 'document_uuid' => 'demo-doc-9002-note'],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-17 16:33:00',
            'clinical_document' => ['document_type' => 'results', 'summary' => 'HbA1c 6.9%, glucosa en ayuno 122 mg/dL.'],
            'links' => ['appointment_id' => '9002', 'document_uuid' => 'demo-doc-9002-results'],
        ],
        [
            'item_type' => 'appointment',
            'encounter_key' => 'appt:9003',
            'event_datetime' => '2026-02-12 11:30:00',
            'agenda' => [
                'status' => 'cancelled',
                'start_at' => '2026-02-12 11:30:00',
                'end_at' => '2026-02-12 12:00:00',
                'modality' => 'presencial',
                'channel_origin' => 'doctor_assistant',
            ],
            'links' => ['appointment_id' => '9003'],
        ],
        [
            'item_type' => 'encounter',
            'encounter_key' => 'appt:9004',
            'event_datetime' => '2026-02-09 14:20:00',
            'clinical' => [
                'has_vitals' => true,
                'has_note' => false,
                'has_prescription' => true,
                'has_orders' => false,
                'has_results' => false,
                'documents' => [
                    ['document_type' => 'vitals'],
                    ['document_type' => 'prescription'],
                ],
            ],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-09 14:10:00',
            'clinical_document' => ['document_type' => 'vitals', 'summary' => 'Peso 82 kg, IMC 28.2.'],
            'links' => ['appointment_id' => '9004', 'document_uuid' => 'demo-doc-9004-vitals'],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-09 14:15:00',
            'clinical_document' => ['document_type' => 'prescription', 'summary' => 'Omeprazol 20mg cada 24h por 14 días.'],
            'links' => ['appointment_id' => '9004', 'document_uuid' => 'demo-doc-9004-rx'],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-01-28 08:40:00',
            'clinical_document' => ['document_type' => 'note', 'summary' => 'Nota externa sin cita ligada en sistema.'],
            'links' => ['appointment_id' => null, 'document_uuid' => 'demo-doc-orphan-01'],
        ],
    ];
}

$patientId = trim((string)($_GET['patient_id'] ?? ''));
$appointmentId = trim((string)($_GET['appointment_id'] ?? ''));
$encounterKey = trim((string)($_GET['encounter_key'] ?? ''));
$include = trim((string)($_GET['include'] ?? 'agenda,clinical'));
$limit = (int)($_GET['limit'] ?? 20);
$cursor = trim((string)($_GET['cursor'] ?? ''));
$direction = trim((string)($_GET['direction'] ?? ''));
$include = $include !== '' ? $include : 'agenda,clinical';
$limit = ($limit > 0 && $limit <= 200) ? $limit : 20;
require_once __DIR__ . '/../../_partials/clinical_embed.php';
$embed = is_embed_request();
$envClinicalApiBaseRaw = trim((string)getenv('CLINICAL_API_BASE'));
$clinicalApiBase = normalize_clinical_api_base($envClinicalApiBaseRaw);
if ($clinicalApiBase === '') {
    $clinicalApiBase = normalize_clinical_api_base(get_api_base());
}
if ($clinicalApiBase === '' || strpos($clinicalApiBase, '/') === 0) {
    $proto = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $proto = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = (string)$_SERVER['HTTP_X_FORWARDED_PROTO'];
    }
    $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
    $clinicalApiBase = $proto . '://' . $host;
}
$clinicalApiIndexBase = $clinicalApiBase . '/api/clinical/index.php';
// usar base raw para HTTP calls, nunca HTML-escaped

$errorMessage = '';
$errorTechnicalDetails = '';
$timelineUrlRaw = '';
$timelineUrlSafe = '';
$resolveErrorMsg = '';
$items = [];
$cursorNext = '';
$cursorPrev = '';
$activeCase = null;
$activeCaseError = '';
$caseAssignError = '';
$caseAssignSuccess = '';

if ($encounterKey === '' && $appointmentId !== '') {
    $encounterKey = 'appt:' . $appointmentId;
}

if ($patientId === '' && $encounterKey !== '') {
    $encodedEncounterKey = rawurlencode($encounterKey);
    $resolveUrl = $clinicalApiIndexBase . '/encounters/' . $encodedEncounterKey;
    $resolveContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $resolveRaw = @file_get_contents($resolveUrl, false, $resolveContext);
    if ($resolveRaw !== false) {
        $resolveDecoded = json_decode($resolveRaw, true);
        $resolveData = is_array($resolveDecoded['data'] ?? null) ? $resolveDecoded['data'] : [];
        $resolveLinks = is_array($resolveData['links'] ?? null) ? $resolveData['links'] : [];
        $resolvedPatientId = trim((string)($resolveLinks['patient_id'] ?? ($resolveData['patient_id'] ?? '')));
        if (is_array($resolveDecoded) && ($resolveDecoded['ok'] ?? false) === true && $resolvedPatientId !== '') {
            $redirectParams = [
                'patient_id' => $resolvedPatientId,
                'include' => $include,
                'limit' => $limit,
            ];
            if ($cursor !== '') {
                $redirectParams['cursor'] = $cursor;
            }
            if ($direction !== '') {
                $redirectParams['direction'] = $direction;
            }
            if ($embed) {
                $redirectParams['embed'] = '1';
            }
            header('Location: /modules/clinical/ui/historial.php?' . http_build_query($redirectParams));
            exit;
        }
    }
    $resolveErrorMsg = 'No se pudo resolver patient_id desde el encounter.';
}

if ($encounterKey === '' && $appointmentId !== '') {
    $encounterKey = 'appt:' . $appointmentId;
}

if ($patientId === '' && $encounterKey !== '') {
    $encodedEncounterKey = rawurlencode($encounterKey);
    $resolveUrl = $clinicalApiIndexBase . '/encounters/' . $encodedEncounterKey;
    $resolveContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $resolveRaw = @file_get_contents($resolveUrl, false, $resolveContext);
    if ($resolveRaw !== false) {
        $resolveDecoded = json_decode($resolveRaw, true);
        $resolveData = is_array($resolveDecoded['data'] ?? null) ? $resolveDecoded['data'] : [];
        $resolveLinks = is_array($resolveData['links'] ?? null) ? $resolveData['links'] : [];
        $resolvedPatientId = trim((string)($resolveLinks['patient_id'] ?? ($resolveData['patient_id'] ?? '')));
        if (is_array($resolveDecoded) && ($resolveDecoded['ok'] ?? false) === true && $resolvedPatientId !== '') {
            $redirectParams = [
                'patient_id' => $resolvedPatientId,
                'include' => $include,
                'limit' => $limit,
            ];
            if ($cursor !== '') {
                $redirectParams['cursor'] = $cursor;
            }
            if ($direction !== '') {
                $redirectParams['direction'] = $direction;
            }
            if ($embed) {
                $redirectParams['embed'] = '1';
            }
            header('Location: /modules/clinical/ui/historial.php?' . http_build_query($redirectParams));
            exit;
        }
    }
    $resolveErrorMsg = 'No se pudo resolver patient_id desde el encounter.';
}

if ($patientId !== '') {
    if ($patientId === 'demo') {
        $demoItems = build_demo_timeline_items();
        $items = array_values(array_filter($demoItems, static function (array $item) use ($include): bool {
            $type = (string)($item['item_type'] ?? '');
            if ($include === 'agenda') {
                return $type === 'appointment';
            }
            if ($include === 'clinical') {
                return $type === 'encounter' || $type === 'document';
            }
            return true;
        }));
        usort($items, static function (array $a, array $b): int {
            return strcmp((string)($b['event_datetime'] ?? ''), (string)($a['event_datetime'] ?? ''));
        });
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }
    } else {
        $query = [
            'include' => $include,
            'limit' => $limit,
        ];
        if ($cursor !== '') {
            $query['cursor'] = $cursor;
        }
        if ($direction !== '') {
            $query['direction'] = $direction;
        }

        $queryApi = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $timelineUrlRaw = $clinicalApiIndexBase . '/patients/' . rawurlencode($patientId) . '/timeline'
            . '?' . $queryApi;
        $timelineUrlSafe = h($timelineUrlRaw);

        // IMPORTANT: always use raw URL for HTTP calls (never HTML-escaped URL).
        $fetch = fetch_http_json($timelineUrlRaw, 4, 2);
        $raw = $fetch['raw'];
        $status = (int)($fetch['status'] ?? 0);
        $headers = is_array($fetch['headers'] ?? null) ? $fetch['headers'] : [];
        $attempts = (int)($fetch['attempts'] ?? 1);

        if ($raw === false) {
            $errorMessage = 'No se pudo cargar el historial. Verifique que el servicio clínico (API) esté activo y reintente.';
            $errorTechnicalDetails = "status: {$status}\ntimeline_url: {$timelineUrlSafe}\nenv_CLINICAL_API_BASE: " . ($envClinicalApiBaseRaw !== '' ? $envClinicalApiBaseRaw : '<empty>') . "\nnormalized_api_base: {$clinicalApiBase}\nattempts: {$attempts}\nerror: " . (string)($fetch['error'] ?? '') . "\nheaders:\n" . implode("\n", $headers);
        } elseif ($status >= 400) {
            $errorMessage = 'No se pudo cargar el historial. Verifique que el servicio clínico (API) esté activo y reintente.';
            $errorTechnicalDetails = "status: {$status}\ntimeline_url: {$timelineUrlSafe}\nenv_CLINICAL_API_BASE: " . ($envClinicalApiBaseRaw !== '' ? $envClinicalApiBaseRaw : '<empty>') . "\nnormalized_api_base: {$clinicalApiBase}\nattempts: {$attempts}\nheaders:\n" . implode("\n", $headers) . "\n\nbody_snippet:\n" . (string)($fetch['body_snippet'] ?? '');
        } else {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $errorMessage = 'No se pudo cargar el historial. Verifique que el servicio clínico (API) esté activo y reintente.';
                $errorTechnicalDetails = "status: {$status}\ntimeline_url: {$timelineUrlSafe}\nenv_CLINICAL_API_BASE: " . ($envClinicalApiBaseRaw !== '' ? $envClinicalApiBaseRaw : '<empty>') . "\nnormalized_api_base: {$clinicalApiBase}\nattempts: {$attempts}\nheaders:\n" . implode("\n", $headers) . "\n\nbody_snippet:\n" . (string)($fetch['body_snippet'] ?? '');
            } elseif (($decoded['ok'] ?? false) !== true) {
                $errorMessage = 'No se pudo cargar el historial. Verifique que el servicio clínico (API) esté activo y reintente.';
                $errorTechnicalDetails = "status: {$status}\ntimeline_url: {$timelineUrlSafe}\nenv_CLINICAL_API_BASE: " . ($envClinicalApiBaseRaw !== '' ? $envClinicalApiBaseRaw : '<empty>') . "\nnormalized_api_base: {$clinicalApiBase}\nattempts: {$attempts}\nheaders:\n" . implode("\n", $headers) . "\n\napi_message: " . (string)($decoded['message'] ?? '');
            } else {
                $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
                $list = $data['items'] ?? [];
                $items = is_array($list) ? $list : [];
                $range = is_array($data['range'] ?? null) ? $data['range'] : [];
                $cursorNext = trim((string)($range['cursor_next'] ?? ''));
                $cursorPrev = trim((string)($range['cursor_prev'] ?? ''));
            }
        }
    }
}

if ($patientId !== '') {
    $caseUrl = $clinicalApiIndexBase . '/patients/' . rawurlencode($patientId) . '/cases/active';
    $caseContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $caseRaw = @file_get_contents($caseUrl, false, $caseContext);
    if ($caseRaw !== false) {
        $caseDecoded = json_decode($caseRaw, true);
        if (is_array($caseDecoded) && ($caseDecoded['ok'] ?? false) === true) {
            $caseData = $caseDecoded['data'] ?? null;
            $activeCase = is_array($caseData) ? $caseData : null;
        } elseif (is_array($caseDecoded)) {
            $activeCaseError = trim((string)($caseDecoded['message'] ?? ''));
        }
    }
}

$filters = [
    'agenda,clinical' => 'Todo',
    'agenda' => 'Agenda',
    'clinical' => 'Clínico',
];

$encounters = [];
$encounterOrder = [];
$orphanDocs = [];
$appointmentItems = [];

foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $itemType = (string)($item['item_type'] ?? '');
    if ($itemType === 'encounter') {
        $ek = trim((string)($item['encounter_key'] ?? ''));
        if ($ek === '') {
            continue;
        }
        $links = is_array($item['links'] ?? null) ? $item['links'] : [];
        $appointmentInEncounter = trim((string)($links['appointment_id'] ?? ''));
        if ($appointmentInEncounter === '' && strpos($ek, 'appt:') === 0) {
            $appointmentInEncounter = appointment_id_from_encounter_key($ek);
        }
        $encounters[$ek] = [
            'encounter_key' => $ek,
            'event_datetime' => (string)($item['event_datetime'] ?? ''),
            'appointment_id' => ($appointmentInEncounter !== '' ? $appointmentInEncounter : null),
            'documents' => [],
            'raw' => $item,
        ];
        $encounterOrder[] = $ek;
    } elseif ($itemType === 'appointment') {
        $appointmentItems[] = $item;
    }
}

foreach ($items as $item) {
    if (!is_array($item) || (string)($item['item_type'] ?? '') !== 'document') {
        continue;
    }
    $links = is_array($item['links'] ?? null) ? $item['links'] : [];
    $appt = trim((string)($links['appointment_id'] ?? ''));
    if ($appt !== '') {
        $key = 'appt:' . $appt;
        if (isset($encounters[$key])) {
            $documentDt = timeline_date_only((string)($item['event_datetime'] ?? ''));
            $encounterDt = timeline_date_only((string)($encounters[$key]['event_datetime'] ?? ''));
            if ($documentDt !== '' && $encounterDt !== '' && $documentDt === $encounterDt) {
                $encounters[$key]['documents'][] = $item;
                continue;
            }
        }
    }
    $orphanDocs[] = $item;
}

$hasRenderableItems = ($appointmentItems !== []) || ($encounterOrder !== []) || ($orphanDocs !== []);
$activeCaseId = (is_array($activeCase) && isset($activeCase['case_id'])) ? (string)$activeCase['case_id'] : '';
if (trim((string)($_GET['flash'] ?? '')) === 'added_case_item') {
    $caseAssignSuccess = 'Agregado al caso activo';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'add_active_case_appointment') {
        $caseId = (int)$activeCaseId;
        $sourceEncounterKey = trim((string)($_POST['encounter_key'] ?? ''));
        $appointmentIdToAssign = appointment_id_from_encounter_key($sourceEncounterKey);
        if ($caseId <= 0) {
            $caseAssignError = 'No hay caso activo para asignar.';
        } elseif ($appointmentIdToAssign === '') {
            $caseAssignError = 'No se pudo obtener appointment_id para asignar.';
        } else {
            $assignUrl = $clinicalApiIndexBase . '/cases/' . rawurlencode((string)$caseId) . '/items';
            $assignPayload = json_encode([
                'item_type' => 'appointment',
                'item_ref' => 'appt:' . $appointmentIdToAssign,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($assignPayload)) {
                $assignPayload = '{"item_type":"appointment","item_ref":""}';
            }
            $assignContext = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'timeout' => 8,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nContent-Type: application/json\r\n",
                    'content' => $assignPayload,
                ],
            ]);
            $assignRaw = @file_get_contents($assignUrl, false, $assignContext);
            if (!is_string($assignRaw) || $assignRaw === '') {
                $caseAssignError = 'No se pudo agregar al caso activo.';
            } else {
                $assignDecoded = json_decode($assignRaw, true);
                if (!is_array($assignDecoded) || ($assignDecoded['ok'] ?? false) !== true) {
                    $caseAssignError = trim((string)($assignDecoded['message'] ?? 'No se pudo agregar al caso activo.'));
                    if ($caseAssignError === '') {
                        $caseAssignError = 'No se pudo agregar al caso activo.';
                    }
                } else {
                    $redirectParams = [
                        'patient_id' => $patientId,
                        'include' => $include,
                        'limit' => $limit,
                        'flash' => 'added_case_item',
                    ];
                    if ($cursor !== '') {
                        $redirectParams['cursor'] = $cursor;
                    }
                    if ($direction !== '') {
                        $redirectParams['direction'] = $direction;
                    }
                    if ($embed) {
                        $redirectParams['embed'] = '1';
                    }
                    if (trim((string)($_GET['debug'] ?? '')) === '1') {
                        $redirectParams['debug'] = '1';
                    }
                    header('Location: /modules/clinical/ui/historial.php?' . http_build_query($redirectParams));
                    exit;
                }
            }
        }
    }
}
$activeCaseItemsCount = is_array($activeCase)
    ? (int)($activeCase['items_count'] ?? 0)
    : 0;

$orphanDocMap = [];
foreach ($orphanDocs as $docItem) {
    if (!is_array($docItem)) {
        continue;
    }
    $orphanDocMap[timeline_item_uid($docItem)] = $docItem;
}

$renderEntries = [];
$bundleEntries = [];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $itemType = trim((string)($item['item_type'] ?? ''));
    if ($itemType === 'appointment') {
        $renderEntries[] = [
            'kind' => 'appointment',
            'item' => $item,
            'event_datetime' => (string)($item['event_datetime'] ?? ''),
            'day_key' => timeline_date_only((string)($item['event_datetime'] ?? '')),
            'category_meta' => timeline_item_catalog_meta($item),
        ];
        continue;
    }
    if ($itemType === 'encounter') {
        $encounterKey = trim((string)($item['encounter_key'] ?? ''));
        if ($encounterKey === '' || !isset($encounters[$encounterKey])) {
            continue;
        }
        $renderEntries[] = [
            'kind' => 'encounter',
            'item' => $encounters[$encounterKey]['raw'],
            'encounter' => $encounters[$encounterKey],
            'event_datetime' => (string)($encounters[$encounterKey]['event_datetime'] ?? ''),
            'day_key' => timeline_date_only((string)($encounters[$encounterKey]['event_datetime'] ?? '')),
            'category_meta' => timeline_item_catalog_meta($encounters[$encounterKey]['raw']),
        ];
        continue;
    }
    if ($itemType === 'document') {
        $uid = timeline_item_uid($item);
        if (!isset($orphanDocMap[$uid])) {
            continue;
        }
        $docItem = $orphanDocMap[$uid];
        $docMeta = timeline_item_catalog_meta($docItem);
        if (timeline_is_bundleable_image($docItem, $docMeta)) {
            $doc = is_array($docItem['clinical_document'] ?? null) ? $docItem['clinical_document'] : [];
            $bundleId = trim((string)($docItem['media_bundle_id'] ?? ($doc['media_bundle_id'] ?? '')));
            $dayKey = timeline_date_only((string)($item['event_datetime'] ?? ''));
            $bundleKey = $dayKey . '|' . $bundleId;
            if (!isset($bundleEntries[$bundleKey])) {
                $bundleEntries[$bundleKey] = [
                    'kind' => 'media_bundle',
                    'item' => $docItem,
                    'bundle_items' => [],
                    'bundle_count' => 0,
                    'event_datetime' => (string)($item['event_datetime'] ?? ''),
                    'day_key' => $dayKey,
                    'category_meta' => $docMeta,
                ];
            }
            $bundleEntries[$bundleKey]['bundle_items'][] = $docItem;
            $bundleEntries[$bundleKey]['bundle_count'] += 1;
            continue;
        }
        $renderEntries[] = [
            'kind' => 'document',
            'item' => $docItem,
            'event_datetime' => (string)($item['event_datetime'] ?? ''),
            'day_key' => timeline_date_only((string)($item['event_datetime'] ?? '')),
            'category_meta' => $docMeta,
        ];
    }
}

foreach ($bundleEntries as $bundleEntry) {
    if (!is_array($bundleEntry)) {
        continue;
    }
    $bundleItems = is_array($bundleEntry['bundle_items'] ?? null) ? $bundleEntry['bundle_items'] : [];
    if ($bundleItems === []) {
        continue;
    }
    usort($bundleItems, static function (array $a, array $b): int {
        return strcmp((string)($a['event_datetime'] ?? ''), (string)($b['event_datetime'] ?? ''));
    });
    $bundleEntry['bundle_items'] = $bundleItems;
    $bundleEntry['item'] = $bundleItems[0];
    $bundleEntry['bundle_count'] = count($bundleItems);
    $renderEntries[] = $bundleEntry;
}

usort($renderEntries, static function (array $a, array $b): int {
    $dtCmp = strcmp((string)($b['event_datetime'] ?? ''), (string)($a['event_datetime'] ?? ''));
    if ($dtCmp !== 0) {
        return $dtCmp;
    }
    return strcmp((string)($b['kind'] ?? ''), (string)($a['kind'] ?? ''));
});

$dayGroups = [];
$dayOrder = [];
$availableCategoryFilters = [];
foreach ($renderEntries as $entry) {
    $dayKey = trim((string)($entry['day_key'] ?? ''));
    if ($dayKey === '') {
        $dayKey = 'unknown';
    }
    if (!isset($dayGroups[$dayKey])) {
        $dayGroups[$dayKey] = [
            'day_key' => $dayKey,
            'day_label' => timeline_day_label($dayKey === 'unknown' ? '' : $dayKey),
            'entries' => [],
            'summary' => [],
        ];
        $dayOrder[] = $dayKey;
    }
    $dayGroups[$dayKey]['entries'][] = $entry;

    $categoryMeta = is_array($entry['category_meta'] ?? null) ? $entry['category_meta'] : [];
    $catalogGroup = trim((string)($categoryMeta['catalog_group'] ?? ''));
    if ($catalogGroup !== '') {
        $availableCategoryFilters[$catalogGroup] = [
            'catalog_group' => $catalogGroup,
            'label' => (string)($categoryMeta['catalog_group_label'] ?? $catalogGroup),
            'priority' => (int)($categoryMeta['catalog_priority'] ?? 999),
        ];
    }
}
foreach ($dayOrder as $dayKey) {
    $dayGroups[$dayKey]['summary'] = timeline_category_summary($dayGroups[$dayKey]['entries']);
}
$availableCategoryFilters = array_values($availableCategoryFilters);
usort($availableCategoryFilters, static function (array $a, array $b): int {
    if ((int)$a['priority'] === (int)$b['priority']) {
        return strcmp((string)$a['label'], (string)$b['label']);
    }
    return (int)$a['priority'] <=> (int)$b['priority'];
});
$timelineCategoryPriorityMap = mxmed_clinical_timeline_group_priority_map();

$buildCursorHref = static function (string $nextCursor) use ($patientId, $include, $limit, $direction): string {
    $params = [
        'patient_id' => $patientId,
        'include' => $include,
        'limit' => $limit,
        'cursor' => $nextCursor,
    ];
    if ($direction !== '') {
        $params['direction'] = $direction;
    }
    return '?' . carry_embed_params($params);
};
// Shell MXMed
$pageTitle = 'Historial de atención';
$extraHead = <<<'HTML'
  <style>
    .clinical-historial .mm-chip{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:.25rem .55rem;
      border-radius:999px;
      font-weight:600;
      font-size:.78rem;
      border:1px solid rgba(0,0,0,.08);
    }
    .clinical-historial .mm-chip.is-on{
      background-color:var(--mm-header-top, #EAF6FB) !important;
      border-color:var(--mm-borde-input, #00B0C5) !important;
      color:var(--mm-barra-vigencia, #003152) !important;
    }
    .clinical-historial .mm-chip.is-off{
      background-color:#fff !important;
      color:#6c757d !important;
      opacity:.55;
      border-color:rgba(0,0,0,.08) !important;
    }
    .clinical-historial .mm-chip .dot{
      width:8px;
      height:8px;
      border-radius:50%;
      background-color:var(--mm-acc-activo, #00738F) !important;
      flex:0 0 auto;
    }
    .clinical-historial .mm-chip.is-off .dot{
      background-color:#adb5bd !important;
    }
    .clinical-historial .is-in-active-case{
      border-left: 4px solid var(--mm-borde-input, #00B0C5);
      background: linear-gradient(90deg, rgba(0,176,197,.06) 0%, rgba(0,176,197,0) 24%);
    }
    .clinical-historial .only-active-case-note{
      background: var(--mm-header-top, #EAF6FB);
      border: 1px solid rgba(0,176,197,.35);
      color: var(--mm-barra-vigencia, #003152);
      border-radius: .6rem;
      padding: .5rem .75rem;
      font-size: .875rem;
      margin-bottom: .75rem;
    }
    .clinical-historial .encounter-doc-preview{
      border: 1px solid rgba(0,0,0,.08);
      border-radius: .5rem;
      padding: .5rem .6rem;
      background: #fff;
    }
    .clinical-historial .encounter-doc-preview .doc-line{
      padding: .25rem 0;
      border-bottom: 1px dashed rgba(0,0,0,.08);
      font-size: .88rem;
    }
    .clinical-historial .encounter-doc-preview .doc-line:last-child{
      border-bottom: 0;
      padding-bottom: 0;
    }
    .clinical-historial .timeline-day-card{
      border: 1px solid rgba(0,0,0,.08);
      border-radius: .85rem;
      background: linear-gradient(180deg, rgba(0,176,197,.04) 0%, rgba(255,255,255,1) 26%);
      padding: .9rem;
    }
    .clinical-historial .timeline-day-header{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:.75rem;
      margin-bottom:.85rem;
    }
    .clinical-historial .timeline-day-events{
      display:flex;
      flex-direction:column;
      gap:.65rem;
    }
    .clinical-historial .timeline-event{
      border: 0;
      background: transparent;
    }
    .clinical-historial .mm-activity-item{
      display:flex;
      align-items:flex-start;
      gap:12px;
      border:1px solid #e5e7eb;
      border-radius:10px;
      padding:9px 12px;
      background:#fff;
      transition:all .15s ease;
      cursor:pointer;
    }
    .clinical-historial .mm-activity-item:hover{
      box-shadow:0 4px 14px rgba(0,0,0,.06);
      border-color:#d1d5db;
    }
    .clinical-historial .mm-activity-icon{
      width:24px;
      height:24px;
      flex-shrink:0;
      color:#374151;
    }
    .clinical-historial .mm-activity-icon svg{
      width:24px;
      height:24px;
      display:block;
    }
    .clinical-historial .mm-activity-body{
      flex:1;
      min-width:0;
    }
    .clinical-historial .mm-activity-title{
      font-weight:600;
      font-size:.95rem;
      line-height:1.15;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .clinical-historial .mm-activity-meta{
      font-size:.76rem;
      color:#6b7280;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
      margin-top:1px;
    }
    .clinical-historial .mm-activity-day-header{
      font-weight:600;
      font-size:1rem;
      color:#374151;
      margin-bottom:0;
    }
    .clinical-historial .mm-activity-actions{
      display:flex;
      flex-wrap:wrap;
      gap:.4rem;
      margin-top:.4rem;
    }
    .clinical-historial .timeline-taxonomy-chip{
      border-radius:999px;
      font-weight:600;
      padding:.24rem .5rem;
      font-size:.72rem;
    }
    .clinical-historial .timeline-category-summary{
      display:flex;
      flex-wrap:wrap;
      gap:.35rem;
    }
    .clinical-historial .timeline-category-filters{
      display:flex;
      flex-wrap:wrap;
      gap:.5rem;
      margin-bottom:1rem;
    }
    .clinical-historial [data-role="doc-overlay"]{
      position: fixed;
      inset: 0;
      z-index: 1060;
    }
    .clinical-historial [data-role="doc-overlay"][hidden]{
      display: none !important;
    }
    .clinical-historial [data-role="doc-overlay-backdrop"]{
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,.55);
    }
    .clinical-historial [data-role="doc-overlay-panel"]{
      position: relative;
      width: min(1200px, calc(100vw - 2rem));
      height: 90vh;
      margin: 5vh auto;
      background: #fff;
      border-radius: .75rem;
      border: 1px solid rgba(0,0,0,.08);
      box-shadow: 0 20px 40px rgba(0,0,0,.25);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .clinical-historial [data-role="doc-overlay-head"]{
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      padding: .65rem .85rem;
      border-bottom: 1px solid rgba(0,0,0,.1);
    }
    .clinical-historial [data-role="doc-overlay-iframe"]{
      width: 100%;
      height: 100%;
      border: 0;
      flex: 1 1 auto;
      background: #fff;
    }
    .tooltip .tooltip-inner{
      white-space:pre-line;
      text-align:left;
    }
  </style>
HTML;
if (!$embed) {
    require_once __DIR__ . '/../../_partials/mm_shell_top.php';
} else {
    render_embed_css($embed);
    echo $extraHead;
    clinical_embed_start();
}
?>
<div class="clinical-historial">
<div class="<?php echo $embed ? 'py-1' : 'container py-4'; ?>">
  <?php if (!$embed): ?>
    <h1 class="h4 mb-1">Historial de atención</h1>
    <p class="text-secondary mb-3">patient_id: <code><?php echo h($patientId !== '' ? $patientId : '-'); ?></code></p>

    <form class="row g-2 mb-3" method="get">
      <div class="col-12 col-md-8">
        <label for="patient_id" class="form-label">Patient ID</label>
        <input id="patient_id" name="patient_id" class="form-control" value="<?php echo h($patientId); ?>" required>
        <?php echo carry_embed_hidden_input(); ?>
      </div>
      <div class="col-12 col-md-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">Cargar historial de atención</button>
      </div>
    </form>
  <?php else: ?>
    <?php // Modo embed: ocultar encabezado y formulario (UX integrado) ?>
  <?php endif; ?>

  <div class="btn-group mb-3" role="group" aria-label="Filtros del historial de atención" data-role="timeline-filters">
    <?php foreach ($filters as $filterValue => $filterLabel): ?>
      <?php
      $isActive = ($include === $filterValue);
      $href = '?' . carry_embed_params([
          'patient_id' => $patientId,
          'include' => $filterValue,
          'limit' => $limit,
      ]);
      ?>
      <a class="btn <?php echo $isActive ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo h($href); ?>">
        <?php echo h($filterLabel); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($patientId !== ''): ?>
    <div class="mm-card mb-3<?php echo $activeCaseId === '' ? ' d-none' : ''; ?>" data-role="case-summary-panel">
      <div class="body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <?php if (is_array($activeCase) && $activeCase !== []): ?>
          <div>
            <strong><?php echo h((string)($activeCase['title'] ?? 'Caso clínico')); ?></strong>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <span class="small text-secondary align-self-center" data-role="active-case-counter">Items en este caso: <?php echo h((string)$activeCaseItemsCount); ?></span>
            <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-success" data-action="toggle-only-active-case">Ver solo este caso</button>
            <button
              type="button"
              class="mm-btn mm-btn-sm mm-btn-outline-primary"
              data-action="rename-active-case"
              data-case-id="<?php echo h((string)($activeCase['case_id'] ?? '')); ?>"
            >Renombrar</button>
            <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="open-cases-modal" data-role="open-cases-btn">Ver casos</button>
          </div>
        <?php else: ?>
          <div>
            <span class="text-secondary">Casos clínicos disponibles.</span>
          </div>
          <div>
            <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="open-cases-modal" data-role="open-cases-btn">Ver casos</button>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="alert alert-info d-none py-2 mb-3" data-role="recent-case-suggestion">
      <div data-role="recent-case-suggestion-text"></div>
      <div class="small text-secondary mt-1" data-role="recent-case-suggestion-subtext">Puedes agruparlos para mantener el expediente organizado.</div>
      <div class="mt-2 d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-primary" data-action="assign-recent-to-active-case">Agrupar recientes</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="snooze-recent-case-suggestion">No por ahora</button>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($resolveErrorMsg !== ''): ?>
    <div class="alert alert-danger"><?php echo h($resolveErrorMsg); ?></div>
  <?php endif; ?>
  <?php if ($activeCaseError !== ''): ?>
    <div class="alert alert-warning py-2"><?php echo h($activeCaseError); ?></div>
  <?php endif; ?>
  <?php if ($caseAssignSuccess !== ''): ?>
    <div class="alert alert-success py-2"><?php echo h($caseAssignSuccess); ?></div>
  <?php endif; ?>
  <?php if ($caseAssignError !== ''): ?>
    <div class="alert alert-danger py-2"><?php echo h($caseAssignError); ?></div>
  <?php endif; ?>

  <?php if ($patientId === ''): ?>
    <?php if ($embed): ?>
      <div class="alert alert-info py-2 mb-2">Sin <code>patient_id</code>.</div>
    <?php else: ?>
      <div class="alert alert-info">Captura un <code>patient_id</code> para consultar el historial de atención.</div>
    <?php endif; ?>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger">
      <?php echo h($errorMessage); ?>
      <?php if ($errorTechnicalDetails !== ''): ?>
        <details class="mt-2">
          <summary>Detalles técnicos</summary>
          <pre class="mb-0 mt-2 small"><?php echo h($errorTechnicalDetails); ?></pre>
        </details>
      <?php endif; ?>
    </div>
  <?php elseif (!$hasRenderableItems): ?>
    <div class="alert alert-secondary">Sin eventos (no hay encuentros ni documentos)</div>
  <?php else: ?>
    <?php if ($cursorNext !== '' || $cursorPrev !== ''): ?>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php if ($cursorNext !== ''): ?>
          <a class="mm-btn mm-btn-sm mm-btn-outline-primary" href="<?php echo h($buildCursorHref($cursorNext)); ?>">Más reciente</a>
        <?php endif; ?>
        <?php if ($cursorPrev !== ''): ?>
          <a class="mm-btn mm-btn-sm mm-btn-outline-primary" href="<?php echo h($buildCursorHref($cursorPrev)); ?>">Más antiguo</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="only-active-case-note d-none" data-role="only-active-case-note">Mostrando solo items del caso activo.</div>
    <button type="button" class="btn btn-link btn-sm px-0 mb-2 text-decoration-none" data-action="toggle-advanced-filters">Ver opciones avanzadas</button>
    <div class="btn-group btn-group-sm mb-3 d-none" role="group" aria-label="Filtro por caso activo" data-role="case-scope-filter">
      <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary active" data-action="set-case-scope" data-case-scope="all">Todos</button>
      <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-case-scope" data-case-scope="in">Solo caso activo</button>
      <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-case-scope" data-case-scope="out">Fuera de caso</button>
    </div>
    <div class="alert alert-secondary d-none py-2 mb-3" data-role="case-scope-empty">Sin eventos del caso activo.</div>
    <div class="timeline-category-filters" data-role="timeline-category-filters">
      <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtros clínicos">
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary active" data-action="set-clinical-filter" data-clinical-filter="all">Todo</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="cita">Citas</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="consulta">Consultas</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="receta">Recetas</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="estudio">Estudios</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="documento">Documentos</button>
      </div>
      <div class="btn-group btn-group-sm flex-wrap d-none" role="group" aria-label="Subfiltros de estudios" data-role="timeline-study-filters">
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary active" data-action="set-study-filter" data-study-filter="all">Todos</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-study-filter" data-study-filter="orden">Órdenes</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-study-filter" data-study-filter="resultado">Resultados</button>
      </div>
    </div>
    <div class="vstack gap-3">
      <?php foreach ($dayOrder as $dayKey): ?>
        <?php $dayGroup = $dayGroups[$dayKey]; ?>
        <section class="timeline-day-card" data-day-card="1" data-day-key="<?php echo h((string)$dayGroup['day_key']); ?>">
          <div class="timeline-day-header">
            <div>
              <div class="mm-activity-day-header"><?php echo h((string)$dayGroup['day_label']); ?></div>
            </div>
          </div>
          <div class="timeline-day-events">
            <?php foreach ($dayGroup['entries'] as $entry): ?>
              <?php
              $entryItem = is_array($entry['item'] ?? null) ? $entry['item'] : [];
              $categoryMeta = is_array($entry['category_meta'] ?? null) ? $entry['category_meta'] : [];
              $entryCategory = trim((string)($categoryMeta['category'] ?? 'other'));
              $entrySubtype = trim((string)($categoryMeta['subtype'] ?? 'unknown'));
              $entryCatalogGroup = trim((string)($categoryMeta['catalog_group'] ?? 'other'));
              $entryCatalogPhase = trim((string)($categoryMeta['catalog_phase'] ?? ''));
              $entryCatalogGroupLabel = trim((string)($categoryMeta['catalog_group_label'] ?? 'Otros'));
              $entryCatalogPhaseLabel = trim((string)($categoryMeta['catalog_phase_label'] ?? ''));
              $entryCatalogPriority = (int)($categoryMeta['catalog_priority'] ?? 999);
              $entryChipText = trim((string)($categoryMeta['chip_text'] ?? $entryCatalogGroupLabel));
              $entryTitle = timeline_activity_title($entryItem, $categoryMeta);
              $entryIcon = timeline_activity_icon($entryItem, $categoryMeta);
              $entryTooltipLines = timeline_activity_tooltip_lines($entryItem, $categoryMeta);
              $entryTooltipText = implode("\n", $entryTooltipLines);
              $entryTooltipFallback = implode(' • ', $entryTooltipLines);
              ?>
              <?php if (($entry['kind'] ?? '') === 'appointment'): ?>
                <?php
                $item = $entryItem;
                $agenda = is_array($item['agenda'] ?? null) ? $item['agenda'] : [];
                $links = is_array($item['links'] ?? null) ? $item['links'] : [];
                $appointmentRef = trim((string)($links['appointment_id'] ?? ''));
                $isInActiveCase = (bool)($item['is_in_active_case'] ?? false);
                $itemCaseId = trim((string)($item['case_id'] ?? ''));
                $appointmentHasEncounter = (bool)($item['has_encounter'] ?? false);
                $appointmentLatestEncounterKey = trim((string)($item['latest_encounter_key'] ?? ''));
                $appointmentEpisodeId = trim((string)(($item['links']['appointment_id'] ?? '')));
                $appointmentEncounterKey = trim((string)($item['encounter_key'] ?? ''));
                if ($appointmentEpisodeId === '') {
                    $appointmentEpisodeId = trim((string)($agenda['appointment_id'] ?? ''));
                }
                if ($appointmentEpisodeId === '' && strpos($appointmentEncounterKey, 'appt:') === 0) {
                    $appointmentEpisodeId = substr($appointmentEncounterKey, 5);
                    $hashPos = strpos($appointmentEpisodeId, '#enc:');
                    if ($hashPos !== false) {
                        $appointmentEpisodeId = substr($appointmentEpisodeId, 0, $hashPos);
                    }
                    $appointmentEpisodeId = trim((string)$appointmentEpisodeId);
                }
                $appointmentHref = '';
                if ($appointmentEpisodeId !== '') {
                    $appointmentHref = '/index.html#p-agenda';
                }
                ?>
                <article class="mm-card timeline-event mm-activity-item <?php echo $isInActiveCase ? 'is-in-active-case' : ''; ?>" data-timeline-item="1" data-role="timeline-item" data-case-id="<?php echo h($itemCaseId); ?>" data-in-active-case="<?php echo $isInActiveCase ? '1' : '0'; ?>" data-item-type="appointment" data-item-ref="<?php echo h($appointmentRef); ?>" data-encounter-key="<?php echo h($appointmentEncounterKey); ?>" data-category="<?php echo h($entryCategory); ?>" data-subtype="<?php echo h($entrySubtype); ?>" data-catalog-group="<?php echo h($entryCatalogGroup); ?>" data-catalog-phase="<?php echo h($entryCatalogPhase); ?>" data-catalog-group-label="<?php echo h($entryCatalogGroupLabel); ?>" data-catalog-priority="<?php echo $entryCatalogPriority; ?>" data-clinical-category="<?php echo h(trim((string)($item['clinical_category'] ?? ''))); ?>" data-study-role="<?php echo h(trim((string)($item['study_role'] ?? ''))); ?>" data-href="<?php echo h($appointmentHref); ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo h($entryTooltipText); ?>" title="<?php echo h($entryTooltipFallback); ?>">
                  <div class="mm-activity-icon" aria-hidden="true"><?php echo $entryIcon; ?></div>
                  <div class="mm-activity-body">
                    <div class="min-w-0 flex-grow-1">
                      <div class="mm-activity-title"><?php echo h($entryTitle); ?></div>
                      <?php if (trim((string)($item['case_title'] ?? '')) !== ''): ?>
                        <div class="mm-activity-meta">Caso: <?php echo h((string)$item['case_title']); ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="mm-activity-actions" data-role="appointment-episode-cta" data-appointment-id="<?php echo h($appointmentEpisodeId); ?>">
                      <?php if (!$isInActiveCase && $appointmentRef !== ''): ?>
                        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-primary" data-action="integrate-to-case" data-item-type="appointment" data-item-ref="<?php echo h($appointmentRef); ?>">Integrar a caso clínico</button>
                      <?php endif; ?>
                      <?php if (!$isInActiveCase && is_array($activeCase) && $appointmentRef !== ''): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Agregar esta cita al caso activo?');">
                          <input type="hidden" name="action" value="add_active_case_appointment">
                          <input type="hidden" name="encounter_key" value="<?php echo h($appointmentEncounterKey); ?>">
                          <button type="submit" class="mm-btn mm-btn-sm mm-btn-outline-success">Agregar a caso activo</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php elseif (($entry['kind'] ?? '') === 'encounter'): ?>
                <?php
                $encounter = is_array($entry['encounter'] ?? null) ? $entry['encounter'] : [];
                $rawEncounter = is_array($encounter['raw'] ?? null) ? $encounter['raw'] : [];
                $clinical = is_array($rawEncounter['clinical'] ?? null) ? $rawEncounter['clinical'] : [];
                $clinicalDocsLegacy = is_array($clinical['documents'] ?? null) ? $clinical['documents'] : [];
                $clinicalDocsPreview = is_array($clinical['documents_preview'] ?? null) ? $clinical['documents_preview'] : [];
                $types = [];
                foreach (($clinicalDocsPreview !== [] ? $clinicalDocsPreview : $clinicalDocsLegacy) as $d) {
                    if (is_array($d)) {
                        $t = trim((string)($d['document_type'] ?? ''));
                        if ($t !== '') {
                            $types[$t] = true;
                        }
                    }
                }
                $hasVitals = (bool)($clinical['has_vitals'] ?? false);
                $hasNote = (bool)($clinical['has_note'] ?? false);
                $hasPrescription = (bool)($clinical['has_prescription'] ?? false);
                $hasOrders = (bool)($clinical['has_orders'] ?? false);
                $hasResults = (bool)($clinical['has_results'] ?? false);
                $docsInEncounter = is_array($encounter['documents'] ?? null) ? $encounter['documents'] : [];
                $encounterDocCount = array_key_exists('documents_count', $clinical) ? (int)$clinical['documents_count'] : count($clinicalDocsLegacy);
                $encounterPreviewDocs = ($clinicalDocsPreview !== []) ? array_slice($clinicalDocsPreview, 0, 3) : array_slice($clinicalDocsLegacy, 0, 3);
                $ek = trim((string)($rawEncounter['encounter_key'] ?? ($encounter['encounter_key'] ?? '')));
                $encCaseId = trim((string)($rawEncounter['case_id'] ?? ''));
                $encInActiveCase = (bool)($rawEncounter['is_in_active_case'] ?? false);
                $encHasEncounter = (bool)($rawEncounter['has_encounter'] ?? true);
                $encLatestEncounterKey = trim((string)($rawEncounter['latest_encounter_key'] ?? $ek));
                $encounterHref = '';
                if ($encHasEncounter && $encLatestEncounterKey !== '') {
                    $encounterHref = '/modules/clinical/ui/encounter.php?' . carry_embed_params(['encounter_key' => $encLatestEncounterKey]);
                }
                ?>
                <article class="mm-card timeline-event mm-activity-item <?php echo $encInActiveCase ? 'is-in-active-case' : ''; ?>" data-timeline-item="1" data-role="timeline-item" data-case-id="<?php echo h($encCaseId); ?>" data-in-active-case="<?php echo $encInActiveCase ? '1' : '0'; ?>" data-item-type="encounter" data-item-ref="<?php echo h($ek); ?>" data-encounter-key="<?php echo h($ek); ?>" data-category="<?php echo h($entryCategory); ?>" data-subtype="<?php echo h($entrySubtype); ?>" data-catalog-group="<?php echo h($entryCatalogGroup); ?>" data-catalog-phase="<?php echo h($entryCatalogPhase); ?>" data-catalog-group-label="<?php echo h($entryCatalogGroupLabel); ?>" data-catalog-priority="<?php echo $entryCatalogPriority; ?>" data-clinical-category="<?php echo h(trim((string)($rawEncounter['clinical_category'] ?? ''))); ?>" data-study-role="<?php echo h(trim((string)($rawEncounter['study_role'] ?? ''))); ?>" data-href="<?php echo h($encounterHref); ?>" data-nav-mode="<?php echo $encounterHref !== '' ? 'encounter' : ''; ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo h($entryTooltipText); ?>" title="<?php echo h($entryTooltipFallback); ?>">
                  <div class="mm-activity-icon" aria-hidden="true"><?php echo $entryIcon; ?></div>
                  <div class="mm-activity-body">
                    <div class="min-w-0 flex-grow-1">
                      <div class="mm-activity-title"><?php echo h($entryTitle); ?></div>
                      <?php if (trim((string)($rawEncounter['case_title'] ?? '')) !== ''): ?>
                        <div class="mm-activity-meta">Caso: <?php echo h((string)$rawEncounter['case_title']); ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="mm-activity-actions">
                      <?php if (!$encInActiveCase && $ek !== ''): ?>
                        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-primary" data-action="integrate-to-case" data-item-type="encounter" data-item-ref="<?php echo h($ek); ?>">Integrar a caso clínico</button>
                      <?php endif; ?>
                      <?php if (!$encInActiveCase && is_array($activeCase) && $ek !== ''): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Agregar esta cita al caso activo?');">
                          <input type="hidden" name="action" value="add_active_case_appointment">
                          <input type="hidden" name="encounter_key" value="<?php echo h($ek); ?>">
                          <button type="submit" class="mm-btn mm-btn-sm mm-btn-outline-success">Agregar a caso activo</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php elseif (($entry['kind'] ?? '') === 'document'): ?>
                <?php
                $docItem = $entryItem;
                $doc = is_array($docItem['clinical_document'] ?? null) ? $docItem['clinical_document'] : [];
                $links = is_array($docItem['links'] ?? null) ? $docItem['links'] : [];
                $docUuid = trim((string)($links['document_uuid'] ?? ''));
                $docCaseId = trim((string)($docItem['case_id'] ?? ''));
                $docInActiveCase = (bool)($docItem['is_in_active_case'] ?? false);
                $docTypeNorm = strtolower(trim((string)($doc['document_type'] ?? '')));
                $docPayload = is_array($doc['payload'] ?? null) ? $doc['payload'] : [];
                $docFilePayload = is_array($docPayload['file'] ?? null) ? $docPayload['file'] : [];
                $docRenderMode = strtolower(trim((string)($doc['render_mode'] ?? ($docFilePayload['render_mode'] ?? ''))));
                $docIsImage = ($docTypeNorm === 'image' || $docRenderMode === 'image');
                $docViewPath = $docIsImage ? '/modules/clinical/ui/viewer.php' : '/modules/clinical/ui/document.php';
                $docHref = $docUuid !== '' ? $docViewPath . '?' . carry_embed_params(['uuid' => $docUuid]) : '';
                ?>
                <article class="mm-card timeline-event mm-activity-item <?php echo $docInActiveCase ? 'is-in-active-case' : ''; ?>" data-timeline-item="1" data-role="timeline-item" data-case-id="<?php echo h($docCaseId); ?>" data-in-active-case="<?php echo $docInActiveCase ? '1' : '0'; ?>" data-item-type="document" data-item-ref="<?php echo h($docUuid); ?>" data-document-uuid="<?php echo h($docUuid); ?>" data-category="<?php echo h($entryCategory); ?>" data-subtype="<?php echo h($entrySubtype); ?>" data-catalog-group="<?php echo h($entryCatalogGroup); ?>" data-catalog-phase="<?php echo h($entryCatalogPhase); ?>" data-catalog-group-label="<?php echo h($entryCatalogGroupLabel); ?>" data-catalog-priority="<?php echo $entryCatalogPriority; ?>" data-clinical-category="<?php echo h(trim((string)($docItem['clinical_category'] ?? ''))); ?>" data-study-role="<?php echo h(trim((string)($docItem['study_role'] ?? ''))); ?>" data-href="<?php echo h($docHref); ?>" data-nav-mode="<?php echo $docHref !== '' ? 'document' : ''; ?>" data-doc-target="<?php echo $docIsImage ? 'image' : 'document'; ?>" data-uuid="<?php echo h($docUuid); ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo h($entryTooltipText); ?>" title="<?php echo h($entryTooltipFallback); ?>">
                  <div class="mm-activity-icon" aria-hidden="true"><?php echo $entryIcon; ?></div>
                  <div class="mm-activity-body">
                    <div class="min-w-0 flex-grow-1">
                      <div class="mm-activity-title"><?php echo h($entryTitle); ?></div>
                      <?php if (trim((string)($docItem['case_title'] ?? '')) !== ''): ?>
                        <div class="mm-activity-meta">Caso: <?php echo h((string)$docItem['case_title']); ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="mm-activity-actions">
                      <?php if (!$docInActiveCase && $docUuid !== ''): ?>
                        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-primary" data-action="integrate-to-case" data-item-type="document" data-item-ref="<?php echo h($docUuid); ?>">Integrar a caso clínico</button>
                      <?php endif; ?>
                      <?php if (!$docInActiveCase && $activeCaseId !== '' && $docUuid !== ''): ?>
                        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-success" data-action="assign-case-item" data-case-id="<?php echo h($activeCaseId); ?>" data-item-type="document" data-item-ref="<?php echo h($docUuid); ?>">Agregar a caso activo</button>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php elseif (($entry['kind'] ?? '') === 'media_bundle'): ?>
                <?php
                $bundleItem = $entryItem;
                $bundleDoc = is_array($bundleItem['clinical_document'] ?? null) ? $bundleItem['clinical_document'] : [];
                $bundleItems = is_array($entry['bundle_items'] ?? null) ? $entry['bundle_items'] : [];
                $bundleCount = max(1, (int)($entry['bundle_count'] ?? count($bundleItems)));
                $bundleUuid = trim((string)($bundleItem['links']['document_uuid'] ?? ''));
                $bundleCaseId = trim((string)($bundleItem['case_id'] ?? ''));
                $bundleInActiveCase = (bool)($bundleItem['is_in_active_case'] ?? false);
                $bundleTitle = trim((string)($bundleItem['media_bundle_title'] ?? ($bundleDoc['media_bundle_title'] ?? '')));
                $bundleTagLabel = trim((string)($bundleItem['media_tag_label'] ?? ($bundleDoc['media_tag_label'] ?? '')));
                $bundleNote = trim((string)($bundleItem['media_bundle_note'] ?? ($bundleDoc['media_bundle_note'] ?? '')));
                $bundleId = trim((string)($bundleItem['media_bundle_id'] ?? ($bundleDoc['media_bundle_id'] ?? '')));
                $bundleHref = $bundleId !== '' ? ('/modules/clinical/ui/viewer.php?' . carry_embed_params([
                    'bundle_id' => $bundleId,
                    'patient_id' => $patientId,
                ])) : ($bundleUuid !== '' ? ('/modules/clinical/ui/viewer.php?' . carry_embed_params(['uuid' => $bundleUuid])) : '');
                $bundleDisplayTitle = $bundleTitle !== '' ? $bundleTitle : ($bundleTagLabel !== '' ? $bundleTagLabel : 'Imagen');
                $bundleMetaParts = [];
                $bundleNotes = is_array($bundleItem['bundle_notes'] ?? null) ? $bundleItem['bundle_notes'] : [];
                $hasNotes = (bool)($bundleNotes['has_notes'] ?? false);
                $notesExcerpt = trim((string)($bundleNotes['excerpt'] ?? ''));
                if (trim((string)($bundleItem['case_title'] ?? '')) !== '') {
                    $bundleMetaParts[] = 'Caso: ' . trim((string)$bundleItem['case_title']);
                }
                if ($bundleNote !== '') {
                    $bundleMetaParts[] = $bundleNote;
                } else {
                    $bundleMetaParts[] = $bundleCount . ' archivos';
                }
                $bundleMetaText = implode(' · ', $bundleMetaParts);
                ?>
                <article class="mm-card timeline-event mm-activity-item <?php echo $bundleInActiveCase ? 'is-in-active-case' : ''; ?>" data-timeline-item="1" data-role="timeline-item" data-case-id="<?php echo h($bundleCaseId); ?>" data-in-active-case="<?php echo $bundleInActiveCase ? '1' : '0'; ?>" data-item-type="document" data-item-ref="<?php echo h($bundleUuid); ?>" data-document-uuid="<?php echo h($bundleUuid); ?>" data-category="<?php echo h($entryCategory); ?>" data-subtype="<?php echo h($entrySubtype); ?>" data-catalog-group="<?php echo h($entryCatalogGroup); ?>" data-catalog-phase="<?php echo h($entryCatalogPhase); ?>" data-catalog-group-label="<?php echo h($entryCatalogGroupLabel); ?>" data-catalog-priority="<?php echo $entryCatalogPriority; ?>" data-clinical-category="<?php echo h(trim((string)($bundleItem['clinical_category'] ?? ''))); ?>" data-study-role="<?php echo h(trim((string)($bundleItem['study_role'] ?? ''))); ?>" data-href="<?php echo h($bundleHref); ?>" data-nav-mode="<?php echo $bundleHref !== '' ? 'document' : ''; ?>" data-doc-target="image" data-uuid="<?php echo h($bundleUuid); ?>" data-bundle-id="<?php echo h($bundleId); ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo h($entryTooltipText); ?>" title="<?php echo h($entryTooltipFallback); ?>">
                  <div class="mm-activity-icon" aria-hidden="true"><?php echo $entryIcon; ?></div>
                  <div class="mm-activity-body">
                    <div class="min-w-0 flex-grow-1">
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="mm-activity-title"><?php echo h($bundleDisplayTitle); ?></div>
                        <?php if ($hasNotes): ?>
                          <span class="badge rounded-pill text-bg-secondary">Notas clínicas</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($bundleMetaText !== ''): ?>
                        <div class="mm-activity-meta"><?php echo h($bundleMetaText); ?></div>
                      <?php endif; ?>
                      <?php if ($hasNotes && $notesExcerpt !== ''): ?>
                        <div class="small text-secondary mt-1"><?php echo h($notesExcerpt); ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="mm-activity-actions">
                      <?php if (!$bundleInActiveCase && $bundleUuid !== ''): ?>
                        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-primary" data-action="integrate-to-case" data-item-type="document" data-item-ref="<?php echo h($bundleUuid); ?>">Integrar a caso clínico</button>
                      <?php endif; ?>
                      <?php if (!$bundleInActiveCase && $activeCaseId !== '' && $bundleUuid !== ''): ?>
                        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-success" data-action="assign-case-item" data-case-id="<?php echo h($activeCaseId); ?>" data-item-type="document" data-item-ref="<?php echo h($bundleUuid); ?>">Agregar a caso activo</button>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div
    class="modal fade"
    id="encounterDetailModal"
    data-role="encounter-detail-modal"
    tabindex="-1"
    aria-hidden="true"
  >
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalle de atención</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" data-action="close-encounter-detail-modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div id="encounterDetailLoading" data-role="encounter-detail-loading" class="text-secondary small d-none">Cargando detalle...</div>
          <div id="encounterDetailError" data-role="encounter-detail-error" class="alert alert-danger d-none mb-2">No se pudo cargar el detalle del encounter.</div>
          <div id="encounterDetailMeta" data-role="encounter-detail-meta" class="small text-secondary mb-2 d-none"></div>
          <div id="encounterDetailList" data-role="encounter-detail-list" class="vstack gap-2"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-bs-dismiss="modal" data-action="close-encounter-detail-modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
  <div data-role="doc-overlay" hidden aria-hidden="true">
    <div data-role="doc-overlay-backdrop"></div>
    <div data-role="doc-overlay-panel" role="dialog" aria-modal="true" aria-label="Documento">
      <div data-role="doc-overlay-head">
        <strong data-role="doc-overlay-title">Documento</strong>
        <div class="d-flex flex-wrap gap-2">
          <a class="mm-btn mm-btn-sm mm-btn-outline-primary" data-role="doc-overlay-open-new" href="#" target="_blank" rel="noopener">Abrir en pestaña</a>
          <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-role="doc-overlay-close">Cerrar</button>
        </div>
      </div>
      <div data-role="doc-overlay-loader" class="small text-secondary px-3 py-2 d-none">Cargando…</div>
      <iframe data-role="doc-overlay-iframe" src="about:blank" loading="lazy"></iframe>
    </div>
  </div>
</div>
</div>
<script src="/modules/clinical/ui/_shared/clinical_doc_render.js"></script>
<script src="/modules/clinical/ui/_shared/clinical_doc_overlay.js"></script>
<script src="/modules/clinical/ui/_shared/clinical_embed_kit.js"></script>
<div class="modal fade" id="clinicalCasesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Casos clínicos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="casesModalLoading" class="text-secondary small d-none">Cargando casos...</div>
        <div id="casesModalEmpty" class="alert alert-secondary d-none mb-0">Sin casos clínicos.</div>
        <div id="casesModalList" class="vstack gap-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-bs-dismiss="modal" data-action="close-cases-modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="clinicalCreateCaseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Integrar a caso clínico</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger small d-none" data-role="integrate-case-error"></div>
        <div class="vstack gap-3">
          <div>
            <div class="fw-semibold mb-2">Casos existentes</div>
            <div class="text-secondary small mb-2 d-none" data-role="integrate-case-loading">Cargando casos...</div>
            <div class="alert alert-secondary small d-none mb-2" data-role="integrate-case-empty">Sin casos clínicos disponibles.</div>
            <div class="vstack gap-2" data-role="integrate-case-list"></div>
          </div>
          <div class="border-top pt-3">
            <div class="fw-semibold mb-2">Crear nuevo caso</div>
            <label for="clinicalCreateCaseTitle" class="form-label">Nombre del caso</label>
            <input
              type="text"
              class="form-control"
              id="clinicalCreateCaseTitle"
              data-role="create-case-title"
              placeholder="Ej. Fractura tibia y peroné"
              maxlength="190"
            >
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-bs-dismiss="modal" data-action="cancel-create-case">Cancelar</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-primary" data-action="confirm-integrate-case">Integrar</button>
        <button type="button" class="btn btn-sm btn-primary" data-action="confirm-create-case">Crear e integrar</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="clinicalDocumentModal" data-role="document-viewer-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-role="document-viewer-title">Documento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" data-action="close-document-viewer-modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div data-role="document-viewer-loading" class="text-secondary small d-none">Cargando documento...</div>
        <div data-role="document-viewer-error" class="alert alert-danger d-none mb-2">No se pudo cargar el documento.</div>
        <div data-role="document-viewer-body" class="vstack gap-2"></div>
      </div>
      <div class="modal-footer d-flex flex-wrap gap-2">
        <a class="mm-btn mm-btn-sm mm-btn-outline-primary" data-role="document-viewer-open-new" href="#" target="_blank" rel="noopener">Abrir en pestaña</a>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="copy-document-link">Copiar enlace</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="print-document-link">Imprimir</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-bs-dismiss="modal" data-action="close-document-viewer-modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<script>
  (function () {
    var patientId = <?php echo json_encode($patientId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var apiBase = <?php echo json_encode($clinicalApiBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var activeCaseId = <?php echo json_encode($activeCaseId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var isEmbed = <?php echo $embed ? 'true' : 'false'; ?>;
    var onlyActiveCaseStorageKey = 'mxmed_historial_only_active_case:' + String(patientId || '');
    var casesModalEl = document.getElementById('clinicalCasesModal');
    var casesModalList = document.getElementById('casesModalList');
    var casesModalEmpty = document.getElementById('casesModalEmpty');
    var casesModalLoading = document.getElementById('casesModalLoading');
    var casesModalInstance = null;
    if (casesModalEl && window.bootstrap && window.bootstrap.Modal) {
      casesModalInstance = window.bootstrap.Modal.getOrCreateInstance(casesModalEl);
    }
    var createCaseModalEl = document.getElementById('clinicalCreateCaseModal');
    var createCaseTitleInput = document.querySelector('[data-role="create-case-title"]');
    var integrateCaseError = document.querySelector('[data-role="integrate-case-error"]');
    var integrateCaseList = document.querySelector('[data-role="integrate-case-list"]');
    var integrateCaseLoading = document.querySelector('[data-role="integrate-case-loading"]');
    var integrateCaseEmpty = document.querySelector('[data-role="integrate-case-empty"]');
    var integrateCaseConfirmBtn = document.querySelector('[data-action="confirm-integrate-case"]');
    var createCaseConfirmBtn = document.querySelector('[data-action="confirm-create-case"]');
    var createCaseModalInstance = null;
    if (createCaseModalEl && window.bootstrap && window.bootstrap.Modal) {
      createCaseModalInstance = window.bootstrap.Modal.getOrCreateInstance(createCaseModalEl);
    }
    var onlyActiveCaseBtn = document.querySelector('[data-action="toggle-only-active-case"]');
    var onlyActiveCaseNotice = document.querySelector('[data-role="only-active-case-note"]');
    var caseScopeFilterWrap = document.querySelector('[data-role="case-scope-filter"]');
    var categoryFilterWrap = document.querySelector('[data-role="timeline-category-filters"]');
    var studyFilterWrap = document.querySelector('[data-role="timeline-study-filters"]');
    var caseSummaryPanel = document.querySelector('[data-role="case-summary-panel"]');
    var openCasesButtons = document.querySelectorAll('[data-role="open-cases-btn"]');
    var advancedFiltersToggle = document.querySelector('[data-action="toggle-advanced-filters"]');
    var caseScopeEmpty = document.querySelector('[data-role="case-scope-empty"]');
    var recentSuggestion = document.querySelector('[data-role="recent-case-suggestion"]');
    var recentSuggestionText = document.querySelector('[data-role="recent-case-suggestion-text"]');
    var advancedFiltersVisible = false;
    var knownCasesCount = activeCaseId !== '' ? 1 : 0;
    var onlyActiveCaseEnabled = false;
    var caseScope = 'all';
    var clinicalCategoryFilter = 'all';
    var studyRoleFilter = 'all';
    var categoryPriorityMap = <?php echo json_encode($timelineCategoryPriorityMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var encounterDetailModalEl = document.querySelector('[data-role="encounter-detail-modal"]');
    var encounterDetailLoading = document.querySelector('[data-role="encounter-detail-loading"]');
    var encounterDetailError = document.querySelector('[data-role="encounter-detail-error"]');
    var encounterDetailMeta = document.querySelector('[data-role="encounter-detail-meta"]');
    var encounterDetailList = document.querySelector('[data-role="encounter-detail-list"]');
    var encounterDetailModalInstance = null;
    if (encounterDetailModalEl && window.bootstrap && window.bootstrap.Modal) {
      encounterDetailModalInstance = window.bootstrap.Modal.getOrCreateInstance(encounterDetailModalEl);
    }
    var documentViewerModalEl = document.querySelector('[data-role="document-viewer-modal"]');
    var documentViewerTitle = document.querySelector('[data-role="document-viewer-title"]');
    var documentViewerLoading = document.querySelector('[data-role="document-viewer-loading"]');
    var documentViewerError = document.querySelector('[data-role="document-viewer-error"]');
    var documentViewerBody = document.querySelector('[data-role="document-viewer-body"]');
    var documentViewerOpenNew = document.querySelector('[data-role="document-viewer-open-new"]');
    var documentViewerModalInstance = null;
    if (documentViewerModalEl && window.bootstrap && window.bootstrap.Modal) {
      documentViewerModalInstance = window.bootstrap.Modal.getOrCreateInstance(documentViewerModalEl);
    }
    var activeDocumentUrl = '';
    var debugMode = false;
    try {
      debugMode = new URLSearchParams(window.location.search || '').get('debug') === '1';
    } catch (_) {
      debugMode = false;
    }
    var recentCandidates = [];
    var recentSuggestStorageKey = 'mxmed_historial_snooze_suggest:' + String(patientId || '');
    var pendingCaseIntegration = null;
    var createCaseSubmitting = false;
    var integrateCaseSubmitting = false;
    try {
      onlyActiveCaseEnabled = activeCaseId !== '' && localStorage.getItem(onlyActiveCaseStorageKey) === '1';
    } catch (_) {
      onlyActiveCaseEnabled = false;
    }

    function applyTimelineCaseScopeFilter() {
      if (!caseScopeFilterWrap) return;
      var buttons = caseScopeFilterWrap.querySelectorAll('[data-action="set-case-scope"]');
      buttons.forEach(function (btn) {
        var scope = String(btn.getAttribute('data-case-scope') || '').trim();
        btn.classList.toggle('active', scope === caseScope);
      });
    }

    function initActivityTooltips() {
      if (!window.bootstrap || !window.bootstrap.Tooltip) return;
      var nodes = document.querySelectorAll('.mm-activity-item[data-bs-toggle="tooltip"]');
      nodes.forEach(function (node) {
        window.bootstrap.Tooltip.getOrCreateInstance(node, {
          trigger: 'hover focus',
          container: 'body'
        });
      });
    }

    function navigateTimelineItem(itemEl) {
      if (!itemEl) return;
      var href = String(itemEl.getAttribute('data-href') || '').trim();
      if (!href) return;
      var mode = String(itemEl.getAttribute('data-nav-mode') || '').trim();
      if ((mode === 'encounter' || mode === 'document') && isEmbed && window.parent && window.parent !== window && typeof window.parent.postMessage === 'function') {
        var payload = { type: 'mxmed:embed:navigate', mode: mode };
        if (mode === 'encounter') {
          var encounterKey = String(itemEl.getAttribute('data-encounter-key') || '').trim();
          if (!encounterKey) return;
          payload.encounter_key = encounterKey;
        } else {
          var uuid = String(itemEl.getAttribute('data-uuid') || '').trim();
          var bundleId = String(itemEl.getAttribute('data-bundle-id') || '').trim();
          if (!uuid) return;
          payload.uuid = uuid;
          if (bundleId) payload.bundle_id = bundleId;
          payload.href = href;
        }
        window.parent.postMessage(payload, '*');
        return;
      }
      window.location.href = href;
    }

    function applyTimelineCategoryFilter() {
      if (!categoryFilterWrap) return;
      var buttons = categoryFilterWrap.querySelectorAll('[data-action="set-clinical-filter"]');
      buttons.forEach(function (btn) {
        var value = String(btn.getAttribute('data-clinical-filter') || '').trim();
        btn.classList.toggle('active', value === clinicalCategoryFilter);
      });
      if (!studyFilterWrap) return;
      studyFilterWrap.classList.toggle('d-none', clinicalCategoryFilter !== 'estudio');
      var studyButtons = studyFilterWrap.querySelectorAll('[data-action="set-study-filter"]');
      studyButtons.forEach(function (btn) {
        var value = String(btn.getAttribute('data-study-filter') || '').trim();
        btn.classList.toggle('active', value === studyRoleFilter);
      });
    }

    function updateDayCardVisibility() {
      var dayCards = document.querySelectorAll('[data-day-card="1"]');
      dayCards.forEach(function (card) {
        var visibleEvents = Array.from(card.querySelectorAll('[data-timeline-item="1"]')).filter(function (item) {
          return !item.classList.contains('d-none');
        });
        card.classList.toggle('d-none', visibleEvents.length === 0);

        var summaryWrap = card.querySelector('[data-role="day-category-summary"]');
        if (!summaryWrap) return;
        var categoryMap = {};
        visibleEvents.forEach(function (item) {
          var category = String(item.getAttribute('data-catalog-group') || '').trim();
          if (!category) return;
          if (!categoryMap[category]) {
            categoryMap[category] = {
              category: category,
              label: String(item.getAttribute('data-catalog-group-label') || category).trim(),
              priority: Number(item.getAttribute('data-catalog-priority') || categoryPriorityMap[category] || 999)
            };
          }
        });
        var categories = Object.keys(categoryMap).map(function (key) { return categoryMap[key]; });
        categories.sort(function (a, b) {
          if (a.priority === b.priority) return a.label.localeCompare(b.label);
          return a.priority - b.priority;
        });
        summaryWrap.innerHTML = '';
        categories.slice(0, 3).forEach(function (meta) {
          var chip = document.createElement('span');
          chip.className = 'badge rounded-pill text-bg-light border';
          chip.setAttribute('data-category-summary-item', '1');
          chip.setAttribute('data-catalog-group', meta.category);
          chip.setAttribute('data-catalog-group-label', meta.label);
          chip.setAttribute('data-catalog-priority', String(meta.priority));
          chip.textContent = meta.label;
          summaryWrap.appendChild(chip);
        });
      });
    }

    function applyOnlyActiveCaseFilter() {
      var timelineItems = document.querySelectorAll('[data-timeline-item="1"]');
      var visibleCount = 0;
      timelineItems.forEach(function (item) {
        var inActiveCase = String(item.getAttribute('data-in-active-case') || '').trim() === '1';
        var itemClinicalCategory = String(item.getAttribute('data-clinical-category') || '').trim();
        var itemStudyRole = String(item.getAttribute('data-study-role') || '').trim();
        var hide = false;

        if (onlyActiveCaseEnabled && activeCaseId !== '') {
          hide = !inActiveCase;
        }
        if (!hide) {
          if (caseScope === 'in') {
            hide = !inActiveCase;
          } else if (caseScope === 'out') {
            hide = inActiveCase;
          }
        }
        if (!hide && clinicalCategoryFilter !== 'all') {
          hide = itemClinicalCategory !== clinicalCategoryFilter;
          if (!hide && clinicalCategoryFilter === 'estudio' && studyRoleFilter !== 'all') {
            hide = itemStudyRole !== studyRoleFilter;
          }
        }

        item.classList.toggle('d-none', hide);
        if (!hide) {
          visibleCount += 1;
        }
      });
      if (onlyActiveCaseNotice) {
        onlyActiveCaseNotice.classList.toggle('d-none', !onlyActiveCaseEnabled || activeCaseId === '');
      }
      if (onlyActiveCaseBtn) {
        onlyActiveCaseBtn.textContent = (onlyActiveCaseEnabled && activeCaseId !== '') ? 'Ver todos' : 'Ver solo este caso';
      }
      applyTimelineCaseScopeFilter();
      applyTimelineCategoryFilter();
      updateDayCardVisibility();
      if (caseScopeEmpty) {
        var showEmpty = visibleCount === 0;
        if (clinicalCategoryFilter === 'estudio' && studyRoleFilter !== 'all') {
          caseScopeEmpty.textContent = 'Sin eventos para el subfiltro de estudios seleccionado.';
        } else if (clinicalCategoryFilter !== 'all') {
          caseScopeEmpty.textContent = 'Sin eventos para la categoría clínica seleccionada.';
        } else if (caseScope === 'in') {
          caseScopeEmpty.textContent = 'Sin eventos del caso activo.';
        } else if (caseScope === 'out') {
          caseScopeEmpty.textContent = 'Sin eventos fuera de caso.';
        } else {
          caseScopeEmpty.textContent = 'Sin eventos visibles.';
        }
        caseScopeEmpty.classList.toggle('d-none', !showEmpty);
      }
    }

    function setOnlyActiveCaseEnabled(nextValue) {
      onlyActiveCaseEnabled = !!nextValue && activeCaseId !== '';
      try {
        if (activeCaseId === '') {
          localStorage.removeItem(onlyActiveCaseStorageKey);
        } else {
          localStorage.setItem(onlyActiveCaseStorageKey, onlyActiveCaseEnabled ? '1' : '0');
        }
      } catch (_) {}
      applyOnlyActiveCaseFilter();
    }

    async function bootstrapCaseSummary() {
      if (!patientId) {
        updateCaseSummaryVisibility();
        return;
      }
      if (activeCaseId !== '') {
        updateCaseSummaryVisibility();
        return;
      }
      try {
        var cases = await listCases(patientId);
        knownCasesCount = cases.length;
      } catch (_) {
        knownCasesCount = 0;
      }
      updateCaseSummaryVisibility();
    }

    function recentSnoozed() {
      try {
        var ts = Number(localStorage.getItem(recentSuggestStorageKey) || '0');
        if (!Number.isFinite(ts) || ts <= 0) return false;
        return (Date.now() - ts) < (24 * 60 * 60 * 1000);
      } catch (_) {
        return false;
      }
    }

    function computeRecentCandidates() {
      if (!activeCaseId) return [];
      var nodes = Array.from(document.querySelectorAll('[data-timeline-item="1"]')).slice(0, 10);
      return nodes.map(function (node) {
        return {
          caseId: String(node.getAttribute('data-case-id') || '').trim(),
          inActiveCase: String(node.getAttribute('data-in-active-case') || '').trim() === '1',
          itemType: String(node.getAttribute('data-item-type') || '').trim(),
          itemRef: String(node.getAttribute('data-item-ref') || '').trim()
        };
      }).filter(function (item) {
        return !item.inActiveCase && item.itemType !== '' && item.itemRef !== '';
      });
    }

    function renderRecentSuggestion() {
      if (!recentSuggestion || !recentSuggestionText) return;
      recentCandidates = computeRecentCandidates();
      var show = activeCaseId !== '' && recentCandidates.length > 0 && !recentSnoozed();
      recentSuggestion.classList.toggle('d-none', !show);
      if (show) {
        recentSuggestionText.textContent = 'Hay ' + recentCandidates.length + ' episodios sin agrupar en casos clínicos.';
      }
    }

    function updateCaseSummaryVisibility() {
      var showPanel = activeCaseId !== '' || knownCasesCount > 0;
      if (caseSummaryPanel) {
        caseSummaryPanel.classList.toggle('d-none', !showPanel);
      }
      openCasesButtons.forEach(function (btn) {
        btn.classList.toggle('d-none', knownCasesCount < 1);
      });
    }

    function applyAdvancedFiltersVisibility() {
      if (!caseScopeFilterWrap || !advancedFiltersToggle) return;
      caseScopeFilterWrap.classList.toggle('d-none', !advancedFiltersVisible);
      advancedFiltersToggle.textContent = advancedFiltersVisible ? 'Ocultar opciones avanzadas' : 'Ver opciones avanzadas';
    }

    async function apiJson(url, options) {
      var response = await fetch(url, Object.assign({
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      }, options || {}));
      var payload = null;
      try {
        payload = await response.json();
      } catch (_) {
        payload = null;
      }
      if (!payload || payload.ok !== true) {
        var message = (payload && payload.message) ? String(payload.message) : ('HTTP ' + response.status);
        var err = new Error(message);
        err.status = response.status;
        err.code = payload && payload.error && payload.error.code ? String(payload.error.code) : '';
        err.data = payload && payload.data ? payload.data : null;
        throw err;
      }
      return payload;
    }

    async function loadActiveCase(pid) {
      if (!pid) return null;
      var url = apiBase + '/api/clinical/index.php/patients/' + encodeURIComponent(pid) + '/cases/active';
      var payload = await apiJson(url, { method: 'GET' });
      return payload.data || null;
    }

    async function createCase(pid, title) {
      var url = apiBase + '/api/clinical/index.php/patients/' + encodeURIComponent(pid) + '/cases';
      var payload = await apiJson(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ patient_id: pid, title: title || 'Caso clínico' }),
        credentials: 'same-origin'
      });
      return payload.data || null;
    }

    async function renameCase(caseId, title) {
      var url = apiBase + '/api/clinical/index.php/cases/' + encodeURIComponent(String(caseId || ''));
      var payload = await apiJson(url, {
        method: 'PATCH',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ title: title }),
        credentials: 'same-origin'
      });
      return payload.data || null;
    }

    async function listCases(pid) {
      if (!pid) return [];
      var url = apiBase + '/api/clinical/index.php/patients/' + encodeURIComponent(pid) + '/cases';
      var payload = await apiJson(url, { method: 'GET' });
      return Array.isArray(payload.data) ? payload.data : [];
    }

    function setCasesModalLoading(flag) {
      if (!casesModalLoading) return;
      casesModalLoading.classList.toggle('d-none', !flag);
    }

    function renderCases(cases) {
      if (!casesModalList || !casesModalEmpty) return;
      casesModalList.innerHTML = '';
      var list = Array.isArray(cases) ? cases : [];
      casesModalEmpty.classList.toggle('d-none', list.length > 0);
      list.forEach(function (item) {
        var row = document.createElement('div');
        row.className = 'border rounded p-2 d-flex flex-wrap justify-content-between align-items-center gap-2';
        var caseId = String(item.case_id || '').trim();
        var title = String(item.title || 'Caso clínico').trim();
        var active = String(item.status || '').trim() === 'active';
        row.innerHTML = ''
          + '<div>'
          + '  <div class="fw-semibold">' + title.replace(/</g, '&lt;') + '</div>'
          + '  <div class="small text-secondary">#' + caseId + ' · ' + (item.updated_at || '-') + '</div>'
          + '</div>'
          + '<div class="d-flex flex-wrap gap-2">'
          + (active ? '' : '<button type="button" class="mm-btn mm-btn-sm mm-btn-outline-primary" data-action="activate-case" data-case-id="' + caseId + '">Activar</button>')
          + '  <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="rename-case-from-modal" data-case-id="' + caseId + '" data-case-title="' + title.replace(/"/g, '&quot;') + '">Renombrar</button>'
          + '</div>';
        casesModalList.appendChild(row);
      });
    }

    async function openCasesModal() {
      if (!patientId) {
        window.alert('patient_id requerido para listar casos.');
        return;
      }
      if (casesModalInstance) {
        casesModalInstance.show();
      } else if (casesModalEl) {
        casesModalEl.style.display = 'block';
        casesModalEl.classList.add('show');
      }
      setCasesModalLoading(true);
      try {
        var cases = await listCases(patientId);
        knownCasesCount = cases.length;
        updateCaseSummaryVisibility();
        renderCases(cases);
      } catch (err) {
        window.alert(err.message || 'No se pudieron listar casos clínicos');
      } finally {
        setCasesModalLoading(false);
      }
    }

    async function assignItem(caseId, itemType, itemRef) {
      var url = apiBase + '/api/clinical/index.php/cases/' + encodeURIComponent(String(caseId || '')) + '/items';
      var payload = await apiJson(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ item_type: itemType, item_ref: itemRef }),
        credentials: 'same-origin'
      });
      return payload.data || null;
    }

    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function selectedIntegrateCaseId() {
      if (!integrateCaseList) return '';
      var selected = integrateCaseList.querySelector('input[name="integrate_case_id"]:checked');
      return selected ? String(selected.value || '').trim() : '';
    }

    function syncIntegrateCaseButtons() {
      if (integrateCaseConfirmBtn) {
        integrateCaseConfirmBtn.disabled = integrateCaseSubmitting || createCaseSubmitting || !selectedIntegrateCaseId();
        integrateCaseConfirmBtn.textContent = integrateCaseSubmitting ? 'Integrando...' : 'Integrar';
      }
      if (createCaseConfirmBtn) {
        createCaseConfirmBtn.disabled = integrateCaseSubmitting || createCaseSubmitting;
        createCaseConfirmBtn.textContent = createCaseSubmitting ? 'Creando...' : 'Crear e integrar';
      }
    }

    function setIntegrateCaseLoading(flag) {
      if (integrateCaseLoading) {
        integrateCaseLoading.classList.toggle('d-none', !flag);
      }
      syncIntegrateCaseButtons();
    }

    function setIntegrateCaseError(message, ownerCaseId) {
      if (!integrateCaseError) return;
      var text = String(message || '').trim();
      integrateCaseError.innerHTML = '';
      if (text === '') {
        integrateCaseError.classList.add('d-none');
        return;
      }
      var copy = document.createElement('div');
      copy.textContent = text;
      integrateCaseError.appendChild(copy);
      if (ownerCaseId) {
        var actions = document.createElement('div');
        actions.className = 'd-flex flex-wrap gap-2 mt-2';
        actions.innerHTML = ''
          + '<button type="button" class="mm-btn mm-btn-sm mm-btn-outline-primary" data-action="activate-owner-case" data-case-id="' + escapeHtml(ownerCaseId) + '">Activar caso #' + escapeHtml(ownerCaseId) + '</button>'
          + '<button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="dismiss-integrate-case-error">Cerrar</button>';
        integrateCaseError.appendChild(actions);
      }
      integrateCaseError.classList.remove('d-none');
    }

    function renderIntegrateCases(cases) {
      if (!integrateCaseList || !integrateCaseEmpty) return;
      integrateCaseList.innerHTML = '';
      var list = Array.isArray(cases) ? cases : [];
      integrateCaseEmpty.classList.toggle('d-none', list.length > 0);
      list.forEach(function (item, index) {
        var row = document.createElement('label');
        row.className = 'border rounded p-2 d-flex gap-3 align-items-start';
        var caseId = String(item.case_id || '').trim();
        var title = String(item.title || 'Caso clínico').trim();
        var updatedAt = String(item.updated_at || '-').trim();
        var itemsCount = (item && item.items_count !== undefined && item.items_count !== null)
          ? String(item.items_count).trim()
          : '';
        var active = String(item.status || '').trim() === 'active';
        var checked = (activeCaseId !== '' && caseId === String(activeCaseId))
          || (activeCaseId === '' && index === 0);
        row.innerHTML = ''
          + '<input class="form-check-input mt-1" type="radio" name="integrate_case_id" value="' + escapeHtml(caseId) + '"' + (checked ? ' checked' : '') + '>'
          + '<div class="flex-grow-1">'
          + '  <div class="d-flex flex-wrap align-items-center gap-2">'
          + '    <span class="fw-semibold">' + escapeHtml(title) + '</span>'
          + '  </div>'
          + '  <div class="small text-secondary mt-1">#' + escapeHtml(caseId) + ' · ' + escapeHtml(updatedAt) + (itemsCount !== '' ? ' · items: ' + escapeHtml(itemsCount) : '') + '</div>'
          + '</div>';
        integrateCaseList.appendChild(row);
      });
      syncIntegrateCaseButtons();
    }

    async function activateCase(caseId) {
      return apiJson(apiBase + '/api/clinical/index.php/cases/' + encodeURIComponent(String(caseId || '')) + '/activate', { method: 'POST' });
    }

    async function loadIntegrateCaseChoices() {
      if (!patientId) {
        setIntegrateCaseError('patient_id requerido para listar casos.');
        return;
      }
      setIntegrateCaseLoading(true);
      setIntegrateCaseError('');
      try {
        var cases = await listCases(patientId);
        knownCasesCount = cases.length;
        updateCaseSummaryVisibility();
        renderIntegrateCases(cases);
      } catch (err) {
        setIntegrateCaseError(err.message || 'No se pudieron cargar los casos clínicos.');
      } finally {
        setIntegrateCaseLoading(false);
      }
    }

    async function integrateToCase(caseId, itemType, itemRef) {
      var nextCaseId = String(caseId || '').trim();
      var nextItemType = String(itemType || '').trim();
      var nextItemRef = String(itemRef || '').trim();
      if (!nextCaseId || !nextItemType || !nextItemRef) return;
      integrateCaseSubmitting = true;
      setIntegrateCaseError('');
      syncIntegrateCaseButtons();
      try {
        await assignItem(nextCaseId, nextItemType, nextItemRef);
        window.location.reload();
      } catch (err) {
        integrateCaseSubmitting = false;
        syncIntegrateCaseButtons();
        if ((Number(err.status) === 409 || err.code === 'conflict') && err.data && err.data.owner_case_id) {
          var ownerCaseId = String(err.data.owner_case_id || '').trim();
          setIntegrateCaseError('Este elemento ya está integrado en el caso #' + ownerCaseId + '.', ownerCaseId);
          return;
        }
        setIntegrateCaseError(err.message || 'No se pudo integrar el elemento al caso.');
      }
    }

    function openCreateCaseModal(context) {
      pendingCaseIntegration = context || null;
      setIntegrateCaseError('');
      integrateCaseSubmitting = false;
      createCaseSubmitting = false;
      if (createCaseTitleInput) {
        createCaseTitleInput.value = '';
      }
      if (integrateCaseList) {
        integrateCaseList.innerHTML = '';
      }
      if (integrateCaseEmpty) {
        integrateCaseEmpty.classList.add('d-none');
      }
      syncIntegrateCaseButtons();
      loadIntegrateCaseChoices();
      if (createCaseModalInstance) {
        createCaseModalInstance.show();
      } else if (createCaseModalEl) {
        createCaseModalEl.style.display = 'block';
        createCaseModalEl.classList.add('show');
      }
    }

    function closeCreateCaseModal() {
      setIntegrateCaseError('');
      integrateCaseSubmitting = false;
      createCaseSubmitting = false;
      syncIntegrateCaseButtons();
      if (createCaseModalInstance) {
        createCaseModalInstance.hide();
      } else if (createCaseModalEl) {
        createCaseModalEl.classList.remove('show');
        createCaseModalEl.style.display = 'none';
      }
    }

    async function ensureActiveCaseThenAssign(itemType, itemRef) {
      var nextItemType = String(itemType || '').trim();
      var nextItemRef = String(itemRef || '').trim();
      if (!nextItemType || !nextItemRef) return;
      openCreateCaseModal({
        itemType: nextItemType,
        itemRef: nextItemRef
      });
    }

    function isImageDocumentMeta(doc) {
      if (!doc || typeof doc !== 'object') return false;
      var type = String((doc.document_type || doc.type || '')).trim().toLowerCase();
      var payload = (doc.payload && typeof doc.payload === 'object') ? doc.payload : {};
      var file = (payload.file && typeof payload.file === 'object') ? payload.file : {};
      var renderMode = String((doc.render_mode || file.render_mode || '')).trim().toLowerCase();
      return type === 'image' || renderMode === 'image';
    }

    function buildDocumentUrl(uuid, mode) {
      var key = String(uuid || '').trim();
      if (!key) return '';
      var query = new URLSearchParams();
      query.set('uuid', key);
      if (isEmbed) {
        query.set('embed', '1');
      }
      var path = (String(mode || '').trim() === 'image')
        ? '/modules/clinical/ui/viewer.php'
        : '/modules/clinical/ui/document.php';
      return path + '?' + query.toString();
    }

    function tuneEncounterDetailDocumentLinks(docsRaw) {
      if (!encounterDetailList) return;
      var docs = Array.isArray(docsRaw) ? docsRaw : [];
      var byUuid = {};
      docs.forEach(function (doc) {
        var uuid = String((doc && (doc.document_uuid || doc.document_id)) || '').trim();
        if (!uuid) return;
        byUuid[uuid] = {
          isImage: isImageDocumentMeta(doc)
        };
      });
      var anchors = encounterDetailList.querySelectorAll('a[href*="/modules/clinical/ui/document.php"], a[href*="/modules/clinical/ui/viewer.php"]');
      anchors.forEach(function (anchor) {
        var rawHref = String(anchor.getAttribute('href') || '').trim();
        if (!rawHref) return;
        var parsed;
        try {
          parsed = new URL(rawHref, window.location.origin);
        } catch (_) {
          return;
        }
        var uuid = String(parsed.searchParams.get('uuid') || '').trim();
        if (!uuid) return;
        var meta = byUuid[uuid] || { isImage: false };
        var mode = meta.isImage ? 'image' : 'document';
        anchor.setAttribute('href', buildDocumentUrl(uuid, mode));
        anchor.textContent = meta.isImage ? 'Ver imagen' : 'Ver documento';
      });
    }

    function setDocumentViewerLoading(flag) {
      if (documentViewerLoading) {
        documentViewerLoading.classList.toggle('d-none', !flag);
      }
    }

    function closeDocumentViewerModal() {
      if (!documentViewerModalEl) return;
      if (documentViewerModalInstance) {
        documentViewerModalInstance.hide();
        return;
      }
      documentViewerModalEl.classList.remove('show');
      documentViewerModalEl.style.display = 'none';
      documentViewerModalEl.setAttribute('aria-hidden', 'true');
    }

    function openDocumentViewerModal() {
      if (!documentViewerModalEl) return;
      if (documentViewerModalInstance) {
        documentViewerModalInstance.show();
        return;
      }
      documentViewerModalEl.style.display = 'block';
      documentViewerModalEl.classList.add('show');
      documentViewerModalEl.removeAttribute('aria-hidden');
    }

    function renderDocumentViewerCard(docData) {
      if (!documentViewerBody) return;
      var renderer = window.MXMed && typeof window.MXMed.renderClinicalDocuments === 'function'
        ? window.MXMed.renderClinicalDocuments
        : null;
      if (!renderer) {
        documentViewerBody.innerHTML = '<div class="alert alert-secondary mb-0">No se pudo renderizar el documento.</div>';
        return;
      }
      documentViewerBody.innerHTML = renderer([docData], {
        embedLink: isEmbed,
        returnTo: window.location.href,
        openInOverlay: isEmbed,
        emptyHtml: '<div class="alert alert-secondary mb-0">Sin contenido de documento.</div>'
      });
    }

    async function openDocumentViewer(uuid, summaryHint, mode) {
      var key = String(uuid || '').trim();
      if (!key || !documentViewerModalEl) return;
      activeDocumentUrl = buildDocumentUrl(key, mode);
      openDocumentViewerModal();
      if (documentViewerError) documentViewerError.classList.add('d-none');
      if (documentViewerBody) documentViewerBody.innerHTML = '';
      if (documentViewerOpenNew) {
        documentViewerOpenNew.setAttribute('href', activeDocumentUrl || '#');
      }
      var shortUuid = key.length > 12 ? key.slice(0, 12) + '...' : key;
      var titleText = String(summaryHint || '').trim();
      if (!titleText) {
        titleText = 'UUID ' + shortUuid;
      }
      if (documentViewerTitle) {
        documentViewerTitle.textContent = 'Documento · ' + titleText;
      }
      setDocumentViewerLoading(true);
      try {
        var url = apiBase + '/api/clinical/index.php/documents/' + encodeURIComponent(key);
        var payload = await apiJson(url, { method: 'GET' });
        var data = payload && payload.data && typeof payload.data === 'object' ? payload.data : null;
        if (!data) {
          throw new Error('Documento no disponible');
        }
        renderDocumentViewerCard(data);
      } catch (_) {
        if (documentViewerError) documentViewerError.classList.remove('d-none');
      } finally {
        setDocumentViewerLoading(false);
      }
    }

    function renderEncounterDetail(payload) {
      var data = payload && typeof payload === 'object'
        ? (payload.data && typeof payload.data === 'object' ? payload.data : payload)
        : {};
      if (encounterDetailMeta) {
        var metaParts = [];
        metaParts.push('Atención: ' + String(data.encounter_key || '-'));
        metaParts.push('Fecha: ' + String(data.event_datetime || '-'));
        encounterDetailMeta.textContent = metaParts.join(' | ');
        encounterDetailMeta.classList.remove('d-none');
      }
      if (!encounterDetailList) return;
      var docsRaw = Array.isArray(data.documents) ? data.documents : [];
      var renderer = window.MXMed && typeof window.MXMed.renderClinicalDocuments === 'function'
        ? window.MXMed.renderClinicalDocuments
        : null;
      if (!renderer) {
        encounterDetailList.innerHTML = '<div class="alert alert-secondary mb-0">Sin documentos en esta atención.</div>';
        return;
      }
      encounterDetailList.innerHTML = renderer(docsRaw, {
        embedLink: true,
        returnTo: window.location.href,
        openInOverlay: isEmbed,
        emptyHtml: '<div class="alert alert-secondary mb-0">Sin documentos en esta atención.</div>'
      });
      tuneEncounterDetailDocumentLinks(docsRaw);
    }

    async function openEncounterDetail(encounterKey) {
      if (!encounterKey) return;
      if (encounterDetailModalInstance) {
        encounterDetailModalInstance.show();
      } else if (encounterDetailModalEl) {
        // Fallback when Bootstrap JS is not available in embed host.
        encounterDetailModalEl.style.display = 'block';
        encounterDetailModalEl.classList.add('show');
        encounterDetailModalEl.removeAttribute('aria-hidden');
      }
      if (encounterDetailLoading) encounterDetailLoading.classList.remove('d-none');
      if (encounterDetailError) encounterDetailError.classList.add('d-none');
      if (encounterDetailMeta) {
        encounterDetailMeta.textContent = '';
        encounterDetailMeta.classList.add('d-none');
      }
      if (encounterDetailList) {
        encounterDetailList.innerHTML = '';
      }
      try {
        var url = apiBase + '/api/clinical/index.php/encounters/' + encodeURIComponent(String(encounterKey));
        if (debugMode && window.console && typeof window.console.log === 'function') {
          window.console.log('[encounter detail] fetching', url);
        }
        var resp = await fetch(url, { method: 'GET', credentials: 'include' });
        if (debugMode && window.console && typeof window.console.log === 'function') {
          window.console.log('[encounter detail] status', resp.status);
        }
        if (!resp.ok) {
          throw new Error('HTTP ' + resp.status);
        }
        var payload = await resp.json();
        if (!payload || payload.ok !== true) {
          throw new Error((payload && payload.message) ? String(payload.message) : 'No se pudo cargar detalle');
        }
        renderEncounterDetail((payload && payload.data) ? payload.data : payload);
      } catch (_) {
        if (encounterDetailError) encounterDetailError.classList.remove('d-none');
      } finally {
        if (encounterDetailLoading) encounterDetailLoading.classList.add('d-none');
      }
    }

    function closeEncounterDetailModal() {
      if (!encounterDetailModalEl) return;
      if (encounterDetailModalInstance) {
        encounterDetailModalInstance.hide();
        return;
      }
      encounterDetailModalEl.classList.remove('show');
      encounterDetailModalEl.style.display = 'none';
      encounterDetailModalEl.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('click', function (event) {
      var createBtn = event.target && event.target.closest ? event.target.closest('[data-action="create-clinical-case"]') : null;
      if (createBtn) {
        event.preventDefault();
        openCreateCaseModal(null);
        return;
      }

      var renameBtn = event.target && event.target.closest ? event.target.closest('[data-action="rename-active-case"]') : null;
      if (renameBtn) {
        event.preventDefault();
        var caseId = String(renameBtn.getAttribute('data-case-id') || '').trim();
        if (!caseId) return;
        var nextTitle = window.prompt('Nuevo nombre del caso clínico:', '');
        if (nextTitle === null) return;
        nextTitle = String(nextTitle || '').trim();
        if (!nextTitle) return;
        renameCase(caseId, nextTitle)
          .then(function () { window.location.reload(); })
          .catch(function (err) { window.alert(err.message || 'No se pudo renombrar caso clínico'); });
        return;
      }

      var openCasesBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-cases-modal"]') : null;
      if (openCasesBtn) {
        event.preventDefault();
        openCasesModal();
        return;
      }

      var openEncounterDetailBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-encounter-detail"]') : null;
      if (openEncounterDetailBtn) {
        event.preventDefault();
        var encounterKey = String(openEncounterDetailBtn.getAttribute('data-encounter-key') || '').trim();
        if (!encounterKey) return;
        openEncounterDetail(encounterKey);
        return;
      }

      var toggleOnlyCaseBtn = event.target && event.target.closest ? event.target.closest('[data-action="toggle-only-active-case"]') : null;
      if (toggleOnlyCaseBtn) {
        event.preventDefault();
        setOnlyActiveCaseEnabled(!onlyActiveCaseEnabled);
        return;
      }

      var setCaseScopeBtn = event.target && event.target.closest ? event.target.closest('[data-action="set-case-scope"]') : null;
      if (setCaseScopeBtn) {
        event.preventDefault();
        caseScope = String(setCaseScopeBtn.getAttribute('data-case-scope') || 'all').trim();
        if (caseScope !== 'in' && caseScope !== 'out') {
          caseScope = 'all';
        }
        applyOnlyActiveCaseFilter();
        return;
      }

      var toggleAdvancedFiltersBtn = event.target && event.target.closest ? event.target.closest('[data-action="toggle-advanced-filters"]') : null;
      if (toggleAdvancedFiltersBtn) {
        event.preventDefault();
        advancedFiltersVisible = !advancedFiltersVisible;
        applyAdvancedFiltersVisibility();
        return;
      }

      var setClinicalFilterBtn = event.target && event.target.closest ? event.target.closest('[data-action="set-clinical-filter"]') : null;
      if (setClinicalFilterBtn) {
        event.preventDefault();
        clinicalCategoryFilter = String(setClinicalFilterBtn.getAttribute('data-clinical-filter') || 'all').trim();
        if (clinicalCategoryFilter === '') {
          clinicalCategoryFilter = 'all';
        }
        if (clinicalCategoryFilter !== 'estudio') {
          studyRoleFilter = 'all';
        }
        applyOnlyActiveCaseFilter();
        return;
      }

      var setStudyFilterBtn = event.target && event.target.closest ? event.target.closest('[data-action="set-study-filter"]') : null;
      if (setStudyFilterBtn) {
        event.preventDefault();
        studyRoleFilter = String(setStudyFilterBtn.getAttribute('data-study-filter') || 'all').trim();
        if (studyRoleFilter !== 'orden' && studyRoleFilter !== 'resultado') {
          studyRoleFilter = 'all';
        }
        applyOnlyActiveCaseFilter();
        return;
      }

      var activateCaseBtn = event.target && event.target.closest ? event.target.closest('[data-action="activate-case"]') : null;
      if (activateCaseBtn) {
        event.preventDefault();
        var activateCaseId = String(activateCaseBtn.getAttribute('data-case-id') || '').trim();
        if (!activateCaseId) return;
        activateCase(activateCaseId)
          .then(function () { window.location.reload(); })
          .catch(function (err) { window.alert(err.message || 'No se pudo activar caso'); });
        return;
      }

      var renameFromModalBtn = event.target && event.target.closest ? event.target.closest('[data-action="rename-case-from-modal"]') : null;
      if (renameFromModalBtn) {
        event.preventDefault();
        var modalCaseId = String(renameFromModalBtn.getAttribute('data-case-id') || '').trim();
        if (!modalCaseId) return;
        var currentTitle = String(renameFromModalBtn.getAttribute('data-case-title') || '').trim();
        var nextModalTitle = window.prompt('Nuevo nombre del caso clínico:', currentTitle);
        if (nextModalTitle === null) return;
        nextModalTitle = String(nextModalTitle || '').trim();
        if (!nextModalTitle) return;
        renameCase(modalCaseId, nextModalTitle)
          .then(function () { window.location.reload(); })
          .catch(function (err) { window.alert(err.message || 'No se pudo renombrar caso'); });
        return;
      }

      var closeCasesBtn = event.target && event.target.closest ? event.target.closest('[data-action="close-cases-modal"]') : null;
      if (closeCasesBtn && !casesModalInstance && casesModalEl) {
        event.preventDefault();
        casesModalEl.classList.remove('show');
        casesModalEl.style.display = 'none';
        return;
      }

      var cancelCreateCaseBtn = event.target && event.target.closest ? event.target.closest('[data-action="cancel-create-case"]') : null;
      if (cancelCreateCaseBtn && !createCaseModalInstance && createCaseModalEl) {
        event.preventDefault();
        closeCreateCaseModal();
        return;
      }

      var dismissIntegrateErrorBtn = event.target && event.target.closest ? event.target.closest('[data-action="dismiss-integrate-case-error"]') : null;
      if (dismissIntegrateErrorBtn) {
        event.preventDefault();
        setIntegrateCaseError('');
        return;
      }

      var activateOwnerCaseBtn = event.target && event.target.closest ? event.target.closest('[data-action="activate-owner-case"]') : null;
      if (activateOwnerCaseBtn) {
        event.preventDefault();
        var ownerCaseId = String(activateOwnerCaseBtn.getAttribute('data-case-id') || '').trim();
        if (!ownerCaseId) return;
        activateCase(ownerCaseId)
          .then(function () { window.location.reload(); })
          .catch(function (err) { setIntegrateCaseError(err.message || 'No se pudo activar el caso.'); });
        return;
      }

      var confirmIntegrateCaseBtn = event.target && event.target.closest ? event.target.closest('[data-action="confirm-integrate-case"]') : null;
      if (confirmIntegrateCaseBtn) {
        event.preventDefault();
        if (!pendingCaseIntegration) {
          setIntegrateCaseError('Selecciona un elemento para integrar.');
          return;
        }
        var selectedCaseId = selectedIntegrateCaseId();
        if (!selectedCaseId) {
          setIntegrateCaseError('Selecciona un caso destino.');
          return;
        }
        integrateToCase(selectedCaseId, pendingCaseIntegration.itemType, pendingCaseIntegration.itemRef);
        return;
      }

      var confirmCreateCaseBtn = event.target && event.target.closest ? event.target.closest('[data-action="confirm-create-case"]') : null;
      if (confirmCreateCaseBtn) {
        event.preventDefault();
        var title = createCaseTitleInput ? String(createCaseTitleInput.value || '').trim() : '';
        if (title.length < 3) {
          setIntegrateCaseError('Ingresa un nombre de al menos 3 caracteres.');
          return;
        }
        createCaseSubmitting = true;
        syncIntegrateCaseButtons();
        setIntegrateCaseError('');
        createCase(patientId, title)
          .then(function (createdCase) {
            var createdCaseId = createdCase && createdCase.case_id ? String(createdCase.case_id) : '';
            if (!pendingCaseIntegration || !createdCaseId) {
              window.location.reload();
              return;
            }
            createCaseSubmitting = false;
            syncIntegrateCaseButtons();
            return integrateToCase(createdCaseId, pendingCaseIntegration.itemType, pendingCaseIntegration.itemRef);
          })
          .catch(function (err) {
            createCaseSubmitting = false;
            syncIntegrateCaseButtons();
            setIntegrateCaseError(err.message || 'No se pudo crear el caso clínico.');
          });
        return;
      }

      var closeEncounterDetailBtn = event.target && event.target.closest ? event.target.closest('[data-action="close-encounter-detail-modal"]') : null;
      if (closeEncounterDetailBtn) {
        event.preventDefault();
        closeEncounterDetailModal();
        return;
      }

      if (!encounterDetailModalInstance && encounterDetailModalEl && event.target === encounterDetailModalEl) {
        closeEncounterDetailModal();
        return;
      }

      var closeDocumentModalBtn = event.target && event.target.closest ? event.target.closest('[data-action="close-document-viewer-modal"]') : null;
      if (closeDocumentModalBtn) {
        event.preventDefault();
        closeDocumentViewerModal();
        return;
      }

      if (!documentViewerModalInstance && documentViewerModalEl && event.target === documentViewerModalEl) {
        closeDocumentViewerModal();
        return;
      }

      var copyDocumentLinkBtn = event.target && event.target.closest ? event.target.closest('[data-action="copy-document-link"]') : null;
      if (copyDocumentLinkBtn) {
        event.preventDefault();
        if (!activeDocumentUrl) return;
        var absoluteUrl = window.location.origin + activeDocumentUrl;
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          navigator.clipboard.writeText(absoluteUrl).catch(function () {});
        }
        return;
      }

      var printDocumentLinkBtn = event.target && event.target.closest ? event.target.closest('[data-action="print-document-link"]') : null;
      if (printDocumentLinkBtn) {
        event.preventDefault();
        if (!activeDocumentUrl) return;
        var printWin = window.open(activeDocumentUrl, '_blank', 'noopener');
        if (printWin && typeof printWin.focus === 'function') {
          printWin.focus();
        }
        return;
      }

      var openDocumentBtn = event.target && event.target.closest ? event.target.closest('[data-nav-mode="document"][data-uuid]') : null;
      if (openDocumentBtn) {
        event.preventDefault();
        var docUuid = String(openDocumentBtn.getAttribute('data-uuid') || '').trim();
        var docTarget = String(openDocumentBtn.getAttribute('data-doc-target') || '').trim().toLowerCase();
        var summaryEl = openDocumentBtn.closest('.border, .doc-line, .mm-card');
        var summaryHint = '';
        if (summaryEl) {
          var secondary = summaryEl.querySelector('.text-secondary');
          summaryHint = secondary ? String(secondary.textContent || '').trim() : '';
        }
        openDocumentViewer(docUuid, summaryHint, docTarget === 'image' ? 'image' : 'document');
        return;
      }

      var assignBtn = event.target && event.target.closest ? event.target.closest('[data-action="assign-case-item"]') : null;
      if (assignBtn) {
        event.preventDefault();
        var cId = String(assignBtn.getAttribute('data-case-id') || '').trim();
        var itemType = String(assignBtn.getAttribute('data-item-type') || '').trim();
        var itemRef = String(assignBtn.getAttribute('data-item-ref') || '').trim();
        if (!cId || !itemType || !itemRef) return;
        assignItem(cId, itemType, itemRef)
          .then(function () { window.location.reload(); })
          .catch(function (err) { window.alert(err.message || 'No se pudo asignar item al caso'); });
        return;
      }

      var integrateBtn = event.target && event.target.closest ? event.target.closest('[data-action="integrate-to-case"]') : null;
      if (integrateBtn) {
        event.preventDefault();
        var integrateItemType = String(integrateBtn.getAttribute('data-item-type') || '').trim();
        var integrateItemRef = String(integrateBtn.getAttribute('data-item-ref') || '').trim();
        if (!integrateItemType || !integrateItemRef) return;
        ensureActiveCaseThenAssign(integrateItemType, integrateItemRef);
        return;
      }

      var activityItem = event.target && event.target.closest ? event.target.closest('.mm-activity-item[data-timeline-item="1"]') : null;
      if (activityItem) {
        var interactiveParent = event.target && event.target.closest
          ? event.target.closest('a, button, input, label, form, textarea, select')
          : null;
        if (interactiveParent) {
          return;
        }
        event.preventDefault();
        navigateTimelineItem(activityItem);
        return;
      }

      var assignRecentBtn = event.target && event.target.closest ? event.target.closest('[data-action="assign-recent-to-active-case"]') : null;
      if (assignRecentBtn) {
        event.preventDefault();
        if (!activeCaseId || recentCandidates.length < 1) return;
        (async function () {
          var okCount = 0;
          for (var i = 0; i < recentCandidates.length; i += 1) {
            var rc = recentCandidates[i];
            try {
              await assignItem(activeCaseId, rc.itemType, rc.itemRef);
              okCount += 1;
            } catch (_) {}
          }
          window.alert('Listo: se agregaron ' + okCount);
          window.location.reload();
        })();
        return;
      }

      var snoozeRecentBtn = event.target && event.target.closest ? event.target.closest('[data-action="snooze-recent-case-suggestion"]') : null;
      if (snoozeRecentBtn) {
        event.preventDefault();
        try {
          localStorage.setItem(recentSuggestStorageKey, String(Date.now()));
        } catch (_) {}
        renderRecentSuggestion();
        return;
      }

      var trigger = event.target && event.target.closest ? event.target.closest('[data-embed-nav]') : null;
      if (!trigger) return;
      var mode = String(trigger.getAttribute('data-nav-mode') || '').trim();
      if (mode !== 'encounter' && mode !== 'document') return;

      if (!window.parent || window.parent === window || typeof window.parent.postMessage !== 'function') {
        return;
      }

      var payload = { type: 'mxmed:embed:navigate', mode: mode };
      if (mode === 'encounter') {
        var encounterKey = String(trigger.getAttribute('data-encounter-key') || '').trim();
        if (!encounterKey) return;
        payload.encounter_key = encounterKey;
      } else {
        var uuid = String(trigger.getAttribute('data-uuid') || '').trim();
        var bundleId = String(trigger.getAttribute('data-bundle-id') || '').trim();
        if (!uuid) return;
        payload.uuid = uuid;
        if (bundleId) payload.bundle_id = bundleId;
        var href = String(trigger.getAttribute('href') || trigger.getAttribute('data-href') || '').trim();
        if (href) payload.href = href;
      }

      event.preventDefault();
      window.parent.postMessage(payload, '*');
    }, true);

    document.addEventListener('keydown', function (event) {
      if (!event || event.key !== 'Escape') return;
      if (encounterDetailModalEl) {
        var encounterVisible = encounterDetailModalEl.classList.contains('show') || encounterDetailModalEl.style.display === 'block';
        if (encounterVisible) {
          closeEncounterDetailModal();
          return;
        }
      }
      if (documentViewerModalEl) {
        var documentVisible = documentViewerModalEl.classList.contains('show') || documentViewerModalEl.style.display === 'block';
        if (documentVisible) {
          closeDocumentViewerModal();
        }
      }
    });

    if (createCaseModalEl) {
      createCaseModalEl.addEventListener('hidden.bs.modal', function () {
        pendingCaseIntegration = null;
        setIntegrateCaseError('');
        integrateCaseSubmitting = false;
        createCaseSubmitting = false;
        if (integrateCaseList) {
          integrateCaseList.innerHTML = '';
        }
        if (createCaseTitleInput) {
          createCaseTitleInput.value = '';
        }
        syncIntegrateCaseButtons();
      });
    }

    if (createCaseTitleInput) {
      createCaseTitleInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          if (createCaseConfirmBtn) {
            createCaseConfirmBtn.click();
          }
        }
      });
    }

    if (integrateCaseList) {
      integrateCaseList.addEventListener('change', function () {
        syncIntegrateCaseButtons();
      });
    }

    if (window.MXMed && typeof window.MXMed.initClinicalEmbedKit === 'function') {
      window.MXMed.initClinicalEmbedKit({ embedOnly: true });
    }

    if (patientId) {
      loadActiveCase(patientId).catch(function () {});
    }
    initActivityTooltips();
    applyOnlyActiveCaseFilter();
    renderRecentSuggestion();
    applyAdvancedFiltersVisibility();
    bootstrapCaseSummary();
  })();
</script>
<?php if ($embed): ?>
<?php clinical_embed_end(); ?>
<?php else: ?>
<?php require_once __DIR__ . '/../../_partials/mm_shell_bottom.php'; ?>
<?php endif; ?>
