# UX-ALIGN01 — informes públicos de corrección de perfiles

Fecha: 2026-10-16. Auditoría sobre el baseline `e927dcfa86e3bf1fb7cf4e972b4155051d2e51c0`. Este documento prepara el contrato pendiente de la fase C. No implementa persistencia, moderación, permisos nuevos, migraciones ni activación normal-local.

## Superficie original y estado comprobado

La superficie pública existente es `profiles/doctor.php?doctor_id=<referencia exacta>`. La página obtiene el DTO del controlador público existente; el front controller explícito es `api/profiles/index.php/public/doctor/<referencia exacta>`. Los candidatos de `public_profile_seo_routes` no prueban una ruta SEO operativa: el read model actual conserva el bloqueo `seo_router_not_implemented`. No se propone otra página pública, otra aplicación ni una ruta SEO inventada.

La única acción de corrección encontrada en el código inicial es “Sugerir corrección”, dentro de `.mxpp-about-target` en `profiles/doctor.php`. Es un enlace sin destino funcional, con `href="#"` y `aria-disabled="true"`. No hay un endpoint público de informes, repositorio o esquema de informes, cola de moderación, notificación del informe, servicio de decisiones ni aplicador gobernado de sus cambios.

`PublicProfilePlanCapabilities::build()` permite mostrar la acción solamente para el plan FREE, perfil no administrado y fuente de membresías disponible. `PublicProfileRepository::resolveOwnershipSource()` considera administrado un perfil con una membresía activa `owner`, `administrator` o `collaborator` de scope `profile` o `profile_doctor`. Este gate de corrección se conserva separado de la elegibilidad para iniciar un reclamo de propiedad. No se amplía a planes pagados ni a perfiles administrados por esta fase.

La auditoría identificó una incoherencia del controlador público: cuando la fuente de membresías no está disponible, calcula `profileIsAdministered=false` y pasa `ownership_source_ready=true` al constructor de capacidades. La corrección acotada debe pasar el valor real de `$membershipAuthorityReady`; la indisponibilidad de la autoridad debe ocultar la acción. No debe interpretar una consulta fallida como prueba de ausencia de administración.

Las colas actuales de `internal/media-review/index.php` y su sección `?section=acreditacion` tienen propósitos y autoridades propios. La revisión de medios y la acreditación C4 no son un backend equivalente de informes públicos; no se guardarán informes allí ni se heredarán sus grants como permiso de moderación de correcciones.

## Presentación permitida antes de activar el backend

La fase C implementa una sección desplegable accesible en la misma ubicación y con el mismo gate público. Muestra el nombre del perfil ya renderizado y explica que la recepción de informes está pendiente de habilitación. El usuario puede abrir o cerrar la presentación informativa y continuar por el perfil original. Los catálogos preparados en los controles deshabilitados son referencia del alcance futuro, no un mecanismo de captura.

Categorías de producto:

- Nueva dirección del médico o consultorio.
- Dirección de consultorio incorrecta.
- Cambio de teléfono de consulta o contacto desactualizado.
- Horarios incorrectos.
- Perfil duplicado.
- Médico retirado.
- Médico fallecido.
- Persona o perfil equivocado.
- Otra corrección factual.

Relaciones de quien informa: paciente, familiar, amistad, colega, asistente, administración del consultorio, institución u otra. La relación es una declaración del informante, nunca una autoridad comprobada.

Mientras no exista recepción segura, los campos, contacto opcional y envío permanecen deshabilitados. No se deben recopilar datos en un formulario que parezca funcional. No hay POST, éxito simulado, confirmación de recepción, correo alternativo inventado, descarga, adjunto, `localStorage`, `sessionStorage` ni escritura en historial. La presentación debe distinguir claramente una corrección de “Reclamar este perfil”; no se pasa su contenido al onboarding ni se inicia un reclamo.

## Agregado y esquema lógico pendientes

Se necesita un único agregado canónico de informe de perfil, dentro del dominio existente de perfiles. El diseño físico, nombres de tablas, migración, permisos SQL y despliegue requieren preparación y aceptación separadas; esta lista no afirma que existan columnas instaladas.

| Información | Contrato pendiente |
| --- | --- |
| Referencia de informe | Identificador opaco estable generado por servidor; no autoriza por sí mismo a leer el informe. |
| Perfil de origen | Referencia exacta del perfil y ruta pública efectivamente resueltas por servidor al abrir el flujo, con procedencia y versión aplicables. |
| Intención | Informe factual de tercero, separado de todo origen C4, admisión, binding, membresía y propiedad. |
| Categoría y relación | Catálogos cerrados versionados; no permiten introducir comandos de cambio. |
| Explicación | Texto UTF-8 acotado y validado; sin HTML ejecutable, datos clínicos solicitados ni instrucciones automáticas de actualización. Límites concretos pendientes. |
| Contacto opcional | Mínimo necesario para aclaración; validado, privado y protegido por la política de custodia y retención que se apruebe. No exigir teléfono o correo para aparentar identidad médica. |
| Evidencia opcional | Referencias a un almacén privado autorizado para este propósito; no bytes o URL pública en el reporte ni en el DTO público. |
| Estado y revisión | Estado explícito, revisión optimista y fechas del servidor; transiciones mediante servicio, con historial inmutable. |
| Decisión humana | Actor autorizado, alcance, motivo, evidencias verificadas y versión del informe revisada. |
| Aplicación | Referencia independiente de operación aprobada, destino tipado, versiones esperadas, resultado e idempotencia; no un PATCH arbitrario suministrado por el informante. |
| Auditoría | Eventos canónicos con procedencia, resultado y correlación; excluir texto libre, contacto, documentos y secretos del evento público o de los logs generales. |

La asociación original no se sustituye silenciosamente al encontrar un duplicado o un perfil parecido. Una resolución posterior debe ser explícita, revisada y trazable. Nombres, correo, alias y relación declarada no prueban identidad ni facultades. Un informe de perfil equivocado no reasigna el informe original, el perfil ni su propietario de manera automática.

El ciclo lógico propuesto separa `PENDING_REVIEW`, `NEEDS_CLARIFICATION`, `REJECTED`, `ACCEPTED_FOR_APPLICATION` y `APPLIED`. Son estados de diseño pendientes, no estados instalados. La recepción persistida y la decisión de revisión deben ser durables antes de mostrar su confirmación. La aceptación de un informe no equivale a haber aplicado su cambio. Un fallo de persistencia, auditoría o aplicación no debe transformarse en éxito visible.

Las aclaraciones y evidencias posteriores se anexan como nuevas revisiones o aportaciones inmutables vinculadas. No se sobrescribe una evidencia aceptada ni se elimina el contenido original para ocultar una decisión anterior. La recuperación posterior al commit requiere transición compensatoria auditada, nunca SQL manual, eliminación de historia o nueva autoridad paralela.

## Recepción pública, privacidad y abuso

Antes de habilitar envíos se necesita un boundary HTTP público propio del informe, que reutilice la infraestructura de seguridad existente. Debe definir expresamente métodos, tipo de contenido, tamaño, validación, origen HTTPS permitido, protección CSRF para el contexto público y rechazo de replays/idempotencia. No se puede llamar al boundary autenticado de C4 como si autorizara a cualquier tercero, ni omitir CSRF por ser un formulario público.

`Identity\Services\RateLimitService` ya usa buckets con dimensiones hasheadas `identity`, `ip` y `device`, pero su catálogo y política actuales solo admiten operaciones de Identity. El flujo de informes necesita una operación y política específicas, dimensionamiento aprobado para informes dirigidos al mismo perfil y comportamiento explícito ante abuso distribuido. No debe consumir buckets de registro, login o recuperación. Cantidades, ventanas, retención, mecanismos de desafío y tratamiento de proxies de confianza quedan pendientes de decisión; este documento no aprueba valores por defecto ni proveedores externos.

La indisponibilidad del limitador, almacenamiento o auditoría debe impedir una recepción declarada como exitosa. Los mensajes no revelan cuentas, propietarios, grants, otros informantes o documentos. El perfil público no expone nombre, relación, contacto, IP ni identidad del informante, ni publica alegaciones o su evidencia. Las respuestas públicas no contienen PHI ni archivos privados.

Un eventual enlace de seguimiento o aclaración requiere un mecanismo opaco, acotado, expirable y seguro definido por contrato; el ID del informe no basta como credencial. No se inventa tal enlace en esta implementación.

## Documentos opcionales

El backend privado de medios/evidencia existente no concede a un tercero anónimo derecho de carga o lectura. Antes de ofrecer adjuntos se requiere una autoridad de upload específica del informe: alcance exacto al expediente, límites de tipo/tamaño/cantidad, detección y validación de contenido, almacenamiento privado y custodia, acceso del revisor, registro de hashes, recuperación de huérfanos y disposición según política aprobada.

Los uploads no comparten claves, referencias autorizantes, prefijos ni acceso con archivos clínicos, C4 o avatares de Operadores. No se aceptan URL públicas arbitrarias como evidencia confiable. No se generan enlaces públicos de descarga. Hasta que este contrato se implemente y pruebe, no hay input de archivo habilitado, captura móvil ni QR de carga.

## Moderación y aplicación gobernada

Se necesita un servicio de revisión del informe y una capacidad explícita de moderación de correcciones. El nombre final del grant, su aprovisionamiento y los niveles de reautenticación/MFA se deben definir por alcance y riesgo; no se crea ni se da por aprobado un grant nuevo en UX-ALIGN01. Ser reviewer de medios, reviewer C4, médico colaborador o propietario de un perfil no concede automáticamente esa facultad interna.

El reviewer debe usar la sesión canónica vigente de una cuenta interna legítima, alcance específico y revisión actual. El servicio comprueba cuenta, sesión, grant y condiciones exigidas al decidir; la UI no sustituye esas verificaciones. Debe permitir solicitar aclaración, rechazar o aceptar con motivo y evidencia verificada, manteniendo la procedencia original. Las escrituras de decisión y auditoría deben ser transaccionales; concurrencia y revocación de autoridad deben fallar cerradas.

La aplicación autorizada debe reutilizar los escritores tipados existentes cuando cubran el dato: identidad pública en `PrivateProfileController`/`PrivateProfileRepository`, puntos de contacto en `DoctorContactPointsController`, información profesional en `ProfessionalInformationService` y datos de consultorio en sus servicios de Agenda. Esos escritores tienen sus propios límites y no son un API público de moderación. Falta el adaptador gobernado que pruebe la decisión, autorice el campo exacto, valide la versión actual y ejecute la mutación con su auditoría e idempotencia. No se llama directamente a una ruta privada con privilegios simulados ni se introduce un escritor SQL general de perfiles.

Si el escritor existente no cubre el cambio solicitado o no permite la atomicidad, procedencia y alcance requeridos, ese tipo de aplicación continúa bloqueado hasta implementar el contrato apropiado. Una decisión humana de aceptación no permite sobrepasar el allowlist del escritor.

La corrección factual nunca crea ni modifica cuentas, credenciales, membresías, propietario, acreditación profesional, suscripción o autoridad clínica. Cambios que afecten fuentes verificadas o el uso profesional de una credencial deben seguir su procedimiento canónico de revisión y reverificación; no se reescribe VID01/CRD02 desde un reporte público.

## Retiro, fallecimiento, duplicados y conflicto de identidad

“Médico retirado” y “Médico fallecido” son alegaciones sensibles hasta que una persona autorizada verifique evidencia adecuada y el alcance del cambio. Debe prepararse un procedimiento específico con autorización, auditoría, posibilidad de aclaración/impugnación y tratamiento de consecuencias antes de permitir su aplicación. Suponer que el formulario contiene una categoría sensible no valida el hecho.

Un informe no puede por sí solo marcar fallecimiento/retiro, cambiar `profile_status`, ocultar o despublicar el perfil, borrar datos profesionales, terminar suscripciones, revocar cuentas, cambiar permisos o transferir propiedad. La tabla `profiles_doctors` contiene `profile_status` como dato de publicación; eso no prueba que exista un registro gobernado de fallecimiento o retiro. El PATCH privado de identidad actual no lo admite como campo editable y esa frontera se conserva.

Un duplicado o una persona equivocada requiere resolución explícita de identidad y procedencia. No se fusionan perfiles, membresías, contactos, citas, datos clínicos ni historias por coincidencia de nombres o correo. El origen del informe se preserva y no se transforma en prueba de reclamo.

## Aceptación necesaria antes de una activación posterior

El siguiente incremento funcional debe probarse exclusivamente con bases/usuarios/archivos sintéticos desechables y componentes originales antes de solicitar un despliegue real. Debe demostrar:

- Recepción idempotente con contexto exacto, validación, CSRF/origen y rate-limit propios; fallo de auditoría o almacenamiento sin éxito falso.
- Separación entre informe, C4, ownership y membresías; cero elevación de autoridad por relación o datos coincidentes.
- Aislamiento de contacto y documentos privados, sin PHI ni identidad del informante en el perfil o respuesta pública.
- Reviewer sin grant, revocado, alcance ajeno o revisión obsoleta rechazado; decisiones concurrentes seguras.
- Aceptación separada de aplicación; mutation allowlist, versión, writer canónico y auditoría obligatorios.
- Fallecimiento/retiro sin cambio automático de publicación, datos, sesión, suscripción o propietario.
- Gate FREE/no administrado preservado; fuente de membresía no disponible falla cerrada; no almacenamiento en navegador.
- Responsive/teclado y ausencia de éxito falso en Chromium/WebKit usando los tamaños exigidos por UX-ALIGN01.
- Normal-local, datos clínicos, membresías, autoridades, Agenda, QA05, OP02 y UI03 intactos.

Este documento prepara trabajo por separado. No autoriza DDL, retención, nuevos permisos, aprovisionamiento de staff, servicios externos o activación en `mxmed`. El estado de la fase C debe seguir siendo parcial mientras recepción, moderación y aplicación permanezcan sin implementar, aunque la presentación pendiente esté verificada.

## Verificación de la presentación acotada

El cambio implementado en `profiles/doctor.php` usa `<details>/<summary>` nativos, conserva la ubicación y el texto “Sugerir corrección”, escapa el nombre público original y mantiene separados el reclamo y el footer FORMAR PARTE. Sus controles no tienen `name`, no pertenecen a un formulario y permanecen deshabilitados. No se añade JavaScript, endpoint, upload, almacenamiento, envío ni reconocimiento de recepción. `public-profile.css` añade únicamente estilos dentro de `.mxpp-correction`, con foco visible y control de apertura de 44 px. El controlador ahora propaga la disponibilidad real de la fuente de membresías a su gate.

`PublicCorrectionReportGateTest.php` ejecuta el repositorio y el controlador existentes con SQLite en memoria; solo adapta la consulta de metadatos MySQL. Sus 52 comprobaciones cubren tabla de membresías ausente, columnas incompletas, fallo de lectura, fuente conocida vacía, owner/administrator/collaborator activos, membresía revocada, otro perfil y los cuatro planes pagados. La proyección no realiza escrituras.

`scripts/ux-align01-correction-browser-qa.py` sirve el template público original con DTO sintético desde el renderer CLI exclusivo de pruebas. Chromium y WebKit pasan 24 escenarios —cuatro estados por cada uno de los tres tamaños 1440×900, 1366×768 y 820×1180 en cada motor—, apertura/cierre mediante teclado, contexto escapado, etiquetas nativas, controles deshabilitados, ausencia de formulario/uploads/vínculos dentro del informe, separación de reclamo, footer conservado y ausencia de overflow, errores de página, mutaciones HTTP y cambios de almacenamiento. No es un ensayo de recepción persistente ni una aprobación Safari del Director.

También se repitió `scripts/ux-align01-public-browser-qa.py` tras el cambio: los seis casos de CLAIM/FORMAR PARTE de la fase B pasan en ambos motores y tres tamaños. Pasan los contratos existentes de visibilidad de corrección, comportamiento por plan, fidelidad de contacto, discovery y presentación de inscripción. Se conservan manifest de fuente y capturas sintéticas en `~/Library/Application Support/MXMED/ux-align01/20261016-review/correction/`.

El contrato estático más amplio `PublicProfileHeroConsultorioContactLayoutTest.php` falla en su línea 52 por una coincidencia literal histórica de la lista de iconos Material Symbols. Se reprodujo el mismo fallo ejecutando el test desde un archivo Git desechable del baseline `e927dcfa…`; no se relajó el test ni se cambió la iconografía para ocultarlo. Esa deuda previa se informa separada de las pruebas acotadas de corrección que sí pasan.
