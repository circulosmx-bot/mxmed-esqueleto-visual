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
Los puertos loopback 3309, 6387 y 8128 deben estar libres. Chrome usa un puerto de depuración efímero dentro de un perfil temporal propio. El script falla si
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
sesiones sintéticas; no es un login productivo.

MR12A.1 corrige la ruta manual: el login de revisor crea una sesión canónica nueva
para la cuenta sintética fija del fixture MR3 y elimina la cookie canónica del
harness anterior conservando sus atributos Secure/HttpOnly. El navegador recibe únicamente un
identificador local HttpOnly (`mxmed_mr12a_qa`), sin token en URL, HTML ni consola.
Un prepend PHP generado fuera del directorio web resuelve ese identificador en
rutas de revisión. Solo funciona con el flag sintético explícito, entorno local,
BD sintética exacta, raíz temporal exacta y host/cliente loopback. El token queda
fuera del directorio web, en archivos privados con permisos 0600. Ningún resolver
productivo acepta esta cookie. La sesión canónica, su caducidad y las capacidades
persistidas siguen validándose normalmente; un login no concede permisos.

El helper anterior reutilizaba para siempre un token capturado al arrancar y
emitía una cookie `__Host-` Secure sobre HTTP. En la reproducción de MR12A.1 Chrome
sí almacenó esa cookie, pero el token ya era inválido después de más de una hora
(TTL de inactividad: una hora). El nuevo login evita tanto esa sesión caducada como
la dependencia del transporte de cookies Secure sobre HTTP. No se modifica el
contrato de cookies productivas ni sus TTL. Si caduca una sesión QA, vuelve a
abrir el enlace de login correspondiente.

La prueba de navegador ahora entra por ambos enlaces `qa-login.php` desde un
perfil limpio y sigue los redirects; no inyecta cookies de propietario o revisor.
Verifica acceso denegado sin sesión y con rol médico, bandeja por lotes y aprobación
por el revisor. La prueba HTTP comprueba revocación de capacidad, sesión inválida,
renovación mediante login y precedencia de una cookie canónica explícita inválida.

En la UI médica, abrir Información → Datos Personales para foto/logo, o Fotos de
actividad y consultorio para galería. Subir una imagen, comprobar que la pública
no cambia, enviar a revisión y abrir la bandeja del revisor. Cambiar de rol mediante `qa-login.php` borra la
identidad QA opuesta en ese navegador. Para usar ambos roles simultáneamente,
utiliza navegadores o perfiles separados; con un solo navegador, vuelve al enlace
de propietario o revisor al cambiar de rol.
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

MR12A.2 agrega «Descargar originales del lote» al abrir un lote enviado. La
descarga contiene SOURCE y manifiesto; no altera las decisiones de revisión.
El [contrato de archivo histórico](media-review-mr12a2-original-archive.md) se
prueba únicamente en almacenamiento local desechable; AWS permanece diferido.

MR12A.3 corrige únicamente el permiso del revisor sintético:
`media_review_corrected_upload` sustituye el nombre incorrecto
`media_review_correct` del fixture. El backend, la sesión y la autorización
productiva permanecen intactos. Se conserva el alcance existente de foto de perfil,
logotipo personal y galería.

Al abrir una solicitud pendiente, en «Intervención de diseño» aparecen «Descargar
original» y «Subir versión corregida». Selecciona un JPG/PNG/WebP válido, comprueba
el nombre y pulsa «Guardar versión corregida». La vista muestra el nuevo REVIEW;
SOURCE permanece descargable y la solicitud sigue pendiente. Solo «Aprobar»
publica el REVIEW; guardar la corrección no publica ni crea otra solicitud/lote.

La regresión prueba esos pasos por la ruta normal `qa-login.php`, comprueba el
SHA-256 del REVIEW mostrado y el estado canónico antes/después, y mantiene ZIP y
archivo histórico. Las negativas cubren usuario sin sesión, propietario, cliente,
permiso revocado, revisor con solo descarga, CSRF inválido, IDs inexistentes y
solicitudes ya procesadas. Para ejecutar el navegador visible durante QA:

```sh
MXMED_QA_VISIBLE_BROWSER=1 node modules/media/tests/OwnerMediaReviewHttpTest.mjs --keep
```

Al finalizar, el entorno queda disponible en las rutas impresas. No se necesitan
cambios de permisos reales ni intervención en AWS.
