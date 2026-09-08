# Doctor profile portraits, all plans — 2026-09-08

Revised-rule baseline: `6bbaebf0cd0e1277201a3bfae8f05870747b826b`, branch
`program/mxmed-product-completion-v1`, clean and synchronized.

## Authorities and behavior

- Photo: `profiles_doctors.photo_url` → repository identity → public DTO
  `identity.photo_url` → hero. Existing nonempty photo URLs retain precedence,
  including broken external URLs; no error-recovery behavior was introduced.
- Gender: `profiles_doctors.gender` → `identity.gender` (additive public DTO
  field). If empty, use existing `identity.gender_label`, sourced from
  `profiles_doctors.gender_label` with the existing gender fallback. Explicit
  unsupported codes stay neutral. No name, prefix, or specialty inference.
- Plan does not participate in portrait selection. Free, Basic, Standard,
  Optimal, and Professional all use the gender avatar when the real photo is
  absent. Existing plan entitlements are unchanged.
- Unknown-gender missing-photo fallback remains the CSS silhouette:
  `.mxpp-avatar--placeholder` containing `.mxpp-avatar-shape`.
- System avatars are only rendering fallbacks, never persisted as photo URLs
  or sent through media upload. The generic alt text says “Imagen de perfil
  de …”, not “Foto del médico”.

## Supplied assets, unchanged

| File under assets/img/doctors/avatars/ | Dimensions | Bytes | SHA-256 |
| --- | --- | --- | --- |
| dr-male.png | 1122 × 1402 | 30003 | 5bfa320b465645ef681ff625050d732a29321aebe843b499baff9b15911d60ca |
| dr-female.png | 1122 × 1402 | 44655 | f7923c7d43b481e1d979276c7b988c35acf8697438df636503f53682f0fa0245 |

## Validation

- `php modules/profiles/tests/PublicProfilePortraitTest.php`: 50 cases pass,
  including the required A–G matrix, aliases, canonical-code precedence,
  unknown gender, no name inference, real-photo precedence for both genders,
  every paid plan, and broken URL preservation.
- `node modules/profiles/tests/PublicProfilePortraitBrowserTest.mjs`: pass at
  1440×900, 1366×768, 390×844, and 320×740. The actual QA profile renders the
  female asset in all five plans. Both assets are
  checked in the existing hero (male substituted in DOM only).
- Existing geometry remains 205×256.25 px desktop and 180×225 px mobile,
  aspect ratio 4:5, object-fit cover. Both PNGs load at their original natural
  dimensions; swapping images does not change the portrait box. No stretching,
  harmful crop, or portrait overflow. No CSS changes were needed.
- Screenshots: `/tmp/mxmed-avatar-qa/{width}-{male|female}.png`.
- PHP syntax and diff whitespace checks pass. No database mutations or QA
  gender/photo/plan persistence occurred; nothing needed restoration.

This revision changes only portrait selection and its tests/documentation.
Canonical public identity fields are unchanged. Uploads, entitlements, booking, schema, migrations, and
AWS are unchanged.
