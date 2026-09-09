import { App, Stack } from 'aws-cdk-lib';
import { Annotations, Template } from 'aws-cdk-lib/assertions';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
type Value =
  | string
  | number
  | boolean
  | null
  | Value[]
  | {
      [key: string]: Value;
    };
type ObjectValue = Record<string, Value>;
interface Resource {
  Type: string;
  Properties: ObjectValue;
}
interface Document {
  Resources?: Record<string, Resource>;
  Parameters?: ObjectValue;
  Outputs?: Record<
    string,
    {
      Value: Value;
      Export?: {
        Name: Value;
      };
    }
  >;
}
function scalar(value: unknown): string {
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  throw new Error('expected scalar: ' + JSON.stringify(value));
}
function required<T>(value: T | undefined): T {
  if (value === undefined) throw new Error('required test value missing');
  return value;
}
const object = (v: Value | undefined): ObjectValue => v as ObjectValue;
const array = (v: Value | undefined): Value[] =>
  Array.isArray(v) ? v : v === undefined ? [] : [v];
const matches = (pattern: string, value: string) =>
  new RegExp('^' + pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replaceAll('*', '.*') + '$').test(
    value,
  );
// Resolve only the CloudFormation forms exercised here; unknown forms fail closed.
// Synthetic ARNs model stack references, never a physical AWS inventory.
function resolver(documents: Document[]) {
  const resources = Object.assign({}, ...documents.map((d) => d.Resources)) as Record<
    string,
    Resource
  >;
  function resolve(v: Value): Value {
    if (Array.isArray(v)) return v.map(resolve);
    if (v === null || typeof v !== 'object') return v;
    if (v['Fn::Split']) {
      const [separator, source] = array(v['Fn::Split']);
      return scalar(resolve(required(source))).split(scalar(separator));
    }
    if (v['Fn::Select']) {
      const [index, values] = array(v['Fn::Select']);
      return required(array(resolve(required(values)))[Number(index)]);
    }
    if (v['Fn::Join']) {
      const [sep, parts] = array(v['Fn::Join']);
      return array(parts)
        .map((x) => scalar(resolve(x)))
        .join(scalar(sep));
    }
    if (v['Fn::ImportValue']) {
      const name = resolve(v['Fn::ImportValue']);
      for (const d of documents)
        for (const out of Object.values(d.Outputs ?? {}))
          if (out.Export && resolve(out.Export.Name) === name) return resolve(out.Value);
      throw Error('unresolved import ' + scalar(name));
    }
    if (v.Ref) {
      const id = scalar(v.Ref);
      if (id === 'AWS::Partition') return 'aws';
      if (id === 'AWS::AccountId') return '111122223333';
      if (id === 'AWS::Region') return 'mx-central-1';
      if (id === 'AWS::URLSuffix') return 'amazonaws.com';
      if (id === 'ApplicationImageDigest') return 'sha256:' + 'a'.repeat(64);
      const r = resources[id];
      if (!r) throw Error('unresolved Ref ' + id);
      if (r.Type === 'AWS::IAM::ManagedPolicy')
        return (
          'arn:aws:iam::111122223333:policy/' +
          scalar(resolve(required(r.Properties.ManagedPolicyName)))
        );
      if (r.Type === 'AWS::ECR::Repository') return resolve(required(r.Properties.RepositoryName));
      if (r.Type === 'AWS::IAM::Role') return resolve(required(r.Properties.RoleName));
      if (r.Type === 'AWS::ECS::TaskDefinition')
        return (
          'arn:aws:ecs:mx-central-1:111122223333:task-definition/' +
          scalar(resolve(required(r.Properties.Family))) +
          ':1'
        );
      if (r.Type === 'AWS::SecretsManager::Secret')
        return (
          'arn:aws:secretsmanager:mx-central-1:111122223333:secret:' +
          scalar(resolve(required(r.Properties.Name))) +
          '-ABC123'
        );
      if (r.Type === 'AWS::EC2::Subnet') return 'subnet-' + id;
      if (r.Type === 'AWS::Logs::LogGroup') return resolve(required(r.Properties.LogGroupName));
      throw Error('unhandled Ref ' + id);
    }
    if (v['Fn::GetAtt']) {
      const attributes = array(v['Fn::GetAtt']).map(scalar);
      const id = required(attributes[0]);
      const attribute = required(attributes[1]);
      const r = resources[required(id)];
      if (!r) throw Error('unresolved GetAtt ' + id);
      if (r.Type === 'AWS::IAM::Role')
        return 'arn:aws:iam::111122223333:role/' + scalar(resolve(required(r.Properties.RoleName)));
      if (r.Type === 'AWS::ECS::Cluster')
        return (
          'arn:aws:ecs:mx-central-1:111122223333:cluster/' +
          scalar(resolve(required(r.Properties.ClusterName)))
        );
      if (r.Type === 'AWS::KMS::Key') return 'arn:aws:kms:mx-central-1:111122223333:key/' + id;
      if (r.Type === 'AWS::ECR::Repository')
        return attribute === 'Arn'
          ? 'arn:aws:ecr:mx-central-1:111122223333:repository/' +
              scalar(r.Properties.RepositoryName)
          : '111122223333.dkr.ecr.mx-central-1.amazonaws.com/' +
              scalar(r.Properties.RepositoryName);
      if (r.Type === 'AWS::EC2::SecurityGroup') return 'sg-' + id;
      if (r.Type === 'AWS::RDS::DBInstance')
        return attribute === 'Endpoint.Port' ? '3306' : 'fixture.internal';
      throw Error('unhandled GetAtt ' + id + ' ' + attribute);
    }
    return Object.fromEntries(Object.entries(v).map(([k, x]) => [k, resolve(x)]));
  }
  return resolve;
}
function allows(
  doc: ObjectValue,
  action: string,
  resource: string,
  context: Record<string, string>,
): boolean {
  const applicable = array(doc.Statement)
    .map(object)
    .filter(
      (s) =>
        array(s.Action).some((a) => matches(scalar(a), action)) &&
        array(s.Resource).some((r) => matches(scalar(r), resource)) &&
        Object.entries(object(s.Condition ?? {})).every(([op, values]) =>
          Object.entries(object(values)).every(([key, expected]) =>
            ['StringEquals', 'ArnEquals'].includes(op)
              ? array(expected).includes(context[key] ?? '')
              : ['StringLike', 'ArnLike'].includes(op)
                ? array(expected).some((e) => matches(scalar(e), context[key] ?? ''))
                : false,
          ),
        ),
    );
  return (
    !applicable.some((s) => s.Effect === 'Deny') && applicable.some((s) => s.Effect === 'Allow')
  );
}
for (const environment of ['staging', 'production'] as const)
  for (const profile of ['launch-lean-v1', 'production-standard-v1', 'scale-ready-v1'] as const)
    for (const mode of [
      'disabled-v1',
      'registry-only-v1',
      'tasks-ready-v1',
      'service-enabled-v1',
    ] as const) {
      if (environment === 'staging' && profile !== 'launch-lean-v1') continue;
      test(`${environment} ${profile} ${mode}: runtime contract and effective authority`, () => {
        const active = mode === 'tasks-ready-v1' || mode === 'service-enabled-v1';
        const stage = new MxMedEnvironmentStage(
          new App({ analyticsReporting: false }),
          `Jobs${environment}${mode}`,
          {
            config: getEnvironmentConfig(
              environment,
              profile,
              mode,
              active ? 'directory-core-v1' : undefined,
            ),
          },
        );
        stage.synth();
        const stacks = stage.node.children.filter((c): c is Stack => Stack.isStack(c));
        const docs = stacks.map((s) => Template.fromStack(s).toJSON() as Document);
        const jobs = Template.fromStack(stage.jobsStack).toJSON() as Document;
        const security = Template.fromStack(stage.securityStack).toJSON() as Document;
        const compute = Template.fromStack(stage.computeStack).toJSON() as Document;
        const network = Template.fromStack(stage.networkStack).toJSON() as Document;
        const resolve = resolver(docs);
        const ofType = (d: Document, type: string) =>
          Object.values(d.Resources ?? {}).filter((r) => r.Type === type);
        expect(ofType(jobs, 'AWS::ECS::TaskDefinition')).toHaveLength(active ? 1 : 0);
        expect(ofType(jobs, 'AWS::Scheduler::Schedule')).toHaveLength(active ? 1 : 0);
        expect(ofType(jobs, 'AWS::ECS::Service')).toHaveLength(0);
        expect(ofType(jobs, 'AWS::ECS::Cluster')).toHaveLength(0);
        expect(ofType(jobs, 'AWS::ECR::Repository')).toHaveLength(0);
        expect(stage.securityStack.dependencies).not.toContain(stage.jobsStack);
        Annotations.fromStack(stage.securityStack).hasNoError('*', '*');
        if (!active) {
          expect(Object.keys(jobs.Resources ?? {})).toHaveLength(0);
          return;
        }
        expect(
          docs
            .flatMap((d) => Object.keys(d.Parameters ?? {}))
            .filter((p) => p === 'ApplicationImageDigest'),
        ).toHaveLength(1);
        const task = object(
          resolve(required(ofType(jobs, 'AWS::ECS::TaskDefinition')[0]).Properties),
        );
        expect(task).toMatchObject({
          Cpu: '512',
          Memory: '1024',
          NetworkMode: 'awsvpc',
          RequiresCompatibilities: ['FARGATE'],
          RuntimePlatform: { CpuArchitecture: 'X86_64', OperatingSystemFamily: 'LINUX' },
        });
        const container = object(array(task.ContainerDefinitions)[0]);
        expect(container).toMatchObject({
          Command: [
            'php',
            '/var/www/html/modules/media/bin/submit-inactive-review-batches.php',
            '100',
          ],
          WorkingDirectory: '/var/www/html',
          User: 'www-data',
          ReadonlyRootFilesystem: true,
          Privileged: false,
          Interactive: false,
          PseudoTerminal: false,
        });
        expect(container.PortMappings).toBeUndefined();
        expect(container.HealthCheck).toBeUndefined();
        const appTask = required(
          ofType(compute, 'AWS::ECS::TaskDefinition').find((r) =>
            array(r.Properties.ContainerDefinitions).some((c) => object(c).Name === 'app'),
          ),
        );
        expect(container.Image).toEqual(
          resolve(required(object(array(appTask.Properties.ContainerDefinitions)[0]).Image)),
        );
        expect(
          array(container.Environment)
            .map((e) => object(e).Name)
            .sort(),
        ).toEqual(['MXMED_DB_HOST', 'MXMED_DB_NAME', 'MXMED_DB_PORT']);
        expect(array(container.Secrets).map((e) => object(e).Name)).toEqual([
          'MXMED_DB_USER',
          'MXMED_DB_PASS',
        ]);
        for (const secret of array(container.Secrets))
          expect(scalar(object(secret).ValueFrom)).toContain(
            `/mxmed/${environment}/application/database-user-ABC123:`,
          );
        expect(JSON.stringify(task)).not.toMatch(
          /mxmed_admin|mxmed_app|MasterUserSecret|DB_PASSWORD|DB_USERNAME/,
        );
        const schedule = object(
          resolve(required(ofType(jobs, 'AWS::Scheduler::Schedule')[0]).Properties),
        );
        expect(schedule).toMatchObject({
          State: 'DISABLED',
          ScheduleExpression: 'rate(5 minutes)',
          FlexibleTimeWindow: { Mode: 'OFF' },
        });
        const target = object(schedule.Target),
          ecs = object(target.EcsParameters);
        expect(target.RetryPolicy).toEqual({
          MaximumRetryAttempts: 2,
          MaximumEventAgeInSeconds: 900,
        });
        expect(target.Input).toBeUndefined();
        expect(target.DeadLetterConfig).toBeUndefined();
        expect(ecs).toMatchObject({
          LaunchType: 'FARGATE',
          PlatformVersion: '1.4.0',
          TaskCount: 1,
        });
        const net = object(object(ecs.NetworkConfiguration).AwsvpcConfiguration);
        expect(net).toEqual({
          AssignPublicIp: 'DISABLED',
          Subnets: stage.networkStack.privateAppSubnets.map((s) =>
            resolve(stage.jobsStack.resolve(s.subnetId) as Value),
          ),
          SecurityGroups: [
            resolve(
              stage.jobsStack.resolve(
                stage.networkStack.applicationSecurityGroup.securityGroupId,
              ) as Value,
            ),
          ],
        });
        const egress = ofType(network, 'AWS::EC2::SecurityGroupEgress').map((r) =>
          object(resolve(r.Properties)),
        );
        expect(egress).toContainEqual(
          expect.objectContaining({
            GroupId: array(net.SecurityGroups)[0],
            DestinationSecurityGroupId: resolve(
              stage.jobsStack.resolve(
                stage.networkStack.databaseSecurityGroup.securityGroupId,
              ) as Value,
            ),
            FromPort: 3306,
            ToPort: 3306,
          }),
        );
        expect(egress).toContainEqual(
          expect.objectContaining({
            GroupId: array(net.SecurityGroups)[0],
            CidrIp: '0.0.0.0/0',
            FromPort: 443,
            ToPort: 443,
          }),
        );
        expect(ofType(network, 'AWS::EC2::NatGateway').length).toBeGreaterThan(0);
        const schedulerPolicy = required(
          ofType(jobs, 'AWS::IAM::Policy').find((r) =>
            scalar(r.Properties.PolicyName).includes('scheduler-invocation'),
          ),
        );
        const executionPolicy = required(
          ofType(jobs, 'AWS::IAM::Policy').find((r) =>
            scalar(r.Properties.PolicyName).includes('job-startup'),
          ),
        );
        const managed = (prefix: string) =>
          object(
            resolve(
              required(
                required(
                  Object.entries(security.Resources ?? {}).find(([id]) => id.startsWith(prefix)),
                )[1].Properties.PolicyDocument,
              ),
            ),
          );
        const jobRoles = ofType(security, 'AWS::IAM::Role').map((r) =>
          object(resolve(r.Properties)),
        );
        for (const [roleArn, expected] of [
          [task.TaskRoleArn, stage.securityStack.jobsTaskRole.roleArn],
          [task.ExecutionRoleArn, stage.securityStack.jobsExecutionRole.roleArn],
        ]) {
          expect(roleArn).toEqual(resolve(stage.jobsStack.resolve(expected) as Value));
          const role = required(
            jobRoles.find((r) => scalar(roleArn).endsWith('/' + scalar(r.RoleName))),
          );
          expect(role.Policies).toBeUndefined();
          expect(role.ManagedPolicyArns).toBeUndefined();
          expect(role.PermissionsBoundary).toEqual(
            resolve(
              stage.jobsStack.resolve(
                stage.securityStack.workloadBoundary.managedPolicyArn,
              ) as Value,
            ),
          );
        }
        expect(resolve(required(executionPolicy.Properties.Roles))).toEqual([
          resolve(stage.jobsStack.resolve(stage.securityStack.jobsExecutionRole.roleName) as Value),
        ]);
        const schedulerRole = required(ofType(jobs, 'AWS::IAM::Role')[0]);
        expect(ofType(jobs, 'AWS::IAM::Role')).toHaveLength(1);
        expect(resolve(required(schedulerRole.Properties.PermissionsBoundary))).toEqual(
          resolve(
            stage.jobsStack.resolve(
              stage.securityStack.schedulerInvocationBoundary.managedPolicyArn,
            ) as Value,
          ),
        );
        const identity = object(resolve(required(schedulerPolicy.Properties.PolicyDocument)));
        const boundary = managed('SchedulerInvocationBoundary');
        const taskArn = scalar(ecs.TaskDefinitionArn),
          clusterArn = scalar(target.Arn);
        const passContext = { 'iam:PassedToService': 'ecs-tasks.amazonaws.com' };
        for (const doc of [identity, boundary]) {
          if (!allows(doc, 'ecs:RunTask', taskArn, { 'ecs:cluster': clusterArn }))
            throw Error(JSON.stringify({ action: 'ecs:RunTask', taskArn, clusterArn, doc }));
          expect(
            allows(doc, 'ecs:RunTask', taskArn, { 'ecs:cluster': clusterArn + '-other' }),
          ).toBe(false);
          for (const role of [scalar(task.TaskRoleArn), scalar(task.ExecutionRoleArn)]) {
            if (!allows(doc, 'iam:PassRole', role, passContext))
              throw Error(JSON.stringify({ action: 'iam:PassRole', role, doc }));
            expect(
              allows(doc, 'iam:PassRole', role, { 'iam:PassedToService': 'lambda.amazonaws.com' }),
            ).toBe(false);
            expect(allows(doc, 'iam:PassRole', role + '-other', passContext)).toBe(false);
          }
        }
        const startup = object(resolve(required(executionPolicy.Properties.PolicyDocument))),
          workload = managed('WorkloadBoundary');
        const context = {
          'aws:RequestedRegion': 'mx-central-1',
          'aws:ResourceTag/Environment': environment,
        };
        for (const s of array(startup.Statement).map(object))
          for (const action of array(s.Action))
            for (const resource of array(s.Resource)) {
              expect(allows(startup, scalar(action), scalar(resource), context)).toBe(true);
              if (!allows(workload, scalar(action), scalar(resource), context))
                throw Error(JSON.stringify({ action, resource, context, workload }));
            }
        expect(
          array(startup.Statement)
            .flatMap((s) => array(object(s).Action))
            .sort(),
        ).toEqual(
          [
            'ecr:GetAuthorizationToken',
            'ecr:BatchCheckLayerAvailability',
            'ecr:GetDownloadUrlForLayer',
            'ecr:BatchGetImage',
            'logs:CreateLogStream',
            'logs:PutLogEvents',
            'secretsmanager:DescribeSecret',
            'secretsmanager:GetSecretValue',
            'kms:Decrypt',
            'kms:DescribeKey',
          ].sort(),
        );
        const exactStartupResources: Record<string, Value> = {
          'ecr:GetAuthorizationToken': '*',
          'ecr:BatchGetImage': resolve(
            stage.jobsStack.resolve(
              required(stage.registryStack).applicationRepository.repositoryArn,
            ) as Value,
          ),
          'secretsmanager:GetSecretValue': resolve(
            stage.jobsStack.resolve(
              required(stage.dataStack.applicationUserSecret).secretArn,
            ) as Value,
          ),
          'kms:Decrypt': resolve(
            stage.jobsStack.resolve(stage.securityStack.secretsKey.keyArn) as Value,
          ),
          'logs:PutLogEvents': `arn:aws:logs:mx-central-1:111122223333:log-group:/mxmed/${environment}/jobs/media-review-batch-submission:*`,
        };
        for (const [action, resource] of Object.entries(exactStartupResources)) {
          const statement = required(
            array(startup.Statement)
              .map(object)
              .find((s) => array(s.Action).includes(action)),
          );
          expect(array(statement.Resource)).toEqual([resource]);
        }
        const rules = ofType(jobs, 'AWS::Events::Rule');
        expect(rules).toHaveLength(1);
        const rule = object(resolve(required(rules[0]).Properties));
        expect(rule.EventPattern).toEqual({
          source: ['aws.ecs'],
          'detail-type': ['ECS Task State Change'],
          detail: {
            lastStatus: ['STOPPED'],
            clusterArn: [clusterArn],
            taskDefinitionArn: [taskArn],
          },
        });
        expect(rule.Targets).toEqual([
          {
            Id: 'JobTaskEvents',
            Arn: `arn:aws:logs:mx-central-1:111122223333:log-group:/mxmed/${environment}/jobs/media-review-batch-task-events`,
          },
        ]);
        const logGroups = ofType(jobs, 'AWS::Logs::LogGroup');
        expect(logGroups).toHaveLength(2);
        for (const log of logGroups) {
          expect(log.Properties.KmsKeyId).toBeDefined();
          expect(log.Properties.RetentionInDays).toBe(environment === 'staging' ? 30 : 90);
        }
        const delivery = JSON.parse(
          scalar(
            resolve(
              required(
                required(ofType(jobs, 'AWS::Logs::ResourcePolicy')[0]).Properties.PolicyDocument,
              ),
            ),
          ),
        ) as ObjectValue;
        const statement = object(array(delivery.Statement)[0]);
        expect(scalar(statement.Resource)).toContain(
          `/mxmed/${environment}/jobs/media-review-batch-task-events:*`,
        );
        expect(object(object(statement.Condition).ArnEquals)['aws:SourceArn']).toBe(
          `arn:aws:events:mx-central-1:111122223333:rule/mxmed-${environment === 'staging' ? 'stg' : 'prd'}-media-review-batch-stopped`,
        );
      });
    }
