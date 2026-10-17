<?php
declare(strict_types=1);
namespace Identity\Services;
/** System-owned executable capabilities, not a user-editable string registry. */
final class InternalCapabilityCatalog
{
    public const MANAGE_ADVISORS='internal_advisors_manage';
    public const APPROVE_INITIAL_PROFILE_OWNERSHIP='profile_initial_ownership_approve';
    public const CREATE_PHYSICIAN_PROFILE='profile_create';
    public const CURATE_PHYSICIAN_PROFILE='profile_curate';
    public const MANAGE_PROFILE_PUBLICATION='profile_publication_manage';
    public const ASSIST_PROFILE_OWNER='profile_support_assist';
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
        foreach([
            self::CREATE_PHYSICIAN_PROFILE=>['Crear perfiles médicos','Crear un perfil médico bajo un caso autorizado; no concede ownership.'],
            self::CURATE_PHYSICIAN_PROFILE=>['Curar perfiles médicos','Corregir información profesional bajo un caso y un perfil exactos.'],
            self::MANAGE_PROFILE_PUBLICATION=>['Gestionar publicación','Administrar el estado público sin cambiar ownership, plan o autoridad clínica.'],
            self::ASSIST_PROFILE_OWNER=>['Asistir administración de perfil','Operar temporalmente como personal real, sin suplantar al médico.'],
        ] as $key=>[$label,$description])$result[$key]=['key'=>$key,'domain'=>'profile_administration','label'=>$label,'description'=>$description,'delegable'=>true,'kind'=>'operational'];
        $key=self::MANAGE_ADVISORS;$result[$key]=['key'=>$key,'domain'=>'internal_governance','label'=>'Administrar asesores','description'=>'Gestionar relaciones de asesores; conceder permisos operativos requiere delegación adicional.','delegable'=>false,'kind'=>'governance'];
        $key=self::APPROVE_INITIAL_PROFILE_OWNERSHIP;$result[$key]=['key'=>$key,'domain'=>'profile_ownership','label'=>'Aprobar ownership inicial','description'=>'Participar como autoridad interna en una decisión R3 de ownership inicial de un perfil médico.','delegable'=>false,'kind'=>'governance'];
        $key='provider_claim_review';$result[$key]=['key'=>$key,'domain'=>'provider_claim','label'=>'Revisar claims de organizaciones','description'=>'Aprobar o rechazar el primer claim de una organización proveedora.','delegable'=>true,'kind'=>'operational'];
        $key='physician_accreditation_review';$result[$key]=['key'=>$key,'domain'=>'physician_accreditation','label'=>'Revisar acreditación médica','description'=>'Revisión humana de identidad, credenciales y vínculo con el perfil exacto; no concede ownership ni firma.','delegable'=>true,'kind'=>'operational'];
        $key='public_profile_correction_review';$result[$key]=['key'=>$key,'domain'=>'public_profile_corrections','label'=>'Revisar informes de perfiles','description'=>'Consultar informes privados y registrar revisión humana del perfil exacto; no aplica cambios ni concede autoridad.','delegable'=>true,'kind'=>'operational'];
        $key='provider_subscription_activate';$result[$key]=['key'=>$key,'domain'=>'provider_commercial','label'=>'Activar suscripción proveedora','description'=>'Crear o cancelar una suscripción organizacional mediante gobernanza interna; no representa un pago.','delegable'=>true,'kind'=>'operational'];
        return $result;
    }
    public static function require(string $key):array{return self::all()[$key]??throw new \RuntimeException('governance_unknown_capability');}
    public static function known(string $key):bool{return isset(self::all()[$key]);}
}
