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
            'physician_pending_summary_read'=>['Consultar resumen de pendientes médicos','Ver sólo conteos por sección de médicos con trabajo de seguimiento ya autorizado; no concede acceso a solicitudes, documentos ni decisiones.'],
            'physician_request_read'=>['Consultar solicitudes de perfiles','Consultar reclamos y solicitudes de perfil nuevo en su contexto exacto; no permite abrir evidencia protegida ni resolver.'],
            'physician_request_evidence_read'=>['Consultar evidencia de solicitudes','Abrir evidencia protegida de una solicitud autorizada; requiere también acceso vigente a solicitudes.'],
            'physician_claim_resolve'=>['Resolver reclamos de perfiles gratuitos','Resolver el reclamo exacto de un perfil gratuito sin propietario, con revisión y MFA; no permite transferencias ni acreditación profesional.'],
            'physician_new_profile_resolve'=>['Resolver solicitudes de perfil nuevo','Resolver la solicitud exacta y su creación canónica con MFA; no permite crear perfiles libremente, publicar ni activar planes.'],
        ] as $key=>[$label,$description])$result[$key]=['key'=>$key,'domain'=>'physician_requests','label'=>$label,'description'=>$description,'delegable'=>true,'kind'=>'operational'];
        foreach([
            self::CREATE_PHYSICIAN_PROFILE=>['Crear perfiles médicos','Crear un perfil médico bajo un caso autorizado; no concede ownership.'],
            self::CURATE_PHYSICIAN_PROFILE=>['Curar perfiles médicos','Corregir información profesional bajo un caso y un perfil exactos.'],
            self::MANAGE_PROFILE_PUBLICATION=>['Gestionar publicación','Administrar el estado público sin cambiar ownership, plan o autoridad clínica.'],
            self::ASSIST_PROFILE_OWNER=>['Asistir administración de perfil','Operar temporalmente como personal real, sin suplantar al médico.'],
        ] as $key=>[$label,$description])$result[$key]=['key'=>$key,'domain'=>'profile_administration','label'=>$label,'description'=>$description,'delegable'=>true,'kind'=>'operational'];
        $key='physician_locations_read';$result[$key]=['key'=>$key,'domain'=>'profile_administration','label'=>'Consultar consultorios asignados','description'=>'Consultar sedes administrativas en un ámbito explícito. Requiere consulta de perfiles; no concede Agenda, contacto privado ni cambios.','delegable'=>true,'kind'=>'operational'];
        $key='physician_administrative_read';$result[$key]=['key'=>$key,'domain'=>'profile_administration','label'=>'Consultar perfiles médicos asignados','description'=>'Buscar y consultar el resumen administrativo en un ámbito explícito. No concede operadores, documentos, clínica ni finanzas.','delegable'=>true,'kind'=>'operational'];
        $key='physician_operator_support_read';$result[$key]=['key'=>$key,'domain'=>'physician_operator_support','label'=>'Consultar operadores y colaboradores','description'=>'Diagnosticar vínculos, invitaciones y permisos en un perfil ya autorizado. No permite administrar operadores ni cambiar titularidad.','delegable'=>true,'kind'=>'operational'];
        $key=self::MANAGE_ADVISORS;$result[$key]=['key'=>$key,'domain'=>'internal_governance','label'=>'Administrar asesores','description'=>'Gestionar relaciones de asesores; conceder permisos operativos requiere delegación adicional.','delegable'=>false,'kind'=>'governance'];
        $key=self::APPROVE_INITIAL_PROFILE_OWNERSHIP;$result[$key]=['key'=>$key,'domain'=>'profile_ownership','label'=>'Aprobar ownership inicial','description'=>'Participar como autoridad interna en una decisión R3 de ownership inicial de un perfil médico.','delegable'=>false,'kind'=>'governance'];
        $key='provider_claim_review';$result[$key]=['key'=>$key,'domain'=>'provider_claim','label'=>'Revisar claims de organizaciones','description'=>'Aprobar o rechazar el primer claim de una organización proveedora.','delegable'=>true,'kind'=>'operational'];
        $key='professional_source_provision';$result[$key]=['key'=>$key,'domain'=>'professional_sources','label'=>'Verificar fuentes profesionales','description'=>'Recibir y revisar evidencia privada del expediente exacto y provisionar VID01/CRD02 con caso, reauth/MFA y auditoría; no acredita, asigna ownership ni firma.','delegable'=>true,'kind'=>'operational'];
        $key='physician_accreditation_review';$result[$key]=['key'=>$key,'domain'=>'physician_accreditation','label'=>'Revisar acreditación médica','description'=>'Revisión humana de identidad, credenciales y vínculo con el perfil exacto; no concede ownership ni firma.','delegable'=>true,'kind'=>'operational'];
        $key='public_profile_correction_review';$result[$key]=['key'=>$key,'domain'=>'public_profile_corrections','label'=>'Revisar informes de perfiles','description'=>'Consultar informes privados y registrar revisión humana del perfil exacto; no aplica cambios ni concede autoridad.','delegable'=>true,'kind'=>'operational'];
        $key='public_profile_correction_apply';$result[$key]=['key'=>$key,'domain'=>'public_profile_correction_application','label'=>'Aplicar correcciones autorizadas','description'=>'Aplicar un cambio tipado sobre un informe aceptado y recurso exactos, con reauth/MFA; no concede revisión, ownership o autoridad clínica.','delegable'=>true,'kind'=>'operational'];
        $key='provider_subscription_activate';$result[$key]=['key'=>$key,'domain'=>'provider_commercial','label'=>'Activar suscripción proveedora','description'=>'Crear o cancelar una suscripción organizacional mediante gobernanza interna; no representa un pago.','delegable'=>true,'kind'=>'operational'];
        return $result;
    }
    public static function require(string $key):array{return self::all()[$key]??throw new \RuntimeException('governance_unknown_capability');}
    public static function known(string $key):bool{return isset(self::all()[$key]);}
}
