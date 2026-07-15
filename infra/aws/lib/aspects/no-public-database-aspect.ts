import { Annotations } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import { CfnDBInstance } from 'aws-cdk-lib/aws-rds';
import type { IConstruct } from 'constructs';

export class NoPublicDatabaseAspect implements IAspect {
  public visit(node: IConstruct): void {
    if (node instanceof CfnDBInstance && node.publiclyAccessible !== false) {
      Annotations.of(node).addError('MXMED_PUBLIC_DATABASE_FORBIDDEN');
    }
  }
}
