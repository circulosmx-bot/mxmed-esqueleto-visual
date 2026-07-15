import { CfnResource } from 'aws-cdk-lib';
import {
  CfnFlowLog,
  CfnNatGateway,
  CfnRoute,
  CfnSecurityGroup,
  CfnSecurityGroupIngress,
  CfnSubnet,
  CfnVPC,
  CfnVPCCidrBlock,
  CfnVPCEndpoint,
} from 'aws-cdk-lib/aws-ec2';
import { CfnLogGroup } from 'aws-cdk-lib/aws-logs';
import type { IConstruct } from 'constructs';

import { mxmedNatGatewayCount } from '../config/environment-config';
import type { MxMedEnvironmentConfig, MxMedEnvironmentName } from '../config/environment-config';

const EXPECTED_SUBNET_CIDRS: Readonly<
  Record<MxMedEnvironmentName, Readonly<Record<string, readonly [string, string]>>>
> = Object.freeze({
  staging: {
    publicingress: ['10.20.16.0/24', '10.20.48.0/24'],
    privateapp: ['10.20.0.0/20', '10.20.32.0/20'],
    privateendpoints: ['10.20.17.0/24', '10.20.49.0/24'],
    isolateddata: ['10.20.18.0/24', '10.20.50.0/24'],
  },
  production: {
    publicingress: ['10.30.16.0/24', '10.30.48.0/24'],
    privateapp: ['10.30.0.0/20', '10.30.32.0/20'],
    privateendpoints: ['10.30.17.0/24', '10.30.49.0/24'],
    isolateddata: ['10.30.18.0/24', '10.30.50.0/24'],
  },
});

const FORBIDDEN_RESOURCE_TYPES = new Set([
  'AWS::EC2::CustomerGateway',
  'AWS::EC2::EgressOnlyInternetGateway',
  'AWS::EC2::NetworkAcl',
  'AWS::EC2::NetworkAclEntry',
  'AWS::EC2::SubnetNetworkAclAssociation',
  'AWS::EC2::TransitGateway',
  'AWS::EC2::VPCPeeringConnection',
  'AWS::EC2::VPNGateway',
]);

const REQUIRED_FLOW_LOG_FIELDS = [
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
] as const;

function normalizedPath(node: IConstruct): string {
  return node.node.path.toLowerCase().replaceAll(/[^a-z0-9]/g, '');
}

function pushIf(errors: string[], condition: boolean, code: string): void {
  if (condition) {
    errors.push(code);
  }
}

function matchesSubnetGroup(node: IConstruct, group: string): boolean {
  return normalizedPath(node).includes(group);
}

function isDefaultIpv4Route(route: CfnRoute): boolean {
  return route.destinationCidrBlock === '0.0.0.0/0';
}

function validateSubnetContract(
  errors: string[],
  subnets: CfnSubnet[],
  environmentName: MxMedEnvironmentName,
): void {
  const expectedGroups = EXPECTED_SUBNET_CIDRS[environmentName];
  pushIf(errors, subnets.length !== 8, 'MXMED_NETWORK_SUBNET_COUNT_INVALID');

  for (const [group, expectedCidrs] of Object.entries(expectedGroups)) {
    const groupSubnets = subnets.filter((subnet) => matchesSubnetGroup(subnet, group));
    pushIf(errors, groupSubnets.length !== 2, `MXMED_NETWORK_SUBNET_GROUP_INVALID:${group}`);
    const actualCidrs = groupSubnets.map((subnet) => subnet.cidrBlock).sort();
    pushIf(
      errors,
      actualCidrs.join(',') !== [...expectedCidrs].sort().join(','),
      `MXMED_NETWORK_SUBNET_CIDR_INVALID:${group}`,
    );
    pushIf(
      errors,
      groupSubnets.some(
        (subnet) =>
          subnet.mapPublicIpOnLaunch === true ||
          subnet.assignIpv6AddressOnCreation === true ||
          subnet.ipv6CidrBlock !== undefined ||
          subnet.ipv6Native === true,
      ),
      `MXMED_NETWORK_SUBNET_PUBLIC_OR_IPV6_FORBIDDEN:${group}`,
    );
  }
}

function validateRoutes(errors: string[], routes: CfnRoute[]): void {
  const publicDefaults = routes.filter(
    (route) => matchesSubnetGroup(route, 'publicingress') && isDefaultIpv4Route(route),
  );
  const applicationDefaults = routes.filter(
    (route) => matchesSubnetGroup(route, 'privateapp') && isDefaultIpv4Route(route),
  );
  const isolatedDefaults = routes.filter(
    (route) =>
      (matchesSubnetGroup(route, 'privateendpoints') ||
        matchesSubnetGroup(route, 'isolateddata')) &&
      isDefaultIpv4Route(route),
  );

  pushIf(
    errors,
    publicDefaults.length !== 2 || publicDefaults.some((route) => route.gatewayId === undefined),
    'MXMED_NETWORK_PUBLIC_DEFAULT_ROUTE_INVALID',
  );
  pushIf(
    errors,
    applicationDefaults.length !== 2 ||
      applicationDefaults.some((route) => route.natGatewayId === undefined),
    'MXMED_NETWORK_APPLICATION_NAT_ROUTE_INVALID',
  );
  pushIf(errors, isolatedDefaults.length > 0, 'MXMED_NETWORK_ISOLATED_DEFAULT_ROUTE_FORBIDDEN');
  pushIf(
    errors,
    routes.some((route) => route.destinationIpv6CidrBlock !== undefined),
    'MXMED_NETWORK_IPV6_ROUTE_FORBIDDEN',
  );
}

function validateSecurityGroups(
  errors: string[],
  securityGroups: CfnSecurityGroup[],
  ingressRules: CfnSecurityGroupIngress[],
): void {
  pushIf(errors, securityGroups.length !== 5, 'MXMED_NETWORK_SECURITY_GROUP_COUNT_INVALID');

  for (const rule of ingressRules) {
    const publicIngress =
      rule.cidrIp === '0.0.0.0/0' ||
      rule.cidrIpv6 === '::/0' ||
      (rule.ipProtocol === '-1' && rule.sourceSecurityGroupId === undefined);
    pushIf(errors, publicIngress, 'MXMED_NETWORK_PUBLIC_SECURITY_GROUP_INGRESS_FORBIDDEN');

    const fromPort = typeof rule.fromPort === 'number' ? rule.fromPort : undefined;
    const toPort = typeof rule.toPort === 'number' ? rule.toPort : undefined;
    const includesSsh =
      rule.ipProtocol === '-1' ||
      (fromPort !== undefined && toPort !== undefined && fromPort <= 22 && toPort >= 22);
    pushIf(errors, includesSsh, 'MXMED_NETWORK_SSH_INGRESS_FORBIDDEN');
  }
}

function validateEndpoints(
  errors: string[],
  endpoints: CfnVPCEndpoint[],
  environmentName: MxMedEnvironmentName,
): void {
  const gatewayEndpoints = endpoints.filter((endpoint) => endpoint.vpcEndpointType === 'Gateway');
  const interfaceEndpoints = endpoints.filter(
    (endpoint) => endpoint.vpcEndpointType === 'Interface',
  );
  const gatewayEndpoint = gatewayEndpoints[0];

  pushIf(
    errors,
    gatewayEndpoints.length !== 1 ||
      gatewayEndpoint === undefined ||
      !matchesSubnetGroup(gatewayEndpoint, 's3gatewayendpoint') ||
      !Array.isArray(gatewayEndpoint.routeTableIds) ||
      gatewayEndpoint.routeTableIds.length !== 2,
    'MXMED_NETWORK_S3_GATEWAY_ENDPOINT_INVALID',
  );

  const expectedInterfaceCount = environmentName === 'production' ? 4 : 0;
  pushIf(
    errors,
    interfaceEndpoints.length !== expectedInterfaceCount,
    'MXMED_NETWORK_INTERFACE_ENDPOINT_COUNT_INVALID',
  );

  if (environmentName === 'production') {
    const requiredPaths = [
      'ecrapiendpoint',
      'ecrdockerendpoint',
      'cloudwatchlogsendpoint',
      'secretsmanagerendpoint',
    ];
    for (const requiredPath of requiredPaths) {
      pushIf(
        errors,
        !interfaceEndpoints.some((endpoint) => matchesSubnetGroup(endpoint, requiredPath)),
        `MXMED_NETWORK_INTERFACE_ENDPOINT_MISSING:${requiredPath}`,
      );
    }
    pushIf(
      errors,
      interfaceEndpoints.some(
        (endpoint) =>
          endpoint.privateDnsEnabled !== true ||
          !Array.isArray(endpoint.subnetIds) ||
          endpoint.subnetIds.length !== 2 ||
          !Array.isArray(endpoint.securityGroupIds) ||
          endpoint.securityGroupIds.length !== 1,
      ),
      'MXMED_NETWORK_INTERFACE_ENDPOINT_CONFIGURATION_INVALID',
    );
  }
}

function validateFlowLogs(
  errors: string[],
  flowLogs: CfnFlowLog[],
  logGroups: CfnLogGroup[],
  retentionDays: number,
): void {
  pushIf(errors, flowLogs.length !== 1, 'MXMED_NETWORK_FLOW_LOG_COUNT_INVALID');
  const flowLog = flowLogs[0];
  if (flowLog !== undefined) {
    pushIf(errors, flowLog.trafficType !== 'ALL', 'MXMED_NETWORK_FLOW_LOG_TRAFFIC_INVALID');
    pushIf(
      errors,
      flowLog.logDestinationType !== 'cloud-watch-logs',
      'MXMED_NETWORK_FLOW_LOG_DESTINATION_INVALID',
    );
    const logFormat = typeof flowLog.logFormat === 'string' ? flowLog.logFormat : '';
    pushIf(
      errors,
      REQUIRED_FLOW_LOG_FIELDS.some((field) => !logFormat.includes(`\${${field}}`)),
      'MXMED_NETWORK_FLOW_LOG_FORMAT_INVALID',
    );
  }

  pushIf(
    errors,
    logGroups.length !== 1 || logGroups[0]?.retentionInDays !== retentionDays,
    'MXMED_NETWORK_FLOW_LOG_RETENTION_INVALID',
  );
}

function validateMxMedNetwork(scope: IConstruct, config: MxMedEnvironmentConfig): string[] {
  const resources = scope.node
    .findAll()
    .filter((node): node is CfnResource => node instanceof CfnResource);
  const errors: string[] = [];
  const vpcs = resources.filter((resource): resource is CfnVPC => resource instanceof CfnVPC);
  const subnets = resources.filter(
    (resource): resource is CfnSubnet => resource instanceof CfnSubnet,
  );
  const natGateways = resources.filter(
    (resource): resource is CfnNatGateway => resource instanceof CfnNatGateway,
  );
  const routes = resources.filter((resource): resource is CfnRoute => resource instanceof CfnRoute);
  const securityGroups = resources.filter(
    (resource): resource is CfnSecurityGroup => resource instanceof CfnSecurityGroup,
  );
  const ingressRules = resources.filter(
    (resource): resource is CfnSecurityGroupIngress => resource instanceof CfnSecurityGroupIngress,
  );
  const endpoints = resources.filter(
    (resource): resource is CfnVPCEndpoint => resource instanceof CfnVPCEndpoint,
  );
  const flowLogs = resources.filter(
    (resource): resource is CfnFlowLog => resource instanceof CfnFlowLog,
  );
  const logGroups = resources.filter(
    (resource): resource is CfnLogGroup => resource instanceof CfnLogGroup,
  );
  const vpc = vpcs[0];
  const vpcConfigurationValid =
    vpc?.cidrBlock === config.vpcCidr &&
    vpc.enableDnsSupport === true &&
    vpc.enableDnsHostnames === true;

  pushIf(
    errors,
    vpcs.length !== 1 || !vpcConfigurationValid,
    'MXMED_NETWORK_VPC_CONFIGURATION_INVALID',
  );
  pushIf(
    errors,
    natGateways.length !== mxmedNatGatewayCount(config.natStrategy),
    'MXMED_NETWORK_NAT_COUNT_INVALID',
  );
  pushIf(
    errors,
    resources.some(
      (resource) =>
        resource instanceof CfnVPCCidrBlock ||
        FORBIDDEN_RESOURCE_TYPES.has(resource.cfnResourceType),
    ),
    'MXMED_NETWORK_FORBIDDEN_RESOURCE_PRESENT',
  );

  validateSubnetContract(errors, subnets, config.environmentName);
  validateRoutes(errors, routes);
  validateSecurityGroups(errors, securityGroups, ingressRules);
  validateEndpoints(errors, endpoints, config.environmentName);
  validateFlowLogs(errors, flowLogs, logGroups, config.flowLogRetentionDays);

  return [...new Set(errors)].sort();
}

export function registerMxMedNetworkGuardrails(
  scope: IConstruct,
  config: MxMedEnvironmentConfig,
): void {
  scope.node.addValidation({
    validate: () => validateMxMedNetwork(scope, config),
  });
}
