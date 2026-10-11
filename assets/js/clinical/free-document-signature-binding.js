/* FREE-DOC02B: Documento libre version-bound signature projection. */
(function () {
  'use strict';
  const clean = value => String(value ?? '').normalize('NFC').replace(/\r\n?/g, '\n');
  const projection = body => {
    const p=body?.payload||{}, d=p.document||{}, patient=p.patient_snapshot||{}, actor=p.actor_snapshot||{};
    const sections=[...(d.sections||[])].sort((a,b)=>Number(a.section_order)-Number(b.section_order));
    return {
      version:1,document_type:'documento_libre',document_uuid:clean(body?.draft_ref),
      document_version:Number(body?.expected_version)||0,
      patient:{id:clean(patient.id),name:clean(patient.full_name),age:clean(patient.age),sex:clean(patient.sex)},
      physician:{user_id:clean(actor.user_id),name:clean(actor.full_name),license:clean(actor.license),
        specialty:clean(actor.specialty),specialty_license:clean(actor.specialty_license)},
      logo_url:p.presentation?.professional_header==='hidden'?'':clean(p.branding?.logo_url_resolved),
      presentation:{professional_header:p.presentation?.professional_header==='hidden'?'hidden':'shown'},
      document:{title:clean(d.title),purpose:clean(d.purpose),
        sections:sections.map(s=>({order:Number(s.section_order)||0,title:clean(s.section_title),body:clean(s.section_body)})),
        closing_note:clean(d.closing_note)}
    };
  };
  const hash=async body=>{
    const bytes=new TextEncoder().encode(JSON.stringify(projection(body)));
    const digest=await crypto.subtle.digest('SHA-256',bytes);
    return [...new Uint8Array(digest)].map(byte=>byte.toString(16).padStart(2,'0')).join('');
  };
  const authority=(body,doctorId)=>`${clean(doctorId)}|${clean(body?.payload?.actor_snapshot?.user_id)}`;
  const imageDigest=data=>window.mxmedConsentSignatureBinding.imageDigest(data);
  const imageHasInk=data=>window.mxmedConsentSignatureBinding.imageHasInk(data);
  const bind=async(body,doctorId,signature)=>{
    const uuid=clean(body?.draft_ref).toLowerCase(),version=Number(body?.expected_version)||0;
    if(!/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/.test(uuid)||version<1
      || !['local_canvas','registered_profile'].includes(signature?.source)
      || !signature?.image_data||!await imageHasInk(signature.image_data)) return null;
    const digest=await imageDigest(signature.image_data);
    if(!digest) return null;
    return {...signature,role:'doctor',binding:{version:1,document_type:'documento_libre',
      document_uuid:uuid,document_version:version,role:'doctor',source:signature.source,
      authority:authority(body,doctorId),artifact_digest:digest,
      content_fingerprint:await hash(body),applied_at:new Date().toISOString()}};
  };
  const classify=async(body,doctorId,registeredImage='')=>{
    const entry=body?.payload?.signatures?.doctor;
    if(!entry?.image_data) return 'absent';
    const binding=entry.binding,source=entry.source;
    if(binding?.version!==1) return 'legacy_unverified_binding';
    if(binding.document_type!=='documento_libre'||binding.document_uuid!==clean(body?.draft_ref)
      || Number(binding.document_version)!==Number(body?.expected_version)
      || binding.role!=='doctor'||entry.role!=='doctor'||binding.source!==source
      || !['local_canvas','registered_profile','remote_qr'].includes(source)
      || binding.authority!==authority(body,doctorId)
      || clean(entry.signer_name)!==clean(body?.payload?.actor_snapshot?.full_name)
      || binding.content_fingerprint!==await hash(body)
      || !await imageHasInk(entry.image_data)||binding.artifact_digest!==await imageDigest(entry.image_data))
      return 'stale_or_unverified_signature';
    if(source==='registered_profile'&&(!registeredImage||binding.artifact_digest!==await imageDigest(registeredImage)))
      return 'stale_or_unverified_signature';
    if(source==='remote_qr'&&(!entry.token||binding.token!==entry.token)) return 'stale_or_unverified_signature';
    return 'valid_bound_signature';
  };
  window.mxmedFreeDocumentSignatureBinding=Object.freeze({projection,hash,authority,bind,classify,imageDigest});
})();
