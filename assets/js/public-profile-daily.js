/* Exact-date public UI; availability, eligibility and totals are owned by the API. */
window.MxmedPublicDailyModal = function (block, booking) {
  'use strict';
  const dialog = document.createElement('dialog');
  dialog.className = 'mxpp-daily-dialog';
  dialog.setAttribute('aria-labelledby', 'mxpp-daily-title');
  dialog.setAttribute('aria-describedby', 'mxpp-daily-date');
  dialog.innerHTML = '<header><div><h2 id="mxpp-daily-title">Horarios disponibles</h2>'
    + '<p id="mxpp-daily-date" aria-live="polite" aria-atomic="true"></p>'
    + '<nav aria-label="Cambiar día"><button type="button" data-daily-prev>Día anterior</button>'
    + '<button type="button" data-daily-next>Día siguiente</button></nav></div><button type="button" data-daily-close aria-label="Cerrar">×</button></header>'
    + '<p data-daily-status role="status"></p><div class="mxpp-daily-results" data-daily-results></div>'
    + '<footer><button type="button" data-daily-close>Cerrar</button></footer>';
  document.body.append(dialog);
  const results = dialog.querySelector('[data-daily-results]');
  const status = dialog.querySelector('[data-daily-status]');
  const previous = dialog.querySelector('[data-daily-prev]');
  const next = dialog.querySelector('[data-daily-next]');
  let date, min, max, request, returnFocus, choosing = false;
  const shift = (value, count) => {
    const d = new Date(value + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() + count);
    return d.toISOString().slice(0, 10);
  };
  async function load(value) {
    request?.abort();
    const controller = new AbortController(); request = controller;
    const timer = setTimeout(() => controller.abort(), 15000);
    date = value;
    previous.disabled = next.disabled = true;
    results.replaceChildren(); results.setAttribute('aria-busy', 'true');
    dialog.querySelector('#mxpp-daily-date').textContent = booking.formatDate(date);
    status.textContent = 'Buscando horarios disponibles…';
    try {
      const params = new URLSearchParams({doctor_id: block.dataset.doctorId, mode: 'day', date});
      if (block.dataset.qaPlan) params.set('mxmed_plan', block.dataset.qaPlan);
      const response = await fetch('/api/agenda/index.php/public/availability?' + params, {
        headers: {Accept: 'application/json'}, signal: controller.signal
      });
      const payload = await response.json();
      if (!response.ok || payload.ok !== true || !Array.isArray(payload.data?.days?.[0]?.slots)) throw Error('availability');
      if (request !== controller || !dialog.open) return;
      min = payload.meta.min_date; max = payload.meta.max_date;
      const slots = payload.data.days[0].slots;
      slots.forEach(slot => {
        const button = document.createElement('button'); button.type = 'button';
        button.className = 'mxpp-daily-slot';
        button.title = slot.consultorio_name;
        const time = document.createElement('strong'); time.textContent = booking.formatTime(slot.start_at) + ' h';
        const office = document.createElement('span'); office.textContent = slot.consultorio_name;
        button.append(time, office);
        button.addEventListener('click', () => {
          choosing = true;
          dialog.close();
          booking.choose(slot);
        });
        results.append(button);
      });
      status.textContent = slots.length ? '' : 'No hay horarios disponibles para este día.';
    } catch (_) {
      if (request !== controller || !dialog.open) return;
      status.textContent = 'No pudimos consultar los horarios. Cierra esta ventana e inténtalo de nuevo.';
    } finally {
      clearTimeout(timer);
      if (request === controller) {
        request = null; results.removeAttribute('aria-busy');
        previous.disabled = !min || date <= min;
        next.disabled = !max || date >= max;
      }
    }
  }
  previous.addEventListener('click', () => load(shift(date, -1)));
  next.addEventListener('click', () => load(shift(date, 1)));
  dialog.querySelectorAll('[data-daily-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('close', () => {
    // Native close events are queued; a rapid reopen must not abort its new request.
    if (dialog.open) return;
    request?.abort(); request = null;
    if (!choosing && returnFocus?.isConnected) returnFocus.focus({preventScroll: true});
  });
  dialog.addEventListener('keydown', event => {
    if (event.key !== 'Tab') return;
    const buttons = [...dialog.querySelectorAll('button:not(:disabled)')];
    const first = buttons[0], last = buttons[buttons.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  return {open(value, trigger) {
    returnFocus = trigger; choosing = false; min = max = null;
    dialog.showModal(); dialog.querySelector('[data-daily-close]').focus({preventScroll: true});
    load(value);
  }};
};
