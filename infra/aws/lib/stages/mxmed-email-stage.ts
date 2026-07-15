import { Stage } from 'aws-cdk-lib';
import type { StageProps } from 'aws-cdk-lib';
import type { Construct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { validateEnvironmentConfig } from '../config/environment-schema';
import { MxMedEmailStack } from '../stacks/mxmed-email-stack';

export interface MxMedEmailStageProps {
  readonly config: MxMedEnvironmentConfig;
  readonly account?: string;
}

export class MxMedEmailStage extends Stage {
  public readonly emailStack: MxMedEmailStack;

  public constructor(scope: Construct, id: string, props: MxMedEmailStageProps) {
    validateEnvironmentConfig(props.config);
    const env: StageProps['env'] =
      props.account === undefined
        ? { region: props.config.emailRegion }
        : { account: props.account, region: props.config.emailRegion };
    super(scope, id, { env });

    this.emailStack = new MxMedEmailStack(this, 'Email', { config: props.config });
  }
}
