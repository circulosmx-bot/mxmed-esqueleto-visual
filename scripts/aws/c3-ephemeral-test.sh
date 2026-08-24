#!/bin/sh
set -eu

BASELINE_PRODUCT_HEAD='1f507b61846b96caa34d390ee3a59779f65e4331'
EXPECTED_ACCOUNT='875691018466'
EXPECTED_REGION='mx-central-1'
AWS_WRITE_AUTHORITY='DIRECTOR_AUTHORIZES_SINGLE_USE_C3_AWS_WRITES'
WATCHDOG_SECONDS=1200

fail() { printf '%s\n' "C3_TEST_FAIL_CLOSED:$1" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || fail "COMMAND_MISSING:$1"; }
mode=''
manifest=''
evidence=''
teardown_armed=false

while [ "$#" -gt 0 ]; do
  case "$1" in
    --run-once|--collect-sanitized-evidence|--ensure-task-stopped) mode="$1" ;;
    --manifest) shift; manifest="${1:-}" ;;
    --evidence) shift; evidence="${1:-}" ;;
    *) fail "UNKNOWN_ARGUMENT:$1" ;;
  esac
  shift
done

validate_authority() {
  need jq
  auth_file="${MXMED_C3_AWS_WRITE_AUTHORIZATION_FILE:-}"
  [ -n "$auth_file" ] && [ -f "$auth_file" ] || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_MISSING'
  [ -f "$manifest" ] || fail 'RUN_MANIFEST_MISSING'
  jq -e --arg account "$EXPECTED_ACCOUNT" --arg region "$EXPECTED_REGION" \
    '.schema == "mxmed.c3.ephemeral.run-manifest.v1" and (.expected_head | test("^[a-f0-9]{40}$")) and .account == $account and .region == $region
     and (.image_digest | test("^sha256:[a-f0-9]{64}$"))
     and ([.gates[] | select(.pass != true)] | length == 0)
     and (.runner.cluster_arn | type == "string")
     and (.runner.task_definition_arn | type == "string")
     and (.runner.private_subnet_ids | length == 2)
     and (.runner.application_security_group_id | test("^sg-[a-f0-9]+$"))
     and ((tostring | test("production|mxmed-prd-|<[^>]+>|UNRESOLVED"; "i")) | not)' \
    "$manifest" >/dev/null || fail 'RUN_MANIFEST_CONTRACT_REJECTED'
  jq -e --arg authority "$AWS_WRITE_AUTHORITY" --arg run "$(jq -r .run_id "$manifest")" \
    '.authorization == $authority and .run_id == $run and .single_use == true' "$auth_file" >/dev/null \
    || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_INVALID'
  [ "${AWS_ACCOUNT_ID:-}" = "$EXPECTED_ACCOUNT" ] || fail 'AWS_ACCOUNT_MISMATCH'
  [ "${AWS_REGION:-}" = "$EXPECTED_REGION" ] || fail 'AWS_REGION_MISMATCH'
  current_head="$(git rev-parse HEAD)"
  [ "$current_head" = "$(jq -r .expected_head "$manifest")" ] || fail 'SOURCE_HEAD_MISMATCH'
  git merge-base --is-ancestor "$BASELINE_PRODUCT_HEAD" "$current_head" || fail 'BASELINE_PRODUCT_HEAD_NOT_ANCESTOR'
  [ -z "$(git status --porcelain)" ] || fail 'WORKTREE_NOT_CLEAN'
}

seal_manifest_field() {
  field="$1" value="$2" tmp="${manifest}.tmp.$$"
  jq --arg field "$field" --arg value "$value" '.[$field]=$value' "$manifest" >"$tmp"
  chmod 0600 "$tmp"
  mv "$tmp" "$manifest"
}

emergency_teardown() {
  [ "$teardown_armed" = true ] || return 0
  teardown_armed=false
  if [ "${MXMED_C3_TEARDOWN_PROFILE:-}" != 'mxmed-c3-stg-teardown' ]; then
    printf '%s\n' 'C3_TEST_FAIL_CLOSED:EXACT_TEARDOWN_PROFILE_REQUIRED' >&2
    return 1
  fi
  AWS_PROFILE="$MXMED_C3_TEARDOWN_PROFILE" \
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --execute-stack-deletes --manifest "$manifest"
}

case "$mode" in
  --run-once)
    validate_authority
    need aws
    [ "$(jq -r '.test_execution_count // 0' "$manifest")" = '0' ] || fail 'ONE_TASK_LIMIT_ALREADY_CONSUMED'
    cluster="$(jq -r .runner.cluster_arn "$manifest")"
    task_definition="$(jq -r .runner.task_definition_arn "$manifest")"
    subnets="$(jq -r '.runner.private_subnet_ids | join(",")' "$manifest")"
    security_group="$(jq -r .runner.application_security_group_id "$manifest")"
    run_output="$(mktemp)"
    trap 'rm -f "$run_output"' EXIT HUP INT TERM
    teardown_armed=true
    trap 'rm -f "$run_output"; emergency_teardown' EXIT HUP INT TERM
    aws ecs run-task \
      --cluster "$cluster" \
      --task-definition "$task_definition" \
      --count 1 \
      --launch-type FARGATE \
      --network-configuration "awsvpcConfiguration={subnets=[$subnets],securityGroups=[$security_group],assignPublicIp=DISABLED}" \
      --started-by "$(jq -r .run_id "$manifest")" >"$run_output"
    task_arn="$(jq -er '.tasks | if length == 1 then .[0].taskArn else error("TASK_COUNT_INVALID") end' "$run_output")"
    tmp="${manifest}.tmp.$$"
    jq --arg arn "$task_arn" '.test_execution_count=1 | .runner.task_arn=$arn' "$manifest" >"$tmp"
    chmod 0600 "$tmp"
    mv "$tmp" "$manifest"
    started_epoch="$(date +%s)"
    while :; do
      status="$(aws ecs describe-tasks --cluster "$cluster" --tasks "$task_arn" --query 'tasks[0].lastStatus' --output text)"
      [ "$status" != 'STOPPED' ] || break
      now_epoch="$(date +%s)"
      if [ $((now_epoch - started_epoch)) -ge "$WATCHDOG_SECONDS" ]; then
        aws ecs stop-task --cluster "$cluster" --task "$task_arn" --reason 'MXMED_C3_CONTROLLER_WATCHDOG_1200_SECONDS' >/dev/null
        aws ecs wait tasks-stopped --cluster "$cluster" --tasks "$task_arn"
        break
      fi
      sleep 5
    done
    terminal_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    seal_manifest_field test_terminal_at_utc "$terminal_at"
    "$(dirname "$0")/c3-ephemeral-test.sh" --collect-sanitized-evidence --manifest "$manifest" --evidence "${evidence:-${manifest%.json}-evidence.json}"
    "$(dirname "$0")/c3-ephemeral-test.sh" --ensure-task-stopped --manifest "$manifest"
    test_pass="$(jq -r '[.task.containers[]?.exitCode] == [0]' "${evidence:-${manifest%.json}-evidence.json}")"
    emergency_teardown
    trap - EXIT HUP INT TERM
    rm -f "$run_output"
    [ "$test_pass" = true ] || fail 'PHYSICAL_C3_TEST_FAILED'
    ;;
  --collect-sanitized-evidence)
    validate_authority
    [ -n "$evidence" ] || fail 'EVIDENCE_PATH_MISSING'
    cluster="$(jq -r .runner.cluster_arn "$manifest")"
    task_arn="$(jq -r .runner.task_arn "$manifest")"
    task_json="$(aws ecs describe-tasks --cluster "$cluster" --tasks "$task_arn" --query 'tasks[0].{lastStatus:lastStatus,stopCode:stopCode,stoppedReason:stoppedReason,containers:containers[*].{name:name,exitCode:exitCode,reason:reason,imageDigest:imageDigest}}' --output json)"
    task_id="${task_arn##*/}"
    log_json="$(aws logs get-log-events --log-group-name /mxmed/staging/c3-runner --log-stream-name "c3/c3-runner/$task_id" --start-from-head --query 'events[].message' --output json)"
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
    cluster="$(jq -r .runner.cluster_arn "$manifest")"
    task_arn="$(jq -r .runner.task_arn "$manifest")"
    status="$(aws ecs describe-tasks --cluster "$cluster" --tasks "$task_arn" --query 'tasks[0].lastStatus' --output text)"
    if [ "$status" != 'STOPPED' ]; then
      aws ecs stop-task --cluster "$cluster" --task "$task_arn" --reason 'MXMED_C3_TERMINAL_CLEANUP' >/dev/null
    fi
    ;;
  *) fail 'MODE_REQUIRED' ;;
esac
