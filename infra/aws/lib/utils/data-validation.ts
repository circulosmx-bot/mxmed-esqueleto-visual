import { CfnDeletionPolicy, CfnOutput, Stack } from 'aws-cdk-lib';
import type { CfnResource } from 'aws-cdk-lib';
import { CfnRole } from 'aws-cdk-lib/aws-iam';
import {
  CfnDBCluster,
  CfnDBInstance,
  CfnDBParameterGroup,
  CfnDBProxy,
  CfnDBSubnetGroup,
} from 'aws-cdk-lib/aws-rds';
import { CfnSecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';

function children<T extends CfnResource>(
  scope: IConstruct,
  resourceType: new (...args: never[]) => T,
): T[] {
  return scope.node.findAll().filter((node): node is T => node instanceof resourceType);
}

function validateDataFoundation(scope: IConstruct, config: MxMedEnvironmentConfig): string[] {
  const errors: string[] = [];
  const instances = children(scope, CfnDBInstance);
  const parameterGroups = children(scope, CfnDBParameterGroup);
  const subnetGroups = children(scope, CfnDBSubnetGroup);
  const roles = children(scope, CfnRole).filter((role) =>
    role.node.path.toLowerCase().includes('enhancedmonitoringrole'),
  );
  const secrets = children(scope, CfnSecret);
  const clusters = children(scope, CfnDBCluster);
  const proxies = children(scope, CfnDBProxy);
  const outputs = scope.node.findAll().filter((node) => node instanceof CfnOutput);

  if (instances.length !== 1) errors.push('MXMED_DATA_DB_INSTANCE_COUNT_INVALID');
  if (parameterGroups.length !== 1) errors.push('MXMED_DATA_PARAMETER_GROUP_COUNT_INVALID');
  if (subnetGroups.length !== 1) errors.push('MXMED_DATA_SUBNET_GROUP_COUNT_INVALID');
  if (roles.length !== 1) errors.push('MXMED_DATA_MONITORING_ROLE_COUNT_INVALID');
  if (secrets.length !== 0) errors.push('MXMED_DATA_DUPLICATE_SECRET_FORBIDDEN');
  if (clusters.length !== 0) errors.push('MXMED_DATA_CLUSTER_FORBIDDEN');
  if (proxies.length !== 0) errors.push('MXMED_DATA_PROXY_FORBIDDEN');
  if (outputs.length !== 0) errors.push('MXMED_DATA_OUTPUT_FORBIDDEN');

  const role = roles[0];
  if (role !== undefined) {
    const trust = Stack.of(role).resolve(role.assumeRolePolicyDocument) as unknown;
    const expectedTrust = {
      Statement: [
        {
          Action: 'sts:AssumeRole',
          Effect: 'Allow',
          Principal: { Service: 'monitoring.rds.amazonaws.com' },
        },
      ],
      Version: '2012-10-17',
    };
    const policies = Stack.of(role).resolve(role.managedPolicyArns) as unknown;
    if (
      JSON.stringify(trust) !== JSON.stringify(expectedTrust) ||
      !Array.isArray(policies) ||
      policies.length !== 1 ||
      !JSON.stringify(policies).includes('AmazonRDSEnhancedMonitoringRole') ||
      role.permissionsBoundary !== undefined ||
      role.policies !== undefined
    ) {
      errors.push('MXMED_DATA_MONITORING_ROLE_INVALID');
    }
  }

  const instance = instances[0];
  if (instance !== undefined) {
    const expected =
      config.environmentName === 'production'
        ? CfnDeletionPolicy.RETAIN
        : CfnDeletionPolicy.SNAPSHOT;
    if (
      instance.cfnOptions.deletionPolicy !== expected ||
      instance.cfnOptions.updateReplacePolicy !== expected
    ) {
      errors.push('MXMED_DATA_REMOVAL_POLICY_INVALID');
    }
  }
  return [...new Set(errors)].sort();
}

export function registerMxMedDataValidation(
  scope: IConstruct,
  config: MxMedEnvironmentConfig,
): void {
  scope.node.addValidation({ validate: () => validateDataFoundation(scope, config) });
}
