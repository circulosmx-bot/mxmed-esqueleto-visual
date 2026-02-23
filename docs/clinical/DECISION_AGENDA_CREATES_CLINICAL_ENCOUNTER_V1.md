# DECISIÓN: AGENDA CREA ENCOUNTER EN CLINICAL AL COMPLETAR CITA (V1)

## 1) Contexto y problema
En Camino 2 se observó que algunas cards de cita (`appointment`) no tienen episodio clínico navegable porque no existe `encounter` en Clinical para ese `appointment_id`.

Resultado operativo:
- la UI debe mostrar "Sin episodio" en esos casos,
- pero el sistema requiere una regla transversal para crear el episodio cuando la atención sí ocurrió.

## 2) Decisión
**Agenda es source-of-truth del flujo de cita y debe disparar la creación del encounter en Clinical** cuando la cita se marca como atendida/finalizada.

Esta decisión es consistente con Step22:
- `appointment_id` canónico lo define Agenda,
- Clinical debe usar ese mismo `appointment_id` para correlación.

## 3) Trigger recomendado
Disparar integración al cambiar la cita a estado de atención finalizada, por ejemplo:
- `completed`
- `attended`
- equivalente funcional en Agenda

Nota:
- no se amarra a una función/método específico en este step,
- solo al evento de transición de estado.

## 4) Request HTTP recomendado hacia Clinical
Endpoint:
- `POST /api/clinical/index.php/patients/{patient_id}/encounters`

Payload mínimo sugerido:
```json
{
  "appointment_id": "fe61cdd67e97dcfde3a70c02",
  "encounter_dt": "2026-03-02 11:00:00",
  "encounter_type": "outpatient",
  "status": "completed"
}
```

Campos:
- `appointment_id`: canónico de Agenda (obligatorio para correlación)
- `encounter_dt`: fecha/hora clínica del acto de atención
- `encounter_type`: tipo operativo (default recomendado: `outpatient`)
- `status`: estado clínico del encounter (default recomendado: `completed`)

## 5) Idempotencia (evitar duplicados)
Regla obligatoria:
- no crear múltiples encounters para el mismo `appointment_id` por repetición de eventos.

Estrategias válidas (a definir en implementación):
- verificación previa en Clinical por `appointment_id` antes de crear,
- o constraint/index único por `appointment_id` según diseño final,
- o endpoint idempotente en Clinical con upsert lógico.

## 6) Manejo de errores
Si falla la llamada a Clinical:
- **no romper el flujo principal de Agenda** (la cita puede quedar completed),
- registrar error con contexto (`patient_id`, `appointment_id`, status, timestamp),
- dejar preparado reintento asíncrono en fase posterior.

## 7) Ejemplo completo (curl)
```bash
curl -X POST "http://127.0.0.1:8091/api/clinical/index.php/patients/p_0c874aa9cbad/encounters" \
  -H "Content-Type: application/json" \
  -d '{
    "appointment_id":"fe61cdd67e97dcfde3a70c02",
    "encounter_dt":"2026-03-02 11:00:00",
    "encounter_type":"outpatient",
    "status":"completed"
  }'
```

## 8) Checklist para Step27 (implementación mínima)
- Agregar guard/feature flag de integración (encendido gradual).
- Detectar transición a estado finalizado en Agenda.
- Disparar POST a Clinical con payload mínimo.
- Aplicar idempotencia por `appointment_id`.
- Log de éxito/error y métricas básicas.
- Validar con curl + QA de embed/timeline (sin cambiar contratos existentes).

---
Alcance de Step26: **solo decisión/contrato operativo**. No se implementa código en API/UI en este step.
