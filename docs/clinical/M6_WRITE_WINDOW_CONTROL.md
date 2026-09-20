# M6 clinical write-window control

This repository provides a source-only operational barrier for the future M6
migration window. It does not activate the window by default and it does not
authorize migration of the working database.

## Authority

`MXMED_CLINICAL_WRITE_WINDOW_CONTROL=OPEN` (also the unset default) preserves
the current runtime. An operational window uses `FILE` plus an absolute
`MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH`. The state file is shared by all PHP
workers on the host and every admission/state transition is serialized with
`flock(LOCK_EX)`. Missing, unreadable, malformed, or unknown FILE authority
fails closed for writers.

The operator command is:

```bash
php scripts/clinical-m6-write-window.php init /operator-owned/path/state.json
php scripts/clinical-m6-write-window.php status /operator-owned/path/state.json
php scripts/clinical-m6-write-window.php block /operator-owned/path/state.json
php scripts/clinical-m6-write-window.php open /operator-owned/path/state.json
```

The runtime process must be configured with the same FILE mode/path. The file
and parent directory must be writable only by the deployment operator and the
runtime service account. The migration database account has no authority over
this file or command.

## Entry and quiescence

1. Verify backup, migration account, schema preconditions, runtime configuration,
   and that the state is valid and OPEN.
2. Run `block`. The exclusive lock changes state before any later writer can be
   admitted.
3. Run `status` until the same snapshot reports `state=BLOCK_WRITES` and
   `active_writers=0`. This is the observable quiescence checkpoint; no timed
   sleep establishes quiescence.
4. The server operator enables `log_bin_trust_function_creators=1`; the separate
   migration account then performs the authorized migrations.

Writers admitted before step 2 finish normally and remain counted. New covered
writes receive HTTP `503` with `M6_WRITE_WINDOW_BLOCKED`. Reads remain available,
and runtime schema-ensure helpers become no-ops while the window is blocked.

## Exit and abort boundary

After migration and immediate validation, the server operator restores
`log_bin_trust_function_creators=0`, then runs `open` and verifies the OPEN
snapshot. Before migration begins, an abort consists of reopening the window
and verifying normal admission. Once migration begins, control transfers to the
separately authorized `SAFE_RETURN_PLAN_ACCEPTANCE_AND_REHEARSAL`; reopening
writes alone is no longer a safe-return procedure.
