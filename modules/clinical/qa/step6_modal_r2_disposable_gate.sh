#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
export FLOW_R1_ARTIFACTS="${STEP6_R2_ARTIFACTS:-/Users/circulodigital/.codex/artifacts/step6-modal-actions-r2}"
bash "$root_dir/modules/clinical/qa/consultation_flow_r1_disposable_gate.sh"
