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

function rendered(): Readonly<Record<string, Resource>> {
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
  return template.Resources ?? {};
}

describe('C3 janitor and control contract', () => {
  const resourcesByLogicalId = rendered();
  const resources = Object.values(resourcesByLogicalId);
  const ofType = (type: string) => resources.filter((resource) => resource.Type === type);

  test('uses the reviewed eight-resource Scheduler plus Step Functions design', () => {
    expect(resources).toHaveLength(8);
    expect(ofType('AWS::Scheduler::Schedule')).toHaveLength(2);
    expect(ofType('AWS::StepFunctions::StateMachine')).toHaveLength(1);
    expect(ofType('AWS::Budgets::Budget')).toHaveLength(0);
    expect(ofType('AWS::Lambda::Function')).toHaveLength(0);
  });

  test('encodes a one-time +22h failsafe and independent +24h janitor deletion', () => {
    const failSafeSchedule = resourcesByLogicalId.FailSafeSchedule;
    const janitorDeleteSchedule = resourcesByLogicalId.JanitorDeleteSchedule;
    if (failSafeSchedule === undefined || janitorDeleteSchedule === undefined) {
      throw new Error('Expected both C3 Janitor schedules to be synthesized');
    }
    const supportedScheduleProperties = new Set([
      'Description',
      'EndDate',
      'FlexibleTimeWindow',
      'GroupName',
      'KmsKeyArn',
      'Name',
      'ScheduleExpression',
      'ScheduleExpressionTimezone',
      'StartDate',
      'State',
      'Target',
    ]);

    expect(failSafeSchedule).toMatchObject({
      Type: 'AWS::Scheduler::Schedule',
      Properties: {
        ScheduleExpression: { Ref: 'FailSafeScheduleExpression' },
        Target: { Arn: { 'Fn::GetAtt': ['JanitorStateMachine', 'Arn'] } },
      },
    });
    expect(janitorDeleteSchedule).toMatchObject({
      Type: 'AWS::Scheduler::Schedule',
      Properties: {
        ScheduleExpression: { Ref: 'JanitorDeleteScheduleExpression' },
        Target: { Arn: 'arn:aws:scheduler:::aws-sdk:cloudformation:deleteStack' },
      },
    });
    for (const schedule of [failSafeSchedule, janitorDeleteSchedule]) {
      expect(schedule.Properties).not.toHaveProperty('ActionAfterCompletion');
      expect(
        Object.keys(schedule.Properties ?? {}).filter(
          (property) => !supportedScheduleProperties.has(property),
        ),
      ).toEqual([]);
    }
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

  test('keeps the known region-incompatible Budget type outside CloudFormation', () => {
    expect(JSON.stringify(resources)).not.toContain('AWS::Budgets::Budget');
    expect(JSON.stringify(resources)).not.toContain('BudgetNotificationTopicArn');
  });

  test('represents all twelve phased machine stop gates in the source contract', () => {
    expect(MXMED_C3_STOP_GATES).toHaveLength(12);
    expect(new Set(MXMED_C3_STOP_GATES).size).toBe(12);
    expect(MXMED_C3_STOP_GATES).toEqual(
      expect.arrayContaining([
        'SOURCE_HEAD_MATCH',
        'FRESH_DIRECTOR_RUNTIME_AUTHORIZATION_PRESENT',
        'PRODUCTION_DENY_PROVEN',
        'MANUAL_TEARDOWN_READY',
        'AUTO_TEARDOWN_FAILSAFE_CONTRACT_READY',
        'NONPRODUCTION_TARGET_PROVEN',
        'ROLE_CHAIN_EXACT_PASS',
        'ECR_DIGEST_SEALED_BEFORE_RUNNER',
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
