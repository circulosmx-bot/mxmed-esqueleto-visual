<?php
declare(strict_types=1);
require_once __DIR__.'/clinical_legal_document_presentation.php';

function clinical_interconsulta_recipient_label(array $recipient): string {
    $mode=(string)($recipient['mode']??'doctor');
    $doctor=trim((string)($recipient['doctor_name']??''));
    $specialty=trim((string)($recipient['specialty']??''));
    $service=trim((string)($recipient['service']??''));
    if($mode==='service') return $service!==''?$service:($specialty!==''?$specialty:'Destino clínico no especificado');
    return $doctor!==''?$doctor:($specialty!==''?$specialty:'Destino clínico no especificado');
}

function clinical_interconsulta_closing(array $payload): string {
    $content=(array)($payload['content']??[]);
    $explicit=trim((string)($content['closing_statement']??''));
    if($explicit!=='') return $explicit;
    return 'Se emite la presente interconsulta para valoración por '
        .clinical_interconsulta_recipient_label((array)($payload['recipient']??[]))
        .', con base en el contexto clínico descrito.';
}

function clinical_interconsulta_canonical_text(array $payload): string {
    $content=(array)($payload['content']??[]);
    $lines=['INTERCONSULTA','Paciente: '.(string)($payload['patient_snapshot']['full_name']??''),
        'Destino: '.clinical_interconsulta_recipient_label((array)($payload['recipient']??[]))];
    foreach(['reason'=>'Motivo de interconsulta','summary'=>'Resumen clínico de referencia',
        'background'=>'Antecedentes relevantes','request'=>'Solicitud al interconsultante',
        'studies'=>'Estudios relevantes','comments'=>'Comentarios al colega',
        'closing_statement'=>'Declaración de cierre','final_note'=>'Observación final del emisor'] as $key=>$label){
        $value=$key==='closing_statement'?clinical_interconsulta_closing($payload):trim((string)($content[$key]??''));
        if($value!=='')$lines[]=$label."\n".$value;
    }
    $lines[]='Médico emisor: '.(string)($payload['actor_snapshot']['full_name']??'');
    return implode("\n\n",$lines);
}

/** One escaped composition for unsaved preview, final review, frozen mobile review and new-document viewer/print. */
function clinical_interconsulta_render_html(array $payload): string {
    $h=static fn($v):string=>htmlspecialchars(trim((string)($v??'')),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $patient=(array)($payload['patient_snapshot']??[]);$actor=(array)($payload['actor_snapshot']??[]);
    $recipient=(array)($payload['recipient']??[]);$content=(array)($payload['content']??[]);
    $brand=(array)($payload['branding']??[]);$sig=(array)($payload['signatures']['doctor']??[]);
    $report=(array)($payload['report']??[]);
    $image=(string)($sig['image_data']??'');
    $image=preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#D',$image)===1?$image:'';
    $logo=(string)($brand['logo_url_resolved']??'');
    $logo=(str_starts_with($logo,'/')&&!str_starts_with($logo,'//'))
        ||preg_match('#^https://[^\s]+$#i',$logo)===1
        ||preg_match('#^data:image/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#D',$logo)===1?$logo:'';
    $html='<article class="informe-doc-sheet interconsulta-doc-sheet document-sheet doc-base-sheet doc-base-sheet--letter doc-base-print-safe"><div class="doc-base-sheet-inner doc-base-body">';
    if(clinical_legal_document_professional_header_mode($payload)==='shown'){
        $html.='<header class="clinical-doc-head informe-doc-head doc-base-medical-header doc-base-header-block doc-base-print-safe">';
        if($logo!=='')$html.='<div class="clinical-doc-head-logo-slot"><img src="'.$h($logo).'" alt="Logo médico"></div>';
        $html.='<div class="clinical-doc-head-main"><div class="clinical-doc-doctor-name">'.$h($actor['full_name']??'').'</div>';
        foreach([$actor['specialty']??'',($actor['license']??'')!==''?'Cédula: '.$actor['license']:'',
            ($actor['specialty_license']??'')!==''?'Cédula esp.: '.$actor['specialty_license']:'',
            $brand['facility_visible']??'', $brand['location_line_visible']??''] as $line)
            if(trim((string)$line)!=='')$html.='<div class="clinical-doc-doctor-site">'.$h($line).'</div>';
        $html.='</div></header>';
    }
    $html.='<div class="doc-base-title-block-wrap"><section class="informe-doc-title-block doc-base-title-block"><div class="informe-doc-title doc-base-title">Interconsulta</div></section></div>';
    $html.='<div class="doc-base-patient-block-wrap"><section class="informe-doc-patient-block doc-base-patient-meta">'
        .'<div class="informe-doc-patient-line"><strong>Paciente:</strong> '.$h($patient['full_name']??'').'</div>'
        .'<div class="informe-doc-patient-line"><strong>Edad:</strong> '.$h($patient['age']??'').' · <strong>Sexo:</strong> '.$h($patient['sex']??'').'</div>'
        .'<div class="informe-doc-patient-line"><strong>Fecha:</strong> '.$h($report['emission_date']??'').'</div>'
        .'</section></div><div class="doc-base-body-block">';
    $mode=(string)($recipient['mode']??'doctor');
    $secondary=array_filter([$mode==='doctor'?($recipient['specialty']??''):
        (trim((string)($recipient['doctor_name']??''))!==''?'Médico: '.$recipient['doctor_name']:''),
        $recipient['facility']??'', $recipient['city']??''],static fn($v)=>trim((string)$v)!=='');
    $html.='<section class="informe-doc-body-section doc-base-section"><div class="informe-doc-section-title doc-base-section-title">Destino de interconsulta</div>'
        .'<div class="informe-doc-text doc-base-text">'.$h(clinical_interconsulta_recipient_label($recipient)).'</div>';
    if($secondary)$html.='<div class="informe-doc-text doc-base-text">'.$h(implode(' · ',$secondary)).'</div>';
    if(trim((string)($recipient['contact']??''))!=='')$html.='<div class="informe-doc-text doc-base-text">Contacto: '.$h($recipient['contact']).'</div>';
    $html.='</section>';
    foreach(['reason'=>'Motivo de interconsulta','summary'=>'Resumen clínico de referencia',
        'background'=>'Antecedentes relevantes','request'=>'Solicitud al interconsultante',
        'studies'=>'Estudios relevantes','comments'=>'Comentarios al colega',
        'closing_statement'=>'Declaración de cierre','final_note'=>'Observación final del emisor'] as $key=>$label){
        $value=$key==='closing_statement'?clinical_interconsulta_closing($payload):trim((string)($content[$key]??''));
        if($value==='')continue;
        $html.='<section class="informe-doc-body-section doc-base-section"><div class="informe-doc-section-title doc-base-section-title">'.$h($label).'</div>'
            .'<div class="informe-doc-text doc-base-text">'.nl2br($h($value)).'</div></section>';
    }
    $html.='<section class="informe-doc-sign doc-base-signature doc-base-print-safe"><div class="informe-doc-section-title doc-base-section-title">Firma del médico emisor</div>';
    if($image!=='')$html.='<img class="informe-doc-sign-image" src="'.$h($image).'" alt="Firma del médico">';
    else $html.='<div class="informe-doc-sign-line"></div>';
    $html.='<div class="informe-doc-sign-meta doc-base-signature-meta">'.$h($sig['signer_name']??($actor['full_name']??'')).'</div></section></div>';
    $footer=array_filter([(string)($brand['group_name']??''),(string)($brand['address_line']??''),(string)($brand['consultorio_phone']??'')]);
    $html.='<div class="doc-base-flex-spacer" aria-hidden="true"></div><div class="doc-base-footer-block">'.$h(implode(' · ',$footer)).'</div>';
    return $html.'</div></article>';
}
