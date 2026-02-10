# Agenda v1 — PASO 9: Contratos UI por pantalla v1

Este documento aterriza PASO 8 en cada pantalla concreta.
Regla: mismas palabras, mismos estados, mismos CTAs.

---

## A) Pantalla: Day (day.php)

### A1. Propósito
Ver disponibilidad del día y citas existentes; navegar día anterior/siguiente; cambiar fecha.

### A2. Parámetros que debe preservar SIEMPRE
- date (obligatorio)
- doctor_id (si existe)
- consultorio_id (si existe)
- slot_minutes (default 30 si inválido)

### A3. Llamadas API
- GET /availability (date, doctor_id, consultorio_id, slot_minutes)
- GET /appointments (from, to, doctor_id, consultorio_id)

### A4. Estados UI
- Loading: “Cargando…”
- Ok: render slots + lista de citas
- Empty:
  - si availability slots_count=0: “No hay horarios disponibles este día.”
  - si appointments vacías: “No hay citas registradas.”
- Error (usar PASO 8):
  - network_error/http_error/invalid_json/db_* con CTA “Reintentar”
  - collision/slot_unavailable normalmente no aparecen aquí (son de writes)

### A5. Acciones visibles
- Cambiar fecha (input date + submit)
- Día anterior / Día siguiente
- Click en slot disponible -> llevar a crear cita (appointment.php con start_at/end_at o equivalente)
- Click en una cita existente -> abrir appointment.php?id=...

### A6. Guardrails
- No prometer disponibilidad futura.
- Si el día carga pero falla appointments o availability: mostrar lo que sí cargó + banner de error parcial.

---

## B) Pantalla: Appointment (appointment.php)

### B1. Propósito
Ver detalle de cita y ejecutar acciones: cancelar, no-show, etc. (según Fase IV/V).

### B2. Parámetros a preservar
- al volver: date, doctor_id, consultorio_id, slot_minutes

### B3. Llamadas API
- GET /appointments/:id (si aplica)
- POST/PATCH según acción (cancel, no_show, etc.)

### B4. Estados UI
- Loading: “Cargando…”
- Ok: ficha con datos + botones de acción
- Error (PASO 8):
  - collision/slot_unavailable -> “Ese horario ya fue tomado…” + CTA “Ver horarios disponibles”
  - db_not_ready/info, db_error/error, network_error/error, etc.

### B5. Confirmaciones
- Acciones destructivas (cancel/no-show): confirm modal o confirmación inline.
- Resultado debe mostrar flash claro: “Acción aplicada” o mensaje de error.

### B6. Guardrails
- En caso de error, no duplicar acciones: UI debe evitar doble submit (botón disabled mientras request).

---

## C) Pantalla: Waitlist (waitlist.php)

### C1. Propósito
Ver la cola activa, agregar entrada, cambiar status, iniciar asignación manual.

### C2. Parámetros a preservar SIEMPRE
- date
- doctor_id
- consultorio_id
- slot_minutes

### C3. Llamadas / acciones
- Listar entradas activas (desde backend actual de UI; si viene de API, tratar igual)
- Alta: action.php (waitlist_add)
- Cambio status: action.php (waitlist_status)
- Iniciar asignación: link a waitlist_assign_pick_day.php (entry_id + contexto)

### C4. Estados UI
- Empty: “No hay pacientes en lista de espera.”
- Error: aplicar PASO 8 (especialmente network_error/http_error/db_*)

### C5. Copy obligatorio (arriba de la tabla)
- “La lista de espera no garantiza cita. Vigencia: 7 días. Orden FIFO salvo override documentado.”

### C6. Guardrails
- No mostrar posición exacta del paciente (si existe internamente).
- El “override” debe estar explícito y registrado en bitácora (solo si UI lo soporta).

---

## D) Pantalla: Waitlist Assign — Pick Day (waitlist_assign_pick_day.php)

### D1. Propósito
Elegir el día donde se buscará slot disponible para asignar al entry.

### D2. Parámetros a preservar
- entry_id (obligatorio)
- doctor_id, consultorio_id, slot_minutes
- date (default hoy)

### D3. Estados UI
- Loading: “Cargando…”
- Error: PASO 8
- Ok: selector de fecha + CTA “Buscar horarios”

### D4. Guardrails
- No sugiere que el paciente ya tiene cita; es “selección de slot para asignación”.

---

## E) Pantalla: Waitlist Assign — Pick Slot (waitlist_assign_pick_slot.php)

### E1. Propósito
Mostrar slots disponibles del día elegido y permitir seleccionar uno.

### E2. Llamadas API
- GET /availability (con date elegido + doctor/consultorio/slot_minutes)

### E3. Estados UI
- Empty: “No hay horarios disponibles este día.” + CTA “Cambiar día”
- Error: PASO 8

### E4. Guardrails
- Si el slot falla por collision/slot_unavailable al confirmar: regresar aquí con flash y refrescar disponibilidad.

---

## F) Acción: Waitlist Assign Confirm (action.php waitlist_assign_confirm)

### F1. Propósito
Crear la cita y vincularla al entry (manual).

### F2. Respuestas esperadas
- Ok: redirect a appointment.php (ficha de la cita) con flash “Cita asignada desde lista de espera.”
- Error:
  - collision/slot_unavailable -> redirect de regreso a pick_slot con mensaje PASO 8
  - network_error/http_error/db_* -> mostrar mensaje + “Reintentar”

---

## Checklist de cierre (PASO 9)
- [ ] Cada pantalla tiene: propósito, params, estados, errores, CTAs.
- [ ] Copy consistente con PASO 8.
- [ ] collision tratado como normal en confirmación (no como “bug”).
- [ ] Se preservan parámetros de contexto en navegación.

