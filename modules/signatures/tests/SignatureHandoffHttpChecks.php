<?php
$httpRoot=$private.'-handoff-http';mkdir($httpRoot.'/api/_lib',0700,true);mkdir($httpRoot.'/api/media',0700,true);mkdir($httpRoot.'/sessions',0700);
copy($root.'/api/_lib/db.php',$httpRoot.'/api/_lib/db.php');foreach(['physician-signature','signature-handoff','signature-handoff-device']as$f)copy($root.'/api/media/'.$f.'.php',$httpRoot.'/api/media/'.$f.'.php');copy($root.'/signature-handoff.php',$httpRoot.'/signature-handoff.php');symlink($root.'/modules',$httpRoot.'/modules');symlink($root.'/assets',$httpRoot.'/assets');
foreach(['sigtesta'=>'synthetic-a','sigtestb'=>'synthetic-b']as$sid=>$doctor)file_put_contents($httpRoot.'/sessions/sess_'.$sid,'user_id|s:15:"synthetic-owner";doctor_id|s:'.strlen($doctor).':"'.$doctor.'";entity_type|s:6:"doctor";');
$env=array_merge(getenv(),['MXMED_DB_PORT'=>$port,'MXMED_DB_NAME'=>'mxmed','MXMED_DB_USER'=>'root','MXMED_PRIVATE_MEDIA_ROOT'=>$private,'MXMED_SIGNATURE_HANDOFF_BASE_URL'=>'http://127.0.0.1:18308']);
$process=proc_open([PHP_BINARY,'-d','session.save_path='.$httpRoot.'/sessions','-d','display_errors=0','-S','127.0.0.1:18308','-t',$httpRoot],[0=>['pipe','r'],1=>['file',$httpRoot.'/http.log','a'],2=>['file',$httpRoot.'/http.log','a']],$pipes,$httpRoot,$env);
try{
 usleep(300000);
 $call=function(string$route,string$method,string$sid='',?array$body=null,string$csrf=''):array{
  $headers="Content-Type: application/json\r\n";if($sid)$headers.="Cookie: PHPSESSID=$sid\r\n";if($csrf)$headers.="X-Signature-Handoff-CSRF: $csrf\r\n";
  $c=stream_context_create(['http'=>['method'=>$method,'header'=>$headers,'content'=>$body===null?'':json_encode((object)$body),'ignore_errors'=>true]]);
  $r=file_get_contents('http://127.0.0.1:18308'.$route,false,$c);$hs=function_exists('http_get_last_response_headers')?http_get_last_response_headers():$http_response_header;preg_match('/ (\d{3}) /',$hs[0],$m);return[(int)$m[1],json_decode($r,true),$r,$hs];
 };
 $admin='/api/media/signature-handoff.php';$device='/api/media/signature-handoff-device.php';
 sigCheck($call($admin,'POST','',[])[0]===401,'handoff HTTP anonymous create denied');$csrf=$call($admin,'GET','sigtesta')[1]['data']['csrf_token'];
 sigCheck($call($admin,'POST','sigtesta',[])[0]===403,'handoff HTTP CSRF enforced');sigCheck($call($admin,'POST','sigtesta',['doctor_id'=>'synthetic-b'],$csrf)[0]===422,'handoff HTTP client doctor rejected');
 $created=$call($admin,'POST','sigtesta',[],$csrf);sigCheck($created[0]===200,'handoff HTTP owner creates');$id=$created[1]['data']['id'];$url=$created[1]['data']['url'];$token=parse_url($url,PHP_URL_FRAGMENT);sigCheck(!str_contains($url,'doctor_id')&&!str_contains($url,'synthetic'),'opaque QR URL');
 sigCheck($call($admin.'?id='.$id,'GET','sigtestb')[0]===403,'handoff HTTP other doctor status denied');
 sigCheck($call($device,'POST','',['token'=>$token])[0]===200,'handoff HTTP bearer validates without account login');sigCheck($call($device,'POST','',['token'=>str_repeat('Z',43)])[0]===410,'handoff HTTP invalid token denied');
 sigCheck($call($device,'POST','',['token'=>$token,'image_data'=>$data,'doctor_id'=>'synthetic-b'])[0]===422,'handoff HTTP bearer cannot select doctor');sigCheck($call('/api/media/physician-signature.php','GET')[0]===401,'handoff bearer creates no Admin login');
 $surface=$call('/signature-handoff.php','GET');sigCheck($surface[0]===200&&!str_contains($surface[2],'app.js')&&!str_contains($surface[2],'localStorage'),'device surface isolated');sigCheck(str_contains(implode(' ',$surface[3]),'no-referrer'),'device referrer policy');
 sigCheck($call($device,'POST','',['token'=>$token,'image_data'=>'broken'])[0]===422,'handoff HTTP invalid save fails');sigCheck($call($admin.'?id='.$id,'GET','sigtesta')[1]['data']['status']==='PENDING','handoff HTTP failed save pending');
 sigCheck($call($device,'POST','',['token'=>$token,'image_data'=>$data])[0]===200,'handoff HTTP canonical completion');sigCheck($call($device,'POST','',['token'=>$token,'image_data'=>$data])[0]===410,'handoff HTTP replay rejected');sigCheck($call($admin.'?id='.$id,'GET','sigtesta')[1]['data']===['status'=>'COMPLETED'],'minimal desktop status');sigCheck($call('/api/media/physician-signature.php','GET','sigtesta')[1]['data']['signature']!==null,'canonical GET after remote completion');
 $log=file_get_contents($httpRoot.'/http.log');sigCheck(!str_contains($log,$token)&&!str_contains($log,'data:image'),'no raw token or signature access log');
}finally{proc_terminate($process);proc_close($process);unlink($httpRoot.'/modules');unlink($httpRoot.'/assets');foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($httpRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($httpRoot);}
