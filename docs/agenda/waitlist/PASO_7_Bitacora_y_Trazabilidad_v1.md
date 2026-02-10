# Lista de Espera (Waitlist) — PASO 7: Bitácora y Trazabilidad v1

## 1. Propósito
Garantizar auditoría, explicación y reconstrucción de todo lo que ocurre con:
- entradas a lista de espera
- cancelaciones
- asignaciones manuales
- futuras automatizaciones

La bitácora no decide ni ejecuta lógica: solo registra hechos.

---

## 2. Principios
- Append-only: los eventos no se editan.
- Actor explícito: siempre se sabe quién hizo qué.
- Correlación clara: todo evento apunta a `waitlist_entry_id` y/o `appointment_id`.
- Neutralidad: sin interpretación ni juicio.
- v1 mínima y suficiente (sin automatismos).

---

## 3. Actores posibles
| actor_type | Descripción |
|---|---|
| operator | Personal del consultorio |
| doctor | Médico autenticado |
| system | Sistema (expiraciones, limpiezas) |
| ai_agent | FUTURO – agente IA supervisado |
| call_center | FUTURO – operador de plataforma |

---

## 4. Eventos mínimos (v1)
| event_type | Descripción |
|---|---|
| waitlist_entry_created | Alta de paciente en lista |
| waitlist_entry_updated | Cambio de estado o notas |
| waitlist_entry_removed | Eliminación manual |
| waitlist_entry_expired | Expiración por vigencia |
| appointment_reassigned_from_waitlist | Asignación manual |
| waitlist_entry_override | Salto justificado del FIFO |

---

## 5. Campos comunes del evento
```json
{
  "event_id": "uuid",
  "event_type": "string",
  "timestamp": "YYYY-MM-DD HH:MM:SS",
  "timezone": "America/Mexico_City",
  "actor_type": "operator|doctor|system|ai_agent|call_center",
  "actor_id": "string|null",
  "waitlist_entry_id": "uuid|null",
  "appointment_id": "uuid|null",
  "metadata": {}
}

