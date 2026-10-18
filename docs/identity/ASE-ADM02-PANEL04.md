# ASE-ADM02-PANEL04 — administración contextual del equipo

Base verificada `f9fe6950565ac3f15806b51246f49b5cf987801b`, sucesora de [UXFLOW03](ASE-ADM02-UXFLOW03.md), no retroceso a UXFUNC02. Rama aislada `feature/ase-adm02-panel04-team-administration`; worktree `mxmed-ase-adm02-panel04`. Solicitud explícita del Director PANEL04: orden, textos, jerarquía, controles y fuentes. Esta autorización sucede la ubicación anterior de Agregar integrante. Reorganización UI-2/UI-3 dirigida, componentes y tokens aprobados conservados; aceptación visual del Director **PENDING**, sin promoción a 8091.

## Política, fuente y conexión

- `PRODUCT_POLICY`: administración Director/Master según autoridad real; alta en asistente existente y detalle en secciones contraíbles. No nuevos roles, capacidades, políticas económicas, admisión alternativa ni elusión de MFA.
- `SOURCE_IMPLEMENTATION`: [partial](../../partials/identity/internal-team.php), [controlador](../../assets/js/internal-team.js), [proyección del equipo](../../modules/identity/Team/ReadService.php), [enriquecimiento de admisión](../../modules/identity/StaffWorkflow/Service.php), [historial acotado](../../modules/identity/Team/ActivityProjection.php). No DDL ni writer económico nuevo.
- `CONNECTIVITY`: encabezado/rol derivado servidor; logout POST canónico Identity/CSRF; menú de otros módulos retirado sólo de esta pantalla. Staff → columnas aprobadas; Master antes de Asesores; activos por defecto para ambas categorías; Agregar debajo de lista incluso vacía. Filtros compactos preservados. Documentos muestra tipo/versión/páginas desde manifest, y bytes sólo mediante revisión protegida existente.
- Orden solicitado: DATOS PERSONALES, DOCUMENTACIÓN, ACCESO Y FUNCIONES, CONDICIONES COMERCIALES (sólo asignación de ventas acreditada), GOBIERNO RESERVADO Y LÍMITES, CLIENTES, HISTORIAL. No se declara vendedor a todos los Advisors. Sin asignación acreditada hay seis secciones visibles; no se rellena una séptima comercial ficticia.
- Datos personales: update existente y versiones ADMIN_PREPARED; sin acceso nuevo Master a expedientes ni contraseñas. Documentación y revisión siguen Director-only con autoridad exacta, contraseña/MFA cuando corresponde. El límite postadmisión de UXFLOW03 permanece.
- Cinco grupos operativos existentes, borrador/estado mixto/guardar/revertir y writer inalterados. Baja definitiva conserva Identity/historia y revoca grants; suspendido no equivale a removido. Restricciones muestra estado real staff/cuenta/permisos; nombramiento y delegación Master conservan su gate dentro de su sección contextual.
- `OPERATIONAL_STATUS`: integración sólo en infraestructura desechable nueva propia, loopback, cuentas e imágenes artificiales; sin datos reales, correo externo, producción, merge o despliegue. El router permite únicamente el endpoint canónico logout adicional, no abre otras APIs Identity ni rutas de módulos.

## Fuentes comerciales y límites exactos

Inspección dirigida: contrato ASE-COM01 en el objeto aprobado `312ee388f93c892e6caf7e845b11e2b42f9c3565`, path `docs/identity/ASE-COM01-commercial-attribution-recurring-commission.md`; [mapa comercial vigente](../contexto/TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md), Identity/catalog/governance, modules/subscriptions y repositorios de checkout/acceptance/PI/eventos/profile_subscriptions, modules/billing, modules/profiles, api y database. Las coincidencias de derivación clínica, fuente FREE, creador/revisor/aceptante o descuentos fiscales no son atribución comercial.

**No localizados en esta fuente:** asignación funcional de vendedor, relación autorizada asesor–cliente, configuración por cliente de comisión, devengados/liquidaciones/comisiones pendientes, metas/reportes/disputas comerciales. Suscripciones y pagos tienen fuentes existentes, pero sin la relación comercial exacta no existe un join autorizado que pueda usarse aquí. No se leen finanzas ajenas ni se fabrica una cartera a partir de email, autoría, revisión o registro. El actor ADVISOR no prueba venta. Una ausencia de relación implementada no se representa como «cero clientes», «cero adeudo» ni «sin disputas».

Por ese motivo no se implementa un writer huérfano de porcentajes ni una atribución nueva; la autorización de persistir un porcentaje por cliente presupone un cliente legítimamente relacionado. D01–D08 de ASE-COM01 siguen acotadas: porcentaje/base, descuento/vigencia, liquidación, refunds/lapse, salida/suspensión, transferencias y cambios de plan. No se usa el 15% de descuento del cliente como comisión. El caso positivo vendedor+clientes+importes canónicos **no se puede verificar en este baseline**; no se sustituye con fixtures que simulen una fuente ausente. Se solicitó ubicación de una fuente aprobada adicional sin detener el resto de la corrección.

## Historial y autorización

Reutiliza `platform_audit_events` inmutable, mapper y productores canónicos existentes. Proyección específica del panel, no activación del servicio general dormido AuditReadService ni concesión de un permiso genérico de auditoría. Se consulta sólo después de autorizar categoría/target exactos mediante ReadService. Director: eventos permitidos de gobierno sobre esa cuenta; acciones propias registradas de admisión/media/acreditación/correcciones; cambios del caso vinculado por el recibo canónico de admisión. Master: sólo grant/revoke sobre ese Advisor con capability dentro de sus delegaciones vigentes. No expone perfil/documentos de admisión al Master.

DTO mínimo: ID del evento, fecha UTC, descripción comprensible, referencia del recurso, resultado y BY_MEMBER/ON_MEMBER. No devuelve payload de metadata, before/after, sesión, cookies, emails ni bytes privados. Orden descendente `(occurred_at_utc,event_id)`, sin union que duplique eventos, máximo 100 + indicador de más registros. Selección nueva limpia detalle y usa generaciones de petición; respuesta tardía de otro target no se renderiza. Fuente sin eventos y fuente indisponible se distinguen.

Cobertura limitada al allowlist de ActivityProjection y a eventos retenidos; no pretende toda la actividad del sistema. Referencia de recurso no equivale a identidad comercial de cliente. Lecturas de directorio/seguridad se excluyen para evitar ruido y datos de seguridad. No nuevo tracking, ledger, emisión de eventos ficticios ni inferencia de actividad desde creación de cuenta. No modifica productores ni integridad de auditoría.

## Verificación y operación de revisión

[Runner dirigido](../../scripts/ase-adm02-panel04-qa.py), [checks](../../scripts/ase-adm02-panel04-checks.py): reutilizan exclusivamente preparación sintética canónica de UXFLOW03; no ejecutan su suite histórica de navegador. MySQL/Valkey/TLS nuevos, guardas de ownership, root/hash/expiry y puertos protegidos; se añaden los readers Team al pin del entorno. Master limitado conserva delegación previa y recibe una delegación adicional mediante writer canónico exclusivamente CLI fixture guardado.

[Evidencia integrada](../evidence/ase-adm02-panel04/qa.json): **45 comprobaciones funcionales PASS**. El [primer cierre](../evidence/ase-adm02-panel04/failed-20261018T234337Z.json) detectó además un error JavaScript después de un logout canónico exitoso: navigate intentaba modificar el selector retirado del DOM. Se preserva ese FAIL histórico. El único cambio posterior de producto fue una guarda de existencia; se reconstruyó y comparó su hash anterior. [Regresión focalizada](../evidence/ase-adm02-panel04/smoke.json): **9 comprobaciones PASS**, incluida reproducción exacta del error anterior y ausencia tras la corrección, logout Director/Master, cookies eliminadas, filtros y tres viewports. No se repitieron admisiones, MFA ni regresiones históricas innecesariamente.

El runner inicial cerró sólo sus procesos tras el fallo; su datadir sintético quedó retenido. Se verificaron ownership0700/UID, root, hashes, expiración, datadir, puertos libres y cierre previo antes de recuperar **ese mismo** entorno. La recuperación reutiliza el proxy TLS firmado existente y obtiene sesiones nuevas mediante login canónico, sin repetir fixtures ni resetear registros. Su wrapper privado sólo controla los procesos que acaba de crear; al cerrar conserva el directorio privado para evidencia. [Verificación final](../evidence/ase-adm02-panel04/verification.json) documenta sintaxis, enlaces, hashes y protección del runtime original.

Capturas Chromium: [1440](../evidence/ase-adm02-panel04/screens/final-panel-1440.png), [1366](../evidence/ase-adm02-panel04/screens/final-panel-1366.png), [390](../evidence/ase-adm02-panel04/screens/final-panel-390.png). Inspección visual de encabezado/columnas y detalle móvil; no Safari físico ni aceptación del Director. Los casos positivos comerciales permanecen NOT_RUN por fuente faltante, no se cuentan entre los PASS.

Preview: `https://127.0.0.1:63259/internal/media-review/index.php?section=equipo`, hasta **2026-10-19 17:37:06 UTC−06:00**. Director con Bruno seleccionado, datos/documentos desplegados; Alma conserva edición y permisos, Celia baja definitiva, Diego suspendido, Fernanda pendiente y Gloria preparada. Master tiene sólo dos delegaciones operativas explícitas, sin admisión/documentos/baja definitiva. Registros legacy sin perfil y campos fuera de autoridad no reciben nombres inferidos.

Reabrir con autenticación canónica: `python3 /tmp/mxmed-panel04-review-foxop5ma/control.py open`. Cerrar: `python3 /tmp/mxmed-panel04-review-foxop5ma/control.py stop`. Chromium aislado y guía privada fuera del producto, con TOTP de ensayo normal; duración de infraestructura no prolonga sesiones/MFA. El URL solo no autentica un navegador nuevo. Un primer arranque fue interrumpido antes de terminar la preparación para corregir una expectativa del fixture Master: la semilla previa ya delegaba `media_review_approve`. Su runner propio dispuso únicamente sus recursos nuevos. No se cuenta esa interrupción como QA PASS.

## CTX-SYNC01

| Campo | Valor |
|---|---|
| CONTEXT_BASELINE | f9fe6950; UXFLOW03, UXFUNC01/02, VIS01 R2 y ASE-COM01 aprobado312ee388; mapas CTX vigentes. |
| AFFECTED_CAPABILITY_IDS | ID-01/02/07, COM-10; COM-02/03/06/07/08/09 inspeccionados, límites comerciales preservados. |
| AFFECTED_JOURNEY_IDS | J09: administración staff, lectura contextual y logout; J08 sin integración financiera nueva. |
| AFFECTED_DATA_OBJECTS | internal_staff, grants/delegaciones, versiones/manifest/completions staff y platform_audit_events; sólo readers nuevos, sin schema. |
| AFFECTED_CONTEXT_FILES | PANEL04, sucesor UXFLOW03, estado/router, inventario, datos, interacciones y mapa comercial. |
| CONTEXT_CHANGES_IMPLEMENTED | Jerarquía exacta, logout, fuentes documentales/historial acotado y límites comerciales precisos. |
| CONTEXT_VERIFICATION | 45 checks funcionales + 9 de regresión PASS; fallo histórico reproducido/corregido, sintaxis, enlaces y hashes revisados. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED; límites comerciales y visuales no se presentan como completados. |
| DOCUMENTATION_COMMIT | Commit mixto aislado; hash informado al entregar. |
| REMAINING_CONTEXT_GAPS | Relación comercial y asignación de ventas ausentes; importes/estadísticas no verificables; decisiones D01–D08, aceptación visual y operación productiva pendientes. |
