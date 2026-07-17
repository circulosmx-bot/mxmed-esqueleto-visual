import { CfnOutput } from 'aws-cdk-lib';
import type { CfnResource } from 'aws-cdk-lib';
import {
  CfnParameterGroup,
  CfnReplicationGroup,
  CfnServerlessCache,
  CfnSubnetGroup,
  CfnUser,
  CfnUserGroup,
} from 'aws-cdk-lib/aws-elasticache';
import { CfnSecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';

function resources<T extends CfnResource>(
  scope: IConstruct,
  resourceType: new (...args: never[]) => T,
): T[] {
  return scope.node.findAll().filter((node): node is T => node instanceof resourceType);
}

function validateSessionFoundation(scope: IConstruct, config: MxMedEnvironmentConfig): string[] {
  const errors: string[] = [];
  const replicationGroups = resources(scope, CfnReplicationGroup);
  const subnetGroups = resources(scope, CfnSubnetGroup);
  const parameterGroups = resources(scope, CfnParameterGroup);
  const users = resources(scope, CfnUser);
  const userGroups = resources(scope, CfnUserGroup);
  const serverlessCaches = resources(scope, CfnServerlessCache);
  const sessionSecrets = resources(scope, CfnSecret).filter((secret) =>
    secret.node.path.includes('SessionAuthSecret'),
  );
  const outputs = scope.node
    .findAll()
    .filter((node) => node instanceof CfnOutput && !node.node.path.includes('/Exports/'));

  if (replicationGroups.length !== 1) errors.push('MXMED_SESSION_REPLICATION_GROUP_COUNT_INVALID');
  if (subnetGroups.length !== 1) errors.push('MXMED_SESSION_SUBNET_GROUP_COUNT_INVALID');
  if (parameterGroups.length !== 1) errors.push('MXMED_SESSION_PARAMETER_GROUP_COUNT_INVALID');
  if (users.length !== 2) errors.push('MXMED_SESSION_USER_COUNT_INVALID');
  if (userGroups.length !== 1) errors.push('MXMED_SESSION_USER_GROUP_COUNT_INVALID');
  if (sessionSecrets.length !== 1) errors.push('MXMED_SESSION_AUTH_SECRET_COUNT_INVALID');
  if (serverlessCaches.length !== 0) errors.push('MXMED_SESSION_SERVERLESS_FORBIDDEN');
  if (outputs.length !== 0) errors.push('MXMED_SESSION_OUTPUT_FORBIDDEN');

  const replicationGroup = replicationGroups[0];
  if (
    replicationGroup !== undefined &&
    replicationGroup.numCacheClusters !== config.sessionReplicaCount + 1
  ) {
    errors.push('MXMED_SESSION_NODE_COUNT_INVALID');
  }

  return [...new Set(errors)].sort();
}

export function registerMxMedSessionValidation(
  scope: IConstruct,
  config: MxMedEnvironmentConfig,
): void {
  scope.node.addValidation({ validate: () => validateSessionFoundation(scope, config) });
}
