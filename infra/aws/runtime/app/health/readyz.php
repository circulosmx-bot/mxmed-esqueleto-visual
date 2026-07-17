<?php

declare(strict_types=1);

http_response_code(503);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(
    ['status' => 'unavailable', 'code' => 'readiness_not_integrated'],
    JSON_THROW_ON_ERROR,
);
