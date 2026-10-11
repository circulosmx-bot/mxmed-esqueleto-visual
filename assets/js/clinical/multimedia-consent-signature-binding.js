/* MULTI-CONS02B: content-only projection matching clinical_multimedia_consent_binding.php. */
(function () {
  'use strict';
  const clean = value => String(value ?? '').normalize('NFC').replace(/\r\n?/g, '\n');
  const projection = body => {
    const p = body?.payload || {}, consent = p.consent || {}, patient = p.patient_snapshot || {};
    const actor = p.actor_snapshot || {}, signer = p.signer || {}, validity = consent.validity || {};
    const context = consent.context || {};
    const media = [...new Set(consent.media_types || [])].sort();
    const purposes = [...new Set(consent.use_purposes || [])].sort();
    const custom = consent.authorization_scope === 'custom_limited_scope' ? clean(consent.custom_scope_description).trim() : '';
    const ranged = validity.mode === 'explicit_date_range';
    const labels = {photo:'fotografías', video:'video', audio:'audio'};
    const uses = {clinical_record:'integración al expediente clínico', clinical_followup:'seguimiento de la evolución clínica'};
    const scopes = {specific_event:'este evento o atención', current_episode:'este episodio de atención',
      current_condition_followup:'el seguimiento del padecimiento actual', custom_limited_scope:custom};
    const optional = [['clinical_event_or_procedure','Atención o procedimiento'], ['anatomical_area','Área anatómica'],
      ['clinical_purpose_note','Nota de contexto clínico'], ['event_date','Fecha del evento']]
      .filter(([key])=>clean(context[key]).trim()).map(([key,label])=>`${label}: ${clean(context[key]).trim()}`);
    let declaration = `De manera voluntaria autorizo la captura de ${media.map(key=>labels[key]).join(', ')} en relación con mi atención clínica. Autorizo su uso únicamente para ${purposes.map(key=>uses[key]).join(' y ')}. El material se utilizará de forma ${consent.identification_scope==='identifiable'?'identificable':'con identificación reducida, sin garantía de anonimato'}, en el ámbito de ${scopes[consent.authorization_scope]}, durante ${ranged?`el periodo del ${validity.valid_from} al ${validity.valid_to}`:'este evento únicamente'}.`;
    if(optional.length) declaration += ` Contexto: ${optional.join('; ')}.`;
    declaration += ' Recibí información sobre este alcance y tuve oportunidad de hacer preguntas antes de decidir.';
    return {
      version:1, document_type:'consentimiento_multimedia', document_uuid:clean(body?.draft_ref),
      document_version:Number(body?.expected_version || 0), document_date:clean(p.document_date),
      patient:{id:clean(body?.context?.patient_id),name:clean(patient.full_name),age:clean(patient.age),sex:clean(patient.sex)},
      physician:{user_id:clean(actor.user_id),name:clean(actor.full_name),license:clean(actor.license),
        specialty:clean(actor.specialty),facility:clean(actor.facility),place:clean(actor.place)},
      signer:{mode:clean(signer.mode),name:clean(signer.name),character:clean(signer.character),
        relationship:clean(signer.relationship),authority:clean(signer.authority)},
      consent:{media_types:media,capture_allowed:consent.capture_allowed===true,use_purposes:purposes,
        identification_scope:clean(consent.identification_scope),authorization_scope:clean(consent.authorization_scope),
        custom_scope_description:custom,validity:{mode:clean(validity.mode),valid_from:ranged?clean(validity.valid_from):'',
          valid_to:ranged?clean(validity.valid_to):''},
        context:{clinical_event_or_procedure:clean(context.clinical_event_or_procedure).trim(),
          anatomical_area:clean(context.anatomical_area).trim(),clinical_purpose_note:clean(context.clinical_purpose_note).trim(),
          event_date:clean(context.event_date).trim()},declaration:clean(declaration)},
      presentation:{professional_header:p.presentation?.professional_header==='hidden'?'hidden':'shown'}
    };
  };
  const hash = async body => Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',
    new TextEncoder().encode(JSON.stringify(projection(body))))), byte=>byte.toString(16).padStart(2,'0')).join('');
  const authority = (body,role,doctorId) => role==='doctor'
    ? `${clean(doctorId)}|${clean(body?.payload?.actor_snapshot?.user_id)}`
    : body?.payload?.signer?.mode==='patient'
      ? JSON.stringify(['patient',clean(body?.context?.patient_id),clean(body?.payload?.patient_snapshot?.full_name)])
      : JSON.stringify(['representative',...['name','character','relationship','authority'].map(key=>clean(body?.payload?.signer?.[key]))]);
  const imageDigest = data=>window.mxmedConsentSignatureBinding.imageDigest(data);
  const bind = async (body,role,doctorId,entry)=>{
    if(!['signer','doctor'].includes(role) || !['local_canvas','registered_profile'].includes(entry?.source)
      || (role==='signer'&&entry.source==='registered_profile')
      || !/^[0-9a-f-]{36}$/i.test(clean(body?.draft_ref)) || Number(body?.expected_version)<1
      || !entry.image_data || !window.mxmedConsentSignatureBinding.imageHasInk(entry.image_data)) return null;
    const digest=await imageDigest(entry.image_data);
    if(!digest)return null;
    return {...entry,binding:{version:1,document_type:'consentimiento_multimedia',document_uuid:clean(body.draft_ref),
      document_version:Number(body.expected_version),role,source:entry.source,authority:authority(body,role,doctorId),
      content_fingerprint:await hash(body),artifact_digest:digest,applied_at:new Date().toISOString()}};
  };
  const classify = async (body,role,doctorId,registeredImage='')=>{
    const entry=body?.payload?.signatures?.[role], b=entry?.binding;
    if(!entry?.image_data)return 'absent';
    if(b?.version!==1)return 'legacy_unverified_binding';
    const name=role==='doctor'?body?.payload?.actor_snapshot?.full_name:
      body?.payload?.signer?.mode==='patient'?body?.payload?.patient_snapshot?.full_name:body?.payload?.signer?.name;
    if(b.document_type!=='consentimiento_multimedia'||b.document_uuid!==body?.draft_ref
      ||Number(b.document_version)!==Number(body?.expected_version)||b.role!==role||entry.role!==role
      ||b.source!==entry.source||b.authority!==authority(body,role,doctorId)||!name||entry.signer_name!==name
      ||b.content_fingerprint!==await hash(body)||b.artifact_digest!==await imageDigest(entry.image_data)
      ||!window.mxmedConsentSignatureBinding.imageHasInk(entry.image_data)
      ||(role==='signer'&&entry.source==='registered_profile'))return 'stale_or_unverified_signature';
    if(entry.source==='registered_profile'&&(!registeredImage||b.artifact_digest!==await imageDigest(registeredImage)))
      return 'stale_or_unverified_signature';
    if(entry.source==='remote_qr'&&(!entry.token||b.token!==entry.token))return 'stale_or_unverified_signature';
    return 'valid_bound_signature';
  };
  window.mxmedMultimediaConsentBinding=Object.freeze({projection,hash,authority,imageDigest,bind,classify});
})();
