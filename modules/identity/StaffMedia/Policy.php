<?php
declare(strict_types=1);
namespace Identity\StaffMedia;
/** Explicit technical/custody contract; no production defaults or implied legal approval. */
final class Policy
{
    public const PORTRAIT='INTERNAL_PRESENTATION_PORTRAIT';
    public const EVIDENCE='STAFF_IDENTITY_EVIDENCE';
    public function __construct(public readonly int $maxBytes,public readonly int $maxSide,public readonly int $maxPixels,
        public readonly int $portraitMaxEdge,public readonly int $portraitMaxBytes,
        public readonly string $custodyReference,public readonly string $retentionReference,private string $operationKey)
    {
        foreach([$maxBytes,$maxSide,$maxPixels,$portraitMaxEdge,$portraitMaxBytes] as $n)if($n<1||$n>2147483647)throw new \InvalidArgumentException('staff_media_policy_required');
        foreach([$custodyReference,$retentionReference] as $ref)if(!preg_match('/^[A-Za-z0-9_.:-]{8,128}$/D',$ref))throw new \InvalidArgumentException('staff_media_policy_required');
        if(strlen($operationKey)!==32)throw new \InvalidArgumentException('staff_media_operation_key_required');
    }
    public static function purpose(string $purpose):void
    {if(!in_array($purpose,[self::PORTRAIT,self::EVIDENCE],true))throw new \InvalidArgumentException('staff_media_invalid');}
    public function assetId(string $actor,string $case,string $operation):string
    {return 'sma_'.substr(hash_hmac('sha256',json_encode(['STAFF-MEDIA-OP-ID-v1',$actor,$case,$operation],JSON_THROW_ON_ERROR),$this->operationKey),0,32);}
    public function digest(array $body,string $bytes):string
    {ksort($body);return hash_hmac('sha256',json_encode(['STAFF-MEDIA-REQUEST-v1',$body,hash('sha256',$bytes)],JSON_THROW_ON_ERROR),$this->operationKey);}
}
