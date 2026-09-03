<?php
declare(strict_types=1);

$_GET = [];
$publicDiscoveryResponse = [
    'ok' => true,
    'error' => null,
    'message' => '',
    'data' => ['entity_type' => 'MEDICO', 'items' => []],
    'meta' => [
        'filters' => ['state' => '', 'city' => '', 'specialty' => ''],
        'pagination' => ['page' => 1, 'page_size' => 20, 'result_count' => 0, 'total_count' => 0, 'total_pages' => 0, 'has_previous' => false, 'has_next' => false],
    ],
];

ob_start();
require __DIR__ . '/../../../profiles/listing.php';
$html = (string)ob_get_clean();
if (!str_contains($html, 'Sin resultados') || !str_contains($html, '0 especialistas encontrados')) {
    throw new RuntimeException('empty listing state not rendered');
}

echo "PublicDiscoveryEmptyPageTest PASS\n";
