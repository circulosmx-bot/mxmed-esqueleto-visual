import { App, Duration, Stack } from 'aws-cdk-lib';
import { FederatedPrincipal, ManagedPolicy, PolicyStatement } from 'aws-cdk-lib/aws-iam';
import { Template } from 'aws-cdk-lib/assertions';

import { MxMedGitHubOidcDeployment } from '../lib/constructs/github-oidc-deployment';
import { STAGING_CONFIG } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import {
  findByLogicalId,
  policyStatements,
  properties,
  renderSecurity,
  resourcesOfType,
} from './security-test-helpers';

function boundary(prefix: 'WorkloadBoundary' | 'DeploymentBoundary') {
  const rendered = renderSecurity(STAGING_CONFIG);
  return findByLogicalId(rendered.resources, prefix)[1];
}

function role(prefix: string) {
  return findByLogicalId(renderSecurity(STAGING_CONFIG).resources, prefix)[1];
}

function oidcTemplate(environmentName: 'staging' | 'production' = 'staging') {
  const app = new App({ analyticsReporting: false });
  const stack = new Stack(app, 'SyntheticOidcStack');
  const deploymentBoundary = new ManagedPolicy(stack, 'SyntheticDeploymentBoundary', {
    statements: [
      new PolicyStatement({
        actions: ['cloudformation:DescribeStacks'],
        resources: ['*'],
      }),
    ],
  });
  new MxMedGitHubOidcDeployment(stack, 'SyntheticGithubOidc', {
    organization: 'synthetic-org',
    repository: 'synthetic-repository',
    branch: 'security-test-branch',
    githubEnvironment: environmentName,
    deploymentBoundary,
    environmentName,
    maxSessionDuration: Duration.hours(1),
  });
  return Template.fromStack(stack).toJSON() as unknown as {
    Resources?: Record<string, { Type: string; Properties?: Record<string, unknown> }>;
  };
}

function expectOidcValidationFailure(
  override: Partial<{
    organization: string;
    repository: string;
    branch: string;
    githubEnvironment: string;
    environmentName: 'staging' | 'production';
  }>,
): void {
  const app = new App({ analyticsReporting: false });
  const stack = new Stack(app, 'SyntheticInvalidOidcStack');
  const deploymentBoundary = new ManagedPolicy(stack, 'Boundary', {
    statements: [
      new PolicyStatement({ actions: ['cloudformation:DescribeStacks'], resources: ['*'] }),
    ],
  });
  expect(
    () =>
      new MxMedGitHubOidcDeployment(stack, 'InvalidGithubOidc', {
        organization: 'synthetic-org',
        repository: 'synthetic-repository',
        branch: 'security-test-branch',
        githubEnvironment: 'staging',
        deploymentBoundary,
        environmentName: 'staging',
        maxSessionDuration: Duration.hours(1),
        ...override,
      }),
  ).toThrow('MXMED_CONFIG_INVALID');
}

describe('permission boundaries', () => {
  test('SEC-IMP-039 creates the workload boundary', () => {
    expect(properties(boundary('WorkloadBoundary')).ManagedPolicyName).toBe(
      'mxmed-stg-workload-boundary',
    );
  });

  test('SEC-IMP-040 creates the deployment boundary', () => {
    expect(properties(boundary('DeploymentBoundary')).ManagedPolicyName).toBe(
      'mxmed-stg-deployment-boundary',
    );
  });

  test('SEC-IMP-041 attaches no AdministratorAccess or PowerUserAccess', () => {
    expect(JSON.stringify(renderSecurity(STAGING_CONFIG).template)).not.toMatch(
      /AdministratorAccess|PowerUserAccess/,
    );
  });

  test('SEC-IMP-042 creates no IAM users', () => {
    expect(resourcesOfType(renderSecurity(STAGING_CONFIG).resources, 'AWS::IAM::User')).toEqual([]);
  });

  test('SEC-IMP-043 creates no access keys', () => {
    expect(
      resourcesOfType(renderSecurity(STAGING_CONFIG).resources, 'AWS::IAM::AccessKey'),
    ).toEqual([]);
  });

  test('SEC-IMP-044 explicitly denies IAM administration', () => {
    const deny = policyStatements(boundary('WorkloadBoundary')).find(
      (statement) => statement.Sid === 'DenyControlPlaneAndPrivilegeEscalation',
    );
    expect(JSON.stringify(deny)).toContain('iam:*');
  });

  test('SEC-IMP-045 explicitly denies KMS administration for workloads', () => {
    const deny = policyStatements(boundary('WorkloadBoundary')).find(
      (statement) => statement.Sid === 'DenyControlPlaneAndPrivilegeEscalation',
    );
    expect(JSON.stringify(deny)).toMatch(/kms:CreateKey/);
    expect(JSON.stringify(deny)).toMatch(/kms:ScheduleKeyDeletion/);
    expect(JSON.stringify(deny)).toMatch(/kms:PutKeyPolicy/);
  });

  test('SEC-IMP-046 explicitly blocks workload PassRole', () => {
    const deny = policyStatements(boundary('WorkloadBoundary')).find(
      (statement) => statement.Sid === 'DenyControlPlaneAndPrivilegeEscalation',
    );
    expect(JSON.stringify(deny)).toContain('iam:PassRole');
  });

  test('SEC-IMP-047 denies opposite-environment resources', () => {
    const denies = policyStatements(boundary('WorkloadBoundary')).filter(
      (statement) => statement.Effect === 'Deny',
    );
    expect(JSON.stringify(denies)).toContain('production');
    expect(JSON.stringify(denies)).toContain('aws:ResourceTag/Environment');
  });
});

describe('workload and deferred human roles', () => {
  test('SEC-IMP-048 creates the execution role', () => {
    expect(properties(role('EcsExecutionRole')).RoleName).toBe('mxmed-stg-ecs-execution-role');
  });

  test('SEC-IMP-049 creates the application role', () => {
    expect(properties(role('ApplicationTaskRole')).RoleName).toBe('mxmed-stg-application-role');
  });

  test('SEC-IMP-050 creates the migration role', () => {
    expect(properties(role('MigrationTaskRole')).RoleName).toBe('mxmed-stg-migration-role');
  });

  test('SEC-IMP-051 creates the jobs role', () => {
    expect(properties(role('JobsTaskRole')).RoleName).toBe('mxmed-stg-jobs-role');
  });

  test('SEC-IMP-052 trusts only ECS tasks for workload roles', () => {
    for (const prefix of [
      'EcsExecutionRole',
      'ApplicationTaskRole',
      'MigrationTaskRole',
      'JobsTaskRole',
    ]) {
      const trust = properties(role(prefix)).AssumeRolePolicyDocument;
      expect(JSON.stringify(trust)).toContain('ecs-tasks.amazonaws.com');
      expect(JSON.stringify(trust)).not.toContain('"Principal":"*"');
    }
  });

  test('SEC-IMP-053 applies boundaries and human factory MFA', () => {
    for (const prefix of [
      'EcsExecutionRole',
      'ApplicationTaskRole',
      'MigrationTaskRole',
      'JobsTaskRole',
    ]) {
      expect(JSON.stringify(properties(role(prefix)).PermissionsBoundary)).toContain(
        'WorkloadBoundary',
      );
    }

    const app = new App({ analyticsReporting: false });
    const stage = new MxMedEnvironmentStage(app, 'SyntheticHumanRoleStage', {
      config: STAGING_CONFIG,
    });
    stage.securityStack.workloadRoleFactory.createSecurityAuditRole(
      stage.securityStack,
      'SyntheticSecurityAuditRole',
      {
        principal: new FederatedPrincipal('synthetic.identity-center', {}, 'sts:AssumeRole'),
        mfaRequired: true,
        maxSessionDuration: Duration.hours(1),
        boundary: stage.securityStack.deploymentBoundary,
        environmentName: 'staging',
        contractualReason: 'Synthetic audit factory verification only',
      },
    );
    const template = Template.fromStack(stage.securityStack).toJSON();
    expect(JSON.stringify(template)).toContain('aws:MultiFactorAuthPresent');
  });

  test('SEC-IMP-054 lets execution read only the four approved secrets', () => {
    const { resources } = renderSecurity(STAGING_CONFIG);
    const [, policy] = findByLogicalId(resources, 'EcsExecutionRoleDefaultPolicy');
    const statements = policyStatements(policy).filter((statement) =>
      JSON.stringify(statement.Action).includes('secretsmanager:GetSecretValue'),
    );
    expect(statements).toHaveLength(4);
    expect(JSON.stringify(statements)).toMatch(/SessionSigningSecret/);
    expect(JSON.stringify(statements)).toMatch(/StripeSecretKeyContainer/);
    expect(JSON.stringify(statements)).toMatch(/StripeWebhookSecretContainer/);
    expect(JSON.stringify(statements)).toMatch(/AiApiKeyContainer/);
  });

  test('SEC-IMP-055 gives application no premature broad policy', () => {
    const template = JSON.stringify(renderSecurity(STAGING_CONFIG).template);
    expect(template).not.toMatch(/ApplicationTaskRoleDefaultPolicy/);
  });

  test('SEC-IMP-056 gives migration no premature database permission', () => {
    const template = JSON.stringify(renderSecurity(STAGING_CONFIG).template);
    expect(template).not.toMatch(/MigrationTaskRoleDefaultPolicy|rds-db:connect/);
  });

  test('SEC-IMP-057 gives jobs no premature permission', () => {
    const template = JSON.stringify(renderSecurity(STAGING_CONFIG).template);
    expect(template).not.toMatch(/JobsTaskRoleDefaultPolicy/);
  });

  test('SEC-IMP-058 creates no workload AssumeRole identity permission', () => {
    const policies = resourcesOfType(renderSecurity(STAGING_CONFIG).resources, 'AWS::IAM::Policy');
    const identityStatements = policies.flatMap(([, policy]) => policyStatements(policy));
    expect(
      identityStatements.some((statement) =>
        JSON.stringify(statement.Action).includes('sts:AssumeRole'),
      ),
    ).toBe(false);
  });

  test('SEC-IMP-059 creates no inline Allow wildcard and defers human roles', () => {
    const { resources, stage } = renderSecurity(STAGING_CONFIG);
    const roles = resourcesOfType(resources, 'AWS::IAM::Role');
    expect(roles.some(([id]) => /SecurityAuditRole|BreakGlassRole/.test(id))).toBe(false);
    expect(stage.securityStack.workloadRoleFactory).toBeDefined();
    const policies = resourcesOfType(resources, 'AWS::IAM::Policy');
    for (const [, policy] of policies) {
      const unsafe = policyStatements(policy).filter(
        (statement) => statement.Effect === 'Allow' && statement.Action === '*',
      );
      expect(unsafe).toEqual([]);
    }
  });
});

describe('reusable GitHub OIDC deployment construct', () => {
  test('SEC-IMP-060 is not instantiated by default', () => {
    const resources = renderSecurity(STAGING_CONFIG).resources;
    expect(resourcesOfType(resources, 'AWS::IAM::OIDCProvider')).toEqual([]);
    expect(Object.keys(resources).some((id) => id.includes('DeploymentRole'))).toBe(false);
  });

  test('SEC-IMP-061 synthesizes a valid synthetic instance', () => {
    const resources = oidcTemplate().Resources ?? {};
    expect(
      Object.values(resources).some((resource) => resource.Type === 'AWS::IAM::OIDCProvider'),
    ).toBe(true);
    expect(Object.values(resources).some((resource) => resource.Type === 'AWS::IAM::Role')).toBe(
      true,
    );
  });

  test('SEC-IMP-062 fixes the OIDC audience', () => {
    const resources = oidcTemplate().Resources ?? {};
    const provider = Object.values(resources).find(
      (resource) => resource.Type === 'AWS::IAM::OIDCProvider',
    );
    expect(provider?.Properties?.ClientIdList).toEqual(['sts.amazonaws.com']);
  });

  test('SEC-IMP-063 fixes the staging subject exactly', () => {
    const resources = oidcTemplate().Resources ?? {};
    const deploymentRole = Object.values(resources).find(
      (resource) => resource.Type === 'AWS::IAM::Role',
    );
    expect(JSON.stringify(deploymentRole?.Properties?.AssumeRolePolicyDocument)).toContain(
      'repo:synthetic-org/synthetic-repository:ref:refs/heads/security-test-branch',
    );
  });

  test('SEC-IMP-064 rejects a wildcard organization', () => {
    expectOidcValidationFailure({ organization: '*' });
  });

  test('SEC-IMP-065 rejects a wildcard repository', () => {
    expectOidcValidationFailure({ repository: 'synthetic-*' });
  });

  test('SEC-IMP-066 rejects production without the production GitHub Environment', () => {
    expectOidcValidationFailure({ environmentName: 'production', githubEnvironment: 'staging' });
  });

  test('SEC-IMP-067 applies the deployment boundary', () => {
    const resources = oidcTemplate('production').Resources ?? {};
    const deploymentRole = Object.values(resources).find(
      (resource) => resource.Type === 'AWS::IAM::Role',
    );
    expect(JSON.stringify(deploymentRole?.Properties?.PermissionsBoundary)).toContain(
      'SyntheticDeploymentBoundary',
    );
  });

  test('SEC-IMP-068 creates zero access keys', () => {
    const resources = oidcTemplate().Resources ?? {};
    expect(
      Object.values(resources).filter((resource) => resource.Type === 'AWS::IAM::AccessKey'),
    ).toEqual([]);
  });
});
