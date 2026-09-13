// CRD03: read-only presentation. Never writes legacy nodes, stores or profile data.
(function(){
  const text = value => typeof value === 'string' ? value.trim() : '';
  const eligible = (row, type) => row && row.credential_type === type
    && row.verification_status === 'VERIFIED' && row.lifecycle_status === 'ACTIVE';
  const node = (tag, value, className = '') => {
    const element = document.createElement(tag);
    element.textContent = value;
    element.className = className;
    return element;
  };
  function renderVerifiedModal(data, state){
    const content = document.getElementById('mxpi-verified-data-content');
    const trigger = document.getElementById('mxpi-verified-data-trigger');
    if(!content || !trigger) return;
    content.replaceChildren();
    trigger.hidden = !data;
    if(!data){
      content.append(node('p', state === 'unavailable' ? 'No se pudieron cargar los datos verificados.' : 'Cargando datos verificados…'));
      return;
    }
    const identity = text(data.verified_identity?.full_name);
    const canonical = data.verified_credentials || {};
    const professional = eligible(canonical.professional, 'PROFESSIONAL') ? canonical.professional : null;
    const specialties = Array.isArray(canonical.specialties) ? canonical.specialties.filter(row => eligible(row, 'SPECIALTY')) : [];
    const identitySection = node('section', '');
    identitySection.dataset.verifiedModalIdentity = '';
    identitySection.append(node('h3', 'Identidad'));
    if(identity){
      identitySection.append(node('p', identity), node('span', '✓ Verificada', 'mx-verified-modal-status'));
    }else identitySection.append(node('p', 'No hay identidad verificada disponible.', 'mx-verified-modal-note'));
    content.append(identitySection);
    const section = node('section', '');
    const credential = (row, verified) => {
      const article = node('article', '', 'mx-verified-modal-credential');
      article.dataset.kind = row.credential_type === 'PROFESSIONAL' ? 'professional' : 'specialty';
      article.dataset.verified = String(verified);
      const title = text(row.professional_area_label);
      if(title) article.append(node('strong', title));
      const license = text(row.license_number);
      if(license) article.append(node('p', (row.credential_type === 'PROFESSIONAL' ? 'Cédula profesional: ' : 'Cédula de especialidad: ') + license));
      const institution = text(row.institution_name);
      if(institution) article.append(node('p', institution));
      if(verified) article.append(node('span', '✓ Verificada', 'mx-verified-modal-status'));
      section.append(article);
    };
    if(professional || specialties.length){
      section.dataset.credentialMode = 'canonical';
      section.append(node('h3', 'Credenciales profesionales'));
      if(professional) credential(professional, true);
      specialties.forEach(row => credential(row, true));
    }else{
      section.dataset.credentialMode = 'legacy';
      section.append(node('h3', 'Credenciales registradas'));
      const legacy = data.identity_public || {};
      if(text(legacy.professional_license)) credential({credential_type:'PROFESSIONAL',license_number:legacy.professional_license}, false);
      if(text(legacy.specialty_license)) credential({credential_type:'SPECIALTY',license_number:legacy.specialty_license}, false);
      if(section.querySelector('article')) section.append(node('p', 'Estas credenciales aún no cuentan con la nueva validación estructurada.', 'mx-verified-modal-note'));
      else section.append(node('p', 'No hay credenciales registradas.', 'mx-verified-modal-note'));
    }
    content.append(section);
  }
  window.mxmedRenderCredentials = function(data, state = 'loading'){
    renderVerifiedModal(data, state);
    const host = document.getElementById('mx-credential-list');
    const heading = document.getElementById('mx-credential-heading');
    if(!host || !heading) return;
    host.replaceChildren();
    heading.textContent = 'Credenciales profesionales';
    host.dataset.mode = data ? 'legacy' : state;
    const empty = message => {
      const p = document.createElement('p');
      p.className = 'mx-credential-empty';
      p.textContent = message;
      host.append(p);
    };
    if(!data){
      empty(state === 'unavailable' ? 'No se pudo cargar la información profesional.' : 'Cargando información profesional…');
      return;
    }
    const canonical = data.verified_credentials || {};
    const professional = eligible(canonical.professional, 'PROFESSIONAL') ? canonical.professional : null;
    const specialties = Array.isArray(canonical.specialties)
      ? canonical.specialties.filter(row => eligible(row, 'SPECIALTY')) : [];
    const row = (title, institution, license, verified, primary = false) => {
      const item = document.createElement('article');
      item.className = 'mx-credential-row';
      const top = document.createElement('div');
      top.className = 'mx-credential-row-head';
      const name = document.createElement('h3');
      name.textContent = title;
      top.append(name);
      if(primary){
        const badge = document.createElement('span');
        badge.className = 'mx-credential-primary';
        badge.textContent = 'Principal';
        top.append(badge);
      }
      if(verified){
        const badge = document.createElement('span');
        badge.className = 'mx-credential-verified';
        badge.textContent = '✓ Verificada';
        top.append(badge);
      }
      const detail = document.createElement('p');
      detail.textContent = [institution, license ? `Cédula ${license}` : ''].filter(Boolean).join(' · ');
      item.append(top, detail);
      host.append(item);
      return item;
    };
    if(professional || specialties.length){
      host.dataset.mode = 'canonical';
      if(professional){
        row(text(professional.professional_area_label), text(professional.institution_name), text(professional.license_number), true).dataset.kind = 'professional';
      }
      // A single current primary marker; no physician selection or persistence.
      const primaryId = data.primary_specialty_credential_id == null ? null : String(data.primary_specialty_credential_id);
      const primary = primaryId !== null
        ? specialties.find(item => String(item.credential_id) === primaryId)
        : specialties.find(item => item.is_primary === true);
      specialties.forEach(item => {
        row(text(item.professional_area_label), text(item.institution_name), text(item.license_number), true, item === primary).dataset.kind = 'specialty';
      });
      return;
    }
    heading.textContent = 'Información profesional registrada';
    const legacy = data.identity_public || {};
    if(text(legacy.professional_license)) row('Cédula profesional', '', text(legacy.professional_license), false);
    if(text(legacy.specialty_primary) || text(legacy.specialty_license)){
      row(text(legacy.specialty_primary) || 'Especialidad registrada', '', text(legacy.specialty_license), false);
    }
    if(!host.children.length) empty('No hay información profesional registrada.');
  };
})();
