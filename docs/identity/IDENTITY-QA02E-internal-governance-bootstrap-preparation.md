# IDENTITY-QA02E — preparación de gobierno interno y ownership inicial

Estado: preparación backend fail-closed. No habilita bootstrap, emisor, cuenta staff, nombramiento de Director ni cambio de membresía.

## Contratos históricos

La revisión usa como autoridad documental el commit histórico `f295a98e00fd6d90c7ce03449ff24e259caee24b`, en particular `MXMED_APROBACION_DECISIONES_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md` y `MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md`.

- DEC-011A mantiene separados personal interno, membresías profesionales y planes comerciales.
- DEC-011B clasifica las acciones de ownership privilegiado como R3: caso, MFA, reautenticación, doble aprobación independiente, separación de funciones, auditoría y revisión posterior.
- DEC-011C exige que el soporte asistido conserve al operador real, tenga alcance y vencimiento, y no suplante al médico. El acceso clínico extraordinario es un flujo R3 separado.

Los documentos históricos declaraban esos controles como requisitos aprobados con implementación cero. QA02E no interpreta su existencia documental como disponibilidad runtime.

## Preparación implementada

`ProfileGovernancePolicy` proyecta los requisitos R3 para el bootstrap del primer Director y la aprobación inicial de `owner/profile`. También define operaciones R2 de administración asistida de perfiles, siempre con perfil exacto, caso y capacidad explícita, sin exigir ownership y sin incluir autoridad clínica.

`FirstDirectorBootstrapService` prepara una única operación backend. Exige una cuenta Identity activa y verificada sin membresías profesionales, consume evidencia de un trust anchor externo dentro de la transacción, rechaza Director existente, depende de la restricción única `director_slot` y revierte el nombramiento si falla la auditoría. No existe adaptador productivo del trust anchor, ruta HTTP, comando CLI ni flag de activación. Los adaptadores por defecto rechazan.

La evidencia de la ceremonia exige dos ratificadores distintos entre sí y del candidato, dos referencias de aprobación, MFA, reautenticación, caso, vencimiento, operación y candidato exactos. Esto modela el primer-actor paradox sin inventar un segundo aprobador dentro de MXMED.

`profile_initial_ownership_approve` es una capacidad específica, de gobierno y no delegable. No se concede a ninguna clase ni cuenta por defecto. Las capacidades separadas `profile_create`, `profile_curate`, `profile_publication_manage` y `profile_support_assist` conservan la futura administración granular de perfiles sin convertir al staff en owner.

`PdoProfileOwnershipApprovalAdapter` ahora revalida dentro de la transacción que el aprobador almacenado siga siendo una cuenta activa y verificada, personal interno activo y titular de la capacidad específica. La revocación entre emisión y consumo deja la aprobación sin consumir y bloquea la transición.

## Persistencia R3 propuesta y razones para mantener el emisor inactivo

La tabla instalada por QA02B conserva un solo `approver_account_id`. La migración propuesta `2026_10_14_02_profile_ownership_r3_endorsements.sql` añade, sin alterar esa tabla, dos endorsements ligados a slots distintos, aprobadores distintos, sesión staff, versión de credencial, evidencia MFA, caso, operación, cuenta, membresía y perfil exactos. Ambos endorsements se consumen en la misma transacción que la aprobación y la membresía. La migración fue ensayada con forward repetido y rollback en una base desechable; no fue aplicada a normal-local.

El runtime todavía no dispone de un proveedor canónico de MFA para personal interno ni de platform cases R3. Conectar un emisor sin esos componentes incumpliría DEC-011B.

Por ello QA02E prepara la política, la persistencia propuesta y la frontera de consumo, pero no compone un emisor HTTP/CLI. La activación futura requiere:

1. autorizar y aplicar la migración R3;
2. integrar el step-up de cada aprobador con MFA y versión de credencial;
3. integrar platform case R3 y revisión posterior;
4. componer la emisión y auditoría atómicas;
5. conservar el step-up separado de la cuenta beneficiaria.

La revalidación de ambos aprobadores y el consumo atómico de ambos endorsements ya están preparados. La migración requiere autorización separada antes de normal-local.

## Planos preservados

El personal interno opera mediante `internal_staff` y capacidades explícitas. El médico opera mediante una membresía ligada a un perfil. El plan comercial únicamente aporta entitlements. Ninguno de esos planos crea autoridad en otro.

La administración asistida futura registra al operador real, el perfil objetivo, el caso y la finalidad. No existe `login as physician`. Las capacidades de perfil no incluyen pacientes, expediente, recetas ni actos médicos. Un owner de perfil tampoco obtiene capacidades de plataforma.

## Evidencia desechable

`GovernedAuthorityPreparationTest.php` cubre trust anchor ausente, autoaprobación, separación de ratificadores, MFA, bootstrap único, restricción de concurrencia, rollback de auditoría, política R3, perfil ownerless, ausencia de autoridad clínica y separación staff/owner.

`ProfileOwnershipPersistenceTest.php` ensaya la migración R3, doble aprobación, auditoría de ambos aprobadores, revocación entre emisión y consumo, y confirma que la membresía no cambia ni se consume evidencia cuando la autoridad deja de ser válida.

Todas las composiciones normales continúan sin referenciar el bootstrap, la política R3 ni el adaptador PDO de autoridad. Las pruebas crean bases con prefijo `mxmed_identity_qa02*` y las eliminan al finalizar.
