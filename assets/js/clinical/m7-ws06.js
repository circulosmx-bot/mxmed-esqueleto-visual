// WS06: the accepted exact-pair M6 gate controls legacy consultation editors.
(function(){
  const patientPane = document.getElementById('p-expediente');
  const panes = ['t-historia', 't-exploracion'].map(id=>document.getElementById(id)).filter(Boolean);
  if(!patientPane || panes.length !== 2) return;
  const selectedPatient = ()=>String(patientPane.dataset.patientId || patientPane.dataset.activePatientId || '').trim();
  const changedByCutover = new WeakSet();
  const legacyCards = ['#t-historia', '#t-exploracion'].map(target=>
    document.querySelector(`[data-exp-completion-target="${target}"]`)).filter(Boolean);
  const currentCard = document.querySelector('[data-exp-completion-target="#t-consulta-actual"]');
  const currentCardDescription = currentCard?.querySelector('.mx-clinical-completion-card-copy span');
  const originalCurrentDescription = currentCardDescription?.textContent || '';
  const originalLabels = ['t-historia', 't-exploracion'].map(key=>{
    const label = document.querySelector(`[data-tab-key="${key}"] .tab-lbl`);
    return { label, text:label?.textContent || '' };
  });
  let gated = true;
  let generation = 0;
  const banners = panes.map(pane=>{
    const banner = document.createElement('div');
    banner.className = 'alert alert-info d-none m7-legacy-history-notice';
    banner.setAttribute('role', 'status');
    banner.innerHTML = '<strong>Registro anterior · sólo lectura.</strong> Los datos guardados permanecen disponibles como referencia histórica. Los borradores anteriores no se convierten en consultas. <button type="button" class="btn btn-outline-primary btn-sm ms-2" data-m7-legacy-open>Ir a consulta ambulatoria</button>';
    pane.prepend(banner);
    banner.querySelector('[data-m7-legacy-open]').addEventListener('click', ()=>{
      const tab = document.querySelector('[data-bs-target="#t-consulta-actual"], [href="#t-consulta-actual"]');
      if(tab && window.bootstrap?.Tab){
        tab.addEventListener('shown.bs.tab', ()=>document.getElementById('m7-workspace-title')?.focus(), { once:true });
        document.getElementById('m7-workspace-title')?.setAttribute('tabindex', '-1');
        window.bootstrap.Tab.getOrCreateInstance(tab).show();
      }
    });
    return banner;
  });
  const isWriter = node=>{
    if(node.closest('.m7-legacy-history-notice, [data-exp-completion-hub-panel]')) return false;
    if(node.matches('input, select, textarea')) return true;
    return node.matches('button') && !node.matches('[data-bs-toggle="collapse"], [data-bs-toggle="pill"], [role="tab"], .nav-link, .accordion-button');
  };
  const apply = ()=>{
    legacyCards.forEach(card=>{
      card.classList.toggle('d-none', gated);
      card.classList.toggle('is-recommended', !gated && card.dataset.expCompletionTarget === '#t-historia');
    });
    currentCard?.classList.toggle('is-recommended', gated);
    if(currentCardDescription) currentCardDescription.textContent = gated
      ? 'Inicia o continúa la consulta ambulatoria de este paciente.' : originalCurrentDescription;
    originalLabels.forEach(({label, text}, index)=>{
      if(label) label.textContent = gated ? (index === 0 ? 'Historia anterior' : 'Exploración anterior') : text;
    });
    panes.forEach((pane, index)=>{
      pane.dataset.m7LegacyReadOnly = gated ? 'true' : 'false';
      banners[index].classList.toggle('d-none', !gated || !selectedPatient());
      pane.querySelectorAll('input, select, textarea, button').forEach(node=>{
        if(!isWriter(node)) return;
        if(gated){
          if(!node.disabled){ changedByCutover.add(node); node.disabled = true; }
        } else if(changedByCutover.has(node)){
          node.disabled = false;
          changedByCutover.delete(node);
        }
      });
    });
  };
  async function refresh(){
    const patientId = selectedPatient();
    const current = ++generation;
    banners.forEach(banner=>{ banner.querySelector('strong').textContent = 'Registro anterior · sólo lectura.'; });
    gated = true; // fail closed while exact-pair authority is unresolved
    apply();
    if(!patientId) return;
    try {
      const response = await fetch(`/api/clinical/index.php/patients/${encodeURIComponent(patientId)}/encounters/active`, {
        credentials:'same-origin', headers:{ Accept:'application/json' }
      });
      const result = await response.json();
      if(current !== generation || selectedPatient() !== patientId) return;
      if(!response.ok || result?.ok !== true) throw new Error('No se pudo verificar la autoridad clínica.');
      gated = result.meta?.integrity_v1 === true;
      apply();
    } catch(_){
      if(current !== generation || selectedPatient() !== patientId) return;
      banners.forEach(banner=>{ banner.querySelector('strong').textContent = 'No se pudo verificar la autoridad clínica. Registro anterior · sólo lectura.'; });
      gated = true;
      apply();
    }
  }
  ['patient:selected', 'expediente:patient_changed', 'expediente:patient-changed'].forEach(name=>window.addEventListener(name, refresh));
  let lastPatient = selectedPatient();
  new MutationObserver(()=>{
    const next = selectedPatient();
    if(next !== lastPatient){ lastPatient = next; refresh(); }
  }).observe(patientPane, { attributes:true, attributeFilter:['data-patient-id','data-active-patient-id'] });
  const observer = new MutationObserver(()=>{ if(gated) apply(); });
  panes.forEach(pane=>observer.observe(pane, { subtree:true, childList:true, attributes:true, attributeFilter:['disabled'] }));
  refresh();
})();
