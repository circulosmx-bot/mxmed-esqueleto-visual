import { Token, type IAspect } from 'aws-cdk-lib';
import { CfnDistribution } from 'aws-cdk-lib/aws-cloudfront';
import { CfnSecurityGroupIngress } from 'aws-cdk-lib/aws-ec2';
import {
  CfnListener,
  CfnLoadBalancer,
  CfnTargetGroup,
} from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import { CfnWebACL } from 'aws-cdk-lib/aws-wafv2';
import type { IConstruct } from 'constructs';

function reject(condition: boolean, code: string): void {
  if (condition) throw new Error(`MXMED_EDGE_GUARDRAIL:${code}`);
}

/** Fail-closed checks for the directly inspectable Edge resource contract. */
export class EdgeFoundationAspect implements IAspect {
  public visit(node: IConstruct): void {
    if (
      node instanceof CfnSecurityGroupIngress &&
      node.node.path.includes('RegionalEdgeFoundation')
    ) {
      reject(node.cidrIp !== undefined || node.cidrIpv6 !== undefined, 'PUBLIC_CIDR');
      reject(node.fromPort !== 443 || node.toPort !== 443, 'INGRESS_NOT_HTTPS');
      reject(node.sourcePrefixListId === undefined, 'PREFIX_LIST_MISSING');
    }
    if (node instanceof CfnLoadBalancer) {
      reject(node.scheme !== 'internet-facing', 'ALB_SCHEME');
      reject(node.ipAddressType !== 'ipv4', 'ALB_IPV4');
      const attributes = node.loadBalancerAttributes;
      reject(
        Array.isArray(attributes) &&
          attributes.some(
            (attribute) => 'key' in attribute && attribute.key.startsWith('access_logs.'),
          ),
        'ALB_ACCESS_LOGS',
      );
    }
    if (node instanceof CfnListener) {
      reject(node.port !== 443 || node.protocol !== 'HTTPS', 'LISTENER_NOT_HTTPS_443');
    }
    if (node instanceof CfnTargetGroup) {
      reject(
        node.targetType !== undefined &&
          !Token.isUnresolved(node.targetType) &&
          node.targetType !== 'ip',
        'TARGET_TYPE',
      );
      reject(node.port !== 8080, 'TARGET_PORT');
      reject(
        !Token.isUnresolved(node.healthCheckPath) && node.healthCheckPath !== '/readyz',
        'TARGET_READINESS_PATH',
      );
    }
    if (node instanceof CfnDistribution) {
      const config = node.distributionConfig as CfnDistribution.DistributionConfigProperty;
      reject(config.ipv6Enabled !== false, 'IPV6_ENABLED');
      reject(config.logging !== undefined, 'CLOUDFRONT_REQUEST_LOGS');
      reject(
        Array.isArray(config.cacheBehaviors) && config.cacheBehaviors.length > 2,
        'BEHAVIOR_LIMIT',
      );
    }
    if (node instanceof CfnWebACL) {
      reject(node.scope !== 'CLOUDFRONT', 'WAF_SCOPE');
      reject(!Array.isArray(node.rules) || node.rules.length !== 5, 'WAF_RULE_COUNT');
      reject(
        Array.isArray(node.rules) &&
          node.rules.some(
            (rule) =>
              'visibilityConfig' in rule &&
              (rule.visibilityConfig as CfnWebACL.VisibilityConfigProperty).sampledRequestsEnabled,
          ),
        'WAF_SAMPLING',
      );
    }
  }
}
