import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { App } from 'aws-cdk-lib';
import { CfnFlowLog, CfnVPC } from 'aws-cdk-lib/aws-ec2';
import { Template } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

interface RenderedResource {
  readonly Type: string;
  readonly Properties?: Record<string, unknown>;
  readonly DeletionPolicy?: string;
}

interface RenderedNetwork {
  readonly app: App;
  readonly stage: MxMedEnvironmentStage;
  readonly resources: Readonly<Record<string, RenderedResource>>;
  readonly outputs: Readonly<Record<string, unknown>>;
}

interface NetworkFixture {
  readonly app: App;
  readonly stage: MxMedEnvironmentStage;
}

const EXPECTED_SUBNETS = Object.freeze({
  staging: {
    'public-ingress': ['10.20.16.0/24', '10.20.48.0/24'],
    'private-app': ['10.20.0.0/20', '10.20.32.0/20'],
    'private-endpoints': ['10.20.17.0/24', '10.20.49.0/24'],
    'isolated-data': ['10.20.18.0/24', '10.20.50.0/24'],
  },
  production: {
    'public-ingress': ['10.30.16.0/24', '10.30.48.0/24'],
    'private-app': ['10.30.0.0/20', '10.30.32.0/20'],
    'private-endpoints': ['10.30.17.0/24', '10.30.49.0/24'],
    'isolated-data': ['10.30.18.0/24', '10.30.50.0/24'],
  },
});

function createNetwork(config: MxMedEnvironmentConfig): NetworkFixture {
  const app = new App({ analyticsReporting: false });
  const suffix = config.environmentName === 'staging' ? 'Staging' : 'Production';
  const stage = new MxMedEnvironmentStage(app, `MxMed${suffix}`, { config });
  return { app, stage };
}

function renderNetwork(config: MxMedEnvironmentConfig): RenderedNetwork {
  const { app, stage } = createNetwork(config);
  const rendered = Template.fromStack(stage.networkStack).toJSON() as unknown as {
    Resources?: Record<string, RenderedResource>;
    Outputs?: Record<string, unknown>;
  };
  return {
    app,
    stage,
    resources: rendered.Resources ?? {},
    outputs: rendered.Outputs ?? {},
  };
}

function resourcesOfType(
  resources: Readonly<Record<string, RenderedResource>>,
  type: string,
): (readonly [string, RenderedResource])[] {
  return Object.entries(resources).filter(([, resource]) => resource.Type === type);
}

function first<T>(values: readonly T[], label: string): T {
  const value = values[0];
  if (value === undefined) {
    throw new Error(`missing-first-value:${label}`);
  }
  return value;
}

function props(resource: RenderedResource): Record<string, unknown> {
  return resource.Properties ?? {};
}

function tagValue(resource: RenderedResource, key: string): string | undefined {
  const tags = props(resource).Tags;
  if (!Array.isArray(tags)) {
    return undefined;
  }
  const match = tags.find(
    (tag) =>
      typeof tag === 'object' && tag !== null && (tag as Record<string, unknown>).Key === key,
  ) as Record<string, unknown> | undefined;
  return typeof match?.Value === 'string' ? match.Value : undefined;
}

function logicalIdFromGetAtt(value: unknown): string | undefined {
  if (typeof value !== 'object' || value === null) {
    return undefined;
  }
  const getAtt = (value as Record<string, unknown>)['Fn::GetAtt'];
  return Array.isArray(getAtt) && typeof getAtt[0] === 'string' ? getAtt[0] : undefined;
}

function logicalIdFromRef(value: unknown): string | undefined {
  if (typeof value !== 'object' || value === null) {
    return undefined;
  }
  const ref = (value as Record<string, unknown>).Ref;
  return typeof ref === 'string' ? ref : undefined;
}

function logicalIdStartingWith(
  resources: Readonly<Record<string, RenderedResource>>,
  prefix: string,
): string {
  const logicalId = Object.keys(resources).find((id) => id.startsWith(prefix));
  if (logicalId === undefined) {
    throw new Error(`missing-logical-id:${prefix}`);
  }
  return logicalId;
}

function securityGroupIngress(
  resources: Readonly<Record<string, RenderedResource>>,
  targetId: string,
): RenderedResource[] {
  return resourcesOfType(resources, 'AWS::EC2::SecurityGroupIngress')
    .map(([, resource]) => resource)
    .filter((resource) => logicalIdFromGetAtt(props(resource).GroupId) === targetId);
}

function securityGroupEgress(
  resources: Readonly<Record<string, RenderedResource>>,
  sourceId: string,
): RenderedResource[] {
  return resourcesOfType(resources, 'AWS::EC2::SecurityGroupEgress')
    .map(([, resource]) => resource)
    .filter((resource) => logicalIdFromGetAtt(props(resource).GroupId) === sourceId);
}

describe.each([
  ['staging', STAGING_CONFIG],
  ['production', PRODUCTION_CONFIG],
] as const)('%s network stack', (_name, config) => {
  test('creates one independent IPv4 VPC with DNS support and hostnames', () => {
    const { resources } = renderNetwork(config);
    const vpcs = resourcesOfType(resources, 'AWS::EC2::VPC');
    expect(vpcs).toHaveLength(1);
    const vpc = first(vpcs, 'vpc')[1];
    expect(props(vpc)).toMatchObject({
      CidrBlock: config.vpcCidr,
      EnableDnsSupport: true,
      EnableDnsHostnames: true,
    });
    expect(resourcesOfType(resources, 'AWS::EC2::VPCCidrBlock')).toHaveLength(0);
    expect(JSON.stringify(vpc)).not.toContain('Ipv6');
  });

  test('creates eight exact subnets in four stable tiers', () => {
    const { resources } = renderNetwork(config);
    const subnets = resourcesOfType(resources, 'AWS::EC2::Subnet').map(([, resource]) => resource);
    expect(subnets).toHaveLength(8);

    for (const [groupName, expectedCidrs] of Object.entries(
      EXPECTED_SUBNETS[config.environmentName],
    )) {
      const groupSubnets = subnets.filter(
        (subnet) => tagValue(subnet, 'aws-cdk:subnet-name') === groupName,
      );
      expect(groupSubnets).toHaveLength(2);
      expect(groupSubnets.map((subnet) => props(subnet).CidrBlock).sort()).toEqual(
        [...expectedCidrs].sort(),
      );
      expect(groupSubnets.map((subnet) => props(subnet).MapPublicIpOnLaunch)).not.toContain(true);
      const azSlots = groupSubnets.map((subnet) => JSON.stringify(props(subnet).AvailabilityZone));
      expect(azSlots.some((az) => az.includes('[0,'))).toBe(true);
      expect(azSlots.some((az) => az.includes('[1,'))).toBe(true);
    }
  });

  test('does not create custom NACL, IPv6, peering, VPN or transit resources', () => {
    const { resources } = renderNetwork(config);
    for (const type of [
      'AWS::EC2::EgressOnlyInternetGateway',
      'AWS::EC2::NetworkAcl',
      'AWS::EC2::NetworkAclEntry',
      'AWS::EC2::SubnetNetworkAclAssociation',
      'AWS::EC2::TransitGateway',
      'AWS::EC2::VPCPeeringConnection',
      'AWS::EC2::VPNGateway',
    ]) {
      expect(resourcesOfType(resources, type)).toHaveLength(0);
    }
    expect(JSON.stringify(resources)).not.toMatch(/DestinationIpv6CidrBlock|Ipv6CidrBlock/);
  });

  test('exposes the typed network contract with only automatic cross-stack outputs', () => {
    const { stage, outputs } = renderNetwork(config);
    expect(stage.networkStack.vpc).toBeDefined();
    expect(stage.networkStack.publicIngressSubnets).toHaveLength(2);
    expect(stage.networkStack.privateAppSubnets).toHaveLength(2);
    expect(stage.networkStack.privateEndpointSubnets).toHaveLength(2);
    expect(stage.networkStack.isolatedDataSubnets).toHaveLength(2);
    expect(stage.networkStack.albIngressSecurityGroup).toBeDefined();
    expect(stage.networkStack.applicationSecurityGroup).toBeDefined();
    expect(stage.networkStack.databaseSecurityGroup).toBeDefined();
    expect(stage.networkStack.sessionSecurityGroup).toBeDefined();
    expect(stage.networkStack.endpointSecurityGroup).toBeDefined();
    expect(stage.networkStack.flowLogGroup).toBeDefined();
    expect(Object.keys(outputs)).toHaveLength(4);
    expect(Object.keys(outputs).every((key) => key.startsWith('ExportsOutput'))).toBe(true);
    expect(JSON.stringify(outputs)).not.toMatch(/password|secret|account/i);
  });

  test('creates the contracted NAT count and one Internet Gateway', () => {
    const { resources } = renderNetwork(config);
    const expectedNatCount = config.environmentName === 'staging' ? 1 : 2;
    expect(resourcesOfType(resources, 'AWS::EC2::NatGateway')).toHaveLength(expectedNatCount);
    expect(resourcesOfType(resources, 'AWS::EC2::EIP')).toHaveLength(expectedNatCount);
    expect(resourcesOfType(resources, 'AWS::EC2::InternetGateway')).toHaveLength(1);
  });

  test('routes public subnets to IGW and private-app subnets to NAT', () => {
    const { resources } = renderNetwork(config);
    const routes = resourcesOfType(resources, 'AWS::EC2::Route');
    const publicRoutes = routes.filter(([id]) => id.includes('publicingress'));
    const appRoutes = routes.filter(([id]) => id.includes('privateapp'));
    expect(publicRoutes).toHaveLength(2);
    expect(appRoutes).toHaveLength(2);
    expect(publicRoutes.every(([, route]) => props(route).GatewayId !== undefined)).toBe(true);
    expect(appRoutes.every(([, route]) => props(route).NatGatewayId !== undefined)).toBe(true);

    const natRefs = appRoutes.map(([, route]) => logicalIdFromRef(props(route).NatGatewayId));
    if (config.environmentName === 'staging') {
      expect(new Set(natRefs).size).toBe(1);
    } else {
      expect(new Set(natRefs).size).toBe(2);
      expect(natRefs[0]).toContain('Subnet1NATGateway');
      expect(natRefs[1]).toContain('Subnet2NATGateway');
    }
  });

  test('keeps private-endpoints and isolated-data without default routes', () => {
    const { resources } = renderNetwork(config);
    const routes = resourcesOfType(resources, 'AWS::EC2::Route');
    expect(
      routes.filter(
        ([id, route]) =>
          (id.includes('privateendpoints') || id.includes('isolateddata')) &&
          props(route).DestinationCidrBlock === '0.0.0.0/0',
      ),
    ).toHaveLength(0);
  });

  test('creates S3 Gateway Endpoint only on private-app route tables', () => {
    const { resources } = renderNetwork(config);
    const endpoints = resourcesOfType(resources, 'AWS::EC2::VPCEndpoint');
    const gateway = endpoints.find(([, endpoint]) => props(endpoint).VpcEndpointType === 'Gateway');
    expect(gateway).toBeDefined();
    if (gateway === undefined) {
      throw new Error('missing-s3-gateway-endpoint');
    }
    const gatewayProps = props(gateway[1]);
    expect(JSON.stringify(gatewayProps.ServiceName)).toContain('.s3');
    const routeTableIds = gatewayProps.RouteTableIds;
    expect(Array.isArray(routeTableIds) ? routeTableIds : []).toHaveLength(2);
    expect(JSON.stringify(routeTableIds)).toContain('privateapp');
    expect(JSON.stringify(routeTableIds)).not.toMatch(
      /publicingress|privateendpoints|isolateddata/,
    );
    expect(JSON.stringify(endpoints)).not.toContain('.dynamodb');
  });

  test('creates interface endpoints only for the production-core profile', () => {
    const { resources } = renderNetwork(config);
    const interfaces = resourcesOfType(resources, 'AWS::EC2::VPCEndpoint').filter(
      ([, endpoint]) => props(endpoint).VpcEndpointType === 'Interface',
    );
    expect(interfaces).toHaveLength(config.environmentName === 'production' ? 4 : 0);
  });

  test('uses private DNS, endpoint subnets and Endpoint SG for every production interface', () => {
    const { resources } = renderNetwork(config);
    const interfaces = resourcesOfType(resources, 'AWS::EC2::VPCEndpoint').filter(
      ([, endpoint]) => props(endpoint).VpcEndpointType === 'Interface',
    );
    if (config.environmentName === 'staging') {
      expect(interfaces).toEqual([]);
      return;
    }

    const endpointSecurityGroupId = logicalIdStartingWith(resources, 'EndpointSecurityGroup');
    const services = interfaces.map(([, endpoint]) => String(props(endpoint).ServiceName)).sort();
    expect(services).toEqual(
      [
        'com.amazonaws.mx-central-1.ecr.api',
        'com.amazonaws.mx-central-1.ecr.dkr',
        'com.amazonaws.mx-central-1.logs',
        'com.amazonaws.mx-central-1.secretsmanager',
      ].sort(),
    );
    for (const [, endpoint] of interfaces) {
      const endpointProps = props(endpoint);
      expect(endpointProps.PrivateDnsEnabled).toBe(true);
      expect(JSON.stringify(endpointProps.SubnetIds)).toMatch(/privateendpointsSubnet1/);
      expect(JSON.stringify(endpointProps.SubnetIds)).toMatch(/privateendpointsSubnet2/);
      expect(JSON.stringify(endpointProps.SecurityGroupIds)).toContain(endpointSecurityGroupId);
    }
  });

  test('creates exactly five base Security Groups with no inline default egress', () => {
    const { resources } = renderNetwork(config);
    const securityGroups = resourcesOfType(resources, 'AWS::EC2::SecurityGroup');
    expect(securityGroups).toHaveLength(5);
    expect(
      securityGroups.every(([, group]) => {
        const inlineEgress = props(group).SecurityGroupEgress;
        return (
          !Array.isArray(inlineEgress) ||
          inlineEgress.every(
            (rule) =>
              typeof rule !== 'object' ||
              rule === null ||
              (rule as Record<string, unknown>).CidrIp !== '0.0.0.0/0',
          )
        );
      }),
    ).toBe(true);
    expect(securityGroups.map(([id]) => id)).toEqual(
      expect.arrayContaining([
        expect.stringMatching(/^AlbIngressSecurityGroup/),
        expect.stringMatching(/^ApplicationSecurityGroup/),
        expect.stringMatching(/^DatabaseSecurityGroup/),
        expect.stringMatching(/^SessionSecurityGroup/),
        expect.stringMatching(/^EndpointSecurityGroup/),
      ]),
    );
  });

  test('allows ALB to Application only on the contracted port 8080', () => {
    const { resources } = renderNetwork(config);
    const appId = logicalIdStartingWith(resources, 'ApplicationSecurityGroup');
    const albId = logicalIdStartingWith(resources, 'AlbIngressSecurityGroup');
    const albRule = securityGroupIngress(resources, appId).find(
      (rule) =>
        props(rule).FromPort === 8080 &&
        props(rule).ToPort === 8080 &&
        logicalIdFromGetAtt(props(rule).SourceSecurityGroupId) === albId,
    );
    expect(albRule).toBeDefined();
  });

  test.each([
    ['DatabaseSecurityGroup', 3306],
    ['SessionSecurityGroup', 6379],
    ['EndpointSecurityGroup', 443],
  ] as const)('allows Application to %s only on port %i', (targetPrefix, port) => {
    const { resources } = renderNetwork(config);
    const appId = logicalIdStartingWith(resources, 'ApplicationSecurityGroup');
    const targetId = logicalIdStartingWith(resources, targetPrefix);
    const ingress = securityGroupIngress(resources, targetId);
    expect(ingress).toHaveLength(1);
    const rule = first(ingress, `${targetPrefix}-ingress`);
    expect(props(rule)).toMatchObject({ FromPort: port, ToPort: port, IpProtocol: 'tcp' });
    expect(logicalIdFromGetAtt(props(rule).SourceSecurityGroupId)).toBe(appId);
  });

  test('limits Application egress to SG dependencies, HTTPS and VPC DNS', () => {
    const { resources } = renderNetwork(config);
    const appId = logicalIdStartingWith(resources, 'ApplicationSecurityGroup');
    const egress = securityGroupEgress(resources, appId);
    expect(egress).toHaveLength(6);
    expect(egress.map((rule) => props(rule))).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ CidrIp: '0.0.0.0/0', FromPort: 443, ToPort: 443 }),
        expect.objectContaining({ CidrIp: config.vpcCidr, IpProtocol: 'tcp', FromPort: 53 }),
        expect.objectContaining({ CidrIp: config.vpcCidr, IpProtocol: 'udp', FromPort: 53 }),
      ]),
    );
  });

  test('has no SSH or public ingress on any Security Group', () => {
    const { resources } = renderNetwork(config);
    const ingress = resourcesOfType(resources, 'AWS::EC2::SecurityGroupIngress').map(
      ([, resource]) => props(resource),
    );
    expect(ingress.some((rule) => rule.CidrIp === '0.0.0.0/0' || rule.CidrIpv6 === '::/0')).toBe(
      false,
    );
    expect(
      ingress.some(
        (rule) =>
          typeof rule.FromPort === 'number' &&
          typeof rule.ToPort === 'number' &&
          rule.FromPort <= 22 &&
          rule.ToPort >= 22,
      ),
    ).toBe(false);
  });

  test('creates VPC Flow Logs ALL to a stable CloudWatch Log Group', () => {
    const { resources } = renderNetwork(config);
    const flowLogs = resourcesOfType(resources, 'AWS::EC2::FlowLog');
    expect(flowLogs).toHaveLength(1);
    const flowLogProps = props(first(flowLogs, 'flow-log')[1]);
    expect(flowLogProps).toMatchObject({
      TrafficType: 'ALL',
      LogDestinationType: 'cloud-watch-logs',
      MaxAggregationInterval: 60,
      ResourceType: 'VPC',
    });
    for (const field of [
      'version',
      'interface-id',
      'srcaddr',
      'dstaddr',
      'srcport',
      'dstport',
      'protocol',
      'packets',
      'bytes',
      'start',
      'end',
      'action',
      'log-status',
      'subnet-id',
      'region',
      'az-id',
      'flow-direction',
      'traffic-path',
    ]) {
      expect(flowLogProps.LogFormat).toContain(`\${${field}}`);
    }

    const logGroups = resourcesOfType(resources, 'AWS::Logs::LogGroup');
    expect(logGroups).toHaveLength(1);
    const logGroup = first(logGroups, 'flow-log-group')[1];
    expect(props(logGroup)).toMatchObject({
      LogGroupName: `mxmed-${config.environmentCode}-network-flow-logs`,
      RetentionInDays: config.flowLogRetentionDays,
    });
    expect(logGroup.DeletionPolicy).toBe(
      config.environmentName === 'production' ? 'Retain' : 'Delete',
    );
  });

  test('uses a scoped VPC Flow Logs delivery role', () => {
    const { resources } = renderNetwork(config);
    const roles = resourcesOfType(resources, 'AWS::IAM::Role');
    const policies = resourcesOfType(resources, 'AWS::IAM::Policy');
    expect(roles).toHaveLength(1);
    expect(policies).toHaveLength(1);
    expect(
      JSON.stringify(props(first(roles, 'flow-log-role')[1]).AssumeRolePolicyDocument),
    ).toContain('vpc-flow-logs.amazonaws.com');
    const policy = JSON.stringify(props(first(policies, 'flow-log-policy')[1]).PolicyDocument);
    expect(policy).toContain('logs:CreateLogStream');
    expect(policy).toContain('logs:PutLogEvents');
    expect(policy).toContain('VpcFlowLogGroup');
    expect(policy).not.toContain('"Resource":"*"');
  });

  test('contains no future workload, secret value or sensitive output', () => {
    const { resources, outputs } = renderNetwork(config);
    const resourceTypes = Object.values(resources).map((resource) => resource.Type);
    expect(resourceTypes).not.toEqual(
      expect.arrayContaining([
        'AWS::ECS::Cluster',
        'AWS::ECS::Service',
        'AWS::RDS::DBInstance',
        'AWS::ElastiCache::ReplicationGroup',
        'AWS::ElasticLoadBalancingV2::LoadBalancer',
        'AWS::CloudFront::Distribution',
        'AWS::WAFv2::WebACL',
        'AWS::SecretsManager::Secret',
      ]),
    );
    expect(Object.keys(outputs)).toHaveLength(4);
    expect(Object.keys(outputs).every((key) => key.startsWith('ExportsOutput'))).toBe(true);
    expect(JSON.stringify(outputs)).not.toMatch(/password|secret|account/i);
    expect(JSON.stringify(resources)).not.toMatch(
      /AKIA|ASIA|arn:aws:[^$]|sk_(?:live|test)|\b\d{12}\b|BEGIN PRIVATE KEY/,
    );
  });
});

describe('network implementation source and guardrails', () => {
  test('uses official endpoint constants and contains no hardcoded service name or lookup', () => {
    const source = readFileSync(
      join(process.cwd(), 'lib', 'stacks', 'mxmed-network-stack.ts'),
      'utf8',
    );
    expect(source).toContain('InterfaceVpcEndpointAwsService.ECR');
    expect(source).toContain('InterfaceVpcEndpointAwsService.ECR_DOCKER');
    expect(source).toContain('InterfaceVpcEndpointAwsService.CLOUDWATCH_LOGS');
    expect(source).toContain('InterfaceVpcEndpointAwsService.SECRETS_MANAGER');
    expect(source).not.toMatch(/com\.amazonaws\.|fromLookup|ContextProvider/);
  });

  test('blocks a VPC whose DNS support is disabled', () => {
    const { app, stage } = createNetwork(STAGING_CONFIG);
    const cfnVpc = stage.networkStack.vpc.node.defaultChild;
    expect(cfnVpc).toBeInstanceOf(CfnVPC);
    (cfnVpc as CfnVPC).enableDnsSupport = false;
    expect(() => app.synth()).toThrow('MXMED_NETWORK_VPC_CONFIGURATION_INVALID');
  });

  test('blocks Flow Logs that do not capture ALL traffic', () => {
    const { app, stage } = createNetwork(PRODUCTION_CONFIG);
    const flowLog = stage.networkStack.node
      .findAll()
      .find((node): node is CfnFlowLog => node instanceof CfnFlowLog);
    expect(flowLog).toBeDefined();
    if (flowLog === undefined) {
      throw new Error('missing-flow-log');
    }
    flowLog.trafficType = 'REJECT';
    expect(() => app.synth()).toThrow('MXMED_NETWORK_FLOW_LOG_TRAFFIC_INVALID');
  });
});
