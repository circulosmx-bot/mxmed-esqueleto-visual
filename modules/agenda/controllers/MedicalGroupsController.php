<?php
namespace Agenda\Controllers;

use Agenda\Repositories\ConsultoriosRepository;
use Agenda\Repositories\MedicalGroupsRepository;
use Agenda\Repositories\MedicalGroupMembershipsRepository;
use Agenda\Repositories\MedicalGroupReviewLogRepository;

require_once __DIR__ . '/../repositories/ConsultoriosRepository.php';
require_once __DIR__ . '/../repositories/MedicalGroupsRepository.php';
require_once __DIR__ . '/../repositories/MedicalGroupMembershipsRepository.php';
require_once __DIR__ . '/../repositories/MedicalGroupReviewLogRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class MedicalGroupsController
{
    private ?MedicalGroupsRepository $groups = null;
    private ?MedicalGroupMembershipsRepository $memberships = null;
    private ?MedicalGroupReviewLogRepository $reviewLog = null;
    private ?ConsultoriosRepository $consultorios = null;
    private ?string $dbError = null;
    private array $actorContext = [];
    private array $contextWarnings = [];

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->groups = new MedicalGroupsRepository($pdo);
            $this->memberships = new MedicalGroupMembershipsRepository($pdo);
            $this->reviewLog = new MedicalGroupReviewLogRepository($pdo);
            $this->consultorios = new ConsultoriosRepository($pdo);
        } catch (\RuntimeException $e) {
            $this->dbError = $e->getMessage();
        }
    }

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function search(array $params = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'medical groups not ready');
        }

        $doctorIdRequested = trim((string)($params['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, false);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }

        $q = trim((string)($params['q'] ?? ''));
        $cp = trim((string)($params['cp'] ?? ''));
        $colonia = trim((string)($params['colonia'] ?? ''));
        $limit = (int)($params['limit'] ?? 20);
        if ($limit <= 0) {
            $limit = 20;
        }

        try {
            $rows = $this->groups->searchVerifiedByContext($q, $cp, $colonia, $limit);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }

        $data = array_map([$this, 'normalizeGroupSummary'], $rows);
        return $this->success($data, [
            'q' => ($q !== '' ? $q : null),
            'cp' => ($cp !== '' ? $cp : null),
            'colonia' => ($colonia !== '' ? $colonia : null),
            'doctor_id_effective' => ($doctorScope['doctor_id'] ?? null),
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
            'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
            'auth_warnings' => $this->contextWarnings,
        ]);
    }

    public function join(string $groupId, array $payload = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'medical groups not ready');
        }

        $groupId = trim((string)$groupId);
        if ($groupId === '') {
            return $this->error('invalid_params', 'group_id is required');
        }

        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $consultorioId = trim((string)($payload['consultorio_id'] ?? ''));
        $displayNameOverride = $this->nullableText($payload['display_name_override'] ?? null);
        if ($consultorioId === '') {
            return $this->error('invalid_params', 'consultorio_id is required');
        }

        try {
            $group = $this->groups->findById($groupId);
            if (!is_array($group)) {
                return $this->error('not_found', 'medical group not found', ['group_id' => $groupId]);
            }

            $consultorio = $this->consultorios->getByDoctorConsultorio($doctorId, $consultorioId);
            if (!is_array($consultorio)) {
                return $this->error('not_found', 'consultorio not found', [
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                ]);
            }

            $membershipStatus = (trim((string)($group['status'] ?? '')) === 'verified') ? 'verified' : 'pending';
            $membership = $this->memberships->upsertMembership([
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'group_id' => $groupId,
                'status' => $membershipStatus,
                'display_name_override' => $displayNameOverride,
            ]);

            $groupDisplayName = trim((string)($group['display_name'] ?? ''));
            $snapshotName = $displayNameOverride ?: ($groupDisplayName !== '' ? $groupDisplayName : null);
            $logoApproved = $this->nullableText($group['logo_url_approved'] ?? null);
            $logoOriginal = $this->nullableText($group['logo_url_original'] ?? null);
            $snapshotLogo = $logoApproved ?: $logoOriginal;

            $this->consultorios->updateGroupSnapshot($doctorId, $consultorioId, $groupId, $snapshotName, $snapshotLogo);
            $consultorioAfter = $this->consultorios->getByDoctorConsultorio($doctorId, $consultorioId);

            $this->reviewLog->append([
                'group_id' => $groupId,
                'action' => 'membership_joined',
                'notes' => sprintf('doctor_id=%s consultorio_id=%s', $doctorId, $consultorioId),
                'actor_user_id' => $this->nullableText($this->actorContext['user_id'] ?? null),
            ]);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success([
            'group' => $this->normalizeGroupSummary($group),
            'membership' => $membership,
            'consultorio' => $consultorioAfter ?: null,
        ], [
            'doctor_id_effective' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
            'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
            'auth_warnings' => $this->contextWarnings,
        ]);
    }

    public function create(array $payload = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'medical groups not ready');
        }

        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $consultorioId = trim((string)($payload['consultorio_id'] ?? ''));
        $submittedGroupName = trim((string)($payload['submitted_group_name'] ?? ''));
        $submittedLogoUrl = $this->nullableText($payload['submitted_logo_url'] ?? null);
        $displayNameOverride = $this->nullableText($payload['display_name_override'] ?? null);

        if ($consultorioId === '' || $submittedGroupName === '') {
            return $this->error('invalid_params', 'consultorio_id and submitted_group_name are required');
        }

        try {
            $consultorio = $this->consultorios->getByDoctorConsultorio($doctorId, $consultorioId);
            if (!is_array($consultorio)) {
                return $this->error('not_found', 'consultorio not found', [
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                ]);
            }

            $group = $this->groups->upsertGroup([
                'display_name' => $submittedGroupName,
                'status' => 'pending',
                'source' => 'user_submitted',
                'created_by_user_id' => $this->nullableText($this->actorContext['user_id'] ?? null),
                'logo_url_original' => $submittedLogoUrl,
            ]);

            $membership = $this->memberships->upsertMembership([
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'group_id' => (string)$group['group_id'],
                'status' => 'pending',
                'submitted_group_name' => $submittedGroupName,
                'submitted_logo_url' => $submittedLogoUrl,
                'display_name_override' => $displayNameOverride,
            ]);

            $snapshotName = $displayNameOverride ?: $submittedGroupName;
            $this->consultorios->updateGroupSnapshot(
                $doctorId,
                $consultorioId,
                (string)$group['group_id'],
                $snapshotName,
                $submittedLogoUrl
            );
            $consultorioAfter = $this->consultorios->getByDoctorConsultorio($doctorId, $consultorioId);

            $this->reviewLog->append([
                'group_id' => (string)$group['group_id'],
                'action' => 'group_submitted',
                'notes' => sprintf('doctor_id=%s consultorio_id=%s', $doctorId, $consultorioId),
                'actor_user_id' => $this->nullableText($this->actorContext['user_id'] ?? null),
            ]);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success([
            'group' => $this->normalizeGroupSummary($group),
            'membership' => $membership,
            'consultorio' => $consultorioAfter ?: null,
        ], [
            'doctor_id_effective' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
            'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
            'auth_warnings' => $this->contextWarnings,
        ]);
    }

    public function pending(array $params = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'medical groups not ready');
        }

        $doctorIdRequested = trim((string)($params['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, false);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }

        $limit = (int)($params['limit'] ?? 100);
        if ($limit <= 0) {
            $limit = 100;
        }

        try {
            $rows = $this->groups->listPending($limit);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }

        $data = array_map([$this, 'normalizeGroupSummary'], $rows);
        return $this->success($data, [
            'doctor_id_effective' => ($doctorScope['doctor_id'] ?? null),
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
            'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
            'auth_warnings' => $this->contextWarnings,
        ]);
    }

    public function approve(string $groupId, array $payload = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'medical groups not ready');
        }

        $groupId = trim((string)$groupId);
        if ($groupId === '') {
            return $this->error('invalid_params', 'group_id is required');
        }

        try {
            $group = $this->groups->findById($groupId);
            if (!is_array($group)) {
                return $this->error('not_found', 'medical group not found', ['group_id' => $groupId]);
            }

            $reviewedAt = date('Y-m-d H:i:s');
            $actorUserId = $this->resolveActorUserId($payload);
            $logoApproved = $this->nullableText(
                $payload['logo_url_approved']
                ?? ($group['logo_url_approved'] ?? null)
                ?? ($group['logo_url_original'] ?? null)
            );

            $updatedGroup = $this->groups->upsertGroup([
                'group_id' => (string)($group['group_id'] ?? $groupId),
                'canonical_name' => (string)($group['canonical_name'] ?? ''),
                'display_name' => (string)($group['display_name'] ?? ''),
                'logo_url_original' => $this->nullableText($group['logo_url_original'] ?? null),
                'logo_url_approved' => $logoApproved,
                'status' => 'verified',
                'source' => (string)($group['source'] ?? 'user_submitted'),
                'created_by_user_id' => $this->nullableText($group['created_by_user_id'] ?? null),
                'reviewed_by_user_id' => $actorUserId,
                'reviewed_at' => $reviewedAt,
                'rejection_reason' => null,
                'merged_into_group_id' => null,
            ]);

            $membershipsUpdated = $this->memberships->bulkSetStatusByGroup($groupId, 'verified', true);
            $consultoriosUpdated = $this->consultorios->updateGroupSnapshotByGroupId(
                $groupId,
                $groupId,
                $this->nullableText($updatedGroup['display_name'] ?? null),
                $logoApproved
            );

            $noteRaw = trim((string)($payload['notes'] ?? ''));
            $note = sprintf(
                'memberships_updated=%d consultorios_updated=%d%s',
                $membershipsUpdated,
                $consultoriosUpdated,
                $noteRaw !== '' ? ' note=' . $noteRaw : ''
            );
            $review = $this->reviewLog->append([
                'group_id' => $groupId,
                'action' => 'group_approved',
                'notes' => $note,
                'actor_user_id' => $actorUserId,
            ]);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success([
            'group' => $this->normalizeGroupSummary($updatedGroup),
            'memberships_updated' => $membershipsUpdated,
            'consultorios_updated' => $consultoriosUpdated,
            'review_log' => $review,
        ], [
            'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
            'auth_warnings' => $this->contextWarnings,
        ]);
    }

    public function reject(string $groupId, array $payload = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'medical groups not ready');
        }

        $groupId = trim((string)$groupId);
        if ($groupId === '') {
            return $this->error('invalid_params', 'group_id is required');
        }

        try {
            $group = $this->groups->findById($groupId);
            if (!is_array($group)) {
                return $this->error('not_found', 'medical group not found', ['group_id' => $groupId]);
            }

            $reviewedAt = date('Y-m-d H:i:s');
            $actorUserId = $this->resolveActorUserId($payload);
            $rejectionReason = $this->nullableText($payload['rejection_reason'] ?? ($payload['reason'] ?? null));
            $updatedGroup = $this->groups->upsertGroup([
                'group_id' => (string)($group['group_id'] ?? $groupId),
                'canonical_name' => (string)($group['canonical_name'] ?? ''),
                'display_name' => (string)($group['display_name'] ?? ''),
                'logo_url_original' => $this->nullableText($group['logo_url_original'] ?? null),
                'logo_url_approved' => $this->nullableText($group['logo_url_approved'] ?? null),
                'status' => 'rejected',
                'source' => (string)($group['source'] ?? 'user_submitted'),
                'created_by_user_id' => $this->nullableText($group['created_by_user_id'] ?? null),
                'reviewed_by_user_id' => $actorUserId,
                'reviewed_at' => $reviewedAt,
                'rejection_reason' => $rejectionReason,
                'merged_into_group_id' => null,
            ]);

            $membershipsUpdated = $this->memberships->bulkSetStatusByGroup($groupId, 'rejected', true);
            $noteRaw = trim((string)($payload['notes'] ?? ''));
            $note = sprintf(
                'memberships_updated=%d%s',
                $membershipsUpdated,
                $noteRaw !== '' ? ' note=' . $noteRaw : ''
            );
            $review = $this->reviewLog->append([
                'group_id' => $groupId,
                'action' => 'group_rejected',
                'notes' => $note,
                'actor_user_id' => $actorUserId,
            ]);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success([
            'group' => $this->normalizeGroupSummary($updatedGroup),
            'memberships_updated' => $membershipsUpdated,
            'review_log' => $review,
        ], [
            'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
            'auth_warnings' => $this->contextWarnings,
        ]);
    }

    public function merge(string $groupId, array $payload = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'medical groups not ready');
        }

        $sourceGroupId = trim((string)$groupId);
        $targetGroupId = trim((string)($payload['target_group_id'] ?? ($payload['merged_into_group_id'] ?? '')));
        if ($sourceGroupId === '' || $targetGroupId === '') {
            return $this->error('invalid_params', 'group_id and target_group_id are required');
        }
        if ($sourceGroupId === $targetGroupId) {
            return $this->error('invalid_params', 'source and target group cannot be the same');
        }

        try {
            $source = $this->groups->findById($sourceGroupId);
            if (!is_array($source)) {
                return $this->error('not_found', 'source medical group not found', ['group_id' => $sourceGroupId]);
            }
            $target = $this->groups->findById($targetGroupId);
            if (!is_array($target)) {
                return $this->error('not_found', 'target medical group not found', ['group_id' => $targetGroupId]);
            }

            $actorUserId = $this->resolveActorUserId($payload);
            $reviewedAt = date('Y-m-d H:i:s');

            $sourceMemberships = $this->memberships->listByGroup($sourceGroupId, 5000);
            $transferred = 0;
            foreach ($sourceMemberships as $membership) {
                $doctorId = trim((string)($membership['doctor_id'] ?? ''));
                $consultorioId = trim((string)($membership['consultorio_id'] ?? ''));
                $currentStatus = strtolower(trim((string)($membership['status'] ?? 'pending')));
                if ($doctorId === '' || $consultorioId === '' || $currentStatus === 'unlinked') {
                    continue;
                }
                $nextStatus = (trim((string)($target['status'] ?? '')) === 'verified') ? 'verified' : 'pending';
                $this->memberships->upsertMembership([
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                    'group_id' => $targetGroupId,
                    'status' => $nextStatus,
                    'submitted_group_name' => $this->nullableText($membership['submitted_group_name'] ?? null),
                    'submitted_logo_url' => $this->nullableText($membership['submitted_logo_url'] ?? null),
                    'display_name_override' => $this->nullableText($membership['display_name_override'] ?? null),
                ]);
                $transferred++;
            }

            $this->memberships->bulkSetStatusByGroup($sourceGroupId, 'unlinked', false);
            $targetLogo = $this->nullableText($target['logo_url_approved'] ?? null)
                ?: $this->nullableText($target['logo_url_original'] ?? null);
            $consultoriosUpdated = $this->consultorios->updateGroupSnapshotByGroupId(
                $sourceGroupId,
                $targetGroupId,
                $this->nullableText($target['display_name'] ?? null),
                $targetLogo
            );

            $updatedSource = $this->groups->upsertGroup([
                'group_id' => (string)($source['group_id'] ?? $sourceGroupId),
                'canonical_name' => (string)($source['canonical_name'] ?? ''),
                'display_name' => (string)($source['display_name'] ?? ''),
                'logo_url_original' => $this->nullableText($source['logo_url_original'] ?? null),
                'logo_url_approved' => $this->nullableText($source['logo_url_approved'] ?? null),
                'status' => 'merged',
                'source' => (string)($source['source'] ?? 'user_submitted'),
                'created_by_user_id' => $this->nullableText($source['created_by_user_id'] ?? null),
                'reviewed_by_user_id' => $actorUserId,
                'reviewed_at' => $reviewedAt,
                'rejection_reason' => null,
                'merged_into_group_id' => $targetGroupId,
            ]);

            $noteRaw = trim((string)($payload['notes'] ?? ''));
            $note = sprintf(
                'target_group_id=%s transferred_memberships=%d consultorios_updated=%d%s',
                $targetGroupId,
                $transferred,
                $consultoriosUpdated,
                $noteRaw !== '' ? ' note=' . $noteRaw : ''
            );
            $review = $this->reviewLog->append([
                'group_id' => $sourceGroupId,
                'action' => 'group_merged',
                'notes' => $note,
                'actor_user_id' => $actorUserId,
            ]);
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e);
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success([
            'group' => $this->normalizeGroupSummary($updatedSource),
            'target_group' => $this->normalizeGroupSummary($target),
            'transferred_memberships' => $transferred,
            'consultorios_updated' => $consultoriosUpdated,
            'review_log' => $review,
        ], [
            'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
            'auth_warnings' => $this->contextWarnings,
        ]);
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

    private function normalizeGroupSummary(array $row): array
    {
        return [
            'group_id' => trim((string)($row['group_id'] ?? '')),
            'canonical_name' => trim((string)($row['canonical_name'] ?? '')),
            'display_name' => trim((string)($row['display_name'] ?? '')),
            'logo_url_approved' => $this->nullableText($row['logo_url_approved'] ?? null),
            'status' => trim((string)($row['status'] ?? '')),
        ];
    }

    private function runtimeError(\RuntimeException $e)
    {
        $message = trim((string)$e->getMessage());
        if ($message === 'consultorio_not_found') {
            return $this->error('not_found', 'consultorio not found');
        }
        if ($message === 'doctor_id, consultorio_id and group_id are required'
            || $message === 'group_id and action are required'
            || $message === 'display_name required'
            || $message === 'membership status is invalid'
            || $message === 'group_id and target_group_id are required') {
            return $this->error('invalid_params', $message);
        }
        if (stripos($message, 'not ready') !== false) {
            return $this->error('db_not_ready', $message);
        }
        return $this->error('db_error', 'database error');
    }

    private function resolveActorUserId(array $payload = []): ?string
    {
        return $this->nullableText($payload['actor_user_id'] ?? ($this->actorContext['user_id'] ?? null));
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function success($data, array $meta = [])
    {
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $data,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
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
