#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
export FLOW_R1_QA_SCRIPT="$root_dir/modules/clinical/qa/step6_mobile_capture_r4_browser.py"
export FLOW_R1_ARTIFACTS="${CAPTURE_R4_ARTIFACTS:-/Users/circulodigital/.codex/artifacts/step6-mobile-capture-r4}"
exec bash "$root_dir/modules/clinical/qa/consultation_flow_r1_disposable_gate.sh"
