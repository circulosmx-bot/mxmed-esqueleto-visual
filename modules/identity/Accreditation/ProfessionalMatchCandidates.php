<?php
declare(strict_types=1);
namespace Identity\Accreditation;
use PDO;

/** Shared discovery precedence. Caller must enforce private review authority. Never a resolution. */
final class ProfessionalMatchCandidates
{
    public static function find(EvidenceAuthority $a,?string $license,?string $givenNames=null,?string $firstSurname=null,
        ?string $specialty=null,?string $state=null,?string $municipality=null):array
    {
        $ids=[];$model='NO_MATCH';
        if($license!==null&&trim($license)!==''){
            if(strlen($license)>64)throw new \InvalidArgumentException('c4_match_input_invalid');
            $ids=$a->query("SELECT doctor_id FROM profiles_doctor_credentials WHERE credential_type='PROFESSIONAL' AND license_number=?
                UNION SELECT doctor_id FROM profiles_doctors WHERE BINARY professional_license=BINARY ? LIMIT 51",[trim($license),trim($license)])->fetchAll(PDO::FETCH_COLUMN);
            if($ids!==[])$model='EXACT_PROFESSIONAL_LICENSE_MATCH';
        }
        if($ids===[]&&$givenNames!==null&&$firstSurname!==null&&$specialty!==null&&$state!==null&&$municipality!==null){
            $parts=[$givenNames,$firstSurname,$specialty,$state,$municipality];
            foreach($parts as $part)if(trim($part)===''||strlen($part)>190)throw new \InvalidArgumentException('c4_composite_context_required');
            $ids=$a->query("SELECT DISTINCT i.doctor_id FROM profiles_verified_identities i JOIN profiles_doctors p ON p.doctor_id=i.doctor_id
                JOIN consultorios c ON c.doctor_id=p.doctor_id WHERE LOWER(TRIM(i.given_names))=LOWER(TRIM(?))
                AND LOWER(TRIM(i.first_surname))=LOWER(TRIM(?)) AND LOWER(TRIM(p.specialty_primary))=LOWER(TRIM(?))
                AND LOWER(TRIM(c.estado))=LOWER(TRIM(?)) AND LOWER(TRIM(c.municipio))=LOWER(TRIM(?)) LIMIT 51",$parts)->fetchAll(PDO::FETCH_COLUMN);
            if($ids!==[])$model='COMPOSITE_PROFESSIONAL_MATCH';
        }
        return ['model'=>$model,'candidate_profile_ids'=>array_slice($ids,0,50),'ambiguous'=>count($ids)>1,'human_review_required'=>true,'selected_profile_id'=>null];
    }
}
