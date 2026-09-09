import { ArnFormat, RemovalPolicy } from 'aws-cdk-lib';
import type { ISubnet, ISecurityGroup } from 'aws-cdk-lib/aws-ec2';
import type { IRepository } from 'aws-cdk-lib/aws-ecr';
import { CfnTaskDefinition } from 'aws-cdk-lib/aws-ecs';
import type { ICluster } from 'aws-cdk-lib/aws-ecs';
import { CfnRule } from 'aws-cdk-lib/aws-events';
import {
  CfnPolicy,
  PolicyDocument,
  PolicyStatement,
  Role,
  ServicePrincipal,
} from 'aws-cdk-lib/aws-iam';
import type { IRole, IManagedPolicy } from 'aws-cdk-lib/aws-iam';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import { CfnLogGroup, CfnResourcePolicy } from 'aws-cdk-lib/aws-logs';
import { CfnSchedule } from 'aws-cdk-lib/aws-scheduler';
import type { ISecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { Construct } from 'constructs';
import { computeCreatesTasks } from '../config/compute-config';
import { mxmedMediaReviewJobFamily, mxmedName } from '../utils/naming';
import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

export interface MediaReviewJobRuntime {
  readonly cluster: ICluster;
  readonly applicationImageUri: string;
  readonly applicationRepository: IRepository;
  readonly privateAppSubnets: readonly ISubnet[];
  readonly applicationSecurityGroup: ISecurityGroup;
  readonly applicationUserSecret: ISecret;
  readonly databaseEndpoint: string;
  readonly databasePort: string;
  readonly databaseName: string;
  readonly jobsTaskRole: IRole;
  readonly jobsExecutionRole: IRole;
  readonly schedulerInvocationBoundary: IManagedPolicy;
  readonly auditKey: IKey;
  readonly secretsKey: IKey;
}
export interface MxMedJobsStackProps extends MxMedContractStackProps {
  readonly runtime?: MediaReviewJobRuntime;
}

/** One-shot runtime only; the schedule remains disabled pending physical validation. */
export class MxMedJobsStack extends BaseMxMedStack {
  public readonly taskDefinition?: CfnTaskDefinition;
  public readonly schedule?: CfnSchedule;
  public constructor(scope: Construct, id: string, props: MxMedJobsStackProps) {
    super(scope, id, {
      ...props,
      component: 'jobs',
      description: 'MXMed private periodic review batch runtime; Scheduler intentionally disabled.',
      metadata: { dataClassification: 'internal', criticality: 'medium', backup: 'not-required' },
    });
    const { config } = props;
    if (!computeCreatesTasks(config.computeActivationMode)) return;
    const r = props.runtime;
    if (!r || r.privateAppSubnets.length === 0)
      throw new Error('MXMED_JOB_RUNTIME_PREREQUISITES_REQUIRED');
    const family = mxmedMediaReviewJobFamily(config.environmentCode);
    const logName = `/mxmed/${config.environmentName}/jobs/media-review-batch-submission`;
    const eventLogName = `/mxmed/${config.environmentName}/jobs/media-review-batch-task-events`;
    const logArn = (name: string) =>
      this.formatArn({
        service: 'logs',
        resource: 'log-group',
        resourceName: name,
        arnFormat: ArnFormat.COLON_RESOURCE_NAME,
      });
    const logs = [logName, eventLogName].map((name, index) => {
      const log = new CfnLogGroup(this, index === 0 ? 'JobLogGroup' : 'TaskEventLogGroup', {
        logGroupName: name,
        kmsKeyId: r.auditKey.keyArn,
        retentionInDays: config.computeLogRetentionDays,
      });
      log.applyRemovalPolicy(RemovalPolicy.RETAIN);
      return log;
    });
    // Deterministic names, not Jobs resource references: no Security -> Jobs cycle.
    r.auditKey.addToResourcePolicy(
      new PolicyStatement({
        sid: 'AllowMediaReviewJobLogsEncryption',
        principals: [new ServicePrincipal(`logs.${config.primaryRegion}.${this.urlSuffix}`)],
        actions: [
          'kms:Encrypt',
          'kms:Decrypt',
          'kms:ReEncrypt*',
          'kms:GenerateDataKey*',
          'kms:DescribeKey',
        ],
        resources: ['*'],
        conditions: {
          ArnEquals: {
            'kms:EncryptionContext:aws:logs:arn': [logArn(logName), logArn(eventLogName)],
          },
        },
      }),
    );
    // L1 policy ownership keeps grants in Jobs instead of mutating a Security role's child policy.
    const executionPolicy = new CfnPolicy(this, 'JobsExecutionPolicy', {
      policyName: mxmedName(config.environmentCode, 'media-review-job-startup'),
      roles: [r.jobsExecutionRole.roleName],
      policyDocument: new PolicyDocument({
        statements: [
          new PolicyStatement({
            actions: ['ecr:GetAuthorizationToken'],
            resources: ['*'],
            conditions: { StringEquals: { 'aws:RequestedRegion': config.primaryRegion } },
          }),
          new PolicyStatement({
            actions: [
              'ecr:BatchCheckLayerAvailability',
              'ecr:GetDownloadUrlForLayer',
              'ecr:BatchGetImage',
            ],
            resources: [r.applicationRepository.repositoryArn],
          }),
          new PolicyStatement({
            actions: ['logs:CreateLogStream', 'logs:PutLogEvents'],
            resources: [`${logArn(logName)}:*`],
          }),
          new PolicyStatement({
            actions: ['secretsmanager:DescribeSecret', 'secretsmanager:GetSecretValue'],
            resources: [r.applicationUserSecret.secretArn],
          }),
          new PolicyStatement({
            actions: ['kms:Decrypt', 'kms:DescribeKey'],
            resources: [r.secretsKey.keyArn],
          }),
        ],
      }).toJSON() as unknown,
    });
    // Explicit Fargate definition avoids implicit role grants from container bindings.
    this.taskDefinition = new CfnTaskDefinition(this, 'MediaReviewTask', {
      family,
      cpu: '512',
      memory: '1024',
      networkMode: 'awsvpc',
      requiresCompatibilities: ['FARGATE'],
      runtimePlatform: { cpuArchitecture: 'X86_64', operatingSystemFamily: 'LINUX' },
      taskRoleArn: r.jobsTaskRole.roleArn,
      executionRoleArn: r.jobsExecutionRole.roleArn,
      containerDefinitions: [
        {
          name: 'media-review-batch',
          image: r.applicationImageUri,
          essential: true,
          command: [
            'php',
            '/var/www/html/modules/media/bin/submit-inactive-review-batches.php',
            '100',
          ],
          workingDirectory: '/var/www/html',
          user: 'www-data',
          readonlyRootFilesystem: true,
          privileged: false,
          interactive: false,
          pseudoTerminal: false,
          environment: [
            { name: 'MXMED_DB_HOST', value: r.databaseEndpoint },
            { name: 'MXMED_DB_PORT', value: r.databasePort },
            { name: 'MXMED_DB_NAME', value: r.databaseName },
          ],
          secrets: [
            { name: 'MXMED_DB_USER', valueFrom: `${r.applicationUserSecret.secretArn}:username::` },
            { name: 'MXMED_DB_PASS', valueFrom: `${r.applicationUserSecret.secretArn}:password::` },
          ],
          logConfiguration: {
            logDriver: 'awslogs',
            options: {
              'awslogs-group': logName,
              'awslogs-region': config.primaryRegion,
              'awslogs-stream-prefix': 'batch',
            },
          },
        },
      ],
    });
    this.taskDefinition.addDependency(executionPolicy);
    for (const log of logs) this.taskDefinition.addDependency(log);
    const invocationRole = new Role(this, 'SchedulerInvocationRole', {
      roleName: mxmedName(config.environmentCode, 'media-review-scheduler-role', 64),
      assumedBy: new ServicePrincipal('scheduler.amazonaws.com', {
        conditions: {
          StringEquals: { 'aws:SourceAccount': this.account },
          ArnEquals: {
            'aws:SourceArn': this.formatArn({
              service: 'scheduler',
              resource: 'schedule-group',
              resourceName: 'default',
              arnFormat: ArnFormat.SLASH_RESOURCE_NAME,
            }),
          },
        },
      }),
      permissionsBoundary: r.schedulerInvocationBoundary,
    });
    const invocationPolicy = new CfnPolicy(this, 'SchedulerInvocationPolicy', {
      policyName: mxmedName(config.environmentCode, 'media-review-scheduler-invocation'),
      roles: [invocationRole.roleName],
      policyDocument: new PolicyDocument({
        statements: [
          new PolicyStatement({
            actions: ['ecs:RunTask'],
            resources: [this.taskDefinition.ref],
            conditions: { ArnEquals: { 'ecs:cluster': r.cluster.clusterArn } },
          }),
          new PolicyStatement({
            actions: ['iam:PassRole'],
            resources: [r.jobsTaskRole.roleArn, r.jobsExecutionRole.roleArn],
            conditions: { StringEquals: { 'iam:PassedToService': 'ecs-tasks.amazonaws.com' } },
          }),
        ],
      }).toJSON() as unknown,
    });
    this.schedule = new CfnSchedule(this, 'MediaReviewSchedule', {
      name: mxmedName(config.environmentCode, 'media-review-batch-submission'),
      groupName: 'default',
      scheduleExpression: 'rate(5 minutes)',
      flexibleTimeWindow: { mode: 'OFF' },
      state: 'DISABLED',
      target: {
        arn: r.cluster.clusterArn,
        roleArn: invocationRole.roleArn,
        retryPolicy: { maximumRetryAttempts: 2, maximumEventAgeInSeconds: 900 },
        ecsParameters: {
          taskDefinitionArn: this.taskDefinition.ref,
          launchType: 'FARGATE',
          platformVersion: '1.4.0',
          taskCount: 1,
          networkConfiguration: {
            awsvpcConfiguration: {
              subnets: r.privateAppSubnets.map((s) => s.subnetId),
              securityGroups: [r.applicationSecurityGroup.securityGroupId],
              assignPublicIp: 'DISABLED',
            },
          },
        },
      },
    });
    this.schedule.addDependency(invocationPolicy);
    const stopped = new CfnRule(this, 'JobStoppedRule', {
      name: mxmedName(config.environmentCode, 'media-review-batch-stopped'),
      state: 'ENABLED',
      eventPattern: {
        source: ['aws.ecs'],
        'detail-type': ['ECS Task State Change'],
        detail: {
          lastStatus: ['STOPPED'],
          clusterArn: [r.cluster.clusterArn],
          taskDefinitionArn: [this.taskDefinition.ref],
        },
      },
      targets: [{ id: 'JobTaskEvents', arn: logArn(eventLogName) }],
    });
    const delivery = new CfnResourcePolicy(this, 'TaskEventLogDelivery', {
      policyName: mxmedName(config.environmentCode, 'media-review-task-event-delivery'),
      policyDocument: this.toJsonString({
        Version: '2012-10-17',
        Statement: [
          {
            Effect: 'Allow',
            Principal: { Service: ['events.amazonaws.com', 'delivery.logs.amazonaws.com'] },
            Action: ['logs:CreateLogStream', 'logs:PutLogEvents'],
            Resource: `${logArn(eventLogName)}:*`,
            Condition: {
              ArnEquals: {
                'aws:SourceArn': this.formatArn({
                  service: 'events',
                  resource: 'rule',
                  resourceName: mxmedName(config.environmentCode, 'media-review-batch-stopped'),
                  arnFormat: ArnFormat.SLASH_RESOURCE_NAME,
                }),
              },
              StringEquals: { 'aws:SourceAccount': this.account },
            },
          },
        ],
      }),
    });
    stopped.addDependency(delivery);
    for (const log of logs) stopped.addDependency(log);
  }
}
