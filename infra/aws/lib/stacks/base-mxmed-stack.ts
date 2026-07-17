import { DefaultStackSynthesizer, Stack, Tags } from 'aws-cdk-lib';
import type { StackProps } from 'aws-cdk-lib';
import type { Construct } from 'constructs';

import type { MxMedEnvironmentConfig, MxMedStackTagMetadata } from '../config/environment-config';
import { mxmedCostTierForComponent } from '../config/launch-profiles';
import { mxmedName } from '../utils/naming';

export interface BaseMxMedStackProps {
  readonly config: MxMedEnvironmentConfig;
  readonly component: string;
  readonly metadata: MxMedStackTagMetadata;
  readonly description: string;
}

export type MxMedContractStackProps = Pick<BaseMxMedStackProps, 'config'>;

/** Common stack contract. Foundation stacks intentionally contain no AWS resources. */
export abstract class BaseMxMedStack extends Stack {
  protected constructor(scope: Construct, id: string, props: BaseMxMedStackProps) {
    // CDK 2.260.0 models bootstrapQualifier incompatibly with exactOptionalPropertyTypes.
    const synthesizer = new DefaultStackSynthesizer() as unknown as NonNullable<
      StackProps['synthesizer']
    >;
    const stackProps: StackProps = {
      stackName: mxmedName(props.config.environmentCode, props.component),
      description: props.description,
      terminationProtection: props.config.enableTerminationProtection,
      synthesizer,
    };
    super(scope, id, stackProps);

    Tags.of(this).add('Project', props.config.tags.Project);
    Tags.of(this).add('Environment', props.config.tags.Environment);
    Tags.of(this).add('ManagedBy', props.config.tags.ManagedBy);
    Tags.of(this).add('Application', props.config.tags.Application);
    Tags.of(this).add('Owner', props.config.tags.Owner);
    Tags.of(this).add('DeploymentProfile', props.config.tags.DeploymentProfile);
    Tags.of(this).add('CostReview', props.config.tags.CostReview);
    Tags.of(this).add('Ephemeral', props.config.tags.Ephemeral);
    Tags.of(this).add('SchedulePolicy', props.config.tags.SchedulePolicy);
    Tags.of(this).add('CostScope', props.config.tags.CostScope);
    Tags.of(this).add('Component', props.component);
    Tags.of(this).add('DataClassification', props.metadata.dataClassification);
    Tags.of(this).add('Criticality', props.metadata.criticality);
    Tags.of(this).add('Backup', props.metadata.backup);
    Tags.of(this).add('CostTier', mxmedCostTierForComponent(props.component));
  }
}
