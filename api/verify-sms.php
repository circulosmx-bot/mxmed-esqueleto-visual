<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
http_response_code(410);

echo json_encode([
    'ok' => false,
    'error' => [
        'code' => 'legacy_endpoint_retired',
        'message' => 'Legacy verification endpoint is retired',
    ],
    'data' => null,
    'meta' => [
        'contract' => 'legacy_identity_verification_retired',
        'version' => 'LEGACY-IDENTITY-RETIREMENT-1',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
