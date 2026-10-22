# Matriz canónica previa a concesión

Detalle completo de autoridad, ámbito, confirmaciones y audit en [JSON](capability-matrix.json).

| ID | Nombre | Clase | Consumidor conectado en este preview |
|---|---|---|---|
| `media_review_read` | Consultar revisión de medios | A | Sí |
| `media_review_approve` | Aprobar medios | A | Sí |
| `media_review_source_download` | Descargar originales | A | Sí |
| `media_review_corrected_upload` | Cargar versión corregida | A | Sí |
| `media_review_improve` | Proponer mejora de logotipo | A | Sí |
| `media_review_request_replacement` | Solicitar otra imagen | A | Sí |
| `physician_pending_summary_read` | Consultar resumen de pendientes médicos | A | Sí |
| `physician_request_read` | Consultar solicitudes de perfiles | A | Sí |
| `physician_request_evidence_read` | Consultar evidencia de solicitudes | A | Sí |
| `physician_claim_resolve` | Resolver reclamos de perfiles gratuitos | A | Sí |
| `physician_new_profile_resolve` | Resolver solicitudes de perfil nuevo | A | Sí |
| `profile_create` | Crear perfiles médicos | C | No |
| `profile_curate` | Curar perfiles médicos | C | No |
| `profile_publication_manage` | Gestionar publicación | C | No |
| `profile_support_assist` | Asistir administración de perfil | C | No |
| `physician_locations_read` | Consultar consultorios asignados | A | Sí |
| `physician_administrative_read` | Consultar perfiles médicos asignados | A | Sí |
| `physician_operator_support_read` | Consultar operadores y colaboradores | A | Sí |
| `internal_advisors_manage` | Administrar asesores | B | No |
| `profile_initial_ownership_approve` | Aprobar ownership inicial | B | No |
| `provider_claim_review` | Revisar claims de organizaciones | A | No |
| `professional_source_provision` | Verificar fuentes profesionales | A | No |
| `physician_accreditation_review` | Revisar acreditación médica | A | No |
| `public_profile_correction_review` | Revisar informes de perfiles | B | No |
| `public_profile_correction_apply` | Aplicar correcciones autorizadas | B | No |
| `provider_subscription_activate` | Activar suscripción proveedora | D | No |

A=17 (14 conectadas); B=4; C=4; D=1. D es exclusión comercial del ensayo, no obsolescencia.
Los tres consumidores A fuera del workspace no se activan ni se conceden.
