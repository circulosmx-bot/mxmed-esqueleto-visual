# OPERADORES · MIGRACIÓN LOCALSTORAGE -> BACKEND (F1.4A)

Fecha de corte: **2026-05-20**  
Ámbito: **documentación de estrategia** (sin cambios funcionales).

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

### 1.2 Backend disponible (F1.1/F1.2)
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
   - migrar como evento `legacy_imported` o mapeo estable;
   - conservar el detalle original en `notes` JSON si aplica.

6. **Password temporal local**
   - no persistir texto plano;
   - convertir a hash solo durante migración controlada o descartar y forzar nuevo envío de credenciales.

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

## 5) Contrato conceptual para F1.4B

> Nota: contrato propuesto para implementación posterior. No está activo aún.

### 5.1 Endpoint de preview

`POST /api/agenda/index.php/operators/migration/preview`

Payload sugerido:

```json
{
  "doctor_id": "1",
  "source": {
    "operators": [],
    "archived_operators": [],
    "audit_trail": []
  },
  "options": {
    "strict": true,
    "include_archived": true,
    "include_audit": true
  }
}
```

Respuesta esperada:

```json
{
  "ok": true,
  "data": {
    "summary_before": {},
    "summary_after_if_applied": {},
    "counts": {
      "operators_local": 0,
      "archived_local": 0,
      "audit_local": 0,
      "migrable": 0,
      "blocked": 0
    },
    "conflicts": [
      {
        "type": "alias_duplicated",
        "operator_id": "op-001",
        "value": "MARY",
        "suggested": "MARY2"
      }
    ],
    "quota": {
      "max_allowed": 3,
      "quota_used_backend": 0,
      "quota_after_apply": 0
    },
    "plan": {
      "insert_operators": [],
      "insert_archived": [],
      "insert_audit_events": []
    }
  }
}
```

Errores esperados:
- `invalid_params`
- `forbidden`
- `db_not_ready`
- `conflict` (si preview strict detecta bloqueo de cupo global)

### 5.2 Endpoint de apply

`POST /api/agenda/index.php/operators/migration/apply`

Payload sugerido:

```json
{
  "doctor_id": "1",
  "source": {
    "operators": [],
    "archived_operators": [],
    "audit_trail": []
  },
  "resolution": {
    "alias_overrides": {
      "op-001": "MARY2"
    },
    "login_overrides": {
      "op-001": "maria.lopez2"
    },
    "skip_operator_ids": []
  },
  "confirm": {
    "accepted": true,
    "preview_hash": "sha256-preview"
  }
}
```

Respuesta esperada:

```json
{
  "ok": true,
  "data": {
    "migrated": {
      "operators": 0,
      "archived_operators": 0,
      "audit_events": 0
    },
    "skipped": [],
    "state": {
      "operators": [],
      "archived_operators": [],
      "audit_trail": [],
      "summary": {},
      "limits": { "max_allowed": 3 }
    }
  }
}
```

Errores esperados:
- `preview_required`
- `preview_hash_mismatch`
- `quota_limit_reached`
- `alias_duplicated`
- `login_duplicated`
- `db_not_ready`

### 5.3 Atomicidad / rollback
- El apply debe ser transaccional por `doctor_id`.
- Si falla un paso crítico:
  - rollback total;
  - no dejar migración parcial.

## 6) QA propuesto para F1.4

### Casos verdes
1. Backend vacío + local con 1-2 operadores contables.
2. Migración con archivados incluidos.
3. Rehidratación backend correcta tras apply.

### Casos de conflicto
1. Alias duplicado contra backend.
2. Login duplicado contra backend.
3. Cupo excedido por contables.

### Seguridad y datos sensibles
1. Verificar que no se persiste password temporal en texto plano.
2. Verificar que `GET /operators` no expone hash ni password plano.

### Persistencia y respaldo
1. Confirmar creación de backup local antes de apply.
2. Confirmar que el backup no se elimina automáticamente en F1.4.
3. Confirmar recarga posterior consistente.

## 7) Riesgos y no-go

### Riesgos
- Inconsistencia de cupo si frontend y backend calculan sobre fuentes distintas en simultáneo.
- Duplicados de alias/login por normalizaciones diferentes.
- Auditoría local con semántica libre no mapeada uniformemente.
- Percepción de “pérdida” si backend vacío sobrescribe vista local.

### No-go (detener migración)
- `doctor_id` no confiable.
- `db_not_ready`.
- Conflictos críticos sin resolución explícita.
- preview/apply hash inconsistente.
- intento de migrar credenciales en texto plano.

## 8) Recomendación de implementación

1. Implementar primero **preview** (sin escrituras).
2. Validar matriz completa de conflictos.
3. Implementar **apply transaccional** con backup local.
4. Activar UI de confirmación explícita.
5. Mantener fallback local hasta QA de cierre F1.4D.
