# MR11.1 operational closeout

Baseline: 29dcb2d57ab8b15cebf33838b8208afd84828e39,
program/mxmed-product-completion-v1; initially clean, upstream equal.

## Empty OPEN batch retirement

Owner withdrawal retains the physician lock, marks the selected pending item
WITHDRAWN, then locks its OPEN batch and performs a current locking read of
READY/PENDING_REVIEW members. If none remain, all members of that never-submitted
batch are detached (batch_id=NULL), preserving their timestamps, and the OPEN
batch is deleted in the same transaction. No ready signal is inserted. A failure
rolls back withdrawal, detachment and deletion together.

The operation requires the withdrawn submission to belong to that OPEN batch.
It cannot detach SUBMITTED members or retire a different current batch when an
older submitted member is withdrawn. Photo/logo withdrawal retains its existing
post-commit private-file cleanup policy; gallery withdrawal retains private bytes.
No submission or media_review_files row is deleted. Inline photo/logo replacement
during upload does not retire the batch between its old and new candidates.

A later upload after final withdrawal creates a different batch ID. If upload
wins the physician lock first, withdrawal sees the new pending member and keeps
the existing batch. One-OPEN-owner uniqueness and gallery capacity 16 are unchanged.
There is no schema change, migration, new batch status or user uploader activation.

## Scheduler discovery — blocked

No productive periodic execution mechanism for this application CLI is wired in
the repository. This is a repository/source finding, not an AWS account inventory.

Evidence inspected:
- infra/aws/lib/stacks/mxmed-jobs-stack.ts: MxMedJobsStack is explicitly a future
  Scheduler/ECS RunTask boundary and creates no scheduled/queue resources.
- infra/aws/lib/stages/mxmed-environment-stage.ts: instantiates that empty Jobs
  stack and adds dependencies; it does not supply a job task or schedule.
- infra/aws/lib/stacks/mxmed-compute-stack.ts: the existing deployment model is
  private ECS/Fargate. Application execution starts Apache; the migration command
  is deliberately fail-closed. Its database injection uses DB_HOST/DB_PORT/DB_NAME
  and DB_USERNAME/DB_PASSWORD.
- api/_lib/db.php: the media executor instead consumes application config or
  MXMED_DB_HOST/PORT/NAME/USER/PASS. No runtime bridge to the injected DB_* contract
  was found in the runtime/scripts inspected.
- infra/aws/runtime/app/Dockerfile and README.md: image scaffold expects a separately
  assembled allowlisted application payload and immutable base/application digests;
  there is no packaged periodic media-job invocation in this composition.
- infra/aws/runtime/app/health/readyz.php currently checks Valkey, not application
  MySQL. Older README wording about unconditional readiness_not_integrated is stale
  and is not used as evidence that this endpoint always returns 503.
- scripts/aws/c3-runtime-contract.sh and c3-ephemeral-deploy.sh concern restricted
  ephemeral/nonproduction validation, not a productive recurring application job.
- No application crontab, container cron/supervisor, workflow scheduler or other
  implemented periodic CLI launch path was found.

FIRST_BLOCKER=NO_PRODUCTIVE_PERIODIC_JOB_RUNTIME

The presence of future JobsStack architecture is not a runnable, correctly
configured periodic executor. No speculative cloud resources/configuration were
added and no AWS deployment, secret, grant, DNS or scheduler action was performed.

## Smallest compatible next step

Complete the established ECS/Fargate runtime contract rather than adopt another
platform: package the reviewed CLI/application payload, bridge application DB
authority from its existing secrets injection without committing credentials, and
validate a private job task with command:
php modules/media/bin/submit-inactive-review-batches.php 100

Then wire MxMedJobsStack to EventBridge Scheduler approximately every five minutes,
with private subnets, no public IP or HTTP cron endpoint, scoped RunTask/PassRole
authority, and operational visibility of failed task exit codes (not merely a
successful RunTask API response). Physical deployment requires separate Director
authorization. This prerequisite runtime integration and scheduling are not
implemented or claimed READY here.

The 30-minute rule and bounded/idempotent executor are unchanged. Browser timers,
page loads and uploads are not substituted for a scheduler. MR12 activation stays
blocked. The tested empty-batch fix is left uncommitted with this discovery report.

## Validation

Disposable MySQL only; no Director media mutation:
- EmptyReviewBatchTest: photo/logo/gallery final withdrawal, retained history,
  multi-item retirement, submitted membership and rollback on deletion failure.
- EmptyReviewBatchRaceTest: last withdrawal vs upload in both deterministic orders.
- ReviewBatchTest: 18-item fixture, manual/29-minute/31-minute submission, one signal,
  derived counts, bounded scans, legacy visibility, gallery capacity.
- ReviewBatchRaceTest, ReviewBatchAtomicTest, GalleryReviewTest: existing batch
  concurrency, atomicity and capacity regressions.
- Exact pre/post MediaReviewReadSnapshot comparison preserves Director rows/files,
  public photo/logo, eight gallery images and the retained synthetic candidate.
