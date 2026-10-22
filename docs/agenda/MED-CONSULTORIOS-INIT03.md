# MED-CONSULTORIOS-INIT03 — paridad del encabezado y preparación de integración

## Decisión y referencia

2026-10-22. Baseline aislado `ac977e03cc64316c49a5c947994326987755fecc`, rama `feature/mxmed-med-consultorios-init02`; original `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`. Se preservan los cambios locales autorizados de ACCESS01 (launcher/guía/evidencia), incluidos en este cierre. Sin merge ni integración al original.

**PRODUCT_POLICY:** Leticia Muñoz Romo, `mxmed-local-development:1`, es la referencia manual entre Physician y Advisor. Aurora es exclusivamente QA sintética; no sustituye a Leticia ni recibe datos, cuentas o sesiones originales. La tipografía, geometría, foto, metadata, plan y sesión del encabezado original son autoritativos. Sólo se añade Dr./Dra. cuando procede del perfil canónico.

## Diagnóstico y corrección mínima

**SOURCE_IMPLEMENTATION:** antes de INIT03, el bloque completo del encabezado, todas las hojas CSS, app.js, header-account.js y backend/API eran idénticos al baseline original (1.880 archivos comparados; [evidencia](../evidence/med-consultorios-init03/source-parity.json)). INIT02 sólo cambió el loader, ordering y bloque Consultorios. No se verificó una regresión CSS ni una dependencia de fuente ausente. No se cambió CSS, dimensiones, markup del encabezado ni comportamiento responsive.

**VERIFICATION visual:** observación de la sesión original autenticada de Leticia en Safari, sin extraer cookies ni recargarla: Asap, peso 800, nombre 28,8 px, avatar ~53 px, header ~82 px. Su ventana reporta outerWidth 1440, innerWidth ~1694 y DPR 1,7; el navegador conserva su escala. QA a 1366 mostró 27,32 px y a 1694/100% mostró 29,76 px en Chromium/WebKit; regla compartida `clamp(1.7rem, 2vw, 1.86rem)`. No son capturas normalizadas al mismo zoom: no se afirma igualdad de píxeles. El nombre sintético, su longitud, fotografía ausente y metadata también difieren legítimamente. [CSSOM original](../evidence/med-consultorios-init03/original-rules.json), [mediciones originales](../evidence/med-consultorios-init03/original-header.json), [Chromium](../evidence/med-consultorios-init03/chromium-header.json), [WebKit](../evidence/med-consultorios-init03/webkit-header.json). Capturas sintéticas a 1694, 768 y 390 en la misma carpeta. No se inventa una causa histórica de fuente fallida.

La brecha comprobada es de presentación del prefijo: el perfil original conserva `prefix=Dra.` separado de `display_name`, pero su header mostraba sólo el nombre; Aurora ya lo incluía en su display_name. Se reutiliza `PublicNamePresentation::compose` para un campo aditivo `professional.presentation_name` en el contexto privado, limitado a Dr./Dra. Se conserva display_name y se usa la presentación en header-account. La hidratación de Información personal conserva el prefijo canónico sin duplicarlo. Otros prefijos y nombres sin prefijo conservan su comportamiento previo. No se modifica el nombre persistido, la autoridad de identidad, membresía, credenciales o permisos. Cache versions actualizadas para los dos consumidores JS.

## QA y preservación

**CONNECTIVITY:** login canónico y servicios reales en infraestructura INIT02 propia; las respuestas fallidas y el nombre sintético sin prefijo se inyectan sólo en el contexto de navegador para verificar recuperación y composición. No se escriben fixtures.

**VERIFICATION:** siete comprobaciones por motor (14 PASS): prefijo canónico/no duplicado y rehidratación; login y tres sedes en primera carga sin placeholder; error y reintento; denegación del médico original; denegación sin sesión; cero writes de hidratación (hash de consultorios y contador SQL); cookie propia. [Chromium](../evidence/med-consultorios-init03/chromium-results.json), [WebKit](../evidence/med-consultorios-init03/webkit-results.json).

Reproducción limitada al runner vivo y propio, sin seed ni cambios de datos:

```sh
python3 scripts/med-consultorios-init03-qa.py /tmp/mxmed-init02-36clk43w chromium
python3 scripts/med-consultorios-init03-qa.py /tmp/mxmed-init02-36clk43w webkit
```

PHP/JS syntax y diff-check pasan. Se intentó además `PublicNamePresentationTest.php`: sus casos de composición pasan, pero falla su comprobación histórica de catálogo porque busca `mxpi-prefix` en index.html, donde ya no existe. Ese test/index.html no cambian respecto al baseline; no se alteró para ocultar el fallo. La comprobación nueva atraviesa el contexto autenticado y la hidratación real del shell. No se repitió la suite histórica.

Lectura acotada: hashes de médico/consultorios/membresías/media originales iguales al recibo previo, mismo HEAD limpio y mismos PIDs 8091/8443; [preservación](../evidence/med-consultorios-init03/original-preservation.json). La [proyección restringida de Advisor](../evidence/med-consultorios-init03/advisor-consistency.json) coincide con nombre y tres sedes originales. Esto acredita el reader, no una nueva sesión visual Advisor. Sesión Safari original observada autenticada; no logout, copia de sesión, reset ni refresh. No se tocaron otros previews.

## Integración controlada — preparada, NO ejecutada

Paquete mínimo de INIT02, sin prefijo: `private-app.php` (sólo hunk de Consultorios/cache de sus scripts), `assets/js/perfil/consultorio/multisede.js`, `assets/js/perfil/consultorio/consultorio-ordering.js`.

Paquete conjunto con la adición aprobada INIT03: esos tres archivos más `assets/js/app.js`, `assets/js/core/header-account.js` y `modules/identity/http/PrivateApplicationAccessResolver.php` (seis archivos en total). El servicio PublicNamePresentation ya existe en el original. No copiar scripts QA, fixtures, archivos privados ni el launcher al runtime médico. No aplicar ciegamente el commit completo: incluye documentación/evidencia/infra QA.

- **Compatibilidad:** GET/PUT de Consultorios y autoridad sin cambios. Se conservan IDs/doctor_id, orden y primary. API privada añade presentation_name sin quitar/renombrar campos. prefix ya existe y fue verificado; no migración, DDL, backfill ni datos semilla.
- **Gate futuro:** verificar HEAD/limpieza del destino, revisar únicamente los seis diffs, respaldar sus versiones y desplegar coherentemente en un paso controlado. No sobrescribir trabajo posterior. Mantener datadir, sockets, entorno, cookies, claves y Valkey originales.
- **Runtime:** PHP built-in original lee el código en solicitudes posteriores; no se requiere reiniciar Identity/Valkey/MySQL ni cerrar sesiones. Si el despliegue futuro usa OPcache sin validación de timestamps, invalidar únicamente los archivos cambiados bajo su autorización; no reinicio general. Los query versions renuevan JS. Una pestaña ya cargada conserva su JS hasta la próxima navegación/recarga voluntaria, una vez guardadas sus ediciones.
- **Smoke posterior autorizado:** primer GET de Leticia debe devolver y pintar sus tres sedes, sin PUT/POST de hidratación; comprobar nombre/prefijo/foto/plan/sesión. Fallos y reintento continúan probándose sólo en Aurora. Comparar hashes de registros antes/después. No ensayar escrituras con Leticia.
- **Rollback:** restaurar coherentemente las seis versiones respaldadas (o sólo los tres hunks INIT02 si se integra por separado), verificar hashes y recargar voluntariamente la UI sin ediciones pendientes. No restaurar DB ni purgar sesiones; no existe migración que revertir.

**OPERATIONAL_STATUS:** READY_FOR_SEPARATE_CONTROLLED_INTEGRATION_DECISION. Original sin integrar. Preview sintético continúa en https://127.0.0.1:56121/private-app.php hasta 2026-10-23 09:21:26 -06:00. La revisión manual principal sigue en https://localhost:8443/private-app.php. Aceptación visual del Director y autorización de integración pendientes; paridad fuente/estilos verificada, no comparación pixel-perfect de perfiles distintos.

## CTX-SYNC01

CONTEXT_BASELINE=ac977e03cc64316c49a5c947994326987755fecc
AFFECTED_CAPABILITY_IDS=PRF-02 (presentación del perfil),PRF-03,PRF-04,PRF-05
AFFECTED_JOURNEY_IDS=J04 (consultorios; presentación transversal del shell, sin alterar J01/J02)
AFFECTED_DATA_OBJECTS=contexto privado professional.presentation_name (aditivo); profiles_doctors.prefix (lectura); consultorios (sin cambios)
AFFECTED_CONTEXT_FILES=este contrato;MED-CONSULTORIOS-INIT02.md;ESTADO_ACTUAL_MXMED.md;INTERACCIONES_MXMED.md;INVENTARIO_FUNCIONAL_MXMED.md
CONTEXT_CHANGES_IMPLEMENTED=referencia Leticia, límites de Aurora, prefijo de presentación, evidencia de paridad y plan de integración separado
CONTEXT_VERIFICATION=fuente/CSSOM original, Chromium/WebKit, reader restringido y hashes originales
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en el commit de cierre INIT03
REMAINING_CONTEXT_GAPS=aceptación visual e integración separadas; test histórico de catálogo obsoleto; no nueva revisión visual Advisor
