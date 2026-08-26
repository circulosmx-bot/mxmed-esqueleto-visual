import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { App, Stack } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import {
  MXMED_C3_CFN_EXECUTION_BOUNDARY_ARNS,
  MXMED_C3_CFN_EXECUTION_ROLE_ARNS,
  MXMED_C3_CFN_EXECUTION_ROLE_NAMES,
  MXMED_C3_CONTROL_BOUNDARY_ARNS,
  MXMED_C3_CONTROL_ROLE_CONTRACTS,
  MXMED_C3_DIRECT_BUDGET_LEGACY_AWS_PORTAL_ACTIONS,
  MXMED_C3_DIRECT_BUDGET_PROGRAMMATIC_ACTIONS,
  MXMED_C3_DIRECT_BUDGET_RESOURCE_PATTERN,
  expectedC3ResourceCount,
} from '../lib/constructs/c3-runner-contract';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedC3EphemeralStage } from '../lib/stages/mxmed-c3-ephemeral-stage';

const repositoryRoot = join(__dirname, '../../..');

type AuthorityKey = keyof typeof MXMED_C3_CFN_EXECUTION_ROLE_NAMES;

interface PolicyStatement {
  readonly Sid?: string;
  readonly Effect: 'Allow' | 'Deny';
  readonly Action: string | readonly string[];
  readonly Resource?: string | readonly string[];
  readonly Principal?: Readonly<Record<string, string | readonly string[]>>;
  readonly Condition?: Readonly<Record<string, Readonly<Record<string, unknown>>>>;
}

interface PolicyDocument {
  readonly Version: string;
  readonly Statement: readonly PolicyStatement[];
}

interface CfnResource {
  readonly Type: string;
  readonly Properties?: Readonly<Record<string, unknown>>;
}

interface SynthesizedTemplate {
  readonly Resources?: Readonly<Record<string, CfnResource>>;
}

const authority = {
  network: {
    stackName: 'mxmed-stg-network',
    policy: 'MXMED_C3_CFN_NETWORK_POLICY.json',
    trust: 'MXMED_C3_CFN_NETWORK_TRUST_POLICY.json',
  },
  security: {
    stackName: 'mxmed-stg-security',
    policy: 'MXMED_C3_CFN_SECURITY_POLICY.json',
    trust: 'MXMED_C3_CFN_SECURITY_TRUST_POLICY.json',
  },
  session: {
    stackName: 'mxmed-stg-session',
    policy: 'MXMED_C3_CFN_SESSION_POLICY.json',
    trust: 'MXMED_C3_CFN_SESSION_TRUST_POLICY.json',
  },
  registry: {
    stackName: 'mxmed-stg-registry',
    policy: 'MXMED_C3_CFN_REGISTRY_POLICY.json',
    trust: 'MXMED_C3_CFN_REGISTRY_TRUST_POLICY.json',
  },
  runner: {
    stackName: 'mxmed-stg-c3-runner',
    policy: 'MXMED_C3_CFN_RUNNER_POLICY.json',
    trust: 'MXMED_C3_CFN_RUNNER_TRUST_POLICY.json',
  },
  janitor: {
    stackName: 'mxmed-stg-c3-janitor',
    policy: 'MXMED_C3_CFN_JANITOR_POLICY.json',
    trust: 'MXMED_C3_CFN_JANITOR_TRUST_POLICY.json',
  },
} as const satisfies Readonly<Record<AuthorityKey, Readonly<Record<string, string>>>>;

const policyPath = (name: string): string => join(repositoryRoot, 'infra/aws/policies/c3', name);
const readDocument = (name: string): PolicyDocument =>
  JSON.parse(readFileSync(policyPath(name), 'utf8')) as PolicyDocument;
const actions = (statement: PolicyStatement): readonly string[] =>
  typeof statement.Action === 'string' ? [statement.Action] : statement.Action;
const resources = (statement: PolicyStatement): readonly string[] => {
  if (statement.Resource === undefined) return [];
  return typeof statement.Resource === 'string' ? [statement.Resource] : statement.Resource;
};
const compactSize = (name: string): number =>
  readFileSync(policyPath(name), 'utf8').replace(/\s/g, '').length;

const wildcardMatches = (pattern: string, value: string): boolean => {
  const expression = pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
  return new RegExp(`^${expression}$`).test(value);
};

const documentAllows = (document: PolicyDocument, action: string, resource: string): boolean => {
  const matching = document.Statement.filter(
    (statement) =>
      actions(statement).some((candidate) => wildcardMatches(candidate, action)) &&
      resources(statement).some((candidate) => wildcardMatches(candidate, resource)),
  );
  if (matching.some((statement) => statement.Effect === 'Deny')) return false;
  return matching.some((statement) => statement.Effect === 'Allow');
};

const fixture = new MxMedC3EphemeralStage(
  new App({ analyticsReporting: false, context: { activity: 'c3-ephemeral' } }),
  'PerStackCfnAuthorityFixture',
  {
    config: getEnvironmentConfig('staging', 'launch-lean-v1', 'registry-only-v1'),
    account: '875691018466',
  },
);

const stacks = Object.fromEntries(
  fixture.node.children
    .filter((child): child is Stack => Stack.isStack(child))
    .map((stack) => [stack.stackName, Template.fromStack(stack).toJSON() as SynthesizedTemplate]),
) as Readonly<Record<string, SynthesizedTemplate>>;

const serviceForResourceType: Readonly<Record<string, string>> = {
  Budgets: 'budgets',
  CloudTrail: 'cloudtrail',
  EC2: 'ec2',
  ECR: 'ecr',
  ECS: 'ecs',
  ElastiCache: 'elasticache',
  IAM: 'iam',
  KMS: 'kms',
  Logs: 'logs',
  S3: 's3',
  Scheduler: 'scheduler',
  SecretsManager: 'secretsmanager',
  StepFunctions: 'states',
};

describe('C3 per-stack CloudFormation execution authority', () => {
  test('defines exactly six deterministic role and boundary identities', () => {
    expect(Object.keys(MXMED_C3_CFN_EXECUTION_ROLE_NAMES)).toEqual(Object.keys(authority));
    expect(new Set(Object.values(MXMED_C3_CFN_EXECUTION_ROLE_NAMES)).size).toBe(6);
    expect(new Set(Object.values(MXMED_C3_CFN_EXECUTION_ROLE_ARNS)).size).toBe(6);
    expect(new Set(Object.values(MXMED_C3_CFN_EXECUTION_BOUNDARY_ARNS)).size).toBe(6);

    for (const key of Object.keys(authority) as AuthorityKey[]) {
      expect(MXMED_C3_CFN_EXECUTION_ROLE_ARNS[key]).toBe(
        `arn:aws:iam::875691018466:role/${MXMED_C3_CFN_EXECUTION_ROLE_NAMES[key]}`,
      );
      expect(MXMED_C3_CFN_EXECUTION_BOUNDARY_ARNS[key]).toBe(
        `arn:aws:iam::875691018466:policy/${MXMED_C3_CFN_EXECUTION_ROLE_NAMES[key]}-Boundary`,
      );
    }
  });

  test('keeps every execution identity policy/boundary below the AWS 6144-character limit', () => {
    for (const item of Object.values(authority)) {
      const document = readDocument(item.policy);
      expect(document.Version).toBe('2012-10-17');
      expect(document.Statement.length).toBeGreaterThan(0);
      expect(compactSize(item.policy)).toBeLessThanOrEqual(6_144);
    }
  });

  test('trusts only CloudFormation from the exact account and stack', () => {
    for (const item of Object.values(authority)) {
      const trust = readDocument(item.trust);
      expect(trust.Statement).toHaveLength(1);
      const statement = trust.Statement[0];
      expect(statement?.Principal).toEqual({ Service: 'cloudformation.amazonaws.com' });
      expect(JSON.stringify(statement?.Principal)).not.toMatch(/\*|:root|AWSReservedSSO/);
      expect(statement?.Condition).toEqual({
        StringEquals: { 'aws:SourceAccount': '875691018466' },
        ArnLike: {
          'aws:SourceArn': `arn:aws:cloudformation:mx-central-1:875691018466:stack/${item.stackName}/*`,
        },
      });
    }
  });

  test('grants only regional log-group listing for Janitor CloudFormation resolution', () => {
    const janitor = readDocument(authority.janitor.policy);
    const describeLogGroups = janitor.Statement.filter((statement) =>
      actions(statement).includes('logs:DescribeLogGroups'),
    );

    expect(describeLogGroups).toEqual([
      {
        Sid: 'ListRegionalLogGroupsForJanitorResolution',
        Effect: 'Allow',
        Action: 'logs:DescribeLogGroups',
        Resource: '*',
        Condition: { StringEquals: { 'aws:RequestedRegion': 'mx-central-1' } },
      },
    ]);

    // The same document is the source for the Janitor identity policy and boundary.
    const effectiveAllows = (action: string, resource: string): boolean =>
      documentAllows(janitor, action, resource) && documentAllows(janitor, action, resource);
    expect(effectiveAllows('logs:DescribeLogGroups', '*')).toBe(true);
    expect(effectiveAllows('logs:GetLogEvents', '*')).toBe(false);
    expect(effectiveAllows('logs:FilterLogEvents', '*')).toBe(false);
    expect(effectiveAllows('logs:StartQuery', '*')).toBe(false);
    expect(effectiveAllows('logs:GetLogRecord', '*')).toBe(false);
    expect(effectiveAllows('logs:DescribeLogStreams', '*')).toBe(false);
    expect(janitor.Statement.flatMap(actions)).not.toContain('logs:*');

    const network = readDocument(authority.network.policy);
    expect(network.Statement.flatMap(actions)).toContain('logs:DescribeLogGroups');
    expect(network.Statement.flatMap(actions)).not.toContain('logs:*');

    for (const key of ['security', 'session', 'registry', 'runner'] as const) {
      const item = authority[key];
      expect(readDocument(item.policy).Statement.flatMap(actions)).not.toContain(
        'logs:DescribeLogGroups',
      );
    }
  });

  test('maps every synthesized resource to one policy with its service action family', () => {
    let covered = 0;
    for (const item of Object.values(authority)) {
      const template = stacks[item.stackName];
      expect(template).toBeDefined();
      const policyActions = readDocument(item.policy).Statement.flatMap(actions);
      for (const resource of Object.values(template?.Resources ?? {})) {
        const namespace = resource.Type.split('::')[1];
        const iamService = namespace === undefined ? undefined : serviceForResourceType[namespace];
        if (iamService === undefined) throw new Error(`UNMAPPED_RESOURCE_TYPE:${resource.Type}`);
        expect(policyActions.some((action) => action.startsWith(`${iamService}:`))).toBe(true);
        covered += 1;
      }
    }
    expect(covered).toBe(106);
    expect(covered).toBe(expectedC3ResourceCount());
  });

  test('bounds every synthesized IAM role and forbids root or wildcard trust', () => {
    const roles = Object.values(stacks).flatMap((template) =>
      Object.values(template.Resources ?? {}).filter(
        (resource) => resource.Type === 'AWS::IAM::Role',
      ),
    );
    expect(roles).toHaveLength(10);
    for (const role of roles) {
      expect(role.Properties?.PermissionsBoundary).toBeDefined();
      const trust = JSON.stringify(role.Properties?.AssumeRolePolicyDocument);
      expect(trust).not.toMatch(/"AWS":"\*"|:root/);
      expect(trust).toMatch(/\.amazonaws\.com/);
    }
  });

  test('materializes explicit bounded Flow Logs and CloudTrail delivery roles', () => {
    const networkRoles = Object.values(stacks['mxmed-stg-network']?.Resources ?? {}).filter(
      (resource) => resource.Type === 'AWS::IAM::Role',
    );
    const securityRoles = Object.values(stacks['mxmed-stg-security']?.Resources ?? {}).filter(
      (resource) => resource.Type === 'AWS::IAM::Role',
    );
    const flowLogsRole = networkRoles.find(
      (role) => role.Properties?.RoleName === 'mxmed-stg-vpc-flow-logs-role',
    );
    const cloudTrailRole = securityRoles.find(
      (role) => role.Properties?.RoleName === 'mxmed-stg-cloudtrail-logs-role',
    );

    expect(flowLogsRole?.Properties?.PermissionsBoundary).toBe(
      MXMED_C3_CFN_EXECUTION_BOUNDARY_ARNS.network,
    );
    expect(JSON.stringify(flowLogsRole)).toContain('vpc-flow-logs.amazonaws.com');
    expect(JSON.stringify(stacks['mxmed-stg-network'])).toContain('mxmed-stg-network-flow-logs');

    expect(cloudTrailRole?.Properties?.PermissionsBoundary).toBeDefined();
    expect(JSON.stringify(cloudTrailRole)).toContain('cloudtrail.amazonaws.com');
    expect(JSON.stringify(stacks['mxmed-stg-security'])).toContain(
      '/mxmed/staging/security/cloudtrail',
    );
  });

  test('binds every CreateChangeSet to the exact stack RoleArn and PassRole service', () => {
    const deploy = readDocument('MXMED_C3_STAGING_DEPLOY_ROLE_POLICY.json');
    const create = deploy.Statement.filter((statement) =>
      actions(statement).includes('cloudformation:CreateChangeSet'),
    );
    expect(create).toHaveLength(6);

    for (const [key, item] of Object.entries(authority) as [
      AuthorityKey,
      (typeof authority)[AuthorityKey],
    ][]) {
      expect(create).toContainEqual(
        expect.objectContaining({
          Resource: `arn:aws:cloudformation:mx-central-1:875691018466:stack/${item.stackName}/*`,
          Condition: {
            StringEquals: { 'cloudformation:RoleArn': MXMED_C3_CFN_EXECUTION_ROLE_ARNS[key] },
          },
        }),
      );
    }

    const passRole = deploy.Statement.filter((statement) =>
      actions(statement).includes('iam:PassRole'),
    );
    expect(passRole).toHaveLength(1);
    const exactPassRole = passRole[0];
    if (exactPassRole === undefined) throw new Error('DEPLOY_PASSROLE_STATEMENT_MISSING');
    expect(resources(exactPassRole)).toEqual(Object.values(MXMED_C3_CFN_EXECUTION_ROLE_ARNS));
    expect(exactPassRole.Condition).toEqual({
      StringEquals: { 'iam:PassedToService': 'cloudformation.amazonaws.com' },
    });
    expect(resources(exactPassRole)).not.toContain('*');
  });

  test('uses separate control boundaries and only official S3 encryption action names', () => {
    expect(MXMED_C3_CONTROL_ROLE_CONTRACTS.deploy.permissionBoundaryArn).toBe(
      MXMED_C3_CONTROL_BOUNDARY_ARNS.deploy,
    );
    expect(MXMED_C3_CONTROL_ROLE_CONTRACTS.testController.permissionBoundaryArn).toBe(
      MXMED_C3_CONTROL_BOUNDARY_ARNS.testController,
    );
    expect(MXMED_C3_CONTROL_ROLE_CONTRACTS.teardown.permissionBoundaryArn).toBe(
      MXMED_C3_CONTROL_BOUNDARY_ARNS.teardown,
    );
    expect(new Set(Object.values(MXMED_C3_CONTROL_BOUNDARY_ARNS)).size).toBe(4);

    const activeNames = [
      ...Object.values(authority).map((item) => item.policy),
      'MXMED_C3_STAGING_PERMISSION_BOUNDARY.json',
      'MXMED_C3_STAGING_DEPLOY_ROLE_POLICY.json',
      'MXMED_C3_STAGING_TEST_CONTROLLER_ROLE_POLICY.json',
      'MXMED_C3_STAGING_TEARDOWN_ROLE_POLICY.json',
      'MXMED_C3_TEMPLATE_BUCKET_POLICY.json',
    ];
    for (const name of activeNames) expect(compactSize(name)).toBeLessThanOrEqual(6_144);
    const activeActions = activeNames.flatMap((name) =>
      readDocument(name).Statement.flatMap(actions),
    );
    expect(activeActions).not.toContain('s3:GetBucketEncryption');
    expect(activeActions).not.toContain('s3:PutBucketEncryption');
    expect(activeActions).toContain('s3:GetEncryptionConfiguration');
    expect(activeActions).toContain('s3:PutEncryptionConfiguration');
  });

  test('grants only the exact Fine Grained programmatic C3 Budget authority', () => {
    const deploy = readDocument('MXMED_C3_STAGING_DEPLOY_ROLE_POLICY.json');
    const directBudget = deploy.Statement.filter(
      (statement) => statement.Sid === 'ManageOnlyC3DirectBudget',
    );
    expect(directBudget).toEqual([
      {
        Sid: 'ManageOnlyC3DirectBudget',
        Effect: 'Allow',
        Action: [...MXMED_C3_DIRECT_BUDGET_PROGRAMMATIC_ACTIONS],
        Resource: MXMED_C3_DIRECT_BUDGET_RESOURCE_PATTERN,
      },
    ]);

    // The same sealed document generates both the Deploy inline policy and its boundary.
    const effectiveAllows = (action: string, resource: string): boolean =>
      documentAllows(deploy, action, resource) && documentAllows(deploy, action, resource);
    const exactBudget = 'arn:aws:budgets::875691018466:budget/mxmed-stg-c3-example';
    for (const action of MXMED_C3_DIRECT_BUDGET_PROGRAMMATIC_ACTIONS) {
      expect(effectiveAllows(action, exactBudget)).toBe(true);
      expect(effectiveAllows(action, 'arn:aws:budgets::875691018466:budget/other-budget')).toBe(
        false,
      );
      expect(
        effectiveAllows(action, 'arn:aws:budgets::000000000000:budget/mxmed-stg-c3-example'),
      ).toBe(false);
      expect(
        effectiveAllows(action, 'arn:aws:budgets::875691018466:budget/mxmed-prd-c3-example'),
      ).toBe(false);
    }
    expect(effectiveAllows('budgets:TagResource', exactBudget)).toBe(false);

    expect(MXMED_C3_DIRECT_BUDGET_LEGACY_AWS_PORTAL_ACTIONS).toEqual([]);
    expect(JSON.stringify(directBudget)).not.toMatch(
      /aws-portal:ModifyBilling|aws-portal:ViewBilling|budgets:\*|budgets:(?:Tag|Untag)Resource/,
    );
    expect(MXMED_C3_CONTROL_ROLE_CONTRACTS.deploy.actions).toEqual(
      expect.arrayContaining([...MXMED_C3_DIRECT_BUDGET_PROGRAMMATIC_ACTIONS]),
    );
    expect(MXMED_C3_CONTROL_ROLE_CONTRACTS.deploy.exactResourcePatterns).toContain(
      MXMED_C3_DIRECT_BUDGET_RESOURCE_PATTERN,
    );
  });

  test('future deploy flow carries the exact six-role map and always supplies --role-arn', () => {
    const script = readFileSync(join(repositoryRoot, 'scripts/aws/c3-ephemeral-deploy.sh'), 'utf8');
    for (const [key, item] of Object.entries(authority) as [
      AuthorityKey,
      (typeof authority)[AuthorityKey],
    ][]) {
      expect(script).toContain(`"${item.stackName}":"${MXMED_C3_CFN_EXECUTION_ROLE_ARNS[key]}"`);
      expect(script).toContain(
        `${item.stackName}) suffix='${MXMED_C3_CFN_EXECUTION_ROLE_NAMES[key].replace('MXMed-C3-CFN-', '')}'`,
      );
    }
    expect(script).toContain('--role-arn "$role_arn"');
    expect(script).toContain('CFN_EXECUTION_ROLE_STACK_UNMAPPED');
    expect(script).not.toMatch(/cdk (bootstrap|deploy)/);
  });
});
