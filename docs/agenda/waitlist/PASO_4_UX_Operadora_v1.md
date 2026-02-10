# Lista de Espera (Waitlist) — PASO 4: UX de Operadora v1

## 1) Propósito del PASO 4
Definir con precisión qué ve y qué hace **la operadora/consultorio** en v1 para gestionar la lista de espera de forma **manual**, sin inducir promesas de cita ni automatizaciones que todavía no existen.

Este documento se alinea con:
- PASO 2 (visión, reglas y guardrails)
- PASO 3 (qué ve / qué no ve el paciente)
- PASO 5 (roles y reglas operativas)
- PASO 7 (bitácora y trazabilidad)

---

## 2) Dónde vive la función en v1
- La operadora trabaja en la UI interna del módulo Agenda (server-rendered):
  - `/api/agenda/ui/waitlist.php`
- La asignación se hace **solo cuando hay hueco real** (cancelación / no-show confirmado / espacio liberado).
- La UI de lista de espera NO sustituye “Agenda del día”; son funciones complementarias.

---

## 3) Pantalla “Lista de espera” (vista principal)
### 3.1 Encabezado y contexto
Debe mostrar:
- Título: **Lista de espera**
- Contexto: **Doctor X · Consultorio Y**
- Botones:
  - **Volver al día** (regresa a day.php del mismo doctor/consultorio/fecha/slot_minutes)
  - **Recargar** (mantiene filtros actuales)

### 3.2 Aviso explicativo (guardrail visible)
Un bloque informativo (alert) que diga, en esencia:
- “Esta lista se usa cuando la agenda está saturada.”
- “La asignación ocurre solo cuando aparece un hueco real (cancelación).”
- “Regla general: FIFO (más antiguo primero), salvo override documentado.”

> Objetivo UX: que nadie interprete que “asignar” es una forma normal de dar citas sin hueco real.

---

## 4) Bloque “Agregar a lista de espera” (captura manual)
### 4.1 Cuándo se usa
- Cuando el paciente no acepta esperar a la próxima cita disponible.
- Cuando llega una solicitud por:
  - Teléfono (consultorio / call center)
  - Presencial
  - Contacto asistido por agente IA (permitido bajo supervisión y credenciales)

### 4.2 Campos mínimos (v1)
- `patient_id` (opcional)
- `patient_name` (recomendado)
- `patient_phone` (recomendado)
- `notes` (opcional)

Regla de captura sugerida:
- Si no hay `patient_id`, pedir al menos **nombre y teléfono**.

### 4.3 Confirmación esperada
Al dar “Agregar”:
- La entrada aparece en “Entradas activas”.
- Queda lista para asignación futura.
- No se genera cita aún.
- Se registra evento de bitácora (ver PASO 7).

---

## 5) Tabla “Entradas activas”
### 5.1 Columnas recomendadas
- Paciente (nombre o fallback)
- Contacto (teléfono)
- Notas
- Creada (timestamp)
- Acciones

### 5.2 Orden por defecto
- FIFO estricto: más antigua arriba (created_at asc).

### 5.3 Qué NO debe mostrar (guardrails)
- No mostrar “posición exacta” hacia el paciente (en UI paciente); aquí es interno, pero aun así no venderlo como “turno garantizado”.
- No mostrar “cita tentativa” ni “fecha probable” en v1.

---

## 6) Acciones por fila (operación)
### 6.1 Asignar (acción secundaria / discreta)
- Debe verse como **link** o acción no primaria (no como botón grande).
- Se usa SOLO cuando ya existe un hueco real.
- Abre el flujo guiado de selección de hueco:

**Flujo UI:**
1) `waitlist_assign_pick_day.php`
2) `waitlist_assign_pick_slot.php`
3) Confirmación en `waitlist.php` (bloque “Confirmar asignación”)
4) Submit a `action.php` con `op=waitlist_assign_confirm`

### 6.2 Cambiar estado / remover (si aplica en v1)
Si v1 incluye cambios de estado manual:
- “Remover” (duplicado / ya no aplica / paciente atendido por otro lado)
- “Marcar rechazada” (si el paciente declinó manualmente)
- “Notas” (actualizar)

> Si estas acciones no están implementadas aún, deben anotarse como FUTURO y no inducirse en UI.

---

## 7) Flujo de “Asignar” (guiado)
### 7.1 Pantalla “Asignar desde Lista de espera — elegir día”
URL: `waitlist_assign_pick_day.php`

Debe incluir:
- Botón “Volver a la lista”
- Atajos:
  - “Mostrar la siguiente cita disponible”
  - “Mostrar las siguientes 3 citas disponibles”
- Lista de “Próximos días con disponibilidad” (máx 10)
- Nota: este flujo solo selecciona un slot disponible del API; no crea cita hasta confirmar.

### 7.2 Pantalla “Elegir slot”
URL: `waitlist_assign_pick_slot.php`

Debe:
- Mostrar slots del día seleccionado (cards/botón seleccionar)
- Volver a “elegir día”

### 7.3 Confirmación de asignación
De regreso en `waitlist.php`, se muestra un bloque con:
- Paciente elegido
- Horario seleccionado
- Botón “Confirmar asignación”

Campos importantes que deben viajar:
- `id` (waitlist entry)
- `start_at`, `end_at`
- `doctor_id`, `consultorio_id`
- `slot_minutes`, `date`
- `actor_role`, `actor_id`, `channel_origin`

---

## 8) Override y trazabilidad (operadora)
### 8.1 Cuándo se permite override
Solo si se documenta claramente un motivo (ejemplos):
- Urgencia clínica indicada por médico
- Caso especial (adulto mayor, etc.)
- Error de captura previo o reorden legítimo por operación

### 8.2 Cómo se captura override en UI (v1)
- Checkbox “Override manual”
- Campo “Motivo override” (texto breve)
- Campo opcional: `linked_cancelled_appointment_id` si el hueco fue por cancelación concreta

### 8.3 Reglas UX del override
- Debe sentirse como excepción, no como operación normal.
- No debe existir “override silencioso”.

---

## 9) Guardrails UX (muy importantes)
La UI de operadora **NO debe inducir**:
- Que “Asignar” sea un método normal para “crear citas” sin hueco.
- Que la lista garantice cita.
- Que la lista sustituya disponibilidad pública.
- Que v1 ya tiene automatización de ofertas 60 min (eso es FUTURO).

Mensajes recomendados en la UI:
- “Se asigna cuando aparece un hueco real (cancelación).”
- “FIFO por defecto; override solo con motivo.”

---

## 10) Manejo de casos especiales
- Si NO hay lista activa o está vacía:
  - Al cancelarse una cita, el slot vuelve a mostrarse como disponible.
  - Aun así se registra bitácora de cancelación/slot disponible (PASO 7).
- Si hay lista activa PERO sin entradas:
  - Igual: el slot se libera normal, con trazabilidad.

---

## 11) Checklist v1 (operadora) vs FUTURO
| Elemento | v1 (manual) | FUTURO |
| --- | --- | --- |
| Captura en lista | Sí (manual) | Sí (manual/IA/call center) |
| Asignación a hueco | Sí (manual) | Sí (automática opcional + manual) |
| Oferta automática 60 min | No | Sí (SMS/WhatsApp/app) |
| Expiración 7 días | Regla documentada; limpieza/gestión operativa | Expiración automatizada + notificación |
| Override | Sí, documentado | Sí, con auditoría más estricta |
| Métricas y panel de operación | No | Sí |

---

## 12) Referencias
- PASO 2: Aterrizaje UX y reglas v1
- PASO 3: UX del paciente v1
- PASO 5: Reglas operativas y roles v1
- PASO 7: Bitácora y trazabilidad v1

