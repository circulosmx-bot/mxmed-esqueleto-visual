<?php
declare(strict_types=1);
namespace Identity\Operational;
use Identity\Accreditation\EvidenceAuthority;

/** Readiness adapter to VID01/CRD02, never a declared-license verifier or second master. */
final class ProfessionalSourceReadiness
{
    public static function inspect(EvidenceAuthority $a,?string $profile):array
    {
        if($profile===null)return ['ready'=>false,'provisioning_available'=>false,'status'=>'PROFILE_RESOLUTION_REQUIRED'];
        $a->scope?->profile($profile);
        $i=$a->query('SELECT verified_identity_id FROM profiles_verified_identities WHERE doctor_id=?',[$profile])->fetchAll(\PDO::FETCH_COLUMN);
        $c=$a->query("SELECT credential_id FROM profiles_doctor_credentials WHERE doctor_id=? AND credential_type='PROFESSIONAL' AND verification_status='VERIFIED' AND lifecycle_status='ACTIVE'",[$profile])->fetchAll(\PDO::FETCH_COLUMN);
        $ready=false;
        if(count($i)===1&&count($c)===1){try{$a->sources($profile,(int)$i[0],(int)$c[0]);$ready=true;}catch(\RuntimeException){}}
        return ['ready'=>$ready,'provisioning_available'=>false,'status'=>$ready?'VERIFIED_CANONICAL_SOURCES':'PENDING_GENUINE_SOURCE_PROVISIONING',
            'message'=>$ready?'El perfil cuenta con fuentes profesionales verificadas; la revisión de la solicitud sigue siendo independiente.'
                :'Faltan fuentes profesionales verificadas. El nombre y la cédula declarados no acreditan identidad. El equipo autorizado debe completar la verificación profesional antes de continuar.'];
    }
}
