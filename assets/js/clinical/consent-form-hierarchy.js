/* CONS-FORM01: presentation only. Existing inputs, IDs, listeners and consent state remain authoritative. */
(function () {
  const modal = document.getElementById('modalConsentimientoInformado');
  const guided = document.getElementById('ci_step_2');
  const complete = document.getElementById('ci_full_view');
  if (!modal || !guided || !complete) return;

  const field = id => document.getElementById(id);
  const card = id => field(id)?.closest('.col-12, .col-md-4, .col-md-6');
  const row = (parent) => {
    const inner = document.createElement('div');
    inner.className = 'row g-3';
    parent.append(inner);
    return inner;
  };
  const move = (target, ids) => ids.forEach(id => {
    const source = card(id);
    if (source && source.parentElement !== target) target.append(source);
  });
  const riskCard = prefix => field(`${prefix}risk_common`)?.closest('.border.rounded')?.parentElement;
  const sections = new Map();
  const contextCard = card('ci_template');

  function section(root, name, label) {
    const item = document.createElement('section');
    item.className = 'col-12 ci-form-section';
    item.dataset.consentSection = name;
    const heading = document.createElement('h4');
    heading.className = 'ci-form-section-heading';
    heading.id = `ci_${root.id}_${name}_heading`;
    heading.textContent = label;
    item.setAttribute('aria-labelledby', heading.id);
    item.append(heading);
    const body = row(item);
    root.querySelector(':scope > .row').append(item);
    return body;
  }
  function additional(root, prefix) {
    const outer = document.createElement('section');
    outer.className = 'col-12 ci-form-section ci-form-section--additional';
    const details = document.createElement('details');
    details.className = 'ci-form-additional';
    details.id = `${root.id}_additional`;
    const summary = document.createElement('summary');
    summary.innerHTML = '<span class="ci-form-section-heading" role="heading" aria-level="4">INFORMACIÓN ADICIONAL</span><span class="ci-form-additional-hint">Campos opcionales</span>';
    details.append(summary);
    const body = row(details);
    body.id = `${details.id}_fields`;
    summary.setAttribute('aria-controls', body.id);
    summary.setAttribute('aria-expanded', 'false');
    move(body, prefix === 'ci_'
      ? ['ci_motivo', 'ci_beneficios_esperados', 'ci_consecuencias_no_aceptar']
      : ['ci_full_motivo', 'ci_full_beneficios', 'ci_full_consecuencias']);
    if (prefix === 'ci_') move(body, ['ci_riesgos_manual_wrap']);
    else move(body, ['ci_full_riesgos']);
    outer.append(details);
    root.querySelector(':scope > .row').append(outer);
    details.addEventListener('toggle', () => {
      summary.setAttribute('aria-expanded', String(details.open));
      if (details.open) details.dataset.engaged = '1';
      else delete details.dataset.engaged;
    });
    return details;
  }
  function build(root, prefix) {
    const procedure = section(root, 'procedure', 'PROCEDIMIENTO');
    move(procedure, [`${prefix}title`, `${prefix}procedimiento`]);
    const purpose = section(root, 'purpose', 'FINALIDAD');
    move(purpose, [`${prefix}objetivo`]);
    const risks = section(root, 'risks', 'RIESGOS Y COMPLICACIONES');
    const existingRiskCard = riskCard(prefix);
    if (existingRiskCard) {
      risks.append(existingRiskCard);
      existingRiskCard.querySelector('label.form-label:not([for])')?.classList.add('ci-form-legacy-risk-heading');
    }
    const alternatives = section(root, 'alternatives', 'ALTERNATIVAS');
    move(alternatives, [`${prefix}alternativas`]);
    const extra = additional(root, prefix);
    const authorizations = section(root, 'authorizations', 'AUTORIZACIONES');
    move(authorizations, [`${prefix}aut_contingencias`]);
    sections.set(root.id, {procedure, extra, prefix});
  }
  build(guided, 'ci_');
  build(complete, 'ci_full_');

  // The existing bound controls remain in the DOM for state synchronization.
  // The review-phase controls are the single visible signer authority.
  const legacyStorage = document.createElement('div');
  legacyStorage.id = 'ci_legacy_signer_controls';
  legacyStorage.hidden = true;
  legacyStorage.inert = true;
  modal.append(legacyStorage);
  for (const prefix of ['ci_', 'ci_full_']) {
    move(legacyStorage, [
      `${prefix}firmante_tipo`, `${prefix}firmante_nombre`, `${prefix}firmante_parentesco`,
      `${prefix}confirm_informed`, `${prefix}enable_witnesses`,
      prefix === 'ci_' ? 'ci_signature_slot_step2' : 'ci_signature_slot_full',
      prefix === 'ci_' ? 'ci_identity_slot_step2' : 'ci_identity_slot_full',
      prefix === 'ci_' ? 'ci_witnesses_wrap_1' : 'ci_full_witnesses_wrap_1',
      prefix === 'ci_' ? 'ci_witnesses_wrap_2' : 'ci_full_witnesses_wrap_2',
      prefix === 'ci_' ? 'ci_doctor_signature_slot_step2' : 'ci_doctor_signature_slot_full'
    ]);
  }

  const signerType = field('ci_review_firmante_tipo');
  const signerName = field('ci_review_firmante_nombre');
  const signerRelation = field('ci_review_firmante_parentesco');
  const patientChoice = field('ci_signer_choice_patient');
  const otherChoice = field('ci_signer_choice_other');
  const representativeFields = field('ci_review_representative_fields');
  const identityToggle = field('ci_identity_toggle');
  const identitySlot = field('ci_identity_slot_review');
  const witnessesToggle = field('ci_review_enable_witnesses');
  const witnessesGroup = field('ci_review_witnesses_group');
  const signatureHeading = field('ci_signature_block')?.querySelector('.ci-signature-heading');
  const signerChangeNotice = field('ci_signer_change_notice');
  let lastRepresentativeType = 'tutor';
  let identityExpanded = null;

  function setVisible(element, visible) {
    if (!element) return;
    element.classList.toggle('d-none', !visible);
    element.inert = !visible;
    element.setAttribute('aria-hidden', String(!visible));
  }

  function warnIfSigned() {
    if (field('ci_signature_status')?.textContent?.trim() !== 'Sin firma') {
      setVisible(signerChangeNotice, true);
    }
  }

  function syncSignerFlow() {
    if (!signerType) return;
    const isPatient = signerType.value === 'paciente';
    if (!isPatient && signerType.value) lastRepresentativeType = signerType.value;
    if (patientChoice) patientChoice.checked = isPatient;
    if (otherChoice) {
      otherChoice.checked = !isPatient;
      otherChoice.setAttribute('aria-expanded', String(!isPatient));
    }
    setVisible(representativeFields, !isPatient);
    const selfOption = signerRelation?.querySelector('option[value="self"]');
    if (selfOption) {
      selfOption.hidden = !isPatient;
      selfOption.disabled = !isPatient;
    }
    const patientOption = signerType.querySelector('option[value="paciente"]');
    if (patientOption) patientOption.hidden = !isPatient;
    if (signatureHeading) signatureHeading.textContent = isPatient ? 'Firma del paciente' : 'Firma del representante';
    const patientName = field('ci_pac_nombre')?.value?.trim() || 'Paciente';
    const identity = field('ci_signer_patient_identity');
    if (identity) identity.textContent = `Paciente: ${patientName}`;

    const hasIdentity = field('ci_identity_files_list')?.textContent?.trim() !== 'Sin anexos cargados.';
    const showIdentity = identityExpanded === null ? hasIdentity : identityExpanded;
    setVisible(identitySlot, showIdentity);
    if (identityToggle) {
      identityToggle.setAttribute('aria-expanded', String(showIdentity));
      identityToggle.textContent = showIdentity ? 'Ocultar identificación' : '+ Adjuntar identificación';
    }
    const showWitnesses = !!witnessesToggle?.checked;
    setVisible(witnessesGroup, showWitnesses);
    witnessesToggle?.setAttribute('aria-expanded', String(showWitnesses));
  }

  patientChoice?.addEventListener('change', () => {
    if (!patientChoice.checked || signerType?.value === 'paciente') return;
    warnIfSigned();
    signerType.value = 'paciente';
    signerType.dispatchEvent(new Event('change', { bubbles: true }));
  });
  otherChoice?.addEventListener('change', () => {
    if (!otherChoice.checked || !signerType || signerType.value !== 'paciente') return;
    warnIfSigned();
    signerType.value = lastRepresentativeType;
    signerType.dispatchEvent(new Event('change', { bubbles: true }));
    signerType.focus();
  });
  [signerType, signerName, signerRelation].forEach(control => {
    control?.addEventListener(control?.tagName === 'INPUT' ? 'input' : 'change', warnIfSigned);
  });
  identityToggle?.addEventListener('click', () => {
    identityExpanded = identityToggle.getAttribute('aria-expanded') !== 'true';
    syncSignerFlow();
    if (identityExpanded) field('ci_identity_files')?.focus();
  });
  witnessesToggle?.addEventListener('change', syncSignerFlow);

  function sync() {
    const full = !complete.classList.contains('d-none');
    const target = sections.get(full ? complete.id : guided.id).procedure;
    const description = card(full ? 'ci_full_procedimiento' : 'ci_procedimiento');
    if (contextCard && contextCard.parentElement !== target) target.insertBefore(contextCard, description || null);
    for (const {extra, prefix} of sections.values()) {
      const secondary = prefix === 'ci_'
        ? ['ci_motivo', 'ci_beneficios_esperados', 'ci_consecuencias_no_aceptar', 'ci_riesgos_manual']
        : ['ci_full_motivo', 'ci_full_beneficios', 'ci_full_consecuencias', 'ci_full_riesgos'];
      extra.open = secondary.some(id => String(field(id)?.value || '').trim() !== '') || extra.dataset.engaged === '1';
    }
    syncSignerFlow();
  }
  window.mxmedConsentFormHierarchySync = sync;
  for (const buttonId of ['ci_mode_guided', 'ci_mode_full']) {
    field(buttonId)?.addEventListener('click', () => window.requestAnimationFrame(sync));
  }
  modal.addEventListener('shown.bs.modal', () => window.requestAnimationFrame(sync));
  modal.addEventListener('hidden.bs.modal', () => {
    identityExpanded = null;
    setVisible(signerChangeNotice, false);
    for (const {extra} of sections.values()) {
      delete extra.dataset.engaged;
      extra.open = false;
    }
  });
  modal.addEventListener('input', event => {
    for (const {extra} of sections.values()) {
      if (extra.contains(event.target)) {
        extra.dataset.engaged = '1';
        extra.open = true;
      }
    }
  }, true);
  sync();
})();
