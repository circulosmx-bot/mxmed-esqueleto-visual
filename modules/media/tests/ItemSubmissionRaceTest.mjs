import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
const sql=code=>execFileSync('php',['-r','require "modules/media/tests/ReviewBatchFixture.php";$p=mr5Pdo();'+code],{encoding:'utf8'}).trim();
const launch=(f,mode,hold=false)=>{
 const code=`require 'modules/media/tests/ReviewBatchFixture.php';class HeldSubmit extends PDO {public function commit():bool {echo "LOCKED\\n";flush();usleep(300000);return parent::commit();}}$p=${hold?"new HeldSubmit('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION])":"mr5Pdo()"};$actor=mr11Actor('${f.doctor}');$r=${mode==='item'?`(new Media\\Services\\MediaCandidateSubmissionService($p))->submit('${f.id}',$actor)`:`(new Media\\Services\\MediaReviewBatchService($p))->submit('${f.doctor}',null,$actor)`};echo json_encode($r);`;
 const child=spawn('php',['-r',code]);let out='',err='',ready;const locked=new Promise(r=>ready=r);child.stdout.on('data',b=>{out+=b;if(out.includes('LOCKED'))ready()});child.stderr.on('data',b=>err+=b);
 const done=new Promise((resolve,reject)=>child.on('exit',c=>c===0?resolve(out):reject(Error(err||out))));return {done,locked};
};
for(const modes of [['item','item'],['item','batch'],['batch','item']]){
 const f=JSON.parse(sql(`$d=mr11Doctor();$f=mr11Candidate($d,'DOCTOR_PROFILE_PHOTO');mr11Candidate($d,'PHYSICIAN_PERSONAL_LOGO');echo json_encode($f);`));
 const first=launch(f,modes[0],true);await Promise.race([first.locked,first.done.then(()=>{throw Error('no lock')})]);const second=launch(f,modes[1]);await Promise.all([first.done,second.done]);
 const state=JSON.parse(sql(`$id='${f.id}';echo json_encode($p->query("SELECT submitted_for_review_at,(SELECT COUNT(*) FROM platform_audit_events WHERE resource_reference='$id' AND action='MEDIA_REVIEW_CANDIDATE_SUBMITTED') events FROM media_review_submissions WHERE submission_id='$id'")->fetch());`));
 assert.ok(state.submitted_for_review_at);assert.equal(Number(state.events),1);console.log('PASS race '+modes.join(' vs ')+': one timestamp/event');
}
