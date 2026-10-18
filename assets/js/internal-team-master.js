/* Exact privileged UI orchestration. Authority, eligibility, proof consumption and audit remain server-owned. */
(() => {
  'use strict';
  const $=id=>document.getElementById(id),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const labels={INTERNAL_MASTER_APPOINT:'Proponer como Master',INTERNAL_MASTER_REMOVE:'Retirar designación Master',INTERNAL_ADVISOR_MANAGEMENT_GRANT:'Conceder gestión de asesores',INTERNAL_ADVISOR_MANAGEMENT_REVOKE:'Retirar gestión de asesores',INTERNAL_CAPABILITY_DELEGATE:'Delegar capacidad',INTERNAL_CAPABILITY_DELEGATION_REVOKE:'Retirar delegación'};
  const messages={NO_AUTHORITY:'No se pudo validar la sesión, la autoridad o la verificación de seguridad. Revisa antes de iniciar un caso nuevo.',MASTER_GATE_DISABLED:'Nombramientos y delegaciones Master no habilitados.',MISSING_IDENTITY_REVIEW:'Nombramiento Master pendiente de revisión de identidad habilitada.',MISSING_MFA_FACTOR:'Falta un factor MFA activo y disponible del Director. Esta pantalla no realiza enrolamiento.',READY_FOR_EXACT_ACTION:'Cada acción exige confirmación, contraseña actual y MFA del Director.',PENDING_CONFIRMATION:'Revisa una sola acción y sus consecuencias.',REAUTHENTICATION_REQUIRED:'Confirma la intención emitida por el servidor con tus credenciales actuales.',MFA_CHALLENGE_PENDING:'Introduce el código de tu factor MFA activo.',MFA_VERIFIED:'MFA verificado. El cambio todavía no se ha ejecutado.',EXECUTION_PENDING:'Procesando el caso. No repitas la solicitud.',CONFIRMED:'CAMBIO CONFIRMADO por el caso y la auditoría canónicos.',CONFLICT:'CONFLICTO: actualiza y revisa un caso nuevo.',EXPIRED:'La autorización expiró. Requiere una revisión nueva.',CANCELED:'Caso cancelado. No se ejecutó este cambio.',UNKNOWN_OUTCOME:'ESTADO PENDIENTE DE CONFIRMAR',READ_UNAVAILABLE:'Consulta no disponible. No se habilitan acciones.'};
  // Pure presentation shared with isolated DOM tests; never grants authority.
  window.teamMasterActionPresentation=flow=>{
      if(flow.operation==='INTERNAL_MASTER_REMOVE')return '<ul><li>La persona pasa a ADVISOR.</li><li>Se revocan gestión de asesores y delegaciones activas.</li><li>Los permisos operativos independientes y las acciones históricas se conservan.</li><li>La suspensión vigente se conserva y una cuenta desactivada no se reactiva.</li><li>No se deciden atribución comercial ni comisiones.</li></ul>';
      if(flow.operation==='INTERNAL_MASTER_APPOINT')return '<p>La designación no concede gestión, delegación ni permisos operativos. Requiere revisión interna de identidad autorizada.</p>';
      if(flow.capability==='internal_advisors_manage')return `<p>Gestión actualmente ${flow.member.management_granted?'concedida':'no concedida'}. Cambia sólo la gestión; las delegaciones y los permisos de uso permanecen separados. Sin gestión vigente, una delegación no permite administrar asesores.</p>`;
      return `<p>Delegación actualmente ${(flow.member.master_delegations||[]).includes(flow.capability)?'vigente':'no concedida'}. Cambia una sola capacidad. No concede uso operativo ni gestión de asesores; no afecta otras delegaciones.</p>`;
    };
  window.createTeamMasterFlow=({editor,read,refresh})=>{
    let data=null,token='',generation=0,active=false,busy=false,state='READ_UNAVAILABLE',flow=null,challenge=null,proof=null,timer=null,returnFocus=null;
    const member=()=>data?.items?.[0];
    const notice=(s,text)=>{state=s;const el=$('master-status');if(el){el.dataset.state=s;el.textContent=text||messages[s];}};
    async function request(action,body,caseId){
      const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),15000);
      try{const r=await fetch('/api/internal/team/master/'+action+'.php'+(caseId?'?case_id='+encodeURIComponent(caseId):''),{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',referrerPolicy:'same-origin',signal:controller.signal,headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-Token':token}:{})},...(body?{body:JSON.stringify(body)}:{})});let value=null;try{value=await r.json();}catch(_){}return {ok:r.ok,status:r.status,value};}finally{clearTimeout(timeout);}
    }
    const allowed=(op,cap)=>token&&state==='READY_FOR_EXACT_ACTION'&&member()?.master_actions?.operations?.some(a=>a.operation===op&&a.capability===cap);
    function mount(){
      const m=member();if(!m)return;
      if(active||state==='READY_FOR_EXACT_ACTION')$('team-mode').textContent='GOBIERNO CON CONFIRMACIÓN';
      const designation=$('designation');if(designation){designation.disabled=true;designation.onclick=null;designation.setAttribute('aria-describedby','master-status');
        if(m.governance_class==='MASTER_ADMIN'){designation.disabled=active||!allowed('INTERNAL_MASTER_REMOVE',null);designation.onclick=()=>start('INTERNAL_MASTER_REMOVE',null);}}
      const manage=$('master-management');if(manage){const op=m.management_granted?'INTERNAL_ADVISOR_MANAGEMENT_REVOKE':'INTERNAL_ADVISOR_MANAGEMENT_GRANT';manage.disabled=active||!allowed(op,'internal_advisors_manage');manage.onclick=()=>{manage.checked=m.management_granted;start(op,'internal_advisors_manage');};}
      document.querySelectorAll('#delegation-matrix [data-capability]').forEach(el=>{const cap=el.dataset.capability,op=(m.master_delegations||[]).includes(cap)?'INTERNAL_CAPABILITY_DELEGATION_REVOKE':'INTERNAL_CAPABILITY_DELEGATE';el.disabled=active||!allowed(op,cap);el.onclick=()=>{el.checked=(m.master_delegations||[]).includes(cap);start(op,cap);};});
    }
    async function adopt(next){
      if(active)return;data=next;token='';const version=++generation,m=member();notice(m?.master_actions?.state||'NO_AUTHORITY');mount();
      if(data?.authority?.class!=='DIRECTOR'||state==='MASTER_GATE_DISABLED')return;
      try{const r=await request('csrf');if(version!==generation||active)return;
        if(!r.ok||typeof r.value?.csrf_token!=='string'||!r.value.csrf_token.startsWith('session.')||!['READY_FOR_EXACT_ACTION','MISSING_MFA_FACTOR'].includes(r.value?.state)){notice(r.status===403||r.status===401?'NO_AUTHORITY':'READ_UNAVAILABLE');return;}
        token=r.value.csrf_token;notice(m.governance_class==='ADVISOR'?'MISSING_IDENTITY_REVIEW':r.value.state);
      }catch(_){if(version===generation)notice('READ_UNAVAILABLE');}finally{if(version===generation)mount();}
    }
    function clearSecrets(){challenge=null;proof=null;document.querySelectorAll('#confirmation input').forEach(e=>{e.value='';});}
    function context(){const f=flow,c=f?.case;return `<p class="team-reference">Cuenta: ${esc(f?.target)} · ${esc(c?.expected_staff?.governance_class||f?.member.governance_class)} · ${esc(c?.expected_staff?.staff_status||f?.member.staff_status)} · Cuenta ${esc(c?.expected_staff?.account_status||f?.member.account_status)}</p><p class="team-reference">${esc(labels[f?.operation])}${f?.capability?' · '+esc(f.capability):''}</p>${c?`<p class="team-reference">Caso: ${esc(c.case_id)}<br>Operación: ${esc(c.operation_id)}<br>Vence: ${esc(c.expires_at)} UTC</p>`:''}`;}
    const consequences=()=>window.teamMasterActionPresentation(flow);
    function show(next,extra=''){
      state=next;notice(next);const dialog=$('confirmation');$('confirmation-title').textContent=labels[flow.operation];$('confirmation-description').textContent=messages[next];
      $('confirmation-body').innerHTML=context()+extra;$('confirmation-body').dataset.masterState=next;
      const accept=$('accept-confirmation'),cancel=$('cancel-confirmation');accept.disabled=busy;accept.hidden=false;cancel.disabled=busy;cancel.hidden=false;cancel.textContent='Cancelar';cancel.onclick=cancelFlow;
      const finish=['CONFIRMED','CANCELED'].includes(next);if(finish){accept.textContent='Cerrar';accept.onclick=close;cancel.hidden=true;}
      else if(next==='PENDING_CONFIRMATION'){accept.textContent='Abrir caso exacto';accept.disabled=true;accept.onclick=open;$('master-ack').onchange=e=>{accept.disabled=!e.target.checked||busy;};}
      else if(next==='REAUTHENTICATION_REQUIRED'){accept.textContent='Confirmar con contraseña';accept.onclick=confirm;}
      else if(next==='MFA_CHALLENGE_PENDING'){accept.textContent='Verificar MFA';accept.onclick=verify;}
      else if(next==='MFA_VERIFIED'){accept.textContent='Ejecutar esta acción';accept.onclick=execute;}
      else if(next==='UNKNOWN_OUTCOME'){accept.textContent='Consultar resultado';accept.onclick=reconcile;cancel.disabled=true;}
      else if(['CONFLICT','EXPIRED','READ_UNAVAILABLE','NO_AUTHORITY'].includes(next)){accept.hidden=true;cancel.textContent=flow.case?'Cancelar caso y cerrar':'Cerrar';}
      else {accept.disabled=true;accept.textContent='Procesando…';}
      dialog.oncancel=e=>{e.preventDefault();if(!busy&&next!=='UNKNOWN_OUTCOME'){if(finish)close();else cancelFlow();}};
      dialog.onkeydown=e=>{
        if(e.key!=='Tab'||!active)return;
        const controls=[...dialog.querySelectorAll('input,button,[tabindex]')].filter(el=>!el.disabled&&!el.hidden&&el.tabIndex>=0&&el.getClientRects().length);
        e.preventDefault();if(!controls.length){dialog.focus();return;}
        const index=controls.indexOf(document.activeElement),step=e.shiftKey?-1:1;controls[(index+step+controls.length)%controls.length].focus();
      };
      if(!dialog.open)dialog.showModal();
      if(!busy){const input=dialog.querySelector('input');(input||(!accept.hidden&&!accept.disabled?accept:cancel)).focus();}
    }
    function exact(c){return c&&c.case_id?.match(/^master_[a-f0-9]{32}$/)&&typeof c.operation_id==='string'&&c.operation_id.length===36&&c.operation===flow.operation&&c.target===flow.target&&c.capability===flow.capability&&typeof c.intent_digest==='string'&&c.intent_digest.length===64;}
    function expired(){return flow?.case&&Date.parse(flow.case.expires_at.replace(' ','T').slice(0,23)+'Z')<=Date.now();}
    function error(r){clearSecrets();show(r?.status===409?'CONFLICT':r?.status===401||r?.status===403?'NO_AUTHORITY':'READ_UNAVAILABLE');}
    async function start(operation,capability){
      if(active||editor.busy||!allowed(operation,capability))return;
      if(editor.dirty()||editor.uncertain){notice('PENDING_CONFIRMATION','Resuelve los permisos pendientes: completa GUARDAR PERMISOS, usa Revertir o cancela esta acción Master. No se descartó el borrador.');state='READY_FOR_EXACT_ACTION';return;}
      active=true;generation++;returnFocus=$('advisor-detail').contains(document.activeElement)&&document.activeElement.matches('button,input,select,textarea,a[href]')?document.activeElement:null;flow={operation,capability,target:member().account_reference,member:structuredClone(member()),case:null,executed:false};mount();
      show('PENDING_CONFIRMATION',consequences()+'<label class="ack-row"><input id="master-ack" type="checkbox">Confirmo esta acción individual y sus consecuencias.</label>');
    }
    async function open(){
      if(busy||state!=='PENDING_CONFIRMATION'||!$('master-ack')?.checked)return;
      busy=true;show('EXECUTION_PENDING');
      try{const fresh=await read({category:flow.member.governance_class,target:flow.target});
        if(JSON.stringify(fresh.items[0])!==JSON.stringify(flow.member)){busy=false;show('CONFLICT');return;}
        const r=await request('open',{operation:flow.operation,target:flow.target,capability:flow.capability,reason:'Confirmación individual del Director en Equipo'});busy=false;
        if(!r.ok||!exact(r.value)||r.value.status!=='OPEN'){error(r);return;}
        flow.case=r.value;show('REAUTHENTICATION_REQUIRED',consequences()+'<label class="wizard-field">Correo de tu cuenta Director<input id="master-email" type="email" autocomplete="username" maxlength="254"></label><label class="wizard-field">Contraseña actual del Director<input id="master-password" type="password" autocomplete="current-password" maxlength="512"></label>');
        timer=setInterval(()=>{if(!busy&&!flow?.executed&&expired()&&!['EXPIRED','CANCELED'].includes(state)){clearSecrets();show('EXPIRED');}},1000);
      }catch(_){busy=false;error();}
    }
    async function confirm(){
      if(busy||state!=='REAUTHENTICATION_REQUIRED')return;if(expired()){clearSecrets();show('EXPIRED');return;}
      const email=$('master-email').value,password=$('master-password').value;if(!email||!password)return;
      $('master-password').value='';$('master-email').value='';busy=true;show('EXECUTION_PENDING');
      try{const r=await request('confirm',{case_id:flow.case.case_id,intent_digest:flow.case.intent_digest,email,password});busy=false;
        if(!r.ok||!/^challenge_[a-f0-9]{32}$/.test(r.value?.challenge_id||'')||!/^[A-Za-z0-9_-]{43}$/.test(r.value?.challenge||'')){error(r);return;}
        challenge=r.value;show('MFA_CHALLENGE_PENDING','<label class="wizard-field">Código MFA del Director<input id="master-code" type="password" inputmode="numeric" autocomplete="off" maxlength="6" pattern="[0-9]{6}"></label>');
      }catch(_){busy=false;error();}
    }
    async function verify(){
      if(busy||state!=='MFA_CHALLENGE_PENDING')return;if(expired()){clearSecrets();show('EXPIRED');return;}
      const code=$('master-code').value;if(!/^\d{6}$/.test(code))return;$('master-code').value='';busy=true;show('EXECUTION_PENDING');
      try{const r=await request('verify',{case_id:flow.case.case_id,challenge_id:challenge.challenge_id,challenge:challenge.challenge,code});challenge=null;busy=false;
        if(!r.ok||!/^proof_[a-f0-9]{32}$/.test(r.value?.proof_id||'')||!/^[A-Za-z0-9_-]{43}$/.test(r.value?.proof||'')){error(r);return;}
        proof=r.value;show('MFA_VERIFIED',consequences());
      }catch(_){busy=false;error();}
    }
    async function execute(){
      if(busy||state!=='MFA_VERIFIED'||!proof||flow.executed)return;if(expired()){clearSecrets();show('EXPIRED');return;}
      flow.executed=true;busy=true;show('EXECUTION_PENDING');
      const body={case_id:flow.case.case_id,proof_id:proof.proof_id,proof:proof.proof};clearSecrets();
      try{await request('execute',body);}catch(_){}finally{body.proof='';body.proof_id='';busy=false;}
      // Always reconcile exact case + UUID + audit, including a successful execute response.
      await reconcile();
    }
    async function reconcile(){
      if(busy||!flow?.case)return;busy=true;show('EXECUTION_PENDING');
      try{const r=await request('read',null,flow.case.case_id);busy=false;
        if(!r.ok||!exact(r.value)||r.value.operation_id!==flow.case.operation_id||r.value.intent_digest!==flow.case.intent_digest){show('UNKNOWN_OUTCOME');return;}
        const c=r.value;
        if(c.status==='CONSUMED'&&c.result?.operation_id===flow.case.operation_id&&c.result?.account_id===flow.target&&typeof c.result?.audit_event_id==='string'&&c.result.audit_event_id.length===36){clearSecrets();show('CONFIRMED');}
        else if(['EXPIRED','REVOKED'].includes(c.status)){clearSecrets();show(c.status==='EXPIRED'?'EXPIRED':'CANCELED');}
        else if(c.status==='OPEN'){clearSecrets();show(expired()?'EXPIRED':'CONFLICT','<p>El caso aún no tiene resultado confirmado. Cancélalo antes de iniciar una revisión nueva; no se repetirá la ejecución.</p>');}
        else show('UNKNOWN_OUTCOME');
      }catch(_){busy=false;show('UNKNOWN_OUTCOME');}
    }
    async function cancelFlow(){
      if(busy||state==='UNKNOWN_OUTCOME')return;
      clearSecrets();clearInterval(timer);timer=null;
      if(!flow.case){close();return;}busy=true;show('EXECUTION_PENDING');
      try{const r=await request(expired()?'expire':'cancel',{case_id:flow.case.case_id});busy=false;
        if(!r.ok||!exact(r.value)||!['REVOKED','EXPIRED'].includes(r.value.status)){show('UNKNOWN_OUTCOME');return;}show('CANCELED');
      }catch(_){busy=false;show('UNKNOWN_OUTCOME');}
    }
    function close(){if(busy||state==='UNKNOWN_OUTCOME')return;clearSecrets();clearInterval(timer);timer=null;active=false;const target=flow.target,category=state==='CONFIRMED'&&flow.operation==='INTERNAL_MASTER_REMOVE'?'ADVISOR':flow.member.governance_class;flow=null;token='';$('confirmation').onkeydown=null;$('confirmation').close();$('confirmation-body').innerHTML='';refresh(target,category).finally(()=>{if(returnFocus?.isConnected)returnFocus.focus();else $('detail-title')?.focus();});}
    window.addEventListener('beforeunload',e=>{if(active){e.preventDefault();e.returnValue='';}});
    return {adopt,mount,get active(){return active;}};
  };
})();
