/* Patient Recetas landing, bounded history and authorized global patient selector. */
(() => {
  'use strict';
  const root = document.querySelector('[data-rx-patient-root]');
  const global = document.querySelector('[data-quick-prescription-panel]');
  if (!root) return;
  const one = (selector, within = root) => within?.querySelector(selector);
  const node = (tag, text = '', className = '') => {
    const value = document.createElement(tag);
    value.textContent = text;
    if (className) value.className = className;
    return value;
  };
  const doctorId = () => String(window.resolveDoctorId?.() || '').trim();
  const rawPatientId = () => String(window.resolveActivePatientId?.() || '').trim();
  const canonicalPatientId = async () => {
    const raw = rawPatientId();
    if (!raw) return '';
    const resolved = String(await window.mxmedResolveCanonicalPrescriptionPatientId?.(raw) || '').trim();
    return resolved || raw;
  };
  const request = async (url, method = 'GET', body = null) => {
    const response = await fetch(url, {method, credentials:'same-origin', headers:{'Accept':'application/json',...(body ? {'Content-Type':'application/json'} : {})},
      ...(body ? {body:JSON.stringify(body)} : {})});
    const data = await response.json().catch(() => null);
    if (!response.ok || data?.ok !== true) throw new Error(data?.message || data?.error || `HTTP ${response.status}`);
    return data.data;
  };
  let view = 'landing', filter = 'all', page = 1, currentPatient = '', currentRawPatient = '', loading = 0;
  let selectedPrescription = '', restoreScrollY = null;
  const show = (next) => {
    view = next;
    root.querySelectorAll('[data-rx-view]').forEach(panel => panel.classList.toggle('d-none', panel.dataset.rxView !== next));
    if (next === 'history') void loadHistory();
  };
  const date = value => String(value || '').slice(0,10) || 'Fecha no disponible';
  const api = (patientId) => `/api/clinical/index.php/doctors/${encodeURIComponent(doctorId())}/patients/${encodeURIComponent(patientId)}/prescriptions`;
  const recetasReturnTarget = (uuid) => {
    const target = new URL(window.location.href);
    target.hash = '';
    target.searchParams.set('rx_return', 'history');
    target.searchParams.set('rx_patient', rawPatientId());
    target.searchParams.set('rx_filter', filter);
    target.searchParams.set('rx_page', String(page));
    target.searchParams.set('rx_scroll', String(Math.max(0, Math.round(window.scrollY || 0))));
    target.searchParams.set('rx_selected', String(uuid || ''));
    return `${target.pathname}${target.search}${target.hash}`;
  };
  const viewer = (uuid, print = false) => {
    const params = new URLSearchParams({uuid, doctor_id:doctorId(), from:'recetas'});
    if (print) {
      params.set('print','1');
      window.open(`/modules/clinical/ui/document.php?${params}`, '_blank', 'noopener');
      return;
    }
    selectedPrescription = String(uuid || '');
    params.set('return_to', recetasReturnTarget(selectedPrescription));
    window.location.assign(`/modules/clinical/ui/document.php?${params}`);
  };
  const actionButton = (label, action, uuid, style = 'btn-outline-primary') => {
    const button = node('button',label,`btn btn-sm ${style}`);
    button.type = 'button'; button.dataset.rxRowAction = action; button.dataset.uuid = uuid;
    return button;
  };
  const renderDrafts = (drafts) => {
    const host = one('[data-rx-drafts]'); host.replaceChildren();
    if (!drafts.length) return;
    const block = node('section','','rx-ux-drafts');
    block.append(node('strong',`Borradores (${drafts.length})`));
    drafts.forEach(draft => {
      const row = node('div','','rx-ux-row-actions');
      row.append(node('span', draft.is_reissue
        ? `BORRADOR DE CORRECCIÓN · Corrige la receta emitida el ${date(draft.source_event_datetime)}.`
        : `Borrador · ${date(draft.updated_at)}`));
      row.append(actionButton('Continuar borrador','draft',draft.document_uuid,'btn-primary'));
      block.append(row);
    });
    host.append(block);
  };
  const renderRows = (data) => {
    const host = one('[data-rx-history-list]'); host.replaceChildren();
    const items = Array.isArray(data.items) ? data.items : [];
    if (!items.length) {
      const empty = node('div','','rx-ux-row');
      empty.append(node('strong','Sin recetas registradas.'));
      empty.append(actionButton('Emitir nueva receta','emit','','btn-primary'));
      host.append(empty);
    }
    items.forEach(item => {
      const row = node('article','','rx-ux-row');
      const head = node('div','','rx-ux-row-head');
      head.append(node('strong',item.medication_summary || 'Medicamentos no detallados'));
      const replacementState = item.replacement_state || 'none';
      const label = item.status === 'voided'
        ? (replacementState === 'completed' ? 'ANULADA / REEMPLAZADA' : 'ANULADA') : 'EMITIDA';
      head.append(node('span',label,`rx-ux-status ${item.status === 'voided' ? 'is-voided' : ''}`));
      row.dataset.rxPrescriptionUuid = item.document_uuid;
      row.append(head);
      row.append(node('small',`${date(item.event_datetime)}${item.physician_name ? ` · ${item.physician_name}` : ''}${item.is_legacy ? ' · Receta histórica; firma moderna no verificada' : ''}`));
      if (replacementState === 'pending' && item.replaced_by_prescription_uuid) {
        const relation = node('div','','small');
        relation.append(node('span','CORRECCIÓN EN PREPARACIÓN · Existe un borrador de corrección para esta receta. '));
        if (item.can_manage) relation.append(actionButton('Continuar corrección','draft',item.replaced_by_prescription_uuid));
        row.append(relation);
      }
      if (replacementState === 'completed' && item.replaced_by_prescription_uuid) {
        const relation = node('div','','small');
        relation.append(node('span',`Reemplazada por una receta emitida el ${date(item.replacement_event_datetime)}. `));
        relation.append(actionButton('Ver reemplazo','view',item.replaced_by_prescription_uuid));
        row.append(relation);
      }
      if (item.corrects_prescription_uuid) {
        const relation = node('div','','small');
        relation.append(node('span',`Corrige la receta emitida el ${date(item.source_event_datetime)}. `));
        relation.append(actionButton('Ver original','view',item.corrects_prescription_uuid));
        row.append(relation);
      }
      const actions = node('div','','rx-ux-row-actions');
      actions.append(actionButton('Ver receta','view',item.document_uuid));
      actions.append(actionButton('Imprimir','print',item.document_uuid));
      if (item.can_manage && ['generated','signed'].includes(item.status) && item.lifecycle_state !== 'pending') {
        actions.append(actionButton('Anular receta','void',item.document_uuid,'btn-outline-danger'));
        if (item.reissue_eligible) actions.append(actionButton('Reemitir corregida','reissue',item.document_uuid));
      }
      row.append(actions); host.append(row);
    });
    one('[data-rx-page-label]').textContent = `Página ${data.page} · ${data.total} receta(s)`;
    one('[data-rx-page="prev"]').disabled = page <= 1;
    one('[data-rx-page="next"]').disabled = !data.has_next;
  };
  async function loadHistory() {
    const epoch = ++loading;
    const status = one('[data-rx-history-status]'); status.textContent = 'Cargando recetas…';
    try {
      const patient = await canonicalPatientId();
      if (!patient || !doctorId()) throw new Error('Selecciona un paciente y confirma el profesional.');
      currentPatient = patient;
      currentRawPatient = rawPatientId();
      const params = new URLSearchParams({filter,page:String(page),limit:'20'});
      const [history,drafts] = await Promise.all([
        request(`${api(patient)}/history?${params}`),request(`${api(patient)}/drafts`)
      ]);
      if (epoch !== loading || patient !== await canonicalPatientId()) return;
      renderRows(history); renderDrafts(Array.isArray(drafts.drafts) ? drafts.drafts : []);
      status.textContent = '';
      if (selectedPrescription) {
        const selected = Array.from(root.querySelectorAll('[data-rx-prescription-uuid]'))
          .find(row => row.dataset.rxPrescriptionUuid === selectedPrescription);
        selected?.querySelector('[data-rx-row-action="view"]')?.focus({preventScroll:true});
      }
      if (restoreScrollY !== null) {
        const top = restoreScrollY; restoreScrollY = null;
        requestAnimationFrame(() => requestAnimationFrame(() => {
          window.scrollTo({top, behavior:'auto'});
          window.setTimeout(() => window.scrollTo({top, behavior:'auto'}), 100);
        }));
      }
    } catch (error) { if (epoch === loading) status.textContent = `No se pudieron cargar las recetas: ${error.message}`; }
  }
  const openEditor = (draftUuid = '') => {
    const patient = rawPatientId();
    if (!patient) return;
    window.mxmedOpenCanonicalPrescription?.({standalone:true,draftUuid});
  };
  let pendingAction = '', pendingUuid = '', dialogTrigger = null;
  const dialog = one('[data-rx-lifecycle-dialog]');
  const closeDialog = () => { dialog?.close(); dialogTrigger?.focus(); };
  dialog?.addEventListener('close', () => dialogTrigger?.focus());
  one('[data-rx-dialog-cancel]')?.addEventListener('click',closeDialog);
  one('[data-rx-lifecycle-form]')?.addEventListener('submit', async event => {
    event.preventDefault();
    const reason = one('[data-rx-reason]').value;
    const detail = one('[data-rx-reason-detail]').value.trim();
    const note = one('[data-rx-note]').value.trim();
    const error = one('[data-rx-dialog-error]');
    if (!reason || (reason === 'otra' && !detail)) { error.textContent='Selecciona un motivo y completa «Otra» cuando corresponda.'; return; }
    const submit = one('[data-rx-dialog-submit]'); submit.disabled=true;
    try {
      const result = await request(`${api(currentPatient)}/${encodeURIComponent(pendingUuid)}/${pendingAction}`,'POST',
        {reason_code:reason,reason_detail:detail,explanatory_note:note});
      closeDialog(); page=1;
      await loadHistory();
      if (pendingAction === 'reissue' && result?.document?.document_id)
        openEditor(result.document.document_id);
    } catch (cause) { error.textContent=cause.message; }
    finally { submit.disabled=false; }
  });
  root.addEventListener('click',event => {
    const choice = event.target.closest('[data-rx-action]');
    if (choice) {
      const action=choice.dataset.rxAction;
      if (action==='emit') openEditor();
      if (action==='history') { page=1;filter='all';show('history'); }
      if (action==='back') show('landing');
      return;
    }
    const tab=event.target.closest('[data-rx-filter]');
    if (tab) {
      filter=tab.dataset.rxFilter;page=1;
      root.querySelectorAll('[data-rx-filter]').forEach(button => {
        const active=button===tab;button.setAttribute('aria-pressed',String(active));
        button.classList.toggle('btn-primary',active);button.classList.toggle('btn-outline-primary',!active);
      });
      void loadHistory();return;
    }
    const pager=event.target.closest('[data-rx-page]');
    if (pager) { page+=pager.dataset.rxPage==='next'?1:-1;page=Math.max(1,page);void loadHistory();return; }
    const button=event.target.closest('[data-rx-row-action]');
    if (!button) return;
    const action=button.dataset.rxRowAction, uuid=button.dataset.uuid;
    if (action==='view'||action==='print') { viewer(uuid,action==='print');return; }
    if (action==='emit') { openEditor();return; }
    if (action==='draft') { openEditor(uuid);return; }
    if (!['void','reissue'].includes(action)) return;
    pendingAction=action;pendingUuid=uuid;dialogTrigger=button;
    one('[data-rx-lifecycle-form]').reset(); one('[data-rx-dialog-error]').textContent='';
    one('[data-rx-dialog-title]').textContent=action==='void'?'Anular receta':'Reemitir corregida';
    one('[data-rx-dialog-description]').textContent=action==='void'
      ? 'La receta emitida quedará anulada de forma permanente. Su contenido y firma histórica permanecerán disponibles.'
      : 'Se creará un borrador con un UUID nuevo. La receta original seguirá vigente hasta que emitas la corregida.';
    one('[data-rx-dialog-submit]').textContent=action==='void'?'Anular receta':'Crear borrador corregido';
    dialog.showModal();one('[data-rx-reason]').focus();
  });
  document.querySelector('[data-bs-target="#t-tratamiento"]')?.addEventListener('shown.bs.tab', () => {
    if (currentRawPatient !== rawPatientId()) { currentPatient='';currentRawPatient='';show('landing'); }
    else if (view==='history') void loadHistory();
  });
  ['patient:selected','expediente:patient_changed','expediente:patient-changed','mxmed:expediente-neutralize'].forEach(name => {
    window.addEventListener(name, () => { loading++;currentPatient='';currentRawPatient='';if (dialog?.open) closeDialog();show('landing'); });
  });
  document.getElementById('modalReceta')?.addEventListener('hidden.bs.modal', () => {
    if (view==='history') void loadHistory();
  });
  window.addEventListener('mxmed:clinical-document-created',event => {
    if (event.detail?.document_type==='prescription' && view==='history') void loadHistory();
  });

  const restoreRecetasNavigation = () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('rx_return') !== 'history') return;
    const expectedPatient = String(params.get('rx_patient') || '').trim();
    const restoredFilter = String(params.get('rx_filter') || 'all');
    const restoredPage = Number.parseInt(params.get('rx_page') || '1', 10);
    const restoredScroll = Number.parseInt(params.get('rx_scroll') || '0', 10);
    if (!['all','issued','voided'].includes(restoredFilter) || !Number.isInteger(restoredPage) || restoredPage < 1) return;
    let attempts = 0;
    const apply = () => {
      attempts += 1;
      const patient = rawPatientId();
      if (!patient || (expectedPatient && patient !== expectedPatient)) {
        if (attempts < 120) window.setTimeout(apply, 100);
        return;
      }
      if (typeof window.showPanel === 'function') window.showPanel('p-expediente');
      else document.querySelector('[data-panel="p-expediente"]')?.click();
      document.querySelector('[data-bs-target="#t-tratamiento"]')?.click();
      filter = restoredFilter; page = restoredPage;
      selectedPrescription = String(params.get('rx_selected') || '').trim();
      restoreScrollY = Number.isFinite(restoredScroll) ? Math.max(0, restoredScroll) : 0;
      root.querySelectorAll('[data-rx-filter]').forEach(button => {
        const active = button.dataset.rxFilter === filter;
        button.setAttribute('aria-pressed', String(active));
        button.classList.toggle('btn-primary', active);
        button.classList.toggle('btn-outline-primary', !active);
      });
      show('history');
      for (const key of ['rx_return','rx_patient','rx_filter','rx_page','rx_scroll','rx_selected']) params.delete(key);
      const clean = `${window.location.pathname}${params.toString() ? `?${params}` : ''}${window.location.hash}`;
      window.history.replaceState(window.history.state, '', clean);
    };
    apply();
  };
  // The shell restores its top-level panel during startup. Apply the nested
  // Recetas state afterwards so the shell cannot overwrite the destination.
  window.addEventListener('load', () => window.setTimeout(restoreRecetasNavigation, 2000), {once:true});

  // The global entry uses the same physician-scoped patient search as the patient archive.
  const query = one('[data-rx-global-query]',global), results = one('[data-rx-global-results]',global);
  const message = one('[data-rx-global-message]',global);
  const searchPanel = one('[data-rx-global-search]',global);
  const syncGlobalSearch = () => searchPanel?.classList.toggle('d-none',global?.dataset.hasActivePatient === '1');
  let timer=0, searchSequence=0;
  const search = async () => {
    const q=query.value.trim(), sequence=++searchSequence;results.replaceChildren();
    if (q.length<2) { message.textContent='Escribe al menos dos caracteres para buscar pacientes.';return; }
    if (!doctorId()) { message.textContent='No se pudo identificar al médico.';return; }
    message.textContent='Buscando pacientes autorizados…';
    try {
      const url=new URL(`/api/patients/index.php/doctors/${encodeURIComponent(doctorId())}/patients/search`,location.origin);
      url.searchParams.set('q',q);url.searchParams.set('limit','12');
      const payload=await request(url);
      if (sequence!==searchSequence) return;
      const items=Array.isArray(payload)?payload:(Array.isArray(payload?.items)?payload.items:[]);
      items.forEach(item => {
        const id=String(item.patient_id||item.id||'').trim();if (!id) return;
        const name=String(item.nombre_completo||item.display_name||item.name||'Paciente');
        const birthdate=String(item.birthdate||item.fecha_nacimiento||'').slice(0,10);
        const button=node('button',`${name}${birthdate ? ` · ${birthdate}` : ''}`,'rx-global-result');
        button.type='button';button.setAttribute('role','option');
        button.addEventListener('click',()=>{
          window.mxmedSelectQuickRxPatient?.(id,name);results.replaceChildren();query.value='';
          message.textContent=`Paciente seleccionado: ${name}.`;
          syncGlobalSearch();
          one('[data-quick-rx-action="emit-active"]',global)?.focus();
        });
        results.append(button);
      });
      message.textContent=results.children.length?'Selecciona un paciente.':'No se encontraron pacientes autorizados.';
    } catch(error) { if (sequence===searchSequence) message.textContent=`No se pudo buscar: ${error.message}`; }
  };
  query?.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(search,250);});
  global?.addEventListener('click',event=>{
    const button=event.target.closest('[data-quick-rx-action="search"]');
    if (!button) return;
    event.preventDefault();event.stopImmediatePropagation();
    window.mxmedChangeQuickRxPatient?.();syncGlobalSearch();query.focus();
    message.textContent='Escribe al menos dos caracteres para buscar pacientes.';
  },true);
  document.querySelector('[data-panel="p-pac-recetas"]')?.addEventListener('click',()=>setTimeout(syncGlobalSearch,0));
  syncGlobalSearch();
})();
