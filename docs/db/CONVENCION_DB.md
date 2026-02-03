# Convención de Base de Datos — México Médico

## 1) Una base por ambiente
- Local: `mxmed`
- Staging (opcional): `mxmed_staging`
- Producción: `mxmed_prod`
Nota: el módulo Agenda utiliza la base configurada en `api/mxmed-db.config.php` o las vars `MXMED_DB_*`.

## 2) Prefijos por módulo
Las tablas deben comenzar con el prefijo del módulo que las consume:
- `agenda_*` → citas, eventos, flags.
- `auth_*` → usuarios, roles, sesiones, tokens.
- `profiles_*` → perfiles públicos, especialidades, ubicaciones.
- `consultorios_*` → consultorios y catálogos de espacios.
- `patients_*` → pacientes y sus vínculos.
- `ehr_*` → expediente clínico (notas, diagnósticos, estudios).
- `rx_*` → recetas.
- `billing_*` → facturas, pagos y pólizas.
- `reviews_*` → opiniones.
- `notifications_*` → buzón y avisos.
- `audit_*` → bitácoras globales.
Nota: `consultorio_schedule` se mantiene por compatibilidad con Availability; podrá renombrarse si se estabiliza otro catálogo.

## 3) Catálogos vs Operación
- Catálogos: datos lentos y estables (horarios base, consultorios, catálogos). Se tratan como lectura frecuente.
- Operación: datos transaccionales y auditables (citas, eventos, flags, pagos, etc.). Se diseñan para escritura append-only si aplica.

## 4) Bitácoras append-only
- Las acciones importantes se registran como eventos (no se sobreescribe el historial). Ejemplo real: `agenda_appointment_events`.

## 5) Credenciales y configuración local
- No versionar los secretos reales (`api/mxmed-db.config.php` está ignorado por git).
- Usar `api/mxmed-db.config.example.php` como plantilla o exportar `MXMED_DB_HOST/PORT/NAME/USER/PASS/CHARSET/COLLATION`.

## 6) Checklist mínimo para nuevas tablas
1. Nombre con prefijo adecuado y sin duplicados.
2. Campos mínimos (PK, foreign keys simples, timestamps) con tipos básicos.
3. Índices simples según queries frecuentes (`doctor_id`, `consultorio_id`, `weekday`, etc.).
4. Evitar triggers/migraciones/ORM innecesarios.
5. Documentar el esquema en `modules/.../db/` o README del módulo.
6. QA: solo agregar pruebas si el flujo lo requiere y sin romper los modos `ready`/`not_ready`.
