import assert from 'node:assert/strict';
import fs from 'node:fs';

const history = fs.readFileSync('assets/js/clinical/rec-ux01.js','utf8');
const viewer = fs.readFileSync('modules/clinical/ui/document.php','utf8');

assert.match(history, /params\.set\('return_to', recetasReturnTarget\(selectedPrescription\)\)/);
assert.match(history, /window\.location\.assign\(`\/modules\/clinical\/ui\/document\.php/);
assert.match(history, /rx_filter/);
assert.match(history, /rx_page/);
assert.match(history, /rx_scroll/);
assert.match(history, /rx_patient/);
assert.match(history, /rx_selected/);
assert.match(history, /window\.open\(`\/modules\/clinical\/ui\/document\.php\?\$\{params\}`, '_blank', 'noopener'\)/);
assert.doesNotMatch(history, /window\.open\(`\/modules\/clinical\/ui\/document\.php\?\$\{params\}`, '_blank'\);/);
assert.match(viewer, /\$value\[0\] !== '\/'/);
assert.match(viewer, /isset\(\$parts\['scheme'\]\)/);
assert.match(viewer, /\$allowedPath/);
assert.match(viewer, /\$fromRecetas \? '\/index\.html'/);
assert.match(viewer, /href="<\?php echo h\(\$backHref\); \?>">← Volver a Recetas<\/a>/);
assert.doesNotMatch(viewer, /if\(window\.opener\)/);

console.log('REC-NAV01 navigation contract: PASS');
