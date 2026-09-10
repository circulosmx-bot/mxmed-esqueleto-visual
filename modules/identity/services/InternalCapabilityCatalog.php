<?php
declare(strict_types=1);
namespace Identity\Services;
/** System-owned executable capabilities, not a user-editable string registry. */
final class InternalCapabilityCatalog
{
    public const MANAGE_ADVISORS='internal_advisors_manage';
    public static function all():array
    {
        $media=[
            'media_review_read'=>['Consultar revisión de medios','Consultar solicitudes y sus derivados REVIEW.'],
            'media_review_approve'=>['Aprobar medios','Publicar el derivado REVIEW mediante aprobación explícita.'],
            'media_review_source_download'=>['Descargar originales','Descargar SOURCE privado con auditoría.'],
            'media_review_corrected_upload'=>['Cargar versión corregida','Guardar CORRECTED y regenerar REVIEW privado.'],
            'media_review_improve'=>['Proponer mejora de logotipo','Preparar y gestionar propuestas privadas de mejora.'],
            'media_review_request_replacement'=>['Solicitar otra imagen','Resolver una solicitud con NEEDS_WORK.'],
        ];$result=[];
        foreach($media as $key=>[$label,$description])$result[$key]=['key'=>$key,'domain'=>'media_review','label'=>$label,'description'=>$description,'delegable'=>true,'kind'=>'operational'];
        $key=self::MANAGE_ADVISORS;$result[$key]=['key'=>$key,'domain'=>'internal_governance','label'=>'Administrar asesores','description'=>'Gestionar relaciones de asesores; conceder permisos operativos requiere delegación adicional.','delegable'=>false,'kind'=>'governance'];return $result;
    }
    public static function require(string $key):array{return self::all()[$key]??throw new \RuntimeException('governance_unknown_capability');}
    public static function known(string $key):bool{return isset(self::all()[$key]);}
}
