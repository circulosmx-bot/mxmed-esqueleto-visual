# UX-ALIGN01 — Evaluación de presentación de Recetas

Fecha: 2026-10-16. Alcance: fase E, evaluación del componente original y QA sintética. La referencia histórica se recibió como descripción funcional; no se supone disponible ningún PDF o imagen del Director en este checkout.

## Decisión de implementación

La presentación actual ya contiene la jerarquía solicitada. Esta fase conserva el renderer, CSS de documentos, snapshots, fingerprints, firma, QR, lifecycle y autoridad clínica. No se cambia el documento para reproducir una maqueta histórica ni se agregan campos sin fuente autorizada.

Se agregan únicamente una fixture CLI sintética, un runner de aceptación y este documento. Los doce archivos de producto protegidos por el runner son idénticos byte por byte a `e927dcfa86e3bf1fb7cf4e972b4155051d2e51c0`. No se crean recetas, firmas, notificaciones ni registros clínicos, y no se consulta ninguna base de datos.

## Fuentes actuales y jerarquía

| Elemento | Fuente/implementación existente | Evaluación |
| --- | --- | --- |
| Vista previa | `private-app.php`, `#ne_rx_review`, `#ne_rx_preview_html`; `assets/js/app.js` coloca el HTML devuelto por el servidor | Se conserva el selector flotante de encabezado, la hoja y los controles originales. El selector queda fuera del contenido del documento. |
| Documento | `clinical_prescription_v2_render()` en `api/_lib/clinical_prescription_v2.php` | Renderiza los snapshots recibidos; no deriva una identidad del operador o del browser. |
| Médico | `physician_snapshot`: nombre, denominación, especialidad y cédulas | Nombre de mayor tamaño; denominación/especialidad y cédulas subordinadas. No se inventan campos opcionales ausentes. |
| Paciente | `patient_snapshot`: nombre, edad y sexo; `issue_date` | Bloque separado del médico, con fecha y contexto clínico. La fixture no utiliza información de pacientes reales. |
| Medicamentos | `prescription.items`, su proyección normalizada y `prescription.observaciones` | Bloques numerados, nombre destacado y presentación, dosis, vía, frecuencia, duración, cantidad e indicaciones diferenciadas. Se conserva REC-REG04C y la compatibilidad de pautas V1/V2. |
| Consultorios y teléfonos | `clinical_professional_presentation_resolve()` y snapshot `professional_presentation` V2 | Publicación explícita, hasta tres oficinas, orden y teléfonos autorizados. La ubicación legacy de una cita no elude la publicación autorizada. |
| Firma y versión | `clinical_prescription_binding.php`, binding JS, flujo de firma existente | No se altera ningún requisito, artefacto, fingerprint o revisión. Las imágenes sintéticas del contrato de firma sólo verifican clasificación en memoria; no constituyen emisión. |
| Perfil público | `professional_presentation.public_profile`, renderer e hidratador existentes | QR y texto «AGENDA UNA CITA» sólo cuando existe una URL canónica aprobada. Los casos visuales nuevos carecen de ese dato y no inventan QR ni destino. |
| Viewer/impresión | `modules/clinical/ui/viewer.php` utiliza `prescription_snapshot.html`; `assets/css/clinical-doc-base.css` | Se conserva el HTML congelado y el stylesheet actual. La impresión usa hoja Letter; la longitud determina el número de páginas. |

Teléfonos personales o de emergencia no se agregan por aparecer en una referencia visual. Las notas privadas tampoco forman parte del documento. Los contratos existentes comprueban la procedencia de la publicación; la nueva fixture introduce explícitamente un snapshot sintético con la misma forma de datos, sin afirmar que se haya validado una publicación real.

No se identificó una carencia de jerarquía o legibilidad que requiera modificar el documento en esta fase. El modo «Sin encabezado» se conserva exactamente: también omite la identidad y bloque de firma del médico según el contrato vigente. No se redefine ese comportamiento para hacer pasar un test antiguo incompatible.

## Por qué un rediseño exige otra versión

El writer conserva `prescription_snapshot.version=2` y el HTML de la receta. El viewer vuelve a mostrar ese HTML histórico. Sin embargo, comparte el archivo actual `clinical-doc-base.css`; un cambio en ese archivo puede cambiar el render y la paginación de documentos ya emitidos, aunque el HTML y el fingerprint de contenido no cambien. Un parámetro de caché del enlace CSS no constituye por sí mismo una versión histórica inmutable.

Los fingerprints existentes incluyen el modo de encabezado, los datos del paciente/médico, contexto, medicamentos y presentación profesional/consultorios/public-profile según sus versiones. Alterar esas proyecciones o reinterpretar un snapshot requiere compatibilidad explícita PHP/JS. Cambiar sólo CSS tampoco autoriza una nueva semántica de PDF histórico.

Si el Director decide un cambio posterior de documento, el siguiente trabajo acotado debe preparar:

1. Una versión explícita de renderer y stylesheet para documentos nuevos, sin sobrescribir los assets de versiones previas.
2. Un contrato persistido que permita resolver exactamente la presentación original; un fallback explícito para snapshots históricos sin esa referencia.
3. La evaluación de qué metadatos de presentación participan en el fingerprint, paridad PHP/JS y compatibilidad de firma/QR para cada versión.
4. Vectores y capturas de referencia de documentos históricos, PDF/paginación, datos opcionales, contenido largo y reemisión. No regenerar ni volver a firmar el documento original como efecto del cambio visual.
5. Autorización independiente para cualquier evolución persistente que resulte necesaria. Esta fase no aplica esa evolución ni modifica el writer.

## Aceptación ejecutada

Comando reproducible, sin servidor ni acceso a datos reales:

```sh
python3 scripts/ux-align01-prescription-qa.py
```

El runner llama a `modules/clinical/qa/ux_align01_presentation_fixture.php`, que utiliza el renderer original con snapshots explícitamente sintéticos. No compone Identity, PDO, Valkey, escritor, firma, sesión o entorno clínico. Intercepta el origen ficticio sólo dentro del browser de prueba; no entrega una página nueva del producto. Los recursos externos permitidos son los estilos Bootstrap y las fuentes que ya utiliza la aplicación original.

Resultado: **36 casos**, Chromium y WebKit a **1440×900, 1366×768 y 820×1180**, con seis snapshots por combinación: sólo datos, logotipo/datos sin logo aprobado disponible, sin encabezado, datos opcionales ausentes, formato histórico sin footer y contenido largo de 24 medicamentos. Cada caso verifica la vista previa y el medio print.

Se comprobaron bloques de medicamentos legibles, jerarquía del nombre/cédulas, separación médico/paciente, textos e indicaciones completos, direcciones largas, footer después de la firma, ausencia de overflow horizontal de la página/hoja, tamaño de texto, publicación opcional, ausencia de notas privadas/teléfonos personales/de emergencia y ausencia de errores JavaScript o solicitudes de escritura. No se transforma la falta de logo en un recurso ficticio.

La vista previa reutiliza el markup real de etapa 3. El contexto de prueba alinea el radio con la presentación del snapshot; no pretende probar aquí un cambio funcional del selector ni el ciclo completo del editor. El documento de impresión reutiliza la envoltura del snapshot del viewer y el stylesheet actual, sin ejecutar la página PHP protegida del viewer.

Ambos motores verificaron medio print, ancho Letter de 816 CSS px, eliminación de sombra, preservación de medicamentos y separación de firma/footer. Chromium exportó además dos PDF Letter leídos con PDFKit, sin abrir una aplicación: caso detallado **2 páginas** y caso largo **7 páginas**. Se recuperaron los nombres de todos los medicamentos y el footer del PDF. No se exige una sola página para una receta extensa ni se declara PDF exportado por WebKit, cuya API de automatización no dispone de esa función.

Se inspeccionaron visualmente las capturas del preview WebKit a 820×1180 y del medio print Chromium a 1440×900. Las capturas sólo contienen texto sintético. Evidencia privada:

`~/Library/Application Support/MXMED/ux-align01/20261016-review/prescription/`

Incluye `result.json` con hashes de fuente y snapshots, doce PNG, dos PDF y `regressions.json` con resultados de contratos existentes.

### Contratos existentes

Pasaron los nueve runners siguientes, sin modificar sus asserts:

```sh
node scripts/clinical/doc-rec-std03-static.test.mjs
node scripts/clinical/doc-rec-std02-static.test.mjs
php modules/clinical/qa/doc_rec_std03a_footer_test.php
node modules/clinical/qa/doc_rec_std03a_binding_test.mjs
php modules/clinical/qa/doc_rec_std03_logo.php
php modules/clinical/qa/rec_reg02_contract.php
node modules/clinical/qa/rec_reg02_binding.test.mjs
php modules/clinical/qa/rec_reg03_contract.php
php modules/clinical/qa/rec_vis08_signature_contract.php
```

El contrato `node scripts/clinical/rec-vis08-contract.test.mjs` conserva un fallo previo: exige «Médico responsable» dentro del modo `hidden`, mientras el renderer y el contrato DOC-REC-STD03 omiten toda la identidad/firma en ese modo. El renderer, los bindings y ese test ya tenían esta discrepancia en el baseline. No se cambia el renderer ni se debilita/elimina la aserción. Por eso no se declara verde toda la suite histórica de Recetas.

## Límites y preservación

Esta aceptación verifica el renderer nativo y componentes originales de presentación. No acredita el flujo autenticado completo de private-app, una consulta de expediente real, la recuperación desde SQL del viewer, entrega/escaneo móvil de QR, emisión o impresión física. Tampoco equivale a aprobación física del Director en Safari. La comprobación de seguridad clínica integral corresponde a los gates existentes de la entrega principal; aquí sus archivos protegidos siguen idénticos.

Normal-local no se consulta ni modifica desde estos runners. No se crean recetas ni firmas, no cambian pacientes, membresías, autoridades, invitaciones, suscripciones, datos persistentes de QA o assets del documento. La composición real sigue teniendo los bloqueos operativos que correspondan; el resultado visual no activa ningún servicio.
