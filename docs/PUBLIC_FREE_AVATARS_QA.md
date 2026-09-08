# Free doctor profile portraits — 2026-09-08

Baseline: `737da70dd826315f1cdd0509057b7fbcfbe4eab5`, branch
`program/mxmed-product-completion-v1`, synchronized. Only the two supplied PNG
files were untracked before implementation.

## Authorities and behavior

- Photo: `profiles_doctors.photo_url` → repository identity → public DTO
  `identity.photo_url` → hero. Existing nonempty photo URLs retain precedence,
  including broken external URLs; no error-recovery behavior was introduced.
- Gender: `profiles_doctors.gender` → `identity.gender` (additive public DTO
  field). If empty, use existing `identity.gender_label`, sourced from
  `profiles_doctors.gender_label` with the existing gender fallback. Explicit
  unsupported codes stay neutral. No name, prefix, or specialty inference.
- Plan: existing resolved/normalized public plan code. Only `free` enables
  the new system images. No additional photo entitlement restriction existed
  in the current hero photo rendering.
- Neutral/paid missing-photo fallback remains the CSS silhouette:
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

- `php modules/profiles/tests/PublicProfilePortraitTest.php`: 38 cases pass,
  including the required A–G matrix, aliases, canonical-code precedence,
  unknown gender, no name inference, real-photo precedence for both genders,
  every paid plan, and broken URL preservation.
- `node modules/profiles/tests/PublicProfilePortraitBrowserTest.mjs`: pass at
  1440×900, 1366×768, 390×844, and 320×740. Actual Free QA profile renders the
  female asset; Professional retains the neutral silhouette. Both assets are
  checked in the existing hero (male substituted in DOM only).
- Existing geometry remains 205×256.25 px desktop and 180×225 px mobile,
  aspect ratio 4:5, object-fit cover. Both PNGs load at their original natural
  dimensions; swapping images does not change the portrait box. No stretching,
  harmful crop, or portrait overflow. No CSS changes were needed.
- Screenshots: `/tmp/mxmed-avatar-qa/{width}-{male|female}.png`.
- PHP syntax and diff whitespace checks pass. No database mutations or QA
  gender/photo/plan persistence occurred; nothing needed restoration.

Backend changes are limited to the read-only public identity field and
portrait selection. Uploads, entitlements, booking, schema, migrations, and
AWS are unchanged.
