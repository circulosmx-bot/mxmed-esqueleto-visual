// VIS04: presentation and read-only context. All commands stay in the M7 handlers.
(function () {
  const root = document.getElementById('m7-workspace');
  const pane = document.getElementById('p-expediente');
  if (!root || !pane) return;
  const body = root.querySelector('[data-m7-body]');
  const steps = [...root.querySelectorAll('[data-m7-section]')];
  const previous = root.querySelector('[data-vis04-prev]');
  const next = root.querySelector('[data-vis04-next]');
  const progress = root.querySelector('[data-vis04-progress]');
  const label = root.querySelector('[data-vis04-editor-label]');
  const reasonHelp = root.querySelector('#vis04-reason-help');
  const currentIndex = () => steps.findIndex(button => button.getAttribute('aria-current') === 'true');
  function syncStep() {
    const index = currentIndex();
    previous.disabled = index <= 0;
    next.disabled = index < 0 || index === steps.length - 1;
    progress.textContent = index < 0 ? '' : `Paso ${index + 1} de 7`;
    const reason = steps[index]?.dataset.m7Section === 'reason';
    label.textContent = reason ? 'Registro de motivo y evolución' : 'Contenido de la consulta';
    label.classList.toggle('visually-hidden', reason);
    reasonHelp.classList.toggle('d-none', !reason);
    const editor = root.querySelector('[data-m7-editor-text]');
    if (reason) editor.setAttribute('aria-describedby', 'vis04-reason-help');
    else editor.removeAttribute('aria-describedby');
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
  syncStep();
  let identity = '', epoch = 0;
  const selected = () => String(pane.dataset.patientId || pane.dataset.activePatientId || '').trim();
  const text = (key, value) => { root.querySelector(`[data-vis04-context="${key}"]`).textContent = value; };
  const brief = (rows, title, empty) => rows.length ? title(rows[0]) + (rows.length > 1 ? ` · + ${rows.length - 1} más` : '') : empty;
  const reviewCue = root.querySelector('[data-vis12-review-cue]');
  const reviewCueItems = root.querySelector('[data-vis12-review-cue-items]');
  function paintReviewCue(antecedents, allergies, medications, problems) {
    const pending = [];
    const antecedentStates = antecedents.status === 'fulfilled' ? Object.values(antecedents.value.knowledge_state_by_category || {}) : [];
    if (antecedentStates.some(state => ['UNKNOWN','NEEDS_REVIEW'].includes(String(state)))) pending.push('Antecedentes');
    if (allergies.status === 'fulfilled' && !['CONFIRMED_NONE','REVIEWED_WITH_ALLERGIES'].includes(String(allergies.value.knowledge_state || 'UNKNOWN'))) pending.push('Alergias');
    if (medications.status === 'fulfilled' && ['UNKNOWN','UNREVIEWED','NEEDS_REVIEW'].includes(String(medications.value.knowledge_state || ''))) pending.push('Medicación');
    if (problems.status === 'fulfilled' && ['UNKNOWN','UNREVIEWED','NEEDS_REVIEW'].includes(String(problems.value.knowledge_state || ''))) pending.push('Problemas');
    reviewCueItems.textContent = pending.join(' · ');
    reviewCue.classList.toggle('d-none', pending.length === 0);
  }
  async function refreshContext() {
    const patient = selected(), key = body.dataset.encounterKey || '';
    const nextIdentity = body.classList.contains('d-none') ? '' : `${patient}:${key}`;
    if (identity === nextIdentity) return;
    identity = nextIdentity;
    const run = ++epoch;
    if (!identity || !patient || !key) return;
    ['allergies','problems','medications','tasks','recent'].forEach(k => text(k, 'Consultando…'));
    const read = async path => {
      const response = await fetch(`/api/clinical/index.php/patients/${encodeURIComponent(patient)}/${path}`, {credentials:'same-origin',headers:{Accept:'application/json'}});
      const result = await response.json();
      if (!response.ok || result?.ok !== true) throw new Error('unavailable');
      return result.data || {};
    };
    const [antecedents,allergies,problems,medications,tasks,recent] = await Promise.allSettled(['longitudinal/antecedents','longitudinal/allergies','longitudinal/problems','longitudinal/medications','longitudinal/tasks','longitudinal-summary'].map(read));
    if (run !== epoch || selected() !== patient || body.dataset.encounterKey !== key) return;
    paintReviewCue(antecedents, allergies, medications, problems);
    if (allergies.status !== 'fulfilled') text('allergies','Estado no disponible.');
    else {
      const a = allergies.value;
      text('allergies', a.knowledge_state === 'CONFIRMED_NONE' ? 'Sin alergias conocidas · revisado'
        : a.knowledge_state === 'REVIEWED_WITH_ALLERGIES' ? brief((a.items || []).filter(r => r.state === 'CURRENT'), r => [r.substance,r.reaction,r.validation_state === 'CLINICIAN_REVIEWED' ? '' : 'Reportada; sin validación clínica'].filter(Boolean).join(' · '),'Revisión sin dato visible.')
        : a.knowledge_state === 'NEEDS_REVIEW' ? 'Revisión pendiente.' : 'Alergias sin revisar.');
    }
    text('problems', problems.status !== 'fulfilled' ? 'Estado no disponible.' : brief((problems.value.items || []).filter(r => r.status === 'ACTIVE'), r => r.label,'Sin problemas activos registrados.'));
    text('medications', medications.status !== 'fulfilled' ? 'Estado no disponible.' : brief((medications.value.items || []).filter(r => r.state === 'ACTIVE_CONFIRMED'), r => [r.medication_name,r.dose,r.dose_unit,r.frequency].filter(Boolean).join(' '),'Sin medicación actual confirmada.'));
    text('tasks', tasks.status !== 'fulfilled' ? 'Estado no disponible.' : brief((tasks.value.items || []).filter(r => r.state === 'OPEN'), r => `${r.title}${r.due_at ? ` · Límite ${r.due_at} UTC` : ''}`,'Sin tareas abiertas registradas.'));
    const last = recent.status === 'fulfilled' ? (recent.value.recent_encounters || []).find(r => r.status === 'closed') : null;
    text('recent', recent.status !== 'fulfilled' ? 'Estado no disponible.' : last ? `Finalizada · ${last.date}` : 'Sin consulta finalizada en el resumen disponible.');
  }
  new MutationObserver(refreshContext).observe(body, {attributes:true,attributeFilter:['class','data-encounter-key']});
  document.addEventListener('shown.bs.tab', () => {
    if (root.getClientRects().length) { identity = ''; refreshContext(); }
  });
  refreshContext();
})();
