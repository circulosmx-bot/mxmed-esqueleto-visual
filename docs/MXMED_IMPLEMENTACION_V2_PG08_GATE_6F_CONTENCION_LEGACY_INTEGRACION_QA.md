# MXMed PG-08 Gate 6F — Contención legacy e integración interna

## Baseline y propósito

Gate 6F se ejecuta sobre la rama `feature/mxmed-apis-data-permissions-privacy-foundations-v2`, HEAD `dc93f2a54859ba9bdb5bb4d56ea74930bf17e794`, siete commits sobre el programa oficial `be9cd4f067096a7798d1ea6941ec291981039823`. La integración es interna, backend containment UI-0/ UI-2 para el flujo aprobado, sin integración al programa ni readiness productivo GO.

El objetivo es contener superficies legacy fail-closed, retirar verificadores simulados, hacer puro el GET del Catálogo, preparar DDL/seed fuera del request y dejar una decisión de readiness explícita:

`NO_GO_LEGACY_BLOCKERS_PRESENT`.

## Fase A y callers

La inspección read-only cubrió `api`, `public`, `modules`, `assets`, `scripts`, pruebas y documentación funcional, excluyendo únicamente `.git`, `node_modules`, `vendor` y evidencia temporal.

El único caller ejecutable inicial fue `assets/js/perfil/consultorio/multisede.js`. La traza final de `modalConsulDelYes` confirmó que el flujo sólo quitaba pane/tab del DOM después de la falsa verificación; no existía endpoint backend destructivo, repositorio, SQL, soft delete, hard delete ni autosave posterior. La contención elimina ese camino fail-open antes de retirar los stubs.

## Verificadores legacy

`api/verify-password.php` y `api/verify-sms.php` responden HTTP 410 Gone con el contrato `legacy_identity_verification_retired`, sin leer body, password, code, sesión, cookies o tokens, sin logs de payload y sin CORS wildcard. No existe redirección ni bandera cliente de reactivación.

## Contención temporal de eliminación de consultorios

La capacidad `consultorio_secondary_delete` permanece en el DOM y en el flujo visual, pero su estado es `temporarily_disabled_pending_secure_reauthentication`. El modal `modalConsulDel`, el hook `modalConsulDelYes`, `data-target-n` y el texto “Eliminar consultorio” se conservan. El botón sólo confirma el mensaje aprobado; no elimina pane/tab, no llama API, no envía credenciales y no dispara persistencia.

Mensaje visible:

> Por seguridad, la eliminación de consultorios está temporalmente deshabilitada. Tus demás sedes y configuraciones permanecen disponibles.

### Reactivación pendiente de eliminación segura de consultorios

La capacidad no fue eliminada; la desactivación es temporal y la acción visual se conserva. Los stubs no deben regresar. La futura implementación usará Identity, actor resuelto exclusivamente server-side, autorización de ownership, reautenticación segura, auditoría PG-08, aprobación del director y QA funcional/visual. No existe fecha comprometida y el estado seguirá pendiente tras cerrar Gate 6F.

El contrato versionado es `modules/platform/config/consultorio-secondary-delete-reactivation-v1.json`. No se reactiva restaurando fetch legacy, con una constante JavaScript ni mediante una variable de cliente.

## Catálogo GET

`api/catalog/index.php` sólo ejecuta el SELECT existente, conserva la ruta pública, filtros, consulta por código postal y forma de respuesta. Se retiraron CREATE TABLE, bootstrap, seed, INSERT y ON DUPLICATE KEY UPDATE del request. Si la tabla no existe, el endpoint devuelve HTTP 503 con `catalog_not_initialized`; otros errores devuelven un error interno seguro sin SQL, stack trace, DSN o credenciales.

## SQL preparado no ejecutado

Se prepararon, pero no se ejecutaron:

- `modules/catalog/db/2026_07_21_01_create_catalog_cp_colonias.sql`;
- `modules/catalog/db/2026_07_21_02_seed_catalog_cp_colonias.sql`.

El DDL conserva definición e índices. El seed conserva las tres filas vigentes y es idempotente. No hay migrador runtime, startup hook, conexión PHP, DROP ni TRUNCATE.

`migration_prepared_not_executed=true`  
`seed_prepared_not_executed=true`

## Manifiesto de contención

`modules/platform/config/legacy-containment-pg08-v1.json` es válido, determinista y versionado. Registra verificadores `retired_fail_closed`, Catálogo `remediated_read_purity`, la familia de eliminación de consultorios como blocker temporal y los blockers diferidos de Profiles, Agenda y schema runtime.

Los contratos y servicios internos en `modules/platform` validan schema/version, superficies únicas, estados/riesgos, manifiesto ausente o inválido, superficies faltantes y readiness fail-closed. No escriben archivos, no consultan red/PDO y no están conectados a endpoints productivos.

## Blockers legacy no corregidos

- Profiles `transitional_open`: `api/profiles/index.php`, `DoctorContactPointsController.php` y `PrivateProfileController.php`; deferred domain migration.
- Agenda client-authoritative actor role: `X-Actor-Role`, `X-User-Role`, body, query, fallback doctor y sesión en `api/agenda/index.php`; deferred Agenda authorization.
- Runtime schema management: baseline de 21 archivos PHP productivos con DDL o `ensureTable/ensureSchema`; deferred schema migration. La lista no aumenta ni se refactoriza en Gate 6F.

## Readiness e integración interna

`PlatformFoundationReadinessEvaluator` integra resultados internos de Gates 6A–6F y conserva:

- `ready=false`;
- `deployment_decision=NO_GO_LEGACY_BLOCKERS_PRESENT`.

No existe botón, endpoint, flag o input de cliente para cambiar a GO. Support-assisted y break-glass siguen deshabilitados; `PrivilegedAccessActivationGate::mayActivate()` permanece siempre false. No hay wiring runtime de Platform ni del audit trail de Gate 6D.

## QA

La prueba `Gate6FLegacyContainmentIntegrationTest.php` verifica manifest, estados, riesgos, retiro 410, Catálogo read-only, tabla faltante, SQL no ejecutado, blockers, deuda de 21 archivos, UI-2 temporal, reactivación versionada, hard-stop 6E, cero wiring, cero AWS, cero datos reales y preservación de Gates 6A–6E.

Se ejecutan además las suites acumuladas de Gates 6A–6E, Identity, Capabilities, lint PHP, diff check, JSON, HTTP 8091 y puertos controlados.

## Rollback

El rollback de esta integración es reversible mediante el octavo commit y su commit padre. No se ejecutan migraciones ni seeds, no se escriben datos y no se modifica la infraestructura. La reactivación de eliminación requiere una microfase futura independiente y aprobación funcional.

## Alcance y no integración

No se modifican Agenda, Clínica, Profiles, Suscripciones, Identity productivo, AWS, `assets/js/app.js`, edge-config ni migraciones de Gate 6D. El programa oficial permanece sin integrar. PP-301 es la única decisión nueva y aparece una sola vez.

Contador oficial: `5/22`. Pendientes: `17`. Actividad 7: bloqueada.

