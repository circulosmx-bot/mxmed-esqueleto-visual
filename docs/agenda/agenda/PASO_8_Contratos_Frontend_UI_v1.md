# Agenda v1 — PASO 8: Contratos Frontend (UI) v1

## 1. Objetivo
Definir el contrato UI estándar para:
- estados (loading/empty/ok)
- errores del API
- mensajes (copy) y CTAs
- comportamiento de recuperación

Regla: la UI no interpreta; solo aplica este documento.

## 2. Estructura estándar de respuesta (API)
La UI asume la forma:
- ok: boolean
- error: string|null
- message: string
- data: any|null
- meta: object

## 3. Estados UI base
- Loading: “Cargando…”
- Empty (sin datos): “No hay registros.”
- Ok: render normal
- Error: render de error con CTA

## 4. Tabla de mapeo error -> UI

| error | Severidad | Mensaje UI (copy) | CTA principal | CTA secundario | Notas |
| --- | --- | --- | --- | --- | --- |
| collision | warn | “Ese horario ya fue tomado. Elige otro.” | “Ver horarios disponibles” | “Cambiar día” | Comportamiento normal: refrescar disponibilidad. |
| slot_unavailable | warn | “Ese slot ya no está disponible.” | “Ver horarios disponibles” | “Cambiar día” | Similar a collision, más genérico. |
| outside_schedule | warn | “Fuera de horario disponible.” | “Ver horarios disponibles” | — | Viene de reglas/ventanas. |
| invalid_params | error | “Parámetros inválidos.” | “Volver” | — | Log interno recomendado. |
| db_not_ready | info | “Datos no disponibles por el momento.” | “Reintentar” | “Volver al inicio” | No asustar; estado de entorno/QA. |
| db_error | error | “No se pudo consultar la base de datos.” | “Reintentar” | “Volver al inicio” | Log interno requerido. |
| http_error | error | “Error al comunicar con el servidor.” | “Reintentar” | — | Opcional: mostrar status en detalle. |
| network_error | error | “No hay conexión con el servicio.” | “Reintentar” | — | Suele ser server caído / baseURL mal. |
| invalid_json | error | “Respuesta inválida del servidor.” | “Reintentar” | — | Log interno requerido. |
| default | error | “Ocurrió un error.” | “Reintentar” | — | Si message es entendible, usarlo. |

## 5. Reglas de recuperación (UX)
- Para collision/slot_unavailable: refrescar disponibilidad y mantener doctor/consultorio/date/slot_minutes.
- Para network_error/http_error: sugerir reintento; si persiste, volver a inicio.
- Para db_not_ready: mostrar como informativo (no rojo), reintento manual.

## 6. Persistencia de contexto (navegación)
La UI debe preservar siempre:
- date
- doctor_id (si aplica)
- consultorio_id (si aplica)
- slot_minutes (default 30 si inválido)

Regla: “los links de navegación heredan parámetros actuales”.

## 7. Checklist de cierre
- [ ] Existe la tabla de mapeo error->UI.
- [ ] Todos los flujos principales usan el mismo copy.
- [ ] Se preservan parámetros de contexto.
- [ ] collision se maneja como comportamiento normal.

