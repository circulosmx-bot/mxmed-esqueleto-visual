import { Annotations, CfnDeletionPolicy, CfnOutput, Stack } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import { CfnKey } from 'aws-cdk-lib/aws-kms';
import { CfnResourcePolicy, CfnSecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentName } from '../config/environment-config';

function normalizedPath(node: IConstruct): string {
  return node.node.path.toLowerCase().replaceAll(/[^a-z0-9]/g, '');
}

function usesContractedSecretsKey(secret: CfnSecret): boolean {
  const stack = Stack.of(secret);
  const secretsKeys = stack.node
    .findAll()
    .filter(
      (node): node is CfnKey =>
        node instanceof CfnKey && normalizedPath(node).includes('secretskey'),
    );
  if (secretsKeys.length !== 1) return false;
  return (
    JSON.stringify(stack.resolve(secret.kmsKeyId)) ===
    JSON.stringify(stack.resolve(secretsKeys[0]?.attrArn))
  );
}

function hasPublicAllowPolicy(node: CfnResourcePolicy): boolean {
  const resolved = Stack.of(node).resolve(node.resourcePolicy) as
    { Statement?: unknown } | undefined;
  if (!Array.isArray(resolved?.Statement)) return true;
  return resolved.Statement.some((statement) => {
    if (typeof statement !== 'object' || statement === null) return true;
    const value = statement as { Effect?: unknown; Principal?: unknown };
    return value.Effect === 'Allow' && JSON.stringify(value.Principal ?? {}).includes('"*"');
  });
}

export class NoPlaintextSecretAspect implements IAspect {
  public constructor(private readonly environmentName: MxMedEnvironmentName) {}

  public visit(node: IConstruct): void {
    if (node instanceof CfnSecret) {
      const name = typeof node.name === 'string' ? node.name : '';
      const external = name.includes('/providers/');
      const retained =
        node.cfnOptions.deletionPolicy === CfnDeletionPolicy.RETAIN &&
        node.cfnOptions.updateReplacePolicy === CfnDeletionPolicy.RETAIN;
      if (
        node.secretString !== undefined ||
        (external && node.generateSecretString !== undefined) ||
        node.kmsKeyId === undefined ||
        !usesContractedSecretsKey(node) ||
        !name.startsWith(`/mxmed/${this.environmentName}/`) ||
        !retained
      ) {
        Annotations.of(node).addError('MXMED_SECURITY_SECRET_GUARDRAIL_FAILED');
      }
      return;
    }

    if (node instanceof CfnResourcePolicy) {
      if (node.blockPublicPolicy !== true || hasPublicAllowPolicy(node)) {
        Annotations.of(node).addError('MXMED_SECURITY_SECRET_RESOURCE_POLICY_FORBIDDEN');
      }
      return;
    }

    if (node instanceof CfnOutput && normalizedPath(node).includes('secret')) {
      Annotations.of(node).addError('MXMED_SECURITY_SECRET_OUTPUT_FORBIDDEN');
    }
  }
}
