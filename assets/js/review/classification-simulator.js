// CLASSIFICATION-SIM01: local Director review only. Never alters physician data or clinical commands.
(function () {
  'use strict';
  if (!/^(localhost|127\.0\.0\.1)$/.test(location.hostname) ||
      new URLSearchParams(location.search).get('qa_tools') === 'hide' ||
      typeof window.mxmedGetQaPlan !== 'function') return;

  const navigation = window.mxmedSpecialtyNavigationV1;
  if (!navigation) return;
  const storageKey = 'mxmed.qa.classification.v1';
  const normalize = navigation.normalize;
  const options = [
    ...Object.keys(navigation.config.specialties).map(label => ({kind:'specialty', label})),
    ...Object.keys(navigation.config.professionalTitles)
      .filter(label => /^[A-ZÁÉÍÓÚÑ]/.test(label)).map(label => ({kind:'title', label}))
  ].map(option => ({...option, value:`${option.kind}:${normalize(option.label)}`}))
    .sort((a, b) => a.label.localeCompare(b.label, 'es'));
  const byValue = new Map(options.map(option => [option.value, option]));
  const read = () => {
    try {
      const value = sessionStorage.getItem(storageKey);
      if (value && !byValue.has(value)) sessionStorage.removeItem(storageKey);
      return byValue.get(value) || null;
    }
    catch (_) { return null; }
  };
  let selected = read();
  const effective = realProfile => {
    if (!selected) return navigation.resolve(realProfile);
    return navigation.resolve({identity_public:selected.kind === 'specialty'
      ? {specialty_primary:selected.label} : {professional_designation:selected.label}});
  };
  let trigger = null;
  const updateTrigger = () => {
    if (!trigger) return;
    trigger.textContent = `${selected?.label || 'Real del perfil'} ▾`;
    trigger.title = selected ? `Clasificación simulada: ${selected.label}` : 'Clasificación real del perfil';
    trigger.setAttribute('aria-label', trigger.title);
    trigger.dataset.simulated = selected ? 'true' : 'false';
  };
  function set(value, source = 'director_selector') {
    const next = byValue.get(value) || null;
    if (next?.value === selected?.value) return selected;
    selected = next;
    try {
      if (selected) sessionStorage.setItem(storageKey, selected.value);
      else sessionStorage.removeItem(storageKey);
    } catch (_) {}
    updateTrigger();
    window.dispatchEvent(new CustomEvent('mxmed:review-classification-changed', {
      detail:{value:selected?.value || null, label:selected?.label || 'Real del perfil',
        simulated:!!selected, source}
    }));
    return selected;
  }

  // Only modules that explicitly call this local API observe the override.
  window.mxmedReviewClassification = Object.freeze({
    options:() => options.map(({kind,label,value}) => ({kind,label,value})),
    current:() => selected ? {...selected} : null,
    resolveNavigation:effective,
    set
  });

  function mount() {
    const planPanel = document.getElementById('mxmed_dev_role_switcher');
    if (!planPanel || document.getElementById('mxmed_qa_classification_trigger')) return;
    const field = document.createElement('div');
    field.className = 'd-flex align-items-center gap-2';
    field.id = 'mxmed_qa_classification_field';
    const label = document.createElement('span');
    label.className = 'small text-muted';
    label.textContent = 'CLASIFICACIÓN';
    trigger = document.createElement('button');
    trigger.id = 'mxmed_qa_classification_trigger';
    trigger.type = 'button';
    trigger.className = 'btn btn-outline-secondary btn-sm text-start flex-grow-1';
    trigger.style.minWidth = '0';
    trigger.style.maxWidth = '260px';
    trigger.style.whiteSpace = 'nowrap';
    trigger.style.overflow = 'hidden';
    trigger.style.textOverflow = 'ellipsis';
    updateTrigger();
    field.append(label, trigger);
    planPanel.insertBefore(field, planPanel.children[1] || null);
    const mobileStyle = document.createElement('style');
    mobileStyle.textContent = '@media(max-width:700px){#mxmed_qa_classification_field>span{display:none}' +
      '#mxmed_qa_classification_trigger{width:150px;max-width:150px}}';
    document.head.append(mobileStyle);

    const dialog = document.createElement('dialog');
    dialog.id = 'mxmed_qa_classification_dialog';
    dialog.setAttribute('aria-label', 'Simular clasificación profesional');
    dialog.className = 'border rounded-3 shadow p-3';
    dialog.style.width = 'min(420px, calc(100vw - 24px))';
    dialog.style.maxHeight = 'min(72vh, 620px)';
    dialog.style.padding = '14px';
    const heading = document.createElement('div');
    heading.className = 'd-flex align-items-center justify-content-between gap-2 mb-2';
    const title = document.createElement('strong');
    title.textContent = 'Clasificación para revisión';
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn btn-outline-secondary btn-sm';
    close.textContent = 'Cerrar';
    close.addEventListener('click', () => dialog.close());
    heading.append(title, close);
    const description = document.createElement('p');
    description.className = 'small text-muted mb-2';
    description.textContent = 'Solo cambia la navegación local. No modifica el perfil ni las órdenes.';
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'form-control form-control-sm mb-2';
    search.placeholder = 'Buscar clasificación';
    search.setAttribute('aria-label', 'Buscar clasificación');
    const count = document.createElement('p');
    count.className = 'small text-muted mb-1';
    count.setAttribute('role', 'status');
    const list = document.createElement('div');
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Clasificaciones disponibles');
    list.style.maxHeight = 'min(46vh, 390px)';
    list.style.overflowY = 'auto';
    list.style.display = 'grid';
    list.style.gap = '3px';
    dialog.append(heading, description, search, count, list);
    document.body.append(dialog);

    function render() {
      const query = normalize(search.value);
      const matches = options.filter(option => normalize(option.label).includes(query));
      list.replaceChildren();
      const real = document.createElement('button');
      real.type = 'button';
      real.className = 'btn btn-outline-primary btn-sm text-start';
      real.textContent = 'Real del perfil';
      real.setAttribute('role', 'option');
      real.setAttribute('aria-selected', String(!selected));
      real.dataset.classificationValue = 'real';
      real.addEventListener('click', () => { set(null); dialog.close(); trigger.focus(); });
      if (!query || normalize('Real del perfil').includes(query)) list.append(real);
      matches.forEach(option => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'btn btn-outline-secondary btn-sm text-start';
        item.textContent = option.label;
        item.dataset.classificationValue = option.value;
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', String(option.value === selected?.value));
        item.addEventListener('click', () => { set(option.value); dialog.close(); trigger.focus(); });
        list.append(item);
      });
      count.textContent = `${matches.length} clasificaciones`;
    }
    trigger.addEventListener('click', () => { search.value = ''; render(); dialog.showModal(); search.focus(); });
    search.addEventListener('input', render);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, {once:true});
  else mount();
})();
