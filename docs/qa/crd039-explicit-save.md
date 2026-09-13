# CRD03.9 — Datos Generales explicit save

Baseline: `58b235119ff729479f3a1080edfc89a651640eee` on `design/physician-crd03-credentials-ui-v1`.

The five profile fields use one existing `PATCH /api/profiles/private/doctor/{doctor_id}`. Editing, blur, theme selection, keyboard selection and reset change only the mounted draft. A sorted, deep normalized snapshot determines dirty state; returning values to the persisted baseline clears it without sending a request. Scoped legacy `dp:` storage listeners neither read nor write the canonical identity card. No other localStorage data is deleted or migrated.

The single floating action is hidden while clean, fixed 24px from the desktop right/bottom edges, and centered with 16px side margins and safe-area bottom spacing on mobile. Dirty content receives at least 88px bottom padding. The action remains mounted across Información subtabs. Returning to Información does not replace a dirty draft; a read already in flight also cannot overwrite new edits.

Saving captures one payload, disables repeated submission and changes the label to `Guardando…`. The persisted baseline advances only after the canonical authority returns a successful identity response. Success hides the action and shows `Cambios guardados` for four seconds. Failure preserves the draft and retry. Edits made while a request is in flight survive the response and remain dirty relative to the confirmed server state. Existing name policy, Unicode Bio150, blocked gender and theme catalog validation are unchanged. Canonical theme `null` remains distinct from an explicitly stored default key; reset changes local state to `null` and requires Save when different.

Administrative contact fields are EXCLUDED_FROM_CRD039: they use independent canonical contact endpoints, blur/Enter persistence and no existing atomic profile/contact transaction. ADMIN_CONTACT_EXPLICIT_SAVE_MIGRATION=DEFERRED. Media uploads, removals, replacement, review submission and withdrawal remain independent explicit operations. Signature stays an independent device/local workflow, SIG01_STATUS=DEFERRED_UNCHANGED. Read-only identity, credentials, gender, profile status and publication eligibility are excluded. UNSAVED_NAVIGATION_GUARD=DEFERRED_NEXT_PHASE; no global route/unload interception was added.

# CRD03.9 pre-implementation save inventory

| Visible control | DOM | Authority | Current trigger / storage | Grouped PATCH | Treatment |
| --- | --- | --- | --- | --- | --- |
| Nombre(s) | mxpi-verified-given-names | verified_identity + server display-name policy; output profiles_doctors.display_name | local selection; static save PATCH; generic legacy localStorage listener possible | yes | include; compare selector/composed name to baseline |
| Primer apellido | mxpi-verified-first-surname | canonical verified_identity | read-only text, no editable input | governed, excluded | unchanged |
| Mostrar segundo apellido | mxpi-show-second-surname | canonical surname + public display-name policy | local checkbox; static grouped save | yes (composed display_name) | include; revert clears dirty |
| Nombre público (legacy fallback) | mxpi-display-name | profiles_doctors.display_name + policy | local input; static grouped save; legacy field assistance varies by human-name classification | yes | include; preserve legacy/nonconforming on load |
| Prefijo profesional | mxpi-prefix | profiles_doctors.prefix | local input; static grouped save; generic dp: localStorage change/blur listener | yes | include; disable scoped legacy storage listener |
| Denominación profesional | mxpi-professional-designation | profiles_doctors.professional_designation | local input; static grouped save; generic field assistance may attach | yes | include; disable scoped legacy storage listener |
| Descripción del encabezado | mxpi-bio-short | profiles_doctors.bio_short, max 150 server Unicode characters | local input; static grouped save; generic dp: localStorage change/blur listener | yes | include; disable scoped legacy storage listener |
| Color del perfil / Restablecer | mx-profile-theme-swatches / mx-profile-theme-reset | profiles_doctors.profile_theme_key + canonical catalog | immediate visual preview; persistence only static grouped save, reset local null | yes | include; canonical key/null baseline |
| Teléfono administrativo | mx-admin-phone | doctor_contact_points platform_admin | API POST/PATCH per contact on blur/Enter; no localStorage | no | EXCLUDED_FROM_CRD039; deferred, no existing profile/contact atomic batch transaction |
| WhatsApp administrativo | mx-admin-whatsapp | doctor_contact_points platform_admin | API POST/PATCH per contact on blur/Enter; no localStorage | no | EXCLUDED_FROM_CRD039; deferred, same authority boundary |
| Foto: seleccionar/cambiar/eliminar | mxpi-photo-select / mxpi-photo-input / mxpi-photo-remove | canonical private media candidate + published authority | explicit operational media HTTP upload/remove | no | independent; never affects form dirty |
| Logo: subir/cambiar/eliminar | [data-profile-logo-upload] .mf-choose / mx-dg-logo / mx-dg-logo-del | canonical media/logo review authority | explicit media HTTP upload/remove | no | independent; never affects form dirty |
| Retirar / enviar a revisión / reemplazar | [data-review-candidate] / owner review controls | media review submission/batch lifecycle | explicit media HTTP operations | no | independent; never affects form dirty |
| Firma: QR, firmar, reintentar, guardar | dg-signature-* | independent signature workflow, legacy localStorage/device/session; SIG01 deferred | dedicated signature actions, separate from profile identity | no | EXCLUDED_FROM_CRD039; preserve existing/deferred workflow |
| Datos verificados / Visibilidad / Ver perfil público | top utility group | governed read models / navigation | existing modal/link actions | no | independent, unchanged |

The profile PATCH supports only the existing identity field contract; contact endpoints operate on separate contact entities and do not expose an atomic profile/contact batch. Admin migration is DEFERRED. No new endpoint or cross-authority transaction is justified. No included field needs localStorage as a save authority. Existing beforeunload guard is scoped to a new-patient flow, not this form: profile unsaved-navigation guard is DEFERRED_NEXT_PHASE.

## Inventory flags

| Control group | Immediate API write before CRD03.9 | localStorage-only authority | Independent workflow | Included now |
| --- | --- | --- | --- | --- |
| Public name / surname visibility | no; static grouped Save | no; legacy generic listener possible | no | yes: composed display_name |
| Prefix, designation, Bio | no; static grouped Save; generic dp: change/blur write possible | no; server is canonical | no | yes |
| Theme selection / reset | no; local preview + static grouped Save | no | no | yes |
| Administrative phone / WhatsApp | yes: per-contact blur/Enter | no | separate canonical contact authority | no, deferred |
| Photo / logo / review operations | explicit per operation | no | yes: canonical media review | no |
| Signature controls | dedicated actions | legacy device/local/session workflow | yes: SIG01 deferred | no |
| Verified data / utilities | no editable governed fields | no | modal/navigation only | no |

## Validation

Focused browser test intercepts every mutation while hydrating the real Director profile: clean/edit/revert, prefix/surname/theme, local reset, poison legacy-storage reload, no autosave, mounted tabs, late-read draft protection, one grouped PATCH, success, failure/retry, repeated activation, concurrent edits, exact preview copy, reachable fixed action and bottom padding at 1440×900, 1366×768 and 390×844, no horizontal overflow or runtime/console errors. The independent media browser suite additionally checks the form stays clean after upload, review submission and withdrawals.

Required regression suites cover VID02 name combinations and rejection, legacy/nonconforming preservation, Bio150 Unicode/paste/empty handling, canonical theme selection/reset/persistence, accepted CRD038 appearance, Leticia hydration, Header/account controls, Sidebar/Agenda and verified-data modal. SQL/API mutation tests use a newly created disposable MySQL 8.4 database (port 3331), including grouped persistence, save/reload, invalid catalog keys, Bio151 rejection and blocked gender. Director database table hashes and the private read model are compared before/after, ignoring only generated_at. Existing eight gallery images are preserved.

The first VID02 run passed assertions but Chrome temporary-profile cleanup raced with shutdown; the unchanged suite passed on rerun. The initial CRD038 public-theme loop sampled a transient layout overflow before settling; an external QA-only copy adding a 350ms settling wait passed all 60 public-theme previews. The unchanged original CRD038 suite also passed on its final rerun. No public profile source or theme authority was changed to accommodate it.

The local Director review URL uses `qa_tools=hide`: only the external localhost router injects a style hiding the QA role/plan panel. Normal HTTP files and packaged product bytes remain unchanged; production layout contains no QA-panel hack. Screenshots and external harnesses stay in `/tmp/mxmed-crd039`, outside source control.
