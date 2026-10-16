# C4-EV01 — expediente protegido y confirmación del médico

Fecha: 2026-10-16. Rama `ux/consultation-step2-vitals-r1`; inicio local `df8ed30d4380ef7bc7b7fad87c46aa9ec24948a3`, limpio, un commit documental por delante de upstream/remoto `97db0129100a5c13c9df0d1cb49dc975ea4adb37`. Se conserva ese commit; no se reescribe historia.

```text
RESULT=PARTIAL_SOURCE_FOUNDATION_IMPLEMENTED
NORMAL_LOCAL_DDL_AUTHORIZED=false
REAL_EVIDENCE_INTAKE_ACTIVATED=false
REAL_SMS_DELIVERY_READY=false
ADMISSION_BINDING_ISSUER_ACTIVATED=false
INITIAL_OWNER_RUNTIME_CONNECTION_CHANGED=false
CLINICAL_SIGNING_ACTIVE=false
```

La falta previa del expediente es el objetivo implementado, no una condición para detener esta entrega. Los componentes privados de fuente se ensayan con cuentas, documentos y revisores sintéticos. No se declara resuelta la operación real: faltan proveedor SMS, custodia/rotación/recuperación de claves y política humana de retención aprobados, despliegue y el emisor C4/ADM02 posterior. No hay otro onboarding, página o entorno de producto.

## Una fuente de evidencia, autoridades separadas

[PhysicianEvidenceService](../../modules/identity/Accreditation/PhysicianEvidenceService.php) mantiene la solicitud, intake documental, revisión humana y confirmación. Reutiliza VID01/CRD02 mediante IDs y hashes canónicos de [ProfessionalAccountBindingProjection](../../modules/identity/repositories/ProfessionalAccountBindingProjection.php). No copia nombres, correos, cédulas o un maestro profesional. Los servicios VID01/CRD02 existentes siguen siendo sus emisores; se prueban también por sus servicios reales en aislamiento.

La [migración preparada 03](../../modules/identity/db/migrations/2026_10_16_03_physician_evidence_foundation.sql) añade sólo:

| Superficie | Autoridad |
|---|---|
| `auth_account_phone_verifications` | Contacto cifrado, versión exacta, desafío/intentados/envíos, aceptación de entrega y verificación actual de esa cuenta. |
| `auth_physician_evidence_requests` | Cuenta, origen inmutable, perfil/VID01/CRD02 y revisiones, versión/política, estado, revisión humana, proof MFA consumido, confirmación y referencias de auditoría. |
| `auth_physician_evidence_files` | Objetos privados inmutables por solicitud, clase documental, clave opaca, hash cifrado, key ID, referencia de retención y acceso/fechas examinados por el revisor. |

También extiende exclusivamente el CHECK de operaciones de `auth_governance_cases` con `PHYSICIAN_EVIDENCE_REVIEW`; [StaffMfaService](../../modules/identity/services/StaffMfaService.php) admite esa operación exacta. Se reutilizan casos, desafíos, factores, proofs y throttling existentes. No se crea otro sistema MFA, de membresías o admisión. `usesConnection` exige el mismo PDO para evidencia/MFA y teléfono/auditoría; una segunda conexión no sustituye la transacción compartida.

Orden del futuro ensayo/aplicación controlada: migración v2 `2026_10_16_01`, tablas de admisión/binding `2026_10_16_02`, luego `2026_10_16_03`. Las dos primeras permanecen byte por byte. **Ninguna se aplicó en `mxmed`** y no se repitieron los quince objetos OP-ACT02A. No hay seeds/backfill ni un down script destructivo; recuperación del ensayo consiste en eliminar/recrear exclusivamente su DB propia.

## Contacto canónico y control de cuenta

Se reutiliza sin cambios `RegistrationService` → `EmailVerificationService`; la prueba registra, acepta los consentimientos existentes, verifica el token por el servicio real y obtiene sesión Identity. No se fabrica un email verificado en ese flujo. Las cuentas staff adicionales son fixtures declarados del ensayo, no altas reales.

[PhoneVerificationService](../../modules/identity/Accreditation/PhoneVerificationService.php) reutiliza `Agenda\Contracts\OtpProviderPort` como puerto independiente del proveedor. Su default es `RejectingOtpProvider`. Un adaptador de email/SES no se convierte en SMS y un acknowledgement no verifica teléfono. En QA se usa sólo entrega sintética en memoria a números ficticios; nunca SES, SMS ni correo reales.

OTP de seis dígitos, diez minutos, cinco intentos, reenvío separado por sesenta segundos y máximo cinco emisiones por cuenta/hora. Los límites técnicos persisten al cambiar teléfono/desafío; no hay reset por nonce. La reserva y auditoría preceden a entrega; la aceptación del proveedor se confirma después en otra transacción. Un fallo/resultado ambiguo deniega verificación. Se exige reauth al emitir, y cuenta/contacto/versión/desafío/sesión exactos al consumir. El código se guarda como HMAC con dominio; el teléfono en AEAD. No se devuelve teléfono ni OTP. Reemplazar contacto invalida el proof anterior; verificar otro número no actualiza una revisión profesional ya ligada a la versión previa.

El expediente exige cuenta activa con correo verificado y teléfono canónico verificado antes del intake profesional. No interpreta un teléfono legacy, email, un flag de fixture o un perfil publicado como ese hecho. Esto no implementa todavía el router completo C4 ni cambia el login actual o una sesión real.

## Origen, matching y legado

Origen congelado en servicio y trigger SQL: `CLAIM_EXISTING_PROFILE`, `REQUEST_NEW_PROFILE`, `CONFLICT_OR_TRANSFER_REVIEW`. Cuenta, perfil original y fuentes/revisiones no pueden cambiar por PATCH o actualización directa; una corrección requiere otro expediente y cierre gobernado del anterior. Se permite revocar un draft por el mismo flujo staff/MFA; no se borra historia.

[ProfileRequestMatcher](../../modules/identity/Accreditation/ProfileRequestMatcher.php) sólo ofrece candidatos dentro de una revisión staff protegida: cédula profesional exacta primero (CRD02 y dato curado legado); después identidad verificada + especialidad + estado/municipio completos; de lo contrario `NO_MATCH`. Devuelve ambigüedad y `selected_profile_id=null` siempre. Ni un candidato único, email o nombre selecciona perfil u otorga autorización. La aprobación vuelve a consultar conflictos de licencia, incluso en legado, propietarios y antecedentes de vinculación con otra cuenta bajo locks.

Se acepta intake pendiente para una solicitud nueva sin perfil/VID01/CRD02 aún creados; el UUID de la solicitud es su origen. No se fabrica un perfil provisional para satisfacer FK. Crear/resolver el nuevo perfil y sus fuentes canónicas corresponde al siguiente workflow C4: ese tipo no puede obtener aprobación rutinaria ni un resultado positivo del puerto actual. Conflicto/transferencia también requiere su procedimiento específico; puede rechazarse/revocarse, nunca autoaprobarse o cambiar owner. Un perfil heredado no adquiere acreditación por existir, estar publicado, tener plan, cédula o membresía.

## Evidencia cifrada y revisión humana

[EvidenceCipher](../../modules/identity/Accreditation/EvidenceCipher.php) usa AEAD XChaCha20-Poly1305 de libsodium, nonce aleatorio y datos asociados a solicitud/archivo/clase; teléfono usa dominio cuenta/versión. El keyring se inyecta exclusivamente desde composición confiable, sin clave generada/default en runtime. Key ID y referencia de política no son una aprobación operativa por sí mismos. No se instalaron claves, política, entorno o compositor reales.

Se reutilizan [PrivateMediaStoragePort](../../modules/media/contracts/PrivateMediaStoragePort.php) y [LocalPersistentPrivateMediaStorage](../../modules/media/storage/LocalPersistentPrivateMediaStorage.php), añadiendo únicamente el namespace privado cifrado. Raíz fuera del árbol/app/document root, directorios 0700, archivos 0600, no-clobber y rechazo de traversal/symlinks. No se usa el catálogo público de fotos/logos como acreditación. Se evitan warnings del filesystem que impriman claves de almacenamiento, conservando errores genéricos y verificación de integridad.

Intake admite PDF/JPEG/PNG, hasta 8 MiB por objeto y diez por expediente; no conserva filename ni escribe plaintext en disco. Staging contiene sólo ciphertext. No hay URL pública ni ruta HTTP nueva. La lectura privada requiere sesión, cuenta propia o capacidad staff exacta y scope solicitud/archivo; su resultado incluye headers obligatorios `no-store`, `nosniff`, attachment y sandbox para la futura integración original. No devolver claves/IDs sensibles en una URL. El consumidor debe conservar esos headers; esta entrega no afirma una página de revisión ya disponible.

Antes de aprobar, un revisor independiente, activo, con `physician_accreditation_review`, reauth reciente y MFA de la política staff existente debe acceder a todos los objetos exactos y afirmar explícitamente la relación humana. Los recibos se atan a su sesión, versión y hash; la prueba MFA a caso/operación/cuenta/perfil/versiones. Rechazar/suspender/revocar/expirar requiere motivo enumerado y el mismo control, sin texto sensible en logs. Un revisor rutinario basta; no se impone otro ni evidencia raíz. La disponibilidad de MFA para el staff nominado sigue la política existente, no se extiende por un grant nuevo.

Estados: DRAFT → SUBMITTED → APPROVED → CONFIRMED; rechazo desde SUBMITTED; suspensión/revocación/expiración controladas, sin reaprobación automática. CAS, locks y auditoría usan el mismo PDO. Fechas de documentos las declara el revisor tras examinarlos, con UTC válida; se usa el vencimiento más temprano y se rechaza evidencia vencida. No se inventa revisión anual ni vencimiento cuando la fuente no lo tiene. Cambios VID01/CRD02, contacto, cuenta, credencial del revisor, grant, factor o estado deniegan decisiones posteriores.

No hay expiración/borrado automático de documentos aceptados ni retención permanente inventada. Un fallo SQL ambiguo puede dejar un objeto cifrado sin referencia: se conserva privado para reconciliación gobernada; no se arriesga borrar un objeto cuyo commit pudo ocurrir. Esa reconciliación y la retención/legal hold/disposición deben quedar aprobadas antes de intake real. Sólo los archivos temporales de ciphertext se limpian automáticamente; QA elimina su raíz desechable completa.

## Confirmación y contrato del emisor posterior

El médico exacto reautentica y obtiene un nonce aleatorio de 256 bits válido cinco minutos, guardado sólo como hash. Se liga a sesión, versión de credencial, revisión y expediente exactos. Reemitir reemplaza el nonce anterior; aceptar exige `true` explícito, sesión/reauth vigentes y todos los hechos revalidados. El consumo queda auditado y conserva su contexto histórico; SQL permite un único ganador concurrente. No hay aceptación por correo, nombre o invitación recibida.

[ProtectedPhysicianEvidencePort](../../modules/identity/Accreditation/ProtectedPhysicianEvidencePort.php) expone `requireConfirmed` al futuro emisor interno: mismo PDO y transacción ya abierta, solicitud/versión/cuenta/perfil exactos. Vuelve a bloquear/revalidar contacto, fuentes/revisiones, autoridad staff, MFA consumido y documentos/disponibilidad/vigencia/confirmación. Devuelve hechos minimizados y referencias de auditoría; no bytes, teléfono, licencia, storage key, nonce o membresía. La confirmación consumida es evidencia durable, no una sesión inmortal; el emisor debe validar su actor/sesión actuales y auditar su propia mutación. La versión de credencial confirmante debe seguir vigente.

No se escribe `profiles_physician_admissions`, `auth_account_professional_bindings` ni memberships. El adaptador normal de InitialOwnerEligibility sigue cerrado. El emisor posterior deberá ligar este request/version y ambas revisiones a su único escritor, revalidar al commit y emitir la admisión/binding con auditoría atómica. No reaplicar documentos ya aceptados ni duplicar revisión profesional. Valkey y SQL no son una transacción distribuida; se revalida sesión antes de commit, sin afirmar atomicidad entre ambos almacenes.

## Auditoría y QA

Se añade un evento finito `PHYSICIAN_EVIDENCE_CHANGED`, metadata minimizada (operación, versiones/estados, caso/motivo y capacidad), mapeado al catálogo de operaciones y módulo AUTH. Contexto/rol/actor/sesión provienen de servidor; no documentos, cédulas, contactos, OTP, hashes secretos, claves o tokens. El writer canónico joined y rutinas nativas se conservan. SHA-256 de las primeras 52 políticas: `2444b5ba944db0bc0a29390a073699e1b85efe4e061376bf6375c781df6db581`, sin cambios. El source pin de despliegue incluye la nueva fundación, migración, almacenamiento y catálogos; no se fabricó autorización de despliegue real.

Ejecutar [runner existente](../../scripts/gov03b-disposable-qa.py) con `--suite evidence`, conexión privada normal sólo para schema-only clone y un directorio privado de evidencia. Genera DB/principal SQL/Valkey/raíces propios, importa cero filas normales, aplica 01/02/03 sólo allí y ejecuta [C4EvidenceIntegrationTest](../../modules/identity/tests/C4EvidenceIntegrationTest.php). El principal no puede leer/escribir `mxmed`; elimina DB, usuario, Valkey y secretos/objetos sintéticos al finalizar. No debe ejecutarse PHP del test directamente con configuración de producto.

La QA prueba flujo real de email y OTP sintéticos, fuentes VID01/CRD02 reales del ensayo, scope/identidad/perfil, acceso documental obligatorio, capacidad/MFA/independencia, un revisor, rechazo de datos vencidos, origen/matching, confirmación concurrente/replay/expiry/cambio de contacto, límites persistentes de OTP/entrega fallida, revocación, rollback físico de auditoría, cifrado y AEAD entre scopes. Verifica cero admisión/binding/owner y firma clínica cerrada, coherencia de chain heads y las 52 políticas anteriores. La aprobación humana del test es una simulación declarada con documentos sintéticos; no se representa como revisión de una persona real.

Regresiones dirigidas: GOV-SIM02/gobierno nativo, OP-IMP01/02 y cuotas/concurrencia, Identity/C3/sesión privada, Agenda, resolver clínico, CLIN-AUTH02A y almacenamiento privado. No se afirma una suite global verde: los tests históricos de auditoría con pines 44/52 y doubles PDO anteriores tienen deuda previa de actualización; no se relajaron para obtener aceptación. La QA nueva usa SQL nativo y verifica las políticas publicadas completas.

Evidencia privada final: `~/Library/Application Support/MXMED/c4-ev01/20261016-review/`. Snapshots antes/después comprueban datos/DDL/audit/rutinas/triggers/configuración/sesiones y QA05/OP02/UI03. El origen original sigue disponible; sin login o QA física Safari del Director y sin detener procesos persistentes.

## Pendiente y autorización siguiente

1. Aprobar proveedor SMS y su operación, custodia/rotación/recuperación de claves, política de retención/acceso/legal hold/disposición y reconciliación de objetos huérfanos; no inventarlos por un ID de configuración.
2. Reanudar el emisor C4/ADM02 con este puerto, escritor de admisión/binding, auditoría/lifecycle y elegibilidad final. Completar el workflow de resolución/creación de perfiles nuevos y los procedimientos específicos de conflicto/transferencia; no elegir un perfil automáticamente.
3. Integrar, con autorización de alcance, en la aplicación original sus controles privados y respuestas no-store; no existe UI alternativa. Aprobar por separado DDL normal-local y personas/capacidades/perfiles concretos antes de operaciones reales.

No se requiere activar normal-local para revisar o completar fuente. Ownership, firma clínica y Operadores mantienen sus gates separados. GOV-SIM02 conserva 3600/43200/300/cinco sesiones, un revisor rutinario y dos asientos no owner; doce meses obligatorios y raíz V3 siguen diferidos.
