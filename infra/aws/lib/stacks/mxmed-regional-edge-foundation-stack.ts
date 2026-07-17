import { CfnOutput, CfnParameter, Duration, Tags } from 'aws-cdk-lib';
import {
  CfnSecurityGroupIngress,
  type ISecurityGroup,
  type ISubnet,
  type IVpc,
  type Vpc,
} from 'aws-cdk-lib/aws-ec2';
import { Certificate } from 'aws-cdk-lib/aws-certificatemanager';
import {
  ApplicationLoadBalancer,
  ApplicationProtocol,
  ApplicationTargetGroup,
  IpAddressType,
  ListenerAction,
  ListenerCondition,
  SslPolicy,
  TargetType,
  Protocol,
} from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

export interface MxMedRegionalEdgeFoundationStackProps extends MxMedContractStackProps {
  readonly vpc: Vpc;
  readonly publicIngressSubnets: readonly ISubnet[];
  readonly albIngressSecurityGroup: ISecurityGroup;
  readonly applicationSecurityGroup: ISecurityGroup;
}

/** Regional, CloudFront-restricted application origin. It never references Compute. */
export class MxMedRegionalEdgeFoundationStack extends BaseMxMedStack {
  public readonly applicationLoadBalancer: ApplicationLoadBalancer;
  public readonly applicationTargetGroup: ApplicationTargetGroup;
  public readonly originDomainNameParameter: CfnParameter;
  public readonly originCertificateArnParameter: CfnParameter;
  public readonly originVerificationHeaderNameParameter: CfnParameter;
  public readonly originVerificationHeaderValueParameter: CfnParameter;

  public constructor(scope: Construct, id: string, props: MxMedRegionalEdgeFoundationStackProps) {
    super(scope, id, {
      ...props,
      component: 'edge-regional',
      description: 'MXMed regional ALB origin restricted to CloudFront; deployment is external.',
      metadata: { dataClassification: 'public', criticality: 'high', backup: 'not-required' },
    });

    const { config } = props;
    this.originDomainNameParameter = new CfnParameter(this, 'EdgeOriginDomainName', {
      type: 'String',
      allowedPattern: '^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$',
      description: 'Approved lowercase regional origin DNS name; no value is versioned.',
    });
    this.originCertificateArnParameter = new CfnParameter(this, 'EdgeOriginCertificateArn', {
      type: 'String',
      allowedPattern: '^arn:[^:]+:acm:mx-central-1:[0-9]{12}:certificate/[0-9a-f-]+$',
      description: 'ARN of the externally issued mx-central-1 origin certificate.',
    });
    const prefixList = new CfnParameter(this, 'CloudFrontOriginFacingPrefixListId', {
      type: 'String',
      allowedPattern: '^pl-[0-9a-fA-F]+$',
      description: 'AWS-managed CloudFront origin-facing prefix list ID for mx-central-1.',
    });
    this.originVerificationHeaderNameParameter = new CfnParameter(
      this,
      'EdgeOriginVerificationHeaderName',
      {
        type: 'String',
        noEcho: true,
        minLength: 1,
        allowedPattern: '^[A-Za-z][A-Za-z0-9-]{0,63}$',
        description: 'Secret custom origin verification header name.',
      },
    );
    this.originVerificationHeaderValueParameter = new CfnParameter(
      this,
      'EdgeOriginVerificationHeaderValue',
      {
        type: 'String',
        noEcho: true,
        minLength: 32,
        allowedPattern: '^[A-Za-z0-9._~+/=-]{32,256}$',
        description: 'Secret custom origin verification header value.',
      },
    );

    new CfnSecurityGroupIngress(this, 'CloudFrontHttpsIngress', {
      groupId: props.albIngressSecurityGroup.securityGroupId,
      ipProtocol: 'tcp',
      fromPort: 443,
      toPort: 443,
      sourcePrefixListId: prefixList.valueAsString,
      description: 'HTTPS from the AWS-managed CloudFront origin-facing prefix list only.',
    });

    this.applicationLoadBalancer = new ApplicationLoadBalancer(this, 'ApplicationLoadBalancer', {
      vpc: props.vpc as unknown as IVpc,
      internetFacing: true,
      vpcSubnets: { subnets: [...props.publicIngressSubnets] },
      securityGroup: props.albIngressSecurityGroup,
      ipAddressType: IpAddressType.IPV4,
      http2Enabled: true,
      dropInvalidHeaderFields: true,
      idleTimeout: Duration.seconds(60),
      deletionProtection:
        config.environmentName === 'production' &&
        config.edgeActivationMode === 'public-traffic-enabled-v1',
    });
    this.applicationLoadBalancer.setAttribute('routing.http.desync_mitigation_mode', 'strictest');

    this.applicationTargetGroup = new ApplicationTargetGroup(this, 'ApplicationTargetGroup', {
      vpc: props.vpc as unknown as IVpc,
      protocol: ApplicationProtocol.HTTP,
      port: config.computeContainerPort,
      targetType: TargetType.IP,
      deregistrationDelay: Duration.seconds(30),
      healthCheck: {
        enabled: true,
        protocol: Protocol.HTTP,
        path: config.computeReadinessPath,
        healthyHttpCodes: '200',
        interval: Duration.seconds(30),
        timeout: Duration.seconds(5),
        healthyThresholdCount: 2,
        unhealthyThresholdCount: 3,
      },
    });
    this.applicationTargetGroup.setAttribute('stickiness.enabled', 'false');
    this.applicationTargetGroup.setAttribute('slow_start.duration_seconds', '0');

    const certificate = Certificate.fromCertificateArn(
      this,
      'OriginCertificate',
      this.originCertificateArnParameter.valueAsString,
    );
    const listener = this.applicationLoadBalancer.addListener('HttpsListener', {
      port: 443,
      protocol: ApplicationProtocol.HTTPS,
      open: false,
      certificates: [certificate],
      sslPolicy: SslPolicy.FORWARD_SECRECY_TLS12_RES_GCM,
      defaultAction: ListenerAction.fixedResponse(403, {
        contentType: 'text/plain',
        messageBody: 'Access denied',
      }),
    });
    listener.addTargetGroups('VerifiedCloudFrontOriginRule', {
      priority: 10,
      targetGroups: [this.applicationTargetGroup],
      conditions: [
        ListenerCondition.hostHeaders([this.originDomainNameParameter.valueAsString]),
        ListenerCondition.httpHeader(this.originVerificationHeaderNameParameter.valueAsString, [
          this.originVerificationHeaderValueParameter.valueAsString,
        ]),
      ],
    });

    const tagOptions = { priority: 400 };
    for (const resource of [this.applicationLoadBalancer, this.applicationTargetGroup]) {
      Tags.of(resource).add('EdgeActivationMode', config.edgeActivationMode, tagOptions);
      Tags.of(resource).add('EdgePricingProfile', config.edgePricingProfile, tagOptions);
      Tags.of(resource).add('CostReview', 'required', tagOptions);
      Tags.of(resource).add('CostTier', 'fixed-edge', tagOptions);
    }

    new CfnOutput(this, 'LoadBalancerDnsName', {
      value: this.applicationLoadBalancer.loadBalancerDnsName,
    });
    new CfnOutput(this, 'TargetGroupArn', { value: this.applicationTargetGroup.targetGroupArn });
    new CfnOutput(this, 'RegionalCertificateArn', {
      value: this.originCertificateArnParameter.valueAsString,
    });
    new CfnOutput(this, 'OriginDomainName', {
      value: this.originDomainNameParameter.valueAsString,
    });
  }
}
