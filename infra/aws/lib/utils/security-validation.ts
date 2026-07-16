import { CfnDeletionPolicy, CfnOutput } from 'aws-cdk-lib';
import type { CfnResource, Duration } from 'aws-cdk-lib';
import { CfnTrail } from 'aws-cdk-lib/aws-cloudtrail';
import { CfnRole } from 'aws-cdk-lib/aws-iam';
import { CfnAlias, CfnKey } from 'aws-cdk-lib/aws-kms';
import { CfnLogGroup } from 'aws-cdk-lib/aws-logs';
import { CfnBucket } from 'aws-cdk-lib/aws-s3';
import { CfnSecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentConfig, MxMedEnvironmentName } from '../config/environment-config';
import { mxmedSecurityKeyAlias } from './security-naming';
import { assertMxMedCondition } from './validation';

interface GithubOidcValidationInput {
  readonly organization: string;
  readonly repository: string;
  readonly branch: string;
  readonly githubEnvironment: string;
  readonly environmentName: MxMedEnvironmentName;
  readonly maxSessionDuration: Duration;
}

const GITHUB_SLUG_PATTERN = /^[A-Za-z0-9](?:[A-Za-z0-9._-]{0,98}[A-Za-z0-9])?$/;
const GITHUB_BRANCH_PATTERN = /^[A-Za-z0-9](?:[A-Za-z0-9._/-]{0,198}[A-Za-z0-9])?$/;

function assertGithubField(value: string, field: string, pattern: RegExp): void {
  assertMxMedCondition(
    pattern.test(value) && !value.includes('*') && !value.includes('..'),
    'MXMED_CONFIG_INVALID',
    field,
    'must be an exact GitHub identifier without wildcard',
  );
}

export function validateGithubOidcDeploymentProps(input: GithubOidcValidationInput): void {
  assertGithubField(input.organization, 'githubOrganization', GITHUB_SLUG_PATTERN);
  assertGithubField(input.repository, 'githubRepository', GITHUB_SLUG_PATTERN);
  assertGithubField(input.branch, 'githubBranch', GITHUB_BRANCH_PATTERN);
  assertGithubField(input.githubEnvironment, 'githubEnvironment', GITHUB_SLUG_PATTERN);
  assertMxMedCondition(
    input.environmentName !== 'production' || input.githubEnvironment === 'production',
    'MXMED_CONFIG_INVALID',
    'githubEnvironment',
    'production requires the production GitHub Environment',
  );
  assertMxMedCondition(
    input.maxSessionDuration.toSeconds() === 3600,
    'MXMED_CONFIG_INVALID',
    'githubSessionDuration',
    'must be exactly one hour',
  );
}

function retained(resource: CfnResource): boolean {
  return (
    resource.cfnOptions.deletionPolicy === CfnDeletionPolicy.RETAIN &&
    resource.cfnOptions.updateReplacePolicy === CfnDeletionPolicy.RETAIN
  );
}

function normalizedPath(node: IConstruct): string {
  return node.node.path.toLowerCase().replaceAll(/[^a-z0-9]/g, '');
}

function pushIf(errors: string[], condition: boolean, code: string): void {
  if (condition) errors.push(code);
}

function childResources<T extends CfnResource>(
  scope: IConstruct,
  resourceType: new (...args: never[]) => T,
): T[] {
  return scope.node.findAll().filter((node): node is T => node instanceof resourceType);
}

function validateKeys(errors: string[], scope: IConstruct, config: MxMedEnvironmentConfig): void {
  const keys = childResources(scope, CfnKey);
  const aliases = childResources(scope, CfnAlias);
  const expectedAliases = [
    mxmedSecurityKeyAlias(config.environmentCode, 'application-data'),
    mxmedSecurityKeyAlias(config.environmentCode, 'secrets'),
    mxmedSecurityKeyAlias(config.environmentCode, 'audit'),
    mxmedSecurityKeyAlias(config.environmentCode, 'backup'),
  ].sort();

  pushIf(errors, keys.length !== 4, 'MXMED_SECURITY_KMS_KEY_COUNT_INVALID');
  pushIf(
    errors,
    keys.some(
      (key) =>
        key.enableKeyRotation !== true ||
        key.keySpec !== 'SYMMETRIC_DEFAULT' ||
        key.keyUsage !== 'ENCRYPT_DECRYPT' ||
        key.multiRegion !== false ||
        key.pendingWindowInDays !== config.kmsDeletionWindowDays ||
        !retained(key),
    ),
    'MXMED_SECURITY_KMS_CONFIGURATION_INVALID',
  );
  const actualAliases = aliases
    .map((alias) => alias.aliasName)
    .filter((alias): alias is string => typeof alias === 'string')
    .sort();
  pushIf(
    errors,
    aliases.length !== 4 || actualAliases.join(',') !== expectedAliases.join(','),
    'MXMED_SECURITY_KMS_ALIAS_INVALID',
  );
}

function validateSecrets(
  errors: string[],
  scope: IConstruct,
  config: MxMedEnvironmentConfig,
): void {
  const secrets = childResources(scope, CfnSecret);
  pushIf(errors, secrets.length !== 4, 'MXMED_SECURITY_SECRET_COUNT_INVALID');

  const expectedPrefix = `/mxmed/${config.environmentName}/`;
  for (const secret of secrets) {
    const name = typeof secret.name === 'string' ? secret.name : '';
    const external = name.includes('/providers/');
    pushIf(
      errors,
      !name.startsWith(expectedPrefix) || secret.kmsKeyId === undefined || !retained(secret),
      'MXMED_SECURITY_SECRET_CONFIGURATION_INVALID',
    );
    pushIf(
      errors,
      secret.secretString !== undefined ||
        (external && secret.generateSecretString !== undefined) ||
        (!external && secret.generateSecretString === undefined),
      'MXMED_SECURITY_SECRET_VALUE_CONTRACT_INVALID',
    );
  }
}

function validateAudit(errors: string[], scope: IConstruct, config: MxMedEnvironmentConfig): void {
  const buckets = childResources(scope, CfnBucket);
  const logGroups = childResources(scope, CfnLogGroup);
  const trails = childResources(scope, CfnTrail);
  const bucket = buckets[0];
  const logGroup = logGroups[0];
  const trail = trails[0];

  const publicBlock = bucket?.publicAccessBlockConfiguration as Record<string, unknown> | undefined;
  pushIf(
    errors,
    buckets.length !== 1 ||
      bucket === undefined ||
      publicBlock?.blockPublicAcls !== true ||
      publicBlock.blockPublicPolicy !== true ||
      publicBlock.ignorePublicAcls !== true ||
      publicBlock.restrictPublicBuckets !== true ||
      (bucket.versioningConfiguration as { status?: string } | undefined)?.status !== 'Enabled' ||
      !retained(bucket),
    'MXMED_SECURITY_AUDIT_BUCKET_INVALID',
  );
  pushIf(
    errors,
    logGroups.length !== 1 ||
      logGroup?.retentionInDays !== config.cloudTrailLogRetentionDays ||
      logGroup.kmsKeyId === undefined ||
      !retained(logGroup),
    'MXMED_SECURITY_CLOUDTRAIL_LOG_GROUP_INVALID',
  );
  const trailText = JSON.stringify(trail?.eventSelectors ?? []);
  pushIf(
    errors,
    trails.length !== 1 ||
      trail?.enableLogFileValidation !== true ||
      trail.isMultiRegionTrail !== true ||
      trail.includeGlobalServiceEvents !== true ||
      trail.isLogging !== true ||
      trail.kmsKeyId === undefined ||
      trailText.includes('DataResources'),
    'MXMED_SECURITY_MANAGEMENT_TRAIL_INVALID',
  );
}

function validateRoles(errors: string[], scope: IConstruct): void {
  const roles = childResources(scope, CfnRole).filter((role) => {
    const path = normalizedPath(role);
    return ['ecsexecutionrole', 'applicationtaskrole', 'migrationtaskrole', 'jobstaskrole'].some(
      (name) => path.includes(name),
    );
  });
  pushIf(errors, roles.length !== 4, 'MXMED_SECURITY_WORKLOAD_ROLE_COUNT_INVALID');
  pushIf(
    errors,
    roles.some((role) => role.permissionsBoundary === undefined),
    'MXMED_SECURITY_WORKLOAD_BOUNDARY_MISSING',
  );
}

function validateSecurityFoundation(scope: IConstruct, config: MxMedEnvironmentConfig): string[] {
  const errors: string[] = [];
  validateKeys(errors, scope, config);
  validateSecrets(errors, scope, config);
  validateAudit(errors, scope, config);
  validateRoles(errors, scope);
  const outputs = scope.node
    .findAll()
    .filter((node) => node instanceof CfnOutput && !node.node.path.includes('/Exports/'));
  pushIf(errors, outputs.length > 0, 'MXMED_SECURITY_OUTPUT_FORBIDDEN');
  pushIf(
    errors,
    config.enableDataEventTrail,
    'MXMED_SECURITY_DATA_EVENT_REQUIRES_STORAGE_CONTRACT',
  );
  return [...new Set(errors)].sort();
}

export function registerMxMedSecurityValidation(
  scope: IConstruct,
  config: MxMedEnvironmentConfig,
): void {
  scope.node.addValidation({
    validate: () => validateSecurityFoundation(scope, config),
  });
}
