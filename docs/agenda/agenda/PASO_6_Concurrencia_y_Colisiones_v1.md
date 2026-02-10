# Agenda v1 — PASO 6: Concurrencia, colisiones y consistencia (estándar)

## 1. Objetivo
Definir la regla estándar cuando dos o más pacientes (o operadores) intentan reservar el mismo slot al mismo tiempo, y asegurar un comportamiento consistente y predecible en UI, API y bitácora.

## 2. Principio estándar (regla de verdad)
**Disponibilidad optimista**: un slot se considera “disponible” mientras no exista una cita confirmada que lo ocupe.
- Ver un slot en pantalla **NO lo aparta**.
- Un slot se “gana” **solo** al confirmar una cita válida (write).
- Si dos intentos compiten, **el primer write válido gana**; los demás reciben `collision`.

## 3. ¿Hasta cuándo se sigue mostrando disponible un slot?
En v1:
- Un slot puede mostrarse como disponible en múltiples sesiones **hasta el momento exacto** en que una cita lo confirme.
- Debido a latencia/red/caché, es normal que durante segundos un slot aún se muestre “disponible” en otras pantallas.
- El sistema se corrige en el write: si ya lo tomó alguien, responde `collision`.

**Regla operativa:** “la pantalla informa; el write decide”.

## 4. Comportamiento esperado ante colisión (`collision`)
Cuando el backend responde `collision`:
- La UI debe mostrar un mensaje claro:  
  **“Ese horario ya fue tomado. Elige otro.”**
- Debe sugerir 2 acciones:
  1) Ver slots disponibles del mismo día  
  2) Ver siguiente(s) slots disponibles / otro día

No se debe culpar al usuario; se debe tratar como algo normal.

## 5. Qué NO haremos en v1 (anti-patrones)
No se implementa:
- “Apartado temporal” por sesión al mostrar disponibilidad
- Timer de bloqueo (ej. 10 min) para formularios
- Locks en frontend
- Locks distribuidos complejos

Motivo: generan bloqueos fantasma, pérdida de citas reales y mayor fricción.

## 6. Interacciones con Waitlist (lista de espera)
- La lista de espera **no compite** con el paciente directo como “reserva”.
- Waitlist solo entra cuando hay “hueco real” (cancelación/no-show con criterio de negocio) y se asigna manualmente (v1).
- Si no hay waitlist activa o está vacía, el hueco vuelve a disponibilidad normal.

## 7. Bitácora / trazabilidad mínima en colisiones
Registrar (cuando aplique):
- Intento de crear cita (write intent)
- Resultado del intento:
  - `appointment_created` (éxito)
  - `appointment_create_failed_collision` (colisión)
Campos sugeridos:
- actor_type, actor_id, channel_origin, doctor_id, consultorio_id, start_at/end_at, timestamp, metadata

## 8. Checklist de cierre (PASO 6)
- [ ] La regla “optimista + collision” está documentada como fuente de verdad.
- [ ] La UI maneja `collision` con mensaje claro y rutas de recuperación.
- [ ] No existe lógica de “hold” temporal en v1.
- [ ] Bitácora contempla fallos por colisión (cuando se instrumente).

## 9. Nota de futuro (no implementar aquí)
En fases futuras podría existir “hold” solo si:
- hay pago/confirmación fuerte o
- hay ventana corta con expiración real
y siempre con trazabilidad (para evitar bloqueos fantasma).

