// Clean visual projection of the identified legacy Director seed, never a data migration.
// The router injects this only on explicit loopback review URLs. Product reads/writes
// and real narratives (including the same wording saved later) remain untouched.
(() => {
  const params = new URLSearchParams(location.search);
  if (!['127.0.0.1', 'localhost', '[::1]'].includes(location.hostname)
    || params.get('review_patient') !== 'plan02ux'
    || params.get('review_encounter') !== 'open'
    || params.get('review_placeholders') !== 'clean') return;

  const sample = 'Solicitar estudios de control y revisar los resultados en la próxima consulta.';
  const seedTime = '2026-09-25 22:48:46';
  const originalFetch = window.fetch.bind(window);
  window.fetch = async (input, init) => {
    const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url, location.href);
    const method = String(init?.method || input?.method || 'GET').toUpperCase();
    const response = await originalFetch(input, init);
    if (method !== 'GET' || !response.ok || url.origin !== location.origin
      || decodeURIComponent(url.pathname) !== '/api/clinical/index.php/encounters/enc:1016') return response;
    const payload = await response.clone().json().catch(() => null);
    const detail = payload?.data;
    const plan = detail?.sections?.plan;
    if (payload?.ok !== true || detail?.patient_id !== 'p_plan02ux_review'
      || Number(detail.encounter_id) !== 1016 || detail.status !== 'open'
      || plan?.section_type !== 'plan' || Number(plan.row_version) !== 1
      || plan.created_at !== seedTime || plan.updated_at !== seedTime
      || plan.narrative_text !== sample || Object.keys(plan.payload || {}).length !== 0) return response;
    // Retain row_version and metadata so the existing writer can save real text safely.
    plan.narrative_text = '';
    const headers = new Headers(response.headers);
    headers.delete('Content-Length');
    headers.set('Cache-Control', 'no-store');
    return new Response(JSON.stringify(payload), {status: response.status, statusText: response.statusText, headers});
  };
})();
