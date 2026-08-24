import { CfnOutput, CfnParameter, Fn, RemovalPolicy, Tags } from 'aws-cdk-lib';
import type { ISecurityGroup, ISubnet } from 'aws-cdk-lib/aws-ec2';
import { CfnCluster, CfnTaskDefinition } from 'aws-cdk-lib/aws-ecs';
import type { IRepository } from 'aws-cdk-lib/aws-ecr';
import { CfnPolicy, CfnRole } from 'aws-cdk-lib/aws-iam';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import { CfnLogGroup } from 'aws-cdk-lib/aws-logs';
import type { ISecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import { MXMED_C3_EXPECTED_HEAD, MXMED_C3_RUNNER_CONTRACT } from '../constructs/c3-runner-contract';

export interface MxMedC3RunnerStackProps extends MxMedContractStackProps {
  readonly privateAppSubnets: readonly ISubnet[];
  readonly applicationSecurityGroup: ISecurityGroup;
  readonly sessionEndpoint: string;
  readonly sessionAuthSecret: ISecret;
  readonly secretsKey: IKey;
  readonly auditKey: IKey;
  readonly applicationRepository: IRepository;
}

/** One-shot, digest-pinned C3 validation task. It intentionally creates no ECS service. */
export class MxMedC3RunnerStack extends BaseMxMedStack {
  public readonly cluster: CfnCluster;
  public readonly taskDefinition: CfnTaskDefinition;
  public readonly runnerImageDigest: CfnParameter;

  public constructor(scope: Construct, id: string, props: MxMedC3RunnerStackProps) {
    super(scope, id, {
      ...props,
      component: 'c3-runner',
      description: 'MXMed C3 ephemeral one-shot Fargate validation runner.',
      metadata: { dataClassification: 'sensitive', criticality: 'high', backup: 'not-required' },
    });

    const runId = new CfnParameter(this, 'RunId', {
      type: 'String',
      allowedPattern: '^c3-[a-z0-9][a-z0-9-]{5,62}$',
      description: 'Director-approved immutable C3 execution run identifier.',
    });
    const expiresAt = new CfnParameter(this, 'ExpiresAtUtc', {
      type: 'String',
      allowedPattern: '^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$',
      description: 'Hard-cap expiry instant from the sealed run manifest.',
    });
    this.runnerImageDigest = new CfnParameter(this, 'RunnerImageDigest', {
      type: 'String',
      allowedPattern: '^sha256:[a-f0-9]{64}$',
      description: 'ECR-resolved immutable image digest; tags and latest are rejected.',
    });
    const permissionBoundaryArn = new CfnParameter(this, 'C3PermissionBoundaryArn', {
      type: 'String',
      allowedPattern: '^arn:aws:iam::875691018466:policy/MXMed-C3-Staging-PermissionBoundary$',
      description: 'Pre-existing Director-approved C3 staging permission boundary.',
    });
    Tags.of(this).add('Phase', 'C3');
    Tags.of(this).add('RunId', runId.valueAsString);
    Tags.of(this).add('ExpiresAt', expiresAt.valueAsString);

    this.cluster = new CfnCluster(this, 'RunnerCluster', {
      clusterName: MXMED_C3_RUNNER_CONTRACT.clusterName,
      clusterSettings: [{ name: 'containerInsights', value: 'disabled' }],
      capacityProviders: ['FARGATE'],
    });
    this.cluster.applyRemovalPolicy(RemovalPolicy.DESTROY);

    const taskRole = this.createRole(
      'RunnerTaskRole',
      'mxmed-stg-c3-runner-task',
      permissionBoundaryArn.valueAsString,
    );
    const executionRole = this.createRole(
      'RunnerExecutionRole',
      'mxmed-stg-c3-runner-execution',
      permissionBoundaryArn.valueAsString,
    );
    const taskPolicy = new CfnPolicy(this, 'RunnerTaskPolicy', {
      policyName: 'mxmed-stg-c3-runner-task-exact-secret',
      roles: [taskRole.ref],
      policyDocument: {
        Version: '2012-10-17',
        Statement: [
          {
            Sid: 'ReadOnlyTheSessionStoreCredential',
            Effect: 'Allow',
            Action: ['secretsmanager:GetSecretValue', 'secretsmanager:DescribeSecret'],
            Resource: props.sessionAuthSecret.secretArn,
          },
          {
            Sid: 'DecryptOnlyThatSecretsManagerValue',
            Effect: 'Allow',
            Action: ['kms:Decrypt'],
            Resource: props.secretsKey.keyArn,
            Condition: {
              StringEquals: {
                'kms:ViaService': `secretsmanager.${props.config.primaryRegion}.amazonaws.com`,
                'kms:EncryptionContext:SecretARN': props.sessionAuthSecret.secretArn,
              },
            },
          },
        ],
      },
    });
    const executionPolicy = new CfnPolicy(this, 'RunnerExecutionPolicy', {
      policyName: 'mxmed-stg-c3-runner-execution',
      roles: [executionRole.ref],
      policyDocument: {
        Version: '2012-10-17',
        Statement: [
          {
            Sid: 'EcrAuthorizationTokenOnly',
            Effect: 'Allow',
            Action: ['ecr:GetAuthorizationToken'],
            Resource: '*',
          },
          {
            Sid: 'PullOnlyTheC3ApplicationImage',
            Effect: 'Allow',
            Action: [
              'ecr:BatchCheckLayerAvailability',
              'ecr:GetDownloadUrlForLayer',
              'ecr:BatchGetImage',
            ],
            Resource: props.applicationRepository.repositoryArn,
          },
          {
            Sid: 'WriteOnlyTheC3RunnerLogGroup',
            Effect: 'Allow',
            Action: ['logs:CreateLogStream', 'logs:PutLogEvents'],
            Resource: Fn.join('', [
              'arn:',
              this.partition,
              ':logs:',
              this.region,
              ':',
              this.account,
              ':log-group:',
              MXMED_C3_RUNNER_CONTRACT.logGroupName,
              ':*',
            ]),
          },
        ],
      },
    });

    const logGroup = new CfnLogGroup(this, 'RunnerLogGroup', {
      logGroupName: MXMED_C3_RUNNER_CONTRACT.logGroupName,
      retentionInDays: 7,
      kmsKeyId: props.auditKey.keyArn,
    });
    logGroup.applyRemovalPolicy(RemovalPolicy.DESTROY);

    this.taskDefinition = new CfnTaskDefinition(this, 'RunnerTaskDefinition', {
      family: MXMED_C3_RUNNER_CONTRACT.taskFamily,
      cpu: String(MXMED_C3_RUNNER_CONTRACT.cpuUnits),
      memory: String(MXMED_C3_RUNNER_CONTRACT.memoryMiB),
      networkMode: 'awsvpc',
      requiresCompatibilities: ['FARGATE'],
      runtimePlatform: { cpuArchitecture: 'X86_64', operatingSystemFamily: 'LINUX' },
      taskRoleArn: taskRole.attrArn,
      executionRoleArn: executionRole.attrArn,
      containerDefinitions: [
        {
          name: 'c3-runner',
          image: Fn.join('', [
            props.applicationRepository.repositoryUri,
            '@',
            this.runnerImageDigest.valueAsString,
          ]),
          essential: true,
          readonlyRootFilesystem: true,
          user: '10001:10001',
          linuxParameters: { capabilities: { drop: ['ALL'] }, initProcessEnabled: true },
          entryPoint: ['/opt/mxmed/entrypoint.sh'],
          environment: [
            { name: 'APP_ENV', value: 'staging' },
            { name: 'SESSION_HOST', value: props.sessionEndpoint },
            { name: 'SESSION_PORT', value: String(MXMED_C3_RUNNER_CONTRACT.port) },
            { name: 'SESSION_PREFIX', value: MXMED_C3_RUNNER_CONTRACT.sessionPrefix },
            { name: 'SESSION_AUTH_SECRET_ARN', value: props.sessionAuthSecret.secretArn },
            { name: 'EXPECTED_SOURCE_HEAD', value: MXMED_C3_EXPECTED_HEAD },
            {
              name: 'C3_TASK_TIMEOUT_SECONDS',
              value: String(MXMED_C3_RUNNER_CONTRACT.taskTimeoutSeconds),
            },
          ],
          logConfiguration: {
            logDriver: 'awslogs',
            options: {
              'awslogs-group': logGroup.ref,
              'awslogs-region': this.region,
              'awslogs-stream-prefix': 'c3',
            },
          },
        },
      ],
    });
    this.taskDefinition.addDependency(taskPolicy);
    this.taskDefinition.addDependency(executionPolicy);
    this.taskDefinition.addDependency(logGroup);
    this.taskDefinition.applyRemovalPolicy(RemovalPolicy.DESTROY);

    this.output('RunnerClusterArn', this.cluster.attrArn);
    this.output('RunnerClusterName', this.cluster.ref);
    this.output('RunnerTaskDefinitionArn', this.taskDefinition.ref);
    this.output('RunnerLogGroupName', logGroup.ref);
    this.output('ApplicationSecurityGroupId', props.applicationSecurityGroup.securityGroupId);
    this.output(
      'PrivateAppSubnetIds',
      Fn.join(
        ',',
        props.privateAppSubnets.map((subnet) => subnet.subnetId),
      ),
    );
  }

  private createRole(id: string, roleName: string, permissionBoundaryArn: string): CfnRole {
    const role = new CfnRole(this, id, {
      roleName,
      permissionsBoundary: permissionBoundaryArn,
      assumeRolePolicyDocument: {
        Version: '2012-10-17',
        Statement: [
          {
            Effect: 'Allow',
            Principal: { Service: 'ecs-tasks.amazonaws.com' },
            Action: 'sts:AssumeRole',
            Condition: {
              StringEquals: { 'aws:SourceAccount': this.account },
              ArnLike: {
                'aws:SourceArn': `arn:${this.partition}:ecs:${this.region}:${this.account}:*`,
              },
            },
          },
        ],
      },
    });
    role.applyRemovalPolicy(RemovalPolicy.DESTROY);
    return role;
  }

  private output(id: string, value: string): void {
    new CfnOutput(this, id, { value });
  }
}
