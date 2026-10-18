/* Draft/transport orchestration only. Identity, scope, locks and writes remain server-owned. */
(() => {
  'use strict';
  const $=id=>document.getElementById(id), esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  window.createTeamPermissionEditor=({read,onData})=>{
    let base=null,draft=new Set(),token='',busy=false,known=true,epoch=0,outcomes=[],message='',dialogOpen=false,lifecycleResult=null;
    const row=data=>data?.items?.[0];
    const editable=()=>base?.authority?.mutations_enabled===true?(row(base)?.editable_capabilities||[]).filter(k=>base.catalog[k]?.kind==='operational'&&base.catalog[k]?.delegable):[];
    const lifecycleOperations=()=>base?.authority?.mutations_enabled===true?(row(base)?.lifecycle_operations||[]).filter(op=>['suspend','reactivate'].includes(op)):[];
    const removalAllowed=()=>base?.authority?.mutations_enabled===true&&row(base)?.removal_allowed===true;
    const available=()=>editable().length||lifecycleOperations().length||removalAllowed();
    const displayName=()=>{const p=row(base)?.personal_profile;return p?[p.given_names,p.first_surname,p.second_surname].filter(Boolean).join(' '):'Perfil personal pendiente';};
    const restriction=()=>row(base)?.staff_status==='REMOVED'?'Baja definitiva: no admite reactivación ni nuevos permisos.':row(base)?.account_status!=='active'?'Cuenta Identity inactiva; permisos operativos bloqueados.':row(base)?.governance_class==='MASTER_ADMIN'?'Esta matriz edita asesores; el gobierno Master tiene autorización independiente.':row(base)?.staff_status==='SUSPENDED'?'Asesor suspendido: reactívalo antes de editar permisos.':base?.authority?.mutations_enabled!==true?'Cambios no habilitados en este entorno.':'Funciones no habilitadas para este asesor o fuera de tu ámbito autorizado.';
    const operations=()=>editable().filter(k=>draft.has(k)!==row(base).grants.includes(k)).map(capability=>({operation:draft.has(capability)?'grant':'revoke',target:row(base).account_reference,capability}));
    const dirty=()=>!!base&&operations().length>0;
    const fingerprint=data=>JSON.stringify({actor:data.authority,member:row(data)&&{id:row(data).account_reference,kind:row(data).governance_class,status:row(data).staff_status,account:row(data).account_status,grants:[...row(data).grants].sort(),editable:[...(row(data).editable_capabilities||[])].sort(),lifecycle:row(data).lifecycle_operations||[],removal:row(data).removal_allowed===true},catalog:data.catalog});
    const sameScope=(a,b)=>fingerprint({...a,items:[{...row(a),grants:row(b).grants}]})===fingerprint(b);
    async function request(path,options={}){
      const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),15000);
      try{const response=await fetch(path,{credentials:'same-origin',cache:'no-store',referrerPolicy:'same-origin',...options,signal:controller.signal});let data=null;try{data=await response.json();}catch(_){}return {ok:response.ok,status:response.status,data};}finally{clearTimeout(timer);}
    }
    async function bootstrap(){
      const response=await request('/api/internal/team/csrf.php',{headers:{Accept:'application/json'}});
      if(!response.ok)throw new Error('bootstrap_unavailable');
      const data=response.data;if(typeof data?.csrf_token!=='string'||!data.csrf_token.startsWith('session.'))throw new Error('bootstrap_unavailable');
      return data.csrf_token;
    }
    function reset(){epoch++;base=null;draft=new Set();token='';known=true;outcomes=[];message='';lifecycleResult=null;}
    async function adopt(data){
      const version=++epoch;base=data;draft=new Set(row(data).grants);token='';known=true;outcomes=[];message='';lifecycleResult=null;mount();
      if(available()){try{const t=await bootstrap();if(version===epoch){token=t;mount();}}catch(_){if(version===epoch){token='';message='SÓLO CONSULTA: no se pudo validar el canal de cambios. Actualiza la consulta para volver a comprobarlo.';mount();}}}
    }
    async function confirm(title,description,html,accept='Confirmar cambios'){
      if(dialogOpen)return false;dialogOpen=true;
      $('confirmation-title').textContent=title;$('confirmation-description').textContent=description;$('confirmation-body').innerHTML=html;$('accept-confirmation').textContent=accept;
      const dialog=$('confirmation');
      return new Promise(resolve=>{
        const finish=value=>{dialog.close();dialogOpen=false;resolve(value);};
        $('cancel-confirmation').onclick=()=>finish(false);$('accept-confirmation').onclick=()=>finish(true);
        dialog.oncancel=e=>{e.preventDefault();finish(false);};dialog.showModal();$('cancel-confirmation').focus();
      });
    }
    async function canLeave(){
      if(busy||dialogOpen)return false;
      if(!dirty()&&known)return true;
      const discard=await confirm('¿Descartar la revisión pendiente?',known?'Hay cambios sin guardar para este integrante. No se enviarán al cambiar de contexto.':'El estado actual sigue pendiente de confirmar. Salir no reintenta ni revierte ninguna operación.','', 'Salir sin guardar');
      if(discard){draft=new Set(row(base)?.grants||[]);known=true;mount();}return discard;
    }
    function mount(){
      if(!base||!$('save-permissions'))return;
      const keys=editable(),enabled=!!token&&known&&!busy;
      document.querySelectorAll('#operational-matrix [data-capability]').forEach(input=>{
        const key=input.dataset.capability;input.disabled=!enabled||!keys.includes(key);input.checked=input.dataset.exposed==='true'&&draft.has(key);input.indeterminate=input.dataset.exposed==='false';input.setAttribute('aria-checked',input.indeterminate?'mixed':String(input.checked));
        input.onchange=()=>{if(!input.disabled){input.checked?draft.add(key):draft.delete(key);mount();}};
      });
      document.querySelectorAll('#operational-matrix .family').forEach(family=>{
        const inputs=[...family.querySelectorAll('[data-capability]')],group=family.querySelector('[data-group-count]');
        const eligible=inputs.filter(i=>keys.includes(i.dataset.capability)),visible=inputs.filter(i=>i.dataset.exposed==='true'),count=visible.filter(i=>draft.has(i.dataset.capability)).length;
        const unknown=visible.length!==inputs.length;
        group.disabled=!enabled||!eligible.length;group.checked=!unknown&&count===inputs.length;group.indeterminate=unknown||(count>0&&count<inputs.length);group.setAttribute('aria-checked',group.indeterminate?'mixed':String(group.checked));
        group.onchange=()=>{if(!group.disabled){const select=!eligible.every(i=>draft.has(i.dataset.capability));eligible.forEach(i=>select?draft.add(i.dataset.capability):draft.delete(i.dataset.capability));mount();}};
        family.querySelector('.family-count').textContent=`${count} de ${visible.length} permisos visibles · ${eligible.length} editables`;
        family.querySelector('.family-limit').textContent=unknown?'Otras asignaciones son desconocidas y no se modificarán.':eligible.length?'La selección es un borrador; requiere confirmación.':restriction();
      });
      $('lifecycle').disabled=!enabled||!lifecycleOperations().length;
      $('lifecycle').onclick=()=>changeLifecycle();
      if($('remove-member')){$('remove-member').disabled=!enabled||!removalAllowed();$('remove-member').onclick=()=>changeLifecycle('remove_advisor');}
      const ops=operations();$('save-permissions').disabled=!enabled||!ops.length;$('revert-permissions').disabled=busy||!ops.length;
      $('save-permissions').onclick=save;$('revert-permissions').onclick=()=>{draft=new Set(row(base).grants);mount();};
      $('pending-permissions').textContent=busy?'Procesando la revisión…':ops.length?`${ops.filter(x=>x.operation==='grant').length} concesiones y ${ops.filter(x=>x.operation==='revoke').length} retiros pendientes`:enabled?'Sin cambios pendientes.':restriction();
      $('pending-permissions').classList.toggle('dirty',ops.length>0);
      $('team-mode').textContent=token&&available()?'CAMBIOS CON CONFIRMACIÓN':'SÓLO CONSULTA';
      const panel=$('permission-results');panel.hidden=!message&&!outcomes.length;
      panel.innerHTML=`<p id="permission-result-title" tabindex="-1"><strong>${esc(message)}</strong></p>${outcomes.length?`<ul>${outcomes.map(o=>`<li data-outcome="${esc(o.state)}">${o.operation==='grant'?'Conceder':'Retirar'} ${esc(base.catalog[o.capability]?.label||'Permiso')} — ${esc({success:'Confirmado por el servidor',failed:'No aplicado: solicitud rechazada',unknown:'Resultado incierto; no se reintentó',not_attempted:'No intentado',sending:'Enviando…'}[o.state])}${o.observed?`<br>${esc(o.observed)}`:''}</li>`).join('')}</ul>`:''}${lifecycleResult?`<p data-lifecycle-outcome="${esc(lifecycleResult.state)}">${esc(lifecycleResult.operation==='remove_advisor'?'Baja definitiva':lifecycleResult.operation==='suspend'?'Suspender asesor':'Reactivar asesor')} — ${esc({success:'Comando confirmado por el servidor.',failed:'Solicitud rechazada; no aplicada.',unknown:'Resultado incierto; no se reintentó.',sending:'Enviando…'}[lifecycleResult.state])}</p>`:''}${!busy&&(outcomes.length||lifecycleResult||!known)?'<button id="reconcile-permissions" type="button">Consultar estado actual</button>':''}`;
      if($('reconcile-permissions'))$('reconcile-permissions').onclick=followUp;
    }
    async function current(target){return read({category:'ADVISOR',target});}
    function adoptCurrent(data){base=data;draft=new Set(row(data).grants);known=true;onData(data);}
    function describeObserved(data){
      outcomes.forEach(o=>{const exposed=data.authority.can_read_masters||data.authority.delegations.includes(o.capability);
        o.observed=!exposed?'Estado fuera del ámbito actual; no se puede confirmar.':`Lectura actual: ${row(data).grants.includes(o.capability)?'asignado':'sin asignación vigente'}. ${o.state==='unknown'?'Esta lectura no identifica quién realizó el cambio.':''}`;
      });
    }
    async function followUp(){
      if(busy||!base)return;if(dirty()&&!(await canLeave()))return;busy=true;token='';mount();
      try{const data=await current(row(base).account_reference);describeObserved(data);adoptCurrent(data);if(available())token=await bootstrap();message=(lifecycleResult?.state==='unknown'||outcomes.some(o=>o.state==='unknown'))?'Estado actual consultado; el resultado de la operación incierta no se atribuye.':'Estado actual consultado. Revisa antes de proponer nuevos cambios.';}
      catch(_){known=false;message='ESTADO PENDIENTE DE CONFIRMAR';}
      finally{busy=false;mount();$('permission-result-title')?.focus();}
    }
    async function changeLifecycle(requested=null){
      if(busy||!token||!known||(requested==='remove_advisor'?!removalAllowed():!lifecycleOperations().length))return;
      if(dirty()){message='Resuelve los permisos pendientes: guárdalos o usa Revertir antes de cambiar el acceso staff.';mount();$('permission-result-title')?.focus();return;}
      busy=true;const previous=base,target=row(base).account_reference,operation=requested||lifecycleOperations()[0],next=operation==='remove_advisor'?'REMOVED':operation==='suspend'?'SUSPENDED':'ACTIVE';let sent=false;
      mount();
      try{
        const fresh=await current(target);
        if(fingerprint(fresh)!==fingerprint(previous)){token='';adoptCurrent(fresh);message='CONFLICTO: cambió el integrante o tu autoridad. Actualiza y revisa antes de confirmar.';return;}
        token=await bootstrap();
        const removing=operation==='remove_advisor';
        const action=removing?'Dar de baja definitiva':operation==='suspend'?'Suspender asesor':'Reactivar asesor';
        const consequence=removing?'La relación staff quedará dada de baja definitivamente. Se revocarán todos sus permisos internos y delegaciones. No podrá reactivarse desde esta pantalla. La cuenta Identity y el historial se conservan.':operation==='suspend'?'Se bloqueará su siguiente acceso operativo como staff.':'El acceso seguirá sujeto a sus permisos vigentes, MFA y controles de cada función. No se habilita una cuenta desactivada ni se restauran permisos revocados.';
        if(!(await confirm(action,`${displayName()} · Cuenta: ${target}.`, `<p>${consequence}</p><p>${removing?'No se eliminan datos ni se alteran relaciones médicas o comerciales.':'Se conservan permisos e historia. No se acredita identidad ni se deciden comisiones futuras.'}</p>`,action)))return;
        const latest=await current(target);
        if(fingerprint(latest)!==fingerprint(previous)){token='';adoptCurrent(latest);message='CONFLICTO: cambió el integrante o tu autoridad durante la confirmación. Revisa nuevamente.';return;}
        outcomes=[];lifecycleResult={operation,state:'sending'};message='Actualizando acceso staff…';mount();sent=true;
        try{
          const response=await request('/api/internal/team/lifecycle.php',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-Token':token},body:JSON.stringify({operation,target})});
          const data=response.data;
          lifecycleResult.state=response.ok&&data?.result==='changed'&&data.operation===operation&&data.target===target&&data.staff_status===next?'success':[400,401,403,405,409,413,415].includes(response.status)?'failed':'unknown';
          message=lifecycleResult.state==='success'?'Confirmando estado actual…':lifecycleResult.state==='unknown'?'Consultando resultado incierto…':response.status===409?'CONFLICTO: no se aplicó un nuevo cambio. Revisa el estado actual.':'La solicitud fue rechazada. Revisa el estado actual.';
        }catch(_){lifecycleResult.state='unknown';}
        token='';
        try{
          const data=await current(target);adoptCurrent(data);
          const consistent=fingerprint({...previous,items:[{...row(previous),staff_status:next,editable_capabilities:row(data).editable_capabilities,lifecycle_operations:row(data).lifecycle_operations,removal_allowed:row(data).removal_allowed,...(operation==='remove_advisor'?{grants:[]}:{} )}]})===fingerprint(data);
          if(lifecycleResult.state==='success')message=consistent?'CAMBIO DE ACCESO CONFIRMADO':'CONFLICTO: el comando fue confirmado, pero el estado actual cambió. Revisa nuevamente.';
          if(lifecycleResult.state==='unknown')message='ESTADO PENDIENTE DE CONFIRMAR · Lectura actual consultada; no permite atribuir el cambio al comando incierto.';
          if(available()){try{token=await bootstrap();}catch(_){token='';}}
        }catch(_){known=false;message='ESTADO PENDIENTE DE CONFIRMAR';}
      }catch(_){token='';known=false;message=sent?'ESTADO PENDIENTE DE CONFIRMAR':'No se pudo validar el estado y la autoridad actuales. No se envió el cambio de acceso.';}
      finally{busy=false;mount();if(message)$('permission-result-title')?.focus();else $('lifecycle')?.focus();}
    }
    async function save(){
      if(busy||!token||!dirty()||!known)return;
      busy=true;const confirmedBase=base,target=row(base).account_reference,ops=operations();mount();let sent=false;
      try{
        const fresh=await current(target);
        if(fingerprint(fresh)!==fingerprint(confirmedBase)){token='';adoptCurrent(fresh);message='CONFLICTO: cambió el estado o tu ámbito. Revisa la nueva lectura y prepara de nuevo los cambios.';return;}
        token=await bootstrap();
        const list=ops.map(o=>`<li>${o.operation==='grant'?'Conceder':'Retirar'} ${esc(base.catalog[o.capability].label)}${o.capability==='media_review_source_download'?' — permite acceder a originales privados, sujeto a los controles de la función.':o.capability==='media_review_approve'?' — permite aprobar publicación, sujeto a los controles de la función.':''}</li>`).join('');
        const agreed=await confirm('Confirmar permisos del asesor',`${displayName()} · Cuenta: ${target}. ${ops.filter(o=>o.operation==='grant').length} concesiones y ${ops.filter(o=>o.operation==='revoke').length} retiros. Cada cambio se confirma individualmente; puede haber resultados parciales.`,`<div class="change-list"><ul>${list}</ul></div>`);
        if(!agreed)return;
        const latest=await current(target);
        if(fingerprint(latest)!==fingerprint(confirmedBase)){token='';adoptCurrent(latest);message='CONFLICTO: cambió el estado o tu ámbito. Revisa y confirma una nueva propuesta.';return;}
        lifecycleResult=null;outcomes=ops.map(o=>({...o,state:'not_attempted'}));message='Aplicando cambios individuales…';let expected=new Set(row(confirmedBase).grants),consistent=true,latestRead=null;
        for(const outcome of outcomes){
          outcome.state='sending';mount();sent=true;
          try{
            const response=await request('/api/internal/team/capability.php',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-Token':token},body:JSON.stringify({operation:outcome.operation,target,capability:outcome.capability})});
            const data=response.data;
            outcome.state=response.ok&&data?.result==='changed'&&data.target===target&&data.operation===outcome.operation&&data.capability===outcome.capability&&data.stored_grant===(outcome.operation==='grant'?'ACTIVE':'REVOKED')?'success':[400,401,403,405,409,413,415].includes(response.status)?'failed':'unknown';
            if(outcome.state==='failed')outcome.observed=response.status===409?'Conflicto del servidor; requiere nueva revisión.':'Sesión, autoridad o solicitud no vigentes; secuencia detenida.';
          }catch(_){outcome.state='unknown';}
          if(outcome.state==='success')outcome.operation==='grant'?expected.add(outcome.capability):expected.delete(outcome.capability);
          try{
            latestRead=await current(target);known=true;describeObserved(latestRead);
            consistent=sameScope(confirmedBase,latestRead)&&JSON.stringify([...expected].sort())===JSON.stringify([...row(latestRead).grants].sort());
          }catch(_){latestRead=null;known=false;consistent=false;}
          mount();if(outcome.state!=='success'||!consistent)break;
        }
        token='';if(latestRead)adoptCurrent(latestRead);else known=false;
        const succeeded=outcomes.filter(o=>o.state==='success').length,uncertain=outcomes.some(o=>o.state==='unknown');
        message=!known?'ESTADO PENDIENTE DE CONFIRMAR':succeeded===outcomes.length&&consistent?'CAMBIOS CONFIRMADOS':succeeded?'CAMBIOS APLICADOS PARCIALMENTE':uncertain?'ESTADO PENDIENTE DE CONFIRMAR · Lectura actual consultada; resultado de la operación incierto.':'No se completaron los cambios. Revisa el estado actual.';
        if(succeeded&&!known)message='CAMBIOS APLICADOS PARCIALMENTE · ESTADO PENDIENTE DE CONFIRMAR';
        else if(succeeded&&uncertain)message='CAMBIOS APLICADOS PARCIALMENTE · Hay una operación con resultado incierto.';
        if(known&&available()){try{token=await bootstrap();}catch(_){token='';}}
      }catch(_){token='';known=false;message=sent?'ESTADO PENDIENTE DE CONFIRMAR':'No se pudo validar el estado y la autoridad actuales. No se enviaron cambios.';}
      finally{busy=false;mount();if(message)$('permission-result-title')?.focus();else $('save-permissions')?.focus();}
    }
    window.addEventListener('beforeunload',e=>{if(dirty()||busy||!known){e.preventDefault();e.returnValue='';}});
    return {adopt,reset,mount,canLeave,dirty,get busy(){return busy||dialogOpen;},get uncertain(){return !known;}};
  };
})();
