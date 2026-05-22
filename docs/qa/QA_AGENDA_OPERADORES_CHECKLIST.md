# QA Checklist · Agenda + Operadores (estabilización)

Fecha base: 2026-05-21  
Objetivo: no regresión de flujos estabilizados antes de nuevas funciones.

## A) Agenda

### Semana
- [ ] Semana inicia en día actual.
- [ ] Navegación de semana mantiene posición estable de flechas.
- [ ] Modo `Todos los consultorios` renderiza cards por consultorio sin colisiones visuales.
- [ ] Modo consultorio específico filtra correctamente.

### Día
- [ ] Vista Día muestra mini calendario + reloj/contexto + KPI + columnas Mañana/Tarde.
- [ ] Domingo sin horario muestra: `No hay horarios disponibles para este día.`
- [ ] Domingo/feriado se puede seleccionar (sin inventar disponibilidad).
- [ ] Scroll interno de Mañana y Tarde funcional y consistente.

### Bloqueos
- [ ] Bloqueo parcial desde slot disponible.
- [ ] Bloqueo de día completo usa ventanas reales del día (no 08:00–20:00 ficticio).
- [ ] Si hay cita real en ventana, bloqueo total respeta restricción.
- [ ] Desbloqueo actualiza Día inmediatamente.
- [ ] Semana y Día permanecen sincronizadas tras bloquear/desbloquear.

### Citas
- [ ] Nueva cita abre desde slot disponible con fecha/hora/consultorio correctos.
- [ ] Reprogramar funciona y refresca render.
- [ ] Cancelar funciona y refresca render.
- [ ] No show funciona y refresca render.
- [ ] Modal `Siguiente cita disponible` lista cards, permite elegir y navegar siguientes/anteriores.

### Reglas de UX
- [ ] Vista Mes no visible en selector principal.
- [ ] Leyenda origen de cita visible en ubicación actual.

## B) Operadores

### Alta
- [ ] Alta completa en wizard progresivo (Datos generales -> Acceso -> Permisos -> Enviar credenciales).
- [ ] Botón principal muestra `Siguiente` fuera del último paso.
- [ ] `Guardar operador` solo aparece en paso Permisos.
- [ ] Cancelar alta limpia formulario y contrae banda.
- [ ] Reingreso a Operadores no rehidrata drafts inconclusos.

### Alias / credenciales
- [ ] Alias obligatorio.
- [ ] Alias normaliza a MAYÚSCULAS, sin acentos, sin espacios.
- [ ] Alias valida longitud (3–15) y unicidad.
- [ ] Login automático sugerido (editable) y deduplicado.
- [ ] Contraseña temporal generada y regenerable.
- [ ] Validación mínima de login/contraseña antes de avanzar.

### Estados / cupo
- [ ] Máximo absoluto: 3 operadores contables (`active`, `paused`, `pending`).
- [ ] Archivados no cuentan para cupo.
- [ ] Si cupo = 0, alta adicional bloqueada.

### Seguridad y archivado
- [ ] Pausar acceso requiere código 6 dígitos.
- [ ] Reactivar acceso requiere código 6 dígitos.
- [ ] Eliminar operador requiere código 6 dígitos.
- [ ] Eliminar = archivar (no borrar).
- [ ] Operador archivado desaparece de registrados y aparece en `Operadores eliminados`.

### Historial
- [ ] Historial de acciones abre desde operador activo.
- [ ] Historial abre desde operador archivado.
- [ ] En archivados no mostrar etiqueta `Operador 01`; usar `Nombre completo (Alias)`.

## C) Persistencia y recarga
- [ ] Recargar conserva operadores registrados/archivados.
- [ ] Recargar conserva cupo calculado correctamente.
- [ ] Recargar no restaura drafts incompletos de alta.

## D) Recomendación de ejecución
- [ ] Ejecutar checklist en escritorio (Safari/Chrome).
- [ ] Repetir smoke básico en responsive.
- [ ] Registrar evidencia (capturas + hora + usuario QA) por bloque A/B/C.

## E) Operadores · Migración localStorage -> backend (F1.4)

Referencia de estrategia:
- [ ] Revisar `../OPERADORES_MIGRACION_LOCAL_BACKEND_MXMED.md` antes de ejecutar pruebas.

Read-through/Fallback (sin migración automática):
- [ ] Backend con datos: UI hidrata desde backend y KPI/cupo son coherentes.
- [ ] Backend vacío + local con datos: UI mantiene datos locales (no vaciado silencioso).
- [ ] Backend vacío + local vacío: estado vacío normal.
- [ ] Backend `db_not_ready` o sin `doctor_id` confiable: fallback local sin bloqueo visual.
- [ ] Verificar que frontend no dispara POST/PATCH de migración automáticamente.

Preview/Apply backend (F1.4B implementado):
- [ ] Preview reporta migrables, conflictos y cupo post-aplicación sin escribir datos.
- [ ] Conflicto alias duplicado se detecta y exige resolución.
- [ ] Conflicto login duplicado se detecta y exige resolución.
- [ ] Exceso de cupo contable bloquea apply correctamente.
- [ ] Apply sin confirmación explícita responde `400`.
- [ ] Apply con conflicto bloqueante responde `409`.
- [ ] Archivados se migran como archivados y no cuentan para cupo.
- [ ] Auditoría local se migra con mapeo estable y orden cronológico.
- [ ] Apply es transaccional (sin estado parcial ante error).
- [ ] Limitación conocida: no existe aún `preview_hash/token` entre preview y apply.

Seguridad y credenciales:
- [ ] No migrar contraseña temporal en texto plano.
- [ ] `GET /operators` no expone password temporal ni hash sensible.

Respaldo y post-migración:
- [ ] Se crea backup local antes del apply.
- [ ] El backup local no se elimina automáticamente en F1.4.
- [ ] Recarga posterior mantiene consistencia backend.

Evidencia técnica F1.4B (curl backend ejecutado):
- [ ] Preview backend vacío + local válido.
- [ ] Apply backend vacío + local válido.
- [ ] Preview alias duplicado.
- [ ] Preview login duplicado.
- [ ] Preview cupo excedido.
- [ ] GET `/operators` post-apply correcto y sin exposición de password.

## F) Cierre F1 Operadores · QA final reducido (2026-05-21)

Resultado consolidado: **PASS**.

- [x] `migration/apply` con `archived_at` ISO no devuelve `db_error`.
- [x] Migración de archivado sin login local funciona con warning `archived_login_generated`.
- [x] Backend vacío + local con datos conserva UI local y no vacía localStorage.
- [x] Backend falla + local con datos conserva UI local y no vacía localStorage.
- [x] Sin `doctor_id` confiable + local con datos conserva UI local (fallback estable).
- [x] Wizard local mantiene `Siguiente` antes de Permisos y `Guardar` solo en Permisos.
- [x] Smoke Agenda Semana/Día sin regresión evidente.

## G) Agenda RBAC F2 (matriz y criterios de aceptación)

Referencia:
- [ ] Revisar `../AGENDA_RBAC_MATRIZ_ACTORES_MXMED.md` antes de ejecutar QA F2.

Pruebas positivas por actor:
- [ ] `doctor` puede ejecutar operación completa interna de Agenda (incluye configuración y operadores).
- [ ] `operator` puede operar Agenda (crear, reprogramar, cancelar, **no_show**, bloquear/desbloquear, waitlist).
- [ ] `patient` opera solo flujo público (`public/availability`, `public/appointments/*`, OTP).

Pruebas negativas por actor:
- [ ] `operator` no puede acceder a `settings` ni `operators`.
- [ ] `patient` no puede consumir endpoints privados de Agenda.
- [ ] `call_center` no ejecuta acciones marcadas como `pendiente de decisión` hasta autorización explícita.
- [ ] `ai_operator` no ejecuta acciones marcadas como `pendiente de decisión` hasta autorización explícita.

Pruebas de seguridad:
- [ ] Intento de spoofing de payload (`actor_role`, `created_by_role`, `channel_origin`) debe ser rechazado por backend.
- [ ] `doctor_scope` mismatch en rutas privadas debe responder `403`.
- [ ] `GET /appointments/{id}/events` debe respetar scope y no exponer datos fuera de contexto.

Pruebas de auditoría:
- [ ] Mutaciones permitidas registran `actor_role`, `actor_id`, `channel_origin`.
- [ ] Eventos críticos (`create`, `cancel`, `no_show`, `reschedule`, `waitlist assign`) dejan rastro consistente.

## H) Cierre F2.3 RBAC backend mínimo (2026-05-21)

Resultado consolidado: **PASS**.

- [x] `operator` recibe `403` en `/operators/*`.
- [x] `operator` recibe `403` en:
  - `GET/PUT /settings`
  - `GET/PUT /schedule`
  - `PUT /consultorios`
  - `POST /geocode/google`
  - `GET /geocode/google-js-config`
- [x] `doctor` no recibe `forbidden` en rutas de Configuración/Operadores.
- [x] Sin header de rol se conserva compatibilidad actual (fallback a `doctor`).
- [x] `operator` sigue permitido en rutas operativas:
  - `appointments`
  - `availability`
  - `GET /consultorios`
  - `waitlist`
- [x] Rutas `public/*` sin afectación.
- [x] `php -l api/agenda/index.php` en PASS.

## I) Agenda Auditoría F2.5A (contrato documental)

Referencia:
- [ ] Revisar `../AGENDA_AUDITORIA_ACTOR_ATTRIBUTION_MXMED.md` antes de F2.5B.

Validaciones de contrato (documentales):
- [ ] Contrato canonico define: `entity_type`, `entity_id`, `action`, `actor_role`, `actor_id`, `channel_origin`, `occurred_at`, `metadata`.
- [ ] Compatibilidad backward documentada: `created_by_*` y `actor_*` conviven temporalmente.
- [ ] Roles canonicos documentados: `doctor`, `operator`, `patient`, `call_center`, `ai_operator`, `system`.
- [ ] `channel_origin` canonico documentado.
- [ ] Matriz de eventos incluida:
  - `appointment_created`
  - `appointment_rescheduled`
  - `appointment_canceled`
  - `appointment_no_show`
  - `waitlist_created`
  - `waitlist_assigned`
  - `availability_blocked`
  - `availability_unblocked`
  - `operator_created`
  - `operator_paused`
  - `operator_reactivated`
  - `operator_archived`
  - `operator_restored`
  - `operator_migrated_from_local`

Validaciones de QA a ejecutar en fases F2.5B-F2.5F:
- [ ] doctor crea/reprograma/cancela/no_show con actor consistente.
- [ ] operator crea/reprograma/cancela/no_show con actor consistente.
- [ ] patient/call_center/ai_operator registran actor y canal esperado en rutas autorizadas.
- [ ] `GET /appointments/{id}/events` expone attribution consistente en DTO final (cuando F2.5D cierre).
- [ ] spoofing de `actor_role`/`created_by_role`/`channel_origin` se valida con identidad autoritativa (pendiente de fase posterior).

## J) Cierre F2.5B-F2.5D (payload + persistencia + DTO events)

Resultado consolidado: **PASS**.

- [x] Frontend (`create/reschedule/cancel/no_show`) envía modelo legacy + canónico.
- [x] Frontend waitlist assign envía actor attribution compatible.
- [x] Alta médica equivalente conserva `channel_origin` y agrega actor canónico.
- [x] Backend write de reprogramación persiste `actor_role`, `actor_id`, `channel_origin`.
- [x] Create/cancel/no_show mantienen persistencia de actor sin regresión.
- [x] `GET /appointments/{id}/events` agrega DTO uniforme aditivo:
  - `action`
  - `entity_type`
  - `entity_id`
  - `occurred_at`
  - `created_by_role`
  - `created_by_id`
  - `actor_display_name`
  - `metadata`
- [x] Campos legacy se conservan (incluye `event_type`, `timestamp`, `notes`, `actor_role`, `actor_id`, `channel_origin`).
- [x] `notes` sigue como string raw.
- [x] `notes` JSON se refleja también en `metadata`.
- [x] `notes` no JSON se refleja como `metadata.notes_text`.
- [x] Reschedule conserva `from_consultorio_id`/`to_consultorio_id` en top-level y metadata.
- [x] UI real create/reschedule/cancel validada en runtime.
- [x] API runtime no_show/waitlist/alta equivalente validada.
- [x] Smoke Semana/Día PASS.
- [x] Fix Semana de corte horario validado en commit separado `8af3e7b`.
