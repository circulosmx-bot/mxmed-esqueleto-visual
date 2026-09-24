// VIS07: administrative presentation only. Existing patient form owns all writes.
(function () {
  const pane = document.getElementById('p-expediente');
  const host = document.getElementById('t-datos');
  const form = host?.querySelector('[data-exp-datos-form]');
  if (!pane || !host || !form) return;
  const patient = () => String(pane.dataset.patientId || pane.dataset.activePatientId || '').trim();
  const el = (tag, text, cls = '') => { const n = document.createElement(tag); n.textContent = text; n.className = cls; return n; };
  const button = (text, fn) => { const n = el('button', text, 'btn btn-outline-primary btn-sm'); n.type = 'button'; n.addEventListener('click', fn); return n; };
  const root = el('section', '', 'vis07-admin'); root.hidden = true; root.setAttribute('aria-label', 'Administrativo');
  const title = el('h3', 'Administrativo');
  root.append(title, el('p', 'Datos de contacto y accesos de gestión del paciente.', 'vis07-intro'));
  const grid = el('div', '', 'vis07-grid');
  const contacts = el('section', '', 'vis07-card vis07-contact');
  const head = el('header', '', 'vis07-card-head'); head.append(el('h4', 'Datos administrativos'));
  const content = el('div'); content.setAttribute('aria-live', 'polite');
  const edit = button('Editar datos', () => { host.classList.add('vis07-editing'); back.hidden = false; form.querySelector('input:not([disabled])')?.focus(); });
  head.append(edit); contacts.append(head, content);
  const back = button('Volver al resumen administrativo', () => { host.classList.remove('vis07-editing'); back.hidden = true; edit.focus(); load(); }); back.hidden = true;
  const hint = el('p', 'Guarda los cambios con «Guardar paciente». Volver al resumen conserva el borrador.', 'vis07-edit-hint');
  const editorNav = el('div', '', 'vis07-editor-nav'); editorNav.append(back, hint);
  function moduleCard(label, copy, action, target) {
    const card = el('section', '', 'vis07-card');
    const link = button(action, async () => {
      const patientId = patient();
      if (typeof window.mxmedM7MayLeaveCurrentPatientContext === 'function' &&
          !window.mxmedM7MayLeaveCurrentPatientContext({ patientId, reason:target === 'p-ag-admin' ? 'admin_to_agenda' : 'admin_to_module' })) return;
      if (target === 'p-ag-admin') {
        if (typeof window.mxmedAgendaHandoffCurrentPatient !== 'function' || !(await window.mxmedAgendaHandoffCurrentPatient(patientId))) return;
        if (typeof window.openGroup === 'function') window.openGroup('agenda');
      }
      if (typeof window.jumpTo === 'function') window.jumpTo(target);
      else document.querySelector(`[data-panel="${target}"]`)?.click();
      if (target === 'p-facturacion' && !document.getElementById(target)?.classList.contains('d-none')) document.querySelector('[data-bs-target="#cfdi-pacientes"]')?.click();
    });
    card.append(el('h4', label), el('p', copy), link); return card;
  }
  grid.append(contacts,
    moduleCard('Agenda', 'Consulta y gestiona las citas en Agenda. Una cita no equivale a una consulta realizada ni a una tarea de seguimiento clínico.', 'Ir a Agenda', 'p-ag-admin'),
    moduleCard('Facturación / Perfil fiscal', 'Los perfiles fiscales y comprobantes se gestionan en Facturación. Selecciona allí al paciente correspondiente.', 'Ir a Facturación', 'p-facturacion'));
  root.append(grid, el('p', 'La información administrativa aporta contexto. Los estados financieros no determinan el estado de la atención clínica.', 'vis07-boundary'));
  host.prepend(root); form.before(editorNav);
  let current = '', epoch = 0;
  function enabled() { return !!patient() && !pane.matches('.mx-expediente-new-patient,.mx-expediente-new-draft,.mx-expediente-empty-patient'); }
  function sync() {
    const active = enabled(); root.hidden = !active; if (host.classList.contains('vis07-ready') !== active) host.classList.toggle('vis07-ready', active);
    editorNav.hidden = !active || !host.classList.contains('vis07-editing');
  }
  async function get(url) {
    const response = await fetch(url, {credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}});
    const result = await response.json();
    if (!response.ok || result.ok !== true) throw new Error('No se pudieron consultar los datos administrativos.');
    return result.data;
  }
  function pair(list, label, value) { list.append(el('dt', label), el('dd', String(value || '').trim() || 'Sin dato registrado')); }
  async function load() {
    const id = patient(), seen = ++epoch;
    if (id !== current) { host.classList.remove('vis07-editing'); back.hidden = true; }
    current = id; sync(); content.replaceChildren();
    if (!enabled()) return;
    content.append(el('p', 'Consultando datos…'));
    try {
      const doctor = String(window.mxmedStore?.activeProfessionalContext?.doctor_id || window.mxmedStore?.doctor_id || '').trim();
      if (!doctor) throw new Error('No se pudo confirmar el contexto del profesional.');
      const [record, privateContacts] = await Promise.all([
        get(`/api/patients/index.php/patients/${encodeURIComponent(id)}`),
        get(`/api/patients/index.php/doctors/${encodeURIComponent(doctor)}/patients/${encodeURIComponent(id)}/contacts/editable`)
      ]);
      if (seen !== epoch || id !== patient()) return;
      const list = el('dl', '', 'vis07-data');
      const rows = Array.isArray(privateContacts.contacts) ? privateContacts.contacts : [];
      const names = {mobile:'Celular',home:'Teléfono de casa',contact:'Teléfono de contacto',primary:'Contacto principal',alternate:'Contacto alterno'};
      if (!rows.length) pair(list, 'Contacto', 'Sin datos de contacto registrados');
      rows.forEach(row => pair(list, row.type === 'email' ? (row.contact_role === 'alternate' ? 'Correo alterno' : 'Correo electrónico') : (names[row.contact_role] || 'Teléfono'), row.value));
      const address = record.addresses?.find(a => a.is_primary) || record.addresses?.[0];
      if (address) pair(list, 'Domicilio', [address.street,address.exterior_number,address.colony,address.municipality,address.state,address.postal_code].filter(Boolean).join(', '));
      pair(list, 'Estado civil', record.profile?.marital_status);
      pair(list, 'Ocupación', record.profile?.occupation);
      content.replaceChildren(list);
    } catch (error) {
      if (seen !== epoch || id !== patient()) return;
      content.replaceChildren(el('p', error.message), button('Reintentar', load));
    }
  }
  // Preserve original controls, listeners, validation and explicit save.
  form.querySelectorAll('input,select').forEach(control => {
    if (!control.hasAttribute('aria-label') && !control.labels?.length) {
      const label = control.parentElement.querySelector('label');
      if (label) control.setAttribute('aria-label', label.textContent.trim());
    }
  });
  new MutationObserver(sync).observe(host, {attributes:true, attributeFilter:['class']});
  new MutationObserver(() => { sync(); if (patient() !== current) load(); }).observe(pane, {attributes:true, attributeFilter:['class','data-patient-id','data-active-patient-id']});
  ['patient:selected','expediente:patient_changed','expediente:patient-changed'].forEach(name => window.addEventListener(name, load));
  pane.querySelector('[data-bs-target="#t-datos"]')?.addEventListener('shown.bs.tab', load);
  if (patient()) load();
})();
