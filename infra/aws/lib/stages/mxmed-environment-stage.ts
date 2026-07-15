import { Stage } from 'aws-cdk-lib';
import type { StageProps } from 'aws-cdk-lib';
import type { Construct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';
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
    this.dataStack = new MxMedDataStack(this, 'Data', stackProps);
    this.storageStack = new MxMedStorageStack(this, 'Storage', stackProps);
    this.sessionStack = new MxMedSessionStack(this, 'Session', stackProps);
    this.computeStack = new MxMedComputeStack(this, 'Compute', stackProps);
    this.edgeStack = new MxMedEdgeStack(this, 'Edge', stackProps);
    this.jobsStack = new MxMedJobsStack(this, 'Jobs', stackProps);
    this.backupStack = new MxMedBackupStack(this, 'Backup', stackProps);
    this.operationsStack = new MxMedOperationsStack(this, 'Operations', stackProps);

    this.dataStack.addDependency(this.networkStack);
    this.dataStack.addDependency(this.securityStack);
    this.storageStack.addDependency(this.securityStack);
    this.sessionStack.addDependency(this.networkStack);
    this.sessionStack.addDependency(this.securityStack);
    this.computeStack.addDependency(this.networkStack);
    this.computeStack.addDependency(this.securityStack);
    this.computeStack.addDependency(this.dataStack);
    this.computeStack.addDependency(this.storageStack);
    this.computeStack.addDependency(this.sessionStack);
    this.edgeStack.addDependency(this.computeStack);
    this.edgeStack.addDependency(this.securityStack);
    this.jobsStack.addDependency(this.computeStack);
    this.jobsStack.addDependency(this.securityStack);
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
