# AUDIT-MP01E identity/session producer contract

This dormant, additive candidate defines typed producer APIs for exactly thirteen identity/session events. It does not register services, alter routes/controllers, or activate the canonical writer.

## Ratified D01–D07 rules

- Failed login uses actor `UNKNOWN` and a dedicated, versioned `HMAC-SHA-256` target. The normalized identifier never leaves the hashing boundary and the IP-HMAC namespace is rejected.
- `AUTH_EMAIL_VERIFICATION_SENT` becomes eligible only after `IdentityNotificationPort::send()` returns normally. It records adapter acceptance for send, never delivery.
- Logout-all emits one aggregate event per logical operation, with no automatic session-revoke fanout and no session-count metadata.
- Producer failures use bounded best effort with an explicit safe failure signal. A confirmed domain outcome remains true; no distributed rollback or retry is claimed.
- Identity/MySQL and Session/Valkey outcomes become audit-eligible only after their authoritative outcome. There is no outbox or cross-store atomicity.
- Recovery reset emits only `AUTH_PASSWORD_RESET_SUCCEEDED`; authenticated change emits only `AUTH_PASSWORD_CHANGED`.
- Email verification consumes a `VerifiedAccountId` created from backend token resolution. The verification token is never an actor, target, metadata value, or persisted field; no session is required.

## Integration boundary

`CanonicalAuditWriterAdapter` delegates exclusively to the published MP01C `CanonicalAuditWriter`. Authenticated and system flows use the published MP01D `AuditWriterContextBridge`. The three normal actor-optional pre-authentication events use the dormant, MP01E-local `PreauthActorOptionalContext`, which projects exactly `UNKNOWN` / `UNKNOWN` with `authentication_failure=false` into the published `TrustedAuditContext`; MP01C and MP01D remain byte-identical. Productive wiring remains forbidden and absent.

## Focused F01–F03 closure

- `AUTH_SESSION_CREATED`, `AUTH_SESSION_ROTATED`, `AUTH_SESSION_REVOKED`, and `AUTH_LOGOUT` target the resulting or affected safe `SessionId`. The request/execution session is independent, so an administrator, a system operation with no request session, or an account operating from session A may revoke session B.
- `AUTH_LOGOUT_ALL` continues to target the authoritative account, emits one aggregate event, never fans out revoke events, and contains no session-count metadata.
- Registration requested, email-verification accepted for send, and password-recovery requested are normal pre-authentication events: actor identity/type are `UNKNOWN` and they are not authentication failures. Failed login alone retains `authentication_failure=true`.
- Registration and recovery without account authority target a versioned `HMAC-SHA-256` of the already canonicalized backend identifier. The namespace is `audit-auth-identifier`, the secret must be at least 256 bits and is supplied by a port, and raw identifiers or account-existence branches are forbidden.

## Safety

No passwords, password hashes, reset/verification tokens, OTPs, Authorization headers, raw cookies, session secrets, raw HTTP requests, arbitrary metadata, arbitrary actor authority, or arbitrary source route are accepted by the producer contracts.
