# STAFF-AUTH-UX08 — sesión Director para administración staff

Baseline limpio `cd2a9ae940fa3aaa6e312bfd8294892d19bb7f04`, [LIFECYCLE03](STAFF-DOC-LIFECYCLE03.md). Rama/worktree aislados `feature/mxmed-staff-auth-ux08` / `mxmed-staff-auth-ux08`.

## Decisión aprobada y ficha UI-2

El Director autoriza expresamente que la sesión canónica autenticada baste para READ, ACK, ACCEPT, REJECT, CORRECTIONS y ADMIT en Equipo interno y permisos. Esta decisión sucede **sólo para esas acciones** la exigencia de reautenticación por operación de CONTROL07/VIS02/LIFECYCLE03. No es un período de gracia ni una prueba MFA reutilizable. Login Identity y su MFA aplicable, gobierno Master, recuperación y ownership mantienen sus contratos.

Superficie: panel y visor existentes; consulta directa, confirmación contextual para decisiones/ACK/admisión, motivo obligatorio para rechazo/correcciones. Sin cambios CSS o rediseño. Se eliminan campos de contraseña/código y ayudas sintéticas de estos diálogos. Riesgos: sesión revocada, autoridad cambiada, decisiones concurrentes y evidencia antigua. Reversión: detener exclusivamente la nueva composición y regresar al checkout LIFECYCLE03 conservado; no reutilizar una base migrada con fuente anterior ni eliminar historia. Aceptación visual PENDING.

## Fuente, conexión y datos

- PRODUCT_POLICY: sesión actual + Director ACTIVE, sin auto-revisión; lectura, ACK, decisión, admisión y permisos independientes.
- SOURCE_IMPLEMENTATION: `StaffWorkflow::authorize` valida actor/sesión/cuenta/credencial y locks canónicos, caso/versiones y elegibilidad. Crea una intención breve ligada a la sesión, no un proof de autenticación. Los writers existentes revalidan al consumir y auditan en la misma transacción. `completeAdmission` sigue siendo el único writer de alta.
- CONNECTIVITY: mismo boundary HTTP Identity/CSRF/origen/TLS/no-store; `authorize` sustituye challenge/verify sólo en StaffWorkflow. Endpoints de ejecución reciben el intent exacto sin credenciales ni proofs ficticios. UI mantiene recuperación de resultado incierto sin reenvío automático. Consultar no registra examen; ACK no acepta ni admite; admisión no concede grants.
- DATA: [migración aditiva](../../modules/identity/db/migrations/2026_10_21_02_staff_session_review.sql) agrega snapshot de versión de caso y revisión previa a `auth_staff_review_intents`, protegido por el trigger de inmutabilidad. Filas antiguas se conservan con snapshot nulo y no se consumen bajo esta política. Dos decisiones preparadas desde la misma revisión no pueden confirmar ambas: la segunda falla por revisión previa cambiada. Los cinco minutos son vigencia de intención/requisito de consulta reciente existente, nunca extensión de sesión ni de prueba MFA.
- PRIVACY: READ resuelve conjunto exacto actual/histórico y mantiene binding, cuenta titular, hash, AEAD, storage privado y audit antes de responder. Ninguna URL pública. ACK/decisiones sólo conjunto vigente; sustituciones no heredan revisión. No cambios a los writers del titular.
- SECURITY: la admisión valida también credencial activa bajo locks sin depender del consumo MFA anterior. CanonicalHttpSessionResolver/UnitOfWork y el writer comprueban sesión antes de commit; no se confía en clase enviada por navegador. Master/MFA/Identity y sus TTL/rate limits permanecen sin cambios. No nuevos roles o grants.
- OPERATIONAL_STATUS: sólo infraestructura loopback owned y datos artificiales; sin producción, 8091, merge ni entregas externas. Instalación de migración únicamente en la base sintética nueva.

## Verificación y revisión

37 checks PASS en [qa.json](../evidence/staff-auth-ux08/qa.json), incluidos Chromium/WebKit a 1366×768 y 390×844; 11 checks adicionales PASS en [security.json](../evidence/staff-auth-ux08/security.json). [Auditoría de lectura](../evidence/staff-auth-ux08/audit.json), [recorrido](../evidence/staff-auth-ux08/workflow.json) y [verificación final](../evidence/staff-auth-ux08/final-verification.json). La regresión detectó una excepción al limpiar el visor durante logout: el documento ya no contenía sus nodos. La limpieza ahora busca dentro del diálogo retenido y elimina las imágenes aun si está desconectado; no cambia permisos ni navegación. Se verificaron resultados HTTP/SQL/audit y Chromium/WebKit; no se repitió la suite histórica. La repetición dirigida conservó cuentas, admisión e historial. Se corrigieron dos fallos del harness: esperar el término de la carga antes de submit y mapear el UUID de audit a su SHA256 físico canónico. El proxy registró BrokenPipe al navegar/cerrar peticiones; los resultados canónicos y la ronda final completa pasaron. No se relajaron assertions ni se resetearon datos. Safari físico y aceptación visual del Director no están acreditados.

## Guía de revisión sintética

Director: `https://127.0.0.1:54030/internal/media-review/index.php?section=equipo`.
Cuenta QA: `qa_director@example.test`. Martha Figueroa Rosado conserva documentos actuales e históricos; Irene es Asesor activo sin grants automáticos. Selecciona Martha → DOCUMENTACIÓN → Consultar documentos. No se solicitan credenciales de nuevo para estas acciones; confirma expresamente examen/decisión/admisión cuando corresponda.

La infraestructura vence **2026-10-22 12:07:11 -06:00 (America/Mexico_City)**: máximo existente de 24 horas, sin extender sesiones Identity. Si caduca la autenticación, utiliza el formulario normal de Identity. El comando de contraseña sólo recupera el valor sintético al portapapeles para ese login.

```sh
cd /Users/circulodigital/Documents/GitHub/mxmed-staff-auth-ux08
python3 scripts/staff-auth-ux08-review.py /tmp/mxmed-ase-adm02imp01-b1fycplj credentials-director
python3 scripts/staff-auth-ux08-review.py /tmp/mxmed-ase-adm02imp01-b1fycplj open-director
python3 scripts/staff-auth-ux08-review.py /tmp/mxmed-ase-adm02imp01-b1fycplj open-advisor
python3 scripts/staff-auth-ux08-review.py /tmp/mxmed-ase-adm02imp01-b1fycplj stop
```

`open-director` abre Chromium y hace login legítimo, o enfoca la ventana owned existente; enfocar no renueva una sesión caducada. Para un navegador nuevo, cierra esa ventana aislada y ejecuta otra vez el comando. Advisor utiliza contexto independiente. `stop` únicamente señala al runner propietario para cerrar sus recursos desechables. El preview LIFECYCLE03 queda intacto.

## CTX-SYNC01

- CONTEXT_BASELINE: `cd2a9ae940fa3aaa6e312bfd8294892d19bb7f04`.
- AFFECTED_CAPABILITY_IDS: ID-02, ID-03, ID-07, COM-10.
- AFFECTED_JOURNEY_IDS: J09.
- AFFECTED_DATA_OBJECTS: snapshots de auth_staff_review_intents; mismos sets/reviews/acknowledgements/completions/audit.
- AFFECTED_CONTEXT_FILES: estado/router/inventario/interacciones/datos/registro visual y contratos sucedidos.
- CONTEXT_CHANGES_IMPLEMENTED: política de sesión Director y confirmaciones sin credenciales para acciones acotadas.
- CONTEXT_VERIFICATION: QA dirigida PASS, revisión del diff y enlaces relativos válidos; fuentes/puertos protegidos sin cambios.
- CONTEXT_SYNC_STATUS: PASS_UPDATED.
- DOCUMENTATION_COMMIT: mismo commit aislado de fuente/evidencia.
- REMAINING_CONTEXT_GAPS: aceptación visual y Safari físico pendientes; límites productivos previos conservados.
