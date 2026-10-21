# STAFF-DOC-VIS01 — visor de evidencia de identidad staff

Baseline limpio verificado `486124b33bdc8179ffc58f5f4eaf0d06d99463b3`, STAFF-CONTROL07. Worktree `mxmed-staff-doc-vis01`, rama `feature/mxmed-staff-doc-vis01`. Conserva las correcciones autorizadas posteriores a STAFF-CONTROL06-R2.

## Decisión y alcance UI-2

Solicitud expresa del Director: mejorar exclusivamente el visor existente que mostraba imágenes artificiales sin contexto suficiente. Se conserva el shell de diálogo, MFA y retorno al caso; se añaden contexto, agrupación documental, etiquetas registradas, ampliación y guía del siguiente paso. No se agregan documentos ni se modifica evidencia subida. Riesgos: mezcla entre casos/versiones, clasificación inventada, retención de fuentes y pie inaccesible. Reversión: cerrar sólo esta composición y volver al worktree STAFF-CONTROL07 preservado. No DDL producto ni promoción a 8091. Aceptación visual PENDING.

## Fuentes y contrato preservado

- `PRODUCT_POLICY`: [consolidación](ASE-ADM02-FUNCTIONAL-CONSOLIDATION.md), [StaffMedia](ASE-ADM02-IMP03-private-staff-media.md), [STAFF-CONTROL05](STAFF-CONTROL05.md), [STAFF-CONTROL06](STAFF-CONTROL06.md) y [STAFF-CONTROL07](STAFF-CONTROL07.md). Consulta, decisión, admisión y grants siguen separados. Master no obtiene facultades de revisión.
- `SOURCE_IMPLEMENTATION`: `staff-evidence-viewer.js` sólo presenta los bytes obtenidos por el READ canónico. No realiza solicitudes ni decide autoridad. El controlador captura el conjunto exacto de la intención, verifica el recibo y vuelve a leer el caso. Si cambió el caso/conjunto, no muestra la evidencia asociada al contexto anterior. Sólo un conjunto vigente por consulta; las versiones históricas del listado no se dibujan.
- `CONNECTIVITY`: mismos endpoints, sesiones, contraseña/MFA, intent/proof de un uso, reader privado, comprobaciones de integridad y auditoría. API, servicio y cifrado no cambian. Nombre/categoría provienen del caso seleccionado; estado de `review_current`/`review`; tipo, documento lógico y versión del manifiesto de ese conjunto; lados de la respuesta canónica READ. No se infieren etiquetas de los píxeles ni del número de imágenes.
- `OPERATIONAL_STATUS`: nueva composición owned sintética loopback, sin personas reales, notificación externa, cambios a 8091 ni activación productiva.

## Presentación y límites de metadata

Título DOCUMENTACIÓN DE IDENTIDAD DEL INTEGRANTE, nombre y categoría de la incorporación interna, estado de revisión y versión del conjunto/perfil. INE → Anverso/Reverso y PASSPORT → Pasaporte/Página biográfica cuando los registros lo establecen. Otros tipos o tipo ausente → Documento de identidad — Tipo no especificado. Número de página explícito → Página N; rol no especificado → Imagen N. El intake actual sólo acepta INE FRONT/BACK o PASSPORT BIOGRAPHIC; el fallback y números se verifican como fixtures de presentación, sin inventar registros canónicos ni ampliar el intake.

Una sección por ID lógico, versión documental separada de páginas. Dos columnas en escritorio, una en móvil; imágenes sin distorsión, ampliación dentro del mismo diálogo, scroll interno y footer fijo. Cerrar, Escape y backdrop conservan reglas del shell; se eliminan todos los `src` y nodos documentales al cerrar, cambiar de caso o abandonar la página. No caché en storage del navegador, links públicos, descargas nuevas, OCR ni afirmaciones de autenticidad.

El banner DOCUMENTACIÓN SINTÉTICA DE PRUEBA se genera en servidor sólo con `MXMED_STAFF_DOC_VIS01=1`, preview explícito y raíz owned disponible. El renderer no puede etiquetar automáticamente documentos operativos como sintéticos. El entorno del harness sólo contiene identidades y archivos artificiales. La guía al pie enumera exclusivamente las acciones que proyecta el servicio; ejecutarlas sigue requiriendo su autorización independiente fuera del visor.

## Verificación

52 checks PASS en el cierre integrado, más comprobación de plantilla sin flags sintéticos. [QA](../evidence/staff-doc-vis01/qa.json), [geometría y navegador](../evidence/staff-doc-vis01/viewer.json), [manifiesto final](../evidence/staff-doc-vis01/final-verification.json). No se heredan PASS históricos. El Advisor se creó/admitió con READ, ACCEPT y ADMIT canónicos en la primera ejecución; la reanudación conservó ese registro sin duplicar operaciones. La espera de limpieza del test se ajustó al evento nativo asíncrono `close`, comprobando realmente cero imágenes antes de continuar. Chromium nativo/1440×900/1366×768/390×844/1366×420 y WebKit 1366×768/390×844; scroll hasta última imagen, ampliación, footer, cierre, selección entre dos casos, contraseña/MFA incorrectos, replay, Advisor denegado, revisión/admisión separadas. Safari físico no probado.

## Revisión local

Director: `https://127.0.0.1:60678/internal/media-review/index.php?section=equipo`.
Advisor: `https://127.0.0.1:60678/internal/staff-workspace/index.php`.
Caducidad: `2026-10-22T00:14:37-06:00` (America/Mexico_City), 24 h desde el lanzamiento; Identity y MFA conservan sus vencimientos.

```sh
cd /Users/circulodigital/Documents/GitHub/mxmed-staff-doc-vis01
python3 scripts/staff-doc-vis01-review.py /tmp/mxmed-ase-adm02imp01-_qbz9mgh status
python3 scripts/staff-doc-vis01-review.py /tmp/mxmed-ase-adm02imp01-_qbz9mgh open
python3 scripts/staff-doc-vis01-review.py /tmp/mxmed-ase-adm02imp01-_qbz9mgh credentials-director
python3 scripts/staff-doc-vis01-review.py /tmp/mxmed-ase-adm02imp01-_qbz9mgh mfa-director
python3 scripts/staff-doc-vis01-review.py /tmp/mxmed-ase-adm02imp01-_qbz9mgh stop
```

Cuenta `qa_director@example.test`; contraseña y código sólo al portapapeles. `open` abre/enfoca las ventanas independientes; no renueva una sesión caducada. Para volver a entrar, usar Identity en esa ventana con la contraseña recuperada, o cerrar la ventana aislada y ejecutar `open-director`. Seleccionar Martha → Revisar incorporación → contraseña actual → código vigente no consumido → visor. Cerrar devuelve a Martha y las acciones elegibles del servicio. Irene permanece admitida por el flujo canónico de preparación del Advisor; Martha queda pendiente para revisión. `stop` sólo señala al runner propietario; no mata procesos ajenos.

## CTX-SYNC01

```ini
CONTEXT_BASELINE=486124b33bdc8179ffc58f5f4eaf0d06d99463b3
AFFECTED_CAPABILITY_IDS=ID-02,ID-03,ID-07; mismas autoridades
AFFECTED_JOURNEY_IDS=J09; consulta documental staff
AFFECTED_DATA_OBJECTS=ninguno modificado; casos/conjuntos/documentos/assets existentes sólo proyectados
AFFECTED_CONTEXT_FILES=STAFF-DOC-VIS01; estado; router; registro visual; inventario; interacciones; sucesor STAFF-CONTROL07
CONTEXT_CHANGES_IMPLEMENTED=contexto exacto, agrupación/etiquetas, ampliación, fuentes efímeras y retorno
CONTEXT_VERIFICATION=QA dirigida PASS; diff completo, pins, backend, worktrees/listeners y enlaces pertinentes verificados
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en commit aislado; hash en entrega
REMAINING_CONTEXT_GAPS=aceptación visual del Director y Safari físico pendientes
```

Mapas de datos: `CONTEXT_CHANGE=NO_IMPACT`; sin tablas, relaciones, metadatos persistidos, políticas ni writers nuevos.
