# CONS-VIS01 physician emergency contact authority proposal

`consultorios.urgencias_json` is currently office-scoped and the public-profile projection reads it per office. A shared physician contact therefore needs one new authority rather than synchronized copies across consultorio rows.

The accompanying SQL creates one versioned row per `doctor_id`. It intentionally contains no backfill and is not located in the automatic migration directory. Normal-local and production application require separate Director authorization.

Reconciliation before cutover:

1. Normalize and hash each non-empty legacy `urgencias_json` value per physician.
2. With zero values, create no row until an authorized owner or administrator explicitly saves shared contacts.
3. With one unique value, present it for explicit adoption; do not copy it automatically.
4. With multiple unique values, require the physician to choose or merge them before any write.
5. After adoption, switch private editing and public-profile reads to `physician_emergency_contacts` in the same release. Keep prescription footer projection unchanged because emergency contacts remain excluded.
6. Preserve legacy columns until a separately approved retention period and rollback plan have completed.

Rollback before cutover is `DROP TABLE physician_emergency_contacts`. After cutover, rollback must first project each physician-level value according to an explicitly approved legacy restoration policy; automatic duplication is prohibited.
