(() => {
  'use strict';
  const about = document.querySelector('[data-mxpp-profile-view="about"]');
  if (!about || typeof HTMLDialogElement === 'undefined') return;

  const itemLimit = 4;
  const sections = new Map();
  const create = (tag, className) => {
    const element = document.createElement(tag);
    element.className = className;
    return element;
  };
  // Native dialog provides an inert background and Escape dismissal.
  const dialog = create('dialog', 'mxpp-about-dialog');
  dialog.setAttribute('aria-labelledby', 'mxpp-about-dialog-title');
  const header = create('header', 'mxpp-about-dialog__header');
  const title = create('h2', '');
  title.id = 'mxpp-about-dialog-title';
  const close = create('button', 'mxpp-about-dialog__close');
  close.type = 'button';
  close.textContent = 'Cerrar';
  const content = create('div', 'mxpp-about-dialog__content');
  content.tabIndex = 0;
  header.append(title, close);
  dialog.append(header, content);
  document.body.append(dialog);
  let origin = null;
  let previousOverflow = '';
  let previousOverflowPriority = '';
  let previousScroll = { x: 0, y: 0 };

  function openAboutDetail(key) {
    // Only identifiers registered from the escaped, already-public ABOUT DOM exist.
    const section = sections.get(key);
    if (!section || !section.button || dialog.open) return;
    origin = section.button;
    title.textContent = section.title;
    header.querySelector('.mxpp-content-panel__icon')?.remove();
    if (section.icon) header.prepend(section.icon.cloneNode(true));
    const full = section.source.cloneNode(true);
    full.classList.remove('mxpp-about-text-preview');
    full.querySelectorAll('[hidden]').forEach((item) => { item.hidden = false; });
    content.replaceChildren(full);
    previousOverflow = document.documentElement.style.getPropertyValue('overflow');
    previousOverflowPriority = document.documentElement.style.getPropertyPriority('overflow');
    previousScroll = { x: window.scrollX, y: window.scrollY };
    document.documentElement.style.setProperty('overflow', 'hidden');
    dialog.showModal();
    content.scrollTop = 0;
    close.focus({ preventScroll: true });
  }
  close.addEventListener('click', () => dialog.close());
  dialog.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab') return;
    const stops = Array.from(dialog.querySelectorAll('button, [href], [tabindex="0"]'))
      .filter((element) => !element.disabled && element.getClientRects().length);
    const first = stops[0];
    const last = stops[stops.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  dialog.addEventListener('close', () => {
    if (previousOverflow) document.documentElement.style.setProperty('overflow', previousOverflow, previousOverflowPriority);
    else document.documentElement.style.removeProperty('overflow');
    origin?.focus({ preventScroll: true });
    window.scrollTo({ left: previousScroll.x, top: previousScroll.y, behavior: 'instant' });
  });
  const outside = (event) => {
    const bounds = dialog.getBoundingClientRect();
    return event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right
      || event.clientY < bounds.top || event.clientY > bounds.bottom);
  };
  let startedOutside = false;
  dialog.addEventListener('pointerdown', (event) => { startedOutside = outside(event); });
  dialog.addEventListener('click', (event) => {
    if (startedOutside && outside(event)) dialog.close();
    startedOutside = false;
  });

  function update(section) {
    if (!about.getClientRects().length) return;
    const truncated = section.list
      ? section.source.children.length > itemLimit
      : section.source.scrollHeight > section.source.clientHeight + 1;
    if (truncated && !section.button) {
      section.button = create('button', 'mxpp-about-more');
      section.button.type = 'button';
      section.button.textContent = 'Ver más';
      section.button.setAttribute('aria-haspopup', 'dialog');
      section.button.setAttribute('aria-label', `Ver más: ${section.title}`);
      section.button.addEventListener('click', () => openAboutDetail(section.key));
      section.source.after(section.button);
    } else if (!truncated && section.button) {
      section.button.remove();
      section.button = null;
    }
  }
  function register(source, sectionTitle, icon = null) {
    const key = `about-section-${sections.size}`;
    const list = source.tagName === 'UL' || source.tagName === 'OL';
    const section = { key, source, title: sectionTitle, icon, list, button: null };
    sections.set(key, section);
    if (list) Array.from(source.children).forEach((item, index) => { item.hidden = index >= itemLimit; });
    else source.classList.add('mxpp-about-text-preview');
    return section;
  }
  about.querySelectorAll('.mxpp-content-panel__group').forEach((group) => {
    const source = group.querySelector('.mxpp-content-panel__copy > ul, .mxpp-content-panel__copy > ol, .mxpp-content-panel__copy > p:not(.mxpp-content-panel__section-empty)');
    const heading = group.querySelector('h3');
    if (source && heading) register(source, heading.textContent, group.querySelector('.mxpp-content-panel__icon'));
  });
  const intro = about.querySelector('.mxpp-content-panel__intro');
  if (intro) register(intro, 'Perfil profesional');
  const refresh = () => sections.forEach(update);
  const observer = new ResizeObserver(refresh);
  observer.observe(about);
  sections.forEach((section) => { if (!section.list) observer.observe(section.source); });
  document.fonts.ready.then(refresh);
  refresh();
})();
