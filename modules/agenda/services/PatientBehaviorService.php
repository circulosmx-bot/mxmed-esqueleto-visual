<?php
namespace Agenda\Services;

use Agenda\Repositories\PatientBehaviorRepository;

class PatientBehaviorService
{
    private PatientBehaviorRepository $repository;

    public function __construct(PatientBehaviorRepository $repository)
    {
        $this->repository = $repository;
    }

    public function evaluatePatientBehavior(string $patientId, string $doctorId): array
    {
        $noShowCount = $this->repository->countNoShow($patientId, $doctorId);
        $lateCancelCount = $this->repository->countLateCancel($patientId, $doctorId);
        $riskLevel = $this->resolveRiskLevel($noShowCount, $lateCancelCount);

        return [
            'no_show_count' => $noShowCount,
            'late_cancel_count' => $lateCancelCount,
            'risk_level' => $riskLevel,
            'recommendation' => $this->resolveRecommendation($riskLevel),
        ];
    }

    private function resolveRiskLevel(int $noShowCount, int $lateCancelCount): string
    {
        $severity = 0; // 0 normal, 1 warning, 2 risk, 3 high_risk

        if ($noShowCount >= 3) {
            $severity = max($severity, 3);
        } elseif ($noShowCount >= 2) {
            $severity = max($severity, 2);
        } elseif ($noShowCount >= 1) {
            $severity = max($severity, 1);
        }

        if ($lateCancelCount >= 3) {
            $severity = max($severity, 2);
        } elseif ($lateCancelCount >= 1) {
            $severity = max($severity, 1);
        }

        if ($severity === 3) {
            return 'high_risk';
        }
        if ($severity === 2) {
            return 'risk';
        }
        if ($severity === 1) {
            return 'warning';
        }
        return 'normal';
    }

    private function resolveRecommendation(string $riskLevel): string
    {
        if ($riskLevel === 'high_risk') {
            return 'Escalar a revisión del consultorio antes de confirmar nuevas citas.';
        }
        if ($riskLevel === 'risk') {
            return 'Requiere validación operativa antes de confirmar nuevas citas.';
        }
        if ($riskLevel === 'warning') {
            return 'Mostrar advertencia amable y confirmar contexto del paciente.';
        }
        return 'Sin incidencias relevantes; operación normal.';
    }
}
