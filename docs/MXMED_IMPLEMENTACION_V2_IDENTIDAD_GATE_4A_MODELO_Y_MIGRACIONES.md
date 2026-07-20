# MXMed — Gate 4A: modelo de identidad y migraciones reversibles V2

Contrato: `MXMED_IDENTITY_MODEL_AND_REVERSIBLE_MIGRATIONS_GATE_4A_V2`

Actividad: `4/22` — `PRODUCT-IMPLEMENTATION/MXMed-Identity-Auth-Session-Foundation-V2/Gate-4A-Identity-Model-And-Reversible-Migrations`

Estado: **`IDENTITY_MODEL_MIGRATIONS_GATE_4A_READY_FOR_DIRECTOR_REVIEW`**
Clasificación: **UI-0 — NO_UI_IMPACT**

## 1. Baseline y límites

La implementación se realizó en `feature/mxmed-identity-auth-session-foundation-v2`, worktree `/Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual-activity04-v2`, sobre `program/mxmed-product-refinement-22-v2@2172423557b1ba7d5840d9ea4861d74f5516bda3`. El avance oficial permanece `3/22`; Gate 4B, 4C y 4D no se iniciaron.

El Gate 4A sólo agrega contratos de dominio, repositorios estructurales, migraciones aditivas/reversibles y pruebas aisladas. No agrega endpoints, autenticación runtime, contraseñas, MFA, recuperación, sesiones, cookies, reclamación operativa, frontend, AWS/CDK, Stripe, seeds ni datos reales. La base usada por 8091 no fue modificada.

## 2. Reconocimiento y referencias canónicas

- Convención DB: `docs/db/CONVENCION_DB.md`; el prefijo `auth_*` es la convención existente para usuarios, roles, sesiones y tokens.
- Perfil profesional canónico: `profiles_doctors.doctor_id`, único en `modules/profiles/db/profiles_doctors_schema.sql:5-27`.
- Entidad organizacional existente: `medical_groups.group_id`, en `modules/agenda/db/medical_groups_schema.sql`.
- Roles operativos existentes (`operator`, `doctor`, etc.) permanecen en sus módulos. `MembershipRole` expone únicamente los códigos de autoridad de membresía aprobados por C1 (`owner`, `administrator`, `collaborator`) como contrato adaptador; no crea un catálogo operativo paralelo ni seeds.
- Suscripciones y capacidades continúan separadas; ninguna tabla `auth_*` contiene plan, precio, suscripción, pago o capacidad.
- Motor compatible observado: MySQL 9.6 local; DDL usa InnoDB, `utf8mb4_unicode_ci`, `VARCHAR` y `CHECK` para compatibilidad MySQL/MariaDB.

La referencia canónica quedó resuelta sin relación polimórfica: una membresía apunta exactamente a `profiles_doctors.doctor_id` o a `medical_groups.group_id`, con FK explícita y restricción XOR. Los identificadores de esas autoridades son inmutables (`ON UPDATE RESTRICT`).

## 3. Modelo implementado

### `auth_accounts`

Cuenta humana canónica, sin credenciales.

- PK `account_id VARCHAR(64)`.
- `email_address` y `email_normalized VARCHAR(190)`; unicidad case-insensitive equivalente mediante normalización PHP y collation existente.
- `status`: `pending_verification`, `active`, `blocked`, `disabled`.
- `email_verified_at` nullable; una cuenta pendiente no puede tener fecha de verificación.
- `created_at`, `updated_at`.
- No contiene password hash, MFA secret, recovery token, session ID ni cookie.

### `auth_account_consents`

Evidencia mínima de consentimiento versionado; no duplica el documento legal.

- PK `consent_id VARCHAR(64)`.
- FK `account_id → auth_accounts.account_id`.
- `document_type`: `terms` o `privacy_notice`.
- `document_version`, `accepted_at`, `metadata_json` mínimo y no sensible, `created_at`.
- Unicidad por cuenta, documento y versión.

### `auth_account_memberships`

Relación de autorización cuenta → membresía → perfil profesional u organización.

- PK `membership_id VARCHAR(64)`.
- FK `account_id → auth_accounts.account_id`.
- FK opcional exclusiva `profile_doctor_id → profiles_doctors.doctor_id` o `entity_group_id → medical_groups.group_id`.
- `role_code`, `scope_code`, `status`, `assignment_source`, `created_at`, `updated_at`, `revoked_at`.
- Estados: `pending`, `active`, `suspended`, `revoked`.
- Roles: `owner`, `administrator`, `collaborator` mediante contrato adaptador C1.
- `active_identity_key` generado y único impide duplicar una membresía no revocada; una membresía `revoked` no concede autoridad.
- La membresía no concede plan, suscripción ni capacidad.

## 4. Migraciones

Forward determinista:

1. `modules/identity/db/migrations/2026_07_19_01_create_auth_accounts.sql`
2. `modules/identity/db/migrations/2026_07_19_02_create_auth_account_consents.sql`
3. `modules/identity/db/migrations/2026_07_19_03_create_auth_account_memberships.sql`

Rollback inverso:

1. `2026_07_19_03_rollback_auth_account_memberships.sql`
2. `2026_07_19_02_rollback_auth_account_consents.sql`
3. `2026_07_19_01_rollback_auth_accounts.sql`

Las migraciones son aditivas, no renombraron ni eliminaron tablas existentes, no incluyen seeds ni datos iniciales y no se ejecutaron contra `mxmed` ni contra la base usada por 8091.

## 5. Contratos y repositorios

Nuevos contratos en `modules/identity/contracts/`:

- `AccountStatus`;
- `MembershipStatus`;
- `ConsentDocumentType`;
- `MembershipRole`;
- `IdentityAccount` con normalización segura de correo;
- `CanonicalProfileReference`;
- `AccountMembership` con autoridad sólo en estado `active`.

Repositorios estructurales en `modules/identity/repositories/`:

- `IdentityAccountRepository`;
- `AccountConsentRepository`;
- `AccountMembershipRepository`.

No se crearon `AuthService`, `LoginService`, `RegistrationService`, `PasswordService`, `RecoveryService`, `SessionService`, `MFAService`, endpoints, controladores ni middleware.

## 6. Pruebas y resultado

Pruebas nuevas:

- `modules/identity/tests/IdentityModelContractTest.php` — estados, correo, roles, scopes y referencias.
- `modules/identity/tests/IdentityPersistenceTest.php` — persistencia, unicidad, FK, membresías múltiples, revocación y ausencia de columnas sensibles.
- `modules/identity/tests/run_gate4a_migration_test.php` — forward, rollback, segundo forward, FKs e integridad en una base temporal desechable.

Resultado observado:

```text
IdentityModelContractTest PASS
Gate4A migration forward/rollback/second-forward PASS
```

La base temporal crea únicamente tablas padre sintéticas (`profiles_doctors`, `medical_groups`) y se elimina al terminar. No se escribió la base principal.

## 7. Seguridad, UI y móvil

- Passwords: `0`.
- Tokens/recovery: `0`.
- MFA secrets: `0`.
- Sessions/cookies: `0`.
- PII real, datos clínicos y pacientes: `0`.
- SQL dinámico: `0`; repositorios usan prepared statements.
- HTML/CSS/JavaScript/formularios/copy/rutas visibles: `0`.
- 8091: intacto y HTTP 200.
- 8140: libre; no se abrió puerto.
- `mobileSmokeRequired=false`.
- `mobileSmokeResult=NOT_APPLICABLE_UI_0`.
- `newMobileDebt=false`.
- `affectedSurfaces=[]`.
- `finalizationChapterDependency=true`.
- `regressionDetected=false`.

## 8. Dependencias, riesgos y deuda diferida

- Las FKs requieren que las tablas canónicas de perfil y grupo existan antes del forward en un ambiente destino; el orden debe quedar en el runner futuro.
- El contrato de roles de membresía es un adaptador de autorización y deberá alinearse con el resolver global en Gate 4C; no autoriza por sí mismo.
- La activación de cuentas, verificación, consentimiento interactivo, login, password, recovery, MFA, sessions y claim queda para Gates 4B/4C posteriores.
- No se habilita migración masiva ni backfill de usuarios existentes.
- Rollback elimina exclusivamente las tres tablas nuevas y conserva las autoridades padre.

## 9. Gate y siguiente paso

Gate 4A queda **`READY_FOR_DIRECTOR_REVIEW`**. Gate 4B no se inició. La Actividad 4 no se integra parcialmente a `program` y no se crea checkpoint en este cierre. Siguiente paso: revisión del director; sólo después de PASS explícito podrá comenzar Gate 4B.
