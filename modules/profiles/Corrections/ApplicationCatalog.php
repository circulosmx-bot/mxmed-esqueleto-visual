<?php
declare(strict_types=1);
namespace Profiles\Corrections;
final class ApplicationCatalog
{
    public const CAPABILITY='public_profile_correction_apply';
    public const OPERATION='PUBLIC_PROFILE_CORRECTION_APPLY';
    public const FAMILY='PUBLIC_PROFILE_PHONE';
    public const SENSITIVE=['PHYSICIAN_DECEASED','PHYSICIAN_RETIRED','DUPLICATE_PROFILE','WRONG_PERSON_OR_PROFILE'];
    public const CATEGORIES=['CHANGED_PHONE','OUTDATED_CONTACT_INFORMATION'];
    public const BLOCKED=[
        'OFFICE_PHONE'=>'Los teléfonos de consultorio usan otra fuente. No se reasignan desde un contacto del perfil.',
        'OFFICE_ADDRESS'=>'La dirección y su mapa requieren un adaptador autorizado propio.',
        'OFFICE_HOURS'=>'Los horarios afectan la disponibilidad de Agenda y requieren un adaptador propio.',
        'OTHER_CONTACTS'=>'Esta fase sólo admite el teléfono público ya publicado del perfil, sin usos privados o de citas.',
        'OTHER_PRESENTATION'=>'No hay un adaptador tipado aprobado para estos campos.',
        'SENSITIVE'=>'Identidad, publicación, duplicados, retiro, fallecimiento y autoridad requieren otro procedimiento.',
    ];
    public static function uuid(mixed $v):string
    {if(!is_string($v)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$v))throw new ReportDenied(400);return $v;}
    public static function command(array $data,PublicPhoneCorrectionAdapter $writer):array
    {
        ReportCatalog::fields($data,['command_id','report_id','report_version','profile_id','field_family','target','expected_version','proposed_value','verification_refs','facts_confirmed','reason']);
        self::uuid($data['command_id']??null);self::uuid($data['report_id']??null);
        if(!is_int($data['report_version']??null)||$data['report_version']<2||!is_string($data['profile_id']??null)
            ||!preg_match('/^[A-Za-z0-9._:-]{1,64}$/D',$data['profile_id'])||($data['field_family']??null)!==self::FAMILY
            ||!is_string($data['expected_version']??null)||!preg_match('/^[a-f0-9]{64}$/D',$data['expected_version'])
            ||($data['facts_confirmed']??null)!==true||!is_array($data['target']??null))throw new ReportDenied(400);
        $target=$data['target'];ReportCatalog::fields($target,['contact_point_id','consultorio_id']);
        if(!is_string($target['contact_point_id']??null)||!preg_match('/^[1-9][0-9]{0,18}$/D',$target['contact_point_id'])
            ||!array_key_exists('consultorio_id',$target))throw new ReportDenied(400);
        // Canonical contact_points reserves office associations for a different contract.
        if($target['consultorio_id']!==null)throw new ReportDenied(403);
        $refs=$data['verification_refs']??null;
        if(!is_array($refs)||!array_is_list($refs)||count($refs)<1||count($refs)>5)throw new ReportDenied(400);
        foreach($refs as $ref)if(!is_string($ref)||!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{7,95}$/D',$ref))throw new ReportDenied(400);
        $data['proposed_value']=$writer->normalize($data['proposed_value']??null);
        $data['reason']=ReportCatalog::text($data['reason']??null,8,500);$data['verification_refs']=array_values(array_unique($refs));
        $ordered=[];
        foreach(['command_id','report_id','report_version','profile_id','field_family','target','expected_version','proposed_value','verification_refs','facts_confirmed','reason'] as $key)$ordered[$key]=$data[$key];
        $ordered['target']=['contact_point_id'=>$target['contact_point_id'],'consultorio_id'=>null];return $ordered;
    }
}
