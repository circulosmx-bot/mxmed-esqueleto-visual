/* Presentation-only regression of the actual consumer, no mocked authorization or writes. */
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('assets/js/staff-workflow.js','utf8');
const fn=source.slice(source.indexOf('function invitationResult(r){'),source.indexOf('async function reconcile(){'));
const feedback={textContent:''},context={wizard:{},remember(){},renderWizard(){},$:()=>feedback,say(){},changed(){},Date};
vm.createContext(context);vm.runInContext(fn,context);
const checks=[];
for(const [state,label] of [['CONSUMED','aceptada'],['REVOKED','revocada'],['REPLACED','reemplazada'],['EXPIRED','vencida'],['ACTIVE','vencida']]){
 context.invitationResult({state,expires_at:'2000-01-01 00:00:00.000000'});assert.ok(feedback.textContent.includes('invitación '+label));checks.push({name:state+' retains authoritative meaning past expiry',pass:true});
}
context.invitationResult({state:'ACTIVE',expires_at:'2099-01-01 00:00:00.000000',delivery_state:'DELIVERY_PENDING'});assert.ok(feedback.textContent.includes('entrega no está confirmada'));checks.push({name:'No transport acknowledgement is invented',pass:true});
fs.writeFileSync('docs/evidence/staff-doc-vis02/invitation-labels.json',JSON.stringify({result:'PASS',scope:'Actual presentation function only; canonical states/dates independently verified in browser QA',checks},null,2)+'\n');console.log('PASS',checks.length,'invitation label regressions');
