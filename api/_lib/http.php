<?php
declare(strict_types=1);

function mxmed_json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mxmed_read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        mxmed_json_response(['ok' => false, 'error' => 'JSON inválido'], 400);
    }
    return $data;
}

function mxmed_require($value, string $message) {
    if ($value === null) mxmed_json_response(['ok' => false, 'error' => $message], 400);
    if (is_string($value) && trim($value) === '') mxmed_json_response(['ok' => false, 'error' => $message], 400);
    return $value;
}

