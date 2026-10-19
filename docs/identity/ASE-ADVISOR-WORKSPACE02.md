# ASE-ADVISOR-WORKSPACE02 — solicitudes de perfil e historial en el workspace médico

`RESULT=COMPLETE_ISOLATED_SYNTHETIC`. Implementación y verificación dirigidas en infraestructura owned con datos ficticios; sin activación normal-local/productiva ni aceptación visual del Director. Los resultados corresponden a WORKSPACE02 y no se heredan de R2.

## Decisión y baseline

Baseline verificado `0dbccc946476f7f5e8e4de000e7f186913069a80`, feature previa `feature/ase-advisor-workspace01-r2-top-tabs`. El trabajo continúa en `feature/ase-advisor-workspace02-profile-requests`, worktree `mxmed-ase-advisor-workspace02-profile-requests`, preservando los checkouts/runtimes anteriores.

**PRODUCT_POLICY:** el Director prioriza el primer recorrido operativo de reclamo FREE y solicitud de perfil nuevo. Autoriza diseño de flujo, controles contextuales, delegación mínima y materialización específica de la aplicación aprobada. Sustituye la prioridad de conectar todos los dominios de edición; no autoriza un creador general de perfiles ni traslados de propietario. El flujo histórico de reclamos es contexto de producto, no evidencia de implementación ni autorización de nuevos requisitos de notificaciones/consentimiento.

**SOURCE_IMPLEMENTATION:** se reutilizan origen/intake cifrado PUB-ENR02, documentos/versiones C4, Identity, grants, MFA, auditoría canónica, writer de perfiles y ownership R3. Se añade una resolución tipada de solicitud, separada de la resolución de acreditación PUB-ENR03. Ambas conservan su significado y sus permisos.

**CONNECTIVITY:** Solicitudes e Historial comparten médico/aplicación con Resumen e Imágenes. La continuación del solicitante usa la ruta Identity existente `/public/identity/perfil-medico.php?application=<id>` y su propia sesión. La composición de este ensayo sólo existe en el router owned; la ruta normal no obtiene servicios mediante flags ni abre el runtime operacional.

**VERIFICATION:** principal 51 PASS (incluye 37 del navegador), ownership 23 PASS, seguridad/concurrencia 20 PASS y complemento UI 10 PASS. Conteo contextual: 3 PASS adicionales, sin mutaciones de negocio. Evidencia y límites por ejecutor abajo; no equivale a aceptación visual ni activación.

**OPERATIONAL_STATUS:** `PRODUCTION_ACTIVATION=false`; sin merge oficial, documentos/personas reales, notificaciones externas ni cambios al runtime original/8091. `DIRECTOR_VISUAL_ACCEPTANCE=PENDING`.

## Fuentes y brechas resueltas

| Área | Fuente anterior real | Integración acotada |
|---|---|---|
| Solicitud pública | [PublicEnrollmentService](../../modules/identity/Accreditation/PublicEnrollmentService.php) fija origen CLAIM/JOIN, cuenta legítima, intake cifrado/versionado e inmutable | [RequestService](../../modules/identity/PhysicianRequests/RequestService.php) lee la misma aplicación y añade decisiones append-only; no crea una segunda cola de staff admission |
| Resolución de acreditación | [ReviewedEnrollmentService](../../modules/identity/Accreditation/ReviewedEnrollmentService.php) requería fuentes verificadas y se detenía en `PENDING_NEW_PROFILE` sin perfil | Permanece independiente. [MaterializedApplication](../../modules/identity/PhysicianRequests/MaterializedApplication.php) reconoce exclusivamente el draft nuevo creado por una solicitud aprobada y ligada, para continuar fuentes/C4 sin publicarlo |
| Documentos | [PhysicianEvidenceService](../../modules/identity/Accreditation/PhysicianEvidenceService.php), EvidenceCipher y almacenamiento privado existente | Vínculo explícito por el solicitante a evidencia C4 del mismo tipo/cuenta/perfil; reader de documentos con permiso independiente y recibos por actor/sesión/versión/hash. No se copia el documento a otra biblioteca |
| Perfil nuevo | [PrivateProfileRepository::upsertIdentity](../../modules/profiles/repositories/PrivateProfileRepository.php) ya contiene creación de `profiles_doctors` | Materialización únicamente dentro de la aprobación nueva: ID derivado de aplicación, nombre/intake declarado, `hidden`, no candidato público, plan FREE y linkage inmutable. No habilita `profile_create` |
| Ownership | [ProfileMembershipRoleChangeService](../../modules/identity/services/ProfileMembershipRoleChangeService.php) exige C4, aprobación gobernada y actor solicitante con step-up | [RequestOwnershipService](../../modules/identity/PhysicianRequests/RequestOwnershipService.php) compone los mismos casos/approvals/proofs/writer con [autoridad exacta por solicitud](../../modules/identity/PhysicianRequests/RequestOwnershipApproverAuthority.php); ningún Advisor recibe membresía médica como atajo |
| Auditoría/historia | `platform_audit_events`, eventos Media y ownership existentes | [RequestAudit](../../modules/identity/PhysicianRequests/RequestAudit.php) agrega un evento de solicitud; [HistoryReader](../../modules/identity/PhysicianRequests/HistoryReader.php) y Team proyectan las mismas filas, con [etiquetas compartidas](../../modules/identity/PhysicianRequests/HistoryLabels.php) |

No se importan requisitos nuevos del documento histórico. El conjunto documental empleado es el conjunto ya definido por C4: identificación y documento profesional; el envío o su lectura no verifican automáticamente credenciales profesionales. Las fuentes profesionales, teléfono, revisión C4, confirmación y vínculo conservan su ciclo y autoridad propios.

## Delegación y límites de lectura

Los cuatro grants son individuales, administrados desde Equipo por el writer de gobierno existente; un grupo visual no concede autoridad. La clase ADVISOR, DIRECTOR o MASTER no sustituye el grant. El uso requiere cuenta verificada/activa, credencial y sesión vigentes, staff activo y ausencia de autorrevisión. Master conserva además su contrato independiente para administrar/delegar capabilities; no recibe estas facultades por un selector de UI.

| Capability | Autoridad operativa | No concede |
|---|---|---|
| `physician_request_read` | Aplicaciones enviadas accesibles, contexto mínimo, decisiones/información e historial autorizado | Documento binario, decisión, acreditación o ownership general |
| `physician_request_evidence_read` | Documentos del vínculo exacto aplicación–evidencia; requiere también read | Aprobar la solicitud o verificar profesionalmente la identidad |
| `physician_claim_resolve` | Aprobar/rechazar/pedir información para CLAIM; caso y MFA `PHYSICIAN_CLAIM_RESOLVE` | Transferir owner existente, resolver cualquier perfil o C4 |
| `physician_new_profile_resolve` | Resolver solicitud nueva y materializar a lo sumo su draft; caso y MFA `PHYSICIAN_NEW_PROFILE_RESOLVE` | Creación general, publicación, planes o factura |

Aprobar también requiere acceso a evidencia, apertura efectiva de los documentos vigentes y confirmación explícita. El permiso se comprueba al leer, abrir caso, verificar/consumir MFA y decidir. La autoridad de ownership queda ligada a resolución, aplicación, tipo, intake, cuenta solicitante, perfil y revisor original; el adapter global Director/Master permanece con sus restricciones anteriores. Revocar el grant invalida nuevas decisiones y la autoridad pendiente de ownership.

Los seis grants Media siguen separados; `media_review_read` no concede ningún acceso o decisión de solicitudes. `physician_accreditation_review` y `professional_source_provision` conservan alcance distinto. Correcciones públicas reservadas, admisión staff, clínica, facturación, comisiones, cartera y las cuatro capabilities genéricas sin consumidor no se habilitan.

## Experiencia unificada y estados reales

Encabezado staff/logout, búsqueda, pendientes, contexto médico y pestañas horizontales permanecen. Al elegir médico/solicitud se contrae el selector, conservando retorno/búsqueda; no quedan directorio e inbox grandes compitiendo con el trabajo. Las pestañas disponibles incluyen Resumen, Solicitudes, Imágenes e Historial según las fuentes y permisos efectivos. No hay otra aplicación, iframe administrativo ni ventana obligatoria para resolver.

Una aplicación sin médico mantiene `doctor_id=null` y el contexto **Solicitud de perfil nuevo**. La UI distingue cuenta solicitante, información declarada y perfil existente; no vincula personas por nombres. CLAIM compara los datos; no sobrescribe el perfil. En nuevas aplicaciones, `ProfessionalMatchCandidates` aporta coincidencias autorizadas y exige resolución explícita; no fusiona ni reasigna por igualdad de nombre.

Acciones independientes: **Aprobar reclamo / Aprobar perfil nuevo**, **Solicitar información**, **Rechazar solicitud**. Motivo, confirmación, contraseña y TOTP aparecen en el límite de la acción elegida. El visor protegido mantiene la solicitud seleccionada. Historial es sólo lectura.

| Estado persistido de solicitud | Interpretación/efecto |
|---|---|
| Sin decisión: `PENDING_REVIEW` proyectado | Intake canónico enviado, aún sin decisión de solicitud |
| `NEEDS_INFORMATION` | Falta información; una respuesta cifrada/versionada se asocia a esa decisión, sin reescribir el intake ni aprobar automáticamente |
| `REJECTED` | Terminal para esta solicitud; conserva cuenta/perfil/plan/contenido e historia; no crea perfil nuevo |
| `APPROVED` + `OWNERSHIP_PENDING` | Solicitud aceptada; en nueva existe su draft oculto. Falta la continuación profesional y/o aceptación personal del solicitante |
| `APPROVED` + `OWNERSHIP_ACTIVE` | La membresía owner real del solicitante está activa; la UI verifica el resultado persistido, no un banner |

La respuesta perdida se resuelve por `case_id` y lookup autorizado: `COMMITTED`, `NOT_COMMITTED` u `OUTCOME_PENDING` son resultados de la consulta, no nuevos estados de negocio. No se reintenta automáticamente una mutación de resultado incierto.

## Dos recorridos y activación separada

**CLAIM:** origen/intake/solicitante canónicos → vínculo documental exacto → abrir desde Pendientes → revisar documentos → reauth/MFA y decisión. El commit revalida perfil existente, elegibilidad FREE pública, ausencia de owner, vínculos/revisiones incompatibles de otra cuenta, decisiones competidoras, fuentes profesionales vigentes, evidencia y grants. La aprobación conserva ID, campos, origen y referencias del perfil. El rechazo/petición de información no alteran perfil ni cuenta.

**Nuevo:** origen JOIN con target null → revisión/documentos → comprobación de coincidencias → reauth/MFA y aprobación → writer existente crea un solo `profiles_doctors` oculto, FREE y no candidato público dentro de la misma transacción → relación aplicación/perfil/resolución inmutable. La ausencia de fuentes verificadas no se simula como acreditación: el draft continúa después por fuentes/PUB-ENR03/C4 independientes. Una coincidencia bloquea materialización y queda visible, sin rechazo automático por nombre.

**Ownership en ambos:** C4 y `ProfessionalAccountBindingProjection` deben acreditar elegibilidad real → el solicitante auténtico prepara su primera membresía legítima mediante `AccountMembershipRepository` → revisor original con grant actual abre/autoriza el caso R3 exacto con contraseña/MFA → solicitante independiente acepta con contraseña fresca → el writer canónico consume approval/step-up y cambia su membresía a owner. Todo ello mantiene su auditoría y checks existentes. Ni el Advisor ni el Director pueden aceptar haciéndose pasar por el solicitante. Un caso expirado conserva su historia y sólo puede continuarse mediante recuperación explícita autorizada; no se borra ni se reusa un proof consumido.

El resultado de solicitud, materialización, C4, ownership, publicación y plan se muestra por separado. Aprobar aquí nunca activa publicación, pago, suscripción, firma clínica ni admisión staff. La eventual membresía de colaborador es **del solicitante legítimo**, en la transición canónica a owner; no pertenece al revisor interno.

## Consistencia, seguridad y auditoría

Los comandos se ligan a IDs exactos de aplicación/solicitante/perfil, intake/revisión y hash de facts. Éste incluye cuenta/credencial, perfil/plan, estado/versión documental, archivos/hashes/vigencia, fuentes profesionales actuales, coincidencias, decisiones competidoras, vínculos/revisiones ajenos y respuestas. Documento cambiado, source cambiado, cuenta/grant revocado o estado concurrente requieren lectura nueva.

La decisión consume proof y añade ledger/evento en un único PDO/transacción; materialización nueva participa de esa misma unidad. UNIQUE de versión/caso/proof y linkage, lock de materialización y locks de recursos impiden repetir el resultado de esta integración. Un fallo de audit revierte la mutación; no hay SQL de compensación que borre un perfil después de un resultado incierto. La confirmación repetida del mismo comando obtiene el receipt original; otro payload para el caso entra en conflicto.

La implementación adquiere y comprueba el lock `profile_materialization` como primera instrucción del callback de decisión, antes de las lecturas consistentes de sesión/facts. Es una corrección defensiva de orden bajo `REPEATABLE READ`: esperar un mutex no renueva un snapshot ya creado. La cuenta del actor conserva su validación bloqueante inicial; la ausencia del lock canónico falla cerrado. La prueba dirigida usa **dos aplicaciones nuevas diferentes, dos revisores y conexiones PDO independientes con la misma cédula**, además del doble envío de una sola aplicación. No se reprodujo una duplicación en el ensayo previo al ajuste; el ensayo de la fuente final acredita un solo perfil/una resolución aprobada y conflicto que requiere consulta nueva del perdedor. Este control se limita a los writers de esta integración, sin atribuirle serialización global de todos los módulos. **Verificación final: PASS**, con conflicto del perdedor en vez de un duplicado.

El evento `PHYSICIAN_PROFILE_REQUEST_CHANGED` sólo guarda operación, versión, antes/después, referencias, tipo y capability; motivos libres y respuestas permanecen cifrados en la solicitud protegida. Los eventos registran actor real, rol/contexto del servidor y fecha UTC. La activación owner conserva `PROFILE_OWNERSHIP_ASSIGNED`; acciones de imagen conservan sus eventos Media. La vista del médico/aplicación y la de actividad Advisor del Director leen los mismos `event_id`, sin escribir duplicados.

Abrir formularios o navegar no se representa como modificación de negocio. Lecturas de evidencia/desafíos pueden producir audit de seguridad, excluido del historial de cambios. Las denegaciones autenticadas se registran como `SENSITIVE_ADMIN_ACTION / DENIED / POLICY_DENIED`, sin documentos, secretos ni texto aportado; nunca aparecen como éxito. Historial limita scope por permisos y enlaces canónicos y no anuncia cobertura universal de módulos no integrados.

Sesión opaca, TLS firmado owned, Origin/Fetch-Site, CSRF, campos exactos y rechazo de JSON duplicado se preservan. El cliente no proporciona autoridad. Borradores y respuestas asíncronas se ligan al contexto; un resultado tardío de A no debe poblar B. El contador incluye solicitudes no resueltas accesibles con read. Revocar read retira esa lectura y su contador; revocar sólo la resolución retira las acciones y conserva la consulta autorizada. El backend rechaza el comando sin permiso incluso antes de refrescar la UI. El indicador del encabezado contextual y Resumen suman pendientes Media y solicitudes accesibles del mismo contexto; cada request_id se cuenta una vez. Aurora muestra 4 (3 Media y 1 claim); un perfil nuevo ya completado muestra 0, sin pendiente residual.

## Datos y migración

[2026_10_20_01_physician_profile_requests.sql](../../modules/identity/db/migrations/2026_10_20_01_physician_profile_requests.sql) añade siete objetos:

- `auth_physician_profile_request_evidence`: vínculo explícito e inmutable a evidencia existente.
- `auth_physician_profile_request_decisions`: ledger append-only, reason cifrado, versión/caso/proof/receipt/audit.
- `auth_physician_profile_request_profiles`: materialización única y enlace autoritativo aplicación → perfil.
- `auth_physician_profile_request_reads`: recibos documentales por actor/sesión/versión/hash.
- `auth_physician_profile_request_responses`: respuestas cifradas ligadas a decisión needs-information.
- `auth_physician_profile_request_ownership`: vínculos históricos de casos R3, sin borrar intentos anteriores.
- `auth_physician_profile_request_locks`: serialización de materialización de esta integración.

El CHECK de operaciones mantiene las 18 operaciones históricas staff/Master y añade únicamente las dos decisiones tipadas. El CHECK de endorsements admite las dos capabilities nuevas sólo consumidas por el adapter de solicitud exacto; el consumidor global no las interpreta como permiso universal. Migración aplicada y recorrida en la DB owned nueva del [ensayo principal](../evidence/ase-advisor-workspace02/qa.json) y de [ownership](../evidence/ase-advisor-workspace02/ownership-browser.json); seguridad final 20 PASS. No describe schema oficial instalado ni autoriza DDL real.

## Verificación y preview

**Cierre dirigido comprobado:** principal 51 PASS, incluyendo 37 checks del navegador; ownership 23 PASS, seguridad/concurrencia 20 PASS y complemento UI 10 PASS; smoke separado de conteo contextual 3 PASS. Chromium y WebKit pasan en 1440×900, 1366×768 y 390×844; ventana nativa observada 1366×783, outerHeight=availableHeight=870, sin overflow horizontal ni errores JS. WebKit automatizado no acredita Safari físico: `SAFARI_PHYSICAL=NOT_RUN`. `DIRECTOR_VISUAL_ACCEPTANCE=PENDING`.

El [manifiesto final](../evidence/ase-advisor-workspace02/final-verification.json) comprueba los resultados separados, el hash de RequestService, sintaxis PHP/JS/Python y preservación de los 25 worktrees/listeners originales. La evidencia se separa por efecto observado, sin sumar suites históricas ni trasladar resultados de una instancia eliminada al preview nuevo.

| Cobertura dirigida | Ejecutor | Cierre de evidencia |
|---|---|---|
| Director/grants, decisiones, contexto, documentos, Media, respuesta perdida, Chromium/WebKit y geometría nativa | `scripts/ase-advisor-workspace02-qa.py` / `-browser.py` | [Ensayo principal](../evidence/ase-advisor-workspace02/qa.json): 51 checks PASS, que incluyen los 37 del [navegador](../evidence/ase-advisor-workspace02/browser-result.json); seis combinaciones motor/tamaño PASS en el entorno conservado. Suite principal completa; cobertura complementaria separada en las filas siguientes |
| C4 real, membresía propia, autorización exacta, aceptación owner y mismo evento médico/Team | `scripts/ase-advisor-workspace02-fixture.php accredit` / `-ownership-browser.py` | [23 checks PASS](../evidence/ase-advisor-workspace02/ownership-browser.json), ambos owners reales del solicitante; ACK perdido de activación nueva resuelto por lookup, sin reintento |
| Recuperación owner append-only | `scripts/ase-advisor-workspace02-ownership-expiry.php` | [PASS](../evidence/ase-advisor-workspace02/ownership-expiry.json): caso anterior revocado/enlace retenido, nuevo caso con TTL real de 1800 s. Clock inyectado sólo en observación de vencimiento; no modifica reloj/TTL del sistema |
| Versiones/fuentes/grants, auditoría atómica, actor/caso indebido y carreras independientes | `scripts/ase-advisor-workspace02-security.py` | [20 checks PASS](../evidence/ase-advisor-workspace02/security-result.json), suite completa y revisor secundario incluidos; RequestService SHA-256 `ea125a3d3e7ea8f3eb8be7777783f3e97080dc786bfe9c25a73884368b5b119d` coincide con la fuente final |
| Media real e historial compartido, fallo aislado de solicitudes y ventana nativa | `scripts/ase-advisor-workspace02-ui-supplement.py` | [10 checks PASS](../evidence/ase-advisor-workspace02/ui-supplement.json): writer Media real, mismo event_id médico/Team, solicitudes 503 no bloquean Media, viewport nativo 1366×783; sin errores JS |
| Conteo contextual compartido con Resumen, duplicación de lectura y nuevo perfil completado | Smoke Playwright de lectura | [3 checks PASS](../evidence/ase-advisor-workspace02/count-smoke.json): 3 Media + 1 claim = 4, request_id duplicado contado una vez y perfil nuevo completado = 0; cero errores JS y cero mutaciones de negocio |

Los solicitantes se autentican por Identity y preparan/aceptan su vínculo desde la interfaz existente. Las pruebas de propiedad no insertan filas owner como resultado positivo. El caso de conflicto con owner previo es un fixture negativo separado. Los formularios no aplicables deben estar realmente ocultos: la respuesta ya enviada no admite otra edición para la misma resolución y la titularidad consumida no muestra otra aceptación.

**Preview conservado:** `HEALTHY_OWNED_SYNTHETIC`, sólo loopback HTTPS; vencimiento **19 de octubre de 2026, 23:01:05 (America/Mexico_City, UTC−06:00)**, sin extensión. [Director/Equipo](https://127.0.0.1:55846/internal/media-review/index.php?section=equipo) y [workspace Advisor](https://127.0.0.1:55846/internal/staff-workspace/index.php) usan cuentas y sesiones sintéticas independientes. QA dirigida completa; ambas ventanas Chromium abiertas y verificadas con login legítimo independiente y el conteo contextual final. El manifiesto final referencia `preview-delivery.json` y sus capturas de entrega; el launcher permite reabrirlas durante la vigencia del entorno. La aceptación del Director sigue pendiente.

Desde el worktree de esta feature:

```sh
python3 scripts/ase-advisor-workspace02-review.py /tmp/mxmed-ase-adm02imp01-ud72egah status
python3 scripts/ase-advisor-workspace02-review.py /tmp/mxmed-ase-adm02imp01-ud72egah open
python3 scripts/ase-advisor-workspace02-review.py /tmp/mxmed-ase-adm02imp01-ud72egah stop
```

El launcher verifica ownership, permisos, fuente/HEAD, listener y vencimiento; el stop solicita limpieza al runner propietario, sin borrar recursos ni matar procesos arbitrarios. El login pasa por Identity, no importa cookies. Las acciones `credentials-director`, `credentials-advisor`, `mfa-director` y `mfa-advisor` copian el dato sintético necesario al portapapeles local sin exponer claves privadas en documentación. No amplían TTL ni sustituyen MFA.

Se conservan claim y aplicación nueva pendientes, ambas resoluciones owner completas con historial y documentos exclusivamente ficticios. En **Pendientes**, abrir **Aurora Ejemplo del Valle** para el claim o **Persona Ficticia New_ready** para la aplicación nueva. Para ver un resultado completado, abrir **Buscar médico o solicitud**, buscar `Clara Ficticia Reclamo` o `New_approve`, seleccionar el resultado y abrir **Historial**; los resueltos no se presentan como pendientes. El límite de cuenta R2 permanece: `circulodigital@msn.com` no existía en la DB owned previamente inspeccionada; no se copian credenciales ni se accede a datos reales. No existe selector que mezcle roles. [Solicitante claim](../evidence/ase-advisor-workspace02/screens/claim_approve-ownership-applicant.png), [solicitante nuevo](../evidence/ase-advisor-workspace02/screens/new_approve-ownership-applicant.png) y [respuesta móvil](../evidence/ase-advisor-workspace02/screens/information-response-applicant-mobile.png) muestran estados realmente persistidos, con formularios no aplicables ocultos.

## CTX-SYNC01

```ini
CONTEXT_BASELINE=WORKSPACE01-R2 0dbccc946476f7f5e8e4de000e7f186913069a80
AFFECTED_CAPABILITY_IDS=ID-02,ID-03,ID-04,ID-05,ID-06,ID-07,PUB-04,PUB-05,MED-01,MED-02,MED-03,COM-10,PLT-01
AFFECTED_JOURNEY_IDS=J01,J02,J09
AFFECTED_DATA_OBJECTS=Identity/sesiones/grants/MFA; enrollment origin/intake; evidencia C4/fuentes/binding; siete objetos de solicitud; profiles_doctors; ownership cases/approvals/memberships; platform_audit_events y proyecciones Media/Team
AFFECTED_CONTEXT_FILES=estado actual; router; inventario funcional; interacciones; datos/migraciones; trazabilidad comercial/asesores; registro visual; contrato WORKSPACE02
CONTEXT_CHANGES_IMPLEMENTED=solicitudes e historial en contexto único; cuatro grants específicos; resolución/materialización separadas de C4 y ownership; aceptación propia y recuperación; historial canónico compartido; ausencia de activación real
CONTEXT_VERIFICATION=fuente final y writers revisados; principal51/ownership23/seguridad20/UI10 PASS; conteo contextual3 PASS separado; ocho documentos y 50 enlaces/anchors pertinentes verificados; diez campos CTX únicos; protección original verificada; Safari físico NOT_RUN
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en el commit aislado de implementación; hash informado en la entrega Git
REMAINING_CONTEXT_GAPS=aceptación visual Director PENDING; Safari físico NOT_RUN; cuenta real solicitada ausente en owned; dominios no integrados de R2 preservados; normal-local/producción inactivos
```
