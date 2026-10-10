<?php
declare(strict_types=1);
require_once __DIR__.'/clinical_consent_signature_binding.php';
require_once __DIR__.'/clinical_legal_document_presentation.php';

/** Informe-only V1 projection; keep field order aligned with informe-signature-binding.js. */
function clinical_informe_binding_projection(array $body): array {
    $p=(array)($body['payload']??[]); $a=(array)($p['actor_snapshot']??[]);
    $patient=(array)($p['patient_snapshot']??[]); $brand=(array)($p['branding']??[]);
    $content=(array)($p['content']??[]);
    $c='clinical_consent_binding_clean';
    $pick=static function(array $source,array $keys) use($c):array {
        $out=[]; foreach($keys as $key) $out[$key]=$c($source[$key]??''); return $out;
    };
    return [
        'version'=>1,'document_type'=>'informe_medico',
        'document_uuid'=>$c($body['draft_ref']??''),
        'document_date'=>$c($p['report']['emission_date']??''),
        'patient'=>['id'=>$c($body['context']['patient_id']??'')]+$pick($patient,['full_name','age','sex','identifier']),
        'physician'=>$pick($a,['user_id','full_name','license','specialty','specialty_license','place','institution','facility']),
        'visible_branding'=>['logo_url'=>$c($brand['logo_url_resolved']??''),
            'facility'=>$c($brand['facility_visible']??($a['facility']??'')),
            'location'=>$c($brand['location_line_visible']??($a['place']??'')),
            'group_name'=>$c($brand['group_name']??''),
            'address_line'=>$c($brand['address_line']??''),
            'consultorio_phone'=>$c($brand['consultorio_phone']??'')],
        'presentation'=>['professional_header'=>clinical_legal_document_professional_header_mode($p)],
        'content'=>$pick($content,['reason','current_illness','relevant_history','clinical_summary','findings',
            'diagnostic_impression','plan','prognosis','closing_statement']),
        'rendered_text_contract'=>'structured_v1'
    ];
}
function clinical_informe_binding_fingerprint(array $body): string {
    return hash('sha256',json_encode(clinical_informe_binding_projection($body),
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}
function clinical_informe_binding_authority(array $body,string $doctorId): string {
    return $doctorId.'|'.(string)($body['payload']['actor_snapshot']['user_id']??'');
}
function clinical_informe_binding_classify(array $body,string $doctorId,?PDO $pdo=null): string {
    $entry=(array)($body['payload']['signatures']['doctor']??[]);
    if(empty($entry['image_data'])) return 'absent';
    $b=(array)($entry['binding']??[]); $source=(string)($entry['source']??'');
    if(($b['version']??null)!==($source==='remote_qr'?1:2)) return 'legacy_unverified_binding';
    $uuid=(string)($body['draft_ref']??'');
    if($uuid==='' || ($b['document_type']??null)!=='informe_medico'
        || ($b['document_uuid']??null)!==$uuid || !empty($b['revoked_in_edit'])) return 'stale_or_unverified_signature';
    if(!in_array($source,['local_canvas','registered_profile','remote_qr'],true)
        || ($entry['role']??null)!=='doctor' || ($b['role']??null)!=='doctor'
        || ($b['source']??null)!==$source
        || ($b['authority']??null)!==clinical_informe_binding_authority($body,$doctorId)
        || clinical_consent_binding_clean($entry['signer_name']??'')
            !==clinical_consent_binding_clean($body['payload']['actor_snapshot']['full_name']??'')
        || !hash_equals(clinical_informe_binding_fingerprint($body),(string)($b['content_fingerprint']??'')))
        return 'stale_or_unverified_signature';
    $digest=clinical_consent_binding_artifact_digest((string)$entry['image_data']);
    if($digest==='' || !hash_equals($digest,(string)($b['artifact_digest']??''))) return 'stale_or_unverified_signature';
    if($source==='registered_profile') {
        if($pdo===null) return 'stale_or_unverified_signature';
        try {
            $stmt=$pdo->prepare('SELECT checksum_sha256 FROM physician_signatures WHERE doctor_id=? LIMIT 1');
            $stmt->execute([$doctorId]);
            $ownedDigest=$stmt->fetchColumn();
        } catch (Throwable) { return 'stale_or_unverified_signature'; }
        if(!is_string($ownedDigest) || !hash_equals($ownedDigest,$digest)) return 'stale_or_unverified_signature';
    }
    if($source==='remote_qr') {
        if($pdo===null || ($entry['token']??'')==='') return 'stale_or_unverified_signature';
        $qr=clinical_informe_qr_row($pdo,(string)$entry['token']);
        if($qr===null || !in_array((string)$qr['status'],['uploaded','consumed'],true)
            || $qr['invalidated_at']!==null || (string)$qr['document_uuid']!==$uuid
            || (int)$qr['document_version']!==(int)($b['document_version']??0)
            || (string)$qr['doctor_id']!==$doctorId
            || (string)$qr['actor_user_id']!==(string)($body['payload']['actor_snapshot']['user_id']??'')
            || (string)$qr['patient_id']!==(string)($body['context']['patient_id']??'')
            || (string)$qr['role']!=='doctor'
            || (string)$qr['signer_authority']!==clinical_informe_binding_authority($body,$doctorId)
            || !hash_equals((string)$qr['content_fingerprint'],(string)$b['content_fingerprint'])
            || !hash_equals((string)$qr['artifact_digest'],$digest)
            || !hash_equals((string)$qr['signature_image_data'],(string)$entry['image_data'])
            || ($b['token']??null)!==(string)$qr['token']
            || ((string)$qr['status']==='uploaded' && (int)$qr['document_version']!==(int)($body['expected_version']??0))
            || ((string)$qr['status']==='consumed' && (string)$qr['claimed_document_uuid']!==$uuid))
            return 'stale_or_unverified_signature';
    }
    return 'valid_bound_signature';
}
