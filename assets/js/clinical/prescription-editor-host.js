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
  const workspaceAnchor = document.createComment('canonical prescription workspace host');
  workspace.before(workspaceAnchor);
  const workspaceHome = workspaceAnchor.parentElement;
  let mode = 'modal', stage = 1, returnView = 'landing';
  let caller = 'patient', sourceElement = null;
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
    const progress = workspace.querySelector('[data-rx-host-progress]');
    if (prev) { prev.disabled = stage === 1; prev.classList.toggle('d-none',stage===1); }
    if (progress) progress.textContent=`Etapa ${stage} de 5`;
    if (save) save.classList.toggle('d-none', stage !== 2);
    if (next) {
      next.textContent = stage === 2 ? 'Continuar a vista previa →' : stage === 3 ? 'Continuar a firma →'
        : stage === 4 ? 'Revisión final →' : stage === 5 ? 'Emitir receta' : 'Continuar →';
      next.disabled = stage === 4 && document.getElementById('ne_rx_final_review')?.disabled === true;
    }
    workspace.dataset.rxStage = String(stage);
    if (focus) focusStage(stage);
  };
  const restoreModalBody = () => {
    if (body.parentElement !== modalContent) modalBodyAnchor.after(body);
    body.classList.remove('rx-editor-embedded-body');
  };
  const renderPatientContext = options => {
    const panel=workspace.querySelector('[data-rx-host-patient-context]');
    const global=options?.caller==='global';
    panel?.classList.toggle('d-none',!global);
    const patient=options?.patient||{};
    const name=workspace.querySelector('[data-rx-host-patient-name]');
    const meta=workspace.querySelector('[data-rx-host-patient-meta]');
    if(name)name.textContent=patient.name||'Paciente activo';
    if(meta)meta.textContent=[patient.age,patient.sex].filter(Boolean).join(' · ');
    const close=workspace.querySelector('[data-rx-host-close]');
    if(close)close.textContent=options?.caller==='consultation'?'← Volver a Consulta':options?.caller==='activity'?'← Volver a Actividad clínica':'← Volver a Recetas';
  };
  const openEmbedded = options => {
    try { window.bootstrap?.Modal?.getInstance(modal)?.hide(); } catch (_) {}
    mode = 'embedded'; caller=options?.caller||'patient'; sourceElement=options?.sourceElement||null;
    returnView = options?.returnView === 'history' ? 'history' : 'landing';
    if(options?.workspaceMount) options.workspaceMount.appendChild(workspace); else workspaceAnchor.after(workspace);
    workspace.classList.remove('d-none');
    options?.workspaceMount?.classList.remove('d-none');
    sourceElement?.classList.add('d-none');
    renderPatientContext(options);
    mount.appendChild(body); body.classList.add('rx-editor-embedded-body');
    workspace.dispatchEvent(new CustomEvent('mxmed:prescription-host-open',{bubbles:true,detail:{mode,...(options||{})}}));
    sync(1,false); workspace.querySelector('#rx-editor-title')?.focus({preventScroll:true});
    return true;
  };
  const closeEmbedded = () => {
    if (mode !== 'embedded') return;
    const closedCaller=caller,closedSource=sourceElement,currentParent=workspace.parentElement;
    restoreModalBody(); workspaceAnchor.after(workspace);
    if(currentParent&&currentParent!==workspaceHome)currentParent.classList.add('d-none');
    closedSource?.classList.remove('d-none');mode='modal';caller='patient';sourceElement=null;
    workspace.dispatchEvent(new CustomEvent('mxmed:prescription-host-closed',{bubbles:true,detail:{returnView,caller:closedCaller}}));
  };
  workspace.querySelector('[data-rx-host-change-patient]')?.addEventListener('click',()=>
    window.dispatchEvent(new CustomEvent('mxmed:prescription-host-request-change-patient')));
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
  window.mxmedPrescriptionHost = {openEmbedded,closeEmbedded,setStage:sync,isEmbedded:()=>mode==='embedded',stage:()=>stage,caller:()=>caller,restoreModalBody};
})();
