<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) {
    require_once $file;
}

use Agenda\Contracts\ActorAuthorityContract;
use Agenda\Contracts\ActorReference;
use Agenda\Contracts\AgendaAppointmentReference;
use Agenda\Contracts\AppointmentLifecycleContract;
use Agenda\Contracts\AuditEventContract;
use Agenda\Contracts\ContactDescriptor;
use Agenda\Contracts\DecisionContractRegistry;
use Agenda\Contracts\IdempotencyContract;
use Agenda\Contracts\IdempotencyRecord;
use Agenda\Contracts\MigrationContract;
use Agenda\Contracts\PatientIdentityMatch;
use Agenda\Contracts\PatientMergeContract;
use Agenda\Contracts\ClinicalEncounterReference;
use Agenda\Contracts\PublicOtpPolicy;
use Agenda\Contracts\RetentionContract;
use Agenda\Contracts\RolloutContract;
use Agenda\Contracts\ScheduleAvailabilityContract;
use Agenda\Contracts\ScheduleWindow;

function gate8aAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function gate8aThrows(callable $callback, string $message): void
{
    try { $callback(); } catch (InvalidArgumentException|RuntimeException) { return; }
    throw new RuntimeException($message);
}

$registry = DecisionContractRegistry::all();
gate8aAssert(count($registry) === 12, 'DEC-013A–L registry has twelve entries');
gate8aAssert(count(array_unique(array_keys($registry))) === 12, 'DEC decision codes are unique');
foreach (range('A', 'L') as $suffix) {
    gate8aAssert(isset($registry['DEC-013' . $suffix]), 'missing DEC-013' . $suffix);
}

$real = new ActorReference('account', 'account-test');
$effective = new ActorReference('operator', 'operator-test');
$authority = ActorAuthorityContract::trusted($real, $effective, 'account-test', 'membership-test', 'doctor', 'profile-test', 'owner');
gate8aAssert($authority->allowed() && $authority->serverAuthoritative(), 'trusted authority allows');
gate8aAssert($authority->realActor()?->id() !== $authority->effectiveActor()?->id(), 'real and effective actors stay separate');
gate8aAssert($authority->toArray()['source'] === ActorAuthorityContract::SOURCE_SERVER_CONTEXT, 'source is server context');
$denied = ActorAuthorityContract::denied(ActorAuthorityContract::CLIENT_INPUT_NOT_AUTHORITATIVE);
gate8aAssert(!$denied->allowed() && !$denied->serverAuthoritative(), 'client authority fails closed');

$schedule = new ScheduleAvailabilityContract('profile-test', 'consultorio-test', 'America/Mexico_City', [new ScheduleWindow('09:00', '09:30')], 30, 0, [], [], [], '2026-07-21');
gate8aAssert($schedule->isReadModel() && !$schedule->editableAuthority(), 'availability is calculated read model');
gate8aAssert($schedule->toArray()['timezone'] === 'America/Mexico_City', 'schedule timezone preserved');

gate8aAssert(in_array(AppointmentLifecycleContract::CONFIRMED, AppointmentLifecycleContract::states(), true), 'confirmed state represented');
$validTransition = AppointmentLifecycleContract::transition(AppointmentLifecycleContract::TENTATIVE, AppointmentLifecycleContract::CONFIRMED);
$invalidTransition = AppointmentLifecycleContract::transition(AppointmentLifecycleContract::CANCELED, AppointmentLifecycleContract::CONFIRMED);
gate8aAssert($validTransition->allowed() && $invalidTransition->httpStatus() === 409, 'invalid transition closes with 409');
gate8aAssert(!AppointmentLifecycleContract::agendaAppointmentIsClinicalEncounter(), 'Agenda appointment differs from Clinical encounter');
gate8aAssert((new AgendaAppointmentReference('appointment-test'))->entityType() !== (new ClinicalEncounterReference('encounter-test'))->entityType(), 'appointment and encounter references differ');

$record = new IdempotencyRecord('reserve', 'idempotency-test', 'slot-test', 'fingerprint-test', ['appointment' => 'result-test']);
$replay = IdempotencyContract::evaluate($record, 'idempotency-test', 'fingerprint-test');
$conflict = IdempotencyContract::evaluate($record, 'idempotency-test', 'different-fingerprint');
gate8aAssert($replay->status() === IdempotencyContract::REPLAY && !$replay->mutationEffective() && $replay->result()['appointment'] === 'result-test', 'replay returns original result');
gate8aAssert($conflict->status() === IdempotencyContract::CONFLICT && $conflict->httpStatus() === 409, 'idempotency conflict closes');

$otp = new PublicOtpPolicy();
gate8aAssert($otp->hashOnly() && $otp->maxAttempts() === 5 && $otp->antiEnumeration(), 'OTP policy is hash-only and bounded');
gate8aAssert(!$otp->verifyState(false, 0, true)->allowed(), 'consumed OTP replay denied');
gate8aAssert($otp->verifyState(false, 0, true)->reason() === 'replay_denied', 'OTP replay reason stable');

$contact = new ContactDescriptor(ContactDescriptor::CONTACT, 'patient_capture', 'consent_reference', 'phone', 'private_masked', 'last4', '2026-07-21');
gate8aAssert($contact->category() === ContactDescriptor::CONTACT, 'contact category represented');
$match = PatientIdentityMatch::probable(['name' => 'normalized', 'birthdate' => 'same']);
gate8aAssert($match->warningBeforeCreate() && !$match->autoMerge() && !$match->curpRequired(), 'probable identity warns without auto-merge and CURP');
gate8aAssert(PatientIdentityMatch::noMatch()->kind() === PatientIdentityMatch::NO_MATCH, 'no-match represented');

$merge = new PatientMergeContract(PatientMergeContract::DRY_RUN, 'actor-test', 'review-test', ['alias-test'], ['snapshot-test'], ['appointment-test'], false);
$apply = new PatientMergeContract(PatientMergeContract::APPLY, 'actor-test', 'review-test', [], [], [], true);
gate8aAssert($merge->disabled() && !$merge->endpointEnabled() && !$merge->canExecute() && $merge->requiresReauthentication(), 'merge disabled and reauthentication required');
gate8aAssert($apply->mode() === PatientMergeContract::APPLY && !$apply->canExecute(), 'apply remains non-executable');

$migration = new MigrationContract('migration-v1', 'checksum-test', 'preflight', 'apply', 'verify', 'rollback', 'ledger');
gate8aAssert(!$migration->executionAllowed(), 'migration execution remains outside Gate 8A');

$event = new AuditEventContract($real, $effective, 'appointment-test', 'transition', 'reason-test', 'correlation-test', 'request-test', 'tentative', 'confirmed', 'accepted', ['result' => 'allowed']);
gate8aAssert($event->appendOnly() && $event->realActor()->id() !== $event->effectiveActor()->id(), 'audit event is append-only and attributed');
gate8aThrows(fn() => new AuditEventContract($real, $effective, 'subject', 'action', 'reason', 'correlation', 'request', 'before', 'after', 'accepted', ['token' => 'forbidden']), 'sensitive audit data rejected');

$retention = new RetentionContract('appointments', 'timeline_projection', 'public_flow_copy', 'sensitive_contact', false, true, false, ['clinical_reference']);
gate8aAssert($retention->concretePeriod() === null && $retention->dryRunOnly(), 'retention has no concrete legal period and is dry-run');

$rollout = new RolloutContract(['shadow', 'dual_read', 'reversible_backfill'], true, ['error_rate'], true, 'rollback-plan', 'owner-test', 'retirement-after-parity');
gate8aAssert($rollout->retirementRequired() && $rollout->hardStop(), 'temporary rollout requires retirement and hard-stop');

echo "Gate8ACanonicalContractsTest PASS\n";
