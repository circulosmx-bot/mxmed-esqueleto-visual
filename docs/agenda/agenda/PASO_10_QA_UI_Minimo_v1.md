# Agenda v1 — PASO 10: QA UI mínimo v1

Este QA valida UI server-rendered (sin JS dinámico) contra el API local.

## 0) Pre-requisitos

### 0.1 Servidores
A) API (puerto 8090) debe estar arriba.
B) UI (puerto 8091) debe estar arriba con AGENDA_API_BASE apuntando al API.

Comandos sugeridos (en 2 terminales separadas):

API:
- (el que ya usas para el API en 8090)

UI:
export AGENDA_API_BASE="http://127.0.0.1:8090/api/agenda/index.php"
php -S 127.0.0.1:8091 -t .

### 0.2 Parámetros base para pruebas
- date=2026-02-10
- doctor_id=1
- consultorio_id=1
- slot_minutes=30

URLs base:
- Day:
  http://127.0.0.1:8091/api/agenda/ui/day.php?date=2026-02-10&doctor_id=1&consultorio_id=1&slot_minutes=30
- Waitlist:
  http://127.0.0.1:8091/api/agenda/ui/waitlist.php?date=2026-02-10&doctor_id=1&consultorio_id=1&slot_minutes=30

## 1) Smoke test (HTML responde)

### 1.1 Day responde 200 y no muestra errores
curl -s "http://127.0.0.1:8091/api/agenda/ui/day.php?date=2026-02-10&doctor_id=1&consultorio_id=1&slot_minutes=30" | rg -n "network_error|http_error|invalid_json|Deprecated" || true

### 1.2 Waitlist responde 200 y no muestra errores
curl -s "http://127.0.0.1:8091/api/agenda/ui/waitlist.php?date=2026-02-10&doctor_id=1&consultorio_id=1&slot_minutes=30" | rg -n "network_error|http_error|invalid_json|Deprecated" || true

Criterio: ambos comandos deben imprimir “nada”.

## 2) Navegación y preservación de contexto

### 2.1 Navbar preserva params (Agenda/Lista de espera)
- En Day, click “Lista de espera” y confirmar que la URL contiene date/doctor_id/consultorio_id/slot_minutes.
- Regresar con “Agenda” y confirmar lo mismo.

Criterio: nunca vuelve a hardcodear doctor_id=1/consultorio_id=1 si ya venían distintos.

## 3) Waitlist: casos básicos

### 3.1 Lista vacía
- Abrir waitlist.php en navegador.
Criterio: muestra “No hay pacientes en lista de espera.” (o equivalente de UI actual).

### 3.2 Alta de entry (manual)
- En waitlist.php, capturar un nombre/nota mínima y crear entry.
Criterio:
- aparece en tabla
- flash de éxito (si aplica)
- no rompe navegación

### 3.3 Cambio de status
- Cambiar status del entry (ej. removida o similar según UI)
Criterio:
- status refleja cambio
- vuelve a lista sin perder params

## 4) Asignación manual: flujo completo

### 4.1 Iniciar asignación
- Desde waitlist.php, seleccionar “Asignar” en un entry.
Criterio: abre pick_day.php con entry_id + params base.

### 4.2 Pick day
- Elegir fecha (2026-02-10 o la que tenga slots) y continuar.
Criterio: abre pick_slot.php manteniendo entry_id/doctor/consultorio/slot_minutes.

### 4.3 Pick slot
- Seleccionar un slot.
Criterio:
- al confirmar, redirige a appointment.php de la cita creada
- muestra flash “Cita asignada desde lista de espera.” (o copy equivalente)

## 5) Concurrencia mínima (colisión)

Simulación: en 2 pestañas/ventanas, intenta asignar el MISMO slot:
- Ventana A confirma primero.
- Ventana B confirma después.

Criterio:
- B recibe error “Horario ocupado (collision)” o “Slot no disponible”.
- La UI redirige de regreso a pick_slot con mensaje y permite elegir otro slot.
- No se crea una segunda cita duplicada.

## 6) Errores de red controlados

### 6.1 Apagar API (8090) momentáneamente
- Dejar UI arriba.
- Recargar Day o Waitlist.

Criterio:
- UI muestra mensaje “Error de red…” (network_error) según PASO 8/9
- No se rompe el layout
- CTA “Reintentar” o equivalente

## 7) Checklist de cierre (PASO 10)
- [ ] Day carga sin errores
- [ ] Waitlist carga sin errores
- [ ] Navbar preserva params
- [ ] Alta entry OK
- [ ] Cambio status OK
- [ ] Asignación manual completa OK
- [ ] Colisión manejada OK
- [ ] Network_error manejado OK

