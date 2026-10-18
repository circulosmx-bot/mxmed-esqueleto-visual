# ASE-ADM02-UXFLOW03 — preparación guiada y administración contextual

Base verificada `3df2288bd4c14c58b98f5bd1921e5b8d5734337d`, UXFUNC02 limpio. Rama/worktree propios `feature/ase-adm02-uxflow03-wizard-separation` / `mxmed-ase-adm02-uxflow03`. El Director autoriza esta corrección UI-2 y la separación de tres recorridos. No promoción, datos reales ni modificación de 8091.

## Ficha UI-2 y componentes recuperados

Se recuperan `#onboarding`, `#onboarding-content`, `#onboarding-feedback`, `.wizard-steps`, `.wizard-field`, `.onboarding-summary` y botones Anterior/Continuar del prototipo aprobado `312ee388`, junto a su anchura de 720px y comportamiento móvil. Cuatro pasos: Persona → Categoría → Permisos → Revisión. VIS01 R2/COM01/ADM01 conservan sus decisiones; condiciones comerciales y gobierno Master no cambian.

La pantalla anterior mezclaba preparación, documentos y decisiones con contraseña/MFA permanentes. Ahora Agregar abre sólo el asistente; Pendientes abre revisión contextual; el integrante existente conserva sus secciones contraíbles y agrega edición personal versionada y acceso al caso real. Alternativa sin cambio: persistía la mezcla de tareas señalada por el Director. Riesgos: activación prematura, pérdida de borrador o salto de step-up; se preservan writers, versiones, revalidación y confirmaciones. Rollback: cerrar exclusivamente este preview y volver a UXFUNC02; no revertir datos ni relaciones terminales.

Adaptaciones mínimas al prototipo: categoría inicial Asesor y permisos sin asignación automática, conforme al flujo ya implementado. No se simulan propuestas de grants/Master sin almacenamiento canónico. El permiso de gestión se configura después de la admisión desde la matriz existente. Se retiran escenarios ficticios y credenciales permanentes; los datos de ensayo y su guía permanecen fuera de la interfaz de administración.

## Conexiones y autoridad

| Eje | Resultado y límite |
|---|---|
| PRODUCT_POLICY | Tres experiencias separadas; preparación administrativa distinta de confirmación personal. Revisión positiva distinta de admisión y de concesión de permisos. Contraseña/MFA en el límite de la acción protegida. |
| SOURCE_IMPLEMENTATION | Reutiliza StaffAdmission prepare/update, InvitationService issue, StaffWorkflow view/challenge/verify/evidence/decide, y el writer de admisión existente. Sin DDL ni segundo registry. |
| CONNECTIVITY | Asistente guarda caso e invitación; revisión muestra acciones según proyección canónica del caso/sesión; la ejecución conserva su gate independiente. Datos personales usan update/version/procedencia existentes. Applicant conserva página propia, separada del controlador Director. |
| VERIFICATION | 39 checks funcionales y 11 checks de presentación PASS en infraestructura nueva propia. |
| OPERATIONAL_STATUS | Sólo ensayo aislado sintético, loopback, vigencia máxima 24 h. Servicios originales y previews anteriores protegidos. Producción cerrada. |

Preparación: validación de nombre/apellido y correo, navegación entre pasos, guardado de borrador y revisión final; prepare y update devuelven versión/caso canónicos. El correo del destinatario permanece en la preparación en curso hasta issue, no se añade una copia a los datos personales. Un borrador retomado pide completar ese destinatario. Issue no confirma documentos ni activa staff. Los resultados inciertos no se reenvían automáticamente.

Revisión: acciones READ, CORRECTIONS, ACCEPT, REJECT y ADMIT representadas con botones contextuales. El backend proyecta disponibilidad; ACCEPT necesita lectura reciente del mismo conjunto en la misma sesión; ADMIT requiere revisión positiva vigente, categoría y ausencia de relación previa. Estas señales no sustituyen la revalidación transaccional ni amplían autoridad. Historial sin caso conectado se identifica como evidencia no disponible, no como revisión pendiente.

`challenge` ya no acepta email del navegador: consulta el correo de la cuenta del actor autenticado y usa el mismo GovernanceSecurityContext::reauthenticate con contraseña, rate limits y binding canónico. No cambia freshness, MFA, consumo único, target/case/set/proof ni auditoría. Los campos de contraseña/código se limpian tras uso y cancelación; no se almacenan en el formulario, archivos o navegador. El motivo se solicita al corregir/rechazar; otras acciones registran su descripción específica. Navegación no pide MFA.

Edición del integrante: sólo el Director recibe referencia del caso autorizado y disponibilidad de edición; los campos son nombre/apellidos/ciudad/estado, nunca cuenta/credencial. `update` crea ADMIN_PREPARED y puede invalidar la vigencia de la revisión anterior; no activa ni suspende automáticamente la relación staff. La actualización documental de una admisión ya completada conserva los límites del servicio existente; no se inventa un circuito de reacreditación. Los casos no elegibles muestran acciones deshabilitadas. Bajas carecen de edición personal en esta UI y de grants operativos.

Permisos y bajas conservan el editor, writer, catálogo, filtros ACTIVE/SUSPENDED/REMOVED y sus restricciones. No cambios de atribución comercial, Master o políticas legales. La aceptación del solicitante conserva el identificador de bundle sintético del backend; la guía privada aclara su alcance, sin afirmar textos legales productivos aprobados.

## Verificación y revisión

[Runner dirigido](../../scripts/ase-adm02-uxflow03-qa.py), [checks](../../scripts/ase-adm02-uxflow03-checks.py). La preparación de fixtures usa invitaciones, confirmación personal, imágenes artificiales, MFA real y admisión canónicos; no atajos sobre tablas protegidas. No batería histórica completa.

[QA integrada](../evidence/ase-adm02-uxflow03/qa.json), [presentación y solicitante](../evidence/ase-adm02-uxflow03/smoke.json), [verificación final](../evidence/ase-adm02-uxflow03/verification.json). Tres journeys reales Chromium: borrador/invitación sin activación; READ/ACCEPT/ADMIT mediante contraseña y TOTP independientes; edición personal, grants/revoke/recarga/baja. Negativos: email de actor enviado por navegador, autorrevisión, acceso entre casos, Master, proof ausente y rollback de preparación ante fallo audit. También se conserva denegación de grants a removidos y uso por suspendidos. No se afirman flujos completos de medios: su autorización se comprueba en la frontera real MediaReviewAuthority.

Chromium en 1440×900, 1366×768 y 390×844; primer paso (incluidos campos inferiores), revisión final, caso contextual y confirmación protegida caben y son operables. Capturas desktop/móvil inspeccionadas. La repetición del smoke amplió la comprobación móvil a ciudad, correo y vía de preparación; no repitió la batería funcional. Sin Safari físico ni aceptación visual final del Director.

Preview: `https://127.0.0.1:59235/internal/media-review/index.php?section=equipo`, hasta **2026-10-19 16:32:40 America/Mexico_City (UTC−06:00)**. Chromium muestra Director y Bruno activo. Agregar abre el asistente; Pendientes → solicitud de Fernanda abre la revisión con evidencia; datos personales y permisos quedan en el integrante seleccionado. Hay además una invitación preparada para Gabriela y una página del solicitante en contexto separado. Todo ficticio; no correo externo.

Abrir de nuevo con sesión canónica: `python3 /tmp/mxmed-uxflow03-review-w3i0n75b/control.py open`. El enlace aislado por sí solo no autentica otro navegador. La guía privada contiene credenciales de ensayo y MFA, no la interfaz del producto ni Git. Duración del entorno no prolonga sesiones.

Cerrar: `python3 /tmp/mxmed-uxflow03-review-w3i0n75b/control.py stop`. El runner dispone sólo sus recursos nuevos propios; previews anteriores, datos y 8091 permanecen intactos.

## CTX-SYNC01

| Campo | Valor |
|---|---|
| CONTEXT_BASELINE | 3df2288b; UXFUNC02 y contratos aprobados VIS01 R2, COM01, ADM01 y consolidación. |
| AFFECTED_CAPABILITY_IDS | ID-07 / COM-10; reutiliza ID-01/02/03, sin roles o capabilities nuevos. |
| AFFECTED_JOURNEY_IDS | J09: preparación, revisión contextual y administración de integrante. |
| AFFECTED_DATA_OBJECTS | Casos/versiones/invitaciones/revisiones/intenciones/receipts existentes; sin schema nuevo. |
| AFFECTED_CONTEXT_FILES | Contrato UXFLOW03, sucesor UXFUNC02, estado/router, inventario, datos e interacciones. |
| CONTEXT_CHANGES_IMPLEMENTED | Asistente aprobado conectado; actor de reauth derivado servidor; acciones por estado; edición personal canónica. |
| CONTEXT_VERIFICATION | 39 + 11 comprobaciones PASS; sintaxis PHP/JS/Python, hashes, diff y enlaces validados. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED. |
| DOCUMENTATION_COMMIT | Commit mixto de corrección, informado al entregar. |
| REMAINING_CONTEXT_GAPS | Operación real, Safari físico, aceptación visual final y actualización documental postadmisión fuera del flujo existente. |
