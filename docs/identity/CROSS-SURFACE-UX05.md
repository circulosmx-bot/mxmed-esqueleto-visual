# CROSS-SURFACE-UX05 — fotografía pública y domicilio administrativo

Baseline `cb12124b5948e3e97d508d516df40b4c074a031c`, rama `feature/mxmed-cross-surface-ux05`. Sucesor acotado de [UX04](CROSS-SURFACE-UX04.md) y consumidor de [LINK03](CROSS-SURFACE-LINK03.md). Staff/Identity sintéticos, fuentes administrativas originales restringidas; no exclusivamente sintético.

## PRODUCT_POLICY

UI-2 expresamente autorizada: completar información administrativa y verificar navegación con Chromium visible, sin reconstruir shell ni cambiar decisiones de publicación. Se permite ampliar proyecciones de lectura con campos no clínicos aprobados. Se conservan los registros originales, relaciones, credenciales, pacientes, agenda y runtime 8091.

**Distinción necesaria del reporte:** ampliar las vistas es DDL de metadata en la base original, aunque no modifica registros. Por tanto `ORIGINAL_RECORDS_CHANGED=false` y `RESTRICTED_VIEW_METADATA_CHANGED=true`; no sería exacto declarar que la base completa no cambió. No hubo nuevos grants SQL, cuentas, tablas ni ampliación a tablas base.

## SOURCE_IMPLEMENTATION

### Fotografía

El diagnóstico de sólo lectura verifica que `profiles_doctors.photo_url` referencia una fila exacta de `media_assets`, del mismo owner PHYSICIAN, propósito DOCTOR_PROFILE_PHOTO, clasificación PUBLIC, estado READY y deleted_at nulo. Existe una versión pública WebP de 640×800; no hay catálogo adicional `media_variants` en esta fuente. El endpoint canónico devuelve el archivo con checksum validado. Hay además una solicitud de foto PENDING_REVIEW y dos WITHDRAWN: no se usan ni se alteran. La foto publicada vigente y el candidato pendiente son objetos distintos.

UX04 devolvía avatar porque su proyección no incluía medios, no porque se hubiera demostrado que no existía foto. La proyección v2 une sólo la referencia vigente/propietario/propósito elegible. No lee documentos de identidad ni storage_key privado. La ruta `admin_portrait` owned revalida Identity, staff, grant y ámbito con el Reader, obtiene únicamente la ruta pública UUID permitida desde el origen loopback configurado y revalida después de obtener el binario. Sin cookies ni credenciales reenviadas, redirects, importación ni archivos copiados; límite 2 MiB, timeout 3 s, tipos PNG/JPEG/WebP, no-store y fallback neutral ante fallo/revocación. No se modifica el servidor original.

### Consultorios

`SqlSource` incorpora explícitamente `projectionVersion=2`; el contrato v1 sigue siendo el default compatible. El adapter local inyecta la versión del recibo. Campos añadidos: calle, num_ext, num_int, cp, colonia y document_publication_enabled. La UI compone domicilio/C.P. y distingue habilitación para documentos de apertura operativa o publicación pública. Conserva títulos/localidad y `physician_locations_read`, origen exacto, límite/cursor, audit y revocación.

No se expone telefonos_json, WhatsApp, urgencias, coordenadas, citas ni pacientes. Un teléfono almacenado no acredita finalidad pública; el diagnóstico no localizó un flag de consentimiento público por teléfono dentro de la sede. Los valores ausentes se indican sin fabricarlos.

[Expansión explícita de proyección](../../scripts/cross-surface-ux05-projection.py): revalida entorno local/UUID/recibo/IDs, conserva copia privada de definiciones previas y hashes de registros, reemplaza sólo views profiles/locations como TEMPTABLE/DEFINER con el mismo filtro de médicos. Usuarios y grants SELECT existentes no cambian. No migración histórica, DML, índices ni tablas. Recuperación: aplicar las definiciones previas del recibo privado únicamente después de retirar consumidores v2; no rollback automático que rompa previews activos.

### Selección y assets

Se preservan los estados/epochs de UX04. Los assets CSS/JS del workspace reciben una versión calculada por SHA256 del contenido para impedir reutilización de una versión anterior bajo la misma URL. La QA usa el botón real «Buscar médico o solicitud», cambios rápidos y respuesta diferida. El launcher final repite Aurora → botón buscar → perfil de origen → Consultorios en Chromium nativo antes de declarar lista la ventana.

### Relaciones e índice

La vista relationships existente conserva perfil, membership_id, role/status, estado de cuenta y verificación de contacto; no infiere nombres de referencias. El diagnóstico del ámbito original observa una relación colaborador activa y ninguna fila owner activa confirmable. La UI dice **no se puede confirmar** y evita convertirlo en prueba de inexistencia. Revocados, si los hay, conservan su estado en la lectura, no se filtran como activos.

El diagnóstico de invitaciones y de revisiones/grants de operador devuelve cero filas para el recurso, pero la UI no usa ese diagnóstico puntual como lector operativo: `auth_profile_operator_invitations` y `auth_profile_operator_grant_revisions/grants` siguen fuera de la proyección/grants SQL. No se muestran como listas vacías verificadas ni se añaden mutaciones owner-only.

El índice conserva seis entradas, estado de ventanas independientes, actor/ámbito y requisitos. Director y Asesor ofrecen botones para copiar el comando exacto de su launcher; ejecutar el comando abre una ventana con login propio. Navegar con la sesión actual sólo enlaza su panel autorizado, nunca cambia rol. El índice no ejecuta comandos arbitrarios ni traslada cookies.

## CONNECTIVITY / superficies comparables

| Superficie | Estado y comparación real |
|---|---|
| Director | Gobierno staff sintético: concede/revoca capacidades del Asesor mediante writer canónico. No ficha médica original. |
| Asesor | Nombre, especialidad, localidad, foto pública, sedes/domicilios y relaciones autorizadas del mismo `origin:id`. |
| Médico | BLOCKED: faltan sesión médica legítima y composición original. El harness sintético de solicitudes tiene sus propios perfiles; asignarle el ID de origen falsificaría el vínculo. |
| Operador | BLOCKED: relación canónica observable no aporta credenciales/sesión propia. Los harnesses sintéticos no acreditan esa relación original. |
| Público | BLOCKED: ruta SEO existente en estado candidate, route_enabled=0, canonical_enabled=0. `active`/is_public_candidate no sustituyen esos gates. No se abrió como perfil publicado. |
| Proveedor | BLOCKED: relación organizacional original no acreditada ni sesión propia. No existen tablas con nombre provider en este schema; eso no acredita inexistencia universal. No se crea organización sintética para representar al médico original. |

No hay desajuste demostrado de datos entre dos fichas médicas habilitadas: sólo Asesor consume la ficha original; Director verifica autorización. Los cuatro contextos restantes tienen prerrequisitos concretos, no equivalencias simuladas.

## VERIFICATION

56 verificaciones de navegador/seguridad PASS (43 principales + 13 de retrato/índice), más 26 de privilegios SQL. Evidencia privada `/tmp/mxmed-ase-adm02imp01-_nimi_l6/ux05-evidence`; no registros/credenciales importados al repositorio.

- Chromium visible: botón Buscar médico o solicitud desde Aurora, selección de origen, regreso, respuesta tardía descartada, revocación de grant/scope y límites/cursor. Requests/Media/Operators siguen operativos. Tres tamaños 1440×900, 1366×768 y 390×844, sin desbordamiento; WebKit dirigido PASS.
- Foto: binario público real, no-store, sin Identity/origen incorrecto/recurso ajeno/URL arbitraria → denegado; retirar grant/ámbito → denegado; volver a sintético no retiene foto original. La revisión de captura detectó el SVG visible delante de la imagen: se corrigió con atributo hidden explícito (SVG no implementa HTMLElement.hidden) y se repitieron las pruebas con SVG oculto e imagen visible. Captura móvil final inspeccionada.
- Assets versionados y comandos independientes visibles; botón copiar comando Director ejercido. Login/logout/relogin Advisor y sesiones distintas; rutas adicionales devuelven 404 en la composición owned, sin elevar roles.
- Las 26 pruebas SQL preservan exactamente los mismos SELECT sobre vistas y deniegan tablas base, escrituras y DDL por los lectores. Hashes de registros originales de perfiles, sedes, relaciones y medios iguales al recibo previo. No pacientes/citas/contactos privados en proyecciones.
- Primer ensayo falló cerrado por faltar el origen público explícito en el entorno cargado; se corrigió la configuración del runner y se creó instancia nueva. No se añadió un origen por defecto ni se relajó la autorización.
- 8091 conserva PID90106, HEAD `f92f37f1958af5140a96c04cb52c5c1ce7af8e01` y checkout limpio. Metadata de views sí fue ampliada: no confundir preservación de registros/runtime con ausencia de DDL.

## OPERATIONAL_STATUS

Sólo rama/preview owned loopback; sin merge, producción ni cambios a 8091. Launcher `scripts/cross-surface-ux05-review.py <owned> open|open-director|open-advisor|credentials-director|credentials-advisor|mfa-director|stop`; sesiones canónicas independientes, infraestructura de hasta 24 horas sin extender Identity/MFA. Aceptación visual Director PENDING.

Preview `https://127.0.0.1:64749`, owned `/tmp/mxmed-ase-adm02imp01-_nimi_l6`, expiración **2026-10-22T23:03:57-06:00 (CDMX)**. Director `/internal/media-review/index.php?section=equipo`; Asesor `/internal/staff-workspace/index.php`; índice `/development/interfaces.php`.
