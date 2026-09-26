<?php
// Isolate the actual production generator and issuance failure branch. No DB or HTTP.
declare(strict_types=1);
namespace Docsec01Entropy;
function random_bytes(int $length): string { throw new \RuntimeException('simulated entropy failure'); }
function clinical_send_response(array $body, int $status): void { $GLOBALS['failure'] = [$body, $status]; }
$source = file_get_contents(__DIR__ . '/../../../api/clinical/index.php');
$start = strpos($source, 'function clinical_note_capture_token_generate(): string');
$end = strpos($source, 'function clinical_note_capture_token_fetch', $start);
eval('namespace ' . __NAMESPACE__ . '; ' . substr($source, $start, $end - $start));
$start = strpos($source, "            try {\n                \$token = clinical_note_capture_token_generate();");
$end = strpos($source, "            \$now = gmdate", $start);
if ($start === false || $end === false) { throw new \RuntimeException('issuance failure branch missing'); }
eval('namespace ' . __NAMESPACE__ . '; use \Throwable; function attempt(): void {' . substr($source, $start, $end - $start) . '$GLOBALS["insert_reached"] = true; }');
attempt();
if (($GLOBALS['failure'][1] ?? null) !== 500 || $GLOBALS['failure'][0]['data'] !== null
    || !array_key_exists('data', $GLOBALS['failure'][0]) || isset($GLOBALS['insert_reached'])
    || strpos($source, "sha1(uniqid('note_capture_'") !== false) {
    throw new \RuntimeException('entropy failure must return 500 with null data before insertion');
}
echo "PASS entropy failure: 500, null data, insertion not reached, no weak fallback\n";
