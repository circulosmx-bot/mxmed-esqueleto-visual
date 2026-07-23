<?php
declare(strict_types=1);

namespace Agenda\Adapters;

use Agenda\Appointments\AppointmentMutationPlan;
use InvalidArgumentException;
use Platform\Contracts\Pg03CutoverFeatureFlagPort;
use Platform\Contracts\Pg03ObservabilityPort;

require_once __DIR__ . '/../appointments/AppointmentMutationPlan.php';
require_once __DIR__ . '/../../platform/contracts/Pg03CutoverFeatureFlagPort.php';
require_once __DIR__ . '/../../platform/contracts/Pg03ObservabilityPort.php';

final class CanonicalAppointmentLifecycleAdapter
{
    public static function canonicalAppointmentLifecycleEnabled(array $config): bool
    {
        return ($config['feature_flags']['canonical_appointment_lifecycle'] ?? null) === true;
    }

    public function mutationPlan(): array
    {
        return (new AppointmentMutationPlan())->toArray();
    }

    public function clinicalBoundary(string $appointmentReference): array
    {
        if ($appointmentReference === '' || preg_match('/\A[A-Za-z0-9._:-]+\z/', $appointmentReference) !== 1) {
            throw new InvalidArgumentException('unsafe_appointment_reference');
        }
        return [
            'appointment_reference_digest' => 'sha256:' . hash('sha256', $appointmentReference),
            'event_owner' => 'agenda',
            'agenda_appointment_is_clinical_encounter' => false,
            'clinical_event_schema' => 'UNRESOLVED_PENDING_PARAMETER_APPROVAL',
            'clinical_retries' => 'UNRESOLVED_PENDING_PARAMETER_APPROVAL',
            'clinical_dlq' => 'UNRESOLVED_PENDING_PARAMETER_APPROVAL',
            'clinical_retention' => 'UNRESOLVED_PENDING_PARAMETER_APPROVAL',
            'clinical_compensations' => 'UNRESOLVED_PENDING_PARAMETER_APPROVAL',
            'outbox_implemented' => false,
            'saga_implemented' => false,
            'worker_implemented' => false,
            'queue_implemented' => false,
            'clinical_requests_executed' => 0,
        ];
    }

    public function readiness(
        Pg03CutoverFeatureFlagPort $flags,
        Pg03ObservabilityPort $observability
    ): array {
        return [
            'mode' => 'dormant_harness_only',
            'feature_configured' => $flags->configuredValue('canonical_appointment_lifecycle'),
            'feature_effective' => false,
            'observability_availability' => $observability->availability(),
            'observability_sink_configured' => false,
            'clinical_parameters_approved' => false,
            'activation_authorized' => false,
            'runtime_wiring' => false,
            'ready' => false,
        ];
    }
}
