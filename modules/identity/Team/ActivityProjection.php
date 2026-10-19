<?php
declare(strict_types=1);
namespace Identity\Team;

use PDO;

/** Bounded staff-panel projection of existing immutable audit events.
 * Called only after ReadService has authorized the exact staff row/category.
 * Never returns metadata, sessions, evidence bytes or personal/financial payloads.
 */
final class ActivityProjection
{
    private const ACTIONS = [
        'INTERNAL_GOVERNANCE_CHANGED'=>'Administración del equipo',
        'STAFF_ADMISSION_CASE_CHANGED'=>'Actualización de incorporación',
        'STAFF_MEDIA_CHANGED'=>'Actualización de documentación',
        'MEDIA_PROFILE_PHOTO_APPROVED'=>'Retrato aprobado',
        'MEDIA_REVIEW_SOURCE_DOWNLOADED'=>'Original consultado',
        'MEDIA_REVIEW_CORRECTED_UPLOADED'=>'Imagen corregida',
        'MEDIA_PHYSICIAN_LOGO_APPROVED'=>'Logotipo aprobado',
        'MEDIA_LOGO_IMPROVEMENT_PROPOSED'=>'Mejora de logotipo propuesta',
        'MEDIA_LOGO_IMPROVEMENT_ACCEPTED'=>'Mejora de logotipo aceptada',
        'MEDIA_LOGO_IMPROVEMENT_DISCARDED'=>'Mejora de logotipo descartada',
        'MEDIA_REVIEW_REPLACEMENT_REQUESTED'=>'Reemplazo de imagen solicitado',
        'MEDIA_DOCTOR_GALLERY_APPROVED'=>'Imagen de galería aprobada',
        'PHYSICIAN_CREDENTIAL_PROVISIONED'=>'Fuente profesional registrada',
        'PHYSICIAN_CREDENTIAL_VERIFIED'=>'Credencial profesional verificada',
        'PHYSICIAN_CREDENTIAL_REJECTED'=>'Credencial profesional rechazada',
        'PHYSICIAN_CREDENTIAL_INACTIVATED'=>'Credencial profesional inactivada',
        'PHYSICIAN_CREDENTIAL_REVOKED'=>'Credencial profesional revocada',
        'PHYSICIAN_EVIDENCE_CHANGED'=>'Evidencia profesional actualizada',
        'PHYSICIAN_AUTHORITY_CHANGED'=>'Autoridad profesional actualizada',
        'PUBLIC_PROFILE_CORRECTION_REVIEWED'=>'Corrección de perfil revisada',
        'PUBLIC_PROFILE_CORRECTION_APPLICATION'=>'Corrección de perfil aplicada',
    ];
    private const OPERATIONS = ['register'=>'Ingreso al equipo','suspend'=>'Acceso suspendido','reactivate'=>'Acceso reactivado','remove_advisor'=>'Baja definitiva del equipo','grant'=>'Permiso concedido','revoke'=>'Permiso revocado','delegate'=>'Delegación concedida','revoke_delegation'=>'Delegación retirada','prepare'=>'Solicitud preparada','update'=>'Datos personales actualizados','correct'=>'Datos personales corregidos','confirm'=>'Datos personales confirmados','invitation_issue'=>'Invitación preparada','invitation_reissue'=>'Invitación reemitida','invitation_accept'=>'Invitación aceptada','evidence_submit'=>'Documentos presentados','review_decision'=>'Revisión documental registrada','admission_complete'=>'Admisión completada'];

    public static function read(PDO $pdo, string $target, array $authority, ?string $case=null): array
    {
        $op="JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation'))";
        $cap="JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.capability'))";
        $subject="JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.target_account_id'))";
        $args=[];
        if($authority['class']==='DIRECTOR'){
            $scope="(real_actor_reference=? OR (action='INTERNAL_GOVERNANCE_CHANGED' AND $subject=?)";
            $args=['account:'.$target,$target];
            if($case!==null){$scope.=" OR (resource_type='staff_admission_case' AND resource_reference=?)";$args[]=$case;}
            $scope.=') AND action IN ('.implode(',',array_fill(0,count(self::ACTIONS),'?')).')';
            array_push($args,...array_keys(self::ACTIONS));
        }else{
            // Delegation authorizes only the matching grant history, not all Advisor activity.
            if(!$authority['delegations'])return ['state'=>'AVAILABLE','items'=>[],'has_more'=>false,'coverage'=>'DELEGATED_GRANTS'];
            $scope="action='INTERNAL_GOVERNANCE_CHANGED' AND $subject=? AND $op IN ('grant','revoke') AND $cap IN (".implode(',',array_fill(0,count($authority['delegations']),'?')).')';
            $args=[$target,...$authority['delegations']];
        }
        try{
            $q=$pdo->prepare("SELECT event_id,occurred_at_utc,action,outcome,real_actor_reference,resource_type,resource_reference,$op AS operation,$cap AS capability FROM platform_audit_events WHERE $scope ORDER BY occurred_at_utc DESC,event_id DESC LIMIT 101");$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        }catch(\PDOException $e){
            // Optional read source failures never produce a fabricated empty history.
            return ['state'=>'UNAVAILABLE','items'=>[],'has_more'=>false];
        }
        $items=[];
        foreach(array_slice($rows,0,100) as $e){
            // Media events have an action but no governance operation. Keep their
            // existing label without using NULL as an array offset (PHP 8.5).
            $description=self::OPERATIONS[$e['operation']??'']??self::ACTIONS[$e['action']];
            if(in_array($e['operation'],['grant','revoke','delegate','revoke_delegation'],true)&&isset(\Identity\Services\InternalCapabilityCatalog::all()[$e['capability']]))$description.=': '.\Identity\Services\InternalCapabilityCatalog::all()[$e['capability']]['label'];
            $items[]=['id'=>$e['event_id'],'at'=>str_replace(' ','T',$e['occurred_at_utc']).'Z','description'=>$description,'resource'=>$e['resource_reference'],'outcome'=>$e['outcome'],'direction'=>$e['real_actor_reference']==='account:'.$target?'BY_MEMBER':'ON_MEMBER'];
        }
        return ['state'=>'AVAILABLE','items'=>$items,'has_more'=>count($rows)>100,'coverage'=>$authority['class']==='DIRECTOR'?'STAFF_AND_RECORDED_OPERATIONAL_ACTIONS':'DELEGATED_GRANTS'];
    }
}
