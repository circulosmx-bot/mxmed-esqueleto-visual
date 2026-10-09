/* Shared educational examples. Documents opt in to explicit field insertion. */
(function () {
  const create = (tag, className, value = '') => {
    const node = document.createElement(tag);
    node.className = className;
    node.textContent = value;
    return node;
  };

  function mount({modal, fieldMap, sections, contextLabel, contextText, note, id,
    onUseExample, useExampleLabel = 'Usar este ejemplo', canUseExample = () => true}) {
    const content = modal?.querySelector('.modal-content');
    if (!content || !Array.isArray(sections) || !sections.length) return null;
    const byKey = new Map(sections.map(section => [section.key, section]));
    const overlay = create('div', 'mxeh-overlay');
    overlay.hidden = true;
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', `${id}-heading`);
    overlay.setAttribute('aria-describedby', `${id}-note`);
    const dialog = create('div', 'mxeh-dialog');
    const header = create('div', 'mxeh-header');
    const heading = create('h5', '', 'EJEMPLO DE LLENADO');
    heading.id = `${id}-heading`;
    const closeButton = create('button', 'mxeh-close', 'Cerrar');
    closeButton.type = 'button';
    closeButton.setAttribute('aria-label', 'Cerrar ejemplo');
    header.append(heading, closeButton);
    const intro = create('div', 'mxeh-intro');
    const context = create('p', 'mxeh-context');
    context.append(create('strong', '', `${contextLabel}: `), document.createTextNode(contextText));
    const notice = create('p', 'mxeh-notice', note);
    notice.id = `${id}-note`;
    const focusLabel = create('strong', 'mxeh-focus-label');
    const explanation = create('p', 'mxeh-explanation');
    intro.append(context, notice, focusLabel, explanation);
    const list = create('div', 'mxeh-sections');
    const nodes = new Map();
    for (const section of sections) {
      const item = create('section', 'mxeh-section');
      item.dataset.exampleField = section.key;
      const title = create('h6', '', section.label);
      const marker = create('span', 'mxeh-current', 'Campo consultado');
      marker.hidden = true;
      title.append(marker);
      item.append(title, create('p', '', section.text));
      const action = typeof onUseExample === 'function' ? create('button', 'mxeh-use', useExampleLabel) : null;
      if (action) {
        action.type = 'button';
        action.hidden = true;
        item.append(action);
      }
      list.append(item);
      nodes.set(section.key, {item, marker, action});
    }
    const confirmation = create('div', 'mxeh-confirm');
    confirmation.hidden = true;
    confirmation.setAttribute('role', 'alert');
    confirmation.append(create('p', '', 'Este campo ya contiene información. ¿Deseas reemplazarla con el texto de ejemplo?'));
    const cancel = create('button', 'mxeh-cancel', 'Cancelar');
    const replace = create('button', 'mxeh-replace', 'Reemplazar');
    cancel.type = replace.type = 'button';
    confirmation.append(cancel, replace);
    dialog.append(header, intro, confirmation, list);
    overlay.append(dialog);
    content.append(overlay);

    let returnFocus = null;
    let selectedKey = null;
    let destination = null;
    function insertSelected() {
      const section = byKey.get(selectedKey);
      if (!section || !destination?.isConnected) return;
      onUseExample({section, destination});
      close(false);
      destination.focus({preventScroll: true});
      if (typeof destination.setSelectionRange === 'function') {
        const end = destination.value.length;
        destination.setSelectionRange(end, end);
      }
    }
    function close(restoreFocus = true) {
      if (overlay.hidden) return;
      overlay.hidden = true;
      confirmation.hidden = true;
      if (restoreFocus && returnFocus?.isConnected) returnFocus.focus({preventScroll: true});
      returnFocus = null;
    }
    function open(key, trigger, fieldId) {
      const selected = byKey.get(key);
      if (!selected) return;
      returnFocus = trigger;
      selectedKey = key;
      destination = modal.querySelector(`#${fieldId}`);
      confirmation.hidden = true;
      focusLabel.textContent = `Campo consultado: ${selected.label}`;
      explanation.textContent = selected.explanation;
      for (const [sectionKey, item] of nodes) {
        const current = sectionKey === key;
        item.item.classList.toggle('is-current', current);
        item.item.classList.toggle('is-context', !current);
        item.marker.hidden = !current;
        if (item.action) item.action.hidden = !current || !(typeof canUseExample === 'function'
          ? canUseExample({section: selected, destination}) : canUseExample !== false);
      }
      overlay.hidden = false;
      list.scrollTop = 0;
      closeButton.focus({preventScroll: true});
      const selectedNode = nodes.get(key).item;
      list.scrollTop = selectedNode.getBoundingClientRect().top - list.getBoundingClientRect().top - 8;
    }
    closeButton.addEventListener('click', () => close());
    for (const {action} of nodes.values()) {
      action?.addEventListener('click', () => {
        if (destination.value.trim()) {
          confirmation.hidden = false;
          replace.focus();
        } else insertSelected();
      });
    }
    cancel.addEventListener('click', () => { confirmation.hidden = true; nodes.get(selectedKey)?.action?.focus(); });
    replace.addEventListener('click', insertSelected);
    overlay.addEventListener('click', event => { if (event.target === overlay) close(); });
    overlay.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close();
      } else if (event.key === 'Tab') {
        const controls = [closeButton, ...Array.from(nodes.values()).map(node => node.action).filter(action => action && !action.hidden),
          ...(!confirmation.hidden ? [cancel, replace] : [])];
        const current = controls.indexOf(document.activeElement);
        if (event.shiftKey && current <= 0) { event.preventDefault(); controls.at(-1).focus(); }
        else if (!event.shiftKey && current === controls.length - 1) { event.preventDefault(); controls[0].focus(); }
      }
    });
    modal.addEventListener('hide.bs.modal', () => close(false));
    for (const [fieldId, key] of Object.entries(fieldMap)) {
      const section = byKey.get(key);
      const label = modal.querySelector(`label[for="${fieldId}"]`);
      if (!section || !label) continue;
      const wrapper = create('div', 'mxeh-field-heading');
      if (label.classList.contains('small')) wrapper.classList.add('mxeh-field-heading--small');
      const trigger = create('button', 'mxeh-trigger', 'ⓘ Ver ejemplo');
      trigger.type = 'button';
      trigger.dataset.exampleFor = fieldId;
      trigger.setAttribute('aria-label', `Ver ejemplo de ${section.label}`);
      trigger.addEventListener('click', event => {
        event.stopPropagation();
        open(key, trigger, fieldId);
      });
      label.before(wrapper);
      wrapper.append(label, trigger);
    }
    return {open, close};
  }

  window.mxmedExampleFieldHelp = {mount};
})();
