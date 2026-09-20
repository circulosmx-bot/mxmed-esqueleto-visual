<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/api/_lib/clinical_private_binary_http.php';
require dirname(__DIR__,3).'/api/_lib/clinical_private_binary_storage.php';
function hcheck($v,$m){if(!$v)throw new RuntimeException($m);}
$count=0;
foreach(['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'] as $mime=>$ext){
 $h=clinical_binary_http_headers(['mime_type'=>$mime,'byte_length'=>5,'source_filename'=>"../../\"bad\r\nInjected: value"]);
 hcheck($h['Content-Type']===$mime&&$h['Content-Disposition']==='inline; filename="document.'.$ext.'"','MIME/disposition');
 hcheck($h['Content-Length']==='5'&&$h['X-Content-Type-Options']==='nosniff'&&$h['Cache-Control']==='private, no-store','headers');
 hcheck(count($h)===5&&!str_contains(implode('',$h),"\r")&&!str_contains(implode('',$h),"\n"),'injection/cache/range');$count++;
}
foreach(['DOCUMENT_BINARY_NOT_FOUND'=>[404,'not_found','resource not found'],'DOCUMENT_BINARY_MISSING'=>[503,'BINARY_UNAVAILABLE','binary unavailable'],'DOCUMENT_BINARY_INTEGRITY_MISMATCH'=>[503,'BINARY_INTEGRITY_FAILED','binary unavailable'],'PRIVATE_BINARY_STORAGE_NOT_CONFIGURED'=>[503,'PRIVATE_BINARY_STORAGE_NOT_CONFIGURED','binary unavailable'],'secret path diagnostic'=>[500,'server_error','server error']] as $code=>$expected){hcheck(clinical_binary_http_error(new RuntimeException($code))===$expected,'error mapping');$count++;}
foreach(['success','short','disconnect','emit-error'] as $case){
 $f=fopen('php://temp','w+b');$bytes=str_repeat('a',140000);fwrite($f,$bytes);rewind($f);$received='';$largest=0;$failed=false;
 try{clinical_binary_http_transfer($f,$case==='short'?140001:140000,function($chunk)use(&$received,&$largest,$case){if($case==='emit-error')throw new RuntimeException('emit');$received.=$chunk;$largest=max($largest,strlen($chunk));},fn()=>$case==='disconnect');}catch(RuntimeException){$failed=true;}
 hcheck(!is_resource($f),'stream not closed');hcheck($largest<=65536,'unbounded chunk');hcheck($failed===($case!=='success'),'transfer failure');if($case==='success')hcheck($received===$bytes,'exact bytes');$count++;
}
$root=sys_get_temp_dir().'/mxmed_multi04b_'.bin2hex(random_bytes(8));mkdir($root,0700);
try{
 try{new ClinicalPrivateBinaryStorage($root.'/absent',dirname(__DIR__,3),null,true);throw new RuntimeException('missing accepted');}catch(ClinicalPrivateBinaryStorageException){}
 hcheck(!file_exists($root.'/absent'),'read-only constructor created root');
 mkdir($root.'/existing',0700);foreach(['staging','clinical','quarantine'] as $n)mkdir($root.'/existing/'.$n,0750);
 $before=[];foreach(['','/staging','/clinical','/quarantine'] as $n)$before[$n]=fileperms($root.'/existing'.$n);
 new ClinicalPrivateBinaryStorage($root.'/existing',dirname(__DIR__,3),null,true);clearstatcache();foreach($before as $n=>$perm)hcheck(fileperms($root.'/existing'.$n)===$perm,'permissions changed');$count+=2;
}finally{foreach(['staging','clinical','quarantine'] as $n)if(is_dir($root.'/existing/'.$n))rmdir($root.'/existing/'.$n);if(is_dir($root.'/existing'))rmdir($root.'/existing');rmdir($root);}
echo "MULTI04B_CONTROLLER_QA=PASS\nSCENARIOS=$count\nANY_DATABASE_CONNECTED=false\nPHYSICAL_HTTP_EXECUTED=false\n";
