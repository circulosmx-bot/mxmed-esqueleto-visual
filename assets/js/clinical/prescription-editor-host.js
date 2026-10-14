/* Shared container adapter for the single canonical Prescription V2 engine. */
(() => {
  'use strict';
  const modal = document.getElementById('modalReceta');
  const modalContent = modal?.querySelector('.modal-content');
  const body = modal?.querySelector('.ne-rx-modal-body');
  const workspace = document.querySelector('[data-rx-editor-workspace]');
  const mount = workspace?.querySelector('[data-rx-editor-mount]');
  if (!modal || !modalContent || !body || !workspace || !mount) return;
  const modalBodyAnchor = document.createComment('canonical prescription editor host');
  body.before(modalBodyAnchor);
  let mode = 'modal', stage = 1, returnView = 'landing';
  const stages = () => Array.from(workspace.querySelectorAll('[data-rx-host-step]'));
  const engineStage = number => body.querySelector(`[data-rx-engine-stage="${number}"]`);
  const focusStage = number => {
    const target = number === 1 ? document.getElementById('ne-rx-stage-context')
      : number === 2 ? document.getElementById('ne-rx-stage-medications')
      : number === 3 ? document.querySelector('#ne_rx_review h6')
      : number === 4 ? document.querySelector('#ne_rx_sign h6') : document.querySelector('#ne_rx_final h6');
    target?.setAttribute('tabindex','-1'); target?.focus({preventScroll:true});
  };
  const sync = (number, focus = true) => {
    stage = Math.max(1, Math.min(5, Number(number) || 1));
    stages().forEach(item => {
      const value = Number(item.dataset.rxHostStep);
      item.classList.toggle('is-current', value === stage);
      item.classList.toggle('is-complete', value < stage);
      if (value === stage) item.setAttribute('aria-current','step'); else item.removeAttribute('aria-current');
    });
    [1,2].forEach(value => engineStage(value)?.classList.toggle('d-none', value !== stage));
    const prev = workspace.querySelector('[data-rx-host-prev]');
    const next = workspace.querySelector('[data-rx-host-next]');
    const save = workspace.querySelector('[data-rx-host-save]');
    if (prev) prev.disabled = stage === 1;
    if (save) save.classList.toggle('d-none', stage !== 2);
    if (next) {
      next.textContent = stage === 2 ? 'Continuar a vista previa' : stage === 3 ? 'Continuar a firma'
        : stage === 4 ? 'Revisión final' : stage === 5 ? 'Emitir receta' : 'Continuar';
      next.disabled = stage === 4 && document.getElementById('ne_rx_final_review')?.disabled === true;
    }
    workspace.dataset.rxStage = String(stage);
    if (focus) focusStage(stage);
  };
  const restoreModalBody = () => {
    if (body.parentElement !== modalContent) modalBodyAnchor.after(body);
    body.classList.remove('rx-editor-embedded-body');
  };
  const openEmbedded = options => {
    try { window.bootstrap?.Modal?.getInstance(modal)?.hide(); } catch (_) {}
    mode = 'embedded'; returnView = options?.returnView === 'history' ? 'history' : 'landing';
    mount.appendChild(body); body.classList.add('rx-editor-embedded-body');
    workspace.dispatchEvent(new CustomEvent('mxmed:prescription-host-open',{bubbles:true,detail:options||{}}));
    window.dispatchEvent(new CustomEvent('mxmed:prescription-host-open',{detail:{mode, ...(options||{})}}));
    sync(1,false); workspace.querySelector('#rx-editor-title')?.focus({preventScroll:true});
    return true;
  };
  const closeEmbedded = () => {
    if (mode !== 'embedded') return;
    restoreModalBody(); mode='modal';
    workspace.dispatchEvent(new CustomEvent('mxmed:prescription-host-closed',{bubbles:true,detail:{returnView}}));
  };
  workspace.querySelector('[data-rx-host-close]')?.addEventListener('click',()=>window.dispatchEvent(new CustomEvent('mxmed:prescription-host-request-close')));
  workspace.querySelector('[data-rx-host-save]')?.addEventListener('click',()=>document.getElementById('ne_rx_save_draft')?.click());
  workspace.querySelector('[data-rx-host-prev]')?.addEventListener('click',()=>{
    if(stage===2)sync(1); else if(stage===3){document.getElementById('ne_rx_back_to_capture')?.click();sync(2);}
    else if(stage===4){document.getElementById('ne_rx_sign')?.classList.add('d-none');document.getElementById('ne_rx_review')?.classList.remove('d-none');sync(3);}
    else if(stage===5)document.getElementById('ne_rx_final_back')?.click();
  });
  workspace.querySelector('[data-rx-host-next]')?.addEventListener('click',()=>{
    if(stage===1)sync(2); else if(stage===2)document.getElementById('ne_rx_preview')?.click();
    else if(stage===3)document.getElementById('ne_rx_save')?.click();
    else if(stage===4)document.getElementById('ne_rx_final_review')?.click();
    else if(stage===5)document.getElementById('ne_rx_emit_signed')?.click();
  });
  window.mxmedPrescriptionHost = {openEmbedded,closeEmbedded,setStage:sync,isEmbedded:()=>mode==='embedded',stage:()=>stage,restoreModalBody};
})();
