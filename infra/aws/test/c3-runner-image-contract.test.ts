import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const repositoryRoot = join(__dirname, '../../..');
const read = (path: string): string => readFileSync(join(repositoryRoot, path), 'utf8');

interface PreFromArgInventory {
  name: string;
  usedInFrom: boolean;
  usedAfterFrom: boolean;
  redeclaredInsideStage: boolean;
}

const inventoryPreFromArgs = (dockerfile: string): PreFromArgInventory[] => {
  const lines = dockerfile.split('\n');
  const firstFromIndex = lines.findIndex((line) => /^\s*FROM\s+/i.test(line));
  if (firstFromIndex < 0) {
    throw new Error('Dockerfile must contain a FROM instruction');
  }

  const fromLine = lines[firstFromIndex] ?? '';
  const stageLines = lines.slice(firstFromIndex + 1);
  const stageSource = stageLines.join('\n');

  return lines
    .slice(0, firstFromIndex)
    .map((line) => /^\s*ARG\s+([A-Za-z_][A-Za-z0-9_]*)/.exec(line)?.[1])
    .filter((name): name is string => name !== undefined)
    .map((name) => {
      const reference = new RegExp(`\\$\\{?${name}\\}?`);
      const redeclaration = new RegExp(`^\\s*ARG\\s+${name}(?:\\s*=.*)?\\s*$`, 'i');
      return {
        name,
        usedInFrom: reference.test(fromLine),
        usedAfterFrom: reference.test(stageSource),
        redeclaredInsideStage: stageLines.some((line) => redeclaration.test(line)),
      };
    });
};

const outOfStageScopeArgs = (dockerfile: string): string[] =>
  inventoryPreFromArgs(dockerfile)
    .filter((arg) => arg.usedAfterFrom && !arg.redeclaredInsideStage)
    .map((arg) => arg.name);

describe('C3 immutable runner image', () => {
  const dockerfile = read('infra/aws/c3-runner/Dockerfile');
  const entrypoint = read('infra/aws/c3-runner/entrypoint.sh');

  test('requires a digest-pinned base and checksum-pinned build inputs', () => {
    expect(dockerfile).toContain('ARG PHP_BASE_IMAGE');
    expect(dockerfile).toContain('@sha256:');
    expect(dockerfile).not.toMatch(/FROM\s+[^$\n]+:latest/i);
    expect(dockerfile).toContain('PHPREDIS_VERSION=6.2.0');
    expect(dockerfile).toContain('AWS_CLI_VERSION=2.28.21');
    expect(dockerfile).toContain('sha256sum --check --strict');
    expect(dockerfile).toContain('org.opencontainers.image.revision');
    expect(dockerfile).toContain('SOURCE_REVISION');
  });

  test('keeps every post-FROM global build argument in stage scope', () => {
    expect(inventoryPreFromArgs(dockerfile)).toEqual([
      {
        name: 'PHP_BASE_IMAGE',
        usedInFrom: true,
        usedAfterFrom: true,
        redeclaredInsideStage: true,
      },
    ]);
    expect(outOfStageScopeArgs(dockerfile)).toEqual([]);

    const invalidActualDockerfile = dockerfile.replace(
      /(?<=FROM \$\{PHP_BASE_IMAGE\}\n\n)ARG PHP_BASE_IMAGE\n/,
      '',
    );
    expect(invalidActualDockerfile).not.toBe(dockerfile);
    expect(outOfStageScopeArgs(invalidActualDockerfile)).toEqual(['PHP_BASE_IMAGE']);
  });

  test('copies only the C3 identity test surface and runs as a read-only non-root task', () => {
    expect(dockerfile).toContain('C3ValkeySessionStoreIntegrationTest.php');
    expect(dockerfile).not.toMatch(/COPY\s+\.\s+/);
    expect(dockerfile).toContain('USER 10001:10001');
    expect(entrypoint).toContain('timeout --signal=TERM --kill-after=10s 900');
    expect(entrypoint).not.toContain('set -x');
  });

  test('acquires only the exact staging session secret without placing values in argv or logs', () => {
    expect(entrypoint).toContain('/mxmed/staging/application/session-store-auth-');
    expect(entrypoint).toContain('--secret-id "$SESSION_AUTH_SECRET_ARN"');
    expect(entrypoint).not.toMatch(/\/mxmed\/production|providers\/stripe|providers\/ai|database/i);
    expect(entrypoint).not.toMatch(/(?:echo|printf).*\$(?:password|secret_json).*>&/i);
    expect(entrypoint).toContain('unset secret_json');
    expect(entrypoint).toContain('SESSION_SIGNING_KEY');
    expect(entrypoint).toContain(
      "MXMED_C3_PHYSICAL_VALKEY_TEST_AUTHORIZED='DIRECTOR_AUTHORIZED_ISOLATED_C3'",
    );
  });
});

describe('C3 control scripts fail closed offline', () => {
  const scripts = [
    'scripts/aws/c3-runtime-contract.sh',
    'scripts/aws/c3-ephemeral-deploy.sh',
    'scripts/aws/c3-ephemeral-test.sh',
    'scripts/aws/c3-ephemeral-teardown.sh',
  ] as const;

  test.each(scripts)('%s has valid shell syntax and no broad production target', (script) => {
    const absolute = join(repositoryRoot, script);
    expect(spawnSync('/bin/sh', ['-n', absolute], { encoding: 'utf8' }).status).toBe(0);
    const source = read(script);
    expect(source).not.toMatch(/aws\s+.*--profile\s+[^"']*prod/i);
    expect(source).not.toMatch(/secretsmanager\s+get-secret-value/);
  });

  test('all AWS-capable modes reject the current activity before invoking AWS', () => {
    const cases = [
      ['scripts/aws/c3-ephemeral-deploy.sh', ['--prepare-template-transport']],
      ['scripts/aws/c3-ephemeral-deploy.sh', ['--execute-stack', '--stack', 'mxmed-stg-network']],
      ['scripts/aws/c3-ephemeral-test.sh', ['--run-once']],
      ['scripts/aws/c3-ephemeral-teardown.sh', ['--execute-stack-deletes']],
    ] as const;
    for (const [script, modeArguments] of cases) {
      const result = spawnSync(
        join(repositoryRoot, script),
        [...modeArguments, '--manifest', '/nonexistent'],
        {
          cwd: repositoryRoot,
          encoding: 'utf8',
          env: { PATH: process.env.PATH ?? '/usr/bin:/bin' },
        },
      );
      expect(result.status).not.toBe(0);
      expect(result.stderr).toMatch(/DIRECTOR_AWS_WRITE_AUTHORIZATION_MISSING/);
      expect(result.stderr).not.toMatch(/Unable to locate credentials|aws: command not found/i);
    }
  });

  test('encodes private one-task execution, watchdog and immediate teardown on every terminal path', () => {
    const testSource = read('scripts/aws/c3-ephemeral-test.sh');
    expect(testSource).toContain('--count 1');
    expect(testSource).toContain('assignPublicIp=DISABLED');
    expect(testSource).toContain('WATCHDOG_SECONDS=1200');
    expect(testSource).not.toContain('--overrides');
    expect(testSource).toContain('c3-ephemeral-teardown.sh');
    const teardown = read('scripts/aws/c3-ephemeral-teardown.sh');
    expect(teardown).toContain('TEARDOWN_START_EXCEEDED_300_SECONDS');
    expect(teardown).toContain('RETAINED_PHYSICAL_IDS_NOT_SEALED');
    expect(teardown).not.toMatch(
      /list-stacks|list-secrets|describe-repositories\s+(?!--repository-names)/,
    );
  });

  test('rejects placeholders, production and wrong account/region with twelve phased gates', () => {
    const deploy = read('scripts/aws/c3-ephemeral-deploy.sh');
    const contract = read('scripts/aws/c3-runtime-contract.sh');
    expect(contract).toContain('PENDING_RUNTIME_RESOLUTION');
    expect(contract).toContain('mxmed-prd-');
    expect(deploy).toContain("EXPECTED_ACCOUNT='875691018466'");
    expect(deploy).toContain("EXPECTED_REGION='mx-central-1'");
    expect(contract).toContain('{ordinal:12,name:"ECR_DIGEST_SEALED_BEFORE_RUNNER"}');
    expect(contract).toContain('[ "$pass_1_to_11" = \'11\' ]');
    expect(contract).toContain('[ "$gate_12" = "$C3_PENDING_RUNTIME" ]');
    expect(deploy).not.toContain('BASELINE_PRODUCT_HEAD=');
  });

  test('uses only direct CloudFormation transport and rejects bootstrap or unsealed templates', () => {
    const deploy = read('scripts/aws/c3-ephemeral-deploy.sh');
    expect(deploy).toContain('cloudformation create-change-set');
    expect(deploy).toContain('cloudformation execute-change-set');
    expect(deploy).not.toMatch(/\bcdk\s+(?:deploy|bootstrap)\b/);
    expect(deploy).toContain("TEMPLATE_BODY_MAX_BYTES='51200'");
    expect(deploy).toContain('OVERSIZE_TEMPLATE_BODY_REJECTED');
    expect(deploy).toContain('UNSEALED_TEMPLATE_UPLOAD_REJECTED');
    expect(deploy).toContain('TEMPLATE_HASH_MISMATCH');
    expect(deploy).toContain('TEMPLATE_BOOTSTRAP_REFERENCE_REJECTED');
    expect(deploy).toContain('TEMPLATE_BUCKET_PUBLIC_ACCESS_BLOCK_INVALID');
    expect(deploy).toContain('mxmed-stg-c3-cf-templates-875691018466-mx-central-1');
    expect(deploy).toContain('$run_id/$name/$sha$C3_OBJECT_KEY_SUFFIX');
  });
});
