# AUDIT-MP01G read API and self-security timeline contract

Status: static, additive, dormant, and repository-self-contained. No route, controller, persistence adapter, container binding, writer activation, or productive read emission is included.

## Authority and access

The read service accepts only `TrustedAuditReadAuthority`, derived from published `TrustedActorContext`. Body, query, headers, and path data cannot supply requester identity, role, capability, or self target. Unknown capabilities deny by default.

The finite capability set is `AUDIT_READ_SELF_SECURITY`, `AUDIT_READ_INTERNAL_SCOPED`, and `AUDIT_READ_ADMIN_PRIVILEGED`. Internal reads require one finite scope and one controlled reason: self security, support investigation, security investigation, compliance review, or operational diagnostic. Wildcard scope is forbidden.

Every successful read creates a minimized audit-of-read intent containing requester, capability, scope, controlled reason, filter fingerprint, result count, and trusted authority provenance. Productive emission remains deliberately inactive.

## Self-security timeline

Eligibility comes directly from `CanonicalAuditPolicyRegistry::policyFor(event_type)['self_timeline']`. Unknown or malformed policy rows are ineligible. Ownership is a separate question delegated to `SelfSecuritySubjectResolverPort`, whose future adapter must use trusted backend relationships and deny unknown bindings. This supports ACCOUNT targets, SESSION-to-account ownership, AUTH_IDENTIFIER_HMAC-to-account bindings without reversing the HMAC, and future target shapes such as step-up challenges. The self factory accepts no account, role, capability, scope, or binding override.

There is no universal `target_id == account_id` fallback and actor identity is not treated as subject ownership. The resolver executes on the canonical row before response projection. No resolver evidence is exposed in the response.

## Query and consistency

Supported filters are limited to persisted audit.v1 dimensions. Unknown filters and invalid combinations fail closed. Default page size is 25; maximum is 100. Ordering is fixed to `created_at DESC, event_id DESC`. The opaque keyset cursor is HMAC-protected and versioned. Invalid or tampered cursors are rejected.

History may end at a retention boundary or be unavailable; the contract never fabricates missing events or promises a stronger snapshot than the append-only store provides.

## Data minimization

Three projections are explicit. The self projection excludes identifiers not needed by the user. Internal and privileged projections remain allowlisted. No projection exposes raw persistence rows, raw metadata JSON, writer-internal metadata, IP HMAC values, raw or summarized user agent, session identifiers, chain/hash fields, provider secrets, tokens, credentials, or sealing internals.

## Least-privilege persistence expectation

A future adapter must receive a dedicated SELECT-only principal for the canonical audit history and must implement the bounded `AuditReadRepositoryPort::fetch(..., limit)` contract. It must not receive INSERT, UPDATE, DELETE, DDL, trigger, or stream-head mutation privileges. This candidate supplies no adapter and executes no database operation.
