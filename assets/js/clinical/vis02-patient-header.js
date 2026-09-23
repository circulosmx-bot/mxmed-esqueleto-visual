// VIS02: a read-only projection of existing encounter and longitudinal authorities.
(function () {
  let host, patient = '', epoch = 0, encounterState = 'unavailable';
  const selected = () => String(document.getElementById('p-expediente')?.dataset.patientId || document.getElementById('p-expediente')?.dataset.activePatientId || '').trim();
  const definitions = [
    ['consulta', 'Consulta', 'description', 'INICIAR CONSULTA', '#t-consulta-actual'],
    ['allergies', 'Alergias', 'warning', 'Ver todas', '#t-antecedentes-longitudinal'],
    ['medications', 'Medicación actual', 'medication', 'Ver medicación', '#t-medicamentos-longitudinal'],
    ['problems', 'Problemas activos', 'clinical_notes', 'Ver todos', '#t-problemas-longitudinal']
  ];
  function message(key, text) { host.querySelector(`[data-vis02-value="${key}"]`).textContent = text; }
  function summary(key, rows, label, detail, empty) {
    const box = host.querySelector(`[data-vis02-value="${key}"]`);
    box.replaceChildren();
    if (!rows.length) { box.textContent = empty; return; }
    rows.slice(0, 2).forEach(row => {
      const line = document.createElement('p');
      const name = document.createElement('strong'); name.textContent = label(row); line.append(name);
      const extra = detail(row);
      if (extra) { const sub = document.createElement('span'); sub.textContent = extra; line.append(sub); }
      box.append(line);
    });
    if (rows.length > 2) { const more = document.createElement('span'); more.textContent = `+ ${rows.length - 2} más`; box.append(more); }
  }
  async function refresh() {
    if (!host || !patient || selected() !== patient) return;
    const id = patient, run = ++epoch;
    encounterState = 'unavailable';
    const cta = host.querySelector('[data-vis02-action="consulta"]'); cta.disabled = true;
    definitions.forEach(([key]) => message(key, 'Consultando…'));
    const read = async suffix => {
      const response = await fetch(`/api/clinical/index.php/patients/${encodeURIComponent(id)}/${suffix}`, {credentials:'same-origin',headers:{Accept:'application/json'}});
      const result = await response.json();
      if (!response.ok || result?.ok !== true) throw new Error('unavailable');
      return result;
    };
    const results = await Promise.allSettled(['encounters/active','longitudinal/allergies','longitudinal/medications','longitudinal/problems'].map(read));
    if (run !== epoch || selected() !== id || patient !== id) return;
    const [enc, allergies, medications, problems] = results;
    if (enc.status === 'fulfilled') {
      const snapshot = enc.value;
      encounterState = snapshot.meta?.integrity_v1 !== true ? 'legacy' : snapshot.data?.encounter_key ? 'open' : 'none';
      message('consulta', encounterState === 'open' ? 'En curso' : encounterState === 'none' ? 'Sin consulta activa' : 'Consultar estado de atención');
      cta.textContent = 'INICIAR CONSULTA'; cta.disabled = false;
    } else { message('consulta', 'Estado no disponible'); cta.textContent = 'INICIAR CONSULTA'; cta.disabled = false; }
    if (allergies.status !== 'fulfilled') message('allergies', 'Estado no disponible');
    else {
      const data = allergies.value.data || {};
      if (data.knowledge_state === 'CONFIRMED_NONE') message('allergies', 'Sin alergias conocidas · revisado');
      else if (data.knowledge_state === 'REVIEWED_WITH_ALLERGIES') summary('allergies', (data.items || []).filter(row => row.state === 'CURRENT'), row => row.substance, row => [row.reaction, row.validation_state === 'CLINICIAN_REVIEWED' ? '' : 'Reportada; sin validación clínica'].filter(Boolean).join(' · '), 'Revisión sin dato visible');
      else message('allergies', data.knowledge_state === 'NEEDS_REVIEW' ? 'Revisión pendiente' : 'Alergias sin revisar');
    }
    if (medications.status !== 'fulfilled') message('medications', 'Estado no disponible');
    else summary('medications', (medications.value.data?.items || []).filter(row => row.state === 'ACTIVE_CONFIRMED'), row => row.medication_name, row => [row.dose,row.dose_unit,row.frequency].filter(Boolean).join(' '), 'Sin medicación actual confirmada');
    if (problems.status !== 'fulfilled') message('problems', 'Estado no disponible');
    else summary('problems', (problems.value.data?.items || []).filter(row => row.status === 'ACTIVE'), row => row.label, () => '', 'Sin problemas activos registrados');
  }
  window.mxmedUpdateCanonicalPatientHeader = (container, data, active) => {
    if (container.id !== 'exp_clinical_context') return;
    host = container;
    container.closest('.mx-clinical-subheader').classList.toggle('vis02-active', active);
    if (!active) { patient = ''; epoch++; return; }
    if (!host.querySelector('.vis02-context')) {
      const metadata = document.createElement('span'); metadata.className = 'vis02-patient-id';
      host.querySelector('.ne-rx-ch-patient-line').append(metadata);
      const cards = document.createElement('div'); cards.className = 'vis02-context'; cards.setAttribute('aria-label', 'Contexto clínico inmediato');
      cards.innerHTML = definitions.map(([key,title,icon,label,target]) => `<section class="vis02-card"><h3><span class="material-symbols-outlined" aria-hidden="true">${icon}</span>${title}</h3><div data-vis02-value="${key}" aria-live="polite">Consultando…</div><button type="button" class="btn ${key === 'consulta' ? 'btn-primary' : 'btn-link'}" data-vis02-action="${key}" data-vis02-target="${target}">${label}</button></section>`).join('');
      host.append(cards);
      cards.addEventListener('click', async event => {
        const button = event.target.closest('[data-vis02-action]');
        if (!button || selected() !== patient) return;
        if (button.dataset.vis02Action === 'consulta') {
          button.disabled = true;
          try { await window.mxmedM7OpenFromHeader?.(patient, encounterState === 'none' ? 'start' : 'resume'); }
          finally { if (selected() === patient) refresh(); }
        } else {
          const tab = document.querySelector(`#p-expediente [data-bs-target="${button.dataset.vis02Target}"]`);
          if (tab) window.bootstrap?.Tab.getOrCreateInstance(tab).show();
        }
      });
    }
    host.querySelector('.vis02-patient-id').textContent = `ID: ${data.patient_id || selected()}`;
    const reason = host.querySelector('.ne-rx-ch-reason');
    reason.classList.toggle('vis02-no-reason', !String(data.clinical_reason || '').trim());
    if (patient !== selected()) { patient = selected(); refresh(); }
  };
  ['m7:encounter-started','m7:encounter-state-changed','mxmed:encounter-lifecycle','mxmed:encounter-changed'].forEach(name => window.addEventListener(name, refresh));
  document.addEventListener('shown.bs.tab', event => { if (event.target.closest('#p-expediente [data-exp-tabs]')) refresh(); });
  document.addEventListener('close', event => { if (event.target.matches('#p-expediente dialog')) refresh(); }, true);
})();
