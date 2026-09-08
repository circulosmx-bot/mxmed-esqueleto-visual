import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFile} from 'node:fs/promises';
const base=process.env.PUBLIC_URL||'http://127.0.0.1:8091';
const php=code=>execFileSync('php',['-r',code],{encoding:'utf8'}).trim();
const sessions=['1','2'].map(doctor=>php(`session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'profile-photo-qa','doctor_id'=>'${doctor}'];echo session_id();session_write_close();`));
const call=async(session,method='GET',body=null,token='',suffix='')=>{
 const r=await fetch(base+'/api/media/profile-photo.php'+suffix,{method,body,headers:{...(session?{Cookie:'PHPSESSID='+session}:{}),...(token?{'X-Profile-Photo-CSRF':token}:{})}});
 return {status:r.status,body:await r.json()};
};
const bytes=await readFile('assets/img/doctors/avatars/dr-female.png');
const form=(data=bytes,type='image/png',name='portrait.png')=>{const f=new FormData();f.append('image',new Blob([data],{type}),name);return f;};
const state=()=>JSON.parse(php(`require 'api/_lib/db.php';$p=mxmed_pdo();echo json_encode(['photo'=>$p->query("SELECT photo_url FROM profiles_doctors WHERE doctor_id='1'")->fetchColumn(),'gallery'=>$p->query("SELECT media_id,checksum_sha256 FROM media_assets WHERE owner_id='1' AND purpose='DOCTOR_GALLERY' AND status='READY' ORDER BY media_id")->fetchAll(PDO::FETCH_ASSOC),'ready'=>(int)$p->query("SELECT COUNT(*) FROM media_assets WHERE owner_id='1' AND purpose='DOCTOR_PROFILE_PHOTO' AND status='READY'")->fetchColumn()]);`));
const retired=id=>{
 const a=JSON.parse(php(`require 'api/_lib/db.php';require 'modules/media/bootstrap.php';$p=mxmed_pdo();$s=$p->prepare('SELECT status,storage_key FROM media_assets WHERE media_id=?');$s->execute(['${id}']);$a=$s->fetch(PDO::FETCH_ASSOC);echo json_encode(['status'=>$a['status'],'exists'=>mxmed_public_media_storage()->exists($a['storage_key'])]);`));
 assert.deepEqual(a,{status:'DELETED',exists:false});
};
const before=state();assert.equal(before.photo,null,'requires empty QA profile photo; never replace a real photo');
let token='',touched=false;const ids=[];
try{
 assert.equal((await call(null,'POST',form())).status,401);
 const first=await call(sessions[0]);assert.equal(first.status,200);assert.equal(first.body.data.photo,null);token=first.body.data.csrf_token;
 assert.equal((await call(sessions[0],'POST',form())).status,403);
 assert.equal((await call(sessions[0],'POST',form(),'invalid')).status,403);
 let r=await call(sessions[0],'POST',form(),token,'?doctor_id=2');assert.equal(r.status,200,JSON.stringify(r));touched=true;
 let photo=r.body.data.photo;ids.push(photo.media_id);assert.equal(state().photo,photo.public_url);assert.equal(state().ready,1);
 assert.equal((await fetch(base+photo.public_url)).status,200);
 const other=await call(sessions[1]);assert.equal(other.body.data.photo,null,'doctor B must have no photo for this fixture');
 assert.equal((await call(sessions[1],'DELETE',null,other.body.data.csrf_token,'?doctor_id=1&media_id='+photo.media_id)).status,200);
 assert.equal(state().photo,photo.public_url,'B cannot delete A by query scope');
 if(process.env.PHOTO_MODERN_SOURCE){
  const previous=photo;
  r=await call(sessions[0],'POST',form(await readFile(process.env.PHOTO_MODERN_SOURCE),'image/jpeg','camera.jpg'),token);
  assert.equal(r.status,200,JSON.stringify(r));photo=r.body.data.photo;ids.push(photo.media_id);
  assert.ok(photo.width<=800&&photo.height<=800&&photo.byte_size<=153600);retired(previous.media_id);
  console.log('PASS: near-10 MiB / 12 MP upload through real PHP multipart endpoint');
 }
 const bad=[form(bytes,'image/png','payload.php'),form(bytes,'image/jpeg','portrait.png'),form(bytes.subarray(0,70),'image/png','broken.png'),form(new Uint8Array(10485761),'image/png','large.png')];
 for(const body of bad){r=await call(sessions[0],'POST',body,token);assert.equal(r.status,422,JSON.stringify(r));assert.equal(state().photo,photo.public_url);assert.equal(state().ready,1);}
 if(process.env.PROFILE_PHOTO_BROWSER_QA==='1'){
  try{execFileSync('node',['modules/media/tests/DoctorProfilePhotoBrowserTest.mjs'],{stdio:'inherit',env:{...process.env,PUBLIC_URL:base,PHOTO_QA_SESSION:sessions[0]}});}
  finally{const current=(await call(sessions[0])).body.data.photo;if(current){photo=current;if(!ids.includes(photo.media_id))ids.push(photo.media_id);}}
 }
 r=await call(sessions[0],'POST',form(await readFile('assets/img/doctors/avatars/dr-male.png')),token);assert.equal(r.status,200,JSON.stringify(r));
 const previous=photo;photo=r.body.data.photo;ids.push(photo.media_id);assert.notEqual(photo.media_id,previous.media_id);assert.equal(state().ready,1);retired(previous.media_id);assert.equal((await fetch(base+previous.public_url)).status,404);
 assert.equal((await call(sessions[0])).body.data.photo.media_id,photo.media_id);
 assert.equal((await call(sessions[0],'DELETE',null,token)).status,200);retired(photo.media_id);assert.equal(state().photo,null);assert.equal(state().ready,0);
 assert.deepEqual(state().gallery,before.gallery);
 const publicHtml=await (await fetch(base+'/profiles/doctor.php?doctor_id=1&mxmed_plan=standard')).text();
 assert.ok(publicHtml.includes('src="/assets/img/doctors/avatars/dr-female.png"'));
 assert.ok(publicHtml.includes('Ver fotos · '+before.gallery.length));
 console.log('PASS: auth/CSRF, server owner, cross-doctor isolation, invalid extension/MIME/corruption/size, atomic invalid replacement, replacement retirement, removal, unchanged gallery');
}finally{
 if(touched){const current=await call(sessions[0]);if(current.body.data.photo){assert.ok(ids.includes(current.body.data.photo.media_id));assert.equal((await call(sessions[0],'DELETE',null,token)).status,200);}}
 for(const id of sessions)php(`session_id('${id}');session_start();session_destroy();`);
}
