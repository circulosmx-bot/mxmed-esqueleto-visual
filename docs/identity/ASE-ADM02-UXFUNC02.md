# ASE-ADM02-UXFUNC02 — directorio por estado y permisos verificables

Base `3dfe09a2cc3f00bdb435b721493ec9a749f942e3`, último UXFUNC01 limpio y publicado. Rama aislada `feature/ase-adm02-uxfunc02`. Solicitud del Director: corregir listado, comprobar controles sobre un Advisor ACTIVE y conservar límites de autoridad.

## Ficha UI-2 y alcance

Pantalla Equipo interno: selector de estado en el directorio aprobado, sin CSS nuevo ni rediseño. Antes mezclaba ACTIVE, SUSPENDED y REMOVED; ahora Activos es el valor inicial. Suspendidos y Dados de baja consultan el mismo estado canónico. Pendientes abre solicitudes existentes, sin tratarlas como staff. La baja confirmada sale del listado activo y conserva su detalle/historia. Un botón permite volver a asesores activos desde un estado restringido.

La alternativa sin cambio mantenía el problema de visibilidad. Riesgo: ocultar historia o habilitar indebidamente permisos; se conserva lectura individual autorizada de cualquier estado, filtro histórico y controles canónicos. Rollback: volver a la rama anterior y cerrar sólo el nuevo ensayo; no revertir relaciones terminales ni borrar historia. La solicitud autoriza estos ajustes funcionales; no autoriza promoción a 8091.

| Eje | Implementación y límite |
|---|---|
| PRODUCT_POLICY | Baja definitiva conserva Identity/historia. ACTIVE permite únicamente capacidades autorizadas con consumidores existentes. Suspensión/baja no reciben nuevos grants. |
| SOURCE_IMPLEMENTATION | ReadService filtra estado en SQL antes de paginar, ACTIVE por defecto para listas Advisor; target mantiene categoría y autorización, sin imponer filtro inicial. No cambia writer, migración, catálogo ni editor de permisos. |
| CONNECTIVITY | Directorio conserva borrador/confirmación/comandos individuales. Reconsulta canónica del editor elimina tarjeta fuera del estado seleccionado, sin duplicar mutación. Solicitudes usa cases/view ya autorizados y el formulario de admisión existente; completions distingue caso admitido. |
| VERIFICATION | Ensayo dirigido nuevo: cuatro admisiones reales sintéticas con MFA; tres activos iniciales y uno suspendido, baja UI deja dos activos. Borrador/grupo/mixto, SQL, recarga, consumidor y concurrencia. Evidencia de cierre abajo. |
| OPERATIONAL_STATUS | Sólo infraestructura sintética propia, loopback y vencimiento máximo 24 h. Original, previews anteriores y producción preservados. |

## Datos y límites

Sin DDL nuevo. `internal_staff.status`, grants y receipts de admisión siguen siendo canónicos. `cases` agrega únicamente la presencia del recibo de admisión al listado ya autorizado (máximo 50 casos recientes, alcance heredado); no expone bytes privados ni nombres fuera del lector existente. Las solicitudes revocadas/admitidas no aparecen como pendientes. Los nombres del directorio vienen del perfil vinculado a una admisión completada, no de la referencia de cuenta.

Los controles de permisos no estaban averiados para ACTIVE: UXFUNC01 ya incluía borradores, grupo, estado mixto y rechazo de conflictos. La selección REMOVED explicaba el bloqueo observado. Se verifican sobre personas ficticias admitidas, sin reactivar identificadores dados de baja. Gobierno Master y capacidades sin consumidor aprobado conservan sus restricciones.

El ensayo conserva Alma Ficticia y Bruno Ensayo activos (cero grants y subconjunto, respectivamente), Diego Simulado suspendido, Celia Prueba dada de baja y Elena Pendiente como solicitud. Todos los apellidos de ensayo son Demostración. Sólo imágenes artificiales y correo inerte example.test.

## Verificación

[Runner dirigido](../../scripts/ase-adm02-uxfunc02-qa.py), [checks](../../scripts/ase-adm02-uxfunc02-checks.py). Los permisos se verifican contra SQL y `InternalOperatorAuthority` → `MediaReviewAuthority::requireRead`, la frontera real compartida por los consumidores de revisión de medios; no es prueba de toda la funcionalidad de medios ni de sus datos. No se repite la batería histórica de admisión. 27 comprobaciones integradas y 11 comprobaciones finales PASS: [QA](../evidence/ase-adm02-uxfunc02/qa.json), [smoke](../evidence/ase-adm02-uxfunc02/smoke.json), [cierre](../evidence/ase-adm02-uxfunc02/verification.json). Chromium en 1440×900, 1366×768 y 390×844, con operación móvil y capturas. Se verificó exactamente un evento de baja y conservación del perfil/cuenta/recibo.

El primer smoke esperaba el código HTTP original de ReadFailure; el router propietario heredado enmascara esas excepciones como 503 cerrado. Se ajustó la expectativa a esa respuesta exacta, sin cambiar el router ni relajar autoridad; no es una indisponibilidad de la navegación válida. Los tres viewports se revisaron automáticamente y las capturas de escritorio/móvil se inspeccionaron. No Safari físico ni aceptación visual final del Director.

## CTX-SYNC01

| Campo | Valor |
|---|---|
| CONTEXT_BASELINE | 3dfe09a2; UXFUNC01 y contratos VIS01 R2/ADM01/consolidación. |
| AFFECTED_CAPABILITY_IDS | ID-07 / COM-10; catálogo intacto. |
| AFFECTED_JOURNEY_IDS | J09: listado, estado, permisos y solicitudes. |
| AFFECTED_DATA_OBJECTS | Proyección de internal_staff.status y presencia de auth_staff_admission_completions; sin esquema nuevo. |
| AFFECTED_CONTEXT_FILES | UXFUNC02, sucesor UXFUNC01, estado/router, inventario, interacciones y datos. |
| CONTEXT_CHANGES_IMPLEMENTED | Activos por defecto, filtros históricos, navegación al formulario existente y fixtures canónicas. |
| CONTEXT_VERIFICATION | 27 + 11 checks PASS; sintaxis PHP/JS/Python, diff, hashes y enlaces válidos. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED. |
| DOCUMENTATION_COMMIT | Commit mixto de esta corrección, informado al entregar. |
| REMAINING_CONTEXT_GAPS | Activación real, Safari físico, aprobación móvil y gobierno Master independiente no acreditados por este ensayo. |

## Revisión disponible

Preview sintético: `https://127.0.0.1:56213/internal/media-review/index.php?section=equipo`; vence el 19 de octubre de 2026 a las 16:05:13, America/Mexico_City (UTC−06:00). Sólo loopback. Chromium abre Director autenticado, vista Activos y Alma seleccionada; guía privada local con MFA canónico. La duración del entorno no extiende la sesión: puede abrirse de nuevo con `python3 /tmp/mxmed-uxfunc02-review-tnb6yo0l/control.py open`.

Cerrar: `python3 /tmp/mxmed-uxfunc02-review-tnb6yo0l/control.py stop`. El runner termina sólo sus procesos propietarios; cerrar la ventana no detiene el entorno. Sin datos personales reales, correo externo, merge ni despliegue.
