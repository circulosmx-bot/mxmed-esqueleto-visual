"""Isolated draft safety and ambiguous create retries; no real writer or DB mutation."""
import json,os,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ.get('STEP2_DRAFT_R2_ARTIFACTS','/tmp/mxmed-step2-draft-r2'));OUT.mkdir(parents=True,exist_ok=True)
reference=json.loads(subprocess.check_output(['php','-r','require $argv[1];echo json_encode(clinical_vital_references_resolve("1990-01-01"));',str(ROOT/'api/_lib/clinical_vital_references.php')],text=True))
with sync_playwright() as p:
 browser=p.webkit.launch();page=browser.new_page();page.on('dialog',lambda d:d.accept());page.goto((ROOT/'index.html').as_uri(),wait_until='domcontentloaded')
 result=page.evaluate(r'''async reference=>{
  const original=document.querySelector('#m7-workspace'),root=original.cloneNode(true);original.replaceWith(root);root.querySelectorAll('.vitalref-number').forEach(w=>w.replaceWith(w.querySelector('input')));
  let patient='adult',key='safe',rows=[],calls=[],persisted=null,loseResponse=true;
  const ok=data=>({ok:true,status:200,json:async()=>({ok:true,data})}),tick=async()=>{await new Promise(r=>setTimeout(r,0));await new Promise(r=>setTimeout(r,0));};
  window.fetch=async(url,opts={})=>{
   const method=opts.method||'GET';calls.push({url:String(url),method,key:new Headers(opts.headers).get('Idempotency-Key'),body:opts.body?JSON.parse(opts.body):null});
   if(String(url).endsWith('/vital-references'))return ok(reference);
   if(method==='POST'){
    if(!persisted)persisted={...JSON.parse(opts.body),observation_id:99,encounter_id:1,row_version:1,invalidated_at:null};
    if(loseResponse){loseResponse=false;throw new TypeError('Lost response after persistence');}
    rows=[persisted];return ok(persisted);
   }
   return ok({items:[],patient_id:patient,observations:rows,sections:{}});
  };
  const ws=mxmedM7WS03(root,k=>'/encounters/'+k,()=>patient),q=s=>root.querySelector(s),code=q('[data-m7-measurement-code]'),value=q('[data-m7-measurement-value]'),source=q('[data-m7-measurement-source]'),save=q('[data-m7-measurement-save]'),cancel=q('[data-m7-measurement-new]'),cue=q('[data-vis30-measurement-draft]'),status=q('[data-m7-measurement-recovered-status]');
  const load=async()=>{ws.load({patient_id:patient,encounter_id:1,observations:rows,sections:{}},key,'open');ws.select('measurements');await tick();};
  const choose=name=>{code.value=name;code.dispatchEvent(new Event('change',{bubbles:true}));};
  const input=(node,text)=>{node.value=text;node.dispatchEvent(new Event('input',{bubbles:true}));};
  const draftKey=()=>`mxmed.m7.ws03.draft:${key}:measurements`;
  await load();choose('temperature');input(value,'37.2');input(source,'direct_measurement');const raw=sessionStorage.getItem(draftKey());await load();
  const safeAutoRestore=value.value==='37.2'&&source.value==='direct_measurement'&&!value.disabled&&!source.disabled&&!code.disabled&&!save.disabled&&!cancel.disabled&&ws.isDirty()&&!status.classList.contains('d-none')&&cue.classList.contains('d-none')&&sessionStorage.getItem(draftKey())===raw;
  const restoreNeverWrites=calls.every(c=>c.method==='GET')&&!JSON.stringify(JSON.parse(raw)).includes('SERVER_AT_SAVE')&&q('.vis-step2-chip')===null;
  cancel.click();const discardClean=value.value===''&&value.placeholder==='Ref. 36.5–37.3'&&!ws.isDirty()&&!ws.hasSavedDrafts()&&save.disabled;
  input(source,'direct_measurement');await load();const partialSourceDraftRestored=!value.disabled&&value.value===''&&source.value==='direct_measurement'&&ws.isDirty()&&save.disabled;cancel.click();
  // Canonical same-code authority makes a new local draft unavailable.
  choose('heart_rate');input(value,'72');input(source,'direct_measurement');const unsafeRaw=sessionStorage.getItem(draftKey());
  rows=[{observation_id:7,encounter_id:1,code:'heart_rate',unit:'bpm',value_numeric:88,source:'patient_report',row_version:1,invalidated_at:null}];const canonical=JSON.stringify(rows);await load();
  const unavailableBlocked=value.disabled&&code.disabled&&source.disabled&&save.disabled&&!cue.classList.contains('d-none')&&status.classList.contains('d-none')&&q('[data-vis30-measurement-recover]').disabled&&sessionStorage.getItem(draftKey())===unsafeRaw&&JSON.stringify(rows)===canonical;
  ws.select('measurements');await load();const unrelatedRepaintCannotUnlock=value.disabled&&source.disabled&&sessionStorage.getItem(draftKey())===unsafeRaw;
  q('[data-vis30-measurement-discard]').click();const unavailableDiscardSafe=!value.disabled&&!ws.isDirty()&&!ws.hasSavedDrafts()&&JSON.stringify(rows)===canonical&&!Array.from(code.options).some(o=>o.value==='heart_rate');
  // Existing edit drafts restore the canonical type even if options were filtered.
  q('[aria-label^="Editar: Frecuencia cardíaca"]').click();input(value,'89');await load();const editDraftRestore=code.value==='heart_rate'&&value.value==='89'&&!value.disabled&&!save.disabled&&ws.isDirty()&&JSON.stringify(rows)===canonical;cancel.click();
  q('[aria-label^="Editar: Frecuencia cardíaca"]').click();input(value,'90');rows=[];await load();const missingEditedRowBlocked=value.disabled&&!cue.classList.contains('d-none');q('[data-vis30-measurement-discard]').click();
  // A lost POST preserves the exact command key/payload and permits only safe retry.
  key='ambiguous';await load();choose('temperature');input(value,'37.2');input(source,'direct_measurement');const first=await ws.saveSelected(),pendingRaw=sessionStorage.getItem(draftKey()),pendingKey=sessionStorage.getItem('mxmed.m7.ws03.create-key:'+key);
  await load();const ambiguousNotNormalRecovery=first===false&&!!pendingKey&&value.value==='37.2'&&value.disabled&&code.disabled&&source.disabled&&cancel.disabled&&!save.disabled&&status.classList.contains('d-none')&&q('[data-m7-measurements-state]').textContent.includes('no se confirmó')&&sessionStorage.getItem(draftKey())===pendingRaw;
  const second=await ws.saveSelected(),posts=calls.filter(c=>c.method==='POST');
  const exactIdempotentRetry=second===true&&posts.length===2&&posts[0].key===pendingKey&&posts[1].key===pendingKey&&JSON.stringify(posts[0].body)===JSON.stringify(posts[1].body)&&rows.length===1&&q('[data-m7-measurements-list]').querySelectorAll('.vis-step2-chip').length===1&&!ws.hasSavedDrafts()&&!sessionStorage.getItem('mxmed.m7.ws03.create-key:'+key);
  const serverAtSavePreserved=posts.every(c=>c.body.capture_time_mode==='SERVER_AT_SAVE'&&c.body.value_numeric===37.2&&!('effective_at' in c.body)&&!('recorded_at' in c.body));
  return {safeAutoRestore,restoreNeverWrites,discardClean,partialSourceDraftRestored,unavailableBlocked,unrelatedRepaintCannotUnlock,unavailableDiscardSafe,editDraftRestore,missingEditedRowBlocked,ambiguousNotNormalRecovery,exactIdempotentRetry,serverAtSavePreserved};
 }''',reference)
 assert all(result.values()),result
 (OUT/'r2-logic-report.json').write_text(json.dumps(result,indent=2));print('STEP2_DRAFT_R2_LOGIC_GATE=PASS',json.dumps(result));browser.close()
