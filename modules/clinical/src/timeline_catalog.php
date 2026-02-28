<?php
declare(strict_types=1);

function mxmed_clinical_timeline_catalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $catalog = [
        'attention' => [
            'label' => 'Atencion',
            'priority' => 10,
            'subtypes' => [
                'encounter' => ['label' => 'Atencion', 'priority' => 10],
                'appointment' => ['label' => 'Cita', 'priority' => 20],
            ],
        ],
        'clinical' => [
            'label' => 'Clinico',
            'priority' => 20,
            'subtypes' => [
                'vitals' => ['label' => 'Signos', 'priority' => 10],
                'note' => ['label' => 'Nota', 'priority' => 20],
            ],
        ],
        'treatment' => [
            'label' => 'Tratamiento',
            'priority' => 30,
            'subtypes' => [
                'prescription' => ['label' => 'Receta', 'priority' => 10],
            ],
        ],
        'orders' => [
            'label' => 'Ordenes',
            'priority' => 40,
            'subtypes' => [
                'lab_order' => ['label' => 'Lab', 'priority' => 10],
                'imaging_order' => ['label' => 'Imagen', 'priority' => 20],
                'order' => ['label' => 'Orden', 'priority' => 30],
            ],
        ],
        'results' => [
            'label' => 'Resultados',
            'priority' => 50,
            'subtypes' => [
                'lab_result' => ['label' => 'Lab', 'priority' => 10],
                'imaging_result' => ['label' => 'Imagen', 'priority' => 20],
                'lab_pdf' => ['label' => 'PDF', 'priority' => 30],
                'result' => ['label' => 'Resultado', 'priority' => 40],
            ],
        ],
        'documents' => [
            'label' => 'Documentos',
            'priority' => 60,
            'subtypes' => [
                'image' => ['label' => 'Imagen', 'priority' => 10],
                'pdf' => ['label' => 'PDF', 'priority' => 20],
                'document' => ['label' => 'Documento', 'priority' => 30],
            ],
        ],
        'other' => [
            'label' => 'Otros',
            'priority' => 999,
            'subtypes' => [
                'unknown' => ['label' => 'Sin clasificar', 'priority' => 999],
            ],
        ],
    ];

    return $catalog;
}

function mxmed_clinical_timeline_catalog_entry(string $category, string $subtype = 'unknown'): array
{
    $catalog = mxmed_clinical_timeline_catalog();
    $categoryKey = trim($category);
    $subtypeKey = trim($subtype);
    if ($categoryKey === '' || !isset($catalog[$categoryKey])) {
        $categoryKey = 'other';
        $subtypeKey = 'unknown';
    }

    $categoryMeta = $catalog[$categoryKey];
    $subtypes = is_array($categoryMeta['subtypes'] ?? null) ? $categoryMeta['subtypes'] : [];
    if ($subtypeKey === '' || !isset($subtypes[$subtypeKey])) {
        $subtypeKey = isset($subtypes['unknown']) ? 'unknown' : array_key_first($subtypes);
    }
    if (!is_string($subtypeKey) || $subtypeKey === '') {
        $subtypeKey = 'unknown';
    }
    $subtypeMeta = is_array($subtypes[$subtypeKey] ?? null) ? $subtypes[$subtypeKey] : ['label' => 'Sin clasificar', 'priority' => 999];

    return [
        'category' => $categoryKey,
        'subtype' => $subtypeKey,
        'category_label' => (string)($categoryMeta['label'] ?? 'Otros'),
        'subtype_label' => (string)($subtypeMeta['label'] ?? 'Sin clasificar'),
        'category_priority' => (int)($categoryMeta['priority'] ?? 999),
        'subtype_priority' => (int)($subtypeMeta['priority'] ?? 999),
    ];
}

function mxmed_clinical_timeline_category_priority_map(): array
{
    $map = [];
    foreach (mxmed_clinical_timeline_catalog() as $category => $meta) {
        $map[$category] = (int)($meta['priority'] ?? 999);
    }
    return $map;
}

function mxmed_clinical_timeline_classify_item(array $item): array
{
    $itemType = strtolower(trim((string)($item['item_type'] ?? '')));
    if ($itemType === 'appointment') {
        return mxmed_clinical_timeline_catalog_entry('attention', 'appointment');
    }
    if ($itemType === 'encounter') {
        return mxmed_clinical_timeline_catalog_entry('attention', 'encounter');
    }
    if ($itemType !== 'document') {
        return mxmed_clinical_timeline_catalog_entry('other', 'unknown');
    }

    $clinicalDocument = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $payload = is_array($clinicalDocument['payload'] ?? null) ? $clinicalDocument['payload'] : [];
    $file = is_array($payload['file'] ?? null) ? $payload['file'] : [];
    $documentType = strtolower(trim((string)($clinicalDocument['document_type'] ?? '')));
    $renderMode = strtolower(trim((string)($clinicalDocument['render_mode'] ?? ($file['render_mode'] ?? ''))));

    if (in_array($documentType, ['vitals', 'vital_signs', 'signs'], true)) {
        return mxmed_clinical_timeline_catalog_entry('clinical', 'vitals');
    }
    if (in_array($documentType, ['note', 'medical_note', 'evolution_note'], true)) {
        return mxmed_clinical_timeline_catalog_entry('clinical', 'note');
    }
    if (in_array($documentType, ['prescription', 'rx'], true)) {
        return mxmed_clinical_timeline_catalog_entry('treatment', 'prescription');
    }
    if ($documentType === 'lab_order') {
        return mxmed_clinical_timeline_catalog_entry('orders', 'lab_order');
    }
    if ($documentType === 'imaging_order') {
        return mxmed_clinical_timeline_catalog_entry('orders', 'imaging_order');
    }
    if (in_array($documentType, ['orders', 'order'], true)) {
        return mxmed_clinical_timeline_catalog_entry('orders', 'order');
    }
    if ($documentType === 'lab_result') {
        return mxmed_clinical_timeline_catalog_entry('results', 'lab_result');
    }
    if ($documentType === 'imaging_result') {
        return mxmed_clinical_timeline_catalog_entry('results', 'imaging_result');
    }
    if ($documentType === 'lab_pdf') {
        return mxmed_clinical_timeline_catalog_entry('results', 'lab_pdf');
    }
    if (in_array($documentType, ['results', 'result'], true)) {
        return mxmed_clinical_timeline_catalog_entry('results', 'result');
    }
    if ($renderMode === 'image' || $documentType === 'image') {
        return mxmed_clinical_timeline_catalog_entry('documents', 'image');
    }
    if ($renderMode === 'pdf' || $documentType === 'pdf') {
        return mxmed_clinical_timeline_catalog_entry('documents', 'pdf');
    }
    if ($documentType !== '') {
        return mxmed_clinical_timeline_catalog_entry('documents', 'document');
    }

    return mxmed_clinical_timeline_catalog_entry('other', 'unknown');
}

function classify_timeline_item(array $item): array
{
    return mxmed_clinical_timeline_classify_item($item);
}
