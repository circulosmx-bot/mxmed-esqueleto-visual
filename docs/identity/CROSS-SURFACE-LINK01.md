# CROSS-SURFACE-LINK01 — límite verificado de conexión

Verificación local: 2026-10-21. Baseline aislado `1e01b139336c3ccfae38afdd36eb3e7e92b4df93`.
Resultado: **BLOCKED_SOURCE_AUTHORITY_BOUNDARY**, sin implementación ni activación.

## PRODUCT_POLICY

El Director autoriza inspección administrativa acotada del perfil de referencia y una conexión de sólo lectura cuando exista autoridad efectiva. No autoriza copiar el perfil, inventar solicitudes, modificar la base original ni convertir colaboración médica en autoridad staff.

## SOURCE_IMPLEMENTATION / CONNECTIVITY

El proceso original 8091 sirve `mxmed-step2-vitals-r1`, rama `ux/consultation-step2-vitals-r1`, HEAD `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`, limpio. Configuración local explícita, MySQL de loopback; se consultó mediante transacciones `READ ONLY`, cerradas con rollback. No se trasladaron credenciales al preview.

La fuente `profiles_doctors` confirma que el doctor_id histórico corresponde al perfil de referencia solicitado. Se comprobaron únicamente campos administrativos y relaciones de ese perfil; los resultados personales no se incorporan a fixtures ni a este documento. Hay una membresía collaborator activa con cuenta activa/verificada, sin owner activo encontrado; no se identificó esa cuenta como la cuenta usual del Director. Hay tres consultorios vinculados, ninguna invitación canónica y registros de operadores legados con estados distintos. No equivalen a delegación canónica vigente. No se encontraron tablas con nombre provider; esto no demuestra ausencia universal de relaciones proveedor.

`AdvisorSupportReader` revalida staff, grant `physician_operator_support_read` y ámbito Requests/Media sobre el mismo PDO. `EvidenceAuthority` valida cuenta/credencial y bloquea filas de la misma base. `staffWorkflowOwnedServices` exige base y datadir desechables. No existe en esta composición un lector federado ni un vínculo de autoridad de la sesión sintética al registro original. Apuntar el PDO original a esta composición rompería la separación. El grant de soporte no concede descubrimiento general de perfiles sin trabajo pendiente.

## VERIFICATION

- Identidad administrativa y relaciones: consulta dirigida, sólo lectura; sin consultas a pacientes o encuentros.
- Original: HEAD/worktree y proceso 8091 conservados; ninguna operación DDL/DML, reinicio ni reemplazo de sesión.
- Preview previo: launcher reporta `HEALTHY_OWNED_SYNTHETIC`; sigue sirviendo el baseline previo. No contiene una integración de este perfil.
- Director: administración de grants existente, no reprobada en navegador en esta tarea.
- Advisor: conexión real bloqueada; no se afirma búsqueda/contexto/soporte funcional del perfil de referencia.
- Médico: comparación administrativa por fuente, no login de médico.
- Operador: relaciones canónicas y legadas distinguidas; pantalla no verificada.
- Público: repositorio/controlador inspeccionados; elegibilidad/publicación efectiva no verificada. El lector público también consulta existencia de citas; no se ejecutó para ampliar innecesariamente el acceso.
- Proveedor: no verificado; no se inventó vínculo.
- Chromium, revocación E2E, responsive y seis pantallas: no ejecutados, porque no existe conexión autorizada que demostrar. Los 53 checks del baseline no son QA de esta integración.

## OPERATIONAL_STATUS / siguiente mínimo

Hace falta una composición de lectura administrativa que mantenga la autoridad staff en su base aislada, limite explícitamente el recurso original, revalide grants/revocación, preserve auditoría y lea sólo columnas autorizadas de la fuente local sin entregar su credencial administrativa al servidor preview. También falta separar búsqueda autorizada de la bandeja de trabajo. Este contrato no está implementado ni se sustituye por membresías médicas o datos copiados. La tarea se detiene en este límite conforme al apartado 11; no se declara COMPLETE.

Capacidades/journeys relacionados: `physician_operator_support_read`, `media_review_read`, `physician_request_read`; OP-01..03 / J07. Sin modificaciones de sus contratos efectivos. Véase [baseline de soporte](ADVISOR-OPERATOR-SUPPORT01.md).
