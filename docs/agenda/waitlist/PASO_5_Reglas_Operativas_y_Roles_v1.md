# Lista de Espera (Waitlist) — PASO 5: Reglas Operativas y Roles v1

Este paso define **quién puede hacer qué**, bajo qué condiciones y con qué límites,
para operar la lista de espera sin ambigüedades ni riesgos operativos.

---

## 1. Roles del sistema

### 1.1 Operadora de consultorio
Puede:
- Ingresar pacientes a la lista de espera.
- Editar notas internas del registro.
- Asignar manualmente una cita cuando detecta un hueco real.
- Marcar excepciones (override) con justificación.

No puede:
- Prometer fecha u hora al paciente sin cita confirmada.
- Saltarse la regla FIFO sin dejar trazabilidad.
- Eliminar registros sin motivo documentado.

---

### 1.2 Médico
Puede:
- Solicitar que un paciente sea ingresado a lista de espera.
- Autorizar excepciones clínicas documentadas.
- Visualizar asignaciones derivadas de lista de espera.

No puede:
- Alterar el orden de la lista sin justificación registrada.
- Asignar citas fuera de disponibilidad real.

---

### 1.3 Call center de la plataforma (futuro)
Puede:
- Operar múltiples listas bajo reglas estándar.
- Ingresar y dar seguimiento a pacientes.
- Ejecutar scripts de contacto supervisados.

No puede:
- Asignar citas automáticamente en v1.
- Modificar reglas de expiración.

---

### 1.4 Agente IA (permitido, futuro)
Puede:
- Ejecutar tareas repetitivas (captura, contacto, recordatorios).
- Proponer acciones a operadores humanos.

No puede:
- Confirmar citas sin validación humana en v1.
- Tomar decisiones clínicas o de prioridad.

---

## 2. Reglas operativas clave

### 2.1 Regla FIFO (First In, First Out)
- La lista se atiende por orden de ingreso.
- Cualquier alteración requiere evento `override` con motivo.

### 2.2 Bloqueo de reserva directa
- Un paciente en lista de espera **no obtiene cita automática**.
- La reserva solo se crea tras acción explícita del operador.

### 2.3 Expiración de registros
- Todo registro expira a los **7 días** si no fue asignado.
- La expiración no genera contacto automático en v1.
- El evento queda registrado en bitácora.

---

## 3. Manejo de cancelaciones

### 3.1 Con lista activa y pacientes en cola
- El operador revisa la lista.
- Asigna el hueco al primer paciente elegible.
- Se crea la cita y se registra el evento.

### 3.2 Con lista activa pero vacía
- El slot se libera como disponibilidad normal.
- Se registra la cancelación sin reasignación.

### 3.3 Sin lista de espera activa
- El sistema solo refleja disponibilidad.
- No se ejecuta ningún flujo especial.

---

## 4. Excepciones (override)

Un override es válido solo si:
- Existe justificación clara (clínica, urgencia, error previo).
- Se registra el evento correspondiente.
- No se oculta ni borra el orden original.

---

## 5. Principios que NO deben romperse

- Nunca prometer cita sin confirmación.
- Nunca ocultar una acción operativa.
- Nunca automatizar decisiones en v1.
- Toda acción relevante deja trazabilidad.

---

Este documento se apoya en:
- PASO 2 (reglas y alcance general)
- PASO 3 (experiencia del paciente)
- PASO 7 (bitácora y trazabilidad)

y deja preparado el terreno para automatización futura sin afectar v1.

