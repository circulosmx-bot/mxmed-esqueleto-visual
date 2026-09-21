<?php
declare(strict_types=1);

/** Read-only readiness authority for the canonical M6 migrations 01-05. */
final class ClinicalSchemaReadinessComparator
{
    private PDO $pdo;
    private string $schema;
    private array $authority;

    public function __construct(PDO $pdo, string $schema, ?string $authorityPath = null)
    {
        $schema = trim($schema);
        if ($schema === '' || !preg_match('/^[A-Za-z0-9_]+$/', $schema)) {
            throw new InvalidArgumentException('SCHEMA_TARGET_REQUIRED');
        }
        $path = $authorityPath ?? dirname(__DIR__, 2) . '/modules/clinical/schema/readiness_authority.json';
        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded) || ($decoded['authority_version'] ?? null) !== 1) {
            throw new RuntimeException('SCHEMA_AUTHORITY_INVALID');
        }
        $this->pdo = $pdo;
        $this->schema = $schema;
        $this->authority = $decoded;
    }

    public function compare(string $mode): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['prestate', 'poststate'], true)) {
            throw new InvalidArgumentException('MODE_MUST_BE_PRESTATE_OR_POSTSTATE');
        }
        $started = hrtime(true);
        $mismatches = $mode === 'prestate' ? $this->comparePrestate() : $this->comparePoststate();
        return [
            'mode' => $mode,
            'ready' => $mismatches === [],
            'target_schema' => $this->schema,
            'authority_version' => $this->authority['authority_version'],
            'migration_set' => $this->authority['migration_set'],
            'mismatches' => $mismatches,
            'unresolved_placeholder_count' => count($this->authority['unresolved_placeholders'] ?? []),
            'auto_repair' => false,
            'read_only' => true,
            'duration_ms' => (int)round((hrtime(true) - $started) / 1_000_000),
            'dependencies' => $this->dependencyStatus($mode, $mismatches),
        ];
    }

    private function comparePrestate(): array
    {
        $a = $this->authority['prestate'];
        $m = [];
        foreach ($a['required_tables'] as $table) {
            if (!$this->tableExists($table)) $m[] = $this->issue('MISSING_TABLE', $table);
        }
        foreach (($a['required_table_collations'] ?? []) as $table => $collation) {
            if ($this->tableExists($table) && $this->norm((string)($this->tableRow($table)['collation'] ?? '')) !== $this->norm($collation)) {
                $m[] = $this->issue('COLLATION_MISMATCH', $table, $collation, $this->tableRow($table)['collation'] ?? null);
            }
        }
        foreach ($a['required_columns'] as $table => $columns) {
            if (!$this->tableExists($table)) continue;
            $actual = $this->columns($table);
            foreach ($columns as $name => $expected) {
                if (!isset($actual[$name])) { $m[] = $this->issue('MISSING_COLUMN', "$table.$name"); continue; }
                foreach (['type', 'nullable'] as $field) {
                    if ($this->norm((string)$actual[$name][$field]) !== $this->norm((string)$expected[$field])) {
                        $m[] = $this->issue($field === 'type' ? 'COLUMN_TYPE_MISMATCH' : 'NULLABILITY_MISMATCH', "$table.$name", $expected[$field], $actual[$name][$field]);
                    }
                }
            }
        }
        foreach ($a['forbidden_tables'] as $table) if ($this->tableExists($table)) $m[] = $this->issue('PARTIAL_MIGRATION_STATE', $table, 'ABSENT', 'PRESENT');
        foreach ($a['forbidden_columns'] as $table => $columns) {
            $actual = $this->columns($table);
            foreach ($columns as $name) if (isset($actual[$name])) $m[] = $this->issue('UNEXPECTED_COLUMN_PRESENT', "$table.$name");
        }
        foreach (($a['required_indexes'] ?? []) as $table => $expected) {
            $actual = $this->indexes($table);
            foreach ($expected as $name => $shape) {
                if (!isset($actual[$name])) { $m[] = $this->issue('INDEX_MISSING', "$table.$name"); continue; }
                foreach ($shape as $key => $value) if ($this->norm((string)($actual[$name][$key] ?? '')) !== $this->norm((string)$value)) {
                    $m[] = $this->issue('INDEX_DEFINITION_MISMATCH', "$table.$name.$key", $value, $actual[$name][$key] ?? null);
                }
            }
        }
        foreach (($a['forbidden_named_objects'] ?? []) as $kind => $byTable) {
            foreach ($byTable as $table => $names) {
                $actual = match ($kind) {'indexes'=>$this->indexes($table),'foreign_keys'=>$this->foreignKeys($table),'checks'=>$this->checks($table),default=>[]};
                foreach ($names as $name) if (isset($actual[$name])) $m[] = $this->issue('PARTIAL_MIGRATION_STATE', "$table.$name", 'ABSENT', 'PRESENT');
            }
        }
        $triggers = $this->namedRows('TRIGGERS', 'TRIGGER_NAME', $a['forbidden_triggers']);
        foreach ($triggers as $name => $_) $m[] = $this->issue('PARTIAL_MIGRATION_STATE', "trigger:$name", 'ABSENT', 'PRESENT');
        $routines = $this->namedRows('ROUTINES', 'ROUTINE_NAME', $a['forbidden_routines']);
        foreach ($routines as $name => $_) $m[] = $this->issue('PARTIAL_MIGRATION_STATE', "routine:$name", 'ABSENT', 'PRESENT');
        if ($this->tableExists('clinical_encounters')) {
            $checks = [
                'legacy_status_domain' => "SELECT COUNT(*) FROM `{$this->schema}`.clinical_encounters WHERE BINARY status NOT IN (BINARY 'open',BINARY 'closed',BINARY 'voided')",
                'duplicate_attributed_open' => "SELECT COUNT(*) FROM (SELECT doctor_id,patient_id FROM `{$this->schema}`.clinical_encounters WHERE doctor_id IS NOT NULL AND BINARY status=BINARY 'open' GROUP BY doctor_id,patient_id HAVING COUNT(*)>1) x",
                'attributed_lifecycle_consistency' => "SELECT COUNT(*) FROM `{$this->schema}`.clinical_encounters WHERE doctor_id IS NOT NULL AND ((BINARY status=BINARY 'open' AND (closed_at IS NOT NULL OR closed_by_user_id IS NOT NULL)) OR (BINARY status=BINARY 'closed' AND (closed_at IS NULL OR closed_by_user_id IS NULL)))",
            ];
            foreach ($checks as $name => $sql) {
                $count = (int)$this->pdo->query($sql)->fetchColumn();
                if ($count !== 0) $m[] = $this->issue('DATA_PRECONDITION_FAILED', $name, 0, $count);
            }
        }
        return $m;
    }

    private function comparePoststate(): array
    {
        $m = [];
        foreach ($this->authority['poststate']['objects'] as $table => $expected) {
            if (!$this->tableExists($table)) { $m[] = $this->issue('MISSING_TABLE', $table); continue; }
            if (isset($expected['table'])) {
                $tableRow = $this->tableRow($table);
                foreach ($expected['table'] as $key => $value) if ($this->norm((string)($tableRow[$key] ?? '')) !== $this->norm((string)$value)) {
                    $m[] = $this->issue($key === 'collation' ? 'COLLATION_MISMATCH' : 'TABLE_DEFINITION_MISMATCH', "$table.$key", $value, $tableRow[$key] ?? null);
                }
            }
            foreach (['columns','indexes','foreign_keys','checks'] as $kind) {
                $actual = match ($kind) {
                    'columns' => array_values($this->columns($table)),
                    'indexes' => array_values($this->indexes($table)),
                    'foreign_keys' => array_values($this->foreignKeys($table)),
                    'checks' => array_values($this->checks($table)),
                };
                $this->compareNamedList($m, $table, $kind, $expected[$kind] ?? [], $actual, isset($expected['table']));
            }
        }
        $this->compareNamedList($m, 'schema', 'triggers', $this->authority['poststate']['triggers'], array_values($this->triggers()), true);
        $routines = $this->namedRows('ROUTINES', 'ROUTINE_NAME', $this->authority['poststate']['forbidden_routines']);
        foreach ($routines as $name => $_) $m[] = $this->issue('ROUTINE_MISMATCH', $name, 'ABSENT_AFTER_MIGRATION', 'PRESENT');
        $this->validateMigration05Catalog($m);
        return $m;
    }

    private function compareNamedList(array &$m, string $table, string $kind, array $expected, array $actual, bool $exact = false): void
    {
        $emap = []; foreach ($expected as $row) $emap[$row['name']] = $this->normalizedRow($row);
        $amap = []; foreach ($actual as $row) $amap[$row['name']] = $this->normalizedRow($row);
        $missingCode = match ($kind) {'columns'=>'MISSING_COLUMN','indexes'=>'INDEX_MISSING','foreign_keys'=>'FOREIGN_KEY_MISSING','checks'=>'CONSTRAINT_MISSING','triggers'=>'TRIGGER_MISSING',default=>'MISSING_OBJECT'};
        $driftCode = match ($kind) {'columns'=>'COLUMN_DEFINITION_MISMATCH','indexes'=>'INDEX_DEFINITION_MISMATCH','foreign_keys'=>'FOREIGN_KEY_DEFINITION_MISMATCH','checks'=>'CONSTRAINT_DEFINITION_MISMATCH','triggers'=>'TRIGGER_DEFINITION_MISMATCH',default=>'OBJECT_DEFINITION_MISMATCH'};
        foreach ($emap as $name => $row) {
            if (!isset($amap[$name])) { $m[] = $this->issue($missingCode, "$table.$name"); continue; }
            if ($row !== $amap[$name]) $m[] = $this->issue($driftCode, "$table.$name", $row, $amap[$name]);
        }
        if ($exact) foreach (array_diff(array_keys($amap), array_keys($emap)) as $name) {
            $m[] = $this->issue('UNEXPECTED_OBJECT_PRESENT', "$table.$name", 'ABSENT', $amap[$name]);
        }
    }

    private function validateMigration05Catalog(array &$m): void
    {
        require_once __DIR__ . '/clinical_multipart_storage_schema.php';
        $catalog = clinical_multipart_storage_required_schema();
        foreach ($catalog as $table => $shape) {
            $expected = $this->authority['poststate']['objects'][$table] ?? null;
            if (!is_array($expected)) { $m[] = $this->issue('MIGRATION05_AUTHORITY_MISSING', $table); continue; }
            foreach (array_keys($shape['columns']) as $column) {
                if (!in_array($column, array_column($expected['columns'], 'name'), true)) $m[] = $this->issue('MIGRATION05_AUTHORITY_MISMATCH', "$table.$column");
            }
        }
    }

    private function dependencyStatus(string $mode, array $mismatches): array
    {
        $ready = $mode === 'poststate' && $mismatches === [];
        return ['C04'=>$ready?'PASS':'NOT_READY','C05'=>$ready?'PASS':'NOT_READY','C21'=>$ready?'PASS':'NOT_READY'];
    }

    private function tableExists(string $table): bool
    {
        $s=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND TABLE_TYPE=\'BASE TABLE\'');$s->execute([$this->schema,$table]);return (int)$s->fetchColumn()===1;
    }
    private function tableRow(string $table): array
    {
        $s=$this->pdo->prepare('SELECT ENGINE engine,TABLE_COLLATION collation FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');$s->execute([$this->schema,$table]);return $s->fetch(PDO::FETCH_ASSOC)?:[];
    }
    private function columns(string $table): array
    {
        $s=$this->pdo->prepare("SELECT COLUMN_NAME name,COLUMN_TYPE type,IS_NULLABLE nullable,IFNULL(COLUMN_DEFAULT,'<NULL>') `default`,EXTRA extra,IFNULL(CHARACTER_SET_NAME,'<NULL>') charset,IFNULL(COLLATION_NAME,'<NULL>') collation,IFNULL(GENERATION_EXPRESSION,'') generation FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION");$s->execute([$this->schema,$table]);$out=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r)$out[$r['name']]=$r;return $out;
    }
    private function indexes(string $table): array
    {
        $s=$this->pdo->prepare('SELECT INDEX_NAME name,CAST(NON_UNIQUE AS CHAR) non_unique,INDEX_TYPE type,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? GROUP BY INDEX_NAME,NON_UNIQUE,INDEX_TYPE ORDER BY INDEX_NAME');$s->execute([$this->schema,$table]);$o=[];foreach($s->fetchAll(PDO::FETCH_ASSOC)as$r)$o[$r['name']]=$r;return$o;
    }
    private function foreignKeys(string $table): array
    {
        $sql='SELECT k.CONSTRAINT_NAME name,GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION) columns,k.REFERENCED_TABLE_NAME referenced_table,GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION) referenced_columns,r.UPDATE_RULE `update`,r.DELETE_RULE `delete` FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA=? AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL GROUP BY k.CONSTRAINT_NAME,k.REFERENCED_TABLE_NAME,r.UPDATE_RULE,r.DELETE_RULE ORDER BY k.CONSTRAINT_NAME';$s=$this->pdo->prepare($sql);$s->execute([$this->schema,$table]);$o=[];foreach($s->fetchAll(PDO::FETCH_ASSOC)as$r)$o[$r['name']]=$r;return$o;
    }
    private function checks(string $table): array
    {
        $sql="SELECT tc.CONSTRAINT_NAME name,cc.CHECK_CLAUSE clause FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=? AND tc.TABLE_NAME=? AND tc.CONSTRAINT_TYPE='CHECK' ORDER BY tc.CONSTRAINT_NAME";$s=$this->pdo->prepare($sql);$s->execute([$this->schema,$table]);$o=[];foreach($s->fetchAll(PDO::FETCH_ASSOC)as$r)$o[$r['name']]=$r;return$o;
    }
    private function triggers(): array
    {
        $names=array_column($this->authority['poststate']['triggers'],'name'); if($names===[])return[];$p=implode(',',array_fill(0,count($names),'?'));$s=$this->pdo->prepare("SELECT TRIGGER_NAME name,EVENT_OBJECT_TABLE `table`,ACTION_TIMING timing,EVENT_MANIPULATION event,ACTION_STATEMENT statement FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=? AND TRIGGER_NAME IN ($p) ORDER BY TRIGGER_NAME");$s->execute(array_merge([$this->schema],$names));$o=[];foreach($s->fetchAll(PDO::FETCH_ASSOC)as$r)$o[$r['name']]=$r;return$o;
    }
    private function namedRows(string $source, string $nameColumn, array $names): array
    {
        if($names===[])return[];$p=implode(',',array_fill(0,count($names),'?'));$schemaColumn=$source==='ROUTINES'?'ROUTINE_SCHEMA':'TRIGGER_SCHEMA';$s=$this->pdo->prepare("SELECT $nameColumn name FROM information_schema.$source WHERE $schemaColumn=? AND $nameColumn IN ($p)");$s->execute(array_merge([$this->schema],$names));$o=[];foreach($s->fetchAll(PDO::FETCH_ASSOC)as$r)$o[$r['name']]=$r;return$o;
    }
    private function normalizedRow(array $row): array {foreach($row as $k=>$v)$row[$k]=$this->norm((string)$v);ksort($row);return$row;}
    private function norm(string $v): string {return preg_replace('/\s+/u',' ',strtolower(trim(str_replace('`','',$v))))??'';}
    private function issue(string $code,string $object,mixed $expected=null,mixed $actual=null): array {return array_filter(['code'=>$code,'object'=>$object,'expected'=>$expected,'actual'=>$actual],fn($v)=>$v!==null);}
}
