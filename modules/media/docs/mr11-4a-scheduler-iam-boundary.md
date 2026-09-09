# MR11.4A — Scheduler invocation boundary

The existing WorkloadBoundary intentionally denies `iam:*` (including
`iam:PassRole`) and does not allow `ecs:RunTask`. An identity-policy Allow cannot
bypass that boundary. WorkloadBoundary and DeploymentBoundary remain unchanged.

| Role | Boundary | Purpose |
| --- | --- | --- |
| applicationTaskRole | WorkloadBoundary | Application data access |
| jobsTaskRole | WorkloadBoundary | Job application runtime |
| jobsExecutionRole | WorkloadBoundary | ECS startup: image, logs and secret delivery; grants deferred |
| Future SchedulerInvocationRole | SchedulerInvocationBoundary | Only contracted RunTask and exact PassRole |
| Deployment role | DeploymentBoundary | Infrastructure deployment |

SecurityStack exposes `schedulerInvocationBoundary` and `jobsExecutionRole`.
The boundary is a maximum, not a grant, and no current role attaches it. The new
execution role trusts only `ecs-tasks.amazonaws.com`, uses WorkloadBoundary and
has no identity-policy grants. Scheduler does not assume either ECS job role.

The boundary permits exactly:

- `ecs:RunTask` on the current account/partition/primary region's
  `task-definition/mxmed-<stg|prd>-media-review-batch-job:*`, constrained by
  `ArnEquals ecs:cluster` to `cluster/mxmed-<stg|prd>-application-cluster`.
- `iam:PassRole` on the actual `jobsTaskRole` and `jobsExecutionRole` ARNs only,
  with `iam:PassedToService=ecs-tasks.amazonaws.com`.

The revision wildcard applies only to the exact task family. Different
families, environments, regions and accounts are outside the allowed scope.
No other actions are allowed, and no Deny shadows the intended PassRole.
AWS documents task-family resource scoping with the `ecs:cluster` condition in
[its RunTask policy example](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/security_iam_id-based-policy-examples.html).

`mxmedMediaReviewJobFamily` in `infra/aws/lib/utils/naming.ts` is the future
JobsStack family authority. `mxmedApplicationClusterName` is shared with
ComputeStack and preserves the existing cluster name. SecurityStack constructs
its ARNs without depending on ComputeStack or JobsStack. It also synthesizes
when compute is disabled; no existing cluster is required at synthesis time.

MR11.4B must create the invocation role in JobsStack with Scheduler trust and
this dedicated boundary, then add the identity policy for the exact task
revision, cluster and two role ARNs. It must attach scoped startup permissions
to jobsExecutionRole, without moving the ECS roles off WorkloadBoundary.

No task definition, schedule, invocation role, operational log, DLQ or effective
RunTask authority is created here. No C3 runner grant, deployment authority,
database, media behavior or Director data is changed. No physical AWS operation
is performed. Runtime wiring, disabled scheduling and local executor validation
remain MR11.4B; physical activation is separately authorized work.

Validation: TypeScript build/lint, focused boundary assertions, relevant security,
ComputeStack and C3 regression suites pass. Staging, production and C3 synth
pass with all existing aspects enabled. Before/after template comparisons keep
all existing Security resources identical in both environments and all 106
existing C3 resources identical. C3's inventory increases to 108 resources
(three managed policies and eleven roles); only inventory expectations change,
not runner permissions. No Security → Compute/Jobs dependency is introduced.
