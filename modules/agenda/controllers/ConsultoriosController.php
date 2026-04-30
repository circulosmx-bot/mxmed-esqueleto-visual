<?php
namespace Agenda\Controllers;

use Agenda\Repositories\ConsultoriosRepository;
use PDOException;
use function Agenda\Helpers\ConsultorioMap\buildConsultorioPublicMapPayload;

require_once __DIR__ . '/../repositories/ConsultoriosRepository.php';
require_once __DIR__ . '/../helpers/consultorio_map.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class ConsultoriosController
{
    private ?ConsultoriosRepository $repository = null;
    private ?string $dbError = null;
    private array $actorContext = [];
    private array $contextWarnings = [];

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->repository = new ConsultoriosRepository($pdo);
        } catch (\RuntimeException $e) {
            $this->dbError = $e->getMessage();
        }
    }

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function index(array $params = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'consultorios table not ready');
        }
        $doctorIdRequested = trim((string)($params['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, false);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $consultorioId = trim((string)($params['consultorio_id'] ?? ''));
        if ($doctorId === '') {
            return $this->error('invalid_params', 'doctor_id is required', ['doctor_id' => $params['doctor_id'] ?? null]);
        }
        try {
            if ($consultorioId !== '') {
                $single = $this->repository->getByDoctorConsultorio($doctorId, $consultorioId);
                $data = $single ? [$this->normalizeRow($single)] : [];
            } else {
                $rows = $this->repository->listByDoctor($doctorId);
                $data = array_map([$this, 'normalizeRow'], $rows);
            }
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'consultorios table not ready') {
                return $this->error('db_not_ready', 'consultorios table not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $data,
            'meta' => (object)[
                'doctor_id_effective' => $doctorId,
                'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
                'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
                'auth_warnings' => $this->contextWarnings,
            ],
        ];
    }

    public function update(array $payload = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'consultorios table not ready');
        }
        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $consultorioId = trim((string)($payload['consultorio_id'] ?? ''));
        if ($doctorId === '' || $consultorioId === '') {
            return $this->error('invalid_params', 'doctor_id and consultorio_id are required');
        }

        $cleanText = static function ($value): ?string {
            $text = trim((string)($value ?? ''));
            return $text === '' ? null : $text;
        };
        $cleanArray = static function ($value): array {
            if (!is_array($value)) {
                return [];
            }
            $out = [];
            foreach ($value as $item) {
                $text = trim((string)($item ?? ''));
                if ($text !== '') {
                    $out[] = $text;
                }
            }
            return array_values(array_unique($out));
        };
        $cleanFloat = static function ($value): ?float {
            if ($value === null || $value === '') {
                return null;
            }
            if (!is_numeric($value)) {
                return null;
            }
            $num = (float)$value;
            return is_finite($num) ? $num : null;
        };
        $normalizeDateTime = static function ($value): ?string {
            $raw = trim((string)($value ?? ''));
            if ($raw === '') {
                return null;
            }
            $ts = strtotime($raw);
            if ($ts === false) {
                return null;
            }
            return gmdate('Y-m-d H:i:s', $ts);
        };
        $normalizeGeocodeSource = static function ($value): ?string {
            $raw = trim((string)($value ?? ''));
            if ($raw === '') {
                return null;
            }
            $safe = strtolower($raw);
            if (!in_array($safe, ['auto_geocoded', 'manual_adjusted', 'device', 'google_suggested', 'google_confirmed'], true)) {
                return null;
            }
            return $safe;
        };
        $hasAnyKey = static function (array $source, array $keys): bool {
            foreach ($keys as $key) {
                if (array_key_exists($key, $source)) {
                    return true;
                }
            }
            return false;
        };
        $pickValueByKeys = static function (array $source, array $keys, $default = null) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $source)) {
                    return $source[$key];
                }
            }
            return $default;
        };

        $visibleNameIsSet = $hasAnyKey($payload, ['nombre_visible', 'titulo']);
        $baseNameIsSet = $hasAnyKey($payload, ['nombre_base', 'grupo_nombre']);
        $visibleName = $visibleNameIsSet
            ? $cleanText($pickValueByKeys($payload, ['nombre_visible', 'titulo']))
            : null;
        $baseName = $baseNameIsSet
            ? $cleanText($pickValueByKeys($payload, ['nombre_base', 'grupo_nombre']))
            : null;
        $telefonosIsSet = array_key_exists('telefonos', $payload);
        $urgenciasIsSet = array_key_exists('urgencias', $payload);
        $latIsSet = array_key_exists('lat', $payload);
        $lngIsSet = array_key_exists('lng', $payload);
        $geocodeSourceIsSet = array_key_exists('geocode_source', $payload);
        $geocodeUpdatedAtIsSet = array_key_exists('geocode_updated_at', $payload);

        $record = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'group_id' => array_key_exists('group_id', $payload) ? $cleanText($payload['group_id']) : null,
            'group_id_is_set' => array_key_exists('group_id', $payload) ? 1 : 0,
            'titulo' => $visibleName,
            'titulo_is_set' => $visibleNameIsSet ? 1 : 0,
            'grupo_nombre' => $baseName,
            'grupo_nombre_is_set' => $baseNameIsSet ? 1 : 0,
            'calle' => array_key_exists('calle', $payload) ? $cleanText($payload['calle']) : null,
            'calle_is_set' => array_key_exists('calle', $payload) ? 1 : 0,
            'num_ext' => array_key_exists('num_ext', $payload) ? $cleanText($payload['num_ext']) : null,
            'num_ext_is_set' => array_key_exists('num_ext', $payload) ? 1 : 0,
            'num_int' => array_key_exists('num_int', $payload) ? $cleanText($payload['num_int']) : null,
            'num_int_is_set' => array_key_exists('num_int', $payload) ? 1 : 0,
            'cp' => array_key_exists('cp', $payload) ? $cleanText($payload['cp']) : null,
            'cp_is_set' => array_key_exists('cp', $payload) ? 1 : 0,
            'colonia' => array_key_exists('colonia', $payload) ? $cleanText($payload['colonia']) : null,
            'colonia_is_set' => array_key_exists('colonia', $payload) ? 1 : 0,
            'municipio' => array_key_exists('municipio', $payload) ? $cleanText($payload['municipio']) : null,
            'municipio_is_set' => array_key_exists('municipio', $payload) ? 1 : 0,
            'estado' => array_key_exists('estado', $payload) ? $cleanText($payload['estado']) : null,
            'estado_is_set' => array_key_exists('estado', $payload) ? 1 : 0,
            'telefonos_json' => $telefonosIsSet
                ? json_encode($cleanArray($payload['telefonos'] ?? []), JSON_UNESCAPED_UNICODE)
                : null,
            'telefonos_json_is_set' => $telefonosIsSet ? 1 : 0,
            'whatsapp' => array_key_exists('whatsapp', $payload) ? $cleanText($payload['whatsapp']) : null,
            'whatsapp_is_set' => array_key_exists('whatsapp', $payload) ? 1 : 0,
            'urgencias_json' => $urgenciasIsSet
                ? json_encode($cleanArray($payload['urgencias'] ?? []), JSON_UNESCAPED_UNICODE)
                : null,
            'urgencias_json_is_set' => $urgenciasIsSet ? 1 : 0,
            'logo_url' => array_key_exists('logo_url', $payload) ? $cleanText($payload['logo_url']) : null,
            'logo_url_is_set' => array_key_exists('logo_url', $payload) ? 1 : 0,
            'foto_url' => array_key_exists('foto_url', $payload) ? $cleanText($payload['foto_url']) : null,
            'foto_url_is_set' => array_key_exists('foto_url', $payload) ? 1 : 0,
            'lat' => $latIsSet ? $cleanFloat($payload['lat']) : null,
            'lat_is_set' => $latIsSet ? 1 : 0,
            'lng' => $lngIsSet ? $cleanFloat($payload['lng']) : null,
            'lng_is_set' => $lngIsSet ? 1 : 0,
            'geocode_source' => $geocodeSourceIsSet ? $normalizeGeocodeSource($payload['geocode_source']) : null,
            'geocode_source_is_set' => $geocodeSourceIsSet ? 1 : 0,
            'geocode_updated_at' => $geocodeUpdatedAtIsSet ? $normalizeDateTime($payload['geocode_updated_at']) : null,
            'geocode_updated_at_is_set' => $geocodeUpdatedAtIsSet ? 1 : 0,
        ];

        try {
            $this->repository->upsertConsultorio($record);
            $saved = $this->repository->getByDoctorConsultorio($doctorId, $consultorioId);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'consultorios table not ready') {
                return $this->error('db_not_ready', 'consultorios table not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => 'consultorio updated',
            'data' => $saved ? $this->normalizeRow($saved) : null,
            'meta' => (object)[
                'doctor_id_effective' => $doctorId,
                'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
                'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
                'auth_warnings' => $this->contextWarnings,
                'row_exists_after_save' => is_array($saved),
            ],
        ];
    }

    private function resolveDoctorScope(string $doctorIdRequested, bool $doctorIsRequired): array
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
                $this->contextWarnings[] = [
                    'type' => 'doctor_scope_mismatch',
                    'doctor_id_requested' => $doctorIdRequested,
                    'doctor_id_context' => $doctorIdContext,
                ];
            }
            return ['ok' => true, 'doctor_id' => $doctorIdContext];
        }
        if ($doctorIsRequired && $doctorIdRequested === '') {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'doctor_id is required',
                'meta' => [],
            ];
        }
        return ['ok' => true, 'doctor_id' => $doctorIdRequested];
    }

    private function normalizeRow(array $row): array
    {
        $toList = static function ($raw): array {
            if (is_array($raw)) {
                return $raw;
            }
            if (!is_string($raw) || trim($raw) === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        };

        $consultorioId = trim((string)($row['consultorio_id'] ?? $row['id'] ?? ''));
        $titulo = trim((string)($row['titulo'] ?? $row['name'] ?? $row['consultorio_name'] ?? ''));
        $publicMap = buildConsultorioPublicMapPayload($row);
        $lat = $publicMap['lat'];
        $lng = $publicMap['lng'];
        $geocodeSource = trim((string)($publicMap['geocode_source'] ?? ''));
        $addressCompact = trim((string)($publicMap['address_compact'] ?? ''));
        $hasConfirmedCoordinates = (bool)($publicMap['public_map_has_confirmed_coords'] ?? false);
        $publicMapIframeUrl = trim((string)($publicMap['public_map_iframe_url'] ?? ''));
        $publicMapSource = trim((string)($publicMap['public_map_source'] ?? ''));

        return [
            'doctor_id' => trim((string)($row['doctor_id'] ?? '')),
            'consultorio_id' => $consultorioId,
            'id' => $consultorioId,
            'group_id' => trim((string)($row['group_id'] ?? '')),
            'titulo' => $titulo,
            'nombre_visible' => $titulo,
            'name' => $titulo,
            'grupo_nombre' => trim((string)($row['grupo_nombre'] ?? '')),
            'nombre_base' => trim((string)($row['grupo_nombre'] ?? '')),
            'calle' => trim((string)($row['calle'] ?? '')),
            'num_ext' => trim((string)($row['num_ext'] ?? '')),
            'num_int' => trim((string)($row['num_int'] ?? '')),
            'cp' => trim((string)($row['cp'] ?? '')),
            'colonia' => trim((string)($row['colonia'] ?? '')),
            'municipio' => trim((string)($row['municipio'] ?? '')),
            'estado' => trim((string)($row['estado'] ?? '')),
            'telefonos' => $toList($row['telefonos_json'] ?? null),
            'whatsapp' => trim((string)($row['whatsapp'] ?? '')),
            'urgencias' => $toList($row['urgencias_json'] ?? null),
            'logo_url' => trim((string)($row['logo_url'] ?? '')),
            'foto_url' => trim((string)($row['foto_url'] ?? '')),
            'lat' => $lat,
            'lng' => $lng,
            'geocode_source' => $geocodeSource,
            'geocode_updated_at' => $row['geocode_updated_at'] ?? null,
            'address_compact' => $addressCompact,
            'public_map_has_confirmed_coords' => $hasConfirmedCoordinates,
            'public_map_iframe_url' => $publicMapIframeUrl,
            'public_map_source' => $publicMapSource,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function error(string $code, string $message, array $meta = [])
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }
}
