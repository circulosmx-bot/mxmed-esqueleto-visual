import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import http from 'node:http';
import {spawn} from 'node:child_process';
import {fileURLToPath} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../../..');
const temporary=fs.mkdtempSync(path.join(os.tmpdir(),'mxmed-crd02-browser-'));
const response={ok:true,data:{identity_public:{display_name:'Médica Sintética QA',professional_designation:'Médica',prefix:'Dra.',gender:'female',gender_label:'Femenino',professional_license:'0099',specialty_license:'0088',specialty_primary:'Legacy QA',specialty_secondary:[],bio_short:'Perfil sintético.',profile_status:'active',is_public_candidate:true},verified_identity:null,public_name_policy:{verified_identity_available:false},verified_credentials:{professional:{credential_id:'1',credential_type:'PROFESSIONAL',license_number:'1111111',professional_area_label:'Médico Cirujano',institution_name:'UAA',verification_status:'VERIFIED',lifecycle_status:'ACTIVE'},specialties:[]},primary_specialty_credential_id:null}};
let profileRequests=0, mutations=0, includeCredentials=false;
const server=http.createServer((req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(!['GET','HEAD'].includes(req.method)){mutations++;res.writeHead(405);res.end();return;}
  if(url.pathname.startsWith('/api/')){
    res.setHeader('Content-Type','application/json');
    if(url.pathname.includes('/private/doctor/')){profileRequests++;const payload=structuredClone(response);if(!includeCredentials){delete payload.data.verified_credentials;delete payload.data.primary_specialty_credential_id;}res.end(JSON.stringify(payload));}
    else res.end(JSON.stringify({ok:false,data:null,error:'synthetic_unavailable'}));
    return;
  }
  const file=path.resolve(root,'.'+(url.pathname==='/'?'/index.html':url.pathname));
  if(!file.startsWith(root+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',({'.html':'text/html','.js':'text/javascript','.css':'text/css','.svg':'image/svg+xml'})[path.extname(file)]||'application/octet-stream');
  fs.createReadStream(file).pipe(res);
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
let chrome,ws;
try {
  chrome=spawn(process.env.CHROME_BINARY||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',['--headless=new','--remote-debugging-port=0','--user-data-dir='+temporary,'--no-first-run','--no-default-browser-check','about:blank'],{stdio:'ignore'});
  const active=path.join(temporary,'DevToolsActivePort');
  for(let i=0;i<100&&!fs.existsSync(active);i++)await new Promise(r=>setTimeout(r,100));
  const port=fs.readFileSync(active,'utf8').split('\n')[0];
  const tab=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();
  ws=new WebSocket(tab.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
  let sequence=0;const pending=new Map();
  ws.addEventListener('message',({data})=>{const message=JSON.parse(data);if(!message.id)return;const promise=pending.get(message.id);pending.delete(message.id);if(message.error)promise.reject(message.error);else promise.resolve(message.result);});
  const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
  const evaluate=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true})).result?.value;
  await send('Page.enable');
  const states=[];
  for(const extended of [false,true]) {
    includeCredentials=extended;
    await send('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/index.html?extended='+extended});
    let hydrated=false;
    for(let i=0;i<120;i++){
      hydrated=await evaluate(`document.querySelector('#mxpi-display-name')?.value==='Médica Sintética QA'`);
      if(hydrated)break;await new Promise(r=>setTimeout(r,100));
    }
    assert(hydrated,'existing profile hydration accepts private response');
    states.push(await evaluate(`({name:document.querySelector('#mxpi-display-name').value,header:document.querySelector('.mx-gh-identity-name-text')?.textContent,license:document.querySelector('#ced-prof')?.value})`));
  }
  assert.deepEqual(states[1],states[0],'Header/profile behavior unchanged by canonical credentials');
  assert.equal(states[1].license,'0099','legacy credential consumer unchanged');
  assert(profileRequests>=2);
  assert.equal(mutations,0,'hydration emits no mutations');
  console.log('DoctorCredentialHydrationTest PASS (real browser; Header/profile hydration, legacy license retained, no mutations)');
} finally {
  ws?.close();if(chrome){chrome.kill();await new Promise(r=>chrome.once('exit',r));}
  server.closeAllConnections();await new Promise(r=>server.close(r));fs.rmSync(temporary,{recursive:true,force:true});
}
