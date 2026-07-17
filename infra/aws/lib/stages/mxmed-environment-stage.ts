import { Stage } from 'aws-cdk-lib';
import type { StageProps } from 'aws-cdk-lib';
import type { ISecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { Construct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { computeCreatesRegistry, computeCreatesTasks } from '../config/compute-config';
import { validateEnvironmentConfig } from '../config/environment-schema';
import { MxMedBackupStack } from '../stacks/mxmed-backup-stack';
import { MxMedComputeStack } from '../stacks/mxmed-compute-stack';
import { MxMedDataStack } from '../stacks/mxmed-data-stack';
import { MxMedEdgeStack } from '../stacks/mxmed-edge-stack';
import { MxMedJobsStack } from '../stacks/mxmed-jobs-stack';
import { MxMedNetworkStack } from '../stacks/mxmed-network-stack';
import { MxMedOperationsStack } from '../stacks/mxmed-operations-stack';
import { MxMedSecurityStack } from '../stacks/mxmed-security-stack';
import { MxMedSessionStack } from '../stacks/mxmed-session-stack';
import { MxMedStorageStack } from '../stacks/mxmed-storage-stack';

export interface MxMedEnvironmentStageProps {
  readonly config: MxMedEnvironmentConfig;
  readonly account?: string;
}

export class MxMedEnvironmentStage extends Stage {
  public readonly networkStack: MxMedNetworkStack;
  public readonly securityStack: MxMedSecurityStack;
  public readonly dataStack: MxMedDataStack;
  public readonly storageStack: MxMedStorageStack;
  public readonly sessionStack: MxMedSessionStack;
  public readonly computeStack: MxMedComputeStack;
  public readonly edgeStack: MxMedEdgeStack;
  public readonly jobsStack: MxMedJobsStack;
  public readonly backupStack: MxMedBackupStack;
  public readonly operationsStack: MxMedOperationsStack;

  public constructor(scope: Construct, id: string, props: MxMedEnvironmentStageProps) {
    validateEnvironmentConfig(props.config);
    const env: StageProps['env'] =
      props.account === undefined
        ? { region: props.config.primaryRegion }
        : { account: props.account, region: props.config.primaryRegion };
    super(scope, id, { env });

    const stackProps = { config: props.config };
    this.networkStack = new MxMedNetworkStack(this, 'Network', stackProps);
    this.securityStack = new MxMedSecurityStack(this, 'Security', stackProps);
    this.dataStack = new MxMedDataStack(this, 'Data', {
      ...stackProps,
      vpc: this.networkStack.vpc,
      isolatedDataSubnets: this.networkStack.isolatedDataSubnets,
      databaseSecurityGroup: this.networkStack.databaseSecurityGroup,
      applicationDataKey: this.securityStack.applicationDataKey,
      secretsKey: this.securityStack.secretsKey,
      migrationTaskRole: this.securityStack.migrationTaskRole,
    });
    this.storageStack = new MxMedStorageStack(this, 'Storage', {
      ...stackProps,
      applicationDataKey: this.securityStack.applicationDataKey,
    });
    this.sessionStack = new MxMedSessionStack(this, 'Session', {
      ...stackProps,
      vpc: this.networkStack.vpc,
      isolatedDataSubnets: this.networkStack.isolatedDataSubnets,
      sessionSecurityGroup: this.networkStack.sessionSecurityGroup,
      applicationDataKey: this.securityStack.applicationDataKey,
      secretsKey: this.securityStack.secretsKey,
    });
    const computeStackProps = {
      ...stackProps,
      vpc: this.networkStack.vpc,
      privateAppSubnets: this.networkStack.privateAppSubnets,
      applicationSecurityGroup: this.networkStack.applicationSecurityGroup,
      applicationDataKey: this.securityStack.applicationDataKey,
      auditKey: this.securityStack.auditKey,
      secretsKey: this.securityStack.secretsKey,
      ecsExecutionRole: this.securityStack.ecsExecutionRole,
      applicationTaskRole: this.securityStack.applicationTaskRole,
      migrationTaskRole: this.securityStack.migrationTaskRole,
      sessionSigningSecret: this.securityStack.sessionSigningSecret as unknown as ISecret,
      stripeSecretKeyReference: this.securityStack.stripeSecretKeyReference,
      stripeWebhookSecretReference: this.securityStack.stripeWebhookSecretReference,
      aiApiKeyReference: this.securityStack.aiApiKeyReference,
      databaseEndpoint: this.dataStack.databaseEndpoint,
      databasePort: this.dataStack.databasePort,
      databaseName: this.dataStack.databaseName,
      masterUserSecret: this.dataStack.masterUserSecret,
      publicMediaBucket: this.storageStack.publicMediaBucket,
      privateDocumentsBucket: this.storageStack.privateDocumentsBucket,
      clinicalRecordsBucket: this.storageStack.clinicalRecordsBucket,
      uploadQuarantineBucket: this.storageStack.uploadQuarantineBucket,
      uploadUrlTtlSeconds: this.storageStack.uploadUrlTtlSeconds,
      downloadUrlTtlSeconds: this.storageStack.downloadUrlTtlSeconds,
      sessionEndpoint: this.sessionStack.primaryEndpointAddress,
      sessionPort: this.sessionStack.primaryEndpointPort,
      sessionAuthSecret: this.sessionStack.authSecret as unknown as ISecret,
      sessionPrefix: this.sessionStack.sessionPrefix,
      sessionIdleTtlSeconds: this.sessionStack.sessionIdleTtlSeconds,
      sessionAbsoluteLifetimeSeconds: this.sessionStack.sessionAbsoluteLifetimeSeconds,
      sessionLockEnabled: props.config.sessionLockEnabled,
      sessionLockTimeoutSeconds: props.config.sessionLockTimeoutSeconds,
      sessionLockWaitMicroseconds: props.config.sessionLockWaitMicroseconds,
    };
    this.computeStack = new MxMedComputeStack(
      this,
      'Compute',
      this.dataStack.applicationUserSecret === undefined
        ? computeStackProps
        : {
            ...computeStackProps,
            applicationUserSecret: this.dataStack.applicationUserSecret,
          },
    );
    this.edgeStack = new MxMedEdgeStack(this, 'Edge', stackProps);
    this.operationsStack = new MxMedOperationsStack(this, 'Operations', stackProps);
    this.jobsStack = new MxMedJobsStack(this, 'Jobs', stackProps);
    this.backupStack = new MxMedBackupStack(this, 'Backup', stackProps);

    this.dataStack.addDependency(this.networkStack);
    this.dataStack.addDependency(this.securityStack);
    this.storageStack.addDependency(this.securityStack);
    this.sessionStack.addDependency(this.networkStack);
    this.sessionStack.addDependency(this.securityStack);
    if (computeCreatesRegistry(props.config.computeActivationMode)) {
      this.computeStack.addDependency(this.securityStack);
    }
    if (computeCreatesTasks(props.config.computeActivationMode)) {
      this.computeStack.addDependency(this.networkStack);
      this.computeStack.addDependency(this.dataStack);
      this.computeStack.addDependency(this.storageStack);
      this.computeStack.addDependency(this.sessionStack);
    }
    this.edgeStack.addDependency(this.computeStack);
    this.edgeStack.addDependency(this.securityStack);
    this.edgeStack.addDependency(this.storageStack);
    this.jobsStack.addDependency(this.computeStack);
    this.jobsStack.addDependency(this.securityStack);
    this.jobsStack.addDependency(this.storageStack);
    this.backupStack.addDependency(this.dataStack);
    this.backupStack.addDependency(this.storageStack);
    this.backupStack.addDependency(this.securityStack);

    for (const observableStack of [
      this.networkStack,
      this.securityStack,
      this.dataStack,
      this.storageStack,
      this.sessionStack,
      this.computeStack,
      this.edgeStack,
      this.jobsStack,
      this.backupStack,
    ]) {
      this.operationsStack.addDependency(observableStack);
    }
  }
}
