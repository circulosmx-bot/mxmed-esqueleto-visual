/* RESP-IMP01A: content-only Responsiva V1, separate from Consentimiento. */
(function () {
  'use strict';
  const clean = value => String(value ?? '').normalize('NFC').replace(/\r\n?/g, '\n');
  const projection = body => {
    const p = body?.payload || {}, patient = p.patient_snapshot || {}, actor = p.actor_snapshot || {};
    const brand = p.branding || {};
    const type = p.responsiva || {}, content = p.content || {}, signer = p.signer || {};
    const headerMode = window.mxmedLegalDocumentPresentation.professionalHeaderMode(p);
    return {
      version: 1, document_type: 'responsiva_medica', document_date: clean(p.report?.emission_date),
      patient: { id: clean(body?.context?.patient_id), name: clean(patient.full_name), age: clean(patient.age), sex: clean(patient.sex) },
      physician: { user_id: clean(actor.user_id), name: clean(actor.full_name), license: clean(actor.license),
        specialty: clean(actor.specialty), specialty_license: clean(actor.specialty_license), place: clean(actor.place),
        institution: clean(actor.institution), facility: clean(actor.facility) },
      visible_branding: { logo_url: clean(brand.logo_url_resolved),
        facility: clean(brand.facility_visible ?? actor.facility),
        location: clean(brand.location_line_visible ?? actor.place) },
      ...(headerMode === 'hidden' ? { presentation: { professional_header: 'hidden' } } : {}),
      type: { key: clean(type.type), other: clean(type.type_other), label: clean(type.type_label) },
      content: { clinical_situation: clean(content.clinical_situation), indicated_conduct: clean(content.indicated_conduct),
        relevant_risk: clean(content.relevant_risk), declaration_text: clean(content.declaration_text),
        additional_manifestation: clean(content.additional_manifestation), closing_statement: clean(content.closing_statement) },
      signer: { role: clean(signer.role), name: clean(signer.name), character: clean(signer.character),
        relationship: clean(signer.relationship) }
    };
  };
  const hash = async body => {
    const bytes = new TextEncoder().encode(JSON.stringify(projection(body)));
    const digest = await crypto.subtle.digest('SHA-256', bytes);
    return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
  };
  const authority = (body, role, doctorId) => role === 'doctor'
    ? `${clean(doctorId)}|${clean(body?.payload?.actor_snapshot?.user_id)}`
    : JSON.stringify(['role', 'name', 'character', 'relationship'].map(key => clean(body?.payload?.signer?.[key])));
  const imageDigest = data => window.mxmedConsentSignatureBinding.imageDigest(data);
  const imageHasInk = data => window.mxmedConsentSignatureBinding.imageHasInk(data);
  const documentUuid = body => {
    const value = clean(body?.draft_ref).trim().toLowerCase();
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/.test(value) ? value : '';
  };
  const bind = async (body, role, doctorId, signature) => {
    const uuid = documentUuid(body);
    if (!uuid || !['local_canvas', 'registered_profile'].includes(signature?.source)) return null;
    if (!signature?.image_data || !await imageHasInk(signature.image_data)) return null;
    const digest = await imageDigest(signature.image_data);
    if (!digest) return null;
    return { ...signature, binding: { version: 2, document_type: 'responsiva_medica', document_uuid: uuid,
      role, source: signature.source,
      authority: authority(body, role, doctorId), artifact_digest: digest,
      content_fingerprint: await hash(body), applied_at: new Date().toISOString() } };
  };
  const classify = async (body, role, doctorId, registeredImage = '') => {
    const entry = body?.payload?.signatures?.[role];
    if (!entry?.image_data) return 'absent';
    const b = entry.binding;
    if (entry.source === 'remote_qr' ? b?.version !== 1 : b?.version !== 2)
      return 'legacy_unverified_binding';
    if (entry.source !== 'remote_qr'
      && (b.document_type !== 'responsiva_medica' || !documentUuid(body)
        || clean(b.document_uuid) !== documentUuid(body))) return 'stale_or_unverified_signature';
    if (b.revoked_in_edit || !['local_canvas', 'registered_profile', 'remote_qr'].includes(entry.source)
      || (role === 'signer' && !['local_canvas', 'remote_qr'].includes(entry.source)) || entry.role !== role
      || b.role !== role || b.source !== entry.source || b.authority !== authority(body, role, doctorId)
      || clean(entry.signer_name) !== clean(role === 'doctor' ? body?.payload?.actor_snapshot?.full_name : body?.payload?.signer?.name)
      || b.content_fingerprint !== await hash(body) || !await imageHasInk(entry.image_data)
      || b.artifact_digest !== await imageDigest(entry.image_data)) return 'stale_or_unverified_signature';
    if (entry.source === 'registered_profile'
      && (!registeredImage || b.artifact_digest !== await imageDigest(registeredImage)))
      return 'stale_or_unverified_signature';
    if (entry.source === 'remote_qr'
      && (!body?.draft_ref || b.document_uuid !== body.draft_ref
        || !entry.token || b.token !== entry.token || !Number.isInteger(Number(b.document_version))))
      return 'stale_or_unverified_signature';
    return 'valid_bound_signature';
  };
  window.mxmedResponsivaSignatureBinding = Object.freeze({ projection, hash, authority, bind, classify, imageDigest });
})();
