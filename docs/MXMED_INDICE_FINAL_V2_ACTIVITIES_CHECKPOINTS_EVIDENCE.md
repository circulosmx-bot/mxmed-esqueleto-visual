# MXMed Product Refinement V2 — Índice final de actividades, checkpoints y evidencia

## Fuente y criterio

Índice construido únicamente desde Git, `docs/PLAN_MAESTRO_MXMED.md` y el preflight formal de Actividad 22. El commit de cierre/target es el commit al que resuelve el checkpoint real. Para tags anotados se conserva tanto el objeto tag como su target; para tags ligeros el objeto es directamente el commit.

No se impone una nomenclatura uniforme. Los nombres de etapa son descripciones verificables derivadas del subject Git o del PP indicado.

## Resumen histórico

```text
ANNOTATED_HISTORICAL_CHECKPOINTS_WITH_LEADING_ZERO=6/6
LIGHTWEIGHT_HISTORICAL_CHECKPOINTS_WITH_LEADING_ZERO=3/3
ANNOTATED_CANONICAL_CHECKPOINTS=12/12
ACTIVITIES_WITH_CHECKPOINT=21/21
VALID_HISTORICAL_ACTIVITY_RECORDS=21/21
```

### Checkpoints anotados históricos con cero inicial

- `checkpoint/mxmed-product-refinement-v2-activity01`
- `checkpoint/mxmed-product-refinement-v2-activity02`
- `checkpoint/mxmed-product-refinement-v2-activity06`
- `checkpoint/mxmed-product-refinement-v2-activity07`
- `checkpoint/mxmed-product-refinement-v2-activity08`
- `checkpoint/mxmed-product-refinement-v2-activity09`

### Checkpoints ligeros históricos con cero inicial

- `checkpoint/mxmed-product-refinement-v2-activity03` → `2172423557b1ba7d5840d9ea4861d74f5516bda3`
- `checkpoint/mxmed-product-refinement-v2-activity04` → `bbbab40f3c423bb73e0afa362cd51eea0b504e17`
- `checkpoint/mxmed-product-refinement-v2-activity05` → `be9cd4f067096a7798d1ea6941ec291981039823`

El checkpoint `activity03` es válido. Su target tiene subject `docs(product): aprueba decisiones de identidad y alcance actividad 4` y su parent es el commit de auditoría de la Actividad 3 `85a8b95bcb701557d62a30ffc6f3f0b33effaa8c`. Este índice preserva el registro; no lo corrige ni lo reinterpreta como error.

### Checkpoints anotados canónicos

Son los doce tags sin cero inicial desde `checkpoint/mxmed-product-refinement-v2-activity10` hasta `checkpoint/mxmed-product-refinement-v2-activity21`.

## Registros de Actividad 1–21

| Act. | Nombre o etapa verificable | Commit de cierre/target | Checkpoint real | Tipo y objeto | Integrado | Evidencia relevante | Restricción posterior |
|---:|---|---|---|---|---|---|---|
| 01 | Catálogo de precios y modalidades V2 | `4cbbf3b5b85399ebb8ce9d51120d2c7b62aa397c` | `checkpoint/mxmed-product-refinement-v2-activity01` | anotado histórico; `169737c84f2a8488215c07f623be5d2458a0b5e0` | sí | Git; PP-281–PP-282 | Precios y autoridad comercial sujetos a gobierno posterior |
| 02 | Autoridad de capacidades existentes V2 | `78e76e338df17c482ae7e3eb4ece6e03f06c39e8` | `checkpoint/mxmed-product-refinement-v2-activity02` | anotado histórico; `61f21de5849b384b3ad1db642d66a7cd3daf896d` | sí | Git; PP-283–PP-285 | Preservar autoridad backend y compatibilidad visual |
| 03 | Auditoría de identidad y decisiones de alcance | `2172423557b1ba7d5840d9ea4861d74f5516bda3` | `checkpoint/mxmed-product-refinement-v2-activity03` | ligero histórico; commit directo | sí | Git; PP-286–PP-287; parent `85a8b95b…` | Preservar literalmente la irregularidad histórica válida |
| 04 | Identidad, acceso y sesiones V2 | `bbbab40f3c423bb73e0afa362cd51eea0b504e17` | `checkpoint/mxmed-product-refinement-v2-activity04` | ligero histórico; commit directo | sí | Git; PP-288–PP-293 | Autorización y sesiones permanecen fail-closed |
| 05 | Auditoría de APIs, datos, permisos, privacidad y retención | `be9cd4f067096a7798d1ea6941ec291981039823` | `checkpoint/mxmed-product-refinement-v2-activity05` | ligero histórico; commit directo | sí | Git; PP-294–PP-295 | Parámetros de privacidad/retención requieren gobierno |
| 06 | Contratos transversales y contención legacy PG-08 | `6fac128e77472d4fafab7088a82c3a4df15c5761` | `checkpoint/mxmed-product-refinement-v2-activity06` | anotado histórico; `3d1bdfee56e8867c796ef7a89e2a60aec381962c` | sí | Git; PP-296–PP-301 | Contención legacy y break-glass deshabilitado |
| 07 | Auditoría y decisiones PG-03 | `ee625b0b57c0caa623c4b156cfa2734a6881cf85` | `checkpoint/mxmed-product-refinement-v2-activity07` | anotado histórico; `6e714bd45013d0955fb28f45fcc83b0ff7e4b3b0` | sí | Git; PP-302–PP-303 | Implementación posterior sujeta a gates |
| 08 | Gates 8A–8G de Agenda e identidad de pacientes | `4072dff286bcf0de05e845f4eb9cf354c059b028` | `checkpoint/mxmed-product-refinement-v2-activity08` | anotado histórico; `879695ae2e6308eb748e06a29280439a9132ae06` | sí | Git; PP-304–PP-310 | Rollout deshabilitado y merge de pacientes prohibido |
| 09 | Auditoría de readiness de cutover PG-03 | `1e3057f2a12afed9da7e6ce95cd20ae81d645c1f` | `checkpoint/mxmed-product-refinement-v2-activity09` | anotado histórico; `b0a41c2d32f85391b5ce2ac9a82e0d1687c07c69` | sí | Git; PP-311 | `NO_GO_BLOCKERS_PRESENT` |
| 10 | Readiness y decisiones CUT-01 PG-03 | `9f3a3187192ddd4f9307841807f207365b2cd529` | `checkpoint/mxmed-product-refinement-v2-activity10` | anotado canónico; `507bcc51e01a4203221ec36bd933c65e98e255fc` | sí | Git; PP-312 | Cutover y runtime no autorizados |
| 11 | CUT01-A Authority Composition Roots | `d492400a6e8c05901d0e2a1065d4657d3065fdd3` | `checkpoint/mxmed-product-refinement-v2-activity11` | anotado canónico; `b31900c96ae7a88ecc07e6ea717c58160fa51e6b` | sí | Git; PP-313 | Roots dormidos y flags false |
| 12 | CUT01-B Schedule, Scope and Sentinel Adapters | `9eac1e9c10c2d7a99ff93b1d53319effea2f51a6` | `checkpoint/mxmed-product-refinement-v2-activity12` | anotado canónico; `628676d97700a0906251ccd3f2028974d01517cf` | sí | Git; PP-314 | Adapters dormidos y sin wiring runtime |
| 13 | CUT01-C OTP, DDL Containment and Privacy Boundaries | `eba2ed0dcc1b11fde4f79e6baeb0901bf17e4093` | `checkpoint/mxmed-product-refinement-v2-activity13` | anotado canónico; `a14fa0d8d28919329b8e0ae549c8d043e8d68795` | sí | Git; PP-315 | OTP/DDL reales prohibidos y flags false |
| 14 | CUT01-D Observability and Clinical Boundary Harness | `fa95d7af6c00adb7e2c5070b3279d036d9ff3fcc` | `checkpoint/mxmed-product-refinement-v2-activity14` | anotado canónico; `fffa278f7d183877ea1a9eb2b7e18bc9bf09d618` | sí | Git; PP-316 | Sin sink, métricas, auditoría o Clinical real |
| 15 | CUT-02 Shadow and Audit-Only Readiness | `0792e6707f63aadde10cb859534d78d23192e894` | `checkpoint/mxmed-product-refinement-v2-activity15` | anotado canónico; `2eaf8059d34818d24b2c0bbecd327f76b31d9e15` | sí | Git; PP-317 | CUT-02, R1 y R2 no autorizados |
| 16 | CUT-02 Director Decisions Approval | `11c42909c3b077c3171932242fb1de08fbcafa21` | `checkpoint/mxmed-product-refinement-v2-activity16` | anotado canónico; `78a7747ca9b5128685ad060bee05a1b39f75a6cf` | sí | Git; PP-318 | Parámetros operativos permanecen diferidos |
| 17 | CUT-02A R0 Shadow Harness | `2964d2f1a1c51cd94b3c3eb7df1caa0abc792904` | `checkpoint/mxmed-product-refinement-v2-activity17` | anotado canónico; `484350569709330499c7cf9acdc4a9d0aeff315a` | sí | Git; PP-319 | Harness offline; R0 disabled |
| 18 | CUT-02B Baseline Sanitized Evidence Readiness | `d765b3270b8085f1f6bec1f321932d82b7dd0f77` | `checkpoint/mxmed-product-refinement-v2-activity18` | anotado canónico; `77cf0686933c8c876bb6644e4d74fb5ef79f7886` | sí | Git; PP-320 | Plan documental; baseline real no autorizado |
| 19 | CUT-02C Synthetic Baseline Package | `d63bb02544cd00807ca931456991e692f3115f6f` | `checkpoint/mxmed-product-refinement-v2-activity19` | anotado canónico; `f88f5396a6bedf50c26b17c9c843165cb25e9afb` | sí | Git; PP-321 | Evidencia sintética únicamente |
| 20 | CUT-02D Technical Review and Sampling Proposal | `be68cb7f8c3fee2f3c9e21ecd3cb9e72bcf9a396` | `checkpoint/mxmed-product-refinement-v2-activity20` | anotado canónico; `9d2b7bc71e746d5ed59ff61d622dac1b9ec6b56d` | sí | Git; PP-322 | Propuesta sin parámetros efectivos |
| 21 | CUT-02E Director Ratification and R0 Governance Gate | `ac4860e381a8714754408e9f12bf5e5889c1cf99` | `checkpoint/mxmed-product-refinement-v2-activity21` | anotado canónico; `b4e652a320fc87aba1d054c0cea71ea540686ee6` | sí | Git; PP-323 | Gobierno efectivo; runtime efectivo 0/8 |

## Integridad histórica

```text
CHECKPOINTS_CREATED_RETROACTIVELY=0
CHECKPOINTS_REBUILT=0
CHECKPOINTS_RENAMED=0
CHECKPOINTS_REPLACED=0
CHECKPOINTS_CONVERTED=0
CHECKPOINTS_DELETED=0
CHECKPOINTS_REPUBLISHED=0
```

La Actividad 22 aún no tiene checkpoint. Tampoco existe checkpoint final. Ambos quedan reservados para integración controlada posterior.
