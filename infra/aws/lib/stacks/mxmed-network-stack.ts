import { RemovalPolicy } from 'aws-cdk-lib';
import {
  CfnSubnet,
  FlowLog,
  FlowLogDestination,
  FlowLogMaxAggregationInterval,
  FlowLogResourceType,
  FlowLogTrafficType,
  GatewayVpcEndpointAwsService,
  InterfaceVpcEndpointAwsService,
  IpAddresses,
  IpProtocol,
  LogFormat,
  Peer,
  Port,
  SecurityGroup,
  SubnetType,
  Vpc,
} from 'aws-cdk-lib/aws-ec2';
import type { ISubnet, IVpc, SubnetSelection } from 'aws-cdk-lib/aws-ec2';
import { LogGroup, RetentionDays } from 'aws-cdk-lib/aws-logs';
import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import { mxmedNatGatewayCount } from '../config/environment-config';
import type { MxMedEnvironmentName } from '../config/environment-config';
import { registerMxMedNetworkGuardrails } from '../utils/network-guardrails';
import { mxmedName } from '../utils/naming';

const APPLICATION_PORT = 8080;

const SUBNET_GROUPS = Object.freeze({
  publicIngress: 'public-ingress',
  privateApp: 'private-app',
  privateEndpoints: 'private-endpoints',
  isolatedData: 'isolated-data',
} as const);

interface MxMedSubnetCidrs {
  readonly publicIngress: readonly [string, string];
  readonly privateApp: readonly [string, string];
  readonly privateEndpoints: readonly [string, string];
  readonly isolatedData: readonly [string, string];
}

const SUBNET_CIDRS: Readonly<Record<MxMedEnvironmentName, MxMedSubnetCidrs>> = Object.freeze({
  staging: {
    publicIngress: ['10.20.16.0/24', '10.20.48.0/24'],
    privateApp: ['10.20.0.0/20', '10.20.32.0/20'],
    privateEndpoints: ['10.20.17.0/24', '10.20.49.0/24'],
    isolatedData: ['10.20.18.0/24', '10.20.50.0/24'],
  },
  production: {
    publicIngress: ['10.30.16.0/24', '10.30.48.0/24'],
    privateApp: ['10.30.0.0/20', '10.30.32.0/20'],
    privateEndpoints: ['10.30.17.0/24', '10.30.49.0/24'],
    isolatedData: ['10.30.18.0/24', '10.30.50.0/24'],
  },
});

const FLOW_LOG_FORMAT = [
  LogFormat.VERSION,
  LogFormat.INTERFACE_ID,
  LogFormat.SRC_ADDR,
  LogFormat.DST_ADDR,
  LogFormat.SRC_PORT,
  LogFormat.DST_PORT,
  LogFormat.PROTOCOL,
  LogFormat.PACKETS,
  LogFormat.BYTES,
  LogFormat.START_TIMESTAMP,
  LogFormat.END_TIMESTAMP,
  LogFormat.ACTION,
  LogFormat.LOG_STATUS,
  LogFormat.SUBNET_ID,
  LogFormat.REGION,
  LogFormat.AZ_ID,
  LogFormat.FLOW_DIRECTION,
  LogFormat.TRAFFIC_PATH,
] as const;

function selection(groupName: string): SubnetSelection {
  return { subnetGroupName: groupName };
}

function flowLogRetention(days: number): RetentionDays {
  return days === 30 ? RetentionDays.ONE_MONTH : RetentionDays.THREE_MONTHS;
}

function applyExactSubnetCidrs(vpc: Vpc, environmentName: MxMedEnvironmentName): void {
  const cidrs = SUBNET_CIDRS[environmentName];
  const groups = [
    [SUBNET_GROUPS.publicIngress, cidrs.publicIngress],
    [SUBNET_GROUPS.privateApp, cidrs.privateApp],
    [SUBNET_GROUPS.privateEndpoints, cidrs.privateEndpoints],
    [SUBNET_GROUPS.isolatedData, cidrs.isolatedData],
  ] as const;

  for (const [groupName, groupCidrs] of groups) {
    const subnets = vpc.selectSubnets(selection(groupName)).subnets;
    if (subnets.length !== groupCidrs.length) {
      throw new Error('MXMED_NETWORK_SUBNET_COUNT_INVALID');
    }

    subnets.forEach((subnet, index) => {
      const cfnSubnet = subnet.node.defaultChild;
      const cidr = groupCidrs[index];
      if (!(cfnSubnet instanceof CfnSubnet) || cidr === undefined) {
        throw new Error('MXMED_NETWORK_SUBNET_ESCAPE_HATCH_INVALID');
      }
      // Vpc L2 does not expose exact per-AZ CIDRs. PP251 permits this narrow,
      // asserted escape hatch while L2 continues to own routes and NAT.
      cfnSubnet.cidrBlock = cidr;
    });
  }
}

/** VPC, routing, endpoints, base security groups and VPC Flow Logs for one environment. */
export class MxMedNetworkStack extends BaseMxMedStack {
  public readonly vpc: Vpc;
  public readonly publicIngressSubnets: readonly ISubnet[];
  public readonly privateAppSubnets: readonly ISubnet[];
  public readonly privateEndpointSubnets: readonly ISubnet[];
  public readonly isolatedDataSubnets: readonly ISubnet[];
  public readonly albIngressSecurityGroup: SecurityGroup;
  public readonly applicationSecurityGroup: SecurityGroup;
  public readonly databaseSecurityGroup: SecurityGroup;
  public readonly sessionSecurityGroup: SecurityGroup;
  public readonly endpointSecurityGroup: SecurityGroup;
  public readonly flowLogGroup: LogGroup;

  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'network',
      description: 'MXMed V1 network: VPC, routing, endpoints, security groups and flow logs.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'not-required' },
    });

    const { config } = props;
    this.vpc = new Vpc(this, 'Vpc', {
      ipAddresses: IpAddresses.cidr(config.vpcCidr),
      ipProtocol: IpProtocol.IPV4_ONLY,
      maxAzs: config.availabilityZoneCount,
      natGateways: mxmedNatGatewayCount(config.natStrategy),
      enableDnsSupport: true,
      enableDnsHostnames: true,
      subnetConfiguration: [
        {
          name: SUBNET_GROUPS.publicIngress,
          subnetType: SubnetType.PUBLIC,
          cidrMask: config.subnetMasks.publicIngress,
          mapPublicIpOnLaunch: false,
        },
        {
          name: SUBNET_GROUPS.privateApp,
          subnetType: SubnetType.PRIVATE_WITH_EGRESS,
          cidrMask: config.subnetMasks.privateApp,
        },
        {
          name: SUBNET_GROUPS.privateEndpoints,
          subnetType: SubnetType.PRIVATE_ISOLATED,
          cidrMask: config.subnetMasks.privateEndpoints,
        },
        {
          name: SUBNET_GROUPS.isolatedData,
          subnetType: SubnetType.PRIVATE_ISOLATED,
          cidrMask: config.subnetMasks.isolatedData,
        },
      ],
    });
    applyExactSubnetCidrs(this.vpc, config.environmentName);

    this.publicIngressSubnets = Object.freeze(
      this.vpc.selectSubnets(selection(SUBNET_GROUPS.publicIngress)).subnets,
    );
    this.privateAppSubnets = Object.freeze(
      this.vpc.selectSubnets(selection(SUBNET_GROUPS.privateApp)).subnets,
    );
    this.privateEndpointSubnets = Object.freeze(
      this.vpc.selectSubnets(selection(SUBNET_GROUPS.privateEndpoints)).subnets,
    );
    this.isolatedDataSubnets = Object.freeze(
      this.vpc.selectSubnets(selection(SUBNET_GROUPS.isolatedData)).subnets,
    );

    this.albIngressSecurityGroup = this.createSecurityGroup(
      'AlbIngressSecurityGroup',
      'MXMed ALB ingress security group; CloudFront ingress is added by Edge.',
    );
    this.applicationSecurityGroup = this.createSecurityGroup(
      'ApplicationSecurityGroup',
      'MXMed ECS application tasks security group.',
    );
    this.databaseSecurityGroup = this.createSecurityGroup(
      'DatabaseSecurityGroup',
      'MXMed database security group; no database resource exists in this phase.',
    );
    this.sessionSecurityGroup = this.createSecurityGroup(
      'SessionSecurityGroup',
      'MXMed session cache security group; no cache resource exists in this phase.',
    );
    this.endpointSecurityGroup = this.createSecurityGroup(
      'EndpointSecurityGroup',
      'MXMed production interface endpoint security group.',
    );
    this.addSecurityGroupRules(config.vpcCidr);

    this.vpc.addGatewayEndpoint('S3GatewayEndpoint', {
      service: GatewayVpcEndpointAwsService.S3,
      subnets: [selection(SUBNET_GROUPS.privateApp)],
    });

    if (config.interfaceEndpointProfile === 'production-core') {
      const endpointProps = {
        subnets: selection(SUBNET_GROUPS.privateEndpoints),
        securityGroups: [this.endpointSecurityGroup],
        privateDnsEnabled: true,
        open: false,
      };
      this.vpc.addInterfaceEndpoint('EcrApiEndpoint', {
        ...endpointProps,
        service: InterfaceVpcEndpointAwsService.ECR,
      });
      this.vpc.addInterfaceEndpoint('EcrDockerEndpoint', {
        ...endpointProps,
        service: InterfaceVpcEndpointAwsService.ECR_DOCKER,
      });
      this.vpc.addInterfaceEndpoint('CloudWatchLogsEndpoint', {
        ...endpointProps,
        service: InterfaceVpcEndpointAwsService.CLOUDWATCH_LOGS,
      });
      this.vpc.addInterfaceEndpoint('SecretsManagerEndpoint', {
        ...endpointProps,
        service: InterfaceVpcEndpointAwsService.SECRETS_MANAGER,
      });
    }

    this.flowLogGroup = new LogGroup(this, 'VpcFlowLogGroup', {
      logGroupName: mxmedName(config.environmentCode, 'network-flow-logs'),
      retention: flowLogRetention(config.flowLogRetentionDays),
      removalPolicy:
        config.environmentName === 'production' ? RemovalPolicy.RETAIN : RemovalPolicy.DESTROY,
    });
    new FlowLog(this, 'VpcFlowLog', {
      flowLogName: mxmedName(config.environmentCode, 'vpc-flow-log'),
      resourceType: FlowLogResourceType.fromVpc(this.vpc as unknown as IVpc),
      destination: FlowLogDestination.toCloudWatchLogs(this.flowLogGroup),
      trafficType: FlowLogTrafficType.ALL,
      maxAggregationInterval: FlowLogMaxAggregationInterval.ONE_MINUTE,
      logFormat: [...FLOW_LOG_FORMAT],
    });

    registerMxMedNetworkGuardrails(this, config);
  }

  private createSecurityGroup(id: string, description: string): SecurityGroup {
    return new SecurityGroup(this, id, {
      // CDK 2.260.0 models Vpc/IVpc incompatibly with exactOptionalPropertyTypes.
      vpc: this.vpc as unknown as IVpc,
      description,
      allowAllOutbound: false,
      allowAllIpv6Outbound: false,
      disableInlineRules: true,
    });
  }

  private addSecurityGroupRules(vpcCidr: string): void {
    const applicationPort = Port.tcp(APPLICATION_PORT);
    const databasePort = Port.tcp(3306);
    const sessionPort = Port.tcp(6379);
    const httpsPort = Port.tcp(443);

    this.applicationSecurityGroup.addIngressRule(
      this.albIngressSecurityGroup,
      applicationPort,
      'Allow the contracted ALB to application port.',
    );
    this.albIngressSecurityGroup.addEgressRule(
      this.applicationSecurityGroup,
      applicationPort,
      'Reach application tasks only on the contracted port.',
    );

    this.databaseSecurityGroup.addIngressRule(
      this.applicationSecurityGroup,
      databasePort,
      'Allow MySQL only from application tasks.',
    );
    this.applicationSecurityGroup.addEgressRule(
      this.databaseSecurityGroup,
      databasePort,
      'Reach the future database security group.',
    );

    this.sessionSecurityGroup.addIngressRule(
      this.applicationSecurityGroup,
      sessionPort,
      'Allow TLS session cache traffic only from application tasks.',
    );
    this.applicationSecurityGroup.addEgressRule(
      this.sessionSecurityGroup,
      sessionPort,
      'Reach the future session cache security group.',
    );

    this.endpointSecurityGroup.addIngressRule(
      this.applicationSecurityGroup,
      httpsPort,
      'Allow HTTPS only from application tasks.',
    );
    this.applicationSecurityGroup.addEgressRule(
      this.endpointSecurityGroup,
      httpsPort,
      'Reach private interface endpoints over HTTPS.',
    );

    this.applicationSecurityGroup.addEgressRule(
      Peer.anyIpv4(),
      httpsPort,
      'Allow contracted external HTTPS APIs through NAT.',
    );
    this.applicationSecurityGroup.addEgressRule(
      Peer.ipv4(vpcCidr),
      Port.tcp(53),
      'Allow TCP DNS resolution inside the VPC.',
    );
    this.applicationSecurityGroup.addEgressRule(
      Peer.ipv4(vpcCidr),
      Port.udp(53),
      'Allow UDP DNS resolution inside the VPC.',
    );
  }
}
