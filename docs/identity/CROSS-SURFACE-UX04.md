# CROSS-SURFACE-UX04 — selección exclusiva y sedes autorizadas

Baseline `6ad0a0fb24a0bb8bc875d16e9d2353811fd5534b`; rama `feature/mxmed-cross-surface-ux04`. Sucesor acotado de [LINK03](CROSS-SURFACE-LINK03.md). Staff/sesiones/grants sintéticos con lectura administrativa original restringida: no es un entorno exclusivamente sintético.

## PRODUCT_POLICY

UI-2 autorizada expresamente por UX04: búsqueda y trabajo exclusivos, avatar, pestaña Consultorios e índice veraz. No rediseño general, escrituras sobre médicos, clínica, cuentas originales ni promoción a 8091. La aceptación visual permanece PENDING. Rollback: volver al commit padre en otro checkout/preview; no tocar los previews anteriores.

## SOURCE_IMPLEMENTATION

- `workspaceMode` separa búsqueda, trabajo y ausencia de selección. Entrar a búsqueda confirma descarte de borradores, limpia contexto/documentos y aumenta epochs; se bloquea mientras una operación está en curso. Las respuestas tardías no restituyen el perfil anterior. Los writers del navegador requieren modo trabajo; la autoridad real sigue en servidor.
- Avatar compacto: sólo `published_assets` del lector canónico Media, restringido a owner exacto, clasificación PUBLIC, estado READY y propósito DOCTOR_PROFILE_PHOTO. URL del mismo origen; sin archivos REVIEW/identidad ni imagen del otro médico. La fuente LINK03 no acredita retrato elegible, por lo que usa avatar neutral. Los eventos de carga quedan vinculados al contexto.
- `physician_locations_read` es una capacidad individual delegable del catálogo/matriz existentes. Requiere además `physician_administrative_read`, staff/cuenta/sesión vigentes y ámbito explícito de ambas capacidades para el mismo `origin:id`. No concede Agenda ni modificación. El Director usa el writer canónico previo para conceder/revocar; no SQL de grants.
- `admin_locations` usa el contrato `Source::locations`, cursor y límite 1–20 (consulta n+1), PDO de sólo lectura y la vista restringida ya existente. Proyecta exclusivamente consultorio_id, titulo, municipio y estado. No hay dirección, contacto ni estado operativo autorizado; la UI lo dice sin inventarlos.
- El resumen general conserva localidad y conteo acotado, pero ya no entrega la lista detallada de sedes. Se evita usar discovery como permiso implícito de consulta de sedes.
- Identidad, scope, revalidación posterior, audit canónico, fail-closed, parámetros preparados, límites y revocación preservan LINK03. No nuevo principal, grant SQL, vista, tabla o migración original. La composición de producción no selecciona automáticamente este adapter local.

## CONNECTIVITY / comparación y seis superficies

| Superficie | Ruta existente | Ejercicio disponible y límite |
|---|---|---|
| Director | `/internal/media-review/index.php?section=equipo` | Sesión propia sintética; grant/revocación Advisor. No se presenta como lector de la ficha médica original. |
| Asesor | `/internal/staff-workspace/index.php` | Contexto de origen exacto, nombre/especialidad/localidad, sedes y relaciones mediante capacidades separadas. |
| Médico | `/private-app.php` | BLOCKED: falta cuenta/sesión médica de prueba legítima y composición original autorizada. Staff no es médico. |
| Operador | `/private-app.php` | BLOCKED: existe relación canónica observable, pero falta sesión propia autorizada y composición; no se importan credenciales/cookies. |
| Público | `/profiles/doctor.php` (controlador, no ruta canónica resuelta) | BLOCKED: proyección no acredita publicación ni ruta SEO. `profile_status=active` no prueba elegibilidad. No se ejecuta el lector público que accede también a fuentes de agenda. |
| Proveedor | `/provider/index.php` | BLOCKED: no hay fuente/relación organizacional acreditada para el recurso en esta composición ni sesión propia; no se afirma inexistencia global de relación. |

El índice muestra ruta, disponibilidad, actor, ámbito, método seguro y requisito faltante. Sólo enlaza el panel autorizado para la sesión actual; ofrece comandos para ventanas independientes. Ninguna fila cambia rol o concede permisos.

| Dato comparado | Fuente/lector | Proyección/UI | Límite |
|---|---|---|---|
| Identificador | profiles_doctors / SqlSource → Reader | Asesor, `origin:canonical_id` | Nunca join por ID sintético numérico |
| Nombre/especialidad | display_name / specialty_primary | Encabezado del Asesor | Sin inferir acreditación |
| Localidad/sedes | consultorios / vista locations → admin_locations | Tres sedes del recurso autorizado | Sin dirección/contacto/estado operativo inventados |
| Publicación | No incluida | No verificada | Omisión, no discrepancia |
| Retrato | Media PUBLIC/READY o ausencia de fuente elegible | Avatar neutral para origen original | No usar evidencia privada |
| Relación operador | Vista relationships → admin_operators | Colaborador activo, titular no confirmado | No prueba login operador ni correspondencia con Director |
| Grants | Identity/internal_staff/internal_operator_grants | Director y respuesta autorizada del Asesor | Gobierno staff sintético; no membresía médica |

La comparación ejercida es fuente administrativa → Asesor y gobierno Director → autorización efectiva Asesor. No se atribuye QA UI a las cuatro superficies bloqueadas.

## VERIFICATION

51 verificaciones dirigidas PASS (39 principales + 12 suplementarias), además del preflight/setup owned. Evidencia privada en `/tmp/mxmed-ase-adm02imp01-_jyenrjw/ux04-evidence`, sin datos de fuente ni secretos copiados a Git.

- Chromium 1440×900, 1366×768 y 390×844: contexto/avatar/sedes, sin desbordamiento. Capturas inspeccionadas; WebKit 1366×768 PASS.
- Aurora → búsqueda → origen original → Aurora, respuesta original tardía, limpieza de sedes; no contexto previo visible. Borrador cancelado conserva trabajo; descarte aceptado limpia. Acción oculta no emite POST; mutación interceptada antes de enviar impide cambiar contexto hasta terminar.
- Grants concedidos/revocados por Director UI, no SQL. Sin grant de sedes →403; ámbito retirado →403; IDs/orígenes incorrectos →403; paginación 2+1 sin duplicación; límite excesivo →400; fuente ausente →503. Revocación oculta sedes ya abiertas.
- Audit canónico de lectura `admin_locations` y denegación, sin copia del perfil original en schema sintético. Requests/Media/Operator siguen respondiendo en su fuente autorizada. Permiso temporal de corrección Media retirado al terminar; no se consumió el pendiente.
- Login/logout/relogin Advisor real; sesiones independientes. Las rutas adicionales se solicitaron con la identidad staff existente y devolvieron 404 en el router owned: no se fabricó sesión médica/operador/proveedor.
- Hashes de las tres proyecciones restringidas y de SHOW GRANTS iguales antes/después. Sin DDL/DML original. Baseline protegido de worktrees/listeners PASS; 8091 conserva PID90106 y HEAD `f92f37f1958af5140a96c04cb52c5c1ce7af8e01` limpio.
- Primer intento de setup se detuvo por `workflow_source_drift` al coincidir una edición con sus pins; el runner cerró sólo su infraestructura. Segundo arranque y verificaciones completos. No se relajó el control de integridad.

## OPERATIONAL_STATUS

Sólo review loopback desechable, sin merge, producción ni cambios de 8091. Launcher `scripts/cross-surface-ux04-review.py <owned> open|open-director|open-advisor|credentials-director|credentials-advisor|mfa-director|stop`; verifica propietario, pins, HEAD, listener y expiración. Reabrir hace login canónico; no extiende TTL de Identity/MFA. Infraestructura hasta 24 horas, según el runner seguro existente.

Preview `https://127.0.0.1:50186`, owned `/tmp/mxmed-ase-adm02imp01-_jyenrjw`; vence **2026-10-22 22:07:42 -06:00 (CDMX)**. Cuentas de revisión `qa_director@example.test` y `qa_target@example.test`, contraseñas sólo por launcher/portapapeles. No se amplían TTLs.

[UX05](CROSS-SURFACE-UX05.md) sucede el límite de retrato/domicilio: verifica foto pública existente y amplía las dos proyecciones de lectura. El avatar UX04 no acreditaba ausencia de imagen.
