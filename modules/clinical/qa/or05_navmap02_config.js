// OR05-NAVMAP02: deterministic contract checks against the audited finite matrix.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
global.window = global;
require(path.join(root, 'assets/js/clinical/or05-specialty-navigation-v1.js'));
const nav = global.mxmedSpecialtyNavigationV1;
const {config} = nav;
const catalog = JSON.parse(fs.readFileSync(path.join(root, 'modules/clinical/catalog/tax03b_source_curation.json'), 'utf8'));
const studies = catalog.entries.filter(row => row.classification === 'CANONICAL_CLEAR');
const keys = new Map(studies.map(row => [row.study_type_key, row.category_key]));
const audit = fs.readFileSync(path.join(root, 'docs/clinical/OR05_NAVMAP01_SPECIALTY_NAVIGATION_MATRIX_V1_PROPOSED.md'), 'utf8');

assert.equal(config.version, 1);
assert.equal(studies.length, 183);
assert.equal(Object.keys(config.specialties).length, 124);
assert.equal(Object.keys(config.professionalTitles).length, 20); // 10 title labels and 10 UI keys.
assert.equal(config.groups.primary.length, 4);
assert.equal(Object.keys(config.groups.quick).length, 8);
const titleRows = [...audit.matchAll(/^\| `([^`]+)` \| ([^|]+) \| [^|]+ \| [^|]+ \| [^|]+ \| ([^|]+) \| Todos los estudios \| [^|]+ \| `([^`]+)`;/gm)];
assert.equal(titleRows.length, 10);
for (const [, uiKey, title, listedQuick, profile] of titleRows) {
  assert.equal(config.professionalTitles[uiKey], profile);
  assert.equal(config.professionalTitles[title.trim()], profile);
  const quick = (config.profiles[profile]?.quick || []).map(code => config.groups.quick[code].label).join(', ') || '—';
  assert.equal(quick, listedQuick.trim(), title);
}
const audited = [...audit.matchAll(/^\| `ui:[^`]+` \| ([^|]+) \| [^|]+ \| [^|]+ \| `([^`]+)` \| ([^|]+) \|/gm)];
assert.equal(audited.length, 124);
for (const [, label, profile, listedQuick] of audited) {
  const key = config.specialties[label.trim()];
  assert.equal(key, profile);
  assert.equal(nav.profileFor(label), profile);
  const quick = (config.profiles[key]?.quick || []).map(code => config.groups.quick[code].label).join(', ') || '—';
  assert.equal(quick, listedQuick.trim(), label);
}
for (const title of Object.keys(config.professionalTitles)) {
  assert.ok(config.profiles[config.professionalTitles[title]], title);
}
for (const [profile, definition] of Object.entries(config.profiles)) {
  assert.ok(definition.quick.length <= 8, profile);
  assert.equal(new Set(definition.quick).size, definition.quick.length, profile);
  for (const group of definition.quick) assert.ok(config.groups.quick[group], `${profile}:${group}`);
  for (const key of definition.promoted) assert.ok(keys.has(key), `${profile}:${key}`);
}
const groups = [...config.groups.primary, ...Object.values(config.groups.quick)];
for (const group of groups) {
  for (const part of group.parts) {
    assert.ok(studies.some(row => row.category_key === part.category), group.id);
    for (const key of part.keys || []) assert.equal(keys.get(key), part.category, `${group.id}:${key}`);
  }
}
const membership = (group, study) => group.parts.some(part => part.category === study.category_key &&
  (!part.keys || part.keys.includes(study.study_type_key)));
for (const study of studies) assert.ok(config.groups.primary.some(group => membership(group, study)), study.study_type_key);
assert.equal(studies.reduce((n, study) => n + groups.filter(group => membership(group, study)).length, 0), 257);
const counts = Object.fromEntries(new Set(studies.map(row => row.category_key)).values().map(category =>
  [category, studies.filter(row => row.category_key === category).length]));
assert.equal(config.groups.lower.filter(group => nav.active(group, counts)).length, 0);
assert.equal(config.groups.quick.P.label, 'Citología');
assert.deepEqual(config.groups.quick.P.parts, [{category:'PATOLOGIA'}]);

const profile = (specialty, title = '') => nav.resolve({identity_public:{specialty_primary:specialty, professional_designation:title}});
assert.deepEqual(profile('Cardiología').quick, ['C', 'F', 'S']);
assert.deepEqual(profile('Neurología').quick, ['N', 'S']);
assert.deepEqual(profile('Gastroenterología').quick, ['E']);
assert.deepEqual(profile('Otorrinolaringología').quick, ['A', 'E', 'S']);
assert.deepEqual(profile('Neumología').quick, ['F', 'S', 'E']);
for (const specialty of ['Dentista', 'Ortodoncia', 'Implantología', 'Cirugía Oral y Maxilofacial'])
  assert.deepEqual(profile(specialty).quick, [], specialty);
assert.equal(profile('Neurología Pediátrica').profile, 'peds_neuro');
assert.equal(profile('Nefrología Pediátrica').profile, 'peds_nephro');
assert.equal(profile('Neumología Pediátrica').profile, 'peds_pulm');
assert.equal(profile('Especialidad inédita').profile, config.generalProfile);
assert.deepEqual(profile('Especialidad inédita').quick, config.profiles.general_med.quick);
assert.equal(profile('Especialidad inédita', 'Cirujano Dentista').profile, 'dental');
const verified = nav.resolve({identity_public:{specialty_primary:'Cardiología',specialty_secondary:['Cardiología','Neumología']},
  primary_specialty_credential_id:2,verified_credentials:{specialties:[
    {credential_id:1,credential_type:'SPECIALTY',verification_status:'VERIFIED',lifecycle_status:'ACTIVE',professional_area_label:'Cardiología'},
    {credential_id:2,credential_type:'SPECIALTY',verification_status:'VERIFIED',lifecycle_status:'ACTIVE',professional_area_label:'Neurología'}
  ]}});
assert.equal(verified.profile, 'neuro');
assert.equal(verified.source, 'verified_primary');
assert.deepEqual(verified.quick, ['N','S','C','F','E']);
assert.equal(new Set(verified.quick).size, verified.quick.length);
assert.ok(verified.quick.length <= 8);
console.log('OR05_NAVMAP02_CONFIG=PASS: 134 finite classifications, 183 studies, 257 navigation relations, profile precedence and fallbacks');
