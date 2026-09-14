<?php
// Included only by the explicitly disposable physical test.
$httpRoot=$private.'-http';mkdir($httpRoot.'/api/_lib',0700,true);mkdir($httpRoot.'/api/media',0700,true);mkdir($httpRoot.'/sessions',0700);
copy($root.'/api/_lib/db.php',$httpRoot.'/api/_lib/db.php');copy($root.'/api/media/physician-signature.php',$httpRoot.'/api/media/physician-signature.php');symlink($root.'/modules',$httpRoot.'/modules');
foreach(['sigtesta'=>'synthetic-a','sigtestb'=>'synthetic-b']as$sid=>$doctor)file_put_contents($httpRoot.'/sessions/sess_'.$sid,'user_id|s:15:"synthetic-owner";doctor_id|s:'.strlen($doctor).':"'.$doctor.'";entity_type|s:6:"doctor";');
$env=array_merge(getenv(),['MXMED_DB_PORT'=>$port,'MXMED_DB_NAME'=>'mxmed','MXMED_DB_USER'=>'root','MXMED_PRIVATE_MEDIA_ROOT'=>$private]);
$process=proc_open([PHP_BINARY,'-d','session.save_path='.$httpRoot.'/sessions','-d','display_errors=0','-S','127.0.0.1:18308','-t',$httpRoot],[0=>['pipe','r'],1=>['file',$httpRoot.'/http.log','a'],2=>['file',$httpRoot.'/http.log','a']],$pipes,$httpRoot,$env);
try{
 usleep(300000);
 $call=function(string$method,string$sid='',?string$body=null,string$csrf='',string$query=''):array{
  $headers="Content-Type: application/json\r\n";if($sid)$headers.="Cookie: PHPSESSID=$sid\r\n";if($csrf)$headers.="X-Signature-CSRF: $csrf\r\n";
  $context=stream_context_create(['http'=>['method'=>$method,'header'=>$headers,'content'=>$body??'','ignore_errors'=>true]]);
  $result=file_get_contents('http://127.0.0.1:18308/api/media/physician-signature.php'.$query,false,$context);preg_match('/ (\d{3}) /',$http_response_header[0],$m);return[(int)$m[1],json_decode($result,true)];
 };
 sigCheck($call('GET')[0]===401,'HTTP anonymous GET denied');sigCheck($call('POST')[0]===401,'HTTP anonymous mutation denied');
 [$code,$response]=$call('GET','sigtesta');sigCheck($code===200&&$response['data']['signature']===null,'HTTP current none');$csrf=$response['data']['csrf_token'];
 sigCheck($call('POST','sigtesta',json_encode(['image_data'=>$data]))[0]===403,'HTTP CSRF denied');
 sigCheck($call('GET','sigtesta',null,'','?doctor_id=synthetic-b')[0]===403,'HTTP cross-doctor query denied');
 sigCheck($call('POST','sigtesta',json_encode(['image_data'=>$data,'doctor_id'=>'synthetic-b']),$csrf)[0]===422,'HTTP client identity denied');
 sigCheck($call('POST','sigtesta',json_encode(['asset_id'=>'invalid']),$csrf)[0]===422,'HTTP invalid asset reference denied');
 sigCheck($call('POST','sigtesta',json_encode(['image_data'=>$data]),$csrf)[0]===200,'HTTP authorized save');
 sigCheck($call('GET','sigtestb')[1]['data']['signature']===null,'HTTP other owner isolated');
 sigCheck($call('GET','sigtesta')[1]['data']['signature']!==null,'HTTP persisted reload');
 sigCheck($call('DELETE','sigtesta',null,$csrf)[0]===200,'HTTP authorized delete');
 sigCheck(!str_contains(file_get_contents($httpRoot.'/http.log'),'data:image'),'HTTP no payload log');
}finally{proc_terminate($process);proc_close($process);unlink($httpRoot.'/modules');foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($httpRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($httpRoot);}
