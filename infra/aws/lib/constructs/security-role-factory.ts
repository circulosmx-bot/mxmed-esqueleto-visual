import { Duration } from 'aws-cdk-lib';
import { PrincipalWithConditions, Role, ServicePrincipal } from 'aws-cdk-lib/aws-iam';
import type { IManagedPolicy, IPrincipal } from 'aws-cdk-lib/aws-iam';
import type { Construct } from 'constructs';

import type { MxMedEnvironmentCode, MxMedEnvironmentName } from '../config/environment-config';
import { mxmedName } from '../utils/naming';
import { assertMxMedCondition } from '../utils/validation';

export type MxMedWorkloadRoleKind = 'ecs-execution' | 'application' | 'migration' | 'jobs';

export interface MxMedHumanRoleProps {
  readonly principal: IPrincipal;
  readonly mfaRequired: boolean;
  readonly maxSessionDuration: Duration;
  readonly boundary: IManagedPolicy;
  readonly environmentName: MxMedEnvironmentName;
  readonly contractualReason: string;
}

export class MxMedSecurityRoleFactory {
  public constructor(
    private readonly environmentName: MxMedEnvironmentName,
    private readonly environmentCode: MxMedEnvironmentCode,
    private readonly workloadBoundary: IManagedPolicy,
  ) {}

  public createWorkloadRole(
    scope: Construct,
    id: string,
    kind: MxMedWorkloadRoleKind,
    description: string,
  ): Role {
    return new Role(scope, id, {
      assumedBy: new ServicePrincipal('ecs-tasks.amazonaws.com'),
      permissionsBoundary: this.workloadBoundary,
      roleName: mxmedName(this.environmentCode, `${kind}-role`, 64),
      description,
      maxSessionDuration: Duration.hours(1),
    });
  }

  public createSecurityAuditRole(scope: Construct, id: string, props: MxMedHumanRoleProps): Role {
    return this.createHumanRole(scope, id, 'security-audit-role', props);
  }

  public createBreakGlassRole(scope: Construct, id: string, props: MxMedHumanRoleProps): Role {
    return this.createHumanRole(scope, id, 'break-glass-role', props);
  }

  private createHumanRole(
    scope: Construct,
    id: string,
    component: 'security-audit-role' | 'break-glass-role',
    props: MxMedHumanRoleProps,
  ): Role {
    assertMxMedCondition(
      props.environmentName === this.environmentName,
      'MXMED_CONFIG_INVALID',
      'humanRoleEnvironment',
      'must match the factory environment',
    );
    assertMxMedCondition(
      props.mfaRequired,
      'MXMED_CONFIG_INVALID',
      'humanRoleMfa',
      'must require MFA',
    );
    assertMxMedCondition(
      props.maxSessionDuration.toSeconds() === Duration.hours(1).toSeconds(),
      'MXMED_CONFIG_INVALID',
      'humanRoleSessionDuration',
      'must be exactly one hour',
    );
    assertMxMedCondition(
      props.contractualReason.trim().length >= 12,
      'MXMED_CONFIG_INVALID',
      'humanRoleReason',
      'must state a contractual reason',
    );

    return new Role(scope, id, {
      assumedBy: new PrincipalWithConditions(props.principal, {
        Bool: { 'aws:MultiFactorAuthPresent': 'true' },
      }),
      permissionsBoundary: props.boundary,
      roleName: mxmedName(this.environmentCode, component, 64),
      description: `MXMed ${component}; ${props.contractualReason.trim()}`,
      maxSessionDuration: props.maxSessionDuration,
    });
  }
}
