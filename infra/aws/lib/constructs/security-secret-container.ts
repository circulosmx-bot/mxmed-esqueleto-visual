import { RemovalPolicy } from 'aws-cdk-lib';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import { CfnSecret, Secret } from 'aws-cdk-lib/aws-secretsmanager';
import type { ISecret } from 'aws-cdk-lib/aws-secretsmanager';
import { Construct } from 'constructs';

import type { MxMedEnvironmentName } from '../config/environment-config';
import type { MxMedExternalSecretPath } from '../utils/security-naming';
import { mxmedSecuritySecretName } from '../utils/security-naming';

export interface SecuritySecretContainerProps {
  readonly environmentName: MxMedEnvironmentName;
  readonly path: MxMedExternalSecretPath;
  readonly encryptionKey: IKey;
  readonly description: string;
}

/** Empty external-provider secret container; values are inserted only by a separate runbook. */
export class SecuritySecretContainer extends Construct {
  public readonly resource: CfnSecret;
  public readonly secret: ISecret;

  public constructor(scope: Construct, id: string, props: SecuritySecretContainerProps) {
    super(scope, id);

    const secretName = mxmedSecuritySecretName(props.environmentName, props.path);
    this.resource = new CfnSecret(this, 'Resource', {
      name: secretName,
      description: props.description,
      kmsKeyId: props.encryptionKey.keyArn,
    });
    this.resource.applyRemovalPolicy(RemovalPolicy.RETAIN);

    this.secret = Secret.fromSecretCompleteArn(this, 'Reference', this.resource.ref);
  }
}
