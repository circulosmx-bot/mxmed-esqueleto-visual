<?php
declare(strict_types=1);
namespace Profiles\Corrections;

final class ReportCatalog
{
    public const VERSION=1;
    public const CAPABILITY='public_profile_correction_review';
    public const OPERATION='PUBLIC_PROFILE_CORRECTION_REVIEW';
    public const CATEGORIES=[
        'NEW_OFFICE_ADDRESS'=>'Nuevo domicilio de consultorio',
        'INCORRECT_OFFICE_ADDRESS'=>'Domicilio de consultorio incorrecto',
        'CHANGED_PHONE'=>'Cambio de teléfono',
        'OUTDATED_CONTACT_INFORMATION'=>'Datos de contacto desactualizados',
        'INCORRECT_HOURS'=>'Horarios incorrectos',
        'DUPLICATE_PROFILE'=>'Perfil duplicado',
        'PHYSICIAN_RETIRED'=>'Posible retiro del profesional',
        'PHYSICIAN_DECEASED'=>'Posible fallecimiento del profesional',
        'WRONG_PERSON_OR_PROFILE'=>'Persona o perfil equivocado',
        'OTHER_FACTUAL_CORRECTION'=>'Otra corrección de información',
    ];
    public const RELATIONS=['PATIENT'=>'Paciente','FAMILY'=>'Familiar','FRIEND'=>'Amistad','COLLEAGUE'=>'Colega',
        'ASSISTANT'=>'Asistente','OFFICE_ADMINISTRATION'=>'Administración del consultorio','INSTITUTION'=>'Institución','OTHER'=>'Otro'];
    public const STATES=['PENDING_REVIEW'=>'Pendiente de revisión','NEEDS_CLARIFICATION'=>'Requiere aclaración',
        'REJECTED'=>'Informe rechazado','ACCEPTED_FOR_APPLICATION'=>'Informe aceptado; cambio pendiente de aplicación'];
    public const REASONS=['NEEDS_DETAILS'=>'Se necesitan más datos','NOT_SUPPORTED'=>'Información no sustentada',
        'OUT_OF_SCOPE'=>'Fuera del alcance del informe','DUPLICATE_REPORT'=>'Informe repetido',
        'FACTS_SUPPORTED'=>'Información respaldada por revisión'];
    public static function payload(array $input):array
    {
        self::fields($input,['category','explanation','relation','contact','privacy_ack']);
        if(!is_string($input['category']??null)||!isset(self::CATEGORIES[$input['category']])
            ||!is_string($input['relation']??null)||($input['relation']!==''&&!isset(self::RELATIONS[$input['relation']]))
            ||($input['privacy_ack']??null)!==true)throw new ReportDenied(400);
        $text=self::text($input['explanation']??null,20,2000);
        $contact=self::text($input['contact']??null,0,254);
        if($contact!==''&&!filter_var($contact,FILTER_VALIDATE_EMAIL))throw new ReportDenied(400);
        return ['catalog_version'=>self::VERSION,'category'=>$input['category'],'explanation'=>$text,
            'relation'=>$input['relation'],'contact'=>$contact,'privacy_ack'=>true];
    }
    public static function text(mixed $value,int $min,int $max):string
    {
        if(!is_string($value)||!preg_match('//u',$value)||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new ReportDenied(400);
        $value=trim(str_replace(["\r\n","\r"],"\n",$value));
        $length=preg_match_all('/./us',$value);
        if($length<$min||$length>$max)throw new ReportDenied(400);
        return $value;
    }
    public static function fields(array $input,array $allowed):void
    {if(array_diff(array_keys($input),$allowed)!==[])throw new ReportDenied(400);}
}
