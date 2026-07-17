import { Stage } from 'aws-cdk-lib';
import type { StageProps } from 'aws-cdk-lib';
import type { ISecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { Construct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { computeCreatesRegistry, computeCreatesTasks } from '../config/compute-config';
import { edgeCreatesRegional } from '../config/edge-config';
import { operationsCreatesObservability } from '../config/operations-profiles';
import { validateEnvironmentConfig } from '../config/environment-schema';
import { MxMedBackupStack } from '../stacks/mxmed-backup-stack';
import { MxMedComputeStack } from '../stacks/mxmed-compute-stack';
import { MxMedDataStack } from '../stacks/mxmed-data-stack';
import { MxMedEdgeStack } from '../stacks/mxmed-edge-stack';
import { MxMedJobsStack } from '../stacks/mxmed-jobs-stack';
import { MxMedNetworkStack } from '../stacks/mxmed-network-stack';
import { MxMedRegionalOperationsStack } from '../stacks/mxmed-regional-operations-stack';
import { MxMedSecurityStack } from '../stacks/mxmed-security-stack';
import { MxMedSessionStack } from '../stacks/mxmed-session-stack';
import { MxMedStorageStack } from '../stacks/mxmed-storage-stack';
import { MxMedRegionalEdgeFoundationStack } from '../stacks/mxmed-regional-edge-foundation-stack';

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
  public readonly regionalEdgeStack: MxMedRegionalEdgeFoundationStack | undefined;
  public readonly edgeStack: MxMedEdgeStack;
  public readonly jobsStack: MxMedJobsStack;
  public readonly backupStack: MxMedBackupStack;
  public readonly operationsStack: MxMedRegionalOperationsStack | undefined;
  public readonly regionalOperationsStack: MxMedRegionalOperationsStack | undefined;

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
    this.regionalEdgeStack = edgeCreatesRegional(props.config)
      ? new MxMedRegionalEdgeFoundationStack(this, 'RegionalEdgeFoundation', {
          ...stackProps,
          vpc: this.networkStack.vpc,
          publicIngressSubnets: this.networkStack.publicIngressSubnets,
          albIngressSecurityGroup: this.networkStack.albIngressSecurityGroup,
          applicationSecurityGroup: this.networkStack.applicationSecurityGroup,
        })
      : undefined;
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
    const edgeAwareComputeStackProps =
      this.regionalEdgeStack === undefined
        ? computeStackProps
        : {
            ...computeStackProps,
            applicationTargetGroup: this.regionalEdgeStack.applicationTargetGroup,
          };
    this.computeStack = new MxMedComputeStack(
      this,
      'Compute',
      this.dataStack.applicationUserSecret === undefined
        ? edgeAwareComputeStackProps
        : {
            ...edgeAwareComputeStackProps,
            applicationUserSecret: this.dataStack.applicationUserSecret,
          },
    );
    this.edgeStack = new MxMedEdgeStack(this, 'Edge', stackProps);
    const regionalOperationsProps = {
      ...stackProps,
      auditKey: this.securityStack.auditKey,
      databaseInstance: this.dataStack.databaseInstance,
      allocatedStorageGiB: props.config.databaseAllocatedStorageGiB,
      databaseInstanceClass: props.config.databaseInstanceClass,
      replicationGroup: this.sessionStack.replicationGroup,
      primaryCacheClusterId: this.sessionStack.primaryCacheClusterId,
      sessionNodeType: props.config.sessionNodeType,
      deploymentProfile: props.config.deploymentProfile,
      computeMaxCapacity: props.config.computeMaxCapacity,
      runtimeCapabilityProfile: props.config.runtimeCapabilityProfile,
      edgeActivationMode: props.config.edgeActivationMode,
      ...(this.sessionStack.replicaCacheClusterId === undefined
        ? {}
        : { replicaCacheClusterId: this.sessionStack.replicaCacheClusterId }),
      ...(this.computeStack.cluster === undefined ? {} : { cluster: this.computeStack.cluster }),
      ...(this.computeStack.service === undefined ? {} : { service: this.computeStack.service }),
      ...(this.regionalEdgeStack === undefined
        ? {}
        : {
            loadBalancer: this.regionalEdgeStack.applicationLoadBalancer,
            targetGroup: this.regionalEdgeStack.applicationTargetGroup,
          }),
    };
    this.operationsStack = operationsCreatesObservability(props.config)
      ? new MxMedRegionalOperationsStack(this, 'RegionalOperations', regionalOperationsProps)
      : undefined;
    this.regionalOperationsStack = this.operationsStack;
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
    if (this.regionalEdgeStack !== undefined) {
      this.regionalEdgeStack.addDependency(this.networkStack);
      this.computeStack.addDependency(this.regionalEdgeStack);
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

    if (this.operationsStack !== undefined) {
      for (const observableStack of [
        this.securityStack,
        this.dataStack,
        this.sessionStack,
        this.computeStack,
      ]) {
        this.operationsStack.addDependency(observableStack);
      }
      if (this.regionalEdgeStack !== undefined) {
        this.operationsStack.addDependency(this.regionalEdgeStack);
      }
    }
  }
}
