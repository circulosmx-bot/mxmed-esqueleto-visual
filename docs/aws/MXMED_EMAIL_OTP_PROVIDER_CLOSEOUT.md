# MXMED / EMAIL-OTP — Technical provider closeout

- Terminal implementation source authority: `88dbdb96bef0ff870d4af471c4a66540d2ac81c9`
- Provider: Amazon SES V2 in `us-east-1`
- Domain identity: `mexicomedico.com`
- Chapter status: `EMAIL_OTP_PROVIDER_CHAPTER_STATUS=COMPLETE`

## Objective and architecture

The EMAIL/OTP provider chapter establishes the production transport boundary for transactional identity email without activating production compute. The application uses the AWS SDK for PHP, `Aws\SesV2\SesV2Client`, the SES V2 `SendEmail` operation, and the default AWS credential chain. SMTP, static AWS credentials, `SendRawEmail`, provider secrets, and SES control-plane authority are not required.

The implemented verification flow uses a high-entropy, single-use verification link. Numeric OTP storage and delivery remain intentionally deferred; no database migration or transient OTP store is required by this chapter.

## Persistent physical resources

The following infrastructure is intentional and must be preserved:

- Route 53 public hosted zone `Z07834302UTP3ATR4KVJ0` for `mexicomedico.com`.
- Verified SES domain identity `mexicomedico.com` with Easy DKIM enabled at RSA 2048.
- Three source-managed Easy-DKIM CNAME records in the existing hosted zone.
- Standard `CDKToolkit` bootstrap version `32` in account `875691018466`, region `us-east-1`.
- CloudFormation stack `mxmed-prd-email`.

The registrar remains Akky. The Director performed the registrar delegation, and public DNS authority was subsequently verified.

## Accepted physical transport proof

SES reports domain verification `SUCCESS`, DKIM status `SUCCESS`, and DKIM signing enabled. Exactly one SES V2 `SendEmail` was accepted from `México Médico <no-reply@mexicomedico.com>` to the official success simulator address `success@simulator.amazonses.com`.

The send ran through a single-purpose ephemeral role whose only data-plane permission was `ses:SendEmail` on `arn:aws:ses:us-east-1:875691018466:identity/mexicomedico.com`. It had no `SendRawEmail`, wildcard SES action, other-service permission, managed policy, or static credential. Its inline policy and role were deleted after the send, leaving zero probe residuals. Detailed physical evidence remains in the external mode-0600 evidence artifacts; the SES MessageId is intentionally not stored here.

## Security properties

- `EMAIL_TRANSPORT_PROVIDER_TECHNICAL_ACCEPTANCE=true`
- `EMAIL_PROVIDER_PHYSICAL_TRANSPORT_PROVEN=true`
- `SES_DOMAIN_IDENTITY_VERIFIED=true`
- `SES_EASY_DKIM_VERIFIED=true`
- `SES_V2_SEND_EMAIL_PHYSICAL_PASS=true`
- `STATIC_AWS_CREDENTIALS=false`
- `SMTP_CREDENTIALS=false`
- `SEND_RAW_EMAIL_REQUIRED=false`
- `SES_APPLICATION_SECRET_REQUIRED=false`
- `NUMERIC_OTP_DEFERRED=true`
- `DATABASE_MIGRATION_REQUIRED=false`
- `TRANSIENT_OTP_STORE_REQUIRED=false`
- `C3_REOPENED=false`
- `EOTP04D_EPHEMERAL_PROBE_ROLE_PRESENT=false`
- `EOTP04D_EPHEMERAL_INLINE_POLICY_PRESENT=false`
- `EOTP04D_PROBE_RESIDUAL_RESOURCE_COUNT=0`

## Deferred production task-role proof

`PRODUCTION_APPLICATION_ROLE_PHYSICALLY_PRESENT=false` and `PRODUCTION_APPLICATION_TASK_ROLE_PHYSICAL_PROOF_DEFERRED=true` because `PRODUCTION_SECURITY_COMPUTE_NOT_YET_DEPLOYED`.

Repository source already grants the future production application role only `ses:SendEmail` on the exact `mexicomedico.com` identity. Its ECS trust and runtime composition will be proven when the real production Security and compute infrastructure is activated. This is not an EMAIL technical-provider blocker.

## Deferred SES production access

The SES account remains in sandbox mode with sending enabled. `SES_PRODUCTION_ACCESS_ENABLED=false`, `SES_PRODUCTION_ACCESS_REQUEST_COUNT=0`, and `SES_PRODUCTION_ACCESS_DEFERRED_TO_RELEASE_READINESS=true`.

Production access must not be requested until the public HTTPS website is intentionally published. The future release-readiness request must target `us-east-1`, use `MailType=TRANSACTIONAL`, and include the then-current México Médico public website URL and operational use-case description.

## Final decision and roadmap

`EMAIL_OTP_PROVIDER_CHAPTER_STATUS=COMPLETE`. The application transport, verified sender identity, Easy DKIM, least-privilege SES data plane, and physical sandbox transport are accepted. No further EMAIL provider test, DNS change, IAM reconciliation, C3 execution, or production-access request is required for this technical closeout.

The next product-development chapter is `PUBLIC_MEDICAL_DISCOVERY_AND_BOOKING`. Later roadmap items are Agenda core closeout, payments, and mobile/release. SES production access remains a release-readiness dependency and is not the next product chapter.
