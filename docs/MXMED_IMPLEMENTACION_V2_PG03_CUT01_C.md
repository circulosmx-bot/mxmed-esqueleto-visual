# MXMed PG-03 — Implementación CUT01-C

## 1. Identificador

`BE-ARCH/MXMed-PG03-CUT01-C-OTP-DDL-Privacy-Boundaries-01`.

## 2. Clasificación UI-0

Actividad backend/arquitectura sin UI, JavaScript, CSS, HTML, localStorage, Clinical ni AWS.

## 3. Baseline y checkpoint

Baseline `9eac1e9c10c2d7a99ff93b1d53319effea2f51a6`; checkpoint anotado `checkpoint/mxmed-product-refinement-v2-activity12`, objeto `628676d97700a0906251ccd3f2028974d01517cf`, desreferenciado al baseline.

## 4. Rama

`feature/mxmed-pg03-cut01-c-otp-ddl-privacy-boundaries-v2`, exclusiva de Actividad 13 y sin integración al programa.

## 5. Alcance exacto

Alcance cerrado de 31 archivos: `NEW_FILES=21`, `MODIFIED_FILES=10`, `SQL_FILES=14`. No existe archivo 32 ni expansión de alcance.

## 6. Migraciones declarativas

`MIGRATION_FILES=14`: siete forward y siete rollback, ordenados desde agenda settings y consultorios hasta OTP requests y appointment flows. Son artefactos declarativos; `MIGRATIONS_APPLIED=0`.

## 7. Superficies DDL

Siete superficies auditadas: agenda settings, consultorios, medical groups, memberships, review log, public OTP requests y public appointment flows. `DDL_RUNTIME_STATEMENTS_BEFORE=17`.

## 8. Contención runtime

La auto-creación y auto-alteración fue sustituida por comprobaciones read-only en `information_schema`. El runtime falla cerrado con `schema_not_ready`, no intenta reparar schema y queda `DDL_RUNTIME_STATEMENTS_AFTER=0`.

## 9. Flag canónico

`canonical_public_agenda=false`; implementado, default false, desactivado y sin autorización de activación. Sólo el booleano literal `true` sería elegible.

## 10. Fuentes y overrides

La configuración se carga sólo desde `modules/agenda/config/agenda.php`. Request, cliente y environment override permanecen false; rollout `R0`, modo `disabled`.

## 11. Provider OTP

`OtpProviderPort` es neutral y `RejectingOtpProvider` permanece no configurado, rechaza determinísticamente con `provider_not_configured`, no envía OTP y no selecciona proveedor comercial.

## 12. Rate limiting

`OtpRateLimitPolicy` exige parámetros explícitos completos y de tipos exactos. Intentos, ventanas, expiración, bloqueo, proveedor, canal, credenciales, SLA y jurisdicción permanecen `UNRESOLVED_PENDING_PARAMETER_APPROVAL`; fixtures de prueba no son defaults.

## 13. Repositorio OTP legacy

`PUBLIC_OTP_REPOSITORY_MODIFIED=false` y `NEW_CANONICAL_PUBLIC_OTP_REPOSITORY_DEPENDENCY=false`. `PublicOtpRepository` conserva su dependencia legacy, permanece fuera del alcance y no es requerido por el adapter canónico.

## 14. Privacidad

`DevOtpSender` conserva su firma y retorno legacy, pero no registra OTP, destinatario ni identificadores; sólo canal permitido, `delivery_mode=dev_compatibility` y `secret_logged=false`. `debug_code` y `otp_debug` fueron eliminados.

## 15. Anti-enumeración

Seis causas sensibles proyectan la misma superficie: `verification_unavailable`, mensaje genérico, HTTP 409, data null y metadata limitada a route/correlation_reference opacos.

## 16. Wiring dormido

PublicOtp y PublicAppointments sólo evalúan el flag server-side y conservan una referencia de clase. No instancian ni ejecutan `CanonicalPublicAgendaAdapter`; rutas, dispatcher y flujo legacy con flag false permanecen.

## 17. Pruebas y lint

Tres pruebas CUT01-C puras/estáticas y quince regresiones heredadas pasan directamente en la rama: `ACTIVITY13_TESTS=18/18`. Los quince PHP nuevos o modificados pasan `PHP_LINT=15/15`.

## 18. Rollback

El rollback de Git se valida con `git revert --no-commit <ACTIVITY13_COMMIT>` en worktree detached y tree idéntico al baseline. Los SQL rollback no se ejecutan; consultorios, auditoría, evidencia OTP y flows tienen retorno no destructivo.

## 19. Impacto

Cero conexiones DB durante implementación/QA, SQL ejecutado, DDL ejecutado, migraciones aplicadas, datos migrados, OTP real, cambios de rutas, UI, Clinical y AWS. Los trece blockers permanecen abiertos y readiness continúa NO-GO.

## 20. Estado

`CUT01_C_IMPLEMENTED_FLAG_OFF_READY_FOR_POSTVALIDATION_NOT_INTEGRATED`. Actividad 12 cerrada e integrada; Actividad 13 implementada pero no integrada; Actividad 14 bloqueada. Contador oficial 12/22, pendientes 10.
