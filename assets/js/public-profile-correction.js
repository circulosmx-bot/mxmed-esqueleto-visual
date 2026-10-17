'use strict';
(() => {
  const form = document.querySelector('[data-correction-report]');
  if (!form) return;
  const status = form.querySelector('[role="status"]');
  const fields = form.querySelector('fieldset');
  let busy = false;
  let completed = false;
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || completed || !form.reportValidity()) return;
    const data = Object.fromEntries(new FormData(form));
    data.privacy_ack = form.elements.privacy_ack.checked;
    busy = true;
    fields.disabled = true;
    status.textContent = 'Enviando informe…';
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch('/api/profiles/public/correction-reports.php', {
        method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data), signal: controller.signal
      });
      const result = await response.json();
      if (!response.ok || result.ok !== true || typeof result.data?.receipt_id !== 'string') {
        status.textContent = response.status === 429
          ? 'Has enviado varios informes. Inténtalo más tarde.'
          : 'No pudimos confirmar la recepción. Conserva la información e inténtalo de nuevo; si el problema continúa, vuelve a abrir este perfil.';
        return;
      }
      completed = true;
      form.reset();
      status.textContent = 'Recibimos tu informe. Nuestro equipo revisará la información antes de realizar cualquier cambio.';
    } catch {
      status.textContent = 'No pudimos confirmar la recepción. Inténtalo de nuevo cuando tengas conexión.';
    } finally {
      clearTimeout(timer);
      busy = false;
      fields.disabled = completed;
    }
  });
  window.addEventListener('pagehide', () => form.reset());
})();
