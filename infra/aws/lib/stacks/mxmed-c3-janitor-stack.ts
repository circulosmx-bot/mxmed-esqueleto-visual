import { CfnParameter, Fn, RemovalPolicy, Tags } from 'aws-cdk-lib';
import { CfnBudget } from 'aws-cdk-lib/aws-budgets';
import { CfnPolicy, CfnRole } from 'aws-cdk-lib/aws-iam';
import { CfnLogGroup } from 'aws-cdk-lib/aws-logs';
import { CfnSchedule } from 'aws-cdk-lib/aws-scheduler';
import { CfnStateMachine } from 'aws-cdk-lib/aws-stepfunctions';
import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import {
  MXMED_C3_COST_CAP_USD,
  MXMED_C3_DELETE_ORDER,
  MXMED_C3_RETAINED_LOGICAL_RESOURCES,
} from '../constructs/c3-runner-contract';

export type MxMedC3JanitorStackProps = MxMedContractStackProps;

type StateDefinition = Record<string, unknown>;

interface RetainedResourceCapture {
  readonly key: string;
  readonly stackName: string;
  readonly logicalId: string;
  readonly type: string;
}

function sdkIntegration(service: string, action: string): string {
  return `arn:aws:states:::${['aws', 'sdk'].join('-')}:${service}:${action}`;
}

function retainedCaptures(): readonly RetainedResourceCapture[] {
  return MXMED_C3_RETAINED_LOGICAL_RESOURCES.map((resource, index) => ({
    key: `r${String(index).padStart(2, '0')}`,
    stackName: resource.stackName,
    logicalId: resource.logicalId,
    type: resource.type,
  }));
}

function cleanupTask(resource: RetainedResourceCapture, next: string): StateDefinition {
  const physicalId = `$.retained.${resource.key}.StackResourceDetail.PhysicalResourceId`;
  const common = { Type: 'Task', ResultPath: null, Next: next };
  if (resource.type === 'AWS::SecretsManager::Secret') {
    return {
      ...common,
      Resource: sdkIntegration('secretsmanager', 'deleteSecret'),
      Parameters: { 'SecretId.$': physicalId, RecoveryWindowInDays: 7 },
    };
  }
  if (resource.type === 'AWS::Logs::LogGroup') {
    return {
      ...common,
      Resource: sdkIntegration('cloudwatchlogs', 'deleteLogGroup'),
      Parameters: { 'LogGroupName.$': physicalId },
    };
  }
  if (resource.type === 'AWS::ECR::Repository') {
    return {
      ...common,
      Resource: sdkIntegration('ecr', 'deleteRepository'),
      Parameters: { 'RepositoryName.$': physicalId, Force: true },
    };
  }
  return {
    ...common,
    Resource: sdkIntegration('kms', 'scheduleKeyDeletion'),
    Parameters: { 'KeyId.$': physicalId, PendingWindowInDays: 7 },
  };
}

function buildJanitorDefinition(): StateDefinition {
  const states: Record<string, StateDefinition> = {};
  const captures = retainedCaptures();
  states.ListRunnerTasks = {
    Type: 'Task',
    Resource: sdkIntegration('ecs', 'listTasks'),
    Parameters: {
      Cluster: 'mxmed-stg-c3-runner',
      'StartedBy.$': '$.run_id',
      DesiredStatus: 'RUNNING',
    },
    ResultPath: '$.runnerTasks',
    Next: 'StopRunnerTasks',
  };
  states.StopRunnerTasks = {
    Type: 'Map',
    ItemsPath: '$.runnerTasks.TaskArns',
    MaxConcurrency: 1,
    ItemSelector: { 'TaskArn.$': '$$.Map.Item.Value' },
    ItemProcessor: {
      ProcessorConfig: { Mode: 'INLINE' },
      StartAt: 'StopExactRunnerTask',
      States: {
        StopExactRunnerTask: {
          Type: 'Task',
          Resource: sdkIntegration('ecs', 'stopTask'),
          Parameters: {
            Cluster: 'mxmed-stg-c3-runner',
            'Task.$': '$.TaskArn',
            Reason: 'MXMED_C3_FAILSAFE_TEARDOWN',
          },
          End: true,
        },
      },
    },
    ResultPath: null,
    Next: 'CaptureRetained00',
  };
  captures.forEach((resource, index) => {
    const stateName = `CaptureRetained${String(index).padStart(2, '0')}`;
    states[stateName] = {
      Type: 'Task',
      Resource: sdkIntegration('cloudformation', 'describeStackResource'),
      Parameters: { StackName: resource.stackName, LogicalResourceId: resource.logicalId },
      ResultPath: `$.retained.${resource.key}`,
      Next:
        index + 1 < captures.length
          ? `CaptureRetained${String(index + 1).padStart(2, '0')}`
          : 'DeleteStack00',
    };
  });

  MXMED_C3_DELETE_ORDER.forEach((stackName, index) => {
    const suffix = String(index).padStart(2, '0');
    const nextSuffix = String(index + 1).padStart(2, '0');
    const deleteName = `DeleteStack${suffix}`;
    const waitName = `WaitForStack${suffix}`;
    const describeName = `DescribeStack${suffix}`;
    const choiceName = `StackDeleted${suffix}`;
    const next =
      index + 1 < MXMED_C3_DELETE_ORDER.length ? `DeleteStack${nextSuffix}` : 'CleanupRetained00';
    states[deleteName] = {
      Type: 'Task',
      Resource: sdkIntegration('cloudformation', 'deleteStack'),
      Parameters: { StackName: stackName },
      ResultPath: null,
      Next: waitName,
      Catch: [{ ErrorEquals: ['States.ALL'], ResultPath: '$.error', Next: 'JanitorFailed' }],
    };
    states[waitName] = { Type: 'Wait', Seconds: 30, Next: describeName };
    states[describeName] = {
      Type: 'Task',
      Resource: sdkIntegration('cloudformation', 'describeStacks'),
      Parameters: { StackName: stackName },
      ResultPath: `$.deletions.${suffix}`,
      Next: choiceName,
      Catch: [
        {
          ErrorEquals: [
            'CloudFormation.ValidationException',
            'CloudFormation.ValidationError',
            'CloudFormation.CloudFormationException',
          ],
          ResultPath: null,
          Next: next,
        },
        { ErrorEquals: ['States.ALL'], ResultPath: '$.error', Next: 'JanitorFailed' },
      ],
    };
    states[choiceName] = {
      Type: 'Choice',
      Choices: [
        {
          Variable: `$.deletions.${suffix}.Stacks[0].StackStatus`,
          StringEquals: 'DELETE_FAILED',
          Next: 'JanitorFailed',
        },
      ],
      Default: waitName,
    };
  });

  captures.forEach((resource, index) => {
    const stateName = `CleanupRetained${String(index).padStart(2, '0')}`;
    const next =
      index + 1 < captures.length
        ? `CleanupRetained${String(index + 1).padStart(2, '0')}`
        : 'JanitorSucceeded';
    if (resource.type !== 'AWS::S3::Bucket') {
      states[stateName] = {
        ...cleanupTask(resource, next),
        Catch: [{ ErrorEquals: ['States.ALL'], ResultPath: '$.error', Next: 'JanitorFailed' }],
      };
      return;
    }
    const physicalId = `$.retained.${resource.key}.StackResourceDetail.PhysicalResourceId`;
    states[stateName] = {
      Type: 'Task',
      Resource: sdkIntegration('s3', 'listObjectVersions'),
      Parameters: { 'Bucket.$': physicalId },
      ResultPath: '$.bucketVersions',
      Next: 'DeleteAuditBucketVersions',
      Catch: [{ ErrorEquals: ['States.ALL'], ResultPath: '$.error', Next: 'JanitorFailed' }],
    };
    const deleteObjects = (itemsPath: string, itemState: string): StateDefinition => ({
      Type: 'Map',
      ItemsPath: itemsPath,
      MaxConcurrency: 1,
      ItemSelector: {
        'Bucket.$': physicalId,
        'Key.$': '$$.Map.Item.Value.Key',
        'VersionId.$': '$$.Map.Item.Value.VersionId',
      },
      ItemProcessor: {
        ProcessorConfig: { Mode: 'INLINE' },
        StartAt: itemState,
        States: {
          [itemState]: {
            Type: 'Task',
            Resource: sdkIntegration('s3', 'deleteObject'),
            Parameters: {
              'Bucket.$': '$.Bucket',
              'Key.$': '$.Key',
              'VersionId.$': '$.VersionId',
            },
            End: true,
          },
        },
      },
      ResultPath: null,
    });
    states.DeleteAuditBucketVersions = {
      ...deleteObjects('$.bucketVersions.Versions', 'DeleteExactVersion'),
      Next: 'DeleteAuditBucketMarkers',
    };
    states.DeleteAuditBucketMarkers = {
      ...deleteObjects('$.bucketVersions.DeleteMarkers', 'DeleteExactMarker'),
      Next: 'DeleteExactAuditBucket',
    };
    states.DeleteExactAuditBucket = {
      Type: 'Task',
      Resource: sdkIntegration('s3', 'deleteBucket'),
      Parameters: { 'Bucket.$': physicalId },
      ResultPath: null,
      Next: next,
      Catch: [{ ErrorEquals: ['States.ALL'], ResultPath: '$.error', Next: 'JanitorFailed' }],
    };
  });
  states.JanitorSucceeded = { Type: 'Succeed' };
  states.JanitorFailed = { Type: 'Fail', Error: 'MXMED_C3_JANITOR_FAIL_CLOSED' };
  return {
    Comment: 'MXMed exact-scope C3 teardown janitor',
    StartAt: 'ListRunnerTasks',
    States: states,
  };
}

/** Automatic teardown at +22h with an independent +24h self-deletion hard cap. */
export class MxMedC3JanitorStack extends BaseMxMedStack {
  public readonly stateMachine: CfnStateMachine;

  public constructor(scope: Construct, id: string, props: MxMedC3JanitorStackProps) {
    super(scope, id, {
      ...props,
      component: 'c3-janitor',
      description: 'MXMed C3 one-time teardown scheduler, state machine and USD 5 budget.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'not-required' },
    });

    const runId = this.parameter('RunId', '^c3-[a-z0-9][a-z0-9-]{5,62}$');
    const expiresAt = this.parameter(
      'ExpiresAtUtc',
      '^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$',
    );
    const failSafeExpression = this.parameter(
      'FailSafeScheduleExpression',
      '^at\\(20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\\)$',
    );
    const deleteJanitorExpression = this.parameter(
      'JanitorDeleteScheduleExpression',
      '^at\\(20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\\)$',
    );
    const budgetTopicArn = this.parameter(
      'BudgetNotificationTopicArn',
      '^arn:[^:]+:sns:[^:]+:[0-9]{12}:[A-Za-z0-9_-]+$',
    );
    const permissionBoundaryArn = this.parameter(
      'C3PermissionBoundaryArn',
      '^arn:aws:iam::875691018466:policy/MXMed-C3-Staging-PermissionBoundary$',
    );
    Tags.of(this).add('Phase', 'C3');
    Tags.of(this).add('RunId', runId.valueAsString);
    Tags.of(this).add('ExpiresAt', expiresAt.valueAsString);

    const stateRole = this.createRole(
      'JanitorStateMachineRole',
      'mxmed-stg-c3-janitor-state-machine',
      'states.amazonaws.com',
      permissionBoundaryArn.valueAsString,
    );
    const schedulerRole = this.createRole(
      'JanitorSchedulerRole',
      'mxmed-stg-c3-janitor-scheduler',
      'scheduler.amazonaws.com',
      permissionBoundaryArn.valueAsString,
    );
    const statePolicy = new CfnPolicy(this, 'JanitorStateMachinePolicy', {
      policyName: 'mxmed-stg-c3-janitor-exact-scope',
      roles: [stateRole.ref],
      policyDocument: {
        Version: '2012-10-17',
        Statement: [
          {
            Sid: 'ExplicitProductionDeny',
            Effect: 'Deny',
            Action: '*',
            Resource: [
              `arn:${this.partition}:cloudformation:*:${this.account}:stack/mxmed-prd-*/*`,
              `arn:${this.partition}:secretsmanager:*:${this.account}:secret:/mxmed/production/*`,
              `arn:${this.partition}:ecr:*:${this.account}:repository/mxmed-prd-*`,
            ],
          },
          {
            Sid: 'DeleteOnlyExactC3StagingStacks',
            Effect: 'Allow',
            Action: [
              'cloudformation:DeleteStack',
              'cloudformation:DescribeStacks',
              'cloudformation:DescribeStackResource',
            ],
            Resource: MXMED_C3_DELETE_ORDER.map(
              (name) =>
                `arn:${this.partition}:cloudformation:${this.region}:${this.account}:stack/${name}/*`,
            ),
          },
          {
            Sid: 'DeleteOnlyReviewedStagingSecrets',
            Effect: 'Allow',
            Action: ['secretsmanager:DeleteSecret'],
            Resource: [
              `arn:${this.partition}:secretsmanager:${this.region}:${this.account}:secret:/mxmed/staging/application/session-*`,
              `arn:${this.partition}:secretsmanager:${this.region}:${this.account}:secret:/mxmed/staging/providers/stripe/*`,
              `arn:${this.partition}:secretsmanager:${this.region}:${this.account}:secret:/mxmed/staging/providers/ai/api-key-*`,
            ],
          },
          {
            Sid: 'DeleteOnlyReviewedStagingLogsAndRepository',
            Effect: 'Allow',
            Action: ['logs:DeleteLogGroup', 'ecr:DeleteRepository'],
            Resource: [
              `arn:${this.partition}:logs:${this.region}:${this.account}:log-group:/mxmed/staging/*`,
              `arn:${this.partition}:ecr:${this.region}:${this.account}:repository/mxmed-stg-application`,
            ],
          },
          {
            Sid: 'ScheduleOnlyTaggedStagingKeys',
            Effect: 'Allow',
            Action: ['kms:ScheduleKeyDeletion'],
            Resource: `arn:${this.partition}:kms:${this.region}:${this.account}:key/*`,
            Condition: { StringEquals: { 'aws:ResourceTag/Environment': 'staging' } },
          },
          {
            Sid: 'EmptyAndDeleteOnlyTaggedStagingAuditBucket',
            Effect: 'Allow',
            Action: ['s3:DeleteBucket', 's3:ListBucketVersions', 's3:DeleteObjectVersion'],
            Resource: [`arn:${this.partition}:s3:::*`, `arn:${this.partition}:s3:::*/*`],
            Condition: { StringEquals: { 's3:ResourceTag/Environment': 'staging' } },
          },
          {
            Sid: 'ListOnlyRegionalC3RunnerTasks',
            Effect: 'Allow',
            Action: ['ecs:ListTasks'],
            Resource: '*',
            Condition: { StringEquals: { 'aws:RequestedRegion': props.config.primaryRegion } },
          },
          {
            Sid: 'StopOnlyC3RunnerClusterTasks',
            Effect: 'Allow',
            Action: ['ecs:StopTask'],
            Resource: `arn:${this.partition}:ecs:${this.region}:${this.account}:task/mxmed-stg-c3-runner/*`,
          },
          {
            Sid: 'WriteJanitorExecutionLogs',
            Effect: 'Allow',
            Action: [
              'logs:CreateLogDelivery',
              'logs:GetLogDelivery',
              'logs:UpdateLogDelivery',
              'logs:DeleteLogDelivery',
              'logs:ListLogDeliveries',
              'logs:PutResourcePolicy',
              'logs:DescribeResourcePolicies',
              'logs:DescribeLogGroups',
            ],
            Resource: '*',
          },
        ],
      },
    });

    const logGroup = new CfnLogGroup(this, 'JanitorLogGroup', {
      logGroupName: '/mxmed/staging/c3-janitor',
      retentionInDays: 7,
    });
    logGroup.applyRemovalPolicy(RemovalPolicy.DESTROY);
    this.stateMachine = new CfnStateMachine(this, 'JanitorStateMachine', {
      stateMachineName: 'mxmed-stg-c3-janitor',
      stateMachineType: 'STANDARD',
      roleArn: stateRole.attrArn,
      definitionString: JSON.stringify(buildJanitorDefinition()),
      loggingConfiguration: {
        destinations: [{ cloudWatchLogsLogGroup: { logGroupArn: logGroup.attrArn } }],
        includeExecutionData: false,
        level: 'ERROR',
      },
      tracingConfiguration: { enabled: false },
    });
    this.stateMachine.addDependency(statePolicy);
    this.stateMachine.addDependency(logGroup);

    const schedulerPolicy = new CfnPolicy(this, 'JanitorSchedulerPolicy', {
      policyName: 'mxmed-stg-c3-janitor-invoke-only',
      roles: [schedulerRole.ref],
      policyDocument: {
        Version: '2012-10-17',
        Statement: [
          {
            Effect: 'Allow',
            Action: ['states:StartExecution'],
            Resource: this.stateMachine.attrArn,
          },
          {
            Effect: 'Allow',
            Action: ['cloudformation:DeleteStack'],
            Resource: `arn:${this.partition}:cloudformation:${this.region}:${this.account}:stack/mxmed-stg-c3-janitor/*`,
          },
        ],
      },
    });
    const failSafeSchedule = new CfnSchedule(this, 'FailSafeSchedule', {
      name: 'mxmed-stg-c3-failsafe',
      description: 'Starts exact-scope cleanup at first resource creation plus 22 hours.',
      scheduleExpression: failSafeExpression.valueAsString,
      flexibleTimeWindow: { mode: 'OFF' },
      target: {
        arn: this.stateMachine.attrArn,
        roleArn: schedulerRole.attrArn,
        input: Fn.sub('{"run_id":"${RunId}","retained":{},"deletions":{}}', {
          RunId: runId.valueAsString,
        }),
        retryPolicy: { maximumEventAgeInSeconds: 300, maximumRetryAttempts: 1 },
      },
    });
    failSafeSchedule.addPropertyOverride('ActionAfterCompletion', 'DELETE');
    failSafeSchedule.addDependency(schedulerPolicy);
    const janitorDeleteSchedule = new CfnSchedule(this, 'JanitorDeleteSchedule', {
      name: 'mxmed-stg-c3-janitor-hard-cap',
      description: 'Deletes the Janitor stack at the independent plus-24-hour hard cap.',
      scheduleExpression: deleteJanitorExpression.valueAsString,
      flexibleTimeWindow: { mode: 'OFF' },
      target: {
        arn: ['arn:aws:scheduler:::aws', 'sdk:cloudformation:deleteStack'].join('-'),
        roleArn: schedulerRole.attrArn,
        input: '{"StackName":"mxmed-stg-c3-janitor"}',
        retryPolicy: { maximumEventAgeInSeconds: 300, maximumRetryAttempts: 1 },
      },
    });
    janitorDeleteSchedule.addPropertyOverride('ActionAfterCompletion', 'DELETE');
    janitorDeleteSchedule.addDependency(schedulerPolicy);

    const subscribers = [{ subscriptionType: 'SNS', address: budgetTopicArn.valueAsString }];
    new CfnBudget(this, 'C3CostBudget', {
      budget: {
        budgetName: Fn.sub('mxmed-stg-c3-${RunId}', { RunId: runId.valueAsString }),
        budgetType: 'COST',
        timeUnit: 'MONTHLY',
        budgetLimit: { amount: MXMED_C3_COST_CAP_USD, unit: 'USD' },
        costFilters: { TagKeyValue: ['user:Phase$C3'] },
      },
      notificationsWithSubscribers: [1, 3, 5].map((threshold) => ({
        notification: {
          comparisonOperator: 'GREATER_THAN',
          notificationType: 'ACTUAL',
          threshold,
          thresholdType: 'ABSOLUTE_VALUE',
        },
        subscribers,
      })),
    });
  }

  private parameter(id: string, allowedPattern: string): CfnParameter {
    return new CfnParameter(this, id, { type: 'String', allowedPattern });
  }

  private createRole(
    id: string,
    roleName: string,
    service: string,
    permissionBoundaryArn: string,
  ): CfnRole {
    return new CfnRole(this, id, {
      roleName,
      permissionsBoundary: permissionBoundaryArn,
      assumeRolePolicyDocument: {
        Version: '2012-10-17',
        Statement: [
          {
            Effect: 'Allow',
            Principal: { Service: service },
            Action: 'sts:AssumeRole',
            Condition: { StringEquals: { 'aws:SourceAccount': this.account } },
          },
        ],
      },
    });
  }
}
