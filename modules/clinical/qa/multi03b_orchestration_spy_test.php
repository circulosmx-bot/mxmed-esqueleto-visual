<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/api/_lib/clinical_multipart_document_service.php';

// No driver, DSN, parent constructor, socket or database. SQL responses are in-memory fixtures.
final class Multi03bPdoSpy extends PDO
{
    public bool $transaction = false;
    public array $rows = [];
    public array $ledger = [];
    public array $snapshot = [];
    public array $events = [];
    public int $commits = 0;
    public string $fail = '';
    public $atCommit = null;
    public $atManifest = null;
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return PDO::ERRMODE_EXCEPTION; }
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool {
        if ($this->transaction) throw new RuntimeException('nested transaction');
        $this->snapshot=[$this->rows,$this->ledger]; $this->transaction=true; $this->events[]='begin'; return true;
    }
    public function commit(): bool {
        $this->commits++;
        if ($this->atCommit) ($this->atCommit)($this);
        if ($this->commits===2 && in_array($this->fail,['commit','ambiguous'],true)) {
            if ($this->fail==='ambiguous') $this->transaction=false;
            throw new RuntimeException('INJECTED_COMMIT_FAILURE');
        }
        $this->transaction=false; $this->events[]='commit'; return true;
    }
    public function rollBack(): bool {
        [$this->rows,$this->ledger]=$this->snapshot; $this->transaction=false; $this->events[]='rollback'; return true;
    }
    public function lastInsertId(?string $name=null): string|false { return '11'; }
    public function prepare(string $query, array $options=[]): PDOStatement|false { return new Multi03bStatementSpy($this,$query); }
}
final class Multi03bStatementSpy extends PDOStatement
{
    private array $params=[];
    private int $affected=1;
    public function __construct(private Multi03bPdoSpy $db, private string $sql) {}
    public function execute(?array $params=null): bool {
        $this->params=$params??[];
        $p=$this->params; $db=$this->db; $sql=$this->sql;
        if (str_contains($sql,'information_schema.')) return true;
        if (str_contains($sql,'INSERT INTO clinical_binary_uploads')) {
            if (!$db->transaction) throw new RuntimeException('staged insert outside transaction');
            $db->rows[$p['upload_id']]=$p+['state'=>'STAGED','bound'=>false]; $db->events[]='staged';
        } elseif (str_contains($sql,'INSERT INTO clinical_idempotency_requests')) {
            $db->events[]='claim';
            if ($db->ledger) {
                $e=new PDOException('duplicate',23000); $e->errorInfo=['23000',1062,'duplicate']; throw $e;
            }
            $db->ledger=['request_hash'=>$p[':request_hash'],'committed_at'=>null];
        } elseif (str_contains($sql,'SET idempotency_request_id=')) {
            $db->rows[$p[1]]['bound']=true; $db->events[]='bind';
        } elseif (str_contains($sql,'INSERT INTO clinical_document_binaries')) {
            if ($db->atManifest) ($db->atManifest)($p);
            $db->events[]='manifest';
            if ($db->fail==='recovery') throw new PDOException('synthetic private database diagnostic');
            if ($db->fail==='manifest') throw new RuntimeException('INJECTED_MANIFEST_FAILURE');
        } elseif (str_contains($sql,"SET storage_state='FINALIZED'")) {
            $db->rows[$p[1]]['state']='FINALIZED'; $db->events[]='finalized';
        } elseif (str_contains($sql,'UPDATE clinical_idempotency_requests')) {
            $db->events[]='complete';
            if ($db->fail==='complete') throw new RuntimeException('INJECTED_COMPLETE_FAILURE');
            $column=str_contains($sql,'`document_revision_id`')?'document_revision_id':'document_id';
            $db->ledger[$column]=$p[':resource_id']; $db->ledger['committed_at']='2026-09-19 12:00:00';
        } elseif (str_contains($sql,'SELECT * FROM clinical_idempotency_requests')) {
            $db->events[]='replay';
        } elseif (str_contains($sql,'SET storage_state=?')) {
            if ($db->fail==='recovery') throw new PDOException('synthetic recovery unavailable');
            if (isset($db->rows[$p[2]])) $db->rows[$p[2]]['state']=$p[0]; else $this->affected=0;
            $db->events[]='recovery';
        } elseif (str_contains($sql,'DELETE FROM clinical_binary_uploads')) {
            $row=$db->rows[$p[0]]??null;
            if (!$row || $row['state']!=='STAGED' || $row['bound']) throw new RuntimeException('unsafe coordination delete');
            unset($db->rows[$p[0]]); $db->events[]='redundant-delete';
        } else throw new RuntimeException('Unexpected SQL');
        return true;
    }
    public function rowCount(): int { return $this->affected; }
    public function fetchColumn(int $column=0): mixed {
        if (str_contains($this->sql,'information_schema.TABLES')) return 1;
        $catalog=clinical_multipart_storage_required_schema();
        return implode(' ', $catalog[$this->params[0]]['checks'][$this->params[1]]);
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array {
        $shape=clinical_multipart_storage_required_schema()[$this->params[0]];
        $rows=[];
        if (str_contains($this->sql,'information_schema.COLUMNS')) {
            foreach ($shape['columns'] as $name=>$c) $rows[]=['COLUMN_NAME'=>$name,'COLUMN_TYPE'=>$c['type'],'IS_NULLABLE'=>$c['nullable'],'EXTRA'=>$c['extra']];
        } else {
            foreach ($shape['indexes'] as $name=>$i) foreach ($i['columns'] as $c) $rows[]=['INDEX_NAME'=>$name,'NON_UNIQUE'=>$i['unique']?0:1,'COLUMN_NAME'=>$c];
        }
        return $rows;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed {
        if (str_contains($this->sql,'clinical_idempotency_requests')) return $this->db->ledger;
        $s=clinical_multipart_storage_required_schema()[$this->params[0]]['foreign_keys'][$this->params[1]];
        return ['COLUMN_NAME'=>$s['column'],'REFERENCED_TABLE_NAME'=>$s['table'],'REFERENCED_COLUMN_NAME'=>$s['referenced_column'],'UPDATE_RULE'=>$s['update'],'DELETE_RULE'=>$s['delete']];
    }
}
function spy_check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function spy_remove(string $path): void {
    if (is_link($path)||is_file($path)) { unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (new FilesystemIterator($path) as $entry) spy_remove($entry->getPathname());
    rmdir($path);
}
$root=sys_get_temp_dir().'/mxmed_multi03b_'.bin2hex(random_bytes(8));
mkdir($root,0700);
$fixture=$root.'/fixture.pdf';
file_put_contents($fixture,"%PDF-1.4\nSynthetic fixture\n%%EOF\n");
$context=['operation'=>'CREATE_ENCOUNTER_DOCUMENT','doctor_id'=>'doctor-qa','patient_id'=>'patient-qa','context_type'=>'ENCOUNTER','context_id'=>'42','document_type'=>'result','metadata'=>[]];
$expires=new DateTimeImmutable('+1 hour');
$scenarios=0;
try {
    foreach (['success','uuid','callback','manifest','complete','commit','ambiguous','cleanup','replacement','recovery'] as $scenario) {
        $db=new Multi03bPdoSpy(); $db->fail=$scenario;
        $private=$root.'/'.$scenario;
        $storage=new ClinicalPrivateBinaryStorage($private,dirname(__DIR__,3));
        $service=new ClinicalMultipartDocumentService($db,$storage);
        $db->atManifest=static function () use ($storage): void {
            $keys=array_column($storage->inventory(true),'key');
            spy_check(count(array_filter($keys,fn($k)=>str_starts_with($k,'clinical/')))===1,'final must precede manifest');
            spy_check(count(array_filter($keys,fn($k)=>str_starts_with($k,'staging/')))===1,'staging must precede commit');
        };
        $db->atCommit=static function ($db) use ($storage,$scenario,$private): void {
            if ($db->commits!==2) return;
            spy_check(count(array_filter($storage->inventory(),fn($i)=>str_starts_with($i['key'],'staging/')))===1,'staging deleted before commit');
            if ($scenario==='cleanup') {
                $key=array_values($db->rows)[0]['staging_key'];
                rename($private.'/'.$key,$private.'/'.$key.'.retained');
                mkdir($private.'/'.$key,0700); // Deterministic fixture-only cleanup failure.
            }
        };
        $create=static function ($pdo,$uuid) use ($scenario): array {
            spy_check(in_array('claim',$pdo->events,true),'callback before claim');
            $pdo->events[]='callback';
            if ($scenario==='callback') throw new RuntimeException('INJECTED_CALLBACK_FAILURE');
            return ['document_id'=>21,'document_uuid'=>$scenario==='uuid'?'mismatch':$uuid,
                'result_column'=>$scenario==='replacement'?'document_revision_id':'document_id','result_id'=>$scenario==='replacement'?31:21];
        };
        $fetch=static fn($pdo,$column,$id): array => ['id'=>$id,'kind'=>$column];
        $c=$scenario==='replacement'?array_replace($context,['operation'=>'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT']):$context;
        $success=in_array($scenario,['success','cleanup','replacement'],true);
        try {
            $result=$service->execute($c,'qa-key','actor',$fixture,null,$expires,$create,$fetch);
            spy_check($success,'failure returned clinical success');
            spy_check($result['cleanup_pending']===($scenario==='cleanup'),'cleanup status');
        } catch (Throwable $error) {
            if ($success) throw $error;
            spy_check(str_starts_with($error->getMessage(),'INJECTED_')||in_array($error->getMessage(),['MULTIPART_DOCUMENT_UUID_MISMATCH','MULTIPART_DATABASE_COORDINATION_FAILED'],true),'unexpected failure: '.$error->getMessage());
        }
        $inventory=$storage->inventory();
        $countNamespace=static fn($prefix): int => count(array_filter($inventory,fn($i)=>str_starts_with($i['key'],$prefix.'/')));
        if (in_array($scenario,['manifest','complete','commit'],true)) {
            spy_check($countNamespace('clinical')===0 && $countNamespace('quarantine')===1 && $countNamespace('staging')===1,'F5 compensation');
            spy_check(array_values($db->rows)[0]['state']==='ORPHANED','F5 coordination state');
        }
        if ($scenario==='recovery') {
            spy_check($countNamespace('clinical')===0 && $countNamespace('quarantine')===1 && $countNamespace('staging')===1,'recovery DB failure preserves bytes');
            spy_check(array_values($db->rows)[0]['state']==='STAGED','failed recovery must not claim state update');
        }
        if ($scenario==='ambiguous') spy_check($countNamespace('clinical')===1 && $countNamespace('staging')===1,'ambiguous commit must preserve bytes');
        if (in_array($scenario,['uuid','callback'],true)) spy_check($countNamespace('clinical')===0 && $countNamespace('staging')===1,'pre-final failure');
        if ($scenario==='success') {
            spy_check($countNamespace('clinical')===1 && $countNamespace('staging')===0,'post-commit cleanup');
            $beforeFinal=$storage->inventory(true);
            $replay=$service->execute($c,'qa-key','actor',$fixture,null,$expires,$create,$fetch);
            spy_check($replay['_idempotency_replay']===true && $replay['id']===21,'lost-response replay');
            spy_check($storage->inventory(true)===$beforeFinal,'replay created binary');
            spy_check(count(array_filter($db->events,fn($e)=>$e==='callback'))===1,'replay created resource');
            spy_check(count(array_filter($db->events,fn($e)=>$e==='manifest'))===1,'replay created manifest');
            $changed=$root.'/changed.pdf'; file_put_contents($changed,"%PDF-1.4\nChanged fixture\n%%EOF\n");
            try {
                $service->execute($c,'qa-key','actor',$changed,null,$expires,$create,$fetch);
                throw new RuntimeException('changed binary accepted');
            } catch (ClinicalIdempotencyException $e) { spy_check($e->errorCode==='IDEMPOTENCY_KEY_REUSED','wrong conflict'); }
            spy_check($storage->inventory(true)===$beforeFinal,'conflict left redundant bytes');
            $scenarios+=2;
        }
        $scenarios++;
    }
    echo "MULTI03B_ORCHESTRATION_SPY_QA=PASS\nSCENARIOS=$scenarios\nANY_DATABASE_CONNECTED=false\nPHYSICAL_DB_QA_EXECUTED=false\n";
} finally { spy_remove($root); }
spy_check(!file_exists($root),'temporary root remains');
echo "MULTI03B_RESIDUAL_TEMP_ROOT_COUNT=0\n";
