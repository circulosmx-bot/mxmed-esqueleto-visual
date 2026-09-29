"""Large file transport QA; generated pixels and all writes are disposable."""
import hashlib,json,os,random,re,struct,subprocess,zlib
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
BASE,DB=os.environ['FLOW_R1_QA_BASE'],os.environ['FLOW_R1_QA_DB'];assert re.fullmatch(r'flow_r1_qa_[a-f0-9]{12}',DB)
ROOT=Path(os.environ['FLOW_R1_QA_ROOT']);OUT=Path(os.environ['FLOW_R1_ARTIFACTS']);OUT.mkdir(exist_ok=True,parents=True)
API=BASE+'/api/clinical/index.php/';checks={};files={};tokens=[]
def check(name,ok=True):assert ok,name;checks[name]='PASS';print('PASS '+name,flush=True)
def sql(q):return subprocess.check_output(['mysql','-N',DB,'-e',q],text=True).strip()
def docs():return int(sql('SELECT COUNT(*) FROM clinical_documents'))
def png(w,h):
 def chunk(t,b):return struct.pack('>I',len(b))+t+b+struct.pack('>I',zlib.crc32(t+b)&0xffffffff)
 rng=random.Random(42);raw=b''.join(b'\0'+rng.randbytes(w*3) for _ in range(h))
 return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',w,h,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(raw,0))+chunk(b'IEND',b'')
normal=png(1200,800);near=png(3000,2600)
check('near-limit fixture is valid 20-24 MB PNG',20*1024*1024<len(near)<24*1024*1024)
with sync_playwright() as p:
 api=p.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'});anon=p.request.new_context()
 def issue():
  r=api.post(API+'note-capture-tokens',data={'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','capture_classification':'clinical_image'});assert r.status==201,r.text();d=r.json()['data'];tokens.append(d['token']);return d
 def upload(t,b):return anon.post(API+'note-capture-tokens/'+t+'/upload',multipart={'file':{'name':'synthetic-size-qa.png','mimeType':'image/png','buffer':b}},timeout=120000)
 for name,b,w,h in [('normal',normal,1200,800),('near',near,3000,2600)]:
  d=issue();n=docs();r=upload(d['token'],b);check(name+' upload accepted exactly once',r.status==201 and docs()==n+1)
  data=sql("SELECT CONCAT(d.document_type,'|',JSON_UNQUOTE(JSON_EXTRACT(d.payload_json,'$.capture_classification')),'|',b.variant_role,'|',b.byte_length,'|',b.sha256) FROM clinical_documents d JOIN clinical_note_capture_tokens t ON t.document_id=d.id JOIN clinical_document_binaries b ON b.document_id=d.id WHERE t.token='"+d['token']+"'")
  check(name+' existing canonical original/classification preserved',data=='image|clinical_image|ORIGINAL|'+str(len(b))+'|'+hashlib.sha256(b).hexdigest())
  check(name+' second upload rejected',upload(d['token'],normal).status==409 and docs()==n+1)
  files[name]={'bytes':len(b),'width':w,'height':h,'mime':'image/png','http':r.status,'server_optimization':'NOT_IN_CURRENT_CANONICAL_WRITER','stored_original_sha256_matches':True}
 for name,size,error,post in [('over-product',26*1024*1024,0,False),('over-php-file',33*1024*1024,1,False),('over-php-post',41*1024*1024,None,True)]:
  d=issue();n=docs();r=upload(d['token'],b'X'*size);body=r.json();check(name+' stable size error and no document',r.status==400 and body['error']=='UPLOAD_TOO_LARGE' and body['meta']['upload_error']==error and body['meta']['post_max_size_exceeded']==post and docs()==n)
  check(name+' failed upload does not consume token',api.get(API+'note-capture-tokens/'+d['token']).json()['data']['status']=='pending')
  api.post(API+'note-capture-tokens/'+d['token']+'/cancel',data={})
 browser=p.webkit.launch();page=browser.new_page(viewport={'width':390,'height':844});d=issue();page.goto(d['mobile_url']);expect(page.locator('#captureChooseFile')).to_be_enabled();expect(page.locator('#captureMaxSize')).to_have_text('Tamaño máximo: 25 MB')
 writes=[];page.on('request',lambda r:writes.append(r.url) if r.method=='POST' else None)
 page.locator('#captureFile').set_input_files({'name':'too-large.png','mimeType':'image/png','buffer':b'X'*(26*1024*1024)});expect(page.locator('#captureMsg')).to_contain_text('supera el límite de 25 MB');expect(page.locator('#captureSubmit')).to_be_disabled();check('mobile early size warning sends no request',not writes);page.screenshot(path=str(OUT/'over-limit-warning-390-webkit.png'))
 page.locator('#captureFile').set_input_files({'name':'normal-2.75MB.png','mimeType':'image/png','buffer':normal});n=docs()
 with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/upload')) as response:page.locator('#captureSubmit').click()
 check('normal mobile UI upload larger than old 2M limit',response.value.status==201 and docs()==n+1);expect(page.locator('#captureMsg')).to_contain_text('Documento enviado correctamente');page.screenshot(path=str(OUT/'normal-mobile-upload-390-webkit.png'));browser.close()
 api.dispose();anon.dispose()
probe=[json.loads(line) for line in (ROOT/'runtime-probe.jsonl').read_text().splitlines()];check('PHP post limit exceeds file limit',all(x['upload_max_filesize']=='32M' and x['post_max_size']=='40M' for x in probe));check('existing 128M memory suffices for current canonical path',all(x['memory_limit']=='128M' and x['peak_php_bytes']<128*1024*1024 for x in probe))
(OUT/'large-file-qa.json').write_text(json.dumps({'qa':'PASS','checks':checks,'files':files,'runtime_probe':probe,'optimization_acceptance':'NOT_MET: existing canonical writer retains ORIGINAL; legacy GD pipeline was not substituted','working_database_connected':False},indent=2))
print('R42_LARGE_FILE_QA=PASS',flush=True)
