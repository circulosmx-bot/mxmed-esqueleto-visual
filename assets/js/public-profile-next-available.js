/* Patient presentation over the existing public mode=next availability endpoint. */
window.MxmedPublicNextAvailable = function (block, booking) {
  'use strict';
  const trigger = block.querySelector('[data-mxpp-next-available]');
  if (!trigger || typeof HTMLDialogElement === 'undefined') return;
  const create = (tag, text, className = '') => {
    const node = document.createElement(tag);
    node.textContent = text;
    node.className = className;
    if (tag === 'button') node.type = 'button';
    return node;
  };
  const icon = name => {
    const node = create('i', '', 'bi bi-' + name);
    node.setAttribute('aria-hidden', 'true');
    return node;
  };
  const dialog = create('dialog', '', 'mxpp-next-dialog mx-ag-next-slots-modal');
  dialog.setAttribute('aria-labelledby', 'mxpp-next-title');
  const header = create('header', '', 'mxpp-next-dialog__header modal-header mx-ag-next-slots-modal-header');
  const main = create('div', '', 'mx-ag-next-slots-header-main');
  const headerIcon = create('span', '', 'mx-ag-next-slots-header-icon');
  headerIcon.append(icon('calendar2-check'));
  const copy = create('div', '', 'mx-ag-next-slots-header-copy');
  const title = create('h2', 'Siguiente cita disponible', 'modal-title');
  title.id = 'mxpp-next-title';
  const close = create('button', '', 'mxpp-next-dialog__close');
  close.setAttribute('aria-label', 'Cerrar');
  const intro = create('div', 'Selecciona el horario que mejor se ajuste a ti.', 'mx-ag-next-slots-header-subtitle');
  copy.append(title, intro);
  main.append(headerIcon, copy);
  header.append(main, close);
  const body = create('div', '', 'mx-ag-next-slots-modal-body');
  const status = create('p', '');
  status.setAttribute('role', 'status');
  const results = create('div', '', 'mxpp-next-dialog__results mx-ag-next-slots-results');
  const info = create('div', '', 'mx-ag-next-slots-info-note');
  info.append(icon('info-circle'), create('span', 'Las citas mostradas corresponden a la disponibilidad actual del médico y consultorio.'));
  body.append(status, results, info);
  const nav = create('nav', '', 'mxpp-next-dialog__nav mx-ag-next-slots-modal-footer');
  nav.setAttribute('aria-label', 'Más citas disponibles');
  const previous = create('button', 'Ver 3 anteriores', 'btn');
  const next = create('button', 'Ver siguientes 3', 'btn');
  const footerClose = create('button', 'Cerrar', 'btn');
  footerClose.addEventListener('click', () => dialog.close());
  nav.append(previous, next, footerClose);
  dialog.append(header, body, nav);
  document.body.append(dialog);
  const names = JSON.parse(block.dataset.publicConsultorios || '{}');
  let context, slots = [], offset = 0, startDate = '', exhausted = false, request = null;

  // Pagination slices server-produced slots; no schedules, capacity or private APIs here.
  async function showPage(target) {
    if (request) return;
    previous.disabled = next.disabled = true;
    results.replaceChildren();
    status.textContent = 'Buscando horarios disponibles…';
    results.setAttribute('aria-busy', 'true');
    const controller = new AbortController();
    request = controller;
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
      while (slots.length < target + 3 && !exhausted) {
        const params = new URLSearchParams({doctor_id: context.doctorId, mode: 'next', days: '3', limit_per_day: '0'});
        if (context.consultorioId) params.set('consultorio_id', context.consultorioId);
        if (startDate) params.set('start_date', startDate);
        const response = await fetch('/api/agenda/index.php/public/availability?' + params, {
          headers: {Accept: 'application/json'}, signal: controller.signal
        });
        const payload = await response.json();
        if (!response.ok || payload.ok !== true || !Array.isArray(payload.data?.days)) throw new Error('availability');
        if (request !== controller || !dialog.open) return;
        const office = String(payload.meta?.consultorio_id_used || '');
        if (!office || (context.consultorioId && context.consultorioId !== office)) throw new Error('context');
        context.consultorioId = office;
        // Compare SQL timestamps in the API's timezone, independently of the patient's timezone.
        const parts = new Intl.DateTimeFormat('en-CA', {
          timeZone: payload.meta?.timezone || 'America/Mexico_City', year: 'numeric', month: '2-digit', day: '2-digit',
          hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
        }).formatToParts(new Date());
        const p = Object.fromEntries(parts.map(part => [part.type, part.value]));
        const now = `${p.year}-${p.month}-${p.day} ${p.hour}:${p.minute}:${p.second}`;
        const days = payload.data.days;
        days.forEach(day => (day.slots || []).forEach(slot => {
          if (!slot.start_at || !slot.end_at || slot.start_at <= now) return;
          slots.push({date: day.date, start_at: slot.start_at, end_at: slot.end_at,
            consultorio_id: office, doctor_id: context.doctorId, booking_url: context.bookingUrl});
        }));
        exhausted = days.length < 3;
        if (days.length) {
          const date = new Date(days[days.length - 1].date + 'T00:00:00Z');
          date.setUTCDate(date.getUTCDate() + 1);
          startDate = date.toISOString().slice(0, 10);
        }
      }
      offset = target;
      const visible = slots.slice(offset, offset + 3);
      status.textContent = visible.length ? '' : 'No encontramos citas disponibles próximamente.';
      visible.forEach(slot => {
        const card = create('article', '', 'mxpp-next-dialog__result mx-ag-next-slot-card');
        const details = create('div', '', 'mx-ag-next-slot-focus-area');
        const calendar = create('div', '', 'mx-ag-next-slot-ico');
        calendar.append(icon('calendar2-check'));
        const date = new Date(slot.date + 'T00:00:00');
        const capitalize = text => text.charAt(0).toLocaleUpperCase('es-MX') + text.slice(1);
        const weekday = capitalize(new Intl.DateTimeFormat('es-MX', {weekday: 'long'}).format(date));
        const month = capitalize(new Intl.DateTimeFormat('es-MX', {month: 'long'}).format(date));
        const dateTime = create('div', '', 'mx-ag-next-slot-main');
        dateTime.append(create('div', 'Fecha y hora', 'mx-ag-next-slot-label'),
          create('div', `${weekday}, ${date.getDate()} de ${month} ${date.getFullYear()}`, 'mx-ag-next-slot-date'),
          create('div', booking.formatTime(slot.start_at) + ' h', 'mx-ag-next-slot-time'));
        const office = create('div', '', 'mx-ag-next-slot-consultorio');
        const officeName = create('div', '', 'mx-ag-next-slot-consultorio-name');
        officeName.append(icon('hospital'), create('span', names[slot.consultorio_id] || 'Consultorio'));
        office.append(create('div', 'Consultorio', 'mx-ag-next-slot-label'), officeName);
        details.append(calendar, dateTime, office);
        const actions = create('div', '', 'mx-ag-next-slot-actions');
        const button = create('button', 'Elegir', 'mx-ag-next-slot-choose');
        button.addEventListener('click', () => {
          dialog.close();
          booking.choose(slot);
        });
        actions.append(button);
        card.append(details, actions);
        results.append(card);
      });
      previous.disabled = offset === 0;
      next.disabled = exhausted && offset + 3 >= slots.length;
    } catch (_) {
      if (request !== controller || !dialog.open) return;
      status.textContent = 'No pudimos consultar los horarios. Cierra esta ventana e inténtalo de nuevo.';
      previous.disabled = offset === 0;
    } finally {
      clearTimeout(timeout);
      if (request === controller) {
        request = null;
        results.removeAttribute('aria-busy');
      }
    }
  }
  trigger.addEventListener('click', () => {
    context = booking.getContext();
    slots = []; offset = 0; startDate = ''; exhausted = false;
    dialog.showModal();
    close.focus({preventScroll: true});
    showPage(0);
  });
  close.addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => {
    request?.abort(); request = null;
    // Do not steal focus when a result has opened the existing booking modal.
    if (document.querySelector('[data-mxpp-booking-modal]')?.hidden !== false) trigger.focus({preventScroll: true});
  });
  dialog.addEventListener('keydown', event => {
    if (event.key !== 'Tab') return;
    const buttons = [...dialog.querySelectorAll('button:not(:disabled)')];
    const first = buttons[0], last = buttons[buttons.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  previous.addEventListener('click', () => showPage(Math.max(0, offset - 3)));
  next.addEventListener('click', () => showPage(offset + 3));
};
