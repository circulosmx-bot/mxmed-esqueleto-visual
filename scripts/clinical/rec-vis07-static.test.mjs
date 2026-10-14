import assert from 'node:assert/strict';
import fs from 'node:fs';

const css=fs.readFileSync('assets/css/style.css','utf8');
const html=fs.readFileSync('private-app.php','utf8');

assert.match(css,/\.rx-medication-composition\.has-added\{[^}]*border:1px solid #55bdc7[^}]*background:#eaf8f9[^}]*box-shadow:inset 4px 0 0 var\(--rx-primary\)/);
assert.match(css,/\.ne-rx-row\[data-ne-rx-state="collapsed"\]\{[^}]*background:#fff/);
assert.match(css,/\.ne-rx-added-head #ne_rx_add\{[^}]*background:var\(--rx-primary\)!important[^}]*color:#fff!important/);
assert.match(css,/#ne_rx_add:focus-visible\{[^}]*outline:3px solid #78d7df/);
assert.match(css,/:has\(\.ne-rx-row\[data-ne-rx-state="expanded"\]\) \.ne-rx-added-head\{display:none!important\}/);
assert.match(html,/class="ne-rx-added-head d-none" id="ne_rx_added_head">[\s\S]*class="btn btn-outline-primary btn-sm" id="ne_rx_add">Agregar otro medicamento<\/button>/);
assert.match(html,/class="btn btn-primary" data-rx-host-next/);
assert.match(html,/style\.css\?v=rec-vis08/);

console.log('REC-VIS07 scoped visual contract: PASS');
