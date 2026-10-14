import fs from 'node:fs';
import assert from 'node:assert/strict';

const read = path => fs.readFileSync(path, 'utf8');
const rx = read('api/_lib/clinical_prescription_v2.php');
const viewer = read('modules/clinical/ui/viewer.php');
const css = read('assets/css/clinical-doc-base.css');
const binding = read('api/_lib/clinical_prescription_binding.php');
const signingQr = read('api/_lib/clinical_prescription_qr.php');

assert.match(rx, /JOIN media_assets a ON a\.public_url = p\.logo_url/);
for (const guard of ["owner_type = 'PHYSICIAN'", "purpose = 'PHYSICIAN_PERSONAL_LOGO'", "classification = 'PUBLIC'", "status = 'READY'"]) {
  assert.ok(rx.includes(guard), `canonical media guard present: ${guard}`);
}
assert.match(rx, /prescription_snapshot'\] = \['version'=>2/);
assert.match(viewer, /\$isPrescriptionV2 && \$prescriptionV2SnapshotHtml !== ''/);
assert.match(viewer, /echo \$prescriptionV2SnapshotHtml/);
assert.match(css, /@media \(max-width: 900px\)/);
assert.match(css, /overflow-x: auto/);
assert.ok(!css.includes('scale('), 'prescription document stylesheet does not scale the page');
assert.match(binding, /'version'=>1, 'document_type'=>'prescription'/);
assert.ok(!signingQr.includes('professional_profile'), 'remote-signing QR authority remains separate');

console.log('PASS DOC-REC-STD03 safe independent static contracts');
