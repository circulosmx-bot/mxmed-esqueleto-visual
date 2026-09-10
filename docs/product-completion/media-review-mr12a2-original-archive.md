# MR12A.2 — originales por lote y contrato histórico

REVIEW es el derivado que ve el asesor y del que se publica. SOURCE es el original
operativo privado. El ZIP del lote es una descarga temporal de trabajo. El archivo
histórico conserva originales individuales a largo plazo; nunca depende de un ZIP.

En una pantalla de lote SUBMITTED, «Descargar originales del lote» solicita
`batch-source-download.php?batch_id=<uuid>`. La sesión canónica debe tener
`media_review_read` y `media_review_source_download`. La autoridad R1 y la auditoría
existente de descarga SOURCE se aplican a cada miembro antes de transmitir el ZIP.
La capacidad interna existente es global: no se inventa una asignación de médicos
al revisor. El servidor resuelve el lote y cada relación de propietario; no acepta
owner_id, claves, rutas, IDs de solicitudes independientes ni lotes OPEN/legacy.

Incluye los SOURCE de la membresía original, también APPROVED/NEEDS_WORK, sin
cambiar la descarga individual ni sus reglas. Se verifican clave canónica, tipo,
tamaño y SHA-256. Si falta un original o su integridad no coincide, falla toda la
descarga. Para limitar recursos se admiten hasta 100 miembros y 200 MiB totales;
los originales ya tienen el límite canónico individual de 10 MiB. Se usa disco
privado temporal, no una acumulación de imágenes en RAM. Los archivos temporales
se eliminan al completar, fallar o finalizar la petición; no son objetos de medios.
Un cierre forzado del proceso por el sistema requiere la limpieza normal del
almacenamiento temporal del host, igual que cualquier operación interrumpida por SIGKILL.

El nombre externo usa una referencia hash del ID de médico, fecha de envío y un
fragmento del UUID del lote. Dentro, las carpetas PERFIL/LOGOTIPO/GALERIA y el orden
más UUID completo evitan colisiones y traversal. `manifest.json` contiene solo
schema, propietario, lote, solicitud, propósito, nombre de entrada, nombre original,
MIME, bytes, SHA-256 y fecha de carga. La autoridad de captura actual no conserva el
nombre de archivo que envió el navegador: `original_filename` es explícitamente
`null`. No se inventa ni se reconstruye a partir de claves privadas. No se cambió
la captura ni el esquema en esta fase; cualquier futura conservación del nombre
necesita un contrato de metadatos separado, y nunca debe usarse como ruta.

## Archivo histórico v1

Objetos:

```
media-archive/v1/physicians/YYYY/MM/<doctor_id>/<batch_id>/sources/<submission_id>/original.<ext>
media-archive/v1/physicians/YYYY/MM/<doctor_id>/<batch_id>/manifest.json
```

YYYY/MM corresponde a `submitted_at` UTC del lote. Los IDs son autoridad; no se
incluyen nombres de personas. Extensiones: jpg/png/webp, según MIME validado.
`HistoricalArchivePort` define escritura inmutable y verificación de identidad y
bytes reales. El manifiesto también se verifica. Una respuesta de subida exitosa,
un ETag opaco o metadatos que simplemente repiten la petición no bastan.

`HistoricalOriginalArchive` devuelve un recibo versionado con estado, batch_id,
resolved_at, verified_at y objetos/identidades. Los estados son NOT_ARCHIVED,
ARCHIVED_UNVERIFIED, ARCHIVED_VERIFIED, FAILED y OPERATIONAL_SOURCE_PURGED (reservado;
no se ejecuta). No son estados de `media_review_batches` y no modifican OPEN/SUBMITTED.
Esta fase prueba el contrato mediante recibos locales; no registra un estado de
archivo productivo en la BD. La futura activación deberá persistir estos recibos
con autoridad y recuperación de fallos antes de habilitar eliminación operacional.

Solo un lote cuyos miembros están todos APPROVED o NEEDS_WORK es elegible.
PENDING_REVIEW, WITHDRAWN y estados desconocidos bloquean el archivo de todo el
lote. APPROVED usa `updated_at`, que la aprobación canónica actualiza al resolver;
NEEDS_WORK usa `review_decided_at`. Se toma la última resolución. Una sustitución
en otro OPEN no mueve el histórico ni cambia la membresía SUBMITTED.

`LocalDisposableHistoricalArchive` solo funciona por CLI en un directorio temporal
explícito `mxmed-archive-*`. Conserva objetos individuales y metadatos de identidad
privados, comprueba bytes y SHA-256 reales, y rechaza claves inseguras y enlaces
simbólicos. No está conectado a endpoints ni incluido en el paquete productivo.

## Retención y activación futura

SOURCE_HOT_RETENTION_DAYS=30 es el valor inicial configurable de la política.
`purgeEligible` exige ARCHIVED_VERIFIED, vuelve a derivar los miembros canónicos,
revalida cada original archivado y el manifiesto y exige que transcurra la retención
desde la fecha más tardía entre resolución y verificación. Estados no verificados,
fallos, datos incompletos o un objeto corrupto siempre niegan la elegibilidad.
No existe un servicio ejecutor de eliminación en esta fase; ningún SOURCE real se
purga, y el adaptador ni siquiera ofrece una operación delete.

Destino futuro: S3 privado con S3 Glacier Deep Archive, PHYSICAL_ACTIVATION_DEFERRED.
La implementación futura debe satisfacer la prueba de integridad incluso cuando
la lectura requiera restauración asíncrona; mientras no pueda probarla, el estado
no es verificado y no habilita purga. No se hicieron llamadas AWS ni cambios de
Scheduler, buckets, credenciales o permisos productivos.

## Prueba local

`node modules/media/tests/OwnerMediaReviewHttpTest.mjs --keep` ejecuta el contrato
local de archivo, ZIP e integridad, las negativas HTTP y la regresión MR12A de
foto/logo/galería/envío/aprobación/NEEDS_WORK. Chrome entra por `qa-login.php`, abre
el lote y descarga sus originales desde el botón. Deja el entorno disponible para
el Director y muestra las rutas del médico, revisor y perfil público sintéticos.
