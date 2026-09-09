# MR12A — activación local de revisión de imágenes

Los controles actuales de foto, logotipo profesional y galería envían candidatos
privados a los endpoints canónicos de revisión. Los GET/DELETE públicos existentes
se conservan para mostrar y eliminar medios ya publicados; ningún upload de estos
tres controles usa el POST público anterior. La eliminación de galería es inmediata
y no necesita aprobación.

En Datos Personales y Fotos de actividad y consultorio, el área «Imágenes en
revisión» reúne los candidatos del propietario: «Pendiente de enviar», «Enviado a
revisión» y «Necesita cambios». El botón «Enviar a revisión» usa el endpoint
canónico y su CSRF, sin seleccionar médico o lote desde el cliente. Los motivos se
muestran como texto, nunca HTML. «Reemplazar imagen» abre el control correspondiente;
el servicio aceptado conserva el lote enviado y crea/usa un nuevo lote abierto.
El historial NEEDS_WORK de galería permanece conforme a su listado canónico; no
se elimina ni se reasigna al subir otra imagen.

`api/media/owner-review.php` es una proyección de lectura autenticada por la sesión
del propietario. Para foto y logo reutiliza la selección `current()` aceptada,
para no mostrar solicitudes antiguas como si fueran el candidato actual. Las
vistas previas sirven exclusivamente REVIEW, después de comprobar propietario,
estado e integridad SHA-256. No entregan SOURCE, claves de almacenamiento ni
metadatos de autorización. Respuestas e imágenes usan `private, no-store`.

El backend de aprobación, corrección, mejora de logo, reemplazo, capacidad y
retiro permanece sin cambios. Solo REVIEW puede publicarse. Las imágenes públicas
anteriores permanecen mientras el candidato está pendiente; los logos públicos
históricos siguen resolviéndose. La galería conserva el orden created_at/media_id
y el máximo combinado de 16. La mejora automática sigue limitada al logo.

La bandeja existente `/internal/media-review/` muestra lotes enviados y elementos
legacy individuales, con revisión parcial y finalización derivada. No se conceden
permisos productivos al revisor. No hay promesa de envío automático a los 30 minutos;
el ejecutor se conserva, pero Scheduler no se ha activado.

## Prueba local reproducible y revisión del Director

Prerrequisitos: Docker, PHP con PDO MySQL/GD, Node 22 y Google Chrome. En macOS se
usa la instalación habitual de Chrome; `MXMED_QA_CHROME` permite indicar otra ruta.
Los puertos loopback 3309, 6387, 8128 y 9348 deben estar libres. El script falla si
no puede crear sus propios contenedores; no reutiliza una BD existente.

Desde la raíz del repositorio:

```sh
node modules/media/tests/OwnerMediaReviewHttpTest.mjs
```

Crea MySQL/Valkey desechables, aplica únicamente el fixture aceptado de medios,
y ejecuta HTTP, navegador y regresiones. No usa el bootstrap global. Las cuentas,
grant rows y sesiones del revisor viven exclusivamente en la base sintética
`mxmed_gate4d_preview_mr3_mr12a_*`, mediante el mecanismo QA canónico existente.
Ninguna cuenta, imagen o grant del Director se copia ni modifica.

Para dejar abierta una sesión manual, ejecutar:

```sh
node modules/media/tests/OwnerMediaReviewHttpTest.mjs --keep
```

Este modo ejecuta HTTP/navegador y conserva el escenario sintético final; omite
las regresiones que reinicializan el fixture. Imprime estas rutas locales:

- Médico: `http://127.0.0.1:8128/qa-login.php?as=owner`.
- Revisor: `http://127.0.0.1:8128/qa-login.php?as=reviewer`.
- Perfil público: `http://127.0.0.1:8128/profiles/doctor.php?doctor_id=<identificador sintético impreso>`.

`qa-login.php` se genera únicamente dentro de la copia temporal, no está en el
repositorio ni en el paquete de aplicación. Solo sirve para establecer las
sesiones sintéticas ya creadas; no es un login productivo.

En la UI médica, abrir Información → Datos Personales para foto/logo, o Fotos de
actividad y consultorio para galería. Subir una imagen, comprobar que la pública
no cambia, enviar a revisión y abrir la bandeja del revisor en otra pestaña.
Aprobar o solicitar cambios, regresar al médico y volver a abrir la sección
para actualizar el estado. En NEEDS_WORK se ve el motivo y «Reemplazar imagen».
Tras aprobar, recargar el perfil público. El escenario final ya contiene medios
públicos y un lote parcialmente revisado para inspección.

Ctrl-C elimina los contenedores creados y los archivos temporales. Repetir el
comando crea un escenario nuevo; no existe un reset dirigido a datos del Director.
El script no imprime tokens. Los endpoints productivos descubiertos son
`/index.html`, `/profiles/doctor.php?doctor_id=...` e `/internal/media-review/`.

## Validación y límites

HTTP prueba los tres propósitos, conservación de públicos, logos históricos,
NEEDS_WORK/nuevo lote, estados del propietario, vistas previas y negativos de
scope/CSRF, publicación y capacidad 15+1/16. El navegador sube archivos por los
controles reales, envía un lote mixto y aprueba desde la bandeja. Las capturas se
escriben en `/tmp/mxmed-mr12a-owner-1366.png` y `...-390.png`.

Las regresiones cubren MR5–MR11 y retiro de lote vacío. El test de logo anterior
se actualiza para comprobar la bandeja por lotes de MR11: abierto oculto, enviado
visible con ambos propósitos, en lugar de esperar filas planas de un lote abierto.

Q1/Q2, la clínica, el bootstrap global, AWS y los costes permanecen sin cambios.
El conteo 8 de galería del Director es el baseline comunicado; la prueba no
consulta su BD para volver a medirlo. MR11.5Q/Q3/Q4 y el despliegue físico siguen
diferidos. MR12A termina en revisión manual del Director.
