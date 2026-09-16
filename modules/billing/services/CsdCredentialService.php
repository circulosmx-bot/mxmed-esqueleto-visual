<?php
declare(strict_types=1);
namespace Billing\Services;

use PDO;
require_once __DIR__.'/IssuerProfileService.php';
require_once __DIR__.'/EncryptedLocalCsdStore.php';
require_once __DIR__.'/BillingIssuanceAudit.php';

final class CsdCredentialService
{
    public function __construct(private PDO $pdo,private IssuerProfileService $issuers,private ?CsdSecretPort $secrets=null) {}

    public function list(string $doctorId,string $issuerId):array
    {
        $this->issuers->get($doctorId,$issuerId);
        $q=$this->pdo->prepare('SELECT credential_id,certificate_serial,certificate_rfc,certificate_sha256,valid_from,valid_to,certificate_type,created_at FROM billing_csd_credentials WHERE doctor_id=? AND issuer_profile_id=? AND archived_at IS NULL ORDER BY created_at DESC');
        $q->execute([$doctorId,$issuerId]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function register(string $doctorId,string $issuerId,string $cer,string $key,string $password):array
    {
        $this->secrets ??= EncryptedLocalCsdStore::runtime();
        $issuer=$this->issuers->get($doctorId,$issuerId);
        if (strlen($cer)>32768 || strlen($key)>32768 || strlen($password)>1024 || $password==='') throw new \InvalidArgumentException('invalid_csd_files');
        $cerPem=str_contains($cer,'-----BEGIN CERTIFICATE-----')?$cer:"-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($cer),64,"\n")."-----END CERTIFICATE-----\n";
        $cert=@openssl_x509_read($cerPem);
        if ($cert===false) throw new \InvalidArgumentException('invalid_csd_certificate');
        $parsed=openssl_x509_parse($cert);
        if (!is_array($parsed) || empty($parsed['serialNumberHex']) || empty($parsed['validFrom_time_t']) || empty($parsed['validTo_time_t'])) throw new \InvalidArgumentException('invalid_csd_certificate');
        if ($parsed['validFrom_time_t']>time() || $parsed['validTo_time_t']<=time()) throw new \InvalidArgumentException('csd_certificate_not_current');
        if(str_contains($key,'-----BEGIN') && !str_contains($key,'-----BEGIN ENCRYPTED PRIVATE KEY-----'))throw new \InvalidArgumentException('invalid_csd_key_format');
        $keyPem=str_contains($key,'-----BEGIN')?$key:"-----BEGIN ENCRYPTED PRIVATE KEY-----\n".chunk_split(base64_encode($key),64,"\n")."-----END ENCRYPTED PRIVATE KEY-----\n";
        $private=@openssl_pkey_get_private($keyPem,$password);
        if ($private===false) throw new \InvalidArgumentException('invalid_csd_key_or_password');
        if (!openssl_x509_check_private_key($cert,$private)) throw new \InvalidArgumentException('csd_key_mismatch');
        $subject=implode(' ',array_filter((array)($parsed['subject']??[]),'is_string'));
        $subjectRfc=null;
        if (preg_match('/\b[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}\b/u',$subject,$match)) $subjectRfc=$match[0];
        if ($subjectRfc!==null && $subjectRfc!==$issuer['rfc']) throw new \InvalidArgumentException('csd_issuer_rfc_mismatch');
        $rawSerial=strtoupper((string)$parsed['serialNumberHex']);
        $decodedSerial=ctype_xdigit($rawSerial) && strlen($rawSerial)%2===0 ? hex2bin($rawSerial) : false;
        $serial=is_string($decodedSerial) && preg_match('/^[0-9]{20}$/D',$decodedSerial) ? $decodedSerial : $rawSerial;
        if(strlen($serial)>80)throw new \InvalidArgumentException('invalid_csd_certificate');
        $id=IssuerProfileService::uuid();$keys=[];
        try {
            foreach (['cer'=>$cer,'key'=>$key,'password'=>$password] as $kind=>$value) $keys[$kind]=$this->secrets->store($doctorId,$id,$kind,$value);
            $this->pdo->beginTransaction();
            $q=$this->pdo->prepare('INSERT INTO billing_csd_credentials (credential_id,issuer_profile_id,doctor_id,certificate_serial,certificate_rfc,certificate_sha256,valid_from,valid_to,certificate_type,certificate_storage_key,private_key_storage_key,password_storage_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            // An X.509 match does not itself prove SAT CSD rather than e.firma.
            $q->execute([$id,$issuerId,$doctorId,$serial,$subjectRfc,hash('sha256',$cer),gmdate('Y-m-d H:i:s',$parsed['validFrom_time_t']),gmdate('Y-m-d H:i:s',$parsed['validTo_time_t']),'UNVERIFIED',$keys['cer'],$keys['key'],$keys['password']]);
            (new BillingIssuanceAudit($this->pdo))->record($doctorId,'CSD_REGISTERED','SUCCESS',null,null,$issuerId);
            $this->pdo->commit();
        } catch (\Throwable $e) {if($this->pdo->inTransaction())$this->pdo->rollBack();foreach($keys as $stored)$this->secrets->delete($stored);throw $e;}
        return ['credential_id'=>$id,'certificate_serial'=>$serial,'certificate_rfc'=>$subjectRfc,
            'valid_from'=>gmdate('Y-m-d H:i:s',$parsed['validFrom_time_t']),'valid_to'=>gmdate('Y-m-d H:i:s',$parsed['validTo_time_t']),
            'certificate_type'=>'UNVERIFIED'];
    }
}
