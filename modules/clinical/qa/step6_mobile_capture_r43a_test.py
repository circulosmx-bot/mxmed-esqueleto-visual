"""R43A: all writes in a disposable DB; real HTTP workers, files and WebKit."""
import os,json,re,subprocess,urllib.request,urllib.error,hashlib,struct,zlib,random,concurrent.futures,threading,time
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
BASE=os.environ['FLOW_R1_QA_BASE'];DB=os.environ['FLOW_R1_QA_DB'];ROOT=Path(os.environ['FLOW_R1_QA_ROOT']);OUT=Path(os.environ['FLOW_R1_ARTIFACTS']);OUT.mkdir(parents=True,exist_ok=True)
assert re.fullmatch(r'flow_r1_qa_[a-f0-9]{12}',DB)
checks={};details={}
def check(name,value=True):
 assert value,name
 checks[name]='PASS';print('PASS '+name,flush=True)
def sql(query):return subprocess.check_output(['mysql','-N',DB,'-e',query],text=True).strip()
def call(path,body=None,auth=True,headers=None):
 h={'Cookie':'PHPSESSID=step3-head-neck-qa'} if auth else {};h.update(headers or {})
 if isinstance(body,dict):h['Content-Type']='application/json';body=json.dumps(body).encode()
 request=urllib.request.Request(BASE+'/api/clinical/index.php/'+path,data=body,headers=h)
 try:r=urllib.request.urlopen(request,timeout=120);status=r.status;raw=r.read()
 except urllib.error.HTTPError as e:status=e.code;raw=e.read()
 try:out=json.loads(raw)
 except:out=raw
 if status>=500:(OUT/'server-debug.log').write_text((ROOT/'server.log').read_text())
 return status,out

def data(path,body=None,auth=True,headers=None):
 status,result=call(path,body,auth,headers);assert status in (200,201),(path,status,result);return result['data']
def create():return data('mobile-capture-sessions',{'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','classification_key':'consentimiento_formato','title':'Documento de prueba'})
def path(s,action=''):return 'mobile-capture-sessions/'+s['session_uuid']+('/'+action if action else '')
def issue(s):return data(path(s,'pages'),{})
def multipart(raw,name='page.jpg',mime='image/jpeg',extra=b''):
 return b'--qa\r\nContent-Disposition: form-data; name="file"; filename="'+name.encode()+b'"\r\nContent-Type: '+mime.encode()+b'\r\n\r\n'+raw+b'\r\n'+extra+b'--qa--\r\n'
def upload(token,raw,extra=b'',secret=''):
 return call('note-capture-tokens/'+token+'/upload',multipart(raw,extra=extra),False,{'Content-Type':'multipart/form-data; boundary=qa','X-Capture-Continuation':secret})
def add(s,raw):
 t=issue(s);code,result=upload(t['token'],raw);assert code==201,(code,result);return result['data'],t

def png(w,h):
 def chunk(t,b):return struct.pack('>I',len(b))+t+b+struct.pack('>I',zlib.crc32(t+b)&0xffffffff)
 rng=random.Random(42);pixels=b''.join(b'\0'+rng.randbytes(w*3) for _ in range(h))
 return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',w,h,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(pixels,0))+chunk(b'IEND',b'')
raw=subprocess.check_output(['php','-r','$i=imagecreatetruecolor(3000,4000);$w=imagecolorallocate($i,255,255,255);imagefill($i,0,0,$w);for($y=150;$y<3700;$y+=100) imagettftext($i,40,0,130,$y,0,"/System/Library/Fonts/Supplemental/Arial.ttf","Documento de prueba - Pagina legible - ABC 0123456789");imagejpeg($i,null,95);'])
(OUT/'printed-original.jpg').write_bytes(raw)
try:
 before=sql('SHOW TABLES').splitlines()
 migration=Path(__file__).resolve().parents[1]/'db/migrations/2026_09_29_12_mobile_capture_sessions.sql'
 for _ in range(2):subprocess.run(['mysql',DB],stdin=migration.open(),check=True)
 added=set(sql('SHOW TABLES').splitlines())-set(before)
 check('migration twice exactly two tables',added=={'clinical_mobile_capture_sessions','clinical_mobile_capture_pages'})
 check('anonymous create rejected',call('mobile-capture-sessions',{},False)[0]==401)
 check('classification required',call('mobile-capture-sessions',{'patient_id':'p_plan02ux_review','encounter_key':'enc:1016'})[0]==400)
 check('result classification rejected',call('mobile-capture-sessions',{'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','classification_key':'estudio_resultado'})[0]==400)
 check('cross patient rejected',call('mobile-capture-sessions',{'patient_id':'p_wrong','encounter_key':'enc:1016','classification_key':'otro'})[0]==403)
 s=create();check('zero pages cannot finalize',call(path(s,'finalize'),{})[0]==409)
 subprocess.run(['php','-d','session.save_path='+str(ROOT/'sessions'),'-r','session_id("wrong-doctor");session_start();$_SESSION["doctor_id"]="2";$_SESSION["user_id"]="wrong";session_write_close();'],check=True)
 check('wrong physician session rejected',call(path(s),auth=False,headers={'Cookie':'PHPSESSID=wrong-doctor'})[0]==403)
 t=issue(s);check('anonymous metadata tampering rejected',upload(t['token'],raw,b'--qa\r\nContent-Disposition: form-data; name="classification_key"\r\n\r\notro\r\n')[0]==400)
 code,r=upload(t['token'],raw);assert code==201,r;s=r['data'];first=s['pages'][0]['page_uuid']
 check('one upload per token',upload(t['token'],raw)[0]==409)
 check('draft pages create zero documents',sql('SELECT COUNT(*) FROM clinical_documents')=='0')
 check('continuation cannot create other sessions',call('mobile-capture-sessions',{},False,{'X-Capture-Continuation':s['continuation']})[0]==401)
 check('continuation cannot cancel',call(path(s,'cancel'),{},False,{'X-Capture-Continuation':s['continuation']})[0]==403)
 other=create();check('continuation cannot cross session',call(path(other,'pages'),{},False,{'X-Capture-Continuation':s['continuation']})[0]==403)
 main=json.loads(sql("SELECT optimized_manifest_json FROM clinical_mobile_capture_pages WHERE page_uuid='"+first+"'"));audit=json.loads(sql("SELECT original_audit_json FROM clinical_mobile_capture_pages WHERE page_uuid='"+first+"'"))
 check('server 2048 optimization and raw audit',max(main['w'],main['h'])==2048 and audit['sha256']==hashlib.sha256(raw).hexdigest() and main['sha256']!=audit['sha256'])
 (OUT/'printed-optimized.webp').write_bytes((ROOT/'private'/main['staging_key']).read_bytes())
 s,_=add(s,raw);s,_=add(s,raw);ids=[p['page_uuid'] for p in s['pages']]
 check('duplicate/incomplete reorder rejected',call(path(s,'reorder'),{'pages':[ids[0],ids[0],ids[1]]})[0]==400)
 s=data(path(s,'reorder'),{'pages':[ids[2],ids[0],ids[1]]});check('explicit reorder 3 1 2',[p['page_uuid'] for p in s['pages']]==[ids[2],ids[0],ids[1]])
 # Reject canonical insert after one page via trigger: rollback keeps all pages retryable.
 sql("DELIMITER $$\nCREATE TRIGGER qa_fail_second BEFORE INSERT ON clinical_documents FOR EACH ROW BEGIN IF JSON_EXTRACT(NEW.payload_json,'$.media_page_number')=2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA_ROLLBACK'; END IF; END$$\nDELIMITER ;")
 check('finalize rollback',call(path(s,'finalize'),{})[0]==500 and sql('SELECT COUNT(*) FROM clinical_documents')=='0' and data(path(s))['status']=='OPEN')
 check('rollback retains staged page', (ROOT/'private'/main['staging_key']).is_file())
 sql('DROP TRIGGER qa_fail_second')
 with concurrent.futures.ThreadPoolExecutor(2) as pool:results=list(pool.map(lambda _:call(path(s,'finalize'),{}),range(2)))
 check('concurrent finalize one bundle',all(x[0]==201 for x in results) and len({x[1]['data']['media_bundle_id'] for x in results})==1 and sql('SELECT COUNT(*) FROM clinical_documents')=='3')
 final=results[0][1]['data'];bundle=final['media_bundle_id'];details['bundle']=bundle
 check('canonical page metadata',sql("SELECT GROUP_CONCAT(JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.media_page_number')) ORDER BY id) FROM clinical_documents")=='1,2,3')
 check('two private manifests per page',sql('SELECT COUNT(*) FROM clinical_document_binaries')=='6')
 check('committed staging removed',not (ROOT/'private'/main['staging_key']).exists())
 check('bundle anonymous read rejected',call('bundles/'+bundle+'/documents',auth=False)[0]==401)
 bundleItems=data('bundles/'+bundle+'/documents')['items']
 check('bundle physician read',len(bundleItems)==3)
 check('page metadata anonymous read rejected',call('documents/'+bundleItems[0]['document_uuid'],auth=False)[0]==401)
 check('page metadata wrong physician rejected',call('documents/'+bundleItems[0]['document_uuid'],auth=False,headers={'Cookie':'PHPSESSID=wrong-doctor'})[0]==403)
 # Concurrent distinct authorizations are both accepted once.
 race=create();a=issue(race);b=issue(race)
 with concurrent.futures.ThreadPoolExecutor(2) as pool:results=list(pool.map(lambda t:upload(t,raw),[a['token'],b['token']]))
 check('different page tokens concurrent',all(x[0]==201 for x in results) and data(path(race))['page_count']==2)
 single=issue(race)
 with concurrent.futures.ThreadPoolExecutor(2) as pool:results=list(pool.map(lambda _:upload(single['token'],raw),range(2)))
 check('same token concurrent exactly one',sorted(x[0] for x in results)==[201,409])
 race=data(path(race));removed=race['pages'][1]['page_uuid'];removedMain=json.loads(sql("SELECT optimized_manifest_json FROM clinical_mobile_capture_pages WHERE page_uuid='"+removed+"'"))
 data(path(race,'remove'),{'page_uuid':removed});finalRemoved=data(path(race,'finalize'),{})
 check('remove page 2 finalize 2 pages',finalRemoved['page_count']==2 and not (ROOT/'private'/removedMain['staging_key']).exists())
 # Lock order gives either full inclusion before finalize or rejection afterwards, no late page.
 race=create();race,_=add(race,raw);pending=issue(race)
 with concurrent.futures.ThreadPoolExecutor(2) as pool:
  f1=pool.submit(call,path(race,'finalize'),{});f2=pool.submit(upload,pending['token'],raw);finalResult=f1.result();uploadResult=f2.result()
 count=data(path(race))['page_count'];check('finalize upload race',finalResult[0]==201 and ((uploadResult[0]==201 and count==2) or (uploadResult[0]==409 and count==1)))
 check('post finalize issuance rejected',call(path(race,'pages'),{})[0]==409)
 cancel=create();cancel,_=add(cancel,raw);cancel,_=add(cancel,raw);pending=issue(cancel);beforeDocs=sql('SELECT COUNT(*) FROM clinical_documents');sid=cancel['session_uuid']
 keys=json.loads('['+','.join(sql("SELECT optimized_manifest_json FROM clinical_mobile_capture_pages WHERE capture_session_id=(SELECT id FROM clinical_mobile_capture_sessions WHERE session_uuid='"+sid+"') AND status='READY'").splitlines())+']')
 data(path(cancel,'cancel'),{});check('cancel zero documents tokens staged files',beforeDocs==sql('SELECT COUNT(*) FROM clinical_documents') and call('note-capture-tokens/'+pending['token']+'/mobile-context',auth=False)[0]==409 and all(not (ROOT/'private'/m['staging_key']).exists() for m in keys))
 expired=create();expired,_=add(expired,raw);sql("UPDATE clinical_mobile_capture_sessions SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE session_uuid='"+expired['session_uuid']+"'");check('expired session cleanup',data(path(expired))['status']=='EXPIRED' and call(path(expired,'pages'),{})[0]==410)
 renew=create();pending=issue(renew);sql("UPDATE clinical_note_capture_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE token='"+pending['token']+"'");check('expired page may renew valid session',issue(renew)['token']!=pending['token']);data(path(renew,'cancel'),{})
 large=png(3000,2600);check('20-24MB fixture',20*1024*1024<len(large)<24*1024*1024);big=create();big,_=add(big,large);bigFinal=data(path(big,'finalize'),{})
 check('20MB raw absent durable storage',not any(p.stat().st_size==len(large) for p in (ROOT/'private').rglob('*') if p.is_file()))
 details['large_raw_bytes']=len(large)
 probes=[json.loads(line) for line in (ROOT/'runtime-probe.jsonl').read_text().splitlines()]
 check('20MB raw temporary removed before request shutdown',any(item['bytes']==len(large) and item['http_status']==201 and not item['raw_temp_exists_at_shutdown'] for item in probes))
 # Metadata orientation=6 (rotate 90 clockwise); APP1 XMP and COM must not survive.
 exif=b'Exif\0\0'+b'II'+struct.pack('<HI',42,8)+struct.pack('<H',1)+struct.pack('<HHI',274,3,1)+struct.pack('<H',6)+b'\0\0'+struct.pack('<I',0)
 oriented=raw[:2]+b'\xff\xe1'+struct.pack('>H',len(exif)+2)+exif+raw[2:];orientation=create();orientation,_=add(orientation,oriented)
 m=json.loads(sql("SELECT optimized_manifest_json FROM clinical_mobile_capture_pages WHERE page_uuid='"+orientation['pages'][0]['page_uuid']+"'"));optimized=(ROOT/'private'/m['staging_key']).read_bytes()
 check('EXIF orientation normalized stripped',m['w']==2048 and m['h']==1536 and b'Exif' not in optimized and b'XMP' not in optimized)
 data(path(orientation,'cancel'),{})
 # Existing DOCSEC01 single-page image and PDF remain canonical and readable.
 old=data('note-capture-tokens',{'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','capture_classification':'clinical_image'})
 code,result=upload(old['token'],raw);check('legacy single-page capture',code==201)
 row=json.loads(sql("SELECT payload_json FROM clinical_documents d JOIN clinical_note_capture_tokens t ON t.document_id=d.id WHERE t.token='"+old['token']+"'"))
 check('normal canonical image uses shared optimizer',row['original_audit']['sha256']==hashlib.sha256(raw).hexdigest() and max(row['image_optimization']['w'],row['image_optimization']['h'])==2048)
 old=data('note-capture-tokens',{'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','capture_classification':'clinical_pdf'})
 pdf=b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n'
 code,result=call('note-capture-tokens/'+old['token']+'/upload',multipart(pdf,'test.pdf','application/pdf'),False,{'Content-Type':'multipart/form-data; boundary=qa'})
 check('PDF preserves bytes one document',code==201 and sql("SELECT b.sha256 FROM clinical_document_binaries b JOIN clinical_note_capture_tokens t ON t.document_id=b.document_id WHERE t.token='"+old['token']+"'")==hashlib.sha256(pdf).hexdigest())
 # Browser: real phone flow 3 pages, reorder, finalize and canonical bundle viewer.
 with sync_playwright() as p:
  browser=p.webkit.launch();mobile=browser.new_page(viewport={'width':390,'height':844});ui=create();t=issue(ui);mobile.goto(t['mobile_url']);expect(mobile.locator('#captureTakePhoto')).to_be_enabled()
  for i in range(3):
   if i:mobile.get_by_role('button',name='Agregar otra página',exact=True).click();expect(mobile.locator('#captureTakePhoto')).to_be_enabled()
   mobile.locator('#captureFile').set_input_files({'name':'page.jpg','mimeType':'image/jpeg','buffer':raw});mobile.locator('#captureSubmit').click();expect(mobile.locator('#captureMsg')).to_contain_text('Página recibida',timeout=30000)
  mobile.get_by_role('button',name='Subir',exact=True).last.click();expect(mobile.locator('#captureMsg')).to_contain_text('Orden actualizado');mobile.screenshot(path=str(OUT/'mobile-review-390-webkit.png'),full_page=True)
  mobile.get_by_role('button',name='Finalizar documento',exact=True).click();expect(mobile.locator('#captureMsg')).to_contain_text('Documento enviado · 3 páginas');check('WebKit multipage phone flow')
  mobile.reload();expect(mobile.locator('#captureMsg')).to_contain_text('Documento enviado · 3 páginas');check('phone reload continuation')
  desktop=browser.new_context(viewport={'width':1440,'height':900});desktop.add_cookies([{'name':'PHPSESSID','value':'step3-head-neck-qa','url':BASE}]);viewer=desktop.new_page();viewer.goto(BASE+'/modules/clinical/ui/viewer.php?bundle_id='+bundle)
  expect(viewer.get_by_text('Página 1 de 3',exact=True)).to_be_visible();expect(viewer.locator('img[alt="Documento"]')).to_be_visible();assert viewer.locator('img[alt="Documento"]').evaluate('(img)=>img.complete&&img.naturalWidth>0')
  viewer.screenshot(path=str(OUT/'bundle-viewer-1440-webkit.png'),full_page=True);viewer.get_by_role('link',name='Siguiente',exact=True).click();expect(viewer.get_by_text('Página 2 de 3',exact=True)).to_be_visible();check('existing viewer private image and page order')
  from urllib.parse import urlsplit
  frontend=browser.new_context(viewport={'width':1440,'height':810});page=frontend.new_page();errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
  api=p.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
  def proxy(route):
   req=route.request;url=urlsplit(req.url);response=api.fetch(BASE+url.path+('?' + url.query if url.query else ''),method=req.method,data=req.post_data_buffer,headers={k:v for k,v in req.headers.items() if k in ['content-type','accept','idempotency-key']});route.fulfill(response=response)
  def guard(route):
   req=route.request
   assert req.method in ['GET','HEAD'] or req.url.endswith('/patient-id/resolve'),'Unexpected Director write'
   route.continue_()
  page.route('**/api/clinical/**',guard)
  for pattern in ['**/api/clinical/index.php/encounters/**','**/api/clinical/index.php/patients/*/encounters*','**/api/clinical/index.php/doctors/*/patients/*/documents*','**/api/clinical/index.php/documents/**','**/api/clinical/index.php/mobile-capture-sessions*','**/api/clinical/index.php/mobile-capture-sessions/**']:page.route(pattern,proxy)
  page.goto('http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_placeholders=clean&review_step=documents',wait_until='commit')
  expect(page.locator('[data-m7-section="documents"]')).to_have_attribute('aria-current','true',timeout=55000);expect(page.locator('[data-m7-doc-state]')).to_have_attribute('data-state','saved')
  for w,h in [(1440,900),(1366,768),(1440,810)]:
   page.set_viewport_size({'width':w,'height':h});page.screenshot(path=str(OUT/f'step6-main-{w}x{h}-webkit.png'));check(f'main no page scroll {w}x{h}',page.evaluate('document.documentElement.scrollHeight<=innerHeight+1&&document.documentElement.scrollWidth<=innerWidth+1'))
  page.locator('[data-m7-capture-start]').click();expect(page.locator('input[name="capture-classification"]')).to_have_count(6);expect(page.locator('[data-docux-capture-generate]')).to_be_disabled();check('explicit classification before QR',page.locator('input[name="capture-classification"]:checked').count()==0)
  page.screenshot(path=str(OUT/'desktop-classification-webkit.png'));page.locator('input[value="consentimiento_formato"]').check();page.locator('[data-docux-capture-title-input]').fill('Prueba de captura agrupada')
  with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/pages')) as response:page.locator('[data-docux-capture-generate]').click()
  live=response.value.json()['data'];expect(page.locator('[data-docux-qr-content]')).to_be_visible()
  qr=page.locator('[data-docux-qr]').screenshot();decoded=subprocess.check_output(['swift','modules/clinical/qa/docux01r1_qr_decode.swift'],input=qr).decode();check('QR decodes exact server URL',decoded==live['mobile_url'])
  code,result=upload(live['token'],raw);assert code==201,result
  for _ in range(2):add(live,raw)
  expect(page.locator('[data-m7-capture-state]')).to_contain_text('3 páginas recibidas',timeout=15000);expect(page.locator('[data-docux-capture-pages] img')).to_have_count(3)
  page.screenshot(path=str(OUT/'desktop-three-pages-webkit.png'))
  page.locator('[data-docux-capture-finalize]').click();expect(page.locator('[data-m7-capture-state]')).to_contain_text('Documento recibido · 3 páginas');page.locator('[data-docux-capture-close]').click();expect(page.locator('[data-docux-capture]')).not_to_be_visible();expect(page.locator('[data-m7-doc-state]')).to_have_attribute('data-state','saved')
  logical=int(sql("SELECT COUNT(*)-COUNT(JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.capture_session_uuid')))+COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.capture_session_uuid'))) FROM clinical_documents WHERE encounter_ref_id=1016"))
  check('Step6 logical bundle count',page.locator('[data-doc-count]').inner_text()==f'({logical})');page.screenshot(path=str(OUT/'desktop-finalized-list-webkit.png'))
  # Cancel failure must remain visible, then recover; context loss cancels the session.
  page.locator('[data-m7-capture-start]').click();page.locator('input[value="otro"]').check()
  with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/pages')) as response:page.locator('[data-docux-capture-generate]').click()
  abandoned=response.value.json()['data'];expect(page.locator('[data-docux-qr-content]')).to_be_visible()
  page.route('**/mobile-capture-sessions/*/cancel',lambda route:route.fulfill(status=503,json={'ok':False,'error':'server_error'}));page.locator('[data-docux-capture-close]').click();expect(page.locator('[data-m7-capture-state]')).to_contain_text('No se pudo cancelar');check('failed cancel stays open',page.locator('[data-docux-capture]').is_visible())
  page.unroute('**/mobile-capture-sessions/*/cancel');page.keyboard.press('Escape');expect(page.locator('[data-docux-capture]')).not_to_be_visible();check('Escape confirms cancel and focus',data(path(abandoned))['status']=='CANCELLED' and page.locator('[data-m7-capture-start]').evaluate('n=>n===document.activeElement'))
  check('no JavaScript errors',not errors);frontend.close();api.dispose()
  browser.close()
finally:
 (OUT/'server.log').write_text((ROOT/'server.log').read_text())
 (OUT/'qa.json').write_text(json.dumps({'checks':checks,'details':details,'working_database_connected':False},indent=2))
