<?php
declare(strict_types=1);

/** Read-only V1 authority. Changes to a definition require a new registry version. */
function clinical_vital_reference_registry(): array
{
    $verified = '2026-09-27';
    $sources = [
        'aha_acc_bp_2025' => ['source_id'=>'aha_acc_bp_2025','source_title'=>'2025 AHA/ACC High Blood Pressure Guideline','source_year'=>2025,'source_version'=>'2025; DOI 10.1161/CIR.0000000000001356','url'=>'https://www.ahajournals.org/doi/10.1161/CIR.0000000000001356','supporting_url'=>'https://www.heart.org/en/health-topics/high-blood-pressure/blood-pressure-explained','verified_on'=>$verified],
        'medlineplus_vital_signs' => ['source_id'=>'medlineplus_vital_signs','source_title'=>'MedlinePlus — Vital signs','source_year'=>2025,'source_version'=>'review-2025-01-01','url'=>'https://medlineplus.gov/ency/article/002341.htm','verified_on'=>$verified],
        'aap_bp_2017' => ['source_id'=>'aap_bp_2017','source_title'=>'AAP — Clinical Practice Guideline for Screening and Management of High Blood Pressure in Children and Adolescents','source_year'=>2017,'source_version'=>'2017; Table 3; DOI 10.1542/peds.2017-1904','url'=>'https://publications.aap.org/pediatrics/article/140/3/e20171904/38358/Clinical-Practice-Guideline-for-Screening-and','verified_on'=>$verified],
        'medlineplus_oximetry' => ['source_id'=>'medlineplus_oximetry','source_title'=>'MedlinePlus — Pulse Oximetry','source_year'=>2024,'source_version'=>'updated-2024-09-12','url'=>'https://medlineplus.gov/lab-tests/pulse-oximetry/','verified_on'=>$verified],
        'nci_pain_scale' => ['source_id'=>'nci_pain_scale','source_title'=>'NCI — Cancer Pain (PDQ), Pain Assessment: numeric rating scale','source_year'=>2025,'source_version'=>'updated-2025-04-24','url'=>'https://www.cancer.gov/about-cancer/treatment/side-effects/pain/pain-hp-pdq','verified_on'=>$verified],
    ];
    $definition = static function(string $id, string $code, string $population, ?int $min, ?int $max, string $context, string $kind, string $display, string $unit, ?string $source, array $caveats=[], bool $sex=false, bool $height=false) use ($sources): array {
        return ['reference_id'=>$id,'measurement_code'=>$code,'population'=>$population,'age_min'=>$min,'age_max'=>$max,'age_unit'=>'completed_years','age_max_exclusive'=>true,'sex_requirement'=>$sex?'biological_sex':'none','height_requirement'=>$height?'validated_height_percentile':'none','context'=>$context,'reference_kind'=>$kind,'reference_available'=>in_array($kind,['range','category','scale'],true),'display_reference'=>$display,'unit'=>$unit,'source'=>$source===null?null:$sources[$source],'caveats'=>$caveats];
    };
    $adult = 'Adulto sano en reposo; referencia informativa para captura ambulatoria.';
    $noInterpretation = 'La referencia no interpreta la medición ni establece diagnóstico o tratamiento.';
    $definitions = [
        'adult_bp'=>$definition('vitalref01.adult.bp','blood_pressure','adult',18,null,$adult,'category','Referencia adulta: normal <120/<80 mmHg','mmHg','aha_acc_bp_2025',[$noInterpretation,'Categoría normal: ambas presiones deben estar por debajo de los límites; no es un valor individual esperado.']),
        'adult_hr'=>$definition('vitalref01.adult.hr','heart_rate','adult',18,null,$adult,'range','Referencia en reposo: 60–100 bpm','bpm','medlineplus_vital_signs',[$noInterpretation,'El reposo, la actividad física y el contexto clínico afectan el pulso.']),
        'adult_rr'=>$definition('vitalref01.adult.rr','respiratory_rate','adult',18,null,$adult,'range','Referencia en reposo: 12–18 rpm','rpm','medlineplus_vital_signs',[$noInterpretation]),
        'adult_temperature'=>$definition('vitalref01.adult.temperature','temperature','adult',18,null,$adult.' Dependiente del sitio de medición.','range','Referencia habitual: 36.5–37.3 °C','°C','medlineplus_vital_signs',[$noInterpretation,'La temperatura varía con la edad, la hora del día y el sitio de medición.']),
        'adolescent_bp'=>$definition('vitalref01.adolescent.bp','blood_pressure','adolescent',13,18,'Adolescente de 13 a <18 años; categorías absolutas AAP, tabla 3.','category','Referencia adolescente: normal <120/<80 mmHg','mmHg','aap_bp_2017',[$noInterpretation]),
        'child_bp'=>$definition('vitalref01.child.bp.incomplete','blood_pressure','child',1,13,'Niño de 1 a <13 años; interpretación por percentiles AAP.','incomplete','Referencia pediátrica: requiere edad, sexo y talla.','mmHg','aap_bp_2017',['V1 no implementa tablas validadas por edad, sexo biológico y percentil de talla; no se calcula un umbral.',$noInterpretation],true,true),
        'infant_bp'=>$definition('vitalref01.infant.bp.unavailable','blood_pressure','infant',0,1,'Menor de 1 año; autoridad específica no activada en V1.','unavailable','Referencia para menores de 1 año no disponible.','mmHg','aap_bp_2017',['No aplicar categorías adultas ni tablas de niños de 1 a <13 años.']),
        'oxygen'=>$definition('vitalref01.oxygen','oxygen_saturation','general',null,null,'Saturación habitual en personas sanas; sensible a altitud y contexto pulmonar.','range','Referencia habitual: 95–100 %','%','medlineplus_oximetry',[$noInterpretation,'La saturación basal aceptable puede diferir con enfermedad pulmonar, altitud y contexto clínico.','La precisión depende del dispositivo y de las condiciones de medición.']),
        'pain'=>$definition('vitalref01.pain.scale','pain','self_report',8,null,'Escala numérica subjetiva de autoinforme; verificar capacidad para usarla.','scale','Escala: 0–10','score','nci_pain_scale',['Describe la escala, no un valor esperado ni normal; no presupone ausencia de dolor.','El PDQ describe esta herramienta en adultos y niños mayores de 7 años; para menores o personas sin capacidad de autoinforme pueden requerirse otras escalas.','Se utiliza únicamente la definición de la herramienta, no recomendaciones oncológicas.']),
    ];
    foreach (['weight'=>'kg','height'=>'cm','waist'=>'cm'] as $code=>$unit) {
        $definitions[$code]=$definition('vitalref01.'.$code.'.none',$code,'all',null,null,'Sin referencia individual validada en V1.','none','',$unit,null,['No inferir un valor individual a partir de edad, sexo, identidad de género o población.']);
    }
    return ['registry_version'=>'VITALREF01.v1','sources'=>$sources,'definitions'=>$definitions,'pediatric_hr_status'=>'DEFERRED_ROUTINE_OUTPATIENT_AUTHORITY_NOT_VALIDATED','pediatric_rr_status'=>'DEFERRED_ROUTINE_OUTPATIENT_AUTHORITY_NOT_VALIDATED'];
}
