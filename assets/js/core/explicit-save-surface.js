// Opt-in lifecycle for an explicit-save editor; registration never changes dirty semantics.
(function(){
  'use strict';
  window.mxmedCreateExplicitSaveSurface = function({
    isDirty, isActive, isSaving = ()=> false, save, discard, render, dialog,
    idleDelay = 4000, clock = window
  }){
    let timer = null;
    let reminderVisible = false;
    let pendingNavigation = null;
    let working = false;
    let operation = null;
    const cancelTimer = ()=>{
      if(timer !== null) clock.clearTimeout(timer);
      timer = null;
    };
    const paint = ()=> render({
      visible: reminderVisible && isDirty() && isActive() && !pendingNavigation,
      guarded: Boolean(pendingNavigation), busy: working || isSaving(),
      saving: operation === 'save' || isSaving()
    });
    function sync({ activity = false } = {}){
      if(!isDirty()){
        cancelTimer();
        reminderVisible = false;
      }else if(pendingNavigation || !isActive()){
        cancelTimer();
      }else if(!reminderVisible && (activity || timer === null)){
        cancelTimer();
        timer = clock.setTimeout(()=>{
          timer = null;
          if(isDirty() && isActive() && !pendingNavigation) reminderVisible = true;
          paint();
        }, idleDelay);
      }
      paint();
    }
    async function finishNavigation(){
      const intent = pendingNavigation;
      await dialog.close();
      pendingNavigation = null;
      reminderVisible = false;
      paint();
      intent?.proceed();
    }
    return {
      sync,
      requestNavigation(intent){
        if(!isActive() || !isDirty()) return false;
        if(pendingNavigation) return true;
        pendingNavigation = intent;
        cancelTimer();
        paint();
        dialog.open(intent.control);
        return true;
      },
      async saveAndContinue(){
        if(!pendingNavigation || working || isSaving()) return;
        working = true;
        operation = 'save';
        paint();
        try{
          if(await save() && !isDirty()) await finishNavigation();
          else dialog.error();
        }catch(_){
          dialog.error();
        }finally{
          working = false;
          operation = null;
          sync();
        }
      },
      async discardAndContinue(){
        if(!pendingNavigation || working || isSaving()) return;
        working = true;
        operation = 'discard';
        paint();
        try{
          await discard();
          if(!isDirty()) await finishNavigation();
          else dialog.error();
        }finally{
          working = false;
          operation = null;
          sync();
        }
      },
      continueEditing(){
        if(working || isSaving()) return false;
        pendingNavigation = null;
        cancelTimer();
        reminderVisible = isDirty();
        paint();
        return true;
      },
      shouldWarnBeforeUnload(){ return isDirty(); }
    };
  };
})();
