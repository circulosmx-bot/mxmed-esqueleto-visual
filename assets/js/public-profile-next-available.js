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
  const dialog = create('dialog', '', 'mxpp-next-dialog');
  dialog.setAttribute('aria-labelledby', 'mxpp-next-title');
  const header = create('header', '', 'mxpp-next-dialog__header');
  const title = create('h2', 'Siguiente cita disponible');
  title.id = 'mxpp-next-title';
  const close = create('button', 'Cerrar', 'mxpp-agenda-compact__nav-btn');
  header.append(title, close);
  const intro = create('p', 'Selecciona el horario que mejor se ajuste a ti.');
  const status = create('p', '');
  status.setAttribute('role', 'status');
  const results = create('div', '', 'mxpp-next-dialog__results');
  const nav = create('nav', '', 'mxpp-next-dialog__nav');
  nav.setAttribute('aria-label', 'Más citas disponibles');
  const previous = create('button', 'Ver 3 anteriores', 'mxpp-agenda-compact__nav-btn');
  const next = create('button', 'Ver siguientes 3', 'mxpp-agenda-compact__nav-btn');
  nav.append(previous, next);
  dialog.append(header, intro, status, results, nav);
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
        const button = create('button', '', 'mxpp-next-dialog__result');
        button.append(create('strong', booking.formatDate(slot.date)),
          create('span', booking.formatTime(slot.start_at) + ' – ' + booking.formatTime(slot.end_at)),
          create('span', names[slot.consultorio_id] || 'Consultorio'), create('span', 'Elegir'));
        button.addEventListener('click', () => {
          dialog.close();
          booking.choose(slot);
        });
        results.append(button);
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
