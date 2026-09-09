import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {execFileSync} from 'node:child_process';
import {createHash} from 'node:crypto';

// No destination argument: this tool owns only this previously nonexistent output.
// It never removes an existing directory (including the old local application/).
const recipe = path.dirname(fileURLToPath(import.meta.url));
const root = fs.realpathSync(path.resolve(recipe, '../..'));
const output = path.join(root, '.application-build');
const manifest = JSON.parse(fs.readFileSync(path.join(recipe, 'runtime-files.json')));
const tracked = new Set(execFileSync('git', ['ls-files', '-z'], {cwd:root, encoding:'utf8'}).split('\0'));
const forbidden = /(^|\/)(?:\.env[^/]*|\.git[^/]*|\.aws|\.ssh|\.DS_Store|node_modules|vendor|uploads|private-media|tests?|qa|docs|migrations|fixtures|tmp|temp|logs|coverage|cdk\.out|cache)(\/|$)|\.(?:sql|dump|bak|log|pem|key|zip)$|(?:^|\/)[^/]*\.config\.(?!example\.|sample\.)/i;
if (!Array.isArray(manifest) || new Set(manifest).size !== manifest.length) throw Error('invalid_manifest');
const scaffold = execFileSync('git', ['ls-files', '-z', 'infra/aws/runtime/app'], {cwd:root, encoding:'utf8'}).split('\0').filter(p => /\/(?:Dockerfile(?:\.dockerignore)?|(?:apache|php|health)\/[^/]+)$/.test(p));
const entries = [...manifest.map(p => [p, 'application/'+p]), ...scaffold.map(p => [p, p.slice('infra/aws/runtime/app/'.length)])];
for (const [source] of entries) {
 if (path.isAbsolute(source) || source.split('/').includes('..') || forbidden.test(source) || !tracked.has(source)) throw Error('unsafe_or_untracked_source: '+source);
 const absolute = path.join(root,source);
 if (!fs.lstatSync(absolute).isFile() || fs.realpathSync(absolute)!==absolute) throw Error('not_regular_source: '+source);
}
for (const required of ['composer.json','composer.lock','index.html','profiles/doctor.php','api/identity/index.php','assets/js/app.js','modules/media/bin/submit-inactive-review-batches.php','api/_lib/db.php','modules/media/services/MediaReviewBatchService.php']) {
 if (!manifest.includes(required)) throw Error('required_runtime_missing: '+required);
}
for (const required of ['Dockerfile','Dockerfile.dockerignore','apache/000-default.conf','apache/ports.conf','apache/security.conf','apache/mxmed-runtime.conf','php/zz-mxmed-production.ini','health/healthz.php','health/readyz.php']) {
 if (!entries.some(([,dest])=>dest===required)) throw Error('required_scaffold_missing: '+required);
}
fs.mkdirSync(output); // EEXIST is fail closed; no recursive deletion or symlink following.
const inventory=[];
for (const [source,relative] of entries.sort((a,b)=>a[1]<b[1]?-1:1)) {
 const bytes=fs.readFileSync(path.join(root,source));
 const dest=path.join(output,relative);
 fs.mkdirSync(path.dirname(dest),{recursive:true,mode:0o755});
 fs.writeFileSync(dest,bytes,{flag:'wx',mode:0o644});
 inventory.push({path:relative,size:bytes.length,sha256:createHash('sha256').update(bytes).digest('hex')});
}
fs.writeFileSync(path.join(output,'inventory.json'),JSON.stringify(inventory,null,2)+'\n');
console.log(JSON.stringify({output,runtimeFiles:manifest.length,forbiddenFiles:0}));
