import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import {
  findByLogicalId,
  first,
  policyStatements,
  properties,
  renderSecurity,
  resourcesOfType,
} from './security-test-helpers';

function bucket(config: MxMedEnvironmentConfig) {
  return first(resourcesOfType(renderSecurity(config).resources, 'AWS::S3::Bucket'), 'bucket')[1];
}

function logGroup(config: MxMedEnvironmentConfig) {
  return first(
    resourcesOfType(renderSecurity(config).resources, 'AWS::Logs::LogGroup'),
    'log-group',
  )[1];
}

function trail(config: MxMedEnvironmentConfig) {
  return first(
    resourcesOfType(renderSecurity(config).resources, 'AWS::CloudTrail::Trail'),
    'trail',
  )[1];
}

describe('audit bucket and management CloudTrail', () => {
  test('SEC-IMP-069 blocks all public access on the audit bucket', () => {
    const block = properties(bucket(PRODUCTION_CONFIG)).PublicAccessBlockConfiguration;
    expect(block).toEqual({
      BlockPublicAcls: true,
      BlockPublicPolicy: true,
      IgnorePublicAcls: true,
      RestrictPublicBuckets: true,
    });
  });

  test('SEC-IMP-070 enables versioning and owner enforcement', () => {
    const bucketProps = properties(bucket(PRODUCTION_CONFIG));
    expect(bucketProps.VersioningConfiguration).toEqual({ Status: 'Enabled' });
    expect(bucketProps.OwnershipControls).toEqual({
      Rules: [{ ObjectOwnership: 'BucketOwnerEnforced' }],
    });
  });

  test('SEC-IMP-071 encrypts bucket and audit services with AuditKey', () => {
    const rendered = renderSecurity(PRODUCTION_CONFIG);
    expect(JSON.stringify(properties(bucket(PRODUCTION_CONFIG)).BucketEncryption)).toContain(
      'AuditKey',
    );
    expect(JSON.stringify(properties(bucket(PRODUCTION_CONFIG)).BucketEncryption)).toContain(
      '"BucketKeyEnabled":true',
    );
    const [, auditKey] = findByLogicalId(rendered.resources, 'AuditKey');
    const policy = JSON.stringify(properties(auditKey).KeyPolicy);
    expect(policy).toContain('cloudtrail.amazonaws.com');
    expect(policy).toContain('logs.mx-central-1');
  });

  test('SEC-IMP-072 enforces SSL through a deny-only public principal', () => {
    const rendered = renderSecurity(PRODUCTION_CONFIG);
    const policy = first(
      resourcesOfType(rendered.resources, 'AWS::S3::BucketPolicy'),
      'bucket-policy',
    )[1];
    const sslStatement = policyStatements(policy).find((statement) =>
      JSON.stringify(statement.Condition).includes('aws:SecureTransport'),
    );
    expect(sslStatement).toMatchObject({ Effect: 'Deny', Principal: { AWS: '*' } });
    expect(JSON.stringify(sslStatement?.Condition)).toContain('false');
  });

  test('SEC-IMP-073 retains the audit bucket on delete and replacement', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      const auditBucket = bucket(config);
      expect(auditBucket.DeletionPolicy).toBe('Retain');
      expect(auditBucket.UpdateReplacePolicy).toBe('Retain');
    }
  });

  test('SEC-IMP-074 retains staging audit objects for 365 days', () => {
    expect(JSON.stringify(properties(bucket(STAGING_CONFIG)).LifecycleConfiguration)).toContain(
      '365',
    );
  });

  test('SEC-IMP-075 retains production audit objects for 2555 days', () => {
    expect(JSON.stringify(properties(bucket(PRODUCTION_CONFIG)).LifecycleConfiguration)).toContain(
      '2555',
    );
  });

  test('SEC-IMP-076 leaves Object Lock disabled', () => {
    expect(properties(bucket(STAGING_CONFIG)).ObjectLockEnabled).toBeUndefined();
    expect(properties(bucket(PRODUCTION_CONFIG)).ObjectLockEnabled).toBeUndefined();
  });

  test('SEC-IMP-077 encrypts the CloudTrail log group with AuditKey', () => {
    expect(JSON.stringify(properties(logGroup(PRODUCTION_CONFIG)).KmsKeyId)).toContain('AuditKey');
    expect(properties(logGroup(PRODUCTION_CONFIG)).LogGroupName).toBe(
      '/mxmed/production/security/cloudtrail',
    );
  });

  test('SEC-IMP-078 retains staging CloudTrail logs for 90 days', () => {
    expect(properties(logGroup(STAGING_CONFIG)).RetentionInDays).toBe(90);
  });

  test('SEC-IMP-079 retains production CloudTrail logs for 365 days', () => {
    expect(properties(logGroup(PRODUCTION_CONFIG)).RetentionInDays).toBe(365);
  });

  test('SEC-IMP-080 creates one multi-region management trail', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      const trails = resourcesOfType(renderSecurity(config).resources, 'AWS::CloudTrail::Trail');
      expect(trails).toHaveLength(1);
      expect(properties(trails[0]?.[1] ?? trail(config)).IsMultiRegionTrail).toBe(true);
    }
  });

  test('SEC-IMP-081 includes global service events', () => {
    expect(properties(trail(STAGING_CONFIG)).IncludeGlobalServiceEvents).toBe(true);
    expect(properties(trail(PRODUCTION_CONFIG)).IncludeGlobalServiceEvents).toBe(true);
  });

  test('SEC-IMP-082 selects all read and write management events', () => {
    const selectors = properties(trail(PRODUCTION_CONFIG)).EventSelectors;
    expect(selectors).toEqual([{ IncludeManagementEvents: true, ReadWriteType: 'All' }]);
  });

  test('SEC-IMP-083 enables log file validation', () => {
    expect(properties(trail(STAGING_CONFIG)).EnableLogFileValidation).toBe(true);
    expect(properties(trail(PRODUCTION_CONFIG)).EnableLogFileValidation).toBe(true);
  });

  test('SEC-IMP-084 integrates S3, KMS and CloudWatch Logs', () => {
    const trailProps = properties(trail(PRODUCTION_CONFIG));
    expect(trailProps.S3BucketName).toBeDefined();
    expect(trailProps.KMSKeyId).toBeDefined();
    expect(trailProps.CloudWatchLogsLogGroupArn).toBeDefined();
    expect(trailProps.CloudWatchLogsRoleArn).toBeDefined();
    expect(trailProps.IsLogging).toBe(true);
  });

  test('SEC-IMP-085 creates no data event selector', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      expect(JSON.stringify(properties(trail(config)).EventSelectors)).not.toContain(
        'DataResources',
      );
    }
  });

  test('SEC-IMP-086 creates no CloudTrail Lake event data store', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      expect(
        resourcesOfType(renderSecurity(config).resources, 'AWS::CloudTrail::EventDataStore'),
      ).toEqual([]);
    }
  });
});
