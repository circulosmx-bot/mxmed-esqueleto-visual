<?php
declare(strict_types=1);

namespace Agenda\UI;

class AgendaApiClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(?string $baseUrl = null, int $timeout = 10)
    {
        $this->baseUrl = $baseUrl ?: $this->detectBaseUrl();
        $this->timeout = $timeout;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query, null);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, [], $body);
    }

    public function patch(string $path, array $body = []): array
    {
        return $this->request('PATCH', $path, [], $body);
    }

    public function friendlyMessage(array $response): string
    {
        $error = $response['error'] ?? null;
        $message = $response['message'] ?? '';
        if (!$error) {
            return '';
        }
        switch ($error) {
            case 'collision':
                return 'Horario ocupado (collision).';
            case 'outside_schedule':
                return 'Fuera de horario disponible (outside_schedule).';
            case 'slot_unavailable':
                return 'Slot no disponible (slot_unavailable).';
            case 'invalid_params':
                return 'Parametros invalidos (invalid_params).';
            case 'db_not_ready':
                return 'Datos no disponibles (db_not_ready).';
            case 'db_error':
                return 'Error de base de datos (db_error).';
            case 'http_error':
                return 'Error HTTP al llamar el API (http_error).';
            case 'network_error':
                return 'Error de red al llamar el API (network_error).';
            case 'invalid_json':
                return 'Respuesta invalida del API (invalid_json).';
            default:
                return $message !== '' ? $message : 'Error del API.';
        }
    }

    private function request(string $method, string $path, array $query, ?array $body): array
    {
        $path = $path === '' ? '' : (strpos($path, '/') === 0 ? $path : '/' . $path);
        $url = $this->baseUrl . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json']);
        if ($body !== null) {
            $payload = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload === false ? '{}' : $payload);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return $this->normalize([
                'ok' => false,
                'error' => 'network_error',
                'message' => $error,
                'data' => null,
                'meta' => [],
            ]);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            return $this->normalize([
                'ok' => false,
                'error' => 'http_error',
                'message' => 'HTTP ' . $status,
                'data' => null,
                'meta' => ['status' => $status],
            ]);
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            return $this->normalize([
                'ok' => false,
                'error' => 'invalid_json',
                'message' => 'Response is not valid JSON',
                'data' => null,
                'meta' => [],
            ]);
        }

        return $this->normalize($decoded);
    }

    private function normalize(array $resp): array
    {
        $ok = isset($resp['ok']) ? (bool)$resp['ok'] : ($resp['error'] ?? null) === null;
        $error = $resp['error'] ?? null;
        $message = isset($resp['message']) ? (string)$resp['message'] : '';
        $data = $resp['data'] ?? null;
        $meta = $resp['meta'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        return [
            'ok' => $ok,
            'error' => $error,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ];
    }

    private function detectBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        return $scheme . '://' . $host . '/api/agenda/index.php';
    }
}
