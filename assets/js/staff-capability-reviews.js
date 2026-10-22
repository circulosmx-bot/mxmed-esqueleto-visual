(() => {
  'use strict';
  const $=id=>document.getElementById(id),api='/api/internal/workspace/requests.php';
  let csrf='',generation=0;
  const clear=()=>{generation++;$('governed-reviews').hidden=true;$('provider-review-panel').hidden=true;$('provider-claims').replaceChildren();csrf='';};
  async function call(mode,input){
    const response=await fetch(api+(input?'':'?mode='+mode),input?{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action:mode,input})}:{credentials:'same-origin',cache:'no-store'});
    const result=await response.json();if(!response.ok||!result.ok){if([401,403].includes(response.status))clear();throw new Error('No fue posible consultar o confirmar esta revisión. Actualiza tu acceso antes de reintentar.');}return result.data;
  }
  async function access(){const epoch=++generation;try{const data=await call('review_access');if(epoch!==generation)return;csrf=data.csrf_token;const c=data.capabilities;$('professional-reviews').hidden=!(c.professional_source_provision||c.physician_accreditation_review);$('provider-reviews').hidden=!c.provider_claim_review;$('governed-reviews').hidden=!Object.values(c).some(Boolean);}catch{clear();}}
  async function providers(){const epoch=++generation;$('provider-feedback').textContent='';$('provider-claims').replaceChildren();try{const data=await call('provider_claims');if(epoch!==generation)return;csrf=data.csrf_token;$('provider-review-panel').hidden=false;
    for(const claim of data.claims){const article=document.createElement('article'),title=document.createElement('h3'),info=document.createElement('p');title.textContent=claim.organization_name;info.textContent='Estado: '+claim.status+' · Evidencia declarada: '+claim.evidence_type;article.append(title,info);
      if(claim.status==='PENDING'){const form=document.createElement('form'),label=document.createElement('label'),select=document.createElement('select'),confirm=document.createElement('input'),text=document.createElement('span'),button=document.createElement('button');
        for(const [value,name] of [['APPROVED:EVIDENCE_ACCEPTED','Aprobar: evidencia comprobada'],['REJECTED:EVIDENCE_INSUFFICIENT','Rechazar: evidencia insuficiente'],['REJECTED:ORGANIZATION_DUPLICATE','Rechazar: organización duplicada'],['REJECTED:OWNERSHIP_CONFLICT','Rechazar: conflicto de titularidad'],['REJECTED:OTHER_REVIEW','Rechazar: requiere otra revisión']]){const option=document.createElement('option');option.value=value;option.textContent=name;select.append(option);}select.setAttribute('aria-label','Decisión del claim');confirm.type='checkbox';confirm.required=true;text.textContent='Confirmo la revisión de este claim y esta organización.';label.append(confirm,text);button.type='submit';button.textContent='Registrar decisión';form.append(select,label,button);
        form.onsubmit=async event=>{event.preventDefault();if(!confirm.checked)return;button.disabled=true;const [decision,reason]=select.value.split(':');try{await call('provider_decide',{request_id:claim.claim_uuid,decision,reason});await providers();$('provider-feedback').textContent='Decisión registrada por el servicio canónico.';}catch(error){$('provider-feedback').textContent=error.message;}finally{button.disabled=false;}};article.append(form);} $('provider-claims').append(article);
    }if(!data.claims.length)$('provider-feedback').textContent='No hay claims en tu ámbito de revisión.';
  }catch(error){$('provider-feedback').textContent=error.message;}}
  $('provider-reviews').onclick=providers;$('close-provider-reviews').onclick=()=>{generation++;$('provider-review-panel').hidden=true;$('provider-claims').replaceChildren();};
  $('staff-logout').addEventListener('click',clear);window.addEventListener('pagehide',clear);window.addEventListener('focus',access);access();
})();
