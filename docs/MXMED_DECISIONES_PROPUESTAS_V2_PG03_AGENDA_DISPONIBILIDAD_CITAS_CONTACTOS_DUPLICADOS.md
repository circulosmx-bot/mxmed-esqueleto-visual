# DECISIONES PROPUESTAS V2 — PG-03 Agenda, disponibilidad, citas, contactos y duplicados

Estado global: `PENDING_DIRECTOR_APPROVAL`. Este documento propone decisiones; no aprueba ninguna.

## DEC-013A — Autoridad server-side de actores de Agenda

- Problema: rol, actor y doctor scope pueden llegar desde headers/body/query y fallback `doctor`.
- Evidencia: `api/agenda/index.php`, `resolveAgendaActorRole`, `resolveEffectiveAgendaActor`, compat mode y QA override.
- Decisión propuesta: resolver cuenta, membership, ownership, role y scope sólo desde sesión/contexto confiable; fail-closed y separar actor real de actor efectivo.
- Alternativas: mantener compatibilidad; aceptar sólo header firmado; migrar por feature flag. Se descartan como autoridad final.
- Riesgos/impacto: puede romper callers legacy; requiere adaptadores y observabilidad; mejora seguridad R1/R2.
- Dependencia: identidad Gate 4C, AuthorizationBoundary y reautenticación para acciones privilegiadas.
- UI classification: UI-0.
- Criterio de aceptación: matriz de rutas con fuente única server-side, pruebas de mismatch y 401/403 cerrados.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013B — Fuente canónica de horarios y disponibilidad

- Problema: cinco nombres de tabla, overrides, feriados, settings y colisiones alimentan proyecciones distintas.
- Evidencia: `AvailabilityRepository`, `OverrideRepository`, `AvailabilityController`, `ScheduleRepository`, `HolidayMxProvider`.
- Decisión propuesta: una fuente canónica versionada para horario/consultorio/zona/duración; disponibilidad como read model calculado y sin efectos secundarios.
- Alternativas: conservar fallback multi-tabla; fijar `consultorio_schedule` sin ledger; calcular sólo en UI.
- Riesgos/impacto: migración y reconciliación; elimina divergencia y facilita “siguiente disponible”.
- Dependencia: DEC-013I, contrato de consultorio y política de timezone.
- UI classification: UI-0.
- Criterio de aceptación: una definición de ventana, slot, override, holiday y collision con pruebas de cambio de consultorio.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013C — Máquina de estados de citas

- Problema: estados y transiciones viven en código disperso; llegada/atención/cierre/reapertura no están formalizados.
- Evidencia: `AppointmentWriteRepository::evaluateAppointmentTransitionDryRun`, `AppointmentEventsRepository`, controllers.
- Decisión propuesta: catálogo versionado de estados y transiciones permitidas, con motivo, actor y evento append-only.
- Alternativas: seguir con strings; usar sólo eventos; delegar a Clinical.
- Riesgos/impacto: requiere compatibilidad legacy y reconciliación histórica; evita mutaciones ambiguas.
- Dependencia: DEC-013A, DEC-013D y bridge clínico.
- UI classification: UI-0.
- Criterio de aceptación: tabla de transición exhaustiva, 409 para inválidas, idempotencia explícita y contrato Clinical.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013D — Idempotencia, concurrencia y doble reserva

- Problema: check-then-insert puede competir; idempotencia es parcial y no hay key HTTP uniforme.
- Evidencia: `AppointmentCollisionsRepository`, `PublicAppointmentsController`, índices `uniq_active_slot`, transacciones y `FOR UPDATE` parciales.
- Decisión propuesta: idempotency key por operación, unique constraint canónica por slot activo, transacción/lock único y replay seguro.
- Alternativas: sólo retry client-side; sólo índice; sólo locks.
- Riesgos/impacto: cambios de schema y manejo de colisiones; evita doble reserva y reintentos duplicados.
- Dependencia: DEC-013B, DEC-013C y DEC-013I.
- UI classification: UI-0.
- Criterio de aceptación: dos reservas concurrentes dejan una sola cita y el replay devuelve el mismo resultado sin duplicar eventos.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013E — Contrato de Agenda pública y OTP

- Problema: dos familias OTP/flow, debug code/log, rate limit incompleto, DDL y PII en payload.
- Evidencia: `PublicOtpController`, `PublicAppointmentsController`, `PublicOtpRepository`, `DevOtpSender`.
- Decisión propuesta: contrato único, OTP hash-only con rate limits multidimensionales, anti-enumeración, expiración/attempts, consentimiento mínimo y cero DDL/write incidental en GET.
- Alternativas: conservar ambos flows; externalizar OTP sin contrato; permitir debug en QA público.
- Riesgos/impacto: migración de callers públicos; reduce abuso y exposición.
- Dependencia: DEC-013A, DEC-013B, DEC-013F y DEC-013I.
- UI classification: UI-0.
- Criterio de aceptación: pruebas de expiración, replay, brute force, alias doctor, mensajes uniformes y payload minimizado.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013F — Modelo y privacidad de contactos de pacientes

- Problema: contactos, WhatsApp, representante/emergencia, procedencia, consentimiento y retención no tienen modelo uniforme.
- Evidencia: `patients_contacts`, controllers de pacientes, payload público y meta de visibilidad.
- Decisión propuesta: separar identificación/contacto/operativo, consentimiento y procedencia por dato; visibilidad explícita y no copiar PII a logs/flows innecesarios.
- Alternativas: ampliar `patients_contacts`; tabla genérica de atributos; mantener payload JSON público.
- Riesgos/impacto: migración y UX de edición; mejora minimización y privacidad.
- Dependencia: DEC-013E, DEC-013K y PG-08.
- UI classification: UI-0.
- Criterio de aceptación: catálogo de campos, scopes de lectura/escritura, masking y retención verificables.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013G — Identidad canónica y detección de duplicados

- Problema: no hay deduplicación real; alta pública/privada puede crear duplicados.
- Evidencia: `PatientsRepository`, búsqueda por tokens/teléfono/email y creación automática desde cita/reserva.
- Decisión propuesta: `patient_id` canónico, normalización determinista, matching por evidencia graduada y warning antes de crear; CURP sólo si se decide legalmente.
- Alternativas: nombre exacto; teléfono único; proveedor externo.
- Riesgos/impacto: falsos positivos y datos compartidos requieren revisión humana.
- Dependencia: DEC-013F, DEC-013H y Clinical references.
- UI classification: UI-0.
- Criterio de aceptación: exact/probable/no-match, explicación minimizada, sin auto-merge y fixtures sintéticos.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013H — Fusión reversible y auditable de pacientes

- Problema: no existe merge/undo ni reasignación de referencias.
- Evidencia: no se encontraron endpoints/repositorios/tablas de alias o merge; Clinical usa `ON DELETE RESTRICT`.
- Decisión propuesta: merge sólo server-side, reversible, con snapshot de referencias, alias, revisión y audit trail; nunca borrar identidad clínica físicamente.
- Alternativas: merge irreversible; marcar duplicado sin mover referencias; soporte manual fuera de producto.
- Riesgos/impacto: alto R3; requiere legal/clinical approval y pruebas de rollback.
- Dependencia: DEC-013A, DEC-013G, DEC-013J y PG-08.
- UI classification: UI-0 hasta aprobación; futura UI-3 si se diseña flujo.
- Criterio de aceptación: plan de referencias completo (citas, encounters, casos, documentos, consents), dry-run, apply/undo y auditoría.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013I — Migraciones fuera de runtime

- Problema: 23 archivos de Agenda contienen DDL/ensure checks durante request; excede máximo contractual 21.
- Evidencia: detector estático y `runtime-schema-agenda-register.json`.
- Decisión propuesta: retirar DDL de request, usar migraciones versionadas, preflight, rollback y ledger; GET debe ser puro.
- Alternativas: tolerar `CREATE IF NOT EXISTS`; bootstrap al startup; ampliar máximo.
- Riesgos/impacto: despliegue requiere coordinación; elimina cambios implícitos.
- Dependencia: todas las decisiones de datos y rollout.
- UI classification: UI-0.
- Criterio de aceptación: cero DDL en rutas de negocio, conteo <=21 contractual y migraciones ensayadas en entorno aislado.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013J — Auditoría mínima y atribución de actores

- Problema: events/notes/error_log no forman un trail PG-08 correlacionable.
- Evidencia: appointment events, operator audit, waitlist notes, `DevOtpSender` y no-show debug.
- Decisión propuesta: audit trail append-only con actor real/efectivo, subject, action, reason, correlation/request ID, minimización y hash/verify.
- Alternativas: ampliar events; usar sólo logs; integrar directamente Gate 6D.
- Riesgos/impacto: costo operativo y schema; mejora no repudio y soporte.
- Dependencia: DEC-013A, PG-08 Gate 6D y retención.
- UI classification: UI-0.
- Criterio de aceptación: eventos mínimos para mutaciones, lecturas sensibles, OTP abuse, merge y disposición; sin payload sensible.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013K — Retención y disposición de citas y contactos

- Problema: no hay política por tabla, legal hold, anonimización o disposición.
- Evidencia: schema SQL, payload JSON público, Clinical `ON DELETE RESTRICT`, ausencia de registry en Agenda.
- Decisión propuesta: registry de retención por dominio, legal hold, disposición simulada y ejecución autorizada; preservar expediente clínico y minimizar logs.
- Alternativas: retención indefinida; borrado por edad; política global única.
- Riesgos/impacto: obligaciones legales y dependencia clínica; evita borrado destructivo.
- Dependencia: DEC-013F, DEC-013H, DEC-013J y PG-08.
- UI classification: UI-0.
- Criterio de aceptación: matriz source/projection/copy, plazos aprobados, hold, dry-run y audit trail.
- Estado: `PENDING_DIRECTOR_APPROVAL`.

## DEC-013L — Compatibilidad, rollout y secuencia

- Problema: callers legacy, aliases doctor, dos flows públicos y múltiples esquemas impiden un corte directo.
- Evidencia: rutas/aliases, fallback compat, SQL alternativo y QA scripts.
- Decisión propuesta: rollout por shadow/read-only, dual-read temporal, backfill reversible, feature flags server-side, métricas y hard-stop; no integrar Actividad 8 hasta cerrar blockers.
- Alternativas: big-bang; mantener indefinidamente compat mode; migrar sólo UI.
- Riesgos/impacto: mayor duración; reduce regresión y permite rollback.
- Dependencia: DEC-013A–K.
- UI classification: UI-0; futuras fases UI-2/UI-3 con aprobación.
- Criterio de aceptación: plan de fases, owner, rollback, métricas, contract tests y aprobación directoral por gate.
- Estado: `PENDING_DIRECTOR_APPROVAL`.
