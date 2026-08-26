#!/bin/sh
set -eu

EXPECTED_ACCOUNT='875691018466'
EXPECTED_REGION='mx-central-1'
BUDGETS_API_REGION='us-east-1'
AWS_WRITE_AUTHORITY='DIRECTOR_AUTHORIZES_SINGLE_USE_C3_AWS_WRITES'
DELETE_STACKS='mxmed-stg-c3-runner mxmed-stg-session mxmed-stg-registry mxmed-stg-security mxmed-stg-network'
TEMPLATE_BUCKET='mxmed-stg-c3-cf-templates-875691018466-mx-central-1'
AUDIT_BUCKET='mxmed-stg-audit-875691018466-mx-central-1'
RETAINED='mxmed-stg-security|ApplicationDataKeyC957928E|AWS::KMS::Key
mxmed-stg-security|SecretsKey317DCF94|AWS::KMS::Key
mxmed-stg-security|AuditKeyB2DBB069|AWS::KMS::Key
mxmed-stg-security|BackupKey60B97760|AWS::KMS::Key
mxmed-stg-security|SessionSigningSecret925D6419|AWS::SecretsManager::Secret
mxmed-stg-security|StripeSecretKeyContainerB8EBA645|AWS::SecretsManager::Secret
mxmed-stg-security|StripeWebhookSecretContainer9B02DE63|AWS::SecretsManager::Secret
mxmed-stg-security|AiApiKeyContainerC19542A6|AWS::SecretsManager::Secret
mxmed-stg-security|AuditBucketB01E0AE8|AWS::S3::Bucket
mxmed-stg-security|CloudTrailLogGroup343A29D6|AWS::Logs::LogGroup
mxmed-stg-session|SessionAuthSecretA6611D29|AWS::SecretsManager::Secret
mxmed-stg-registry|RegistryKeyDD63DA09|AWS::KMS::Key
mxmed-stg-registry|ApplicationRepository13E54097|AWS::ECR::Repository'
CONTRACT_HELPER='scripts/aws/c3-runtime-contract.sh'

[ -f "$CONTRACT_HELPER" ] || { printf '%s\n' 'C3_TEARDOWN_FAIL_CLOSED:RUNTIME_CONTRACT_HELPER_MISSING' >&2; exit 1; }
. "$CONTRACT_HELPER"

fail() { printf '%s\n' "C3_TEARDOWN_FAIL_CLOSED:$1" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || fail "COMMAND_MISSING:$1"; }
mode=''
manifest=''
state=''

while [ "$#" -gt 0 ]; do
  case "$1" in
    --execute-stack-deletes|--delete-direct-budget|--cleanup-retained|--orphan-inventory|--residual-cost-inventory|--delete-janitor-stack|--final-read-only-verification) mode="$1" ;;
    --manifest) shift; manifest="${1:-}" ;;
    --state) shift; state="${1:-}" ;;
    *) fail "UNKNOWN_ARGUMENT:$1" ;;
  esac
  shift
done

validate_authority() {
  need jq
  auth_file="${MXMED_C3_AWS_WRITE_AUTHORIZATION_FILE:-}"
  [ -n "$auth_file" ] && [ -f "$auth_file" ] || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_MISSING'
  [ -n "$state" ] || fail 'RUNTIME_STATE_PATH_MISSING'
  c3_validate_phase PRE_TEARDOWN "$manifest" "$state" || fail 'RUN_MANIFEST_OR_STATE_CONTRACT_REJECTED'
  jq -e --arg authority "$AWS_WRITE_AUTHORITY" --arg run "$(jq -r .run_id "$manifest")" \
    '.authorization == $authority and .run_id == $run and .single_use == true' "$auth_file" >/dev/null \
    || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_INVALID'
  [ "${AWS_ACCOUNT_ID:-}" = "$EXPECTED_ACCOUNT" ] || fail 'AWS_ACCOUNT_MISMATCH'
  [ "${AWS_REGION:-}" = "$EXPECTED_REGION" ] || fail 'AWS_REGION_MISMATCH'
  current_head="$(git rev-parse HEAD)"
  [ "$current_head" = "$(jq -r .source_head "$manifest")" ] || fail 'SOURCE_HEAD_MISMATCH'
  [ "${MXMED_C3_TEARDOWN_PROFILE:-}" = 'mxmed-c3-stg-teardown' ] || fail 'EXACT_TEARDOWN_PROFILE_REQUIRED'
  export AWS_PROFILE="$MXMED_C3_TEARDOWN_PROFILE"
}

cleanup_template_transport() {
  [ "$(jq -r '.template_transport.bucket_name' "$manifest")" = "$TEMPLATE_BUCKET" ] \
    || fail 'TEMPLATE_BUCKET_OUT_OF_SCOPE'
  if ! aws s3api head-bucket --bucket "$TEMPLATE_BUCKET" >/dev/null 2>&1; then
    return 0
  fi
  jq -r '.template_transport_objects[].key' "$state" | while IFS= read -r key; do
    case "$key" in
      "$(jq -r .run_id "$manifest")"/mxmed-stg-*/[0-9a-f]*.template.json) ;;
      *) fail 'TEMPLATE_OBJECT_KEY_OUT_OF_SCOPE' ;;
    esac
    aws s3api delete-object --bucket "$TEMPLATE_BUCKET" --key "$key" >/dev/null
  done
  remaining="$(aws s3api list-objects-v2 --bucket "$TEMPLATE_BUCKET" --query 'KeyCount' --output text)"
  [ "$remaining" = '0' ] || fail 'TEMPLATE_BUCKET_OBJECTS_REMAIN'
  aws s3api delete-bucket-policy --bucket "$TEMPLATE_BUCKET"
  aws s3api delete-bucket --bucket "$TEMPLATE_BUCKET"
}

cleanup_exact_direct_budget() {
  budget_name="$(jq -r .direct_budget_authority.budget_name "$manifest")"
  [ "$(jq -r .direct_budget_authority.api_region "$manifest")" = "$BUDGETS_API_REGION" ] \
    || fail 'DIRECT_BUDGET_API_REGION_MISMATCH'
  [ "$budget_name" = "mxmed-stg-c3-$(jq -r .run_id "$manifest")" ] \
    || fail 'DIRECT_BUDGET_NAME_NOT_EXACT_RUN_BOUND'
  if aws budgets describe-budget --region "$BUDGETS_API_REGION" --account-id "$EXPECTED_ACCOUNT" \
    --budget-name "$budget_name" >/dev/null 2>&1; then
    now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    if [ "$(jq -r .direct_budget_created "$state")" = false ]; then
      c3_atomic_state_update "$manifest" "$state" '
        .direct_budget_created=true|.direct_budget_created_at_utc=$now
        |.created_resource_ids += [{type:"direct-budget",id:.direct_budget_name}]
      ' --arg now "$now"
    fi
    c3_atomic_state_update "$manifest" "$state" \
      'if .direct_budget_deletion_started_at_utc == $pending then .direct_budget_deletion_started_at_utc=$now else . end' \
      --arg pending "$C3_PENDING_RUNTIME_RESOLUTION" --arg now "$now"
    aws budgets delete-budget --region "$BUDGETS_API_REGION" --account-id "$EXPECTED_ACCOUNT" \
      --budget-name "$budget_name" >/dev/null || fail 'DIRECT_BUDGET_EXACT_DELETE_FAILED'
    deleted="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    c3_record_direct_budget_deleted "$manifest" "$state" "$now" "$deleted"
  fi
  if absence="$(aws budgets describe-budget --region "$BUDGETS_API_REGION" --account-id "$EXPECTED_ACCOUNT" \
    --budget-name "$budget_name" 2>&1)"; then
    fail 'DIRECT_BUDGET_EXACT_ABSENCE_NOT_PROVEN'
  else
    case "$absence" in
      *NotFoundException*|*not\ found*|*could\ not\ be\ found*) ;;
      *) fail 'DIRECT_BUDGET_ABSENCE_READBACK_FAILED' ;;
    esac
  fi
  c3_atomic_state_update "$manifest" "$state" '.direct_budget_residual_count=0'
}

capture_retained_physical_ids() {
  [ "$(jq '.retained_physical_resource_ids | length' "$state")" = '0' ] || return 0
  captures='[]'
  while IFS='|' read -r stack logical_id resource_type; do
    [ -n "$stack" ] || continue
    if ! physical_id="$(aws cloudformation describe-stack-resource --stack-name "$stack" --logical-resource-id "$logical_id" --query 'StackResourceDetail.PhysicalResourceId' --output text 2>/dev/null)"; then
      [ "$(jq -r '.deployment_failure_at_utc // empty' "$state")" != '' ] && continue
      fail "RETAINED_PHYSICAL_ID_MISSING:$logical_id"
    fi
    if [ -z "$physical_id" ] || [ "$physical_id" = 'None' ]; then
      [ "$(jq -r '.deployment_failure_at_utc // empty' "$state")" != '' ] && continue
      fail "RETAINED_PHYSICAL_ID_MISSING:$logical_id"
    fi
    if [ "$logical_id" = 'AuditBucketB01E0AE8' ] && [ "$physical_id" != "$AUDIT_BUCKET" ]; then
      fail 'AUDIT_BUCKET_PHYSICAL_NAME_MISMATCH'
    fi
    captures="$(printf '%s' "$captures" | jq --arg stack "$stack" --arg logical "$logical_id" --arg type "$resource_type" --arg physical "$physical_id" '. + [{stack_name:$stack,logical_id:$logical,type:$type,physical_id:$physical}]')"
  done <<EOF
$RETAINED
EOF
  capture_count="$(printf '%s' "$captures" | jq length)"
  [ "$capture_count" = '13' ] || [ "$(jq -r '.deployment_failure_at_utc // empty' "$state")" != '' ] \
    || fail 'RETAINED_CAPTURE_COUNT_INVALID'
  c3_atomic_state_update "$manifest" "$state" '.retained_physical_resource_ids=$captures' --argjson captures "$captures"
}

empty_exact_versioned_bucket() {
  bucket="$1"
  aws s3api list-object-versions --bucket "$bucket" --output json \
    | jq -c '[.Versions[]?,.DeleteMarkers[]?] | .[] | {Key:.Key,VersionId:.VersionId}' \
    | while IFS= read -r object; do
        key="$(printf '%s' "$object" | jq -r .Key)"
        version="$(printf '%s' "$object" | jq -r .VersionId)"
        aws s3api delete-object --bucket "$bucket" --key "$key" --version-id "$version" >/dev/null
      done
  aws s3api list-multipart-uploads --bucket "$bucket" --output json \
    | jq -c '.Uploads[]? | {Key:.Key,UploadId:.UploadId}' \
    | while IFS= read -r upload; do
        key="$(printf '%s' "$upload" | jq -r .Key)"
        upload_id="$(printf '%s' "$upload" | jq -r .UploadId)"
        aws s3api abort-multipart-upload --bucket "$bucket" --key "$key" --upload-id "$upload_id"
      done
}

cleanup_one() {
  resource_type="$1" physical_id="$2"
  case "$resource_type" in
    'AWS::KMS::Key') aws kms schedule-key-deletion --key-id "$physical_id" --pending-window-in-days 7 >/dev/null ;;
    'AWS::SecretsManager::Secret') aws secretsmanager delete-secret --secret-id "$physical_id" --recovery-window-in-days 7 >/dev/null ;;
    'AWS::Logs::LogGroup') aws logs delete-log-group --log-group-name "$physical_id" ;;
    'AWS::ECR::Repository') aws ecr delete-repository --repository-name "$physical_id" --force >/dev/null ;;
    'AWS::S3::Bucket') empty_exact_versioned_bucket "$physical_id"; aws s3api delete-bucket --bucket "$physical_id" ;;
    *) fail "UNAPPROVED_RETAINED_RESOURCE_TYPE:$resource_type" ;;
  esac
}

case "$mode" in
  --execute-stack-deletes)
    validate_authority
    capture_retained_physical_ids
    now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    terminal="$(jq -r --arg pending "$C3_PENDING_RUNTIME_RESOLUTION" \
      'if .test_terminal_at_utc == $pending then empty else .test_terminal_at_utc // empty end' "$state")"
    if [ -n "$terminal" ]; then
      delay=$(( $(date -u -j -f '%Y-%m-%dT%H:%M:%SZ' "$now" +%s) - $(date -u -j -f '%Y-%m-%dT%H:%M:%SZ' "$terminal" +%s) ))
      [ "$delay" -le 300 ] || fail 'TEARDOWN_START_EXCEEDED_300_SECONDS'
    fi
    c3_atomic_state_update "$manifest" "$state" \
      'if .teardown_started_at_utc == $pending then .teardown_started_at_utc=$now else . end' \
      --arg pending "$C3_PENDING_RUNTIME_RESOLUTION" --arg now "$now"
    cleanup_exact_direct_budget
    for stack in $DELETE_STACKS; do
      if aws cloudformation describe-stacks --stack-name "$stack" >/dev/null 2>&1; then
        aws cloudformation delete-stack --stack-name "$stack"
        aws cloudformation wait stack-delete-complete --stack-name "$stack"
      fi
    done
    cleanup_template_transport
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --cleanup-retained --manifest "$manifest" --state "$state"
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --orphan-inventory --manifest "$manifest" --state "$state"
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --residual-cost-inventory --manifest "$manifest" --state "$state"
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --final-read-only-verification --manifest "$manifest" --state "$state"
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --delete-janitor-stack --manifest "$manifest" --state "$state"
    ;;
  --delete-direct-budget)
    validate_authority
    cleanup_exact_direct_budget
    ;;
  --cleanup-retained)
    validate_authority
    physical_count="$(jq '.retained_physical_resource_ids | length' "$state")"
    [ "$physical_count" = '13' ] || [ "$(jq -r '.deployment_failure_at_utc // empty' "$state")" != '' ] \
      || fail 'RETAINED_PHYSICAL_IDS_NOT_SEALED'
    jq -r '.retained_physical_resource_ids[] | [.type,.physical_id] | @tsv' "$state" \
      | while IFS="$(printf '\t')" read -r resource_type physical_id; do cleanup_one "$resource_type" "$physical_id"; done
    ;;
  --orphan-inventory)
    validate_authority
    inventory='[]'
    while IFS="$(printf '\t')" read -r resource_type physical_id; do
      resource_state='ABSENT_OR_DELETION_REQUESTED'
      case "$resource_type" in
        'AWS::KMS::Key') resource_state="$(aws kms describe-key --key-id "$physical_id" --query 'KeyMetadata.KeyState' --output text 2>/dev/null || printf ABSENT)" ;;
        'AWS::SecretsManager::Secret') resource_state="$(aws secretsmanager describe-secret --secret-id "$physical_id" --query 'DeletedDate' --output text 2>/dev/null || printf ABSENT)" ;;
        'AWS::S3::Bucket') aws s3api head-bucket --bucket "$physical_id" >/dev/null 2>&1 && resource_state=ACTIVE || true ;;
        'AWS::ECR::Repository') aws ecr describe-repositories --repository-names "$physical_id" >/dev/null 2>&1 && resource_state=ACTIVE || true ;;
        'AWS::Logs::LogGroup') count="$(aws logs describe-log-groups --log-group-name-prefix "$physical_id" --query 'length(logGroups[?logGroupName==`'"$physical_id"'`])' --output text)"; [ "$count" = '0' ] || resource_state=ACTIVE ;;
      esac
      inventory="$(printf '%s' "$inventory" | jq --arg type "$resource_type" --arg id "$physical_id" --arg state "$resource_state" '. + [{type:$type,physical_id:$id,state:$state}]')"
    done <<EOF
$(jq -r '.retained_physical_resource_ids[] | [.type,.physical_id] | @tsv' "$state")
EOF
    c3_atomic_state_update "$manifest" "$state" '.orphan_inventory=$inventory' --argjson inventory "$inventory"
    ;;
  --residual-cost-inventory)
    validate_authority
    [ "$(jq '.orphan_inventory | length' "$state")" = "$(jq '.retained_physical_resource_ids | length' "$state")" ] || fail 'ORPHAN_INVENTORY_MISSING'
    active="$(jq '[.orphan_inventory[] | select(.state == "ACTIVE" or (.type == "AWS::KMS::Key" and .state != "PendingDeletion" and .state != "ABSENT") or (.type == "AWS::SecretsManager::Secret" and .state == "None"))] | length' "$state")"
    if aws s3api head-bucket --bucket "$TEMPLATE_BUCKET" >/dev/null 2>&1; then active=$((active + 1)); fi
    budget_name="$(jq -r .direct_budget_authority.budget_name "$manifest")"
    if budget_probe="$(aws budgets describe-budget --region "$BUDGETS_API_REGION" --account-id "$EXPECTED_ACCOUNT" --budget-name "$budget_name" 2>&1)"; then
      direct_budget_residual=1
      active=$((active + 1))
    else
      case "$budget_probe" in
        *NotFoundException*|*not\ found*|*could\ not\ be\ found*) direct_budget_residual=0 ;;
        *) fail 'DIRECT_BUDGET_RESIDUAL_READBACK_FAILED' ;;
      esac
    fi
    c3_atomic_state_update "$manifest" "$state" \
      '.residual_billable_resource_count=$active|.direct_budget_residual_count=$direct' \
      --argjson active "$active" --argjson direct "$direct_budget_residual"
    ;;
  --final-read-only-verification)
    validate_authority
    [ "$(jq -r '.residual_billable_resource_count // -1' "$state")" = '0' ] || fail 'RESIDUAL_BILLABLE_RESOURCES_REMAIN'
    [ "$(jq -r '.direct_budget_residual_count // -1' "$state")" = '0' ] || fail 'DIRECT_BUDGET_RESIDUAL_REMAINS'
    printf '%s\n' 'C3_RESIDUAL_BILLABLE_RESOURCE_COUNT=0'
    ;;
  --delete-janitor-stack)
    validate_authority
    if aws cloudformation describe-stacks --stack-name mxmed-stg-c3-janitor >/dev/null 2>&1; then
      aws cloudformation delete-stack --stack-name mxmed-stg-c3-janitor
      aws cloudformation wait stack-delete-complete --stack-name mxmed-stg-c3-janitor
    fi
    now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    c3_atomic_state_update "$manifest" "$state" \
      'if .teardown_completed_at_utc == $pending then .teardown_completed_at_utc=$now else . end' \
      --arg pending "$C3_PENDING_RUNTIME_RESOLUTION" --arg now "$now"
    ;;
  *) fail 'MODE_REQUIRED' ;;
esac
