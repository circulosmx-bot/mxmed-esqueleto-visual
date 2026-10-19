# ASE-ADVISOR-WORKSPACE03 — pendientes agrupados y navegación completa

Baseline verificado: `40b8691f94636909bfb448655939e91387f59fe5`, WORKSPACE02 limpio. Rama aislada `feature/ase-advisor-workspace03-physician-pending-groups`. Autoridad: solicitud expresa del Director WORKSPACE03, con agrupación por médico, once pestañas y contadores autorizados. No autoriza módulos nuevos, cartera comercial, mensajería ni activación normal-local.

## Decisión y superficie

`PRODUCT_POLICY`: una tarjeta por ID médico canónico; las aplicaciones sin perfil usan su propio origin_id. Ni nombres coincidentes ni cuentas solicitantes se fusionan. La relación de materialización existente conserva continuidad. El círculo rojo cuenta **tareas accionables**, no tarjetas ni todo lo aún sin resolver. El encabezado y las pestañas cuentan pendientes visibles autorizados, incluidos resúmenes restringidos; una solicitud que espera exclusivamente al solicitante sigue sin resolver, pero no integra la cola accionable. Recibir su respuesta restaura una tarea, no crea otra.

Cambio UI-2/UI-3 acotado y expresamente solicitado sobre el workspace existente: tarjetas agrupadas, contadores compactos y fila horizontal de once pestañas con scroll y menú «Secciones». No modifica el modal aceptado. RESUMEN, SOLICITUDES, PERFIL, ACREDITACIÓN, CONSULTORIOS, SERVICIOS Y ATENCIÓN, IMÁGENES, PLAN, OPINIONES, SEO e HISTORIAL permanecen representados. Las áreas no conectadas muestran un estado informativo; no hacen llamadas a consumidores owner ni contienen formularios ficticios. La aceptación visual final sigue pendiente.

## Fuente, autoridad y alcance

`SOURCE_IMPLEMENTATION`: [PendingSummary](../../modules/identity/PhysicianRequests/PendingSummary.php) es un lector transaccional sin escrituras de negocio. Reutiliza el predicado de submissions Media, origins/intakes, última decisión, respuesta de esa decisión y enlace aplicación–perfil existentes. Media sólo cuenta submissions enviados y READY que requieren revisión con REVIEW disponible. Una decisión terminal no cuenta otra vez. La cantidad Media accionable requiere lectura y al menos una facultad aplicable; mejora sola sólo cuenta logotipos. Solicitudes requieren read y resolve del tipo correcto. Evidencia y elegibilidad de una aprobación conservan validación propia.

El mínimo grant añadido es `physician_pending_summary_read`, dentro de la familia de solicitudes administrada por el Director mediante el catálogo y writer de grants existentes. No se concede por rol. No hay tabla nueva, DDL, cartera o asignación inventada.

**Ámbito de seguimiento**: unión de perfiles que ya tienen submissions Media accesibles bajo `media_review_read` y perfiles ligados a solicitudes que el actor puede consultar con `physician_request_read`, excluyendo autorrevisión y respetando el scope de cohorte si existe. Los registros históricos conservan el contexto después de resolver el último pendiente. Ser público o existir en profiles_doctors no introduce al médico en el ámbito. El grant de resumen por sí solo no permite enumerar perfiles.

Dentro de ese ámbito, el grant permite agregar pendientes de la otra sección aunque falte su read operacional. No autoriza descubrir aplicaciones sin perfil, personas solicitantes, IDs de casos restringidos, documentos, títulos privados, motivos, notas o finanzas. El DTO sólo añade conteos por Solicitudes/Imágenes al contexto médico ya autorizado. No se fabrican contadores para los otros dominios.

Una cuenta sólo con lectura puede seguir consultando el contenido que ya le permite su grant, pero no recibe tareas accionables si carece de facultad operativa. Los grupos sin tarea accionable se separan bajo «Otros pendientes visibles para seguimiento»; no inflan el círculo rojo. El grupo sale de la cola accionable al resolver su última tarea, y el perfil conserva su búsqueda/historia autorizadas.

## Conexión y consistencia

`CONNECTIVITY`: `requests.php?mode=pending` utiliza la composición explícita owned de WORKSPACE02, Identity y transporte seguro existentes. Los demás modos de decisiones, evidencia, ownership e historial se conservan. La lista completa de grants se revalida dentro de la transacción, junto con cuenta/staff/credencial y sesión viva antes de retornar. Cada categoría autorizada admite un cero estable, sin revelar por su mera presencia que antes existió un expediente restringido. El reader no concede autoridad por doctor_id enviado por el cliente. Un par aplicación/médico incorrecto es conflicto; un contexto fuera de ámbito se deniega.

Las cuentas y los conteos se calculan del mismo snapshot de fuentes, sin decrementos optimistas. Tras un commit o una reconciliación confirmada, el cliente consulta nuevamente. Navegar, fallar o repetir un ACK no resta unidades. Epochs/controladores impiden que una respuesta del contexto anterior reemplace al actual. Actualización periódica y retorno a la ventana revalidan fuentes y permisos; cada lectura/acción del servidor revalida inmediatamente. Si el reader está indisponible se indica conteo sin verificar, no un cero ficticio. El resto del trabajo autorizado conserva su acceso por búsqueda.

El índice limita a 100 los grupos devueltos por categoría, manteniendo los totales canónicos completos; no convierte ese límite en el total. La búsqueda conserva los límites de lectura previos. Las aplicaciones materializadas cambian a la clave del perfil vinculado, sin duplicar trabajo ni borrar su historia.

## Verificación y operación

`VERIFICATION=PASS_ISOLATED_SYNTHETIC`. [Ensayo dirigido](../evidence/ase-advisor-workspace03/qa.json): **53 checks PASS**, incluidos [39 de navegador](../evidence/ase-advisor-workspace03/browser-result.json), cinco de setup y nueve de admisión canónica de la cuenta ficticia. [Complemento](../evidence/ase-advisor-workspace03/supplement.json): **10 PASS** de fuente final, mutación denegada, lecturas repetidas, respuesta tardía A/B e indisponibilidad/recuperación de conteos y revocación/restitución de lectura Media manteniendo el scope autorizado de Solicitudes, sin recargar la página. Son 63 comprobaciones dirigidas, sin suites históricas heredadas. Chromium y WebKit pasan en 1440×900, 1366×768 y 390×844; también viewport nativo. Safari físico NOT_RUN; smoke móvil interino.

[Transición Media](../evidence/ase-advisor-workspace03/media-transition.json): aprobación real, cinco → cuatro pendientes visibles de Aurora y decremento global de una tarea; historial canónico presente. [Espera del solicitante](../evidence/ase-advisor-workspace03/waiting-observation.json): una solicitud sin resolver y cero tareas accionables hasta la respuesta propia, luego una tarea de revisión. Las reanudaciones inspeccionaron decisiones ya persistidas sin repetirlas ni resetear registros. La automatización descartó inicialmente el confirm de Media; no produjo mutación y se corrigió la aceptación explícita de la prueba. El ensayo inicial de información no confirmó cambio; se comprobó su estado y la decisión se completó por el mismo formulario con MFA vigente antes de continuar. No se ocultaron errores detrás de un banner de éxito.

Capturas de [inbox agrupado nativo](../evidence/ase-advisor-workspace03/screens/native-grouped-inbox.png), [resumen restringido](../evidence/ase-advisor-workspace03/screens/summary-only-request.png) y [móvil Chromium](../evidence/ase-advisor-workspace03/screens/chromium-390.png). Los totales varían legítimamente con las facultades del actor: la matriz de navegación se verificó con todos los grants de esta fase; el preview final deja Media operativo y Solicitudes sólo como agregado acotado. No se atribuyen al resumen las facultades de resolución.

`OPERATIONAL_STATUS=OWNED_SYNTHETIC_ONLY`. Sin cambios a aplicación original, 8091, bases reales, cuentas reales, notificaciones externas, ownership/cuotas comerciales o activación de producción. WORKSPACE02 permanece en su worktree original. Rollback: cerrar únicamente el runtime de esta fase mediante su launcher y volver a la rama baseline conservada; no hay migración ni estado persistente original que revertir.

## Preview y recorrido de revisión

Instancia owned `https://127.0.0.1:59695`, sólo loopback TLS. Ambas ventanas Chromium abiertas y verificadas con login canónico; `preview-delivery.json` conserva geometría y capturas, y `final-verification.json` liga la evidencia a los hashes de fuente y a la preservación de 26 worktrees/listeners. Vence **2026-10-19 23:53:08 UTC−06:00 (America/Mexico_City)**, sin extensión de sesión, MFA ni runtime. [Director/Equipo](https://127.0.0.1:59695/internal/media-review/index.php?section=equipo) y [Asesor](https://127.0.0.1:59695/internal/staff-workspace/index.php) usan sesiones legítimas independientes: `qa_director@example.test` e `oper09_advisor@example.test`. La cuenta real preferida no se ha sustituido ni copiado.

- Aurora conserva **cuatro pendientes visibles**: tres imágenes y un reclamo. La cuenta final puede operar Media y consultar sólo el agregado de Solicitudes. El círculo rojo muestra sus siete tareas Media accionables, distribuidas en Aurora y Bruno; el reclamo restringido no lo incrementa. No son constantes del producto.
- Abre «Secciones» para recorrer las once pestañas. «Solicitudes» muestra el conteo y su restricción; «Historial» permite consultar la aprobación Media completada.
- Para revisar también aplicaciones y sus resoluciones, el Director puede conceder a Irene los cuatro permisos existentes de Solicitudes: consultar solicitudes, consultar evidencia, resolver reclamos y resolver perfiles nuevos. El resumen no sustituye esos permisos. Después de actualizar, `New_ready` permanece disponible; `Clara Ficticia Reclamo` y `New_approve` conservan decisiones aprobadas e historial. Ownership/publicación siguen sus etapas independientes; esta regresión no afirma haber activado ownership en los casos nuevos del ensayo.
- La cuenta ficticia separada `qa_advisor@example.test`, sin resumen ni resolución, comprobó que no obtiene contadores Media restringidos. No es la sesión entregada al Director.

Desde cualquier directorio:

```sh
python3 /Users/circulodigital/Documents/GitHub/mxmed-ase-advisor-workspace03-physician-pending-groups/scripts/ase-advisor-workspace03-review.py /tmp/mxmed-ase-adm02imp01-fc818co5 status
python3 /Users/circulodigital/Documents/GitHub/mxmed-ase-advisor-workspace03-physician-pending-groups/scripts/ase-advisor-workspace03-review.py /tmp/mxmed-ase-adm02imp01-fc818co5 open
python3 /Users/circulodigital/Documents/GitHub/mxmed-ase-advisor-workspace03-physician-pending-groups/scripts/ase-advisor-workspace03-review.py /tmp/mxmed-ase-adm02imp01-fc818co5 stop
```

El launcher comprueba ownership, rama/HEAD/fuentes, listener y vigencia. `open` usa el login Identity, sin importar cookies; también admite `open-director` y `open-advisor`. `credentials-director`, `credentials-advisor`, `mfa-director` y `mfa-advisor` copian exclusivamente el dato sintético al portapapeles local para una entrada manual legítima. Nunca se documentan claves ni se amplían TTL. `stop` sólo señala al runner propietario, que dispone sus propios recursos; no actúa sobre WORKSPACE02 ni el runtime original.

## CTX-SYNC01

```ini
CONTEXT_BASELINE=WORKSPACE02 40b8691f94636909bfb448655939e91387f59fe5
AFFECTED_CAPABILITY_IDS=ID-04,ID-05,ID-06,ID-07,PUB-04,PUB-05,MED-01,MED-02,MED-03,COM-10
AFFECTED_JOURNEY_IDS=J01,J02,J09
AFFECTED_DATA_OBJECTS=grants Identity, origins/intakes, decisiones/respuestas/enlaces de solicitudes, submissions/batches/REVIEW Media y perfiles existentes; sin tabla nueva
AFFECTED_CONTEXT_FILES=estado actual, router, inventario, interacciones, datos/migraciones, trazabilidad, registro visual y contrato WORKSPACE03
CONTEXT_CHANGES_IMPLEMENTED=agrupación exacta, once pestañas, resumen de ámbito mínimo y contadores accionables separados de pendientes visibles
CONTEXT_VERIFICATION=53 checks principales y 10 complementarios PASS, seis combinaciones Chromium/WebKit y viewport nativo; ocho documentos, 17 enlaces y diez campos CTX verificados
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en commit aislado; hash en entrega final
REMAINING_CONTEXT_GAPS=aceptación visual pendiente, Safari físico no ejecutado, activación normal-local/producción ausente y demás dominios sin consumidores nuevos
```
