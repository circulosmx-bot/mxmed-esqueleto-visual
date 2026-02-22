#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
TARGET="$REPO_ROOT/docs/qa/clinical_encounters_smoke.sh"

if [[ ! -f "$TARGET" ]]; then
  echo "[FAIL] Missing target script: $TARGET" >&2
  exit 1
fi

exec bash "$TARGET" "$@"
