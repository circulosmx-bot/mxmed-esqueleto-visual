import { App } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import {
  MXMED_C3_CONTROL_ROLE_CONTRACTS,
  MXMED_C3_RETAINED_LOGICAL_RESOURCES,
  MXMED_C3_STOP_GATES,
} from '../lib/constructs/c3-runner-contract';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedC3EphemeralStage } from '../lib/stages/mxmed-c3-ephemeral-stage';

interface Resource {
  readonly Type: string;
  readonly Properties?: Readonly<Record<string, unknown>>;
}

function rendered(): readonly Resource[] {
  const stage = new MxMedC3EphemeralStage(
    new App({ analyticsReporting: false }),
    'JanitorFixture',
    {
      config: getEnvironmentConfig('staging', 'launch-lean-v1', 'registry-only-v1'),
      account: '875691018466',
    },
  );
  const template = Template.fromStack(stage.janitorStack).toJSON() as {
    Resources?: Record<string, Resource>;
  };
  return Object.values(template.Resources ?? {});
}

describe('C3 janitor and control contract', () => {
  const resources = rendered();
  const ofType = (type: string) => resources.filter((resource) => resource.Type === type);

  test('uses the reviewed nine-resource Scheduler plus Step Functions design', () => {
    expect(resources).toHaveLength(9);
    expect(ofType('AWS::Scheduler::Schedule')).toHaveLength(2);
    expect(ofType('AWS::StepFunctions::StateMachine')).toHaveLength(1);
    expect(ofType('AWS::Budgets::Budget')).toHaveLength(1);
    expect(ofType('AWS::Lambda::Function')).toHaveLength(0);
  });

  test('encodes a one-time +22h failsafe and independent +24h janitor deletion', () => {
    const schedules = JSON.stringify(ofType('AWS::Scheduler::Schedule'));
    expect(schedules).toContain('FailSafeScheduleExpression');
    expect(schedules).toContain('JanitorDeleteScheduleExpression');
    expect(schedules).toContain('ActionAfterCompletion');
    expect(schedules).toContain('DELETE');
    expect(schedules).toContain('cloudformation:deleteStack');
  });

  test('captures and cleans exactly all 13 reviewed retained logical resources', () => {
    expect(MXMED_C3_RETAINED_LOGICAL_RESOURCES).toHaveLength(13);
    const machine = JSON.stringify(ofType('AWS::StepFunctions::StateMachine'));
    for (const resource of MXMED_C3_RETAINED_LOGICAL_RESOURCES) {
      expect(machine).toContain(resource.stackName);
      expect(machine).toContain(resource.logicalId);
    }
    expect(machine).toContain('RecoveryWindowInDays');
    expect(machine).toContain('scheduleKeyDeletion');
    expect(machine).toContain('deleteRepository');
    expect(machine).toContain('deleteBucket');
  });

  test('fails closed, separates roles, applies boundaries, and explicitly denies production', () => {
    expect(ofType('AWS::IAM::Role')).toHaveLength(2);
    expect(ofType('AWS::IAM::Policy')).toHaveLength(2);
    const iam = JSON.stringify([...ofType('AWS::IAM::Role'), ...ofType('AWS::IAM::Policy')]);
    expect(iam).toContain('PermissionsBoundary');
    expect(iam).toContain('ExplicitProductionDeny');
    expect(iam).toContain('mxmed-prd-');
    expect(iam).not.toMatch(/AdministratorAccess|PowerUserAccess/);
  });

  test('encodes the USD 5 cap with advisory USD 1, 3 and 5 alerts', () => {
    const budget = JSON.stringify(ofType('AWS::Budgets::Budget'));
    expect(budget).toContain('"Amount":5');
    expect(budget).toContain('"Threshold":1');
    expect(budget).toContain('"Threshold":3');
    expect(budget).toContain('"Threshold":5');
    expect(budget).toContain('BudgetNotificationTopicArn');
  });

  test('represents all ten machine stop gates in the source contract', () => {
    expect(MXMED_C3_STOP_GATES).toHaveLength(10);
    expect(new Set(MXMED_C3_STOP_GATES).size).toBe(10);
    expect(MXMED_C3_STOP_GATES).toEqual(
      expect.arrayContaining([
        'DIRECTOR_AWS_WRITE_AUTHORIZATION_PRESENT',
        'PRODUCTION_DENY_PROVEN',
        'MANUAL_TEARDOWN_READY',
        'AUTO_TEARDOWN_FAILSAFE_READY',
        'NONPRODUCTION_TARGET_PROVEN',
      ]),
    );
  });

  test('keeps deploy, test-controller and teardown human authorities separated', () => {
    expect(Object.keys(MXMED_C3_CONTROL_ROLE_CONTRACTS)).toEqual([
      'deploy',
      'testController',
      'teardown',
    ]);
    const contracts = JSON.stringify(MXMED_C3_CONTROL_ROLE_CONTRACTS);
    expect(contracts).toContain('MXMed-C3-Staging-Deploy-Boundary');
    expect(contracts).toContain('MXMed-C3-Staging-TestController-Boundary');
    expect(contracts).toContain('MXMed-C3-Staging-Teardown-Boundary');
    expect(contracts).toContain('mxmed-prd-');
    expect(contracts).not.toMatch(/AdministratorAccess|PowerUserAccess/);
    expect(MXMED_C3_CONTROL_ROLE_CONTRACTS.testController.actions).not.toContain(
      'secretsmanager:GetSecretValue',
    );
    expect(MXMED_C3_CONTROL_ROLE_CONTRACTS.testController.actions).not.toEqual(
      expect.arrayContaining(['cloudformation:CreateStack', 'cloudformation:DeleteStack']),
    );
    expect(contracts).toContain('budgets:ViewBudget');
    expect(contracts).toContain('budgets:ModifyBudget');
    expect(contracts).not.toMatch(/budgets:(?:DescribeBudgets|CreateBudget|DeleteBudget)/);
  });
});
