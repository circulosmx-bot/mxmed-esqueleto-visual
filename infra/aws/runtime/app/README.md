# MXMed application runtime scaffold

This scaffold implements the static PHP 8.5 Apache/X86_64 contract without building an image.
The canonical packaging recipe at `scripts/packaging/README.md` provides immutable
PHP/Composer digests and a pinned phpredis version; no Docker argument has a mutable default.

Use `node scripts/packaging/assemble-application.mjs` from the repository root to
create the isolated `.application-build/` context. Version the recipe and runtime
manifest, never a duplicated application tree. See that recipe for build and QA.

`/healthz` is dependency-free liveness. `/readyz` currently checks bounded Valkey
connectivity/authentication; it does not validate application MySQL readiness.

The image is not built, pulled, pushed, scanned, or deployed by CDK synth.
