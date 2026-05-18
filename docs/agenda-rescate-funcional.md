# Rescate funcional del módulo Agenda

## Adenda de actualización (2026-05-18)

Este documento nació como plan de rescate. Parte de sus secciones de “parcial/no conectado” ya fueron resueltas en el shell principal.

Estado real adicional confirmado:
- Operadores en `#p-ag-operadores` ya tiene flujo funcional frontend (wizard, alias/login/password temporal, envío simulado, archivado lógico, historial y controles sensibles con código de 6 dígitos simulado).
- Vista Día/Semana custom ya está estabilizada en shell principal.

Interpretar los puntos heredados de esta bitácora como contexto histórico y no como estado vigente cuando contradigan `assets/js/app.js` e `index.html`.

## Estado actual estable
- Semana custom de Agenda operativa en `assets/js/app.js` (ancla por rango y 6 columnas).
- Configuración de Agenda activa (acordeón, Horarios y consultorios, Recordatorios y confirmaciones).
- Editor real de horarios reutilizado en Agenda y en Mi perfil/Consultorios.
- Integración de disponibilidad/citas desde API privada (`/appointments`, `/availability`) sin cambios de backend.

## Inventario técnico (funciones objetivo)

| # | Función | Estado | UI/Entrada principal | JS principal | Endpoint(s) | Riesgo de ruptura | Conexión con Semana custom |
|---|---|---|---|---|---|---|---|
| 1 | Buscar siguiente cita disponible | Activa | Botón `Buscar cita` (`data-ag-work-action="next-available"`) + modal `#ag_next_slots_modal` | `openNextAvailableSlotFinder`, `loadNextAvailableSlots`, `collectNextAvailableSlots` | `GET /appointments`, `GET /availability` | Medio (depende de doctor/consultorio numéricos) | Usa mismo scope de consultorio y minutos de slot |
| 2 | Sugerencias agrupadas de 3 citas | Activa | Modal siguiente cita (`Ver siguientes 3`, `Ver 3 anteriores`) | `collectNextAvailableSlots({ limit: 3 })` | `GET /availability`, `GET /appointments` | Bajo | Reusa disponibilidad real, no motor paralelo |
| 3 | Lista gris | Parcial (lógica activa, vista dedicada ausente) | Señal en flujo cancelar tarde / badges visuales | `normalizePatientFlagType`, `resolvePatientFlagMeta`, `resolveAppointmentColor` | `GET /patients/{id}/flags`, `POST /appointments/{id}/cancel` | Medio (sin pantalla operativa específica) | Impacta color/estado de cards pasadas |
| 4 | Lista negra | Parcial (lógica activa, vista dedicada ausente) | Señal en no-show + badges visuales | mismas funciones de flags + no-show | `GET /patients/{id}/flags`, `POST /appointments/{id}/no_show` | Medio | Impacta visualización de eventos históricos |
| 5 | No show | Activa | Modal acciones de cita (`Paciente no asistió`) | `applyEventNoShow`, `openEventActionModal` | `POST /appointments/{id}/no_show` | Bajo | Refetch y repintado de eventos |
| 6 | Reprogramación | Activa | Modal acciones de cita (`Reprogramar`) | `applyEventReschedule`, calendario de reprogramación | `PATCH /appointments/{id}/reschedule` + lecturas de disponibilidad | Medio | Usa disponibilidad del rango actual |
| 7 | Cancelación | Activa | Modal acciones (`Cancelar cita`, post-acciones) | `applyEventCancel`, `setEventActionSection('cancel')` | `POST /appointments/{id}/cancel` | Bajo/Medio (post-flujos) | Refetch directo sobre semana custom |
| 8 | Bloqueo de horario | Activa parcial | Slot action layer custom (`Bloquear horario`) + modal block | `openBlockModalFromSelection`, `handleBlockConfirm`, `collectBlockedSlotEvents` | Sin endpoint dedicado (persistencia localStorage) | Medio/Alto (no persistencia backend) | Se dibuja como `blocked_slot` en custom week |
| 9 | Estados de cita | Activa parcial | Badge/acciones en modal de cita | `resolveAppointmentStatusMeta`, `resolveAppointmentColor` | Derivado de `/appointments` | Medio (acciones `in-progress/finished` no habilitadas real) | Define colores y etiqueta de cards |
| 10 | Disponibilidad por consultorio | Activa | Filtro `#ag_consultorio_filter` + scope en fetch | `fetchAvailabilityEvents`, `evaluateScheduleDayAllowance` | `GET /availability` | Bajo | Base del render en custom week |
| 11 | Modo Todos | Activa | Opción `Todos los consultorios` | `setActiveAgendaConsultorio`, `resolveAgendaAvailabilityConsultorioScope`, fetch combinado | `GET /consultorios` + múltiples `GET /availability` | Medio (sincronía de scope/range/cache) | Une eventos por consultorio para la semana |
| 12 | Horarios por consultorio | Activa | Mi perfil/Consultorios + Agenda/Configuración (inmersivo) | `mountActiveScheduleGrid`, `hydrateFromBackend`, `handleConsultorioTabSwitch` | `GET/PUT /schedule` | Alto (estado singleton `activeBody/rowRefs/activePane`) | Alimenta business rules y filtro de días |
| 13 | Recordatorios y confirmaciones | Activa | Bloque `Recordatorios y confirmaciones` | `normalizeAgendaReminderSettings`, `applyAgendaReminderSettingsToUi` | `GET/PUT /settings` | Bajo | Independiente del motor de slots |
| 14 | Simulador WhatsApp | Activa | Vista previa integrada (panel derecho) | `renderAgendaReminderPreviewFromUi`, `renderAgendaReminderPreviewInteraction` | Sin endpoint adicional | Bajo | No afecta Semana custom |
| 15 | Flujo nueva cita desde slot libre | Activa | Click en card disponible custom + modal nueva cita | `openCreateModalFromSelection`, `handleCreateSubmit` | `POST /appointments` | Bajo/Medio (dependencia de datos precargados) | Flujo principal desde `availability_slot` |

## Fase 2A · Diagnóstico específico: Buscar siguiente cita disponible

### Hallazgo consolidado
- **Ya existe y está activa en el shell principal** (no es una función faltante).
- Entrada visual activa en las 3 vistas de Agenda:
  - `index.html`: botones `data-ag-work-action="next-available"` en `#p-ag-admin`, `#p-ag-ajustes`, `#p-ag-operadores`.
- Modal operativo ya integrado:
  - `index.html`: `#ag_next_slots_modal`, `#ag_next_slots_results`, `#ag_next_slots_prev_btn`, `#ag_next_slots_more_btn`.
- Flujo JS operativo en SPA:
  - `assets/js/app.js`: `openNextAvailableSlotFinder`, `loadNextAvailableSlots`, `collectNextAvailableSlots`, `renderNextSlotOptions`.
  - Navegación por grupos: `limit: 3`, “Ver siguientes 3”, “Ver 3 anteriores”.
  - Selección de opción: enfoca y resalta el slot real en calendario para agendar.
- Endpoints usados por el flujo SPA:
  - `GET /api/agenda/index.php/availability`
  - `GET /api/agenda/index.php/appointments`

### Clasificación técnica (2A)
| Componente | Estado | Evidencia |
|---|---|---|
| Botón en shell principal | Activo | `data-ag-work-action="next-available"` + handler delegado en `assets/js/app.js` |
| Handler JS principal | Activo | `openNextAvailableSlotFinder` -> `loadNextAvailableSlots` -> `collectNextAvailableSlots` |
| Modal/panel UX | Activo | `#ag_next_slots_modal` + resultados clicables |
| Consumo `/availability` | Activo | `AgendaApiClient.getAvailability(...)` en flujo next slots |
| Agrupación de 3 sugerencias | Activo | `collectNextAvailableSlots({ limit: 3 })` |
| Conexión directa a waitlist assign desde modal “Buscar cita” | Parcial / no directa | Waitlist assign en SPA está centrado en post-cancelación; no hay puente directo desde este modal |
| Implementación legacy equivalente | Legacy operativa | `api/agenda/ui/waitlist_assign_pick_day.php` (“Mostrar la siguiente cita disponible”, “Mostrar las siguientes 3…”) |

### Ruta de integración recomendada (sin reescribir lógica)
1. **Mantener esta implementación SPA como fuente principal** de “Buscar siguiente cita disponible”.
2. **No migrar lógica de cálculo desde legacy** (`api/agenda/ui/*`), usarla solo como referencia funcional/histórica.
3. **Reforzar reconexión UX dentro del workspace Agenda**:
   - conservar el botón “Buscar cita” visible en submenú interno de Agenda;
   - garantizar que siempre abre `#ag_next_slots_modal` en contexto Agenda (ya implementado).
4. **Fase posterior (2B, opcional)**:
   - enlazar “siguiente cita disponible” con flujos de waitlist en SPA sin crear motor paralelo (reusar mismos endpoints y helpers).

### Riesgos identificados para 2A
- En flujo SPA actual, `collectNextAvailableSlots` exige `doctor_id` y `consultorio_id` numéricos para modo no demo.
  - Si el selector queda en estado no numérico/indefinido, puede fallar con mensaje de contexto.
- Existe dualidad SPA vs UI legacy (`api/agenda/ui/*`): riesgo de divergencia UX si se intentan mantener ambos como primarios.
- El botón “Buscar cita” funciona por modal y foco de slot; no hace asignación automática (esto es correcto para 2A, pero hay que explicitarlo en QA funcional).

### QA manual recomendado (Fase 2A)
1. Entrar a `Agenda semanal` y hacer click en `Buscar cita`.
2. Verificar apertura de modal `Siguiente cita disponible`.
3. Validar que aparecen hasta 3 opciones reales.
4. Click en `Ver siguientes 3` y `Ver 3 anteriores`:
   - confirmar paginación sin romper contexto.
5. Seleccionar una opción:
   - cerrar modal;
   - agenda cambia/enfoca slot sugerido;
   - slot queda resaltado y permite continuar con flujo de agendar.
6. Repetir en:
   - consultorio específico;
   - modo `Todos` (si aplica en el contexto actual del selector).
7. Confirmar ausencia de errores en consola y que no aparece warning falso de carga de citas durante el flujo.

## Funciones desconectadas o parcialmente conectadas

### Botones visibles sin flujo operativo completo
- `Agenda -> Operadores` (`#p-ag-operadores`) ya no es UI estática; hoy cuenta con handlers frontend para alta, edición inline, pausado/reactivación, archivado lógico e historial.

### Handlers existentes con UX actualmente oculta/parcial
- `applyEventInProgress` y `applyEventFinished` existen, pero la visibilidad de esos botones queda desactivada por reglas (`showMarkInProgress=false`, `supportsFinishAction=false`).

### Endpoints existentes no integrados al shell Agenda actual
- `GET /appointments/{id}/events` (sí usado en `api/agenda/ui/appointment.php`, no en el flujo principal SPA de `index.html`).
- Waitlist completo (`POST/PATCH /waitlist`) no está expuesto como flujo completo en la vista SPA principal; en SPA se usa sobre todo `GET /waitlist` y `POST /waitlist/{id}/assign` en post-cancelación.

### UI paralela (riesgo de divergencia)
- Existe un frente operativo alterno en `api/agenda/ui/*` (`day.php`, `waitlist.php`, `appointment.php`) además del workspace de Agenda en `index.html` + `assets/js/app.js`.

### Duplicidad funcional detectada
- Bloqueo de horario aparece en dos rutas UX:
  - Menú legacy `#ag_cell_menu_block_slot` (marcado "próximamente", deshabilitado en HTML).
  - Slot action layer custom (activo y funcional).

## Funciones críticas a preservar (no romper)
- Fuente única de rango semanal: `resolveCustomWeekRangeFromAnchor()`.
- Sincronización de consultorio activo: `setActiveAgendaConsultorio()`.
- Coherencia cache/rango/scope: `customWeekLastEventsRangeKey`, `customWeekLastEventsScopeKey`, `agendaLatestEventsForCustomWeek`.
- Editor de horarios compartido: `mountActiveScheduleGrid()` + `hydrateFromBackend()` + bridge inmersivo.

## Riesgos de integración
- Contaminación de estado entre hosts del editor de horarios (Consultorios vs Agenda inmersiva) por estado singleton.
- Deriva funcional entre SPA (`index.html`) y UI legacy (`api/agenda/ui/*`).
- Flags gris/negra funcionales sin módulo de operación dedicado (solo señales contextuales).
- Bloqueo de horario sin persistencia backend (localStorage), con riesgo de inconsistencia entre sesiones/dispositivos.

## Plan de reincorporación incremental

### Fase 1: inventario y documentación
- Consolidar este inventario y congelar funciones críticas.
- Alinear documentación histórica que quedó desactualizada (por ejemplo `modules/agenda/README.md` aún describe “stubs”).

### Fase 2: reconectar funciones visibles
- Operadores: flujo visual ya conectado en frontend; pendiente endurecimiento backend de seguridad/permisos.
- Unificar entrada de bloqueo (dejar una sola ruta UX activa).

### Fase 3: validar Buscar siguiente cita disponible
- Pruebas por modo consultorio específico y modo Todos.
- Verificar consistencia de `slot_minutes`, rango y exclusión de ocupados.

### Fase 4: validar lista gris/lista negra/no show
- Trazabilidad completa: acción -> write -> flag -> visualización.
- Definir si se incorpora módulo dedicado de gestión de listas o se mantiene solo señal contextual.

### Fase 5: integración completa al workspace Agenda
- Reducir dependencias de UI paralela `api/agenda/ui/*` en operación diaria.
- Mantener backend único y evitar duplicar lógica en frontend.

### Fase 6: QA integral y cierre
- QA por función (creación, cancelación, no_show, reprogramación, waitlist assign, horarios, reminders, custom week).
- Checklist de no regresión en sábados, modo Todos, y reentrada Agenda <-> Configuración.

## QA sugerido por función (resumen)
- Buscar siguiente cita: validar grupos de 3 + paginación adelante/atrás.
- Slot libre -> Nueva cita: verificar precarga fecha/hora/consultorio y repintado inmediato.
- Cancelación/no_show/reprogramación: verificar write + estado visual actualizado.
- Modo Todos: verificar unión real por consultorio en disponibilidad.
- Horarios: validar C1/C2/C3 en Mi perfil y Agenda inmersiva, sin contaminación.
- Recordatorios/simulador: validar toggles y preview en tiempo real.

## Flujos pendientes de incorporación al workspace Agenda

> Estados usados: `Consolidado en SPA`, `Existe pero falta conectar al SPA`, `Parcial`, `Solo legacy`, `Solo backend`, `No encontrado`, `Requiere decisión UX`.

| Flujo | Existe backend | Existe legacy UI | Existe SPA actual | Archivos/funciones | Estado | Riesgo | Recomendación |
|---|---|---|---|---|---|---|---|
| Crear cita | Sí (`POST /appointments`) | Sí (`day.php` + `action.php`) | Sí | `openCreateModalFromSelection`, `handleCreateSubmit`, `AppointmentWriteController::create` | Consolidado en SPA | Bajo | Mantener como flujo base único. |
| Ver detalle de cita | Sí (`GET /appointments/{id}`, `GET /appointments/{id}/events`) | Sí (`appointment.php`) | Sí (modal SPA) | `openEventActionModal`, `AppointmentsController`, `AppointmentEventsController` | Parcial | Medio | Conectar timeline de eventos (`/events`) al modal SPA para paridad con legacy. |
| Editar cita (campos generales) | No endpoint dedicado | No (solo reprogramar/cancelar/no_show) | No | N/A | Requiere decisión UX | Medio | Definir si “editar” se limita a reprogramar o requiere endpoint nuevo (fuera de esta fase). |
| Cancelar cita | Sí (`POST /appointments/{id}/cancel`) | Sí | Sí | `applyEventCancel`, `AppointmentWriteController::cancel` | Consolidado en SPA | Bajo | Mantener; reforzar QA de flags tardíos. |
| Confirmar cita (privada interna) | No endpoint privado dedicado (solo público `public/appointments/confirm`) | No | Parcial visual (status badge) | `resolveAppointmentStatusMeta`, `PublicAppointmentsController::confirm` | Requiere decisión UX | Medio/Alto | Decidir política de confirmación interna antes de exponer acción SPA. |
| Marcar no show | Sí (`POST /appointments/{id}/no_show`) | Sí | Sí | `applyEventNoShow`, `AppointmentWriteController::noShow` | Consolidado en SPA | Bajo | Mantener; validar flags automáticos por política. |
| Reprogramar cita | Sí (`PATCH /appointments/{id}/reschedule`) | Sí | Sí | `applyEventReschedule`, `AppointmentWriteController::reschedule` | Consolidado en SPA | Bajo | Mantener y preservar selector de slots actual. |
| Cambiar estado de cita (en curso/finalizada) | No write endpoint explícito | No | Parcial (handlers ocultos) | `applyEventInProgress`, `applyEventFinished` (UI deshabilitada) | Parcial | Medio | Decidir si activar con contrato backend o retirar UI latente. |
| Bloquear horario individual | Infra parcial (overrides leídas, no write HTTP expuesto) | No flujo claro | Sí (persistencia local) | `openBlockModalFromSelection`, `collectBlockedSlotEvents`, `AvailabilityController` (overrides read) | Parcial | Alto | Definir write backend de bloqueos; mientras, etiquetar bloqueo local explícitamente. |
| Bloquear rango de horarios | Infra parcial | No flujo claro | Sí (local, modo “hasta”) | `handleBlockConfirm`, `slot-block-mode until` | Parcial | Alto | Mismo plan: unificar en endpoint de overrides write. |
| Bloquear día completo | Infra parcial (close override en dominio) | No | No | `AvailabilityController` (close/open overrides) | Solo backend | Medio/Alto | Diseñar UI mínima y endpoint write antes de conectar en SPA. |
| Desbloquear horario | Infra parcial | No | Sí (remove local block) | `removeBlockedSlotById` | Parcial | Alto | Migrar desbloqueo a backend cuando exista write de bloqueos. |
| Desbloquear día completo | Infra parcial | No | No | N/A | Solo backend | Medio | Añadir junto al flujo de bloqueo por día en misma fase. |
| Distinguir bloqueo vs cita real | Sí (metadatos disponibilidad + tipos) | Sí | Sí | `event_type=blocked_slot`, `resolveAppointmentColor`, `AvailabilityController` meta | Consolidado en SPA | Bajo | Mantener tipado de eventos; no mezclar con citas ocupadas. |
| Buscar siguiente cita por consultorio | Sí (`/availability`, `/appointments`) | Sí (`waitlist_assign_pick_day.php`) | Sí | `openNextAvailableSlotFinder`, `collectNextAvailableSlots` | Consolidado en SPA | Bajo | Mantener SPA como fuente principal. |
| Buscar siguiente cita modo Todos | Sí (consultas por consultorio) | No directo | Sí | `resolveAgendaAvailabilityConsultorioScope`, `collectNextAvailableSlots` | Parcial | Medio | Endurecer fallback/errores de scope no numérico y mantener consultorio real por slot. |
| Selección de slot sugerido | Sí | Sí | Sí | `renderNextSlotOptions`, `openCreateModalFromSelection` | Consolidado en SPA | Bajo | Mantener reutilización del flujo de slot libre. |
| Enfoque visual en calendario desde sugerencia | N/A (front) | No | Sí | `focusCustomWeekSlotByDate`, `setNextSlotFocusTarget` | Consolidado en SPA | Bajo | Mantener highlight temporal y navegación al rango correspondiente. |
| Apertura de Nueva cita desde “Elegir” | Sí | Sí | Sí | `openCreateModalFromSelection`, modal `#ag_create_modal` | Consolidado en SPA | Bajo | Mantener sin modal paralelo. |
| Agregar paciente a waitlist | Sí (`POST /waitlist`) | Sí | No (SPA principal) | `WaitlistController::store`, `api/agenda/ui/waitlist.php` | Existe pero falta conectar al SPA | Medio | Integrar formulario mínimo en workspace Agenda (sin duplicar lógica). |
| Ver lista de espera | Sí (`GET /waitlist`) | Sí | Parcial (post-cancel) | `getWaitlistEntries`, `waitlist.php` | Parcial | Medio | Exponer panel de waitlist dedicado en SPA. |
| Asignar cita desde waitlist | Sí (`POST /waitlist/{id}/assign`) | Sí | Parcial (post-cancel) | `assignWaitlistEntry`, `waitlist_assign_pick_*` | Parcial | Medio | Unificar assign desde un único modal SPA. |
| Escoger día para waitlist | Sí (via `/availability`) | Sí | No | `waitlist_assign_pick_day.php` | Solo legacy | Bajo/Medio | Reusar motor de “Buscar cita” para cubrir este paso en SPA. |
| Escoger slot para waitlist | Sí (via `/availability`) | Sí | No | `waitlist_assign_pick_slot.php` | Solo legacy | Bajo/Medio | Reusar selección de slot existente en SPA. |
| Convertir espera en cita real | Sí | Sí | Parcial | `WaitlistController::assign`, `assignWaitlistEntry` | Parcial | Medio | Completar flujo end-to-end de waitlist en shell principal. |
| Lista gris | Sí (flags + comportamiento) | Parcial | Parcial | `PatientFlagsController`, `PatientBehaviorController`, `resolvePatientFlagMeta` | Parcial | Medio | Definir panel operativo de consulta/gestión de flags. |
| Lista negra | Sí (flags + comportamiento) | Parcial | Parcial | mismas rutas de flags | Parcial | Medio | Igual: incorporar UI de gestión dedicada o pauta operacional explícita. |
| Flags por paciente | Sí (`GET /patients/{id}/flags`) | Parcial | Sí (consumo interno) | `AgendaApiClient.getPatientFlags`, `normalizePatientFlags` | Parcial | Medio | Exponer trazabilidad legible en modal de cita/paciente. |
| Marcar paciente conflictivo | Parcial (derivado de no_show/cancel + reglas) | Parcial | Parcial | `applyEventCancel`, `applyEventNoShow`, flags linked list | Requiere decisión UX | Medio | Definir acción explícita “marcar” vs solo reglas automáticas. |
| Relación con no_show | Sí | Sí | Sí | `AppointmentWriteController::noShow`, visual badges | Consolidado en SPA | Bajo | Mantener; documentar reglas de activación de flag. |
| Trazabilidad de eventos | Sí (`GET /appointments/{id}/events`) | Sí | Parcial | `AppointmentEventsController`, `appointment.php` | Parcial | Medio | Conectar eventos históricos al modal SPA de detalle. |
| Operadores/asistentes de consultorio | Parcial (actor roles permitidos) | No | Sí (panel funcional frontend) | `#p-ag-operadores`, `assets/js/app.js` (wizard + edición + archivado) | Parcial | Medio | Mantener UX actual y cerrar persistencia/autorización backend real. |
| Permisos de operadores | Parcial (backend acepta actor_role) | No | Sí (UI de permisos visible) | `AppointmentWriteController` + wizard/permisos frontend | Parcial | Medio/Alto | Implementar enforcement backend por módulo y auditoría server-side. |
| Asignación de operadores | No endpoint claro de Agenda | No | Parcial | N/A (modelo frontend actual + actor context) | Parcial | Medio | Definir contrato backend de asignación/gestión de asistencias por consultorio. |
| Acciones permitidas por operador | Parcial (writes aceptan role operator) | No | Sí (acciones UI + flujo de seguridad simulado) | `assets/js/app.js` (`pause/reactivate/archive/history`) | Parcial | Medio | Conectar verificación real de identidad y permisos antes de producción. |
| Disponibilidad pública | Sí (`GET /public/availability`) | No (UI legacy privada) | Sí (frontend público independiente) | `PublicAvailabilityController`, `assets/js/public/public-agenda.js` | Existe pero falta conectar al SPA | Bajo | Mantener aislado como flujo público externo; no mezclar con workspace interno. |
| Solicitud pública de cita | Sí (`/public/appointments/request|reserve`) | No | Sí (frontend público independiente) | `PublicAppointmentsController`, `public-book.js` | Existe pero falta conectar al SPA | Bajo | Mantener en portal público; documentar dependencia OTP. |
| OTP público | Sí (`/public/otp/request|verify`) | No | Sí (frontend público independiente) | `PublicOtpController`, `public-book.js`, `public-cancel.js` | Existe pero falta conectar al SPA | Bajo | Mantener flujos actuales y endurecer observabilidad. |
| Confirmación/cancelación pública | Sí (`/public/appointments/confirm|cancel|verify`) | No | Sí (frontend público independiente) | `PublicAppointmentsController`, `public-cancel.js` | Existe pero falta conectar al SPA | Bajo | Mantener separado del shell interno de Agenda. |
| Abrir expediente desde cita | Backend clínico existe | Legacy Agenda no | Parcial (desde pacientes/contextos, no claro desde cita Agenda) | `jumpTo('p-expediente')`, contexto clínico global | Requiere decisión UX | Medio | Definir CTA explícita en modal de cita para abrir expediente. |
| Iniciar consulta desde cita | Backend clínico existe | Legacy Agenda no | Parcial (flujo clínico existe fuera de Agenda) | acciones `encounter` en módulo clínico | Requiere decisión UX | Medio/Alto | Definir si Agenda puede iniciar consulta o solo abrir contexto paciente. |
| Vínculo agenda → paciente → expediente | Parcial | Legacy parcial | Parcial | `patient:selected`, bridge encounter en `app.js` | Parcial | Medio | Estandarizar handoff desde Agenda sin auto-iniciar consulta. |
| Respeto patient_id global + expediente privado | Parcial (arquitectura documentada, enforcement gradual) | No | Parcial | docs de arquitectura + controles clínicos actuales | Requiere decisión UX | Alto | Implementar validaciones de acceso por doctor/contexto en fases de seguridad. |

### Resumen operativo del rescate

1. **Listos para rescatar de inmediato (bajo riesgo)**  
   - Waitlist completo en SPA (alta/listado/asignación) reutilizando endpoints existentes.  
   - Trazabilidad de eventos de cita (`/appointments/{id}/events`) dentro del modal SPA.  
   - Pulido final de “Buscar siguiente cita disponible” en modo Todos.

2. **Ya consolidados en SPA**  
   - Crear cita desde slot libre.  
   - Cancelar, no_show, reprogramar.  
   - Semana custom + disponibilidad por consultorio + modo Todos base.  
   - Recordatorios/simulador.

3. **Solo legacy hoy**  
   - Waitlist pick-day / pick-slot operativos en `api/agenda/ui/waitlist_assign_pick_day.php` y `waitlist_assign_pick_slot.php`.

4. **Solo backend hoy**  
   - Capacidades de overrides/bloqueos en disponibilidad (sin write HTTP de UI).  
   - Partes de permisos por rol operator sin consola de gestión.

5. **Requieren decisión UX antes de implementar**  
  - Confirmación interna de cita (privada).  
  - Estados “en curso/finalizada” como write real.  
  - Operadores (enforcement backend de permisos por módulo y auth real).  
  - Bridge Agenda → Expediente/Consulta con reglas explícitas de acceso.

6. **Siguiente fase recomendada (menor riesgo → mayor impacto)**  
   1) Conectar timeline de eventos y waitlist CRUD al shell SPA.  
   2) Unificar bloqueo de horario (una sola entrada UX) y definir contrato write de overrides.  
  3) Mantener panel Operadores activo en frontend y cerrar enforcement backend/RBAC.  
   4) Definir y conectar bridge clínico explícito (abrir expediente / iniciar consulta) con reglas de privacidad por médico.

## Contrato de origen de cita para visualización en Agenda

### Campos operativos (fuente de verdad)
- `channel_origin` (preferente para clasificación visual).
- `created_by_role` (complementario para clasificación/fallback).
- `created_by_id` (auditoría operativa, no visual principal).
- Compatibilidad de lectura adicional: `origin` / `source` (si existen en la fila).

### Prioridad de resolución visual (SPA)
1. `origin_visual_key` explícito (si viene en el evento).
2. `channel_origin`.
3. `created_by_role` / `actor_role`.
4. `origin` / `source`.
5. Fallback `user`.

### Valores oficiales esperados
- Usuario / Médico: `doctor`, `medico`, `user`, `internal_user`.
- Operadora: `operator`, `operadora`, `assistant`.
- Paciente vía perfil web: `public_profile`, `web_profile`, `patient_web`, `public`, `patient`.
- Operador IA: `ai_operator`, `ai`, `ia`, `bot`, `agent`.
- Call Center: `call_center`, `call-center`, `phone`, `telefono`.

### Mapeo de color de franja
- `user` -> `#2F80ED`
- `operator` -> `#13B8B5`
- `web-patient` -> `#5CB85C`
- `ai` -> `#7E57C2`
- `call-center` -> `#F57C00`

### Notas
- Este contrato es operativo/auditable para origen de cita (no dato clínico sensible).
- En `GET /appointments`, el backend debe exponer al menos `channel_origin`, y cuando exista, también `created_by_role`/`created_by_id`.
