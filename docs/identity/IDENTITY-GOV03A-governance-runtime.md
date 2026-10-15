# IDENTITY-GOV03A — infraestructura R3 de gobierno de Identity

Estado: código y esquema preparados; pruebas desechables completas. La composición productiva permanece deshabilitada salvo que `MXMED_IDENTITY_GOVERNANCE_ENABLED=1` y todas las dependencias criptográficas estén configuradas. No se aplicó la migración a `mxmed`, no se crearon miembros de personal y no se modificó ninguna membresía real.

## Contrato implementado

La migración `2026_10_15_01_identity_governance_r3_runtime.sql` agrega casos R3, endorsements de caso, factores TOTP cifrados, challenges y pruebas ligadas a acción, evidencia de trust anchor y eventos de gobierno. No contiene seeds ni cambios de autoridad. La migración R3 anterior continúa como evidencia final y consumible para `ProfileMembershipRoleChangeService`.

`R3ProfileGovernanceService` abre un caso sobre una membresía y un perfil exactos. El solicitante y el beneficiario no pueden aprobar. Dos actores distintos deben conservar cuenta activa y verificada, clase `DIRECTOR` o `MASTER_ADMIN`, grant activo `profile_initial_ownership_approve`, versión de credencial vigente y una prueba MFA distinta. La autoridad se revalida al crear el endorsement y otra vez al consumir la aprobación.

`StaffMfaService` implementa TOTP RFC 6238 de seis dígitos, periodo de 30 segundos y deriva cada prueba desde un challenge aleatorio. El secreto de 160 bits se cifra con `sodium_crypto_secretbox`; la llave de 256 bits sólo se recibe desde configuración. La verificación admite una ventana de ±30 segundos, limita cada challenge a cinco intentos, bloquea durante 15 minutos, impide reutilizar factor/time-step y emite una prueba de cinco minutos ligada a actor, sesión, versión de credencial, operación, caso, objetivo, beneficiario y perfil. Los valores claros de inscripción y prueba se devuelven una vez y no se registran. La revocación de un factor exige un caso `MFA_RECOVERY` con dos endorsers todavía autorizados.

`Ed25519ExternalTrustAnchorAdapter` acepta únicamente evidencia con firma Ed25519 válida para la organización y ambiente configurados. La evidencia liga operación UUID, candidato, caso, dos ratificadores independientes, MFA, reautenticación y una vigencia máxima de quince minutos. Sólo se persisten hashes y metadatos públicos. La firma y la operación son únicas y el consumo ocurre dentro de la transacción del primer Director.

La aprobación del caso inicial materializa la aprobación y dos endorsements que ya consume el escritor canónico existente. El beneficiario mantiene una sesión canónica vigente, reingresa su contraseña y recibe una prueba de cinco minutos. La transición modifica la membresía existente y su finalizador consume caso y endorsements dentro de esa misma transacción.

La recuperación admite reemplazo atómico de owner y remoción únicamente cuando permanece otro owner activo. Revalida al solicitante y a ambos aprobadores inmediatamente antes del cambio. El reemplazo convierte al owner anterior en administrator y la membresía sustituta del mismo perfil en owner, sin crear membresías ni autoridad clínica. Los eventos de gobierno tienen guardas de base de datos que rechazan actualización y borrado.

## Composición HTTP

`POST /api/identity/governance.php/...` exige HTTPS, origen o referer exacto, sesión canónica, CSRF ligado a la sesión, método POST y configuración completa. Las respuestas de rechazo no enumeran cuentas, casos ni factores. No existe mutación por GET ni endpoint de inscripción MFA en esta fase. La inscripción real debe formar parte de una ceremonia operativa aprobada; el servicio criptográfico está preparado, pero GOV03A no autoriza inscribir usuarios reales.

Variables requeridas para una activación futura:

- `MXMED_IDENTITY_GOVERNANCE_ENABLED=1`
- `MXMED_STAFF_MFA_ENCRYPTION_KEY`: 32 bytes codificados en base64
- `MXMED_STAFF_MFA_KEY_ID`: identificador de rotación, sin material secreto
- `MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY`: llave pública Ed25519 en base64
- `MXMED_GOVERNANCE_ORGANIZATION_ID`: audiencia exacta de la evidencia
- secretos ya exigidos por el audit trail canónico

No hay valor predeterminado, correo confiable, token maestro ni proveedor sintético en runtime.

## Política de contactos profesionales

GOV03A no restringe las rutas `profile/contact-points`. El contrato vigente valida sesión canónica y perfil exacto, pero no establece claramente si un collaborator puede editar los contactos profesionales ordinarios. Los contactos de emergencia y la publicación asociada siguen bajo `PUT /consultorios` y exigen owner o administrator. Cambiar la política general de contactos profesionales requiere una decisión de producto separada.

## Preparación para GOV03B

Antes de cualquier cambio real se requiere una autorización separada para:

1. respaldar y aplicar ambas migraciones R3 pendientes a normal-local;
2. configurar una llave pública de un emisor externo real y documentar sus dos ratificadores;
3. ejecutar el bootstrap de un Director independiente que no tenga membresías profesionales;
4. realizar la inscripción MFA presencial o equivalente, con custodia y recuperación aprobadas;
5. nombrar un segundo aprobador independiente y conceder los grants auditados;
6. verificar el audit trail y ensayar revocación/recuperación con actores reales autorizados;
7. abrir, aprobar y aceptar el caso exacto del beneficiario;
8. autorizar separadamente la transición real de la membresía.

El estado de GOV03A es `CODE_READY`, `SCHEMA_READY` y `DISPOSABLE_QA_READY`. El emisor externo, aprobadores reales, MFA real, migración normal-local y promoción de la Directora permanecen inactivos.
