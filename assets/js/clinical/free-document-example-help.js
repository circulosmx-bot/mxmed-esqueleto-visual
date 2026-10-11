/* One editable, fictional starting document for Documento libre. No persistence here. */
(function () {
  const example = Object.freeze({
    title: 'Resumen clínico complementario',
    purpose: 'Documento elaborado para dejar constancia complementaria de la valoración clínica realizada y de las recomendaciones indicadas al paciente.',
    sections: [
      {title: 'Motivo del documento', body: 'Se elabora el presente documento como constancia complementaria de la valoración médica realizada, con el propósito de resumir la información clínica relevante y las indicaciones proporcionadas durante la atención.'},
      {title: 'Información clínica relevante', body: 'Durante la valoración, el paciente se encontró consciente, orientado y en condiciones generales estables. Se revisaron los antecedentes y datos clínicos disponibles relacionados con el motivo de atención, sin identificarse durante esta evaluación datos que requirieran una intervención inmediata.'},
      {title: 'Evolución y situación actual', body: 'Al momento de la valoración se reporta evolución clínica estable. Se explicaron al paciente los hallazgos identificados, las medidas generales recomendadas y la importancia de vigilar cualquier cambio en su estado de salud.'},
      {title: 'Recomendaciones', body: 'Se recomienda continuar con las indicaciones médicas previamente establecidas, mantener vigilancia de la evolución y solicitar nueva valoración si los síntomas persisten, se intensifican o aparecen manifestaciones distintas a las observadas hasta este momento.'}
    ],
    closing: 'El presente documento se emite con base en la información disponible y en la valoración realizada, y deberá interpretarse dentro del contexto clínico correspondiente.'
  });
  const make = (tag, className, text) => {
    const node = document.createElement(tag);
    node.className = className;
    if (text) node.textContent = text;
    return node;
  };
  const meaningful = value => String(value || '').trim() !== '';

  function mount({modal, getCurrent, applyField, applyFull}) {
    const host = modal.querySelector('.modal-content');
    if (!host) return null;
    const overlay = make('div', 'mxeh-overlay fdex-overlay');
    overlay.hidden = true;
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'fdex-heading');
    overlay.setAttribute('aria-describedby', 'fdex-warning');
    const dialog = make('div', 'mxeh-dialog fdex-dialog');
    const header = make('div', 'mxeh-header');
    const heading = make('h5', '', 'EJEMPLO DE DOCUMENTO LIBRE');
    heading.id = 'fdex-heading';
    const closeButton = make('button', 'mxeh-close', 'Cerrar');
    closeButton.type = 'button';
    closeButton.setAttribute('aria-label', 'Cerrar ejemplo');
    header.append(heading, closeButton);
    const intro = make('div', 'mxeh-intro');
    intro.append(make('p', '', 'Ejemplo completo para adaptar al caso real. Puedes usar un campo o el documento entero como base.'));
    const warning = make('p', 'mxeh-notice', 'Este contenido es un ejemplo ficticio. Revísalo y adáptalo a la información real del paciente antes de emitir el documento.');
    warning.id = 'fdex-warning';
    intro.append(warning);
    const selectedLabel = make('strong', 'mxeh-focus-label');
    intro.append(selectedLabel);

    const confirmation = make('div', 'mxeh-confirm fdex-confirm');
    confirmation.hidden = true;
    confirmation.setAttribute('role', 'alertdialog');
    confirmation.setAttribute('aria-labelledby', 'fdex-confirm-heading');
    confirmation.setAttribute('aria-describedby', 'fdex-confirm-body');
    const confirmHeading = make('strong', '', 'Usar este documento como base');
    confirmHeading.id = 'fdex-confirm-heading';
    const confirmBody = make('p', '', 'Se sustituirá el contenido actual del Documento libre por un ejemplo editable. Revísalo y adáptalo a la información real del paciente antes de emitirlo.');
    confirmBody.id = 'fdex-confirm-body';
    const cancel = make('button', 'mxeh-cancel', 'Cancelar');
    const replace = make('button', 'mxeh-replace', 'Usar como base');
    cancel.type = replace.type = 'button';
    confirmation.append(confirmHeading, confirmBody, cancel, replace);

    const list = make('div', 'mxeh-sections fdex-sections');
    function card(label, text, key, index) {
      const item = make('section', 'mxeh-section fdex-card');
      item.dataset.fdexKey = key;
      if (index !== undefined) item.dataset.fdexIndex = String(index);
      item.append(make('h6', '', label), make('p', '', text));
      const action = make('button', 'mxeh-use fdex-field-use', 'Usar este ejemplo');
      action.type = 'button';
      action.hidden = true;
      item.append(action);
      list.append(item);
      return item;
    }
    card('Título del documento', example.title, 'title');
    card('Propósito', example.purpose, 'purpose');
    example.sections.forEach((section, index) => {
      const group = make('div', 'fdex-section-group');
      group.dataset.fdexSection = String(index);
      group.append(make('h6', 'fdex-section-heading', `SECCIÓN ${index + 1}`));
      list.append(group);
      const title = card('Título de sección', section.title, 'section_title', index);
      const body = card('Contenido de sección', section.body, 'section_body', index);
      group.append(title, body);
    });
    card('Nota de cierre', example.closing, 'closing');
    const footer = make('div', 'fdex-footer');
    const fullAction = make('button', 'mxeh-use fdex-full-use', 'USAR ESTE DOCUMENTO COMO BASE');
    fullAction.type = 'button';
    footer.append(fullAction);
    dialog.append(header, intro, confirmation, list, footer);
    overlay.append(dialog);
    host.append(overlay);

    let origin = null;
    let destination = null;
    let activeKey = '';
    let pending = null;
    let returnAction = null;
    function resetConfirmation() {
      confirmation.hidden = true;
      list.inert = false;
      fullAction.disabled = false;
      pending = null;
    }
    function close(restoreFocus = true) {
      if (overlay.hidden) return;
      overlay.hidden = true;
      resetConfirmation();
      if (restoreFocus && origin?.isConnected) origin.focus({preventScroll:true});
      origin = destination = null;
    }
    function doField(text) {
      if (!destination?.isConnected) return;
      const field = destination;
      applyField(field, text);
      close(false);
      field.focus({preventScroll:true});
      field.scrollIntoView({block:'center'});
    }
    function doFull() {
      applyFull(example);
      close(false);
    }
    function confirm(kind, action, button) {
      pending = action;
      returnAction = button;
      confirmHeading.textContent = kind === 'full' ? 'Usar este documento como base' : 'Reemplazar contenido del campo';
      confirmBody.textContent = kind === 'full'
        ? 'Se sustituirá el contenido actual del Documento libre por un ejemplo editable. Revísalo y adáptalo a la información real del paciente antes de emitirlo.'
        : 'Este campo ya contiene información. ¿Deseas reemplazarla con el texto de ejemplo?';
      replace.textContent = kind === 'full' ? 'Usar como base' : 'Reemplazar';
      confirmation.hidden = false;
      list.inert = true;
      fullAction.disabled = true;
      replace.focus();
    }
    function open(key, trigger, fieldId, sectionIndex = 0) {
      const field = modal.querySelector(`#${CSS.escape(fieldId)}`);
      if (!field) return;
      origin = trigger;
      destination = field;
      activeKey = key;
      resetConfirmation();
      selectedLabel.textContent = `Campo consultado: ${key === 'section_title' ? 'Título de sección' : key === 'section_body' ? 'Contenido de sección' : key === 'closing' ? 'Nota de cierre' : key === 'purpose' ? 'Propósito' : 'Título del documento'}`;
      const chosen = Math.min(example.sections.length - 1, Math.max(0, Number(sectionIndex) || 0));
      for (const item of list.querySelectorAll('.fdex-card')) {
        const match = item.dataset.fdexKey === key;
        const sameSection = Number(item.dataset.fdexIndex) === chosen;
        const highlighted = match && (!['section_title','section_body'].includes(key) || sameSection);
        item.classList.toggle('is-current', highlighted);
        item.classList.toggle('is-context', !highlighted);
        item.querySelector('.fdex-field-use').hidden = !match;
      }
      overlay.hidden = false;
      list.scrollTop = 0;
      closeButton.focus({preventScroll:true});
      const target = list.querySelector(`.fdex-card.is-current`);
      if (target) list.scrollTop = Math.max(0, target.offsetTop - list.offsetTop - 12);
    }
    for (const [fieldId, key] of Object.entries({fd_title_input:'title',fd_purpose:'purpose',fd_closing:'closing'})) {
      const label = modal.querySelector(`label[for="${fieldId}"]`);
      if (!label) continue;
      const wrap = make('div', 'mxeh-field-heading');
      const trigger = make('button', 'mxeh-trigger', 'ⓘ Ver ejemplo');
      trigger.type = 'button';
      trigger.dataset.exampleFor = fieldId;
      trigger.setAttribute('aria-label', `Ver ejemplo de ${label.textContent.trim()}`);
      trigger.addEventListener('click', () => open(key, trigger, fieldId));
      label.before(wrap);
      wrap.append(label, trigger);
    }
    list.addEventListener('click', event => {
      const button = event.target.closest('.fdex-field-use');
      if (!button) return;
      const item = button.closest('.fdex-card');
      const key = item?.dataset.fdexKey;
      if (key !== activeKey) return;
      const index = Number(item.dataset.fdexIndex || 0);
      const text = key === 'section_title' ? example.sections[index].title
        : key === 'section_body' ? example.sections[index].body : example[key];
      const action = () => doField(text);
      if (meaningful(destination?.value)) confirm('field', action, button);
      else action();
    });
    fullAction.addEventListener('click', () => {
      const current = getCurrent();
      const occupied = [current.title,current.purpose,current.closing,...current.sections.flatMap(section => [section.title,section.body])].some(meaningful);
      if (occupied) confirm('full', doFull, fullAction);
      else doFull();
    });
    cancel.addEventListener('click', () => {
      resetConfirmation();
      returnAction?.focus();
    });
    replace.addEventListener('click', () => { const action = pending; resetConfirmation(); action?.(); });
    closeButton.addEventListener('click', () => close());
    overlay.addEventListener('click', event => { if (event.target === overlay) close(); });
    overlay.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(); return; }
      if (event.key !== 'Tab') return;
      const controls = [closeButton, ...list.querySelectorAll('.fdex-field-use:not([hidden])'), fullAction,
        ...(!confirmation.hidden ? [cancel,replace] : [])];
      const index = controls.indexOf(document.activeElement);
      if (event.shiftKey && index <= 0) { event.preventDefault(); controls.at(-1).focus(); }
      else if (!event.shiftKey && index === controls.length - 1) { event.preventDefault(); controls[0].focus(); }
    });
    modal.addEventListener('hide.bs.modal', () => close(false));
    return {open,close,example};
  }
  window.mxmedFreeDocumentExampleHelp = {mount,example};
})();
