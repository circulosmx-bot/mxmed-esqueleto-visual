import { Annotations, CfnDeletionPolicy } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import { CfnDBInstance } from 'aws-cdk-lib/aws-rds';
import { CfnBucket } from 'aws-cdk-lib/aws-s3';
import { CfnSecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentName } from '../config/environment-config';

export class ProductionRetentionAspect implements IAspect {
  public constructor(private readonly environmentName: MxMedEnvironmentName) {}

  public visit(node: IConstruct): void {
    if (this.environmentName !== 'production') {
      return;
    }

    if (node instanceof CfnDBInstance) {
      const retained =
        node.cfnOptions.deletionPolicy === CfnDeletionPolicy.RETAIN ||
        node.cfnOptions.deletionPolicy === CfnDeletionPolicy.SNAPSHOT;
      if (node.deletionProtection !== true || !retained) {
        Annotations.of(node).addError('MXMED_PRODUCTION_DATABASE_RETENTION_REQUIRED');
      }
    }

    if (
      (node instanceof CfnBucket || node instanceof CfnSecret) &&
      node.cfnOptions.deletionPolicy !== CfnDeletionPolicy.RETAIN
    ) {
      Annotations.of(node).addError('MXMED_PRODUCTION_DATA_RETENTION_REQUIRED');
    }
  }
}
