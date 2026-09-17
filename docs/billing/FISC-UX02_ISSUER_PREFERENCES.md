# FISC-UX02: issuer preferences and guided setup

Apply `modules/billing/db/2026_09_16_04_create_billing_issuer_preferences.sql` after FISC02B's issuance-core migration. It creates an empty, forward-only table. Existing issuers, drafts, invoices, patients and media are untouched.

`billing_issuer_preferences` is scoped by both doctor and active issuer. The service validates a description, an optional exact decimal unit price, and one of `PROFESSIONAL_LOGO` or `NONE`. The mode stores no logo file or URL. Availability is resolved from the physician's current approved `PHYSICIAN_PERSONAL_LOGO` asset; invoice PDF rendering is outside this phase.

The initial description suggestion reads only active, verified professional credentials and an active, verified primary specialty credential. The transitional client-side profession taxonomy is not a billing authority. Unknown or unverified classification falls back to “Servicios profesionales”. A saved issuer description always wins over later changes to professional credentials. The suggestion does not assign SAT codes, tax object, tax rows, regime, CFDI use or tax treatment.

On a new unsaved draft, the selected issuer's saved description and price prefill only the first concept. The browser does not reapply preferences to a loaded draft or overwrite a manually edited first concept. Exact amounts and totals remain validated and calculated by FISC02B on the server. CSD registration and PAC remain on their existing guarded authorities.
