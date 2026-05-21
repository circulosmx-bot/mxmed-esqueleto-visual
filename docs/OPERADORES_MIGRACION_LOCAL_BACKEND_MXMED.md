# OPERADORES · MIGRACIÓN LOCALSTORAGE -> BACKEND (F1.4A + F1.4B)

Fecha de corte: **2026-05-20**  
Ámbito: **estrategia + estado backend implementado de migración**.

## 1) Estado actual

### 1.1 Frontend local (fuente histórica)
- Key principal: `mxmed.agenda.operators.state.v1`
- Estructura persistida:
  - `operators[]`
  - `archived_operators[]`
  - `audit_trail[]`
- Drafts de alta:
  - key: `mxmed.agenda.operators.create_draft.v1`
  - regla vigente: **no se persisten drafts inconclusos**.

### 1.2 Backend disponible (F1.1/F1.2/F1.4B)
- Tablas:
  - `agenda_operators`
  - `agenda_operator_permissions`
  - `agenda_operator_audit_events`
- Endpoints actuales:
  - `GET /api/agenda/index.php/operators`
  - `POST /api/agenda/index.php/operators`
  - `PATCH /api/agenda/index.php/operators/{operator_id}/pause`
  - `PATCH /api/agenda/index.php/operators/{operator_id}/reactivate`
  - `PATCH /api/agenda/index.php/operators/{operator_id}/archive`
  - `PATCH /api/agenda/index.php/operators/{operator_id}/restore`
  - `POST /api/agenda/index.php/operators/migration/preview`
  - `POST /api/agenda/index.php/operators/migration/apply`

### 1.3 Estado de integración frontend (F1.3)
- Operadores funciona en **read-through**:
  - intenta `GET /operators` con `doctor_id` confiable;
  - si backend tiene datos, hidrata desde backend;
  - si backend está vacío y local tiene datos, conserva local;
  - si backend falla/no listo, fallback local.
- Mutaciones de UI (alta/pausa/reactivar/archivar/restaurar/envío credenciales) siguen locales.

## 2) Tabla de mapeo localStorage -> backend

| Fuente localStorage | Destino backend | Regla de migración |
|---|---|---|
| `operators[].operator_id` | `agenda_operators.operator_id` | Conservar si no existe; si colisiona, generar nuevo ID y registrar mapeo |
| `operators[].operator_label` | `agenda_operators.operator_label` | Opcional |
| `operators[].alias` | `agenda_operators.alias` + `alias_normalized` | Normalizar a mayúsculas, sin acentos, sin espacios, `A-Z0-9-`, 3..15 |
| `operators[].full_name` | `agenda_operators.full_name` | Obligatorio |
| `operators[].phone` | `agenda_operators.phone` | Opcional |
| `operators[].email` | `agenda_operators.email` | Lowercase |
| `operators[].gender` | `agenda_operators.gender` | Normalizar |
| `operators[].role` | `agenda_operators.role` | `operator` o `assistant` |
| `operators[].status` | `agenda_operators.status` | Solo `active|paused|pending` en activos; `archived` en archivados |
| `operators[].login` | `agenda_operators.login` + `login_normalized` | Minúsculas, sin acentos, sin espacios, `a-z0-9.-` |
| `operators[].force_password_change` | `agenda_operators.force_password_change` | Boolean |
| `operators[].invitation_status` | `agenda_operators.invitation_status` | Conservar cuando aplique |
| `operators[].operator_credentials_sent_at` | `agenda_operators.operator_credentials_sent_at` | Fecha/hora opcional |
| `operators[].last_access` | `agenda_operators.last_access` | Fecha/hora opcional |
| `archived_operators[].archived_at` | `agenda_operators.archived_at` | Obligatorio para archivados |
| `operators[].permissions[]` | `agenda_operator_permissions` | Reemplazo por operador (lista permitida) |
| `archived_operators[].permissions[]` | `agenda_operator_permissions` | Conservar permisos históricos |
| `audit_trail[]` (`module/action/entity/at`) | `agenda_operator_audit_events` | Mapear a `module_name/action_label/entity_label/at`; `event_type` derivado |
| `temp_password` local | `temp_password_hash` | **Nunca migrar plano**; hash o invalidar y forzar regeneración |

## 3) Políticas de migración

### 3.1 Escenarios base

1. **Backend vacío + local con datos**
   - permitir migración asistida (preview + confirmación).

2. **Backend con datos + local con datos**
   - no auto-merge silencioso;
   - mostrar conflictos y conteos;
   - exigir decisión explícita.

3. **Backend vacío + local vacío**
   - no hay migración que ejecutar.

### 3.2 Conflictos y resolución

1. **Alias duplicado**
   - bloquear apply automático de ese operador;
   - proponer alias alterno (sufijo incremental) en preview.

2. **Login duplicado**
   - bloquear apply automático de ese operador;
   - proponer login alterno (sufijo incremental).

3. **Cupo lleno (máximo 3 contables)**
   - impedir migrar operadores contables excedentes (`active|paused|pending`);
   - permitir migrar archivados sin consumir cupo.

4. **Operador archivado local**
   - migrar como `status=archived`;
   - no contar para cupo;
   - conservar `archived_at`.

5. **Auditoría local sin equivalente exacto**
   - en F1.4B se registra evento por operador: `operator_migrated_from_local`;
   - conservar metadata técnica en `notes` JSON.

6. **Password temporal local**
   - no persistir texto plano;
   - en F1.4B se descarta password plano y se marca warning + `force_password_change`.

7. **Drafts inconclusos**
   - no migrar (regla vigente: drafts no persisten).

## 4) Flujo oficial recomendado (asistido)

1. **Detectar condiciones**
   - `doctor_id` confiable;
   - presencia de datos locales;
   - presencia de datos backend.

2. **Preview / dry-run**
   - simular mapeo y validaciones;
   - calcular:
     - migrables,
     - conflictos,
     - bloqueados por cupo.

3. **Confirmación explícita**
   - usuario confirma migración;
   - sin confirmación no se escribe backend.

4. **Apply transaccional**
   - insertar/actualizar operadores + permisos + auditoría;
   - resolver conflictos aprobados;
   - rollback completo ante error crítico.

5. **Backup local**
   - crear backup de `mxmed.agenda.operators.state.v1` con timestamp;
   - **no borrar backup en F1.4**.

6. **Rehidratación backend**
   - refrescar UI desde backend;
   - mantener fallback local solo como contingencia.

## 5) Endpoints backend F1.4B implementados

### 5.1 `POST /api/agenda/index.php/operators/migration/preview`
Implementado y activo. Comportamiento:
- no escribe datos;
- recibe `doctor_id` y fuente local (`operators`, `archived_operators`, `audit_trail`);
- normaliza alias/login;
- calcula `migratable`, `skipped`, `conflicts`, `warnings`;
- calcula `summary_before` y `summary_after_if_applied`;
- marca `has_blocking_conflicts` cuando hay conflictos bloqueantes.

### 5.2 `POST /api/agenda/index.php/operators/migration/apply`
Implementado y activo. Comportamiento:
- exige confirmación explícita:
  - `confirm: true`
  - o `confirm.accepted: true`;
- reusa validación de preview;
- si hay conflicto bloqueante devuelve `409`;
- aplica migración transaccional (rollback total si falla);
- migra operadores contables y archivados;
- crea auditoría `operator_migrated_from_local`.

### 5.3 Confirmación y limitación actual
- Confirmación de apply: **implementada**.
- `preview_hash/token`: **aún no implementado** (pendiente para endurecer el handshake preview->apply).

### 5.4 Conflictos bloqueantes actuales
- `alias_duplicated`
- `login_duplicated`
- `quota_exceeded`
- `operator_incomplete` (según datos mínimos requeridos)

### 5.5 Warnings actuales
- `temp_password_plain_discarded`
- `temp_password_hash_discarded` (si hash inválido)
- `operator_id_reassigned` (cuando hay colisión o ausencia de id)
- `status_forced_archived` (si viene en bucket archivado con estado distinto)
- `role_defaulted`

### 5.6 Password temporal y seguridad
- No se persiste `temp_password` plano.
- Si llega password temporal plano desde local:
  - se descarta,
  - se fuerza `force_password_change = true`,
  - se emite warning.
- `GET /operators` no expone password temporal ni hash.

### 5.7 Auditoría de migración
- Se registra evento por operador migrado:
  - `event_type = operator_migrated_from_local`
  - `module_name = Operadores`
  - `action_label = Operador migrado desde local`
  - `notes` con metadata técnica de origen.

## 6) QA curl ejecutado (F1.4B)

Resultado: **PASS** en matriz mínima aprobada.

1. Preview backend vacío + local válido: PASS.  
2. Apply backend vacío + local válido: PASS.  
3. Preview alias duplicado: PASS (conflicto detectado).  
4. Preview login duplicado: PASS (conflicto detectado).  
5. Preview cupo excedido: PASS (conflicto detectado).  
6. Apply sin confirmación: PASS (`400 invalid_params`).  
7. Apply con conflicto bloqueante: PASS (`409 conflict`).  
8. Archivados migran como `archived` y no cuentan para cupo: PASS.  
9. GET `/operators` post-apply devuelve estado esperado: PASS.  
10. GET no expone password temporal/hash: PASS.

## 7) Riesgos y no-go (vigente)

### Riesgos
- Inconsistencia de cupo si frontend y backend calculan sobre fuentes distintas en simultáneo.
- Duplicados de alias/login por normalizaciones diferentes.
- Auditoría local con semántica libre no mapeada uniformemente.
- Percepción de “pérdida” si backend vacío sobrescribe vista local.

### No-go (detener migración)
- `doctor_id` no confiable.
- `db_not_ready`.
- Conflictos críticos sin resolución explícita.
- intento de ejecutar apply sin confirmación explícita.
- (futuro) hash/token preview->apply inconsistente cuando se implemente.
- intento de migrar credenciales en texto plano.

## 8) Siguiente fase recomendada (F1.4C)

Implementar UI controlada de migración:
1. detección de datos locales migrables;
2. preview visible con conflictos/warnings;
3. confirmación explícita de apply;
4. feedback de resultados y recarga read-through desde backend;
5. mantener fallback local sin borrado automático de backup.

## 9) Estado por fase
- F1.4A Documentación de estrategia: **concluido**.
- F1.4B Backend preview/apply: **concluido**.
- F1.4C UI preview/confirmación: **pendiente**.
- F1.4D QA de cierre + retiro progresivo de dependencia local: **pendiente**.
