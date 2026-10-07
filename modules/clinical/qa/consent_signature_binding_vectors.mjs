import fs from 'node:fs';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';
import { spawnSync } from 'node:child_process';

const source = fs.readFileSync('assets/js/clinical/consent-signature-binding.js', 'utf8');
const vectors = JSON.parse(fs.readFileSync('modules/clinical/qa/consent_signature_binding_vectors.json', 'utf8'));
const context = {window: {}, crypto: webcrypto, TextEncoder};
vm.createContext(context);
vm.runInContext(source, context);
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
