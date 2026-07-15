import { RemovalPolicy } from 'aws-cdk-lib';
import type { Duration } from 'aws-cdk-lib';
import { OidcProviderNative, OpenIdConnectPrincipal, Role } from 'aws-cdk-lib/aws-iam';
import type { IManagedPolicy } from 'aws-cdk-lib/aws-iam';
import { Construct } from 'constructs';

import type { MxMedEnvironmentName } from '../config/environment-config';
import { mxmedName } from '../utils/naming';
import { validateGithubOidcDeploymentProps } from '../utils/security-validation';

export interface MxMedGitHubOidcDeploymentProps {
  readonly organization: string;
  readonly repository: string;
  readonly branch: string;
  readonly githubEnvironment: string;
  readonly deploymentBoundary: IManagedPolicy;
  readonly environmentName: MxMedEnvironmentName;
  readonly maxSessionDuration: Duration;
}

/** Reusable native GitHub OIDC provider and exact-subject deployment role. */
export class MxMedGitHubOidcDeployment extends Construct {
  public readonly provider: OidcProviderNative;
  public readonly deploymentRole: Role;
  public readonly subject: string;

  public constructor(scope: Construct, id: string, props: MxMedGitHubOidcDeploymentProps) {
    super(scope, id);
    validateGithubOidcDeploymentProps(props);

    const environmentCode = props.environmentName === 'staging' ? 'stg' : 'prd';
    this.subject =
      props.environmentName === 'production'
        ? `repo:${props.organization}/${props.repository}:environment:${props.githubEnvironment}`
        : `repo:${props.organization}/${props.repository}:ref:refs/heads/${props.branch}`;

    this.provider = new OidcProviderNative(this, 'Provider', {
      url: 'https://token.actions.githubusercontent.com',
      clientIds: ['sts.amazonaws.com'],
      removalPolicy: RemovalPolicy.RETAIN,
    });
    this.deploymentRole = new Role(this, 'DeploymentRole', {
      assumedBy: new OpenIdConnectPrincipal(this.provider, {
        StringEquals: {
          'token.actions.githubusercontent.com:aud': 'sts.amazonaws.com',
          'token.actions.githubusercontent.com:sub': this.subject,
        },
      }),
      permissionsBoundary: props.deploymentBoundary,
      roleName: mxmedName(environmentCode, 'deployment-role', 64),
      description: `MXMed ${props.environmentName} deployment role using restricted GitHub OIDC.`,
      maxSessionDuration: props.maxSessionDuration,
    });
  }
}
