// M7 WS05: terminal-state UI over existing canonical encounter commands.
(function () {
  window.mxmedM7WS05 = function (root, encounterUrl, selectedPatient, hasUnsaved, onTransition) {
    const panel = root.querySelector('[data-m7-terminal]');
    if (!panel) return null;
    const $ = selector => panel.querySelector(selector);
    const state = root.querySelector('[data-m7-terminal-state]');
    const finalize = $('[data-m7-finalize]');
    const voidForm = $('[data-m7-void-form]');
    const amendmentForm = $('[data-m7-amendment-form]');
    const amendmentList = $('[data-m7-amendment-list]');
    const original = $('[data-m7-amendment-original]');
    const target = $('[data-m7-amendment-target]');
    let context = null;
    let busy = false;
    let attempt = '';
    const show = (node, visible) => node?.classList.toggle('d-none', !visible);
    const key = () => globalThis.crypto?.randomUUID?.() || `m7-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const label = type => ({ reason_evolution:'Motivo / Evolución', physical_exam:'Exploración', assessment:'Valoración', plan:'Plan', observation:'Medición' })[type] || type;
    const errorText = code => ({
      M6_WRITE_WINDOW_BLOCKED:'Las escrituras clínicas están pausadas. No se realizó esta acción.',
      ENCOUNTER_CLOSED:'Esta consulta ya fue finalizada. Actualiza el estado.',
      ENCOUNTER_VOIDED:'Esta consulta fue anulada. No se puede editar.',
      ENCOUNTER_TERMINAL:'La consulta cambió de estado en otra sesión. Actualiza antes de continuar.',
      VERSION_CONFLICT:'La consulta cambió en otra sesión. Actualiza antes de continuar.',
      IDEMPOTENCY_KEY_REUSED:'Este intento ya se usó para otra corrección. Revisa el contenido.'
    })[code] || 'No se completó la acción. Comprueba el estado de la consulta.';
    function notice(text, kind = '') { state.textContent = text; state.dataset.state = kind; }
    function sameContext() { return !!context && selectedPatient() === context.patientId; }
    async function request(path, body, idempotencyKey = '') {
      const response = await fetch(`${encounterUrl(context.key)}/${path}`, {
        method:'POST', credentials:'same-origin',
        headers:{ Accept:'application/json', 'Content-Type':'application/json', ...(idempotencyKey ? {'Idempotency-Key':idempotencyKey} : {}) },
        body:JSON.stringify(body)
      });
      const result = await response.json().catch(() => null);
      if (!response.ok || result?.ok !== true) {
        const code = typeof result?.error === 'string' ? result.error : String(result?.error?.code || response.status);
        const error = new Error(errorText(code)); error.code = code; throw error;
      }
      return result;
    }
    function paint(detail) {
      if (!context) return;
      const status = String(detail.status || '').toLowerCase();
      context.status = status;
      context.detail = detail;
      show($('[data-m7-terminal-actions]'), status === 'open');
      show(finalize, status === 'open');
      show(voidForm, status === 'open');
      show(amendmentForm, status === 'closed');
      show($('[data-m7-terminal-void]'), status === 'voided');
      $('[data-m7-terminal-void]').textContent = status === 'voided'
        ? `Anulada${detail.voided_at ? ` · ${detail.voided_at}` : ''}${detail.voided_by_user_id ? ` · Por ${detail.voided_by_user_id}` : ''}. Motivo: ${detail.void_reason || 'No disponible'}` : '';
      notice(status === 'open' ? 'Consulta activa. Finalizar y anular requieren una acción explícita.'
        : status === 'closed' ? `Finalizada${detail.closed_at ? ` · ${detail.closed_at}` : ''}. La edición normal está deshabilitada.`
        : 'Anulada. Consulta histórica de sólo lectura.');
      const sections = detail.sections || {};
      const selectedTarget = target.value;
      target.replaceChildren();
      Object.entries({reason_evolution:'Motivo / Evolución',physical_exam:'Exploración',assessment:'Valoración',plan:'Plan'}).forEach(([value,text]) => target.add(new Option(text,value)));
      for (const row of detail.observations || []) target.add(new Option(`Medición ${row.code} · ${row.effective_at}`, `observation:${row.observation_id}`));
      if ([...target.options].some(option => option.value === selectedTarget)) target.value = selectedTarget;
      else {
        const firstSaved = ['reason_evolution','physical_exam','assessment','plan'].find(type =>
          sections[type] && (String(sections[type].narrative_text || '').trim() || Object.keys(sections[type].payload?.systems || {}).length));
        if (firstSaved) target.value = firstSaved;
        else if ((detail.observations || []).length) target.value = `observation:${detail.observations[0].observation_id}`;
      }
      const choose = () => {
        const value = target.value;
        const row = value.startsWith('observation:') ? (detail.observations || []).find(item => String(item.observation_id) === value.slice(12)) : sections[value];
        original.textContent = row ? (value === 'physical_exam' ? JSON.stringify(row.payload?.systems || {}, null, 2)
          : value.startsWith('observation:') ? JSON.stringify({code:row.code,value_numeric:row.value_numeric,unit:row.unit,systolic_mm_hg:row.systolic_mm_hg,diastolic_mm_hg:row.diastolic_mm_hg},null,2)
          : String(row.narrative_text || 'Sin contenido')) : 'Sin contenido guardado.';
      };
      target.onchange = choose; choose();
      amendmentList.replaceChildren();
      const amendments = Array.isArray(detail.amendments) ? detail.amendments : [];
      if (!amendments.length) amendmentList.textContent = 'No hay enmiendas de esta consulta.';
      for (const row of amendments) {
        const item = document.createElement('article'); item.className = 'm7-amendment-item';
        const heading = document.createElement('strong');
        heading.textContent = `Enmienda de ${row.target_type === 'section' ? label(row.target_field) : label(row.target_type)} · ${row.amended_at || ''}`;
        const meta = document.createElement('p'); meta.textContent = `Motivo: ${row.reason || ''} · Autor: ${row.author_user_id || 'No disponible'}`;
        const content = document.createElement('p'); content.textContent = String(row.correction?.text || row.correction?.narrative_text || JSON.stringify(row.correction || {}));
        item.append(heading,meta,content); amendmentList.append(item);
      }
    }
    async function reload() {
      if (!sameContext()) return;
      const currentKey = context.key;
      const response = await fetch(encounterUrl(currentKey), {credentials:'same-origin',headers:{Accept:'application/json'}});
      const result = await response.json().catch(() => null);
      if (!response.ok || result?.ok !== true) throw new Error('No se pudo actualizar la consulta.');
      if (!sameContext() || context.key !== currentKey || String(result.data?.patient_id) !== context.patientId) return;
      paint(result.data);
      return result.data;
    }
    async function execute(kind, body, idempotencyKey = '') {
      if (busy || !sameContext()) return;
      busy = true; finalize.disabled = true;
      [...voidForm.elements,...amendmentForm.elements].forEach(control => control.disabled = true);
      try {
        // Recheck the authoritative state immediately before every terminal command.
        const fresh = await reload();
        if (!fresh || (kind === 'amendments' ? fresh.status !== 'closed' : fresh.status !== 'open')) throw new Error('La consulta cambió de estado. Se actualizó la vista sin enviar la acción.');
        await request(kind,body,idempotencyKey);
        if (kind === 'amendments') { amendmentForm.reset(); attempt = ''; }
        const detail = await reload();
        if (kind !== 'amendments' && detail) onTransition(detail);
        notice(kind === 'amendments' ? 'Enmienda añadida. El contenido original permanece intacto.' : kind === 'finalize' ? 'Consulta finalizada.' : 'Consulta anulada.', 'saved');
      } catch (error) {
        notice(error.message || 'No se completó la acción.', 'failed');
        if (['ENCOUNTER_CLOSED','ENCOUNTER_VOIDED','ENCOUNTER_TERMINAL','VERSION_CONFLICT'].includes(error.code)) {
          try { const detail = await reload(); if (detail) onTransition(detail); } catch (_) { /* Keep the explicit failure visible. */ }
        }
      } finally {
        busy = false; finalize.disabled = false;
        [...voidForm.elements,...amendmentForm.elements].forEach(control => control.disabled = false);
      }
    }
    finalize.addEventListener('click', () => {
      if (context?.status !== 'open' || busy) return;
      if (hasUnsaved()) { notice('Hay cambios locales sin guardar. Guárdalos o descártalos antes de finalizar.', 'failed'); return; }
      if (!window.confirm('Finalizar cerrará esta consulta y dejará la edición clínica normal en sólo lectura. No elimina la consulta ni depende del pago o la factura. ¿Finalizar ahora?')) return;
      execute('finalize', {});
    });
    voidForm.addEventListener('submit', event => {
      event.preventDefault();
      if (context?.status !== 'open' || busy || !voidForm.reportValidity()) return;
      // The command owns its reason; unrelated clinical drafts still block VOID.
      if (hasUnsaved({excludeVoidReason:true})) { notice('Hay cambios locales sin guardar. Resuélvelos antes de anular.', 'failed'); return; }
      const reason = $('[data-m7-void-reason]').value.trim();
      if (!reason) return;
      if (!window.confirm('Anular conservará esta consulta y su motivo en el historial. No podrá reabrirse. ¿Anular ahora?')) return;
      execute('void', {reason});
    });
    amendmentForm.addEventListener('input', () => { attempt = ''; });
    amendmentForm.addEventListener('submit', event => {
      event.preventDefault();
      if (context?.status !== 'closed' || busy || !amendmentForm.reportValidity()) return;
      const value = target.value;
      const type = value.startsWith('observation:') ? 'observation' : 'section';
      const id = type === 'observation' ? Number(value.slice(12)) : null;
      if (type === 'observation' && !Number.isInteger(id)) return;
      const reason = $('[data-m7-amendment-reason]').value.trim();
      const text = $('[data-m7-amendment-text]').value.trim();
      if (!reason || !text) return;
      if (!attempt) attempt = key();
      execute('amendments',{target:{type,id,field:type === 'observation' ? null : value},reason,correction:{text}},attempt);
    });
    $('[data-m7-terminal-refresh]').addEventListener('click', () => reload().catch(() => notice('No se pudo actualizar la consulta.', 'failed')));
    return {
      load(detail) {
        const keyValue = String(detail.encounter_key || '');
        const patientId = String(detail.patient_id || '');
        if (!keyValue || !patientId) return;
        if (context?.key !== keyValue) { attempt = ''; voidForm.reset(); amendmentForm.reset(); }
        context = {key:keyValue,patientId,status:String(detail.status || '').toLowerCase(),detail};
        paint(detail);
      },
      select(selected) { show(panel, selected); if (selected && context) reload().catch(() => notice('No se pudo actualizar la consulta.', 'failed')); },
      reset() { context = null; attempt = ''; busy = false; show(panel,false); },
      isDirty({excludeVoidReason = false} = {}) { return !!(context && ((!excludeVoidReason && $('[data-m7-void-reason]').value.trim()) || $('[data-m7-amendment-reason]').value.trim() || $('[data-m7-amendment-text]').value.trim())); },
      isBusy() { return busy; }
    };
  };
})();
