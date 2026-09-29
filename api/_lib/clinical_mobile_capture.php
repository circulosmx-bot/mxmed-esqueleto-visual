<?php
declare(strict_types=1);
require_once __DIR__ . '/clinical_encounter_multipart_adapter.php';
require_once __DIR__ . '/clinical_capture_classification.php';
require_once __DIR__ . '/clinical_capture_upload_errors.php';

/** Existing clinical activity media categories; study results retain their order writer. */
function clinical_mobile_capture_catalog(): array
{
    $labels = ['documento_externo'=>'Documento externo del paciente', 'evidencia_clinica'=>'Evidencia clínica',
        'receta_previa'=>'Receta o tratamiento previo', 'consentimiento_formato'=>'Consentimiento o formato',
        'bitacora_hospitalaria'=>'Bitácora/hoja hospitalaria', 'otro'=>'Otro'];
    $items=[];
    foreach ($labels as $key=>$label) $items[$key]=['id'=>$key,'label'=>$label,'icon'=>'description','document_type'=>'image'];
    return $items;
}

/** All mutations lock session BEFORE page/token and encounter; never acquire legacy token mutex. */
final class ClinicalMobileCapture
{
    private ClinicalPrivateBinaryStorage $storage;
    public function __construct(private PDO $pdo)
    {
        [$root] = clinical_encounter_multipart_config();
        $this->storage = new ClinicalPrivateBinaryStorage($root);
    }
    private function query(string $sql, array $params=[]): PDOStatement
    {
        $q=$this->pdo->prepare($sql);$q->execute($params);return $q;
    }
    public function session(string $uuid, bool $lock=false): array
    {
        $row=$this->query('SELECT * FROM clinical_mobile_capture_sessions WHERE session_uuid=?'.($lock?' FOR UPDATE':''),[$uuid])->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('CAPTURE_NOT_FOUND',404);
        return $row;
    }
    public function fromToken(string $token): ?array
    {
        $row=$this->query('SELECT s.session_uuid FROM clinical_note_capture_tokens t JOIN clinical_mobile_capture_pages p ON p.token_id=t.id JOIN clinical_mobile_capture_sessions s ON s.id=p.capture_session_id WHERE t.token=?',[$token])->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }
    private function pages(array $s, bool $all=false): array
    {
        return $this->query('SELECT * FROM clinical_mobile_capture_pages WHERE capture_session_id=?'.($all?'':" AND status<>'REMOVED'").' ORDER BY page_order,id',[$s['id']])->fetchAll(PDO::FETCH_ASSOC);
    }
    public function authorize(array $s, ?array $doctor, string $bearer): void
    {
        if ($bearer!=='') {
            if (!preg_match('/^[a-f0-9]{64}$/D',$bearer) || !$s['continuation_hash'] || !hash_equals($s['continuation_hash'],hash('sha256',$bearer))) throw new RuntimeException('CAPTURE_FORBIDDEN',403);
            if($s['status']==='COMPLETED' && $s['expires_at']<=gmdate('Y-m-d H:i:s'))throw new RuntimeException('CAPTURE_EXPIRED',410);
            return;
        }
        if (!$doctor) throw new RuntimeException('CAPTURE_UNAUTHENTICATED',401);
        $e=clinical_note_capture_encounter_authority($this->pdo,$s);
        if ((string)$e['doctor_id']!==(string)$doctor['doctor_id']) throw new RuntimeException('CAPTURE_FORBIDDEN',403);
    }
    private function openEncounter(array $s): array
    {
        $e=clinical_note_capture_encounter_authority($this->pdo,$s);
        $e=$this->query('SELECT * FROM clinical_encounters WHERE encounter_id=? FOR UPDATE',[$e['encounter_id']])->fetch(PDO::FETCH_ASSOC);
        if (!$e || $e['status']!=='open' || !clinical_note_capture_encounter_uses_v1($e)) throw new RuntimeException('ENCOUNTER_TERMINAL',409);
        return $e;
    }
    private function assertOpen(array $s): void
    {
        if ($s['status']!=='OPEN') throw new RuntimeException('CAPTURE_'.$s['status'], $s['status']==='EXPIRED'?410:409);
    }
    /** Opportunistic expiry and post-commit cleanup also recover interrupted cleanup. */
    public function expire(string $uuid): void
    {
        $this->pdo->beginTransaction();
        try {
            $s=$this->session($uuid,true);
            if ($s['status']==='OPEN' && $s['expires_at']<=gmdate('Y-m-d H:i:s')) {
                $this->closeLocked($s,'EXPIRED');
            }
            $this->pdo->commit();
        } catch (Throwable $e) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        $this->cleanup($this->session($uuid));
    }
    private function closeLocked(array $s,string $status): void
    {
        $this->query("UPDATE clinical_mobile_capture_sessions SET status=?,continuation_hash=NULL,cancelled_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?",[$status,$s['id']]);
        $this->query("UPDATE clinical_note_capture_tokens t JOIN clinical_mobile_capture_pages p ON p.token_id=t.id SET t.status='cancelled',t.cancelled_at=UTC_TIMESTAMP(),t.updated_at=UTC_TIMESTAMP() WHERE p.capture_session_id=? AND t.status='pending'",[$s['id']]);
        $this->query("UPDATE clinical_mobile_capture_pages SET status='REMOVED',page_order=NULL,removed_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE capture_session_id=?",[$s['id']]);
    }
    public function cleanup(array $s): void
    {
        foreach($this->pages($s,true) as $p) {
            if($p['status']!=='REMOVED' && $s['status']!=='COMPLETED')continue;
            foreach(['optimized_manifest_json','thumbnail_manifest_json'] as $key){
                $m=json_decode((string)$p[$key],true);
                if(!empty($m['staging_key']))try{$this->storage->deleteUncommitted($m['staging_key']);}catch(Throwable){}
            }
        }
    }
    public function create(array $doctor,array $input): array
    {
        foreach(['patient_id','encounter_key','classification_key'] as $field)if(!is_string($input[$field]??null))throw new RuntimeException('CAPTURE_FIELDS_REQUIRED',400);
        if(isset($input['title'])&&!is_string($input['title']))throw new RuntimeException('CAPTURE_TITLE_INVALID',400);
        $item=clinical_mobile_capture_catalog()[$input['classification_key']??'']??null;
        if(!$item)throw new RuntimeException('CAPTURE_CLASSIFICATION_REQUIRED',400);
        $title=trim((string)($input['title']??''));
        if(mb_strlen($title)>160)throw new RuntimeException('CAPTURE_TITLE_TOO_LONG',400);
        $s=['patient_id'=>(string)($input['patient_id']??''),'encounter_key'=>(string)($input['encounter_key']??'')];
        $e=clinical_note_capture_encounter_authority($this->pdo,$s);
        if((string)$e['doctor_id']!==(string)$doctor['doctor_id'])throw new RuntimeException('CAPTURE_FORBIDDEN',403);
        $uuid=ClinicalPrivateBinaryStorage::uuidV4();
        $this->pdo->beginTransaction();
        try {
            $this->openEncounter($s);
            $this->query("INSERT INTO clinical_mobile_capture_sessions (session_uuid,patient_id,encounter_key,classification_key,classification_label_snapshot,title,status,expires_at,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'OPEN',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),?,UTC_TIMESTAMP(),UTC_TIMESTAMP())",
                [$uuid,$s['patient_id'],$s['encounter_key'],$item['id'],$item['label'],$title!==''?$title:$item['label'].' · '.gmdate('Y-m-d'),$doctor['user_id']]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        return $this->describe($this->session($uuid));
    }
    public function describe(array $s): array
    {
        $pages=[];
        foreach($this->pages($s) as $p){
            if($p['status']!=='READY')continue;
            $pages[]=['page_uuid'=>$p['page_uuid'],'page_number'=>count($pages)+1,'status'=>'READY',
                'thumbnail_url'=>'/api/clinical/index.php/mobile-capture-sessions/'.$s['session_uuid'].'/thumbnail/'.$p['page_uuid']];
        }
        return ['session_uuid'=>$s['session_uuid'],'status'=>$s['status'],'title'=>$s['title'],
            'classification'=>['id'=>$s['classification_key'],'label'=>$s['classification_label_snapshot']],
            'expires_at'=>clinical_note_capture_datetime_to_iso($s['expires_at']),'pages'=>$pages,'page_count'=>count($pages),
            'media_bundle_id'=>$s['media_bundle_id']];
    }
    public function issueLocked(array $s): array
    {
        $this->assertOpen($s);$this->openEncounter($s);
        // Expired pending pages are retired; distinct active tokens may upload concurrently.
        foreach($this->pages($s) as $p)if($p['status']==='PENDING'){
            $expires=$this->query('SELECT expires_at FROM clinical_note_capture_tokens WHERE id=?',[$p['token_id']])->fetchColumn();
            if($expires<=gmdate('Y-m-d H:i:s'))$this->removeLocked($s,$p['page_uuid']);
        }
        $count=(int)$this->query("SELECT COUNT(*) FROM clinical_mobile_capture_pages WHERE capture_session_id=? AND status<>'REMOVED'",[$s['id']])->fetchColumn();
        if($count>=30)throw new RuntimeException('CAPTURE_PAGE_LIMIT',409);
        $token=clinical_note_capture_token_generate();$page=ClinicalPrivateBinaryStorage::uuidV4();
        $expires=min(strtotime($s['expires_at'].' UTC'),time()+900);
        $this->query("INSERT INTO clinical_note_capture_tokens (token,patient_id,encounter_key,note_context,status,expires_at,created_at,updated_at) VALUES (?,?,?,'mobile_capture_r43a','pending',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())",[$token,$s['patient_id'],$s['encounter_key'],gmdate('Y-m-d H:i:s',$expires)]);
        $tokenId=(int)$this->pdo->lastInsertId();
        $this->query("INSERT INTO clinical_mobile_capture_pages (page_uuid,capture_session_id,token_id,page_order,status,created_at,updated_at) VALUES (?,?,?,?,'PENDING',UTC_TIMESTAMP(),UTC_TIMESTAMP())",[$page,$s['id'],$tokenId,$count+1]);
        $url=clinical_capture_mobile_url('/public/note-capture.html?token='.rawurlencode($token).'&mode=multipage');
        return ['token'=>$token,'page_uuid'=>$page,'page_number'=>$count+1,'mobile_url'=>$url,'qr_value'=>$url,'expires_at'=>gmdate('c',$expires)];
    }
    private function reorderLocked(array $s,array $ids): void
    {
        if(!array_is_list($ids) || array_filter($ids,fn($id)=>!is_string($id)))throw new RuntimeException('CAPTURE_PAGE_SET_MISMATCH',400);
        $ready=array_values(array_filter($this->pages($s),fn($p)=>$p['status']==='READY'));
        $actual=array_column($ready,'page_uuid');$sorted=$ids;sort($actual);sort($sorted);
        if($actual!==$sorted || count(array_unique($ids))!==count($ids))throw new RuntimeException('CAPTURE_PAGE_SET_MISMATCH',400);
        // Positive temporary orders avoid collisions under the unique(session,page_order).
        foreach($this->pages($s) as $p)if($p['status']==='PENDING')$ids[]=$p['page_uuid'];
        $this->query("UPDATE clinical_mobile_capture_pages SET page_order=page_order+1000 WHERE capture_session_id=? AND status<>'REMOVED'",[$s['id']]);
        foreach($ids as $i=>$id)$this->query('UPDATE clinical_mobile_capture_pages SET page_order=?,updated_at=UTC_TIMESTAMP() WHERE capture_session_id=? AND page_uuid=?',[$i+1,$s['id'],$id]);
    }
    private function removeLocked(array $s,string $uuid): void
    {
        $p=$this->query("SELECT * FROM clinical_mobile_capture_pages WHERE capture_session_id=? AND page_uuid=? AND status<>'REMOVED'",[$s['id'],$uuid])->fetch(PDO::FETCH_ASSOC);
        if(!$p)throw new RuntimeException('CAPTURE_PAGE_NOT_FOUND',404);
        $this->query("UPDATE clinical_mobile_capture_pages SET status='REMOVED',page_order=NULL,removed_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?",[$p['id']]);
        $this->query("UPDATE clinical_note_capture_tokens SET status='cancelled',cancelled_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=? AND status='pending'",[$p['token_id']]);
        $remaining=$this->pages($s);$this->query("UPDATE clinical_mobile_capture_pages SET page_order=page_order+1000 WHERE capture_session_id=? AND status<>'REMOVED'",[$s['id']]);
        foreach($remaining as $i=>$item)$this->query('UPDATE clinical_mobile_capture_pages SET page_order=? WHERE id=?',[$i+1,$item['id']]);
    }
    public function command(string $uuid,?array $doctor,string $bearer,string $action,array $input): array
    {
        $allowed=['reorder'=>['pages'],'remove'=>['page_uuid']][$action]??[];
        if(array_diff(array_keys($input),$allowed))throw new RuntimeException('CAPTURE_METADATA_IMMUTABLE',400);
        $this->authorize($this->session($uuid),$doctor,$bearer);$this->expire($uuid);
        $this->pdo->beginTransaction();$finalKeys=[];$commitAttempted=false;
        try{
            $s=$this->session($uuid,true);$this->authorize($s,$doctor,$bearer);
            if($action==='finalize' && $s['status']==='COMPLETED'){$this->pdo->commit();return $this->describe($s);}
            $this->assertOpen($s);
            if($action==='pages')$result=$this->issueLocked($s);
            elseif($action==='cancel'){
                if(!$doctor || $bearer!=='')throw new RuntimeException('CAPTURE_FORBIDDEN',403);
                $this->closeLocked($s,'CANCELLED');
            }elseif($action==='reorder'){if(!is_array($input['pages']??null))throw new RuntimeException('CAPTURE_PAGE_SET_MISMATCH',400);$this->reorderLocked($s,$input['pages']);}
            elseif($action==='remove')$this->removeLocked($s,(string)($input['page_uuid']??''));
            elseif($action==='finalize')$this->finalizeLocked($s,$finalKeys);
            else throw new RuntimeException('CAPTURE_ACTION_INVALID',404);
            $commitAttempted=true;$this->pdo->commit();
        }catch(Throwable $e){
            $rolled=false;if($this->pdo->inTransaction())$rolled=$this->pdo->rollBack();
            // Never remove possibly committed objects after an ambiguous commit.
            if($rolled&&!$commitAttempted)foreach($finalKeys as $key)try{$this->storage->quarantine($key);}catch(Throwable){}
            throw $e;
        }
        $s=$this->session($uuid);$this->cleanup($s);
        return array_merge($this->describe($s),$result??[]);
    }
    private function finalizeLocked(array $s,array &$finalKeys): void
    {
        $e=$this->openEncounter($s);$pages=array_values(array_filter($this->pages($s),fn($p)=>$p['status']==='READY'));
        if(!$pages)throw new RuntimeException('CAPTURE_PAGES_REQUIRED',409);
        $bundle=ClinicalPrivateBinaryStorage::uuidV4();
        $this->query("UPDATE clinical_mobile_capture_sessions SET status='FINALIZING',updated_at=UTC_TIMESTAMP() WHERE id=?",[$s['id']]);
        foreach($pages as $index=>$p){
            $documentUuid=ClinicalPrivateBinaryStorage::uuidV4();$main=json_decode($p['optimized_manifest_json'],true);$thumb=json_decode($p['thumbnail_manifest_json'],true);
            $payload=['document_type'=>'image','title'=>$s['title'],'summary'=>$s['title'],'payload'=>[
                'media_tag_key'=>$s['classification_key'],'media_tag_label'=>$s['classification_label_snapshot'],
                'media_bundle_id'=>$bundle,'media_bundle_title'=>$s['title'],'media_page_number'=>$index+1,'media_page_count'=>count($pages),
                'capture_session_uuid'=>$s['session_uuid'],'capture_method'=>'mobile','original_audit'=>json_decode($p['original_audit_json'],true)]];
            $id=clinical_v1_document_insert($this->pdo,$e,$payload,$s['created_by_user_id'],$documentUuid);
            foreach(['ORIGINAL'=>$main,'THUMBNAIL'=>$thumb] as $role=>$m){
                $binary=ClinicalPrivateBinaryStorage::uuidV4();$key=$this->storage->buildFinalKey($documentUuid,$binary,$role);
                $this->storage->finalizeCreateOnly($m['staging_key'],$key,$m['sha256'],$m['byte_length']);$finalKeys[]=$key;
                $this->query('INSERT INTO clinical_document_binaries (document_id,binary_uuid,variant_role,variant_version,storage_key,sha256,byte_length,mime_type,width_px,height_px,created_at,finalized_at) VALUES (?,?,?,1,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())',[$id,$binary,$role,$key,$m['sha256'],$m['byte_length'],$m['mime_type'],$m['w'],$m['h']]);
            }
            $this->query("UPDATE clinical_note_capture_tokens SET document_id=?,document_uuid=?,status='consumed',consumed_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?",[$id,$documentUuid,$p['token_id']]);
        }
        foreach($this->pages($s) as $p)if($p['status']==='PENDING')$this->removeLocked($s,$p['page_uuid']);
        $this->query("UPDATE clinical_mobile_capture_sessions SET status='COMPLETED',media_bundle_id=?,finalized_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?",[$bundle,$s['id']]);
    }
    public function tokenContext(string $token): array
    {
        $found=$this->fromToken($token);if(!$found)throw new RuntimeException('CAPTURE_NOT_FOUND',404);
        $this->expire($found['session_uuid']);$s=$this->session($found['session_uuid']);$this->assertOpen($s);
        $t=$this->query('SELECT t.*,p.page_order,p.status AS page_status FROM clinical_note_capture_tokens t JOIN clinical_mobile_capture_pages p ON p.token_id=t.id WHERE t.token=?',[$token])->fetch(PDO::FETCH_ASSOC);
        if($t['status']!=='pending'||$t['page_status']!=='PENDING')throw new RuntimeException('CAPTURE_TOKEN_USED',409);
        if($t['expires_at']<=gmdate('Y-m-d H:i:s'))throw new RuntimeException('CAPTURE_TOKEN_EXPIRED',410);
        return ['session_uuid'=>$s['session_uuid'],'classification'=>['id'=>$s['classification_key'],'label'=>$s['classification_label_snapshot']],
            'page_number'=>(int)$t['page_order'],'status'=>'pending','expires_at'=>clinical_note_capture_datetime_to_iso($t['expires_at'])];
    }
    public function upload(string $token,array $files,array $input): array
    {
        $context=$this->tokenContext($token);
        // Anonymous clients cannot supply domain metadata, including classification/title/scope.
        if($input!==[])throw new RuntimeException('CAPTURE_METADATA_IMMUTABLE',400);
        $failure=clinical_capture_upload_failure($files['file']??null,(int)($_SERVER['CONTENT_LENGTH']??0),(string)ini_get('post_max_size'));
        if($failure)throw new RuntimeException($failure['code'],400);
        $file=clinical_encounter_multipart_file($files);$optimized=null;$staged=[];$committed=false;$commitAttempted=false;
        $this->pdo->beginTransaction();
        try{
            $s=$this->session($context['session_uuid'],true);$this->assertOpen($s);$this->openEncounter($s);
            $p=$this->query('SELECT p.*,t.status AS token_status,t.expires_at FROM clinical_mobile_capture_pages p JOIN clinical_note_capture_tokens t ON t.id=p.token_id WHERE t.token=? AND p.capture_session_id=? FOR UPDATE',[$token,$s['id']])->fetch(PDO::FETCH_ASSOC);
            if(!$p||$p['status']!=='PENDING'||$p['token_status']!=='pending')throw new RuntimeException('CAPTURE_TOKEN_USED',409);
            if($p['expires_at']<=gmdate('Y-m-d H:i:s')||$s['expires_at']<=gmdate('Y-m-d H:i:s'))throw new RuntimeException('CAPTURE_TOKEN_EXPIRED',410);
            $optimized=clinical_optimize_private_image($file);
            foreach(['main'=>'optimized','thumbnail'=>'thumb'] as $key=>$meta){
                $staged[$key]=$this->storage->stageFile($optimized[$key]);
                $staged[$key]['w']=$optimized['manifest'][$meta]['w'];$staged[$key]['h']=$optimized['manifest'][$meta]['h'];
            }
            $previous=trim((string)($_SERVER['HTTP_X_CAPTURE_CONTINUATION']??''));
            $secret=$previous!=='' && $s['continuation_hash'] && hash_equals($s['continuation_hash'],hash('sha256',$previous)) ? $previous : bin2hex(random_bytes(32));
            $this->query("UPDATE clinical_mobile_capture_pages SET status='READY',optimized_manifest_json=?,thumbnail_manifest_json=?,original_audit_json=?,updated_at=UTC_TIMESTAMP() WHERE id=?",[json_encode($staged['main']),json_encode($staged['thumbnail']),json_encode($optimized['manifest']['original']),$p['id']]);
            $this->query("UPDATE clinical_note_capture_tokens SET status='uploaded',uploaded_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?",[$p['token_id']]);
            $this->query('UPDATE clinical_mobile_capture_sessions SET continuation_hash=?,updated_at=UTC_TIMESTAMP() WHERE id=?',[hash('sha256',$secret),$s['id']]);
            $commitAttempted=true;$this->pdo->commit();$committed=true;
        }catch(Throwable $e){
            $rolled=false;if($this->pdo->inTransaction())$rolled=$this->pdo->rollBack();
            if($rolled&&!$commitAttempted)foreach($staged as $m)try{$this->storage->deleteUncommitted($m['staging_key']);}catch(Throwable){}
            throw $e;
        }finally{clinical_clean_private_image($optimized);if(is_uploaded_file($file['tmp_name']))@unlink($file['tmp_name']);}
        return $this->describe($this->session($context['session_uuid']))+['continuation'=>$secret];
    }
    public function thumbnail(array $s,string $page): void
    {
        if(!in_array($s['status'],['OPEN','COMPLETED'],true))throw new RuntimeException('CAPTURE_NOT_AVAILABLE',410);
        $p=$this->query("SELECT * FROM clinical_mobile_capture_pages WHERE capture_session_id=? AND page_uuid=? AND status='READY'",[$s['id'],$page])->fetch(PDO::FETCH_ASSOC);
        if(!$p)throw new RuntimeException('CAPTURE_PAGE_NOT_FOUND',404);
        $m=json_decode($p['thumbnail_manifest_json'],true);$key=$m['staging_key'];
        if($s['status']==='COMPLETED'){
            $key=$this->query("SELECT b.storage_key FROM clinical_document_binaries b JOIN clinical_note_capture_tokens t ON t.document_id=b.document_id WHERE t.id=? AND b.variant_role='THUMBNAIL' AND b.variant_version=1",[$p['token_id']])->fetchColumn();
        }
        $stream=$this->storage->openReadStream($key);header('Content-Type: '.$m['mime_type']);header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');fpassthru($stream);fclose($stream);
    }
}

/** Routes are deliberately small: bearer access never returns patient/encounter identity or raw files. */
function clinical_mobile_capture_route(PDO $pdo,string $method,array $segments): void
{
    header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
    try{
        $service=new ClinicalMobileCapture($pdo);
        if($segments[0]==='note-capture-tokens'){
            if(count($segments)!==3 || !in_array($segments[2],['upload','mobile-context'],true))throw new RuntimeException('CAPTURE_USE_SESSION',409);
            if($method==='GET'&&$segments[2]==='mobile-context')$data=$service->tokenContext($segments[1]);
            elseif($method==='POST'&&$segments[2]==='upload')$data=$service->upload($segments[1],$_FILES,$_POST);
            else throw new RuntimeException('CAPTURE_NOT_FOUND',404);
        }else{
            $bearer=trim((string)($_SERVER['HTTP_X_CAPTURE_CONTINUATION']??''));$doctor=null;
            if($bearer===''){$doctor=clinical_require_doctor_context('mobile-capture-sessions');if(!$doctor)return;}
            if(count($segments)===2 && $segments[1]==='classifications' && $method==='GET'){
                if(!$doctor)throw new RuntimeException('CAPTURE_UNAUTHENTICATED',401);
                $data=['items'=>array_values(clinical_mobile_capture_catalog()),'title_supported'=>true];
            }elseif(count($segments)===1&&$method==='POST'){
                if(!$doctor)throw new RuntimeException('CAPTURE_UNAUTHENTICATED',401);
                $data=$service->create($doctor,clinical_mobile_capture_body());
            }else{
                $uuid=(string)($segments[1]??'');$s=$service->session($uuid);$service->authorize($s,$doctor,$bearer);$service->expire($uuid);$s=$service->session($uuid);
                if($method==='GET'&&count($segments)===2)$data=$service->describe($s);
                elseif($method==='GET'&&count($segments)===4&&$segments[2]==='thumbnail'){$service->thumbnail($s,$segments[3]);return;}
                elseif($method==='POST'&&count($segments)===3)$data=$service->command($uuid,$doctor,$bearer,$segments[2],clinical_mobile_capture_body());
                else throw new RuntimeException('CAPTURE_NOT_FOUND',404);
            }
        }
        clinical_send_response(['ok'=>true,'data'=>$data],$method==='POST'?201:200);
    }catch(Throwable $e){
        $status=in_array($e->getCode(),[400,401,403,404,409,410],true)?$e->getCode():500;
        if($e->getMessage()==='DOCUMENT_CONTEXT_MISMATCH')$status=403;
        if($e instanceof InvalidArgumentException || in_array($e->getMessage(),['solo se permiten imágenes jpeg/png/webp','imagen inválida','dimensiones de imagen inválidas','no se pudo decodificar imagen'],true))$status=400;
        clinical_send_response(['ok'=>false,'error'=>$status===500?'CAPTURE_OPERATION_FAILED':$e->getMessage(),'data'=>null],$status);
    }
}

/** New captured bundle metadata follows the same physician scope as private binaries. */
function clinical_mobile_capture_read_scope(PDO $pdo,string $uuid): bool
{
    $doctor=clinical_require_doctor_context('mobile-capture-document');if(!$doctor)return false;
    try{$service=new ClinicalMobileCapture($pdo);$service->authorize($service->session($uuid),$doctor,'');return true;}
    catch(Throwable){clinical_send_response(['ok'=>false,'error'=>'forbidden','data'=>null],403);return false;}
}

function clinical_mobile_capture_body(): array
{
    $body=clinical_read_json_body();
    if(($body['ok']??false)!==true)throw new RuntimeException('CAPTURE_JSON_REQUIRED',400);
    return $body['data'];
}
