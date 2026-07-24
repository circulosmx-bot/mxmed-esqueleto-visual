# CUT-02D — Decisiones propuestas

## Identidad y regla de vigencia

Actividad 20/22. Identificador `ARCH-QA/MXMed-PG03-CUT02-D-Synthetic-Evidence-Technical-Review-Sampling-Proposal-01`. Estas decisiones son candidatas documentales. Ninguna está ratificada, vigente o autorizada para runtime.

### DEC-020A — aceptación del paquete como evidencia sintética offline

Se propone aceptar el paquete CUT-02C únicamente como evidencia sintética offline, debido a su catálogo cerrado, determinismo, privacidad e integridad verificadas. Esta aceptación no abarca tráfico real ni readiness productiva.

```text
Status: PROPOSED_PENDING_DIRECTOR_APPROVAL
Ratified: false
Effective: false
Runtime impact: none
```

### DEC-020B — límites de representatividad y prohibición de inferencia productiva

Se propone declarar que el paquete no demuestra distribución, latencia, carga, concurrencia, capacidad, disponibilidad, fallos, proveedores, privacidad, observabilidad, operación o recuperación reales. Todo parámetro numérico requeriría baseline real y aprobación separada.

```text
Status: PROPOSED_PENDING_DIRECTOR_APPROVAL
Ratified: false
Effective: false
Runtime impact: none
```

### DEC-020C — elegibilidad y orden candidato de superficies

Se propone conservar el catálogo cerrado `canonical_actor_authority`, `canonical_schedule_read`, `canonical_availability_compare`, `canonical_appointment_lifecycle` y `canonical_patient_identity`, en ese orden candidato de revisión. El orden no constituye activación.

```text
Status: PROPOSED_PENDING_DIRECTOR_APPROVAL
Ratified: false
Effective: false
Runtime impact: none
```

### DEC-020D — estructura futura de sampling sin tasa aprobada

Se propone una estructura futura basada en unidad elegible, key opaca, scope cerrado, exclusiones de privacidad y hard stops, invariancia legacy, prohibición de write canónico y safe return R0 disabled. Tasa, ventana y volumen permanecen sin resolver.

```text
Status: PROPOSED_PENDING_DIRECTOR_APPROVAL
Ratified: false
Effective: false
Runtime impact: none
```

### DEC-020E — requisitos de sink, dashboard, alertas, owners y on-call

Se propone exigir, antes de otra revisión, sink seleccionado y configurado, retención aprobada, dashboard y alertas implementados, y owner/on-call aprobados. No se selecciona proveedor, plataforma, persona o equipo en esta actividad.

```text
Status: PROPOSED_PENDING_DIRECTOR_APPROVAL
Ratified: false
Effective: false
Runtime impact: none
```

### DEC-020F — hard stops y retorno seguro R0

Se propone reutilizar sin cambios los quince hard stops CUT-02A. Cualquier señal aplicable conservaría legacy, impediría respuesta/write canónico y retornaría a R0 disabled sin rollback SQL.

```text
Status: PROPOSED_PENDING_DIRECTOR_APPROVAL
Ratified: false
Effective: false
Runtime impact: none
```

### DEC-020G — criterios de entrada para revisión directorial

Se propone exigir trazabilidad completa, revisión de privacidad real, controles operativos, safe return, baseline real autorizado y fundamento verificable para los parámetros todavía no resueltos antes de presentar una aprobación separada.

```text
Status: PROPOSED_PENDING_DIRECTOR_APPROVAL
Ratified: false
Effective: false
Runtime impact: none
```

### DEC-020H — prohibición de R1 hasta aprobación separada

Se propone mantener R1 bloqueado hasta que decisiones y parámetros reciban aprobación separada, los controles operativos estén completos y exista baseline real suficiente para sustentar la revisión. R2 y producción permanecen fuera de alcance.

```text
Status: PROPOSED_PENDING_DIRECTOR_APPROVAL
Ratified: false
Effective: false
Runtime impact: none
```

## Resultado

```text
PROPOSED_DECISIONS=8/8
RATIFIED_DECISIONS=0/8
EFFECTIVE_DECISIONS=0/8
DECISION_RATIFICATION_AUTHORIZED=false
SAMPLING=0
ROLLOUT_STAGE=R0
ROLLOUT_MODE=disabled
```
