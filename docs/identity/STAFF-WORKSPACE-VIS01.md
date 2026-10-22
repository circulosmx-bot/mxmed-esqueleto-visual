# STAFF-WORKSPACE-VIS01 — paridad de cabeceras

Baseline `f69af160699afe7af1aac24eb0166e9f238c3c8e`, [STAFF-HEADER-UX10](STAFF-HEADER-UX10.md). Rama/worktree `feature/mxmed-staff-workspace-vis01` / `mxmed-staff-workspace-vis01`.

## Decisión y alcance

Solicitud expresa del Director: aplicar su cabecera recientemente aprobada a Gestión de perfiles médicos. UI-2 según solicitud; se mantiene aislada la paridad visual, sin promoción a 8091. WORKSPACE02/03 y las quince áreas de STAFF-CONTROL04 permanecen. Riesgos: confundir nombre/rol, atribuir género, ocultar logout o alterar layout. Reversión: detener sólo el runner propietario y volver a UX10 conservado. No se añaden controles de cuenta.

- PRODUCT_POLICY: misma marca/tipografía, título único, identidad no accionable y logout discreto; no cambios de autoridad ni negocio.
- SOURCE_IMPLEMENTATION: `staff-platform-header.css` comparte los estilos reales de UX10 con ambos templates. Logo exacto `/assets/mexico-medico.svg`. Director conserva ilustración; Asesor reutiliza el SVG neutral de `staff-personal.js`, sin inferir género. Cabecera Advisor sin antetítulo ni subtítulo. Todo el contenido después de la cabecera permanece idéntico.
- CONNECTIVITY: `staff-workspace.js` sigue mostrando `actor.display_name` y `actor.access_level`, obtenidos por `AdvisorWorkspaceService` tras resolver sesión y staff activo. Nombre compuesto desde versión personal canónica ligada a cuenta; no desde médico/paciente. El contrato existente no expone retratos ni avatares elegidos: se usa placeholder neutral, sin nuevo lector privado ni recursos remotos. Logout conserva IDs y transporte canónico.
- VERIFICATION: PASS, 57 checks del runner y comprobaciones adicionales de acceso/foco en ambos motores. Paridad comparada en Chromium/WebKit a 1440×900, 1366×768, 390×844; regresión de selección, búsqueda, inbox, pestañas y separación de sesiones. Smoke móvil interino; Safari físico no ejecutado.
- OPERATIONAL_STATUS: nueva composición owned loopback, sólo datos sintéticos. Sin producción ni alteraciones al runtime original, roles o permisos existentes.

## CTX-SYNC01

CONTEXT_BASELINE=f69af160699afe7af1aac24eb0166e9f238c3c8e
AFFECTED_CAPABILITY_IDS=ID-02/ID-03/ID-07/COM-10; presentación de superficies existentes
AFFECTED_JOURNEY_IDS=J09; flujos conservados
AFFECTED_DATA_OBJECTS=NONE; mismos contratos y fuentes, sin DDL/writers
AFFECTED_CONTEXT_FILES=estado, router, registro visual, contratos UX10 y WORKSPACE-VIS01
CONTEXT_CHANGES_IMPLEMENTED=cabecera compartida y presentación autorizada del Asesor
CONTEXT_VERIFICATION=PASS; diff, fuentes, pruebas y enlaces verificados
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=mismo commit de entrega aislada
REMAINING_CONTEXT_GAPS=aceptación visual pendiente; Safari físico no probado

Mapas funcional/de interacciones/de datos revisados: NO_IMPACT en sus contratos; no capacidades, pasos, campos o fuentes nuevos. Sólo rutas y contrato de la superficie visual actualizados.

## Evidencia y límites exactos

[QA](../evidence/staff-workspace-vis01/qa.json), [flujo](../evidence/staff-workspace-vis01/workflow.json), [acceso/foco](../evidence/staff-workspace-vis01/access.json), [límites de fuente](../evidence/staff-workspace-vis01/source-boundaries.json). 57 checks PASS: paridad de tipografía/logo/avatar/logout y altura comparable, selección Director, nombre Asesor y restricciones, búsqueda e inbox agrupado, quince pestañas y contexto, logout canónico, separación de sesiones. Hover/foco y logout/relogin adicional PASS en Chromium/WebKit. Se inspeccionaron capturas desktop y móvil. La última comprobación incluye la transición compartida de fondo de 120 ms; movimiento reducido conserva su regla existente.

Las 887 fuentes JS/dominio/API y todo el contenido bajo la cabecera Advisor son idénticos al baseline. La cuenta Irene conserva cero grants; su inbox vacío es una restricción real, no un error visual. La navegación positiva se verifica con `qa_target`, fixture base preexistente de lectura de medios, sembrado al crear la nueva base desechable. No se ejecutan grants/revokes sobre cuentas de revisión. Director tampoco adquiere permisos operativos por su rol. Las pestañas sin consumidor siguen informativas/restringidas. El retrato privado no forma parte del contrato de identidad del workspace; el placeholder neutral es deliberado y no infiere género.

Se descartaron de forma controlada dos instancias de preparación: una detuvo la simulación por cambio de hash durante arranque; otra confirmó que Director sin grant no puede operar medios. Sus registros de fallo quedan como evidencia histórica, no como resultado final. La instancia final usa fuentes fijadas y fixture base de lectura; no se modificaron runtimes anteriores.

## Revisión actual

Director: https://127.0.0.1:53927/internal/media-review/index.php?section=equipo

Asesor: https://127.0.0.1:53927/internal/staff-workspace/index.php

Vencimiento: **22 de octubre de 2026, 18:50:35, Ciudad de México (UTC−06:00)**. Infraestructura hasta 24 horas; sin extender Identity/MFA.

Sesiones independientes: `qa_director@example.test` y `oper09_advisor@example.test`. Chromium abierto, Director con Martha seleccionada. Desde `/Users/circulodigital/Documents/GitHub/mxmed-staff-workspace-vis01`:

```sh
python3 scripts/staff-workspace-vis01-review.py /tmp/mxmed-ase-adm02imp01-t4v8p0vh open
python3 scripts/staff-workspace-vis01-review.py /tmp/mxmed-ase-adm02imp01-t4v8p0vh credentials-director
python3 scripts/staff-workspace-vis01-review.py /tmp/mxmed-ase-adm02imp01-t4v8p0vh credentials-advisor
python3 scripts/staff-workspace-vis01-review.py /tmp/mxmed-ase-adm02imp01-t4v8p0vh stop
```

Las credenciales sólo se copian al portapapeles. `stop` solicita cierre exclusivamente al runner propietario. Sin merge ni producción. `DIRECTOR_VISUAL_ACCEPTANCE=PENDING`; siguiente paso mínimo: revisión visual.
