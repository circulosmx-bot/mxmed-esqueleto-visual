import { Annotations, Stack } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import { CfnRepository } from 'aws-cdk-lib/aws-ecr';
import { CfnAlias, CfnKey } from 'aws-cdk-lib/aws-kms';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { mxmedName } from '../utils/naming';

function text(value: unknown, node: IConstruct): string {
  return JSON.stringify(Stack.of(node).resolve(value));
}

/** Fail-closed inventory and security contract for the dedicated Registry stack. */
export class RegistryFoundationAspect implements IAspect {
  public constructor(private readonly config: MxMedEnvironmentConfig) {}

  public visit(node: IConstruct): void {
    if (node instanceof Stack) {
      this.validateInventory(node);
      return;
    }
    if (node instanceof CfnRepository) {
      const rendered = Stack.of(node).resolve({
        repositoryName: node.repositoryName,
        encryption: node.encryptionConfiguration,
        scan: node.imageScanningConfiguration,
        mutability: node.imageTagMutability,
        emptyOnDelete: node.emptyOnDelete,
      }) as Readonly<Record<string, unknown>>;
      const lifecycle = text(node.lifecyclePolicy, node).replaceAll('\\', '');
      if (
        rendered.repositoryName !== mxmedName(this.config.environmentCode, 'application') ||
        rendered.mutability !== 'IMMUTABLE' ||
        text(rendered.scan, node).includes('false') ||
        !text(rendered.encryption, node).includes('KMS') ||
        rendered.emptyOnDelete === true ||
        !lifecycle.includes(
          `"countNumber":${String(this.config.computeEcrUntaggedRetentionDays)}`,
        ) ||
        !lifecycle.includes(`"countNumber":${String(this.config.computeEcrMaxImageCount)}`)
      ) {
        Annotations.of(node).addError('MXMED_REGISTRY_ECR_CONTRACT_INVALID');
      }
      return;
    }
    if (node instanceof CfnKey) {
      const rendered = Stack.of(node).resolve({
        enableKeyRotation: node.enableKeyRotation,
        keySpec: node.keySpec,
        keyUsage: node.keyUsage,
        multiRegion: node.multiRegion,
        pendingWindowInDays: node.pendingWindowInDays,
      }) as Readonly<Record<string, unknown>>;
      if (
        rendered.enableKeyRotation !== this.config.enableKeyRotation ||
        rendered.keySpec !== 'SYMMETRIC_DEFAULT' ||
        rendered.keyUsage !== 'ENCRYPT_DECRYPT' ||
        rendered.multiRegion === true ||
        rendered.pendingWindowInDays !== this.config.kmsDeletionWindowDays
      ) {
        Annotations.of(node).addError('MXMED_REGISTRY_KEY_CONTRACT_INVALID');
      }
      return;
    }
    if (node instanceof CfnAlias) {
      const alias: unknown = Stack.of(node).resolve(node.aliasName);
      if (alias !== `alias/${mxmedName(this.config.environmentCode, 'registry')}`) {
        Annotations.of(node).addError('MXMED_REGISTRY_ALIAS_CONTRACT_INVALID');
      }
    }
  }

  private validateInventory(stack: Stack): void {
    const nodes = stack.node.findAll();
    const count = (resourceType: new (...args: never[]) => IConstruct): number =>
      nodes.filter((candidate) => candidate instanceof resourceType).length;
    if (count(CfnKey) !== 1 || count(CfnAlias) !== 1 || count(CfnRepository) !== 1) {
      Annotations.of(stack).addError('MXMED_REGISTRY_INVENTORY_INVALID');
    }
  }
}
