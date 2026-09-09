# Canonical application package

Edit the tracked application source (`api`, `modules`, `assets`, `profiles`,
`internal`, `public`, and the root entrypoints listed in `runtime-files.json`).
The explicit manifest is the runtime inclusion authority: add new runtime files
there when adding application dependencies. Only Git-indexed regular files are
eligible; their current working-tree bytes are used, including accepted pending
edits. New source must be intentionally staged before assembly.

The previous staging tree, `infra/aws/runtime/app/application/`, is local output
excluded by `.git/info/exclude`, not a canonical source. Nothing in this recipe
reads, modifies or deletes it. Before MR11.3 no tracked assembler populated that
tree; it lacked `modules/media`. The versioned Dockerfile already used PHP 8.5,
Apache 2.4, Composer lock installation, www-data, and `/var/www/html`.

From repository root, with Git, Node and Docker available:

```sh
node scripts/packaging/assemble-application.mjs
node scripts/packaging/package.test.mjs
node scripts/packaging/build-application.mjs
node scripts/packaging/image.test.mjs
```

Generated output is `.application-build/` (root `.gitignore`). It contains the
scoped Docker context, `application/`, and a deterministic path/size/SHA-256
inventory. It is never an editable source tree and must not be committed.
Assembly refuses an existing output path, even a symlink, and never deletes
anything. For a fresh assembly, move the previous output aside or use a fresh
checkout. Missing source, symlinks and untracked manifest entries fail closed.

Before calling Docker, the build command verifies every context file against the
inventory and rejects added, changed or missing files and symlinks.
The build command uses the existing `infra/aws/runtime/app/Dockerfile`, copied
into the isolated context with its Dockerfile.dockerignore and runtime settings.
`build-inputs.json` pins PHP and Composer by digest and phpredis by version.
phpredis 6.3.0 includes the PHP 8.5 compilation fix documented in the
[official release notes](https://github.com/phpredis/phpredis/releases/tag/6.3.0);
the first local attempt with the older C3 pin 6.2.0 failed on a removed header.
Target is `linux/amd64`, matching ECS X86_64 even on Apple Silicon. The local tag
`mxmed-application:local-validation` is only a QA handle, never deployment
authority. OS package repositories and PECL still follow the existing Dockerfile
contract; identical package inventories are guaranteed, not identical image IDs.

PHP dependencies come from root `composer.json` + `composer.lock` and are
installed in the image with `--no-dev`; local vendor is never copied. Application
JS is served directly from tracked assets with existing browser CDN references;
there is no application npm build. `infra/aws/package-lock.json` controls CDK
only and is not part of the runtime payload.

Secrets/config overrides, `.env*`, credentials, uploads (including tracked legacy
uploads), private originals, dumps, QA, docs, caches, local vendor/node_modules,
Git/IDE state and arbitrary untracked files are excluded by the exact manifest
and a second forbidden-path check. Only this isolated context is sent to Docker;
the existing Dockerfile.dockerignore provides another layer. No real media is
copied for QA. The existing web contract may reference externally stored uploads;
packaging does not migrate or manufacture them.

The executor path is:
`/var/www/html/modules/media/bin/submit-inactive-review-batches.php`.
With WORKDIR `/var/www/html`, its command is:
`php modules/media/bin/submit-inactive-review-batches.php 100`.
It requires `api/_lib/db.php` and `modules/media/services/MediaReviewBatchService.php`.
The service has no further PHP includes; db.php has an optional local config
include which is intentionally absent, leaving canonical MXMED_DB_* authority.
The image test loads both without calling mxmed_pdo, verifies Composer/AWS SDK,
PHP extensions and lint, then serves dependency-free web routes as www-data with
a read-only filesystem. The executor has no safe help mode: never run it against
real DB credentials for packaging QA.

`setup-test-db.php` initializes only the existing fixed disposable port 3309,
from repository DDL and test audit procedures. It must run against a fresh
throwaway MySQL container, never against application DB settings. Run the
preserved media test suite with `MR5_FIXTURE_ROOT=/tmp/mxmed-mr5-mr113qa` after
setup, then run `node scripts/packaging/executor.test.mjs` to exercise two
concurrent image executors plus a repeated invocation against that fixture only.
Neither schema nor Director data is migrated by the assembler.

The existing ComputeStack ECR repository + `ApplicationImageDigest` SHA-256
resolution remains authoritative. A separately authorized publication phase can
build this canonical image, push it to that repository and supply its immutable
digest. MR11.3 does not push an image or deploy anything. Web and future CLI tasks
reuse that same digest; no media-specific image is introduced.

Separate unresolved runtime contract: ComputeStack injects DB_HOST/PORT/NAME and
DB_USERNAME/PASSWORD; api/_lib/db.php expects MXMED_DB_HOST/PORT/NAME/USER/PASS.
No entrypoint mapping exists. MR11.3 documents but does not repair that mismatch.
JobsStack, Scheduler, IAM, networking and MR12 activation remain untouched.

## MR11.3 validation

Local linux/amd64 image: PHP CLI 8.5.10, executor lint, static bootstrap without
DB, Composer AWS SDK and required extensions pass. Apache serves liveness, HTML,
JS and a dependency-free PHP error response as www-data on a read-only root.
Composer vendor ownership is assigned to www-data before read-only permissions.
Two concurrent packaged executors against disposable MySQL and a repeat preserve
29-minute OPEN / 31-minute SUBMITTED and exactly one ready signal.

Clean accepted HEAD plus the new recipe/build definition and candidate working
tree assemblies pass independently; repeated candidate inventories match (653
runtime files). Missing dependencies, local sentinels, forbidden tracked config,
symlinks, output overwrite and context-inventory tampering are rejected.
The existing 199 ComputeStack tests and EmptyReviewBatchTest,
EmptyReviewBatchRaceTest, ReviewBatchTest, ReviewBatchRaceTest,
ReviewBatchAtomicTest and GalleryReviewTest pass. No Director DB access, AWS
operation, reviewer grant, schema migration or upload activation was performed.
