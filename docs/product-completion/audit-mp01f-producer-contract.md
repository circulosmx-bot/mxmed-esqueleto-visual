# AUDIT-MP01F producer contract

Status: static, additive, dormant, and repository-self-contained.

This candidate is derived against product HEAD `940c6586b604198e38a16e52460fb4c922af31be`. It consumes the published MP01C writer and policy, MP01D trusted request/actor context, and the shared `Platform\Contracts\AuditEventScopePolicy` plus `Identity\Audit\BoundedBestEffortAuditEmitter`. It does not define another writer, serializer, sealer, event catalog, operation catalog, source-module catalog, or emitter.

## Fixed scope

- 15 canonical MP01F event types.
- 7 existing correlatable operations.
- 6 existing source modules: PROFILE, OWNERSHIP, INVITATION, ROLE, SECURITY, ADMIN.
- Actor and target must come from trusted backend state.
- Emission is allowed only after the authoritative outcome.

## Sensitive administrator action

D-MP01F-01 remains DIRECTOR_RATIFIED with policy `A_EXPLICIT_FINITE_BACKEND_ACTION_CATALOG`.

- Catalog version: `mp01f-sensitive-admin-v1`.
- Initial catalog: empty because discovery found no unequivocally eligible current backend action lacking a more specific canonical event.
- Free-form and unknown keys are rejected.
- Body, query, header, and arbitrary metadata cannot select an action.
- The fourteen specific MP01F events cannot be duplicated as `SENSITIVE_ADMIN_ACTION`.
- Adding a key later requires an explicit catalog and policy change with tests.

## Safety boundaries

Invitation tokens, magic links, OTPs, challenge secrets, credentials, passwords, and arbitrary metadata are forbidden. The candidate contains no productive wiring and performs no runtime activation. MP01E remains at 13 events and its actor-optional preauthentication surface remains exactly three events.
