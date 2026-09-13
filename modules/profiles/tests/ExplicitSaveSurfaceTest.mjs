import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const context={window:{}};
vm.runInNewContext(readFileSync(new URL('../../../assets/js/core/explicit-save-surface.js',import.meta.url),'utf8'),context);
let now=0,id=0;const tasks=new Map();
const clock={setTimeout(fn,delay){tasks.set(++id,{at:now+delay,fn});return id},clearTimeout(id){tasks.delete(id)}};
const advance=ms=>{now+=ms;for(const [id,t] of [...tasks])if(t.at<=now){tasks.delete(id);t.fn()}};
let dirty=false,active=true,saving=false,last,opens=0,closes=0,errors=0,saveCalls=0,discardCalls=0,navigated=[];
let saveResult=true,resolveSave;
const surface=context.window.mxmedCreateExplicitSaveSurface({
 isDirty:()=>dirty,isActive:()=>active,isSaving:()=>saving,clock,
 render:s=>last=s,
 dialog:{open(){opens++},async close(){closes++},error(){errors++}},
 async save(){saveCalls++;if(resolveSave)await new Promise(r=>{resolveSave=r});if(saveResult)dirty=false;return saveResult},
 discard(){discardCalls++;dirty=false}
});
surface.sync();assert.equal(last.visible,false);assert.equal(tasks.size,0);
dirty=true;surface.sync({activity:true});assert.equal(last.visible,false);advance(1999);assert.equal(last.visible,false);
surface.sync({activity:true});advance(1999);assert.equal(last.visible,false);advance(1);assert.equal(last.visible,true);
surface.sync({activity:true});assert.equal(last.visible,true);assert.equal(tasks.size,0);
dirty=false;surface.sync();assert.equal(last.visible,false);
dirty=true;surface.sync({activity:true});advance(1000);dirty=false;surface.sync();advance(5000);assert.equal(last.visible,false);assert.equal(tasks.size,0);
assert.equal(surface.requestNavigation({proceed:()=>navigated.push('clean')}),false);assert.equal(opens,0);
dirty=true;surface.sync({activity:true});assert.equal(surface.requestNavigation({proceed:()=>navigated.push('first')}),true);
assert.equal(surface.requestNavigation({proceed:()=>navigated.push('replacement')}),true);assert.equal(opens,1);advance(5000);assert.equal(last.visible,false);
assert.equal(surface.continueEditing(),true);assert.equal(dirty,true);assert.equal(last.visible,true);assert.deepEqual(navigated,[]);
surface.requestNavigation({proceed:()=>navigated.push('discard')});await surface.discardAndContinue();assert.equal(discardCalls,1);assert.equal(saveCalls,0);assert.equal(dirty,false);assert.deepEqual(navigated,['discard']);
dirty=true;surface.requestNavigation({proceed:()=>navigated.push('saved')});saveResult=false;await surface.saveAndContinue();assert.equal(errors,1);assert.equal(dirty,true);assert.deepEqual(navigated,['discard']);assert.equal(last.guarded,true);assert.equal(last.busy,false);
saveResult=true;resolveSave=true;const inFlight=surface.saveAndContinue();assert.equal(last.busy,true);assert.equal(last.saving,true);await surface.saveAndContinue();await surface.discardAndContinue();assert.equal(surface.continueEditing(),false);assert.equal(saveCalls,2);assert.equal(discardCalls,1);resolveSave();await inFlight;assert.deepEqual(navigated,['discard','saved']);assert.equal(last.guarded,false);assert.equal(last.visible,false);
dirty=true;assert.equal(surface.shouldWarnBeforeUnload(),true);dirty=false;assert.equal(surface.shouldWarnBeforeUnload(),false);
active=false;dirty=true;assert.equal(surface.requestNavigation({proceed(){}}),false);surface.sync({activity:true});assert.equal(tasks.size,0);
assert.equal(closes,2);
console.log('EXPLICIT_SAVE_SURFACE=PASS: deterministic 0/1999/2000 ms, debounce, revert cancellation, stable reminder, scoped navigation, one pending intent, discard without save, retry, duplicate/close lock, clean and beforeunload decisions');
