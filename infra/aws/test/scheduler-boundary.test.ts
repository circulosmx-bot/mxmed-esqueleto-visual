import { ArnFormat } from 'aws-cdk-lib';
import { Annotations, Template } from 'aws-cdk-lib/assertions';
import { getEnvironmentConfig } from '../lib/config/environments';
import { mxmedApplicationClusterName, mxmedMediaReviewJobFamily } from '../lib/utils/naming';
import {
  findByLogicalId,
  policyStatements,
  properties,
  renderSecurity,
  resourcesOfType,
} from './security-test-helpers';

for (const environment of ['staging', 'production'] as const) {
  const code = environment === 'staging' ? 'stg' : 'prd';
  const opposite = code === 'stg' ? 'prd' : 'stg';
  const { stage, resources } = renderSecurity(
    getEnvironmentConfig(environment, 'launch-lean-v1', 'disabled-v1'),
  );
  const [boundaryId, boundary] = findByLogicalId(resources, 'SchedulerInvocationBoundary');
  const [workloadId, workload] = findByLogicalId(resources, 'WorkloadBoundary');
  const jobRoles = ['JobsTaskRole', 'JobsExecutionRole'].map((prefix) =>
    findByLogicalId(resources, prefix),
  );
  const statements = policyStatements(boundary);
  test(`${environment}: exact maximum actions, family, cluster and PassRole`, () => {
    expect(
      resourcesOfType(resources, 'AWS::IAM::ManagedPolicy').filter(([id]) =>
        id.startsWith('SchedulerInvocationBoundary'),
      ),
    ).toHaveLength(1);
    expect(properties(boundary).ManagedPolicyName).toBe(
      `mxmed-${code}-scheduler-invocation-boundary`,
    );
    expect(statements).toHaveLength(2);
    expect(statements.map((s) => s.Action).sort()).toEqual(['ecs:RunTask', 'iam:PassRole']);
    expect(statements.every((s) => s.Effect === 'Allow')).toBe(true);
    const run = statements.find((s) => s.Action === 'ecs:RunTask');
    expect(run?.Resource).toEqual(
      stage.securityStack.resolve(
        stage.securityStack.formatArn({
          service: 'ecs',
          region: 'mx-central-1',
          resource: 'task-definition',
          resourceName: `mxmed-${code}-media-review-batch-job:*`,
          arnFormat: ArnFormat.SLASH_RESOURCE_NAME,
        }),
      ),
    );
    expect(run?.Condition).toEqual({
      ArnEquals: {
        'ecs:cluster': stage.securityStack.resolve(
          stage.securityStack.formatArn({
            service: 'ecs',
            region: 'mx-central-1',
            resource: 'cluster',
            resourceName: `mxmed-${code}-application-cluster`,
            arnFormat: ArnFormat.SLASH_RESOURCE_NAME,
          }),
        ) as unknown,
      },
    });
    const pass = statements.find((s) => s.Action === 'iam:PassRole');
    expect(pass?.Resource).toEqual(jobRoles.map(([id]) => ({ 'Fn::GetAtt': [id, 'Arn'] })));
    expect(pass?.Condition).toEqual({
      StringEquals: { 'iam:PassedToService': 'ecs-tasks.amazonaws.com' },
    });
    expect(JSON.stringify(run)).not.toContain(`mxmed-${opposite}-`);
    expect(jobRoles.map(([, r]) => properties(r).RoleName)).toEqual([
      `mxmed-${code}-jobs-role`,
      `mxmed-${code}-jobs-execution-role`,
    ]);
    expect(mxmedMediaReviewJobFamily(code)).toBe(`mxmed-${code}-media-review-batch-job`);
    expect(mxmedApplicationClusterName(code)).toBe(`mxmed-${code}-application-cluster`);
  });
  test(`${environment}: workload deny preserved; job roles remain unprivileged ECS roles`, () => {
    const policies = policyStatements(workload);
    expect(policies.find((s) => s.Sid === 'DenyControlPlaneAndPrivilegeEscalation')).toMatchObject({
      Effect: 'Deny',
      Action: expect.arrayContaining(['iam:*', 'iam:PassRole']) as unknown,
    });
    expect(
      policies
        .filter((s) => s.Effect === 'Allow')
        .some((s) => JSON.stringify(s.Action).includes('ecs:RunTask')),
    ).toBe(false);
    for (const [, r] of jobRoles) {
      expect(properties(r).PermissionsBoundary).toEqual({ Ref: workloadId });
      expect(properties(r).AssumeRolePolicyDocument).toEqual({
        Version: '2012-10-17',
        Statement: [
          {
            Action: 'sts:AssumeRole',
            Effect: 'Allow',
            Principal: { Service: 'ecs-tasks.amazonaws.com' },
          },
        ],
      });
      expect(properties(r).Policies).toBeUndefined();
      expect(properties(r).ManagedPolicyArns).toBeUndefined();
    }
    for (const [, r] of resourcesOfType(resources, 'AWS::IAM::Role'))
      expect(properties(r).PermissionsBoundary).not.toEqual({ Ref: boundaryId });
    expect(JSON.stringify(resources)).not.toContain('scheduler.amazonaws.com');
  });
  test(`${environment}: no job resources, authority or downstream dependencies`, () => {
    expect(stage.securityStack.dependencies).not.toContain(stage.computeStack);
    expect(stage.securityStack.dependencies).not.toContain(stage.jobsStack);
    Template.fromStack(stage.jobsStack).resourceCountIs('AWS::ECS::TaskDefinition', 0);
    Template.fromStack(stage.jobsStack).resourceCountIs('AWS::Scheduler::Schedule', 0);
    for (const type of [
      'AWS::ECS::TaskDefinition',
      'AWS::Scheduler::Schedule',
      'AWS::Events::Rule',
    ])
      expect(resourcesOfType(resources, type)).toHaveLength(0);
    Annotations.fromStack(stage.securityStack).hasNoError('*', '*');
    stage.synth();
  });
}
