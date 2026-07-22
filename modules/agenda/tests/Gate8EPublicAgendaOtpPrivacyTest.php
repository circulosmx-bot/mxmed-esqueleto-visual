<?php
declare(strict_types=1);

require_once __DIR__ . '/../contracts/AppointmentLifecycleContract.php';
foreach (glob(__DIR__ . '/../appointments/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../publicflow/*.php') as $file) require_once $file;

use Agenda\Appointments\AppointmentSlotIdentity;
use Agenda\PublicFlow\PublicAgendaDomainException;
use Agenda\PublicFlow\PublicAgendaPolicy;
use Agenda\PublicFlow\PublicAuditEvent;
use Agenda\PublicFlow\PublicBookingHandoff;
use Agenda\PublicFlow\PublicBookingIntent;
use Agenda\PublicFlow\PublicBookingMutationPlan;
use Agenda\PublicFlow\PublicCancellationCapability;
use Agenda\PublicFlow\PublicContactPrivacyProjection;
use Agenda\PublicFlow\PublicContactReference;
use Agenda\PublicFlow\PublicOtpChallenge;
use Agenda\PublicFlow\PublicOtpVerificationCommand;
use Agenda\PublicFlow\PublicOtpVerificationDecision;
use Agenda\PublicFlow\PublicOtpVerifier;
use Agenda\PublicFlow\PublicVerificationGrant;

function gate8eAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function gate8eThrows(callable $callback, string $reason, string $message): void
{
    try { $callback(); }
    catch (PublicAgendaDomainException $error) {
        if ($error->reason() === $reason) return;
        throw new RuntimeException($message . ' (' . $error->reason() . ')');
    }
    throw new RuntimeException($message);
}

function gate8eRejectsWithoutOutput(callable $callback, string $reason, string $message): void
{
    $output = null;
    try { $output = $callback(); }
    catch (PublicAgendaDomainException $error) {
        gate8eAssert($output === null, $message . ' created output');
        if ($error->reason() === $reason) return;
        throw new RuntimeException($message . ' (' . $error->reason() . ')');
    }
    throw new RuntimeException($message . ' returned output');
}

function gate8eSlot(string $profile = 'profile-public', string $consultorio = 'consultorio-public', string $start = '2026-07-21T09:00:00-06:00', string $end = '2026-07-21T09:30:00-06:00'): AppointmentSlotIdentity
{
    return new AppointmentSlotIdentity($profile, $consultorio, 'America/Mexico_City', $start, $end);
}

function gate8eIntent(string $id = 'intent-public', string $profile = 'profile-public', string $consultorio = 'consultorio-public', string $channel = 'email', string $reference = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'): PublicBookingIntent
{
    $contact = new PublicContactReference($channel, $reference, $channel === 'email' ? 'p***@example.mx' : '+52 *** 4567');
    return new PublicBookingIntent($id, $profile, $consultorio, gate8eSlot($profile, $consultorio), $contact, '2026-07-21T08:50:00-06:00', '2026-07-21T10:00:00-06:00');
}

function gate8eChallenge(PublicBookingIntent $intent, string $id = 'challenge-public', string $state = 'pending', int $attempts = 0, string $expires = '2026-07-21T09:10:00-06:00'): PublicOtpChallenge
{
    return new PublicOtpChallenge($id, $intent->intentId(), $intent->bindingFingerprint(), password_hash('654321', PASSWORD_DEFAULT), $state, $attempts, 5, '2026-07-21T09:00:00-06:00', $expires, $state === 'verified' || $state === 'consumed' ? '2026-07-21T09:02:00-06:00' : null, $state === 'consumed' ? '2026-07-21T09:03:00-06:00' : null, 1, $state === 'consumed' ? str_repeat('b', 64) : null);
}

function gate8eCommand(PublicBookingIntent $intent, PublicOtpChallenge $challenge, string $operation = 'operation-public', string $key = 'key-public', string $otp = '654321', ?object $rate = null, string $at = '2026-07-21T09:02:00-06:00'): PublicOtpVerificationCommand
{
    return new PublicOtpVerificationCommand($operation, $key, 'correlation-public', $challenge->challengeId(), $intent->intentId(), $intent->bindingFingerprint(), $otp, $at, $rate);
}

$policy = new PublicAgendaPolicy();
gate8eAssert($policy->contractId() === 'pg03-public-agenda-otp-privacy', 'contract id exact');
gate8eAssert($policy->version() === 1 && $policy->lifecycleId() === 'pg03-appointment-lifecycle' && $policy->lifecycleVersion() === 1, 'versions exact');
gate8eAssert($policy->channels() === ['sms', 'email'], 'channels exact');
gate8eAssert($policy->otpDigits() === 6 && $policy->otpTtlSeconds() === 600 && $policy->otpMaxAttempts() === 5, 'otp policy exact');
gate8eAssert($policy->states() === ['pending', 'verified', 'expired', 'locked', 'consumed'], 'states exact');
gate8eAssert($policy->publicFlowAuthoritative() === false && $policy->serverAuthoritativeHandoffRequired() === true && $policy->clinicalEncounter() === false, 'authority boundary exact');

$reference = str_repeat('a', 64);
$contact = new PublicContactReference('email', $reference, 'p***@example.mx');
gate8eThrows(fn() => new PublicContactReference('push', $reference, 'masked'), 'invalid_channel', 'unknown channel fails closed');
gate8eThrows(fn() => new PublicContactReference('email', 'not-a-digest', 'masked'), 'invalid_contact_reference', 'reference shape fails closed');
gate8eThrows(fn() => new PublicContactReference('email', $reference, 'paciente.real@example.mx'), 'invalid_masked_destination', 'complete email mask rejected');
gate8eThrows(fn() => new PublicContactReference('email', $reference, '+5214491234567'), 'invalid_masked_destination', 'complete phone mask rejected');
gate8eAssert($contact->maskedDestination() === 'p***@example.mx' && !str_contains(json_encode($contact->toArray(), JSON_THROW_ON_ERROR), 'paciente.real@example.mx'), 'masked contact only');

$intent = gate8eIntent('intent-public', 'profile-public', 'consultorio-public', 'email', $reference);
$intentSame = gate8eIntent('intent-public', 'profile-public', 'consultorio-public', 'email', $reference);
$intentOther = gate8eIntent('intent-other', 'profile-public', 'consultorio-public', 'email', $reference);
$profileOther = gate8eIntent('intent-public', 'profile-other', 'consultorio-public', 'email', $reference);
$consultorioOther = gate8eIntent('intent-public', 'profile-public', 'consultorio-other', 'email', $reference);
$slotOther = new PublicBookingIntent('intent-public', 'profile-public', 'consultorio-public', gate8eSlot('profile-public', 'consultorio-public', '2026-07-21T10:00:00-06:00', '2026-07-21T10:30:00-06:00'), $contact, '2026-07-21T08:50:00-06:00', '2026-07-21T10:00:00-06:00');
$channelOther = gate8eIntent('intent-public', 'profile-public', 'consultorio-public', 'sms', $reference);
$contactOther = gate8eIntent('intent-public', 'profile-public', 'consultorio-public', 'email', str_repeat('b', 64));
gate8eAssert($intent->bindingFingerprint() === $intentSame->bindingFingerprint(), 'fingerprint deterministic');
gate8eAssert($intent->bindingFingerprint() !== $intentOther->bindingFingerprint(), 'intent changes fingerprint');
gate8eAssert($intent->bindingFingerprint() !== $profileOther->bindingFingerprint(), 'profile changes fingerprint');
gate8eAssert($intent->bindingFingerprint() !== $consultorioOther->bindingFingerprint(), 'consultorio changes fingerprint');
gate8eAssert($intent->bindingFingerprint() !== $slotOther->bindingFingerprint(), 'slot changes fingerprint');
gate8eAssert($intent->bindingFingerprint() !== $channelOther->bindingFingerprint(), 'channel changes fingerprint');
gate8eAssert($intent->bindingFingerprint() !== $contactOther->bindingFingerprint(), 'contact reference changes fingerprint');
gate8eThrows(fn() => new PublicBookingIntent('bad', 'profile-public', 'wrong-consultorio', gate8eSlot(), $contact, '2026-07-21T08:50:00-06:00', '2026-07-21T10:00:00-06:00'), 'invalid_booking_intent', 'slot binding required');
gate8eThrows(fn() => new PublicBookingIntent('bad', 'profile-public', 'consultorio-public', gate8eSlot(), $contact, '2026-07-21T10:00:00-06:00', '2026-07-21T10:00:00-06:00'), 'invalid_booking_intent', 'timestamp ordering required');

$challenge = gate8eChallenge($intent);
$policyDecision = $policy->rateLimitDecision(true);
$verifier = new PublicOtpVerifier();
$missingRate = $verifier->verify(gate8eCommand($intent, $challenge, 'op-missing', 'key-missing', '654321', null), $challenge, $intent);
gate8eAssert($missingRate->status() === PublicOtpVerificationDecision::RATE_LIMIT_DECISION_REQUIRED, 'missing rate limit fails closed');
$limited = $verifier->verify(gate8eCommand($intent, $challenge, 'op-limited', 'key-limited', '654321', $policy->rateLimitDecision(false)), $challenge, $intent);
gate8eAssert($limited->status() === PublicOtpVerificationDecision::RATE_LIMITED, 'denied rate limit fails closed');
gate8eThrows(fn() => gate8eCommand($intent, $challenge, 'op-invalid', 'key-invalid', '12', $policyDecision), 'invalid_otp', 'otp shape fails closed');

$wrong = $verifier->verify(gate8eCommand($intent, $challenge, 'op-wrong', 'key-wrong', '000000', $policyDecision), $challenge, $intent);
gate8eAssert($wrong->status() === PublicOtpVerificationDecision::INVALID_CODE && $wrong->attemptsUsed() === 1 && $wrong->challenge()->state() === 'pending', 'wrong otp increments attempt');
$lockedChallenge = gate8eChallenge($intent, 'challenge-locked', 'pending', 4);
$locked = $verifier->verify(gate8eCommand($intent, $lockedChallenge, 'op-lock', 'key-lock', '000000', $policyDecision), $lockedChallenge, $intent);
gate8eAssert($locked->status() === PublicOtpVerificationDecision::LOCKED && $locked->challenge()->state() === 'locked' && $locked->attemptsUsed() === 5, 'fifth wrong otp locks');
$expiredChallenge = gate8eChallenge($intent, 'challenge-expired');
$expired = $verifier->verify(gate8eCommand($intent, $expiredChallenge, 'op-expired', 'key-expired', '654321', $policyDecision, '2026-07-21T09:11:00-06:00'), $expiredChallenge, $intent);
gate8eAssert($expired->status() === PublicOtpVerificationDecision::EXPIRED && $expired->challenge()->state() === 'expired', 'expired challenge fails closed');
gate8eThrows(fn() => new PublicOtpChallenge('challenge-invalid-state', $intent->intentId(), $intent->bindingFingerprint(), password_hash('654321', PASSWORD_DEFAULT), 'verified', 0, 5, '2026-07-21T09:00:00-06:00', '2026-07-21T09:10:00-06:00'), 'invalid_challenge_state', 'terminal snapshot invariants');

$verifiedCommand = gate8eCommand($intent, $challenge, 'op-verified', 'key-verified', '654321', $policyDecision);
$verified = $verifier->verify($verifiedCommand, $challenge, $intent);
gate8eAssert($verified->status() === PublicOtpVerificationDecision::VERIFIED && $verified->grant() !== null, 'correct otp verifies');
gate8eAssert($verified->event() !== null && $verified->handoff() !== null, 'verification produces one event and handoff');
gate8eAssert($verified->handoff()?->toArray()['server_authoritative_required'] === true, 'handoff server authoritative');
$replay = $verifier->verify($verifiedCommand, $verified->challenge(), $intent, $verified);
gate8eAssert($replay->status() === PublicOtpVerificationDecision::REPLAY && $replay->grant()?->grantDigest() === $verified->grant()?->grantDigest(), 'replay returns same grant');
gate8eAssert($replay->attemptsUsed() === $verified->attemptsUsed() && $replay->event() === null && $replay->handoff() === null, 'replay has no mutations');

$otherBindingCommand = new PublicOtpVerificationCommand('op-conflict', 'key-verified', 'correlation-public', $challenge->challengeId(), $intent->intentId(), str_repeat('c', 64), '654321', '2026-07-21T09:02:00-06:00', $policyDecision);
$conflict = $verifier->verify($otherBindingCommand, $verified->challenge(), $intent, $verified);
gate8eAssert($conflict->status() === PublicOtpVerificationDecision::IDEMPOTENCY_CONFLICT && $conflict->httpStatus() === 409, 'incompatible idempotency key conflicts');

$challengeMismatch = new PublicOtpVerificationCommand('op-challenge-mismatch', 'key-challenge-mismatch', 'correlation-public', 'different-challenge', $intent->intentId(), $intent->bindingFingerprint(), '654321', '2026-07-21T09:02:00-06:00', $policyDecision);
gate8eAssert($verifier->verify($challengeMismatch, $challenge, $intent)->status() === PublicOtpVerificationDecision::CHALLENGE_MISMATCH, 'challenge mismatch rejected');
$intentMismatch = new PublicOtpVerificationCommand('op-intent-mismatch', 'key-intent-mismatch', 'correlation-public', $challenge->challengeId(), 'different-intent', $intent->bindingFingerprint(), '654321', '2026-07-21T09:02:00-06:00', $policyDecision);
gate8eAssert($verifier->verify($intentMismatch, $challenge, $intent)->status() === PublicOtpVerificationDecision::INTENT_MISMATCH, 'intent mismatch rejected');
$bindingMismatch = new PublicOtpVerificationCommand('op-binding-mismatch', 'key-binding-mismatch', 'correlation-public', $challenge->challengeId(), $intent->intentId(), str_repeat('d', 64), '654321', '2026-07-21T09:02:00-06:00', $policyDecision);
gate8eAssert($verifier->verify($bindingMismatch, $challenge, $intent)->status() === PublicOtpVerificationDecision::BINDING_MISMATCH, 'binding mismatch rejected');

$grant = $verified->grant();
gate8eAssert($grant !== null && !$grant->consumed(), 'grant issued and unused');
gate8eThrows(fn() => $grant->consume('2026-07-21T10:00:00-06:00'), 'grant_expired', 'expired grant rejected');
$consumedGrant = $grant->consume('2026-07-21T09:03:00-06:00');
gate8eThrows(fn() => $consumedGrant->consume('2026-07-21T09:04:00-06:00'), 'grant_consumed', 'grant single use');
gate8eAssert($grant->intentId() === $intent->intentId() && $grant->bindingFingerprint() === $intent->bindingFingerprint(), 'grant binding exact');
gate8eAssert(!str_contains(json_encode($grant->toArray(), JSON_THROW_ON_ERROR), '654321'), 'raw otp absent from grant');
gate8eThrows(fn() => PublicVerificationGrant::issue($challenge, $intentOther, '2026-07-21T09:02:00-06:00'), 'binding_mismatch', 'grant other intent rejected');

$capability = PublicCancellationCapability::issue($intent, '2026-07-21T09:02:00-06:00');
gate8eThrows(fn() => $capability->consume('2026-07-21T10:00:00-06:00'), 'invalid_cancellation_capability', 'expired capability rejected');
$consumedCapability = $capability->consume('2026-07-21T09:03:00-06:00');
gate8eThrows(fn() => $consumedCapability->consume('2026-07-21T09:04:00-06:00'), 'grant_consumed', 'capability single use');
gate8eAssert($capability->intentId() === $intent->intentId(), 'capability intent exact');
$capabilityHandoff = PublicBookingHandoff::create($intent, 'capability', 'appointment-public', null, $capability, 'correlation-cancel', '2026-07-21T09:03:00-06:00');
gate8eAssert($capabilityHandoff->reasonCode() === 'public_capability_canceled' && $capabilityHandoff->toState() === 'canceled', 'capability handoff exact');
gate8eThrows(fn() => PublicBookingHandoff::create($intentOther, 'capability', 'appointment-public', null, $capability, 'correlation-cancel', '2026-07-21T09:03:00-06:00'), 'unauthorized_public_handoff', 'capability other intent rejected');

$projection = new PublicContactPrivacyProjection();
$before = $projection->beforeConfirmation($intent, $challenge, 480, 'verify_otp', 'generic_pending');
$after = $projection->afterConfirmation('appointment-public', 'confirmed', 'done', 'generic_confirmed');
$genericError = $projection->genericError('invalid_code');
gate8eAssert($before === ['intent_id' => 'intent-public', 'challenge_id' => 'challenge-public', 'masked_destination' => 'p***@example.mx', 'expires_in' => 480, 'next_action' => 'verify_otp', 'result_code' => 'generic_pending'], 'canonical before projection stable');
gate8eAssert($after === ['appointment_id' => 'appointment-public', 'status' => 'confirmed', 'next_action' => 'done', 'result_code' => 'generic_confirmed'], 'canonical after projection stable');
gate8eAssert($genericError === ['result_code' => 'invalid_code', 'next_action' => 'retry'], 'canonical generic error stable');
foreach (['verify_otp', 'done', 'retry', 'generic_pending', 'generic_confirmed', 'confirmed', 'invalid_code', 'locked', 'expired', 'verified'] as $token) {
    gate8eAssert(PublicAgendaPolicy::publicProjectionToken($token) === $token, 'valid public token preserved');
}
foreach (['operation-public', 'correlation-public', 'invalid_code', 'public_otp_verified', 'op:verify', 'correlation.v1'] as $token) {
    gate8eAssert(PublicAgendaPolicy::auditMetadataToken($token) === $token, 'valid audit token preserved');
}
gate8eAssert(PublicAgendaPolicy::identifier('opaque value', 'invalid_booking_intent') === 'opaque value', 'generic identifier unchanged');

$projectionInjectionCases = [
    [fn() => $projection->beforeConfirmation($intent, $challenge, 480, 'paciente.real@example.mx', 'generic_pending'), 'email in next action'],
    [fn() => $projection->beforeConfirmation($intent, $challenge, 480, '+5214491234567', 'generic_pending'), 'phone in next action'],
    [fn() => $projection->beforeConfirmation($intent, $challenge, 480, '654321', 'generic_pending'), 'otp in next action'],
    [fn() => $projection->beforeConfirmation($intent, $challenge, 480, 'code_654321', 'generic_pending'), 'embedded otp in next action'],
    [fn() => $projection->beforeConfirmation($intent, $challenge, 480, 'verify_otp', 'paciente.real@example.mx'), 'email in result code'],
    [fn() => $projection->beforeConfirmation($intent, $challenge, 480, 'verify_otp', '+5214491234567'), 'phone in result code'],
    [fn() => $projection->beforeConfirmation($intent, $challenge, 480, 'verify_otp', '654321'), 'otp in result code'],
    [fn() => $projection->beforeConfirmation($intent, $challenge, 480, 'verify_otp', 'code_654321'), 'embedded otp in result code'],
    [fn() => $projection->afterConfirmation('appointment-public', 'paciente.real@example.mx', 'done', 'generic_confirmed'), 'email in status'],
    [fn() => $projection->afterConfirmation('appointment-public', '+5214491234567', 'done', 'generic_confirmed'), 'phone in status'],
    [fn() => $projection->afterConfirmation('appointment-public', '654321', 'done', 'generic_confirmed'), 'otp in status'],
    [fn() => $projection->genericError('paciente.real@example.mx'), 'email in generic error'],
    [fn() => $projection->genericError('+5214491234567'), 'phone in generic error'],
    [fn() => $projection->beforeConfirmation($intent, $challenge, 480, 'invalid token', 'generic_pending'), 'space in token'],
    [fn() => $projection->beforeConfirmation($intent, $challenge, 480, '1invalid', 'generic_pending'), 'numeric token prefix'],
];
foreach ($projectionInjectionCases as [$callback, $message]) gate8eRejectsWithoutOutput($callback, 'invalid_public_projection_token', $message);

$publicSerialized = json_encode([$before, $after, $wrong->toArray(), $verified->toArray()], JSON_THROW_ON_ERROR);
foreach (['paciente.real@example.mx', '+5214491234567', '654321', 'code_654321', 'otp-654321', $challenge->credentialHash(), $reference] as $forbidden) gate8eAssert(!str_contains($publicSerialized, $forbidden), 'sensitive fixture absent from public surfaces');
$internalSerialized = json_encode([$verified->event()?->toArray(), $verified->handoff()?->toArray(), $capabilityHandoff->toArray()], JSON_THROW_ON_ERROR);
foreach (['paciente.real@example.mx', '+5214491234567', '654321', $challenge->credentialHash()] as $forbidden) gate8eAssert(!str_contains($internalSerialized, $forbidden), 'sensitive fixture absent from domain outputs');
gate8eAssert(array_keys($before) === ['intent_id', 'challenge_id', 'masked_destination', 'expires_in', 'next_action', 'result_code'], 'before projection allow list');
gate8eAssert(array_keys($after) === ['appointment_id', 'status', 'next_action', 'result_code'], 'after projection allow list');

gate8eAssert(PublicAuditEvent::types() === ['public_otp_challenge_issued', 'public_otp_attempt_rejected', 'public_otp_verified', 'public_otp_locked', 'public_otp_expired', 'public_otp_consumed', 'public_booking_handoff_requested', 'public_booking_cancellation_requested'], 'audit allow list');
gate8eAssert($verified->event()?->eventId() === $verified->event()?->eventId() && !str_contains(json_encode($verified->event()?->toArray(), JSON_THROW_ON_ERROR), '654321'), 'audit deterministic and minimized');
$canonicalEvent = new PublicAuditEvent('public_otp_attempt_rejected', 'intent-public', 'challenge-public', 'operation-public', 'correlation-public', 'invalid_code', 'email', 1, '2026-07-21T09:02:00-06:00', 1, false);
$canonicalEventArray = ['event_id' => 'b2ae37248ada8113e61ebe6ab25699277ea897833f36694fad8d38e79b04eb01', 'event_type' => 'public_otp_attempt_rejected', 'intent_id_digest' => '44646e493c5dd9c7e7617e1b30cfd2448b148a1fde6de5a25702b521fb68f3fc', 'challenge_id_digest' => 'e90e74188763e6739cb6dfc57396719eb9075e9012a6947e77ae50bfb8127269', 'operation_id' => 'operation-public', 'correlation_id' => 'correlation-public', 'outcome_code' => 'invalid_code', 'channel' => 'email', 'policy_version' => 1, 'occurred_at' => '2026-07-21T09:02:00.000000-06:00', 'attempts_used' => 1, 'terminal' => false];
gate8eAssert($canonicalEvent->eventId() === 'b2ae37248ada8113e61ebe6ab25699277ea897833f36694fad8d38e79b04eb01', 'canonical event id stable');
gate8eAssert($canonicalEvent->toArray() === $canonicalEventArray, 'canonical event serialization stable');

$auditFactory = fn(string $operation, string $correlation, string $outcome): PublicAuditEvent => new PublicAuditEvent('public_otp_attempt_rejected', 'intent-public', 'challenge-public', $operation, $correlation, $outcome, 'email', 1, '2026-07-21T09:02:00-06:00', 1, false);
$auditInjectionValues = ['paciente.real@example.mx', '+5214491234567', '654321', 'otp-654321'];
foreach ($auditInjectionValues as $injected) {
    gate8eRejectsWithoutOutput(fn() => $auditFactory($injected, 'correlation-public', 'invalid_code'), 'invalid_audit_metadata', 'injection in operation id');
    gate8eRejectsWithoutOutput(fn() => $auditFactory('operation-public', $injected, 'invalid_code'), 'invalid_audit_metadata', 'injection in correlation id');
    gate8eRejectsWithoutOutput(fn() => $auditFactory('operation-public', 'correlation-public', $injected), 'invalid_audit_outcome', 'injection in outcome code');
}

$handoffMap = [
    'pending' => 'create_pending_otp_appointment',
    'verified' => 'confirm_verified_appointment',
    'expired' => 'cancel_expired_appointment',
    'locked' => 'cancel_locked_appointment',
];
foreach ($handoffMap as $operation => $type) {
    $handoff = PublicBookingHandoff::create($intent, $operation, $operation === 'verified' ? 'appointment-public' : null, $operation === 'verified' ? $grant->grantDigest() : null, null, 'correlation-handoff', '2026-07-21T09:03:00-06:00');
    gate8eAssert($handoff->handoffType() === $type, 'handoff type exact');
}

$mutationPlan = new PublicBookingMutationPlan();
$expectedPlan = ['begin_transaction', 'lock_public_intent', 'lock_rate_limit_scope', 'lock_active_otp_challenge', 'resolve_idempotency', 'verify_binding_fingerprint', 'verify_challenge_state', 'verify_expiration', 'verify_attempt_budget', 'verify_credential', 'persist_challenge_result', 'persist_or_replay_verification_grant', 'delegate_appointment_mutation_to_gate8d', 'append_public_audit_event', 'persist_idempotency_result', 'commit'];
gate8eAssert($mutationPlan->steps() === $expectedPlan && $mutationPlan->failureAction() === 'rollback' && $mutationPlan->transactionRequired(), 'mutation plan exact');
gate8eAssert($mutationPlan->gate8dDelegationRequired() && $mutationPlan->appendAuditInSameTransaction() && $mutationPlan->grantInSameTransaction() && !$mutationPlan->directAppointmentWriteAllowed() && !$mutationPlan->directSqlAllowed() && !$mutationPlan->executesOperations(), 'mutation boundaries exact');

$planText = file_get_contents(__DIR__ . '/../../../docs/PLAN_MAESTRO_MXMED.md');
gate8eAssert(is_string($planText), 'plan readable');
foreach (['PP-304', 'PP-305', 'PP-306', 'PP-307', 'PP-308'] as $number) gate8eAssert(substr_count($planText, '### ' . $number . ' —') === 1, $number . ' exact once');
$pp308 = [];
preg_match('/### PP-308 .*?(?=### PP-[0-9]+ —|\z)/s', $planText, $pp308);
gate8eAssert(isset($pp308[0]), 'PP-308 present');
$pp308Normalized = rtrim($pp308[0], "\r\n") . "\n";
gate8eAssert(hash('sha256', $pp308Normalized) === 'cc2c17c0061742e72e234cda7ccfe3efee1fac904e30d1a755fd6f1a236926f4', 'PP-308 normalized hash');

$domainSource = '';
foreach (glob(__DIR__ . '/../publicflow/*.php') as $file) $domainSource .= file_get_contents($file);
foreach (['PDO', 'SELECT ', 'INSERT ', 'UPDATE ', 'DELETE ', 'FOR UPDATE', 'beginTransaction', 'commit(', 'rollBack', '$_GET', '$_POST', '$_SESSION', 'getenv(', 'header(', 'getallheaders', 'curl_', 'fopen(', 'file_put_contents', 'error_log', 'DevOtpSender', 'PublicAppointmentsController', 'PublicOtpController', 'PublicOtpRepository', 'AppointmentWriteController', 'createFromPayload', 'random_bytes', 'uniqid(', 'date('] as $forbidden) gate8eAssert(!str_contains($domainSource, $forbidden), 'domain purity: ' . $forbidden);

echo "Gate8EPublicAgendaOtpPrivacyTest PASS\n";
