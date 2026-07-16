import { mxmedNatGatewayCount } from '../lib/config/environment-config';
import {
  validateEnvironmentConfig,
  validateEnvironmentNetworkSeparation,
} from '../lib/config/environment-schema';
import {
  getEnvironmentConfig,
  PRODUCTION_CONFIG,
  STAGING_CONFIG,
} from '../lib/config/environments';

describe('environment configuration', () => {
  test('accepts the explicit staging configuration', () => {
    expect(() => {
      validateEnvironmentConfig(STAGING_CONFIG);
    }).not.toThrow();
    expect(getEnvironmentConfig('staging', 'launch-lean-v1')).toEqual(STAGING_CONFIG);
  });

  test('accepts the explicit production configuration', () => {
    expect(() => {
      validateEnvironmentConfig(PRODUCTION_CONFIG);
    }).not.toThrow();
    expect(getEnvironmentConfig('production', 'launch-lean-v1')).toEqual(PRODUCTION_CONFIG);
  });

  test('rejects an unknown environment', () => {
    expect(() => getEnvironmentConfig('preview', 'launch-lean-v1')).toThrow(
      'MXMED_ENVIRONMENT_INVALID:environment:context must be staging or production',
    );
  });

  test('rejects a non-contractual primary region', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, primaryRegion: 'us-west-2' });
    }).toThrow('MXMED_CONFIG_INVALID:primaryRegion');
  });

  test('rejects production without every guardrail', () => {
    for (const field of [
      'enableDeletionProtection',
      'enableTerminationProtection',
      'enableWaf',
      'enableCloudFrontLogging',
    ] as const) {
      expect(() => {
        validateEnvironmentConfig({ ...PRODUCTION_CONFIG, [field]: false });
      }).toThrow(`MXMED_CONFIG_INVALID:${field}`);
    }
  });

  test('rejects missing mandatory tags', () => {
    const { Owner: _owner, ...missingOwner } = PRODUCTION_CONFIG.tags;
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, tags: missingOwner });
    }).toThrow('MXMED_CONFIG_INVALID:tags');
  });

  test('rejects an unsafe Stripe return policy', () => {
    expect(() => {
      validateEnvironmentConfig({
        ...PRODUCTION_CONFIG,
        stripeReturnLoggingPolicy: 'full-request-line',
      });
    }).toThrow('MXMED_CONFIG_INVALID:stripeReturnLoggingPolicy');
  });

  test('rejects sensitive field names without echoing the field', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, databasePassword: 'redacted-value' });
    }).toThrow('MXMED_CONFIG_SENSITIVE_FIELD:configuration');
  });

  test('rejects credential-like values without echoing the value', () => {
    const credentialLikeValue = ['AKIA', '12345678', '90ABCDEF'].join('');
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, domainAlias: credentialLikeValue });
    }).toThrow('MXMED_CONFIG_CREDENTIAL_LIKE_VALUE:configuration');
  });

  test('contains the contracted staging network configuration', () => {
    expect(STAGING_CONFIG).toMatchObject({
      vpcCidr: '10.20.0.0/16',
      subnetMasks: {
        publicIngress: 24,
        privateApp: 20,
        privateEndpoints: 24,
        isolatedData: 24,
      },
      availabilityZoneCount: 2,
      interfaceEndpointProfile: 's3-only',
      flowLogRetentionDays: 30,
    });
    expect(mxmedNatGatewayCount(STAGING_CONFIG.natStrategy)).toBe(1);
  });

  test('contains the contracted production network configuration', () => {
    expect(PRODUCTION_CONFIG).toMatchObject({
      vpcCidr: '10.30.0.0/16',
      subnetMasks: {
        publicIngress: 24,
        privateApp: 20,
        privateEndpoints: 24,
        isolatedData: 24,
      },
      availabilityZoneCount: 2,
      interfaceEndpointProfile: 's3-only',
      flowLogRetentionDays: 90,
    });
    expect(mxmedNatGatewayCount(PRODUCTION_CONFIG.natStrategy)).toBe(1);
  });

  test.each(['8.8.0.0/16', '10.20.0.0/24', '10.20.0.0/invalid'])(
    'rejects an invalid private /16 CIDR: %s',
    (vpcCidr) => {
      expect(() => {
        validateEnvironmentConfig({ ...STAGING_CONFIG, vpcCidr });
      }).toThrow('MXMED_CONFIG_INVALID:vpcCidr');
    },
  );

  test('rejects a valid private CIDR assigned to the wrong environment', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, vpcCidr: '10.30.0.0/16' });
    }).toThrow('MXMED_CONFIG_INVALID:vpcCidr');
  });

  test('rejects equal staging and production CIDRs', () => {
    expect(() => {
      validateEnvironmentNetworkSeparation(STAGING_CONFIG, {
        ...PRODUCTION_CONFIG,
        vpcCidr: STAGING_CONFIG.vpcCidr,
      });
    }).toThrow('MXMED_CONFIG_INVALID:vpcCidrPair');
  });

  test('rejects any subnet mask drift', () => {
    expect(() => {
      validateEnvironmentConfig({
        ...STAGING_CONFIG,
        subnetMasks: { ...STAGING_CONFIG.subnetMasks, privateApp: 21 },
      });
    }).toThrow('MXMED_CONFIG_INVALID:subnetMasks');
  });

  test.each([1, 3])('rejects availabilityZoneCount=%i in V1', (availabilityZoneCount) => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, availabilityZoneCount });
    }).toThrow('MXMED_CONFIG_INVALID:availabilityZoneCount');
  });

  test('rejects NAT strategies that do not match the environment', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, natStrategy: 'dual-az' });
    }).toThrow('MXMED_CONFIG_INVALID:natStrategy');
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, natStrategy: 'dual-az' });
    }).toThrow('MXMED_CONFIG_INVALID:natStrategy');
  });

  test('rejects unknown or cross-environment endpoint profiles', () => {
    expect(() => {
      validateEnvironmentConfig({
        ...STAGING_CONFIG,
        interfaceEndpointProfile: 'production-core',
      });
    }).toThrow('MXMED_CONFIG_INVALID:interfaceEndpointProfile');
    expect(() => {
      validateEnvironmentConfig({
        ...PRODUCTION_CONFIG,
        interfaceEndpointProfile: 'production-core',
      });
    }).toThrow('MXMED_CONFIG_INVALID:interfaceEndpointProfile');
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, interfaceEndpointProfile: 'unknown' });
    }).toThrow('MXMED_CONFIG_INVALID:interfaceEndpointProfile');
  });

  test('rejects Flow Log retention drift', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, flowLogRetentionDays: 90 });
    }).toThrow('MXMED_CONFIG_INVALID:flowLogRetentionDays');
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, flowLogRetentionDays: 30 });
    }).toThrow('MXMED_CONFIG_INVALID:flowLogRetentionDays');
  });

  test('rejects accidental IPv6 configuration without echoing the object', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, enableIpv6: true });
    }).toThrow('MXMED_CONFIG_INVALID:networkAddressFamily');
  });
});
