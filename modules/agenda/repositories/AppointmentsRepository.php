<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class AppointmentsRepository
{
    private PDO $pdo;
    private string $table;
    private array $columnsCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $table = trim((string)($config['appointments_table'] ?? ''));
        $this->table = $table !== '' ? $this->sanitizeIdentifier($table) : 'agenda_appointments';
    }

    private function ensureTable(): void
    {
        if (!$this->tableExists($this->table)) {
            throw new RuntimeException('appointments table not ready');
        }
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function listByRange(string $from, string $to, ?string $doctorId = null, ?string $consultorioId = null, int $limit = 200, ?string $patientId = null): array
    {
        $this->ensureTable();
        $columns = $this->getColumns($this->table);
        $selectFields = [
            'appointment_id',
            'doctor_id',
            'consultorio_id',
            'patient_id',
            'start_at',
            'end_at',
            'modality',
            'status',
        ];
        foreach ([
            'channel_origin',
            'created_by_role',
            'created_by_id',
            'operator_id',
            'operator_slot',
            'operator_number',
            'operator_alias',
            'origin_visual_key',
            'source',
            'origin',
        ] as $optionalField) {
            if (in_array($optionalField, $columns, true)) {
                $selectFields[] = $optionalField;
            }
        }
        $selectSql = implode(', ', $selectFields);
        $sql = sprintf(
            'SELECT %s FROM %s WHERE start_at < :to AND end_at > :from',
            $selectSql,
            $this->table
        );
        $params = ['from' => $from, 'to' => $to];

        if ($doctorId) {
            $sql .= ' AND doctor_id = :doctor_id';
            $params['doctor_id'] = $doctorId;
        }
        if ($consultorioId) {
            $sql .= ' AND consultorio_id = :consultorio_id';
            $params['consultorio_id'] = $consultorioId;
        }
        if ($patientId) {
            $sql .= ' AND patient_id = :patient_id';
            $params['patient_id'] = $patientId;
        }

        $sql .= ' ORDER BY start_at ASC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $this->withCreatorSnapshots($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getColumns(string $table): array
    {
        if (!isset($this->columnsCache[$table])) {
            $stmt = $this->pdo->prepare(
                'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $stmt->execute(['table' => $table]);
            $this->columnsCache[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        return $this->columnsCache[$table];
    }

    public function getById(string $id): ?array
    {
        $this->ensureTable();
        $sql = sprintf('SELECT * FROM %s WHERE appointment_id = :id LIMIT 1', $this->table);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->withCreatorSnapshots([$row])[0] : null;
    }

    /** Label DTO only: never hydrate accounts, contacts or the current seat occupant. */
    private function withCreatorSnapshots(array $rows): array
    {
        if(!$rows)return $rows;
        $s=$this->pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='agenda_appointment_creator_snapshots'");$s->execute();
        $map=[];
        if((int)$s->fetchColumn()===1){
            $ids=array_column($rows,'appointment_id');
            $s=$this->pdo->prepare('SELECT s.appointment_id,s.snapshot_version,s.creator_alias,s.creator_position,s.origin_visual_key,s.created_at_utc FROM agenda_appointment_creator_snapshots s JOIN '.$this->table.' a ON a.appointment_id=s.appointment_id AND a.doctor_id=s.profile_doctor_id WHERE s.appointment_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
            $s->execute($ids);
            foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r)$map[$r['appointment_id']]=['version'=>(int)$r['snapshot_version'],'alias'=>$r['creator_alias'],'position'=>$r['creator_position']===null?null:(int)$r['creator_position'],'origin_visual_key'=>$r['origin_visual_key'],'created_at_utc'=>$r['created_at_utc']];
        }
        foreach($rows as &$row){
            $row['creator_snapshot']=$map[$row['appointment_id']]??null;
            $row['creator_origin_status']=$row['creator_snapshot']===null?'historical_unverified':'snapshot_v1';
        }unset($row);
        return $rows;
    }

    private function sanitizeIdentifier(?string $value): string
    {
        if (!$value) {
            return '';
        }
        return preg_replace('/[^a-zA-Z0-9_]/', '', $value) ?: '';
    }

    private function loadConfig(): array
    {
        $path = __DIR__ . '/../config/agenda.php';
        if (!is_file($path)) {
            return [];
        }
        $config = require $path;
        return is_array($config) ? $config : [];
    }
}
