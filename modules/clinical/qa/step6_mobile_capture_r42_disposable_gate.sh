#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
export FLOW_R1_CAPTURE_R42=1
export FLOW_R1_QA_SCRIPT="$root_dir/modules/clinical/qa/step6_mobile_capture_r42_browser.py"
export FLOW_R1_ARTIFACTS="${CAPTURE_R42_ARTIFACTS:-/Users/circulodigital/.codex/artifacts/step6-mobile-capture-r42}"
exec bash "$root_dir/modules/clinical/qa/consultation_flow_r1_disposable_gate.sh"
