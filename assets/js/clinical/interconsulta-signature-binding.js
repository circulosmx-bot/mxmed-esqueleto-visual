/* Interconsulta V1 projection and explicit physician signature application. */
(function () {
  'use strict';
  const clean = value => String(value ?? '').normalize('NFC').replace(/\r\n?/g, '\n');
  const pick = (source, keys) => Object.fromEntries(keys.map(key => [key, clean(source?.[key])]));
  const projection = body => {
    const p=body?.payload||{}, a=p.actor_snapshot||{}, patient=p.patient_snapshot||{};
    const brand=p.branding||{}, recipient=p.recipient||{}, content=p.content||{};
    return {
      version:1, document_type:'interconsulta', document_uuid:clean(body?.draft_ref),
      document_date:clean(p.report?.emission_date),
      patient:{id:clean(body?.context?.patient_id),...pick(patient,['full_name','age','sex','identifier'])},
      physician:pick(a,['user_id','full_name','license','specialty','specialty_license','place','institution','facility']),
      visible_branding:{logo_url:clean(brand.logo_url_resolved),
        facility:clean(brand.facility_visible??a.facility),location:clean(brand.location_line_visible??a.place),
        group_name:clean(brand.group_name),address_line:clean(brand.address_line),
        consultorio_phone:clean(brand.consultorio_phone)},
      presentation:{professional_header:window.mxmedLegalDocumentPresentation.professionalHeaderMode(p)},
      recipient:pick(recipient,['mode','source','recipient_user_id','directory_ref','doctor_name','specialty','service','facility','city','contact']),
      content:pick(content,['reason','summary','background','request','studies','comments','closing_statement','final_note']),
      rendered_text_contract:'structured_v1'
    };
  };
  const hash=async body=>{
    const bytes=new TextEncoder().encode(JSON.stringify(projection(body)));
    const digest=await crypto.subtle.digest('SHA-256',bytes);
    return Array.from(new Uint8Array(digest),byte=>byte.toString(16).padStart(2,'0')).join('');
  };
  const authority=(body,doctorId)=>`${clean(doctorId)}|${clean(body?.payload?.actor_snapshot?.user_id)}`;
  const imageDigest=data=>window.mxmedConsentSignatureBinding.imageDigest(data);
  const imageHasInk=data=>window.mxmedConsentSignatureBinding.imageHasInk(data);
  const bind=async(body,doctorId,signature)=>{
    const uuid=clean(body?.draft_ref).toLowerCase();
    if(!/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/.test(uuid)
      || !['local_canvas','registered_profile'].includes(signature?.source)
      || !signature?.image_data || !await imageHasInk(signature.image_data)) return null;
    const digest=await imageDigest(signature.image_data);
    if(!digest) return null;
    return {...signature,binding:{version:2,document_type:'interconsulta',document_uuid:uuid,
      role:'doctor',source:signature.source,authority:authority(body,doctorId),artifact_digest:digest,
      content_fingerprint:await hash(body),applied_at:new Date().toISOString()}};
  };
  const classify=async(body,doctorId,registeredImage='')=>{
    const entry=body?.payload?.signatures?.doctor;
    if(!entry?.image_data) return 'absent';
    const b=entry.binding, source=entry.source, uuid=clean(body?.draft_ref).toLowerCase();
    if(source==='remote_qr'?b?.version!==1:b?.version!==2) return 'legacy_unverified_binding';
    if(b?.document_type!=='interconsulta'||b?.document_uuid!==uuid||b.revoked_in_edit
      || !['local_canvas','registered_profile','remote_qr'].includes(source)
      || entry.role!=='doctor'||b.role!=='doctor'||b.source!==source
      || b.authority!==authority(body,doctorId)
      || clean(entry.signer_name)!==clean(body?.payload?.actor_snapshot?.full_name)
      || b.content_fingerprint!==await hash(body)
      || !await imageHasInk(entry.image_data)
      || b.artifact_digest!==await imageDigest(entry.image_data)) return 'stale_or_unverified_signature';
    if(source==='registered_profile'&&(!registeredImage||b.artifact_digest!==await imageDigest(registeredImage)))
      return 'stale_or_unverified_signature';
    if(source==='remote_qr'&&(!entry.token||b.token!==entry.token||!Number.isInteger(Number(b.document_version))))
      return 'stale_or_unverified_signature';
    return 'valid_bound_signature';
  };
  window.mxmedInterconsultaSignatureBinding=Object.freeze({projection,hash,authority,bind,classify,imageDigest});
})();
