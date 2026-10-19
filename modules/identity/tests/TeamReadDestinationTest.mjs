// Canonical login destination functions, isolated VM only; no authentication/network/session mock claimed as backend QA.
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=fs.readFileSync(new URL('../../../public/identity/identity-access.js',import.meta.url),'utf8');
const functions=source.slice(source.indexOf('  function safePrivateDestination()'),source.indexOf("  document.querySelectorAll('[data-password-toggle]')"));
let checks=0;
async function run(next,allowed,expected){
  let destination=null;
  const location={origin:'https://example.test',search:'?next='+encodeURIComponent(next),hash:'',replace:v=>{destination=v;}};
  const context={window:{location,localStorage:{setItem(){}}},location,URL,URLSearchParams,Date,JSON,fetch:async()=>({ok:true,json:async()=>({authenticated:true,application_access:{allowed}})})};
  vm.createContext(context);vm.runInContext(functions,context);
  if(expected===null)await assert.rejects(context.routeAfterLogin(),/professional_access_required/);
  else {await context.routeAfterLogin();assert.equal(destination,expected);}
  checks++;
}
await run('/internal/media-review/index.php?section=equipo',false,'/internal/media-review/index.php?section=equipo');
await run('/private-app.php',false,null);
await run('/private-app.php',true,'/private-app.php');
await run('/internal/media-review/index.php?section=acreditacion',false,'/internal/media-review/index.php?section=acreditacion');
await run('/internal/media-review/index.php?section=correcciones',false,'/internal/media-review/index.php?section=correcciones');
await run('https://evil.example/internal/media-review/index.php?section=equipo',false,null);
await run('/internal/media-review/index.php?section=equipo&role=DIRECTOR',false,null);
await run('/internal/media-review/index.php?section=equipo#untrusted',false,null);
await run('/internal/staff-workspace/index.php',false,'/internal/staff-workspace/index.php');
await run('/internal/staff-workspace/index.php?role=DIRECTOR',false,null);
await run('/internal/staff-workspace/index.php#untrusted',false,null);
await run('https://evil.example/internal/staff-workspace/index.php',false,null);
await run('/private-app.php?next=/internal/staff-workspace/index.php',false,null);
console.log(JSON.stringify({result:'PASS',checks,scope:'Existing canonical login return navigation only; team server independently revalidates session/staff/grants'}));
