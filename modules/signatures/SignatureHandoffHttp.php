<?php
declare(strict_types=1);
require_once __DIR__.'/SignatureHandoffService.php';
require_once __DIR__.'/../../api/_lib/db.php';
require_once __DIR__.'/../media/private-bootstrap.php';
function signatureHandoffReply(int $status,array $body): never {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function signatureHandoffHeaders(): void {
    header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
}
function signatureHandoffService(): \Signatures\SignatureHandoffService {$pdo=mxmed_pdo();return new \Signatures\SignatureHandoffService($pdo,new \Signatures\PhysicianSignatureService($pdo,mxmed_private_media_storage()));}
function signatureHandoffBody(): array {
    if(strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]))!=='application/json'||(int)($_SERVER['CONTENT_LENGTH']??0)>2800000)throw new RuntimeException('handoff_invalid_payload');
    $raw=file_get_contents('php://input',false,null,0,2800001);
    if($raw===false||strlen($raw)>2800000)throw new RuntimeException('handoff_invalid_payload');
    $body=json_decode($raw,true);if(!is_array($body)||!str_starts_with(ltrim($raw),'{'))throw new RuntimeException('handoff_invalid_payload');return $body;
}
function signatureHandoffUrl(string $token): string {
    $base=trim((string)getenv('MXMED_SIGNATURE_HANDOFF_BASE_URL'));
    if($base===''){
        // Loopback-only HTTP is for automated local review, not real-device deployment.
        $host=(string)($_SERVER['HTTP_HOST']??'');
        if(preg_match('/^(127\.0\.0\.1|localhost|\[::1\])(?::[0-9]{1,5})?$/D',$host)!==1)throw new RuntimeException('handoff_https_base_required');
        $base='http://'.$host;
    }
    $url=parse_url($base);
    if(!$url||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||!isset($url['host'])||!in_array($url['scheme']??'',['https','http'],true))throw new RuntimeException('handoff_https_base_required');
    if($url['scheme']!=='https'&&!in_array($url['host'],['127.0.0.1','localhost','[::1]'],true))throw new RuntimeException('handoff_https_base_required');
    return rtrim($base,'/').'/signature-handoff.php#'.$token;
}
function signatureHandoffError(Throwable $e): never {
    $code=match($e->getMessage()){'handoff_denied'=>410,'handoff_owner_denied'=>403,'handoff_invalid_payload','signature_invalid_payload','signature_input_too_large','signature_invalid_image','signature_decode_failed','signature_empty','signature_output_too_large'=>422,default=>503};
    signatureHandoffReply($code,['ok'=>false,'error'=>$code===503?'handoff_unavailable':($code===410?'handoff_denied':($code===403?'handoff_owner_denied':'handoff_invalid_payload'))]);
}
