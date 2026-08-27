#!/bin/sh
set -eu

C3_PENDING_RUNTIME_RESOLUTION='PENDING_RUNTIME_RESOLUTION'
C3_PENDING_RUNTIME='PENDING_RUNTIME'
C3_PASS='PASS'
C3_MANIFEST_SCHEMA='mxmed.c3.ephemeral.sealed-run-manifest.v3'
C3_STATE_SCHEMA='mxmed.c3.ephemeral.runtime-state.v2'
C3_ACCOUNT='875691018466'
C3_REGION='mx-central-1'
C3_BUDGETS_API_REGION='us-east-1'
C3_BUDGET_NOTIFICATION_TOPIC_ARN='arn:aws:sns:mx-central-1:875691018466:mxmed-stg-c3-notifications'
C3_OBJECT_KEY_SUFFIX='.template.json'

c3_contract_fail() {
  printf '%s\n' "C3_RUNTIME_CONTRACT_FAIL_CLOSED:$1" >&2
  return 1
}

c3_sha256() { shasum -a 256 "$1" | awk '{print $1}'; }
c3_file_mode() { stat -f '%Lp' "$1" 2>/dev/null || stat -c '%a' "$1"; }

c3_gate_definitions_json() {
  jq -cn '[
    {ordinal:1,name:"SOURCE_HEAD_MATCH"},
    {ordinal:2,name:"WORKTREE_CLEAN"},
    {ordinal:3,name:"FRESH_DIRECTOR_RUNTIME_AUTHORIZATION_PRESENT"},
    {ordinal:4,name:"PRODUCTION_DENY_PROVEN"},
    {ordinal:5,name:"SEALED_TEMPLATE_AND_RESOURCE_SCOPE_PASS"},
    {ordinal:6,name:"ESTIMATED_COST_WITHIN_USD_5_CAP"},
    {ordinal:7,name:"MANUAL_TEARDOWN_READY"},
    {ordinal:8,name:"AUTO_TEARDOWN_FAILSAFE_CONTRACT_READY"},
    {ordinal:9,name:"RETAINED_RESOURCE_CLEANUP_READY"},
    {ordinal:10,name:"NONPRODUCTION_TARGET_PROVEN"},
    {ordinal:11,name:"ROLE_CHAIN_EXACT_PASS"},
    {ordinal:12,name:"ECR_DIGEST_SEALED_BEFORE_RUNNER"}
  ]'
}

c3_validate_manifest() {
  local manifest gates payload_sha
  manifest="$1"
  [ -f "$manifest" ] || c3_contract_fail 'SEALED_MANIFEST_MISSING'
  [ "$(c3_file_mode "$manifest")" = '600' ] || c3_contract_fail 'SEALED_MANIFEST_MODE_INVALID'
  gates="$(c3_gate_definitions_json)"
  jq -e --arg schema "$C3_MANIFEST_SCHEMA" --arg account "$C3_ACCOUNT" \
    --arg region "$C3_REGION" --arg budgets_region "$C3_BUDGETS_API_REGION" --arg pending "$C3_PENDING_RUNTIME_RESOLUTION" \
    --arg budget_topic "$C3_BUDGET_NOTIFICATION_TOPIC_ARN" \
    --arg suffix "$C3_OBJECT_KEY_SUFFIX" --argjson gates "$gates" '
      . as $root
      | .schema == $schema
      and (.run_uuid | test("^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$"))
      and .run_id == ("c3-" + .run_uuid)
      and (.source_head | test("^[0-9a-f]{40}$"))
      and .account == $account and .region == $region
      and .activity_cost_cap_usd == 5
      and .budget_notification_topic_arn == $budget_topic
      and .direct_budget_authority.api_region == $budgets_region
      and .direct_budget_authority.account_id == $account
      and .direct_budget_authority.budget_name_format == "mxmed-stg-c3-${RUN_ID}"
      and .direct_budget_authority.budget_name == ("mxmed-stg-c3-" + $root.run_id)
      and .direct_budget_authority.runtime_object_count == 1
      and .direct_budget_authority.cleanup_contract == "EXACT_RUN_ID_BOUND_DESCRIBE_DELETE_ABSENCE"
      and .direct_budget_authority.budget == {
        BudgetName:("mxmed-stg-c3-" + $root.run_id),BudgetType:"COST",TimeUnit:"MONTHLY",
        BudgetLimit:{Amount:"5",Unit:"USD"},CostFilters:{TagKeyValue:["user:Phase$C3"]}
      }
      and (.direct_budget_authority.budget | has("ResourceTags") | not)
      and (.direct_budget_authority.notifications_with_subscribers | length) == 3
      and ([.direct_budget_authority.notifications_with_subscribers[].Notification.Threshold] | sort) == [1,3,5]
      and ([.direct_budget_authority.notifications_with_subscribers[] | select(
        .Notification.ThresholdType != "ABSOLUTE_VALUE" or .Notification.NotificationType != "ACTUAL"
        or .Notification.ComparisonOperator != "GREATER_THAN"
        or .Subscribers != [{SubscriptionType:"SNS",Address:$budget_topic}]
      )] | length) == 0
      and .deployment_mode == "DIRECT_CLOUDFORMATION_FROM_SEALED_TEMPLATES"
      and .runtime_clock_contract == {
        origin:"FIRST_SUCCESSFUL_RUNTIME_AWS_MUTATION",
        failsafe_offset_hours:22,
        hard_cap_offset_hours:24,
        teardown_start_max_delay_seconds:300
      }
      and .pending_runtime_fields == {
        first_runtime_mutation_at_utc:$pending,
        failsafe_at_utc:$pending,
        hard_cap_at_utc:$pending,
        physical_ecr_image_digest:$pending
      }
      and .object_key_contract == {
        canonical_format:("RUN_ID/STACK_NAME/TEMPLATE_SHA256" + $suffix),
        suffix:$suffix,
        binds_run_id:true,
        binds_stack_name:true,
        binds_template_sha256:true,
        path_traversal_safe:true
      }
      and .gate_definitions == $gates
      and (.gate_definitions | length) == 12
      and .phase_requirements.pre_first_write == {required_pass_count:11,gate_12_state:"PENDING_RUNTIME"}
      and .phase_requirements.pre_runner == {required_pass_count:12,gate_12_state:"PASS"}
      and (.templates | length) == 6
      and ([.templates[].stack_name] | sort) == ([
        "mxmed-stg-c3-janitor","mxmed-stg-c3-runner","mxmed-stg-network",
        "mxmed-stg-registry","mxmed-stg-security","mxmed-stg-session"
      ] | sort)
      and ([.templates[]
        | select(
            (.sha256 | test("^[0-9a-f]{64}$") | not)
            or (.bytes <= 0)
            or (.transport != "TEMPLATE_BODY" and .transport != "C3_TEMPLATE_S3_URL")
            or (.object_key != (
              if .transport == "C3_TEMPLATE_S3_URL"
              then ($root.run_id + "/" + .stack_name + "/" + .sha256 + $suffix)
              else null end
            ))
          )] | length) == 0
      and (.policy_sha256 | type) == "object"
      and (.policy_sha256 | length) > 0
      and ([.policy_sha256[] | select(test("^[0-9a-f]{64}$") | not)] | length) == 0
      and (.expected_resource_graph | keys | sort) == ([
        "mxmed-stg-c3-janitor","mxmed-stg-c3-runner","mxmed-stg-network",
        "mxmed-stg-registry","mxmed-stg-security","mxmed-stg-session"
      ] | sort)
      and ([.expected_resource_graph[]] | add) == 106
      and .cfn_execution_role_arns == {
        "mxmed-stg-network":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Network",
        "mxmed-stg-security":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Security",
        "mxmed-stg-session":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Session",
        "mxmed-stg-registry":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Registry",
        "mxmed-stg-c3-runner":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Runner",
        "mxmed-stg-c3-janitor":"arn:aws:iam::875691018466:role/MXMed-C3-CFN-Janitor"
      }
      and .approved_role_profiles == {
        deploy:"mxmed-c3-stg-deploy",
        test_controller:"mxmed-c3-stg-test-controller",
        teardown:"mxmed-c3-stg-teardown"
      }
      and .expected_resource_counts == {cloudformation:106,direct_runtime:1,total_authorized:107,data:0,storage:0,application_service:0,public_runner_ip:0}
      and .retained_resource_expectations.count == 13
      and .template_transport.bucket_name == "mxmed-stg-c3-cf-templates-875691018466-mx-central-1"
      and .template_transport.public_access_blocked == true
      and .template_transport.default_encryption == "AES256"
      and .template_transport.ephemeral == true
      and .template_transport.delete_after_c3 == true
      and (.image_build_inputs.source_revision == .source_head)
      and (.image_build_inputs.php_base_image_reference | test("@sha256:[0-9a-f]{64}$"))
      and (.image_build_inputs.php_base_image_digest | test("^sha256:[0-9a-f]{64}$"))
      and (.image_build_inputs.php_base_image_reference | endswith("@" + $root.image_build_inputs.php_base_image_digest))
      and (.image_build_inputs.aws_cli_archive_sha256 | test("^[0-9a-f]{64}$"))
      and (.image_build_inputs.phpredis_version | test("^[0-9]+\\.[0-9]+\\.[0-9]+$"))
      and (.image_build_inputs.dockerfile_sha256 | test("^[0-9a-f]{64}$"))
      and (.image_build_inputs.entrypoint_sha256 | test("^[0-9a-f]{64}$"))
      and ((tostring | test("mxmed-prd-|/mxmed/production|:latest"; "i")) | not)
      and ((tostring | test("<[^>]+>")) | not)
    ' "$manifest" >/dev/null || c3_contract_fail 'SEALED_MANIFEST_CONTRACT_REJECTED'
  payload_sha="$(jq -cS '.direct_budget_authority | {budget,notifications_with_subscribers}' "$manifest" | shasum -a 256 | awk '{print $1}')"
  [ "$payload_sha" = "$(jq -r .direct_budget_authority.payload_sha256 "$manifest")" ] \
    || c3_contract_fail 'DIRECT_BUDGET_PAYLOAD_HASH_MISMATCH'
}

c3_validate_state_base() {
  local manifest state manifest_sha gates
  manifest="$1"; state="$2"
  c3_validate_manifest "$manifest"
  [ -f "$state" ] || c3_contract_fail 'RUNTIME_STATE_MISSING'
  [ "$(c3_file_mode "$state")" = '600' ] || c3_contract_fail 'RUNTIME_STATE_MODE_INVALID'
  manifest_sha="$(c3_sha256 "$manifest")"; gates="$(c3_gate_definitions_json)"
  jq -e --arg schema "$C3_STATE_SCHEMA" --arg sha "$manifest_sha" \
    --arg run_uuid "$(jq -r .run_uuid "$manifest")" --arg run_id "$(jq -r .run_id "$manifest")" \
    --arg source_head "$(jq -r .source_head "$manifest")" \
    --arg pending "$C3_PENDING_RUNTIME_RESOLUTION" --arg pending_gate "$C3_PENDING_RUNTIME" \
    --argjson gates "$gates" --argjson sealed_manifest "$(jq -c . "$manifest")" '
      .schema == $schema and .run_manifest_sha256 == $sha
      and .run_uuid == $run_uuid and .run_id == $run_id and .source_head == $source_head
      and (.gate_states | length) == 12
      and ([.gate_states[] | {ordinal,name}]) == $gates
      and ([.gate_states[].name] | unique | length) == 12
      and ([.gate_states[] | select(.state != "PASS" and .state != $pending_gate)] | length) == 0
      and (.first_runtime_mutation_at_utc == $pending or (.first_runtime_mutation_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.failsafe_at_utc == $pending or (.failsafe_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.hard_cap_at_utc == $pending or (.hard_cap_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.physical_ecr_image_digest == $pending or (.physical_ecr_image_digest | test("^sha256:[0-9a-f]{64}$")))
      and (.ecr_digest_sealed_at_utc == $pending or (.ecr_digest_sealed_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.image_source_revision == $pending or (.image_source_revision | test("^[0-9a-f]{40}$")))
      and (.test_started_at_utc == $pending or (.test_started_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.test_terminal_at_utc == $pending or (.test_terminal_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.teardown_started_at_utc == $pending or (.teardown_started_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.teardown_completed_at_utc == $pending or (.teardown_completed_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.failsafe_active | type == "boolean")
      and .direct_budget_name == $sealed_manifest.direct_budget_authority.budget_name
      and (.direct_budget_created | type == "boolean")
      and (.direct_budget_created_at_utc == $pending or (.direct_budget_created_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.direct_budget_readback_pass | type == "boolean")
      and (
        if has("direct_budget_visibility_attempt_count") then
          (.direct_budget_visibility_attempt_count | type == "number")
          and .direct_budget_visibility_attempt_count >= 0
          and (.direct_budget_visibility_first_attempt_at_utc == $pending or (.direct_budget_visibility_first_attempt_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
          and (.direct_budget_visibility_last_attempt_at_utc == $pending or (.direct_budget_visibility_last_attempt_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
          and (.direct_budget_visibility_stage as $stage
            | ["NOT_STARTED","BUDGET","NOTIFICATIONS","SUBSCRIBERS","STABILIZED","TIMEOUT"]
            | index($stage) != null)
          and (.direct_budget_visibility_stabilized | type == "boolean")
          and (.direct_budget_visibility_timeout | type == "boolean")
          and (.direct_budget_visibility_last_sanitized_result as $result | [
            "NOT_ATTEMPTED","BUDGET_NOT_FOUND","BUDGET_MATCH","NOTIFICATIONS_NOT_FOUND",
            "NOTIFICATIONS_INCOMPLETE","NOTIFICATIONS_MATCH","SUBSCRIBERS_NOT_FOUND",
            "SUBSCRIBERS_INCOMPLETE","BUDGET_UNEXPECTED_ERROR","BUDGET_SEMANTIC_MISMATCH",
            "NOTIFICATIONS_UNEXPECTED_ERROR","NOTIFICATIONS_SEMANTIC_MISMATCH",
            "SUBSCRIBERS_UNEXPECTED_ERROR","SUBSCRIBERS_SEMANTIC_MISMATCH","PASS","TIMEOUT"
          ] | index($result) != null)
          and (.direct_budget_visibility_stabilized and .direct_budget_visibility_timeout | not)
          and (
            if .direct_budget_visibility_attempt_count == 0 then
              .direct_budget_visibility_first_attempt_at_utc == $pending
              and .direct_budget_visibility_last_attempt_at_utc == $pending
              and .direct_budget_visibility_stage == "NOT_STARTED"
              and .direct_budget_visibility_last_sanitized_result == "NOT_ATTEMPTED"
              and (.direct_budget_visibility_stabilized | not)
              and (.direct_budget_visibility_timeout | not)
            else
              .direct_budget_created
              and .direct_budget_visibility_first_attempt_at_utc != $pending
              and .direct_budget_visibility_last_attempt_at_utc != $pending
              and ((.direct_budget_visibility_first_attempt_at_utc | fromdateiso8601) <= (.direct_budget_visibility_last_attempt_at_utc | fromdateiso8601))
            end
          )
          and (if .direct_budget_visibility_stabilized then
            .direct_budget_readback_pass
            and (.direct_budget_visibility_timeout | not)
            and .direct_budget_visibility_stage == "STABILIZED"
            and .direct_budget_visibility_last_sanitized_result == "PASS"
          else true end)
          and (if .direct_budget_visibility_timeout then
            (.direct_budget_readback_pass | not)
            and (.direct_budget_visibility_stabilized | not)
            and .direct_budget_visibility_stage == "TIMEOUT"
            and .direct_budget_visibility_last_sanitized_result == "TIMEOUT"
          else true end)
          and (if .direct_budget_readback_pass then .direct_budget_visibility_stabilized else true end)
        else
          .teardown_completed_at_utc != $pending
        end
      )
      and (.direct_budget_deletion_started_at_utc == $pending or (.direct_budget_deletion_started_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.direct_budget_deleted_at_utc == $pending or (.direct_budget_deleted_at_utc | test("^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$")))
      and (.direct_budget_residual_count == $pending or .direct_budget_residual_count == 0 or .direct_budget_residual_count == 1)
      and (if .direct_budget_readback_pass then .direct_budget_created else true end)
      and (if .direct_budget_deleted_at_utc != $pending then .direct_budget_created and .direct_budget_deletion_started_at_utc != $pending else true end)
      and (.runner | type == "object")
      and (.residual_billable_resource_count == $pending or ((.residual_billable_resource_count | type) == "number" and .residual_billable_resource_count >= 0))
      and (.created_resource_ids | type == "array")
      and (.retained_physical_resource_ids | type == "array")
      and (.gate_transitions | type == "array")
      and ([.template_transport_objects[] as $transport
        | $transport
        | select(
            (.checksum_sha256 | test("^[A-Za-z0-9+/]{43}=$") | not)
            or ([ $sealed_manifest.templates[]
              | select(.stack_name == $transport.stack_name and .object_key == $transport.key)
            ] | length) != 1
          )] | length) == 0
    ' "$state" >/dev/null || c3_contract_fail 'RUNTIME_STATE_CONTRACT_REJECTED'
}

c3_epoch() {
  date -u -j -f '%Y-%m-%dT%H:%M:%SZ' "$1" +%s 2>/dev/null || date -u -d "$1" +%s 2>/dev/null
}
c3_from_epoch() {
  date -u -r "$1" '+%Y-%m-%dT%H:%M:%SZ' 2>/dev/null || date -u -d "@$1" '+%Y-%m-%dT%H:%M:%SZ' 2>/dev/null
}

c3_validate_resolved_clock() {
  local state first_epoch failsafe_epoch hard_cap_epoch
  state="$1"
  first_epoch="$(c3_epoch "$(jq -r .first_runtime_mutation_at_utc "$state")")" || c3_contract_fail 'FIRST_RUNTIME_MUTATION_TIMESTAMP_INVALID'
  failsafe_epoch="$(c3_epoch "$(jq -r .failsafe_at_utc "$state")")" || c3_contract_fail 'FAILSAFE_TIMESTAMP_INVALID'
  hard_cap_epoch="$(c3_epoch "$(jq -r .hard_cap_at_utc "$state")")" || c3_contract_fail 'HARD_CAP_TIMESTAMP_INVALID'
  [ $((failsafe_epoch - first_epoch)) -eq 79200 ] || c3_contract_fail 'FAILSAFE_NOT_FIRST_MUTATION_PLUS_22H'
  [ $((hard_cap_epoch - first_epoch)) -eq 86400 ] || c3_contract_fail 'HARD_CAP_NOT_FIRST_MUTATION_PLUS_24H'
}

c3_gate_state() {
  jq -r --arg gate "$2" '.gate_states[] | select(.name == $gate) | .state' "$1"
}

c3_validate_phase() {
  local phase manifest state pass_1_to_11 gate_12
  phase="$1"; manifest="$2"; state="${3:-}"
  case "$phase" in PRE_RUNTIME_SEAL) c3_validate_manifest "$manifest"; return;; esac
  c3_validate_state_base "$manifest" "$state"
  pass_1_to_11="$(jq '[.gate_states[] | select(.ordinal <= 11 and .state == "PASS")] | length' "$state")"
  gate_12="$(c3_gate_state "$state" ECR_DIGEST_SEALED_BEFORE_RUNNER)"
  case "$phase" in
    PRE_FIRST_WRITE)
      [ "$pass_1_to_11" = '11' ] || c3_contract_fail 'PRE_FIRST_WRITE_GATE_1_TO_11_NOT_PASS'
      [ "$gate_12" = "$C3_PENDING_RUNTIME" ] || c3_contract_fail 'PRE_FIRST_WRITE_GATE_12_NOT_PENDING'
      jq -e --arg p "$C3_PENDING_RUNTIME_RESOLUTION" '
        .first_runtime_mutation_at_utc == $p and .failsafe_at_utc == $p
        and .hard_cap_at_utc == $p and .physical_ecr_image_digest == $p
      ' "$state" >/dev/null || c3_contract_fail 'PRE_FIRST_WRITE_RUNTIME_FIELD_NOT_PENDING'
      ;;
    POST_FIRST_RUNTIME_MUTATION)
      [ "$pass_1_to_11" = '11' ] || c3_contract_fail 'POST_MUTATION_GATE_1_TO_11_NOT_PASS'
      c3_validate_resolved_clock "$state"
      ;;
    PRE_RUNNER|PRE_TEST)
      [ "$(jq '[.gate_states[] | select(.state == "PASS")] | length' "$state")" = '12' ] || c3_contract_fail 'ALL_12_GATES_REQUIRED'
      [ "$gate_12" = "$C3_PASS" ] || c3_contract_fail 'GATE_12_NOT_PASS'
      c3_validate_resolved_clock "$state"
      jq -e --arg head "$(jq -r .source_head "$manifest")" '
        (.physical_ecr_image_digest | test("^sha256:[0-9a-f]{64}$")) and .image_source_revision == $head
      ' "$state" >/dev/null || c3_contract_fail 'IMAGE_PROVENANCE_NOT_SEALED'
      ;;
    PRE_TEARDOWN)
      [ "$pass_1_to_11" = '11' ] || c3_contract_fail 'PRE_TEARDOWN_GATE_1_TO_11_NOT_PASS'
      c3_validate_resolved_clock "$state"
      ;;
    *) c3_contract_fail "UNKNOWN_VALIDATION_PHASE:$phase";;
  esac
}

c3_atomic_state_update() {
  local manifest state filter directory temporary
  manifest="$1"; state="$2"; filter="$3"; shift 3
  directory="$(dirname "$state")"
  temporary="$(mktemp "$directory/.mxmed-c3-runtime-state.XXXXXX")"
  trap 'rm -f "$temporary"' HUP INT TERM
  if ! jq "$@" "$filter" "$state" >"$temporary"; then
    rm -f "$temporary"
    trap - HUP INT TERM
    c3_contract_fail 'RUNTIME_STATE_ATOMIC_UPDATE_FAILED'
    return 1
  fi
  chmod 0600 "$temporary"
  if ! c3_validate_state_base "$manifest" "$temporary"; then
    rm -f "$temporary"
    trap - HUP INT TERM
    return 1
  fi
  mv "$temporary" "$state"
  trap - HUP INT TERM
}

c3_initialize_state() {
  local manifest state manifest_sha gates temporary
  manifest="$1"; state="$2"
  c3_validate_manifest "$manifest"
  [ ! -e "$state" ] || c3_contract_fail 'RUNTIME_STATE_ALREADY_EXISTS'
  manifest_sha="$(c3_sha256 "$manifest")"; gates="$(c3_gate_definitions_json)"
  temporary="$(mktemp "$(dirname "$state")/.mxmed-c3-runtime-state.XXXXXX")"
  trap 'rm -f "$temporary"' HUP INT TERM
  if ! jq -n --arg schema "$C3_STATE_SCHEMA" --arg sha "$manifest_sha" \
    --arg run_uuid "$(jq -r .run_uuid "$manifest")" --arg run_id "$(jq -r .run_id "$manifest")" \
    --arg source_head "$(jq -r .source_head "$manifest")" \
    --arg pending "$C3_PENDING_RUNTIME_RESOLUTION" --arg pending_gate "$C3_PENDING_RUNTIME" \
    --arg budget_name "$(jq -r .direct_budget_authority.budget_name "$manifest")" --argjson gates "$gates" '
      {
        schema:$schema,run_manifest_sha256:$sha,run_uuid:$run_uuid,run_id:$run_id,source_head:$source_head,
        phase:"PRE_FIRST_WRITE",
        gate_states:($gates|map(.+{state:(if .ordinal==12 then $pending_gate else "PASS" end)})),
        gate_transitions:($gates|map({gate:.name,from:null,to:(if .ordinal==12 then $pending_gate else "PASS" end),at_utc:null})),
        first_runtime_mutation_at_utc:$pending,failsafe_at_utc:$pending,hard_cap_at_utc:$pending,
        physical_ecr_image_digest:$pending,ecr_digest_sealed_at_utc:$pending,image_source_revision:$pending,
        test_started_at_utc:$pending,test_terminal_at_utc:$pending,
        teardown_started_at_utc:$pending,teardown_completed_at_utc:$pending,
        failsafe_active:false,created_resource_ids:[],retained_physical_resource_ids:[],
        direct_budget_name:$budget_name,direct_budget_created:false,direct_budget_created_at_utc:$pending,
        direct_budget_readback_pass:false,direct_budget_deletion_started_at_utc:$pending,
        direct_budget_deleted_at_utc:$pending,direct_budget_residual_count:$pending,
        direct_budget_visibility_attempt_count:0,
        direct_budget_visibility_first_attempt_at_utc:$pending,
        direct_budget_visibility_last_attempt_at_utc:$pending,
        direct_budget_visibility_stage:"NOT_STARTED",
        direct_budget_visibility_stabilized:false,
        direct_budget_visibility_timeout:false,
        direct_budget_visibility_last_sanitized_result:"NOT_ATTEMPTED",
        template_transport_objects:[],change_set_template_semantic_sha256:{},runner:{},
        orphan_inventory:[],residual_billable_resource_count:$pending
      }
    ' >"$temporary"; then
    rm -f "$temporary"
    trap - HUP INT TERM
    c3_contract_fail 'RUNTIME_STATE_INITIALIZATION_WRITE_FAILED'
    return 1
  fi
  chmod 0600 "$temporary"
  if ! c3_validate_phase PRE_FIRST_WRITE "$manifest" "$temporary"; then
    rm -f "$temporary"
    trap - HUP INT TERM
    return 1
  fi
  mv "$temporary" "$state"
  trap - HUP INT TERM
}

c3_record_first_runtime_mutation() {
  local manifest state timestamp first_epoch failsafe hard_cap
  manifest="$1"; state="$2"; timestamp="$3"
  [ "$(jq -r .first_runtime_mutation_at_utc "$state")" = "$C3_PENDING_RUNTIME_RESOLUTION" ] || c3_contract_fail 'FIRST_RUNTIME_MUTATION_TIMESTAMP_REWRITE_REJECTED'
  first_epoch="$(c3_epoch "$timestamp")" || c3_contract_fail 'FIRST_RUNTIME_MUTATION_TIMESTAMP_INVALID'
  failsafe="$(c3_from_epoch $((first_epoch + 79200)))"; hard_cap="$(c3_from_epoch $((first_epoch + 86400)))"
  c3_atomic_state_update "$manifest" "$state" '
    .phase="POST_FIRST_RUNTIME_MUTATION"|.first_runtime_mutation_at_utc=$first
    |.failsafe_at_utc=$failsafe|.hard_cap_at_utc=$hard_cap
  ' --arg first "$timestamp" --arg failsafe "$failsafe" --arg hard_cap "$hard_cap"
  c3_validate_phase POST_FIRST_RUNTIME_MUTATION "$manifest" "$state"
}

c3_record_direct_budget_created() {
  local manifest state timestamp
  manifest="$1"; state="$2"; timestamp="$3"
  [ "$(jq -r .direct_budget_created "$state")" = false ] || c3_contract_fail 'DIRECT_BUDGET_CREATE_REWRITE_REJECTED'
  c3_atomic_state_update "$manifest" "$state" '
    .direct_budget_created=true|.direct_budget_created_at_utc=$timestamp
    |.created_resource_ids += [{type:"direct-budget",id:.direct_budget_name}]
  ' --arg timestamp "$timestamp"
}

c3_record_direct_budget_readback() {
  c3_record_direct_budget_visibility_stabilized "$1" "$2"
}

c3_record_direct_budget_visibility_attempt() {
  local manifest state timestamp stage result previous last_epoch timestamp_epoch
  manifest="$1"; state="$2"; timestamp="$3"; stage="$4"; result="$5"
  [ "$(jq -r .direct_budget_created "$state")" = true ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_BEFORE_CREATE_REJECTED'
  [ "$(jq -r .direct_budget_visibility_stabilized "$state")" = false ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_AFTER_STABILIZED_REJECTED'
  [ "$(jq -r .direct_budget_visibility_timeout "$state")" = false ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_AFTER_TIMEOUT_REJECTED'
  case "$stage:$result" in
    BUDGET:BUDGET_NOT_FOUND|BUDGET:BUDGET_MATCH|BUDGET:BUDGET_UNEXPECTED_ERROR|BUDGET:BUDGET_SEMANTIC_MISMATCH|NOTIFICATIONS:NOTIFICATIONS_NOT_FOUND|NOTIFICATIONS:NOTIFICATIONS_INCOMPLETE|NOTIFICATIONS:NOTIFICATIONS_MATCH|NOTIFICATIONS:NOTIFICATIONS_UNEXPECTED_ERROR|NOTIFICATIONS:NOTIFICATIONS_SEMANTIC_MISMATCH|SUBSCRIBERS:SUBSCRIBERS_NOT_FOUND|SUBSCRIBERS:SUBSCRIBERS_INCOMPLETE|SUBSCRIBERS:SUBSCRIBERS_UNEXPECTED_ERROR|SUBSCRIBERS:SUBSCRIBERS_SEMANTIC_MISMATCH|SUBSCRIBERS:PASS) ;;
    *) c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_STAGE_RESULT_INVALID'; return 1;;
  esac
  timestamp_epoch="$(c3_epoch "$timestamp")" || { c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_TIMESTAMP_INVALID'; return 1; }
  previous="$(jq -r .direct_budget_visibility_last_attempt_at_utc "$state")"
  if [ "$previous" != "$C3_PENDING_RUNTIME_RESOLUTION" ]; then
    last_epoch="$(c3_epoch "$previous")" || { c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_LAST_TIMESTAMP_INVALID'; return 1; }
    [ "$timestamp_epoch" -ge "$last_epoch" ] \
      || { c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_TIMESTAMP_NOT_MONOTONIC'; return 1; }
  fi
  c3_atomic_state_update "$manifest" "$state" '
    .direct_budget_visibility_attempt_count += 1
    |if .direct_budget_visibility_first_attempt_at_utc == $pending
      then .direct_budget_visibility_first_attempt_at_utc=$timestamp else . end
    |.direct_budget_visibility_last_attempt_at_utc=$timestamp
    |.direct_budget_visibility_stage=$stage
    |.direct_budget_visibility_last_sanitized_result=$result
  ' --arg pending "$C3_PENDING_RUNTIME_RESOLUTION" --arg timestamp "$timestamp" \
    --arg stage "$stage" --arg result "$result"
}

c3_record_direct_budget_visibility_stabilized() {
  local manifest state
  manifest="$1"; state="$2"
  [ "$(jq -r .direct_budget_created "$state")" = true ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_STABILIZED_BEFORE_CREATE_REJECTED'
  [ "$(jq -r .direct_budget_visibility_attempt_count "$state")" -gt 0 ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_STABILIZED_WITHOUT_ATTEMPT_REJECTED'
  [ "$(jq -r .direct_budget_visibility_stabilized "$state")" = false ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_STABILIZED_REWRITE_REJECTED'
  [ "$(jq -r .direct_budget_visibility_timeout "$state")" = false ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_STABILIZED_AFTER_TIMEOUT_REJECTED'
  [ "$(jq -r .direct_budget_visibility_stage "$state")" = 'SUBSCRIBERS' ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_STABILIZED_BEFORE_SUBSCRIBERS_REJECTED'
  [ "$(jq -r .direct_budget_visibility_last_sanitized_result "$state")" = 'PASS' ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_STABILIZED_WITHOUT_PASS_REJECTED'
  c3_atomic_state_update "$manifest" "$state" '
    .direct_budget_visibility_stabilized=true
    |.direct_budget_visibility_stage="STABILIZED"
    |.direct_budget_readback_pass=true
  '
}

c3_record_direct_budget_visibility_timeout() {
  local manifest state
  manifest="$1"; state="$2"
  [ "$(jq -r .direct_budget_created "$state")" = true ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_TIMEOUT_BEFORE_CREATE_REJECTED'
  [ "$(jq -r .direct_budget_visibility_attempt_count "$state")" -gt 0 ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_TIMEOUT_WITHOUT_ATTEMPT_REJECTED'
  [ "$(jq -r .direct_budget_visibility_stabilized "$state")" = false ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_TIMEOUT_AFTER_STABILIZED_REJECTED'
  [ "$(jq -r .direct_budget_visibility_timeout "$state")" = false ] \
    || c3_contract_fail 'DIRECT_BUDGET_VISIBILITY_TIMEOUT_REWRITE_REJECTED'
  c3_atomic_state_update "$manifest" "$state" '
    .direct_budget_visibility_timeout=true
    |.direct_budget_visibility_stage="TIMEOUT"
    |.direct_budget_visibility_last_sanitized_result="TIMEOUT"
  '
}

c3_record_direct_budget_deleted() {
  local manifest state started deleted current_started
  manifest="$1"; state="$2"; started="$3"; deleted="$4"
  [ "$(jq -r .direct_budget_created "$state")" = true ] || c3_contract_fail 'DIRECT_BUDGET_DELETE_BEFORE_CREATE_REJECTED'
  [ "$(jq -r .direct_budget_deleted_at_utc "$state")" = "$C3_PENDING_RUNTIME_RESOLUTION" ] || c3_contract_fail 'DIRECT_BUDGET_DELETE_REWRITE_REJECTED'
  current_started="$(jq -r .direct_budget_deletion_started_at_utc "$state")"
  [ "$current_started" = "$C3_PENDING_RUNTIME_RESOLUTION" ] || [ "$current_started" = "$started" ] \
    || c3_contract_fail 'DIRECT_BUDGET_DELETE_START_REWRITE_REJECTED'
  c3_atomic_state_update "$manifest" "$state" '
    .direct_budget_deletion_started_at_utc=$started|.direct_budget_deleted_at_utc=$deleted
  ' --arg started "$started" --arg deleted "$deleted"
}

c3_seal_ecr_digest() {
  local manifest state digest source_revision timestamp
  manifest="$1"; state="$2"; digest="$3"; source_revision="$4"; timestamp="$5"
  [ "$source_revision" = "$(jq -r .source_head "$manifest")" ] || c3_contract_fail 'IMAGE_SOURCE_REVISION_MANIFEST_MISMATCH'
  case "$digest" in sha256:[0-9a-f][0-9a-f]*) ;; *) c3_contract_fail 'PHYSICAL_ECR_DIGEST_INVALID';; esac
  [ "$(jq -r .physical_ecr_image_digest "$state")" = "$C3_PENDING_RUNTIME_RESOLUTION" ] || c3_contract_fail 'PHYSICAL_ECR_DIGEST_REWRITE_REJECTED'
  [ "$(c3_gate_state "$state" ECR_DIGEST_SEALED_BEFORE_RUNNER)" = "$C3_PENDING_RUNTIME" ] || c3_contract_fail 'GATE_12_REWRITE_REJECTED'
  c3_atomic_state_update "$manifest" "$state" '
    .physical_ecr_image_digest=$digest|.ecr_digest_sealed_at_utc=$timestamp|.image_source_revision=$source
    |(.gate_states[]|select(.name=="ECR_DIGEST_SEALED_BEFORE_RUNNER")|.state)="PASS"
    |.gate_transitions += [{gate:"ECR_DIGEST_SEALED_BEFORE_RUNNER",from:"PENDING_RUNTIME",to:"PASS",at_utc:$timestamp}]
  ' --arg digest "$digest" --arg timestamp "$timestamp" --arg source "$source_revision"
  c3_validate_phase PRE_RUNNER "$manifest" "$state"
}

if [ "${0##*/}" = 'c3-runtime-contract.sh' ]; then
  command="${1:-}"; shift || true
  case "$command" in
    validate-manifest) c3_validate_phase PRE_RUNTIME_SEAL "$1";;
    init-state) c3_initialize_state "$1" "$2";;
    validate-phase) c3_validate_phase "$1" "$2" "${3:-}";;
    record-first-mutation) c3_record_first_runtime_mutation "$1" "$2" "$3";;
    record-budget-created) c3_record_direct_budget_created "$1" "$2" "$3";;
    record-budget-readback) c3_record_direct_budget_readback "$1" "$2";;
    record-budget-visibility-attempt) c3_record_direct_budget_visibility_attempt "$1" "$2" "$3" "$4" "$5";;
    record-budget-visibility-stabilized) c3_record_direct_budget_visibility_stabilized "$1" "$2";;
    record-budget-visibility-timeout) c3_record_direct_budget_visibility_timeout "$1" "$2";;
    record-budget-deleted) c3_record_direct_budget_deleted "$1" "$2" "$3" "$4";;
    seal-digest) c3_seal_ecr_digest "$1" "$2" "$3" "$4" "$5";;
    *) c3_contract_fail 'COMMAND_REQUIRED';;
  esac
fi
