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

function normalize_encounter_match_key(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/(enc:[A-Za-z0-9:_-]+)/', $value, $m) === 1) {
        return strtolower(trim((string)$m[1]));
    }
    return strtolower($value);
}

function encounter_key_matches_active(string $candidate, string $active): bool
{
    $candidateNorm = normalize_encounter_match_key($candidate);
    $activeNorm = normalize_encounter_match_key($active);
    if ($candidateNorm === '' || $activeNorm === '') {
        return false;
    }
    return $candidateNorm === $activeNorm;
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

function timeline_item_case_membership_keys(array $item): array
{
    $keys = [];
    $itemType = strtolower(trim((string)($item['item_type'] ?? '')));
    if ($itemType === '') {
        return $keys;
    }
    $links = is_array($item['links'] ?? null) ? $item['links'] : [];
    if ($itemType === 'appointment') {
        $appointmentId = trim((string)($links['appointment_id'] ?? ''));
        if ($appointmentId !== '') {
            $keys[] = 'appointment|appt:' . $appointmentId;
        }
        $ref = trim((string)($item['ref'] ?? ''));
        if ($ref !== '') {
            $keys[] = 'appointment|' . $ref;
        }
    } elseif ($itemType === 'encounter') {
        $encounterKey = trim((string)($item['encounter_key'] ?? ''));
        if ($encounterKey !== '') {
            $keys[] = 'encounter|' . $encounterKey;
        }
    } elseif ($itemType === 'document') {
        $docRefCandidates = [
            trim((string)($links['document_uuid'] ?? '')),
            trim((string)($item['document_uuid'] ?? '')),
            trim((string)($item['document_id'] ?? '')),
        ];
        $clinicalDoc = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
        $docRefCandidates[] = trim((string)($clinicalDoc['document_uuid'] ?? ''));
        $docRefCandidates[] = trim((string)($clinicalDoc['id'] ?? ''));
        foreach ($docRefCandidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $keys[] = 'document|' . $candidate;
        }
    }

    return array_values(array_unique(array_filter(array_map(static function ($value): string {
        return strtolower(trim((string)$value));
    }, $keys))));
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

function timeline_mix_entries_for_demo(array $entries): array
{
    if (count($entries) < 4) {
        return $entries;
    }
    $buckets = [];
    $order = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $meta = is_array($entry['category_meta'] ?? null) ? $entry['category_meta'] : [];
        $group = trim((string)($meta['catalog_group'] ?? 'other'));
        if ($group === '') {
            $group = 'other';
        }
        if (!isset($buckets[$group])) {
            $buckets[$group] = [];
            $order[] = $group;
        }
        $buckets[$group][] = $entry;
    }
    if (count($order) <= 1) {
        return $entries;
    }
    $mixed = [];
    $safety = 0;
    while ($safety < 5000) {
        $safety++;
        $added = false;
        foreach ($order as $group) {
            if (!empty($buckets[$group])) {
                $mixed[] = array_shift($buckets[$group]);
                $added = true;
            }
        }
        if (!$added) {
            break;
        }
    }
    return count($mixed) === count($entries) ? $mixed : $entries;
}

function timeline_demo_visible_family(array $entry): string
{
    $kind = strtolower(trim((string)($entry['kind'] ?? '')));
    if ($kind === 'appointment') {
        return 'Cita';
    }
    if ($kind === 'encounter') {
        return 'Consulta';
    }
    if ($kind === 'media_bundle') {
        return 'Documento';
    }

    $item = is_array($entry['item'] ?? null) ? $entry['item'] : [];
    $itemType = strtolower(trim((string)($item['item_type'] ?? '')));
    if ($itemType === 'appointment') {
        return 'Cita';
    }
    if ($itemType === 'encounter') {
        return 'Consulta';
    }

    $doc = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $payload = is_array($doc['payload'] ?? null) ? $doc['payload'] : [];
    $file = is_array($payload['file'] ?? null) ? $payload['file'] : [];
    $docType = strtolower(trim((string)($doc['document_type'] ?? '')));
    $renderMode = strtolower(trim((string)($doc['render_mode'] ?? ($file['render_mode'] ?? ''))));

    if (in_array($docType, ['note', 'medical_note', 'evolution_note', 'nota_evolucion'], true)) {
        return 'Nota clínica';
    }
    if (in_array($docType, ['lab_order', 'imaging_order', 'order', 'orders', 'lab_result', 'imaging_result', 'result', 'results', 'lab_pdf'], true)) {
        return 'Estudio';
    }
    if (in_array($docType, ['prescription', 'rx'], true)) {
        return 'Receta';
    }
    if (in_array($docType, ['procedure', 'immunization', 'medication_administration', 'wound_care'], true)) {
        return 'Procedimiento';
    }
    if (in_array($docType, ['consentimiento_informado', 'consent_document', 'image', 'pdf', 'text', 'document', 'file'], true)) {
        return 'Documento';
    }
    if (in_array($renderMode, ['image', 'pdf'], true)) {
        return 'Documento';
    }

    $meta = is_array($entry['category_meta'] ?? null) ? $entry['category_meta'] : [];
    $catalogGroup = strtolower(trim((string)($meta['catalog_group'] ?? '')));
    $subtype = strtolower(trim((string)($meta['subtype'] ?? '')));
    if ($catalogGroup === 'attention' && $subtype === 'appointment') {
        return 'Cita';
    }
    if ($catalogGroup === 'attention') {
        return 'Consulta';
    }
    if ($catalogGroup === 'studies') {
        return 'Estudio';
    }
    if ($catalogGroup === 'treatment') {
        return 'Receta';
    }
    if ($catalogGroup === 'documents' || $catalogGroup === 'multimedia') {
        return 'Documento';
    }
    if ($catalogGroup === 'clinical') {
        return 'Consulta';
    }

    return 'Documento';
}

function timeline_demo_entry_title_hint(array $entry): string
{
    $item = is_array($entry['item'] ?? null) ? $entry['item'] : [];
    $doc = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $payload = is_array($doc['payload'] ?? null) ? $doc['payload'] : [];
    $parts = [
        (string)($doc['title'] ?? ''),
        (string)($payload['title'] ?? ''),
        (string)($payload['document_title'] ?? ''),
        (string)($doc['summary'] ?? ''),
        (string)($entry['event_label'] ?? ''),
    ];
    $joined = strtolower(trim(implode(' | ', array_filter(array_map('trim', $parts), static function ($v) {
        return $v !== '';
    }))));
    return $joined;
}

function timeline_demo_is_auto_cierre_marker(array $entry): bool
{
    $item = is_array($entry['item'] ?? null) ? $entry['item'] : [];
    $doc = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $payload = is_array($doc['payload'] ?? null) ? $doc['payload'] : [];
    $docType = strtolower(trim((string)($doc['document_type'] ?? '')));
    if (!in_array($docType, ['note', 'nota_evolucion', 'medical_note', 'evolution_note'], true)) {
        return false;
    }
    $source = strtolower(trim((string)($payload['source'] ?? '')));
    $titleHint = timeline_demo_entry_title_hint($entry);
    $hasAutoToken = (strpos($titleHint, 'auto') !== false) || (strpos($source, 'auto') !== false);
    $hasCloseToken = (strpos($titleHint, 'cierre') !== false)
        || (strpos($titleHint, 'close') !== false)
        || (strpos($source, 'cierre') !== false)
        || (strpos($source, 'close') !== false)
        || (strpos($source, 'final') !== false);
    return $hasAutoToken && $hasCloseToken;
}

function timeline_demo_episode_marker_type(array $entry): string
{
    $family = timeline_demo_visible_family($entry);
    if ($family === 'Consulta') {
        return 'consulta';
    }
    if (timeline_demo_is_auto_cierre_marker($entry)) {
        return 'auto_cierre_note';
    }
    return '';
}

function timeline_limit_demo_episode_markers_per_day(array $entries): array
{
    $hasConsultaMarker = false;
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (timeline_demo_episode_marker_type($entry) === 'consulta') {
            $hasConsultaMarker = true;
            break;
        }
    }

    $result = [];
    $consultaSeen = false;
    $autoCierreSeen = false;
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $markerType = timeline_demo_episode_marker_type($entry);
        if ($markerType === 'consulta') {
            if ($consultaSeen) {
                continue;
            }
            $consultaSeen = true;
        } elseif ($markerType === 'auto_cierre_note') {
            if ($hasConsultaMarker) {
                continue;
            }
            if ($autoCierreSeen) {
                continue;
            }
            $autoCierreSeen = true;
        }
        $result[] = $entry;
    }
    return $result;
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

function timeline_is_compact_datetime_text(string $value): bool
{
    $text = trim($value);
    if ($text === '') {
        return false;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) {
        return true;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?$/', $text) === 1) {
        return true;
    }
    return false;
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
        return 'Consulta';
    }
    if ($group === 'clinical' && ($subtype === 'note' || $subtype === 'note_evolution' || $documentType === 'note' || $documentType === 'nota_evolucion')) {
        return 'Nota clínica';
    }
    if (in_array($documentType, ['prescription', 'rx'], true)) {
        return 'Receta';
    }
    if ($documentType === 'consentimiento_informado' || $documentType === 'consent_document') {
        return 'Consentimiento informado';
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
            return 'Documento';
        }
        return 'Documento';
    }
    if ($group === 'clinical') {
        return 'Consulta';
    }
    if ($group === 'documents') {
        return 'Documento';
    }

    $fallback = trim((string)($meta['subtype_label'] ?? $meta['catalog_group_label'] ?? 'Evento clinico'));
    return $fallback !== '' ? $fallback : 'Evento clinico';
}

function timeline_human_document_type_label(string $documentType): string
{
    $type = strtolower(trim($documentType));
    if ($type === 'image') {
        return 'Fotografía';
    }
    if ($type === 'pdf' || $type === 'document' || $type === 'file') {
        return 'Documento';
    }
    if ($type === 'text') {
        return 'Nota';
    }
    if ($type === 'lab_result' || $type === 'result') {
        return 'Estudio';
    }
    if ($type === 'imaging' || $type === 'imaging_result') {
        return 'Estudio de imagen';
    }
    return 'Documento';
}

function timeline_is_generic_document_label(string $value): bool
{
    $label = trim($value);
    if ($label === '') {
        return false;
    }
    $norm = timeline_normalize_label($label);
    if ($norm === '') {
        return false;
    }
    if (in_array($norm, ['documento', 'evidencia clinica', 'documento clinico', 'documento clinico image'], true)) {
        return true;
    }
    if (strpos($norm, 'documento clinico') === 0) {
        return true;
    }
    $labelLower = strtolower($label);
    if (strpos($labelLower, 'clinical document') !== false) {
        return true;
    }
    if (preg_match('/documento\\s+cl/i', $labelLower) === 1) {
        return true;
    }
    if (preg_match('/evidencia\\s+cl/i', $labelLower) === 1) {
        return true;
    }
    if (strpos($labelLower, '(image)') !== false && strpos($labelLower, 'documento') !== false) {
        return true;
    }
    return false;
}

function timeline_should_show_document_complementary(string $text, string $title = ''): bool
{
    $value = trim($text);
    if ($value === '') {
        return false;
    }
    if (timeline_is_generic_document_label($value)) {
        return false;
    }
    if (mb_strlen($value) < 3) {
        return false;
    }
    $titleNorm = timeline_normalize_label($title);
    $valueNorm = timeline_normalize_label($value);
    if ($titleNorm !== '' && $valueNorm !== '' && $titleNorm === $valueNorm) {
        return false;
    }
    return true;
}

function timeline_activity_icon(array $item, array $meta): string
{
    $itemType = trim((string)($item['item_type'] ?? ''));
    $group = trim((string)($meta['catalog_group'] ?? ''));
    $phase = trim((string)($meta['catalog_phase'] ?? ''));
    $document = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $documentType = strtolower(trim((string)($document['document_type'] ?? '')));
    $documentTypeIconMap = [
        'nota_evolucion' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="2"></rect><path d="M8 9h8M8 13h8M8 17h5"></path></svg>',
        'note' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="2"></rect><path d="M8 9h8M8 13h8M8 17h5"></path></svg>',
        'prescription' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 4h8a4 4 0 0 1 0 8H8z"></path><path d="M8 12v8"></path><path d="M4 16h8"></path><path d="m14 14 6 6"></path></svg>',
        'lab_order' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 4v7l-4 7a2 2 0 0 0 1.8 3h8.4A2 2 0 0 0 18 18l-4-7V4"></path><path d="M8 4h8"></path></svg>',
        'lab_result' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 4v7l-4 7a2 2 0 0 0 1.8 3h8.4A2 2 0 0 0 18 18l-4-7V4"></path><path d="M8 4h8"></path></svg>',
        'imaging_order' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="2"></rect><circle cx="12" cy="12" r="2.8"></circle><path d="M19 9.5v-2"></path><path d="M5 9.5v-2"></path></svg>',
        'imaging_result' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="2"></rect><circle cx="12" cy="12" r="2.8"></circle><path d="M19 9.5v-2"></path><path d="M5 9.5v-2"></path></svg>',
        'result' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 4h10v6l-3 3v5a2 2 0 0 1-4 0v-5l-3-3z"></path><path d="M9 4v2M15 4v2"></path></svg>',
        'consentimiento_informado' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="2"></rect><path d="M8 9h8"></path><path d="M8 13h5"></path><path d="m14.5 16 1.8 1.8L19.5 14.6"></path></svg>',
        'consent_document' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="2"></rect><path d="M8 9h8"></path><path d="M8 13h5"></path><path d="m14.5 16 1.8 1.8L19.5 14.6"></path></svg>',
    ];
    if ($itemType === 'document' && isset($documentTypeIconMap[$documentType])) {
        return $documentTypeIconMap[$documentType];
    }

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

    echo '<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet">' . "\n";
    echo '<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet">' . "\n";
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

function timeline_is_demo_local_patient(string $patientId): bool
{
    $pid = strtolower(trim($patientId));
    if ($pid === '') {
        return false;
    }
    if ($pid === 'demo') {
        return true;
    }
    // Pacientes demo locales conservados para QA visual.
    static $demoLocalIds = [
        'p_253c0c00eb77', // Adriana Ruiz Salgado
        'p_f8a91656546b', // Jorge Emiliano Cardenas Mena
    ];
    return in_array($pid, $demoLocalIds, true);
}

$patientId = trim((string)($_GET['patient_id'] ?? ''));
$appointmentId = trim((string)($_GET['appointment_id'] ?? ''));
$encounterKey = trim((string)($_GET['encounter_key'] ?? ''));
$focusCaseId = trim((string)($_GET['case_id'] ?? ''));
$isCaseEmbed = trim((string)($_GET['case_embed'] ?? '')) === '1';
$view = strtolower(trim((string)($_GET['view'] ?? 'historial')));
$allowedViews = ['historial', 'cases'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'historial';
}
$isCasesView = ($view === 'cases');
$include = trim((string)($_GET['include'] ?? 'agenda,clinical'));
$limit = (int)($_GET['limit'] ?? 20);
$cursor = trim((string)($_GET['cursor'] ?? ''));
$direction = trim((string)($_GET['direction'] ?? ''));
$include = 'agenda,clinical';
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
    $hostRaw = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
    $hostName = $hostRaw;
    $hostPort = null;
    if (strpos($hostRaw, ':') !== false) {
        $hostParts = explode(':', $hostRaw, 2);
        $hostName = trim((string)($hostParts[0] ?? ''));
        $portCandidate = trim((string)($hostParts[1] ?? ''));
        if ($portCandidate !== '' && ctype_digit($portCandidate)) {
            $hostPort = (int)$portCandidate;
        }
    }
    $hostNameLower = strtolower(trim($hostName));
    $isLocalHost = in_array($hostNameLower, ['127.0.0.1', 'localhost'], true);
    // Local dev convention: UI on :8092 and clinical API on :8091.
    if ($isLocalHost && $hostPort === 8092) {
        $clinicalApiBase = $proto . '://' . $hostName . ':8091';
    } else {
        $clinicalApiBase = $proto . '://' . $hostRaw;
    }
}
$clinicalApiIndexBase = $clinicalApiBase . '/api/clinical/index.php';
// Client-side fetch base: in embed, force same-origin relative routes to avoid CORS between host and iframe origins.
$clinicalApiClientBase = $embed ? '' : $clinicalApiBase;
// usar base raw para HTTP calls, nunca HTML-escaped
$currentUserId = trim((string)($_SESSION['user_id'] ?? ($_SERVER['PHP_AUTH_USER'] ?? 'qa')));
if ($currentUserId === '') {
    $currentUserId = 'qa';
}

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
$activeEncounterKey = trim((string)$encounterKey);

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
    $isDemoPatient = timeline_is_demo_local_patient($patientId);
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

if ($patientId !== '' && $patientId !== 'demo') {
    $activeEncounterKey = '';
    $activeEncounterUrl = $clinicalApiIndexBase . '/patients/' . rawurlencode($patientId) . '/encounters/active';
    $activeEncounterContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $activeEncounterRaw = @file_get_contents($activeEncounterUrl, false, $activeEncounterContext);
    if ($activeEncounterRaw !== false) {
        $activeEncounterDecoded = json_decode($activeEncounterRaw, true);
        if (is_array($activeEncounterDecoded) && ($activeEncounterDecoded['ok'] ?? false) === true) {
            $activeEncounterData = is_array($activeEncounterDecoded['data'] ?? null) ? $activeEncounterDecoded['data'] : [];
            $activeEncounterKey = trim((string)($activeEncounterData['encounter_key'] ?? ($activeEncounterDecoded['encounter_key'] ?? '')));
        }
    }
}
$activeEncounterKey = normalize_encounter_match_key($activeEncounterKey);

$items = array_values(array_filter($items, static function ($item): bool {
    if (!is_array($item)) {
        return false;
    }
    $itemType = trim((string)($item['item_type'] ?? ''));
    return $itemType === 'appointment' || $itemType === 'encounter' || $itemType === 'document';
}));

$focusCaseItemMap = [];
if ($focusCaseId !== '' && $patientId !== '') {
    $caseItemsUrl = $clinicalApiIndexBase . '/cases/' . rawurlencode($focusCaseId) . '/items?limit=200';
    $caseItemsContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $caseItemsRaw = @file_get_contents($caseItemsUrl, false, $caseItemsContext);
    if (is_string($caseItemsRaw) && $caseItemsRaw !== '') {
        $caseItemsDecoded = json_decode($caseItemsRaw, true);
        if (is_array($caseItemsDecoded) && ($caseItemsDecoded['ok'] ?? false) === true) {
            $caseItemsList = is_array($caseItemsDecoded['data'] ?? null) ? $caseItemsDecoded['data'] : [];
            foreach ($caseItemsList as $caseItem) {
                if (!is_array($caseItem)) {
                    continue;
                }
                $ciType = strtolower(trim((string)($caseItem['item_type'] ?? '')));
                $ciRef = trim((string)($caseItem['item_ref'] ?? ''));
                if ($ciType === '' || $ciRef === '') {
                    continue;
                }
                $focusCaseItemMap[$ciType . '|' . strtolower($ciRef)] = true;
            }
        }
    }
}

if ($focusCaseId !== '') {
    $items = array_values(array_filter($items, static function ($item) use ($focusCaseId, $focusCaseItemMap): bool {
        if (!is_array($item)) {
            return false;
        }
        if ($focusCaseItemMap !== []) {
            $membershipKeys = timeline_item_case_membership_keys($item);
            foreach ($membershipKeys as $membershipKey) {
                if (isset($focusCaseItemMap[$membershipKey])) {
                    return true;
                }
            }
            return false;
        }
        $itemCaseId = trim((string)($item['case_id'] ?? ''));
        return $itemCaseId !== '' && $itemCaseId === $focusCaseId;
    }));
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
    if (($isDemoPatient ?? false) === true) {
        $entries = timeline_mix_entries_for_demo($dayGroups[$dayKey]['entries']);
        $dayGroups[$dayKey]['entries'] = timeline_limit_demo_episode_markers_per_day($entries);
    }
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
$countAppointments = count(array_filter($items, static fn($it): bool => (($it['item_type'] ?? '') === 'appointment')));
$countDocuments = count(array_filter($items, static fn($it): bool => (($it['item_type'] ?? '') === 'document')));

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
    .clinical-historial .is-active-encounter{
      border-left: 4px solid #198754;
      background: linear-gradient(90deg, rgba(25,135,84,.10) 0%, rgba(25,135,84,0) 26%);
      box-shadow: 0 0 0 1px rgba(25,135,84,.16) inset;
    }
    .clinical-historial .encounter-active-badge{
      background:#198754;
      color:#fff;
      border-radius:999px;
      padding:.18rem .48rem;
      font-size:.68rem;
      font-weight:700;
      line-height:1;
      letter-spacing:.01em;
      white-space:nowrap;
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
    .clinical-historial [data-role="only-active-case-pool-note"]{
      background:#f8fafc;
      border-color:rgba(100,116,139,.35);
      color:#334155;
      margin-top:-.35rem;
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
      padding: .72rem;
    }
    .clinical-historial .timeline-day-header{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:.55rem;
      margin-bottom:.55rem;
    }
    .clinical-historial .timeline-day-events{
      display:flex;
      flex-direction:column;
      gap:.45rem;
    }
    .clinical-historial .timeline-day-events .mm-activity-item.is-focus-case-item{
      order:1;
    }
    .clinical-historial .timeline-day-events .mm-activity-item.is-focus-available-item{
      order:2;
      border-top:1px dashed #cbd5e1;
      padding-top:.42rem;
      margin-top:.16rem;
      background:linear-gradient(90deg, rgba(148,163,184,.08) 0%, rgba(148,163,184,0) 36%);
    }
    .clinical-historial .timeline-event{
      border: 0;
      background: transparent;
    }
    .clinical-historial .mm-activity-item{
      display:flex;
      align-items:flex-start;
      gap:10px;
      border:1px solid #e5e7eb;
      border-radius:10px;
      padding:7px 10px;
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
      line-height:1.1;
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
      line-height:1.15;
      margin-top:0;
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
      gap:.3rem;
      margin-top:.25rem;
      line-height:1.15;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic{
      display:flex;
      align-items:flex-start;
      gap:10px;
      border:1px solid #cfe9f0;
      border-radius:12px;
      padding:6px 8px;
      background:#f7fcff;
      border-color:#cfe9f0;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic:hover{
      border-color:#cfe9f0;
      box-shadow:none;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic > .mm-activity-body{
      width:100%;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-actions{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:.35rem .75rem;
      margin-top:0;
      flex:0 0 auto;
      margin-left:auto;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-line2{
      margin-top:1px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:.35rem .75rem;
      flex-wrap:nowrap;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-studies-line{
      margin-top:0;
      font-size:.84rem;
      line-height:1.15;
      color:#51607a;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
      flex:1 1 280px;
      min-width:0;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-studies-line.is-pending,
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-studies-line.is-complete{
      color:#6b7280;
      font-weight:400;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-studies-line.is-empty{
      min-height:1px;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-head{
      display:block;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-title{
      display:flex;
      align-items:center;
      gap:.4rem;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-date{
      font-size:.78rem;
      color:#6b7280;
      line-height:1.25;
      white-space:nowrap;
    }
    .clinical-historial .mm-activity-actions .mm-link-action{
      appearance:none;
      border:0;
      background:transparent;
      padding:.12rem 0;
      margin:0;
      color:#0b5ed7;
      text-decoration:underline;
      text-underline-offset:2px;
      font-size:.78rem;
      line-height:1.2;
      cursor:pointer;
      white-space:nowrap;
    }
    .clinical-historial .mm-activity-actions .mm-link-action:hover{
      color:#084298;
    }
    .clinical-historial .mm-activity-actions .mm-link-action--integrate{
      border:1px solid #bfdbfe;
      border-radius:999px;
      background:#eff6ff;
      color:#1d4ed8;
      text-decoration:none;
      font-weight:600;
      padding:.16rem .6rem;
      line-height:1.2;
    }
    .clinical-historial .mm-activity-actions .mm-link-action--integrate:hover{
      background:#dbeafe;
      color:#1e40af;
      text-decoration:none;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-actions .mm-link-action--study{
      border:1px solid #bfdbfe;
      border-radius:999px;
      background:#eff6ff;
      color:#1d4ed8;
      text-decoration:none;
      font-weight:500;
      padding:.16rem .6rem;
      line-height:1.2;
    }
    .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-actions .mm-link-action--study:hover{
      background:#dbeafe;
      color:#1e40af;
      text-decoration:none;
    }
    @media (max-width: 991.98px){
      .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-line2{
        align-items:flex-start;
        flex-wrap:wrap;
      }
      .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-studies-line{
        min-width:100%;
      }
      .clinical-historial .mm-activity-item.est-order-card.is-study-diagnostic .est-order-actions{
        width:100%;
        justify-content:flex-start;
        flex-wrap:wrap;
      }
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
<div class="<?php echo $embed ? 'py-1 clinical-historial-embed' : 'container py-4'; ?>">
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

  <?php if ($patientId !== ''): ?>
    <?php if (!$isCaseEmbed): ?>
    <div class="alert alert-info d-none py-2 mb-3" data-role="recent-case-suggestion">
      <div data-role="recent-case-suggestion-text"></div>
      <div class="small text-secondary mt-1" data-role="recent-case-suggestion-subtext">Agrupa los registros en casos clínicos para mantener el expediente organizado.</div>
      <div class="mt-2 d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-primary" data-action="assign-recent-to-active-case">Agrupar recientes</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="snooze-recent-case-suggestion">No por ahora</button>
      </div>
    </div>
    <?php endif; ?>
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
  <?php else: ?>
    <?php if ($patientId !== ''): ?>
      <div class="d-flex justify-content-end gap-2 flex-wrap mb-3 d-none">
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-primary d-none" data-action="open-wound-care-modal">Registrar curación</button>
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-primary d-none" data-action="open-immunization-modal">Registrar vacuna</button>
      </div>
    <?php endif; ?>
    <?php if ($isCasesView): ?>
      <div class="mm-card">
        <div class="body">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h2 class="h6 mb-0">Casos clínicos del paciente</h2>
          </div>
          <div class="text-secondary small d-none" data-role="cases-tab-loading">Cargando casos...</div>
          <div class="alert alert-secondary d-none mb-0" data-role="cases-tab-empty">Sin casos clínicos disponibles para este paciente.</div>
          <div class="alert alert-danger d-none mb-0" data-role="cases-tab-error"></div>
          <div class="vstack gap-2" data-role="cases-tab-list"></div>
        </div>
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

    <?php if (!$isCaseEmbed): ?>
      <div class="only-active-case-note d-none" data-role="only-active-case-note">Caso clínico enfocado.</div>
      <div class="only-active-case-note d-none" data-role="only-active-case-pool-note">Registros disponibles para integrar a este caso.</div>
      <div class="timeline-category-filters mb-3" data-role="timeline-category-filters">
        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtros clínicos de historial">
          <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary active" data-action="set-clinical-filter" data-clinical-filter="all">Todo</button>
          <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="cita">Citas</button>
          <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="consulta">Consultas</button>
          <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="receta">Recetas</button>
          <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="procedimiento">Procedimientos</button>
          <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="estudio">Estudios</button>
          <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="set-clinical-filter" data-clinical-filter="documento">Documentos</button>
        </div>
      </div>
      <div class="alert alert-secondary d-none py-2 mb-3" data-role="case-scope-empty">Sin eventos del filtro seleccionado.</div>
    <?php endif; ?>
    <?php
    $studyResultRefByOrderRef = [];
    foreach ($dayOrder as $mapDayKey) {
        $mapDayGroup = $dayGroups[$mapDayKey] ?? null;
        if (!is_array($mapDayGroup)) {
            continue;
        }
        $mapEntries = is_array($mapDayGroup['entries'] ?? null) ? $mapDayGroup['entries'] : [];
        foreach ($mapEntries as $mapEntry) {
            if (($mapEntry['kind'] ?? '') !== 'document') {
                continue;
            }
            $mapItem = is_array($mapEntry['item'] ?? null) ? $mapEntry['item'] : [];
            $mapDoc = is_array($mapItem['clinical_document'] ?? null) ? $mapItem['clinical_document'] : [];
            $mapType = strtolower(trim((string)($mapDoc['document_type'] ?? '')));
            if (!in_array($mapType, ['lab_result', 'imaging_result', 'result', 'lab_pdf'], true)) {
                continue;
            }
            $mapLinks = is_array($mapItem['links'] ?? null) ? $mapItem['links'] : [];
            $resultUuid = trim((string)($mapLinks['document_uuid'] ?? ($mapDoc['document_uuid'] ?? ($mapDoc['document_id'] ?? ''))));
            $resultId = trim((string)($mapDoc['document_db_id'] ?? ($mapDoc['id'] ?? '')));
            $resultRef = $resultUuid !== '' ? $resultUuid : $resultId;
            if ($resultRef === '') {
                continue;
            }
            $mapPayload = is_array($mapDoc['payload'] ?? null) ? $mapDoc['payload'] : [];
            $relatedRefs = [
                trim((string)($mapPayload['related_order_document_uuid'] ?? '')),
                trim((string)($mapPayload['related_order_document_id'] ?? '')),
                trim((string)($mapPayload['related_document_uuid'] ?? '')),
                trim((string)($mapPayload['related_document_id'] ?? '')),
                trim((string)($mapPayload['related_order_id'] ?? '')),
            ];
            foreach ($relatedRefs as $relatedRef) {
                if ($relatedRef === '' || isset($studyResultRefByOrderRef[$relatedRef])) {
                    continue;
                }
                $studyResultRefByOrderRef[$relatedRef] = $resultRef;
            }
        }
    }
    ?>
    <div class="vstack gap-3">
      <?php foreach ($dayOrder as $dayKey): ?>
        <?php $dayGroup = $dayGroups[$dayKey]; ?>
        <?php
        $dayRenderEntries = is_array($dayGroup['entries'] ?? null) ? $dayGroup['entries'] : [];
        if (($isDemoPatient ?? false) === true && !empty($dayRenderEntries)) {
            $hasConsultaVisible = false;
            foreach ($dayRenderEntries as $candidateEntry) {
                if (!is_array($candidateEntry)) {
                    continue;
                }
                $candidateItem = is_array($candidateEntry['item'] ?? null) ? $candidateEntry['item'] : [];
                $candidateMeta = is_array($candidateEntry['category_meta'] ?? null) ? $candidateEntry['category_meta'] : [];
                $candidateTitle = trim((string)timeline_activity_title($candidateItem, $candidateMeta));
                if ($candidateTitle === 'Consulta') {
                    $hasConsultaVisible = true;
                    break;
                }
            }

            $consultaShown = false;
            $autoCierreShown = false;
            $filteredDayEntries = [];
            foreach ($dayRenderEntries as $candidateEntry) {
                if (!is_array($candidateEntry)) {
                    continue;
                }
                $candidateItem = is_array($candidateEntry['item'] ?? null) ? $candidateEntry['item'] : [];
                $candidateMeta = is_array($candidateEntry['category_meta'] ?? null) ? $candidateEntry['category_meta'] : [];
                $candidateTitle = trim((string)timeline_activity_title($candidateItem, $candidateMeta));
                $isConsultaVisible = ($candidateTitle === 'Consulta');
                $isAutoCierreVisible = timeline_demo_is_auto_cierre_marker($candidateEntry)
                    || stripos($candidateTitle, 'nota clínica auto') === 0 && stripos($candidateTitle, 'cierre') !== false;

                if ($isConsultaVisible) {
                    if ($consultaShown) {
                        continue;
                    }
                    $consultaShown = true;
                } elseif ($isAutoCierreVisible) {
                    if ($hasConsultaVisible || $autoCierreShown) {
                        continue;
                    }
                    $autoCierreShown = true;
                }

                $filteredDayEntries[] = $candidateEntry;
            }
            $dayRenderEntries = $filteredDayEntries;
        }
        ?>
        <section class="timeline-day-card" data-day-card="1" data-day-key="<?php echo h((string)$dayGroup['day_key']); ?>">
          <div class="timeline-day-header">
            <div>
              <div class="mm-activity-day-header"><?php echo h((string)$dayGroup['day_label']); ?></div>
            </div>
          </div>
          <div class="timeline-day-events">
            <?php foreach ($dayRenderEntries as $entry): ?>
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
                $appointmentId = trim((string)($links['appointment_id'] ?? ''));
                $appointmentRef = $appointmentId !== ''
                    ? 'appt:' . $appointmentId
                    : trim((string)($item['ref'] ?? ''));
                $isInActiveCase = (bool)($item['is_in_active_case'] ?? false);
                $itemCaseId = trim((string)($item['case_id'] ?? ''));
                $appointmentHasEncounter = (bool)($item['has_encounter'] ?? false);
                $appointmentEpisodeId = $appointmentId;
                $appointmentEncounterKey = trim((string)($item['encounter_key'] ?? ''));
                $appointmentIsActiveEncounter = encounter_key_matches_active($appointmentEncounterKey, $activeEncounterKey);
                $appointmentClinicalCategory = trim((string)($item['clinical_category'] ?? ''));
                $appointmentReasonText = trim((string)($agenda['reason_text'] ?? ''));
                $appointmentDisplayTitle = $entryTitle;
                if ($appointmentClinicalCategory === 'procedimiento') {
                    $appointmentDisplayTitle = 'Procedimiento programado';
                    if ($appointmentReasonText !== '') {
                        $appointmentDisplayTitle .= ': ' . $appointmentReasonText;
                    }
                }
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
                <article class="mm-card timeline-event mm-activity-item <?php echo $isInActiveCase ? 'is-in-active-case' : ''; ?> <?php echo $appointmentIsActiveEncounter ? 'is-active-encounter' : ''; ?>" data-timeline-item="1" data-role="timeline-item" data-case-id="<?php echo h($itemCaseId); ?>" data-in-active-case="<?php echo $isInActiveCase ? '1' : '0'; ?>" data-item-type="appointment" data-item-ref="<?php echo h($appointmentRef); ?>" data-encounter-key="<?php echo h($appointmentEncounterKey); ?>" data-category="<?php echo h($entryCategory); ?>" data-subtype="<?php echo h($entrySubtype); ?>" data-catalog-group="<?php echo h($entryCatalogGroup); ?>" data-catalog-phase="<?php echo h($entryCatalogPhase); ?>" data-catalog-group-label="<?php echo h($entryCatalogGroupLabel); ?>" data-catalog-priority="<?php echo $entryCatalogPriority; ?>" data-clinical-category="<?php echo h(trim((string)($item['clinical_category'] ?? ''))); ?>" data-study-role="<?php echo h(trim((string)($item['study_role'] ?? ''))); ?>" data-href="<?php echo h($appointmentHref); ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo h($entryTooltipText); ?>" title="<?php echo h($entryTooltipFallback); ?>">
                  <div class="mm-activity-icon" aria-hidden="true"><?php echo $entryIcon; ?></div>
                  <div class="mm-activity-body">
                    <div class="min-w-0 flex-grow-1">
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="mm-activity-title"><?php echo h($appointmentDisplayTitle); ?></div>
                        <?php if ($appointmentIsActiveEncounter): ?>
                          <span class="encounter-active-badge">Consulta actual</span>
                        <?php endif; ?>
                      </div>
                      <?php if (trim((string)($item['case_title'] ?? '')) !== ''): ?>
                        <div class="mm-activity-meta">Caso: <?php echo h((string)$item['case_title']); ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="mm-activity-actions clinical-card-actions text-muted small mt-1" data-role="appointment-episode-cta" data-appointment-id="<?php echo h($appointmentEpisodeId); ?>">
                      <?php $appointmentActionCount = 0; ?>
                      <?php if ($appointmentClinicalCategory === 'procedimiento' && $appointmentEpisodeId !== ''): ?>
                        <button type="button" class="action-link action-link-btn" data-action="register-procedure" data-appt-id="<?php echo h($appointmentEpisodeId); ?>" data-patient-id="<?php echo h($patientId); ?>" data-start-at="<?php echo h((string)($agenda['start_at'] ?? ($item['event_datetime'] ?? ''))); ?>" data-reason-text="<?php echo h($appointmentReasonText); ?>">Registrar procedimiento realizado</button>
                        <?php $appointmentActionCount++; ?>
                      <?php endif; ?>
                      <?php if ($appointmentClinicalCategory === 'procedimiento' && $appointmentEpisodeId !== ''): ?>
                        <?php if ($appointmentActionCount > 0): ?><span class="mx-1">·</span><?php endif; ?>
                        <button type="button" class="action-link action-link-btn" data-action="open-procedure-from-appointment" data-appointment-id="<?php echo h($appointmentEpisodeId); ?>" data-default-title="<?php echo h($appointmentReasonText); ?>" data-default-datetime="<?php echo h((string)($item['event_datetime'] ?? ($agenda['start_at'] ?? ''))); ?>">Agregar detalles</button>
                        <?php $appointmentActionCount++; ?>
                      <?php endif; ?>
                      <?php if (!$isInActiveCase && $appointmentRef !== ''): ?>
                        <?php if ($appointmentActionCount > 0): ?><span class="mx-1">·</span><?php endif; ?>
                        <span class="action-link" data-action="integrate-to-case" data-item-type="appointment" data-item-ref="<?php echo h($appointmentRef); ?>">Integrar a caso clínico</span>
                        <?php $appointmentActionCount++; ?>
                      <?php endif; ?>
                      <?php if (!$isInActiveCase && is_array($activeCase) && $appointmentRef !== ''): ?>
                        <?php if ($appointmentActionCount > 0): ?><span class="mx-1">·</span><?php endif; ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Agregar esta cita al caso activo?');">
                          <input type="hidden" name="action" value="add_active_case_appointment">
                          <input type="hidden" name="encounter_key" value="<?php echo h($appointmentEncounterKey); ?>">
                          <button type="submit" class="action-link action-link-btn">Agregar a caso activo</button>
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
                $encIsActiveEncounter = encounter_key_matches_active($ek, $activeEncounterKey);
                $encounterHref = '';
                if ($encHasEncounter && $ek !== '') {
                    $encounterHref = '/modules/clinical/ui/encounter.php?' . carry_embed_params(['encounter_key' => $ek]);
                }
                ?>
                <article class="mm-card timeline-event mm-activity-item <?php echo $encInActiveCase ? 'is-in-active-case' : ''; ?> <?php echo $encIsActiveEncounter ? 'is-active-encounter' : ''; ?>" data-timeline-item="1" data-role="timeline-item" data-case-id="<?php echo h($encCaseId); ?>" data-in-active-case="<?php echo $encInActiveCase ? '1' : '0'; ?>" data-item-type="encounter" data-item-ref="<?php echo h($ek); ?>" data-encounter-key="<?php echo h($ek); ?>" data-category="<?php echo h($entryCategory); ?>" data-subtype="<?php echo h($entrySubtype); ?>" data-catalog-group="<?php echo h($entryCatalogGroup); ?>" data-catalog-phase="<?php echo h($entryCatalogPhase); ?>" data-catalog-group-label="<?php echo h($entryCatalogGroupLabel); ?>" data-catalog-priority="<?php echo $entryCatalogPriority; ?>" data-clinical-category="<?php echo h(trim((string)($rawEncounter['clinical_category'] ?? ''))); ?>" data-study-role="<?php echo h(trim((string)($rawEncounter['study_role'] ?? ''))); ?>" data-href="<?php echo h($encounterHref); ?>" data-nav-mode="<?php echo $encounterHref !== '' ? 'encounter' : ''; ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo h($entryTooltipText); ?>" title="<?php echo h($entryTooltipFallback); ?>">
                  <div class="mm-activity-icon" aria-hidden="true"><?php echo $entryIcon; ?></div>
                  <div class="mm-activity-body">
                    <div class="min-w-0 flex-grow-1">
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="mm-activity-title"><?php echo h($entryTitle); ?></div>
                        <?php if ($encIsActiveEncounter): ?>
                          <span class="encounter-active-badge">Consulta actual</span>
                        <?php endif; ?>
                      </div>
                      <?php if (trim((string)($rawEncounter['case_title'] ?? '')) !== ''): ?>
                        <div class="mm-activity-meta">Caso: <?php echo h((string)$rawEncounter['case_title']); ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="mm-activity-actions clinical-card-actions text-muted small mt-1">
                      <?php $encounterActionCount = 0; ?>
                      <?php if (!$encInActiveCase && $ek !== ''): ?>
                        <span class="action-link" data-action="integrate-to-case" data-item-type="encounter" data-item-ref="<?php echo h($ek); ?>">Integrar a caso clínico</span>
                        <?php $encounterActionCount++; ?>
                      <?php endif; ?>
                      <?php if (!$encInActiveCase && is_array($activeCase) && $ek !== ''): ?>
                        <?php if ($encounterActionCount > 0): ?><span class="mx-1">·</span><?php endif; ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Agregar esta cita al caso activo?');">
                          <input type="hidden" name="action" value="add_active_case_appointment">
                          <input type="hidden" name="encounter_key" value="<?php echo h($ek); ?>">
                          <button type="submit" class="action-link action-link-btn">Agregar a caso activo</button>
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
                $docUuid = trim((string)($links['document_uuid'] ?? ($doc['document_uuid'] ?? ($doc['document_id'] ?? ''))));
                $docDbId = trim((string)($doc['document_db_id'] ?? ($doc['id'] ?? '')));
                $docPrimaryRef = $docUuid !== '' ? $docUuid : $docDbId;
                $docCaseId = trim((string)($docItem['case_id'] ?? ''));
                $docInActiveCase = (bool)($docItem['is_in_active_case'] ?? false);
                $docTypeNorm = strtolower(trim((string)($doc['document_type'] ?? '')));
                $docPayload = is_array($doc['payload'] ?? null) ? $doc['payload'] : [];
                $docSource = strtolower(trim((string)($docPayload['source'] ?? '')));
                $docFilePayload = is_array($docPayload['file'] ?? null) ? $docPayload['file'] : [];
                $docRenderMode = strtolower(trim((string)($doc['render_mode'] ?? ($docFilePayload['render_mode'] ?? ''))));
                $docIsImage = ($docTypeNorm === 'image' || $docRenderMode === 'image');
                $docOccurredAt = trim((string)($docItem['occurred_at'] ?? ($doc['occurred_at'] ?? ($docItem['event_datetime'] ?? ''))));
                $docOccurredDate = '';
                if ($docOccurredAt !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}/', $docOccurredAt, $docDateMatch)) {
                    $docOccurredDate = $docDateMatch[0];
                }
                $docUserTitle = trim((string)($doc['title'] ?? ($docPayload['title'] ?? ($docPayload['document_title'] ?? ''))));
                $docCategoryLabel = trim((string)($docPayload['document_category_label'] ?? ($docPayload['category_label'] ?? ($doc['media_tag_label'] ?? ($docPayload['media_tag_label'] ?? '')))));
                $docSummaryText = trim((string)($doc['summary'] ?? ''));
                $docTypeHumanLabel = timeline_human_document_type_label($docTypeNorm);
                $docDisplayTitle = $docUserTitle !== '' ? $docUserTitle : ($docTypeHumanLabel !== '' ? $docTypeHumanLabel : 'Documento');
                $docMetaLine1 = '';
                $docMetaLine2 = '';
                $docMetaLine3 = '';
                $studyStatusLine = '';
                $isImmunization = ($docTypeNorm === 'immunization');
                $isMedicationAdministration = ($docTypeNorm === 'medication_administration');
                $isWoundCare = ($docTypeNorm === 'wound_care');
                $isProcedure = ($docTypeNorm === 'procedure');
                $isConsentDoc = in_array($docTypeNorm, ['consentimiento_informado', 'consent_document'], true);
                $isSimpleUploadDoc = in_array($docTypeNorm, ['image', 'pdf', 'text', 'document', 'file'], true);
                $isAdjuntarFlowDoc = ($docSource === 'actividad_clinica_host');
                $isStudyOrder = in_array($docTypeNorm, ['lab_order', 'imaging_order', 'order'], true);
                $isStudyResult = in_array($docTypeNorm, ['lab_result', 'imaging_result', 'result', 'lab_pdf'], true);
                $isStudyDoc = $isStudyOrder || $isStudyResult;
                $docPayloadStatus = strtolower(trim((string)($docPayload['status'] ?? '')));
                $docStatus = $docPayloadStatus !== '' ? $docPayloadStatus : 'active';
                $replacedByRef = trim((string)($docPayload['replaced_by_document_uuid'] ?? ($docPayload['replaced_by_document_id'] ?? '')));
                $replacementSourceRef = trim((string)($docPayload['replacement_source_document_uuid'] ?? ($docPayload['replacement_source_document_id'] ?? '')));
                $resultSourceOrderRef = trim((string)($docPayload['related_order_document_uuid'] ?? ($docPayload['related_order_document_id'] ?? ($docPayload['related_document_uuid'] ?? ($docPayload['related_document_id'] ?? '')))));
                $linkedResultRef = '';
                if ($isStudyOrder) {
                    foreach ([$docUuid, $docDbId] as $orderRefCandidate) {
                        $candidate = trim((string)$orderRefCandidate);
                        if ($candidate !== '' && isset($studyResultRefByOrderRef[$candidate])) {
                            $linkedResultRef = trim((string)$studyResultRefByOrderRef[$candidate]);
                            break;
                        }
                    }
                }
                $hasLinkedResult = ($linkedResultRef !== '');
                $studyOrderVisualState = '';
                if ($isStudyOrder) {
                    $studyOrderVisualState = $hasLinkedResult ? 'Resultado disponible' : 'En espera de resultados';
                }
                if ($isStudyDoc) {
                    if (in_array($docTypeNorm, ['lab_order', 'lab_result', 'lab_pdf'], true)) {
                        $docDisplayTitle = 'Estudio de laboratorio';
                    } elseif (in_array($docTypeNorm, ['imaging_order', 'imaging_result'], true)) {
                        $docDisplayTitle = 'Estudio de imagen';
                    }
                    $requestedStudies = is_array($docPayload['requested_studies'] ?? null) ? $docPayload['requested_studies'] : [];
                    $requestedStudies = array_values(array_filter(array_map(static function ($v) {
                        return trim((string)$v);
                    }, $requestedStudies), static function ($v) {
                        return $v !== '';
                    }));
                    $selectionCount = (int)($docPayload['selection_count'] ?? count($requestedStudies));
                    $priority = trim((string)($docPayload['priority'] ?? ''));
                    $indication = trim((string)($docPayload['indication'] ?? ''));
                    $metaParts = [];
                    if ($selectionCount > 0) {
                        $metaParts[] = $selectionCount . ' estudio' . ($selectionCount === 1 ? '' : 's');
                    }
                    if ($priority !== '') {
                        $metaParts[] = $priority;
                    }
                    $docMetaLine1 = implode(' · ', $metaParts);
                    if (!$isStudyResult && $docStatus === 'replaced') {
                        $studyStatusLine = 'Orden reemplazada';
                    } elseif (!$isStudyResult && $docStatus === 'active' && $replacementSourceRef !== '') {
                        $studyStatusLine = 'Orden activa (reemplazo)';
                    }
                    if ($isStudyResult && $docOccurredAt !== '') {
                        $docMetaLine2 = $docOccurredAt;
                    } elseif ($docMetaLine2 === '' && !empty($requestedStudies)) {
                        $preview = array_slice($requestedStudies, 0, 3);
                        $previewText = implode(', ', $preview);
                        $remaining = count($requestedStudies) - count($preview);
                        if ($remaining > 0) {
                            $previewText .= ' y ' . $remaining . ' más';
                        }
                        $docMetaLine2 = $previewText;
                    }
                    if ($docMetaLine3 === '' && $indication !== '') {
                        $docMetaLine3 = 'Indicación: ' . $indication;
                    }
                    $studyCompactLine = '';
                    $studyCompactLineClass = 'est-order-studies-line';
                    if ($isStudyOrder) {
                        $studyCompactLine = $studyOrderVisualState;
                        if ($hasLinkedResult) {
                            $studyCompactLineClass .= ' is-complete';
                        } else {
                            $studyCompactLineClass .= ' is-pending';
                        }
                    } elseif (!empty($requestedStudies)) {
                        $preview = array_slice($requestedStudies, 0, 3);
                        $remaining = count($requestedStudies) - count($preview);
                        $studyCompactLine = 'Estudios: ' . implode(', ', $preview);
                        if ($remaining > 0) {
                            $studyCompactLine .= ' y ' . $remaining . ' más';
                        }
                    }
                } elseif ($isSimpleUploadDoc) {
                    $docPrimaryName = $docUserTitle;
                    if ($docPrimaryName === '' || timeline_is_generic_document_label($docPrimaryName)) {
                        if ($docSummaryText !== '' && !timeline_is_generic_document_label($docSummaryText)) {
                            $docPrimaryName = $docSummaryText;
                        }
                    }
                    $docDisplayTitle = $docPrimaryName !== '' ? $docPrimaryName : ($docTypeHumanLabel !== '' ? $docTypeHumanLabel : 'Documento');
                    $docMetaLine1 = $docTypeHumanLabel !== '' ? $docTypeHumanLabel : 'Documento';
                    if ($docSummaryText !== '' && timeline_normalize_label($docSummaryText) !== timeline_normalize_label($docDisplayTitle)) {
                        $docMetaLine3 = $docSummaryText;
                    }
                } elseif ($isConsentDoc) {
                    $consentStatus = strtolower(trim((string)($docPayload['status'] ?? ($docPayload['consent']['status'] ?? 'draft'))));
                    $consentStatusLabel = $consentStatus === 'granted' ? 'Emitido' : 'Borrador';
                    $consentTitle = trim((string)($docPayload['consent']['document_title'] ?? ''));
                    $consentProcedure = trim((string)($docPayload['consent_legal']['procedimiento'] ?? ($docPayload['procedure'] ?? '')));
                    $consentSignerName = trim((string)($docPayload['firmante']['nombre'] ?? ''));
                    $docDisplayTitle = $consentTitle !== '' ? $consentTitle : 'Consentimiento informado';
                    $docMetaLine1 = 'Estado: ' . $consentStatusLabel;
                    if ($docOccurredAt !== '') {
                        $docMetaLine2 = $docOccurredAt;
                    }
                    if ($consentSignerName !== '') {
                        $docMetaLine3 = 'Firmante: ' . $consentSignerName;
                    } elseif ($consentProcedure !== '') {
                        $docMetaLine3 = $consentProcedure;
                    }
                } elseif ($isImmunization) {
                    $vaccine = is_array($docPayload['vaccine'] ?? null) ? $docPayload['vaccine'] : [];
                    $trace = is_array($docPayload['trace'] ?? null) ? $docPayload['trace'] : [];
                    $schedule = is_array($docPayload['schedule'] ?? null) ? $docPayload['schedule'] : [];
                    $administration = is_array($docPayload['administration'] ?? null) ? $docPayload['administration'] : [];
                    $notes = is_array($docPayload['notes'] ?? null) ? $docPayload['notes'] : [];
                    $vaccineName = trim((string)($vaccine['product_name'] ?? ($docPayload['vaccine_name'] ?? '')));
                    $placeName = trim((string)($administration['place_name'] ?? ''));
                    $placeSector = trim((string)($administration['place_sector'] ?? ''));
                    $lot = trim((string)($trace['lot'] ?? ($docPayload['lot'] ?? '')));
                    $dose = trim((string)($schedule['dose_volume'] ?? ($docPayload['dose'] ?? '')));
                    $route = trim((string)($schedule['route'] ?? ($docPayload['route'] ?? '')));
                    $note = trim((string)($notes['clinical'] ?? ''));
                    $site = trim((string)($schedule['site'] ?? ($docPayload['site'] ?? '')));
                    $docDisplayTitle = 'Vacunación';
                    if ($vaccineName !== '') {
                        $docDisplayTitle = 'Vacunación: ' . $vaccineName;
                    }
                    if ($placeName !== '') {
                        $docMetaLine1 = 'Aplicada en: ' . $placeName;
                        if ($placeSector !== '') {
                            $docMetaLine1 .= ' (' . $placeSector . ')';
                        }
                    }
                    $detailParts = [];
                    if ($lot !== '') {
                        $detailParts[] = 'Lote ' . $lot;
                    }
                    if ($dose !== '') {
                        $detailParts[] = $dose;
                    }
                    if ($route !== '') {
                        $detailParts[] = $route;
                    }
                    $docMetaLine2 = implode(' · ', $detailParts);
                    if ($note !== '') {
                        $docMetaLine3 = 'Nota: ' . $note;
                    } elseif ($docMetaLine2 === '' && $site !== '') {
                        $docMetaLine3 = $site;
                    }
                } elseif ($isMedicationAdministration) {
                    $itemPayload = is_array($docPayload['item'] ?? null) ? $docPayload['item'] : [];
                    $administration = is_array($docPayload['administration'] ?? null) ? $docPayload['administration'] : [];
                    $medicationName = trim((string)($itemPayload['name'] ?? ''));
                    $dose = trim((string)($itemPayload['dose'] ?? ''));
                    $route = trim((string)($itemPayload['route'] ?? ''));
                    $placeType = trim((string)($administration['place_type'] ?? ''));
                    $placeName = trim((string)($administration['place_name'] ?? ''));
                    $placeSector = trim((string)($administration['place_sector'] ?? ''));
                    $note = trim((string)($docPayload['notes']['clinical'] ?? ''));
                    $docDisplayTitle = 'Aplicación de medicamento';
                    if ($medicationName !== '') {
                        $docDisplayTitle .= ': ' . $medicationName;
                    }
                    $detailParts = [];
                    if ($dose !== '') {
                        $detailParts[] = $dose;
                    }
                    if ($route !== '') {
                        $detailParts[] = $route;
                    }
                    $docMetaLine1 = implode(' · ', $detailParts);
                    if ($placeType === 'consultorio_prop') {
                        $docMetaLine2 = 'Aplicada en: Consultorio';
                    } elseif ($placeType === 'institucion') {
                        $placeLabel = ($placeName !== '' ? $placeName : 'Institución');
                        if ($placeSector !== '') {
                            $placeLabel .= ' (' . ucfirst($placeSector) . ')';
                        }
                        $docMetaLine2 = 'Aplicada en: ' . $placeLabel;
                    } elseif ($placeType === 'otro') {
                        $docMetaLine2 = 'Aplicada en: ' . ($placeName !== '' ? $placeName : 'Otro');
                    }
                    if ($note !== '') {
                        $docMetaLine3 = 'Nota: ' . $note;
                    }
                } elseif ($isWoundCare) {
                    $itemPayload = is_array($docPayload['item'] ?? null) ? $docPayload['item'] : [];
                    $administration = is_array($docPayload['administration'] ?? null) ? $docPayload['administration'] : [];
                    $woundName = trim((string)($itemPayload['name'] ?? ''));
                    $material = trim((string)($itemPayload['material'] ?? ''));
                    $placeType = trim((string)($administration['place_type'] ?? ''));
                    $placeName = trim((string)($administration['place_name'] ?? ''));
                    $placeSector = trim((string)($administration['place_sector'] ?? ''));
                    $note = trim((string)($docPayload['notes']['clinical'] ?? ''));
                    $docDisplayTitle = 'Curación';
                    if ($woundName !== '') {
                        $docDisplayTitle .= ': ' . $woundName;
                    }
                    if ($material !== '') {
                        $docMetaLine1 = 'Material: ' . $material;
                    }
                    if ($placeType === 'consultorio_prop') {
                        $docMetaLine2 = 'Aplicada en: Consultorio';
                    } elseif ($placeType === 'institucion') {
                        $placeLabel = ($placeName !== '' ? $placeName : 'Institución');
                        if ($placeSector !== '') {
                            $placeLabel .= ' (' . ucfirst($placeSector) . ')';
                        }
                        $docMetaLine2 = 'Aplicada en: ' . $placeLabel;
                    } elseif ($placeType === 'otro') {
                        $docMetaLine2 = 'Aplicada en: ' . ($placeName !== '' ? $placeName : 'Otro');
                    }
                    if ($note !== '') {
                        $docMetaLine3 = 'Nota: ' . $note;
                    }
                } elseif ($isProcedure) {
                    $itemPayload = is_array($docPayload['item'] ?? null) ? $docPayload['item'] : [];
                    $administration = is_array($docPayload['administration'] ?? null) ? $docPayload['administration'] : [];
                    $procedureName = trim((string)($itemPayload['name'] ?? ''));
                    $description = trim((string)($itemPayload['description'] ?? ''));
                    $placeType = trim((string)($administration['place_type'] ?? ''));
                    $placeName = trim((string)($administration['place_name'] ?? ''));
                    $placeSector = trim((string)($administration['place_sector'] ?? ''));
                    $note = trim((string)($docPayload['notes']['clinical'] ?? ''));
                    $docDisplayTitle = 'Procedimiento';
                    if ($procedureName !== '') {
                        $docDisplayTitle .= ': ' . $procedureName;
                    }
                    if ($description !== '') {
                        $docMetaLine1 = $description;
                    }
                    if ($placeType === 'consultorio_prop') {
                        $docMetaLine2 = 'Aplicada en: Consultorio';
                    } elseif ($placeType === 'institucion') {
                        $placeLabel = ($placeName !== '' ? $placeName : 'Institución');
                        if ($placeSector !== '') {
                            $placeLabel .= ' (' . ucfirst($placeSector) . ')';
                        }
                        $docMetaLine2 = 'Aplicada en: ' . $placeLabel;
                    } elseif ($placeType === 'otro') {
                        $docMetaLine2 = 'Aplicada en: ' . ($placeName !== '' ? $placeName : 'Otro');
                    }
                    if ($note !== '') {
                        $docMetaLine3 = 'Nota: ' . $note;
                    }
                } elseif (!$isStudyDoc) {
                    if ($docCategoryLabel !== '' && !timeline_is_generic_document_label($docCategoryLabel)) {
                        $docMetaLine1 = $docCategoryLabel;
                    }
                    if ($docOccurredAt !== '') {
                        $docMetaLine2 = $docOccurredAt;
                    }
                    if ($docSummaryText !== '' && timeline_normalize_label($docSummaryText) !== timeline_normalize_label($docDisplayTitle)) {
                        $docMetaLine3 = $docSummaryText;
                    }
                }
                foreach (['docMetaLine1', 'docMetaLine2', 'docMetaLine3'] as $metaVar) {
                    if (timeline_is_compact_datetime_text((string)$$metaVar)) {
                        $$metaVar = '';
                    }
                }
                if (timeline_is_generic_document_label($docDisplayTitle)) {
                    $docDisplayTitle = $docUserTitle !== '' ? $docUserTitle : ($docTypeHumanLabel !== '' ? $docTypeHumanLabel : 'Documento');
                }
                if (timeline_is_generic_document_label($docMetaLine1)) {
                    $docMetaLine1 = '';
                }
                if (!timeline_should_show_document_complementary($docMetaLine3, $docDisplayTitle)) {
                    $docMetaLine3 = '';
                }
                $docViewPath = $docIsImage || $isConsentDoc ? '/modules/clinical/ui/viewer.php' : '/modules/clinical/ui/document.php';
                $docHref = $docUuid !== '' ? $docViewPath . '?' . carry_embed_params(['uuid' => $docUuid]) : '';
                $studyVisualClass = '';
                if ($isStudyDoc) {
                    $studyVisualClass = ' est-order-card ' . (($docTypeNorm === 'lab_order' || $docTypeNorm === 'lab_result') ? 'est-order--lab' : 'est-order--img');
                }
                $documentVisualClass = !$isStudyDoc ? ' is-document-card' : '';
                $showDocIntegrateAction = (!$docInActiveCase && $docUuid !== '');
                ?>
                <article class="mm-card timeline-event mm-activity-item <?php echo h($studyVisualClass . $documentVisualClass); ?> <?php echo $docInActiveCase ? 'is-in-active-case' : ''; ?> <?php echo $isStudyDoc ? 'is-study-diagnostic ' . (($docTypeNorm === 'lab_order' || $docTypeNorm === 'lab_result') ? 'is-study-lab' : 'is-study-img') : ''; ?>" data-timeline-item="1" data-role="timeline-item" data-case-id="<?php echo h($docCaseId); ?>" data-in-active-case="<?php echo $docInActiveCase ? '1' : '0'; ?>" data-item-type="document" data-item-ref="<?php echo h($docPrimaryRef); ?>" data-document-id="<?php echo h($docDbId); ?>" data-document-uuid="<?php echo h($docUuid); ?>" data-document-type="<?php echo h($docTypeNorm); ?>" data-document-type-label="<?php echo h($docTypeHumanLabel !== '' ? $docTypeHumanLabel : 'Documento'); ?>" data-visible-title="<?php echo h($docDisplayTitle); ?>" data-result-ref="<?php echo h($linkedResultRef); ?>" data-category="<?php echo h($entryCategory); ?>" data-subtype="<?php echo h($entrySubtype); ?>" data-catalog-group="<?php echo h($entryCatalogGroup); ?>" data-catalog-phase="<?php echo h($entryCatalogPhase); ?>" data-catalog-group-label="<?php echo h($entryCatalogGroupLabel); ?>" data-catalog-priority="<?php echo $entryCatalogPriority; ?>" data-clinical-category="<?php echo h(trim((string)($docItem['clinical_category'] ?? ''))); ?>" data-study-role="<?php echo h(trim((string)($docItem['study_role'] ?? ''))); ?>" data-is-study-doc="<?php echo $isStudyDoc ? '1' : '0'; ?>" data-order-status="<?php echo h($docStatus); ?>" data-order-has-result="<?php echo $hasLinkedResult ? '1' : '0'; ?>" data-replaced-by-ref="<?php echo h($replacedByRef); ?>" data-replacement-source-ref="<?php echo h($replacementSourceRef); ?>" data-related-order-ref="<?php echo h($resultSourceOrderRef); ?>" data-href="<?php echo h($docHref); ?>" data-nav-mode="<?php echo $docHref !== '' ? 'document' : ''; ?>" data-doc-target="<?php echo $docIsImage ? 'image' : 'document'; ?>" data-uuid="<?php echo h($docUuid); ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo h($entryTooltipText); ?>" title="<?php echo h($entryTooltipFallback); ?>">
                  <div class="mm-activity-icon" aria-hidden="true"><?php echo $entryIcon; ?></div>
                  <div class="mm-activity-body">
                    <div class="min-w-0 flex-grow-1">
                      <?php if ($isStudyDoc): ?>
                        <div class="est-order-head">
                          <div class="est-order-title">
                            <span><?php echo h($docDisplayTitle); ?></span>
                          </div>
                        </div>
                        <div class="est-order-line2">
                          <?php if (!empty($studyCompactLine)): ?>
                            <div class="<?php echo h($studyCompactLineClass ?? 'est-order-studies-line'); ?>"><?php echo h($studyCompactLine); ?></div>
                          <?php else: ?>
                            <div class="est-order-studies-line is-empty"></div>
                          <?php endif; ?>
                          <div class="mm-activity-actions est-order-actions clinical-card-actions text-muted small mt-1">
                            <?php $hasStudyAction = false; ?>
                            <?php if ($isStudyOrder && !$hasLinkedResult && $docPrimaryRef !== ''): ?>
                              <span class="action-link" data-action="open-diagnostic-order-result" data-ref="<?php echo h($docPrimaryRef); ?>">Ingresar resultado</span>
                              <?php $hasStudyAction = true; ?>
                              <span class="mx-1">·</span>
                              <span class="action-link" data-action="open-diagnostic-order-replace" data-ref="<?php echo h($docPrimaryRef); ?>">Reemplazar orden</span>
                            <?php endif; ?>
                            <?php if (!$docInActiveCase && $docUuid !== ''): ?>
                              <?php if ($hasStudyAction): ?>
                                <span class="mx-1">·</span>
                              <?php endif; ?>
                              <span class="action-link" data-action="integrate-to-case" data-item-type="document" data-item-ref="<?php echo h($docUuid); ?>">Integrar a caso clínico</span>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php else: ?>
                        <div class="est-order-head">
                          <div class="est-order-title">
                            <span><?php echo h($docDisplayTitle); ?></span>
                          </div>
                        </div>
                        <div class="est-order-line2">
                          <?php if ($docMetaLine1 !== ''): ?>
                            <div class="est-order-studies-line est-doc-studies-line"><?php echo h($docMetaLine1); ?></div>
                          <?php else: ?>
                            <div class="est-order-studies-line est-doc-studies-line is-empty"></div>
                          <?php endif; ?>
                          <?php if ($showDocIntegrateAction): ?>
                            <div class="mm-activity-actions est-order-actions clinical-card-actions text-muted small mt-1">
                              <span class="action-link" data-action="integrate-to-case" data-item-type="document" data-item-ref="<?php echo h($docUuid); ?>">Integrar a caso clínico</span>
                            </div>
                          <?php endif; ?>
                        </div>
                        <?php if ($docMetaLine2 !== ''): ?>
                          <div class="mm-activity-meta"><?php echo h($docMetaLine2); ?></div>
                        <?php endif; ?>
                        <?php if ($docMetaLine3 !== ''): ?>
                          <div class="mm-activity-meta mm-activity-meta--complementary"><?php echo h($docMetaLine3); ?></div>
                        <?php endif; ?>
                        <?php if (trim((string)($docItem['case_title'] ?? '')) !== ''): ?>
                          <div class="mm-activity-meta">Caso: <?php echo h((string)$docItem['case_title']); ?></div>
                        <?php endif; ?>
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
                    <div class="mm-activity-actions clinical-card-actions text-muted small mt-1">
                      <?php $bundleActionCount = 0; ?>
                      <?php if (!$bundleInActiveCase && $bundleUuid !== ''): ?>
                        <span class="action-link" data-action="integrate-to-case" data-item-type="document" data-item-ref="<?php echo h($bundleUuid); ?>">Integrar a caso clínico</span>
                        <?php $bundleActionCount++; ?>
                      <?php endif; ?>
                      <?php if (!$bundleInActiveCase && $activeCaseId !== '' && $bundleUuid !== ''): ?>
                        <?php if ($bundleActionCount > 0): ?><span class="mx-1">·</span><?php endif; ?>
                        <button type="button" class="action-link action-link-btn" data-action="assign-case-item" data-case-id="<?php echo h($activeCaseId); ?>" data-item-type="document" data-item-ref="<?php echo h($bundleUuid); ?>">Agregar a caso activo</button>
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
          <h5 class="modal-title">Consulta</h5>
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
        <button type="button" class="btn-close" data-bs-dismiss="modal" data-action="close-cases-modal" aria-label="Cerrar"></button>
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
          <div class="border rounded p-2 bg-light-subtle">
            <div class="small text-secondary mb-1">Registro</div>
            <div class="fw-semibold" data-role="integrate-case-context-title">Registro clínico</div>
            <div class="small text-secondary" data-role="integrate-case-context-type"></div>
          </div>
          <div>
            <div class="fw-semibold mb-2">Casos existentes</div>
            <div class="text-secondary small mb-2 d-none" data-role="integrate-case-loading">Cargando casos...</div>
            <div class="alert alert-secondary small d-none mb-2" data-role="integrate-case-empty">Sin casos clínicos disponibles.</div>
            <div class="vstack gap-2" data-role="integrate-case-list"></div>
          </div>
          <div class="border-top pt-3">
            <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-action="toggle-create-case-section">+ Crear nuevo caso</button>
            <div class="d-none mt-3" data-role="create-case-section">
              <label for="clinicalCreateCaseTitle" class="form-label">Nombre del caso</label>
              <input
                type="text"
                class="form-control"
                id="clinicalCreateCaseTitle"
                data-role="create-case-title"
                placeholder="Ej. Fractura tibia y peroné"
                maxlength="190"
              >
              <div class="mt-2">
                <button type="button" class="btn btn-sm btn-primary" data-action="confirm-create-case">Crear caso e integrar</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-bs-dismiss="modal" data-action="cancel-create-case">Cancelar</button>
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
        <div data-role="document-viewer-meta" class="small text-secondary d-none mb-2"></div>
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
<div class="modal fade" id="clinicalImmunizationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Registrar vacuna</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger small d-none" data-role="immunization-form-error"></div>
        <form class="vstack gap-3" data-role="immunization-form">
          <div>
            <label for="immunizationPlaceType" class="form-label">Lugar de aplicación</label>
            <select id="immunizationPlaceType" class="form-select" data-role="immunization-place-type" required>
              <option value="">Selecciona una opción</option>
              <option value="consultorio_prop">Consultorio</option>
              <option value="institucion">Institución</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div class="d-none" data-role="immunization-place-name-wrap">
            <label for="immunizationPlaceName" class="form-label">¿Cuál? / ¿Dónde?</label>
            <input id="immunizationPlaceName" type="text" class="form-control" data-role="immunization-place-name" maxlength="190">
          </div>
          <div class="d-none" data-role="immunization-place-sector-wrap">
            <label for="immunizationPlaceSector" class="form-label">Sector</label>
            <select id="immunizationPlaceSector" class="form-select" data-role="immunization-place-sector">
              <option value="">Sin especificar</option>
              <option value="publica">Pública</option>
              <option value="privada">Privada</option>
            </select>
          </div>
          <div>
            <label for="immunizationVaccineSelect" class="form-label">Vacuna</label>
            <select id="immunizationVaccineSelect" class="form-select" data-role="immunization-vaccine-select" required>
              <option value="">Selecciona una vacuna…</option>
              <optgroup label="Comunes">
                <option value="influenza_tetravalente">Influenza tetravalente</option>
                <option value="hepatitis_b">Hepatitis B</option>
                <option value="td_tdap">Td/Tdap (tétanos-difteria/tosferina)</option>
                <option value="srp">SRP (triple viral)</option>
                <option value="vph">VPH</option>
                <option value="neumococo">Neumococo</option>
                <option value="covid_refuerzo">COVID-19 (refuerzo)</option>
              </optgroup>
              <optgroup label="Lista ampliada">
                <option value="bcg">BCG</option>
                <option value="rotavirus">Rotavirus</option>
                <option value="varicela">Varicela</option>
                <option value="hepatitis_a">Hepatitis A</option>
                <option value="meningococo">Meningococo</option>
                <option value="zoster">Herpes zóster</option>
                <option value="tosferina">Tosferina</option>
                <option value="polio_ipv">Polio (IPV)</option>
                <option value="dpt">DPT</option>
                <option value="hib">Hib (Haemophilus influenzae tipo b)</option>
              </optgroup>
              <option value="__other__">Otra (escribir)</option>
            </select>
          </div>
          <div class="d-none" data-role="immunization-other-vaccine-wrap">
            <label for="immunizationOtherVaccine" class="form-label">Especifica vacuna</label>
            <input id="immunizationOtherVaccine" type="text" class="form-control" data-role="immunization-other-vaccine" maxlength="190">
          </div>
          <div>
            <label for="immunizationManufacturer" class="form-label">Fabricante</label>
            <input id="immunizationManufacturer" type="text" class="form-control" data-role="immunization-manufacturer" maxlength="190">
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="immunizationLot" class="form-label">Lote</label>
              <input id="immunizationLot" type="text" class="form-control" data-role="immunization-lot" maxlength="120">
            </div>
            <div class="col-md-6">
              <label for="immunizationDose" class="form-label">Dosis</label>
              <input id="immunizationDose" type="text" class="form-control" data-role="immunization-dose" maxlength="120" placeholder="Ej. 0.5 mL">
            </div>
            <div class="col-md-6">
              <label for="immunizationRoute" class="form-label">Vía</label>
              <input id="immunizationRoute" type="text" class="form-control" data-role="immunization-route" maxlength="120" placeholder="Ej. IM, SC, ID">
            </div>
            <div class="col-md-6">
              <label for="immunizationEventDatetime" class="form-label">Fecha y hora</label>
              <input id="immunizationEventDatetime" type="datetime-local" class="form-control" data-role="immunization-event-datetime">
            </div>
          </div>
          <div>
            <label for="immunizationNotes" class="form-label">Notas</label>
            <textarea id="immunizationNotes" class="form-control" data-role="immunization-notes" rows="3" maxlength="2000"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-bs-dismiss="modal" data-action="cancel-immunization-modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary" data-action="submit-immunization">Guardar</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="clinicalWoundCareModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Registrar curación</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger small d-none" data-role="wound-care-form-error"></div>
        <form class="vstack gap-3" data-role="wound-care-form">
          <div>
            <label for="woundCareName" class="form-label">Nombre de curación</label>
            <input id="woundCareName" type="text" class="form-control" data-role="wound-care-name" maxlength="190" required>
          </div>
          <div>
            <label for="woundCareMaterial" class="form-label">Material</label>
            <input id="woundCareMaterial" type="text" class="form-control" data-role="wound-care-material" maxlength="190">
          </div>
          <div>
            <label for="woundCarePlaceType" class="form-label">Lugar de aplicación</label>
            <select id="woundCarePlaceType" class="form-select" data-role="wound-care-place-type" required>
              <option value="">Selecciona una opción</option>
              <option value="consultorio_prop">Consultorio</option>
              <option value="institucion">Institución</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div class="d-none" data-role="wound-care-place-name-wrap">
            <label for="woundCarePlaceName" class="form-label">¿Cuál? / ¿Dónde?</label>
            <input id="woundCarePlaceName" type="text" class="form-control" data-role="wound-care-place-name" maxlength="190">
          </div>
          <div class="d-none" data-role="wound-care-place-sector-wrap">
            <label for="woundCarePlaceSector" class="form-label">Sector</label>
            <select id="woundCarePlaceSector" class="form-select" data-role="wound-care-place-sector">
              <option value="">Sin especificar</option>
              <option value="publica">Pública</option>
              <option value="privada">Privada</option>
            </select>
          </div>
          <div>
            <label for="woundCareEventDatetime" class="form-label">Fecha y hora</label>
            <input id="woundCareEventDatetime" type="datetime-local" class="form-control" data-role="wound-care-event-datetime">
          </div>
          <div>
            <label for="woundCareNotes" class="form-label">Nota clínica</label>
            <textarea id="woundCareNotes" class="form-control" data-role="wound-care-notes" rows="3" maxlength="2000"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-bs-dismiss="modal" data-action="cancel-wound-care-modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary" data-action="submit-wound-care">Guardar</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="clinicalGenericProcedureModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Registrar procedimiento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger small d-none" data-role="generic-procedure-form-error"></div>
        <form class="vstack gap-3" data-role="generic-procedure-form">
          <input type="hidden" data-role="generic-procedure-appointment-id" value="">
          <div>
            <label for="genericProcedureType" class="form-label">Tipo de procedimiento</label>
            <select id="genericProcedureType" class="form-select" data-role="generic-procedure-type" required>
              <option value="">Selecciona una opción</option>
              <option value="immunization">Vacuna</option>
              <option value="medication_administration">Aplicación de medicamento</option>
              <option value="wound_care">Curación</option>
              <option value="other_procedure">Otro procedimiento</option>
            </select>
          </div>
          <div>
            <label for="genericProcedureEventDatetime" class="form-label">Fecha y hora</label>
            <input id="genericProcedureEventDatetime" type="datetime-local" class="form-control" data-role="generic-procedure-event-datetime" required>
          </div>
          <div>
            <label for="genericProcedurePlaceType" class="form-label">Lugar de aplicación</label>
            <select id="genericProcedurePlaceType" class="form-select" data-role="generic-procedure-place-type" required>
              <option value="">Selecciona una opción</option>
              <option value="consultorio_prop">Consultorio</option>
              <option value="institucion">Institución</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div class="d-none" data-role="generic-procedure-place-name-wrap">
            <label for="genericProcedurePlaceName" class="form-label">¿Cuál? / ¿Dónde?</label>
            <input id="genericProcedurePlaceName" type="text" class="form-control" data-role="generic-procedure-place-name" maxlength="190">
          </div>
          <div class="d-none" data-role="generic-procedure-place-sector-wrap">
            <label for="genericProcedurePlaceSector" class="form-label">Sector</label>
            <select id="genericProcedurePlaceSector" class="form-select" data-role="generic-procedure-place-sector">
              <option value="">Sin especificar</option>
              <option value="publica">Pública</option>
              <option value="privada">Privada</option>
            </select>
          </div>
          <div class="d-none" data-role="generic-procedure-medication-fields">
            <div class="row g-3">
              <div class="col-12">
                <label for="genericProcedureMedicationName" class="form-label">Medicamento (nombre)</label>
                <input id="genericProcedureMedicationName" type="text" class="form-control" data-role="generic-procedure-medication-name" maxlength="190">
              </div>
              <div class="col-md-6">
                <label for="genericProcedureMedicationDose" class="form-label">Dosis</label>
                <input id="genericProcedureMedicationDose" type="text" class="form-control" data-role="generic-procedure-medication-dose" maxlength="120">
              </div>
              <div class="col-md-6">
                <label for="genericProcedureMedicationRoute" class="form-label">Vía</label>
                <input id="genericProcedureMedicationRoute" type="text" class="form-control" data-role="generic-procedure-medication-route" maxlength="120">
              </div>
            </div>
          </div>
          <div class="d-none" data-role="generic-procedure-immunization-fields">
            <div class="row g-3">
              <div class="col-12">
                <label for="genericProcedureVaccineSelect" class="form-label">Vacuna</label>
                <select id="genericProcedureVaccineSelect" class="form-select" data-role="generic-procedure-vaccine-select">
                  <option value="">Selecciona una vacuna…</option>
                  <optgroup label="Comunes">
                    <option value="influenza_tetravalente">Influenza tetravalente</option>
                    <option value="hepatitis_b">Hepatitis B</option>
                    <option value="td_tdap">Td/Tdap (tétanos-difteria/tosferina)</option>
                    <option value="srp">SRP (triple viral)</option>
                    <option value="vph">VPH</option>
                    <option value="neumococo">Neumococo</option>
                    <option value="covid_refuerzo">COVID-19 (refuerzo)</option>
                  </optgroup>
                  <optgroup label="Lista ampliada">
                    <option value="bcg">BCG</option>
                    <option value="rotavirus">Rotavirus</option>
                    <option value="varicela">Varicela</option>
                    <option value="hepatitis_a">Hepatitis A</option>
                    <option value="meningococo">Meningococo</option>
                    <option value="zoster">Herpes zóster</option>
                    <option value="tosferina">Tosferina</option>
                    <option value="polio_ipv">Polio (IPV)</option>
                    <option value="dpt">DPT</option>
                    <option value="hib">Hib (Haemophilus influenzae tipo b)</option>
                  </optgroup>
                  <option value="__other__">Otra (escribir)</option>
                </select>
              </div>
              <div class="col-12 d-none" data-role="generic-procedure-other-vaccine-wrap">
                <label for="genericProcedureOtherVaccine" class="form-label">Especifica vacuna</label>
                <input id="genericProcedureOtherVaccine" type="text" class="form-control" data-role="generic-procedure-other-vaccine" maxlength="190">
              </div>
              <div class="col-md-6">
                <label for="genericProcedureManufacturer" class="form-label">Fabricante</label>
                <input id="genericProcedureManufacturer" type="text" class="form-control" data-role="generic-procedure-manufacturer" maxlength="190">
              </div>
              <div class="col-md-6">
                <label for="genericProcedureLot" class="form-label">Lote</label>
                <input id="genericProcedureLot" type="text" class="form-control" data-role="generic-procedure-lot" maxlength="120">
              </div>
              <div class="col-md-6">
                <label for="genericProcedureDose" class="form-label">Dosis</label>
                <input id="genericProcedureDose" type="text" class="form-control" data-role="generic-procedure-dose" maxlength="120">
              </div>
              <div class="col-md-6">
                <label for="genericProcedureRoute" class="form-label">Vía</label>
                <input id="genericProcedureRoute" type="text" class="form-control" data-role="generic-procedure-route" maxlength="120">
              </div>
            </div>
          </div>
          <div class="d-none" data-role="generic-procedure-wound-fields">
            <div class="row g-3">
              <div class="col-12">
                <label for="genericProcedureWoundName" class="form-label">Nombre de curación</label>
                <input id="genericProcedureWoundName" type="text" class="form-control" data-role="generic-procedure-wound-name" maxlength="190">
              </div>
              <div class="col-12">
                <label for="genericProcedureWoundMaterial" class="form-label">Material</label>
                <input id="genericProcedureWoundMaterial" type="text" class="form-control" data-role="generic-procedure-wound-material" maxlength="190">
              </div>
            </div>
          </div>
          <div class="d-none" data-role="generic-procedure-other-fields">
            <div class="row g-3">
              <div class="col-12">
                <label for="genericProcedureOtherName" class="form-label">Nombre del procedimiento</label>
                <input id="genericProcedureOtherName" type="text" class="form-control" data-role="generic-procedure-other-name" maxlength="190">
              </div>
              <div class="col-12">
                <label for="genericProcedureOtherDescription" class="form-label">Descripción</label>
                <textarea id="genericProcedureOtherDescription" class="form-control" data-role="generic-procedure-other-description" rows="3" maxlength="2000"></textarea>
              </div>
            </div>
          </div>
          <div>
            <label for="genericProcedureNotes" class="form-label">Nota clínica</label>
            <textarea id="genericProcedureNotes" class="form-control" data-role="generic-procedure-notes" rows="3" maxlength="2000"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-secondary" data-bs-dismiss="modal" data-action="cancel-generic-procedure-modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary" data-action="submit-generic-procedure">Guardar</button>
      </div>
    </div>
  </div>
</div>
<script>
  (function () {
    var patientId = <?php echo json_encode($patientId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var currentUserId = <?php echo json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var currentInclude = <?php echo json_encode($include, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var apiBase = <?php echo json_encode($clinicalApiClientBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var activeCaseId = <?php echo json_encode($activeCaseId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var isCasesView = <?php echo $isCasesView ? 'true' : 'false'; ?>;
    var isEmbed = <?php echo $embed ? 'true' : 'false'; ?>;
    var onlyActiveCaseStorageKey = 'mxmed_historial_only_active_case:' + String(patientId || '');
    var casesModalEl = document.getElementById('clinicalCasesModal');
    var casesModalList = document.getElementById('casesModalList');
    var casesModalEmpty = document.getElementById('casesModalEmpty');
    var casesModalLoading = document.getElementById('casesModalLoading');
    var casesTabList = document.querySelector('[data-role="cases-tab-list"]');
    var casesTabEmpty = document.querySelector('[data-role="cases-tab-empty"]');
    var casesTabLoading = document.querySelector('[data-role="cases-tab-loading"]');
    var casesTabError = document.querySelector('[data-role="cases-tab-error"]');
    var caseEmbedFrameLoaded = Object.create(null);
    var caseItemsCache = Object.create(null);
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
    var createCaseConfirmBtn = document.querySelector('[data-action="confirm-create-case"]');
    var integrateCaseContextTitle = document.querySelector('[data-role="integrate-case-context-title"]');
    var integrateCaseContextType = document.querySelector('[data-role="integrate-case-context-type"]');
    var createCaseSection = document.querySelector('[data-role="create-case-section"]');
    var createCaseModalInstance = null;
    if (createCaseModalEl && window.bootstrap && window.bootstrap.Modal) {
      createCaseModalInstance = window.bootstrap.Modal.getOrCreateInstance(createCaseModalEl);
    }
    function isHtmlIconMarkup(markup){
      var raw = String(markup || '').trim().toLowerCase();
      return raw.indexOf('<') >= 0 && raw.indexOf('>') >= 0;
    }
    function resolveApprovedImagingIconMarkup(){
      var exact = '<span class="tab-ico material-symbols-outlined" aria-hidden="true">radiology</span>';
      if (typeof window.resolveClinicalDocumentSvgIcon === 'function') {
        var hostSvg = String(window.resolveClinicalDocumentSvgIcon('imaging_order', 'Imagenología') || '');
        if (hostSvg && isHtmlIconMarkup(hostSvg)) return hostSvg;
      }
      if (window.parent && window.parent !== window && typeof window.parent.resolveClinicalDocumentSvgIcon === 'function') {
        var parentSvg = String(window.parent.resolveClinicalDocumentSvgIcon('imaging_order', 'Imagenología') || '');
        if (parentSvg && isHtmlIconMarkup(parentSvg)) return parentSvg;
      }
      var localTab = document.querySelector('#p-expediente [data-tab-key="t-estudios"] .tab-ico')
        || document.querySelector('[data-tab-key="t-estudios"] .tab-ico');
      if (localTab && localTab.outerHTML) return String(localTab.outerHTML);
      if (window.parent && window.parent !== window && window.parent.document) {
        try {
          var parentTab = window.parent.document.querySelector('#p-expediente [data-tab-key="t-estudios"] .tab-ico')
            || window.parent.document.querySelector('[data-tab-key="t-estudios"] .tab-ico');
          if (parentTab && parentTab.outerHTML) return String(parentTab.outerHTML);
        } catch (_e) {}
      }
      return exact;
    }
    function resolveLocalDiagnosticFallbackSvg(documentType, area){
      var type = String(documentType || '').trim().toLowerCase();
      var label = String(area || '').trim().toLowerCase();
      var map = {
        lab_order: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 4v7l-4 7a2 2 0 0 0 1.8 3h8.4A2 2 0 0 0 18 18l-4-7V4"></path><path d="M8 4h8"></path><path d="M9 14h6"></path></svg>',
        lab_result: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 4v7l-4 7a2 2 0 0 0 1.8 3h8.4A2 2 0 0 0 18 18l-4-7V4"></path><path d="M8 4h8"></path><path d="M9 14h6"></path></svg>',
        imaging_order: resolveApprovedImagingIconMarkup(),
        imaging_result: resolveApprovedImagingIconMarkup(),
        result: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v6l-3 3v5a2 2 0 0 1-4 0v-5L7 10V4z"></path><path d="M9 4v2"></path><path d="M15 4v2"></path></svg>'
      };
      if (map[type]) return map[type];
      if (label.indexOf('laboratorio') >= 0) return map.lab_order;
      if (label.indexOf('imagen') >= 0) return map.imaging_order;
      return map.result;
    }
    function resolveSharedDiagnosticIconSvg(documentType, area){
      if (typeof window.resolveClinicalDocumentSvgIcon === 'function') {
        var hostSvg = String(window.resolveClinicalDocumentSvgIcon(documentType, area) || '');
        if (hostSvg && isHtmlIconMarkup(hostSvg)) return hostSvg;
      }
      if (window.parent && window.parent !== window && typeof window.parent.resolveClinicalDocumentSvgIcon === 'function') {
        var parentSvg = String(window.parent.resolveClinicalDocumentSvgIcon(documentType, area) || '');
        if (parentSvg && isHtmlIconMarkup(parentSvg)) return parentSvg;
      }
      return resolveLocalDiagnosticFallbackSvg(documentType, area);
    }
    function hydrateSharedDiagnosticIcons(scope){
      var root = scope || document;
      if (!root || !root.querySelectorAll) return;
      var nodes = root.querySelectorAll('[data-role="diagnostic-icon"][data-document-type]');
      nodes.forEach(function (node) {
        var docType = String(node.getAttribute('data-document-type') || '').trim();
        var area = String(node.getAttribute('data-order-area') || '').trim();
        if (!docType) return;
        var iconSvg = resolveSharedDiagnosticIconSvg(docType, area);
        if (!iconSvg) return;
        node.classList.add('est-order-ico-svg');
        node.innerHTML = iconSvg;
        if (!node.querySelector('svg') && !node.querySelector('.material-symbols-outlined, .material-symbols-rounded')) {
          node.innerHTML = resolveLocalDiagnosticFallbackSvg(docType, area);
        }
      });
    }
    hydrateSharedDiagnosticIcons(document);
    window.addEventListener('DOMContentLoaded', function () {
      hydrateSharedDiagnosticIcons(document);
    });
    window.addEventListener('load', function () {
      hydrateSharedDiagnosticIcons(document);
      window.setTimeout(function () {
        hydrateSharedDiagnosticIcons(document);
      }, 250);
    });
    var onlyActiveCaseTrigger = document.querySelector('[data-role="case-summary-focus-trigger"]');
    var onlyActiveCaseNotice = document.querySelector('[data-role="only-active-case-note"]');
    var onlyActiveCasePoolNotice = document.querySelector('[data-role="only-active-case-pool-note"]');
    var categoryFilterWrap = document.querySelector('[data-role="timeline-category-filters"]');
    var studyFilterWrap = document.querySelector('[data-role="timeline-study-filters"]');
    var advancedFiltersPanel = document.querySelector('[data-role="advanced-filters-panel"]');
    var caseSummaryPanel = document.querySelector('[data-role="case-summary-panel"]');
    var openCasesButtons = document.querySelectorAll('[data-role="open-cases-btn"]');
    var advancedFiltersToggle = document.querySelector('[data-action="toggle-advanced-filters"]');
    var caseScopeEmpty = document.querySelector('[data-role="case-scope-empty"]');
    var recentSuggestion = document.querySelector('[data-role="recent-case-suggestion"]');
    var recentSuggestionText = document.querySelector('[data-role="recent-case-suggestion-text"]');
    var advancedFiltersVisible = false;
    var knownCasesCount = activeCaseId !== '' ? 1 : 0;
    var onlyActiveCaseEnabled = false;
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
    var documentViewerMeta = document.querySelector('[data-role="document-viewer-meta"]');
    var documentViewerBody = document.querySelector('[data-role="document-viewer-body"]');
    var documentViewerOpenNew = document.querySelector('[data-role="document-viewer-open-new"]');
    var documentViewerModalInstance = null;
    if (documentViewerModalEl && window.bootstrap && window.bootstrap.Modal) {
      documentViewerModalInstance = window.bootstrap.Modal.getOrCreateInstance(documentViewerModalEl);
    }
    var immunizationModalEl = document.getElementById('clinicalImmunizationModal');
    var immunizationModalInstance = null;
    if (immunizationModalEl && window.bootstrap && window.bootstrap.Modal) {
      immunizationModalInstance = window.bootstrap.Modal.getOrCreateInstance(immunizationModalEl);
    }
    var immunizationForm = document.querySelector('[data-role="immunization-form"]');
    var immunizationFormError = document.querySelector('[data-role="immunization-form-error"]');
    var immunizationPlaceType = document.querySelector('[data-role="immunization-place-type"]');
    var immunizationPlaceNameWrap = document.querySelector('[data-role="immunization-place-name-wrap"]');
    var immunizationPlaceName = document.querySelector('[data-role="immunization-place-name"]');
    var immunizationPlaceSectorWrap = document.querySelector('[data-role="immunization-place-sector-wrap"]');
    var immunizationPlaceSector = document.querySelector('[data-role="immunization-place-sector"]');
    var immunizationVaccineSelect = document.querySelector('[data-role="immunization-vaccine-select"]');
    var immunizationOtherVaccineWrap = document.querySelector('[data-role="immunization-other-vaccine-wrap"]');
    var immunizationOtherVaccine = document.querySelector('[data-role="immunization-other-vaccine"]');
    var immunizationManufacturer = document.querySelector('[data-role="immunization-manufacturer"]');
    var immunizationLot = document.querySelector('[data-role="immunization-lot"]');
    var immunizationDose = document.querySelector('[data-role="immunization-dose"]');
    var immunizationRoute = document.querySelector('[data-role="immunization-route"]');
    var immunizationEventDatetime = document.querySelector('[data-role="immunization-event-datetime"]');
    var immunizationNotes = document.querySelector('[data-role="immunization-notes"]');
    var immunizationSubmitBtn = document.querySelector('[data-action="submit-immunization"]');
    var immunizationSubmitting = false;
    var woundCareModalEl = document.getElementById('clinicalWoundCareModal');
    var woundCareModalInstance = null;
    if (woundCareModalEl && window.bootstrap && window.bootstrap.Modal) {
      woundCareModalInstance = window.bootstrap.Modal.getOrCreateInstance(woundCareModalEl);
    }
    var woundCareForm = document.querySelector('[data-role="wound-care-form"]');
    var woundCareFormError = document.querySelector('[data-role="wound-care-form-error"]');
    var woundCareName = document.querySelector('[data-role="wound-care-name"]');
    var woundCareMaterial = document.querySelector('[data-role="wound-care-material"]');
    var woundCarePlaceType = document.querySelector('[data-role="wound-care-place-type"]');
    var woundCarePlaceNameWrap = document.querySelector('[data-role="wound-care-place-name-wrap"]');
    var woundCarePlaceName = document.querySelector('[data-role="wound-care-place-name"]');
    var woundCarePlaceSectorWrap = document.querySelector('[data-role="wound-care-place-sector-wrap"]');
    var woundCarePlaceSector = document.querySelector('[data-role="wound-care-place-sector"]');
    var woundCareEventDatetime = document.querySelector('[data-role="wound-care-event-datetime"]');
    var woundCareNotes = document.querySelector('[data-role="wound-care-notes"]');
    var woundCareSubmitBtn = document.querySelector('[data-action="submit-wound-care"]');
    var woundCareSubmitting = false;
    var genericProcedureModalEl = document.getElementById('clinicalGenericProcedureModal');
    var genericProcedureModalInstance = null;
    if (genericProcedureModalEl && window.bootstrap && window.bootstrap.Modal) {
      genericProcedureModalInstance = window.bootstrap.Modal.getOrCreateInstance(genericProcedureModalEl);
    }
    var genericProcedureForm = document.querySelector('[data-role="generic-procedure-form"]');
    var genericProcedureFormError = document.querySelector('[data-role="generic-procedure-form-error"]');
    var genericProcedureAppointmentId = document.querySelector('[data-role="generic-procedure-appointment-id"]');
    var genericProcedureType = document.querySelector('[data-role="generic-procedure-type"]');
    var genericProcedureEventDatetime = document.querySelector('[data-role="generic-procedure-event-datetime"]');
    var genericProcedurePlaceType = document.querySelector('[data-role="generic-procedure-place-type"]');
    var genericProcedurePlaceNameWrap = document.querySelector('[data-role="generic-procedure-place-name-wrap"]');
    var genericProcedurePlaceName = document.querySelector('[data-role="generic-procedure-place-name"]');
    var genericProcedurePlaceSectorWrap = document.querySelector('[data-role="generic-procedure-place-sector-wrap"]');
    var genericProcedurePlaceSector = document.querySelector('[data-role="generic-procedure-place-sector"]');
    var genericProcedureImmunizationFields = document.querySelector('[data-role="generic-procedure-immunization-fields"]');
    var genericProcedureVaccineSelect = document.querySelector('[data-role="generic-procedure-vaccine-select"]');
    var genericProcedureOtherVaccineWrap = document.querySelector('[data-role="generic-procedure-other-vaccine-wrap"]');
    var genericProcedureOtherVaccine = document.querySelector('[data-role="generic-procedure-other-vaccine"]');
    var genericProcedureManufacturer = document.querySelector('[data-role="generic-procedure-manufacturer"]');
    var genericProcedureLot = document.querySelector('[data-role="generic-procedure-lot"]');
    var genericProcedureDose = document.querySelector('[data-role="generic-procedure-dose"]');
    var genericProcedureRoute = document.querySelector('[data-role="generic-procedure-route"]');
    var genericProcedureMedicationFields = document.querySelector('[data-role="generic-procedure-medication-fields"]');
    var genericProcedureMedicationName = document.querySelector('[data-role="generic-procedure-medication-name"]');
    var genericProcedureMedicationDose = document.querySelector('[data-role="generic-procedure-medication-dose"]');
    var genericProcedureMedicationRoute = document.querySelector('[data-role="generic-procedure-medication-route"]');
    var genericProcedureWoundFields = document.querySelector('[data-role="generic-procedure-wound-fields"]');
    var genericProcedureWoundName = document.querySelector('[data-role="generic-procedure-wound-name"]');
    var genericProcedureWoundMaterial = document.querySelector('[data-role="generic-procedure-wound-material"]');
    var genericProcedureOtherFields = document.querySelector('[data-role="generic-procedure-other-fields"]');
    var genericProcedureOtherName = document.querySelector('[data-role="generic-procedure-other-name"]');
    var genericProcedureOtherDescription = document.querySelector('[data-role="generic-procedure-other-description"]');
    var genericProcedureNotes = document.querySelector('[data-role="generic-procedure-notes"]');
    var genericProcedureSubmitBtn = document.querySelector('[data-action="submit-generic-procedure"]');
    var genericProcedureSubmitting = false;
    var immunizationVaccineCatalog = {
      influenza_tetravalente: 'Influenza tetravalente',
      hepatitis_b: 'Hepatitis B',
      td_tdap: 'Td/Tdap (tétanos-difteria/tosferina)',
      srp: 'SRP (triple viral)',
      vph: 'VPH',
      neumococo: 'Neumococo',
      covid_refuerzo: 'COVID-19 (refuerzo)',
      bcg: 'BCG',
      rotavirus: 'Rotavirus',
      varicela: 'Varicela',
      hepatitis_a: 'Hepatitis A',
      meningococo: 'Meningococo',
      zoster: 'Herpes zóster',
      tosferina: 'Tosferina',
      polio_ipv: 'Polio (IPV)',
      dpt: 'DPT',
      hib: 'Hib (Haemophilus influenzae tipo b)'
    };
    function populateImmunizationCatalogOptions() {
      var keys = Object.keys(immunizationVaccineCatalog || {});
      if (!keys.length) return;
      keys.sort(function (a, b) {
        return String(immunizationVaccineCatalog[a] || '').localeCompare(String(immunizationVaccineCatalog[b] || ''), 'es', { sensitivity: 'base' });
      });
      var applyToSelect = function (selectEl) {
        if (!selectEl) return;
        var existing = new Set();
        Array.from(selectEl.options || []).forEach(function (opt) {
          var v = String(opt.value || '').trim();
          if (v !== '') existing.add(v);
        });
        keys.forEach(function (key) {
          if (existing.has(key)) return;
          var opt = document.createElement('option');
          opt.value = key;
          opt.textContent = String(immunizationVaccineCatalog[key] || key);
          selectEl.appendChild(opt);
        });
      };
      applyToSelect(immunizationVaccineSelect);
      applyToSelect(genericProcedureVaccineSelect);
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
      var isStudyDoc = String(itemEl.getAttribute('data-is-study-doc') || '').trim() === '1';
      if (mode === 'document' && isStudyDoc) {
        var studyRef = String(itemEl.getAttribute('data-uuid') || itemEl.getAttribute('data-item-ref') || '').trim();
        if (studyRef) {
          openDiagnosticDocument(studyRef, 'Documento diagnóstico');
          return;
        }
      }
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

    function openDiagnosticDocument(ref, summaryHint) {
      var safeRef = String(ref || '').trim();
      if (!safeRef) return;
      if (isEmbed && window.parent && window.parent !== window && typeof window.parent.postMessage === 'function') {
        window.parent.postMessage({
          type: 'mxmed:embed:open-diagnostic-document',
          document_ref: safeRef
        }, '*');
        return;
      }
      if (typeof window.mxmedOpenDiagnosticDocumentDetail === 'function') {
        try {
          window.mxmedOpenDiagnosticDocumentDetail(safeRef, { source: 'historial_standalone' });
          return;
        } catch (_) {}
      }
      openDocumentViewer(safeRef, String(summaryHint || '').trim() || 'Documento diagnóstico', 'document');
    }

    function openDiagnosticOrderAction(action, ref) {
      var safeAction = String(action || '').trim();
      var safeRef = String(ref || '').trim();
      if (!safeAction || !safeRef) return;
      if (isEmbed && window.parent && window.parent !== window && typeof window.parent.postMessage === 'function') {
        window.parent.postMessage({
          type: 'mxmed:embed:diagnostic-order-action',
          action: safeAction,
          document_ref: safeRef
        }, '*');
        return;
      }
      if (safeAction === 'upload_result' && typeof window.mxmedOpenDiagnosticOrderResultModal === 'function') {
        try {
          window.mxmedOpenDiagnosticOrderResultModal(safeRef, { source: 'historial_standalone' });
          return;
        } catch (_) {}
      }
      if (safeAction === 'replace_order' && typeof window.mxmedStartDiagnosticOrderReplacement === 'function') {
        try {
          window.mxmedStartDiagnosticOrderReplacement(safeRef, { source: 'historial_standalone' });
          return;
        } catch (_) {}
      }
      window.alert('Esta acción operativa está disponible en el expediente integrado.');
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
        var visibleEvents = Array.from(card.querySelectorAll('[data-role="timeline-item"]')).filter(function (item) {
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
      var timelineItems = document.querySelectorAll('[data-role="timeline-item"]');
      var visibleCount = 0;
      var availableForIntegrationCount = 0;
      timelineItems.forEach(function (item) {
        var inActiveCase = String(item.getAttribute('data-in-active-case') || '').trim() === '1';
        var caseId = String(item.getAttribute('data-case-id') || '').trim();
        var hasAnyCase = caseId !== '';
        var isAvailableForIntegration = !inActiveCase && !hasAnyCase;
        var itemClinicalCategory = String(item.getAttribute('data-clinical-category') || '').trim();
        var itemStudyRole = String(item.getAttribute('data-study-role') || '').trim();
        var hide = false;

        if (onlyActiveCaseEnabled && activeCaseId !== '') {
          // En modo enfoque: mostrar caso activo + registros no integrados.
          // Ocultar eventos de otros casos para reducir ruido visual.
          hide = !inActiveCase && hasAnyCase;
        }
        if (!hide && clinicalCategoryFilter !== 'all') {
          hide = itemClinicalCategory !== clinicalCategoryFilter;
          if (!hide && clinicalCategoryFilter === 'estudio' && studyRoleFilter !== 'all') {
            hide = itemStudyRole !== studyRoleFilter;
          }
        }

        item.classList.toggle('d-none', hide);
        item.classList.toggle('is-focus-case-item', onlyActiveCaseEnabled && inActiveCase && !hide);
        item.classList.toggle('is-focus-available-item', onlyActiveCaseEnabled && isAvailableForIntegration && !hide);
        if (!hide) {
          visibleCount += 1;
          if (onlyActiveCaseEnabled && isAvailableForIntegration) {
            availableForIntegrationCount += 1;
          }
        }
      });
      if (onlyActiveCaseNotice) {
        onlyActiveCaseNotice.classList.toggle('d-none', !onlyActiveCaseEnabled || activeCaseId === '');
      }
      if (onlyActiveCasePoolNotice) {
        var showPoolNote = onlyActiveCaseEnabled && activeCaseId !== '' && availableForIntegrationCount > 0;
        onlyActiveCasePoolNotice.classList.toggle('d-none', !showPoolNote);
      }
      if (onlyActiveCaseTrigger) {
        onlyActiveCaseTrigger.classList.toggle('is-focused', onlyActiveCaseEnabled && activeCaseId !== '');
      }
      applyTimelineCategoryFilter();
      updateDayCardVisibility();
      if (caseScopeEmpty) {
        var showEmpty = visibleCount === 0;
        if (clinicalCategoryFilter === 'estudio' && studyRoleFilter !== 'all') {
          caseScopeEmpty.textContent = 'Sin eventos para el subfiltro de estudios seleccionado.';
        } else if (clinicalCategoryFilter !== 'all') {
          caseScopeEmpty.textContent = 'Sin eventos para la categoría clínica seleccionada.';
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

    function navigateWithInclude(nextInclude) {
      var includeValue = String(nextInclude || '').trim();
      if (!includeValue) return;
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('include', includeValue);
        window.location.href = url.toString();
      } catch (_) {}
    }

    function resetClinicalFiltersToAll() {
      clinicalCategoryFilter = 'all';
      studyRoleFilter = 'all';
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
      var nodes = Array.from(document.querySelectorAll('[data-role="timeline-item"]')).slice(0, 10);
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
        recentSuggestionText.textContent = 'Hay ' + recentCandidates.length + ' registros sin agrupar en casos clínicos.';
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
      if (!advancedFiltersToggle) return;
      if (advancedFiltersPanel) {
        advancedFiltersPanel.classList.toggle('d-none', !advancedFiltersVisible);
      }
      advancedFiltersToggle.textContent = advancedFiltersVisible ? 'Ocultar opciones avanzadas' : 'Ver opciones avanzadas';
    }

    function setImmunizationFormError(message) {
      if (!immunizationFormError) return;
      var text = String(message || '').trim();
      immunizationFormError.textContent = text;
      immunizationFormError.classList.toggle('d-none', text === '');
    }

    function syncImmunizationPlaceFields() {
      var placeType = immunizationPlaceType ? String(immunizationPlaceType.value || '').trim() : '';
      var needsPlaceName = placeType === 'institucion' || placeType === 'otro';
      var showSector = placeType === 'institucion';
      if (immunizationPlaceNameWrap) {
        immunizationPlaceNameWrap.classList.toggle('d-none', !needsPlaceName);
      }
      if (immunizationPlaceSectorWrap) {
        immunizationPlaceSectorWrap.classList.toggle('d-none', !showSector);
      }
      if (!needsPlaceName && immunizationPlaceName) {
        immunizationPlaceName.value = '';
      }
      if (!showSector && immunizationPlaceSector) {
        immunizationPlaceSector.value = '';
      }
    }

    function syncWoundCarePlaceFields() {
      var placeType = woundCarePlaceType ? String(woundCarePlaceType.value || '').trim() : '';
      var needsPlaceName = placeType === 'institucion' || placeType === 'otro';
      var showSector = placeType === 'institucion';
      if (woundCarePlaceNameWrap) {
        woundCarePlaceNameWrap.classList.toggle('d-none', !needsPlaceName);
      }
      if (woundCarePlaceSectorWrap) {
        woundCarePlaceSectorWrap.classList.toggle('d-none', !showSector);
      }
      if (!needsPlaceName && woundCarePlaceName) {
        woundCarePlaceName.value = '';
      }
      if (!showSector && woundCarePlaceSector) {
        woundCarePlaceSector.value = '';
      }
    }

    function syncGenericProcedurePlaceFields() {
      var placeType = genericProcedurePlaceType ? String(genericProcedurePlaceType.value || '').trim() : '';
      var needsPlaceName = placeType === 'institucion' || placeType === 'otro';
      var showSector = placeType === 'institucion';
      if (genericProcedurePlaceNameWrap) {
        genericProcedurePlaceNameWrap.classList.toggle('d-none', !needsPlaceName);
      }
      if (genericProcedurePlaceSectorWrap) {
        genericProcedurePlaceSectorWrap.classList.toggle('d-none', !showSector);
      }
      if (!needsPlaceName && genericProcedurePlaceName) {
        genericProcedurePlaceName.value = '';
      }
      if (!showSector && genericProcedurePlaceSector) {
        genericProcedurePlaceSector.value = '';
      }
    }

    function syncGenericProcedureTypeFields() {
      var procedureType = genericProcedureType ? String(genericProcedureType.value || '').trim() : '';
      if (genericProcedureImmunizationFields) {
        genericProcedureImmunizationFields.classList.toggle('d-none', procedureType !== 'immunization');
      }
      if (genericProcedureMedicationFields) {
        genericProcedureMedicationFields.classList.toggle('d-none', procedureType !== 'medication_administration');
      }
      if (genericProcedureWoundFields) {
        genericProcedureWoundFields.classList.toggle('d-none', procedureType !== 'wound_care');
      }
      if (genericProcedureOtherFields) {
        genericProcedureOtherFields.classList.toggle('d-none', procedureType !== 'other_procedure');
      }
      if (procedureType !== 'immunization') {
        if (genericProcedureVaccineSelect) genericProcedureVaccineSelect.value = '';
        if (genericProcedureOtherVaccine) genericProcedureOtherVaccine.value = '';
        if (genericProcedureManufacturer) genericProcedureManufacturer.value = '';
        if (genericProcedureLot) genericProcedureLot.value = '';
        if (genericProcedureDose) genericProcedureDose.value = '';
        if (genericProcedureRoute) genericProcedureRoute.value = '';
      }
      if (procedureType !== 'medication_administration') {
        if (genericProcedureMedicationName) genericProcedureMedicationName.value = '';
        if (genericProcedureMedicationDose) genericProcedureMedicationDose.value = '';
        if (genericProcedureMedicationRoute) genericProcedureMedicationRoute.value = '';
      }
      if (procedureType !== 'wound_care') {
        if (genericProcedureWoundName) genericProcedureWoundName.value = '';
        if (genericProcedureWoundMaterial) genericProcedureWoundMaterial.value = '';
      }
      if (procedureType !== 'other_procedure') {
        if (genericProcedureOtherName) genericProcedureOtherName.value = '';
        if (genericProcedureOtherDescription) genericProcedureOtherDescription.value = '';
      }
      syncGenericProcedureVaccineFields();
    }

    function syncGenericProcedureVaccineFields() {
      var vaccineKey = genericProcedureVaccineSelect ? String(genericProcedureVaccineSelect.value || '').trim() : '';
      var showOther = vaccineKey === '__other__';
      if (genericProcedureOtherVaccineWrap) {
        genericProcedureOtherVaccineWrap.classList.toggle('d-none', !showOther);
      }
      if (!showOther && genericProcedureOtherVaccine) {
        genericProcedureOtherVaccine.value = '';
      }
    }

    function syncImmunizationVaccineFields() {
      var vaccineKey = immunizationVaccineSelect ? String(immunizationVaccineSelect.value || '').trim() : '';
      var showOther = vaccineKey === '__other__';
      if (immunizationOtherVaccineWrap) {
        immunizationOtherVaccineWrap.classList.toggle('d-none', !showOther);
      }
      if (!showOther && immunizationOtherVaccine) {
        immunizationOtherVaccine.value = '';
      }
    }

    function syncImmunizationSubmitButton() {
      if (!immunizationSubmitBtn) return;
      immunizationSubmitBtn.disabled = immunizationSubmitting;
      immunizationSubmitBtn.textContent = immunizationSubmitting ? 'Guardando...' : 'Guardar';
    }

    function setWoundCareFormError(message) {
      if (!woundCareFormError) return;
      var text = String(message || '').trim();
      woundCareFormError.textContent = text;
      woundCareFormError.classList.toggle('d-none', text === '');
    }

    function syncWoundCareSubmitButton() {
      if (!woundCareSubmitBtn) return;
      woundCareSubmitBtn.disabled = woundCareSubmitting;
      woundCareSubmitBtn.textContent = woundCareSubmitting ? 'Guardando...' : 'Guardar';
    }

    function setGenericProcedureFormError(message) {
      if (!genericProcedureFormError) return;
      var text = String(message || '').trim();
      genericProcedureFormError.textContent = text;
      genericProcedureFormError.classList.toggle('d-none', text === '');
    }

    function syncGenericProcedureSubmitButton() {
      if (!genericProcedureSubmitBtn) return;
      genericProcedureSubmitBtn.disabled = genericProcedureSubmitting;
      genericProcedureSubmitBtn.textContent = genericProcedureSubmitting ? 'Guardando...' : 'Guardar';
    }

    function resetImmunizationForm() {
      if (immunizationForm) {
        immunizationForm.reset();
      }
      setImmunizationFormError('');
      immunizationSubmitting = false;
      syncImmunizationSubmitButton();
      syncImmunizationPlaceFields();
    }

    function resetWoundCareForm() {
      if (woundCareForm) {
        woundCareForm.reset();
      }
      setWoundCareFormError('');
      woundCareSubmitting = false;
      syncWoundCareSubmitButton();
      syncWoundCarePlaceFields();
    }

    function resetGenericProcedureForm() {
      if (genericProcedureForm) {
        genericProcedureForm.reset();
      }
      if (genericProcedureAppointmentId) {
        genericProcedureAppointmentId.value = '';
      }
      setGenericProcedureFormError('');
      genericProcedureSubmitting = false;
      syncGenericProcedureSubmitButton();
      syncGenericProcedurePlaceFields();
      syncGenericProcedureTypeFields();
    }

    function toDatetimeLocalValue(value) {
      var text = String(value || '').trim();
      if (!text) {
        return '';
      }
      var match = text.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/);
      if (match) {
        return match[1] + 'T' + match[2];
      }
      return '';
    }

    function openImmunizationModal() {
      if (!patientId) {
        window.alert('patient_id requerido para registrar vacuna.');
        return;
      }
      resetImmunizationForm();
      if (immunizationModalInstance) {
        immunizationModalInstance.show();
      } else if (immunizationModalEl) {
        immunizationModalEl.style.display = 'block';
        immunizationModalEl.classList.add('show');
      }
    }

    function openWoundCareModal() {
      if (!patientId) {
        window.alert('patient_id requerido para registrar curación.');
        return;
      }
      resetWoundCareForm();
      if (woundCareModalInstance) {
        woundCareModalInstance.show();
      } else if (woundCareModalEl) {
        woundCareModalEl.style.display = 'block';
        woundCareModalEl.classList.add('show');
      }
    }

    function openGenericProcedureModal(defaultType, defaults) {
      if (!patientId) {
        window.alert('patient_id requerido para registrar procedimiento.');
        return;
      }
      var modalDefaults = defaults && typeof defaults === 'object' ? defaults : {};
      resetGenericProcedureForm();
      if (genericProcedureType) {
        genericProcedureType.value = String(defaultType || 'immunization').trim() || 'immunization';
      }
      if (genericProcedureAppointmentId) {
        genericProcedureAppointmentId.value = String(modalDefaults.appointmentId || '').trim();
      }
      if (genericProcedureEventDatetime) {
        genericProcedureEventDatetime.value = toDatetimeLocalValue(modalDefaults.defaultDatetime || '');
      }
      if (genericProcedureOtherName && String(defaultType || '').trim() === 'other_procedure') {
        genericProcedureOtherName.value = String(modalDefaults.defaultTitle || '').trim();
      }
      syncGenericProcedureTypeFields();
      if (genericProcedureModalInstance) {
        genericProcedureModalInstance.show();
      } else if (genericProcedureModalEl) {
        genericProcedureModalEl.style.display = 'block';
        genericProcedureModalEl.classList.add('show');
      }
    }

    function closeImmunizationModal() {
      if (!immunizationModalEl) return;
      if (immunizationModalInstance) {
        immunizationModalInstance.hide();
        return;
      }
      immunizationModalEl.classList.remove('show');
      immunizationModalEl.style.display = 'none';
      immunizationModalEl.setAttribute('aria-hidden', 'true');
      resetImmunizationForm();
    }

    function closeWoundCareModal() {
      if (!woundCareModalEl) return;
      if (woundCareModalInstance) {
        woundCareModalInstance.hide();
        return;
      }
      woundCareModalEl.classList.remove('show');
      woundCareModalEl.style.display = 'none';
      woundCareModalEl.setAttribute('aria-hidden', 'true');
      resetWoundCareForm();
    }

    function closeGenericProcedureModal() {
      if (!genericProcedureModalEl) return;
      if (genericProcedureModalInstance) {
        genericProcedureModalInstance.hide();
        return;
      }
      genericProcedureModalEl.classList.remove('show');
      genericProcedureModalEl.style.display = 'none';
      genericProcedureModalEl.setAttribute('aria-hidden', 'true');
      resetGenericProcedureForm();
    }

    function normalizeEventDatetime(value) {
      var text = String(value || '').trim();
      if (!text) return '';
      if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(text)) {
        return text.replace('T', ' ') + ':00';
      }
      if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(text)) {
        return text.replace('T', ' ');
      }
      return text;
    }

    function resolveClinicalActorUserId() {
      if (currentUserId) {
        return String(currentUserId).trim();
      }
      if (window.MXMED_USER_ID) {
        return String(window.MXMED_USER_ID).trim();
      }
      if (window.__MXMED && window.__MXMED.user_id) {
        return String(window.__MXMED.user_id).trim();
      }
      var rootUserId = document.body ? String(document.body.getAttribute('data-user-id') || '').trim() : '';
      if (rootUserId) {
        return rootUserId;
      }
      // TODO: reemplazar este fallback por user_id de sesión real.
      return '1';
    }

    function buildImmunizationRequest(input) {
      var placeType = String(input.placeType || '').trim();
      var placeName = String(input.placeName || '').trim();
      var placeSector = String(input.placeSector || '').trim();
      var vaccineKey = String(input.vaccineKey || '').trim();
      var selectedVaccineLabel = String(input.selectedVaccineLabel || '').trim();
      var otherVaccineName = String(input.otherVaccineName || '').trim();
      var manufacturer = String(input.manufacturer || '').trim();
      var lot = String(input.lot || '').trim();
      var doseVolume = String(input.doseVolume || '').trim();
      var route = String(input.route || '').trim();
      var notesClinical = String(input.notesClinical || '').trim();
      var eventDatetime = String(input.eventDatetime || '').trim();
      var productName = '';

      if (!placeType) {
        return { error: 'Selecciona el lugar de aplicación.' };
      }
      if (!eventDatetime) {
        return { error: 'Captura la fecha y hora.' };
      }
      if (!vaccineKey) {
        return { error: 'Selecciona una vacuna.' };
      }
      if (vaccineKey === '__other__') {
        if (otherVaccineName.length < 3) {
          return { error: 'Especifica la vacuna con al menos 3 caracteres.' };
        }
        productName = otherVaccineName;
      } else {
        productName = selectedVaccineLabel;
      }
      if (!productName) {
        return { error: 'Selecciona una vacuna válida.' };
      }
      if ((placeType === 'institucion' || placeType === 'otro') && !placeName) {
        return { error: 'Indica ¿cuál? / ¿dónde? para el lugar de aplicación.' };
      }

      var payload = {
        administration: {
          place_type: placeType
        },
        vaccine: {
          product_name: productName
        },
        vaccine_name: productName
      };
      if (placeName) {
        payload.administration.place_name = placeName;
      }
      if (placeType === 'institucion' && placeSector) {
        payload.administration.place_sector = placeSector;
      }
      if (manufacturer) {
        payload.vaccine.manufacturer = manufacturer;
      }
      if (vaccineKey !== '__other__') {
        payload.vaccine.catalog_key = vaccineKey;
      }
      if (lot) {
        payload.trace = { lot: lot };
        payload.lot = lot;
      }
      if (doseVolume || route) {
        payload.schedule = {};
        if (doseVolume) {
          payload.schedule.dose_volume = doseVolume;
          payload.dose = doseVolume;
        }
        if (route) {
          payload.schedule.route = route;
          payload.route = route;
        }
      }
      if (notesClinical) {
        payload.notes = notesClinical;
      }

      return {
        error: '',
        request: {
          type: 'immunization',
          actor: { user_id: currentUserId || 'qa' },
          context: { patient_id: patientId },
          title: 'Vacunación',
          event_datetime: eventDatetime,
          payload: payload
        }
      };
    }

    async function submitImmunization() {
      if (immunizationSubmitting) return;
      if (!patientId) {
        setImmunizationFormError('patient_id requerido.');
        return;
      }
      var placeType = immunizationPlaceType ? String(immunizationPlaceType.value || '').trim() : '';
      var placeName = immunizationPlaceName ? String(immunizationPlaceName.value || '').trim() : '';
      var placeSector = immunizationPlaceSector ? String(immunizationPlaceSector.value || '').trim() : '';
      var vaccineKey = immunizationVaccineSelect ? String(immunizationVaccineSelect.value || '').trim() : '';
      var selectedVaccineLabel = '';
      if (immunizationVaccineSelect && immunizationVaccineSelect.selectedIndex >= 0) {
        var selectedOption = immunizationVaccineSelect.options[immunizationVaccineSelect.selectedIndex];
        selectedVaccineLabel = selectedOption ? String(selectedOption.text || '').trim() : '';
      }
      var otherVaccineName = immunizationOtherVaccine ? String(immunizationOtherVaccine.value || '').trim() : '';
      var productName = '';
      var manufacturer = immunizationManufacturer ? String(immunizationManufacturer.value || '').trim() : '';
      var lot = immunizationLot ? String(immunizationLot.value || '').trim() : '';
      var doseVolume = immunizationDose ? String(immunizationDose.value || '').trim() : '';
      var route = immunizationRoute ? String(immunizationRoute.value || '').trim() : '';
      var notesClinical = immunizationNotes ? String(immunizationNotes.value || '').trim() : '';
      var eventDatetime = normalizeEventDatetime(immunizationEventDatetime ? immunizationEventDatetime.value : '');

      var immunizationRequest = buildImmunizationRequest({
        placeType: placeType,
        placeName: placeName,
        placeSector: placeSector,
        vaccineKey: vaccineKey,
        selectedVaccineLabel: selectedVaccineLabel,
        otherVaccineName: otherVaccineName,
        manufacturer: manufacturer,
        lot: lot,
        doseVolume: doseVolume,
        route: route,
        notesClinical: notesClinical,
        eventDatetime: eventDatetime
      });
      if (immunizationRequest.error) {
        setImmunizationFormError(immunizationRequest.error);
        return;
      }

      immunizationSubmitting = true;
      syncImmunizationSubmitButton();
      setImmunizationFormError('');
      try {
        await apiJson(apiBase + '/api/clinical/index.php/documents', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(immunizationRequest.request),
          credentials: 'same-origin'
        });
        closeImmunizationModal();
        window.location.reload();
      } catch (err) {
        immunizationSubmitting = false;
        syncImmunizationSubmitButton();
        setImmunizationFormError(err && err.message ? err.message : 'No se pudo registrar la vacuna.');
      }
    }

    async function submitWoundCare() {
      if (woundCareSubmitting) return;
      if (!patientId) {
        setWoundCareFormError('patient_id requerido.');
        return;
      }
      var name = woundCareName ? String(woundCareName.value || '').trim() : '';
      var material = woundCareMaterial ? String(woundCareMaterial.value || '').trim() : '';
      var placeType = woundCarePlaceType ? String(woundCarePlaceType.value || '').trim() : '';
      var placeName = woundCarePlaceName ? String(woundCarePlaceName.value || '').trim() : '';
      var placeSector = woundCarePlaceSector ? String(woundCarePlaceSector.value || '').trim() : '';
      var eventDatetime = normalizeEventDatetime(woundCareEventDatetime ? woundCareEventDatetime.value : '');
      var notesClinical = woundCareNotes ? String(woundCareNotes.value || '').trim() : '';

      if (name.length < 3) {
        setWoundCareFormError('Ingresa el nombre de la curación.');
        return;
      }
      if (!placeType) {
        setWoundCareFormError('Selecciona el lugar de aplicación.');
        return;
      }
      if ((placeType === 'institucion' || placeType === 'otro') && !placeName) {
        setWoundCareFormError('Indica ¿cuál? / ¿dónde? para el lugar de aplicación.');
        return;
      }

      var payload = {
        administration: {
          place_type: placeType
        },
        item: {
          kind: 'procedure',
          name: name
        }
      };
      if (material) {
        payload.item.material = material;
      }
      if (placeName) {
        payload.administration.place_name = placeName;
      }
      if (placeType === 'institucion' && placeSector) {
        payload.administration.place_sector = placeSector;
      }
      if (notesClinical) {
        payload.notes = notesClinical;
      }

      var body = {
        type: 'wound_care',
        title: 'Curación',
        actor: { user_id: currentUserId || 'qa' },
        context: { patient_id: patientId },
        payload: payload
      };
      if (eventDatetime !== '') {
        body.event_datetime = eventDatetime;
      }

      woundCareSubmitting = true;
      syncWoundCareSubmitButton();
      setWoundCareFormError('');
      try {
        await apiJson(apiBase + '/api/clinical/index.php/documents', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(body),
          credentials: 'same-origin'
        });
        closeWoundCareModal();
        window.location.reload();
      } catch (err) {
        woundCareSubmitting = false;
        syncWoundCareSubmitButton();
        setWoundCareFormError(err && err.message ? err.message : 'No se pudo registrar la curación.');
      }
    }

    async function submitGenericProcedure() {
      if (genericProcedureSubmitting) return;
      if (!patientId) {
        setGenericProcedureFormError('patient_id requerido.');
        return;
      }
      var procedureType = genericProcedureType ? String(genericProcedureType.value || '').trim() : '';
      var eventDatetime = normalizeEventDatetime(genericProcedureEventDatetime ? genericProcedureEventDatetime.value : '');
      var placeType = genericProcedurePlaceType ? String(genericProcedurePlaceType.value || '').trim() : '';
      var placeName = genericProcedurePlaceName ? String(genericProcedurePlaceName.value || '').trim() : '';
      var placeSector = genericProcedurePlaceSector ? String(genericProcedurePlaceSector.value || '').trim() : '';
      var notesClinical = genericProcedureNotes ? String(genericProcedureNotes.value || '').trim() : '';
      var item = {};
      var title = 'Procedimiento';

      if (!procedureType) {
        setGenericProcedureFormError('Selecciona el tipo de procedimiento.');
        return;
      }
      if (!eventDatetime) {
        setGenericProcedureFormError('Captura la fecha y hora.');
        return;
      }
      if (!placeType) {
        setGenericProcedureFormError('Selecciona el lugar de aplicación.');
        return;
      }
      if ((placeType === 'institucion' || placeType === 'otro') && !placeName) {
        setGenericProcedureFormError('Indica ¿cuál? / ¿dónde? para el lugar de aplicación.');
        return;
      }

      if (procedureType === 'immunization') {
        var genericVaccineKey = genericProcedureVaccineSelect ? String(genericProcedureVaccineSelect.value || '').trim() : '';
        var genericSelectedVaccineLabel = '';
        if (genericProcedureVaccineSelect && genericProcedureVaccineSelect.selectedIndex >= 0) {
          var genericSelectedOption = genericProcedureVaccineSelect.options[genericProcedureVaccineSelect.selectedIndex];
          genericSelectedVaccineLabel = genericSelectedOption ? String(genericSelectedOption.text || '').trim() : '';
        }
        var genericImmunizationRequest = buildImmunizationRequest({
          placeType: placeType,
          placeName: placeName,
          placeSector: placeSector,
          vaccineKey: genericVaccineKey,
          selectedVaccineLabel: genericSelectedVaccineLabel,
          otherVaccineName: genericProcedureOtherVaccine ? String(genericProcedureOtherVaccine.value || '').trim() : '',
          manufacturer: genericProcedureManufacturer ? String(genericProcedureManufacturer.value || '').trim() : '',
          lot: genericProcedureLot ? String(genericProcedureLot.value || '').trim() : '',
          doseVolume: genericProcedureDose ? String(genericProcedureDose.value || '').trim() : '',
          route: genericProcedureRoute ? String(genericProcedureRoute.value || '').trim() : '',
          notesClinical: notesClinical,
          eventDatetime: eventDatetime
        });
        if (genericImmunizationRequest.error) {
          setGenericProcedureFormError(genericImmunizationRequest.error);
          return;
        }
        genericProcedureSubmitting = true;
        syncGenericProcedureSubmitButton();
        setGenericProcedureFormError('');
        try {
          await apiJson(apiBase + '/api/clinical/index.php/documents', {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(genericImmunizationRequest.request),
            credentials: 'same-origin'
          });
          closeGenericProcedureModal();
          window.location.reload();
        } catch (err) {
          genericProcedureSubmitting = false;
          syncGenericProcedureSubmitButton();
          setGenericProcedureFormError(err && err.message ? err.message : 'No se pudo registrar el procedimiento.');
        }
        return;
      } else if (procedureType === 'medication_administration') {
        var medicationName = genericProcedureMedicationName ? String(genericProcedureMedicationName.value || '').trim() : '';
        var medicationDose = genericProcedureMedicationDose ? String(genericProcedureMedicationDose.value || '').trim() : '';
        var medicationRoute = genericProcedureMedicationRoute ? String(genericProcedureMedicationRoute.value || '').trim() : '';
        if (!medicationName) {
          setGenericProcedureFormError('Ingresa el nombre del medicamento.');
          return;
        }
        item = { kind: 'medication', name: medicationName };
        if (medicationDose) item.dose = medicationDose;
        if (medicationRoute) item.route = medicationRoute;
        title = 'Aplicación de medicamento';
      } else if (procedureType === 'wound_care') {
        var woundName = genericProcedureWoundName ? String(genericProcedureWoundName.value || '').trim() : '';
        var woundMaterial = genericProcedureWoundMaterial ? String(genericProcedureWoundMaterial.value || '').trim() : '';
        if (!woundName) {
          setGenericProcedureFormError('Ingresa el nombre de la curación.');
          return;
        }
        item = { kind: 'procedure', name: woundName };
        if (woundMaterial) item.material = woundMaterial;
        title = 'Curación';
      } else if (procedureType === 'other_procedure') {
        var otherName = genericProcedureOtherName ? String(genericProcedureOtherName.value || '').trim() : '';
        var otherDescription = genericProcedureOtherDescription ? String(genericProcedureOtherDescription.value || '').trim() : '';
        if (!otherName) {
          setGenericProcedureFormError('Ingresa el nombre del procedimiento.');
          return;
        }
        item = { kind: 'procedure', name: otherName };
        if (otherDescription) item.description = otherDescription;
        title = 'Procedimiento';
      } else {
        setGenericProcedureFormError('Tipo de procedimiento no soportado.');
        return;
      }

      var payload = {
        administration: {
          place_type: placeType
        },
        item: item
      };
      if (placeName) {
        payload.administration.place_name = placeName;
      }
      if (placeType === 'institucion' && placeSector) {
        payload.administration.place_sector = placeSector;
      }
      if (notesClinical) {
        payload.notes = notesClinical;
      }

      var requestType = (procedureType === 'other_procedure') ? 'procedure' : procedureType;
      var requestContext = { patient_id: patientId };
      var genericAppointmentId = genericProcedureAppointmentId ? String(genericProcedureAppointmentId.value || '').trim() : '';
      if (genericAppointmentId) {
        requestContext.appointment_id = genericAppointmentId;
      }

      genericProcedureSubmitting = true;
      syncGenericProcedureSubmitButton();
      setGenericProcedureFormError('');
      try {
        await apiJson(apiBase + '/api/clinical/index.php/documents', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            type: requestType,
            title: title,
            event_datetime: eventDatetime,
            actor: { user_id: resolveClinicalActorUserId() },
            context: requestContext,
            payload: payload
          }),
          credentials: 'same-origin'
        });
        closeGenericProcedureModal();
        window.location.reload();
      } catch (err) {
        genericProcedureSubmitting = false;
        syncGenericProcedureSubmitButton();
        setGenericProcedureFormError(err && err.message ? err.message : 'No se pudo registrar el procedimiento.');
      }
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

    async function listCaseItems(caseId) {
      var id = String(caseId || '').trim();
      if (!id) return [];
      if (Object.prototype.hasOwnProperty.call(caseItemsCache, id)) {
        return Array.isArray(caseItemsCache[id]) ? caseItemsCache[id] : [];
      }
      var url = apiBase + '/api/clinical/index.php/cases/' + encodeURIComponent(id) + '/items?limit=200';
      var payload = await apiJson(url, { method: 'GET' });
      var list = Array.isArray(payload.data) ? payload.data : [];
      caseItemsCache[id] = list;
      return list;
    }

    function buildCaseEmbedSrc(caseId) {
      var id = String(caseId || '').trim();
      if (!id || !patientId) return '';
      return '/modules/clinical/ui/historial.php?patient_id=' + encodeURIComponent(patientId)
        + '&embed=1&view=historial&case_embed=1&case_id=' + encodeURIComponent(id);
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
        row.className = 'border rounded p-2';
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

    function setCasesTabLoading(flag) {
      if (!casesTabLoading) return;
      casesTabLoading.classList.toggle('d-none', !flag);
    }

    function setCasesTabError(message) {
      if (!casesTabError) return;
      var text = String(message || '').trim();
      casesTabError.textContent = text;
      casesTabError.classList.toggle('d-none', text === '');
    }

    function renderCasesTab(cases) {
      if (!casesTabList || !casesTabEmpty) return;
      var list = Array.isArray(cases) ? cases : [];
      casesTabList.innerHTML = '';
      casesTabEmpty.classList.toggle('d-none', list.length > 0);
      list.forEach(function (item) {
        var row = document.createElement('div');
        row.className = 'border rounded p-2 case-tab-card';
        var caseId = String(item.case_id || '').trim();
        var title = String(item.title || 'Caso clínico').trim();
        var active = String(item.status || '').trim() === 'active';
        var itemsCountRaw = (item && item.items_count !== undefined && item.items_count !== null)
          ? String(item.items_count).trim()
          : '';
        if (active) {
          row.classList.add('is-active-case');
        }
        row.innerHTML = ''
          + '<div class="case-tab-head" data-action="toggle-case-items" data-case-id="' + escapeHtml(caseId) + '" role="button" tabindex="0" aria-expanded="false">'
          + '  <div class="d-flex align-items-center justify-content-between gap-2">'
          + '    <span class="fw-semibold d-inline-flex align-items-center gap-2"><span>' + escapeHtml(title) + '</span><span class="case-tab-caret" aria-hidden="true">▾</span></span>'
          + '    <button type="button" class="action-link action-link-btn small text-secondary" data-action="rename-case-from-modal" data-case-id="' + escapeHtml(caseId) + '" data-case-title="' + escapeHtml(title) + '">Renombrar</button>'
          + '  </div>'
          + '  <div class="small text-secondary">#' + escapeHtml(caseId) + ' · ' + escapeHtml(String(item.updated_at || '-').trim()) + (itemsCountRaw !== '' ? ' · items: ' + escapeHtml(itemsCountRaw) : '') + '</div>'
          + (active ? '  <div class="small text-secondary mt-1">Caso activo</div>' : '')
          + '</div>'
          + '<div class="case-tab-items d-none mt-2" data-role="case-items" data-case-id="' + escapeHtml(caseId) + '">'
          + '  <div class="small text-secondary d-none" data-role="case-items-empty">Este caso aún no tiene registros integrados.</div>'
          + '  <div class="small text-secondary d-none" data-role="case-items-loading">Cargando registros...</div>'
          + '  <iframe class="case-tab-items-iframe d-none" data-role="case-items-iframe" src="about:blank" loading="lazy" title="Registros del caso clínico"></iframe>'
          + '</div>';
        casesTabList.appendChild(row);
      });
    }

    async function toggleCaseItems(headEl) {
      if (!headEl) return;
      var caseId = String(headEl.getAttribute('data-case-id') || '').trim();
      if (!caseId) return;
      var card = headEl.closest ? headEl.closest('.case-tab-card') : null;
      if (!card) return;
      var panel = card.querySelector('[data-role="case-items"]');
      if (!panel) return;
      var isOpen = !panel.classList.contains('d-none');
      if (isOpen) {
        panel.classList.add('d-none');
        headEl.setAttribute('aria-expanded', 'false');
        card.classList.remove('is-open');
        return;
      }

      panel.classList.remove('d-none');
      headEl.setAttribute('aria-expanded', 'true');
      card.classList.add('is-open');
      var emptyNode = panel.querySelector('[data-role="case-items-empty"]');
      var loadingNode = panel.querySelector('[data-role="case-items-loading"]');
      var iframeNode = panel.querySelector('[data-role="case-items-iframe"]');
      if (emptyNode) emptyNode.classList.add('d-none');
      if (loadingNode) loadingNode.classList.remove('d-none');
      if (loadingNode) loadingNode.textContent = 'Cargando registros...';
      if (iframeNode) iframeNode.classList.add('d-none');
      try {
        var caseItems = await listCaseItems(caseId);
        if (loadingNode) loadingNode.classList.add('d-none');
        if (!Array.isArray(caseItems) || caseItems.length < 1) {
          if (emptyNode) emptyNode.classList.remove('d-none');
          return;
        }
      } catch (err) {
        if (loadingNode) {
          loadingNode.textContent = err && err.message ? String(err.message) : 'No se pudieron cargar los registros del caso.';
        }
        return;
      }
      if (!iframeNode) return;
      iframeNode.classList.remove('d-none');
      if (caseEmbedFrameLoaded[caseId] === true) {
        return;
      }
      var nextSrc = buildCaseEmbedSrc(caseId);
      if (!nextSrc) return;
      iframeNode.setAttribute('src', nextSrc);
      caseEmbedFrameLoaded[caseId] = true;
    }

    async function loadCasesTab() {
      if (!isCasesView) return;
      if (!patientId) {
        setCasesTabError('patient_id requerido para listar casos.');
        renderCasesTab([]);
        return;
      }
      setCasesTabLoading(true);
      setCasesTabError('');
      try {
        var cases = await listCases(patientId);
        knownCasesCount = cases.length;
        updateCaseSummaryVisibility();
        renderCasesTab(cases);
      } catch (err) {
        renderCasesTab([]);
        setCasesTabError(err && err.message ? err.message : 'No se pudieron listar casos clínicos.');
      } finally {
        setCasesTabLoading(false);
      }
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

    function syncIntegrateCaseButtons() {
      if (createCaseConfirmBtn) {
        createCaseConfirmBtn.disabled = integrateCaseSubmitting || createCaseSubmitting;
        createCaseConfirmBtn.textContent = createCaseSubmitting ? 'Creando...' : 'Crear caso e integrar';
      }
      if (integrateCaseList) {
        var rowButtons = integrateCaseList.querySelectorAll('[data-action="integrate-case-here"]');
        rowButtons.forEach(function (btn) {
          btn.disabled = integrateCaseSubmitting || createCaseSubmitting;
          btn.textContent = integrateCaseSubmitting ? 'Integrando...' : 'Integrar aquí';
        });
      }
    }

    function setIntegrateCaseContext(context) {
      var data = context && typeof context === 'object' ? context : {};
      var title = String(data.label || '').trim();
      var typeLabel = String(data.typeLabel || '').trim();
      if (title === '') title = 'Registro clínico';
      if (integrateCaseContextTitle) {
        integrateCaseContextTitle.textContent = title;
      }
      if (integrateCaseContextType) {
        integrateCaseContextType.textContent = typeLabel;
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
        var row = document.createElement('div');
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
          + '<input class="form-check-input mt-1 opacity-50" type="radio" name="integrate_case_id" value="' + escapeHtml(caseId) + '"' + (checked ? ' checked' : '') + ' aria-label="Seleccionar caso ' + escapeHtml(title) + '">'
          + '<div class="flex-grow-1">'
          + '  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">'
          + '    <span class="fw-semibold">' + escapeHtml(title) + '</span>'
          + '    <button type="button" class="mm-btn mm-btn-sm mm-btn-outline-primary" data-action="integrate-case-here" data-case-id="' + escapeHtml(caseId) + '">Integrar aquí</button>'
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
      setIntegrateCaseContext(pendingCaseIntegration || null);
      if (createCaseTitleInput) {
        createCaseTitleInput.value = '';
      }
      if (createCaseSection) {
        createCaseSection.classList.add('d-none');
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
      if (createCaseSection) {
        createCaseSection.classList.add('d-none');
      }
      syncIntegrateCaseButtons();
      if (createCaseModalInstance) {
        createCaseModalInstance.hide();
      } else if (createCaseModalEl) {
        createCaseModalEl.classList.remove('show');
        createCaseModalEl.style.display = 'none';
      }
    }

    async function ensureActiveCaseThenAssign(itemType, itemRef, contextMeta) {
      var nextItemType = String(itemType || '').trim();
      var nextItemRef = String(itemRef || '').trim();
      if (!nextItemType || !nextItemRef) return;
      openCreateCaseModal({
        itemType: nextItemType,
        itemRef: nextItemRef,
        label: contextMeta && contextMeta.label ? String(contextMeta.label || '').trim() : '',
        typeLabel: contextMeta && contextMeta.typeLabel ? String(contextMeta.typeLabel || '').trim() : ''
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

    async function openDocumentViewer(uuid, summaryHint, mode, options) {
      var key = String(uuid || '').trim();
      if (!key || !documentViewerModalEl) return;
      var opts = options && typeof options === 'object' ? options : {};
      activeDocumentUrl = buildDocumentUrl(key, mode);
      openDocumentViewerModal();
      if (documentViewerError) documentViewerError.classList.add('d-none');
      if (documentViewerBody) documentViewerBody.innerHTML = '';
      if (documentViewerMeta) {
        documentViewerMeta.classList.add('d-none');
        documentViewerMeta.textContent = '';
      }
      if (documentViewerOpenNew) {
        documentViewerOpenNew.setAttribute('href', activeDocumentUrl || '#');
      }
      var titleText = String(summaryHint || '').trim();
      if (!titleText) {
        titleText = 'Documento';
      }
      if (documentViewerTitle) {
        documentViewerTitle.textContent = titleText;
      }
      var typeLabel = String(opts.typeLabel || '').trim();
      if (documentViewerMeta && typeLabel !== '') {
        documentViewerMeta.textContent = typeLabel;
        documentViewerMeta.classList.remove('d-none');
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
        metaParts.push('Consulta: ' + String(data.encounter_key || '-'));
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

      var toggleCaseItemsBtn = event.target && event.target.closest ? event.target.closest('[data-action="toggle-case-items"]') : null;
      if (toggleCaseItemsBtn) {
        var withinRename = event.target && event.target.closest
          ? event.target.closest('[data-action="rename-case-from-modal"]')
          : null;
        if (!withinRename) {
          event.preventDefault();
          toggleCaseItems(toggleCaseItemsBtn);
          return;
        }
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

      var toggleAdvancedFiltersBtn = event.target && event.target.closest ? event.target.closest('[data-action="toggle-advanced-filters"]') : null;
      if (toggleAdvancedFiltersBtn) {
        event.preventDefault();
        advancedFiltersVisible = !advancedFiltersVisible;
        applyAdvancedFiltersVisibility();
        return;
      }

      var openImmunizationBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-immunization-modal"]') : null;
      if (openImmunizationBtn) {
        event.preventDefault();
        openGenericProcedureModal('immunization');
        return;
      }

      var openWoundCareBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-wound-care-modal"]') : null;
      if (openWoundCareBtn) {
        event.preventDefault();
        openGenericProcedureModal('wound_care');
        return;
      }

      var openGenericProcedureBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-generic-procedure-modal"]') : null;
      if (openGenericProcedureBtn) {
        event.preventDefault();
        openGenericProcedureModal('immunization');
        return;
      }

      var registerProcedureBtn = event.target && event.target.closest ? event.target.closest('[data-action="register-procedure"]') : null;
      if (registerProcedureBtn) {
        event.preventDefault();
        var registerAppointmentId = String(registerProcedureBtn.getAttribute('data-appt-id') || '').trim();
        var registerStartAt = String(registerProcedureBtn.getAttribute('data-start-at') || '').trim();
        var registerReasonText = String(registerProcedureBtn.getAttribute('data-reason-text') || '').trim();
        if (!registerAppointmentId) {
          window.alert('Faltan datos del appointment para registrar el procedimiento.');
          return;
        }
        openGenericProcedureModal('other_procedure', {
          appointmentId: registerAppointmentId,
          defaultTitle: registerReasonText,
          defaultDatetime: registerStartAt
        })
        if (genericProcedureNotes) {
          genericProcedureNotes.value = 'Registrado desde agenda';
        }
        return;
      }

      var openProcedureFromAppointmentBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-procedure-from-appointment"]') : null;
      if (openProcedureFromAppointmentBtn) {
        event.preventDefault();
        openGenericProcedureModal('other_procedure', {
          appointmentId: String(openProcedureFromAppointmentBtn.getAttribute('data-appointment-id') || '').trim(),
          defaultTitle: String(openProcedureFromAppointmentBtn.getAttribute('data-default-title') || '').trim(),
          defaultDatetime: String(openProcedureFromAppointmentBtn.getAttribute('data-default-datetime') || '').trim()
        });
        return;
      }

      var setClinicalFilterBtn = event.target && event.target.closest ? event.target.closest('[data-action="set-clinical-filter"]') : null;
      if (setClinicalFilterBtn) {
        event.preventDefault();
        clinicalCategoryFilter = String(setClinicalFilterBtn.getAttribute('data-clinical-filter') || 'all').trim();
        if (clinicalCategoryFilter === '') {
          clinicalCategoryFilter = 'all';
        }
        if (clinicalCategoryFilter === 'all') {
          studyRoleFilter = 'all';
          if (currentInclude !== 'agenda,clinical') {
            navigateWithInclude('agenda,clinical');
            return;
          }
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
        casesModalEl.setAttribute('aria-hidden', 'true');
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

      var integrateCaseHereBtn = event.target && event.target.closest ? event.target.closest('[data-action="integrate-case-here"]') : null;
      if (integrateCaseHereBtn) {
        event.preventDefault();
        if (!pendingCaseIntegration) {
          setIntegrateCaseError('Selecciona un elemento para integrar.');
          return;
        }
        var directCaseId = String(integrateCaseHereBtn.getAttribute('data-case-id') || '').trim();
        if (!directCaseId) {
          setIntegrateCaseError('Selecciona un caso destino.');
          return;
        }
        integrateToCase(directCaseId, pendingCaseIntegration.itemType, pendingCaseIntegration.itemRef);
        return;
      }

      var toggleCreateCaseSectionBtn = event.target && event.target.closest ? event.target.closest('[data-action="toggle-create-case-section"]') : null;
      if (toggleCreateCaseSectionBtn) {
        event.preventDefault();
        if (!createCaseSection) return;
        var willShow = createCaseSection.classList.contains('d-none');
        createCaseSection.classList.toggle('d-none', !willShow);
        if (willShow && createCaseTitleInput) {
          createCaseTitleInput.focus();
        }
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

      var cancelImmunizationBtn = event.target && event.target.closest ? event.target.closest('[data-action="cancel-immunization-modal"]') : null;
      if (cancelImmunizationBtn && !immunizationModalInstance && immunizationModalEl) {
        event.preventDefault();
        closeImmunizationModal();
        return;
      }

      var cancelWoundCareBtn = event.target && event.target.closest ? event.target.closest('[data-action="cancel-wound-care-modal"]') : null;
      if (cancelWoundCareBtn && !woundCareModalInstance && woundCareModalEl) {
        event.preventDefault();
        closeWoundCareModal();
        return;
      }

      var cancelGenericProcedureBtn = event.target && event.target.closest ? event.target.closest('[data-action="cancel-generic-procedure-modal"]') : null;
      if (cancelGenericProcedureBtn && !genericProcedureModalInstance && genericProcedureModalEl) {
        event.preventDefault();
        closeGenericProcedureModal();
        return;
      }

      var submitImmunizationBtn = event.target && event.target.closest ? event.target.closest('[data-action="submit-immunization"]') : null;
      if (submitImmunizationBtn) {
        event.preventDefault();
        submitImmunization();
        return;
      }

      var submitWoundCareBtn = event.target && event.target.closest ? event.target.closest('[data-action="submit-wound-care"]') : null;
      if (submitWoundCareBtn) {
        event.preventDefault();
        submitWoundCare();
        return;
      }

      var submitGenericProcedureBtn = event.target && event.target.closest ? event.target.closest('[data-action="submit-generic-procedure"]') : null;
      if (submitGenericProcedureBtn) {
        event.preventDefault();
        submitGenericProcedure();
        return;
      }

      if (!documentViewerModalInstance && documentViewerModalEl && event.target === documentViewerModalEl) {
        closeDocumentViewerModal();
        return;
      }

      if (!immunizationModalInstance && immunizationModalEl && event.target === immunizationModalEl) {
        closeImmunizationModal();
        return;
      }

      if (!woundCareModalInstance && woundCareModalEl && event.target === woundCareModalEl) {
        closeWoundCareModal();
        return;
      }

      if (!genericProcedureModalInstance && genericProcedureModalEl && event.target === genericProcedureModalEl) {
        closeGenericProcedureModal();
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

      var actionIntentBtn = event.target && event.target.closest ? event.target.closest('[data-action]') : null;
      var openDocumentBtn = event.target && event.target.closest ? event.target.closest('[data-nav-mode="document"][data-uuid]') : null;
      if (openDocumentBtn) {
        if (actionIntentBtn) {
          // Preserve contextual action buttons inside timeline cards.
          // Otherwise closest('[data-nav-mode]') resolves to the card container
          // and hijacks actions like upload_result/replace_order.
          openDocumentBtn = null;
        }
      }
      if (openDocumentBtn) {
        event.preventDefault();
        var docUuid = String(openDocumentBtn.getAttribute('data-uuid') || '').trim();
        var docTarget = String(openDocumentBtn.getAttribute('data-doc-target') || '').trim().toLowerCase();
        var openDocumentItem = openDocumentBtn.closest ? openDocumentBtn.closest('[data-role="timeline-item"]') : null;
        var visibleTitleHint = openDocumentItem ? String(openDocumentItem.getAttribute('data-visible-title') || '').trim() : '';
        var documentTypeHint = openDocumentItem ? String(openDocumentItem.getAttribute('data-document-type-label') || '').trim() : '';
        var isStudyDocumentBtn = String(openDocumentBtn.getAttribute('data-is-study-doc') || '').trim() === '1'
          || (openDocumentItem && String(openDocumentItem.getAttribute('data-is-study-doc') || '').trim() === '1');
        var summaryEl = openDocumentBtn.closest('.border, .doc-line, .mm-card');
        var summaryHint = '';
        if (summaryEl) {
          var secondary = summaryEl.querySelector('.text-secondary');
          summaryHint = secondary ? String(secondary.textContent || '').trim() : '';
        }
        if (isStudyDocumentBtn) {
          openDiagnosticDocument(docUuid, visibleTitleHint || summaryHint || 'Documento diagnóstico');
          return;
        }
        openDocumentViewer(
          docUuid,
          visibleTitleHint || summaryHint,
          docTarget === 'image' ? 'image' : 'document',
          { typeLabel: documentTypeHint }
        );
        return;
      }

      var openDiagnosticDocumentBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-diagnostic-document"]') : null;
      if (openDiagnosticDocumentBtn) {
        event.preventDefault();
        var relatedRef = String(openDiagnosticDocumentBtn.getAttribute('data-ref') || '').trim();
        var relatedHint = String(openDiagnosticDocumentBtn.getAttribute('data-summary-hint') || '').trim();
        if (!relatedRef) return;
        openDiagnosticDocument(relatedRef, relatedHint || 'Documento diagnóstico');
        return;
      }

      var openDiagnosticOrderResultBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-diagnostic-order-result"]') : null;
      if (openDiagnosticOrderResultBtn) {
        event.preventDefault();
        var resultOrderRef = String(openDiagnosticOrderResultBtn.getAttribute('data-ref') || '').trim();
        if (!resultOrderRef) return;
        openDiagnosticOrderAction('upload_result', resultOrderRef);
        return;
      }

      var openDiagnosticOrderReplaceBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-diagnostic-order-replace"]') : null;
      if (openDiagnosticOrderReplaceBtn) {
        event.preventDefault();
        var replaceOrderRef = String(openDiagnosticOrderReplaceBtn.getAttribute('data-ref') || '').trim();
        if (!replaceOrderRef) return;
        openDiagnosticOrderAction('replace_order', replaceOrderRef);
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
        var timelineItem = integrateBtn.closest ? integrateBtn.closest('[data-role="timeline-item"]') : null;
        var visibleTitle = timelineItem
          ? String(timelineItem.getAttribute('data-visible-title') || '').trim()
          : '';
        if (!visibleTitle && timelineItem) {
          var titleNode = timelineItem.querySelector('.mm-activity-title, .est-order-title span, .est-order-title');
          if (titleNode) {
            visibleTitle = String(titleNode.textContent || '').trim();
          }
        }
        var typeLabel = timelineItem
          ? String(timelineItem.getAttribute('data-document-type-label') || '').trim()
          : '';
        if (!typeLabel && timelineItem) {
          var metaNode = timelineItem.querySelector('.est-doc-studies-line, .mm-activity-meta');
          if (metaNode) {
            typeLabel = String(metaNode.textContent || '').trim();
          }
        }
        ensureActiveCaseThenAssign(integrateItemType, integrateItemRef, { label: visibleTitle, typeLabel: typeLabel });
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
      if (event && (event.key === 'Enter' || event.key === ' ')) {
        var toggleHead = event.target && event.target.closest ? event.target.closest('[data-action="toggle-case-items"]') : null;
        if (toggleHead) {
          var interactive = event.target && event.target.closest
            ? event.target.closest('button, a, input, textarea, select, label')
            : null;
          if (!interactive || interactive === toggleHead) {
            event.preventDefault();
            toggleCaseItems(toggleHead);
            return;
          }
        }
      }
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

    if (onlyActiveCaseTrigger) {
      onlyActiveCaseTrigger.addEventListener('keydown', function (event) {
        if (!event) return;
        if (event.key !== 'Enter' && event.key !== ' ') return;
        var interactive = event.target && event.target.closest
          ? event.target.closest('button, a, input, textarea, select, label')
          : null;
        if (interactive) return;
        event.preventDefault();
        setOnlyActiveCaseEnabled(!onlyActiveCaseEnabled);
      });
    }

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

    if (immunizationModalEl) {
      immunizationModalEl.addEventListener('hidden.bs.modal', function () {
        resetImmunizationForm();
      });
    }

    if (woundCareModalEl) {
      woundCareModalEl.addEventListener('hidden.bs.modal', function () {
        resetWoundCareForm();
      });
    }

    if (genericProcedureModalEl) {
      genericProcedureModalEl.addEventListener('hidden.bs.modal', function () {
        resetGenericProcedureForm();
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

    if (immunizationPlaceType) {
      immunizationPlaceType.addEventListener('change', function () {
        syncImmunizationPlaceFields();
      });
    }

    if (woundCarePlaceType) {
      woundCarePlaceType.addEventListener('change', function () {
        syncWoundCarePlaceFields();
      });
    }

    if (genericProcedurePlaceType) {
      genericProcedurePlaceType.addEventListener('change', function () {
        syncGenericProcedurePlaceFields();
      });
    }

    if (immunizationVaccineSelect) {
      immunizationVaccineSelect.addEventListener('change', function () {
        syncImmunizationVaccineFields();
      });
    }

    if (genericProcedureType) {
      genericProcedureType.addEventListener('change', function () {
        syncGenericProcedureTypeFields();
      });
    }

    if (genericProcedureVaccineSelect) {
      genericProcedureVaccineSelect.addEventListener('change', function () {
        syncGenericProcedureVaccineFields();
      });
    }

    if (immunizationForm) {
      immunizationForm.addEventListener('submit', function (event) {
        event.preventDefault();
        submitImmunization();
      });
    }

    if (woundCareForm) {
      woundCareForm.addEventListener('submit', function (event) {
        event.preventDefault();
        submitWoundCare();
      });
    }

    if (genericProcedureForm) {
      genericProcedureForm.addEventListener('submit', function (event) {
        event.preventDefault();
        submitGenericProcedure();
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
    if (currentInclude !== 'agenda,clinical') {
      navigateWithInclude('agenda,clinical');
      return;
    }
    if (isCasesView) {
      loadCasesTab();
      bootstrapCaseSummary();
      return;
    }
    populateImmunizationCatalogOptions();
    syncImmunizationPlaceFields();
    syncImmunizationVaccineFields();
    syncImmunizationSubmitButton();
    syncWoundCarePlaceFields();
    syncWoundCareSubmitButton();
    syncGenericProcedurePlaceFields();
    syncGenericProcedureTypeFields();
    syncGenericProcedureVaccineFields();
    syncGenericProcedureSubmitButton();
    resetClinicalFiltersToAll();
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
