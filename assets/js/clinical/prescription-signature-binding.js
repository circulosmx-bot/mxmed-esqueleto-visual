(function () {
  'use strict';
  const clean = value => String(value ?? '').normalize('NFC').replace(/\r\n?/g, '\n');
  const projection = body => {
    const p=body?.payload||{}, patient=p.patient_snapshot||{}, doctor=p.physician_snapshot||{};
    const professional=p.professional_context||{}, consultorio=professional.consultorio||{};
    const diagnosis=p.diagnostic_context||{}, items=(p.prescription?.items||[]).map(item=>{
      const result={};
      for(const key of ['name','presentation','dose','route','frequency','duration','quantity','instructions'])
        result[key]=clean(item?.[key]);
      return result;
    });
    const presentationV2=p.presentation?.version===2;
    const header=presentationV2&&['hidden','data_only','logo_and_data'].includes(p.presentation?.professional_header)
      ?p.presentation.professional_header:(p.presentation?.professional_header==='hidden'?'hidden':'shown');
    const headerShown=header!=='hidden',logoShown=header==='shown'||header==='logo_and_data';
    const result={version:1,document_type:'prescription',document_uuid:clean(body?.draft_ref),
      document_version:Number(body?.expected_version)||0,
      patient:{id:clean(patient.id),name:clean(patient.full_name),age:clean(patient.age),sex:clean(patient.sex)},
      physician:{user_id:clean(doctor.user_id),doctor_id:clean(doctor.doctor_id),name:clean(doctor.full_name),
        prefix:headerShown?clean(doctor.prefix):'',designation:headerShown?clean(doctor.designation):'',
        specialty:headerShown?clean(doctor.specialty):'',
        license:clean(doctor.license),specialty_license:clean(doctor.specialty_license)},
      issue_date:clean(p.issue_date),diagnostic_context:{mode:clean(diagnosis.mode),text:clean(diagnosis.text)},
      encounter_reference:{encounter_id:Number(p.encounter_reference?.encounter_id)||0,
        appointment_id:clean(p.encounter_reference?.appointment_id)},
      medication_count:items.length,medications:items,observations:clean(p.prescription?.observaciones),
      presentation:presentationV2?{version:2,professional_header:header}:{professional_header:header},
      professional_context:{logo_url:logoShown?clean(professional.logo_url):'',
        consultorio:{name:headerShown?clean(consultorio.name):'',
          address:headerShown?clean(consultorio.address):''}}};
    const footer=p.professional_presentation||{};
    if(Number(footer.version)>=2){
      result.version=2;
      result.professional_context.consultorio={name:'',address:''};
      result.professional_presentation={version:2,offices:(Array.isArray(footer.offices)?footer.offices:[]).slice(0,3).map(office=>({
        name:clean(office?.name),address:clean(office?.address),
        phones:(Array.isArray(office?.phones)?office.phones:[]).map(clean),
        is_primary:office?.is_primary===true,
        display_order:office?.display_order==null?null:(Number(office.display_order)||0)
      }))};
    }
    return result;
  };
  const hash=async body=>{
    const bytes=new TextEncoder().encode(JSON.stringify(projection(body)));
    const digest=await crypto.subtle.digest('SHA-256',bytes);
    return [...new Uint8Array(digest)].map(x=>x.toString(16).padStart(2,'0')).join('');
  };
  const authority=(body,doctorId)=>`${clean(doctorId)}|${clean(body?.payload?.physician_snapshot?.user_id)}`;
  const bind=async(body,doctorId,signature)=>{
    const uuid=clean(body?.draft_ref).toLowerCase(), version=Number(body?.expected_version)||0;
    if(!/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/.test(uuid)||version<1
      || !['local_canvas','registered_profile'].includes(signature?.source)
      || !signature?.image_data||!await window.mxmedConsentSignatureBinding.imageHasInk(signature.image_data))return null;
    const digest=await window.mxmedConsentSignatureBinding.imageDigest(signature.image_data);
    if(!digest)return null;
    return {...signature,role:'doctor',binding:{version:1,document_type:'prescription',document_uuid:uuid,
      document_version:version,role:'doctor',source:signature.source,authority:authority(body,doctorId),
      artifact_digest:digest,content_fingerprint:await hash(body),applied_at:new Date().toISOString()}};
  };
  window.mxmedPrescriptionSignatureBinding=Object.freeze({projection,hash,authority,bind});
})();
