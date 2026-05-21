# OPERADORES · CIERRE F1 (Backend + Migración + UI)

Fecha de cierre: **2026-05-21**  
Estado: **F1 cerrada (PASS QA final reducido)**.

## 1) Resumen F1 cerrada

- F1.1 Backend base Operadores: **concluido**.
- F1.2 Mutaciones + auditoría backend: **concluido**.
- F1.3 Read-through frontend + fallback local: **concluido**.
- F1.4A Documentación de migración: **concluido**.
- F1.4B Backend preview/apply migración: **concluido**.
- F1.4C UI de migración localStorage -> backend: **concluido**.

Fixes de cierre aplicados:
- Archivados sin login local migrables (`f8c7380`).
- Normalización `archived_at` ISO en migración (`afa3e92`).
- Fallback local protegido para evitar vaciado silencioso (`4600de5`).

## 2) Endpoints finales F1

- `GET /api/agenda/index.php/operators`
- `POST /api/agenda/index.php/operators`
- `PATCH /api/agenda/index.php/operators/{operator_id}/pause`
- `PATCH /api/agenda/index.php/operators/{operator_id}/reactivate`
- `PATCH /api/agenda/index.php/operators/{operator_id}/archive`
- `PATCH /api/agenda/index.php/operators/{operator_id}/restore`
- `POST /api/agenda/index.php/operators/migration/preview`
- `POST /api/agenda/index.php/operators/migration/apply`

## 3) Estados y reglas finales

- Estados: `active`, `paused`, `pending`, `archived`.
- Contables para cupo: `active|paused|pending`.
- `archived` no cuenta para cupo.
- Máximo absoluto: **3** operadores contables por doctor.
- Alias único por doctor entre no archivados.
- Login único por doctor entre no archivados.
- Verificación de 6 dígitos para mutaciones sensibles: **temporal/simulado**.
- Password temporal no se expone en `GET /operators`.

## 4) Migración localStorage -> backend

- Flujo con `preview` + `apply`.
- `apply` exige confirmación explícita (`confirm:true`).
- Soporte de warnings (incluye `archived_login_generated`).
- Conflictos bloqueantes (alias/login/cupo/incompletos).
- Backup local conservado.
- LocalStorage **no se borra automáticamente** en F1.
- No existe migración automática sin acción del usuario.

## 5) QA final documentada (PASS)

PASS:
- `migration/apply` con `archived_at` ISO.
- Migración de archivado sin login local.
- Backend vacío + local con datos.
- Backend falla + local con datos.
- Sin doctor_id confiable + local con datos.
- Wizard local estable.
- Smoke Semana/Día.

## 6) Commits relevantes

- `7fd49c2` backend base operadores.
- `1f5b812` mutaciones y auditoría.
- `c9cc7a3` read-through fallback.
- `7024a36` documentación migración.
- `aad814a` preview/apply migración.
- `83d9418` docs estado migración backend.
- `adf97ad` UI migración.
- `f8c7380` archivados sin login.
- `afa3e92` fechas ISO.
- `4600de5` fallback local protegido.

## 7) Riesgos pendientes / F2

- Verificación de 6 dígitos sigue simulada.
- Envío real de credenciales pendiente.
- Mutaciones UI aún pueden operar local en fallback.
- Falta RBAC real sobre acciones de Agenda.
- Falta estrategia productiva para limpieza de datos QA.
- Falta `preview_hash/token` para endurecer preview->apply.

## 8) Siguiente fase sugerida

**F2 — Permisos reales de Operadores sobre Agenda**:
- Gating frontend por permiso.
- Enforcement backend por actor/permiso.
- Auditoría por actor en acciones críticas.
- Visibilidad/ocultamiento de acciones según permiso efectivo.
