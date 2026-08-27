import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { chmodSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const repositoryRoot = join(__dirname, '../../..');
const helper = join(repositoryRoot, 'scripts/aws/c3-runtime-contract.sh');
const deployController = join(repositoryRoot, 'scripts/aws/c3-ephemeral-deploy.sh');
const teardownController = join(repositoryRoot, 'scripts/aws/c3-ephemeral-teardown.sh');
const hash = 'a'.repeat(64);
const otherHash = 'b'.repeat(64);
const sourceHead = '1'.repeat(40);
const runUuid = '123e4567-e89b-42d3-a456-426614174000';
const runId = `c3-${runUuid}`;
const budgetNotificationTopicArn =
  'arn:aws:sns:mx-central-1:875691018466:mxmed-stg-c3-notifications';

function canonical(value: unknown): string {
  if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
  if (value !== null && typeof value === 'object') {
    return `{${Object.entries(value as Record<string, unknown>)
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([key, item]) => `${JSON.stringify(key)}:${canonical(item)}`)
      .join(',')}}`;
  }
  return JSON.stringify(value);
}

const gates = [
  'SOURCE_HEAD_MATCH',
  'WORKTREE_CLEAN',
  'FRESH_DIRECTOR_RUNTIME_AUTHORIZATION_PRESENT',
  'PRODUCTION_DENY_PROVEN',
  'SEALED_TEMPLATE_AND_RESOURCE_SCOPE_PASS',
  'ESTIMATED_COST_WITHIN_USD_5_CAP',
  'MANUAL_TEARDOWN_READY',
  'AUTO_TEARDOWN_FAILSAFE_CONTRACT_READY',
  'RETAINED_RESOURCE_CLEANUP_READY',
  'NONPRODUCTION_TARGET_PROVEN',
  'ROLE_CHAIN_EXACT_PASS',
  'ECR_DIGEST_SEALED_BEFORE_RUNNER',
].map((name, index) => ({ ordinal: index + 1, name }));

function manifestFixture(): Record<string, unknown> {
  const stacks = [
    'mxmed-stg-c3-janitor',
    'mxmed-stg-network',
    'mxmed-stg-security',
    'mxmed-stg-session',
    'mxmed-stg-registry',
    'mxmed-stg-c3-runner',
  ];
  const budget = {
    BudgetName: `mxmed-stg-c3-${runId}`,
    BudgetType: 'COST',
    TimeUnit: 'MONTHLY',
    BudgetLimit: { Amount: '5', Unit: 'USD' },
    CostFilters: { TagKeyValue: ['user:Phase$C3'] },
  };
  const notifications_with_subscribers = [1, 3, 5].map((Threshold) => ({
    Notification: {
      ComparisonOperator: 'GREATER_THAN',
      NotificationType: 'ACTUAL',
      Threshold,
      ThresholdType: 'ABSOLUTE_VALUE',
    },
    Subscribers: [{ SubscriptionType: 'SNS', Address: budgetNotificationTopicArn }],
  }));
  const payload_sha256 = createHash('sha256')
    .update(`${canonical({ budget, notifications_with_subscribers })}\n`)
    .digest('hex');
  return {
    schema: 'mxmed.c3.ephemeral.sealed-run-manifest.v3',
    run_uuid: runUuid,
    run_id: runId,
    source_head: sourceHead,
    account: '875691018466',
    region: 'mx-central-1',
    deployment_mode: 'DIRECT_CLOUDFORMATION_FROM_SEALED_TEMPLATES',
    director_authorization_reference: 'DIRECTOR-SEAL-C3',
    activity_cost_cap_usd: 5,
    budget_notification_topic_arn: budgetNotificationTopicArn,
    direct_budget_authority: {
      api_region: 'us-east-1',
      account_id: '875691018466',
      budget_name_format: 'mxmed-stg-c3-${RUN_ID}',
      budget_name: `mxmed-stg-c3-${runId}`,
      payload_sha256,
      runtime_object_count: 1,
      cleanup_contract: 'EXACT_RUN_ID_BOUND_DESCRIBE_DELETE_ABSENCE',
      budget,
      notifications_with_subscribers,
    },
    c3_permission_boundary_arn:
      'arn:aws:iam::875691018466:policy/MXMed-C3-Staging-PermissionBoundary',
    runtime_clock_contract: {
      origin: 'FIRST_SUCCESSFUL_RUNTIME_AWS_MUTATION',
      failsafe_offset_hours: 22,
      hard_cap_offset_hours: 24,
      teardown_start_max_delay_seconds: 300,
    },
    pending_runtime_fields: {
      first_runtime_mutation_at_utc: 'PENDING_RUNTIME_RESOLUTION',
      failsafe_at_utc: 'PENDING_RUNTIME_RESOLUTION',
      hard_cap_at_utc: 'PENDING_RUNTIME_RESOLUTION',
      physical_ecr_image_digest: 'PENDING_RUNTIME_RESOLUTION',
    },
    object_key_contract: {
      canonical_format: 'RUN_ID/STACK_NAME/TEMPLATE_SHA256.template.json',
      suffix: '.template.json',
      binds_run_id: true,
      binds_stack_name: true,
      binds_template_sha256: true,
      path_traversal_safe: true,
    },
    gate_definitions: gates,
    phase_requirements: {
      pre_first_write: { required_pass_count: 11, gate_12_state: 'PENDING_RUNTIME' },
      pre_runner: { required_pass_count: 12, gate_12_state: 'PASS' },
    },
    template_sha256: hash,
    templates: stacks.map((stackName, index) => ({
      stack_name: stackName,
      template_file: `${stackName}.template.json`,
      bytes: 100 + index,
      sha256: index === 1 ? otherHash : hash,
      transport: index === 1 ? 'C3_TEMPLATE_S3_URL' : 'TEMPLATE_BODY',
      object_key: index === 1 ? `${runId}/${stackName}/${otherHash}.template.json` : null,
    })),
    template_transport: {
      bucket_name: 'mxmed-stg-c3-cf-templates-875691018466-mx-central-1',
      region: 'mx-central-1',
      public_access_blocked: true,
      default_encryption: 'AES256',
      versioning: false,
      ephemeral: true,
      delete_after_c3: true,
    },
    source_sha256: hash,
    script_sha256: hash,
    policy_sha256: { 'infra/aws/policies/c3/example.json': hash },
    image_build_inputs: {
      source_revision: sourceHead,
      php_base_image_reference: `php:8.4-cli@sha256:${hash}`,
      php_base_image_digest: `sha256:${hash}`,
      aws_cli_archive_sha256: hash,
      phpredis_version: '6.2.0',
      dockerfile_sha256: hash,
      entrypoint_sha256: hash,
    },
    approved_role_profiles: {
      deploy: 'mxmed-c3-stg-deploy',
      test_controller: 'mxmed-c3-stg-test-controller',
      teardown: 'mxmed-c3-stg-teardown',
    },
    cfn_execution_role_arns: {
      'mxmed-stg-network': 'arn:aws:iam::875691018466:role/MXMed-C3-CFN-Network',
      'mxmed-stg-security': 'arn:aws:iam::875691018466:role/MXMed-C3-CFN-Security',
      'mxmed-stg-session': 'arn:aws:iam::875691018466:role/MXMed-C3-CFN-Session',
      'mxmed-stg-registry': 'arn:aws:iam::875691018466:role/MXMed-C3-CFN-Registry',
      'mxmed-stg-c3-runner': 'arn:aws:iam::875691018466:role/MXMed-C3-CFN-Runner',
      'mxmed-stg-c3-janitor': 'arn:aws:iam::875691018466:role/MXMed-C3-CFN-Janitor',
    },
    stack_names: stacks,
    expected_resource_counts: {
      cloudformation: 106,
      direct_runtime: 1,
      total_authorized: 107,
      data: 0,
      storage: 0,
      application_service: 0,
      public_runner_ip: 0,
    },
    expected_resource_graph: {
      'mxmed-stg-c3-janitor': 8,
      'mxmed-stg-network': 20,
      'mxmed-stg-security': 30,
      'mxmed-stg-session': 20,
      'mxmed-stg-registry': 10,
      'mxmed-stg-c3-runner': 18,
    },
    retained_resource_expectations: { count: 13, physical_resources: [] },
  };
}

describe('C3 phase-aware immutable manifest and runtime state contract', () => {
  let directory: string;
  let manifestPath: string;
  let statePath: string;

  const writeManifest = (manifest = manifestFixture()): void => {
    writeFileSync(manifestPath, `${JSON.stringify(manifest)}\n`, { mode: 0o600 });
    chmodSync(manifestPath, 0o600);
  };
  const run = (...args: string[]) =>
    spawnSync(helper, args, { cwd: repositoryRoot, encoding: 'utf8' });
  const expectAccepted = (...args: string[]): void => {
    const result = run(...args);
    expect({ status: result.status, stderr: result.stderr }).toEqual({ status: 0, stderr: '' });
  };
  const expectRejected = (...args: string[]): void => {
    expect(run(...args).status).not.toBe(0);
  };

  const installBudgetAwsStub = (
    scenario: string,
  ): {
    PATH: string;
    AWS_LOG: string;
    DESCRIBE_COUNT: string;
    NOTIFICATION_COUNT: string;
    CLOCK_FILE: string;
    SLEEP_LOG: string;
    SCENARIO: string;
  } => {
    const awsLog = join(directory, 'aws.log');
    const describeCount = join(directory, 'describe.count');
    const notificationCount = join(directory, 'notification.count');
    const clock = join(directory, 'clock');
    const sleepLog = join(directory, 'sleep.log');
    writeFileSync(clock, '1787702400\n');
    writeFileSync(
      join(directory, 'aws'),
      `#!/bin/sh
set -eu
printf '%s\\n' "$*" >> "$AWS_LOG"
next_count() {
  file="$1"
  if [ -f "$file" ]; then count="$(sed -n '1p' "$file")"; else count=0; fi
  count=$((count + 1))
  printf '%s\\n' "$count" > "$file"
  printf '%s\\n' "$count"
}
not_found() { printf '%s\\n' 'NotFoundException: exact budget not found' >&2; exit 254; }
budget='{"BudgetName":"mxmed-stg-c3-${runId}","BudgetType":"COST","TimeUnit":"MONTHLY","BudgetLimit":{"Amount":"5.0","Unit":"USD"},"CostFilters":{"TagKeyValue":["user:Phase$C3"]}}'
notifications='[{"Threshold":"5.0","ThresholdType":"ABSOLUTE_VALUE","NotificationType":"ACTUAL","ComparisonOperator":"GREATER_THAN","NotificationState":"OK"},{"Threshold":1.0,"ThresholdType":"ABSOLUTE_VALUE","NotificationType":"ACTUAL","ComparisonOperator":"GREATER_THAN","NotificationState":"ALARM"},{"Threshold":"3","ThresholdType":"ABSOLUTE_VALUE","NotificationType":"ACTUAL","ComparisonOperator":"GREATER_THAN","NotificationState":"OK"}]'
case "$2" in
  describe-budget)
    count="$(next_count "$DESCRIBE_COUNT")"
    case "$SCENARIO" in
      budget-notfound-once) [ "$count" -eq 1 ] && not_found ;;
      guard-notfound) not_found ;;
      guard-late) [ "$count" -ne 2 ] && not_found ;;
      guard-expire) [ $((count % 2)) -eq 1 ] && not_found ;;
    esac
    printf '%s\\n' "$budget"
    ;;
  describe-notifications-for-budget)
    count="$(next_count "$NOTIFICATION_COUNT")"
    if [ "$SCENARIO" = incomplete-notifications-once ] && [ "$count" -eq 1 ]; then
      printf '%s\\n' '[{"Threshold":1,"ThresholdType":"ABSOLUTE_VALUE","NotificationType":"ACTUAL","ComparisonOperator":"GREATER_THAN","NotificationState":"OK"}]'
    else
      printf '%s\\n' "$notifications"
    fi
    ;;
  describe-subscribers-for-notification)
    if [ "$SCENARIO" = missing-subscriber ]; then printf '%s\\n' '[]';
    else printf '%s\\n' '[{"Address":"${budgetNotificationTopicArn}","SubscriptionType":"SNS"}]'; fi
    ;;
  delete-budget) ;;
  create-budget) ;;
  *) printf '%s\\n' "unexpected aws operation: $*" >&2; exit 64 ;;
esac
`,
      { mode: 0o700 },
    );
    chmodSync(join(directory, 'aws'), 0o700);
    writeFileSync(
      join(directory, 'sleep'),
      `#!/bin/sh
set -eu
printf '%s\\n' "$1" >> "$SLEEP_LOG"
if [ -n "\${CLOCK_FILE:-}" ]; then
  current="$(sed -n '1p' "$CLOCK_FILE")"
  printf '%s\\n' $((current + $1)) > "$CLOCK_FILE"
fi
`,
      { mode: 0o700 },
    );
    chmodSync(join(directory, 'sleep'), 0o700);
    writeFileSync(
      join(directory, 'date'),
      `#!/bin/sh
set -eu
if [ -z "\${CLOCK_FILE:-}" ]; then exec /bin/date "$@"; fi
current="$(sed -n '1p' "$CLOCK_FILE")"
case "$*" in
  *+%s*) printf '%s\\n' "$current" ;;
  *+%Y-%m-%dT%H:%M:%SZ*) /bin/date -u -r "$current" '+%Y-%m-%dT%H:%M:%SZ' ;;
  *) /bin/date "$@" ;;
esac
`,
      { mode: 0o700 },
    );
    chmodSync(join(directory, 'date'), 0o700);
    return {
      PATH: `${directory}:${process.env.PATH ?? '/usr/bin:/bin'}`,
      AWS_LOG: awsLog,
      DESCRIBE_COUNT: describeCount,
      NOTIFICATION_COUNT: notificationCount,
      CLOCK_FILE: clock,
      SLEEP_LOG: sleepLog,
      SCENARIO: scenario,
    };
  };

  const initializeCreatedBudgetState = (): void => {
    expectAccepted('init-state', manifestPath, statePath);
    expectAccepted('record-budget-created', manifestPath, statePath, '2026-08-25T12:01:00Z');
  };

  const runDeployVisibilityWaiter = (scenario: string) => {
    initializeCreatedBudgetState();
    const stub = installBudgetAwsStub(scenario);
    return spawnSync(
      'sh',
      [
        '-c',
        `set -eu
MXMED_C3_SOURCE_BUDGET_HELPERS_ONLY=1 . "$1"
manifest="$2"
state="$3"
MXMED_C3_DEPLOY_PROFILE=mxmed-c3-stg-deploy
budget_name="$(jq -r .direct_budget_authority.budget_name "$manifest")"
budget_json="$(jq -c .direct_budget_authority.budget "$manifest")"
notifications_json="$(jq -c .direct_budget_authority.notifications_with_subscribers "$manifest")"
wait_for_direct_budget_visibility`,
        'sh',
        deployController,
        manifestPath,
        statePath,
      ],
      { cwd: repositoryRoot, encoding: 'utf8', env: { ...process.env, ...stub, CLOCK_FILE: '' } },
    );
  };

  const runTeardownBudgetGuard = (scenario: string) => {
    initializeCreatedBudgetState();
    const stub = installBudgetAwsStub(scenario);
    return {
      result: spawnSync(
        'sh',
        [
          '-c',
          `set -eu
set --
MXMED_C3_SOURCE_BUDGET_HELPERS_ONLY=1 . "$TEARDOWN_CONTROLLER"
manifest="$MANIFEST_PATH"
state="$STATE_PATH"
cleanup_exact_direct_budget`,
        ],
        {
          cwd: repositoryRoot,
          encoding: 'utf8',
          env: {
            ...process.env,
            ...stub,
            MANIFEST_PATH: manifestPath,
            STATE_PATH: statePath,
            TEARDOWN_CONTROLLER: teardownController,
          },
        },
      ),
      stub,
    };
  };

  beforeEach(() => {
    directory = mkdtempSync(join(tmpdir(), 'mxmed-c3-runtime-contract-'));
    manifestPath = join(directory, 'manifest.json');
    statePath = join(directory, 'state.json');
    writeManifest();
  });

  afterEach(() => {
    rmSync(directory, { recursive: true, force: true });
  });

  test('accepts exactly 12 canonical gates and rejects the stale 10-gate model', () => {
    expectAccepted('validate-manifest', manifestPath);
    const stale = manifestFixture();
    stale.gate_definitions = gates.slice(0, 10);
    writeManifest(stale);
    expectRejected('validate-manifest', manifestPath);
  });

  test.each([
    ['missing', undefined],
    ['null', null],
    ['empty', ''],
    ['wildcard', 'arn:aws:sns:mx-central-1:875691018466:*'],
    ['wrong region', 'arn:aws:sns:us-east-1:875691018466:mxmed-stg-c3-notifications'],
    ['wrong account', 'arn:aws:sns:mx-central-1:000000000000:mxmed-stg-c3-notifications'],
    ['wrong topic', 'arn:aws:sns:mx-central-1:875691018466:mxmed-stg-other-notifications'],
    ['production-like', 'arn:aws:sns:mx-central-1:875691018466:mxmed-prd-c3-notifications'],
  ])('rejects %s budget notification topic authority at pre-seal', (_label, topicArn) => {
    const manifest = manifestFixture();
    if (topicArn === undefined) {
      delete manifest.budget_notification_topic_arn;
    } else {
      manifest.budget_notification_topic_arn = topicArn;
    }
    writeManifest(manifest);
    expectRejected('validate-manifest', manifestPath);
  });

  test('accepts only the exact staging budget topic and seals Gate 8', () => {
    const manifest = manifestFixture();
    manifest.budget_notification_topic_arn = budgetNotificationTopicArn;
    writeManifest(manifest);
    expectAccepted('validate-manifest', manifestPath);
    expectAccepted('init-state', manifestPath, statePath);
    const state = JSON.parse(readFileSync(statePath, 'utf8')) as {
      gate_states: { name: string; state: string }[];
    };
    expect(
      state.gate_states.find(({ name }) => name === 'AUTO_TEARDOWN_FAILSAFE_CONTRACT_READY')?.state,
    ).toBe('PASS');
  });

  test('rejects the original field omission before runtime-state initialization', () => {
    const manifest = manifestFixture();
    delete manifest.budget_notification_topic_arn;
    writeManifest(manifest);
    expectRejected('validate-manifest', manifestPath);
    expectRejected('init-state', manifestPath, statePath);
    expect(() => readFileSync(statePath, 'utf8')).toThrow();
  });

  test.each([
    ['missing', '', /UNSAFE_OR_EMPTY_MANIFEST_VALUE/],
    ['wildcard', 'arn:aws:sns:mx-central-1:875691018466:*', /UNSAFE_OR_EMPTY_MANIFEST_VALUE/],
    [
      'wrong region',
      'arn:aws:sns:us-east-1:875691018466:mxmed-stg-c3-notifications',
      /BUDGET_NOTIFICATION_TOPIC_ARN_INVALID/,
    ],
    [
      'wrong account',
      'arn:aws:sns:mx-central-1:000000000000:mxmed-stg-c3-notifications',
      /BUDGET_NOTIFICATION_TOPIC_ARN_INVALID/,
    ],
    [
      'wrong topic',
      'arn:aws:sns:mx-central-1:875691018466:mxmed-stg-other-notifications',
      /BUDGET_NOTIFICATION_TOPIC_ARN_INVALID/,
    ],
    [
      'production-like',
      'arn:aws:sns:mx-central-1:875691018466:mxmed-prd-c3-notifications',
      /BUDGET_NOTIFICATION_TOPIC_ARN_INVALID/,
    ],
  ])('generator rejects %s topic before creating a manifest', (_label, topicArn, error) => {
    const outputPath = join(directory, 'generated.json');
    const result = spawnSync(
      deployController,
      [
        '--prepare-run-manifest',
        '--output',
        outputPath,
        '--run-uuid',
        runUuid,
        '--run-id',
        runId,
        '--authorization-reference',
        'DIRECTOR-SEAL-C3',
        '--budget-topic-arn',
        topicArn,
        '--build-inputs',
        '/nonexistent',
      ],
      { cwd: repositoryRoot, encoding: 'utf8' },
    );
    expect(result.status).not.toBe(0);
    expect(result.stderr).toMatch(error);
    expect(() => readFileSync(outputPath, 'utf8')).toThrow();
  });

  test('direct Budget uses only the exact sealed topic without Janitor parameter fallback', () => {
    const deploy = readFileSync(deployController, 'utf8');
    const contract = readFileSync(helper, 'utf8');
    expect(contract).toContain(`C3_BUDGET_NOTIFICATION_TOPIC_ARN='${budgetNotificationTopicArn}'`);
    expect(contract).toContain('.budget_notification_topic_arn == $budget_topic');
    expect(deploy).toContain(
      `EXPECTED_BUDGET_NOTIFICATION_TOPIC_ARN='${budgetNotificationTopicArn}'`,
    );
    expect(deploy).toContain('--notifications-with-subscribers "$notifications_json"');
    expect(deploy).not.toContain('ParameterKey=BudgetNotificationTopicArn');
    expect(deploy).not.toMatch(/budget_notification_topic_arn\s*\/\//);
  });

  test('accepts pending runtime fields before first write but rejects them afterward', () => {
    expectAccepted('init-state', manifestPath, statePath);
    expectAccepted('validate-phase', 'PRE_FIRST_WRITE', manifestPath, statePath);
    expectRejected('validate-phase', 'POST_FIRST_RUNTIME_MUTATION', manifestPath, statePath);
    expectRejected('validate-phase', 'PRE_RUNNER', manifestPath, statePath);
  });

  test('seals the physical runtime clock once and derives exact +22h and +24h values', () => {
    expectAccepted('init-state', manifestPath, statePath);
    expectAccepted('record-first-mutation', manifestPath, statePath, '2026-08-25T12:00:00Z');
    const state = JSON.parse(readFileSync(statePath, 'utf8')) as Record<string, unknown>;
    expect(state.first_runtime_mutation_at_utc).toBe('2026-08-25T12:00:00Z');
    expect(state.failsafe_at_utc).toBe('2026-08-26T10:00:00Z');
    expect(state.hard_cap_at_utc).toBe('2026-08-26T12:00:00Z');
    expectAccepted('validate-phase', 'POST_FIRST_RUNTIME_MUTATION', manifestPath, statePath);
    expectRejected('record-first-mutation', manifestPath, statePath, '2026-08-25T12:00:01Z');
  });

  test('records the exact direct Budget lifecycle monotonically in the sidecar', () => {
    expectAccepted('init-state', manifestPath, statePath);
    expectAccepted('record-budget-created', manifestPath, statePath, '2026-08-25T12:01:00Z');
    expectAccepted(
      'record-budget-visibility-attempt',
      manifestPath,
      statePath,
      '2026-08-25T12:01:01Z',
      'SUBSCRIBERS',
      'PASS',
    );
    expectAccepted('record-budget-visibility-stabilized', manifestPath, statePath);
    expectRejected('record-budget-created', manifestPath, statePath, '2026-08-25T12:02:00Z');
    expectAccepted(
      'record-budget-deleted',
      manifestPath,
      statePath,
      '2026-08-25T13:00:00Z',
      '2026-08-25T13:00:01Z',
    );
    const state = JSON.parse(readFileSync(statePath, 'utf8')) as Record<string, unknown>;
    expect(state).toMatchObject({
      direct_budget_name: `mxmed-stg-c3-${runId}`,
      direct_budget_created: true,
      direct_budget_readback_pass: true,
      direct_budget_visibility_attempt_count: 1,
      direct_budget_visibility_first_attempt_at_utc: '2026-08-25T12:01:01Z',
      direct_budget_visibility_last_attempt_at_utc: '2026-08-25T12:01:01Z',
      direct_budget_visibility_stage: 'STABILIZED',
      direct_budget_visibility_stabilized: true,
      direct_budget_visibility_timeout: false,
      direct_budget_visibility_last_sanitized_result: 'PASS',
      direct_budget_residual_count: 'PENDING_RUNTIME_RESOLUTION',
    });
    expectRejected(
      'record-budget-deleted',
      manifestPath,
      statePath,
      '2026-08-25T13:00:00Z',
      '2026-08-25T13:00:02Z',
    );
  });

  test('binds exact-run Budget semantics and dual-region controller paths', () => {
    const deploy = readFileSync(deployController, 'utf8');
    const teardown = readFileSync(
      join(repositoryRoot, 'scripts/aws/c3-ephemeral-teardown.sh'),
      'utf8',
    );
    for (const source of [deploy, teardown]) {
      expect(source).toContain("BUDGETS_API_REGION='us-east-1'");
      expect(source).toContain('mxmed-stg-c3-$(jq -r .run_id "$manifest")');
    }
    expect(deploy).toContain('aws budgets create-budget --region "$BUDGETS_API_REGION"');
    expect(deploy).toContain('aws budgets describe-budget --region "$BUDGETS_API_REGION"');
    expect(teardown).toContain('aws budgets delete-budget --region "$BUDGETS_API_REGION"');
    expect(teardown).not.toMatch(/list-budgets/);
    expect(deploy).not.toMatch(/ResourceTags|aws-portal:/);
  });

  test('moves Gate 12 pending to PASS only for a digest and matching manifest revision', () => {
    expectAccepted('init-state', manifestPath, statePath);
    expectAccepted('record-first-mutation', manifestPath, statePath, '2026-08-25T12:00:00Z');
    expectRejected(
      'seal-digest',
      manifestPath,
      statePath,
      runId,
      sourceHead,
      '2026-08-25T13:00:00Z',
    );
    expectRejected(
      'seal-digest',
      manifestPath,
      statePath,
      `sha256:${hash}`,
      '2'.repeat(40),
      '2026-08-25T13:00:00Z',
    );
    expectAccepted(
      'seal-digest',
      manifestPath,
      statePath,
      `sha256:${hash}`,
      sourceHead,
      '2026-08-25T13:00:00Z',
    );
    expectAccepted('validate-phase', 'PRE_RUNNER', manifestPath, statePath);
    expectRejected(
      'seal-digest',
      manifestPath,
      statePath,
      `sha256:${otherHash}`,
      sourceHead,
      '2026-08-25T13:00:01Z',
    );
  });

  test.each([
    ['bare UUID', runUuid],
    ['malformed prefix', `C3-${runUuid}`],
  ])('rejects %s as RUN_ID', (_label, invalidRunId) => {
    const manifest = manifestFixture();
    manifest.run_id = invalidRunId;
    writeManifest(manifest);
    expectRejected('validate-manifest', manifestPath);
  });

  test('rejects a non-v4 RUN_UUID', () => {
    const manifest = manifestFixture();
    manifest.run_uuid = '123e4567-e89b-12d3-a456-426614174000';
    manifest.run_id = `c3-${manifest.run_uuid as string}`;
    writeManifest(manifest);
    expectRejected('validate-manifest', manifestPath);
  });

  test.each([
    ['alternate suffix', `${runId}/mxmed-stg-network/${otherHash}.json`],
    [
      'wrong run',
      `c3-223e4567-e89b-42d3-a456-426614174000/mxmed-stg-network/${otherHash}.template.json`,
    ],
    ['wrong stack', `${runId}/mxmed-stg-security/${otherHash}.template.json`],
    ['wrong hash', `${runId}/mxmed-stg-network/${hash}.template.json`],
    ['path traversal', `${runId}/../mxmed-stg-network/${otherHash}.template.json`],
  ])('rejects noncanonical object key: %s', (_label, objectKey) => {
    const manifest = manifestFixture();
    const templates = manifest.templates as Record<string, unknown>[];
    const networkTemplate = templates[1];
    if (networkTemplate === undefined) throw new Error('NETWORK_TEMPLATE_FIXTURE_MISSING');
    networkTemplate.object_key = objectKey;
    writeManifest(manifest);
    expectRejected('validate-manifest', manifestPath);
  });

  test('accepts only manifest-bound uploaded template keys in mutable state', () => {
    expectAccepted('init-state', manifestPath, statePath);
    const state = JSON.parse(readFileSync(statePath, 'utf8')) as Record<string, unknown>;
    state.template_transport_objects = [
      {
        stack_name: 'mxmed-stg-network',
        key: `${runId}/mxmed-stg-network/${otherHash}.template.json`,
        checksum_sha256: `${'A'.repeat(43)}=`,
      },
    ];
    writeFileSync(statePath, `${JSON.stringify(state)}\n`, { mode: 0o600 });
    chmodSync(statePath, 0o600);
    expectAccepted('validate-phase', 'PRE_FIRST_WRITE', manifestPath, statePath);
    const transport = (state.template_transport_objects as Record<string, unknown>[])[0];
    if (transport === undefined) throw new Error('TRANSPORT_FIXTURE_MISSING');
    transport.key = `${runId}/mxmed-stg-security/${otherHash}.template.json`;
    writeFileSync(statePath, `${JSON.stringify(state)}\n`, { mode: 0o600 });
    expectRejected('validate-phase', 'PRE_FIRST_WRITE', manifestPath, statePath);
  });

  test('binds mutable state cryptographically to immutable manifest bytes and mode 0600', () => {
    expectAccepted('init-state', manifestPath, statePath);
    const manifest = manifestFixture();
    manifest.activity_cost_cap_usd = 4;
    writeManifest(manifest);
    expectRejected('validate-phase', 'PRE_FIRST_WRITE', manifestPath, statePath);
    writeManifest();
    chmodSync(statePath, 0o644);
    expectRejected('validate-phase', 'PRE_FIRST_WRITE', manifestPath, statePath);
  });

  test('uses manifest source provenance without a fixed repository HEAD', () => {
    const deploy = readFileSync(deployController, 'utf8');
    const stack = readFileSync(
      join(repositoryRoot, 'infra/aws/lib/stacks/mxmed-c3-runner-stack.ts'),
      'utf8',
    );
    expect(deploy).toContain('ParameterKey=SourceRevision');
    expect(deploy).toContain('jq -r .source_head "$manifest"');
    expect(deploy).toContain('--build-arg "SOURCE_REVISION=$(jq -r .source_head "$manifest")"');
    expect(stack).toContain("new CfnParameter(this, 'SourceRevision'");
    expect(`${deploy}\n${stack}`).not.toContain('878237984810f1dddae48be2c73ed75cfbe34384');
  });

  test('prepares change sets in two reachable phases without the original digest cycle', () => {
    const deploy = readFileSync(deployController, 'utf8');
    const preDigest =
      /PRE_DIGEST_STACKS='([^']+)'/.exec(deploy)?.[1]?.split(' ').filter(Boolean) ?? [];
    const runner = /RUNNER_STACKS='([^']+)'/.exec(deploy)?.[1]?.split(' ').filter(Boolean) ?? [];
    const preparation =
      / {2}--prepare-change-sets\)([\s\S]*?)\n {2}--execute-stack\)/.exec(deploy)?.[1] ?? '';

    expect(preDigest).toEqual([
      'mxmed-stg-c3-janitor',
      'mxmed-stg-network',
      'mxmed-stg-security',
      'mxmed-stg-session',
      'mxmed-stg-registry',
    ]);
    expect(runner).toEqual(['mxmed-stg-c3-runner']);
    expect(preDigest).not.toContain('mxmed-stg-c3-runner');
    expect(preparation).toContain('pre-digest)');
    expect(preparation).toContain('require_future_write_authority POST_FIRST_RUNTIME_MUTATION');
    expect(preparation).toContain('PRE_DIGEST_CHANGE_SET_GATE_12_NOT_PENDING');
    expect(preparation).toContain('prepare_stacks="$PRE_DIGEST_STACKS"');
    expect(preparation).toContain('runner)');
    expect(preparation).toContain('require_future_write_authority PRE_RUNNER');
    expect(preparation).toContain('prepare_stacks="$RUNNER_STACKS"');
    expect(preparation).toContain('for name in $prepare_stacks');
    expect(preparation).not.toContain('for name in $STACKS');

    const originalMonolithicOrder = [...preDigest, ...runner];
    const hasDigestDependencyCycle = (
      order: string[],
      registryIsPhysical: boolean,
      runnerRequiresPhysicalDigest: boolean,
    ): boolean =>
      order.includes('mxmed-stg-registry') &&
      order.includes('mxmed-stg-c3-runner') &&
      runnerRequiresPhysicalDigest &&
      !registryIsPhysical;
    expect(hasDigestDependencyCycle(originalMonolithicOrder, false, true)).toBe(true);

    const reconciledRuntimeSequence = [
      ...preDigest,
      'execute:mxmed-stg-c3-janitor',
      'execute:mxmed-stg-network',
      'execute:mxmed-stg-security',
      'execute:mxmed-stg-session',
      'execute:mxmed-stg-registry',
      'seal:physical-ecr-digest',
      ...runner,
      'execute:mxmed-stg-c3-runner',
    ];
    expect(reconciledRuntimeSequence.indexOf('execute:mxmed-stg-registry')).toBeLessThan(
      reconciledRuntimeSequence.indexOf('seal:physical-ecr-digest'),
    );
    expect(reconciledRuntimeSequence.indexOf('seal:physical-ecr-digest')).toBeLessThan(
      reconciledRuntimeSequence.indexOf('mxmed-stg-c3-runner'),
    );
  });

  test('ignores response-only NotificationState during canonical comparison', () => {
    const result = runDeployVisibilityWaiter('success');
    expect({ status: result.status, stderr: result.stderr }).toEqual({ status: 0, stderr: '' });
  });

  test('accepts notification order differences after deterministic sorting', () => {
    const result = runDeployVisibilityWaiter('success');
    expect(result.status).toBe(0);
    expect(JSON.parse(readFileSync(statePath, 'utf8'))).toMatchObject({
      direct_budget_visibility_stabilized: true,
    });
  });

  test('compares numerically equivalent notification thresholds canonically', () => {
    const result = runDeployVisibilityWaiter('success');
    expect(result.status).toBe(0);
    expect(JSON.parse(readFileSync(statePath, 'utf8'))).toMatchObject({
      direct_budget_visibility_last_sanitized_result: 'PASS',
    });
  });

  test('retries an incomplete normalized notification set inside the waiter', () => {
    const result = runDeployVisibilityWaiter('incomplete-notifications-once');
    expect(result.status).toBe(0);
    expect(JSON.parse(readFileSync(statePath, 'utf8'))).toMatchObject({
      direct_budget_visibility_attempt_count: 2,
      direct_budget_visibility_stabilized: true,
    });
    expect(readFileSync(join(directory, 'sleep.log'), 'utf8').trim()).toBe('1');
  });

  test('retries NotFound only after the successful CreateBudget state is sealed', () => {
    const result = runDeployVisibilityWaiter('budget-notfound-once');
    expect(result.status).toBe(0);
    expect(JSON.parse(readFileSync(statePath, 'utf8'))).toMatchObject({
      direct_budget_visibility_attempt_count: 2,
      direct_budget_visibility_stabilized: true,
    });
  });

  test('rejects a visibility attempt before successful CreateBudget', () => {
    expectAccepted('init-state', manifestPath, statePath);
    expectRejected(
      'record-budget-visibility-attempt',
      manifestPath,
      statePath,
      '2026-08-25T12:01:00Z',
      'BUDGET',
      'BUDGET_NOT_FOUND',
    );
  });

  test('reaches PASS only with complete notifications and exact subscribers', () => {
    const result = runDeployVisibilityWaiter('success');
    expect(result.status).toBe(0);
    expect(JSON.parse(readFileSync(statePath, 'utf8'))).toMatchObject({
      direct_budget_readback_pass: true,
      direct_budget_visibility_stage: 'STABILIZED',
      direct_budget_visibility_stabilized: true,
      direct_budget_visibility_timeout: false,
    });
  });

  test('keeps a missing subscriber retryable through the final attempt', () => {
    const result = runDeployVisibilityWaiter('missing-subscriber');
    expect(result.status).toBe(1);
    expect(JSON.parse(readFileSync(statePath, 'utf8'))).toMatchObject({
      direct_budget_visibility_attempt_count: 8,
      direct_budget_visibility_stabilized: false,
    });
  });

  test('fails closed and seals timeout after the bounded waiter expires', () => {
    const result = runDeployVisibilityWaiter('missing-subscriber');
    expect(result.status).toBe(1);
    expect(JSON.parse(readFileSync(statePath, 'utf8'))).toMatchObject({
      direct_budget_visibility_stage: 'TIMEOUT',
      direct_budget_visibility_timeout: true,
      direct_budget_visibility_last_sanitized_result: 'TIMEOUT',
    });
    expect(readFileSync(join(directory, 'sleep.log'), 'utf8').trim().split('\n')).toEqual([
      '1',
      '2',
      '4',
      '8',
      '10',
      '10',
      '10',
    ]);
  });

  test('never performs a second CreateBudget during visibility timeout', () => {
    runDeployVisibilityWaiter('missing-subscriber');
    const calls = readFileSync(join(directory, 'aws.log'), 'utf8');
    expect(calls).not.toContain('create-budget');
  });

  test('retains exact account and run-bound Budget identity in every read', () => {
    runDeployVisibilityWaiter('success');
    const calls = readFileSync(join(directory, 'aws.log'), 'utf8').trim().split('\n');
    expect(calls.length).toBeGreaterThan(0);
    for (const call of calls) {
      expect(call).toContain('--account-id 875691018466');
      expect(call).toContain(`--budget-name mxmed-stg-c3-${runId}`);
    }
  });

  test('increments runtime-state visibility attempt count monotonically', () => {
    initializeCreatedBudgetState();
    expectAccepted(
      'record-budget-visibility-attempt',
      manifestPath,
      statePath,
      '2026-08-25T12:01:01Z',
      'BUDGET',
      'BUDGET_NOT_FOUND',
    );
    expectAccepted(
      'record-budget-visibility-attempt',
      manifestPath,
      statePath,
      '2026-08-25T12:01:02Z',
      'NOTIFICATIONS',
      'NOTIFICATIONS_INCOMPLETE',
    );
    expect(JSON.parse(readFileSync(statePath, 'utf8'))).toMatchObject({
      direct_budget_visibility_attempt_count: 2,
    });
  });

  test('enforces monotonic first and last visibility timestamps', () => {
    initializeCreatedBudgetState();
    expectAccepted(
      'record-budget-visibility-attempt',
      manifestPath,
      statePath,
      '2026-08-25T12:01:02Z',
      'BUDGET',
      'BUDGET_NOT_FOUND',
    );
    expectRejected(
      'record-budget-visibility-attempt',
      manifestPath,
      statePath,
      '2026-08-25T12:01:01Z',
      'BUDGET',
      'BUDGET_NOT_FOUND',
    );
    expect(JSON.parse(readFileSync(statePath, 'utf8'))).toMatchObject({
      direct_budget_visibility_first_attempt_at_utc: '2026-08-25T12:01:02Z',
      direct_budget_visibility_last_attempt_at_utc: '2026-08-25T12:01:02Z',
    });
  });

  test('teardown does not accept the first NotFound after acknowledged CreateBudget', () => {
    const { result, stub } = runTeardownBudgetGuard('guard-notfound');
    expect({ status: result.status, stderr: result.stderr }).toEqual({ status: 0, stderr: '' });
    expect(readFileSync(stub.DESCRIBE_COUNT, 'utf8').trim()).toBe('2');
  });

  test('teardown deletes a late-visible exact Budget and restarts absence confirmation', () => {
    const { result, stub } = runTeardownBudgetGuard('guard-late');
    expect(result.status).toBe(0);
    const calls = readFileSync(stub.AWS_LOG, 'utf8');
    expect(calls.match(/budgets delete-budget/g)).toHaveLength(1);
    expect(readFileSync(stub.DESCRIBE_COUNT, 'utf8').trim()).toBe('4');
  });

  test('teardown touches no Budget outside the exact sealed identity', () => {
    const { result, stub } = runTeardownBudgetGuard('guard-late');
    expect(result.status).toBe(0);
    const calls = readFileSync(stub.AWS_LOG, 'utf8').trim().split('\n');
    for (const call of calls) {
      expect(call).toContain('--account-id 875691018466');
      expect(call).toContain(`--budget-name mxmed-stg-c3-${runId}`);
    }
  });

  test('requires two final NotFound observations separated by at least five seconds', () => {
    const { result, stub } = runTeardownBudgetGuard('guard-notfound');
    expect(result.status).toBe(0);
    expect(readFileSync(stub.SLEEP_LOG, 'utf8').trim()).toBe('5');
    expect(Number(readFileSync(stub.CLOCK_FILE, 'utf8').trim())).toBe(1787702405);
    expect(JSON.parse(readFileSync(statePath, 'utf8'))).toMatchObject({
      direct_budget_residual_count: 0,
    });
  });

  test('fails with teardown-incomplete semantics when the 120-second guard expires', () => {
    const { result } = runTeardownBudgetGuard('guard-expire');
    expect(result.status).not.toBe(0);
    expect(result.stderr).toContain('DIRECT_BUDGET_TEARDOWN_VISIBILITY_GUARD_EXPIRED');
    const state = JSON.parse(readFileSync(statePath, 'utf8')) as Record<string, unknown>;
    expect(state.direct_budget_residual_count).toBe('PENDING_RUNTIME_RESOLUTION');
  });

  test('introduces no broad Budget listing in deploy or teardown', () => {
    const source = `${readFileSync(deployController, 'utf8')}\n${readFileSync(
      teardownController,
      'utf8',
    )}`;
    expect(source).not.toMatch(/budgets (describe-budgets|list-budgets)/);
  });

  test('keeps one CreateBudget path and no same-RUN_ID runtime retry path', () => {
    const deploy = readFileSync(deployController, 'utf8');
    expect(deploy.match(/aws budgets create-budget/g)).toHaveLength(1);
    expect(deploy).toContain("BUDGET_VISIBILITY_DELAYS='1 2 4 8 10 10 10'");
    expect(deploy).toContain('BUDGET_VISIBILITY_MAX_SECONDS=60');
    expect(deploy).not.toMatch(/retry.*create-budget|create-budget.*retry/i);
  });

  test('phase selection fails closed before AWS and change-set failures start exact teardown', () => {
    const deploy = readFileSync(deployController, 'utf8');
    const noPhase = spawnSync(
      deployController,
      ['--prepare-change-sets', '--no-execute', '--manifest', '/nonexistent'],
      { cwd: repositoryRoot, encoding: 'utf8', env: { PATH: process.env.PATH ?? '/usr/bin:/bin' } },
    );
    expect(noPhase.status).not.toBe(0);
    expect(noPhase.stderr).toContain('CHANGE_SET_PREPARATION_PHASE_REQUIRED');
    expect(noPhase.stderr).not.toMatch(/Unable to locate credentials|aws: command not found/i);

    expect(deploy).toContain('CHANGE_SET_CREATE_FAILED');
    expect(deploy).toContain('CHANGE_SET_CREATE_WAIT_FAILED');
    expect(deploy).toContain('CHANGE_SET_RESOURCE_COUNT_MISMATCH');
    expect(deploy).toContain('CHANGE_SET_TEMPLATE_SEMANTIC_HASH_MISMATCH');
    expect(deploy).toContain('|| start_partial_teardown "$name"');
    expect(deploy).toContain('c3-ephemeral-teardown.sh" --execute-stack-deletes');
    expect(deploy).toContain('.phase="ABORTING"');
  });
});
