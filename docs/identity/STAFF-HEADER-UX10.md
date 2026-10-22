# STAFF-HEADER-UX10 — cabecera e identidad discreta

Baseline `38354c74d96b009cdff078bea558697932ef0c91`, [STAFF-PANEL-UX09](STAFF-PANEL-UX09.md). Rama/worktree `feature/mxmed-staff-header-ux10` / `mxmed-staff-header-ux10`.

## Alcance UI-3 autorizado

Decisión expresa: retirar el antetítulo, conservar logo/título, categorías en mayúsculas, avatar ejecutivo circular local y cierre de sesión discreto. Alternativa sin cambios: conservar cabecera UX09; no satisface la solicitud. Propuesta reversible exclusivamente aislada. Riesgos: legibilidad móvil, confundir rol con acción, movimiento al cambiar categorías. Se verifica geometría, foco y tamaños táctiles. Rollback: volver a la revisión UX09 conservada; detener sólo el runner propietario UX10. No promoción a 8091.

- PRODUCT_POLICY: exclusivamente presentación solicitada; identidad/autoridad/ciclo documental sin cambios.
- SOURCE_IMPLEMENTATION: dos archivos de producto: template `partials/identity/internal-team.php` y CSS `assets/css/internal-team.css`. Logo canónico intacto, una sola línea de título, SVG decorativo inline de ejecutivo con traje/corbata y tokens existentes, rol como texto no interactivo; logout con fondo/borde transparentes y foco visible. Categorías en mayúsculas reales, mismos IDs y aria-pressed.
- CONNECTIVITY: mismos scripts, endpoints, sesiones y contratos; sin dependencias nuevas ni recursos remotos.
- VERIFICATION: PASS, 88 comprobaciones del runner y logout/relogin/teclado adicional en ambos motores. Chromium/WebKit en 1440×900, 1366×768, 390×844; selección, estabilidad de categorías, documentación/invitación y foco. Smoke móvil interino, no aprobación móvil final ni Safari físico.
- OPERATIONAL_STATUS: owned sintético loopback independiente, cero registros reales, sin producción ni runtime original. Aceptación visual Director PENDING.

## CTX-SYNC01

CONTEXT_BASELINE=38354c74d96b009cdff078bea558697932ef0c91
AFFECTED_CAPABILITY_IDS=ID-02/ID-03/ID-07/COM-10; sólo cabecera de superficie, capacidades sin cambios
AFFECTED_JOURNEY_IDS=J09; pasos y comportamiento intactos
AFFECTED_DATA_OBJECTS=NONE; presentación estática, sin lectores/writers/esquema nuevos
AFFECTED_CONTEXT_FILES=estado, router, registro visual, contrato UX09, este contrato
CONTEXT_CHANGES_IMPLEMENTED=sucesión visual acotada de cabecera y categorías
CONTEXT_VERIFICATION=PASS; diff, fuentes y enlaces revisados
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=mismo commit aislado de entrega
REMAINING_CONTEXT_GAPS=aceptación visual y Safari físico pendientes

Inventario funcional, mapa de interacciones y mapa de datos revisados: NO_IMPACT porque no cambian capacidades, pasos, estados, autoridad ni fuentes canónicas. No se regeneran mapas por un cambio de texto y aspecto.

## Evidencia y revisión

[QA dirigida](../evidence/staff-header-ux10/qa.json): 88 PASS, snapshots protegidos sin cambios. [Acceso y teclado](../evidence/staff-header-ux10/access.json): logout/login real y categorías mediante Tab/Enter en Chromium, Option+Tab/Enter en WebKit (navegación de controles macOS). Foco visible, altura táctil de logout ≥44 px, categorías estables al alternar, selección de Martha, invitaciones y documentos actuales/históricos; consulta no acepta ni admite. 724 fuentes JavaScript/Identity/internal/API idénticas al baseline. Las únicas fuentes de producto cambiadas son template y CSS. No se ejecutó la suite histórica completa.

URL Director: https://127.0.0.1:65314/internal/media-review/index.php?section=equipo

URL Asesor: https://127.0.0.1:65314/internal/staff-workspace/index.php

Vencimiento: **22 de octubre de 2026 a las 18:33:52, Ciudad de México (UTC−06:00)**. Infraestructura 24 h, sin ampliar sesiones Identity ni MFA.

Cuenta sintética Director: `qa_director@example.test`. Desde `/Users/circulodigital/Documents/GitHub/mxmed-staff-header-ux10`:

```sh
python3 scripts/staff-header-ux10-review.py /tmp/mxmed-ase-adm02imp01-wsl852j0 open
python3 scripts/staff-header-ux10-review.py /tmp/mxmed-ase-adm02imp01-wsl852j0 credentials-director
python3 scripts/staff-header-ux10-review.py /tmp/mxmed-ase-adm02imp01-wsl852j0 mfa-director
python3 scripts/staff-header-ux10-review.py /tmp/mxmed-ase-adm02imp01-wsl852j0 stop
```

Credenciales sólo al portapapeles local. Detención por marcador validado para el runner propietario. UX09 sigue intacto como retorno. `DIRECTOR_VISUAL_ACCEPTANCE=PENDING`, `PRODUCTION_ACTIVATION=false`.
