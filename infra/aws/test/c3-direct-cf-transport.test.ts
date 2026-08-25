import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { App, Stack } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import {
  MXMED_C3_AUDIT_BUCKET_NAME,
  MXMED_C3_CONTROL_ROLE_CONTRACTS,
  MXMED_C3_DEPLOYMENT_MODE,
  MXMED_C3_TEMPLATE_BODY_MAX_BYTES,
  MXMED_C3_TEMPLATE_BUCKET_NAME,
  c3TemplateTransportForBytes,
  validateC3TemplateTransportCandidate,
} from '../lib/constructs/c3-runner-contract';
import type { MxMedC3TemplateTransportCandidate } from '../lib/constructs/c3-runner-contract';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedC3EphemeralStage } from '../lib/stages/mxmed-c3-ephemeral-stage';
import { MxMedSecurityStack } from '../lib/stacks/mxmed-security-stack';

const repositoryRoot = join(__dirname, '../../..');
const readJson = (path: string): unknown =>
  JSON.parse(readFileSync(join(repositoryRoot, path), 'utf8')) as unknown;

interface SynthesizedTemplate {
  readonly Parameters?: Readonly<Record<string, unknown>>;
  readonly Rules?: Readonly<Record<string, unknown>>;
  readonly [key: string]: unknown;
}

interface SnsTopicPolicyStatement {
  readonly Sid: string;
  readonly Effect: 'Allow' | 'Deny';
  readonly Principal: '*' | Readonly<{ Service: string }>;
  readonly Action: string;
  readonly Resource: string;
  readonly Condition: Readonly<Record<string, Readonly<Record<string, string>>>>;
}

interface SnsTopicPolicy {
  readonly Version: string;
  readonly Statement: readonly SnsTopicPolicyStatement[];
}

const SNS_TOPIC_POLICY_ACTION_ALLOWLIST = new Set([
  'sns:AddPermission',
  'sns:DeleteTopic',
  'sns:GetDataProtectionPolicy',
  'sns:GetTopicAttributes',
  'sns:ListSubscriptionsByTopic',
  'sns:ListTagsForResource',
  'sns:Publish',
  'sns:PutDataProtectionPolicy',
  'sns:RemovePermission',
  'sns:SetTopicAttributes',
  'sns:Subscribe',
]);

interface SnsPublishRequest {
  readonly principalService: string;
  readonly sourceAccount: string;
  readonly sourceArn: string;
  readonly secureTransport: boolean;
}

function evaluateC3SnsPublish(
  policy: SnsTopicPolicy,
  request: SnsPublishRequest,
): 'allowed' | 'explicitDeny' | 'implicitDeny' {
  const matching = policy.Statement.filter((statement) => {
    if (statement.Action !== 'sns:Publish') return false;
    const principalMatches =
      statement.Principal === '*' || statement.Principal.Service === request.principalService;
    if (!principalMatches) return false;
    const secureTransport = statement.Condition.Bool?.['aws:SecureTransport'];
    if (secureTransport !== undefined && secureTransport !== String(request.secureTransport)) {
      return false;
    }
    const sourceAccount = statement.Condition.StringEquals?.['aws:SourceAccount'];
    if (sourceAccount !== undefined && sourceAccount !== request.sourceAccount) return false;
    const sourceArn = statement.Condition.ArnLike?.['aws:SourceArn'];
    if (sourceArn !== undefined && !request.sourceArn.startsWith(sourceArn.replace(/\*$/, ''))) {
      return false;
    }
    return true;
  });
  if (matching.some((statement) => statement.Effect === 'Deny')) return 'explicitDeny';
  return matching.some((statement) => statement.Effect === 'Allow') ? 'allowed' : 'implicitDeny';
}

function allStrings(value: unknown): readonly string[] {
  if (typeof value === 'string') return [value];
  if (Array.isArray(value)) return value.flatMap(allStrings);
  if (value !== null && typeof value === 'object') {
    return Object.values(value).flatMap(allStrings);
  }
  return [];
}

describe('C3 direct CloudFormation transport contract', () => {
  const app = new App({ analyticsReporting: false });
  const stage = new MxMedC3EphemeralStage(app, 'DirectCfFixture', {
    config: getEnvironmentConfig('staging', 'launch-lean-v1', 'registry-only-v1'),
    account: '875691018466',
  });
  const templates = stage.node.children
    .filter((child): child is Stack => Stack.isStack(child))
    .map((stack) => Template.fromStack(stack).toJSON() as SynthesizedTemplate);
  const validCandidate: MxMedC3TemplateTransportCandidate = {
    account: '875691018466',
    region: 'mx-central-1',
    bucketName: 'mxmed-stg-c3-cf-templates-875691018466-mx-central-1',
    publicAccessBlocked: true,
    sealed: true,
    expectedSha256: 'a'.repeat(64),
    actualSha256: 'a'.repeat(64),
    templateBytes: 51_200,
    transport: 'TEMPLATE_BODY',
    templateText: '{"Resources":{}}',
  };

  test('seals the exact direct transport and deterministic staging names', () => {
    expect(MXMED_C3_DEPLOYMENT_MODE).toBe('DIRECT_CLOUDFORMATION_FROM_SEALED_TEMPLATES');
    expect(MXMED_C3_TEMPLATE_BODY_MAX_BYTES).toBe(51_200);
    expect(MXMED_C3_TEMPLATE_BUCKET_NAME).toBe(
      'mxmed-stg-c3-cf-templates-875691018466-mx-central-1',
    );
    expect(MXMED_C3_AUDIT_BUCKET_NAME).toBe('mxmed-stg-audit-875691018466-mx-central-1');
  });

  test('routes only templates above 51200 bytes through the temporary S3 bucket', () => {
    expect(c3TemplateTransportForBytes(51_200)).toBe('TEMPLATE_BODY');
    expect(c3TemplateTransportForBytes(51_201)).toBe('C3_TEMPLATE_S3_URL');
    expect(() => c3TemplateTransportForBytes(0)).toThrow('MXMED_C3_TEMPLATE_BYTES_INVALID');
  });

  test.each([
    [{ sealed: false }, 'MXMED_C3_TEMPLATE_UNSEALED'],
    [{ actualSha256: 'b'.repeat(64) }, 'MXMED_C3_TEMPLATE_HASH_MISMATCH'],
    [{ bucketName: 'wrong-bucket' }, 'MXMED_C3_TEMPLATE_BUCKET_MISMATCH'],
    [{ publicAccessBlocked: false }, 'MXMED_C3_TEMPLATE_BUCKET_PUBLIC'],
    [{ account: '000000000000' }, 'MXMED_C3_ACCOUNT_MISMATCH'],
    [{ region: 'us-east-1' }, 'MXMED_C3_REGION_MISMATCH'],
    [{ templateText: '{"BootstrapVersion":{}}' }, 'MXMED_C3_TEMPLATE_FORBIDDEN_AUTHORITY'],
    [{ templateText: '{"CheckBootstrapVersion":{}}' }, 'MXMED_C3_TEMPLATE_FORBIDDEN_AUTHORITY'],
    [{ templateText: 'hnb659fds' }, 'MXMED_C3_TEMPLATE_FORBIDDEN_AUTHORITY'],
    [{ templateText: 'mxmed-prd-resource' }, 'MXMED_C3_TEMPLATE_FORBIDDEN_AUTHORITY'],
    [{ templateBytes: 51_201, transport: 'TEMPLATE_BODY' }, 'MXMED_C3_TEMPLATE_TRANSPORT_MISMATCH'],
  ] as const)('rejects unsafe transport mutation %#', (change, expected) => {
    expect(() => {
      validateC3TemplateTransportCandidate({ ...validCandidate, ...change });
    }).toThrow(expected);
  });

  test('accepts the exact sealed nonproduction transport authority', () => {
    expect(() => {
      validateC3TemplateTransportCandidate(validCandidate);
    }).not.toThrow();
  });

  test('rejects bootstrap parameters, rules and hnb659fds references in every template', () => {
    expect(templates).toHaveLength(6);
    for (const template of templates) {
      expect(template.Parameters?.BootstrapVersion).toBeUndefined();
      expect(template.Rules?.CheckBootstrapVersion).toBeUndefined();
      expect(JSON.stringify(template)).not.toMatch(/hnb659fds|\/cdk-bootstrap\/|CDKToolkit/);
    }
  });

  test('contains no CDK Docker asset or inline application image build authority', () => {
    const serialized = JSON.stringify(templates);
    expect(serialized).toContain('mxmed-stg-application');
    expect(serialized).toContain('RunnerImageDigest');
    expect(serialized).not.toMatch(/DockerImageAsset|ImagePublishingRole|cdk-hnb659fds/);
  });

  test('does not change normal production audit-bucket naming behavior', () => {
    const normal = new MxMedSecurityStack(
      new App({ analyticsReporting: false }),
      'NormalSecurity',
      {
        config: getEnvironmentConfig('production', 'launch-lean-v1', 'disabled-v1'),
      },
    );
    const template = Template.fromStack(normal).toJSON() as {
      readonly Resources: Readonly<
        Record<string, { readonly Type: string; readonly Properties?: Record<string, unknown> }>
      >;
    };
    const buckets = Object.values(template.Resources).filter(
      (resource) => resource.Type === 'AWS::S3::Bucket',
    );
    expect(buckets).toHaveLength(1);
    expect(buckets[0]?.Properties).not.toHaveProperty('BucketName');
  });

  test('materialized policies contain only valid Budgets IAM action names', () => {
    const paths = [
      'infra/aws/policies/c3/MXMED_C3_STAGING_PERMISSION_BOUNDARY.json',
      'infra/aws/policies/c3/MXMED_C3_STAGING_DEPLOY_ROLE_POLICY.json',
      'infra/aws/policies/c3/MXMED_C3_STAGING_TEST_CONTROLLER_ROLE_POLICY.json',
      'infra/aws/policies/c3/MXMED_C3_STAGING_TEARDOWN_ROLE_POLICY.json',
    ];
    const values = paths.flatMap((path) => allStrings(readJson(path)));
    expect(values).toContain('budgets:ViewBudget');
    expect(values).toContain('budgets:ModifyBudget');
    expect(values).not.toEqual(
      expect.arrayContaining([
        'budgets:DescribeBudgets',
        'budgets:CreateBudget',
        'budgets:DeleteBudget',
      ]),
    );
    expect(values.join('\n')).not.toMatch(/AdministratorAccess|PowerUserAccess/);
  });

  test('materialized role policies cover every exact source-contract action', () => {
    const documents = {
      deploy: allStrings(
        readJson('infra/aws/policies/c3/MXMED_C3_STAGING_DEPLOY_ROLE_POLICY.json'),
      ),
      testController: allStrings(
        readJson('infra/aws/policies/c3/MXMED_C3_STAGING_TEST_CONTROLLER_ROLE_POLICY.json'),
      ),
      teardown: allStrings(
        readJson('infra/aws/policies/c3/MXMED_C3_STAGING_TEARDOWN_ROLE_POLICY.json'),
      ),
    };
    for (const role of Object.keys(documents) as readonly (keyof typeof documents)[]) {
      expect(documents[role]).toEqual(
        expect.arrayContaining([...MXMED_C3_CONTROL_ROLE_CONTRACTS[role].actions]),
      );
    }
  });

  test('template bucket and SNS policies deny public transport and bind exact C3 resources', () => {
    const bucket = JSON.stringify(
      readJson('infra/aws/policies/c3/MXMED_C3_TEMPLATE_BUCKET_POLICY.json'),
    );
    const sns = JSON.stringify(readJson('infra/aws/policies/c3/MXMED_C3_SNS_TOPIC_POLICY.json'));
    expect(bucket).toContain(MXMED_C3_TEMPLATE_BUCKET_NAME);
    expect(bucket).toContain('aws:SecureTransport');
    expect(bucket).not.toMatch(/Principal":\{?"AWS":"\*"/);
    expect(sns).toContain('mxmed-stg-c3-notifications');
    expect(sns).toContain('budgets.amazonaws.com');
    expect(sns).toContain('aws:SourceAccount');
  });

  test('uses only the AWS-documented Publish action in the exact C3 SNS topic policy', () => {
    const policy = readJson(
      'infra/aws/policies/c3/MXMED_C3_SNS_TOPIC_POLICY.json',
    ) as SnsTopicPolicy;
    expect(policy.Statement).toHaveLength(2);
    expect(
      policy.Statement.every((statement) =>
        SNS_TOPIC_POLICY_ACTION_ALLOWLIST.has(statement.Action),
      ),
    ).toBe(true);
    expect(policy.Statement.map((statement) => statement.Action)).toEqual([
      'sns:Publish',
      'sns:Publish',
    ]);
    expect(policy.Statement.some((statement) => statement.Action.includes('*'))).toBe(false);
    expect(policy.Statement).toEqual([
      {
        Sid: 'DenyInsecureTransport',
        Effect: 'Deny',
        Principal: '*',
        Action: 'sns:Publish',
        Resource: 'arn:aws:sns:mx-central-1:875691018466:mxmed-stg-c3-notifications',
        Condition: { Bool: { 'aws:SecureTransport': 'false' } },
      },
      {
        Sid: 'AllowOnlyAccountBudgetsPublisher',
        Effect: 'Allow',
        Principal: { Service: 'budgets.amazonaws.com' },
        Action: 'sns:Publish',
        Resource: 'arn:aws:sns:mx-central-1:875691018466:mxmed-stg-c3-notifications',
        Condition: {
          StringEquals: { 'aws:SourceAccount': '875691018466' },
          ArnLike: { 'aws:SourceArn': 'arn:aws:budgets::875691018466:*' },
        },
      },
    ]);
  });

  test('allows only secure same-account Budgets publication', () => {
    const policy = readJson(
      'infra/aws/policies/c3/MXMED_C3_SNS_TOPIC_POLICY.json',
    ) as SnsTopicPolicy;
    const valid: SnsPublishRequest = {
      principalService: 'budgets.amazonaws.com',
      sourceAccount: '875691018466',
      sourceArn: 'arn:aws:budgets::875691018466:budget/mxmed-stg-c3-runtime',
      secureTransport: true,
    };
    expect(evaluateC3SnsPublish(policy, valid)).toBe('allowed');
    expect(evaluateC3SnsPublish(policy, { ...valid, secureTransport: false })).toBe('explicitDeny');
    expect(
      evaluateC3SnsPublish(policy, { ...valid, principalService: 'events.amazonaws.com' }),
    ).toBe('implicitDeny');
    expect(evaluateC3SnsPublish(policy, { ...valid, sourceAccount: '111122223333' })).toBe(
      'implicitDeny',
    );
    expect(
      evaluateC3SnsPublish(policy, {
        ...valid,
        sourceArn: 'arn:aws:budgets::111122223333:budget/unrelated',
      }),
    ).toBe('implicitDeny');
    expect(
      evaluateC3SnsPublish(policy, { ...valid, principalService: 'public.example.invalid' }),
    ).toBe('implicitDeny');
  });
});
