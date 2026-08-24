#!/bin/sh
set -eu

BASELINE_PRODUCT_HEAD='1f507b61846b96caa34d390ee3a59779f65e4331'
EXPECTED_ACCOUNT='875691018466'
EXPECTED_REGION='mx-central-1'
AWS_WRITE_AUTHORITY='DIRECTOR_AUTHORIZES_SINGLE_USE_C3_AWS_WRITES'
DELETE_STACKS='mxmed-stg-c3-runner mxmed-stg-session mxmed-stg-registry mxmed-stg-security mxmed-stg-network'
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

fail() { printf '%s\n' "C3_TEARDOWN_FAIL_CLOSED:$1" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || fail "COMMAND_MISSING:$1"; }
mode=''
manifest=''

while [ "$#" -gt 0 ]; do
  case "$1" in
    --execute-stack-deletes|--cleanup-retained|--orphan-inventory|--residual-cost-inventory|--delete-janitor-stack|--final-read-only-verification) mode="$1" ;;
    --manifest) shift; manifest="${1:-}" ;;
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
     and ([.stack_names[] | select(startswith("mxmed-stg-") | not)] | length == 0)
     and (.retained_resource_expectations.count == 13)
     and ((tostring | test("production|mxmed-prd-|<[^>]+>|UNRESOLVED"; "i")) | not)' "$manifest" >/dev/null \
    || fail 'RUN_MANIFEST_CONTRACT_REJECTED'
  jq -e --arg authority "$AWS_WRITE_AUTHORITY" --arg run "$(jq -r .run_id "$manifest")" \
    '.authorization == $authority and .run_id == $run and .single_use == true' "$auth_file" >/dev/null \
    || fail 'DIRECTOR_AWS_WRITE_AUTHORIZATION_INVALID'
  [ "${AWS_ACCOUNT_ID:-}" = "$EXPECTED_ACCOUNT" ] || fail 'AWS_ACCOUNT_MISMATCH'
  [ "${AWS_REGION:-}" = "$EXPECTED_REGION" ] || fail 'AWS_REGION_MISMATCH'
  current_head="$(git rev-parse HEAD)"
  [ "$current_head" = "$(jq -r .expected_head "$manifest")" ] || fail 'SOURCE_HEAD_MISMATCH'
  git merge-base --is-ancestor "$BASELINE_PRODUCT_HEAD" "$current_head" || fail 'BASELINE_PRODUCT_HEAD_NOT_ANCESTOR'
}

capture_retained_physical_ids() {
  [ "$(jq '.retained_resource_expectations.physical_resources | length' "$manifest")" = '0' ] || return 0
  captures='[]'
  while IFS='|' read -r stack logical_id resource_type; do
    [ -n "$stack" ] || continue
    if ! physical_id="$(aws cloudformation describe-stack-resource --stack-name "$stack" --logical-resource-id "$logical_id" --query 'StackResourceDetail.PhysicalResourceId' --output text 2>/dev/null)"; then
      [ "$(jq -r '.deployment_failure_at_utc // empty' "$manifest")" != '' ] && continue
      fail "RETAINED_PHYSICAL_ID_MISSING:$logical_id"
    fi
    if [ -z "$physical_id" ] || [ "$physical_id" = 'None' ]; then
      [ "$(jq -r '.deployment_failure_at_utc // empty' "$manifest")" != '' ] && continue
      fail "RETAINED_PHYSICAL_ID_MISSING:$logical_id"
    fi
    captures="$(printf '%s' "$captures" | jq --arg stack "$stack" --arg logical "$logical_id" --arg type "$resource_type" --arg physical "$physical_id" '. + [{stack_name:$stack,logical_id:$logical,type:$type,physical_id:$physical}]')"
  done <<EOF
$RETAINED
EOF
  capture_count="$(printf '%s' "$captures" | jq length)"
  [ "$capture_count" = '13' ] || [ "$(jq -r '.deployment_failure_at_utc // empty' "$manifest")" != '' ] \
    || fail 'RETAINED_CAPTURE_COUNT_INVALID'
  tmp="${manifest}.tmp.$$"
  jq --argjson captures "$captures" '.retained_resource_expectations.physical_resources=$captures' "$manifest" >"$tmp"
  chmod 0600 "$tmp"
  mv "$tmp" "$manifest"
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
    terminal="$(jq -r '.test_terminal_at_utc // empty' "$manifest")"
    if [ -n "$terminal" ]; then
      delay=$(( $(date -u -j -f '%Y-%m-%dT%H:%M:%SZ' "$now" +%s) - $(date -u -j -f '%Y-%m-%dT%H:%M:%SZ' "$terminal" +%s) ))
      [ "$delay" -le 300 ] || fail 'TEARDOWN_START_EXCEEDED_300_SECONDS'
    fi
    tmp="${manifest}.tmp.$$"; jq --arg now "$now" '.teardown_started_at_utc=$now' "$manifest" >"$tmp"; chmod 0600 "$tmp"; mv "$tmp" "$manifest"
    for stack in $DELETE_STACKS; do
      if aws cloudformation describe-stacks --stack-name "$stack" >/dev/null 2>&1; then
        aws cloudformation delete-stack --stack-name "$stack"
        aws cloudformation wait stack-delete-complete --stack-name "$stack"
      fi
    done
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --cleanup-retained --manifest "$manifest"
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --orphan-inventory --manifest "$manifest"
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --residual-cost-inventory --manifest "$manifest"
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --final-read-only-verification --manifest "$manifest"
    "$(dirname "$0")/c3-ephemeral-teardown.sh" --delete-janitor-stack --manifest "$manifest"
    ;;
  --cleanup-retained)
    validate_authority
    physical_count="$(jq '.retained_resource_expectations.physical_resources | length' "$manifest")"
    [ "$physical_count" = '13' ] || [ "$(jq -r '.deployment_failure_at_utc // empty' "$manifest")" != '' ] \
      || fail 'RETAINED_PHYSICAL_IDS_NOT_SEALED'
    jq -r '.retained_resource_expectations.physical_resources[] | [.type,.physical_id] | @tsv' "$manifest" \
      | while IFS="$(printf '\t')" read -r resource_type physical_id; do cleanup_one "$resource_type" "$physical_id"; done
    ;;
  --orphan-inventory)
    validate_authority
    inventory='[]'
    while IFS="$(printf '\t')" read -r resource_type physical_id; do
      state='ABSENT_OR_DELETION_REQUESTED'
      case "$resource_type" in
        'AWS::KMS::Key') state="$(aws kms describe-key --key-id "$physical_id" --query 'KeyMetadata.KeyState' --output text 2>/dev/null || printf ABSENT)" ;;
        'AWS::SecretsManager::Secret') state="$(aws secretsmanager describe-secret --secret-id "$physical_id" --query 'DeletedDate' --output text 2>/dev/null || printf ABSENT)" ;;
        'AWS::S3::Bucket') aws s3api head-bucket --bucket "$physical_id" >/dev/null 2>&1 && state=ACTIVE || true ;;
        'AWS::ECR::Repository') aws ecr describe-repositories --repository-names "$physical_id" >/dev/null 2>&1 && state=ACTIVE || true ;;
        'AWS::Logs::LogGroup') count="$(aws logs describe-log-groups --log-group-name-prefix "$physical_id" --query 'length(logGroups[?logGroupName==`'"$physical_id"'`])' --output text)"; [ "$count" = '0' ] || state=ACTIVE ;;
      esac
      inventory="$(printf '%s' "$inventory" | jq --arg type "$resource_type" --arg id "$physical_id" --arg state "$state" '. + [{type:$type,physical_id:$id,state:$state}]')"
    done <<EOF
$(jq -r '.retained_resource_expectations.physical_resources[] | [.type,.physical_id] | @tsv' "$manifest")
EOF
    tmp="${manifest}.tmp.$$"; jq --argjson inventory "$inventory" '.orphan_inventory=$inventory' "$manifest" >"$tmp"; chmod 0600 "$tmp"; mv "$tmp" "$manifest"
    ;;
  --residual-cost-inventory)
    validate_authority
    [ "$(jq '.orphan_inventory | length' "$manifest")" = "$(jq '.retained_resource_expectations.physical_resources | length' "$manifest")" ] || fail 'ORPHAN_INVENTORY_MISSING'
    active="$(jq '[.orphan_inventory[] | select(.state == "ACTIVE" or (.type == "AWS::KMS::Key" and .state != "PendingDeletion" and .state != "ABSENT") or (.type == "AWS::SecretsManager::Secret" and .state == "None"))] | length' "$manifest")"
    tmp="${manifest}.tmp.$$"; jq --argjson active "$active" '.residual_billable_resource_count=$active' "$manifest" >"$tmp"; chmod 0600 "$tmp"; mv "$tmp" "$manifest"
    ;;
  --final-read-only-verification)
    validate_authority
    [ "$(jq -r '.residual_billable_resource_count // -1' "$manifest")" = '0' ] || fail 'RESIDUAL_BILLABLE_RESOURCES_REMAIN'
    printf '%s\n' 'C3_RESIDUAL_BILLABLE_RESOURCE_COUNT=0'
    ;;
  --delete-janitor-stack)
    validate_authority
    if aws cloudformation describe-stacks --stack-name mxmed-stg-c3-janitor >/dev/null 2>&1; then
      aws cloudformation delete-stack --stack-name mxmed-stg-c3-janitor
      aws cloudformation wait stack-delete-complete --stack-name mxmed-stg-c3-janitor
    fi
    ;;
  *) fail 'MODE_REQUIRED' ;;
esac
