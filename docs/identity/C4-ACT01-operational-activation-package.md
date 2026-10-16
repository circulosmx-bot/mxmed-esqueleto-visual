# C4-ACT01 — preparación operativa de la acreditación original

2026-10-16. Inicio `d12df98dd562cf768c0e58f4ade3e9bbc40f9ac1`, rama `ux/consultation-step2-vitals-r1`, árbol limpio y upstream/remoto iguales. Alcance: preparar y publicar artefactos revisables; **sin activación, DDL normal-local, credenciales nuevas ni personas autorizadas**. Se revisan [C4-EV01](C4-EV01-protected-evidence-foundation.md), [C4-ADM02-IMP02](C4-ADM02-IMP02-physician-authority-issuer.md) y [C4-UI01](C4-UI01-original-physician-onboarding.md); sus fundaciones y QA funcional desechable no se vuelven a auditar.

El [manifiesto de preparación](../../modules/identity/deployment/c4-act01/preparation.json) contiene fingerprints, hashes, orden y decisiones vacías. **No es un receipt, autorización ni configuración consumida por la aplicación.** El [inspector CLI](../../scripts/c4-activation-inspect.php) sólo lee: no tiene modo apply ni ejecuta archivos SQL. Quedan pendientes un adaptador SMS concreto y el cargador/compositor operacional con sus guards; por ello el resultado es `PARTIAL_MXMED_C4_ACT01`, aunque los planes y el ensayo de esquema sí se entregan. No se etiqueta preparado un ejecutor live inexistente.

## Estado físico y fingerprints

Servidor original HTTPS `https://localhost:8443`, PHP 8091/8092 escuchando desde este worktree, raíz 200 y acceso Identity 200. Anónimos: aplicación privada, acreditación y sección staff 302 al login existente; current-session 401. No se inicia ni detiene ningún servidor. Configuración normal-local: Identity explícito, origen HTTPS anterior, Valkey 6385 y namespace `mxmed:local:session:`; runtime Operadores, gobierno HTTP y ceremonia no habilitados. No están provisionados en esa configuración la clave MFA, firma del proxy, autorización de deployment ni trust anchor. No se imprimen secretos ni cookies.

| Identificación observada | Valor |
|---|---|
| Conexión de inspección/despliegue | `127.0.0.1:3306 / mxmed / root@localhost` |
| MySQL / server UUID | `9.6.0 / 07988c70-ffcb-11f0-9849-5901fae17cc6` |
| SHA-256 identidad exacta | `7be7a9b06e102bd19f4625c949bbba0d7998c89a86dc88286f878244f0c34532` |
| Fingerprint canónico del esquema | `41b7b26cb647083c3e7ab877791a01ec5139a67fb8a6f18f24f57dd6d7fcee92` |
| DDL completo, FKs, CHECKs, triggers y rutinas | `a3973a84e102e08835db9c08d7a5434a95d6cbc99f99c61e90ac4405fd415f76` |
| DDL de las quince tablas OP-ACT02A | `bea817698775593b4e8ae907c630efbbeba1d7ba22cb30be14e15159d8f35793` |
| Source hash de gobierno/C4 | `e0d6ad0dc62ab94884985a07c5f9ff7008fb0cd86e00253e1846704add222cb1` |

128 tablas, 138 FKs, 35 triggers y 11 rutinas; cero CHECKs no enforced y cero FKs hacia otro esquema. Las columnas 01 y las siete tablas C4 están ausentes: preestado C4 intacto. Una cuenta Identity y una membresía; **cero staff, grants, factores MFA y owners activos**. Son agregados, no una elección de beneficiario. El usuario root observado sirve para inspección/DDL controlada, no constituye un diseño de mínimo privilegio para el runtime futuro. Cambiar principal exige otro fingerprint/approval vinculado al principal efectivo.

Repetición de solo lectura, con conexión privada ya existente fuera del repo, archivo 0600 y directorio 0700:

```sh
umask 077
php scripts/c4-activation-inspect.php --connection /ruta/privada/connection.json > /ruta/privada/inspection.json
php scripts/c4-activation-inspect.php --connection /ruta/privada/connection.json --baseline /ruta/privada/inspection.json
```

El inspector acepta únicamente `mxmed` en loopback:3306, usa transacción READ ONLY, valida OP-ACT02A y devuelve metadata/agregados sin cuentas, teléfonos ni documentos. La segunda ejecución compara identidad, DDL, fuentes, migraciones y autoridades. Un cambio, una ejecución parcial o una autorización faltante **detiene** el plan; obtener un JSON no habilita DDL. La inspección tampoco garantiza ausencia de cambios concurrentes: repetirla bajo la ventana controlada inmediatamente antes de cualquier futuro DDL.

## SMS: contrato reutilizable, adaptador pendiente

Se reutiliza [OtpProviderPort](../../modules/agenda/contracts/OtpProviderPort.php) y `OtpDeliveryResult`. El único adaptador real encontrado, [SesPublicAgendaOtpAdapter](../../modules/agenda/adapters/SesPublicAgendaOtpAdapter.php), admite sólo `email`, valida el destino como email y llama `sendEmail`; rechaza `sms`. Tener AWS SDK en composer no equivale a tener un proveedor SMS contratado, configurado o un adaptador de aplicación. Los otros implementadores son fixtures de tests. `RejectingOtpProvider` debe seguir siendo el default.

El adaptador que se autorice implementará `providerId/configured/deliver('sms', E.164, seis dígitos, challenge_id)`. Se requieren: cuenta/contrato y responsable del proveedor, endpoint/región/residencia aprobados, sender/originator habilitado, cobertura para los teléfonos del piloto, plantilla transaccional española, SDK/HTTP compatible, credencial de envío de mínimo privilegio, timeout acotado, límites de coste y cuotas. El template contiene sólo código/vigencia/contexto de México Médico; no identidad oficial, especialidad, receta o datos de paciente. Los parámetros exactos dependen del proveedor aún no elegido; no se inventa aquí una variable de credenciales supuestamente disponible.

| Resultado del proveedor | Contrato requerido |
|---|---|
| Aceptación verificable con referencia no vacía del mensaje | `accepted=true`; significa aceptación para entrega, **no recepción ni teléfono verificado**. La referencia queda privada/minimizada. |
| Rechazo, credencial ausente, 4xx/5xx, cuota, timeout, excepción o respuesta ambigua | `accepted=false`, motivo finito sin body sensible; no anuncio de envío ni OTP usable. |
| Timeout/reintento del SDK | No reenvío automático ciego que duplique SMS. Usar challenge_id como idempotency key sólo si el proveedor garantiza esa semántica; de lo contrario esperar el reenvío canónico. |
| Callback de entrega | No verifica teléfono ni concede autoridad. Si se implementa después, autenticación/anti-replay/correlación propias; no hay webhook C4 actual. |

[PhoneVerificationService](../../modules/identity/Accreditation/PhoneVerificationService.php) ya genera/verifica OTP: seis dígitos, diez minutos, cinco intentos, sesenta segundos entre emisiones, máximo cinco por cuenta/hora persistentes aunque cambie número/challenge. Reserva y auditoría antes del envío; aceptación persistida en otra transacción; fallo de ese commit deniega. OTP ligado a cuenta, contacto/versión/challenge, sesión y credencial, guardado como HMAC con key ID. Reenvío sustituye el anterior, consumo una vez, revocación/expiry/cambio de sesión deniegan. No se delega esa autoridad a un proveedor tipo “Verify” que valide un OTP diferente.

Prueba sin SMS: cliente del proveedor falso/transport stub con egress deshabilitado, teléfonos `+1555…`, asserts de payload y casos aceptación/rechazo/timeout/malformed/cuota/idempotencia; luego mismo servicio y rutas originales en SQL/Valkey/objetos propios. Los fixtures C4-UI01 existentes sirven para el recorrido, no para acreditar integración de un proveedor nuevo. Ningún `dry_run` que devuelva éxito entra al runtime normal. No se ha enviado SMS ni consultado cuentas cloud.

**Decisión mínima D01:** aportar un proveedor/gateway SMS empresarial ya aprobado con su contrato/API, o autorizar la selección de uno nuevo y el adaptador de envío. Hasta entonces sólo UI de estado. No se necesita volver a seleccionar el proveedor de email ni reabrir su cierre.

## Decisión documental propuesta — pendiente de aprobación

| Decisión | Propuesta concreta; ninguna aprobada por este documento |
|---|---|
| D02: custodia | Keyring C4 XChaCha20-Poly1305 de claves aleatorias de 32 bytes; elegir macOS Keychain para normal-local o el gestor de secretos ya aprobado. El cargador debe inyectar bytes en memoria, no publicarlos en env HTTP/argv/logs/Git/JS. MFA, HMAC del proxy y credenciales SMS usan secretos independientes; no reutilizarlos como clave C4. El HMAC OTP actual usa el cipher C4 con dominio y key ID, no una credencial del proveedor. |
| Acceso humano | Sólo cuenta staff activa, capacidad exacta `physician_accreditation_review`, sesión/credencial vigentes, solicitud/versión/file exactos, reauth/MFA para decidir e independencia del solicitante. El visor HTTP es staff-only; el solicitante ve metadata. Sin acceso por owner, plan, Operadores o permiso de medios. Custodios de infraestructura no adquieren grant de revisión. |
| Rotación C4 | Nuevo key ID sólo para nuevas escrituras; conservar claves anteriores para descifrar y validar HMAC vinculados a registros vigentes, backups y holds. `EvidenceCipher` ya soporta keyring; los triggers impiden reescribir objetos/key ID/hash existentes. No inventar un UPDATE de recifrado: requeriría otro escritor/migración gobernados y ensayo. Nunca retirar claves por calendario sin inventario de referencias y prueba de recuperación. |
| Rotación MFA | `StaffMfaService` usa una clave/key ID, no el keyring C4. Cambiarla ciegamente deja factores ilegibles. Su rotación/recuperación es una operación distinta con su ceremonia y eventual trabajo de fuente; no se resetean factores ni sesiones. |
| Recuperación | Respaldo cifrado de keyring bajo custodia distinta del SQL/objetos; inventario de key IDs y acceso restringido, custodio sustituto designado. Ensayo aislado de descifrado/autenticidad con claves restauradas, bytes sintéticos y backups verificables antes de intake. Pérdida/corrupción: denegar lectura/admisión; no generar una clave sustituta ni fallback claro. |
| D03: retención | Director/responsable debe fijar por escrito plazos y punto de inicio para drafts abandonados, rechazados, acreditados, revocados, contactos/OTP, backups y objetos sin referencia; también plazo de cuarentena. Todos están **sin definir**, no en cero, permanentes ni automáticamente doce meses. La vigencia de la acreditación viene de evidencia revisada; no equivale a su retención. |
| Disposal y legal hold | Registro privado de hold por scope y responsable; hold suspende disposal, incluso de backups/claves vinculadas. Liberación explícita. Antes de borrar, revisión humana de plazo/hold/referencias/backup, autorización y evidencia minimizada. No existe worker C4 de disposal ni evento canónico de eliminación: no se simula con `FILE_READ` ni se escribe directamente al audit sink. Si se requiere automatización, implementarla y auditarla por separado antes de usarla. |
| Logging/audit | Reutilizar `PHYSICIAN_EVIDENCE_CHANGED` y `PHYSICIAN_AUTHORITY_CHANGED` y sus operaciones finitas. IDs opacos, versiones/estados/caso/motivo/actor derivados del servidor. Nunca payloads, teléfono, OTP, cédula, nombre del archivo, contraseña, nonce, clave, path de objeto o body del proveedor. Transporte/access logs deben excluir cuerpos y parámetros secretos. |

No se provisiona Keychain, vault, archivo de claves, política de retención ni registro de holds real. Una referencia de política no prueba aprobación. Hasta tener D02/D03, custodios y recuperación probados, no se permiten documentos reales.

## Reconciliación de objetos cifrados sin referencia

Plan de recuperación sin adopción ni borrado automático: detener intake durante la reconciliación autorizada; inventario privado por namespace C4 y dos snapshots SQL consistentes; cotejar cada storage key opaca con `auth_physician_evidence_files`, tamaño y hash de ciphertext sin descifrar documentos reales. Clasificar referenciado íntegro, referenciado ausente/corrupto, cifrado no referenciado y temporal de staging. La falta de referencia puede ser un commit ambiguo; no se considera abandonado por antigüedad solamente.

Referenciado ausente/corrupto: denegar autoridad por el servicio existente, preservar evidencia y restaurar exclusivamente desde backup probado bajo autorización, sin fabricar metadata. No referenciado: cuarentena privada que conserve ciphertext/IDs/key ID y posibilidad de recuperación; repetir consulta tras resolver transacciones/fallos. No insertar filas para “adoptarlo” ni moverlo a espacio público. Disposal sólo con D03, plazo aplicable, holds/backups revisados y aprobación humana; si faltan, conservar privado y detener nuevas cargas al alcanzar el límite de capacidad aprobado. `PrivateMediaStoragePort` tiene delete, pero no un listado/reconciliador/retention worker ni gate de hold: el plan no afirma esos componentes implementados.

## DDL: orden y procedimiento controlado posterior

Las cuatro fuentes SQL conservan sus hashes originales en el manifiesto, incluyendo su advertencia de no autorización normal-local. No se editan para quitar esa advertencia; una aprobación posterior deberá identificar explícitamente esos hashes, target y alcance. **No ejecutar el instalador OP-ACT02A ni sus quince CREATE otra vez.**

| Orden | Migración `2026_10_16_…` | Compatibilidad y guard |
|---|---|---|
| 1 | `01_governance_security_bindings.sql` | Añade contextos nullable a casos/factores/desafíos y v2 a trust evidence; no backfill ni convierte evidencia legacy en confiable. FKs a perfil/membresía/cuenta y CHECKs v2. |
| 2 | `02_physician_account_binding.sql` | Dos tablas de admisión/binding con FKs a VID01/CRD02/staff/Identity y unicidad/generados. Sin seeds ni owners. |
| 3 | `03_physician_evidence_foundation.sql` | Teléfono, solicitudes y archivos privados; dos triggers inmutables y ampliación del CHECK de operaciones de caso. |
| 4 | `04_physician_authority_issuer.sql` | Guard previo exige ambas autoridades 02 vacías; amplía sus referencias/índices, añade workflow e historia, ocho triggers y operación de cambio. No adoptar filas previas. |

Secuencia para la próxima autorización, no comandos de ejecución live:

1. Congelar fuente revisada y ventana de cambios. Repetir inspector contra target exacto, snapshot datos/DDL/rutinas/triggers/audit/membresías/sesiones/configuración y checksum/journals OP-ACT02A y QA05/OP02/UI03. Denegar si falta un objeto anterior, hay drift, columnas/tablas C4 parciales, datos en autoridades, server/principal distinto o cambio concurrente. Verificar tipos/collation/índices de FKs y MySQL/CHECKs enforced; no deshabilitar `foreign_key_checks` para aplicar C4.
2. Crear backup nuevo consistente de **todo** `mxmed`, incluyendo rutinas/triggers/eventos/binarios; fuera de Git/web, 0700/0600, SHA-256 y CRC, sin password en argv. Mantener copia protegida y política de acceso/retención del backup. Restaurar a un esquema propio, con usuario temporal aislado; transformar sólo namespaces/definers DDL, jamás INSERT de datos. Comparar todas las cuentas/checksums/DDL, índices/FKs/CHECKs/params, 11 rutinas, 35 triggers y audit chains. Cero huérfanos. Backup antiguo de 113 tablas anterior a OP-ACT02A no sustituye éste de 128.
3. Ensayar 01→04 en esa restauración; guardar metadata y journal después de cada archivo. Comparar todas las filas anteriores y objetos no incluidos en el cambio, CHECKs/FKs/generados/unicidad y triggers esperados. El único efecto esperado en cuatro tablas OP-ACT02A es la ampliación 01 y CHECK de operaciones; las otras once conservan DDL. Borrar únicamente base/usuario del ensayo, guardar evidencia privada.
4. **Nueva autorización explícita de DDL**, no receipt emitido ahora. Ejecutor live aún por implementar/revisar: denegar por defecto; bind a server/schema/principal, HEAD/source/hash de cuatro SQL, pre/post fingerprints y backup restaurado; journal privado por statement con estado anterior/intento/postDDL/error. Se requiere privilegio DDL de deployment para triggers con binlog habilitado; no conceder SUPER al runtime ni cambiar `log_bin_trust_function_creators`, binlog o política global. Separar deployment principal de app restringida. No llevar credenciales de deployment a PHP web.
5. Con C4 todavía inactivo, repetir preflight bajo ventana controlada; aplicar exclusivamente esos cuatro artefactos y postvalidar contra esquema ensayado. MySQL hace commits implícitos: un `ROLLBACK` no revierte el paquete. Evitar nuevas escrituras hasta cerrar gates. No reejecutar scripts completos tras error.
6. DDL parcial: mantener runtime nulo/deshabilitado; preservar journal/SQL error y SHOW CREATE. Clasificar statements efectivamente aplicados, incluido el intervalo DROP/ADD CHECK; comparar con el checkpoint del ensayo. Si hay datos/actividad nueva, detener recuperación. Elegir bajo autorización entre continuación exacta de statements ausentes y restore completo probado en una ventana sin nuevas escrituras. No borrar tablas compartidas, evidencia o journals; no downgrade/down automático ni recuperación “vacía” OP-ACT02A. Si no puede demostrarse checkpoint, repetir restore y ensayo propios para preparar el plan, no improvisar live.
7. Después de DDL aceptado, emitir **otra** aprobación operativa para secretos, staff, composición y piloto; DDL por sí solo deja `FUNCTIONAL_TEST_READY=false`. Un revert de Git no desinstala DDL ni borra evidencia; apagar la composición revoca disponibilidad, no historia.

## Staff legítimo: prerrequisito distinto del Director médico

No hay staff elegible actualmente. [FirstDirectorBootstrapService](../../modules/identity/services/FirstDirectorBootstrapService.php) exige cuenta activa/email verificado/credencial/sesión/reauth propias y **ninguna membresía médica**; rechaza la cuenta colaboradora existente. El primer Director interno requiere ceremonia CLI canónica, evidencia externa v2 Ed25519 de los ratificadores independientes exigidos por el contrato y approval de deployment exacto con target/operación/caso/origen/vigencia/source/schema. No se genera ni acepta aquí evidencia, ratificador o receipt. No inferir que quien dirige este trabajo es ya una autoridad `internal_staff.DIRECTOR`.

Con gobierno interno legítimo establecido, [InternalGovernanceService::execute](../../modules/identity/services/InternalGovernanceService.php) es el único escritor existente para `register`, `appoint_master` y `grant`. Un Director interno puede provisionar personal y la capacidad **operativa y delegable** `physician_accreditation_review`; un Master sólo gestiona ADVISOR si posee `internal_advisors_manage` y la delegación exacta. No se reutilizan permisos de medios, owner, soporte ni aprobación de ownership. Ese servicio es backend-only: el HTTP de gobierno actual no expone register/grant; no se inventa una URL administrativa ni se llama con cookies prestadas. Su invocación operativa necesita un wrapper/ceremonia guardados con target/scope aprobados, sesión propia y auditoría, aún no preparados como ejecutor live en esta entrega.

**Dependencia importante:** aunque la revisión admite staff con capacidad exacta, `StaffMfaService::assertEnrollment` exige que sujeto y registrador independiente sean DIRECTOR/MASTER_ADMIN activos, con sesiones/reauth/credenciales vigentes. Un ADVISOR con grant no puede enrolarse por la ceremonia actual. Para un revisor legítimo se necesita una persona con clase elegible y capacidad explícita, más otra persona elegible como registrador de enrolamiento; **no dos revisores por expediente**. Si se desea que el revisor sea ADVISOR de mínimo privilegio, hace falta decisión y cambio acotado de esa política con QA, no elevarlo silenciosamente.

`scripts/governance-ceremony.php` admite `mfa_begin/mfa_activate/mfa_cancel` y `bootstrap`, requiere habilitación/approval temporal y archivos privados. `mfa_begin` exige registrador distinto incluido en `mfa_registrar_account_ids`; entrega secreto sólo en archivo privado exclusivo, nunca en Codex/stdout. Habilitar esa ceremonia para una operación no requiere habilitar Operadores ni permitir ownership HTTP. El Director debe nominar cuentas internas separadas y ratificadores/custodios legítimos; su alta/login/reauth/MFA serán manuales y autorizados en otra fase.

## Composición mínima propuesta — no conectada

Las tres entradas originales siguen creando `PhysicianOnboardingHttp($identity)` sin servicios. No se añade una página, login, dashboard ni variable que por sí sola active C4. El manifiesto tiene `runtime_enabled=false` y ningún consumidor de producto.

Después de D01–D05 y del ejecutor/guard de activación revisados: un único factory compartido por `/public/identity/perfil-medico.php`, `/api/identity/physician-onboarding.php` y la sección de `/internal/media-review/index.php`. Obtener `IdentityHttpComposition` ya existente; comprobar autorización C4 privada/temporal vinculada a source, target, post-schema, cohort exacto y políticas. Denegar ante ausencia/revocación/drift y conservar respuesta pendiente/indisponible sin construir writers. No usar una autorización de bootstrap/owner como permiso general de intake.

Con todos los gates válidos: mismo PDO → `GovernanceSecurityContext` → `EvidenceCipher` (keyring y policy aprobados) → `LocalPersistentPrivateMediaStorage` fuera del árbol/web, 0700/0600/no symlinks → `StaffMfaService` existente y separado → `PhysicianAdmissionComposition`; con esa misma autoridad/cipher/audit y proveedor SMS real, `PhoneVerificationService`; finalmente `PhysicianOnboardingHttp(identity, services, phone)`. No construir otro emisor, verifier ni SessionStore. Reutilizar los scopes, CSRF y `EvidenceHttpBoundary` existentes. El allowlist del piloto debe validarse desde actor canónico y perfil exactos **en cada operación**, no sólo en la página o en un flag del navegador; requiere fuente adicional del guard/factory.

El HMAC de transporte debe provisionarse como el mismo secreto privado de proxy y PHP en reinicio controlado posterior, bound al origen `https://localhost:8443`; sin él las mutaciones permanecen denegadas. No TLS nuevo ni exposición de red. Preparar permisos SQL mínimos sobre tablas/rutinas canónicas para el principal runtime, comprobar denegación cross-schema y revalidar fingerprints con ese principal. El actual usuario root de desarrollo no se incorpora como credencial recomendada al paquete.

Nunca inyectar `services->eligibility` a la composición de ownership en esta activación; ese handoff es otra autorización. Se conservan el deny de firma clínica y runtime Operadores deshabilitado. Los escenarios perfil nuevo, conflicto/transferencia y resolución de un perfil ocupado siguen indisponibles.

## Gates Safari separados

| Gate | Estado actual y URLs/acciones |
|---|---|
| A — `UI_ONLY_READY` | **Sí para navegación/estado pendiente originales; no QA física Safari nueva.** `https://localhost:8443/public/identity/perfil-medico.php`: con sesión propia ya abierta, consultar pasos, expandir explicación, volver a la aplicación/Información profesional y usar Actualizar estado. Sin sesión sólo ver login. No enviar registro, OTP ni documentos. `https://localhost:8443/private-app.php`: navegar por la tarjeta de acreditación sin editar datos. |
| A — staff | `https://localhost:8443/internal/media-review/index.php?section=acreditacion`: sin sesión redirección y sin capacidad 403 esperados. No hay reviewer real, por lo que la bandeja autorizada no está disponible en Safari normal-local. No promover al Director para abrirla. |
| B — `FUNCTIONAL_TEST_READY` | **No.** Mismas rutas, sólo después de esquema aceptado, proveedor/credenciales/entrega, custodia/retención/recuperación, guard/composición y staff/MFA legítimos, más QA de seguridad sobre el runtime normal-local autorizado. Los seis recorridos Chromium/WebKit desechables de UI01 no satisfacen este gate. |

El piloto funcional posterior debe delimitar cuentas/perfiles exactos, teléfonos/documentos permitidos, disposición y autorización explícita de sus mutaciones. Primero verificar lectura/denegaciones, luego OTP/upload/revisión/confirmación/emisión sólo dentro de ese alcance. Owner/Operadores/firma siguen fuera. No se invita todavía al Director a probar el recorrido real.

## QA y siguiente autorización

Esta fase prueba el inspector y sus denegaciones sin mutaciones; verifica backup actual y esquema en base propia. No repite suites funcionales de fundamentos ni tests destructivos persistentes. Se preservan snapshots completos de `mxmed`, payloads de sesiones, configuración, archivos JSON de journals OP-ACT02A y checksums QA05/OP02/UI03 antes/después. El test histórico `PhysicianCredentialAuditPolicyTest.php` sigue esperando 52 eventos frente a 54: deuda previa documentada, sin relajarlo ni declarar suite global verde.

**Preservación final PASS:** snapshots normales idénticos, sesiones/configuración/journals sin cambios y QA05/OP02/UI03 iguales. Nueve comprobaciones del inspector incluyen repetición exacta, drift, target persistente incorrecto, ausencia/duplicado de argumentos, rechazo de modo apply, symlink y archivo legible por otros, más coherencia del manifiesto. HTTPS se comprueba con la CA local existente, sin omitir verificación TLS; JS/CSS servidos coinciden con fuente UI01 y continúan sin cambios. No se usa una sesión real ni se entra a Safari por el Director.

**Ensayo físico actual PASS:** backup completo de 128 tablas / 11 rutinas / 35 triggers, SHA-256 `585fcedf5f61d3e488f187c01b19f6b78fb198df125c0c8cbc11ec0db3698caa`, CRC gzip y snapshot de fuente idéntico. Restore compara todas las filas/checksums/DDL y metadata/audit; sólo cambia namespace/definer del ensayo y se normaliza el delimitador final de tres triggers de una sentencia. 01, 02, 03 y 04 pasan secuencialmente sobre ese restore; resultado 135 tablas / 45 triggers, cero huérfanos en todas las FKs y datos anteriores iguales. Se conservan definiciones OP-ACT02A no incluidas en los ALTER previstos. Principal temporal sin acceso SELECT/UPDATE/CALL a `mxmed`; base y usuario eliminados. No se ejecuta ningún servicio de aplicación, SMS, admisión, revisión o firma durante este ensayo. El backup no es un permiso para aplicar live y deberá renovarse si cambia el target.

Evidencia privada: `~/Library/Application Support/MXMED/c4-act01/20261016-review/`; backups y snapshots contienen datos privados y no van a Git. El primer restore restringido encontró MySQL 1419 (binlog/SUPER); se eliminó su base/usuario y se corrigió el ensayo usando el administrador de deployment ya existente sólo en el esquema propio, con definers restringidos y sin cambios globales. El runtime no recibe privilegios adicionales.

**Decisiones requeridas:** D01 proveedor/API SMS; D02 custodios y Keychain/vault/recuperación; D03 plazos/holds/disposal y responsables; D04 nominaciones internas legítimas, registrador y política de clase MFA; D05 alcance del piloto y autorizaciones separadas de fuente, DDL y operación. El manifiesto deja todos los campos sin aprobar.

**Próximo trabajo autorizado que se solicita:** seleccionar D01–D05 y autorizar sólo la fuente faltante (adaptador SMS, factory/guard y wrapper gobernado de provisión), con tests de transporte sin SMS y QA propia; después autorizar DDL exacto y ceremonias/secretos concretos en fases separadas. Esta preparación, su commit y push no aprueban ninguna de esas acciones. No se necesitan más auditorías generales para cerrar los hechos ya verificados.
