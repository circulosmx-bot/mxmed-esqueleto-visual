import type {
  MxMedEnvironmentCode,
  MxMedEnvironmentConfig,
  MxMedEnvironmentName,
} from './environment-config';
import { MXMED_REQUIRED_GLOBAL_TAG_KEYS } from './environment-config';
import { assertMxMedCondition, assertNoSensitiveConfiguration } from '../utils/validation';

const ENVIRONMENT_CODES: Readonly<Record<MxMedEnvironmentName, MxMedEnvironmentCode>> = {
  staging: 'stg',
  production: 'prd',
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function assertBooleanField(config: Record<string, unknown>, field: string): void {
  assertMxMedCondition(
    typeof config[field] === 'boolean',
    'MXMED_CONFIG_INVALID',
    field,
    'must be boolean',
  );
}

function validateTags(value: unknown, environmentName: MxMedEnvironmentName): void {
  assertMxMedCondition(isRecord(value), 'MXMED_CONFIG_INVALID', 'tags', 'must be a tag map');

  for (const key of MXMED_REQUIRED_GLOBAL_TAG_KEYS) {
    assertMxMedCondition(
      typeof value[key] === 'string' && value[key].length > 0,
      'MXMED_CONFIG_INVALID',
      'tags',
      `mandatory tag ${key} is required`,
    );
  }

  assertMxMedCondition(
    value.Project === 'mxmed' &&
      value.Environment === environmentName &&
      value.ManagedBy === 'aws-cdk' &&
      value.Application === 'mexico-medico' &&
      value.Owner === 'platform',
    'MXMED_CONFIG_INVALID',
    'tags',
    'mandatory tag values must match the MXMed contract',
  );
}

export function validateEnvironmentConfig(input: unknown): asserts input is MxMedEnvironmentConfig {
  assertNoSensitiveConfiguration(input);
  assertMxMedCondition(
    isRecord(input),
    'MXMED_CONFIG_INVALID',
    'configuration',
    'must be an object',
  );

  const environmentName = input.environmentName;
  assertMxMedCondition(
    environmentName === 'staging' || environmentName === 'production',
    'MXMED_ENVIRONMENT_INVALID',
    'environmentName',
    'must be staging or production',
  );

  assertMxMedCondition(
    input.environmentCode === ENVIRONMENT_CODES[environmentName],
    'MXMED_CONFIG_INVALID',
    'environmentCode',
    'must match the selected environment',
  );
  assertMxMedCondition(
    input.projectName === 'mxmed',
    'MXMED_CONFIG_INVALID',
    'projectName',
    'must be mxmed',
  );
  assertMxMedCondition(
    input.applicationName === 'mexico-medico',
    'MXMED_CONFIG_INVALID',
    'applicationName',
    'must be mexico-medico',
  );
  assertMxMedCondition(
    input.primaryRegion === 'mx-central-1',
    'MXMED_CONFIG_INVALID',
    'primaryRegion',
    'must be mx-central-1',
  );
  assertMxMedCondition(
    input.emailRegion === 'us-east-1',
    'MXMED_CONFIG_INVALID',
    'emailRegion',
    'must be us-east-1',
  );
  assertMxMedCondition(
    input.accountSource === 'deployment-identity' || input.accountSource === 'ci-variable',
    'MXMED_CONFIG_INVALID',
    'accountSource',
    'must use an approved deployment source',
  );
  assertMxMedCondition(
    Number.isInteger(input.availabilityZoneCount) &&
      Number(input.availabilityZoneCount) >= 2 &&
      Number(input.availabilityZoneCount) <= 3,
    'MXMED_CONFIG_INVALID',
    'availabilityZoneCount',
    'must be an integer between 2 and 3',
  );
  assertMxMedCondition(
    input.natStrategy === 'single-az' || input.natStrategy === 'dual-az',
    'MXMED_CONFIG_INVALID',
    'natStrategy',
    'must be an approved strategy',
  );
  assertMxMedCondition(
    input.computeSizingProfile === 'reduced' || input.computeSizingProfile === 'production-ha',
    'MXMED_CONFIG_INVALID',
    'computeSizingProfile',
    'must be an approved profile',
  );
  assertMxMedCondition(
    input.databaseSizingProfile === 'single-az-reduced' ||
      input.databaseSizingProfile === 'multi-az-production',
    'MXMED_CONFIG_INVALID',
    'databaseSizingProfile',
    'must be an approved profile',
  );

  if (input.domainAlias !== undefined) {
    assertMxMedCondition(
      typeof input.domainAlias === 'string' &&
        /^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/.test(
          input.domainAlias,
        ),
      'MXMED_CONFIG_INVALID',
      'domainAlias',
      'must be omitted or a valid lowercase DNS alias',
    );
  }

  assertMxMedCondition(
    Number.isInteger(input.logRetentionDays) &&
      Number(input.logRetentionDays) >= (environmentName === 'production' ? 90 : 30) &&
      Number(input.logRetentionDays) <= 3653,
    'MXMED_CONFIG_INVALID',
    'logRetentionDays',
    'must be within the environment retention range',
  );
  assertMxMedCondition(
    Number.isInteger(input.backupRetentionDays) &&
      Number(input.backupRetentionDays) >= (environmentName === 'production' ? 35 : 7) &&
      Number(input.backupRetentionDays) <= 3650,
    'MXMED_CONFIG_INVALID',
    'backupRetentionDays',
    'must be within the environment retention range',
  );

  for (const field of [
    'enableDeletionProtection',
    'enableTerminationProtection',
    'enableWaf',
    'enableCloudFrontLogging',
  ]) {
    assertBooleanField(input, field);
  }

  if (environmentName === 'production') {
    for (const field of [
      'enableDeletionProtection',
      'enableTerminationProtection',
      'enableWaf',
      'enableCloudFrontLogging',
    ]) {
      assertMxMedCondition(
        input[field] === true,
        'MXMED_CONFIG_INVALID',
        field,
        'must be enabled in production',
      );
    }
  }

  assertMxMedCondition(
    input.enableWaf === true && input.enableCloudFrontLogging === true,
    'MXMED_CONFIG_INVALID',
    'environmentGuardrails',
    'WAF and safe CloudFront logging are required',
  );
  assertMxMedCondition(
    input.stripeReturnLoggingPolicy === 'path-only-no-query',
    'MXMED_CONFIG_INVALID',
    'stripeReturnLoggingPolicy',
    'must be path-only-no-query',
  );

  validateTags(input.tags, environmentName);
}

export function parseEnvironmentName(value: unknown): MxMedEnvironmentName {
  assertMxMedCondition(
    value === 'staging' || value === 'production',
    'MXMED_ENVIRONMENT_INVALID',
    'environment',
    'context must be staging or production',
  );
  return value;
}
