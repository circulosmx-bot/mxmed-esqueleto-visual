<?php
declare(strict_types=1);
namespace Platform\Services;
final class CanonicalAuditMetadataSanitizer
{
    private const FORBIDDEN=['_audit_v1','ip_hmac','ip_hmac_key_version','raw_ip','raw_user_agent','raw_body','raw_headers','cookies','tokens','otp','passwords','credential_hashes'];
    public function sanitize(array $input,array $allowed): array { foreach(array_keys($input) as $key){if(in_array($key,self::FORBIDDEN,true)||str_starts_with($key,'_audit_v1.'))throw new \InvalidArgumentException('producer_writer_internal_metadata_forbidden');if(!in_array($key,$allowed,true))throw new \InvalidArgumentException('unknown_metadata');}if(isset($input['changed_field_names'])){$v=$input['changed_field_names'];if(!is_array($v)||count($v)>32)throw new \InvalidArgumentException('invalid_changed_field_names');foreach($v as $item)if(!is_string($item)||strlen($item)>64)throw new \InvalidArgumentException('invalid_changed_field_names');}ksort($input,SORT_STRING);return $input; }
}
