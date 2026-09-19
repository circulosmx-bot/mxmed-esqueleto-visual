#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 4 || $# -gt 5 ]]; then
  echo 'usage: m5_barrier_release.sh ROOT ENVIRONMENT_ID RUN_ID POINT [TIMEOUT_SECONDS]' >&2
  exit 64
fi

root="$1"
environment_id="$2"
run_id="$3"
point="$4"
timeout_seconds="${5:-20}"

[[ "$root" = /* && "$root" != / ]]
[[ "$environment_id" =~ ^m5-disposable-[A-Za-z0-9._-]+$ ]]
[[ "$run_id" =~ ^[A-Za-z0-9._-]+$ ]]
[[ "$timeout_seconds" =~ ^[0-9]+$ ]]
case "$point" in
  T04_CONCURRENT_START_BEFORE_INSERT|T11_CONCURRENT_FINALIZE_BEFORE_TERMINAL_LOCK_OR_COMMIT|T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK) ;;
  *) echo 'invalid barrier point' >&2; exit 64 ;;
esac

directory="${root%/}/${environment_id}/${run_id}/${point}"
deadline=$((SECONDS + timeout_seconds))
while [[ ! -f "$directory/arrived_A" || ! -f "$directory/arrived_B" ]]; do
  if (( SECONDS >= deadline )); then
    echo 'M5_QA_BARRIER_CONTROLLER_TIMEOUT' >&2
    exit 70
  fi
  sleep 0.01
done

temporary="$directory/.release.$$"
: > "$temporary"
mv "$temporary" "$directory/release"
printf 'M5_QA_BARRIER_RELEASED=%s\n' "$point"
