import { spawnSync } from 'node:child_process';
import { chmodSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const repositoryRoot = join(__dirname, '../../..');
const helper = join(repositoryRoot, 'scripts/aws/c3-runtime-contract.sh');
const hash = 'a'.repeat(64);
const otherHash = 'b'.repeat(64);
const sourceHead = '1'.repeat(40);
const runUuid = '123e4567-e89b-42d3-a456-426614174000';
const runId = `c3-${runUuid}`;

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
  return {
    schema: 'mxmed.c3.ephemeral.sealed-run-manifest.v2',
    run_uuid: runUuid,
    run_id: runId,
    source_head: sourceHead,
    account: '875691018466',
    region: 'mx-central-1',
    deployment_mode: 'DIRECT_CLOUDFORMATION_FROM_SEALED_TEMPLATES',
    director_authorization_reference: 'DIRECTOR-SEAL-C3',
    activity_cost_cap_usd: 5,
    budget_notification_topic_arn:
      'arn:aws:sns:mx-central-1:875691018466:mxmed-stg-c3-notifications',
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
      total: 107,
      data: 0,
      storage: 0,
      application_service: 0,
      public_runner_ip: 0,
    },
    expected_resource_graph: {
      'mxmed-stg-c3-janitor': 9,
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
    const deploy = readFileSync(join(repositoryRoot, 'scripts/aws/c3-ephemeral-deploy.sh'), 'utf8');
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
});
