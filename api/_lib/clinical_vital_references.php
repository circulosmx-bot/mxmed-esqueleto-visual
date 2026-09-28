<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/clinical/vitals/reference_registry.php';

/** Pure read resolver. Only canonical DOB is accepted; no caller-supplied age or sex. */
function clinical_vital_references_resolve(?string $birthdate, ?DateTimeImmutable $today=null): array
{
    $today=($today ?? new DateTimeImmutable('today',new DateTimeZone('America/Mexico_City')))->setTime(0,0);
    $age=null;
    if ($birthdate!==null && preg_match('/^\d{4}-\d{2}-\d{2}$/D',$birthdate) && substr($birthdate,0,4)!=='0000') {
        $dob=DateTimeImmutable::createFromFormat('!Y-m-d',$birthdate,$today->getTimezone());
        if ($dob!==false && $dob->format('Y-m-d')===$birthdate && $dob<=$today) $age=$dob->diff($today)->y;
    }
    $population=$age===null?'unknown':($age>=18?'adult':($age>=13?'adolescent':($age>=1?'child':'infant')));
    $registry=clinical_vital_reference_registry(); $definitions=$registry['definitions'];
    $unavailable=static function(string $code,string $unit,string $display,?array $source=null,string $status='CONTEXT_INCOMPLETE') use ($population): array {
        return ['reference_id'=>'vitalref01.'.$population.'.'.$code.'.unavailable','measurement_code'=>$code,'population'=>$population,'age_min'=>null,'age_max'=>null,'age_unit'=>'completed_years','age_max_exclusive'=>true,'sex_requirement'=>'none','height_requirement'=>'none','context'=>'No hay autoridad aplicable activada para este contexto en V1.','reference_kind'=>'unavailable','reference_available'=>false,'display_reference'=>$display,'unit'=>$unit,'source'=>$source,'caveats'=>['No inferir ni sustituir información demográfica o una medición clínica.'],'resolution_status'=>$status];
    };
    $items=[];
    foreach (['blood_pressure'=>'mmHg','heart_rate'=>'bpm','respiratory_rate'=>'rpm','temperature'=>'°C','oxygen_saturation'=>'%','pain'=>'score','weight'=>'kg','height'=>'cm','waist'=>'cm'] as $code=>$unit) {
        if (in_array($code,['weight','height','waist'],true)) $item=$definitions[$code];
        elseif ($code==='oxygen_saturation') $item=$definitions['oxygen'];
        elseif ($code==='pain') $item=$age!==null && $age>=8 ? $definitions['pain'] : $unavailable($code,$unit,'Escala: verificar edad y autoinforme.',$registry['sources']['nci_pain_scale']);
        elseif ($code==='blood_pressure' && $age!==null) $item=$definitions[$population.'_bp'];
        elseif ($age!==null && $age>=18) $item=$definitions[['heart_rate'=>'adult_hr','respiratory_rate'=>'adult_rr','temperature'=>'adult_temperature'][$code]];
        else {
            $status=$age===null?'CANONICAL_DOB_REQUIRED':($code==='temperature'?'PEDIATRIC_AUTHORITY_NOT_ACTIVATED':$registry[$code==='heart_rate'?'pediatric_hr_status':'pediatric_rr_status']);
            $item=$unavailable($code,$unit,$age===null?'Referencia: requiere fecha de nacimiento válida.':'Referencia pediátrica no disponible en V1.',null,$status);
        }
        $item['resolution_status']=$item['resolution_status'] ?? ($item['reference_kind']==='incomplete'?'VALIDATED_PERCENTILE_AUTHORITY_REQUIRED':($item['reference_kind']==='none'?'NO_INDIVIDUAL_REFERENCE':($item['reference_available']?'AVAILABLE':'UNAVAILABLE')));
        $items[]=$item;
    }
    return ['registry_version'=>$registry['registry_version'],'patient_context'=>['population'=>$population,'age_authority'=>$age===null?'unavailable':'canonical_birthdate','evaluated_on'=>$today->format('Y-m-d')],'items'=>$items];
}
