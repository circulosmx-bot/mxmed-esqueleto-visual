import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { firstResource, properties, renderData, resourcesOfType } from './data-test-helpers';

function roleByMarker(template: Readonly<Record<string, unknown>>, marker: string): unknown {
  const resources = (template.Resources ?? {}) as Record<string, unknown>;
  const entry = Object.entries(resources).find(([logicalId]) =>
    logicalId.toLowerCase().includes(marker.toLowerCase()),
  );
  if (entry === undefined) throw new Error(`missing-role-${marker}`);
  return entry[1];
}

describe('data security boundaries', () => {
  test('DATA-IMP-066 attaches DatabaseSecurityGroup', () => {
    const props = properties(
      firstResource(renderData(STAGING_CONFIG).resources, 'AWS::RDS::DBInstance'),
    );
    expect(JSON.stringify(props.VPCSecurityGroups)).toMatch(/DatabaseSecurityGroup/);
  });
  test('DATA-IMP-067 attaches no duplicate DB security group', () => {
    const props = properties(
      firstResource(renderData(PRODUCTION_CONFIG).resources, 'AWS::RDS::DBInstance'),
    );
    expect(props.VPCSecurityGroups).toHaveLength(1);
  });
  test('DATA-IMP-068 keeps the DB security group free of public ingress', () => {
    const network = renderData(PRODUCTION_CONFIG).networkTemplate;
    const resources = (network.Resources ?? {}) as Record<
      string,
      { Type?: string; Properties?: Record<string, unknown> }
    >;
    const databaseIngress = Object.values(resources).filter(
      (resource) =>
        resource.Type === 'AWS::EC2::SecurityGroupIngress' &&
        JSON.stringify(resource.Properties?.GroupId).includes('DatabaseSecurityGroup'),
    );
    expect(databaseIngress).toHaveLength(1);
    expect(databaseIngress[0]?.Properties).not.toHaveProperty('CidrIp');
    expect(databaseIngress[0]?.Properties).not.toHaveProperty('CidrIpv6');
  });
  test('DATA-IMP-069 publishes no secret output from DataStack', () => {
    const rendered = renderData(STAGING_CONFIG);
    expect(rendered.outputs).toEqual({});
    expect(JSON.stringify(rendered.template)).not.toMatch(/MasterUserSecretSecretArn.*Output/i);
  });
  test('DATA-IMP-070 creates no duplicate Secrets Manager secret', () => {
    expect(
      resourcesOfType(renderData(PRODUCTION_CONFIG).resources, 'AWS::SecretsManager::Secret'),
    ).toEqual([]);
  });
  test('DATA-IMP-071 grants the application role no master-secret access', () => {
    const security = renderData(PRODUCTION_CONFIG).securityTemplate;
    expect(JSON.stringify(roleByMarker(security, 'ApplicationTaskRole'))).not.toMatch(
      /MasterUserSecret|rds-db:connect/i,
    );
  });
  test('DATA-IMP-072 grants the migration role no premature master-secret access', () => {
    const security = renderData(PRODUCTION_CONFIG).securityTemplate;
    expect(JSON.stringify(roleByMarker(security, 'MigrationTaskRole'))).not.toMatch(
      /MasterUserSecret|rds-db:connect/i,
    );
  });
});
