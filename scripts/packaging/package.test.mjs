import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'mxmed-package-test-'));
const run=(cmd,args,cwd)=>execFileSync(cmd,args,{cwd,encoding:'utf8',stdio:['ignore','pipe','pipe']});
try {
 const inventories=[];
 for(const name of ['accepted','candidate','repeat']) {
  const checkout=path.join(temp,name);
  run('git',['clone','--quiet','--no-hardlinks','--no-checkout',root,checkout],temp);
  run('git',['checkout','--quiet','--detach','29dcb2d57ab8b15cebf33838b8208afd84828e39'],checkout);
  // New recipe overlaid on clean accepted checkout; never copy machine-local payload.
  fs.cpSync(path.join(root,'scripts/packaging'),path.join(checkout,'scripts/packaging'),{recursive:true});
  fs.copyFileSync(path.join(root,'infra/aws/runtime/app/Dockerfile'),path.join(checkout,'infra/aws/runtime/app/Dockerfile'));
  if(name!=='accepted') {
   for(const file of JSON.parse(fs.readFileSync(path.join(root,'scripts/packaging/runtime-files.json')))) fs.copyFileSync(path.join(root,file),path.join(checkout,file));
  }
  fs.writeFileSync(path.join(checkout,'api/mxmed-db.config.php'),'harmless sentinel');
  fs.writeFileSync(path.join(checkout,'assets/local-sentinel.js'),'harmless sentinel');
  run('node',['scripts/packaging/assemble-application.mjs'],checkout);
  const inventory=JSON.parse(fs.readFileSync(path.join(checkout,'.application-build/inventory.json')));
  assert(!inventory.some(f=>/sentinel|mxmed-db.config.php|uploads\//.test(f.path)));
  assert(inventory.some(f=>f.path==='application/modules/media/bin/submit-inactive-review-batches.php'));
  assert.throws(()=>run('node',['scripts/packaging/assemble-application.mjs'],checkout));
  const injected=path.join(checkout,'.application-build/untracked-local-state');
  fs.writeFileSync(injected,'harmless sentinel');
  assert.throws(()=>run('node',['scripts/packaging/build-application.mjs'],checkout),/context_inventory_mismatch/);
  fs.unlinkSync(injected);
  inventories.push(inventory);
  console.log('PASS '+name+' isolated checkout, no local leakage, output overwrite rejected');
 }
 assert.deepEqual(inventories[1],inventories[2]);
 const differences=inventories[0].filter((f,i)=>f.sha256!==inventories[1][i].sha256).map(f=>f.path);
 assert.deepEqual(differences.sort(),['GalleryReviewCandidateService.php','MediaReviewBatchService.php','PhysicianMediaReviewCandidateService.php'].map(f=>'application/modules/media/services/'+f).sort());
 console.log('PASS repeated inventory; candidate includes exactly three MR11.1 runtime changes');
 const broken=path.join(temp,'broken');
 run('git',['clone','--quiet','--no-hardlinks',root,broken],temp);
 fs.cpSync(path.join(root,'scripts/packaging'),path.join(broken,'scripts/packaging'),{recursive:true});
 fs.unlinkSync(path.join(broken,'modules/media/services/MediaReviewBatchService.php'));
 assert.throws(()=>run('node',['scripts/packaging/assemble-application.mjs'],broken));
 assert(!fs.existsSync(path.join(broken,'.application-build')));
 console.log('PASS missing dependency rejected before output creation');
 run('git',['checkout','--','modules/media/services/MediaReviewBatchService.php'],broken);
 const target=path.join(temp,'do-not-touch');fs.mkdirSync(target);fs.writeFileSync(path.join(target,'sentinel'),'safe');
 fs.symlinkSync(target,path.join(broken,'.application-build'),'dir');
 assert.throws(()=>run('node',['scripts/packaging/assemble-application.mjs'],broken));
 assert.equal(fs.readFileSync(path.join(target,'sentinel'),'utf8'),'safe');
 fs.unlinkSync(path.join(broken,'.application-build'));
 fs.writeFileSync(path.join(broken,'api/mxmed-db.config.php'),'harmless sentinel');
 run('git',['add','-f','api/mxmed-db.config.php'],broken);
 const manifestPath=path.join(broken,'scripts/packaging/runtime-files.json');
 const manifest=JSON.parse(fs.readFileSync(manifestPath));manifest.push('api/mxmed-db.config.php');fs.writeFileSync(manifestPath,JSON.stringify(manifest));
 assert.throws(()=>run('node',['scripts/packaging/assemble-application.mjs'],broken));
 assert(!fs.existsSync(path.join(broken,'.application-build')));
 console.log('PASS symlink destination protected; even tracked local config rejected');
} finally {fs.rmSync(temp,{recursive:true,force:true});}
