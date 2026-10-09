/* CONS-SIGN02A: versioned, content-only consent signature projection. */
(function () {
  'use strict';
  const clean = value => String(value ?? '').normalize('NFC').replace(/\r\n?/g, '\n');
  const canonical = value => {
    if (Array.isArray(value)) return value.map(canonical);
    if (value && typeof value === 'object') return Object.fromEntries(
      Object.keys(value).sort().map(key => [key, canonical(value[key])])
    );
    if (typeof value === 'string') return clean(value);
    return value === true || value === false ? value : (value == null ? '' : value);
  };
  const attachmentKey = ref => clean(ref?.document_uuid || ref?.document_id || '')
    ? `uuid:${clean(ref?.document_uuid || ref?.document_id || '')}`
    : `sha256:${clean(ref?.sha256 || '').toLowerCase()}`;
  const projection = body => {
    const payload = body?.payload || {};
    const form = payload.form_snapshot || {};
    const patient = payload.patient_snapshot || {};
    const actor = payload.actor_snapshot || {};
    const consent = payload.consent || {};
    const signer = payload.firmante || {};
    const witnesses = Array.isArray(payload.testigos) ? payload.testigos : [];
    const refs = Array.isArray(payload.signer_identity_attachment_manifest)
      ? payload.signer_identity_attachment_manifest : [];
    const context = payload.signature_context || body?.context || {};
    const version = payload.presentation ? 2 : 1;
    return canonical({
      version,
      document_type: 'consentimiento_informado',
      document_date: clean(payload.signature_document_date || ''),
      patient: { id: clean(context.patient_id || ''), name: clean(patient.full_name),
        age: clean(patient.age), sex: clean(patient.sexo) },
      context: { encounter_key: clean(context.encounter_key),
        appointment_id: clean(context.appointment_id) },
      physician: { user_id: clean(body?.actor_user_id || actor.user_id),
        name: clean(actor.full_name), license: clean(actor.license),
        place: clean(payload.place), institution: clean(payload.institution_name),
        facility: clean(payload.facility_name) },
      ...(version === 2 ? {
        presentation: {professional_header: window.mxmedLegalDocumentPresentation.professionalHeaderMode(payload)},
        visible_header: {name: clean(payload.branding?.professional_name_visible),
          specialty: clean(actor.specialty), specialty_license: clean(actor.specialty_license),
          license: clean(actor.license), logo_url: clean(payload.branding?.logo_url_resolved),
          facility: clean(payload.branding?.facility_visible),
          location: clean(payload.branding?.location_line_visible)}
      } : {}),
      content: { title: clean(consent.document_title), procedure: clean(form.procedimiento),
        motive: clean(form.motivo), objective: clean(form.objetivo), risks: clean(form.riesgos),
        risk_common: clean(form.risk_comunes), risk_infrequent: clean(form.risk_poco_frecuentes),
        risk_rare: clean(form.risk_raros_graves), benefits: clean(form.beneficios_esperados),
        alternatives: clean(form.alternativas), consequences: clean(form.consecuencias_no_aceptar),
        contingency: !!form.autorizacion_contingencias,
        rendered_text: clean(payload.rendered_text) },
      signer: { type: clean(signer.tipo), name: clean(signer.nombre),
        relationship: clean(signer.relacion || signer.parentesco) },
      informed_confirmation: !!form.confirm_informed,
      witnesses: [clean(witnesses[0]?.nombre), clean(witnesses[1]?.nombre)],
      attachments: refs.map(attachmentKey).sort()
    });
  };
  const stableAttachments = body => {
    const refs = body?.payload?.signer_identity_attachment_manifest || [];
    return Array.isArray(refs) && refs.every(ref =>
      !!clean(ref?.document_uuid || ref?.document_id || ''));
  };
  const hash = async body => {
    const bytes = new TextEncoder().encode(JSON.stringify(projection(body)));
    const digest = await crypto.subtle.digest('SHA-256', bytes);
    return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
  };
  const imageBytes = data => {
    const match = /^data:image\/png;base64,([A-Za-z0-9+/]+={0,2})$/.exec(clean(data));
    if (!match) return null;
    try { return Uint8Array.from(atob(match[1]), char => char.charCodeAt(0)); }
    catch (_) { return null; }
  };
  const imageDigest = async data => {
    const bytes = imageBytes(data);
    if (!bytes || !bytes.length || bytes.length > 2097152) return '';
    const digest = await crypto.subtle.digest('SHA-256', bytes);
    return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
  };
  const hasInk = (canvas, minimumPixels = 12) => {
    if (!canvas) return false;
    const {width, height} = canvas;
    const rgba = canvas.getContext('2d')?.getImageData(0, 0, width, height)?.data;
    if (!rgba) return false;
    let count = 0;
    let left = width, right = -1, top = height, bottom = -1;
    for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
      const i = (y * width + x) * 4;
      if (rgba[i + 3] > 120 && Math.min(rgba[i], rgba[i + 1], rgba[i + 2]) < 245) {
        count++; left = Math.min(left, x); right = Math.max(right, x);
        top = Math.min(top, y); bottom = Math.max(bottom, y);
      }
    }
    return count >= minimumPixels && right - left >= 5 && bottom - top >= 2;
  };
  const imageHasInk = data => new Promise(resolve => {
    if (!imageBytes(data)) { resolve(false); return; }
    const image = new Image();
    image.onload = () => {
      const canvas = document.createElement('canvas');
      canvas.width = image.naturalWidth;
      canvas.height = image.naturalHeight;
      if (!canvas.width || !canvas.height || canvas.width * canvas.height > 4000000) {
        resolve(false); return;
      }
      canvas.getContext('2d').drawImage(image, 0, 0);
      resolve(hasInk(canvas));
    };
    image.onerror = () => resolve(false);
    image.src = data;
  });
  const classify = async (body, role, authority, registeredImage = '') => {
    const entry = body?.payload?.signatures?.[role];
    if (!entry?.image_data) return 'absent';
    const binding = entry.binding;
    const version = projection(body).version;
    if (entry.source === 'remote_qr' && (!binding || ![1, 2].includes(binding.version)
      || !binding.consent_uuid || Number(binding.token_id) < 1)) return 'legacy_unbound';
    if (!binding || ![1, 2].includes(binding.version)) return 'legacy_unverified_binding';
    if (binding.version !== version) return 'stale_or_unverified';
    if (binding.revoked_in_edit) return 'stale_or_unverified';
    if (!['local_canvas', 'registered_profile', 'remote_qr'].includes(entry.source)
      || (role === 'patient' && !['local_canvas', 'remote_qr'].includes(entry.source))
      || entry.role !== (role === 'patient' ? 'patient_or_representative' : 'doctor')
      || !body?.payload?.signature_document_date
      || !stableAttachments(body)) return 'stale_or_unverified';
    const digest = await imageDigest(entry.image_data);
    if (!digest || !await imageHasInk(entry.image_data)
      || digest !== binding.artifact_digest
      || await hash(body) !== binding.content_fingerprint
      || binding.role !== role || binding.authority !== authority
      || binding.source !== entry.source) return 'stale_or_unverified';
    if (entry.source === 'remote_qr'
      && (!entry.token || !Number.isInteger(Number(binding.token_id))
        || Number(binding.token_id) < 1
        || !binding.consent_uuid
        || binding.consent_uuid !== body?.payload?.qr_consent_uuid)) return 'stale_or_unverified';
    if (entry.source === 'registered_profile'
      && (!registeredImage || await imageDigest(registeredImage) !== digest)) return 'stale_or_unverified';
    return 'valid_bound_signature';
  };
  window.mxmedConsentSignatureBinding = Object.freeze({projection, hash, imageDigest, hasInk, imageHasInk, stableAttachments, classify});
})();
