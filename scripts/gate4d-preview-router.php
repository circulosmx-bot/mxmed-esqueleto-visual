<?php
declare(strict_types=1);

$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$role = (string)(getenv('MXMED_PREVIEW_ROLE') ?: 'frontend');

if ($role === 'backend' && str_starts_with($path, '/api/identity/index.php')) {
    require __DIR__ . '/../api/identity/index.php';
    return true;
}

if ($role === 'frontend') {
    $surfaceRoutes = [
        '/acceso' => __DIR__ . '/../public/identity/acceso.php',
        '/crear-cuenta' => __DIR__ . '/../public/identity/crear-cuenta.php',
        '/verificar-correo' => __DIR__ . '/../public/identity/verificar-correo.php',
        '/recuperar-acceso' => __DIR__ . '/../public/identity/recuperar-acceso.php',
        '/restablecer-acceso' => __DIR__ . '/../public/identity/restablecer-acceso.php',
    ];
    if (isset($surfaceRoutes[$path])) {
        require $surfaceRoutes[$path];
        return true;
    }
    if (str_starts_with($path, '/api/identity/index.php')) {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $configuredOrigin = rtrim((string)(getenv('MXMED_PREVIEW_ORIGIN') ?: 'https://127.0.0.1:8140'), '/');
        $forwardedOrigin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
        $headers = ['Accept: application/json', 'Origin: ' . ($forwardedOrigin !== '' ? $forwardedOrigin : $configuredOrigin)];
        foreach (['CONTENT_TYPE' => 'Content-Type', 'HTTP_REFERER' => 'Referer', 'HTTP_X_CSRF_TOKEN' => 'X-CSRF-Token', 'HTTP_COOKIE' => 'Cookie'] as $serverKey => $headerName) {
            $value = trim((string)($_SERVER[$serverKey] ?? ''));
            if ($value !== '') $headers[] = $headerName . ': ' . $value;
        }
        $options = ['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'ignore_errors' => true, 'timeout' => 5]];
        if ($method !== 'GET' && $method !== 'HEAD') $options['http']['content'] = (string)file_get_contents('php://input');
        $body = @file_get_contents('http://127.0.0.1:8141' . $path . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), false, stream_context_create($options));
        if ($body === false) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(['ok' => false, 'error' => 'TEMPORARILY_UNAVAILABLE'], JSON_UNESCAPED_UNICODE);
            return true;
        }
        foreach (($http_response_header ?? []) as $responseHeader) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $responseHeader, $match)) { http_response_code((int)$match[1]); continue; }
            if (stripos($responseHeader, 'Content-Type:') === 0 || stripos($responseHeader, 'Cache-Control:') === 0 || stripos($responseHeader, 'Pragma:') === 0 || stripos($responseHeader, 'X-Content-Type-Options:') === 0 || stripos($responseHeader, 'Content-Security-Policy:') === 0 || stripos($responseHeader, 'Set-Cookie:') === 0) header($responseHeader, false);
        }
        echo $body;
        return true;
    }
}

return false;
