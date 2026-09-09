import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {execFileSync} from 'node:child_process';
import {createHash} from 'node:crypto';
const recipe=path.dirname(fileURLToPath(import.meta.url));
const inputs=JSON.parse(fs.readFileSync(path.join(recipe,'build-inputs.json')));
for(const key of ['php','composer']) if(!/^[a-z0-9./-]+@sha256:[a-f0-9]{64}$/.test(inputs[key])) throw Error('immutable_base_required');
if(inputs.platform!=='linux/amd64'||!/^\d+\.\d+\.\d+$/.test(inputs.phpredis)) throw Error('invalid_build_inputs');
const context=path.resolve(recipe,'../../.application-build');
if(fs.realpathSync(context)!==context)throw Error('symlink_context');
const inventory=JSON.parse(fs.readFileSync(path.join(context,'inventory.json')));
const expected=new Map(inventory.map(f=>[f.path,f]));
if(expected.size!==inventory.length)throw Error('duplicate_inventory_entry');
const found=[];
function check(directory,prefix='') {
 for(const name of fs.readdirSync(directory)) {
  const relative=prefix+name,absolute=path.join(directory,name),stat=fs.lstatSync(absolute);
  if(stat.isDirectory())check(absolute,relative+'/');
  else {
   if(!stat.isFile())throw Error('non_regular_context_file');
   if(relative==='inventory.json')continue;
   const entry=expected.get(relative);
   if(!entry||entry.size!==stat.size||entry.sha256!==createHash('sha256').update(fs.readFileSync(absolute)).digest('hex'))throw Error('context_inventory_mismatch: '+relative);
   found.push(relative);
  }
 }
}
check(context);if(found.length!==expected.size)throw Error('incomplete_context');
execFileSync('docker',['build','--platform',inputs.platform,'--build-arg','PHP_BASE_IMAGE='+inputs.php,'--build-arg','COMPOSER_BASE_IMAGE='+inputs.composer,'--build-arg','PHPREDIS_VERSION='+inputs.phpredis,'--tag','mxmed-application:local-validation',path.resolve(recipe,'../../.application-build')],{stdio:'inherit'});
