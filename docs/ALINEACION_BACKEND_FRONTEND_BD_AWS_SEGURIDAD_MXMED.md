# ALINEACION BACKEND, FRONTEND, BD Y AWS PARA SEGURIDAD CLINICA (MXMed)

## 1) Objetivo
Definir un plan tecnico unificado para evolucionar MXMed hacia:
- `patient_id` global.
- expediente privado por medico/contexto.
- control de acceso explicito.
- auditoria clinica trazable.
- pseudonimizacion de logs.
- integracion futura con AWS KMS (sin implementar cifrado todavia).

Este documento alinea decisiones para backend, frontend, base de datos y arquitectura AWS sin romper Agenda, Pacientes, Expediente ni Consulta.

## 2) Alcance y restricciones de esta fase
- Fase de diagnostico + arquitectura + plan.
- Sin cambios en produccion.
- Sin cambios de endpoints funcionales.
- Sin implementacion de cifrado aun.
- Sin migraciones ejecutadas.

## 3) Fuentes base
- `docs/ARQUITECTURA_PACIENTE_GLOBAL_EXPEDIENTE_PRIVADO_MXMED.md`
- `docs/SEGURIDAD_DATOS_CLINICOS_AWS_KMS_MXMED.md`
- `docs/agenda-rescate-funcional.md`
- `docs/MAPEO_AGENDA_MXMED.md`
- `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md`

## 4) Diagnostico actual (codigo real)

### 4.1 Backend
1. Agenda ya tiene base de control de alcance por actor y doctor:
- `api/agenda/index.php` resuelve actor context y aplica `setActorContext(...)` a controladores.
- Se valida `doctor_id` y hay respuestas `unauthorized/forbidden` con modo estricto.

2. Pacientes no tiene el mismo endurecimiento centralizado de scope:
- `api/patients/index.php` enruta a controladores, pero no aplica un middleware uniforme de actor/scope como Agenda.

3. Clinical es amplio y funcional, pero con autorizacion distribuida por ruta:
- `api/clinical/index.php` concentra muchas rutas y validaciones locales.
- Hay fallback de actor (`clinical_request_actor_user_id`) que puede derivar a valores no deseables para hardening (`qa`) cuando falta identidad.

4. Writes de agenda ya incluyen contexto operativo de cita:
- `doctor_id`, `consultorio_id`, `patient_id` en `agenda_appointments`.
- eventos operativos en `agenda_appointment_events`.

### 4.2 Frontend
1. Existen campos tecnicos visibles para usuario final que conviene desacoplar de UX:
- `index.html`: `#ag_patient_id_input` (Paciente ID).
- `index.html`: etiqueta `Medico (doctor_id)`.

2. Hay trazas de consola con contexto sensible/identificadores en flujos clinicos/agenda.
- Riesgo: exposicion innecesaria en navegadores compartidos, capturas o soporte remoto.

### 4.3 Base de datos
1. Pacientes:
- `patients_patients`, `patients_contacts`, `patients_consents`, `patients_doctor_links`.
- Existe relacion doctor-paciente formal.

2. Agenda:
- `agenda_appointments`, `agenda_appointment_events`, `agenda_patient_flags`, `agenda_patient_incidents`.
- Estructura madura para operacion de citas.

3. Clinical v2:
- Referencia `patients_patients.patient_id`.
- Tablas clave: `clinical_record_entries`, `clinical_consents`, `clinical_cases`, `clinical_encounters`.
- Gap de alineacion: en varias tablas clinicas estructuradas no esta explicitado de forma uniforme el contexto clinico (`doctor_id`, `clinic_id`, `institution_id`) para enforcement directo por fila.

## 5) Decisiones arquitectonicas (alineacion objetivo)

## 5.1 Paciente global + expediente privado
1. `patient_id` es identidad global del paciente.
2. El expediente es privado por contexto medico.
3. Compartir `patient_id` no comparte automaticamente expediente entre medicos.
4. Acceso cruzado (interconsulta/referencia) solo con permiso explicito y auditable.

## 5.2 Agenda no inicia consulta automaticamente
1. Agenda puede abrir contexto operativo del paciente.
2. Iniciar consulta clinica debe ser accion explicita del usuario.
3. La apertura de expediente/encuentro debe pasar por autorizacion clinica, no por mera navegacion.

## 5.3 Autorizacion explicita por contexto
Toda operacion clinica/sensible debe resolverse contra:
- actor (`user_id`, `role`)
- `doctor_id`
- `clinic_id`
- `institution_id`
- permiso especifico por accion

## 6) Propuesta por capa

### 6.1 Backend

#### 6.1.1 Estandar de actor context
Unificar un helper transversal (sin romper rutas actuales), por ejemplo:
- `resolveActorContext(request)`
- `requireDoctorScope(context, requestedDoctorId)`
- `requireClinicalScope(context, targetRecordContext)`

#### 6.1.2 Politicas de acceso (contrato funcional)
Definir helpers de autorizacion reutilizables:

```php
canAccessPatientRecord(array $actorContext, string $patientId, string $action, array $context = []): array
canAccessClinicalRecord(array $actorContext, string $recordId, string $action, array $context = []): array
```

Respuesta sugerida:

```php
[
  'allowed' => true|false,
  'code' => 'ok|forbidden|not_found|scope_mismatch',
  'reason' => '...',
  'audit_tags' => [...]
]
```

#### 6.1.3 Donde validar minimo
1. Patients:
- Lectura por `doctor_id` vinculado o permiso institucional.
- Alta de paciente global separada de autorizacion de lectura clinica.

2. Agenda:
- Mantener validacion actual de `doctor_id`.
- En writes, validar que `patient_id` sea accesible por el contexto del medico (relacion activa o politica equivalente).

3. Clinical:
- Endurecer rutas de lectura/escritura con policy comun.
- Evitar fallback permisivo de actor en rutas productivas.

#### 6.1.4 Auditoria backend
Registrar eventos de acceso clinico en tabla de auditoria dedicada (ver seccion BD), no en logs tecnicos.

### 6.2 Frontend

#### 6.2.1 Regla UX de identificadores
1. Ocultar `patient_id` al usuario final en flujos normales.
2. Usar nombre + metadatos funcionales para UX.
3. Mantener IDs internamente en estado/app payload.
4. Mostrar identificadores tecnicos solo en modo debug controlado.

#### 6.2.2 Regla de consola
1. No loggear PII ni datos clinicos.
2. Si se requiere traza, usar referencias pseudonimizadas.
3. Encapsular debugging tras bandera (`MXMED_DEBUG=true`) y sanitizacion.

#### 6.2.3 Frontera Agenda/Paciente/Expediente
1. Agenda abre contexto de paciente, no consulta automatica.
2. Botones de abrir expediente/consulta deben solicitar contexto explicito y pasar por backend policy.

### 6.3 Base de datos

## 6.3.1 Tablas actuales a preservar
- `patients_patients`
- `patients_doctor_links`
- `agenda_appointments`
- `agenda_appointment_events`
- `clinical_record_entries`
- `clinical_encounters`
- `clinical_cases`

## 6.3.2 Alineacion recomendada (propuesta de esquema)
1. Relacion medico-paciente (ya existe):
- `patients_doctor_links` como base de acceso operativo.

2. Contexto clinico por registro:
- Asegurar que recursos clinicos tengan o hereden de forma trazable:
  - `doctor_id`
  - `clinic_id`
  - `institution_id`

3. Auditoria dedicada:

Tabla propuesta `clinical_audit_logs`:
- `audit_id` (PK)
- `occurred_at`
- `actor_user_id`
- `actor_role`
- `doctor_id`
- `clinic_id`
- `institution_id`
- `patient_ref` (pseudonimo)
- `target_type` (record|encounter|document|case)
- `target_id`
- `action` (read|create|update|close|export)
- `decision` (allow|deny)
- `reason_code`
- `request_id`
- `source_ip_hash`
- `user_agent_hash`
- `meta_json`

4. Campos candidatos a cifrado (futuro):
- notas clinicas, diagnosticos, planes, documentos adjuntos, observaciones sensibles.

5. Campos en claro para busqueda operativa (minimos):
- IDs, fechas, estado, llaves de relacion, codigos de flujo.

## 6.3.3 Indices sugeridos
- `patients_doctor_links (doctor_id, status, patient_id)`
- `agenda_appointments (doctor_id, consultorio_id, start_at)`
- `clinical_encounters (patient_id, encounter_dt)`
- `clinical_audit_logs (doctor_id, occurred_at)`
- `clinical_audit_logs (patient_ref, occurred_at)`

### 6.4 AWS / Seguridad

## 6.4.1 Principios
1. No guardar llaves criticas en repo, BD o codigo.
2. Gestion criptografica con AWS KMS.
3. Secretos de app en AWS Secrets Manager / SSM Parameter Store.

## 6.4.2 Envelope encryption (objetivo)
1. KMS key protege data keys.
2. Data key cifra payload clinico sensible.
3. Se almacena payload cifrado + data key cifrada.

## 6.4.3 IAM y minimo privilegio
1. Roles separados por servicio y ambiente.
2. Politicas KMS por accion (`Encrypt`, `Decrypt`, `GenerateDataKey`) y contexto.
3. Deny por defecto fuera de rutas autorizadas.

## 6.4.4 Ambientes
- Dev / Test / Prod con:
  - llaves distintas
  - secretos distintos
  - cuentas/roles separados

## 6.4.5 Recuperacion
Documentar y probar:
- acceso break-glass
- politicas KMS respaldadas
- rotacion de llaves
- recifrado planificado
- restauracion de backups
- procedimiento ante perdida de acceso IAM/KMS

## 7) Datos sensibles y politica de minimizacion

## 7.1 No registrar en logs tecnicos
- nombre completo
- CURP
- telefono
- email
- diagnostico libre
- notas clinicas

## 7.2 Uso de referencia pseudonima
Definir `patient_ref` (ej. HMAC-SHA256 de `patient_id` con sal/clave de entorno no expuesta al cliente).

## 7.3 Separacion de tipos de log
1. Logs tecnicos: rendimiento/errores, sin PII.
2. Auditoria clinica: acceso/accion/decision con trazabilidad regulatoria.

## 8) Matriz de alineacion por capa

| Capa | Estado actual | Gap | Accion propuesta | Prioridad |
|---|---|---|---|---|
| Backend Agenda | Scope doctor consolidado | Falta estandar transversal para otros modulos | Extraer helper/policy comun de actor/scope | Alta |
| Backend Patients | CRUD funcional | Scope/permisos no uniformes | Aplicar helper comun `canAccessPatientRecord` | Alta |
| Backend Clinical | Rutas robustas pero monoliticas | Autorizacion distribuida y fallback de actor | Endurecer policy central + auditoria | Alta |
| Frontend Agenda/Pacientes | UX operativa | IDs tecnicos visibles y logs con contexto | Ocultar IDs, debug seguro, sanitizar consola | Media-Alta |
| BD | Estructura base madura | Falta tabla de auditoria clinica dedicada y contexto explicito uniforme en recursos clinicos | Disenar migraciones controladas | Alta |
| AWS Seguridad | Principios definidos en docs | Sin capa tecnica integrada aun | Fase gradual KMS + IAM + secretos + recovery | Media-Alta |

## 9) Fases de implementacion recomendadas

### Fase A (documentacion y contrato)
- Congelar contratos de autorizacion y auditoria.
- Alinear naming y codigos de error (`forbidden`, `scope_mismatch`, etc.).

### Fase B (backend hardening sin cifrado)
- Introducir helpers `canAccessPatientRecord` y `canAccessClinicalRecord`.
- Aplicarlos en Patients/Clinical primero en modo observacion (audit-only), luego enforcing.

### Fase C (frontend hardening)
- Retirar visualizacion innecesaria de `patient_id`.
- Sanitizar logs y proteger debug.

### Fase D (BD y auditoria)
- Crear tabla(s) de auditoria clinica.
- Agregar indices y campos de contexto faltantes en recursos clinicos donde aplique.

### Fase E (AWS KMS)
- Integrar envelope encryption en capas de persistencia sensibles.
- Activar rotacion y politicas IAM definitivas.

### Fase F (QA tecnico y cumplimiento)
- Pruebas de autorizacion por rol/alcance.
- Pruebas de no fuga de datos en logs.
- Pruebas de recuperacion operativa KMS/IAM.

## 10) Riesgos y mitigaciones

1. Riesgo: romper flujos existentes por enforcement abrupto.
- Mitigacion: activar politicas en modo observacion primero y monitorear.

2. Riesgo: fuga de datos en trazas legacy.
- Mitigacion: barrido de `console.*` y logger backend con sanitizacion.

3. Riesgo: inconsistencia entre modulos (Agenda/Pacientes/Clinical).
- Mitigacion: un solo helper de autorizacion + contratos de respuesta comunes.

4. Riesgo: complejidad operativa KMS/IAM.
- Mitigacion: plan de adopcion por fases y runbooks de recuperacion.

## 11) QA tecnico futuro (checklist)
1. Acceso a expediente de paciente por medico no relacionado debe negar.
2. Relacion medico-paciente activa debe permitir agenda operativa segun permiso.
3. Abrir Agenda no debe iniciar consulta automaticamente.
4. Logs tecnicos no deben contener PII ni contenido clinico.
5. Eventos de auditoria deben registrar actor, accion, objetivo y decision.
6. Modo debug no debe activarse por defecto en produccion.
7. Cambios de alcance (`doctor_id`, `clinic_id`) deben quedar trazados.
8. Cifrado (cuando se implemente) debe ser transparente para funcionalidades existentes.

## 12) Entregables de esta fase
- Documento maestro de alineacion creado.
- Sin cambios funcionales de backend/frontend/endpoints.
- Base lista para iniciar implementacion por fases sin improvisacion.
