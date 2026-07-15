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

const EXPECTED_VPC_CIDRS: Readonly<Record<MxMedEnvironmentName, string>> = {
  staging: '10.20.0.0/16',
  production: '10.30.0.0/16',
};

const EXPECTED_SUBNET_MASKS = Object.freeze({
  publicIngress: 24,
  privateApp: 20,
  privateEndpoints: 24,
  isolatedData: 24,
});

const EXPECTED_ENDPOINT_PROFILES = Object.freeze({
  staging: 's3-only',
  production: 'production-core',
} as const);

const EXPECTED_FLOW_LOG_RETENTION_DAYS = Object.freeze({
  staging: 30,
  production: 90,
} as const);

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function containsIpv6Field(value: unknown, visited = new Set<object>()): boolean {
  if (typeof value !== 'object' || value === null || visited.has(value)) {
    return false;
  }

  visited.add(value);
  if (Array.isArray(value)) {
    return value.some((entry) => containsIpv6Field(entry, visited));
  }

  return Object.entries(value).some(
    ([key, entry]) => key.toLowerCase().includes('ipv6') || containsIpv6Field(entry, visited),
  );
}

function isRfc1918Cidr16(value: unknown): value is string {
  if (typeof value !== 'string') {
    return false;
  }

  const match = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d{1,2})$/.exec(value);
  if (match === null) {
    return false;
  }

  const octets = match.slice(1, 5).map(Number);
  const prefix = Number(match[5]);
  if (octets.some((octet) => !Number.isInteger(octet) || octet < 0 || octet > 255)) {
    return false;
  }

  const first = octets[0];
  const second = octets[1];
  const privateRange =
    first === 10 ||
    (first === 172 && second !== undefined && second >= 16 && second <= 31) ||
    (first === 192 && second === 168);
  return privateRange && prefix === 16;
}

function validateSubnetMasks(value: unknown): void {
  assertMxMedCondition(
    isRecord(value),
    'MXMED_CONFIG_INVALID',
    'subnetMasks',
    'must be the contracted subnet mask map',
  );

  const expectedKeys = Object.keys(EXPECTED_SUBNET_MASKS).sort();
  assertMxMedCondition(
    Object.keys(value).sort().join(',') === expectedKeys.join(','),
    'MXMED_CONFIG_INVALID',
    'subnetMasks',
    'must contain only the contracted subnet tiers',
  );

  for (const [key, mask] of Object.entries(EXPECTED_SUBNET_MASKS)) {
    assertMxMedCondition(
      value[key] === mask,
      'MXMED_CONFIG_INVALID',
      'subnetMasks',
      'must match the MXMed V1 network contract',
    );
  }
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
  assertMxMedCondition(
    !containsIpv6Field(input),
    'MXMED_CONFIG_INVALID',
    'networkAddressFamily',
    'IPv6 configuration is not allowed in V1',
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
    isRfc1918Cidr16(input.vpcCidr),
    'MXMED_CONFIG_INVALID',
    'vpcCidr',
    'must be an RFC1918 /16 CIDR',
  );
  assertMxMedCondition(
    input.vpcCidr === EXPECTED_VPC_CIDRS[environmentName],
    'MXMED_CONFIG_INVALID',
    'vpcCidr',
    'must match the selected environment',
  );
  validateSubnetMasks(input.subnetMasks);
  assertMxMedCondition(
    input.availabilityZoneCount === 2,
    'MXMED_CONFIG_INVALID',
    'availabilityZoneCount',
    'must be exactly two in V1',
  );
  assertMxMedCondition(
    input.natStrategy === 'single-az' || input.natStrategy === 'dual-az',
    'MXMED_CONFIG_INVALID',
    'natStrategy',
    'must be an approved strategy',
  );
  assertMxMedCondition(
    input.natStrategy === (environmentName === 'staging' ? 'single-az' : 'dual-az'),
    'MXMED_CONFIG_INVALID',
    'natStrategy',
    'must match the selected environment',
  );
  assertMxMedCondition(
    input.interfaceEndpointProfile === EXPECTED_ENDPOINT_PROFILES[environmentName],
    'MXMED_CONFIG_INVALID',
    'interfaceEndpointProfile',
    'must match the selected environment',
  );
  assertMxMedCondition(
    input.flowLogRetentionDays === EXPECTED_FLOW_LOG_RETENTION_DAYS[environmentName],
    'MXMED_CONFIG_INVALID',
    'flowLogRetentionDays',
    'must match the selected environment',
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

export function validateEnvironmentNetworkSeparation(
  staging: MxMedEnvironmentConfig,
  production: MxMedEnvironmentConfig,
): void {
  assertMxMedCondition(
    staging.environmentName === 'staging' && production.environmentName === 'production',
    'MXMED_CONFIG_INVALID',
    'networkEnvironments',
    'must compare staging and production in canonical order',
  );
  assertMxMedCondition(
    staging.vpcCidr !== production.vpcCidr,
    'MXMED_CONFIG_INVALID',
    'vpcCidrPair',
    'staging and production CIDRs must differ',
  );
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
