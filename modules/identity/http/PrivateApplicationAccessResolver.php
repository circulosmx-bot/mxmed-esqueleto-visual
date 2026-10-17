<?php
declare(strict_types=1);

namespace Identity\Http;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\ReasonCode;
use Identity\Contracts\SessionRecord;
use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Services\CurrentSubscriptionReadModelService;

require_once __DIR__ . '/IdentityHttpComposition.php';
require_once __DIR__ . '/CanonicalClinicalIdentityResolver.php';

final class PrivateApplicationAccessDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly int $httpStatus,
        public readonly ?array $context = null,
        public readonly ?AuthenticatedAccessContext $access = null,
        public readonly ?SessionRecord $session = null,
    ) {}
}

/** Resolves the one professional context that may receive the private physician shell. */
final class PrivateApplicationAccessResolver
{
    private const CAPABILITIES = ['patients', 'clinical_record', 'prescriptions'];

    public function __construct(private IdentityHttpComposition $identity) {}

    public static function fromProcessEnvironment(): self
    {
        return new self(IdentityHttpComposition::fromProcessEnvironment());
    }

    public function resolve(array $cookies): PrivateApplicationAccessDecision
    {
        $rawToken = $cookies['__Host-mxmed_session'] ?? null;
        $rawToken = is_string($rawToken) && $rawToken !== '' ? $rawToken : null;
        $session = $this->identity->sessions()->validate($rawToken);
        if (!$session->allowed() || $session->context() === null || $session->record() === null) {
            $unavailable = in_array($session->reasonCode(), [ReasonCode::SESSION_STORE_UNAVAILABLE, ReasonCode::STORAGE_UNAVAILABLE], true);
            return new PrivateApplicationAccessDecision(false, $session->reasonCode(), $unavailable ? 503 : 401);
        }

        $access = $session->context();
        try {
            $selection = (new CanonicalDoctorMembershipPolicy())->select(
                $this->identity->memberships()->activeForAccount($access->accountId())
            );
        } catch (\Throwable) {
            return new PrivateApplicationAccessDecision(false, 'membership_unavailable', 503, null, $access, $session->record());
        }
        if (!is_array($selection['membership'] ?? null)) {
            return new PrivateApplicationAccessDecision(false, (string)($selection['reason'] ?? 'membership_missing'), 403, null, $access, $session->record());
        }

        $membership = $selection['membership'];
        $doctorId = trim((string)($membership['profile_doctor_id'] ?? ''));
        try {
            $profile = $this->identity->pdo()->prepare(
                'SELECT doctor_id, display_name, professional_designation FROM profiles_doctors WHERE doctor_id = :doctor_id LIMIT 2'
            );
            $profile->execute(['doctor_id' => $doctorId]);
            $profiles = $profile->fetchAll();
        } catch (\Throwable) {
            return new PrivateApplicationAccessDecision(false, 'doctor_profile_unavailable', 503, null, $access, $session->record());
        }
        if (count($profiles) !== 1) {
            return new PrivateApplicationAccessDecision(false, 'doctor_profile_missing', 403, null, $access, $session->record());
        }

        $subscription = [];
        $capabilitySourceAvailable = true;
        try {
            $subscription = (new CurrentSubscriptionReadModelService(
                new CurrentSubscriptionRepository($this->identity->pdo())
            ))->resolveForEntity('doctor', $doctorId);
        } catch (\Throwable) {
            $capabilitySourceAvailable = false;
        }

        $capabilities = [];
        foreach (self::CAPABILITIES as $capabilityId) {
            if (!$capabilitySourceAvailable) {
                $capabilities[$capabilityId] = false;
                continue;
            }
            $decision = $this->identity->authorization()->authorize(
                $access,
                'profile_doctor',
                $doctorId,
                $capabilityId,
                $subscription
            );
            $capabilities[$capabilityId] = $decision->allowed();
        }

        return new PrivateApplicationAccessDecision(true, 'allowed', 200, [
            'account' => [
                'id' => $access->accountId(),
                'status' => 'active',
            ],
            'professional' => [
                'doctor_id' => $doctorId,
                'display_name' => trim((string)($profiles[0]['display_name'] ?? '')),
                'professional_designation' => trim((string)($profiles[0]['professional_designation'] ?? '')),
                'role' => (string)($membership['role_code'] ?? ''),
                'scope' => (string)($membership['scope_code'] ?? ''),
            ],
            'capabilities' => $capabilities,
            'capability_source_available' => $capabilitySourceAvailable,
            'session' => [
                'idle_expires_at' => $session->record()->expiresAt()->format(DATE_ATOM),
                'absolute_expires_at' => $session->record()->absoluteExpiresAt()->format(DATE_ATOM),
            ],
        ], $access, $session->record());
    }
}
