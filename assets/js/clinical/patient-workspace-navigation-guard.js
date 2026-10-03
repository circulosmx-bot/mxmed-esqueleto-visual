// VIS24: one decision point for patient-local work that cannot be resumed.
(function () {
  const sources = new Map();
  let pending = false;
  let bypass = false;
  const copy = {
    generic: ['Cambios sin guardar', 'Si sales de esta sección ahora, los cambios realizados se perderán.'],
    order: ['Orden en preparación', 'Aún no has terminado esta orden. Si sales ahora, perderás los cambios que no se hayan guardado.'],
    result: ['Resultado en captura', 'Aún no has terminado este resultado. Si sales ahora, perderás los cambios que no se hayan guardado.'],
    prescription: ['Receta en preparación', 'Aún no has terminado esta receta. Si sales ahora, perderás los cambios que no se hayan guardado.']
  };
  const stateOf = source => {
    try {
      if (source.isSaving?.()) return 'SAVING';
      if (source.isInProgress?.()) return 'IN_PROGRESS';
      if (source.isDirty?.()) return 'DIRTY';
    } catch (_) { return 'DIRTY'; }
    return 'CLEAN';
  };
  const active = (ids, destination = '') => [...sources.values()].filter(source =>
    (!ids || ids.includes(source.id)) && (!destination || source.risksDestination?.(destination) !== false) && stateOf(source) !== 'CLEAN');
  const waitForSaving = async saving => {
    // Save callbacks resolve success/failure in their own module. Never offer discard mid-write.
    await Promise.all(saving.map(source => source.whenSettled?.() || new Promise(resolve => {
      const started = Date.now();
      const poll = () => source.isSaving?.() && Date.now() - started < 30000 ? setTimeout(poll, 50) : resolve();
      poll();
    })));
    return saving.every(source => stateOf(source) === 'CLEAN');
  };
  function confirmLeave(rows, trigger) {
    const kind = rows.length === 1 ? rows[0].getContextCopy?.() || rows[0].copy || 'generic' : 'generic';
    const [heading, message] = copy[kind] || copy.generic;
    return new Promise(resolve => {
      const dialog = document.createElement('dialog');
      dialog.className = 'mx-patient-leave-dialog';
      dialog.setAttribute('aria-labelledby', 'mx-patient-leave-title');
      dialog.setAttribute('aria-describedby', 'mx-patient-leave-copy');
      dialog.innerHTML = '<div class="mx-patient-leave-content"><h2 id="mx-patient-leave-title"></h2><p id="mx-patient-leave-copy"></p><div class="mx-patient-leave-actions"><button type="button" class="btn btn-primary" data-stay>Seguir aquí</button><button type="button" class="btn btn-outline-danger" data-discard>Salir sin guardar</button></div></div>';
      dialog.querySelector('h2').textContent = heading;
      dialog.querySelector('p').textContent = message;
      document.body.append(dialog);
      const finish = leave => {
        dialog.close(); dialog.remove();
        if (!leave && trigger?.isConnected) trigger.focus({preventScroll:true});
        resolve(leave);
      };
      dialog.querySelector('[data-stay]').onclick = () => finish(false);
      dialog.querySelector('[data-discard]').onclick = () => finish(true);
      dialog.oncancel = event => { event.preventDefault(); finish(false); };
      dialog.showModal(); dialog.querySelector('[data-stay]').focus();
    });
  }
  async function request(destination, proceed, trigger = document.activeElement, sourceIds = null) {
    if (bypass) return true;
    if (pending) return false;
    const rows = active(sourceIds, destination);
    if (!rows.length) { proceed?.(); return true; }
    pending = true;
    try {
      const saving = rows.filter(source => stateOf(source) === 'SAVING');
      if (saving.length && !(await waitForSaving(saving))) return false;
      const auto = active(sourceIds, destination).filter(source => source.saveBeforeLeave);
      for (const source of auto) if (!(await source.saveBeforeLeave())) return false;
      const dirty = active(sourceIds, destination);
      if (dirty.some(source => source.canDiscard?.() === false)) { dirty.forEach(source=>source.onBlocked?.()); return false; }
      if (dirty.length && !(await confirmLeave(dirty, trigger))) return false;
      for (const source of dirty) if ((await source.discard?.()) === false) return false;
      bypass = true;
      try { proceed?.(); } finally { bypass = false; }
      return true;
    } finally { pending = false; }
  }
  function register(source) {
    if (!source?.id || sources.has(source.id)) throw new Error('Patient workspace guard source must have a unique ID.');
    sources.set(source.id, source);
    return () => sources.delete(source.id);
  }
  window.mxmedPatientWorkspaceNavigationGuard = {register, request, active, isBypassing:() => bypass};
  register({id:'patient-new-entry',isDirty:()=>window.mxmedHasNewPatientDraftProgress?.()===true,
    isSaving:()=>window.mxmedNewPatientSaveBusy?.()===true,
    discard:()=>window.mxmedDiscardNewPatientEntry?.()});
  window.addEventListener('beforeunload', event => {
    if (!active().length) return;
    event.preventDefault(); event.returnValue = '';
  });
  function intercept(event, target, destination) {
    if (bypass || !active(null, destination).length) return;
    event.preventDefault(); event.stopImmediatePropagation();
    void request(destination, () => target.click(), target);
  }
  document.addEventListener('click', event => {
    const target = event.target.closest('.menu-main[data-panel], .menu-main[data-group], .menu-sub-btn[data-panel], #p-expediente .mm-tabs-row [data-bs-target], #p-expediente [data-vis01-open], #p-expediente [data-vis01-return], [data-clinical-action="active-close"], [data-clinical-action="active-search"]');
    if (!target || target.disabled || target.classList.contains('active')) return;
    if (target.classList.contains('menu-main') && target.dataset.group && !target.dataset.panel && target.classList.contains('active')) return;
    intercept(event, target, target.dataset.panel || target.dataset.bsTarget || target.dataset.vis01Open || target.dataset.clinicalAction || 'patient-workspace');
  }, true);
  // Programmatic Bootstrap transitions (including keyboard arrows) do not pass through click.
  document.addEventListener('show.bs.tab', event => {
    const target = event.target;
    if (!target.matches?.('#p-expediente .mm-tabs-row [data-bs-target]') || bypass || !active(null,target.dataset.bsTarget).length) return;
    event.preventDefault();
    void request(target.dataset.bsTarget, () => window.bootstrap?.Tab.getOrCreateInstance(target).show(), target);
  }, true);
  // Cover commands that navigate without a click (search, CTA, and keyboard flows).
  function installNavigationCommands() {
    for (const name of ['showPanel','jumpTo']) {
      const original = window[name];
      if (typeof original !== 'function' || original.__mxmedPatientGuard) continue;
      const wrapped = function (destination) {
        const target = String(destination || '');
        if (bypass || !active(null,target).length || !target) return original.apply(this, arguments);
        const args = arguments, receiver = this;
        void request(target, () => original.apply(receiver, args));
        return false;
      };
      wrapped.__mxmedPatientGuard = true;
      window[name] = wrapped;
    }
  }
  window.addEventListener('load', installNavigationCommands);
  window.setTimeout(installNavigationCommands, 2500);
})();
