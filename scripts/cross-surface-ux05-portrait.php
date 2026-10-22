<?php
declare(strict_types=1);
/** Owned transport of one eligible public image; no file import, cookies or credential forwarding. */
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
try{
 if(getenv('MXMED_UX05_REVIEW')!=='1'||$_SERVER['REQUEST_METHOD']!=='GET'||array_diff(array_keys($_GET),['mode','profile_ref']))throw new RuntimeException('denied');
 require_once __DIR__.'/ase-advisor-workspace02-runtime.php';$s=workspace02OwnedServices();
 if(!Identity\Http\GovernanceTransport::secure($_SERVER,'',(string)getenv('MXMED_GOVERNANCE_PROXY_KEY')))throw new RuntimeException('denied');
 $actor=(new Identity\Http\CanonicalHttpSessionResolver($s['identity']->sessions()))->resolve($_COOKIE,true);if(!$actor)throw new RuntimeException('denied');
 $reader=($s['administration'])();$ref=$_GET['profile_ref']??'';if(!is_string($ref))throw new RuntimeException('denied');
 $first=$reader->read($actor,'admin_summary',$ref);$path=$first['physician']['portrait_path']??'';
 if(!is_string($path)||!preg_match('~^/api/media/index.php/public/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$~D',$path))throw new RuntimeException('denied');
 $origin=getenv('MXMED_UX05_PUBLIC_MEDIA_ORIGIN');if(!preg_match('~^http://127\.0\.0\.1:[0-9]+$~D',$origin))throw new RuntimeException('unavailable');
 $ctx=stream_context_create(['http'=>['method'=>'GET','timeout'=>3,'follow_location'=>0,'max_redirects'=>0,'header'=>"Connection: close\r\n"]]);
 $bytes=@file_get_contents($origin.$path,false,$ctx,0,2097153);
 if(!is_string($bytes)||strlen($bytes)>2097152||!preg_match('~^HTTP/\S+ 200 ~',$http_response_header[0]??''))throw new RuntimeException('unavailable');
 $image=@getimagesizefromstring($bytes);if(!$image||!in_array($image['mime'],['image/webp','image/jpeg','image/png'],true))throw new RuntimeException('unavailable');
 $again=$reader->read($actor,'admin_summary',$ref);if(($again['physician']['portrait_path']??null)!==$path)throw new RuntimeException('denied');
 header('Content-Type: '.$image['mime']);echo $bytes;
}catch(Throwable $e){http_response_code(str_contains($e->getMessage(),'denied')?403:503);header('Content-Type: application/json');echo '{"error":"portrait_unavailable"}';}
