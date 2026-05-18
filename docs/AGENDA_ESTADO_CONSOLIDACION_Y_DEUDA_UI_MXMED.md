# AGENDA: Estado de consolidación y deuda UX/UI – MXMed

## Adenda de actualización (2026-05-18)

Esta versión incorpora el estado real actual del shell principal:
- Vista Semana custom operativa.
- Vista Día custom operativa (mini calendario, reloj/contexto, KPIs, columnas Mañana/Tarde).
- Vista Mes oculta en selector principal.
- Bloqueo parcial y bloqueo de día completo funcionales en UX shell.
- Desbloqueo funcional con re-render de Día/Semana.
- Nueva cita + modal “Siguiente cita disponible” operativos.

Se mantiene deuda en:
- convergencia final con front legacy `api/agenda/ui/*`,
- write backend dedicado para bloqueos administrativos desde shell (actualmente persiste en frontend/localStorage).

## 1) Propósito
Este documento formaliza el estado real del módulo Agenda en MXMed con base en evidencia del repositorio.

Objetivo:
- separar la consolidación funcional (backend/contratos/lógica) de la deuda pendiente de UX/UI,
- evitar interpretar la falta de integración visual al shell principal como una falla del módulo Agenda,
- definir el contrato mínimo que deberá respetar cualquier futura UI de Agenda (incluyendo opción FullCalendar o capa equivalente).

Referencias base:
- `docs/MAPEO_AGENDA_MXMED.md`
- `docs/AGENDA_COMO_PUERTA_DE_ENTRADA_CLINICA_MXMED.md`
- `docs/agenda/CIERRE_AGENDA_V1_ESTADO_FINAL.md`
- `docs/agenda/CIERRE_AGENDA_PUBLICA_V1.md`
- `docs/agenda/agenda/PASO_8_Contratos_Frontend_UI_v1.md`
- `docs/agenda/agenda/PASO_9_Contratos_UI_por_Pantalla_v1.md`

---

## 2) Matriz de madurez del módulo Agenda

| Rubro | Estado actual | Evidencia del repo | Nivel de madurez | Pendiente real |
|---|---|---|---|---|
| Backend/API core | Router y controladores activos para agenda operativa | `api/agenda/index.php`, `modules/agenda/controllers/*` | Alto | Mantener gobernanza de contratos |
| Writes (create/reschedule/cancel/no_show) | Implementados con eventos y validaciones | `AppointmentWriteController.php`, `AppointmentWriteRepository.php`, `docs/agenda/FASE_IV_FLOWS_WRITE_V1.md` | Alto | Endurecimiento evolutivo puntual |
| Waitlist | CRUD + assign funcional con flujo operativo | `WaitlistController.php`, `api/agenda/ui/waitlist*.php`, `docs/agenda/waitlist/*` | Alto | Integración visual futura al shell principal |
| Agenda pública | Flujo P1–P6 documentado y QA reproducible | `PublicAppointmentsController.php`, `PublicOtpController.php`, `docs/agenda/CIERRE_AGENDA_PUBLICA_V1.md`, `modules/agenda/qa/public_*` | Alto | Evolución UX pública sin romper contratos |
| Disponibilidad | Motor activo con ventanas/overrides/colisiones | `AvailabilityController.php`, `docs/agenda/CIERRE_FASE_II_MOTOR_DISPONIBILIDAD.md` | Alto | Homologar tabla base en entornos heterogéneos |
| Creación/vinculación de paciente | Agenda puede crear paciente canónico en flujos definidos | `modules/agenda/helpers/patients_client.php`, `AppointmentWriteController.php`, `WaitlistController.php` | Alto | Reforzar trazabilidad UX (mensajería y visibilidad) |
| Bridge clínico opcional | Existe bridge a encounters condicionado por feature flag | `ClinicalEncounterBridge.php`, `AppointmentWriteRepository.php`, `docs/clinical/DECISION_AGENDA_CREATES_CLINICAL_ENCOUNTER_V1.md` | Medio-Alto | Gobernanza de cuándo habilitarlo por operación |
| QA técnico | Paquetes QA y cierres de fase documentados | `docs/qa/*agenda*`, `modules/agenda/qa/*`, `docs/agenda/CIERRE_*` | Alto | Automatización CI/CD incremental |
| Documentación de contratos | Contratos funcionales y semánticos ya escritos | `docs/agenda/CIERRE_FASE_I6_SEMANTICA_Y_CONTRATO_FRONTEND.md`, `PASO_8`, `PASO_9` | Alto | Mantener sincronía con cambios futuros |
| UI operativa aislada | UI server-rendered operativa fuera del shell principal | `api/agenda/ui/day.php`, `waitlist.php`, `action.php`, `docs/agenda/CIERRE_AGENDA_V1_ESTADO_FINAL.md` | Medio-Alto | UX moderna unificada con shell principal |
| Integración al shell principal | Operativa en Semana/Día custom; cobertura parcial en waitlist/legacy | `index.html` (`p-ag-*`), `assets/js/app.js` | Medio-Alto | Cerrar convergencia shell custom ↔ legacy sin duplicidad |
| UX final tipo FullCalendar (o equivalente) | No bloqueante; ya existe capa custom operativa | `assets/js/app.js` (`renderCustomWeekView`, `renderCustomDayView`) | Medio | Evolución visual incremental sin romper contratos |

---

## 3) Separación formal: módulo consolidado vs UI pendiente

### 3.1 Qué ya está consolidado
Se considera consolidado en Agenda:
- API operativa para citas, waitlist, disponibilidad, eventos y agenda pública.
- Contratos JSON estables (`ok/error/message/data/meta`) y semántica documentada.
- Writes clínico-operativos con bitácora de eventos.
- Dependencia controlada con Patients para resolver/crear `patient_id` cuando aplica.
- Paquetes QA reproducibles y cierres de fase en documentación histórica.

### 3.2 Qué sigue pendiente (y por qué)
Pendiente principal:
- convergencia de cobertura entre shell custom y frentes legacy de Agenda (especialmente waitlist y write backend dedicado para bloqueos administrativos).

Esto NO implica módulo funcional incompleto. Implica deuda de convergencia y endurecimiento operativo en frentes aún mixtos.

### 3.3 Error de interpretación que debe evitarse
Es incorrecto concluir “Agenda no está hecha” solo porque:
- aún coexisten rutas legacy de Agenda fuera del shell,
- o no se ha unificado totalmente la persistencia de bloqueos administrativos en backend desde la UX shell.

El estado real es: lógica/contratos consolidados + shell custom operativo + deuda de convergencia final y backend de soporte para ciertos flujos administrativos.

---

## 4) Contratos para futura UI de Agenda

Toda futura UI (FullCalendar o equivalente) debe respetar estos contratos ya existentes:

### 4.1 Endpoints base mínimos
- `GET /api/agenda/index.php/appointments`
- `GET /api/agenda/index.php/appointments/{id}`
- `POST /api/agenda/index.php/appointments`
- `PATCH /api/agenda/index.php/appointments/{id}/reschedule`
- `POST /api/agenda/index.php/appointments/{id}/cancel`
- `POST /api/agenda/index.php/appointments/{id}/no_show`
- `GET /api/agenda/index.php/appointments/{id}/events`
- `GET /api/agenda/index.php/availability`
- `GET/POST/PATCH /api/agenda/index.php/waitlist...`

### 4.2 Envoltura de respuesta
La UI debe consumir respuestas con contrato uniforme:
- `ok`
- `error`
- `message`
- `data`
- `meta`

### 4.3 Estados/semántica operativa
La UI debe respetar semántica existente de:
- create/reschedule/cancel/no_show,
- eventos de cita y trazabilidad,
- disponibilidad y colisiones,
- waitlist y assign.

### 4.4 Relación con Pacientes
- Agenda puede vincular un `patient_id` existente.
- En casos definidos puede detonar creación canónica de paciente desde Agenda.
- La UI futura no debe crear fuentes paralelas de identidad fuera de Patients.

### 4.5 Relación con Expediente y Consulta
Reglas obligatorias:
- Agenda puede abrir contexto de paciente/expediente.
- Agenda NO debe iniciar consulta clínica automáticamente por abrir cita o expediente.
- Guardar/crear paciente NO inicia consulta.
- La consulta activa se inicia explícitamente en el flujo clínico definido.

### 4.6 Bridge clínico
- El bridge Agenda -> Encounter existe como mecanismo opcional y condicionado por feature flag.
- La UI no debe asumir que toda cita crea encounter automático.

---

## 5) Opciones de aterrizaje visual (sin implementación en esta fase)

Opciones viables de UX/UI para Agenda:
- FullCalendar integrado al shell principal.
- Capa visual equivalente (grid/calendario propio) que consuma los mismos endpoints.
- Híbrido: mantener UI server-rendered operativa como fallback mientras migra UX principal.

Condición transversal: cualquier opción debe preservar contratos y reglas clínicas vigentes.

---

## 6) Riesgos de integración futura

1. Mezclar “abrir cita” con “iniciar consulta” y romper regla clínica.
2. Crear estados UI paralelos que contradigan `patient_id` canónico.
3. Romper semántica de write events por adaptar visual sin contrato.
4. Acoplar indebidamente bridge clínico como comportamiento obligatorio.
5. Introducir una UI nueva sin respetar wrappers y errores actuales.

---

## 7) Relación Agenda ↔ Pacientes ↔ Expediente ↔ Consulta

Relación canónica:
- Agenda opera citas y disponibilidad.
- Pacientes provee identidad canónica.
- Expediente representa el contexto clínico visible del paciente.
- Consulta (encounter) representa el acto clínico activo.

Transición válida:
- Agenda -> paciente -> expediente -> iniciar consulta (acción explícita).

Transición inválida:
- Agenda -> iniciar consulta automática sin acción explícita.

---

## 8) Conclusión arquitectónica

Agenda en MXMed se encuentra funcionalmente consolidada a nivel backend/contratos/operación y QA.

La deuda principal vigente es de integración UX/UI en la experiencia principal del sistema, no de ausencia de lógica de negocio.

Por lo tanto, las siguientes fases deben priorizar diseño e implementación visual sobre un módulo ya estable, respetando contratos y reglas clínicas existentes.
