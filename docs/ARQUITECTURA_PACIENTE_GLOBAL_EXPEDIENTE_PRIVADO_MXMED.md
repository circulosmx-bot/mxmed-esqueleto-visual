# ARQUITECTURA PACIENTE GLOBAL + EXPEDIENTE PRIVADO (MXMed)

## Propósito
Definir el principio arquitectónico para separar identidad global del paciente y propiedad del expediente clínico por contexto médico.

## Principio rector
MXMed opera con **paciente global + expediente privado**.

- `patient_id` representa la identidad global del paciente dentro del sistema.
- El expediente clínico pertenece al contexto del médico/institución que lo genera, no al sistema global como un repositorio único compartido.

## Modelo conceptual
- Un mismo `patient_id` puede relacionarse con varios doctores.
- La relación médico-paciente (`doctor_patient_relations`) **no** otorga acceso automático al expediente de otro médico.
- El acceso clínico debe resolverse por reglas explícitas de alcance y permisos.

## Reglas de Agenda
- Cada cita debe relacionarse con:
  - `doctor_id`
  - `consultorio_id`
  - `patient_id`
- Agenda puede abrir el contexto del paciente (navegación operativa).
- Agenda **no** debe iniciar consulta clínica automáticamente.
- La consulta clínica debe abrirse de forma explícita por acción del usuario.

## Interconsultas y referencias (futuro)
- Interconsultas/referencias deben usar permisos explícitos.
- Cualquier acceso cruzado a expediente requiere trazabilidad y autorización del contexto origen/destino.

## Ejemplo de estructura de datos (conceptual)

### `patients`
Tabla de identidad global del paciente.

| Campo | Tipo | Significado |
|---|---|---|
| `patient_id` | UUID/INT | Identidad global del paciente |
| `full_name` | TEXT | Nombre del paciente |
| `dob` | DATE | Fecha de nacimiento |
| `contact_data` | JSON/TEXT | Datos de contacto |

### `doctors`
Tabla de profesionales de salud.

| Campo | Tipo | Significado |
|---|---|---|
| `doctor_id` | UUID/INT | Identidad del médico |
| `clinic_id` | UUID/INT | Clínica principal |
| `institution_id` | UUID/INT | Institución |

### `doctor_patient_relations`
Vinculación operativa médico-paciente.

| Campo | Tipo | Significado |
|---|---|---|
| `relation_id` | UUID/INT | Identificador de relación |
| `doctor_id` | UUID/INT | Médico vinculado |
| `patient_id` | UUID/INT | Paciente vinculado |
| `status` | ENUM | Activa/inactiva |

### `clinical_records`
Expediente clínico privado por contexto.

| Campo | Tipo | Significado |
|---|---|---|
| `record_id` | UUID/INT | Identificador del registro clínico |
| `patient_id` | UUID/INT | Paciente global |
| `doctor_id` | UUID/INT | Propietario clínico del registro |
| `clinic_id` | UUID/INT | Contexto clínico |
| `institution_id` | UUID/INT | Contexto institucional |
| `payload` | JSON/TEXT | Contenido clínico |
| `created_by` | UUID/INT | Usuario que registra |

### `agenda_appointments`
Relación operativa de agenda con paciente global y contexto médico.

| Campo | Tipo | Significado |
|---|---|---|
| `appointment_id` | UUID/INT | Cita |
| `doctor_id` | UUID/INT | Médico de la cita |
| `consultorio_id` | UUID/INT | Consultorio de la cita |
| `patient_id` | UUID/INT | Paciente global |
| `start_at` | DATETIME | Inicio |
| `end_at` | DATETIME | Fin |
| `status` | ENUM | Estado de cita |

## Implicación clave de seguridad funcional
Compartir `patient_id` entre médicos no implica compartir `clinical_records`. El expediente permanece acotado por `doctor_id`/`clinic_id`/`institution_id` y permisos explícitos.

## Criterio operativo
Este principio habilita continuidad asistencial sin sacrificar privacidad clínica entre profesionales.
