import { Stage } from 'aws-cdk-lib';
import type { StageProps } from 'aws-cdk-lib';
import type { Construct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { MxMedGlobalEdgeStack } from '../stacks/mxmed-global-edge-stack';

export interface MxMedGlobalEdgeStageProps {
  readonly config: MxMedEnvironmentConfig;
  readonly account?: string;
}

export class MxMedGlobalEdgeStage extends Stage {
  public readonly globalEdgeStack: MxMedGlobalEdgeStack;

  public constructor(scope: Construct, id: string, props: MxMedGlobalEdgeStageProps) {
    const env: StageProps['env'] =
      props.account === undefined
        ? { region: 'us-east-1' }
        : { account: props.account, region: 'us-east-1' };
    super(scope, id, { env });
    this.globalEdgeStack = new MxMedGlobalEdgeStack(this, 'GlobalEdge', {
      config: props.config,
    });
  }
}
