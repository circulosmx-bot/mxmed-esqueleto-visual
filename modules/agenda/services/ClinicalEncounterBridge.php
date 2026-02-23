<?php
namespace Agenda\Services;

use RuntimeException;

class ClinicalEncounterBridge
{
    private string $apiBase;
    private bool $enabled;

    public function __construct(array $config = [])
    {
        $envEnabled = trim((string)getenv('AGENDA_ENABLE_CLINICAL_ENCOUNTER_BRIDGE'));
        $this->enabled = ($envEnabled === '1');

        $envBase = trim((string)getenv('CLINICAL_API_BASE'));
        $cfgBase = trim((string)($config['clinical_api_base'] ?? ''));
        $base = $envBase !== '' ? $envBase : ($cfgBase !== '' ? $cfgBase : 'http://127.0.0.1:8091');
        $this->apiBase = rtrim($base, '/');
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function syncCompletedAppointment(array $appointment): void
    {
        if (!$this->enabled) {
            return;
        }

        $status = strtolower(trim((string)($appointment['status'] ?? '')));
        if ($status !== 'completed') {
            return;
        }

        $patientId = trim((string)($appointment['patient_id'] ?? ''));
        $appointmentId = trim((string)($appointment['appointment_id'] ?? ''));
        if ($patientId === '' || $appointmentId === '') {
            return;
        }

        if ($this->encounterExistsForAppointment($patientId, $appointmentId)) {
            return;
        }

        $encounterDt = trim((string)($appointment['start_at'] ?? ''));
        if ($encounterDt === '') {
            $encounterDt = trim((string)($appointment['end_at'] ?? ''));
        }
        if ($encounterDt === '') {
            $encounterDt = trim((string)($appointment['created_at'] ?? ''));
        }
        if ($encounterDt === '') {
            $encounterDt = date('Y-m-d H:i:s');
        }

        $url = $this->apiBase
            . '/api/clinical/index.php/patients/' . rawurlencode($patientId)
            . '/encounters';
        $payload = [
            'appointment_id' => $appointmentId,
            'encounter_dt' => $encounterDt,
            'encounter_type' => 'outpatient',
            'status' => 'completed',
        ];

        $result = $this->httpJson('POST', $url, $payload);
        if (($result['ok'] ?? false) !== true) {
            $msg = trim((string)($result['message'] ?? 'clinical bridge post failed'));
            throw new RuntimeException($msg !== '' ? $msg : 'clinical bridge post failed');
        }
    }

    private function encounterExistsForAppointment(string $patientId, string $appointmentId): bool
    {
        $url = $this->apiBase
            . '/api/clinical/index.php/patients/' . rawurlencode($patientId)
            . '/encounters?limit=100';
        $result = $this->httpJson('GET', $url);
        if (($result['ok'] ?? false) !== true) {
            return false;
        }

        $data = $result['data'] ?? null;
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $current = trim((string)($row['appointment_id'] ?? ''));
            if ($current !== '' && $current === $appointmentId) {
                return true;
            }
        }

        return false;
    }

    private function httpJson(string $method, string $url, ?array $payload = null): array
    {
        $method = strtoupper(trim($method));
        $headers = "Accept: application/json\r\n";
        $content = null;
        if ($method !== 'GET') {
            $headers .= "Content-Type: application/json\r\n";
            $content = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($content)) {
                $content = '{}';
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => 3,
                'ignore_errors' => true,
                'header' => $headers,
                'content' => $content,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $status = 0;
        $responseHeaders = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : [];
        if (!is_array($responseHeaders)) {
            $responseHeaders = [];
        }
        if (!empty($responseHeaders[0]) && preg_match('/^HTTP\/\S+\s+(\d{3})\b/', (string)$responseHeaders[0], $m) === 1) {
            $status = (int)$m[1];
        }

        if ($raw === false) {
            return [
                'ok' => false,
                'status' => $status,
                'message' => 'http request failed',
                'data' => null,
            ];
        }

        $decoded = json_decode((string)$raw, true);
        $is2xx = ($status >= 200 && $status < 300);
        if (!is_array($decoded)) {
            return [
                'ok' => $is2xx,
                'status' => $status,
                'message' => $is2xx ? '' : 'invalid json',
                'data' => null,
            ];
        }

        $ok = (bool)($decoded['ok'] ?? false);
        return [
            'ok' => ($is2xx && $ok),
            'status' => $status,
            'message' => (string)($decoded['message'] ?? ''),
            'data' => $decoded['data'] ?? null,
        ];
    }
}
