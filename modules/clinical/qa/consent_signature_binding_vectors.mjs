import fs from 'node:fs';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';
import { spawnSync } from 'node:child_process';

const source = fs.readFileSync('assets/js/clinical/consent-signature-binding.js', 'utf8');
const presentationSource = fs.readFileSync('assets/js/clinical/legal-document-presentation.js', 'utf8');
const vectors = JSON.parse(fs.readFileSync('modules/clinical/qa/consent_signature_binding_vectors.json', 'utf8'));
const context = {window: {}, crypto: webcrypto, TextEncoder};
vm.createContext(context);
vm.runInContext(presentationSource, context);
vm.runInContext(source, context);
const newConsent = structuredClone(vectors[0].body);
newConsent.payload.presentation = {version: 1, professional_header: 'shown'};
newConsent.payload.branding = {professional_name_visible: 'Dra. Ejemplo', logo_url_resolved: '/logo.webp',
  facility_visible: 'Clínica ejemplo', location_line_visible: 'Ciudad ejemplo'};
newConsent.payload.actor_snapshot.specialty = 'Medicina interna';
newConsent.payload.actor_snapshot.specialty_license = '12345';
vectors.push({name: 'presentation_v2_shown', body: newConsent});
const hiddenConsent = structuredClone(newConsent);
hiddenConsent.payload.presentation.professional_header = 'hidden';
vectors.push({name: 'presentation_v2_hidden', body: hiddenConsent});
for (const {name, body} of vectors) {
  const clientProjection = JSON.stringify(context.window.mxmedConsentSignatureBinding.projection(body));
  const clientHash = await context.window.mxmedConsentSignatureBinding.hash(body);
  const php = spawnSync('php', ['-r',
    "require 'api/_lib/clinical_consent_signature_binding.php'; $body=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); echo json_encode(['projection'=>clinical_consent_binding_projection($body),'hash'=>clinical_consent_binding_fingerprint($body)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);"
  ], {input: JSON.stringify(body), encoding: 'utf8'});
  if (php.status !== 0) throw Error(`${name}: PHP ${php.stderr}`);
  const server = JSON.parse(php.stdout);
  if (clientProjection !== JSON.stringify(server.projection) || clientHash !== server.hash) {
    throw Error(`${name}: client/server projection or hash differs`);
  }
  console.log(`${name}: PASS ${clientHash}`);
}
if (await context.window.mxmedConsentSignatureBinding.hash(newConsent)
    === await context.window.mxmedConsentSignatureBinding.hash(hiddenConsent)) {
  throw Error('header mode must change the V2 fingerprint');
}
console.log('presentation_v2_stale_on_toggle: PASS');
