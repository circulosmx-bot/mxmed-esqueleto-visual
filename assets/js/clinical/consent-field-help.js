/* CONS-HELP01: educational examples only; this module never reads or writes consent state. */
(function () {
  const modal = document.getElementById('modalConsentimientoInformado');
  const content = modal?.querySelector('.modal-content');
  if (!content) return;

  const example = {
    procedure: 'Extirpación de una lesión cutánea superficial en el antebrazo con anestesia local (caso ficticio)',
    sections: [
      {
        key: 'description', label: 'Descripción del procedimiento',
        explanation: 'Explica en qué consiste el procedimiento y cómo se realizará.',
        text: 'Se limpiará la piel del antebrazo, se aplicará anestesia local y se retirará una lesión cutánea superficial. La herida se cerrará según su tamaño y se indicarán cuidados posteriores. El tejido podrá enviarse a análisis si la valoración clínica lo requiere.'
      },
      {
        key: 'objective', label: 'Objetivo',
        explanation: 'Describe qué se pretende lograr con el procedimiento.',
        text: 'Retirar la lesión y, si corresponde, obtener tejido para aclarar su naturaleza mediante análisis.'
      },
      {
        key: 'common', label: 'Riesgos comunes',
        explanation: 'Describe eventos adversos relativamente frecuentes asociados al procedimiento.',
        text: 'Puede haber molestia transitoria, sensibilidad local, un pequeño hematoma y una cicatriz en el sitio tratado.'
      },
      {
        key: 'uncommon', label: 'Riesgos poco frecuentes',
        explanation: 'Describe eventos menos habituales pero clínicamente relevantes.',
        text: 'Puede presentarse infección local, sangrado posterior o apertura de la herida que requiera atención adicional.'
      },
      {
        key: 'complications', label: 'Complicaciones posibles',
        explanation: 'Describe eventos de mayor gravedad que requieren especial consideración.',
        text: 'De forma excepcional puede ocurrir una reacción grave al anestésico local o un problema de cicatrización que requiera tratamiento adicional.'
      },
      {
        key: 'benefits', label: 'Beneficios esperados',
        explanation: 'Describe los resultados favorables que razonablemente podrían obtenerse.',
        text: 'Se espera retirar la lesión. El análisis del tejido, cuando esté indicado, puede aportar información para decidir el seguimiento.'
      },
      {
        key: 'alternatives', label: 'Alternativas',
        explanation: 'Describe otras opciones que pueden considerarse en lugar del procedimiento propuesto.',
        text: 'Según la valoración individual, puede considerarse observación con seguimiento o solicitar una opinión dermatológica antes de decidir la extirpación.'
      },
      {
        key: 'refusal', label: 'Consecuencias de no aceptar',
        explanation: 'Describe qué podría ocurrir si el paciente decide no realizar el procedimiento.',
        text: 'La lesión permanecerá y podría cambiar con el tiempo. Si se había recomendado analizarla, posponer la toma de tejido puede retrasar la aclaración del diagnóstico.'
      }
    ]
  };
  const fields = {
    ci_procedimiento: 'description', ci_full_procedimiento: 'description',
    ci_objetivo: 'objective', ci_full_objetivo: 'objective',
    ci_risk_common: 'common', ci_full_risk_common: 'common',
    ci_risk_infrequent: 'uncommon', ci_full_risk_infrequent: 'uncommon',
    ci_risk_rare_serious: 'complications', ci_full_risk_rare_serious: 'complications',
    ci_beneficios_esperados: 'benefits', ci_full_beneficios: 'benefits',
    ci_alternativas: 'alternatives', ci_full_alternativas: 'alternatives',
    ci_consecuencias_no_aceptar: 'refusal', ci_full_consecuencias: 'refusal'
  };

  const element = (tag, className, text) => {
    const node = document.createElement(tag);
    node.className = className;
    if (text) node.textContent = text;
    return node;
  };
  const overlay = element('div', 'ci-example-overlay');
  overlay.hidden = true;
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-labelledby', 'ci_example_heading');
  overlay.setAttribute('aria-describedby', 'ci_example_notice');
  const dialog = element('div', 'ci-example-dialog');
  const header = element('div', 'ci-example-header');
  const heading = element('h5', '', 'EJEMPLO DE LLENADO');
  heading.id = 'ci_example_heading';
  const closeButton = element('button', 'ci-example-close', 'Cerrar');
  closeButton.type = 'button';
  closeButton.setAttribute('aria-label', 'Cerrar ejemplo');
  header.append(heading, closeButton);
  const intro = element('div', 'ci-example-intro');
  const procedure = element('p', 'ci-example-procedure');
  procedure.append(element('strong', '', 'Procedimiento de referencia: '), document.createTextNode(example.procedure));
  const notice = element('p', 'ci-example-notice', 'Este ejemplo es únicamente una referencia de redacción. Debe adaptarse a las características del procedimiento y del paciente.');
  notice.id = 'ci_example_notice';
  const focusLabel = element('strong', 'ci-example-focus-label');
  const explanation = element('p', 'ci-example-explanation');
  intro.append(procedure, notice, focusLabel, explanation);
  const sections = element('div', 'ci-example-sections');
  const sectionNodes = new Map();
  for (const section of example.sections) {
    const item = element('section', 'ci-example-section');
    item.dataset.exampleField = section.key;
    const title = element('h6', '', section.label);
    const marker = element('span', 'ci-example-current', 'Campo consultado');
    marker.hidden = true;
    title.append(marker);
    item.append(title, element('p', '', section.text));
    sections.append(item);
    sectionNodes.set(section.key, {item, marker});
  }
  dialog.append(header, intro, sections);
  overlay.append(dialog);
  content.append(overlay);

  let returnFocus = null;
  function close(restoreFocus = true) {
    if (overlay.hidden) return;
    overlay.hidden = true;
    if (restoreFocus && returnFocus?.isConnected) returnFocus.focus({preventScroll: true});
    returnFocus = null;
  }
  function open(key, trigger) {
    const chosen = example.sections.find(section => section.key === key);
    if (!chosen) return;
    returnFocus = trigger;
    focusLabel.textContent = `Campo consultado: ${chosen.label}`;
    explanation.textContent = chosen.explanation;
    for (const [sectionKey, nodes] of sectionNodes) {
      const current = sectionKey === key;
      nodes.item.classList.toggle('is-current', current);
      nodes.marker.hidden = !current;
    }
    overlay.hidden = false;
    sections.scrollTop = 0;
    closeButton.focus({preventScroll: true});
    const current = sectionNodes.get(key).item;
    sections.scrollTop = current.getBoundingClientRect().top - sections.getBoundingClientRect().top - 8;
  }
  closeButton.addEventListener('click', () => close());
  overlay.addEventListener('click', event => { if (event.target === overlay) close(); });
  overlay.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      close();
    } else if (event.key === 'Tab') {
      event.preventDefault();
      closeButton.focus();
    }
  });
  modal.addEventListener('hide.bs.modal', () => close(false));

  for (const [id, key] of Object.entries(fields)) {
    const label = document.querySelector(`#ci_wizard label[for="${id}"]`);
    if (!label) continue;
    const title = example.sections.find(section => section.key === key).label;
    const wrapper = element('div', `ci-help-field-heading${label.classList.contains('small') ? ' ci-help-field-heading--small' : ''}`);
    const trigger = element('button', 'ci-example-trigger', 'ⓘ Ver ejemplo');
    trigger.type = 'button';
    trigger.dataset.consentHelpField = key;
    trigger.setAttribute('aria-label', `Ver ejemplo de ${title}`);
    trigger.addEventListener('click', () => open(key, trigger));
    label.before(wrapper);
    wrapper.append(label, trigger);
  }
})();
