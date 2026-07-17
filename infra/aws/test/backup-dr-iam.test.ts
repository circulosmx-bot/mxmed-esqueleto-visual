import {
  activeBackupConfig,
  backupTemplate,
  createBackupStage,
  resourceEntries,
  resourceProperties,
  templateJson,
  templateText,
} from './backup-dr-test-helpers';

const template = backupTemplate(activeBackupConfig());
const text = templateText(template);

describe('dedicated least-privilege Backup role', () => {
  test('creates one role trusted by AWS Backup', () => {
    const roles = resourceProperties(template, 'AWS::IAM::Role');
    expect(roles).toHaveLength(1);
    expect(JSON.stringify(roles[0]?.AssumeRolePolicyDocument)).toContain('backup.amazonaws.com');
  });

  test('uses the deterministic dedicated role name', () => {
    expect(resourceProperties(template, 'AWS::IAM::Role')[0]?.RoleName).toBe(
      'mxmed-prd-backup-service-role',
    );
  });

  test.each([
    'AdministratorAccess',
    'AWSBackupFullAccess',
    'secretsmanager:GetSecretValue',
    'rds:DeleteDBInstance',
    's3:DeleteBucket',
    's3:DeleteObject',
    'kms:ScheduleKeyDeletion',
    'iam:CreateRole',
    'iam:AttachRolePolicy',
  ])('does not grant %s', (forbidden) => {
    expect(text).not.toContain(forbidden);
  });

  test('has no managed policy attachment', () => {
    expect(resourceEntries(template, 'AWS::IAM::ManagedPolicy')).toHaveLength(0);
    expect(resourceProperties(template, 'AWS::IAM::Role')[0]?.ManagedPolicyArns).toBeUndefined();
  });

  test('scopes mutating RDS snapshot actions to source and generated snapshots', () => {
    const policies = resourceProperties(template, 'AWS::IAM::Policy');
    const statementText = JSON.stringify(policies);
    expect(statementText).toContain('rds:CreateDBSnapshot');
    expect(statementText).toContain('DatabaseInstance');
    expect(statementText).toContain('mxmed-prd-*');
  });

  test('scopes object read permissions to the two critical buckets', () => {
    const policies = JSON.stringify(resourceProperties(template, 'AWS::IAM::Policy'));
    expect(policies).toContain('ClinicalRecordsBucket');
    expect(policies).toContain('PrivateDocumentsBucket');
    expect(policies).not.toContain('PublicMediaBucket');
    expect(policies).not.toContain('UploadQuarantineBucket');
  });

  test('does not add vault permissions to application or migration roles', () => {
    const stage = createBackupStage(activeBackupConfig());
    const securityText = templateText(templateJson(stage.securityStack));
    expect(securityText).not.toMatch(/backup:(?:DescribeBackupVault|StartBackupJob)/);
  });
});
