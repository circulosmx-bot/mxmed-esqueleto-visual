# AUDIT-MP01B migration and persistence foundation runbook

Status: repository promotion candidate only. This preparation did not edit a repository or execute SQL.

## Purpose and fixed order

AUDIT-MP01B versions the additive migration/persistence foundation for the append-only audit sink. Run B1, B2, B3, B4, then B5 in that exact order through one MySQL client session so temporary tables, route variables, privilege evidence, and receipt hash chains survive across files. The active route is adaptive: `EMPTY` uses the explicit no-row contract; `POPULATED` copies legacy rows and reconciles stream heads.

## Validated material contracts

- R46 lineage keeps canonical temporary-table folds free of MySQL ERROR 1137 by separating count, fold, final accumulator, and final SHA operations; repository cursor folds are likewise reopen-safe.
- R47 adds nullable `canonical_event_id` idempotently using `information_schema.columns` and prepared DDL compatible with MySQL 8.4.
- R48 probes temporary support objects directly in the same session; `information_schema.tables` is used only for persistent shadow/head objects.
- R49 binds EMPTY B2 batch and inserted-count values to the current execution and uses `NO_ROWS` resume values.
- R50 inserts only missing stream heads. It never updates or silently repairs a divergent existing head.
- R51 binds B4 permission postconditions to the active route. R52 pseudo assertions/hardcoded PASS values are absent. R53 counts the four UPDATE/DELETE guards as raw metadata cardinality.
- R54 excludes nullable legacy `canonical_event_id` values before duplicate grouping. A NULL canonical ID is valid legacy state; no canonical ID is invented or backfilled here.

The privilege authority remains 13 required rules, 3 prohibited rules, and zero required UPDATE privileges on `platform_audit_stream_heads`. Source and shadow UPDATE/DELETE triggers enforce append-only behavior. Legacy event identifiers and event hashes are copied without silent rehashing.

## Deployment boundary

`REAL_DATABASE_EXECUTION_AUTHORIZED=false`

Repository materialization only versions these artifacts; it does not deploy them. Before any separately authorized real-database migration: backup required; restore rehearsal required; real row inventory required; migration lock required; measured deployment window required. Rollback is not an automatic destructive reverse migration: use the separately approved restore/forward-repair contract.

`RUNTIME_WRITER_ACTIVATED=false`
`RUNTIME_PRODUCERS_ACTIVATED=false`
`WRITER_IMPLEMENTATION_DEFERRED_TO_MP01C=true`
`PRODUCER_WIRING_DEFERRED=true`
`REQUEST_CONTEXT_DEFERRED_TO_MP01D=true`

AUDIT-MP01C remains NOT_STARTED. No deployment, writer activation, producer wiring, staging integration, or AWS action is claimed by this bundle.
