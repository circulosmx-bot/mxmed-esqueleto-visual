<?php
declare(strict_types=1);

/** Read-only, patient-scoped safety context for the canonical prescription editor. */
final class ClinicalPrescriptionContext
{
    public function __construct(private PDO $pdo) {}

    public function read(string $doctorId, string $patientId): array
    {
        $patient = $this->pdo->prepare(
            'SELECT patient_id,display_name,birthdate,sex FROM patients_patients WHERE patient_id=? LIMIT 1'
        );
        $patient->execute([$patientId]);
        $person = $patient->fetch(PDO::FETCH_ASSOC);
        if (!is_array($person)) throw new ClinicalLongitudinalException('NOT_FOUND', 404);

        // Each reused longitudinal reader enforces the active doctor-patient link.
        $allergyData = (new ClinicalLongitudinalAntecedents($this->pdo))
            ->read('allergies', $doctorId, $patientId);
        $medicationData = (new ClinicalLongitudinalMedications($this->pdo))
            ->read($doctorId, $patientId);
        $problemData = (new ClinicalLongitudinalProblems($this->pdo))
            ->read($doctorId, $patientId);

        // Read the canonical encounter table directly. Route cohort selection is a
        // delivery concern and must not replace clinical truth in a safety context.
        $encounter = (new ClinicalEncounterIntegrityRepository($this->pdo))
            ->findOpen($doctorId, $patientId);
        $reason = null;
        if (is_array($encounter)) {
            $section = $this->pdo->prepare("SELECT narrative_text FROM clinical_encounter_sections
                WHERE encounter_id=? AND section_type='reason_evolution' LIMIT 1");
            $section->execute([(int)$encounter['encounter_id']]);
            $value = trim((string)($section->fetchColumn() ?: ''));
            $reason = $value !== '' ? $value : null;
        }

        $age = null;
        if (!empty($person['birthdate'])) {
            try {
                $zone = new DateTimeZone('America/Mexico_City');
                $age = (new DateTimeImmutable((string)$person['birthdate'], $zone))
                    ->diff(new DateTimeImmutable('today', $zone))->y;
            } catch (Throwable) { $age = null; }
        }

        $allergyRows = array_values(array_filter((array)($allergyData['items'] ?? []),
            static fn($row) => is_array($row) && ($row['state'] ?? '') === 'CURRENT'));
        $allergies = array_map(static fn($row) => [
            'allergy_id'=>(int)$row['allergy_id'], 'substance'=>(string)$row['substance'],
            'reaction'=>$row['reaction'] === null ? null : (string)$row['reaction'],
            'state'=>(string)$row['state'], 'validation_state'=>(string)$row['validation_state'],
            'provenance'=>(string)$row['provenance'], 'row_version'=>(int)$row['row_version'],
        ], $allergyRows);
        $allMedicationRows = (array)($medicationData['items'] ?? []);
        $medicationRows = array_values(array_filter($allMedicationRows,
            static fn($row) => is_array($row) && in_array(($row['state'] ?? ''),
                ['ACTIVE_CONFIRMED','REPORTED_BY_PATIENT','PRESCRIBED_NOT_CONFIRMED_ACTIVE'], true)));
        $medications = array_map(static fn($row) => [
            'medication_id'=>(int)$row['medication_id'], 'medication_name'=>(string)$row['medication_name'],
            'dose'=>$row['dose'], 'dose_unit'=>$row['dose_unit'], 'route'=>$row['route'],
            'frequency'=>$row['frequency'], 'started_at'=>$row['started_at'],
            'state'=>(string)$row['state'], 'provenance'=>(string)$row['provenance'],
            'row_version'=>(int)$row['row_version'],
        ], $medicationRows);
        $problemRows = array_values(array_filter((array)($problemData['items'] ?? []),
            static fn($row) => is_array($row) && ($row['status'] ?? '') === 'ACTIVE'));
        $problems = array_map(static fn($row) => [
            'problem_id'=>(int)$row['problem_id'], 'label'=>(string)$row['label'],
            'status'=>(string)$row['status'], 'provenance'=>(string)$row['provenance'],
            'row_version'=>(int)$row['row_version'],
        ], $problemRows);

        $allergyKnowledge = (string)($allergyData['knowledge_state'] ?? 'UNKNOWN');
        $allergyState = $allergies !== [] ? 'PRESENT' : match ($allergyKnowledge) {
            'CONFIRMED_NONE' => 'CONFIRMED_NONE',
            'REVIEWED_WITH_ALLERGIES' => 'UNREVIEWED',
            default => 'UNREVIEWED',
        };
        $medicationKnowledge = (string)($medicationData['knowledge_state'] ?? 'UNREVIEWED');
        $medicationState = $medications !== [] ? 'PRESENT'
            : ($allMedicationRows !== [] ? 'NONE_ACTIVE' : 'UNREVIEWED');

        $zone = new DateTimeZone('America/Mexico_City');
        $data = [
            'patient' => ['patient_id'=>(string)$person['patient_id'],
                'display_name'=>(string)$person['display_name'], 'age'=>$age,
                'sex'=>match (strtoupper(trim((string)($person['sex'] ?? '')))) {
                    'F', 'FEMALE', 'FEMENINO' => 'Femenino',
                    'M', 'MALE', 'MASCULINO' => 'Masculino',
                    default => (string)($person['sex'] ?? ''),
                }],
            'encounter' => $encounter === null
                ? ['state'=>'NONE','encounter_id'=>null,'encounter_date'=>null,'motive'=>null]
                : ['state'=>'OPEN','encounter_id'=>(int)$encounter['encounter_id'],
                    'encounter_date'=>(string)$encounter['encounter_dt'],'motive'=>$reason],
            'allergies' => ['state'=>$allergyState,'knowledge_state'=>$allergyKnowledge,
                'items'=>$allergies],
            'medications' => ['state'=>$medicationState,'knowledge_state'=>$medicationKnowledge,
                'list_version'=>(int)($medicationData['list_version'] ?? 0),'items'=>$medications],
            'problems' => ['state'=>$problems === [] ? 'NONE_ACTIVE' : 'PRESENT','items'=>$problems],
            'clinical_date' => (new DateTimeImmutable('now', $zone))->format('Y-m-d'),
        ];
        $revisionMaterial = $data;
        unset($revisionMaterial['clinical_date']);
        $data['revision'] = hash('sha256', json_encode($revisionMaterial,
            JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        return $data;
    }
}
