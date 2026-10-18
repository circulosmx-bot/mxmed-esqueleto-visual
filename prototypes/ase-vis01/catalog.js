// Generated from InternalCapabilityCatalog::all(); visual snapshot, never authorization.
window.ASE_VIS01_CATALOG = {
    "media_review_read": {
        "key": "media_review_read",
        "domain": "media_review",
        "label": "Consultar revisión de medios",
        "description": "Consultar solicitudes y sus derivados REVIEW.",
        "delegable": true,
        "kind": "operational"
    },
    "media_review_approve": {
        "key": "media_review_approve",
        "domain": "media_review",
        "label": "Aprobar medios",
        "description": "Publicar el derivado REVIEW mediante aprobación explícita.",
        "delegable": true,
        "kind": "operational"
    },
    "media_review_source_download": {
        "key": "media_review_source_download",
        "domain": "media_review",
        "label": "Descargar originales",
        "description": "Descargar SOURCE privado con auditoría.",
        "delegable": true,
        "kind": "operational"
    },
    "media_review_corrected_upload": {
        "key": "media_review_corrected_upload",
        "domain": "media_review",
        "label": "Cargar versión corregida",
        "description": "Guardar CORRECTED y regenerar REVIEW privado.",
        "delegable": true,
        "kind": "operational"
    },
    "media_review_improve": {
        "key": "media_review_improve",
        "domain": "media_review",
        "label": "Proponer mejora de logotipo",
        "description": "Preparar y gestionar propuestas privadas de mejora.",
        "delegable": true,
        "kind": "operational"
    },
    "media_review_request_replacement": {
        "key": "media_review_request_replacement",
        "domain": "media_review",
        "label": "Solicitar otra imagen",
        "description": "Resolver una solicitud con NEEDS_WORK.",
        "delegable": true,
        "kind": "operational"
    },
    "profile_create": {
        "key": "profile_create",
        "domain": "profile_administration",
        "label": "Crear perfiles médicos",
        "description": "Crear un perfil médico bajo un caso autorizado; no concede ownership.",
        "delegable": true,
        "kind": "operational"
    },
    "profile_curate": {
        "key": "profile_curate",
        "domain": "profile_administration",
        "label": "Curar perfiles médicos",
        "description": "Corregir información profesional bajo un caso y un perfil exactos.",
        "delegable": true,
        "kind": "operational"
    },
    "profile_publication_manage": {
        "key": "profile_publication_manage",
        "domain": "profile_administration",
        "label": "Gestionar publicación",
        "description": "Administrar el estado público sin cambiar ownership, plan o autoridad clínica.",
        "delegable": true,
        "kind": "operational"
    },
    "profile_support_assist": {
        "key": "profile_support_assist",
        "domain": "profile_administration",
        "label": "Asistir administración de perfil",
        "description": "Operar temporalmente como personal real, sin suplantar al médico.",
        "delegable": true,
        "kind": "operational"
    },
    "internal_advisors_manage": {
        "key": "internal_advisors_manage",
        "domain": "internal_governance",
        "label": "Administrar asesores",
        "description": "Gestionar relaciones de asesores; conceder permisos operativos requiere delegación adicional.",
        "delegable": false,
        "kind": "governance"
    },
    "profile_initial_ownership_approve": {
        "key": "profile_initial_ownership_approve",
        "domain": "profile_ownership",
        "label": "Aprobar ownership inicial",
        "description": "Participar como autoridad interna en una decisión R3 de ownership inicial de un perfil médico.",
        "delegable": false,
        "kind": "governance"
    },
    "provider_claim_review": {
        "key": "provider_claim_review",
        "domain": "provider_claim",
        "label": "Revisar claims de organizaciones",
        "description": "Aprobar o rechazar el primer claim de una organización proveedora.",
        "delegable": true,
        "kind": "operational"
    },
    "professional_source_provision": {
        "key": "professional_source_provision",
        "domain": "professional_sources",
        "label": "Verificar fuentes profesionales",
        "description": "Recibir y revisar evidencia privada del expediente exacto y provisionar VID01\/CRD02 con caso, reauth\/MFA y auditoría; no acredita, asigna ownership ni firma.",
        "delegable": true,
        "kind": "operational"
    },
    "physician_accreditation_review": {
        "key": "physician_accreditation_review",
        "domain": "physician_accreditation",
        "label": "Revisar acreditación médica",
        "description": "Revisión humana de identidad, credenciales y vínculo con el perfil exacto; no concede ownership ni firma.",
        "delegable": true,
        "kind": "operational"
    },
    "public_profile_correction_review": {
        "key": "public_profile_correction_review",
        "domain": "public_profile_corrections",
        "label": "Revisar informes de perfiles",
        "description": "Consultar informes privados y registrar revisión humana del perfil exacto; no aplica cambios ni concede autoridad.",
        "delegable": true,
        "kind": "operational"
    },
    "public_profile_correction_apply": {
        "key": "public_profile_correction_apply",
        "domain": "public_profile_correction_application",
        "label": "Aplicar correcciones autorizadas",
        "description": "Aplicar un cambio tipado sobre un informe aceptado y recurso exactos, con reauth\/MFA; no concede revisión, ownership o autoridad clínica.",
        "delegable": true,
        "kind": "operational"
    },
    "provider_subscription_activate": {
        "key": "provider_subscription_activate",
        "domain": "provider_commercial",
        "label": "Activar suscripción proveedora",
        "description": "Crear o cancelar una suscripción organizacional mediante gobernanza interna; no representa un pago.",
        "delegable": true,
        "kind": "operational"
    }
};
