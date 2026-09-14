<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/signatures/SignatureHandoffHttp.php';
signatureHandoffHeaders();
// Bearer-only capability. No session login, account cookie or general Admin authority.
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')signatureHandoffReply(405,['ok'=>false,'error'=>'method_not_allowed']);
try{
 if($_GET)throw new RuntimeException('handoff_denied');$body=signatureHandoffBody();$keys=array_keys($body);sort($keys);
 if(($keys!==['token']&&$keys!==['image_data','token'])||!is_string($body['token']??null)||array_key_exists('image_data',$body)&&!is_string($body['image_data']))throw new RuntimeException('handoff_invalid_payload');
 $service=signatureHandoffService();
 if($keys===['token'])signatureHandoffReply(200,['ok'=>true,'data'=>$service->validate($body['token'])]);
 $service->complete($body['token'],$body['image_data']);signatureHandoffReply(200,['ok'=>true,'data'=>['status'=>'COMPLETED']]);
}catch(Throwable $e){signatureHandoffError($e);}
