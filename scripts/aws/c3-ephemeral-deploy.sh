#!/bin/sh
set -eu

EXPECTED_ACCOUNT='875691018466'
EXPECTED_REGION='mx-central-1'
BUDGETS_API_REGION='us-east-1'
BUDGET_VISIBILITY_DELAYS='1 2 4 8 10 10 10'
BUDGET_VISIBILITY_MAX_SECONDS=60
EXPECTED_COST_CAP='5'
EXPECTED_BUDGET_NOTIFICATION_TOPIC_ARN='arn:aws:sns:mx-central-1:875691018466:mxmed-stg-c3-notifications'
PRE_DIGEST_STACKS='mxmed-stg-c3-janitor mxmed-stg-network mxmed-stg-security mxmed-stg-session mxmed-stg-registry'
RUNNER_STACKS='mxmed-stg-c3-runner'
STACKS="$PRE_DIGEST_STACKS $RUNNER_STACKS"
AWS_WRITE_AUTHORITY='DIRECTOR_AUTHORIZES_SINGLE_USE_C3_AWS_WRITES'
PERMISSION_BOUNDARY_ARN='arn:aws:iam::875691018466:policy/MXMed-C3-Staging-PermissionBoundary'
ASSEMBLY='infra/aws/cdk.out/staging-c3-ephemeral/assembly-MxMedStagingC3Ephemeral'
TEMPLATE_BODY_MAX_BYTES='51200'
TEMPLATE_BUCKET='mxmed-stg-c3-cf-templates-875691018466-mx-central-1'
TEMPLATE_BUCKET_POLICY='infra/aws/policies/c3/MXMED_C3_TEMPLATE_BUCKET_POLICY.json'
DEPLOYMENT_MODE='DIRECT_CLOUDFORMATION_FROM_SEALED_TEMPLATES'
CFN_ROLE_PREFIX='arn:aws:iam::875691018466:role/MXMed-C3-CFN-'
CONTRACT_HELPER='scripts/aws/c3-runtime-contract.sh'

[ -f "$CONTRACT_HELPER" ] || { printf '%s\n' 'C3_DEPLOY_FAIL_CLOSED:RUNTIME_CONTRACT_HELPER_MISSING' >&2; exit 1; }
. "$CONTRACT_HELPER"

fail() { printf '%s\n' "C3_DEPLOY_FAIL_CLOSED:$1" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || fail "COMMAND_MISSING:$1"; }
safe_value() { case "$1" in ''|*[!A-Za-z0-9_./:@+=,-]*) return 1;; esac; }

budget_error_is_not_found() {
  case "$1" in *NotFoundException*|*not\ found*|*could\ not\ be\ found*) return 0;; *) return 1;; esac
}

normalize_budget_notifications() {
  jq -ce '[.[] | {
    ComparisonOperator,
    NotificationType,
    Threshold:(.Threshold|tonumber as $threshold
      | if $threshold == ($threshold|floor) then ($threshold|floor) else $threshold end),
    ThresholdType
  }] | sort_by(.Threshold,.NotificationType,.ComparisonOperator,.ThresholdType)'
}

normalize_budget_subscribers() {
  jq -ce '[.[] | {SubscriptionType,Address}] | sort_by(.SubscriptionType,.Address)'
}

probe_direct_budget_visibility() {
  visibility_stage='BUDGET'
  visibility_result='BUDGET_UNEXPECTED_ERROR'
  if actual_budget="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws budgets describe-budget --region "$BUDGETS_API_REGION" \
    --account-id "$EXPECTED_ACCOUNT" \
    --budget-name "$budget_name" --query Budget --output json 2>&1)"; then
    if ! printf '%s' "$actual_budget" | jq -e --argjson expected "$budget_json" '
      .BudgetName == $expected.BudgetName and .BudgetType == $expected.BudgetType and .TimeUnit == $expected.TimeUnit
      and .BudgetLimit.Unit == $expected.BudgetLimit.Unit and (.BudgetLimit.Amount|tonumber) == ($expected.BudgetLimit.Amount|tonumber)
      and .CostFilters == $expected.CostFilters' >/dev/null; then
      visibility_result='BUDGET_SEMANTIC_MISMATCH'
      return 20
    fi
  else
    if budget_error_is_not_found "$actual_budget"; then
      visibility_result='BUDGET_NOT_FOUND'
      return 10
    fi
    return 20
  fi

  visibility_stage='NOTIFICATIONS'
  visibility_result='NOTIFICATIONS_UNEXPECTED_ERROR'
  if actual_notifications_raw="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws budgets describe-notifications-for-budget \
    --region "$BUDGETS_API_REGION" --account-id "$EXPECTED_ACCOUNT" \
    --budget-name "$budget_name" --query Notifications --output json 2>&1)"; then
    if ! actual_notifications="$(printf '%s' "$actual_notifications_raw" | normalize_budget_notifications)"; then
      visibility_result='NOTIFICATIONS_SEMANTIC_MISMATCH'
      return 20
    fi
  else
    if budget_error_is_not_found "$actual_notifications_raw"; then
      visibility_result='NOTIFICATIONS_NOT_FOUND'
      return 10
    fi
    return 20
  fi
  if [ "$actual_notifications" != "$expected_notifications" ]; then
    missing_notifications="$(jq -cn --argjson actual "$actual_notifications" \
      --argjson expected "$expected_notifications" '$expected - $actual | length')"
    unexpected_notifications="$(jq -cn --argjson actual "$actual_notifications" \
      --argjson expected "$expected_notifications" '$actual - $expected | length')"
    if [ "$missing_notifications" -gt 0 ] && [ "$unexpected_notifications" -eq 0 ]; then
      visibility_result='NOTIFICATIONS_INCOMPLETE'
      return 10
    fi
    visibility_result='NOTIFICATIONS_SEMANTIC_MISMATCH'
    return 20
  fi

  visibility_stage='SUBSCRIBERS'
  notification_count="$(printf '%s' "$expected_notifications" | jq length)"
  notification_index=0
  while [ "$notification_index" -lt "$notification_count" ]; do
    notification="$(printf '%s' "$expected_notifications" | jq -c ".[$notification_index]")"
    visibility_result='SUBSCRIBERS_UNEXPECTED_ERROR'
    if actual_subscribers_raw="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws budgets describe-subscribers-for-notification \
      --region "$BUDGETS_API_REGION" --account-id "$EXPECTED_ACCOUNT" --budget-name "$budget_name" \
      --notification "$notification" --query Subscribers --output json 2>&1)"; then
      if ! actual_subscribers="$(printf '%s' "$actual_subscribers_raw" | normalize_budget_subscribers)"; then
        visibility_result='SUBSCRIBERS_SEMANTIC_MISMATCH'
        return 20
      fi
    else
      if budget_error_is_not_found "$actual_subscribers_raw"; then
        visibility_result='SUBSCRIBERS_NOT_FOUND'
        return 10
      fi
      return 20
    fi
    if [ "$actual_subscribers" != "$expected_subscribers" ]; then
      missing_subscribers="$(jq -cn --argjson actual "$actual_subscribers" \
        --argjson expected "$expected_subscribers" '$expected - $actual | length')"
      unexpected_subscribers="$(jq -cn --argjson actual "$actual_subscribers" \
        --argjson expected "$expected_subscribers" '$actual - $expected | length')"
      if [ "$missing_subscribers" -gt 0 ] && [ "$unexpected_subscribers" -eq 0 ]; then
        visibility_result='SUBSCRIBERS_INCOMPLETE'
        return 10
      fi
      visibility_result='SUBSCRIBERS_SEMANTIC_MISMATCH'
      return 20
    fi
    notification_index=$((notification_index + 1))
  done
  visibility_result='PASS'
  return 0
}

wait_for_direct_budget_visibility() {
  expected_notifications="$(printf '%s' "$notifications_json" \
    | jq -c '[.[].Notification]' | normalize_budget_notifications)" \
    || fail 'DIRECT_BUDGET_EXPECTED_NOTIFICATION_NORMALIZATION_FAILED'
  expected_subscribers="$(jq -cn --arg topic "$EXPECTED_BUDGET_NOTIFICATION_TOPIC_ARN" \
    '[{SubscriptionType:"SNS",Address:$topic}]')"
  visibility_started_epoch="$(date -u +%s)"
  for visibility_delay in $BUDGET_VISIBILITY_DELAYS final; do
    if probe_direct_budget_visibility; then
      visibility_status=0
    else
      visibility_status=$?
    fi
    visibility_now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    c3_record_direct_budget_visibility_attempt "$manifest" "$state" "$visibility_now" \
      "$visibility_stage" "$visibility_result" || return 2
    visibility_now_epoch="$(date -u +%s)"
    visibility_elapsed=$((visibility_now_epoch - visibility_started_epoch))
    if [ "$visibility_elapsed" -gt "$BUDGET_VISIBILITY_MAX_SECONDS" ]; then
      c3_record_direct_budget_visibility_timeout "$manifest" "$state" || return 2
      return 1
    fi
    if [ "$visibility_status" -eq 0 ]; then
      c3_record_direct_budget_visibility_stabilized "$manifest" "$state" || return 2
      return 0
    fi
    [ "$visibility_status" -eq 10 ] || return 2
    if [ "$visibility_delay" = final ]; then
      c3_record_direct_budget_visibility_timeout "$manifest" "$state" || return 2
      return 1
    fi
    visibility_remaining=$((BUDGET_VISIBILITY_MAX_SECONDS - visibility_elapsed))
    [ "$visibility_remaining" -gt 0 ] || {
      c3_record_direct_budget_visibility_timeout "$manifest" "$state" || return 2
      return 1
    }
    if [ "$visibility_delay" -gt "$visibility_remaining" ]; then
      visibility_delay="$visibility_remaining"
    fi
    sleep "$visibility_delay"
  done
  return 2
}

verify_fixed_security_secret_names_absent() {
  expected_names="$(c3_security_fixed_secret_names_json)"
  sealed_names="$(jq -c '.security_fixed_secret_name_precheck.names' "$manifest")" \
    || fail 'SECURITY_FIXED_SECRET_PRECHECK_CONTRACT_INVALID'
  [ "$sealed_names" = "$expected_names" ] || fail 'SECURITY_FIXED_SECRET_PRECHECK_SCOPE_MISMATCH'
  [ "$(printf '%s' "$sealed_names" | jq length)" = '4' ] \
    || fail 'SECURITY_FIXED_SECRET_PRECHECK_COUNT_INVALID'
  printf '%s' "$sealed_names" | jq -r '.[]' | while IFS= read -r secret_name; do
    [ -n "$secret_name" ] || fail 'SECURITY_FIXED_SECRET_PRECHECK_NAME_INVALID'
    if secret_state="$(AWS_PROFILE="$MXMED_C3_TEARDOWN_PROFILE" aws secretsmanager describe-secret \
      --region "$EXPECTED_REGION" --secret-id "$secret_name" --output json 2>&1)"; then
      lifecycle="$(printf '%s' "$secret_state" | jq -r \
        'if .DeletedDate == null then "ACTIVE" elif (.DeletedDate | type) == "string" then "SCHEDULED_FOR_DELETION" else "AMBIGUOUS" end' \
        2>/dev/null || printf AMBIGUOUS)"
      case "$lifecycle" in
        ACTIVE) fail "SECURITY_FIXED_SECRET_ACTIVE:$secret_name" ;;
        SCHEDULED_FOR_DELETION) fail "SECURITY_FIXED_SECRET_SCHEDULED_FOR_DELETION:$secret_name" ;;
        *) fail "SECURITY_FIXED_SECRET_LIFECYCLE_AMBIGUOUS:$secret_name" ;;
      esac
    else
      case "$secret_state" in
        *ResourceNotFoundException*) ;;
        *) fail "SECURITY_FIXED_SECRET_DESCRIBE_FAILED:$secret_name" ;;
      esac
    fi
  done
}

if [ "${MXMED_C3_SOURCE_BUDGET_HELPERS_ONLY:-}" = '1' ]; then
  return 0 2>/dev/null || exit 0
fi

manifest=''
state=''
output=''
run_uuid=''
run_id=''
authorization_reference=''
budget_topic_arn=''
build_inputs=''
image_source_revision=''
mode=''
no_execute=false
stack=''
change_set_phase=''

while [ "$#" -gt 0 ]; do
  case "$1" in
    --prepare-run-manifest|--review-assembly|--initialize-runtime-state|--prepare-template-transport|--prepare-change-sets|--execute-stack|--create-direct-budget|--build-push-and-seal-image|--resolve-and-seal-image-digest) mode="$1" ;;
    --no-execute) no_execute=true ;;
    --manifest) shift; manifest="${1:-}" ;;
    --state) shift; state="${1:-}" ;;
    --output) shift; output="${1:-}" ;;
    --run-uuid) shift; run_uuid="${1:-}" ;;
    --run-id) shift; run_id="${1:-}" ;;
    --authorization-reference) shift; authorization_reference="${1:-}" ;;
    --budget-topic-arn) shift; budget_topic_arn="${1:-}" ;;
    --build-inputs) shift; build_inputs="${1:-}" ;;
    --image-source-revision) shift; image_source_revision="${1:-}" ;;
    --stack) shift; stack="${1:-}" ;;
    --phase) shift; change_set_phase="${1:-}" ;;
    *) fail "UNKNOWN_ARGUMENT:$1" ;;
  esac
  shift
done

[ -z "$change_set_phase" ] || [ "$mode" = '--prepare-change-sets' ] \
  || fail 'CHANGE_SET_PREPARATION_PHASE_MODE_MISMATCH'

validate_manifest() {
  need jq
  c3_validate_manifest "$manifest" || fail 'RUN_MANIFEST_CONTRACT_REJECTED'
}

validate_fresh_authorization() {
  auth_file="${MXMED_C3_AWS_WRITE_AUTHORIZATION_FILE:-}"
  [ -n "$auth_file" ] && [ -f "$auth_file" ] || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_MISSING'
  validate_manifest
  jq -e --arg authority "$AWS_WRITE_AUTHORITY" --arg run "$(jq -r .run_id "$manifest")" \
    '.authorization == $authority and .run_id == $run and .single_use == true' \
    "$auth_file" >/dev/null || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_INVALID'
  current_head="$(git rev-parse HEAD)"
  [ "$current_head" = "$(jq -r .source_head "$manifest")" ] || fail 'SOURCE_HEAD_MISMATCH'
  [ -z "$(git status --porcelain)" ] || fail 'WORKTREE_NOT_CLEAN'
  [ "${AWS_ACCOUNT_ID:-}" = "$EXPECTED_ACCOUNT" ] || fail 'AWS_ACCOUNT_MISMATCH'
  [ "${AWS_REGION:-}" = "$EXPECTED_REGION" ] || fail 'AWS_REGION_MISMATCH'
  [ "${MXMED_C3_DEPLOY_PROFILE:-}" = 'mxmed-c3-stg-deploy' ] || fail 'EXACT_DEPLOY_PROFILE_REQUIRED'
  [ "${MXMED_C3_TEST_CONTROLLER_PROFILE:-}" = 'mxmed-c3-stg-test-controller' ] || fail 'EXACT_TEST_CONTROLLER_PROFILE_REQUIRED'
  [ "${MXMED_C3_TEARDOWN_PROFILE:-}" = 'mxmed-c3-stg-teardown' ] || fail 'EXACT_TEARDOWN_PROFILE_REQUIRED'
}

require_future_write_authority() {
  phase="$1"
  validate_fresh_authorization
  [ -n "$state" ] || fail 'RUNTIME_STATE_PATH_MISSING'
  c3_validate_phase "$phase" "$manifest" "$state" || fail "RUNTIME_STATE_PHASE_REJECTED:$phase"
  if [ "$phase" = 'PRE_FIRST_WRITE' ]; then
    verify_fixed_security_secret_names_absent
  fi
}

template_file_for_stack() {
  jq -r --arg stack "$1" \
    '.artifacts[] | select(.properties.stackName == $stack) | .properties.templateFile' \
    "$ASSEMBLY/manifest.json"
}

verify_sealed_templates() {
  for name in $STACKS; do
    file="$ASSEMBLY/$(template_file_for_stack "$name")"
    [ -f "$file" ] || fail "TEMPLATE_MISSING:$name"
    expected_sha="$(jq -r --arg stack "$name" '.templates[] | select(.stack_name == $stack) | .sha256' "$manifest")"
    actual_sha="$(shasum -a 256 "$file" | awk '{print $1}')"
    [ "$actual_sha" = "$expected_sha" ] || fail "TEMPLATE_HASH_MISMATCH:$name"
    expected_bytes="$(jq -r --arg stack "$name" '.templates[] | select(.stack_name == $stack) | .bytes' "$manifest")"
    actual_bytes="$(stat -f '%z' "$file")"
    [ "$actual_bytes" = "$expected_bytes" ] || fail "TEMPLATE_SIZE_MISMATCH:$name"
    if jq -e '.Parameters.BootstrapVersion? != null or .Rules.CheckBootstrapVersion? != null' "$file" >/dev/null \
      || grep -Eiq 'hnb659fds|/cdk-bootstrap/|CDKToolkit' "$file"; then
      fail "TEMPLATE_BOOTSTRAP_REFERENCE_REJECTED:$name"
    fi
    jq -e '[.Resources[]? | select(.Type == "AWS::Budgets::Budget")] | length == 0' "$file" >/dev/null \
      || fail "KNOWN_UNSUPPORTED_CFN_RESOURCE_TYPE_PRESENT:$name"
    transport="$(jq -r --arg stack "$name" '.templates[] | select(.stack_name == $stack) | .transport' "$manifest")"
    if [ "$actual_bytes" -gt "$TEMPLATE_BODY_MAX_BYTES" ]; then
      [ "$transport" = 'C3_TEMPLATE_S3_URL' ] || fail "OVERSIZE_TEMPLATE_BODY_REJECTED:$name"
    else
      [ "$transport" = 'TEMPLATE_BODY' ] || fail "TEMPLATE_TRANSPORT_MISMATCH:$name"
    fi
  done
}

create_direct_change_set() {
  name="$1"
  file="$2"
  transport="$3"
  role_arn="$(cfn_execution_role_for_stack "$name")"
  change_set="$(jq -r .run_id "$manifest")-review"
  set -- cloudformation create-change-set \
    --stack-name "$name" --change-set-name "$change_set" --change-set-type CREATE \
    --role-arn "$role_arn" \
    --capabilities CAPABILITY_NAMED_IAM \
    --description "MXMed C3 sealed direct-CloudFormation change set"
  if [ "$transport" = 'TEMPLATE_BODY' ]; then
    set -- "$@" --template-body "file://$file"
  else
    key="$(jq -r --arg stack "$name" '.templates[] | select(.stack_name == $stack) | .object_key' "$manifest")"
    checksum="$(jq -r --arg stack "$name" '.template_transport_objects[] | select(.stack_name == $stack) | .checksum_sha256' "$state")"
    [ "$checksum" != 'null' ] && [ -n "$checksum" ] \
      || start_partial_teardown "$name" "UNSEALED_TEMPLATE_UPLOAD_REJECTED:$name"
    actual_checksum="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws s3api head-object \
      --bucket "$TEMPLATE_BUCKET" --key "$key" --checksum-mode ENABLED \
      --query 'ChecksumSHA256' --output text)" \
      || start_partial_teardown "$name" "S3_TEMPLATE_CHECKSUM_READ_FAILED:$name"
    [ "$actual_checksum" = "$checksum" ] \
      || start_partial_teardown "$name" "S3_TEMPLATE_CHECKSUM_MISMATCH:$name"
    set -- "$@" --template-url "https://$TEMPLATE_BUCKET.s3.$EXPECTED_REGION.amazonaws.com/$key"
  fi
  case "$name" in
    mxmed-stg-c3-janitor)
      set -- "$@" --parameters \
        "ParameterKey=RunId,ParameterValue=$(jq -r .run_id "$manifest")" \
        "ParameterKey=ExpiresAtUtc,ParameterValue=$(jq -r .hard_cap_at_utc "$state")" \
        "ParameterKey=FailSafeScheduleExpression,ParameterValue=at($(jq -r .failsafe_at_utc "$state" | sed 's/Z$//'))" \
        "ParameterKey=JanitorDeleteScheduleExpression,ParameterValue=at($(jq -r .hard_cap_at_utc "$state" | sed 's/Z$//'))" \
        "ParameterKey=C3PermissionBoundaryArn,ParameterValue=$PERMISSION_BOUNDARY_ARN"
      ;;
    mxmed-stg-c3-runner)
      digest="$(jq -r .physical_ecr_image_digest "$state")"
      case "$digest" in
        sha256:[0-9a-f][0-9a-f]*) ;;
        *) start_partial_teardown "$name" 'IMAGE_DIGEST_NOT_SEALED' ;;
      esac
      set -- "$@" --parameters \
        "ParameterKey=RunId,ParameterValue=$(jq -r .run_id "$manifest")" \
        "ParameterKey=ExpiresAtUtc,ParameterValue=$(jq -r .hard_cap_at_utc "$state")" \
        "ParameterKey=RunnerImageDigest,ParameterValue=$digest" \
        "ParameterKey=SourceRevision,ParameterValue=$(jq -r .source_head "$manifest")" \
        "ParameterKey=C3PermissionBoundaryArn,ParameterValue=$PERMISSION_BOUNDARY_ARN"
      ;;
    *)
      set -- "$@" --parameters \
        "ParameterKey=RunId,ParameterValue=$(jq -r .run_id "$manifest")" \
        "ParameterKey=ExpiresAtUtc,ParameterValue=$(jq -r .hard_cap_at_utc "$state")"
      ;;
  esac
  AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws "$@" >/dev/null \
    || start_partial_teardown "$name" "CHANGE_SET_CREATE_FAILED:$name"
  AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws cloudformation wait change-set-create-complete \
    --stack-name "$name" --change-set-name "$change_set" \
    || start_partial_teardown "$name" "CHANGE_SET_CREATE_WAIT_FAILED:$name"
}

cfn_execution_role_for_stack() {
  case "$1" in
    mxmed-stg-network) suffix='Network' ;;
    mxmed-stg-security) suffix='Security' ;;
    mxmed-stg-session) suffix='Session' ;;
    mxmed-stg-registry) suffix='Registry' ;;
    mxmed-stg-c3-runner) suffix='Runner' ;;
    mxmed-stg-c3-janitor) suffix='Janitor' ;;
    *) fail "CFN_EXECUTION_ROLE_STACK_UNMAPPED:$1" ;;
  esac
  role_arn="${CFN_ROLE_PREFIX}${suffix}"
  case "$role_arn" in
    arn:aws:iam::875691018466:role/MXMed-C3-CFN-*) printf '%s\n' "$role_arn" ;;
    *) fail "CFN_EXECUTION_ROLE_INVALID:$1" ;;
  esac
}

start_partial_teardown() {
  failed_stack="$1" failure_reason="${2:-STACK_DEPLOYMENT_FAILED}" now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  c3_atomic_state_update "$manifest" "$state" \
    '.deployment_failure_at_utc=$now | .deployment_failure_stack=$stack | .phase="ABORTING"' \
    --arg stack "$failed_stack" --arg now "$now"
  [ "${MXMED_C3_TEARDOWN_PROFILE:-}" = 'mxmed-c3-stg-teardown' ] \
    || fail 'EXACT_TEARDOWN_PROFILE_REQUIRED'
  AWS_PROFILE="$MXMED_C3_TEARDOWN_PROFILE" \
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --execute-stack-deletes --manifest "$manifest" --state "$state" \
    || fail "PARTIAL_DEPLOYMENT_TEARDOWN_REQUIRES_ATTENTION:$failed_stack"
  fail "STACK_DEPLOYMENT_FAILED_AND_TEARDOWN_STARTED:$failed_stack:$failure_reason"
}

case "$mode" in
  --prepare-run-manifest)
    need jq
    [ -n "$output" ] || fail 'OUTPUT_PATH_MISSING'
    printf '%s' "$run_uuid" | grep -Eq '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$' \
      || fail 'RUN_UUID_INVALID'
    [ "$run_id" = "c3-$run_uuid" ] || fail 'RUN_ID_NOT_PREFIXED_UUIDV4'
    for value in "$authorization_reference" "$run_uuid" "$run_id" "$budget_topic_arn"; do
      safe_value "$value" || fail 'UNSAFE_OR_EMPTY_MANIFEST_VALUE'
    done
    [ "$budget_topic_arn" = "$EXPECTED_BUDGET_NOTIFICATION_TOPIC_ARN" ] \
      || fail 'BUDGET_NOTIFICATION_TOPIC_ARN_INVALID'
    case "$budget_topic_arn" in arn:aws:sns:mx-central-1:875691018466:*) ;; *) fail 'BUDGET_TOPIC_OUT_OF_SCOPE';; esac
    [ -f "$build_inputs" ] || fail 'IMAGE_BUILD_INPUTS_MISSING'
    jq -e . "$build_inputs" >/dev/null || fail 'IMAGE_BUILD_INPUTS_JSON_INVALID'
    [ -f "$ASSEMBLY/manifest.json" ] || fail 'C3_ASSEMBLY_MISSING'
    templates='[]'
    expected_graph='{}'
    for name in $STACKS; do
      template_file="$(template_file_for_stack "$name")"
      template_path="$ASSEMBLY/$template_file"
      [ -f "$template_path" ] || fail "TEMPLATE_MISSING:$name"
      bytes="$(stat -f '%z' "$template_path")"
      sha="$(shasum -a 256 "$template_path" | awk '{print $1}')"
      if [ "$bytes" -gt "$TEMPLATE_BODY_MAX_BYTES" ]; then
        transport='C3_TEMPLATE_S3_URL'
        object_key="$run_id/$name/$sha$C3_OBJECT_KEY_SUFFIX"
      else
        transport='TEMPLATE_BODY'
        object_key=''
      fi
      templates="$(printf '%s' "$templates" | jq \
        --arg stack "$name" --arg file "$template_file" --arg sha "$sha" \
        --arg transport "$transport" --arg object "$object_key" --argjson bytes "$bytes" \
        '. + [{stack_name:$stack,template_file:$file,bytes:$bytes,sha256:$sha,transport:$transport,object_key:(if $object == "" then null else $object end)}]')"
      resource_count="$(jq '.Resources | length' "$template_path")"
      expected_graph="$(printf '%s' "$expected_graph" | jq --arg stack "$name" --argjson count "$resource_count" '.[$stack]=$count')"
    done
    template_sha="$(printf '%s' "$templates" | jq -r '.[].sha256' | sort | shasum -a 256 | awk '{print $1}')"
    source_sha="$(git ls-files -s infra/aws scripts/aws | shasum -a 256 | awk '{print $1}')"
    script_sha="$(shasum -a 256 scripts/aws/c3-*.sh | sort | shasum -a 256 | awk '{print $1}')"
    policy_hashes='{}'
    for policy in infra/aws/policies/c3/*.json; do
      [ -f "$policy" ] || fail 'C3_POLICY_SET_MISSING'
      policy_hash="$(shasum -a 256 "$policy" | awk '{print $1}')"
      policy_hashes="$(printf '%s' "$policy_hashes" | jq --arg path "$policy" --arg sha "$policy_hash" '.[$path]=$sha')"
    done
    for digest in "$template_sha" "$source_sha" "$script_sha"; do case "$digest" in [0-9a-f][0-9a-f]*) ;; *) fail 'LOCAL_DIGEST_MISSING';; esac; done
    current_head="$(git rev-parse HEAD)"
    image_inputs="$(jq -c . "$build_inputs")"
    [ "$(printf '%s' "$image_inputs" | jq -r .source_revision)" = "$current_head" ] || fail 'IMAGE_SOURCE_REVISION_HEAD_MISMATCH'
    gates="$(c3_gate_definitions_json)"
    fixed_security_names="$(c3_security_fixed_secret_names_json)"
    retained_expectations="$(c3_retained_resource_expectations_json)"
    direct_budget="$(jq -cn --arg account "$EXPECTED_ACCOUNT" --arg region "$BUDGETS_API_REGION" \
      --arg name "mxmed-stg-c3-$run_id" --arg topic "$budget_topic_arn" '
      {api_region:$region,account_id:$account,budget_name_format:"mxmed-stg-c3-${RUN_ID}",budget_name:$name,
       runtime_object_count:1,cleanup_contract:"EXACT_RUN_ID_BOUND_DESCRIBE_DELETE_ABSENCE",
       budget:{BudgetName:$name,BudgetType:"COST",TimeUnit:"MONTHLY",BudgetLimit:{Amount:"5",Unit:"USD"},CostFilters:{TagKeyValue:["user:Phase$C3"]}},
       notifications_with_subscribers:([1,3,5]|map({Notification:{ComparisonOperator:"GREATER_THAN",NotificationType:"ACTUAL",Threshold:.,ThresholdType:"ABSOLUTE_VALUE"},Subscribers:[{SubscriptionType:"SNS",Address:$topic}]}))}')"
    direct_budget_sha="$(printf '%s\n' "$direct_budget" | jq -cS '{budget,notifications_with_subscribers}' | shasum -a 256 | awk '{print $1}')"
    direct_budget="$(printf '%s' "$direct_budget" | jq --arg sha "$direct_budget_sha" '.+{payload_sha256:$sha}')"
    jq -n \
      --arg run_uuid "$run_uuid" --arg run "$run_id" --arg head "$current_head" --arg account "$EXPECTED_ACCOUNT" \
      --arg region "$EXPECTED_REGION" --arg auth "$authorization_reference" \
      --arg topic "$budget_topic_arn" --arg template "$template_sha" --arg source "$source_sha" \
      --arg script "$script_sha" --argjson stacks "$(printf '%s\n' $STACKS | jq -R . | jq -s .)" \
      --arg boundary "$PERMISSION_BOUNDARY_ARN" \
      --argjson templates "$templates" --arg deployment_mode "$DEPLOYMENT_MODE" \
      --arg template_bucket "$TEMPLATE_BUCKET" --arg pending "$C3_PENDING_RUNTIME_RESOLUTION" \
      --arg suffix "$C3_OBJECT_KEY_SUFFIX" --argjson gates "$gates" --argjson fixed_security_names "$fixed_security_names" \
      --argjson retained_expectations "$retained_expectations" --argjson image_inputs "$image_inputs" \
      --argjson policy_hashes "$policy_hashes" --argjson expected_graph "$expected_graph" --argjson direct_budget "$direct_budget" \
      '{
        schema:"mxmed.c3.ephemeral.sealed-run-manifest.v3",
        run_uuid:$run_uuid,run_id:$run,source_head:$head,account:$account,region:$region,
        deployment_mode:$deployment_mode,director_authorization_reference:$auth,
        activity_cost_cap_usd:5,budget_notification_topic_arn:$topic,
        direct_budget_authority:$direct_budget,
        c3_permission_boundary_arn:$boundary,
        runtime_clock_contract:{origin:"FIRST_SUCCESSFUL_RUNTIME_AWS_MUTATION",failsafe_offset_hours:22,hard_cap_offset_hours:24,teardown_start_max_delay_seconds:300},
        pending_runtime_fields:{first_runtime_mutation_at_utc:$pending,failsafe_at_utc:$pending,hard_cap_at_utc:$pending,physical_ecr_image_digest:$pending},
        object_key_contract:{canonical_format:("RUN_ID/STACK_NAME/TEMPLATE_SHA256"+$suffix),suffix:$suffix,binds_run_id:true,binds_stack_name:true,binds_template_sha256:true,path_traversal_safe:true},
        gate_definitions:$gates,
        security_fixed_secret_name_precheck:{required:true,integrated_gate:"SEALED_TEMPLATE_AND_RESOURCE_SCOPE_PASS",names:$fixed_security_names,required_state:"ABSENT",active_blocks_run:true,scheduled_for_deletion_blocks_run:true,ambiguous_error_blocks_run:true},
        phase_requirements:{pre_first_write:{required_pass_count:11,gate_12_state:"PENDING_RUNTIME"},pre_runner:{required_pass_count:12,gate_12_state:"PASS"}},
        template_sha256:$template,templates:$templates,
        template_transport:{bucket_name:$template_bucket,region:$region,public_access_blocked:true,default_encryption:"AES256",versioning:false,ephemeral:true,delete_after_c3:true},
        source_sha256:$source,script_sha256:$script,policy_sha256:$policy_hashes,
        image_build_inputs:$image_inputs,expected_resource_graph:$expected_graph,
        approved_role_profiles:{deploy:"mxmed-c3-stg-deploy",test_controller:"mxmed-c3-stg-test-controller",teardown:"mxmed-c3-stg-teardown"},
        cfn_execution_role_arns:{"mxmed-stg-network":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Network","mxmed-stg-security":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Security","mxmed-stg-session":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Session","mxmed-stg-registry":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Registry","mxmed-stg-c3-runner":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Runner","mxmed-stg-c3-janitor":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Janitor"},
        stack_names:$stacks,
        expected_resource_counts:{cloudformation:106,direct_runtime:1,total_authorized:107,data:0,storage:0,application_service:0,public_runner_ip:0},
        retained_resource_expectations:{count:13,physical_resources:$retained_expectations}
      }' \
      >"$output"
    chmod 0600 "$output"
    c3_validate_phase PRE_RUNTIME_SEAL "$output" || fail 'PRE_RUNTIME_SEAL_VALIDATION_FAILED'
    printf '%s\n' "RUN_MANIFEST_PREPARED=$output"
    ;;
  --review-assembly)
    validate_manifest
    [ "$(git rev-parse HEAD)" = "$(jq -r .source_head "$manifest")" ] || fail 'SOURCE_HEAD_MISMATCH'
    [ -z "$(git status --porcelain)" ] || fail 'WORKTREE_NOT_CLEAN'
    [ -f "$ASSEMBLY/manifest.json" ] || fail 'C3_ASSEMBLY_MISSING'
    verify_sealed_templates
    actual_count="$(jq -s '[.[].Resources // {} | length] | add' "$ASSEMBLY"/*.template.json)"
    [ "$actual_count" = '106' ] || fail 'CANDIDATE_RESOURCE_COUNT_MISMATCH'
    template_text="$(jq -s -c '.' "$ASSEMBLY"/*.template.json)"
    printf '%s' "$template_text" | jq -e '
      ([.. | objects | .Type? | select(. == "AWS::RDS::DBInstance" or . == "AWS::ECS::Service")] | length) == 0
      and ([.. | objects | .Type? | select(. == "AWS::ECS::TaskDefinition")] | length) == 1
      and ([.. | objects | .Type? | select(. == "AWS::Scheduler::Schedule")] | length) == 2' >/dev/null \
      || fail 'C3_ASSEMBLY_SCOPE_INVALID'
    printf '%s\n' 'C3_ASSEMBLY_REVIEW=PASS'
    ;;
  --initialize-runtime-state)
    validate_fresh_authorization
    [ -n "$state" ] || fail 'RUNTIME_STATE_PATH_MISSING'
    c3_initialize_state "$manifest" "$state" || fail 'RUNTIME_STATE_INITIALIZATION_FAILED'
    printf '%s\n' "C3_RUNTIME_STATE_INITIALIZED=$state"
    ;;
  --prepare-template-transport)
    require_future_write_authority PRE_FIRST_WRITE
    need aws
    need openssl
    verify_sealed_templates
    [ -f "$TEMPLATE_BUCKET_POLICY" ] || fail 'TEMPLATE_BUCKET_POLICY_MISSING'
    AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws s3api create-bucket \
      --bucket "$TEMPLATE_BUCKET" --region "$EXPECTED_REGION" \
      --create-bucket-configuration "LocationConstraint=$EXPECTED_REGION" >/dev/null
    if ! c3_record_first_runtime_mutation "$manifest" "$state" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"; then
      AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws s3api delete-bucket --bucket "$TEMPLATE_BUCKET" \
        || fail 'FIRST_RUNTIME_MUTATION_CLOCK_AND_EMPTY_BUCKET_ROLLBACK_FAILED'
      fail 'FIRST_RUNTIME_MUTATION_CLOCK_SEAL_FAILED_EMPTY_BUCKET_ROLLED_BACK'
    fi
    AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws s3api put-public-access-block \
      --bucket "$TEMPLATE_BUCKET" \
      --public-access-block-configuration 'BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true'
    AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws s3api put-bucket-encryption \
      --bucket "$TEMPLATE_BUCKET" \
      --server-side-encryption-configuration '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"},"BucketKeyEnabled":false}]}'
    AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws s3api put-bucket-tagging \
      --bucket "$TEMPLATE_BUCKET" \
      --tagging "TagSet=[{Key=Project,Value=mxmed},{Key=Environment,Value=staging},{Key=Phase,Value=C3},{Key=Ephemeral,Value=true},{Key=RunId,Value=$(jq -r .run_id "$manifest")},{Key=ExpiresAt,Value=$(jq -r .hard_cap_at_utc "$state")}]"
    AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws s3api put-bucket-policy \
      --bucket "$TEMPLATE_BUCKET" --policy "file://$TEMPLATE_BUCKET_POLICY"
    block_state="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws s3api get-public-access-block --bucket "$TEMPLATE_BUCKET" --output json)"
    printf '%s' "$block_state" | jq -e '.PublicAccessBlockConfiguration | [.BlockPublicAcls,.IgnorePublicAcls,.BlockPublicPolicy,.RestrictPublicBuckets] | all' >/dev/null \
      || fail 'TEMPLATE_BUCKET_PUBLIC_ACCESS_BLOCK_INVALID'
    versioning="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws s3api get-bucket-versioning --bucket "$TEMPLATE_BUCKET" --query 'Status' --output text)"
    [ "$versioning" = 'None' ] || [ -z "$versioning" ] || fail 'TEMPLATE_BUCKET_VERSIONING_MUST_BE_DISABLED'
    for name in $STACKS; do
      transport="$(jq -r --arg stack "$name" '.templates[] | select(.stack_name == $stack) | .transport' "$manifest")"
      [ "$transport" = 'C3_TEMPLATE_S3_URL' ] || continue
      file="$ASSEMBLY/$(template_file_for_stack "$name")"
      key="$(jq -r --arg stack "$name" '.templates[] | select(.stack_name == $stack) | .object_key' "$manifest")"
      checksum="$(openssl dgst -sha256 -binary "$file" | openssl base64 -A)"
      response="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws s3api put-object \
        --bucket "$TEMPLATE_BUCKET" --key "$key" --body "$file" \
        --checksum-algorithm SHA256 --checksum-sha256 "$checksum" \
        --content-type application/json --output json)"
      [ "$(printf '%s' "$response" | jq -r .ChecksumSHA256)" = "$checksum" ] \
        || fail "TEMPLATE_UPLOAD_CHECKSUM_MISMATCH:$name"
      c3_atomic_state_update "$manifest" "$state" \
        '.template_transport_objects += [{stack_name:$stack,key:$key,checksum_sha256:$checksum}]' \
        --arg stack "$name" --arg checksum "$checksum" --arg key "$key"
    done
    printf '%s\n' 'C3_TEMPLATE_TRANSPORT_PREPARED=PASS'
    ;;
  --prepare-change-sets)
    [ "$no_execute" = true ] || fail 'NO_EXECUTE_FLAG_REQUIRED'
    case "$change_set_phase" in
      pre-digest)
        require_future_write_authority POST_FIRST_RUNTIME_MUTATION
        [ "$(c3_gate_state "$state" ECR_DIGEST_SEALED_BEFORE_RUNNER)" = "$C3_PENDING_RUNTIME" ] \
          || fail 'PRE_DIGEST_CHANGE_SET_GATE_12_NOT_PENDING'
        prepare_stacks="$PRE_DIGEST_STACKS"
        ;;
      runner)
        require_future_write_authority PRE_RUNNER
        prepare_stacks="$RUNNER_STACKS"
        ;;
      *) fail 'CHANGE_SET_PREPARATION_PHASE_REQUIRED' ;;
    esac
    need aws
    [ -d "$ASSEMBLY" ] || fail 'C3_ASSEMBLY_MISSING'
    verify_sealed_templates
    for name in $prepare_stacks; do
      file="$ASSEMBLY/$(template_file_for_stack "$name")"
      transport="$(jq -r --arg stack "$name" '.templates[] | select(.stack_name == $stack) | .transport' "$manifest")"
      create_direct_change_set "$name" "$file" "$transport"
      expected="$(jq '.Resources | length' "$file")"
      actual="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws cloudformation describe-change-set \
        --stack-name "$name" --change-set-name "$(jq -r .run_id "$manifest")-review" \
        --query 'length(Changes)' --output text)" \
        || start_partial_teardown "$name" "CHANGE_SET_DESCRIBE_FAILED:$name"
      [ "$actual" = "$expected" ] \
        || start_partial_teardown "$name" "CHANGE_SET_RESOURCE_COUNT_MISMATCH:$name"
      expected_semantic_sha="$(jq -S -c . "$file" | shasum -a 256 | awk '{print $1}')"
      actual_semantic_sha="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws cloudformation get-template \
        --stack-name "$name" --change-set-name "$(jq -r .run_id "$manifest")-review" --output json \
        | jq -S -c .TemplateBody | shasum -a 256 | awk '{print $1}')" \
        || start_partial_teardown "$name" "CHANGE_SET_TEMPLATE_READ_FAILED:$name"
      [ "$actual_semantic_sha" = "$expected_semantic_sha" ] \
        || start_partial_teardown "$name" "CHANGE_SET_TEMPLATE_SEMANTIC_HASH_MISMATCH:$name"
      c3_atomic_state_update "$manifest" "$state" \
        '.change_set_template_semantic_sha256[$stack]=$sha' \
        --arg stack "$name" --arg sha "$actual_semantic_sha"
    done
    ;;
  --execute-stack)
    case " $STACKS " in *" $stack "*) ;; *) fail 'STACK_OUT_OF_SCOPE';; esac
    if [ "$stack" = 'mxmed-stg-c3-runner' ]; then
      require_future_write_authority PRE_RUNNER
    else
      require_future_write_authority POST_FIRST_RUNTIME_MUTATION
    fi
    if [ "$stack" != 'mxmed-stg-c3-janitor' ]; then
      [ "$(jq -r .failsafe_active "$state")" = 'true' ] || fail 'FAILSAFE_NOT_ACTIVE_FORWARD_PROGRESS_REJECTED'
    fi
    AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws cloudformation execute-change-set --stack-name "$stack" --change-set-name "$(jq -r .run_id "$manifest")-review" \
      || start_partial_teardown "$stack"
    AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws cloudformation wait stack-create-complete --stack-name "$stack" \
      || start_partial_teardown "$stack"
    stack_id="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws cloudformation describe-stacks --stack-name "$stack" --query 'Stacks[0].StackId' --output text)"
    c3_atomic_state_update "$manifest" "$state" \
      '.created_resource_ids += [{type:"cloudformation-stack",stack_name:$stack,id:$id}]' \
      --arg stack "$stack" --arg id "$stack_id"
    if [ "$stack" = 'mxmed-stg-c3-janitor' ]; then
      janitor_resources="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws cloudformation describe-stack-resources --stack-name "$stack" --output json)"
      printf '%s' "$janitor_resources" | jq -e '
        ([.StackResources[] | select(.ResourceType=="AWS::Scheduler::Schedule" and .ResourceStatus=="CREATE_COMPLETE")] | length) == 2
        and ([.StackResources[] | select(.ResourceType=="AWS::StepFunctions::StateMachine" and .ResourceStatus=="CREATE_COMPLETE")] | length) == 1
      ' >/dev/null || start_partial_teardown "$stack"
      c3_atomic_state_update "$manifest" "$state" '.failsafe_active=true'
    fi
    if [ "$stack" = 'mxmed-stg-c3-runner' ]; then
      outputs="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws cloudformation describe-stacks --stack-name "$stack" --query 'Stacks[0].Outputs' --output json)"
      runner="$(printf '%s' "$outputs" | jq '
        def value($key): first(.[] | select(.OutputKey == $key) | .OutputValue);
        {cluster_arn:value("RunnerClusterArn"),cluster_name:value("RunnerClusterName"),task_definition_arn:value("RunnerTaskDefinitionArn"),log_group_name:value("RunnerLogGroupName"),application_security_group_id:value("ApplicationSecurityGroupId"),private_subnet_ids:(value("PrivateAppSubnetIds")|split(","))}
      ')"
      printf '%s' "$runner" | jq -e '.private_subnet_ids|length==2' >/dev/null || fail 'RUNNER_OUTPUT_CONTRACT_INVALID'
      c3_atomic_state_update "$manifest" "$state" '.runner=$runner' --argjson runner "$runner"
    fi
    ;;
  --create-direct-budget)
    require_future_write_authority POST_FIRST_RUNTIME_MUTATION
    need aws
    [ "$(jq -r .failsafe_active "$state")" = true ] || fail 'FAILSAFE_NOT_ACTIVE_DIRECT_BUDGET_REJECTED'
    [ "$(jq -r .direct_budget_created "$state")" = false ] || fail 'DIRECT_BUDGET_ALREADY_CREATED'
    budget_name="$(jq -r .direct_budget_authority.budget_name "$manifest")"
    [ "$budget_name" = "mxmed-stg-c3-$(jq -r .run_id "$manifest")" ] || fail 'DIRECT_BUDGET_NAME_NOT_EXACT_RUN_BOUND'
    budget_json="$(jq -c .direct_budget_authority.budget "$manifest")"
    notifications_json="$(jq -c .direct_budget_authority.notifications_with_subscribers "$manifest")"
    AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws budgets create-budget --region "$BUDGETS_API_REGION" \
      --account-id "$EXPECTED_ACCOUNT" --budget "$budget_json" \
      --notifications-with-subscribers "$notifications_json" \
      || start_partial_teardown direct-budget 'DIRECT_BUDGET_CREATE_FAILED'
    c3_record_direct_budget_created "$manifest" "$state" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
      || start_partial_teardown direct-budget 'DIRECT_BUDGET_CREATE_STATE_SEAL_FAILED'
    if wait_for_direct_budget_visibility; then
      :
    else
      visibility_wait_status=$?
      if [ "$visibility_wait_status" -eq 1 ]; then
        start_partial_teardown direct-budget 'DIRECT_BUDGET_VISIBILITY_TIMEOUT'
      fi
      start_partial_teardown direct-budget "DIRECT_BUDGET_VISIBILITY_FAIL_CLOSED:$visibility_result"
    fi
    ;;
  --build-push-and-seal-image)
    require_future_write_authority POST_FIRST_RUNTIME_MUTATION
    need aws
    need docker
    [ "$(jq -r .failsafe_active "$state")" = 'true' ] || fail 'FAILSAFE_NOT_ACTIVE_IMAGE_PUSH_REJECTED'
    image_source_revision="$(jq -r .source_head "$manifest")"
    [ "$(shasum -a 256 infra/aws/c3-runner/Dockerfile | awk '{print $1}')" = "$(jq -r .image_build_inputs.dockerfile_sha256 "$manifest")" ] \
      || fail 'DOCKERFILE_SHA256_MISMATCH'
    [ "$(shasum -a 256 infra/aws/c3-runner/entrypoint.sh | awk '{print $1}')" = "$(jq -r .image_build_inputs.entrypoint_sha256 "$manifest")" ] \
      || fail 'ENTRYPOINT_SHA256_MISMATCH'
    repository_uri="$(AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws cloudformation describe-stacks \
      --stack-name mxmed-stg-registry \
      --query 'Stacks[0].Outputs[?OutputKey==`ApplicationRepositoryUri`].OutputValue | [0]' \
      --output text)"
    [ "$repository_uri" = '875691018466.dkr.ecr.mx-central-1.amazonaws.com/mxmed-stg-application' ] \
      || fail 'PHYSICAL_ECR_REPOSITORY_URI_MISMATCH'
    image_tag="$repository_uri:$(jq -r .run_id "$manifest")"
    AWS_PROFILE="$MXMED_C3_DEPLOY_PROFILE" aws ecr get-login-password --region "$EXPECTED_REGION" \
      | docker login --username AWS --password-stdin "${repository_uri%%/*}" >/dev/null
    docker build \
      --file infra/aws/c3-runner/Dockerfile \
      --build-arg "PHP_BASE_IMAGE=$(jq -r .image_build_inputs.php_base_image_reference "$manifest")" \
      --build-arg "PHPREDIS_VERSION=$(jq -r .image_build_inputs.phpredis_version "$manifest")" \
      --build-arg "AWS_CLI_ZIP_SHA256=$(jq -r .image_build_inputs.aws_cli_archive_sha256 "$manifest")" \
      --build-arg "SOURCE_REVISION=$(jq -r .source_head "$manifest")" \
      --tag "$image_tag" .
    built_source_revision="$(docker image inspect "$image_tag" \
      --format '{{ index .Config.Labels "org.opencontainers.image.revision" }}')"
    [ "$built_source_revision" = "$image_source_revision" ] || fail 'BUILT_IMAGE_SOURCE_REVISION_MISMATCH'
    docker push "$image_tag"
    digest="$(AWS_PROFILE="$MXMED_C3_TEARDOWN_PROFILE" aws ecr describe-images \
      --repository-name mxmed-stg-application --image-ids imageTag="$(jq -r .run_id "$manifest")" \
      --query 'imageDetails[0].imageDigest' --output text)"
    c3_seal_ecr_digest "$manifest" "$state" "$digest" "$image_source_revision" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
      || fail 'ECR_DIGEST_GATE_SEAL_FAILED'
    ;;
  --resolve-and-seal-image-digest)
    require_future_write_authority POST_FIRST_RUNTIME_MUTATION
    [ -n "$image_source_revision" ] || fail 'IMAGE_SOURCE_REVISION_MISSING'
    digest="$(AWS_PROFILE="$MXMED_C3_TEARDOWN_PROFILE" aws ecr describe-images --repository-name mxmed-stg-application --image-ids imageTag="$(jq -r .run_id "$manifest")" --query 'imageDetails[0].imageDigest' --output text)"
    case "$digest" in sha256:[0-9a-f][0-9a-f]*) ;; *) fail 'IMAGE_DIGEST_INVALID';; esac
    c3_seal_ecr_digest "$manifest" "$state" "$digest" "$image_source_revision" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
      || fail 'ECR_DIGEST_GATE_SEAL_FAILED'
    ;;
  *) fail 'MODE_REQUIRED' ;;
esac
