// VIS04/VIS31: step presentation and focus mode. Commands stay in M7; context lives in the patient header.
(function () {
  const root = document.getElementById('m7-workspace');
  const pane = document.getElementById('p-expediente');
  if (!root || !pane) return;
  const body = root.querySelector('[data-m7-body]');
  const steps = [...root.querySelectorAll('[data-m7-section]')];
  const consultationTab = pane.querySelector('[data-bs-target="#t-consulta-actual"]');
  const previous = root.querySelector('[data-vis04-prev]');
  const next = root.querySelector('[data-vis04-next]');
  const progress = root.querySelector('[data-vis04-progress]');
  const label = root.querySelector('[data-vis04-editor-label]');
  const stepHelper = root.querySelector('[data-vis21-step-helper]');
  const stepCopy = {
    reason: ['Motivo / Evolución','Motivo de atención, síntomas o evolución.'],
    measurements: ['Mediciones','Signos vitales y mediciones clínicas.'],
    exam: ['Exploración','Hallazgos de la exploración física.'],
    assessment: ['Valoración','Impresión clínica, diagnósticos y análisis del caso.'],
    plan: ['Plan','Indicaciones, tratamiento y plan de atención.'],
    documents: ['Documentos / Acciones','Documentos y acciones de esta consulta.'],
    finalize: ['Finalizar','Revisión y cierre de la consulta.']
  };
  let focusWasActive = false, focusScrollToken = 0;
  const currentIndex = () => steps.findIndex(button => button.getAttribute('aria-current') === 'true');
  function consultationStartIsComfortablyVisible() {
    const rect = root.getBoundingClientRect();
    const compact = matchMedia('(max-width:819.98px)').matches;
    const minimumTop = compact ? 8 : 64;
    const maximumTop = compact ? Math.min(innerHeight * .24, 170) : Math.min(innerHeight * .28, 250);
    const usefulBottom = Math.min(innerHeight * .62, compact ? 440 : 540);
    return rect.top >= minimumTop && rect.top <= maximumTop && rect.bottom >= usefulBottom;
  }
  function positionActiveConsultation() {
    const token = ++focusScrollToken;
    requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(() => {
        if (token !== focusScrollToken
          || !document.body.classList.contains('exp-consultation-focus-active')
          || !root.getClientRects().length
          || consultationStartIsComfortablyVisible()) return;
        const behavior = matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
        root.scrollIntoView({behavior, block:'start', inline:'nearest'});
      }, 180)));
  }
  function syncFocusMode() {
    const active = body.dataset.encounterState === 'open'
      && !body.classList.contains('d-none')
      && consultationTab?.classList.contains('active')
      && !pane.classList.contains('d-none');
    document.body.classList.toggle('exp-consultation-focus-active', !!active);
    if (active && !focusWasActive) positionActiveConsultation();
    if (!active) focusScrollToken++;
    focusWasActive = !!active;
  }
  function syncStep() {
    const index = currentIndex();
    previous.disabled = index <= 0;
    next.disabled = index < 0 || index === steps.length - 1;
    progress.textContent = index < 0 ? '' : `Paso ${index + 1} de 7`;
    const selected = steps[index]?.dataset.m7Section;
    next.textContent = 'Siguiente';
    const copy = stepCopy[selected] || stepCopy.reason;
    stepHelper.textContent = copy[1];
    stepHelper.title = copy[1];
    label.textContent = copy[0];
    const editor = root.querySelector('[data-m7-editor-text]');
    editor.removeAttribute('aria-labelledby');
    editor.setAttribute('aria-describedby', 'vis21-step-helper');
    editor.placeholder = selected === 'assessment' ? 'Describe tu impresión diagnóstica, evolución y diagnósticos diferenciales relevantes.' : '';
  }
  function advance(delta) {
    const before = currentIndex(), target = steps[before + delta];
    if (!target || target.disabled) return;
    // The original handler owns dirty/busy/conflict guards. Never change section state here.
    target.click();
    if (currentIndex() !== before) target.focus({preventScroll:true});
  }
  previous.addEventListener('click', () => advance(-1));
  next.addEventListener('click', () => advance(1));
  new MutationObserver(syncStep).observe(root.querySelector('.m7-workspace-sections'), {subtree:true,attributes:true,attributeFilter:['aria-current','disabled']});
  new MutationObserver(syncFocusMode).observe(body, {attributes:true,attributeFilter:['class','data-encounter-state']});
  new MutationObserver(syncFocusMode).observe(pane, {attributes:true,attributeFilter:['class']});
  if (consultationTab) new MutationObserver(syncFocusMode).observe(consultationTab, {attributes:true,attributeFilter:['class','aria-selected']});
  syncStep();
  syncFocusMode();
  document.addEventListener('shown.bs.tab', syncFocusMode);
  document.addEventListener('hidden.bs.tab', syncFocusMode);
  ['m7:encounter-started','m7:encounter-state-changed','mxmed:encounter-lifecycle','mxmed:encounter-changed'].forEach(name => window.addEventListener(name, syncFocusMode));
})();
