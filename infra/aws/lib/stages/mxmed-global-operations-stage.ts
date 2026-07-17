import { Stage } from 'aws-cdk-lib';
import type { StageProps } from 'aws-cdk-lib';
import type { Construct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { edgeCreatesGlobal } from '../config/edge-config';
import {
  operationsCreatesCost,
  operationsCreatesGlobalObservability,
} from '../config/operations-profiles';
import { MxMedCostManagementStack } from '../stacks/mxmed-cost-management-stack';
import { MxMedGlobalEdgeStack } from '../stacks/mxmed-global-edge-stack';
import { MxMedGlobalOperationsStack } from '../stacks/mxmed-global-operations-stack';

export interface MxMedGlobalOperationsStageProps {
  readonly config: MxMedEnvironmentConfig;
  readonly account?: string;
}

export class MxMedGlobalOperationsStage extends Stage {
  public readonly costManagementStack: MxMedCostManagementStack;
  public readonly globalEdgeStack: MxMedGlobalEdgeStack | undefined;
  public readonly globalOperationsStack: MxMedGlobalOperationsStack | undefined;

  public constructor(scope: Construct, id: string, props: MxMedGlobalOperationsStageProps) {
    const env: StageProps['env'] =
      props.account === undefined
        ? { region: 'us-east-1' }
        : { account: props.account, region: 'us-east-1' };
    super(scope, id, { env });
    if (!operationsCreatesCost(props.config)) {
      throw new Error('MXMED_GLOBAL_OPERATIONS_STAGE_DISABLED');
    }
    this.costManagementStack = new MxMedCostManagementStack(this, 'CostManagement', {
      config: props.config,
    });
    this.globalEdgeStack = edgeCreatesGlobal(props.config)
      ? new MxMedGlobalEdgeStack(this, 'GlobalEdge', { config: props.config })
      : undefined;
    this.globalOperationsStack =
      operationsCreatesGlobalObservability(props.config) && this.globalEdgeStack !== undefined
        ? new MxMedGlobalOperationsStack(this, 'GlobalOperations', {
            config: props.config,
            globalNotificationsKey: this.costManagementStack.globalNotificationsKey,
            distribution: this.globalEdgeStack.distribution,
            webAcl: this.globalEdgeStack.webAcl,
          })
        : undefined;
    if (this.globalOperationsStack !== undefined) {
      if (this.globalEdgeStack === undefined)
        throw new Error('MXMED_GLOBAL_EDGE_DEPENDENCY_MISSING');
      this.globalOperationsStack.addDependency(this.costManagementStack);
      this.globalOperationsStack.addDependency(this.globalEdgeStack);
    }
  }
}
