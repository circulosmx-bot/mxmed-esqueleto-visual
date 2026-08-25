#!/bin/sh
set -eu

EXPECTED_ACCOUNT='875691018466'
EXPECTED_REGION='mx-central-1'
AWS_WRITE_AUTHORITY='DIRECTOR_AUTHORIZES_SINGLE_USE_C3_AWS_WRITES'
WATCHDOG_SECONDS=1200
CONTRACT_HELPER='scripts/aws/c3-runtime-contract.sh'

[ -f "$CONTRACT_HELPER" ] || { printf '%s\n' 'C3_TEST_FAIL_CLOSED:RUNTIME_CONTRACT_HELPER_MISSING' >&2; exit 1; }
. "$CONTRACT_HELPER"

fail() { printf '%s\n' "C3_TEST_FAIL_CLOSED:$1" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || fail "COMMAND_MISSING:$1"; }
mode=''
manifest=''
state=''
evidence=''
teardown_armed=false

while [ "$#" -gt 0 ]; do
  case "$1" in
    --run-once|--collect-sanitized-evidence|--ensure-task-stopped) mode="$1" ;;
    --manifest) shift; manifest="${1:-}" ;;
    --state) shift; state="${1:-}" ;;
    --evidence) shift; evidence="${1:-}" ;;
    *) fail "UNKNOWN_ARGUMENT:$1" ;;
  esac
  shift
done

validate_authority() {
  need jq
  auth_file="${MXMED_C3_AWS_WRITE_AUTHORIZATION_FILE:-}"
  [ -n "$auth_file" ] && [ -f "$auth_file" ] || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_MISSING'
  [ -n "$state" ] || fail 'RUNTIME_STATE_PATH_MISSING'
  c3_validate_phase PRE_TEST "$manifest" "$state" || fail 'RUN_MANIFEST_OR_STATE_CONTRACT_REJECTED'
  jq -e '
    (.runner.cluster_arn | type == "string")
    and (.runner.task_definition_arn | type == "string")
    and (.runner.private_subnet_ids | length == 2)
    and (.runner.application_security_group_id | test("^sg-[a-f0-9]+$"))
  ' "$state" >/dev/null || fail 'RUNNER_RUNTIME_STATE_REJECTED'
  jq -e --arg authority "$AWS_WRITE_AUTHORITY" --arg run "$(jq -r .run_id "$manifest")" \
    '.authorization == $authority and .run_id == $run and .single_use == true' "$auth_file" >/dev/null \
    || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_INVALID'
  [ "${AWS_ACCOUNT_ID:-}" = "$EXPECTED_ACCOUNT" ] || fail 'AWS_ACCOUNT_MISMATCH'
  [ "${AWS_REGION:-}" = "$EXPECTED_REGION" ] || fail 'AWS_REGION_MISMATCH'
  current_head="$(git rev-parse HEAD)"
  [ "$current_head" = "$(jq -r .source_head "$manifest")" ] || fail 'SOURCE_HEAD_MISMATCH'
  [ -z "$(git status --porcelain)" ] || fail 'WORKTREE_NOT_CLEAN'
  [ "${MXMED_C3_TEST_CONTROLLER_PROFILE:-}" = 'mxmed-c3-stg-test-controller' ] || fail 'EXACT_TEST_CONTROLLER_PROFILE_REQUIRED'
  [ "${MXMED_C3_TEARDOWN_PROFILE:-}" = 'mxmed-c3-stg-teardown' ] || fail 'EXACT_TEARDOWN_PROFILE_REQUIRED'
}

seal_state_field() {
  field="$1" value="$2"
  [ "$(jq -r --arg field "$field" '.[$field]' "$state")" = "$C3_PENDING_RUNTIME_RESOLUTION" ] \
    || fail "RUNTIME_STATE_FIELD_REWRITE_REJECTED:$field"
  c3_atomic_state_update "$manifest" "$state" '.[$field]=$value' --arg field "$field" --arg value "$value"
}

aws_test() { AWS_PROFILE="$MXMED_C3_TEST_CONTROLLER_PROFILE" aws "$@"; }

emergency_teardown() {
  [ "$teardown_armed" = true ] || return 0
  teardown_armed=false
  if [ "${MXMED_C3_TEARDOWN_PROFILE:-}" != 'mxmed-c3-stg-teardown' ]; then
    printf '%s\n' 'C3_TEST_FAIL_CLOSED:EXACT_TEARDOWN_PROFILE_REQUIRED' >&2
    return 1
  fi
  AWS_PROFILE="$MXMED_C3_TEARDOWN_PROFILE" \
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --execute-stack-deletes --manifest "$manifest" --state "$state"
}

case "$mode" in
  --run-once)
    validate_authority
    need aws
    [ "$(jq -r '.test_execution_count // 0' "$state")" = '0' ] || fail 'ONE_TASK_LIMIT_ALREADY_CONSUMED'
    cluster="$(jq -r .runner.cluster_arn "$state")"
    task_definition="$(jq -r .runner.task_definition_arn "$state")"
    subnets="$(jq -r '.runner.private_subnet_ids | join(",")' "$state")"
    security_group="$(jq -r .runner.application_security_group_id "$state")"
    run_output="$(mktemp)"
    trap 'rm -f "$run_output"' EXIT HUP INT TERM
    teardown_armed=true
    trap 'rm -f "$run_output"; emergency_teardown' EXIT HUP INT TERM
    seal_state_field test_started_at_utc "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    aws_test ecs run-task \
      --cluster "$cluster" \
      --task-definition "$task_definition" \
      --count 1 \
      --launch-type FARGATE \
      --network-configuration "awsvpcConfiguration={subnets=[$subnets],securityGroups=[$security_group],assignPublicIp=DISABLED}" \
      --started-by "$(jq -r .run_id "$manifest")" >"$run_output"
    task_arn="$(jq -er '.tasks | if length == 1 then .[0].taskArn else error("TASK_COUNT_INVALID") end' "$run_output")"
    c3_atomic_state_update "$manifest" "$state" '.test_execution_count=1 | .runner.task_arn=$arn' --arg arn "$task_arn"
    started_epoch="$(date +%s)"
    while :; do
      status="$(aws_test ecs describe-tasks --cluster "$cluster" --tasks "$task_arn" --query 'tasks[0].lastStatus' --output text)"
      [ "$status" != 'STOPPED' ] || break
      now_epoch="$(date +%s)"
      if [ $((now_epoch - started_epoch)) -ge "$WATCHDOG_SECONDS" ]; then
        aws_test ecs stop-task --cluster "$cluster" --task "$task_arn" --reason 'MXMED_C3_CONTROLLER_WATCHDOG_1200_SECONDS' >/dev/null
        aws_test ecs wait tasks-stopped --cluster "$cluster" --tasks "$task_arn"
        break
      fi
      sleep 5
    done
    terminal_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    seal_state_field test_terminal_at_utc "$terminal_at"
    "$(dirname "$0")/c3-ephemeral-test.sh" --collect-sanitized-evidence --manifest "$manifest" --state "$state" --evidence "${evidence:-${manifest%.json}-evidence.json}"
    "$(dirname "$0")/c3-ephemeral-test.sh" --ensure-task-stopped --manifest "$manifest" --state "$state"
    test_pass="$(jq -r '[.task.containers[]?.exitCode] == [0]' "${evidence:-${manifest%.json}-evidence.json}")"
    emergency_teardown
    trap - EXIT HUP INT TERM
    rm -f "$run_output"
    [ "$test_pass" = true ] || fail 'PHYSICAL_C3_TEST_FAILED'
    ;;
  --collect-sanitized-evidence)
    validate_authority
    [ -n "$evidence" ] || fail 'EVIDENCE_PATH_MISSING'
    cluster="$(jq -r .runner.cluster_arn "$state")"
    task_arn="$(jq -r .runner.task_arn "$state")"
    task_json="$(aws_test ecs describe-tasks --cluster "$cluster" --tasks "$task_arn" --query 'tasks[0].{lastStatus:lastStatus,stopCode:stopCode,stoppedReason:stoppedReason,containers:containers[*].{name:name,exitCode:exitCode,reason:reason,imageDigest:imageDigest}}' --output json)"
    task_id="${task_arn##*/}"
    log_json="$(aws_test logs get-log-events --log-group-name /mxmed/staging/c3-runner --log-stream-name "c3/c3-runner/$task_id" --start-from-head --query 'events[].message' --output json)"
    if ! printf '%s' "$log_json" | jq -e '
      length > 0 and all(.[];
        test("^(C3_RUNNER_START:staging:tls-valkey|C3_PHYSICAL_VALKEY_TEST=PASS|TLS_HOSTNAME_VERIFICATION=PASS|ACL_AUTH=PASS|NAMESPACE_RESTRICTION=PASS|TTL_TOUCH_ROTATE_REVOKE_INDEX=PASS|MAX_FIVE_AFTER_20_CREATES=PASS|READ_ONLY_HEALTH_PING=PASS)$"))
    ' >/dev/null; then
      log_json='["C3_PHYSICAL_TEST_FAILED_SANITIZED"]'
    fi
    jq -n --argjson task "$task_json" --argjson logs "$log_json" \
      --arg run_id "$(jq -r .run_id "$manifest")" --arg collected "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
      '{schema:"mxmed.c3.sanitized-evidence.v1",run_id:$run_id,collected_at_utc:$collected,task:$task,allowed_test_markers:$logs}
       | walk(if type == "string" then gsub("(?i)(password|secret|token|authorization)[^ ]*"; "[REDACTED]") else . end)' \
      >"$evidence"
    chmod 0600 "$evidence"
    ;;
  --ensure-task-stopped)
    validate_authority
    cluster="$(jq -r .runner.cluster_arn "$state")"
    task_arn="$(jq -r .runner.task_arn "$state")"
    status="$(aws_test ecs describe-tasks --cluster "$cluster" --tasks "$task_arn" --query 'tasks[0].lastStatus' --output text)"
    if [ "$status" != 'STOPPED' ]; then
      aws_test ecs stop-task --cluster "$cluster" --task "$task_arn" --reason 'MXMED_C3_TERMINAL_CLEANUP' >/dev/null
    fi
    ;;
  *) fail 'MODE_REQUIRED' ;;
esac
