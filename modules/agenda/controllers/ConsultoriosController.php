<?php
namespace Agenda\Controllers;

use Agenda\Repositories\ConsultoriosRepository;
use PDOException;

require_once __DIR__ . '/../repositories/ConsultoriosRepository.php';
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

        $record = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'titulo' => $cleanText($payload['titulo'] ?? null),
            'grupo_nombre' => $cleanText($payload['grupo_nombre'] ?? null),
            'calle' => $cleanText($payload['calle'] ?? null),
            'num_ext' => $cleanText($payload['num_ext'] ?? null),
            'num_int' => $cleanText($payload['num_int'] ?? null),
            'cp' => $cleanText($payload['cp'] ?? null),
            'colonia' => $cleanText($payload['colonia'] ?? null),
            'municipio' => $cleanText($payload['municipio'] ?? null),
            'estado' => $cleanText($payload['estado'] ?? null),
            'telefonos_json' => json_encode($cleanArray($payload['telefonos'] ?? []), JSON_UNESCAPED_UNICODE),
            'whatsapp' => $cleanText($payload['whatsapp'] ?? null),
            'urgencias_json' => json_encode($cleanArray($payload['urgencias'] ?? []), JSON_UNESCAPED_UNICODE),
            'logo_url' => $cleanText($payload['logo_url'] ?? null),
            'foto_url' => $cleanText($payload['foto_url'] ?? null),
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

        return [
            'doctor_id' => trim((string)($row['doctor_id'] ?? '')),
            'consultorio_id' => $consultorioId,
            'id' => $consultorioId,
            'titulo' => $titulo,
            'name' => $titulo,
            'grupo_nombre' => trim((string)($row['grupo_nombre'] ?? '')),
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
