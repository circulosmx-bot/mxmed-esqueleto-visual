# MXMed application runtime scaffold

This scaffold implements the static PHP 8.5 Apache/X86_64 contract without building an image.
The future image pipeline must provide immutable digest values for `PHP_BASE_IMAGE`,
`COMPOSER_BASE_IMAGE`, and a reviewed `PHPREDIS_VERSION`; no argument has a mutable default.

Before invoking a build, the pipeline must assemble an allowlisted `application/` directory containing
only the reviewed MXMed runtime payload. It must exclude repositories, documentation, QA, SQL, local
uploads, secrets, caches, dependencies, and CDK outputs. Composer is available only in the discarded
build stage and is not present in the final image.

`/healthz` is a dependency-free liveness endpoint. `/readyz` deliberately returns HTTP 503 with
`readiness_not_integrated`; Edge traffic remains blocked until bounded MySQL and Valkey checks are
implemented and tested in a later functional microphase.

The image is not built, pulled, pushed, scanned, or deployed by CDK synth.
