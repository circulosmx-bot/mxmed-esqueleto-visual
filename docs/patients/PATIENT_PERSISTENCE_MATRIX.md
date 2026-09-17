# PAT-PERSIST01 — patient persistence matrix

Scope: administrative patient data edited in **Expediente → Datos generales**. The clinical *Motivo de consulta* is an encounter field (`data-hc-field="chief_complaint"`) and is not a column of the patient domain. The header photograph is a separate media affordance, not an administrative patient field; no media authority was changed here.

## Authority and root cause

Before this change, `#dg-save-patient` updated `patients_profiles`, a primary address, and only the primary mobile contact. It did not write `patients_patients.sex` or `birthdate`; edited names could update `patients_profiles` while leaving `patients_patients.display_name` stale. Home/contact phones and both emails were rendered as editable but omitted from the save. Contact-person name and relationship were also editable without any read or write authority; those two controls have been removed.

The canonical authorities remain `patients_patients` (display name, birthdate, sex), `patients_profiles` (structured names, marital status, occupation), `patients_contacts` (phones/emails), and `patients_addresses` (primary address). A single `PUT /api/patients/index.php/doctors/{doctor_id}/patients/{patient_id}/datos-generales` validates and saves the whole editable form in one database transaction. It requires a matching authenticated doctor session and active doctor-patient link. Existing unscoped profile/address POST routes now reject writes with HTTP 403. Existing doctor-scoped editable-contact read/write routes also verify the session doctor.

Names use one server-side rule: when both first name and paternal surname are explicitly submitted, `display_name` is the normalized joining of first name, paternal surname, and optional maternal surname. The existing `PatientNameValidator` validates each component. A legacy patient with no structured name keeps the prior display name until the user explicitly supplies a complete structured name. Sex storage for new writes is `M`, `F`, `O`, or `NULL`; legacy strings such as `Masculino` remain readable and are not rewritten without an explicit save. Birthdates use ISO `YYYY-MM-DD` with `checkdate` validation.

Migration `modules/patients/db/migrations/2026_09_16_01_patient_contact_roles.sql` adds nullable `contact_role` and a unique `(patient_id, contact_role)` key. Legacy rows remain intact. The first explicit save associates existing phone/email rows by their prior primary/order precedence, then keeps stable roles (`mobile`, `home`, `contact`, `primary_email`, `alternate_email`) on subsequent saves. Other unclassified contact rows are retained. Existing preferred-contact-method values are preserved when a row is updated.

## Editable fields

All rows below use the **Guardar paciente** button (`#dg-save-patient`) as the UI write trigger. `P` means physical local database read after the HTTP write; `B` means close/reopen browser hydration. The common update authority is `SavePatientDetailsController → PatientDetailsRepository::save`. For creation, core fields are inserted by `CreatePatientController → PatientsRepository::createPatient`, then the same doctor-scoped save completes the administrative form; if completion fails, the UI reports the partial creation instead of reporting success.

| FIELD / UI label | UI selector | Canonical authority | Write path (create and update) | Read / reload path | Round-trip test | Status |
|---|---|---|---|---|---|---|
| Nombre(s) | `[data-pac-nombre]` | `patients_profiles.first_name`; synchronized `patients_patients.display_name` | create: POST then PUT; update: PUT | `GET /patients/{id}` → profile; search reads display name | P, B, search | PERSISTENT |
| Primer Apellido | `[data-pac-apellido-paterno]` | `patients_profiles.paternal_last_name`; synchronized display name | create: POST then PUT; update: PUT | patient detail and search | P, B, search | PERSISTENT |
| Segundo Apellido | `[data-pac-apellido-materno]` | `patients_profiles.maternal_last_name`; synchronized display name | create: POST then PUT; update: PUT | patient detail and search | P, B, search | PERSISTENT |
| Fecha de Nacimiento | `[data-dg-dia]`, `[data-dg-mes]`, `[data-dg-anio]` | `patients_patients.birthdate` | create: POST; update: PUT | patient detail → day/month/year | P, B, invalid leap day rejected | PERSISTENT |
| Sexo | `input[name="pac-genero"]` | `patients_patients.sex` | create: POST; update: PUT | patient detail; patient and billing search | P, B, M→F→M→O, billing female icon | PERSISTENT |
| Estado Civil | `[data-pac-profile-marital-status]` | `patients_profiles.marital_status` | create: PUT after POST; update: PUT | patient detail → profile | P, B | PERSISTENT |
| Ocupación | `[data-pac-profile-occupation]` | `patients_profiles.occupation` | create: PUT after POST; update: PUT | patient detail → profile | P, B | PERSISTENT |
| Teléfono Celular | `[data-pac-phone="mobile"]` | `patients_contacts.phone`, `contact_role=mobile` | create: POST then PUT; update: PUT | doctor-scoped editable contacts | P, B | PERSISTENT |
| Teléfono Casa | `[data-pac-phone="home"]` | `patients_contacts.phone`, `contact_role=home` | create: PUT after POST; update: PUT | doctor-scoped editable contacts | P, B | PERSISTENT |
| Teléfono celular (persona de contacto) | `[data-pac-phone="contact"]` | `patients_contacts.phone`, `contact_role=contact` | create: PUT after POST; update: PUT | doctor-scoped editable contacts | P, B | PERSISTENT |
| Correo electrónico | `[data-pac-email="primary"]` | `patients_contacts.email`, `contact_role=primary_email` | create: PUT after POST; update: PUT | doctor-scoped editable contacts | P, B | PERSISTENT |
| Correo electrónico alterno | `[data-pac-email="alternate"]` | `patients_contacts.email`, `contact_role=alternate_email` | create: PUT after POST; update: PUT | doctor-scoped editable contacts | P, B | PERSISTENT |
| Código Postal | `[data-pac-address-cp]` | `patients_addresses.postal_code` | create: PUT after POST; update: PUT | patient detail → primary address | P, B | PERSISTENT |
| Colonia / Fraccionamiento | `[data-pac-address-colony]` | `patients_addresses.colony` | create: PUT after POST; update: PUT | patient detail → primary address | P, B | PERSISTENT |
| Estado | `[data-pac-address-state]` | `patients_addresses.state` | create: PUT after POST; update: PUT | patient detail → primary address | P, B | PERSISTENT |
| Municipio | `[data-pac-address-municipality]` | `patients_addresses.municipality` | create: PUT after POST; update: PUT | patient detail → primary address | P, B | PERSISTENT |
| Localidad | `[data-pac-address-locality]` | `patients_addresses.locality` | create: PUT after POST; update: PUT | patient detail → primary address | P, B | PERSISTENT |
| Calle | `[data-pac-address-street]` | `patients_addresses.street` | create: PUT after POST; update: PUT | patient detail → primary address | P, B | PERSISTENT |
| # ext | `[data-pac-address-ext]` | `patients_addresses.exterior_number` | create: PUT after POST; update: PUT | patient detail → primary address | P, B | PERSISTENT |
| # int | `[data-pac-address-int]` | `patients_addresses.interior_number` | create: PUT after POST; update: PUT | patient detail → primary address | P, B | PERSISTENT |
| Piso | `[data-pac-address-floor]` | `patients_addresses.floor` | create: PUT after POST; update: PUT | patient detail → primary address | P, B | PERSISTENT |

## Derived, fixed, and removed fields

| FIELD | UI / authority | Status |
|---|---|---|
| `display_name` | No independent editor. Derived on the server from the three structured-name inputs; read by Expediente search, Billing patient search, and invoice patient selection. | READ_ONLY_BY_DESIGN |
| `country` | Fixed `MX` in the current form; stored in `patients_addresses.country`, not independently editable. | READ_ONLY_BY_DESIGN |
| `catalog_cp_colonia_id` | No current visible input; existing value is preserved when postal code and colony are unchanged, otherwise cleared. | NOT_EXPOSED |
| `preferred_contact_method` | No current visible input; existing value is preserved on contact updates. | NOT_EXPOSED |
| Contact-person name / relationship | Previously unbound controls removed because no canonical columns or save contract exist. The contact-person phone remains. | NOT_EXPOSED |
| `notes_admin`, patient status, doctor-patient links | Not editable on this form. | NOT_EXPOSED |

## Physical field-by-field round trip

A controlled synthetic patient was created with a core row and one mobile contact, then the **visible Expediente form** was edited and saved. `API` means the doctor-scoped save/detail and editable-contact responses; `DB` is a direct read of the physical local MySQL tables; `UI reload` was measured after closing the browser and opening a fresh session through patient search. `∅` means no row/value before the edit. All rows passed; the synthetic patient and its related rows were removed afterward. The mobile control presents the national digits while the database stores the canonical `+52` value.

| Field | Pre DB | UI write | Post API | Post DB | UI reload | Result |
|---|---|---|---|---|---|---|
| Nombre(s) | ∅ | Patricia Elena | Patricia Elena | Patricia Elena | Patricia Elena | PASS |
| Primer Apellido | ∅ | Martínez | Martínez | Martínez | Martínez | PASS |
| Segundo Apellido | ∅ | Persistencia | Persistencia | Persistencia | Persistencia | PASS |
| Fecha de Nacimiento | 1984-07-18 | 1985-08-19 | 1985-08-19 | 1985-08-19 | 1985-08-19 | PASS |
| Sexo | M | F | F | F | F | PASS |
| Estado Civil | ∅ | Casada | Casada | Casada | Casada | PASS |
| Ocupación | ∅ | Investigadora | Investigadora | Investigadora | Investigadora | PASS |
| Teléfono Celular | +525512345678 | 5512349999 | +525512349999 | +525512349999 | 5512349999 | PASS |
| Teléfono Casa | ∅ | 5555556789 | 5555556789 | 5555556789 | 5555556789 | PASS |
| Teléfono de contacto | ∅ | 5511122233 | 5511122233 | 5511122233 | 5511122233 | PASS |
| Correo electrónico | ∅ | patricia.qa@example.test | patricia.qa@example.test | patricia.qa@example.test | patricia.qa@example.test | PASS |
| Correo alterno | ∅ | patricia.alt@example.test | patricia.alt@example.test | patricia.alt@example.test | patricia.alt@example.test | PASS |
| Código Postal | ∅ | 20000 | 20000 | 20000 | 20000 | PASS |
| Colonia | ∅ | Aguascalientes Centro | Aguascalientes Centro | Aguascalientes Centro | Aguascalientes Centro | PASS |
| Estado | ∅ | Aguascalientes | Aguascalientes | Aguascalientes | Aguascalientes | PASS |
| Municipio | ∅ | Aguascalientes | Aguascalientes | Aguascalientes | Aguascalientes | PASS |
| Localidad | ∅ | Centro | Centro | Centro | Centro | PASS |
| Calle | ∅ | Calle Uno | Calle Uno | Calle Uno | Calle Uno | PASS |
| # ext | ∅ | 12 | 12 | 12 | 12 | PASS |
| # int | ∅ | B | B | B | B | PASS |
| Piso | ∅ | 2 | 2 | 2 | 2 | PASS |

The derived `display_name` changed from `Patricia Martínez Persistencia` to `Patricia Elena Martínez Persistencia` in API, DB, Expediente search, and Billing search. Separate synthetic M→F→M runs confirmed the `face_3` and `face` icons respectively in Expediente and Billing. A separate browser-created patient verified that the new-patient flow completes the structured profile and secondary phone before reporting success; its rows were also removed.

## Physical local QA and boundaries

The local `mxmed` database was validated after the migration. A doctor-scoped lookup resolved the Director's Macías patient to one exact patient ID; its prestate (`sex=Masculino`, birthdate `1995-11-05`, unchanged `updated_at`) was read but never written. Synthetic patients were used for all destructive checks, then their patient, profile, contact, address, and doctor-link rows were deleted. Tests verified all 21 editable fields through HTTP/API and physical table reads; representative fields were changed and reopened in Chromium, including sex F→M, birthdate, name, marital status, occupation, phone, email, and street. Invalid date (`1983-02-29`) and loose sex (`female`) returned HTTP 422 without changing the canonical row. A different doctor ID returned HTTP 403. Billing patient search and selected patient both rendered the persisted female value with `face_3`; the same canonical search result supplies invoice patient selection. No fiscal profile, invoice, clinical encounter, or Agenda row was changed.
