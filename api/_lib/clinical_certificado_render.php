<?php
declare(strict_types=1);
require_once __DIR__.'/clinical_legal_document_presentation.php';

function clinical_certificado_canonical_text(array $payload): string {
    $certificate=(array)($payload['certificate']??[]);
    $content=(array)($payload['content']??[]);
    $lines=[(string)($certificate['type_label']??'Certificado médico'),
        (string)($payload['patient_snapshot']['full_name']??''),
        (string)($content['declaration_text']??'')];
    if(($certificate['type']??'')==='certificado_general'
        && trim((string)($content['general_condition_label']??''))!=='')
        $lines[]='Condición general: '.(string)$content['general_condition_label'];
    if(($certificate['type']??'')==='constancia_atencion')
        $lines[]='Fecha de atención: '.(string)($content['care_date']??'');
    if(($certificate['type']??'')==='reposo_medico' && trim((string)($content['rest_days']??''))!==''
        && trim((string)($content['rest_start_date']??''))!=='')
        $lines[]='Reposo: '.(string)($content['rest_days']??'').' día(s), del '
            .(string)($content['rest_start_date']??'').' al '.(string)($content['rest_end_date']??'').'.';
    if(($certificate['type']??'')==='reposo_medico') $lines[]=(string)($content['return_note']??'');
    if(($certificate['type']??'')==='certificado_general') {
        $term=trim((string)($content['validity_term']??''));
        if($term!=='') $lines[]='El presente certificado tiene una vigencia de '.$term.' a partir de la fecha de emisión.';
        if(($content['observations_mode']??'')==='present') $lines[]=(string)($content['observations']??'');
    }
    $lines[]=(string)($content['closing_statement']??'');
    return implode("\n\n",array_values(array_filter(array_map('trim',$lines),static fn($v)=>$v!=='')));
}

/** Escaped shared composition for unsaved preview, frozen draft, viewer and print. */
function clinical_certificado_render_html(array $payload): string {
    $h=static fn(mixed $v):string=>htmlspecialchars(trim((string)($v??'')),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $patient=(array)($payload['patient_snapshot']??[]); $actor=(array)($payload['actor_snapshot']??[]);
    $brand=(array)($payload['branding']??[]); $cert=(array)($payload['certificate']??[]);
    $content=(array)($payload['content']??[]); $report=(array)($payload['report']??[]);
    $type=(string)($cert['type']??''); $sig=(array)($payload['signatures']['doctor']??[]);
    $image=(string)($sig['image_data']??'');
    $image=preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#D',$image)===1?$image:'';
    $logo=(string)($brand['logo_url_resolved']??'');
    $logo=(str_starts_with($logo,'/')&&!str_starts_with($logo,'//'))
        ||preg_match('#^https://[^\s]+$#i',$logo)===1
        ||preg_match('#^data:image/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#D',$logo)===1?$logo:'';
    $parts=[];
    $append=static function(mixed $value) use(&$parts):void {
        $text=trim((string)($value??'')); if($text!==''&&!in_array($text,$parts,true)) $parts[]=$text;
    };
    $append($content['declaration_text']??'');
    if($type==='certificado_general' && trim((string)($content['general_condition_label']??''))!=='')
        $append('Condición general: '.(string)$content['general_condition_label']);
    if($type==='constancia_atencion') $append('Fecha de atención: '.(string)($content['care_date']??''));
    if($type==='reposo_medico' && trim((string)($content['rest_days']??''))!==''
        && trim((string)($content['rest_start_date']??''))!=='')
        $append('Reposo: '.(string)($content['rest_days']??'').' día(s), del '
        .(string)($content['rest_start_date']??'').' al '.(string)($content['rest_end_date']??'').'.');
    if($type==='reposo_medico') $append($content['return_note']??'');
    if($type==='certificado_general') {
        $term=trim((string)($content['validity_term']??''));
        if($term!=='') $append('El presente certificado tiene una vigencia de '.$term.' a partir de la fecha de emisión.');
        if(($content['observations_mode']??'')==='present') $append($content['observations']??'');
    }
    $append($content['closing_statement']??'');
    $html='<article class="informe-doc-sheet certificado-doc-sheet document-sheet doc-base-sheet doc-base-sheet--letter doc-base-print-safe"><div class="doc-base-sheet-inner doc-base-body">';
    if(clinical_legal_document_professional_header_mode($payload)==='shown') {
        $html.='<header class="clinical-doc-head informe-doc-head doc-base-medical-header doc-base-header-block doc-base-print-safe">';
        if($logo!=='') $html.='<div class="clinical-doc-head-logo-slot"><img src="'.$h($logo).'" alt="Logo médico"></div>';
        $html.='<div class="clinical-doc-head-main"><div class="clinical-doc-doctor-name">'.$h($actor['full_name']??'').'</div>';
        foreach([($actor['specialty']??''),($actor['license']??'')!==''?'Cédula: '.$actor['license']:'',
            ($actor['specialty_license']??'')!==''?'Cédula esp.: '.$actor['specialty_license']:'',
            $brand['facility_visible']??'', $brand['location_line_visible']??''] as $line)
            if(trim((string)$line)!=='') $html.='<div class="clinical-doc-doctor-site">'.$h($line).'</div>';
        $html.='</div></header>';
    }
    $recipient=trim((string)($content['recipient_header']??''));
    if($recipient!=='') $html.='<section class="informe-doc-body-section doc-base-section"><div class="informe-doc-text doc-base-text">'.$h($recipient).'</div></section>';
    $html.='<div class="doc-base-title-block-wrap"><section class="informe-doc-title-block doc-base-title-block"><div class="informe-doc-title doc-base-title">'.$h($cert['type_label']??'Certificado médico').'</div></section></div>';
    $html.='<div class="doc-base-patient-block-wrap"><section class="informe-doc-patient-block doc-base-patient-meta">'
        .'<div class="informe-doc-patient-line"><strong>Paciente:</strong> '.$h($patient['full_name']??'').'</div>'
        .'<div class="informe-doc-patient-line"><strong>Edad:</strong> '.$h($patient['age']??'').' · <strong>Sexo:</strong> '.$h($patient['sex']??'').'</div>'
        .'<div class="informe-doc-patient-line"><strong>Fecha:</strong> '.$h($report['emission_date']??'').'</div>'
        .'</section></div><div class="doc-base-body-block">';
    if($parts!==[]) {
        $html.='<section class="informe-doc-body-section doc-base-section"><div class="informe-doc-text doc-base-text">';
        foreach($parts as $part) $html.='<p>'.nl2br($h($part)).'</p>';
        $html.='</div></section>';
    }
    $html.='<section class="informe-doc-sign doc-base-signature doc-base-print-safe"><div class="informe-doc-section-title doc-base-section-title">Firma del médico</div>';
    if($image!=='') $html.='<img class="informe-doc-sign-image" src="'.$h($image).'" alt="Firma del médico">';
    else $html.='<div class="informe-doc-sign-line"></div>';
    $html.='<div class="informe-doc-sign-meta doc-base-signature-meta">'.$h($sig['signer_name']??($actor['full_name']??'')).'</div></section></div>';
    $footer=array_filter([(string)($brand['group_name']??''),(string)($brand['address_line']??''),
        (string)($brand['consultorio_phone']??'')]);
    $html.='<div class="doc-base-flex-spacer" aria-hidden="true"></div><div class="doc-base-footer-block">'.$h(implode(' · ',$footer)).'</div>';
    return $html.'</div></article>';
}
