# STAFF-PANEL-UX09 — refinamiento del panel Director

Baseline `464707ba1eb75c60eb9098962de3a74da399110b`, [STAFF-AUTH-UX08](STAFF-AUTH-UX08.md). Rama/worktree aislados `feature/mxmed-staff-panel-ux09` / `mxmed-staff-panel-ux09`.

## Decisión y ficha UI-2/UI-3

Solicitud expresa del Director: simplificar documentación/invitación, unificar acciones, ordenar Asesores antes de Administradores maestros y mejorar jerarquía de botones/cabecera con identidad visual existente. Esta entrega es la propuesta visual aislada autorizada, no aceptación física ni promoción oficial. Se conserva layout, selección, accesibilidad de controles nativos y visor; no se implementa otro módulo. Riesgos: confundir una entrega anterior con vigente, ocultar acceso histórico o fechas de aceptación, perder foco/selección en responsive. Reversión: detener sólo este runner y usar la rama AUTH-UX08 conservada. No cambia 8091.

## Cinco ejes y fuentes

- PRODUCT_POLICY: mismo modelo de sesión Director, acciones separadas y versiones canónicas de AUTH-UX08. Sólo se cambia presentación/interacción autorizada.
- SOURCE_IMPLEMENTATION: `staff-workflow.js` presenta un área documental única y selecciona la entrega vigente por `current_set_id` del servidor, no por orden supuesto. Sets restantes detrás de `Documentación histórica`, cerrada por defecto. Si no existe conjunto vigente, se explica y se conservan las entregas anteriores; no se etiqueta como vigente un set obsoleto. Estado/examen/decisión y botones documentales quedan juntos. Admisión sigue separada y grants no cambian.
- CONNECTIVITY: mismos endpoints READ/ACK/decide/admit y recibos; historial conserva selección exacta y lector protegido. No contraseña/MFA repetidos. Invitación muestra estado, emisión, vencimiento y aceptación autenticada real; entrega local y referencia se ocultan en `Más detalles`. Se mantiene la fuente `issued_at/delivered_at/consumed_at/expires_at` y America/Mexico_City. Una invitación aceptada no se convierte en vencida por su fecha histórica.
- VISUAL: `partials/identity/internal-team.php` reutiliza `/assets/mexico-medico.svg`, ya empleado por Identity; no copia/crea logo. `internal-team.css` usa ink/teal/soft/line existentes y la paleta destructiva de `mxmed-admin-visual-primitives.css`. Primarios, secundarios, neutros y destructivos diferenciados; sin colorear tarjetas o previews indiscriminadamente. Orden DOM Asesores → Administradores maestros, también para teclado.
- VERIFICATION: PASS (56 checks del runner y comprobación final móvil/confirmación en ambos motores); QA dirigida Chromium/WebKit a 1366×768, 820×900 y 390×844; fuentes Identity se comparan byte a byte con baseline.
- OPERATIONAL_STATUS: sólo nueva composición owned loopback, escenarios example.test/imágenes geométricas. AUTH-UX08 y original preservados. Sin producción, invitación externa, merge o nuevas reglas de negocio.

## CTX-SYNC01

- CONTEXT_BASELINE: `464707ba1eb75c60eb9098962de3a74da399110b`.
- AFFECTED_CAPABILITY_IDS: ID-02, ID-03, ID-07, COM-10; presentación únicamente.
- AFFECTED_JOURNEY_IDS: J09.
- AFFECTED_DATA_OBJECTS: NO_IMPACT; mismos objetos/readers/writers/versiones/audit, sin DDL.
- AFFECTED_CONTEXT_FILES: estado, router, inventario, interacciones, registro visual, contrato AUTH-UX08 y este contrato.
- CONTEXT_CHANGES_IMPLEMENTED: área documental única, historia secundaria, invitación concisa, orden de categorías y branding/énfasis existentes.
- CONTEXT_VERIFICATION: PASS; fuentes, mapas y contrato sincronizados con QA dirigida.
- CONTEXT_SYNC_STATUS: PASS.
- DOCUMENTATION_COMMIT: mismo commit aislado de implementación/evidencia.
- REMAINING_CONTEXT_GAPS: aceptación visual; Safari físico no ejecutado y límites productivos preservados.

## Evidencia y límites

[QA](../evidence/staff-panel-ux09/qa.json), [flujo](../evidence/staff-panel-ux09/workflow.json), [comprobación final responsive](../evidence/staff-panel-ux09/responsive-final.json). 56 checks PASS; comparación de 38 snapshots protegidos sin diferencias. Módulos PHP Identity idénticos al baseline; no nuevo writer, esquema, autoridad ni auditoría. Capturas Chromium/WebKit a tres tamaños. Una apertura histórica excedió el timeout inicial; repetición dirigida completa PASS. La inspección visual detectó una regla heredada de flex que estrechaba botones móviles: corregida con selector acotado y comprobación final de anchura, confirmación destructiva y Escape en ambos motores. Safari físico no probado. Aceptación visual del Director PENDING.

## Revisión sintética actual

URL Director: https://127.0.0.1:60049/internal/media-review/index.php?section=equipo

URL Asesor: https://127.0.0.1:60049/internal/staff-workspace/index.php

Vence: **22 de octubre de 2026, 16:38:43, Ciudad de México (UTC−06:00)**. No se amplía la vigencia de Identity, MFA o invitaciones. Cuenta Director: `qa_director@example.test`. Martha permanece pendiente y con dos conjuntos documentales; no se fabrica reconocimiento de examen por consultar.

Desde `/Users/circulodigital/Documents/GitHub/mxmed-staff-panel-ux09`:

```sh
python3 scripts/staff-panel-ux09-review.py /tmp/mxmed-ase-adm02imp01-mtcc5cnc open
python3 scripts/staff-panel-ux09-review.py /tmp/mxmed-ase-adm02imp01-mtcc5cnc credentials-director
python3 scripts/staff-panel-ux09-review.py /tmp/mxmed-ase-adm02imp01-mtcc5cnc mfa-director
python3 scripts/staff-panel-ux09-review.py /tmp/mxmed-ase-adm02imp01-mtcc5cnc stop
```

Las credenciales se copian localmente al portapapeles, no se documentan. `stop` solicita cierre al runner propietario. AUTH-UX08, original y puerto 8091 permanecen intactos.

## STAFF-HEADER-UX10 — refinamiento visual aislado

[Contrato y revisión](STAFF-HEADER-UX10.md), padre `38354c74`: título único, avatar local no interactivo, cierre de sesión discreto y categorías en mayúsculas. Sucede sólo esta presentación UX09; flujos/autoridad/datos intactos. UI-3 expresamente solicitada; aceptación visual PENDING, sin producción/8091.
