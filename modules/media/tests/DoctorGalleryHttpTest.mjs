import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFile} from 'node:fs/promises';
const base=process.env.PUBLIC_URL || 'http://127.0.0.1:8092';
const php=code=>execFileSync('php',['-r',code],{encoding:'utf8'}).trim();
// Test-only sessions, created locally; never add a production authentication bypass.
const sessions=['1','1','2'].map(doctor=>php(`session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'gallery-qa','doctor_id'=>'${doctor}'];echo session_id();session_write_close();`));
const ids=[];
const call=async(session,method='GET',body=null,token='',suffix='')=>{
  const response=await fetch(base+'/api/media/gallery.php'+suffix,{method,body,headers:{...(session?{Cookie:'PHPSESSID='+session}:{}),...(token?{'X-Gallery-CSRF':token}:{})}});
  return {status:response.status,body:await response.json()};
};
try{
  assert.equal((await call(null)).status,401);
  const before=await call(sessions[0]);assert.equal(before.status,200);
  const token=before.body.data.csrf_token;
  const bytes=await readFile('assets/img/doctors/avatars/dr-male.png');
  const form=()=>{const f=new FormData();f.append('image',new Blob([bytes],{type:'image/png'}),'sample.png');return f;};
  assert.equal((await call(sessions[0],'POST',form())).status,403);
  const uploaded=await call(sessions[0],'POST',form(),token);assert.equal(uploaded.status,200,JSON.stringify(uploaded));
  const added=uploaded.body.data.images.filter(x=>!before.body.data.images.some(b=>b.media_id===x.media_id));
  ids.push(...added.map(x=>x.media_id));assert.equal(added.length,1);
  const second=await call(sessions[1]);assert.deepEqual(second.body.data.images,uploaded.body.data.images);
  assert.equal((await fetch(base+added[0].public_url)).status,200);
  const other=await call(sessions[2]);
  assert.equal((await call(sessions[2],'DELETE',null,other.body.data.csrf_token,'?media_id='+ids[0])).status,404);
  assert.ok((await call(sessions[0])).body.data.images.some(x=>x.media_id===ids[0]));
  assert.equal((await call(sessions[0],'DELETE',null,token,'?media_id='+ids[0])).status,200);
  assert.deepEqual((await call(sessions[1])).body.data.images,before.body.data.images);
  assert.equal((await fetch(base+added[0].public_url)).status,404);
  console.log('PASS: authenticated upload/list/reload/second session/public URL/delete, CSRF and cross-doctor protection');
}finally{
  for(const id of ids){
    php(`require 'api/_lib/db.php';require 'modules/media/bootstrap.php';$p=mxmed_pdo();$s=$p->prepare('SELECT storage_key FROM media_assets WHERE media_id=?');$s->execute(['${id}']);$key=$s->fetchColumn();if($key)mxmed_public_media_storage()->delete($key);$p->prepare('DELETE FROM media_assets WHERE media_id=?')->execute(['${id}']);`);
  }
  for(const id of sessions)php(`session_id('${id}');session_start();session_destroy();`);
}
