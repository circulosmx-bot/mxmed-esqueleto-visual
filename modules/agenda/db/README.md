## Convención de base de datos — Agenda

- Cada ambiente usa una base distinta:
  - Local development: `mxmed`
  - Staging (opcional): `mxmed_staging`
  - Producción: `mxmed_prod`

- El módulo Agenda lee `api/mxmed-db.config.php` (o las vars `MXMED_DB_*`) y espera que la DB configurada tenga al menos las tablas `agenda_appointments`, `agenda_appointment_events`, `agenda_patient_flags` (el SQL mínimo está en `modules/agenda/db/ready_schema.sql`).

## Pasos rápidos
1. Instala MySQL (o MariaDB) en tu entorno local.
2. Crea la base y el usuario:
   ```sql
   CREATE DATABASE mxmed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'mxmed'@'localhost' IDENTIFIED BY 'change-me';
   GRANT ALL PRIVILEGES ON mxmed.* TO 'mxmed'@'localhost';
   FLUSH PRIVILEGES;
   ```
3. Importa el schema mínimo (desde la terminal, no dentro del prompt `mysql`):
   ```sh
   mysql -u mxmed -p mxmed < modules/agenda/db/ready_schema.sql
   ```
   Verifica las tablas:
   ```sh
   mysql -u mxmed -p mxmed -e "SHOW TABLES;"
   ```

## QA ejecutable
- Para validar el flujo sin DB: `QA_MODE=not_ready BASE_URL=http://127.0.0.1:8089/api/agenda/index.php bash modules/agenda/qa/requests.sh`
- Para validar con la DB lista: `QA_MODE=ready BASE_URL=http://127.0.0.1:8089/api/agenda/index.php bash modules/agenda/qa/requests.sh`

El primer comando debe terminar con `QA script finished (not_ready mode)` y mensajes `db_not_ready`. El segundo debe completar todo el flujo `ready` (create → events → reschedule → cancel → no_show → flags) sin errores.
