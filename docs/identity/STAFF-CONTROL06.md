# STAFF-CONTROL06 — selección coherente y autenticación contextual

Baseline verificado limpio `ab6b78349c26b9f80906065dedfdb1210449726f`, [STAFF-CONTROL05](STAFF-CONTROL05.md). Rama y worktree aislados `feature/mxmed-staff-control06-director-ux` / `mxmed-staff-control06-director-ux`.

## Decisión y ficha UI-2

Solicitud expresa del Director STAFF-CONTROL06: corregir selección, explicación de contraseña y teclado geográfico. Pantalla: administración interna del equipo. Antes, las tarjetas pendientes abrían un modal mientras persistía otro integrante a la derecha; la reautenticación no identificaba la cuenta; el selector cerrado no admitía búsqueda. Ahora la selección sustituye el detalle derecho por el caso exacto, la confirmación identifica al actor autenticado y el selector conserva su catálogo con typeahead desde cerrado/abierto. Mantener la conducta anterior conservaría las tres inconsistencias observadas. Sin rediseño general ni nuevas facultades. Riesgos: respuestas fuera de orden, conservación de borradores, foco y separación de revisión/admisión; verificación dirigida requerida. Rollback: cerrar únicamente esta composición owned y volver a la rama anterior, sin migración ni cambios a 8091. Aceptación visual del Director pendiente.

## Fuentes y límites

- `PRODUCT_POLICY`: seleccionar a una persona no la admite, no concede permisos ni la suplanta. Consulta documental, decisión de revisión, admisión y concesión explícita siguen siendo acciones independientes.
- `SOURCE_IMPLEMENTATION`: `internal-team.js` selecciona cuenta o caso y marca la tarjeta exacta con `aria-pressed`; `staff-workflow.js` monta el lector canónico existente en el panel derecho. Respuestas anteriores se descartan por generación de consulta. Navegación bloqueada durante operaciones protegidas; descartar preparación no guardada requiere confirmación. Refresco conserva caso y secciones abiertas. No crea otro perfil ni relación.
- `CONNECTIVITY`: `case`, `view`, `invitation_status`, `intentions` y `simulation_status` existentes. El bootstrap obtiene correo de `auth_accounts` para el ID resuelto por `CanonicalHttpSessionResolver` y categoría vigente de `internal_staff`. Es referencia contextual propia, no autoridad enviada por el navegador. La UI deriva «Director»/«Administrador maestro»/«usuario autenticado»; esto no habilita al Master para revisar admisiones.
- `GovernanceSecurityContext::reauthenticate`, intents, challenge/MFA, pruebas de un solo uso, binding, versiones, frescura y audit no cambian. El diálogo nombra acción, candidato y cuenta del Director; documentos protegidos quedan en un diálogo enfocado y se retiran del DOM al cerrarlo. Leer documentos no acepta evidencia ni activa staff.
- `team-selects.js` conserva el select como fuente de valor y eventos. NFD, eliminación de marcas diacríticas y minúsculas sólo se aplican al índice de búsqueda. Prefijos, flechas, Enter, Escape, foco, anuncios de coincidencia/sin resultados y límites de viewport no alteran valores canónicos. Estado cambia → ciudad incompatible se borra. Fuente `Geography` / `catalog_cp_colonias`; el ID de colonia nunca se convierte en ID municipal.
- Los seis hitos del worker continúan siendo observaciones verificadas. REVIEW_REQUIRED muestra «Simulación completada — Lista para revisión del Director». Ninguna reemisión al navegar; Lucía vencida mantiene acción explícita separada.
- `OPERATIONAL_STATUS=OWNED_SYNTHETIC_ONLY`: sin despliegue, datos reales, correo externo, consumidores Advisor nuevos ni cambios a WORKSPACE02/03. STAFF-CONTROL05 y original/8091 permanecen separados.

## Verificación

**80 comprobaciones dirigidas PASS**: [composición y admisión/grants](../evidence/staff-control06/qa.json) (18), [selección, autenticación y viewports](../evidence/staff-control06/supplement.json) (44), [estados y cancelación](../evidence/staff-control06/selection-states.json) (8), [búsqueda visible y respuestas tardías](../evidence/staff-control06/keyboard-final.json) (10). No se heredan los 52 PASS del baseline. Chromium y WebKit: 1440×900, 1366×768 y 390×844; Chromium nativo con viewport real. Safari físico NOT_RUN; aceptación visual PENDING.

Irene ACTIVE → Martha pendiente → Candela pendiente → Irene mantiene identidad y resaltado exactos. Suspensión/reactivación de Irene y baja de Samuel usan comandos canónicos; Master se consulta sólo con autoridad existente. Samuel conserva cuenta e historia. Cancelar otro caso conserva su identidad en el detalle sin acciones futuras. Martha permanece REVIEW_REQUIRED sin staff/grants; su consulta documental legítima no registra revisión positiva. Irene conserva exactamente los dos permisos Media confirmados individualmente después de admisión. Catálogo importado en DB propia; una observación temporal futura controlada del fixture de Lucía acredita su vencimiento sin cambiar expiración inmutable, emitir otra invitación ni tocar datos reales.

Contraseña incorrecta rechazada; Director con contraseña/MFA accede sólo al caso exacto; Advisor rechazado por el reader protegido. Documento se retira al cerrar; Escape, scroll/pie y geometría se conservan. Prefijos cerrados/abiertos, acentos, opciones similares, dependencia geográfica, no-resultados realmente dentro del viewport y respuestas tardías se verifican en ambos motores. [Manifiesto final](../evidence/staff-control06/final-verification.json) registra fuente, preservación, sintaxis y enlaces.

Durante el desarrollo se corrigieron dos referencias DOM/datos detectadas por el ensayo inicial y una carrera real: la selección automática al terminar de cargar el directorio podía reemplazar una elección explícita hecha durante la carga. La generación de detalle ahora impide esa sustitución. Se ajustó el ensayo móvil para volver al directorio antes de usar Agregar integrante y la espera de encabezado al refrescar. La sesión nativa inicial dejó de ser válida tras las sesiones múltiples del ensayo; el cierre nativo se recuperó con login canónico nuevo, sin extender TTL. Los intentos interrumpidos no se cuentan como PASS ni provocaron resets de registros.

Los readers/writers, autenticación, MFA y audit canónicos permanecen sin modificaciones salvo la referencia propia añadida al bootstrap. Las pruebas de admisión inicial preceden al ajuste final de carrera/estado cancelado; el recorrido posterior de selección y el complemento final verifican esos cambios sobre los mismos datos conservados.

## Revisión aislada

Director: https://127.0.0.1:62103/internal/media-review/index.php?section=equipo

Vence: **2026-10-21T18:21:11-06:00**, America/Mexico_City. No se amplían TTL de Identity o invitaciones. Datos de Martha Figueroa Rosado, Candela, Lucía e Irene son enteramente sintéticos.

Desde el worktree de esta rama:

```sh
python3 scripts/staff-control06-review.py /tmp/mxmed-ase-adm02imp01-7dfa0btm open-director
python3 scripts/staff-control06-review.py /tmp/mxmed-ase-adm02imp01-7dfa0btm status
python3 scripts/staff-control06-review.py /tmp/mxmed-ase-adm02imp01-7dfa0btm credentials-director
python3 scripts/staff-control06-review.py /tmp/mxmed-ase-adm02imp01-7dfa0btm mfa-director
python3 scripts/staff-control06-review.py /tmp/mxmed-ase-adm02imp01-7dfa0btm stop
```

Cuenta de revisión `qa_director@example.test`. Los comandos de credenciales y MFA copian al portapapeles exclusivamente secretos sintéticos privados; no se incluyen en fuente ni capturas. `open-director`/`open-advisor` abren o enfocan ventanas independientes; no transfieren cookies. `stop` sólo señala al runner propietario para cerrar su infraestructura. Tras vencimiento, no reutilizar la sesión.

## CTX-SYNC01

```ini
CONTEXT_BASELINE=STAFF-CONTROL05 ab6b78349c26b9f80906065dedfdb1210449726f
AFFECTED_CAPABILITY_IDS=ID-02,ID-03,ID-07,COM-10; catálogo geográfico existente sin nuevas capacidades
AFFECTED_JOURNEY_IDS=J09; J04 sólo reutilización de catálogo
AFFECTED_DATA_OBJECTS=lecturas de casos/invitaciones/intenciones y cuenta propia; sin DDL ni writers nuevos
AFFECTED_CONTEXT_FILES=ESTADO_ACTUAL_MXMED; README; MXMED_REGISTRO_CONTRATOS_VISUALES; INVENTARIO_FUNCIONAL_MXMED; INTERACCIONES_MXMED; DATOS_Y_MIGRACIONES_MXMED; identity/STAFF-CONTROL06
CONTEXT_CHANGES_IMPLEMENTED=selección cuenta/caso; actor en reautenticación; teclado geográfico; documentos enfocados
CONTEXT_VERIFICATION=80 checks de esta tarea; diff completo, fuente, hashes, sintaxis y enlaces pertinentes
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en commit aislado; hash en entrega
REMAINING_CONTEXT_GAPS=aceptación visual y Safari físico pendientes; integraciones ajenas sin cambios
```

## Sucesor visual

El [ajuste R2 solicitado por el Director](STAFF-CONTROL06-R2.md) sucede la disposición de cabecera, lista y acción de alta y añade ayuda local MFA; este contrato conserva la evidencia histórica de selección y autorización.
