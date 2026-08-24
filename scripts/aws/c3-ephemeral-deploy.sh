#!/bin/sh
set -eu

BASELINE_PRODUCT_HEAD='1f507b61846b96caa34d390ee3a59779f65e4331'
EXPECTED_ACCOUNT='875691018466'
EXPECTED_REGION='mx-central-1'
EXPECTED_COST_CAP='5'
STACKS='mxmed-stg-c3-janitor mxmed-stg-network mxmed-stg-security mxmed-stg-session mxmed-stg-registry mxmed-stg-c3-runner'
AWS_WRITE_AUTHORITY='DIRECTOR_AUTHORIZES_SINGLE_USE_C3_AWS_WRITES'
PERMISSION_BOUNDARY_ARN='arn:aws:iam::875691018466:policy/MXMed-C3-Staging-PermissionBoundary'
ASSEMBLY='infra/aws/cdk.out/staging-c3-ephemeral/assembly-MxMedStagingC3Ephemeral'

fail() { printf '%s\n' "C3_DEPLOY_FAIL_CLOSED:$1" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || fail "COMMAND_MISSING:$1"; }
safe_value() { case "$1" in ''|*[!A-Za-z0-9_./:@+=,-]*) return 1;; esac; }

manifest=''
output=''
run_id=''
authorization_reference=''
start_window=''
failsafe_at=''
expires_at=''
budget_topic_arn=''
mode=''
no_execute=false
stack=''

while [ "$#" -gt 0 ]; do
  case "$1" in
    --prepare-run-manifest|--review-assembly|--prepare-change-sets|--execute-stack|--mark-first-resource-create-at|--resolve-and-seal-image-digest) mode="$1" ;;
    --no-execute) no_execute=true ;;
    --manifest) shift; manifest="${1:-}" ;;
    --output) shift; output="${1:-}" ;;
    --run-id) shift; run_id="${1:-}" ;;
    --authorization-reference) shift; authorization_reference="${1:-}" ;;
    --start-window) shift; start_window="${1:-}" ;;
    --failsafe-at) shift; failsafe_at="${1:-}" ;;
    --expires-at) shift; expires_at="${1:-}" ;;
    --budget-topic-arn) shift; budget_topic_arn="${1:-}" ;;
    --stack) shift; stack="${1:-}" ;;
    *) fail "UNKNOWN_ARGUMENT:$1" ;;
  esac
  shift
done

validate_manifest() {
  need jq
  [ -f "$manifest" ] || fail 'RUN_MANIFEST_MISSING'
  jq -e --arg account "$EXPECTED_ACCOUNT" --arg region "$EXPECTED_REGION" \
    '.schema == "mxmed.c3.ephemeral.run-manifest.v1"
     and (.expected_head | test("^[a-f0-9]{40}$")) and .account == $account and .region == $region
     and .activity_cost_cap_usd == 5
     and (.run_id | test("^c3-[a-z0-9][a-z0-9-]{5,62}$"))
     and (.director_authorization_reference | length > 0)
     and (.start_window_utc | test("Z$"))
     and (.failsafe_at_utc | test("Z$"))
     and (.expires_at_utc | test("Z$"))
     and (.image_digest == null or (.image_digest | test("^sha256:[a-f0-9]{64}$")))
     and ([.gates[]] | length == 10)
     and ([.gates[].name] | length == 10 and unique | length == 10)
     and ([.stack_names[] | select(startswith("mxmed-stg-") | not)] | length == 0)
     and ((tostring | test("production|mxmed-prd-|<[^>]+>|UNRESOLVED"; "i")) | not)' \
    "$manifest" >/dev/null || fail 'RUN_MANIFEST_CONTRACT_REJECTED'
}

set_gate() {
  gate="$1" value="$2" tmp="${manifest}.tmp.$$"
  jq --arg gate "$gate" --argjson value "$value" \
    '(.gates[] | select(.name == $gate) | .pass)=$value' "$manifest" >"$tmp"
  chmod 0600 "$tmp"
  mv "$tmp" "$manifest"
}

require_gates() {
  excluded_gate="${1:-}"
  jq -e --arg excluded "$excluded_gate" \
    '([.gates[] | select(.name != $excluded and .pass != true)] | length) == 0' "$manifest" >/dev/null \
    || fail 'REQUIRED_STOP_GATE_FALSE'
}

require_future_write_authority() {
  auth_file="${MXMED_C3_AWS_WRITE_AUTHORIZATION_FILE:-}"
  [ -n "$auth_file" ] && [ -f "$auth_file" ] || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_MISSING'
  validate_manifest
  jq -e --arg authority "$AWS_WRITE_AUTHORITY" --arg run "$(jq -r .run_id "$manifest")" \
    '.authorization == $authority and .run_id == $run and .single_use == true' \
    "$auth_file" >/dev/null || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_INVALID'
  current_head="$(git rev-parse HEAD)"
  [ "$current_head" = "$(jq -r .expected_head "$manifest")" ] || fail 'SOURCE_HEAD_MISMATCH'
  git merge-base --is-ancestor "$BASELINE_PRODUCT_HEAD" "$current_head" || fail 'BASELINE_PRODUCT_HEAD_NOT_ANCESTOR'
  [ -z "$(git status --porcelain)" ] || fail 'WORKTREE_NOT_CLEAN'
  [ "${AWS_ACCOUNT_ID:-}" = "$EXPECTED_ACCOUNT" ] || fail 'AWS_ACCOUNT_MISMATCH'
  [ "${AWS_REGION:-}" = "$EXPECTED_REGION" ] || fail 'AWS_REGION_MISMATCH'
  set_gate 'DIRECTOR_AWS_WRITE_AUTHORIZATION_PRESENT' true
}

start_partial_teardown() {
  failed_stack="$1" now="$(date -u +%Y-%m-%dT%H:%M:%SZ)" tmp="${manifest}.tmp.$$"
  jq --arg stack "$failed_stack" --arg now "$now" \
    '.deployment_failure_at_utc=$now | .deployment_failure_stack=$stack' "$manifest" >"$tmp"
  chmod 0600 "$tmp"
  mv "$tmp" "$manifest"
  [ "${MXMED_C3_TEARDOWN_PROFILE:-}" = 'mxmed-c3-stg-teardown' ] \
    || fail 'EXACT_TEARDOWN_PROFILE_REQUIRED'
  AWS_PROFILE="$MXMED_C3_TEARDOWN_PROFILE" \
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --execute-stack-deletes --manifest "$manifest" \
    || fail "PARTIAL_DEPLOYMENT_TEARDOWN_REQUIRES_ATTENTION:$failed_stack"
  fail "STACK_DEPLOYMENT_FAILED_AND_TEARDOWN_STARTED:$failed_stack"
}

case "$mode" in
  --prepare-run-manifest)
    need jq
    [ -n "$output" ] || fail 'OUTPUT_PATH_MISSING'
    case "$run_id" in c3-[a-z0-9][a-z0-9-][a-z0-9-][a-z0-9-][a-z0-9-][a-z0-9-]*) ;; *) fail 'RUN_ID_INVALID';; esac
    for value in "$authorization_reference" "$start_window" "$failsafe_at" "$expires_at" "$budget_topic_arn"; do
      safe_value "$value" || fail 'UNSAFE_OR_EMPTY_MANIFEST_VALUE'
    done
    start_epoch="$(date -u -j -f '%Y-%m-%dT%H:%M:%SZ' "$start_window" +%s 2>/dev/null)" || fail 'START_WINDOW_INVALID'
    failsafe_epoch="$(date -u -j -f '%Y-%m-%dT%H:%M:%SZ' "$failsafe_at" +%s 2>/dev/null)" || fail 'FAILSAFE_TIMESTAMP_INVALID'
    expires_epoch="$(date -u -j -f '%Y-%m-%dT%H:%M:%SZ' "$expires_at" +%s 2>/dev/null)" || fail 'EXPIRY_TIMESTAMP_INVALID'
    [ $((failsafe_epoch - start_epoch)) -le 79200 ] && [ "$failsafe_epoch" -gt "$start_epoch" ] || fail 'FAILSAFE_EXCEEDS_22_HOURS'
    [ $((expires_epoch - start_epoch)) -le 86400 ] && [ "$expires_epoch" -gt "$failsafe_epoch" ] || fail 'EXPIRY_EXCEEDS_24_HOURS'
    case "$budget_topic_arn" in arn:aws:sns:mx-central-1:875691018466:*) ;; *) fail 'BUDGET_TOPIC_OUT_OF_SCOPE';; esac
    template_sha="$(find infra/aws/cdk.out/staging-c3-ephemeral -name '*.template.json' -type f -exec shasum -a 256 {} \; 2>/dev/null | sort | shasum -a 256 | awk '{print $1}')"
    source_sha="$(git ls-files -s infra/aws scripts/aws | shasum -a 256 | awk '{print $1}')"
    script_sha="$(shasum -a 256 scripts/aws/c3-ephemeral-*.sh | sort | shasum -a 256 | awk '{print $1}')"
    for digest in "$template_sha" "$source_sha" "$script_sha"; do case "$digest" in [0-9a-f][0-9a-f]*) ;; *) fail 'LOCAL_DIGEST_MISSING';; esac; done
    current_head="$(git rev-parse HEAD)"
    git merge-base --is-ancestor "$BASELINE_PRODUCT_HEAD" "$current_head" || fail 'BASELINE_PRODUCT_HEAD_NOT_ANCESTOR'
    jq -n \
      --arg run "$run_id" --arg head "$current_head" --arg account "$EXPECTED_ACCOUNT" \
      --arg region "$EXPECTED_REGION" --arg auth "$authorization_reference" \
      --arg start "$start_window" --arg failsafe "$failsafe_at" --arg expires "$expires_at" \
      --arg topic "$budget_topic_arn" --arg template "$template_sha" --arg source "$source_sha" \
      --arg script "$script_sha" --argjson stacks "$(printf '%s\n' $STACKS | jq -R . | jq -s .)" \
      --arg boundary "$PERMISSION_BOUNDARY_ARN" \
      '{schema:"mxmed.c3.ephemeral.run-manifest.v1",run_id:$run,expected_head:$head,account:$account,region:$region,director_authorization_reference:$auth,activity_cost_cap_usd:5,start_window_utc:$start,failsafe_at_utc:$failsafe,expires_at_utc:$expires,budget_notification_topic_arn:$topic,c3_permission_boundary_arn:$boundary,template_sha256:$template,source_sha256:$source,script_sha256:$script,image_digest:null,approved_role_arns:{deploy:null,test_controller:null,teardown:null},stack_names:$stacks,expected_resource_counts:{total:107,data:0,storage:0,application_service:0},retained_resource_expectations:{count:13,physical_resources:[]},gates:["SOURCE_HEAD_MATCH","WORKTREE_CLEAN","DIRECTOR_AWS_WRITE_AUTHORIZATION_PRESENT","PRODUCTION_DENY_PROVEN","CHANGE_SET_EXACT_SCOPE_PASS","ESTIMATED_COST_WITHIN_USD_5_CAP","MANUAL_TEARDOWN_READY","AUTO_TEARDOWN_FAILSAFE_READY","RETAINED_RESOURCE_CLEANUP_READY","NONPRODUCTION_TARGET_PROVEN"]|map({name:.,pass:false}),first_resource_create_at_utc:null,test_terminal_at_utc:null,teardown_started_at_utc:null}' \
      >"$output"
    chmod 0600 "$output"
    printf '%s\n' "RUN_MANIFEST_PREPARED=$output"
    ;;
  --review-assembly)
    validate_manifest
    [ "$(git rev-parse HEAD)" = "$(jq -r .expected_head "$manifest")" ] || fail 'SOURCE_HEAD_MISMATCH'
    [ -z "$(git status --porcelain)" ] || fail 'WORKTREE_NOT_CLEAN'
    [ -f "$ASSEMBLY/manifest.json" ] || fail 'C3_ASSEMBLY_MISSING'
    actual_count="$(jq -s '[.[].Resources // {} | length] | add' "$ASSEMBLY"/*.template.json)"
    [ "$actual_count" = '107' ] || fail 'CANDIDATE_RESOURCE_COUNT_MISMATCH'
    template_text="$(jq -s -c '.' "$ASSEMBLY"/*.template.json)"
    printf '%s' "$template_text" | jq -e '
      ([.. | objects | .Type? | select(. == "AWS::RDS::DBInstance" or . == "AWS::ECS::Service")] | length) == 0
      and ([.. | objects | .Type? | select(. == "AWS::ECS::TaskDefinition")] | length) == 1
      and ([.. | objects | .Type? | select(. == "AWS::Scheduler::Schedule")] | length) == 2' >/dev/null \
      || fail 'C3_ASSEMBLY_SCOPE_INVALID'
    for gate in SOURCE_HEAD_MATCH WORKTREE_CLEAN PRODUCTION_DENY_PROVEN ESTIMATED_COST_WITHIN_USD_5_CAP MANUAL_TEARDOWN_READY AUTO_TEARDOWN_FAILSAFE_READY RETAINED_RESOURCE_CLEANUP_READY NONPRODUCTION_TARGET_PROVEN; do set_gate "$gate" true; done
    printf '%s\n' 'C3_ASSEMBLY_REVIEW=PASS'
    ;;
  --prepare-change-sets)
    [ "$no_execute" = true ] || fail 'NO_EXECUTE_FLAG_REQUIRED'
    require_future_write_authority
    require_gates 'CHANGE_SET_EXACT_SCOPE_PASS'
    need aws
    [ -d "$ASSEMBLY" ] || fail 'C3_ASSEMBLY_MISSING'
    for name in $STACKS; do
      case "$name" in
        mxmed-stg-c3-janitor)
          npx --no-install --prefix infra/aws cdk deploy "$name" --app "$ASSEMBLY" --exclusively --method prepare-change-set --change-set-name "$(jq -r .run_id "$manifest")-review" --require-approval never \
            --parameters "$name:RunId=$(jq -r .run_id "$manifest")" "$name:ExpiresAtUtc=$(jq -r .expires_at_utc "$manifest")" "$name:FailSafeScheduleExpression=at($(jq -r .failsafe_at_utc "$manifest" | sed 's/Z$//'))" "$name:JanitorDeleteScheduleExpression=at($(jq -r .expires_at_utc "$manifest" | sed 's/Z$//'))" "$name:BudgetNotificationTopicArn=$(jq -r .budget_notification_topic_arn "$manifest")" "$name:C3PermissionBoundaryArn=$PERMISSION_BOUNDARY_ARN"
          ;;
        mxmed-stg-c3-runner)
          digest="$(jq -r '.image_digest // empty' "$manifest")"; [ -n "$digest" ] || fail 'IMAGE_DIGEST_NOT_SEALED'
          npx --no-install --prefix infra/aws cdk deploy "$name" --app "$ASSEMBLY" --exclusively --method prepare-change-set --change-set-name "$(jq -r .run_id "$manifest")-review" --require-approval never \
            --parameters "$name:RunId=$(jq -r .run_id "$manifest")" "$name:ExpiresAtUtc=$(jq -r .expires_at_utc "$manifest")" "$name:RunnerImageDigest=$digest" "$name:C3PermissionBoundaryArn=$PERMISSION_BOUNDARY_ARN"
          ;;
        *) npx --no-install --prefix infra/aws cdk deploy "$name" --app "$ASSEMBLY" --exclusively --method prepare-change-set --change-set-name "$(jq -r .run_id "$manifest")-review" --require-approval never \
          --parameters "$name:RunId=$(jq -r .run_id "$manifest")" "$name:ExpiresAtUtc=$(jq -r .expires_at_utc "$manifest")" ;;
      esac
      expected="$(jq -r --arg stack "$name" '.artifacts[] | select(.properties.stackName == $stack) | .properties.templateFile' "$ASSEMBLY/manifest.json" | xargs -I{} jq '.Resources | length' "$ASSEMBLY/{}")"
      actual="$(aws cloudformation describe-change-set --stack-name "$name" --change-set-name "$(jq -r .run_id "$manifest")-review" --query 'length(Changes)' --output text)"
      [ "$actual" = "$expected" ] || fail "CHANGE_SET_RESOURCE_COUNT_MISMATCH:$name"
    done
    set_gate 'CHANGE_SET_EXACT_SCOPE_PASS' true
    ;;
  --execute-stack)
    require_future_write_authority
    require_gates
    case " $STACKS " in *" $stack "*) ;; *) fail 'STACK_OUT_OF_SCOPE';; esac
    aws cloudformation execute-change-set --stack-name "$stack" --change-set-name "$(jq -r .run_id "$manifest")-review" \
      || start_partial_teardown "$stack"
    aws cloudformation wait stack-create-complete --stack-name "$stack" \
      || start_partial_teardown "$stack"
    if [ "$stack" = 'mxmed-stg-c3-runner' ]; then
      outputs="$(aws cloudformation describe-stacks --stack-name "$stack" --query 'Stacks[0].Outputs' --output json)"
      runner="$(printf '%s' "$outputs" | jq '
        def value($key): first(.[] | select(.OutputKey == $key) | .OutputValue);
        {cluster_arn:value("RunnerClusterArn"),cluster_name:value("RunnerClusterName"),task_definition_arn:value("RunnerTaskDefinitionArn"),log_group_name:value("RunnerLogGroupName"),application_security_group_id:value("ApplicationSecurityGroupId"),private_subnet_ids:(value("PrivateAppSubnetIds")|split(","))}
      ')"
      printf '%s' "$runner" | jq -e '.private_subnet_ids|length==2' >/dev/null || fail 'RUNNER_OUTPUT_CONTRACT_INVALID'
      tmp="${manifest}.tmp.$$"; jq --argjson runner "$runner" '.runner=$runner' "$manifest" >"$tmp"; chmod 0600 "$tmp"; mv "$tmp" "$manifest"
    fi
    ;;
  --mark-first-resource-create-at)
    require_future_write_authority
    timestamp="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    tmp="${manifest}.tmp.$$"
    jq --arg timestamp "$timestamp" 'if .first_resource_create_at_utc == null then .first_resource_create_at_utc=$timestamp else error("FIRST_RESOURCE_TIMESTAMP_ALREADY_SEALED") end' "$manifest" >"$tmp"
    chmod 0600 "$tmp"
    mv "$tmp" "$manifest"
    ;;
  --resolve-and-seal-image-digest)
    require_future_write_authority
    digest="$(aws ecr describe-images --repository-name mxmed-stg-application --image-ids imageTag="$(jq -r .run_id "$manifest")" --query 'imageDetails[0].imageDigest' --output text)"
    case "$digest" in sha256:[0-9a-f][0-9a-f]*) ;; *) fail 'IMAGE_DIGEST_INVALID';; esac
    tmp="${manifest}.tmp.$$"
    jq --arg digest "$digest" 'if .image_digest == null then .image_digest=$digest else error("IMAGE_DIGEST_ALREADY_SEALED") end' "$manifest" >"$tmp"
    chmod 0600 "$tmp"
    mv "$tmp" "$manifest"
    ;;
  *) fail 'MODE_REQUIRED' ;;
esac
