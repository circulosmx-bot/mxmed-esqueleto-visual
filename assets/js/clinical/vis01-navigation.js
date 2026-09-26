// VIS01: primary destinations and contextual links only; no clinical commands.
(function () {
  const pane = document.getElementById('p-expediente');
  const navigation = pane?.querySelector('.vis01-primary-navigation');
  if (!navigation) return;
  const context = pane.querySelector('[data-vis01-context]');
  const returnButton = context.querySelector('[data-vis01-return]');
  const title = context.querySelector('[data-vis01-context-title]');
  const parents = {
    '#t-antecedentes-longitudinal': '#t-resumen-longitudinal',
    '#t-problemas-longitudinal': '#t-resumen-longitudinal',
    '#t-medicamentos-longitudinal': '#t-resumen-longitudinal',
    '#t-tareas-longitudinal': '#t-resumen-longitudinal',
    '#t-mediciones-longitudinal': '#t-resumen-longitudinal',
    '#t-historia': '#t-historial-atencion',
    '#t-exploracion': '#t-historial-atencion',
    '#t-gineco': '#t-historial-atencion',
    '#t-manejo': '#t-historial-atencion',
    '#t-notas': '#t-historial-atencion',
    '#t-archivo': '#t-consent'
  };
  const trigger = target => [...navigation.querySelectorAll('[data-bs-target]')].find(tab => tab.dataset.bsTarget === target);
  function open(target) {
    const tab = trigger(target);
    if (!tab || tab.disabled || tab.closest('.nav-item').classList.contains('d-none')) return;
    // Bootstrap emits hide.bs.tab, preserving the existing draft/dirty guard.
    window.bootstrap?.Tab.getOrCreateInstance(tab).show();
  }
  // Bootstrap's arrow-key list includes hidden compatibility targets. Restrict
  // keyboard traversal to the six visible destinations, retaining hide guards.
  navigation.addEventListener('keydown', event => {
    if (!['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    const tabs = [...navigation.querySelectorAll('.nav-item:not([data-vis01-secondary]):not([hidden]):not(.d-none) > .nav-link')]
      .filter(tab => !tab.disabled && tab.getAttribute('aria-disabled') !== 'true');
    const index = tabs.indexOf(event.target);
    if (index < 0) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
      : (index + (['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1) + tabs.length) % tabs.length;
    const target = tabs[next];
    open(target.dataset.bsTarget);
    if (target.classList.contains('active')) {
      target.focus({preventScroll:true});
      target.scrollIntoView({block:'nearest', inline:'nearest'});
    }
  }, true);
  pane.addEventListener('click', event => {
    const link = event.target.closest('[data-vis01-open]');
    if (link && pane.contains(link)) open(link.dataset.vis01Open);
  });
  returnButton.addEventListener('click', () => open(returnButton.dataset.target));
  function sync() {
    const active = navigation.querySelector('.nav-link.active');
    const parent = parents[active?.dataset.bsTarget];
    context.hidden = !parent;
    navigation.querySelectorAll('.nav-link').forEach(tab => {
      const contextual = tab.dataset.bsTarget === parent;
      if (tab.classList.contains('vis01-context-active') !== contextual) tab.classList.toggle('vis01-context-active', contextual);
      if (contextual) tab.setAttribute('aria-current', 'location');
      else tab.removeAttribute('aria-current');
    });
    if (parent) {
      returnButton.dataset.target = parent;
      returnButton.textContent = `Volver a ${trigger(parent).querySelector('.tab-lbl').textContent.trim()}`;
      title.textContent = active.querySelector('.tab-lbl').textContent.trim();
    }
    pane.querySelectorAll('[data-vis01-open]').forEach(link => {
      const tab = trigger(link.dataset.vis01Open);
      link.hidden = !tab || tab.closest('.nav-item').classList.contains('d-none');
    });
  }
  navigation.addEventListener('shown.bs.tab', event => {
    sync();
    if (event.target.closest('[data-vis01-secondary]')) {
      // The compatibility trigger is hidden. Move focus to its visible context.
      returnButton.focus({preventScroll:true});
    }
  });
  // The patient gate also activates tabs without Bootstrap events.
  new MutationObserver(sync).observe(navigation, {subtree:true,attributes:true,attributeFilter:['class']});
  sync();
})();
