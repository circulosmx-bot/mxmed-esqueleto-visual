#!/usr/bin/env bash
set -euo pipefail
qa_dir="$(cd "$(dirname "$0")" && pwd)"
export FLOW_R1_CAPTURE_R43A=1
export FLOW_R1_QA_SCRIPT="$qa_dir/step6_mobile_capture_r43a_test.py"
: "${FLOW_R1_ARTIFACTS:?Provide an evidence directory outside source}"
bash "$qa_dir/consultation_flow_r1_disposable_gate.sh"
