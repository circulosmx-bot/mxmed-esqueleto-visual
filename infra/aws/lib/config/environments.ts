import type { MxMedEnvironmentConfig, MxMedEnvironmentName } from './environment-config';
import {
  parseEnvironmentName,
  validateEnvironmentConfig,
  validateEnvironmentNetworkSeparation,
} from './environment-schema';

export const STAGING_CONFIG = Object.freeze({
  environmentName: 'staging',
  environmentCode: 'stg',
  projectName: 'mxmed',
  applicationName: 'mexico-medico',
  primaryRegion: 'mx-central-1',
  emailRegion: 'us-east-1',
  accountSource: 'deployment-identity',
  vpcCidr: '10.20.0.0/16',
  subnetMasks: {
    publicIngress: 24,
    privateApp: 20,
    privateEndpoints: 24,
    isolatedData: 24,
  },
  availabilityZoneCount: 2,
  natStrategy: 'single-az',
  interfaceEndpointProfile: 's3-only',
  flowLogRetentionDays: 30,
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
  vpcCidr: '10.30.0.0/16',
  subnetMasks: {
    publicIngress: 24,
    privateApp: 20,
    privateEndpoints: 24,
    isolatedData: 24,
  },
  availabilityZoneCount: 2,
  natStrategy: 'dual-az',
  interfaceEndpointProfile: 'production-core',
  flowLogRetentionDays: 90,
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

validateEnvironmentConfig(STAGING_CONFIG);
validateEnvironmentConfig(PRODUCTION_CONFIG);
validateEnvironmentNetworkSeparation(STAGING_CONFIG, PRODUCTION_CONFIG);

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
