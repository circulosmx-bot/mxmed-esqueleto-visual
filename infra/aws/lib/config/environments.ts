import type { MxMedEnvironmentConfig, MxMedEnvironmentName } from './environment-config';
import { parseEnvironmentName, validateEnvironmentConfig } from './environment-schema';

export const STAGING_CONFIG = Object.freeze({
  environmentName: 'staging',
  environmentCode: 'stg',
  projectName: 'mxmed',
  applicationName: 'mexico-medico',
  primaryRegion: 'mx-central-1',
  emailRegion: 'us-east-1',
  accountSource: 'deployment-identity',
  availabilityZoneCount: 2,
  natStrategy: 'single-az',
  computeSizingProfile: 'reduced',
  databaseSizingProfile: 'single-az-reduced',
  logRetentionDays: 30,
  backupRetentionDays: 7,
  enableDeletionProtection: false,
  enableTerminationProtection: false,
  enableWaf: true,
  enableCloudFrontLogging: true,
  stripeReturnLoggingPolicy: 'path-only-no-query',
  tags: {
    Project: 'mxmed',
    Environment: 'staging',
    ManagedBy: 'aws-cdk',
    Application: 'mexico-medico',
    Owner: 'platform',
  },
} satisfies MxMedEnvironmentConfig);

export const PRODUCTION_CONFIG = Object.freeze({
  environmentName: 'production',
  environmentCode: 'prd',
  projectName: 'mxmed',
  applicationName: 'mexico-medico',
  primaryRegion: 'mx-central-1',
  emailRegion: 'us-east-1',
  accountSource: 'deployment-identity',
  availabilityZoneCount: 2,
  natStrategy: 'dual-az',
  computeSizingProfile: 'production-ha',
  databaseSizingProfile: 'multi-az-production',
  logRetentionDays: 90,
  backupRetentionDays: 35,
  enableDeletionProtection: true,
  enableTerminationProtection: true,
  enableWaf: true,
  enableCloudFrontLogging: true,
  stripeReturnLoggingPolicy: 'path-only-no-query',
  tags: {
    Project: 'mxmed',
    Environment: 'production',
    ManagedBy: 'aws-cdk',
    Application: 'mexico-medico',
    Owner: 'platform',
  },
} satisfies MxMedEnvironmentConfig);

const ENVIRONMENTS: Readonly<Record<MxMedEnvironmentName, MxMedEnvironmentConfig>> = {
  staging: STAGING_CONFIG,
  production: PRODUCTION_CONFIG,
};

export function getEnvironmentConfig(value: unknown): MxMedEnvironmentConfig {
  const environmentName = parseEnvironmentName(value);
  const config = ENVIRONMENTS[environmentName];
  validateEnvironmentConfig(config);
  return config;
}
