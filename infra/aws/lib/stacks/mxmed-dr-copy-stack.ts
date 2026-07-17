import { CfnParameter, RemovalPolicy, Tags } from 'aws-cdk-lib';
import { CfnBackupVault } from 'aws-cdk-lib/aws-backup';
import { Key, KeySpec, KeyUsage } from 'aws-cdk-lib/aws-kms';
import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import { drCopyVaultName } from '../utils/backup-dr-naming';

export interface MxMedDrCopyStackProps extends MxMedContractStackProps {
  readonly drRegion: string;
}

/** Offline-only destination vault fixture; no real DR region is persisted by the app. */
export class MxMedDrCopyStack extends BaseMxMedStack {
  public readonly destinationBackupKey: Key;
  public readonly drCopyVault: CfnBackupVault;
  public readonly drRegion: string;
  public readonly sourceRegionalVaultArn: CfnParameter;
  public readonly copyProfile = 'periodic-snapshot-copy-v1' as const;

  public constructor(scope: Construct, id: string, props: MxMedDrCopyStackProps) {
    super(scope, id, {
      ...props,
      component: 'dr-copy',
      description: 'MXMed explicit-region DR copy destination fixture.',
      metadata: { dataClassification: 'sensitive', criticality: 'high', backup: 'required' },
      regionOverride: props.drRegion,
    });
    this.drRegion = props.drRegion;
    this.destinationBackupKey = new Key(this, 'DestinationBackupKey', {
      alias: `alias/mxmed-${props.config.environmentCode}-dr-backup`,
      description: 'MXMed destination backup encryption key for an approved DR fixture.',
      keySpec: KeySpec.SYMMETRIC_DEFAULT,
      keyUsage: KeyUsage.ENCRYPT_DECRYPT,
      enableKeyRotation: true,
      multiRegion: false,
      removalPolicy: RemovalPolicy.RETAIN,
    });
    this.destinationBackupKey.applyRemovalPolicy(RemovalPolicy.RETAIN);
    this.sourceRegionalVaultArn = new CfnParameter(this, 'SourceRegionalBackupVaultArn', {
      type: 'String',
      allowedPattern: '^arn:[^:]+:backup:[a-z0-9-]+:[0-9]{12}:backup-vault:[A-Za-z0-9_-]+$',
      description: 'Source regional vault ARN supplied explicitly; no cross-region Export.',
    });
    this.drCopyVault = new CfnBackupVault(this, 'DrCopyVault', {
      backupVaultName: drCopyVaultName(props.config),
      encryptionKeyArn: this.destinationBackupKey.keyArn,
      ...(props.config.backupVaultLockMode === 'unlocked-v1'
        ? {}
        : {
            lockConfiguration: {
              minRetentionDays: props.config.backupVaultMinRetentionDays,
              maxRetentionDays: props.config.backupVaultMaxRetentionDays,
              ...(props.config.backupVaultLockMode === 'compliance-approved-v1'
                ? { changeableForDays: props.config.backupComplianceChangeableForDays ?? 3 }
                : {}),
            },
          }),
    });
    this.drCopyVault.applyRemovalPolicy(RemovalPolicy.RETAIN, {
      applyToUpdateReplacePolicy: true,
    });
    for (const resource of [this.destinationBackupKey, this.drCopyVault]) {
      Tags.of(resource).add('BackupDrActivationMode', props.config.backupDrActivationMode, {
        priority: 220,
      });
      Tags.of(resource).add('BackupPolicyVersion', 'v1', { priority: 220 });
      Tags.of(resource).add('BackupScope', 'critical-copy', { priority: 220 });
      Tags.of(resource).add('RecoveryTier', 'tier-1', { priority: 220 });
      Tags.of(resource).add('CostTier', 'usage-controlled', { priority: 220 });
      Tags.of(resource).add('RestoreValidation', 'false', { priority: 220 });
    }
  }
}
