// ===== Datos Personales: especialidades y validaciones =====
(function(){
  const T = [
    'Alergología','Análisis Clínicos','Anestesiología','Angiología y Cirugía Vascular','Audiología','Cardiología','Cirugía Bariátrica','Cirugía Cabeza y Cuello','Cirugía Cardiovascular','Cirugía de Columna','Cirugía de Mano','Cirugía de Pie','Cirugía Gastrointestinal','Cirugía General','Cirugía Laparoscópica','Cirugía Maxilofacial','Cirugía Oncológica Pediátrica','Cirugía Pediátrica','Cirugía Plástica','Cirugía Torácica','Coloproctología','Colposcopía','Cuidados Paliativos','Dentista','Dermatología','Diabetología','Endocrinología','Endodoncia','Estudios de Diagnóstico','Gastroenterología','Geriatría','Ginecología y Obstetricia','Hematología','Implantología Dental','Kinesiología','Medicina Crítica','Medicina del Trabajo','Medicina Estética','Medicina Familiar','Medicina Física y Rehabilitación','Medicina General','Medicina Integrada','Medicina Interna','Medicina Nuclear','Nefrología','Nefrología Pediátrica','Neumología','Neumología Pediátrica','Neurocirugía','Neurología','Neurología Pediátrica','Nutriología','Odontología','Odontopediatría','Oftalmología','Oncología','Optometría','Ortodoncia','Ortopedia Dental','Ortopedia y Traumatología','Otorrinolaringología','Patología','Pediatría','Podología','Proctología','Psicología','Psiquiatría','Radiología e Imagen','Reumatología','Urología','Otra (especificar)'
  ];

  function buildSelect(el){
    el.innerHTML = '';
    const optEmpty = document.createElement('option'); optEmpty.value=''; optEmpty.textContent='—'; el.appendChild(optEmpty);
    for(const t of T){ const o=document.createElement('option'); o.value=t; o.textContent=t; el.appendChild(o); }
  }

  function syncDuplicates(){
    const selects = Array.from(document.querySelectorAll('.esp-select'));
    const vals = selects.map(s=>s.value).filter(v=>v && !v.startsWith('Otra'));
    selects.forEach(s=>{
      // Resalte visual en el select que tiene valor
      if(s.value){ s.classList.add('picked'); } else { s.classList.remove('picked'); }
      Array.from(s.options).forEach(o=>{
        if(!o.value || o.value.startsWith('Otra')){ o.disabled=false; o.classList.remove('taken'); return; }
        const isTaken = vals.includes(o.value);
        // La opción tomada se marca en todas las persianas
        o.classList.toggle('taken', isTaken);
        // Deshabilitar en persianas distintas a la que la tiene seleccionada
        o.disabled = isTaken && s.value !== o.value;
      });
    });
  }

  function toggleOtra(){
    const wrap = document.getElementById('esp-otra-wrap');
    const s3 = document.getElementById('esp-3');
    if(!wrap || !s3) return;
    if(s3.value && s3.value.startsWith('Otra')) wrap.classList.remove('d-none');
    else wrap.classList.add('d-none');
  }

  // Insertar selects si existen anclas de correo en la vista de Datos
  const correo = document.getElementById('dp-correo');
  const row = correo?.closest('.row');
  const hasCredentialFieldsInMarkup = !!(
    document.querySelector('#p-info #t-info-datos #esp-1')
    && document.querySelector('#p-info #t-info-datos #esp-2')
    && document.querySelector('#p-info #t-info-datos #esp-3')
  );
  if(row && !hasCredentialFieldsInMarkup){
    ['esp-1','esp-2','esp-3'].forEach(id=>{
      const col = document.createElement('div'); col.className='col-md-4';
      const lab = document.createElement('label'); lab.className='form-label';
      lab.textContent = id==='esp-1' ? 'Especialidad Principal' : id==='esp-2' ? 'Especialidad Secundaria' : 'Otra Especialidad';
      const sel = document.createElement('select'); sel.className='form-select esp-select'; sel.id=id;
      col.appendChild(lab); col.appendChild(sel);
      row.insertBefore(col, correo.closest('.col-md-6'));
      buildSelect(sel);
      sel.addEventListener('change', ()=>{ syncDuplicates(); toggleOtra(); });
    });
    const wrap = document.createElement('div'); wrap.className='col-md-12 d-none'; wrap.id='esp-otra-wrap';
    const lab = document.createElement('label'); lab.className='form-label'; lab.textContent='Especifica otra especialidad';
    const inp = document.createElement('input'); inp.className='form-control'; inp.id='esp-otra'; inp.placeholder='Escribe la especialidad';
    wrap.appendChild(lab); wrap.appendChild(inp);
    row.insertBefore(wrap, correo.closest('.col-md-6'));
    syncDuplicates(); toggleOtra();
  }

  // Género: reemplaza "Otro" por "No Específico"
  const gen = document.getElementById('dp-genero');
  if(gen){ Array.from(gen.options).forEach(o=>{ if(/^otro$/i.test(o.textContent.trim())) o.textContent='No Específico'; }); }

  // Remueve campos no requeridos si quedaron (por contenido de etiqueta)
  function removeByLabel(text){
    document.querySelectorAll('.row .form-label').forEach(l=>{
      if(l.textContent && l.textContent.indexOf(text) >= 0){ const col = l.closest('[class^="col-"]'); col?.remove(); }
    });
  }
  ['Domicilio','Ciudad','País','Foto/Avatar','URL sitio personal'].forEach(removeByLabel);

  // Envolver WhatsApp con prefijo 🇲🇽 +52
  const w = document.getElementById('dp-whatsapp');
  if(w && !w.closest('.input-group')){
    const wrap = document.createElement('div'); wrap.className='input-group';
    const span = document.createElement('span'); span.className='input-group-text'; span.textContent='🇲🇽 +52';
    const col = w.closest('[class^="col-"]');
    col.replaceChildren();
    const lab = document.createElement('label'); lab.className='form-label'; lab.textContent='Teléfono Whatsapp';
    col.appendChild(lab);
    col.appendChild(wrap);
    wrap.appendChild(span);
    w.placeholder='10 dígitos'; w.maxLength=14; wrap.appendChild(w);
  }

  // Validación de correo y teléfono (básica) + tooltips
  const email = document.getElementById('dp-correo');
  // No usamos tooltip Bootstrap; renderizamos una burbuja propia dentro de save-wrap

  function setErrorTooltip(el, msg, isError){
    const col = el.closest('.save-wrap') || ensureSaveMark(el);
    if(!col) return;
    let bub = col.querySelector(':scope > .err-bubble');
    if(isError){
      el.classList.add('is-invalid');
      el.classList.remove('is-valid');
      col.classList.add('has-error');
      if(!bub){ bub = document.createElement('div'); bub.className='err-bubble'; col.appendChild(bub); }
      bub.textContent = msg;
    } else {
      el.classList.remove('is-invalid');
      el.classList.remove('is-valid');
      col.classList.remove('has-error');
      if(bub){ bub.remove(); }
    }
  }

  if(email){ email.type='email'; email.addEventListener('blur', ()=>{
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
    setErrorTooltip(email, 'Ingresa un correo electrónico válido', (!!email.value && !ok));
  }); }
  if(w){ w.addEventListener('input', ()=>{
    const digits = (w.value||'').replace(/\D+/g,'');
    const ok = digits.length===10 || (digits.startsWith('52') && digits.length===12);
    setErrorTooltip(w, 'Ingresa un número de teléfono válido', (!!w.value && !ok));
  }); }

  // Autosave + check verde
  function ensureSaveMark(ctrl){
    const col = ctrl.closest('[class^="col-"]') || ctrl.parentElement;
    if(!col) return null;
    col.classList.add('save-wrap');
    // Determinar host (input-group o el propio input) para posicionar dentro del campo
    let host = ctrl.closest('.input-group');
    if(!host){
      if(!ctrl.parentElement.classList.contains('save-field')){
        const field = document.createElement('div'); field.className='save-field';
        ctrl.parentElement.insertBefore(field, ctrl);
        field.appendChild(ctrl);
        host = field;
      } else { host = ctrl.parentElement; }
    } else {
      host.classList.add('save-field');
    }
    // Crear/ubicar marca dentro del host
    let mark = host.querySelector(':scope > .save-ok');
    if(!mark){
      mark = document.createElement('span');
      mark.className = 'save-ok';
      mark.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">check_small</span>';
      host.appendChild(mark);
    }
    return col;
  }

  function initAutosave(){
    const expedienteRoot = document.getElementById('p-expediente');
    let creatingPatientPromise = null;
    let explicitSaveCompleted = false;
    const savePatientBtn = document.getElementById('dg-save-patient');
    const savePatientFeedback = document.getElementById('dg-save-feedback');
    const normalizeFieldLabel = (value)=> String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, ' ')
      .trim()
      .toLowerCase();

    const readFieldLabelText = (ctrl)=>{
      if(!ctrl) return '';
      const chunks = [];
      const id = String(ctrl.id || '').trim();
      if(id){
        try{
          const escaped = (window.CSS && typeof window.CSS.escape === 'function') ? window.CSS.escape(id) : id.replace(/"/g, '\\"');
          const byFor = document.querySelector(`label[for="${escaped}"]`);
          if(byFor) chunks.push(byFor.textContent || '');
        }catch(_){}
      }
      const wrappingLabel = ctrl.closest('label');
      if(wrappingLabel) chunks.push(wrappingLabel.textContent || '');
      const fieldWrap = ctrl.closest('[class^="col-"], [class*=" col-"], .form-group, .mb-3, .dg-form > div');
      const label = fieldWrap?.querySelector?.(':scope > label, :scope > .form-label');
      if(label) chunks.push(label.textContent || '');
      return chunks.join(' ');
    };

    const isHumanNameFieldForNativeTextAssist = (ctrl)=>{
      if(!expedienteRoot || !ctrl || !expedienteRoot.contains(ctrl)) return false;
      if(ctrl.disabled || ctrl.readOnly) return false;
      const tag = String(ctrl.tagName || '').toLowerCase();
      if(tag !== 'input') return false;
      const type = String(ctrl.getAttribute('type') || ctrl.type || 'text').toLowerCase();
      if(type && type !== 'text') return false;
      if(ctrl.matches('[data-pac-nombre], [data-pac-apellido-paterno], [data-pac-apellido-materno]')) return true;
      return normalizeFieldLabel(readFieldLabelText(ctrl)) === 'nombre de la persona de contacto';
    };

    const getActivePatientId = ()=>{
      if(typeof window.resolveActivePatientId === 'function'){
        const resolved = String(window.resolveActivePatientId() || '').trim();
        if(resolved) return resolved;
      }
      if(!expedienteRoot) return '';
      const fromData = String(expedienteRoot.dataset?.activePatientId || expedienteRoot.dataset?.patientId || '').trim();
      if(fromData) return fromData;
      const fromAttr = String(expedienteRoot.getAttribute('data-active-patient-id') || expedienteRoot.getAttribute('data-patient-id') || '').trim();
      if(fromAttr) return fromAttr;
      const fromGlobal = String(window.mxmedActivePatientId || window.__MXMED_ACTIVE_PATIENT_ID || (window.mxmedStore && window.mxmedStore.activePatientId) || '').trim();
      return fromGlobal;
    };

    const setActivePatientId = (patientId, opts = {})=>{
      const pid = String(patientId || '').trim();
      if(!pid) return;
      if(typeof window.setActivePatientId === 'function'){
        return window.setActivePatientId(pid, {
          emitEvent: true,
          skipUnsavedNewPatientConfirm: true,
          ...opts
        });
      }
      if(!expedienteRoot) return;
      expedienteRoot.dataset.activePatientId = pid;
      expedienteRoot.dataset.patientId = pid;
      expedienteRoot.setAttribute('data-active-patient-id', pid);
      expedienteRoot.setAttribute('data-patient-id', pid);
      window.mxmedActivePatientId = pid;
      window.__MXMED_ACTIVE_PATIENT_ID = pid;
      if(window.mxmedStore && typeof window.mxmedStore === 'object'){
        window.mxmedStore.activePatientId = pid;
      }
      window.dispatchEvent(new Event('patient:selected'));
      return true;
    };

    const buildCreatePayload = ()=>{
      if(!expedienteRoot) return null;
      const first = (expedienteRoot.querySelector('[data-pac-nombre]')?.value || '').trim();
      const apPat = (expedienteRoot.querySelector('[data-pac-apellido-paterno]')?.value || '').trim();
      const apMat = (expedienteRoot.querySelector('[data-pac-apellido-materno]')?.value || '').trim();
      const displayName = [first, apPat, apMat].filter(Boolean).join(' ').trim();
      if(!displayName) return null;

      const dd = (expedienteRoot.querySelector('[data-dg-dia]')?.value || '').trim();
      const mm = (expedienteRoot.querySelector('[data-dg-mes]')?.value || '').trim();
      const yy = (expedienteRoot.querySelector('[data-dg-anio]')?.value || '').trim();
      const sex = (expedienteRoot.querySelector('input[name="pac-genero"]:checked')?.value || '').trim();
      const birthdate = (yy && mm && dd) ? `${yy}-${mm}-${dd}` : null;
      const doctorId = String(
        (typeof window.resolveDoctorId === 'function' ? window.resolveDoctorId() : '') ||
        document.body?.dataset?.doctorId ||
        ''
      ).trim();

      const payload = {
        display_name: displayName,
        birthdate: birthdate || undefined,
        sex: sex || undefined
      };
      if(doctorId){
        payload.doctor_id = doctorId;
      }
      return payload;
    };

    const getPatientMobilePhoneControls = ()=>{
      if(!expedienteRoot) return null;
      const input = expedienteRoot.querySelector('[data-pac-phone="mobile"]');
      if(!input) return null;
      const phoneField = input.closest('.mx-phone-field');
      const countrySelect = phoneField?.querySelector?.('.mx-phone-country') || null;
      const fieldWrap = input.closest('[class^="col-"], [class*=" col-"], .form-group, .mb-3') || phoneField || input.parentElement;
      return { input, countrySelect, fieldWrap };
    };

    const readPatientMobilePhoneFromDom = ()=>{
      const controls = getPatientMobilePhoneControls();
      if(!controls) return null;
      const { input, countrySelect } = controls;
      let country = String(countrySelect?.value || input.dataset.phoneCountry || 'MX').trim().toUpperCase();
      let dialCode = String(countrySelect?.selectedOptions?.[0]?.dataset?.dial || input.dataset.phoneDialCode || '').replace(/\D+/g, '');
      const nationalDigits = String(input.value || '').replace(/\D+/g, '');
      if(!country){
        country = 'MX';
      }
      if(!dialCode && country === 'MX'){
        dialCode = '52';
      }
      return {
        country,
        dialCode,
        nationalDigits,
        value: dialCode ? `+${dialCode}${nationalDigits}` : nationalDigits,
        input,
        countrySelect: countrySelect || null
      };
    };

    const ensurePatientMobilePhoneFeedback = ()=>{
      const controls = getPatientMobilePhoneControls();
      if(!controls?.fieldWrap) return null;
      let feedback = controls.fieldWrap.querySelector(':scope > [data-pac-phone-mobile-feedback]');
      if(!feedback){
        feedback = document.createElement('div');
        feedback.className = 'form-text text-danger d-none';
        feedback.setAttribute('data-pac-phone-mobile-feedback', '');
        feedback.setAttribute('aria-live', 'polite');
        controls.fieldWrap.appendChild(feedback);
      }
      return feedback;
    };

    const setPatientMobilePhoneFeedback = (message = '')=>{
      const controls = getPatientMobilePhoneControls();
      const feedback = ensurePatientMobilePhoneFeedback();
      const msg = String(message || '').trim();
      if(controls?.input){
        controls.input.classList.toggle('is-invalid', !!msg);
        if(msg){
          controls.input.setAttribute('aria-invalid', 'true');
        }else{
          controls.input.removeAttribute('aria-invalid');
        }
      }
      if(!feedback) return;
      feedback.textContent = msg;
      feedback.classList.toggle('d-none', !msg);
    };

    const validateRequiredPatientMobilePhone = ()=>{
      const phone = readPatientMobilePhoneFromDom();
      if(!phone?.input){
        return { valid: false, message: 'Captura el teléfono celular del paciente.', phone };
      }
      if(!phone.nationalDigits){
        return { valid: false, message: 'Captura el teléfono celular del paciente.', phone };
      }
      if(phone.country === 'MX' || phone.dialCode === '52'){
        if(phone.nationalDigits.length !== 10){
          return { valid: false, message: 'El teléfono celular debe tener 10 dígitos nacionales.', phone };
        }
        return { valid: true, message: '', phone: { ...phone, country: 'MX', dialCode: '52', value: `+52${phone.nationalDigits}` } };
      }
      if(!phone.dialCode){
        return { valid: false, message: 'Selecciona la lada del teléfono celular.', phone };
      }
      if(phone.nationalDigits.length < 7 || phone.nationalDigits.length > 15){
        return { valid: false, message: 'El teléfono celular debe tener entre 7 y 15 dígitos.', phone };
      }
      return { valid: true, message: '', phone };
    };

    const appendPatientMobileContactToPayload = (payload, phone)=>{
      if(!payload || !phone?.value) return payload;
      const contacts = Array.isArray(payload.contacts) ? payload.contacts.slice() : [];
      contacts.push({
        type: 'phone',
        value: phone.value,
        is_primary: true,
        preferred_contact_method: 'phone'
      });
      payload.contacts = contacts;
      return payload;
    };

    const resolveDatosGeneralesDoctorId = ()=>{
      return String(
        (typeof window.resolveDoctorId === 'function' ? window.resolveDoctorId() : '') ||
        document.body?.dataset?.doctorId ||
        ''
      ).trim();
    };

    const getProfileFields = ()=>{
      if(!expedienteRoot) return null;
      return {
        firstName: expedienteRoot.querySelector('[data-pac-nombre]'),
        paternalLastName: expedienteRoot.querySelector('[data-pac-apellido-paterno]'),
        maternalLastName: expedienteRoot.querySelector('[data-pac-apellido-materno]'),
        maritalStatus: expedienteRoot.querySelector('[data-pac-profile-marital-status]'),
        occupation: expedienteRoot.querySelector('[data-pac-profile-occupation]')
      };
    };

    const cleanProfileValue = (value)=> String(value || '').replace(/\s+/g, ' ').trim();
    let lastHydratedProfileSnapshot = '';

    const normalizeProfilePayload = (profile)=>{
      return {
        first_name: cleanProfileValue(profile?.first_name || ''),
        paternal_last_name: cleanProfileValue(profile?.paternal_last_name || ''),
        maternal_last_name: cleanProfileValue(profile?.maternal_last_name || ''),
        marital_status: cleanProfileValue(profile?.marital_status || ''),
        occupation: cleanProfileValue(profile?.occupation || '')
      };
    };

    const serializeProfileSnapshot = (profile)=> JSON.stringify(normalizeProfilePayload(profile));

    const readPatientProfileFromDom = ()=>{
      const fields = getProfileFields();
      if(!fields) return null;
      const readProfileValue = (field)=>{
        const value = cleanProfileValue(field?.value || '');
        if(
          field?.dataset?.mxmedDisplayNameFallback === '1'
          && value === cleanProfileValue(field.dataset.mxmedDisplayNameFallbackValue || '')
        ){
          return '';
        }
        return value;
      };
      return {
        first_name: readProfileValue(fields.firstName),
        paternal_last_name: cleanProfileValue(fields.paternalLastName?.value || ''),
        maternal_last_name: cleanProfileValue(fields.maternalLastName?.value || ''),
        marital_status: cleanProfileValue(fields.maritalStatus?.value || ''),
        occupation: cleanProfileValue(fields.occupation?.value || '')
      };
    };

    const rememberProfileSnapshotFromDom = ()=>{
      lastHydratedProfileSnapshot = serializeProfileSnapshot(readPatientProfileFromDom() || {});
    };

    const hasPatientProfileData = (profile)=>{
      if(!profile || typeof profile !== 'object') return false;
      return [
        profile.first_name,
        profile.paternal_last_name,
        profile.maternal_last_name,
        profile.marital_status,
        profile.occupation
      ].some((value)=> String(value || '').trim() !== '');
    };

    const savePatientProfile = async (patientId, profile, { force = false } = {})=>{
      const pid = String(patientId || '').trim();
      if(!pid) throw new Error('patient_id requerido');
      const payload = normalizeProfilePayload(profile);
      if(!force && !hasPatientProfileData(payload)) return null;
      const response = await fetch(`/api/patients/index.php/patients/${encodeURIComponent(pid)}/profile`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const json = await response.json().catch(()=> null);
      if(!response.ok || json?.ok !== true){
        throw new Error(String(json?.message || json?.error || 'No se pudo guardar el perfil.'));
      }
      return json?.data?.profile || null;
    };

    const dispatchProfileChange = (field, eventName = 'change')=>{
      if(!field) return;
      field.dispatchEvent(new Event(eventName, { bubbles: true }));
    };

    const setProfileFieldValue = (field, value, { dispatchEvents = true } = {})=>{
      if(!field) return;
      const next = String(value || '');
      if(field.value === next) return;
      field.value = next;
      if(dispatchEvents){
        dispatchProfileChange(field, 'input');
        dispatchProfileChange(field, 'change');
      }
    };

    const clearDisplayNameFallback = (field)=>{
      if(!field?.dataset) return;
      delete field.dataset.mxmedDisplayNameFallback;
      delete field.dataset.mxmedDisplayNameFallbackValue;
    };

    const markDisplayNameFallback = (field, displayName)=>{
      if(!field?.dataset) return;
      const value = cleanProfileValue(displayName || '');
      if(!value){
        clearDisplayNameFallback(field);
        return;
      }
      field.dataset.mxmedDisplayNameFallback = '1';
      field.dataset.mxmedDisplayNameFallbackValue = value;
    };

    const hasProfileIdentityData = (profile)=>{
      if(!profile || typeof profile !== 'object') return false;
      return [
        profile.first_name,
        profile.paternal_last_name,
        profile.maternal_last_name
      ].some((value)=> String(value || '').trim() !== '');
    };

    const parsePatientBirthdate = (birthdate)=>{
      const raw = String(birthdate || '').trim();
      const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if(!match) return { year:'', month:'', day:'' };
      return { year: match[1], month: match[2], day: match[3] };
    };

    const normalizePatientSexValue = (value)=>{
      const upper = String(value || '').trim().toUpperCase();
      if(['M', 'MASCULINO', 'HOMBRE', 'MALE'].includes(upper)) return 'M';
      if(['F', 'FEMENINO', 'MUJER', 'FEMALE'].includes(upper)) return 'F';
      if(['O', 'OTRO', 'OTRA', 'NO ESPECIFICADO', 'NO ESPECIFICADA', 'OTHER'].includes(upper)) return 'O';
      return '';
    };

    const setPrimaryFieldValue = (field, value, { dispatchEvents = true } = {})=>{
      if(!field) return;
      const next = String(value || '');
      if(field.value === next) return;
      field.value = next;
      if(dispatchEvents){
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
      }
    };

    const normalizeMxPhoneNationalDigits = (value)=>{
      const digits = String(value || '').replace(/\D+/g, '');
      if(digits.length === 12 && digits.startsWith('52')) return digits.slice(2);
      if(digits.length === 10) return digits;
      return '';
    };

    const normalizeMxPhoneStorageValue = (value)=>{
      const national = normalizeMxPhoneNationalDigits(value);
      return national ? `+52${national}` : '';
    };

    const serializeEditableMobilePhoneSnapshot = (value)=> JSON.stringify({
      value: normalizeMxPhoneStorageValue(value)
    });

    let lastHydratedEditableMobilePhoneSnapshot = serializeEditableMobilePhoneSnapshot('');
    let editableContactsHydrationToken = 0;
    let editableContactsHydrationInFlightPatientId = '';
    let editableContactsHydrationInFlightPromise = null;
    let lastEditableContactsHydratedPatientId = '';
    let lastEditableContactsHydratedAt = 0;

    const resetEditableContactsHydrationCache = ()=>{
      editableContactsHydrationInFlightPatientId = '';
      editableContactsHydrationInFlightPromise = null;
      lastEditableContactsHydratedPatientId = '';
      lastEditableContactsHydratedAt = 0;
    };

    const setMobilePhoneCountryMx = (controls)=>{
      const { input, countrySelect } = controls || {};
      if(countrySelect && countrySelect.value !== 'MX'){
        countrySelect.value = 'MX';
        countrySelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if(input?.dataset){
        input.dataset.phoneCountry = 'MX';
        input.dataset.phoneDialCode = '52';
      }
    };

    const rememberEditableMobilePhoneSnapshotFromDom = ()=>{
      const phone = readPatientMobilePhoneFromDom();
      const value = phone?.nationalDigits ? `+52${phone.nationalDigits}` : '';
      lastHydratedEditableMobilePhoneSnapshot = serializeEditableMobilePhoneSnapshot(value);
    };

    const hydrateEditableMobilePhoneIntoDom = (contacts)=>{
      const controls = getPatientMobilePhoneControls();
      if(!controls?.input) return false;
      const list = Array.isArray(contacts) ? contacts : [];
      const phones = list.filter((contact)=> String(contact?.type || '').trim() === 'phone');
      const primaryPhone = phones.find((contact)=> contact?.is_primary === true) || phones[0] || null;
      const rawValue = primaryPhone?.value || primaryPhone?.phone || '';
      const nationalDigits = normalizeMxPhoneNationalDigits(rawValue);
      setMobilePhoneCountryMx(controls);
      setPrimaryFieldValue(controls.input, nationalDigits, { dispatchEvents: true });
      setPatientMobilePhoneFeedback('');
      lastHydratedEditableMobilePhoneSnapshot = serializeEditableMobilePhoneSnapshot(rawValue);
      return true;
    };

    const buildEditablePatientContactsUrl = (patientId)=>{
      const doctorId = resolveDatosGeneralesDoctorId();
      const pid = String(patientId || '').trim();
      if(!doctorId || !pid) return '';
      return `/api/patients/index.php/doctors/${encodeURIComponent(doctorId)}/patients/${encodeURIComponent(pid)}/contacts/editable`;
    };

    const fetchEditablePatientContacts = async (patientId)=>{
      const url = buildEditablePatientContactsUrl(patientId);
      if(!url) return null;
      const response = await fetch(url, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      const json = await response.json().catch(()=> null);
      if(!response.ok || json?.ok !== true){
        throw new Error(String(json?.message || json?.error || 'No se pudieron cargar contactos editables.'));
      }
      return Array.isArray(json?.data?.contacts) ? json.data.contacts : [];
    };

    const fetchAndHydrateEditablePatientContacts = async (patientId, shouldApply = ()=> true)=>{
      const pid = String(patientId || '').trim();
      if(!pid) return false;
      let contacts = null;
      try{
        contacts = await fetchEditablePatientContacts(pid);
      }catch(err){
        console.warn('[DG-CONTACTS-EDITABLE-GET] request_error', {
          patient_id: pid,
          message: String(err?.message || '').trim()
        });
        contacts = [];
      }
      if(shouldApply() !== true) return false;
      return hydrateEditableMobilePhoneIntoDom(contacts || []);
    };

    const hydrateEditableContactsForActivePatient = (patientId)=>{
      const pid = String(patientId || '').trim();
      if(!pid || isInNewEntryMode()) return Promise.resolve(false);
      const now = Date.now();
      if(lastEditableContactsHydratedPatientId === pid && now - lastEditableContactsHydratedAt < 300){
        return Promise.resolve(false);
      }
      if(editableContactsHydrationInFlightPatientId === pid && editableContactsHydrationInFlightPromise){
        return editableContactsHydrationInFlightPromise;
      }
      const token = ++editableContactsHydrationToken;
      editableContactsHydrationInFlightPatientId = pid;
      editableContactsHydrationInFlightPromise = fetchAndHydrateEditablePatientContacts(pid, ()=>{
        return token === editableContactsHydrationToken
          && String(getActivePatientId() || '').trim() === pid
          && !isInNewEntryMode();
      })
        .then((applied)=>{
          if(applied === true){
            lastEditableContactsHydratedPatientId = pid;
            lastEditableContactsHydratedAt = Date.now();
          }
          return applied;
        })
        .finally(()=>{
          if(editableContactsHydrationInFlightPatientId === pid){
            editableContactsHydrationInFlightPatientId = '';
            editableContactsHydrationInFlightPromise = null;
          }
        });
      return editableContactsHydrationInFlightPromise;
    };

    const readEditableMobilePhoneForSave = ()=>{
      const phone = readPatientMobilePhoneFromDom();
      if(!phone?.input) return { hasValue: false, valid: true, value: '', snapshot: serializeEditableMobilePhoneSnapshot('') };
      if(!phone.nationalDigits){
        return { hasValue: false, valid: true, value: '', snapshot: serializeEditableMobilePhoneSnapshot('') };
      }
      if(phone.country !== 'MX' && phone.dialCode !== '52'){
        return {
          hasValue: true,
          valid: false,
          message: 'Por ahora el teléfono editable debe ser de México (+52).',
          value: '',
          snapshot: serializeEditableMobilePhoneSnapshot('')
        };
      }
      if(phone.nationalDigits.length !== 10){
        return {
          hasValue: true,
          valid: false,
          message: 'El teléfono celular debe tener 10 dígitos nacionales.',
          value: '',
          snapshot: serializeEditableMobilePhoneSnapshot('')
        };
      }
      const value = `+52${phone.nationalDigits}`;
      return {
        hasValue: true,
        valid: true,
        value,
        snapshot: serializeEditableMobilePhoneSnapshot(value)
      };
    };

    const saveEditableMobilePhone = async (patientId, mobilePhone)=>{
      const url = buildEditablePatientContactsUrl(patientId);
      if(!url) throw new Error('No se pudo identificar al médico para guardar el teléfono.');
      const payload = {
        contacts: [{
          type: 'phone',
          value: mobilePhone.value,
          is_primary: true,
          preferred_contact_method: 'phone'
        }]
      };
      const response = await fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const json = await response.json().catch(()=> null);
      if(!response.ok || json?.ok !== true){
        throw new Error(String(json?.message || json?.error || 'No se pudo guardar el teléfono.'));
      }
      return Array.isArray(json?.data?.contacts) ? json.data.contacts : [];
    };

    const setPatientGenderValue = (sex)=>{
      const normalized = normalizePatientSexValue(sex);
      const genderInputs = Array.from(expedienteRoot?.querySelectorAll('input[name="pac-genero"]') || []);
      let changed = false;
      let selected = null;
      genderInputs.forEach((input)=>{
        const shouldCheck = !!normalized && input.value === normalized;
        if(input.checked !== shouldCheck){
          input.checked = shouldCheck;
          changed = true;
        }
        if(shouldCheck) selected = input;
      });
      if(changed && selected){
        selected.dispatchEvent(new Event('change', { bubbles: true }));
      }else if(changed){
        const previouslyChecked = genderInputs.find((input)=> input.checked);
        if(previouslyChecked){
          previouslyChecked.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    };

    const getContactFields = ()=>{
      if(!expedienteRoot) return null;
      return {
        mobilePhone: expedienteRoot.querySelector('[data-pac-phone="mobile"]'),
        homePhone: expedienteRoot.querySelector('[data-pac-phone="home"]'),
        contactPhone: expedienteRoot.querySelector('[data-pac-phone="contact"]'),
        primaryEmail: expedienteRoot.querySelector('[data-pac-email="primary"]'),
        alternateEmail: expedienteRoot.querySelector('[data-pac-email="alternate"]')
      };
    };

    const readRawContactValue = (contact)=>{
      if(!contact || typeof contact !== 'object') return '';
      return cleanProfileValue(contact.value || contact.phone || contact.email || contact.contact_value || '');
    };

    const hydratePatientContactsIntoDom = (contacts, options = {})=>{
      const fields = getContactFields();
      if(!fields) return false;
      const preserveMobilePhone = options?.preserveMobilePhone === true;
      [
        preserveMobilePhone ? null : fields.mobilePhone,
        fields.homePhone,
        fields.contactPhone,
        fields.primaryEmail,
        fields.alternateEmail
      ].forEach((field)=>{
        setPrimaryFieldValue(field, '', { dispatchEvents: false });
      });
      const list = Array.isArray(contacts) ? contacts : [];
      const phoneContacts = list.filter((contact)=> String(contact?.type || '').trim() === 'phone');
      const emailContacts = list.filter((contact)=> String(contact?.type || '').trim() === 'email');
      const primaryPhone = phoneContacts.find((contact)=> contact?.is_primary === true) || phoneContacts[0] || null;
      const secondaryPhone = phoneContacts.find((contact)=> contact !== primaryPhone) || null;
      const primaryEmail = emailContacts.find((contact)=> contact?.is_primary === true) || emailContacts[0] || null;
      const alternateEmail = emailContacts.find((contact)=> contact !== primaryEmail) || null;
      const primaryPhoneValue = readRawContactValue(primaryPhone);
      const secondaryPhoneValue = readRawContactValue(secondaryPhone);
      const primaryEmailValue = readRawContactValue(primaryEmail);
      const alternateEmailValue = readRawContactValue(alternateEmail);
      if(!preserveMobilePhone && primaryPhoneValue) setPrimaryFieldValue(fields.mobilePhone, primaryPhoneValue, { dispatchEvents: false });
      if(secondaryPhoneValue) setPrimaryFieldValue(fields.homePhone, secondaryPhoneValue, { dispatchEvents: false });
      if(primaryEmailValue) setPrimaryFieldValue(fields.primaryEmail, primaryEmailValue, { dispatchEvents: false });
      if(alternateEmailValue) setPrimaryFieldValue(fields.alternateEmail, alternateEmailValue, { dispatchEvents: false });
      return true;
    };

    const hydratePatientIdentityAndProfileIntoDom = (patient)=>{
      const fields = getProfileFields();
      if(!fields || !patient || typeof patient !== 'object') return false;
      const profile = (patient.profile && typeof patient.profile === 'object') ? patient.profile : null;
      const useProfileIdentity = hasProfileIdentityData(profile);
      const firstName = useProfileIdentity ? cleanProfileValue(profile.first_name || '') : '';
      const paternalLastName = useProfileIdentity ? cleanProfileValue(profile.paternal_last_name || '') : '';
      const maternalLastName = useProfileIdentity ? cleanProfileValue(profile.maternal_last_name || '') : '';
      setProfileFieldValue(fields.firstName, firstName, { dispatchEvents: true });
      clearDisplayNameFallback(fields.firstName);
      setProfileFieldValue(fields.paternalLastName, paternalLastName, { dispatchEvents: true });
      setProfileFieldValue(fields.maternalLastName, maternalLastName, { dispatchEvents: true });
      setProfileFieldValue(fields.maritalStatus, profile?.marital_status || '', { dispatchEvents: false });
      setProfileFieldValue(fields.occupation, profile?.occupation || '', { dispatchEvents: false });

      const birth = parsePatientBirthdate(patient.birthdate);
      setPrimaryFieldValue(expedienteRoot.querySelector('[data-dg-dia]'), birth.day, { dispatchEvents: true });
      setPrimaryFieldValue(expedienteRoot.querySelector('[data-dg-mes]'), birth.month, { dispatchEvents: true });
      setPrimaryFieldValue(expedienteRoot.querySelector('[data-dg-anio]'), birth.year, { dispatchEvents: true });
      setPatientGenderValue(patient.sex || patient.gender || '');
      hydratePatientContactsIntoDom(patient.contacts || [], { preserveMobilePhone: true });
      rememberProfileSnapshotFromDom();
      return true;
    };

    const hydratePatientDetailIntoDatosGenerales = (patient)=>{
      if(!patient || typeof patient !== 'object') return false;
      const hydratedProfile = hydratePatientIdentityAndProfileIntoDom(patient);
      const addresses = Array.isArray(patient.addresses) ? patient.addresses : [];
      const primaryAddress = addresses.find((entry)=> entry?.is_primary === true) || addresses[0] || null;
      const hydratedAddress = primaryAddress
        ? hydratePatientAddressIntoDom(primaryAddress)
        : (clearPatientAddressFields(), false);
      const patientId = String(patient.patient_id || '').trim();
      if(patientId && String(getActivePatientId() || '').trim() === patientId && !isInNewEntryMode()){
        hydrateEditableContactsForActivePatient(patientId).catch(()=> null);
      }
      return hydratedProfile || hydratedAddress;
    };

    const hydratePatientProfileIntoDom = (profile)=>{
      const fields = getProfileFields();
      if(!fields) return false;
      const data = (profile && typeof profile === 'object') ? profile : {};
      clearDisplayNameFallback(fields.firstName);
      setProfileFieldValue(fields.firstName, data.first_name || '', { dispatchEvents: false });
      setProfileFieldValue(fields.paternalLastName, data.paternal_last_name || '', { dispatchEvents: false });
      setProfileFieldValue(fields.maternalLastName, data.maternal_last_name || '', { dispatchEvents: false });
      setProfileFieldValue(fields.maritalStatus, data.marital_status || '', { dispatchEvents: false });
      setProfileFieldValue(fields.occupation, data.occupation || '', { dispatchEvents: false });
      rememberProfileSnapshotFromDom();
      return true;
    };

    const clearPatientProfileFields = ()=> hydratePatientProfileIntoDom(null);

    const fetchPatientDetails = async (patientId)=>{
      const pid = String(patientId || '').trim();
      if(!pid) return null;
      const response = await fetch(`/api/patients/index.php/patients/${encodeURIComponent(pid)}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      const json = await response.json().catch(()=> null);
      if(!response.ok || json?.ok !== true || !json?.data || typeof json.data !== 'object') return null;
      return json.data;
    };

    const fetchAndHydratePatientProfile = async (patientId, shouldApply = ()=> true)=>{
      const patient = await fetchPatientDetails(patientId);
      if(shouldApply() !== true) return false;
      if(!patient){
        clearPatientProfileFields();
        return false;
      }
      return hydratePatientIdentityAndProfileIntoDom(patient);
    };

    const getAddressFields = ()=>{
      if(!expedienteRoot) return null;
      return {
        cp: expedienteRoot.querySelector('[data-pac-address-cp]'),
        colony: expedienteRoot.querySelector('[data-pac-address-colony]'),
        state: expedienteRoot.querySelector('[data-pac-address-state]'),
        municipality: expedienteRoot.querySelector('[data-pac-address-municipality]'),
        locality: expedienteRoot.querySelector('[data-pac-address-locality]'),
        street: expedienteRoot.querySelector('[data-pac-address-street]'),
        exteriorNumber: expedienteRoot.querySelector('[data-pac-address-ext]'),
        interiorNumber: expedienteRoot.querySelector('[data-pac-address-int]'),
        floor: expedienteRoot.querySelector('[data-pac-address-floor]')
      };
    };

    const cleanAddressValue = (value)=> String(value || '').replace(/\s+/g, ' ').trim();
    const cleanAddressCp = (value)=> String(value || '').replace(/\D+/g, '').slice(0, 5);
    let lastHydratedAddressSnapshot = '';

    const readPatientAddressFromDom = ()=>{
      const fields = getAddressFields();
      if(!fields) return null;
      return {
        country: 'MX',
        postal_code: cleanAddressCp(fields.cp?.value || ''),
        colony: cleanAddressValue(fields.colony?.value || ''),
        state: cleanAddressValue(fields.state?.value || ''),
        municipality: cleanAddressValue(fields.municipality?.value || ''),
        locality: cleanAddressValue(fields.locality?.value || ''),
        street: cleanAddressValue(fields.street?.value || ''),
        exterior_number: cleanAddressValue(fields.exteriorNumber?.value || ''),
        interior_number: cleanAddressValue(fields.interiorNumber?.value || ''),
        floor: cleanAddressValue(fields.floor?.value || ''),
        catalog_cp_colonia_id: null
      };
    };

    const normalizeAddressPayload = (address)=>{
      return {
        country: cleanAddressValue(address?.country || 'MX').toUpperCase() || 'MX',
        postal_code: cleanAddressCp(address?.postal_code || ''),
        colony: cleanAddressValue(address?.colony || ''),
        state: cleanAddressValue(address?.state || ''),
        municipality: cleanAddressValue(address?.municipality || ''),
        locality: cleanAddressValue(address?.locality || ''),
        street: cleanAddressValue(address?.street || ''),
        exterior_number: cleanAddressValue(address?.exterior_number || ''),
        interior_number: cleanAddressValue(address?.interior_number || ''),
        floor: cleanAddressValue(address?.floor || ''),
        catalog_cp_colonia_id: address?.catalog_cp_colonia_id == null ? null : address.catalog_cp_colonia_id
      };
    };

    const serializeAddressSnapshot = (address)=> JSON.stringify(normalizeAddressPayload(address || {}));

    const rememberAddressSnapshotFromDom = ()=>{
      lastHydratedAddressSnapshot = serializeAddressSnapshot(readPatientAddressFromDom() || {});
    };

    const hasPatientAddressData = (address)=>{
      if(!address || typeof address !== 'object') return false;
      return [
        address.postal_code,
        address.colony,
        address.state,
        address.municipality,
        address.locality,
        address.street,
        address.exterior_number,
        address.interior_number,
        address.floor
      ].some((value)=> String(value || '').trim() !== '');
    };

    const validatePatientAddress = (address)=>{
      if(!hasPatientAddressData(address)) return null;
      const cp = String(address?.postal_code || '').trim();
      if(cp && !/^\d{5}$/.test(cp)){
        return 'El código postal debe tener 5 dígitos.';
      }
      return null;
    };

    const savePatientPrimaryAddress = async (patientId, address)=>{
      const pid = String(patientId || '').trim();
      if(!pid) throw new Error('patient_id requerido');
      if(!hasPatientAddressData(address)) return null;
      const validationError = validatePatientAddress(address);
      if(validationError) throw new Error(validationError);
      const response = await fetch(`/api/patients/index.php/patients/${encodeURIComponent(pid)}/address`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(address)
      });
      const json = await response.json().catch(()=> null);
      if(!response.ok || json?.ok !== true){
        throw new Error(String(json?.message || json?.error || 'No se pudo guardar el domicilio.'));
      }
      return json?.data?.address || null;
    };

    const dispatchAddressChange = (field, eventName = 'change')=>{
      if(!field) return;
      field.dispatchEvent(new Event(eventName, { bubbles: true }));
    };

    const setAddressFieldValue = (field, value, { dispatchEvents = true } = {})=>{
      if(!field) return;
      const next = String(value || '');
      if(field.value === next) return;
      field.value = next;
      if(dispatchEvents){
        dispatchAddressChange(field, 'input');
        dispatchAddressChange(field, 'change');
      }
    };

    const setAddressColonyValue = (select, value, meta = {})=>{
      if(!select) return;
      const colony = cleanAddressValue(value || '');
      if(!colony){
        select.value = '';
        dispatchAddressChange(select, 'change');
        return;
      }
      const exists = Array.from(select.options || []).some((option)=> option.value === colony);
      if(!exists){
        const option = document.createElement('option');
        option.value = colony;
        option.textContent = colony;
        option.dataset.estado = cleanAddressValue(meta.state || '');
        option.dataset.municipio = cleanAddressValue(meta.municipality || '');
        select.appendChild(option);
      }
      select.disabled = false;
      select.removeAttribute('disabled');
      select.value = colony;
      dispatchAddressChange(select, 'change');
    };

    const hydratePatientAddressIntoDom = (address)=>{
      const fields = getAddressFields();
      if(!fields || !address || typeof address !== 'object') return false;
      setAddressFieldValue(fields.cp, cleanAddressCp(address.postal_code || ''), { dispatchEvents: false });
      setAddressColonyValue(fields.colony, address.colony || '', address);
      setAddressFieldValue(fields.state, address.state || '');
      setAddressFieldValue(fields.municipality, address.municipality || '');
      setAddressFieldValue(fields.locality, address.locality || '');
      setAddressFieldValue(fields.street, address.street || '');
      setAddressFieldValue(fields.exteriorNumber, address.exterior_number || '');
      setAddressFieldValue(fields.interiorNumber, address.interior_number || '');
      setAddressFieldValue(fields.floor, address.floor || '');
      rememberAddressSnapshotFromDom();
      return true;
    };

    const clearPatientAddressFields = ()=>{
      const fields = getAddressFields();
      if(!fields) return;
      setAddressFieldValue(fields.cp, '');
      setAddressFieldValue(fields.state, '');
      setAddressFieldValue(fields.municipality, '');
      setAddressFieldValue(fields.locality, '');
      setAddressFieldValue(fields.street, '');
      setAddressFieldValue(fields.exteriorNumber, '');
      setAddressFieldValue(fields.interiorNumber, '');
      setAddressFieldValue(fields.floor, '');
      if(fields.colony){
        fields.colony.innerHTML = '<option value="">Captura primero el código postal</option>';
        fields.colony.value = '';
        fields.colony.disabled = true;
        fields.colony.setAttribute('disabled', 'disabled');
        dispatchAddressChange(fields.colony, 'change');
      }
      rememberAddressSnapshotFromDom();
    };

    const fetchAndHydratePatientAddress = async (patientId, shouldApply = ()=> true)=>{
      const pid = String(patientId || '').trim();
      if(!pid) return false;
      const response = await fetch(`/api/patients/index.php/patients/${encodeURIComponent(pid)}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      const json = await response.json().catch(()=> null);
      const addresses = Array.isArray(json?.data?.addresses) ? json.data.addresses : [];
      const primary = addresses.find((entry)=> entry?.is_primary === true) || addresses[0] || null;
      if(shouldApply() !== true) return false;
      if(!primary){
        clearPatientAddressFields();
        return false;
      }
      return hydratePatientAddressIntoDom(primary);
    };

    window.mxmedReadPatientAddressFromDom = readPatientAddressFromDom;
    window.mxmedHasPatientAddressData = hasPatientAddressData;
    window.mxmedSavePatientPrimaryAddress = savePatientPrimaryAddress;
    window.mxmedHydratePatientAddressIntoDom = hydratePatientAddressIntoDom;
    window.mxmedReadPatientProfileFromDom = readPatientProfileFromDom;
    window.mxmedHasPatientProfileData = hasPatientProfileData;
    window.mxmedSavePatientProfile = savePatientProfile;
    window.mxmedHydratePatientProfileIntoDom = hydratePatientProfileIntoDom;
    window.mxmedHydratePatientDetailIntoDatosGenerales = hydratePatientDetailIntoDatosGenerales;

    const isInNewEntryMode = ()=>{
      if(!expedienteRoot) return false;
      return String(expedienteRoot.dataset?.newEntryMode || expedienteRoot.getAttribute('data-new-entry-mode') || '').trim() === '1';
    };

    const readIdentityDraftFromDom = ()=>{
      if(!expedienteRoot) return null;
      return {
        nombre: String(expedienteRoot.querySelector('[data-pac-nombre]')?.value || '').trim(),
        apellido_paterno: String(expedienteRoot.querySelector('[data-pac-apellido-paterno]')?.value || '').trim(),
        apellido_materno: String(expedienteRoot.querySelector('[data-pac-apellido-materno]')?.value || '').trim(),
        sexo: String(expedienteRoot.querySelector('input[name="pac-genero"]:checked')?.value || '').trim(),
        dia: String(expedienteRoot.querySelector('[data-dg-dia]')?.value || '').trim(),
        mes: String(expedienteRoot.querySelector('[data-dg-mes]')?.value || '').trim(),
        anio: String(expedienteRoot.querySelector('[data-dg-anio]')?.value || '').trim()
      };
    };

    const hasPrimaryIdentityData = ()=>{
      const draft = readIdentityDraftFromDom();
      if(!draft) return false;
      return [
        draft.nombre,
        draft.apellido_paterno,
        draft.apellido_materno,
        draft.sexo,
        draft.dia,
        draft.mes,
        draft.anio
      ].some((value)=> String(value || '').trim() !== '');
    };

    const persistIdentityDraftForPatient = (patientId)=>{
      const pid = String(patientId || '').trim();
      if(!pid) return false;
      const draft = readIdentityDraftFromDom();
      if(!draft) return false;
      const hasData = Object.values(draft).some((val)=> String(val || '').trim() !== '');
      if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
        window.mxmedStore = {};
      }
      if(!window.mxmedStore.patientIdentityDrafts || typeof window.mxmedStore.patientIdentityDrafts !== 'object'){
        window.mxmedStore.patientIdentityDrafts = {};
      }
      if(!hasData){
        delete window.mxmedStore.patientIdentityDrafts[pid];
        return false;
      }
      window.mxmedStore.patientIdentityDrafts[pid] = draft;
      const label = [draft.nombre, draft.apellido_paterno, draft.apellido_materno].filter(Boolean).join(' ').trim();
      if(label){
        if(typeof window.mxmedRememberPatientLabel === 'function'){
          window.mxmedRememberPatientLabel(pid, label);
        }else{
          if(!window.mxmedStore.patientLabelById || typeof window.mxmedStore.patientLabelById !== 'object'){
            window.mxmedStore.patientLabelById = {};
          }
          window.mxmedStore.patientLabelById[pid] = label;
        }
      }
      return true;
    };

    const syncNewPatientDirtyState = ()=>{
      if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
        window.mxmedStore = {};
      }
      const dirty = isInNewEntryMode() && hasPrimaryIdentityData() && !explicitSaveCompleted;
      window.mxmedStore.newPatientEntryDirty = dirty;
      return dirty;
    };

    const clearNewPatientDirtyState = ()=>{
      explicitSaveCompleted = false;
      if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
        window.mxmedStore = {};
      }
      window.mxmedStore.newPatientEntryDirty = false;
    };

    window.mxmedHasUnsavedNewPatientDraft = ()=> syncNewPatientDirtyState();
    window.mxmedClearNewPatientEntryDirty = ()=> clearNewPatientDirtyState();

    const setSaveFeedback = (text, type = 'muted')=>{
      if(!savePatientFeedback) return;
      const msg = String(text || '').trim();
      if(!msg){
        savePatientFeedback.textContent = '';
        savePatientFeedback.className = 'small text-muted d-none';
        return;
      }
      const cls = type === 'success'
        ? 'small text-success'
        : type === 'error'
          ? 'small text-danger'
          : 'small text-muted';
      savePatientFeedback.textContent = msg;
      savePatientFeedback.className = cls;
    };

    const saveDatosGeneralesForActivePatient = ()=>{
      const patientId = getActivePatientId();
      if(!patientId){
        setSaveFeedback('Selecciona un paciente para guardar datos generales.', 'error');
        return Promise.resolve(null);
      }
      const profile = readPatientProfileFromDom();
      const address = readPatientAddressFromDom();
      const mobilePhone = readEditableMobilePhoneForSave();
      if(!mobilePhone.valid){
        setPatientMobilePhoneFeedback(mobilePhone.message || 'Revisa el teléfono celular.');
        setSaveFeedback(mobilePhone.message || 'Revisa el teléfono celular.', 'error');
        return Promise.resolve(null);
      }
      setPatientMobilePhoneFeedback('');
      const validationError = validatePatientAddress(address);
      if(validationError){
        setSaveFeedback(validationError, 'error');
        return Promise.resolve(null);
      }
      const shouldSaveAddress = hasPatientAddressData(address);
      const profileChanged = serializeProfileSnapshot(profile || {}) !== lastHydratedProfileSnapshot;
      const addressChanged = shouldSaveAddress && serializeAddressSnapshot(address || {}) !== lastHydratedAddressSnapshot;
      const mobilePhoneChanged = mobilePhone.hasValue && mobilePhone.snapshot !== lastHydratedEditableMobilePhoneSnapshot;
      if(!profileChanged && !addressChanged && !mobilePhoneChanged){
        setSaveFeedback('No hay cambios por guardar.', 'muted');
        return Promise.resolve({ profileSaved: null, addressSaved: null, phoneSaved: null });
      }
      if(savePatientBtn) savePatientBtn.disabled = true;
      setSaveFeedback('Guardando datos generales...', 'muted');
      const slowSaveFeedbackTimer = window.setTimeout(()=>{
        if(savePatientBtn?.disabled){
          setSaveFeedback('Guardando cambios; el servidor sigue procesando la solicitud...', 'muted');
        }
      }, 8000);
      const profileSavePromise = profileChanged
        ? savePatientProfile(patientId, profile, { force: true })
        .then((savedProfile)=>{
          if(savedProfile) hydratePatientProfileIntoDom(savedProfile);
          return true;
        })
        .catch((err)=>{
          console.warn('[DG-PROFILE-SAVE] request_error', {
            patient_id: patientId,
            message: String(err?.message || '').trim()
          });
          return false;
        })
        : Promise.resolve(null);
      const addressSavePromise = addressChanged
        ? savePatientPrimaryAddress(patientId, address)
            .then((savedAddress)=>{
              if(savedAddress) hydratePatientAddressIntoDom(savedAddress);
              return true;
            })
            .catch((err)=>{
              console.warn('[DG-ADDRESS-SAVE] request_error', {
                patient_id: patientId,
                message: String(err?.message || '').trim()
              });
              return false;
            })
        : Promise.resolve(null);
      const phoneSavePromise = mobilePhoneChanged
        ? saveEditableMobilePhone(patientId, mobilePhone)
            .then((contacts)=>{
              hydrateEditableMobilePhoneIntoDom(contacts);
              if(typeof window.mxmedInvalidatePatientsIndexCache === 'function'){
                window.mxmedInvalidatePatientsIndexCache();
              }
              return true;
            })
            .catch((err)=>{
              console.warn('[DG-CONTACTS-EDITABLE-SAVE] request_error', {
                patient_id: patientId,
                message: String(err?.message || '').trim()
              });
              return false;
            })
        : Promise.resolve(null);
      return Promise.all([profileSavePromise, addressSavePromise, phoneSavePromise])
        .then(([profileSaved, addressSaved, phoneSaved])=>{
          if(profileSaved === false && addressSaved === false && phoneSaved === false){
            setSaveFeedback('No se pudieron guardar los datos generales.', 'error');
          }else if(phoneSaved === false){
            setSaveFeedback('Datos generales guardados parcialmente; no se pudo guardar el teléfono.', 'error');
          }else if(profileSaved === false){
            setSaveFeedback('Datos generales guardados parcialmente; no se pudo guardar el perfil.', 'error');
          }else if(addressSaved === false){
            setSaveFeedback('Datos generales guardados parcialmente; no se pudo guardar el domicilio.', 'error');
          }else if(phoneSaved === true && (profileSaved === true || addressSaved === true)){
            setSaveFeedback('Datos generales guardados correctamente.', 'success');
          }else if(phoneSaved === true){
            setSaveFeedback('Teléfono guardado correctamente.', 'success');
          }else if(addressSaved === true){
            setSaveFeedback('Perfil y domicilio guardados correctamente.', 'success');
          }else if(profileSaved === true){
            setSaveFeedback('Perfil guardado correctamente.', 'success');
          }else{
            setSaveFeedback('Cambios guardados correctamente.', 'success');
          }
          return { profileSaved, addressSaved, phoneSaved };
        })
        .catch((err)=>{
          console.warn('[DG-PROFILE-SAVE] request_error', {
            patient_id: patientId,
            message: String(err?.message || '').trim()
          });
          setSaveFeedback(String(err?.message || 'No se pudieron guardar los datos generales.'), 'error');
          return null;
        })
        .finally(()=>{
          window.clearTimeout(slowSaveFeedbackTimer);
          if(savePatientBtn) savePatientBtn.disabled = false;
        });
    };

    const createPatientFromExplicitSave = ()=>{
      if(typeof window.mxmedValidatePatientEmails === 'function' && window.mxmedValidatePatientEmails() === false){
        setSaveFeedback('Revisa el formato de los correos electrónicos.', 'error');
        return Promise.resolve(null);
      }
      const payload = buildCreatePayload();
      if(!payload || !String(payload.display_name || '').trim()){
        setSaveFeedback('Captura nombre y apellidos para guardar.', 'error');
        return Promise.resolve(null);
      }
      const mobileValidation = validateRequiredPatientMobilePhone();
      if(!mobileValidation.valid){
        setPatientMobilePhoneFeedback(mobileValidation.message);
        setSaveFeedback(mobileValidation.message, 'error');
        mobileValidation.phone?.input?.focus?.();
        return Promise.resolve(null);
      }
      setPatientMobilePhoneFeedback('');
      appendPatientMobileContactToPayload(payload, mobileValidation.phone);
      const profile = readPatientProfileFromDom();
      const shouldSaveProfile = hasPatientProfileData(profile);
      const address = readPatientAddressFromDom();
      const shouldSaveAddress = hasPatientAddressData(address);
      const addressValidationError = validatePatientAddress(address);
      if(addressValidationError){
        setSaveFeedback(addressValidationError, 'error');
        return Promise.resolve(null);
      }
      if(creatingPatientPromise) return creatingPatientPromise;
      if(savePatientBtn) savePatientBtn.disabled = true;
      setSaveFeedback('Guardando paciente...', 'muted');
      console.info('[P14-PATIENT-SAVE] attempt', {
        display_name: payload.display_name || '',
        has_birthdate: !!payload.birthdate,
        sex: payload.sex || '',
        has_doctor_id: !!payload.doctor_id
      });
      creatingPatientPromise = fetch('/api/patients/index.php/patients', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      }).then((res)=> res.json().catch(()=> null))
        .then((json)=>{
          const patientId = String(json?.data?.patient_id || '').trim();
          if(json?.ok === true && patientId){
            console.info('[P14-PATIENT-SAVE] success', { patient_id: patientId });
            persistIdentityDraftForPatient(patientId);
            explicitSaveCompleted = true;
            syncNewPatientDirtyState();
            const profileSavePromise = shouldSaveProfile
              ? savePatientProfile(patientId, profile)
                  .then((savedProfile)=>{
                    if(savedProfile) hydratePatientProfileIntoDom(savedProfile);
                    return true;
                  })
                  .catch((err)=>{
                    console.warn('[DG-PROFILE-SAVE] after_create_error', {
                      patient_id: patientId,
                      message: String(err?.message || '').trim()
                    });
                    return false;
                  })
              : Promise.resolve(null);
            const addressSavePromise = shouldSaveAddress
              ? savePatientPrimaryAddress(patientId, address)
                  .then((savedAddress)=>{
                    if(savedAddress) hydratePatientAddressIntoDom(savedAddress);
                    return true;
                  })
                  .catch((err)=>{
                    console.warn('[DG-ADDRESS-SAVE] after_create_error', {
                      patient_id: patientId,
                      message: String(err?.message || '').trim()
                    });
                    return false;
                  })
              : Promise.resolve(null);
            return Promise.all([profileSavePromise, addressSavePromise]).then(([profileSaved, addressSaved])=>{
              Promise.resolve(setActivePatientId(patientId, { applyEntryRule: false }))
              .catch(()=> null)
              .finally(()=>{
                if(typeof window.mxmedShowClinicalCompletionHub === 'function'){
                  try{
                    window.mxmedShowClinicalCompletionHub({ patientId, source: 'datos-generales', event: 'explicit_save' });
                  }catch(_){}
                }
              });
            if(typeof window.mxmedInvalidatePatientsIndexCache === 'function'){
              window.mxmedInvalidatePatientsIndexCache();
            }
              if(profileSaved === false && addressSaved === false){
                setSaveFeedback('Paciente guardado, pero no se pudieron guardar perfil ni domicilio.', 'error');
              }else if(profileSaved === false){
                setSaveFeedback('Paciente guardado, pero no se pudo guardar perfil.', 'error');
              }else if(addressSaved === false){
                setSaveFeedback('Paciente guardado, pero no se pudo guardar domicilio.', 'error');
              }else if(shouldSaveProfile && shouldSaveAddress){
                setSaveFeedback('Paciente, perfil y domicilio guardados correctamente.', 'success');
              }else if(shouldSaveProfile){
                setSaveFeedback('Paciente y perfil guardados correctamente.', 'success');
              }else if(shouldSaveAddress){
                setSaveFeedback('Paciente y domicilio guardados correctamente.', 'success');
              }else{
                setSaveFeedback('Paciente guardado correctamente.', 'success');
              }
              return patientId;
            });
          }
          console.warn('[P14-PATIENT-SAVE] no_create', {
            ok: json?.ok === true,
            error: String(json?.error || '').trim(),
            message: String(json?.message || '').trim(),
            has_patient_id: !!patientId
          });
          setSaveFeedback(String(json?.message || 'No se pudo guardar el paciente.'), 'error');
          return null;
        })
        .catch((err)=>{
          console.warn('[P14-PATIENT-SAVE] request_error', {
            message: String(err?.message || '').trim()
          });
          setSaveFeedback('Error de red al guardar paciente.', 'error');
          return null;
        })
        .finally(()=>{
          creatingPatientPromise = null;
          if(savePatientBtn) savePatientBtn.disabled = false;
        });
      return creatingPatientPromise;
    };
    savePatientBtn?.addEventListener('click', ()=>{
      const activePatientId = getActivePatientId();
      if(activePatientId && !isInNewEntryMode()){
        saveDatosGeneralesForActivePatient();
        return;
      }
      createPatientFromExplicitSave();
    });

    const mobilePhoneControls = getPatientMobilePhoneControls();
    if(mobilePhoneControls?.input && !mobilePhoneControls.input.__mxmedRequiredMobileBound){
      mobilePhoneControls.input.__mxmedRequiredMobileBound = true;
      mobilePhoneControls.input.addEventListener('input', ()=>{
        if(validateRequiredPatientMobilePhone().valid){
          setPatientMobilePhoneFeedback('');
        }
      });
      mobilePhoneControls.countrySelect?.addEventListener('change', ()=>{
        if(validateRequiredPatientMobilePhone().valid){
          setPatientMobilePhoneFeedback('');
        }
      });
    }

    window.mxmedReadPatientMobilePhoneFromDom = readPatientMobilePhoneFromDom;
    window.mxmedValidateRequiredPatientMobilePhone = validateRequiredPatientMobilePhone;

    const setupBirthdateKeyboardAssist = ()=>{
      if(!expedienteRoot) return;
      const daySelect = expedienteRoot.querySelector('[data-dg-dia]');
      const monthSelect = expedienteRoot.querySelector('[data-dg-mes]');
      const yearSelect = expedienteRoot.querySelector('[data-dg-anio]');

      if(daySelect && !daySelect.__mxmedBirthDayNumericBound){
        daySelect.__mxmedBirthDayNumericBound = true;
        let dayNumberBuffer = '';
        let dayNumberTimer = null;
        const commitDayNumber = (value)=>{
          const raw = String(value || '').trim();
          if(!/^\d{1,2}$/.test(raw)) return false;
          const day = Number(raw);
          if(!Number.isInteger(day) || day < 1 || day > 31) return false;
          const next = String(day).padStart(2, '0');
          if(daySelect.value !== next){
            daySelect.value = next;
            daySelect.dispatchEvent(new Event('input', { bubbles: true }));
            daySelect.dispatchEvent(new Event('change', { bubbles: true }));
          }
          return true;
        };
        const clearDayNumberBuffer = ()=>{
          dayNumberBuffer = '';
          if(dayNumberTimer){
            window.clearTimeout(dayNumberTimer);
            dayNumberTimer = null;
          }
        };
        const flushDayNumberBuffer = ()=>{
          if(dayNumberBuffer){
            commitDayNumber(dayNumberBuffer);
          }
          clearDayNumberBuffer();
        };
        daySelect.addEventListener('keydown', (event)=>{
          if(event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
          if(!/^\d$/.test(event.key || '')) return;
          event.preventDefault();
          if(dayNumberTimer){
            window.clearTimeout(dayNumberTimer);
            dayNumberTimer = null;
          }
          dayNumberBuffer = (dayNumberBuffer + event.key).slice(-2);
          const numeric = Number(dayNumberBuffer);
          if(dayNumberBuffer.length === 2){
            if(numeric >= 1 && numeric <= 31){
              commitDayNumber(dayNumberBuffer);
              clearDayNumberBuffer();
              return;
            }
            clearDayNumberBuffer();
            return;
          }
          if(/^[4-9]$/.test(event.key)){
            commitDayNumber(event.key);
            clearDayNumberBuffer();
            return;
          }
          dayNumberTimer = window.setTimeout(flushDayNumberBuffer, 650);
        });
        daySelect.addEventListener('blur', flushDayNumberBuffer);
      }

      if(monthSelect && !monthSelect.__mxmedBirthMonthNumericBound){
        monthSelect.__mxmedBirthMonthNumericBound = true;
        let monthNumberBuffer = '';
        let monthNumberTimer = null;
        const commitMonthNumber = (value)=>{
          const month = Number(value);
          if(!Number.isInteger(month) || month < 1 || month > 12) return false;
          const next = String(month).padStart(2, '0');
          if(monthSelect.value !== next){
            monthSelect.value = next;
            monthSelect.dispatchEvent(new Event('input', { bubbles: true }));
            monthSelect.dispatchEvent(new Event('change', { bubbles: true }));
          }
          return true;
        };
        const clearMonthNumberBuffer = ()=>{
          monthNumberBuffer = '';
          if(monthNumberTimer){
            window.clearTimeout(monthNumberTimer);
            monthNumberTimer = null;
          }
        };
        monthSelect.addEventListener('keydown', (event)=>{
          if(event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
          if(!/^\d$/.test(event.key || '')) return;
          event.preventDefault();
          if(monthNumberTimer){
            window.clearTimeout(monthNumberTimer);
          }
          monthNumberBuffer = (monthNumberBuffer + event.key).slice(-2);
          const numeric = Number(monthNumberBuffer);
          if(monthNumberBuffer.length === 2){
            if(numeric >= 10 && numeric <= 12){
              commitMonthNumber(monthNumberBuffer);
              clearMonthNumberBuffer();
              return;
            }
            monthNumberBuffer = event.key;
          }
          if(event.key !== '1'){
            commitMonthNumber(event.key);
            clearMonthNumberBuffer();
            return;
          }
          monthNumberTimer = window.setTimeout(()=>{
            commitMonthNumber(monthNumberBuffer);
            clearMonthNumberBuffer();
          }, 650);
        });
        monthSelect.addEventListener('blur', ()=>{
          if(monthNumberBuffer){
            commitMonthNumber(monthNumberBuffer);
          }
          clearMonthNumberBuffer();
        });
      }

      if(yearSelect && !yearSelect.__mxmedBirthYearNativeAnchorBound){
        yearSelect.__mxmedBirthYearNativeAnchorBound = true;
        const yearAnchorValue = '2000';
        let yearAnchorRestoreTimer = null;
        const hasYearAnchor = ()=>{
          return Array.from(yearSelect.options || []).some((option)=> option.value === yearAnchorValue);
        };
        const restoreTemporaryYearAnchor = ()=>{
          if(yearSelect.dataset.mxmedTemporaryYearAnchor !== '1') return;
          if(yearSelect.value === yearAnchorValue){
            yearSelect.value = '';
          }
          delete yearSelect.dataset.mxmedTemporaryYearAnchor;
        };
        const clearYearAnchorRestoreTimer = ()=>{
          if(yearAnchorRestoreTimer){
            window.clearTimeout(yearAnchorRestoreTimer);
            yearAnchorRestoreTimer = null;
          }
        };
        const primeYearAnchorForNativeOpen = ()=>{
          if(yearSelect.value !== '' || !hasYearAnchor()) return;
          clearYearAnchorRestoreTimer();
          yearSelect.dataset.mxmedTemporaryYearAnchor = '1';
          yearSelect.value = yearAnchorValue;
          yearAnchorRestoreTimer = window.setTimeout(()=>{
            yearAnchorRestoreTimer = null;
            restoreTemporaryYearAnchor();
          }, 0);
        };
        const cancelTemporaryYearAnchor = ()=>{
          clearYearAnchorRestoreTimer();
          delete yearSelect.dataset.mxmedTemporaryYearAnchor;
        };
        const shouldPrimeYearAnchorForKey = (key)=>{
          return key === 'Enter'
            || key === ' '
            || key === 'ArrowDown'
            || key === 'ArrowUp'
            || key === 'PageDown'
            || key === 'PageUp';
        };
        yearSelect.addEventListener('pointerdown', (event)=>{
          if(event.defaultPrevented || (typeof event.button === 'number' && event.button !== 0)) return;
          primeYearAnchorForNativeOpen();
        }, true);
        yearSelect.addEventListener('mousedown', (event)=>{
          if(event.defaultPrevented || (typeof event.button === 'number' && event.button !== 0)) return;
          primeYearAnchorForNativeOpen();
        }, true);
        yearSelect.addEventListener('keydown', (event)=>{
          if(event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
          if(!shouldPrimeYearAnchorForKey(event.key || '')) return;
          primeYearAnchorForNativeOpen();
        }, true);
        yearSelect.addEventListener('input', cancelTemporaryYearAnchor);
        yearSelect.addEventListener('change', cancelTemporaryYearAnchor);
        yearSelect.addEventListener('blur', ()=>{
          clearYearAnchorRestoreTimer();
          restoreTemporaryYearAnchor();
        });
      }

    };

    setupBirthdateKeyboardAssist();

    document.querySelectorAll('input.form-control, select.form-select, textarea.form-control').forEach(ctrl=>{
      if(ctrl.type==='file') return;
      // excluir campos de búsqueda u opt-out manual
      if(ctrl.type==='search' || ctrl.classList.contains('no-check') || ctrl.dataset.noCheck==='1') return;
      if(isHumanNameFieldForNativeTextAssist(ctrl)) return;
      if(!ctrl.id){ ctrl.id = 'dp_auto_' + Math.random().toString(36).slice(2,8); }
      const col = ensureSaveMark(ctrl);
      const key = 'dp:'+ctrl.id;
      const saved = localStorage.getItem(key);
      if(saved!==null) ctrl.value = saved;
      const maybeMark = ()=>{
        const val = (ctrl.value||'').trim();
        let validByType = true;
        if(ctrl.id==='dp-correo'){
          validByType = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
        } else if(ctrl.id==='dp-whatsapp'){
          const digits = val.replace(/\D+/g,'');
          validByType = digits.length===10 || (digits.startsWith('52') && digits.length===12);
        }
        const invalid = ctrl.classList.contains('is-invalid') || !validByType;
        let hasVal = val !== '';
        // Género: no mostrar check si está sin seleccionar (opción placeholder)
        if(ctrl.id==='dp-genero'){
          try{
            hasVal = (ctrl.selectedIndex > 0) && (val !== '—');
          }catch(_){ hasVal = val !== '' && val !== '—'; }
        }
        if(col){ col.classList.toggle('saved', hasVal && !invalid); }
      };
      maybeMark();
      ctrl.addEventListener('input', ()=>{ explicitSaveCompleted = false; maybeMark(); syncNewPatientDirtyState(); });
      ctrl.addEventListener('change', ()=>{ localStorage.setItem(key, ctrl.value); explicitSaveCompleted = false; maybeMark(); syncNewPatientDirtyState(); });
      ctrl.addEventListener('blur', ()=>{ localStorage.setItem(key, ctrl.value); explicitSaveCompleted = false; maybeMark(); syncNewPatientDirtyState(); });
    });
    document.querySelectorAll('input[name="pac-genero"]').forEach((ctrl)=>{
      ctrl.addEventListener('change', ()=>{ explicitSaveCompleted = false; syncNewPatientDirtyState(); });
    });
    let profileHydrationToken = 0;
    let profileHydrationTimer = 0;
    const hydrateProfileForCurrentActivePatientNow = ()=>{
      if(isInNewEntryMode()) return;
      const patientId = getActivePatientId();
      const token = ++profileHydrationToken;
      if(!patientId){
        editableContactsHydrationToken++;
        resetEditableContactsHydrationCache();
        clearPatientProfileFields();
        hydratePatientContactsIntoDom([]);
        rememberEditableMobilePhoneSnapshotFromDom();
        return;
      }
      fetchAndHydratePatientProfile(patientId, ()=>{
        return token === profileHydrationToken
          && String(getActivePatientId() || '').trim() === patientId
          && !isInNewEntryMode();
      })
        .then(()=> hydrateEditableContactsForActivePatient(patientId))
        .catch(()=> null);
    };
    const hydrateProfileForCurrentActivePatient = ()=>{
      window.clearTimeout(profileHydrationTimer);
      profileHydrationTimer = window.setTimeout(hydrateProfileForCurrentActivePatientNow, 80);
    };
    let addressHydrationToken = 0;
    let addressHydrationTimer = 0;
    const hydrateAddressForCurrentActivePatientNow = ()=>{
      if(isInNewEntryMode()) return;
      const patientId = getActivePatientId();
      const token = ++addressHydrationToken;
      if(!patientId){
        clearPatientAddressFields();
        return;
      }
      fetchAndHydratePatientAddress(patientId, ()=>{
        return token === addressHydrationToken
          && String(getActivePatientId() || '').trim() === patientId
          && !isInNewEntryMode();
      })
        .then(()=> null)
        .catch(()=> null);
    };
    const hydrateAddressForCurrentActivePatient = ()=>{
      window.clearTimeout(addressHydrationTimer);
      addressHydrationTimer = window.setTimeout(hydrateAddressForCurrentActivePatientNow, 80);
    };
    ['patient:selected', 'expediente:patient_changed', 'expediente:patient-changed'].forEach((eventName)=>{
      window.addEventListener(eventName, ()=>{
        hydrateProfileForCurrentActivePatient();
        hydrateAddressForCurrentActivePatient();
      });
    });
    window.addEventListener('mxmed:expediente-neutralize', ()=>{
      editableContactsHydrationToken++;
      resetEditableContactsHydrationCache();
      clearNewPatientDirtyState();
      clearPatientProfileFields();
      clearPatientAddressFields();
      hydratePatientContactsIntoDom([]);
      rememberEditableMobilePhoneSnapshotFromDom();
    });
    window.setTimeout(syncNewPatientDirtyState, 0);
  }

  initAutosave();
})();

// ===== Enfermedades y Tratamientos: inputs con chips (máx. 40) =====
(function(){
  const LIM = 40;
  function load(scope){ try { return JSON.parse(localStorage.getItem('chips:'+scope)||'[]'); } catch(e){ return []; } }
  function save(scope, arr){ localStorage.setItem('chips:'+scope, JSON.stringify(arr)); document.dispatchEvent(new CustomEvent('chips:refresh', {detail:{scope}})); }
  function setup(scope){
    const input = document.getElementById(scope+'-input');
    const btn   = document.getElementById(scope+'-add');
    const cnt   = document.getElementById(scope+'-count');
    const list  = document.getElementById(scope+'-list');
    if(!input || !btn || !cnt || !list) return;

    render();

    function updateCount(){
      const used = (input.value||'').length;
      const left = Math.max(0, LIM - used);
      cnt.textContent = left+"/"+LIM;
      const tooLong = used> LIM;
      setError(tooLong ? 'Máximo 40 caracteres. Ej.: "Cáncer de mama"' : '', tooLong);
      btn.disabled = tooLong || used===0;
      cnt.style.visibility = left < 10 ? 'visible' : 'hidden';
    }

    function setError(msg, isErr){
      // reusa burbuja propia
      let col = input.closest('.save-wrap');
      if(!col){ col = input.parentElement; }
      let bub = col.querySelector(':scope > .err-bubble');
      if(isErr){
        if(!bub){ bub = document.createElement('div'); bub.className='err-bubble'; col.appendChild(bub); }
        bub.textContent = msg;
        input.classList.add('is-invalid'); col.classList.add('has-error');
      }else{
        if(bub) bub.remove(); input.classList.remove('is-invalid'); col.classList.remove('has-error');
      }
    }

    function render(){
      const items = load(scope);
      list.innerHTML='';
      items.forEach((txt, i)=>{
        const chip = document.createElement('span'); chip.className='chip'; chip.textContent = txt;
        const x = document.createElement('button'); x.type='button'; x.className='chip-x'; x.setAttribute('aria-label','Eliminar'); x.textContent='×';
        x.addEventListener('click', ()=>{ const a=load(scope); a.splice(i,1); save(scope,a); render(); });
        chip.appendChild(x); list.appendChild(chip);
      });
      if(items.length>2){
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'chip-sort-link';
        link.dataset.scope = scope;
        link.textContent = 'cambia el orden';
        list.appendChild(link);
      }
    }

    btn.addEventListener('click', ()=>{
      const val = (input.value||'').trim();
      if(!val || val.length> LIM){ updateCount(); return; }
      const a = load(scope); a.push(val); save(scope,a); render();
      input.value=''; updateCount();
    });
    input.addEventListener('input', updateCount);
    input.addEventListener('blur', updateCount);
    updateCount();
  }

  setup('enf');
  setup('trt');

  // Modal de ordenamiento
  const sortModalEl = document.getElementById('modalSortChips');
  const sortListEl = document.getElementById('sort-list');
  const sortSaveBtn = document.getElementById('sort-save');
  let sortScope = null; let temp = [];

  function renderSort(){
    sortListEl.innerHTML='';
    temp.forEach((t,i)=>{
      const li = document.createElement('li'); li.className='sort-item'; li.draggable=true; li.dataset.index=i;
      const h = document.createElement('span'); h.className='material-symbols-outlined sort-handle'; h.textContent='drag_indicator';
      const tx = document.createElement('span'); tx.textContent = t; tx.style.flex='1 1 auto';
      li.appendChild(h); li.appendChild(tx); sortListEl.appendChild(li);
    });
  }

  function bindDnD(){
    let dragIdx=null;
    sortListEl.addEventListener('dragstart', e=>{ const li=e.target.closest('.sort-item'); if(li){ dragIdx=+li.dataset.index; li.classList.add('dragging'); Array.from(sortListEl.querySelectorAll('.sort-item')).forEach(el=>{ if(el!==li) el.classList.add('dimmed'); }); e.dataTransfer.effectAllowed='move'; }});
    sortListEl.addEventListener('dragover', e=>{ e.preventDefault(); });
    sortListEl.addEventListener('drop', e=>{ e.preventDefault(); const li=e.target.closest('.sort-item'); if(li&& dragIdx!=null){ const dropIdx=+li.dataset.index; const it=temp.splice(dragIdx,1)[0]; temp.splice(dropIdx,0,it); renderSort(); bindDnD(); }});
    sortListEl.addEventListener('dragend', ()=>{ Array.from(sortListEl.children).forEach(el=>{ el.classList.remove('dragging'); el.classList.remove('dimmed'); }); });
  }

  document.addEventListener('click', (e)=>{
    const a = e.target.closest('.chip-sort-link');
    if(!a) return;
    e.preventDefault();
    sortScope = a.dataset.scope;
    temp = load(sortScope).slice();
    renderSort(); bindDnD();
    if(window.bootstrap && sortModalEl){ new bootstrap.Modal(sortModalEl).show(); }
  });

  sortSaveBtn?.addEventListener('click', ()=>{
    if(sortScope){ save(sortScope, temp); renderSort(); document.dispatchEvent(new Event('chips:refresh')); }
    const m = bootstrap.Modal.getInstance(sortModalEl); m?.hide();
  });

  document.addEventListener('chips:refresh', (ev)=>{
    // refrescar ambas listas al cambiar orden
    setup('enf'); setup('trt');
  });
})();

// ===== Servicios Principales: contador 50/50 por campo =====
(function(){
  const LIM = 50;
  ['srv1','srv2','srv3','srv4'].forEach(id=>{
    const input = document.getElementById(id);
    const cnt = document.getElementById(id+'-count');
    if(!input || !cnt) return;
    function update(){
      const used = (input.value||'').length;
      const left = Math.max(0, LIM - used);
      cnt.textContent = left + '/' + LIM;
      cnt.style.visibility = left < 10 ? 'visible' : 'hidden';
    }
    input.addEventListener('input', update);
    input.addEventListener('blur', update);
    update();
  });
})();

// ===== Mi Formación Profesional: resumen + chips (cert, cursos, diplomas, miembro) =====
