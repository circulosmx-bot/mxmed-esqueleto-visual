# MR11.4B — Disabled periodic review-batch runtime

JobsStack now owns the private, one-shot Fargate runtime for automatic review-batch
submission. After 30 minutes without activity, a batch becomes eligible. The
five-minute schedule would normally submit it about 30–35 minutes after its last
activity, subject to startup time and backlog. The executor handles at most 100
batches per run; transactional locking and durable signals make retries and
concurrent invocations safe.

**The Scheduler definition is DISABLED. No physical deployment, activation,
AWS task execution or ECR image push was performed in MR11.4B.** MR12 remains
blocked; MR11.5 physical staging validation requires separate Director authorization.

## Runtime and authority

- `disabled-v1` and `registry-only-v1`: no Jobs runtime resources or runtime props
  are required. `tasks-ready-v1` and `service-enabled-v1`: the task and disabled
  schedule exist, independently of whether the application ECS Service exists.
- The existing Compute cluster and Registry application repository are reused.
  Compute exposes the resolved immutable image URI from its sole
  `ApplicationImageDigest` parameter. Both app and job consume that same URI;
  Jobs introduces no second digest input, repository or cluster.
- Task family: `mxmed-<stg|prd>-media-review-batch-job`, using the shared naming
  helper already authorized by SchedulerInvocationBoundary.
- Fargate Linux/amd64, platform 1.4.0, CPU 512, memory 1024 MiB, one task per
  invocation. User `www-data`, working directory `/var/www/html`, readonly root,
  no ports or web health check. Local execution needs no writable `/tmp` volume.
- Exact command: `php /var/www/html/modules/media/bin/submit-inactive-review-batches.php 100`.
- Environment: `MXMED_DB_HOST`, `MXMED_DB_PORT`, `MXMED_DB_NAME` from DataStack.
  `applicationUserSecret.username/password` supply `MXMED_DB_USER/PASS` through
  ECS secret references. No master credential, new secret, DB grant or schema change.
- Existing `jobsTaskRole` receives no AWS grants. Existing `jobsExecutionRole`
  receives only regional ECR authentication, exact repository pull, job-log writes,
  application-user secret reads and secrets-key decryption/description.
- SchedulerInvocationRole attaches the accepted SchedulerInvocationBoundary and
  independently grants only RunTask on the exact task revision/cluster and
  PassRole on the two existing job roles for `ecs-tasks.amazonaws.com`.
  WorkloadBoundary, DeploymentBoundary and SchedulerInvocationBoundary semantics
  remain unchanged. Tests evaluate identity/boundary intersections, including
  negative cluster, role and service cases.
- Scheduler trust restricts the account and default schedule-group ARN, following
  [AWS Scheduler's SourceArn contract](https://docs.aws.amazon.com/scheduler/latest/UserGuide/cross-service-confused-deputy-prevention.html).

## Network, scheduling and failure visibility

The target uses existing private application subnets and applicationSecurityGroup
with public IP disabled. Existing HTTPS egress through NAT supports image, secret
and log startup; the existing application-to-database SG relationship allows
TCP 3306. NetworkStack is unchanged. Tests cover staging launch-lean and all three
production capacity profiles in all four compute modes.

Scheduler uses `rate(5 minutes)`, flexible window OFF, retry attempts 2 and maximum
event age 900 seconds. There is no Scheduler command override or DLQ. Successful
RunTask delivery does not imply successful PHP execution.

Two dedicated auditKey-encrypted CloudWatch groups use configured retention
(staging 30 days, production 90 days) and retain on removal:

- `/mxmed/<environment>/jobs/media-review-batch-submission`: container output.
- `/mxmed/<environment>/jobs/media-review-batch-task-events`: complete ECS STOPPED
  events, including stoppedReason and container exitCode when supplied by ECS.

The EventBridge rule matches only the canonical cluster and exact job task
revision. Delivery is restricted to the event log group and exact rule/account;
CloudWatch Logs documents these
[resource-policy condition keys](https://docs.aws.amazon.com/AmazonCloudWatchLogs/latest/APIReference/API_PutResourcePolicy.html).
The audit-key policy grants only regional CloudWatch Logs with the exact two log
ARN encryption contexts. Deterministic names avoid Security → Jobs dependencies.
No email or other external notification is implemented.

## Validation and operational limits

Static validation covers activation, a single image authority, command and DB
injection, private networking, scoped startup/invocation permissions, encrypted
logs and exact STOPPED-event delivery. Packaging regressions include PHP CLI,
executor inclusion, phpredis 6.3.0, local image build and readonly web smoke test.
A disposable MySQL database verifies the actual packaged executor with all five
MXMED_DB variables, without a local DB config file; repeated/concurrent runs
preserve 29-minute OPEN / 31-minute SUBMITTED behavior and one durable signal.
Batch regressions cover empty OPEN retirement, preserved withdrawn history,
withdraw/upload races and the permanent gallery capacity of 16.

No Director data was accessed or mutated: the accepted gallery count remains
8 by preservation, not a fresh production query. Photo, logo, candidate history
and all user upload flows remain outside this change.

MR11.5 should separately validate physical image/secret availability, DB credentials
and existing grants, private startup connectivity, readonly command execution,
normal and failing STOPPED-event delivery, encryption and retained logs in staging.
Scheduler activation must remain a separately authorized step. Static/local PASS
is not evidence of physical AWS deployment or runtime success.
