<?php
namespace Agenda\Controllers;

use Agenda\Adapters\CanonicalScheduleReadAdapter;
use Agenda\Repositories\AgendaSettingsRepository;

require_once __DIR__ . '/../adapters/CanonicalScheduleReadAdapter.php';
require_once __DIR__ . '/../repositories/AgendaSettingsRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class AgendaSettingsController
{
    private const REMINDER_TEMPLATE_VERSION = 1;

    private ?AgendaSettingsRepository $repository = null;
    private ?string $dbError = null;
    private array $actorContext = [];
    private array $contextWarnings = [];

    public function __construct()
    {
        $config = require __DIR__ . '/../config/agenda.php';
        $canonicalScheduleReadAdapterClass = CanonicalScheduleReadAdapter::canonicalScheduleReadEnabled($config)
            ? CanonicalScheduleReadAdapter::class
            : null;
        try {
            $pdo = mxmed_pdo();
            $this->repository = new AgendaSettingsRepository($pdo);
        } catch (\RuntimeException $e) {
            $this->dbError = $e->getMessage();
        } catch (\Throwable $e) {
            $this->dbError = 'database error';
        }
    }

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function index(array $params = []): array
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'agenda settings not ready');
        }
        $doctorIdRequested = trim((string)($params['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $consultorioId = trim((string)($params['consultorio_id'] ?? ''));
        if ($doctorId === '' || $consultorioId === '') {
            return $this->error('invalid_params', 'doctor_id and consultorio_id are required');
        }
        try {
            $row = $this->repository->getByDoctorConsultorio($doctorId, $consultorioId);
        } catch (\RuntimeException $e) {
            return $this->error('db_not_ready', 'agenda settings not ready');
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error');
        }
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $this->normalize($row, $doctorId, $consultorioId),
            'meta' => (object)[
                'doctor_id_effective' => $doctorId,
                'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
                'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
                'auth_warnings' => $this->contextWarnings,
            ],
        ];
    }

    public function update(array $payload = []): array
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'agenda settings not ready');
        }
        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $consultorioId = trim((string)($payload['consultorio_id'] ?? ''));
        if ($doctorId === '' || $consultorioId === '') {
            return $this->error('invalid_params', 'doctor_id and consultorio_id are required');
        }
        $duration = (int)($payload['appointment_duration_min'] ?? 30);
        $gap = (int)($payload['gap_between_appointments_min'] ?? 0);
        $policy = $payload['cancellation_policy_hours'] ?? null;
        $policy = ($policy === null || $policy === '') ? null : (int)$policy;
        $channels = is_array($payload['channels']) ? $payload['channels'] : [];
        $channels = array_values(array_unique(array_filter(array_map(static function ($v) {
            return trim((string)$v);
        }, $channels))));
        if (!in_array($duration, [20, 30, 40, 60], true)) {
            $duration = 30;
        }
        if (!in_array($gap, [0, 10, 15], true)) {
            $gap = 0;
        }
        if (!in_array($policy, [24, 48], true)) {
            $policy = null;
        }
        $existingReminderTemplate = '';
        $existingWaitlistEnabled = false;
        try {
            $existingRow = $this->repository->getByDoctorConsultorio($doctorId, $consultorioId);
            $existingReminderTemplate = trim((string)($existingRow['reminder_template'] ?? ''));
            $existingWaitlistEnabled = $this->extractWaitlistEnabledFromReminderTemplate($existingReminderTemplate);
        } catch (\Throwable $e) {
            $existingReminderTemplate = '';
            $existingWaitlistEnabled = false;
        }
        $reminderTemplateRaw = trim((string)($payload['reminder_template'] ?? ''));
        $waitlistEnabled = array_key_exists('waitlist_enabled', $payload)
            ? $this->normalizeBoolean($payload['waitlist_enabled'], false)
            : $existingWaitlistEnabled;
        $reminderTemplate = $this->composeReminderTemplateForStorage(
            $reminderTemplateRaw,
            $waitlistEnabled,
            $existingReminderTemplate
        );
        $record = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'appointment_duration_min' => $duration,
            'gap_between_appointments_min' => $gap,
            'channels_json' => json_encode($channels, JSON_UNESCAPED_UNICODE),
            'cancellation_policy_hours' => $policy,
            'reminder_template' => ($reminderTemplate === '' ? null : $reminderTemplate),
        ];
        try {
            $this->repository->upsert($record);
            $row = $this->repository->getByDoctorConsultorio($doctorId, $consultorioId);
        } catch (\RuntimeException $e) {
            return $this->error('db_not_ready', 'agenda settings not ready');
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error');
        }
        return [
            'ok' => true,
            'error' => null,
            'message' => 'agenda settings updated',
            'data' => $this->normalize($row, $doctorId, $consultorioId),
            'meta' => (object)[
                'doctor_id_effective' => $doctorId,
                'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
                'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
                'auth_warnings' => $this->contextWarnings,
            ],
        ];
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
                $this->contextWarnings[] = [
                    'type' => 'doctor_scope_mismatch',
                    'doctor_id_requested' => $doctorIdRequested,
                    'doctor_id_context' => $doctorIdContext,
                ];
            }
            return ['ok' => true, 'doctor_id' => $doctorIdContext];
        }
        if ($doctorIdRequested === '') {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'doctor_id and consultorio_id are required',
                'meta' => [],
            ];
        }
        return ['ok' => true, 'doctor_id' => $doctorIdRequested];
    }

    private function normalize(?array $row, string $doctorId, string $consultorioId): array
    {
        $reminderTemplate = trim((string)($row['reminder_template'] ?? ''));
        $waitlistEnabled = $this->extractWaitlistEnabledFromReminderTemplate($reminderTemplate);
        $channels = [];
        if (is_array($row) && isset($row['channels_json']) && is_string($row['channels_json']) && trim($row['channels_json']) !== '') {
            $decoded = json_decode($row['channels_json'], true);
            if (is_array($decoded)) {
                $channels = array_values(array_unique(array_filter(array_map(static function ($v) {
                    return trim((string)$v);
                }, $decoded))));
            }
        }
        return [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'appointment_duration_min' => (int)($row['appointment_duration_min'] ?? 30),
            'gap_between_appointments_min' => (int)($row['gap_between_appointments_min'] ?? 0),
            'channels' => $channels,
            'cancellation_policy_hours' => isset($row['cancellation_policy_hours']) ? (int)$row['cancellation_policy_hours'] : null,
            'waitlist_enabled' => $waitlistEnabled,
            'reminder_template' => $reminderTemplate,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function decodeReminderTemplateObject(string $rawTemplate): ?array
    {
        $raw = trim($rawTemplate);
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        return $decoded;
    }

    private function normalizeBoolean($value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return ((int)$value) === 1;
        }
        $raw = strtolower(trim((string)$value));
        if ($raw === '') {
            return $default;
        }
        if (in_array($raw, ['1', 'true', 'on', 'yes', 'si'], true)) {
            return true;
        }
        if (in_array($raw, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }
        return $default;
    }

    private function extractWaitlistEnabledFromReminderTemplate(string $rawTemplate): bool
    {
        $decoded = $this->decodeReminderTemplateObject($rawTemplate);
        if (!is_array($decoded) || !array_key_exists('waitlist_enabled', $decoded)) {
            return false;
        }
        return $this->normalizeBoolean($decoded['waitlist_enabled'], false);
    }

    private function encodeReminderTemplatePayload(array $payload, string $fallbackTemplate): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded) && $encoded !== '') {
            return $encoded;
        }
        return trim($fallbackTemplate);
    }

    private function composeReminderTemplateForStorage(string $incomingTemplate, bool $waitlistEnabled, string $existingTemplate = ''): string
    {
        $incoming = trim($incomingTemplate);
        $existing = trim($existingTemplate);

        if ($incoming === '') {
            if ($existing === '') {
                return '';
            }
            $existingDecoded = $this->decodeReminderTemplateObject($existing);
            if (!is_array($existingDecoded)) {
                return $existing;
            }
            $existingDecoded['waitlist_enabled'] = $waitlistEnabled;
            return $this->encodeReminderTemplatePayload($existingDecoded, $existing);
        }

        $incomingDecoded = $this->decodeReminderTemplateObject($incoming);
        if (is_array($incomingDecoded)) {
            $incomingDecoded['waitlist_enabled'] = $waitlistEnabled;
            return $this->encodeReminderTemplatePayload($incomingDecoded, $incoming);
        }

        $existingDecoded = $this->decodeReminderTemplateObject($existing);
        if (is_array($existingDecoded)) {
            $existingDecoded['message_template'] = $incoming;
            $existingDecoded['waitlist_enabled'] = $waitlistEnabled;
            return $this->encodeReminderTemplatePayload($existingDecoded, $incoming);
        }

        $payload = [
            'version' => self::REMINDER_TEMPLATE_VERSION,
            'message_template' => $incoming,
            'waitlist_enabled' => $waitlistEnabled,
        ];
        return $this->encodeReminderTemplatePayload($payload, $incoming);
    }

    private function error(string $code, string $message, array $meta = []): array
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
