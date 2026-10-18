/* UI-3, fictional state in memory only. No fetch/XHR, persistence, account lookup or backend calls. */
(() => {
  'use strict';
  const catalog = Object.values(window.ASE_VIS01_CATALOG);
  const operational = catalog.filter(c => c.kind === 'operational' && c.delegable);
  const reserved = catalog.filter(c => c.kind !== 'operational' || !c.delegable);
  const future = new Set(['profile_create','profile_curate','profile_publication_manage','profile_support_assist']);
  const restricted = new Set(['public_profile_correction_review','public_profile_correction_apply']);
  const groups = [
    {title:'Acreditación profesional', domains:['physician_accreditation','professional_sources'], note:'Flujo existente. Su habilitación requiere verificación y autorización específicas.'},
    {title:'Revisión de medios', domains:['media_review'], note:'Cada función se concede por separado; consultar no concede aprobar ni descargar.'},
    {title:'Correcciones de perfiles', domains:['public_profile_corrections','public_profile_correction_application'], note:'Flujo existente para Dirección y Master. Su uso por asesores aún no está habilitado.'},
    {title:'Administración de perfiles', domains:['profile_administration'], note:'Permisos definidos, con funciones pendientes de conectar. Vista de sólo lectura.'},
    {title:'Organizaciones y proveedores', domains:['provider_claim','provider_commercial'], note:'Funciones para organizaciones. La pantalla de trabajo aún está pendiente.'}
  ];
  // Presentation copy only; keys, labels, domains and delegability remain the exported catalog's.
  const descriptions = {
    media_review_read:'Consultar las imágenes recibidas para revisión.',
    media_review_approve:'Aprobar explícitamente una imagen para su publicación.',
    media_review_source_download:'Descargar el archivo original privado con registro de la acción.',
    media_review_corrected_upload:'Cargar una corrección y preparar una nueva vista para revisión.',
    media_review_improve:'Preparar propuestas privadas de mejora de un logotipo.',
    media_review_request_replacement:'Pedir al usuario una nueva imagen e indicar el motivo.',
    profile_create:'Crear un perfil bajo un caso autorizado, sin asignar su titularidad.',
    profile_curate:'Corregir información profesional en un perfil y caso específicos.',
    profile_publication_manage:'Gestionar la visibilidad pública sin cambiar titularidad ni plan.',
    profile_support_assist:'Asistir de forma identificada, sin suplantar al médico.',
    professional_source_provision:'Revisar y verificar evidencia profesional de un expediente específico.',
    physician_accreditation_review:'Revisar identidad, credenciales y vínculo con el perfil médico.',
    public_profile_correction_review:'Revisar informes sobre un perfil sin aplicar cambios.',
    public_profile_correction_apply:'Aplicar una corrección previamente autorizada en el perfil exacto.',
    provider_claim_review:'Revisar la solicitud de vinculación de una organización proveedora.',
    provider_subscription_activate:'Gestionar una suscripción organizacional. Esta acción no acredita un pago.'
  };
  const fixture = (id,name,status,grants) => ({id,name,status,grants:new Set(grants),version:1,events:[]});
  const advisors = [
    fixture('aurora','Aurora Naranjo','ACTIVE',['media_review_read']),
    fixture('bruno','Bruno Lago','ACTIVE',['media_review_read','physician_accreditation_review','professional_source_provision']),
    fixture('cora','Cora Vela','ACTIVE',operational.map(c => c.key)),
    fixture('diego','Diego Roble','ACTIVE',[]),
    fixture('elisa','Elisa Monte','SUSPENDED',['media_review_read','media_review_approve']),
    fixture('mar','Mar Solano','ACTIVE',['media_review_read','media_review_source_download'])
  ];
  const delegated = new Set(['media_review_read','media_review_approve','physician_accreditation_review']);
  const $ = id => document.getElementById(id);
  let selected = advisors[0];
  let draft = new Set(selected.grants);
  let mode = 'director';
  let directoryState = 'ready';
  let query = '';
  let stale = false;
  let pendingAction = null;
  let dialogReturnFocus = null;
  const dialog = $('confirmation');
  const escape = value => String(value).replace(/[&<>"']/g,c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const labelFor = key => window.ASE_VIS01_CATALOG[key].label;
  const changes = () => ({grants:[...draft].filter(k => !selected.grants.has(k)),revokes:[...selected.grants].filter(k => !draft.has(k))});
  const dirty = () => selected && (changes().grants.length + changes().revokes.length > 0);
  const self = () => mode === 'self' && selected?.id === 'mar';
  const canManage = () => mode !== 'missing' && !self();
  const editable = key => canManage() && selected?.status === 'ACTIVE' && !stale && !future.has(key) && !restricted.has(key) && (mode === 'director' || delegated.has(key));
  const status = advisor => `<span class="status ${advisor.status === 'SUSPENDED' ? 'suspended' : ''}">${advisor.status === 'ACTIVE' ? 'Activo' : 'Suspendido'}</span>`;
  const say = message => { $('feedback').textContent = message; };
  function openDialog(title, description, body, acceptLabel, action) {
    dialogReturnFocus = document.activeElement;
    $('confirmation-title').textContent = title;
    $('confirmation-description').textContent = description;
    $('confirmation-body').innerHTML = body;
    $('accept-confirmation').textContent = acceptLabel;
    $('accept-confirmation').hidden = !action;
    $('cancel-confirmation').textContent = action ? 'Cancelar' : 'Entendido';
    pendingAction = action;
    dialog.showModal();
  }
  function transition(action) {
    if (!dirty()) { action(); return; }
    openDialog('¿Descartar los cambios pendientes?', 'Aún no has guardado esta propuesta de permisos. Puedes seguir editando o descartarla para continuar.', '', 'Descartar y continuar', action);
  }
  function choose(advisor) {
    transition(() => { selected = advisor; draft = new Set(advisor.grants); stale = false; say(''); render(); $('detail-title').focus({preventScroll:innerWidth > 640}); });
  }
  function renderDirectory() {
    $('directory-count').textContent = directoryState === 'ready' ? `${advisors.length} ficticios` : '';
    const content = $('directory-content');
    content.setAttribute('aria-busy', String(directoryState === 'loading'));
    if (directoryState === 'loading') { content.innerHTML = '<div class="empty-state" role="status"><strong>Cargando asesores…</strong>Ejemplo visual de consulta en curso.</div>'; return; }
    if (directoryState === 'error') { content.innerHTML = '<div class="empty-state" role="alert"><strong>No pudimos consultar el directorio.</strong>Intenta de nuevo para recuperar los registros ficticios.<br><button type="button" id="retry-directory">Reintentar</button></div>'; $('retry-directory').onclick = () => { directoryState='ready'; $('directory-state').value='ready'; selected=advisors[0]; draft=new Set(selected.grants); render(); }; return; }
    if (directoryState === 'empty') { content.innerHTML = '<div class="empty-state"><strong>Aún no hay asesores.</strong>La incorporación vinculará una cuenta existente con el equipo interno.</div>'; return; }
    const normalized = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
    const list = advisors.filter(a => normalized(a.name).includes(normalized(query)));
    if (!list.length) { content.innerHTML = '<div class="empty-state"><strong>Sin resultados.</strong>Prueba con otro nombre.</div>'; return; }
    content.innerHTML = `<ul class="advisor-list">${list.map(a => `<li><button type="button" class="advisor-card" data-advisor="${a.id}" aria-pressed="${selected?.id === a.id}"><span class="avatar" aria-hidden="true">${a.name.split(' ').map(n=>n[0]).join('')}</span><span class="advisor-card-copy"><span class="advisor-name">${a.name}</span><span class="advisor-meta">${status(a)}<span>${a.grants.size} ${a.grants.size === 1 ? 'permiso' : 'permisos'}</span></span></span>${selected?.id === a.id ? '<span class="selection-mark" aria-hidden="true">✓</span>' : ''}</button></li>`).join('')}</ul>`;
    content.querySelectorAll('[data-advisor]').forEach(button => button.onclick = () => choose(advisors.find(a=>a.id === button.dataset.advisor)));
  }
  function authorityCopy() {
    if (self()) return 'No puedes administrar tus propios permisos ni tu estado. Selecciona a otra persona para revisar el alcance delegado.';
    if (mode === 'missing') return 'Este Master no tiene autorización para administrar asesores. Los controles permanecen en consulta.';
    if (mode !== 'director') return 'Vista Master: puede gestionar a otros asesores y sólo los permisos con delegación exacta. El resto permanece en consulta.';
    return 'Vista Director: administra permisos operativos individuales. Las autoridades de gobierno permanecen separadas.';
  }
  function reasonFor(key) {
    if (future.has(key)) return 'Sin flujo conectado · sólo lectura';
    if (restricted.has(key)) return 'Uso restringido a Dirección / Master';
    if (!canManage()) return self() ? 'Autoadministración no permitida' : 'Sin autorización de gestión';
    if (selected.status === 'SUSPENDED') return 'Asesor suspendido · permisos conservados';
    if (mode !== 'director' && !delegated.has(key)) return 'Sin delegación para administrar este permiso';
    if (key.startsWith('provider_')) return 'Servicio existente · pantalla pendiente';
    return '';
  }
  function renderDetail() {
    const detail = $('advisor-detail');
    if (directoryState !== 'ready' || !selected) { detail.innerHTML = '<div class="empty-state detail-empty"><h2 id="detail-title">Selecciona un asesor</h2><p>Sus permisos y su historial aparecerán aquí cuando el directorio esté disponible.</p></div>'; return; }
    detail.innerHTML = `<header class="detail-header"><div class="detail-heading-row"><div><p class="eyebrow">Asesor seleccionado · persona ficticia</p><h2 id="detail-title" tabindex="-1">${selected.name}</h2>${status(selected)}<p class="detail-subtitle"><span id="selected-count">${draft.size}</span> <span id="count-label">${draft.size === 1 ? 'permiso asignado' : 'permisos asignados'}</span> · simulación</p></div><button id="lifecycle" class="lifecycle" type="button" ${!canManage() || dirty() || stale ? 'disabled' : ''}>${selected.status === 'ACTIVE' ? 'SUSPENDER ASESOR' : 'REACTIVAR ASESOR'}</button></div><p class="authority-note">${authorityCopy()}${selected.status === 'SUSPENDED' ? ' La suspensión conserva los permisos y el historial; las acciones operativas quedan bloqueadas.' : ''}</p></header>
    <div id="conflict-banner" class="conflict-banner" role="alert" tabindex="-1" ${stale ? '' : 'hidden'}><p><strong>La información cambió durante la edición.</strong> Tu propuesta no se aplicó. Actualiza los datos antes de preparar otra propuesta.</p><button id="refresh-conflict" type="button">Actualizar y descartar cambios</button></div>
    <form id="capabilities-form"><div class="matrix">${groups.map(group => `<fieldset class="capability-group"><legend>${group.title}</legend><p class="group-note">${group.note}</p>${operational.filter(c=>group.domains.includes(c.domain)).map(c => `<label class="capability-row" for="cap-${c.key}"><input id="cap-${c.key}" type="checkbox" data-capability="${c.key}" ${draft.has(c.key) ? 'checked' : ''} ${editable(c.key) ? '' : 'disabled'} aria-describedby="desc-${c.key}"><span class="capability-text"><span class="capability-label">${escape(c.label)}</span><span class="capability-description" id="desc-${c.key}">${escape(descriptions[c.key] || c.description)}</span>${reasonFor(c.key) ? `<span class="availability">${reasonFor(c.key)}</span>` : ''}</span></label>`).join('')}</fieldset>`).join('')}</div>
    <div class="save-area"><div class="save-row"><p id="unsaved" class="unsaved" role="status" aria-live="polite"></p><div class="save-buttons"><button id="revert" type="button" class="secondary">Revertir</button><button id="save-permissions" class="primary" type="submit">GUARDAR PERMISOS</button></div></div><p class="save-explanation">Guardado explícito y simulado. Un permiso asignado no activa un flujo pendiente o restringido.</p></div></form>
    <details class="reserved"><summary>Autoridades de gobierno · fuera de esta matriz</summary><p>No son permisos operativos delegables a un asesor.</p><ul>${reserved.map(c=>`<li>${escape(c.label)}</li>`).join('')}</ul></details>
    <details class="history"><summary>Historial del asesor · ejemplo ficticio</summary><p class="history-note">Ejemplos para revisar la presentación. La integración futura consultará auditoría canónica; aquí no se consulta ningún registro real.</p><ol id="history-list"></ol></details>`;
    detail.querySelectorAll('[data-capability]').forEach(input => input.onchange = () => {
      if (!editable(input.dataset.capability)) { input.checked=draft.has(input.dataset.capability); return; }
      if (input.checked) draft.add(input.dataset.capability); else draft.delete(input.dataset.capability);
      say(''); updatePending();
    });
    $('capabilities-form').onsubmit = event => { event.preventDefault(); savePreview(); };
    $('revert').onclick = () => { draft = new Set(selected.grants); say('Propuesta revertida. Los permisos simulados guardados se conservan.'); renderDetail(); $('detail-title').focus(); };
    $('lifecycle').onclick = lifecyclePreview;
    $('refresh-conflict').onclick = () => { draft=new Set(selected.grants); stale=false; say('Datos ficticios actualizados. Puedes preparar una nueva propuesta.'); render(); $('detail-title').focus(); };
    renderHistory(); updatePending();
  }
  function updatePending() {
    if (!$('unsaved')) return;
    const count = changes().grants.length + changes().revokes.length;
    $('unsaved').textContent = count ? `${count} ${count === 1 ? 'cambio sin guardar' : 'cambios sin guardar'}` : 'Sin cambios pendientes';
    $('unsaved').classList.toggle('dirty', count > 0);
    $('selected-count').textContent = draft.size;
    $('count-label').textContent = count ? (draft.size === 1 ? 'permiso propuesto' : 'permisos propuestos') : (draft.size === 1 ? 'permiso asignado' : 'permisos asignados');
    $('save-permissions').disabled = !count || !canManage() || stale || selected.status !== 'ACTIVE';
    $('revert').disabled = !count || stale;
    $('lifecycle').disabled = !canManage() || count > 0 || stale;
  }
  function savePreview() {
    if (!dirty() || !canManage() || stale || selected.status !== 'ACTIVE') return;
    const delta = changes();
    const version = selected.version;
    const section = (title, keys) => `<section class="change-list"><h3>${title} (${keys.length})</h3>${keys.length ? `<ul>${keys.map(key=>`<li>${escape(labelFor(key))}</li>`).join('')}</ul>` : '<p>Ninguno.</p>'}</section>`;
    openDialog('Confirmar propuesta de permisos', `Revisa los cambios para ${selected.name}. Esta confirmación sólo actualiza la simulación en memoria.`, section('Conceder',delta.grants)+section('Revocar',delta.revokes), 'Confirmar simulación', () => {
      if ($('simulate-conflict').checked) { selected.version++; $('simulate-conflict').checked=false; }
      if (selected.version !== version) { stale=true; say('Conflicto simulado: no se aplicó ningún cambio de tu propuesta.'); renderDetail(); return; }
      delta.grants.forEach(key=>selected.events.unshift(`Permiso concedido: ${labelFor(key)}.`));
      delta.revokes.forEach(key=>selected.events.unshift(`Permiso revocado: ${labelFor(key)}.`));
      selected.grants = new Set(draft); selected.version++;
      say(`Simulación guardada. Concedidos: ${delta.grants.length}. Revocados: ${delta.revokes.length}. No hubo cambios reales.`); render();
    });
  }
  function lifecyclePreview() {
    if (!canManage() || dirty() || stale) return;
    const suspend = selected.status === 'ACTIVE';
    openDialog(suspend ? '¿Suspender al asesor?' : '¿Reactivar al asesor?', `${selected.name}: ${suspend ? 'la suspensión bloquea futuras acciones operativas y conserva todos sus permisos e historial. No elimina la cuenta.' : 'la reactivación conserva sus permisos. Cada operación sigue requiriendo los controles de su servicio.'} Sólo es una simulación.`, '', suspend ? 'Simular suspensión' : 'Simular reactivación', () => {
      selected.status = suspend ? 'SUSPENDED' : 'ACTIVE'; selected.version++; selected.events.unshift(suspend ? 'Asesor suspendido. Permisos e historial conservados.' : 'Asesor reactivado.');
      say(suspend ? 'Suspensión simulada. Permisos e historial conservados; no se modificó ninguna cuenta real.' : 'Reactivación simulada. No se modificó ninguna cuenta real.'); render();
    });
  }
  function renderHistory() {
    const examples = ['Asesor registrado en el equipo interno.','Permiso concedido: Consultar revisión de medios.','Permiso revocado: Aprobar medios.','Asesor suspendido. Permisos e historial conservados.','Asesor reactivado.'];
    $('history-list').innerHTML = [...selected.events, ...examples].map((text,index)=>`<li><time>Ejemplo ${index+1} · Dirección ficticia</time>${escape(text)}</li>`).join('');
  }
  function render() { renderDirectory(); renderDetail(); }
  $('advisor-search').oninput = event => { query=event.target.value; renderDirectory(); };
  $('authority-view').onchange = event => {
    const next=event.target.value; $('authority-view').value=mode;
    transition(() => { mode=next; $('authority-view').value=mode; if (mode==='self') selected=advisors.find(a=>a.id==='mar'); draft=new Set(selected?.grants || []); stale=false; say(''); render(); });
  };
  $('directory-state').onchange = event => {
    const next=event.target.value; $('directory-state').value=directoryState;
    transition(() => { directoryState=next; $('directory-state').value=next; selected=advisors[0]; draft=new Set(selected.grants); stale=false; say(''); render(); });
  };
  $('add-advisor').onclick = () => openDialog('Agregar asesor · vista previa', 'La incorporación vinculará al equipo interno una cuenta legítima de Identity ya existente. No crea una cuenta nueva.', '<p>La búsqueda autorizada de cuentas, su validación y el registro canónico se conectarán en otra fase. Aquí no se consulta ni registra a ninguna persona.</p>', '', null);
  $('cancel-confirmation').onclick = () => { pendingAction=null; dialog.close(); };
  $('accept-confirmation').onclick = () => { const action=pendingAction; pendingAction=null; dialog.close(); action?.(); };
  dialog.addEventListener('keydown', event => {
    if (event.key !== 'Tab') return;
    const controls = [...dialog.querySelectorAll('button')].filter(button => !button.hidden && !button.disabled && button.getClientRects().length);
    if (!controls.length) return;
    event.preventDefault();
    const current = controls.indexOf(document.activeElement);
    const next = current < 0 ? (event.shiftKey ? controls.length - 1 : 0) : (current + (event.shiftKey ? -1 : 1) + controls.length) % controls.length;
    controls[next].focus();
  });
  dialog.addEventListener('close', () => {
    // A queued close from the previous dialog must not cancel a newly opened confirmation.
    if (dialog.open) return;
    pendingAction=null;
    if (dialogReturnFocus?.isConnected) { dialogReturnFocus.focus(); return; }
    const target = stale && !$('conflict-banner')?.hidden ? $('conflict-banner') : $('detail-title');
    target?.focus();
  });
  window.addEventListener('beforeunload', event => { if (dirty()) { event.preventDefault(); event.returnValue=''; } });
  render();
})();
