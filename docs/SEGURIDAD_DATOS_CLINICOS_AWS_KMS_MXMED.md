# SEGURIDAD DE DATOS CLÍNICOS CON AWS KMS (MXMed)

## Propósito
Definir principios de seguridad y custodia criptográfica para proteger datos clínicos en MXMed.

## Principio rector
MXMed **no administrará manualmente llaves criptográficas críticas**. La gestión de llaves debe delegarse a servicios especializados como **AWS KMS** (o equivalente de nivel empresarial).

## Reglas de llaves y secretos
- Las llaves críticas no deben guardarse en:
  - base de datos
  - repositorio
  - código fuente
- La plataforma debe operar con claves administradas por KMS y políticas IAM.

## Protección de datos
- Cifrado en reposo para datos sensibles clínicos.
- HTTPS/TLS para datos en tránsito.
- Pseudonimización para logs, analítica y depuración.

## Control de acceso clínico
- Acceso por alcance y permisos explícitos con base en:
  - `doctor_id`
  - `clinic_id`
  - `institution_id`
- El acceso al expediente debe auditarse en todo momento.

## Auditoría
- Registrar quién accedió, cuándo, desde dónde y con qué acción.
- Mantener trazabilidad de lectura/escritura de datos clínicos.
- Los eventos de auditoría deben ser inmutables o con controles de integridad.

## Separación de ambientes
- Desarrollo
- Pruebas
- Producción

Cada ambiente debe tener llaves, políticas y secretos separados.

## Recomendación técnica: Envelope Encryption
Modelo recomendado:
- KMS key (CMK) protege data keys.
- Data keys cifran datos sensibles clínicos.
- Solo se desencripta la data key cuando el proceso autorizado lo requiere.

Beneficio:
- Minimiza exposición de llaves maestras.
- Facilita rotación y gobierno criptográfico.

## Plan de recuperación y continuidad
Documentar y probar periódicamente:
- Acceso administrativo AWS de emergencia.
- Estructura IAM (roles, cuentas break-glass, MFA).
- `KMS key policies` y su respaldo documental.
- Backups y restauración validada.
- Rotación de llaves y procedimiento de recifrado.
- Protocolo ante pérdida de acceso o bloqueo de llaves.

## Requisitos operativos mínimos
- Ningún desarrollador debe depender de llaves hardcodeadas.
- Rotación y acceso privilegiado deben requerir aprobación y registro.
- Cualquier acceso extraordinario debe quedar auditado.

## Nota de implementación
La implementación puede ser gradual:
1. Documentación y arquitectura.
2. Endurecimiento de controles de acceso y auditoría.
3. Integración técnica completa de cifrado y gobierno de llaves.
