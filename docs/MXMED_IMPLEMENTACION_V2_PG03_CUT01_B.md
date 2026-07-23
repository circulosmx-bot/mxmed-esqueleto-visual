# MXMed PG-03 — Implementación CUT01-B

## 1. Identificador

`BE-ARCH/MXMed-PG03-CUT01-B-Schedule-Scope-Sentinel-Adapters-01`.

## 2. Baseline y checkpoint

Parent vinculante `d492400a6e8c05901d0e2a1065d4657d3065fdd3`; checkpoint anotado `checkpoint/mxmed-product-refinement-v2-activity11`, cuyo target es el mismo parent. Actividad 11 está cerrada e integrada.

## 3. Clasificación UI-0

Actividad backend/arquitectura UI-0, sin JavaScript, CSS, HTML, localStorage, cambios visuales, Clinical ni AWS.

## 4. Alcance

Alcance exacto de diez archivos: cuatro nuevos y seis modificados. Los nuevos son dos adapters, una prueba autocontenida y este documento; se modifican tres controllers, `ScheduleRepository`, configuración Agenda y Plan Maestro.

## 5. Adapter de schedule

`CanonicalScheduleReadAdapter` transforma un snapshot legacy ya seleccionado en `CanonicalScheduleVersion`. Es puro, fail-closed, no elige fuentes, no fusiona ventanas y delega orden y traslapes al dominio canónico.

## 6. Adapter de comparación

`CanonicalAvailabilityCompareAdapter` compara sólo la respuesta de un día de `AvailabilityController::index()` con `CanonicalAvailabilityResult`. Produce diagnóstico read-only, razones cerradas, diferencias por dimensión y digests SHA-256 deterministas sin payload libre.

## 7. Cinco fuentes legacy

La allow-list conserva, en orden legacy, `consultorio_schedule`, `consultorio_schedules`, `consultorio_horarios`, `consultorio_horarios_base` y `agenda_consultorio_schedule`. El adapter recibe exactamente una fuente seleccionada y no inventa precedencia.

## 8. Parámetros explícitos

Versión, ID de versión, profile, timezone, effective range, duración y gap son obligatorios. Los fixtures de prueba los proporcionan expresamente; no existen defaults canónicos implícitos.

## 9. Parámetros diferidos

Timezone definitivo, precedencia de fuentes, duración y gap predeterminados, effective range, registros sin consultorio, volumen/estrategia de backfill y métricas/umbrales/ventanas R1–R4 permanecen `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.

## 10. Scope profile/consultorio

El doctor legacy es referencia diagnóstica y no autoridad canónica. Profile y consultorio se comparan exactamente; no hay fallback, scope implícito ni conversión automática.

## 11. Sentinel `__all__`

`__all__` sigue permitido sólo como entrada agregada de waitlist. Nunca representa un consultorio canónico, nunca llega a assignment ni a `AppointmentWriteRepository`, claim de slot o creación/actualización de cita.

## 12. Wiring dormido

AgendaSettings y Waitlist evalúan el flag schedule; Availability evalúa el flag compare reutilizando su configuración. Los tres conservan únicamente una referencia de clase: no instancian adapters, no llaman `adapt()`/`compare()` y no procesan requests canónicos.

## 13. Flags

`canonical_schedule_read=false` y `canonical_availability_compare=false`. Están implementados, desactivados y no autorizados para activación; sólo el booleano literal `true` sería elegible. No hay override de request, cliente o environment; R0 permanece disabled.

## 14. ScheduleController excluido

`ScheduleController.php` permanece intacto y conserva lecturas mediante `listByDoctorConsultorio()` y escrituras mediante `replaceWeeklySchedule()`. No se conecta el adapter a tráfico real.

## 15. AppointmentWriteRepository excluido

`AppointmentWriteRepository.php` permanece intacto. No se agregan writes, transacciones, claims, citas, eventos o mutaciones de waitlist.

## 16. Pruebas

La prueba CUT01-B es autocontenida, determinista y sin DB. Junto con catorce regresiones heredadas verifica flags, adapters, sentinel, wiring, pureza y Gates 8A–8G: `ACTIVITY12_TESTS=15/15`; lint PHP `8/8`.

## 17. Safe return

Retorno seguro al parent `d492400a6e8c05901d0e2a1065d4657d3065fdd3` mediante `git revert --no-commit <ACTIVITY12_COMMIT>` en un worktree detached; el tree resultante debe ser idéntico al parent sin crear commit.

## 18. Blockers

Los trece blockers siguen abiertos. F-001, F-002, F-004 y F-006 son `IMPLEMENTATION_PARTIAL_FLAG_OFF_BLOCKER_OPEN`; los otros nueve conservan `DECISION_RATIFIED_BLOCKER_OPEN`. F-026 queda `IMPLEMENTATION_PARTIAL_FLAG_OFF_HIGH_OPEN`. Cutover y readiness permanecen NO-GO.

## 19. Exclusiones

No se activan flags, R1–R4, shadow/dual read, parámetros definitivos, SQL, DDL, migraciones, backfill, schedule writes, OTP, lifecycle, observabilidad, auditoría, outbox, saga, UI, Clinical, datos reales ni AWS.

## 20. Estado no integrado

Actividad 12 queda `CUT01_B_IMPLEMENTED_FLAGS_OFF_READY_FOR_POSTVALIDATION_NOT_INTEGRATED`; Actividad 13 continúa bloqueada. El contador oficial permanece 11/22, con once actividades pendientes. Implementado no significa activado, autorizado, integrado, listo para R1 o producción.
