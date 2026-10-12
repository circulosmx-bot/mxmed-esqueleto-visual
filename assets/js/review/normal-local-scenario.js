(() => {
  if (location.origin !== 'https://localhost:8443' || !['/', '/index.html'].includes(location.pathname)) return;
  const endpoint = '/api/local-review/index.php';
  let state;
  const panel = document.createElement('aside');
  panel.id = 'mxmed-local-review';
  panel.setAttribute('aria-label', 'Escenario local de revisión');
  panel.innerHTML = `<details open><summary>Revisión local · Perfil 1</summary><div class="review-content">
    <strong data-label></strong><div>Plan real: <span data-real></span></div>
    <label>Escenario de revisión <select aria-label="Escenario de revisión"><option value="real">Real</option><option value="standard">Estándar</option><option value="optimum">Óptimo</option><option value="professional">Profesional</option></select></label>
    <div data-capabilities></div><div data-administration></div><div role="status" aria-live="polite"></div>
    </div></details>`;
  const select = panel.querySelector('select');
  const status = panel.querySelector('[role=status]');
  const labels = {standard:'Estándar',optimum:'Óptimo',professional:'Profesional',free:'Gratuito',basic:'Básico'};
  function render(data) {
    state=data; const current=data.current; const review=current.local_review;
    panel.querySelector('[data-label]').textContent=data.label;
    panel.querySelector('[data-real]').textContent=labels[review.real_plan] || review.real_plan;
    select.value=review.scenario;
    panel.querySelector('[data-capabilities]').textContent=[['patients','Pacientes'],['clinical_record','Expediente clínico'],['prescriptions','Recetas']].map(([key,label])=>`${label}: ${current.feature_access[key].available?'Sí':'No'}`).join(' · ');
    panel.querySelector('[data-administration]').textContent=`Perfil administrado: ${data.administration.is_administered?'Sí':'No'} · Fuente disponible: ${data.administration.source_ready?'Sí':'No'} (solo lectura)`;
    status.textContent=review.conflict?'La suscripción real cambió. Escenario sin aplicar; selecciona Real para restablecer.':'';
  }
  async function request(method='GET',plan) {
    const response=await fetch(endpoint,{method,credentials:'same-origin',cache:'no-store',headers:{'Origin':location.origin,'Content-Type':'application/json',...(state?{'X-CSRF-Token':state.csrf_token}:{})},...(method==='GET'?{}:{body:JSON.stringify({plan,canonical_fingerprint:state.current.local_review.canonical_fingerprint})})});
    const data=await response.json(); if (!response.ok) throw new Error(data.error || 'No disponible'); return data;
  }
  select.addEventListener('change',async()=>{
    const plan=select.value; select.disabled=true;
    try { render(await request(plan==='real'?'DELETE':'POST',plan)); window.dispatchEvent(new CustomEvent('mxmed:local-review-changed',{detail:state.current})); }
    catch(error) { status.textContent=`No se pudo aplicar: ${error.message}`; select.value=state.current.local_review.scenario; }
    finally { select.disabled=false; }
  });
  request().then(data=>{render(data);document.body.append(panel);document.body.classList.add('mxmed-local-review-enabled');}).catch(()=>{});
})();
