# MAPEO OPERADORES MXMED

## 1) Alcance y evidencia
Documento de estado real del módulo **Operadores** en shell principal (`#p-ag-operadores`), validado contra:
- `index.html` (sección Operadores + modales de seguridad/historial)
- `assets/js/app.js` (bloque `Agenda · Operadores (frontend UX scaffold, backend-safe)`)
- `assets/css/style.css` (clases `mx-ag-ops-*`)

Fecha de corte: **2026-05-20**.

## 2) Estado actual de UI (concluido en frontend)
- Vista Operadores en bandas/acordeones horizontales.
- Listado de operadores registrados con expansión por operador (solo uno abierto a la vez).
- Banda **Agregar operador** en el mismo flujo visual (sin panel lateral fijo).
- Sección discreta de **Operadores eliminados** (colapsable, lista compacta).
- Modal de verificación sensible de 6 dígitos para:
  - eliminar (archivar),
  - pausar acceso,
  - reactivar acceso.
- Modal de **Historial de acciones** por operador (activo o archivado).

## 3) Arquitectura frontend de estado
Modelo principal (`MODEL`) en `assets/js/app.js`:
- `plan_standard_limit: 2`
- `plan_absolute_limit: 3`
- `operators: []`
- `archived_operators: []`
- `audit_trail: []`

Persistencia local:
- Estado principal: `mxmed.agenda.operators.state.v1`
- Draft de alta: `mxmed.agenda.operators.create_draft.v1`

Regla vigente de draft:
- **No se persiste** alta inconclusa.
- `persistCreateDraftState()` limpia estado.
- `hydrateCreateDraftState()` no rehidrata.

## 4) Wizard de alta (Agregar operador)
Pasos (`CREATE_WIZARD_STEPS`):
1. `general`
2. `access`
3. `permissions`
4. `send` (paso final de envío de credenciales simulado)

Reglas:
- Los pasos se desbloquean progresivamente.
- El botón principal depende del paso activo:
  - `Siguiente` en `general` y `access`
  - `Guardar operador` en `permissions`
  - `Enviar correo con credenciales` en `send`
- El operador se crea en `permissions`; el envío se confirma en `send`.

## 5) Alias, login y contraseña temporal

### Alias (alta y edición)
Validación activa:
- obligatorio
- normalización a mayúsculas
- sin acentos
- sin espacios
- caracteres permitidos: `A-Z`, `0-9`, `-`
- longitud: 3 a 15
- único entre operadores no archivados

### Login sugerido
Fórmula:
- `primerNombre.primerApellido`
- minúsculas
- sin acentos ni espacios
- deduplicación con sufijo numérico (`maria.lopez2`, `maria.lopez3`, ...)
- editable manualmente por usuario

### Contraseña temporal
- generada automáticamente en paso `access`
- validación mínima de complejidad (mayúscula, minúscula, número, símbolo, longitud)
- acciones de UX:
  - regenerar contraseña
  - copiar contraseña
- `force_password_change` activo por defecto

## 6) Envío de credenciales (simulado)
Estado actual:
- Simulación frontend (sin proveedor real email/SMS).
- Al confirmar envío:
  - `status = pending`
  - `invitation_status = sent`
  - `operator_credentials_sent_at = now`
  - se agrega registro a `audit_trail`.

Pendiente backend:
- endpoint seguro para envío real,
- trazabilidad server-side de entrega/errores,
- tokens de activación/verificación.

## 7) Estados de operador y cupo
Estados visibles:
- `active`
- `paused`
- `pending` (invitación pendiente/enviada)
- `archived`

Regla de cupo:
- máximo absoluto: **3**
- cuentan para límite: `active`, `paused`, `pending`
- no cuentan: `archived`

Comportamiento:
- al llegar a 3, se bloquea alta adicional.
- al archivar uno, se libera cupo.

## 8) Eliminar operador = archivar (no borrar)
Flujo vigente:
1. Click `Eliminar operador`
2. Modal de verificación 6 dígitos
3. Confirmación válida -> mover a `archived_operators`

Conserva:
- identidad (`operator_id`, alias, nombre, contacto, login),
- permisos históricos,
- `audit_trail`,
- `archived_at`.

## 9) Historial de acciones
- Fuente principal: `MODEL.audit_trail` + `operator.audit_meta`.
- Render cronológico (más reciente arriba).
- En archivados, encabezado usa `Nombre completo (Alias)` y no etiqueta `Operador 01`.

## 10) Permisos visibles actuales
Permisos de UI en wizard/edición:
- Agenda
- Pacientes
- Facturar
- Comprobante de pago

Nota:
- Esta capa es visual/operativa en frontend.
- Enforcement real por backend/RBAC sigue pendiente.

## 11) Riesgos y deuda técnica
- Seguridad de verificación 6 dígitos aún simulada en frontend.
- Envío de credenciales sin backend real.
- Auditoría persistida en localStorage (no canónica para producción).
- Falta policy server-side para permisos por módulo.

## 12) No tocar sin QA
- Regla de límite absoluto 3.
- Regla de archivado lógico (no borrado).
- Regla de no persistir drafts inconclusos.
- Regla de botón del wizard por paso activo.
- Render de archivados sin numeración operativa (`Operador 01`) en historial.

## 13) QA manual sugerido (Operadores)
1. Alta completa por wizard (general -> access -> permissions -> send).
2. Confirmar que `Guardar operador` solo aparece en `permissions`.
3. Validar alias obligatorio/único/normalizado.
4. Validar login sugerido y deduplicación.
5. Validar contraseña temporal generada/regenerada/copiada.
6. Confirmar envío simulado y estado `pending`.
7. Pausar y reactivar con verificación 6 dígitos.
8. Eliminar y validar archivado lógico.
9. Confirmar que archivados no cuentan para cupo.
10. Abrir historial de activo y archivado.

## 14) Ruta recomendada a backend real
Fase recomendada:
1. Persistencia backend de operadores + archivados + auditoría.
2. Endpoints de envío y verificación real de credenciales.
3. Enforcement RBAC por módulo/acción.
4. Reemplazo de verificación simulada (6 dígitos) por flujo seguro.
5. Migración de `audit_trail` local a bitácora canónica server-side.

## 15) Estado de migración localStorage -> backend (F1.4)
- Documento oficial de estrategia:
  - [`docs/OPERADORES_MIGRACION_LOCAL_BACKEND_MXMED.md`](OPERADORES_MIGRACION_LOCAL_BACKEND_MXMED.md)
- Estado actual:
  - F1.1/F1.2 backend list/create/mutaciones + auditoría: **concluido**.
  - F1.3 read-through con fallback local: **concluido**.
  - F1.4A documentación de migración y política de conflictos: **concluido**.
  - F1.4B preview/apply backend: **concluido**.
  - F1.4C UI de confirmación de migración: **pendiente**.
  - F1.4D QA de cierre y retiro progresivo de dependencia local: **pendiente**.

Endpoints F1.4B activos:
- `POST /api/agenda/index.php/operators/migration/preview`
- `POST /api/agenda/index.php/operators/migration/apply`

Notas F1.4B:
- `apply` exige confirmación explícita (`confirm=true` o `confirm.accepted=true`).
- Conflictos bloqueantes: alias/login duplicado, cupo excedido, operador incompleto.
- Warnings relevantes: password temporal plano descartado, reasignación de `operator_id`, normalizaciones.
- Auditoría de migración: `operator_migrated_from_local`.
- Limitación actual: aún no existe `preview_hash/token` entre preview y apply.

Reglas críticas vigentes durante F1.4:
- No migrar automáticamente sin confirmación explícita.
- No persistir password temporal en texto plano.
- Operadores archivados no cuentan para cupo.
- Si backend está vacío y local tiene datos, no vaciar UI silenciosamente.
