<?php
namespace Agenda\Controllers;

class GoogleGeocodeController
{
    private array $actorContext = [];

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function search(array $payload = []): array
    {
        $doctorScope = $this->resolveDoctorScope(trim((string)($payload['doctor_id'] ?? '')));
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }

        $query = trim((string)($payload['query'] ?? ''));
        if ($query === '') {
            return $this->error('invalid_params', 'query is required');
        }

        $keyMeta = $this->resolveApiKeyMeta();
        $apiKey = $keyMeta['key'];
        if ($apiKey === '') {
            return $this->error('not_configured', 'google geocoding api key not configured', $this->buildKeyDebugMeta($keyMeta));
        }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
            'address' => $query,
            'region' => 'mx',
            'language' => 'es',
            'key' => $apiKey,
        ]);

        $response = $this->fetchJson($url);
        if (!$response['ok']) {
            return $this->error('provider_unreachable', 'No se pudo ubicar automáticamente.', [
                'provider' => 'google',
                'http_status' => (int)($response['status'] ?? 0),
            ] + $this->buildKeyDebugMeta($keyMeta));
        }

        $status = strtoupper(trim((string)($response['json']['status'] ?? '')));
        $results = is_array($response['json']['results'] ?? null) ? $response['json']['results'] : [];

        if ($status !== 'OK' || !$results) {
            return $this->error('geocode_no_result', 'No se pudo ubicar automáticamente.', [
                'provider' => 'google',
                'provider_status' => $status !== '' ? $status : 'UNKNOWN',
            ] + $this->buildKeyDebugMeta($keyMeta));
        }

        $candidates = [];
        foreach ($results as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lat = $row['geometry']['location']['lat'] ?? null;
            $lng = $row['geometry']['location']['lng'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }
            $candidates[] = [
                'lat' => (float)$lat,
                'lng' => (float)$lng,
                'formatted_address' => trim((string)($row['formatted_address'] ?? '')),
                'accuracy' => trim((string)($row['geometry']['location_type'] ?? '')),
                'place_id' => trim((string)($row['place_id'] ?? '')),
            ];
        }

        if (!$candidates) {
            return $this->error('geocode_no_result', 'No se pudo ubicar automáticamente.', [
                'provider' => 'google',
                'provider_status' => $status !== '' ? $status : 'UNKNOWN',
            ] + $this->buildKeyDebugMeta($keyMeta));
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'provider' => 'google',
                'candidates' => $candidates,
            ],
            'meta' => (object)[
                'doctor_id_effective' => (string)$doctorScope['doctor_id'],
                'provider_status' => $status,
                'key_source' => $this->buildKeyDebugMeta($keyMeta)['key_source'],
                'key_length' => $this->buildKeyDebugMeta($keyMeta)['key_length'],
                'key_prefix' => $this->buildKeyDebugMeta($keyMeta)['key_prefix'],
                'key_suffix' => $this->buildKeyDebugMeta($keyMeta)['key_suffix'],
                'php_sapi' => $this->buildKeyDebugMeta($keyMeta)['php_sapi'],
                'env_available' => (object)$this->buildKeyDebugMeta($keyMeta)['env_available'],
            ],
        ];
    }

    public function mapsJsConfig(array $query = []): array
    {
        $doctorScope = $this->resolveDoctorScope(trim((string)($query['doctor_id'] ?? '')));
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }

        $keyMeta = $this->resolveMapsJsApiKeyMeta();
        $apiKey = $keyMeta['key'];
        if ($apiKey === '') {
            return [
                'ok' => true,
                'error' => null,
                'message' => '',
                'data' => [
                    'enabled' => false,
                    'api_key' => '',
                    'library_url' => '',
                ],
                'meta' => (object)[
                    'doctor_id_effective' => (string)$doctorScope['doctor_id'],
                    'key_source' => $this->buildKeyDebugMeta($keyMeta)['key_source'],
                    'key_length' => $this->buildKeyDebugMeta($keyMeta)['key_length'],
                    'key_prefix' => $this->buildKeyDebugMeta($keyMeta)['key_prefix'],
                    'key_suffix' => $this->buildKeyDebugMeta($keyMeta)['key_suffix'],
                    'php_sapi' => $this->buildKeyDebugMeta($keyMeta)['php_sapi'],
                    'env_available' => (object)$this->buildKeyDebugMeta($keyMeta)['env_available'],
                ],
            ];
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'enabled' => true,
                'api_key' => $apiKey,
                'library_url' => 'https://maps.googleapis.com/maps/api/js',
            ],
            'meta' => (object)[
                'doctor_id_effective' => (string)$doctorScope['doctor_id'],
                'key_source' => $this->buildKeyDebugMeta($keyMeta)['key_source'],
                'key_length' => $this->buildKeyDebugMeta($keyMeta)['key_length'],
                'key_prefix' => $this->buildKeyDebugMeta($keyMeta)['key_prefix'],
                'key_suffix' => $this->buildKeyDebugMeta($keyMeta)['key_suffix'],
                'php_sapi' => $this->buildKeyDebugMeta($keyMeta)['php_sapi'],
                'env_available' => (object)$this->buildKeyDebugMeta($keyMeta)['env_available'],
            ],
        ];
    }

    private function resolveApiKeyMeta(): array
    {
        $candidates = [
            [
                'source' => 'env:MXMED_GOOGLE_GEOCODE_API_KEY',
                'key' => getenv('MXMED_GOOGLE_GEOCODE_API_KEY') ?: '',
            ],
            [
                'source' => 'env:GOOGLE_GEOCODING_API_KEY',
                'key' => getenv('GOOGLE_GEOCODING_API_KEY') ?: '',
            ],
            [
                'source' => 'env:GOOGLE_MAPS_API_KEY',
                'key' => getenv('GOOGLE_MAPS_API_KEY') ?: '',
            ],
        ];
        $fileCfg = $this->loadLocalGoogleConfig();
        if (($fileCfg['google_geocode_api_key'] ?? '') !== '') {
            $candidates[] = [
                'source' => 'file:api/mxmed-google.config.php',
                'key' => (string)$fileCfg['google_geocode_api_key'],
            ];
        }

        foreach ($candidates as $candidate) {
            $key = trim((string)($candidate['key'] ?? ''));
            if ($key !== '') {
                return [
                    'key' => $key,
                    'source' => (string)($candidate['source'] ?? 'unknown'),
                ];
            }
        }
        return [
            'key' => '',
            'source' => 'none',
        ];
    }

    private function resolveMapsJsApiKeyMeta(): array
    {
        $candidates = [
            [
                'source' => 'env:MXMED_GOOGLE_MAPS_JS_API_KEY',
                'key' => getenv('MXMED_GOOGLE_MAPS_JS_API_KEY') ?: '',
            ],
            [
                'source' => 'env:GOOGLE_MAPS_JS_API_KEY',
                'key' => getenv('GOOGLE_MAPS_JS_API_KEY') ?: '',
            ],
        ];
        $fileCfg = $this->loadLocalGoogleConfig();
        if (($fileCfg['google_maps_js_api_key'] ?? '') !== '') {
            $candidates[] = [
                'source' => 'file:api/mxmed-google.config.php',
                'key' => (string)$fileCfg['google_maps_js_api_key'],
            ];
        }

        foreach ($candidates as $candidate) {
            $key = trim((string)($candidate['key'] ?? ''));
            if ($key !== '') {
                return [
                    'key' => $key,
                    'source' => (string)($candidate['source'] ?? 'unknown'),
                ];
            }
        }
        return [
            'key' => '',
            'source' => 'none',
        ];
    }

    private function loadLocalGoogleConfig(): array
    {
        $path = __DIR__ . '/../../../api/mxmed-google.config.php';
        if (!is_file($path)) {
            return [];
        }
        $cfg = require $path;
        return is_array($cfg) ? $cfg : [];
    }

    private function buildKeyDebugMeta(array $meta): array
    {
        $key = trim((string)($meta['key'] ?? ''));
        $len = strlen($key);
        return [
            'key_source' => (string)($meta['source'] ?? 'none'),
            'key_length' => $len,
            'key_prefix' => $len >= 6 ? substr($key, 0, 6) : $key,
            'key_suffix' => $len >= 4 ? substr($key, -4) : $key,
            'php_sapi' => PHP_SAPI,
            'env_available' => [
                'MXMED_GOOGLE_GEOCODE_API_KEY' => getenv('MXMED_GOOGLE_GEOCODE_API_KEY') !== false,
                'GOOGLE_GEOCODING_API_KEY' => getenv('GOOGLE_GEOCODING_API_KEY') !== false,
                'GOOGLE_MAPS_API_KEY' => getenv('GOOGLE_MAPS_API_KEY') !== false,
                'MXMED_GOOGLE_MAPS_JS_API_KEY' => getenv('MXMED_GOOGLE_MAPS_JS_API_KEY') !== false,
                'GOOGLE_MAPS_JS_API_KEY' => getenv('GOOGLE_MAPS_JS_API_KEY') !== false,
            ],
        ];
    }

    private function fetchJson(string $url): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
            ]);
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = (string)curl_error($ch);
            if ($raw === false) {
                return ['ok' => false, 'status' => $code > 0 ? $code : 0, 'json' => null, 'error' => $err];
            }
            $json = json_decode((string)$raw, true);
            if (!is_array($json)) {
                return ['ok' => false, 'status' => $code, 'json' => null, 'error' => 'invalid_json'];
            }
            return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'json' => $json, 'error' => ''];
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return ['ok' => false, 'status' => 0, 'json' => null, 'error' => 'unreachable'];
        }
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            return ['ok' => false, 'status' => 200, 'json' => null, 'error' => 'invalid_json'];
        }
        return ['ok' => true, 'status' => 200, 'json' => $json, 'error' => ''];
    }

    private function resolveDoctorScope(string $doctorIdRequested): array
    {
        $doctorIdContext = trim((string)($this->actorContext['doctor_id'] ?? ''));
        $strictMode = ($this->actorContext['strict'] ?? false) === true;

        if ($doctorIdContext !== '') {
            if ($doctorIdRequested !== '' && $doctorIdRequested !== $doctorIdContext) {
                if ($strictMode) {
                    return [
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor scope mismatch',
                        'meta' => [
                            'doctor_id_requested' => $doctorIdRequested,
                            'doctor_id_context' => $doctorIdContext,
                        ],
                    ];
                }
            }
            return ['ok' => true, 'doctor_id' => $doctorIdContext];
        }

        if ($doctorIdRequested === '') {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'doctor_id is required',
                'meta' => [],
            ];
        }
        return ['ok' => true, 'doctor_id' => $doctorIdRequested];
    }

    private function error(string $error, string $message, array $meta = []): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'message' => $message,
            'data' => null,
            'meta' => (object)$meta,
        ];
    }
}
