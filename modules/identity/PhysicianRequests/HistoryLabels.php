<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;

/** Shared labels for immutable events in physician history and Director's Team view. */
final class HistoryLabels
{
    public const ACTIONS=[
        'PHYSICIAN_PROFILE_REQUEST_CHANGED'=>'Solicitud de perfil actualizada',
        'PROFILE_OWNERSHIP_ASSIGNED'=>'Propiedad inicial activada por el solicitante',
        'MEDIA_PROFILE_PHOTO_APPROVED'=>'Retrato aprobado',
        'MEDIA_REVIEW_CORRECTED_UPLOADED'=>'Imagen corregida',
        'MEDIA_PHYSICIAN_LOGO_APPROVED'=>'Logotipo aprobado',
        'MEDIA_LOGO_IMPROVEMENT_PROPOSED'=>'Mejora de logotipo propuesta',
        'MEDIA_LOGO_IMPROVEMENT_ACCEPTED'=>'Mejora de logotipo aceptada',
        'MEDIA_LOGO_IMPROVEMENT_DISCARDED'=>'Mejora de logotipo descartada',
        'MEDIA_REVIEW_REPLACEMENT_REQUESTED'=>'Reemplazo de imagen solicitado',
        'MEDIA_DOCTOR_GALLERY_APPROVED'=>'Imagen de galería aprobada',
        'PUBLIC_PROFILE_CORRECTION_REVIEWED'=>'Corrección de perfil revisada',
        'PUBLIC_PROFILE_CORRECTION_APPLICATION'=>'Corrección de perfil aplicada',
    ];
    public const REQUEST_OPERATIONS=[
        'EVIDENCE_LINKED'=>'Evidencia vinculada a la solicitud',
        'REQUEST_APPROVED'=>'Solicitud aprobada',
        'REQUEST_REJECTED'=>'Solicitud rechazada',
        'INFORMATION_REQUESTED'=>'Información adicional solicitada',
        'INFORMATION_RESPONDED'=>'Respuesta del solicitante registrada',
        'PROFILE_MATERIALIZED'=>'Perfil nuevo creado',
        'MEMBERSHIP_PREPARED'=>'Vínculo del solicitante preparado para propiedad inicial',
        'OWNERSHIP_ACTIVATED'=>'Propiedad inicial activada',
    ];
    public static function label(string $action,?string $operation=null,?string $type=null):string
    {
        if($action==='PHYSICIAN_PROFILE_REQUEST_CHANGED'){
            if($operation==='REQUEST_APPROVED')return $type==='CLAIM_EXISTING_PROFILE'?'Reclamo aprobado':($type==='REQUEST_NEW_PROFILE'?'Solicitud de perfil nuevo aprobada':'Solicitud aprobada');
            if($operation==='REQUEST_REJECTED')return $type==='CLAIM_EXISTING_PROFILE'?'Reclamo rechazado':($type==='REQUEST_NEW_PROFILE'?'Solicitud de perfil nuevo rechazada':'Solicitud rechazada');
            return self::REQUEST_OPERATIONS[$operation??'']??'Actividad de solicitud';
        }
        return self::ACTIONS[$action]??'Actividad registrada';
    }
}
