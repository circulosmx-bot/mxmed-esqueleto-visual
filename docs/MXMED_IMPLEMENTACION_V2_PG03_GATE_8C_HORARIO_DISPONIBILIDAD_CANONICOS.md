# PRODUCT-IMPLEMENTATION/MXMed-PG03-Activity08-Gate8C-CanonicalScheduleAvailability-01

## Resultado

`PASS_ACTIVITY_8_GATE_8C_CANONICAL_SCHEDULE_AVAILABILITY_IMPLEMENTED`

## Resumen ejecutivo

Gate 8C implementa una capa de dominio UI-0, pura, determinista, inmutable y sin conexión runtime para representar la fuente canónica y versionada de horario por `profile_id` y `consultorio_id`, y para calcular una proyección de disponibilidad con ventanas, feriados, overrides, colisiones y slots.

La disponibilidad es un read model calculado, no una fuente editable. El cálculo recibe una fecha explícita, conserva la timezone IANA canónica, falla cerrado ante inconsistencias y no consulta datos reales, rutas, repositorios, SQL, red ni reloj global.

## Baseline

- Rama: `feature/mxmed-pg03-agenda-foundations-v2`.
- HEAD inicial: `73852741f02a56d943269f45e2153bfe5eb0a03d`.
- Programa oficial: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Gate 8A: `9bb7d8f8ec448edd8a0d77dabd44834b9d1f98af`, `POSTVALIDATED_COMPLETE`.
- Gate 8B pre-correction: `1000e5860702212f5303f5095bf0ec901276bae6`.
- Gate 8B postvalidated: `73852741f02a56d943269f45e2153bfe5eb0a03d`, `POSTVALIDATED_COMPLETE_WITH_ROUTE_POLICY_CORRECTED`.
- Checkpoint base: `checkpoint/mxmed-product-refinement-v2-activity07`.
- Cuatro commits sobre el programa antes de Gate 8C.

## Autoridad DEC-013B

La implementación sigue la aprobación directorial de DEC-013B. El horario canónico es específico por perfil y consultorio, versionado, inmutable y separado de la disponibilidad calculada. No se autoriza aún reconciliar tablas legacy ni cambiar rutas.

## Dependencias Gate 8A y Gate 8B

Gate 8C usa `Agenda\\Contracts\\ScheduleAvailabilityContract` de Gate 8A como read model y conserva `isReadModel() === true`, `editableAuthority() === false` y `mode = calculated_read_model`. Gate 8B permanece intacto y sus actores server-side no se conectan en este gate.

## Fuente canónica versionada

`CanonicalScheduleVersion` exige identificador, versión entera positiva, `profile_id`, `consultorio_id`, timezone IANA, fechas de vigencia, duración de 5–720 minutos, gap de 0–720 minutos y ventanas semanales. La vigencia usa el intervalo de fechas `[effective_from, effective_until)` cuando existe límite superior. La instancia es readonly y sus ventanas se ordenan de forma estable.

## Identidad profile/consultorio

El selector recibe ambos identificadores y sólo considera una versión cuya identidad coincida exactamente. No hay alias de doctor, selección del primer consultorio ni fallback entre consultorios. Dos consultorios del mismo perfil se prueban con versiones, timezone, overrides y colisiones aislados.

## Timezone

La timezone debe ser un identificador IANA validado mediante `DateTimeZone` y la lista de identificadores oficiales. La fecha objetivo se recibe explícitamente y el weekday se calcula en esa timezone, sin usar una timezone global.

## Ventanas semanales

`WeeklyScheduleWindow` valida weekday 1–7 y horas ordenadas `HH:MM`. Las ventanas del mismo día no pueden traslaparse; las adyacentes sí son válidas. La semántica de intervalos es semiabierta `[start, end)`.

## Selección de versión

`CanonicalScheduleVersionSelector` filtra `effective_from <= fecha` y `effective_until = null` o `fecha < effective_until`. Cero resultados produce `canonical_schedule_missing`; más de uno produce `canonical_schedule_ambiguous`; una identidad incompatible produce `profile_mismatch` o `consultorio_mismatch`. Nunca elige silenciosamente una versión cercana.

## Semántica de intervalos

Las operaciones ordenan, unen y deduplican intervalos de forma determinista. La adyacencia no es traslape: `09:00–10:00` y `10:00–11:00` son distintos. Las sustracciones sólo afectan intersecciones estrictas.

## Overrides

`AvailabilityOverride` acepta únicamente `open` o `close`, identificador, perfil, consultorio, fecha, ventana opcional, `full_day`, fuente backend y `active`. Overrides inactivos, de otra identidad o de otra fecha se ignoran. Un cierre de día completo limpia la disponibilidad; un cierre parcial resta; una apertura agrega o reabre. Un override inválido falla cerrado.

## Feriados

El calculador recibe explícitamente una colección `HolidayClosure` ya resuelta. Un feriado activo de la fecha cierra las ventanas base. Una apertura explícita posterior puede reabrir un rango. No se llama a `HolidayMxProvider`.

## Colisiones

`CollisionWindow` recibe identidad, fecha, intervalo, fuente mínima y actividad. No contiene paciente, teléfono, email ni motivo clínico. Las colisiones de otra identidad se ignoran, se normalizan y se aplican como sustracción final; una colisión nunca puede ser reabierta por un override.

## Precedencia de cálculo

La secuencia canónica es:

1. `canonical_version`;
2. `base_windows`;
3. `holiday_closure`;
4. `close_overrides`;
5. `open_overrides`;
6. `normalize_windows`;
7. `collisions`;
8. `slots`;
9. `read_model`.

## Generación de slots

Cada slot ocupa `duration_minutes`; el siguiente candidato avanza `duration_minutes + gap_minutes`. No se crean slots parciales, fuera de una ventana, duplicados o desordenados. Cada slot conserva fecha y timezone explícitas.

## Cambio de consultorio

La prueba sintética usa un mismo perfil con `consultorio-a` y `consultorio-b`, distintas ventanas y timezone. Un request de A no selecciona B; overrides y colisiones cruzadas no filtran disponibilidad; el read model conserva exactamente el consultorio solicitado.

## Read model

`CanonicalAvailabilityResult` envuelve `ScheduleAvailabilityContract` y agrega versión, fecha de cálculo, overrides aplicados, feriado, conteo de colisiones y slots. El contrato permanece calculado, no editable y separado de cualquier autoridad de escritura.

## Fail-closed

Se distinguen `canonical_schedule_missing`, `canonical_schedule_ambiguous`, `profile_mismatch`, `consultorio_mismatch`, `invalid_timezone`, `invalid_effective_range`, `invalid_weekday`, `invalid_window`, `overlapping_windows`, `invalid_override`, `invalid_collision`, `invalid_duration` e `invalid_gap`. Una inconsistencia nunca se convierte silenciosamente en disponibilidad abierta.

## Determinismo

Todas las entradas son argumentos explícitos. No se usa fecha actual, reloj global, aleatoriedad, UUID, entorno, sesión, superglobal, almacenamiento, red o efectos secundarios. La misma entrada produce la misma serialización.

## Seguridad y privacidad

La capa no recibe credenciales, payloads libres ni datos de pacientes. Identidad de perfil y consultorio es exacta; las colisiones conservan sólo una fuente mínima. La autoridad editable queda deshabilitada.

## Límites de Gate 8C

No hay lectura de base de datos, escritura de horario, migraciones ejecutadas, reconciliación de tablas, shadow read, dual read, cutover, cambio de API pública ni cálculo conectado a citas reales.

## No runtime wiring

Router legacy sin cambios; controllers y repositories sin cambios; fuente legacy todavía no reconciliada; migración no iniciada; shadow/dual-read no iniciado; runtime wiring `0`; route behavior changes `0`; SQL `0`; datos reales `0`; OTP real `0`; citas reales `0`; merges reales `0`; AWS writes `0`; puerto 8091 intacto.

## Compatibilidad pendiente

La compatibilidad con tablas y rutas legacy queda pendiente de un gate posterior con decisión explícita, validación de paridad y rollback. Gate 8D no está iniciado.

## Pruebas

`Gate8CCanonicalScheduleAvailabilityTest.php` usa fixtures sintéticos para validar modelo, inmutabilidad, timezone, weekday, ventanas, vigencia, selector, precedencia, overrides, feriados, colisiones, slots, aislamiento de consultorio, read model, determinismo y archivos protegidos. También se ejecutan las regresiones Gate8A, Gate8B, Gate6B, Gate6F, Identity, ExistingCapabilityAuthorityService y CurrentSubscriptionFeatureAccessReadModel.

## Rollback

Rollback principal: `git revert --no-edit <gate8c_commit>`.

Retorno alterno de inspección: `git switch -c recovery/activity08-gate8c 73852741f02a56d943269f45e2153bfe5eb0a03d`.

No se ejecuta rollback. El bundle preflight se conserva.

## Puntos seguros

- Programa: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Gate 8A: `9bb7d8f8ec448edd8a0d77dabd44834b9d1f98af`.
- Gate 8B pre-correction: `1000e5860702212f5303f5095bf0ec901276bae6`.
- Gate 8B postvalidated: `73852741f02a56d943269f45e2153bfe5eb0a03d`.
- Bundle: `/tmp/mxmed-activity08-gate8c-preflight-v2/activity08-before-gate8c.bundle`.

## Evidencia

La evidencia temporal se entrega en `/tmp/mxmed-activity08-gate8c-canonical-schedule-availability-v2/` con exactamente 10 JSON y 3 textos: estado baseline, archivos cambiados, puntos seguros, modelo canónico, selección, precedencia, aislamiento, read model, resultados de pruebas, QA, estado final de Git, rollback y ausencia de runtime.

## Git

Se crea un quinto commit aditivo con el mensaje exacto `feat(agenda): implementa horario y disponibilidad canonicos gate 8C`. No se hace amend, rebase, squash, merge, cherry-pick, reset destructivo ni force push. El programa oficial permanece sin integrar, upstream 0/0 y el worktree limpio.

## Estado del programa

- Gate 8C: `COMPLETE`.
- Gate 8D: `NOT_STARTED`.
- Actividad 8: `IN_PROGRESS_GATE_8C_COMPLETE`.
- Actividad 9: `BLOCKED`.
- Contador oficial: `7/22`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.

## Endurecimiento contractual y retorno seguro

Fecha: 2026-07-21.

El diagnóstico QA identificó que las colecciones de entrada debían ordenarse de forma canónica, que los identificadores y fuentes requerían una política segura, que `full_day` debía ser no ambiguo y que el read model debía minimizar sus colecciones a recursos activos y aplicables. La corrección preserva el orden de cálculo, el aislamiento por consultorio y la semántica de intervalos.

- Orden canónico aplicado a versiones, overrides, feriados y colisiones.
- Identificadores `version_id`, ids y sources restringidos a la política segura de 128 caracteres.
- `full_day=true` sólo acepta ventana nula; `full_day=false` exige ventana válida.
- Read model minimizado a overrides, feriados y colisiones activos y aplicables.
- `applied_override_ids` deduplicado, ordenado y estable.
- Punto seguro pre-corrección: `6345b42a8a0170e293347a6f60ce959d39e2be94`.
- Bundle: `/tmp/mxmed-activity08-gate8c-contract-hardening-preflight-v2/activity08-before-gate8c-hardening.bundle`.
- Corrección en commit independiente y reversible.
- Rollback: `git revert --no-edit <gate8c_hardening_commit>`.
- Gate 8D permanece bloqueado hasta la postvalidación.
