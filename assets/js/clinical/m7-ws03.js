// M7 WS03: encounter-owned observations and explicit three-state physical examination.
(function(){
  window.mxmedM7WS03 = function(root, encounterUrl, currentPatient, onTerminal){
    const q = selector => root.querySelector(selector);
    const measurementPanel = q('[data-m7-measurements]');
    const examPanel = q('[data-m7-exam]');
    const form = q('[data-m7-measurements-form]');
    const code = q('[data-m7-measurement-code]');
    const value = q('[data-m7-measurement-value]');
    const valueLabel = q('[data-m7-measurement-value-label]');
    const pressureLabel = q('[data-m7-measurement-pressure-label]');
    const systolic = q('[data-m7-measurement-systolic]');
    const diastolic = q('[data-m7-measurement-diastolic]');
    const unit = q('[data-m7-measurement-unit]');
    const time = q('[data-m7-measurement-time]');
    const source = q('[data-m7-measurement-source]');
    const measurementState = q('[data-m7-measurements-state]');
    const measurementList = q('[data-m7-measurements-list]');
    const priorList = q('[data-vis29-prior]');
    const formTitle = q('[data-vis29-form-title]');
    const invalidationDialog = q('[data-meas01-confirm]');
    const pendingDialog = q('[data-vis29-pending-dialog]');
    const pendingRegister = q('[data-vis29-pending-register]');
    const pendingHint = q('[data-vis29-pending-hint]');
    const recoveredDraft = q('[data-vis30-measurement-draft]');
    const recoveredDraftCopy = q('[data-vis30-measurement-draft-copy]');
    const recoverDraft = q('[data-vis30-measurement-recover]');
    const discardRecoveredDraft = q('[data-vis30-measurement-discard]');
    let priorEpoch=0, priorRows=[], reuseCandidate=null, lastCode='blood_pressure', noticeTimer=0, createPending=false;
    const measurementConflict = q('[data-m7-measurement-conflict]');
    const measurementDraft = q('[data-m7-measurement-draft]');
    const measurementServer = q('[data-m7-measurement-server]');
    const examState = q('[data-m7-exam-state]');
    const examMeta = q('[data-m7-exam-meta]');
    const examSystems = q('[data-m7-exam-systems]');
    const examSave = q('[data-m7-exam-save]');
    const examDraftCue = q('[data-vis30-exam-draft]');
    const examDraftRecover = q('[data-vis30-exam-recover]');
    const examDraftDiscard = q('[data-vis30-exam-discard]');
    const examConflict = q('[data-m7-exam-conflict]');
    const examDraft = q('[data-m7-exam-draft]');
    const examServer = q('[data-m7-exam-server]');
    const catalog = {
      blood_pressure:['Presión arterial','mmHg'],heart_rate:['Frecuencia cardíaca','bpm'],
      respiratory_rate:['Frecuencia respiratoria','rpm'],temperature:['Temperatura','°C'],
      oxygen_saturation:['Saturación de oxígeno','%'],pain:['Dolor','score'],
      weight:['Peso','kg'],height:['Estatura','cm'],waist:['Cintura','cm']
    };
    const systems = {
      general:'Estado general', cardiovascular:'Cardiovascular', respiratory:'Respiratorio',
      abdomen:'Abdomen', neurological:'Neurológico', musculoskeletal:'Musculoesquelético', skin:'Piel'
    };
    let key = '', patient = '', mode = 'none', selected = '', observations = [], selectedObservation = null;
    let baselineMeasurement = '', baselineExam = '', examVersion = null, busy = false;
    let measurementLocked = false, examLocked = false;
    let createKey = '', loadedExam = {}, measurementNotice = '', examNotice = '', pendingDecision = false;
    let retainedMeasurementDraft = '', retainedExamDraft = '', availableMeasurementDraft = '', unavailableMeasurementDraft = false, availableExamDraft = '';
    const drafts = new Map();
    const hide = (node, visible)=>node.classList.toggle('d-none', !visible);
    const draftId = type=>`mxmed.m7.ws03.draft:${key}:${type}`;
    const toLocalDateTime = stamp=>{
      if(!stamp) return '';
      const date=new Date(String(stamp).replace(' ','T').slice(0,19)+'Z');
      if(Number.isNaN(date.getTime())) return '';
      const local=new Date(date.getTime()-date.getTimezoneOffset()*60000);
      return local.toISOString().slice(0,16);
    };
    const toUtcDateTime = local=>new Date(`${local}:00`).toISOString().slice(0,19).replace('T',' ');
    const createKeyId = ()=>`mxmed.m7.ws03.create-key:${key}`;
    function forgetCreateKey(){
      createKey=''; try{sessionStorage.removeItem(createKeyId());}catch(_){}
    }
    function getDraft(type){
      const id=draftId(type);
      if(drafts.has(id)) return drafts.get(id);
      try { return sessionStorage.getItem(id); } catch(_){ return null; }
    }
    function setDraft(type, text){
      if(!key) return;
      const id=draftId(type); drafts.set(id,text);
      try { sessionStorage.setItem(id,text); } catch(_){}
    }
    function clearDraft(type){
      const id=draftId(type); drafts.delete(id);
      try { sessionStorage.removeItem(id); } catch(_){}
    }
    function measurementSnapshot(){
      return JSON.stringify({ observation_id:selectedObservation?.observation_id || null,
        reuse_candidate:reuseCandidate, code:code.value, value:value.value, systolic:systolic.value, diastolic:diastolic.value,
        time:time.value, source:source.value });
    }
    function classifyMeasurementDraft(raw){
      try{
        const draft=JSON.parse(raw);
        if(!draft || typeof draft!=='object' || Array.isArray(draft)) return {meaningful:false,unavailable:false};
        if(draft.reuse_candidate?.observation_id && catalog[draft.reuse_candidate.code]) return {meaningful:true,unavailable:false};
        const text=name=>String(draft[name]??'').trim();
        if(draft.observation_id){
          const row=observations.find(item=>Number(item.observation_id)===Number(draft.observation_id));
          if(!row) return {meaningful:true,unavailable:true};
          return {meaningful:text('code')!==String(row.code||'') || text('value')!==String(row.value_numeric??'') ||
            text('systolic')!==String(row.systolic_mm_hg??'') || text('diastolic')!==String(row.diastolic_mm_hg??'') ||
            text('time')!==toLocalDateTime(row.effective_at) || text('source')!==String(row.source||''),unavailable:false};
        }
        return {meaningful:['value','systolic','diastolic','time','source'].some(name=>text(name)!==''),unavailable:false};
      }catch(_){return {meaningful:false,unavailable:false};}
    }
    function examSnapshot(){
      const result={};
      examSystems.querySelectorAll('[data-m7-exam-system]').forEach(row=>{
        result[row.dataset.m7ExamSystem]={state:row.querySelector('select').value,finding:row.querySelector('input').value};
      });
      return JSON.stringify(result);
    }
    function examDraftIsMeaningful(raw){
      try{
        const draft=JSON.parse(raw), baseline=JSON.parse(baselineExam);
        if(!draft || typeof draft!=='object' || Array.isArray(draft)) return false;
        return Object.keys(systems).some(name=>{
          const saved=baseline[name]||{}, pending=draft[name]||{};
          const savedState=saved.state||'NOT_REVIEWED', pendingState=pending.state||'NOT_REVIEWED';
          return savedState!==pendingState || (pendingState==='ABNORMAL' && String(saved.finding||'')!==String(pending.finding||''));
        });
      }catch(_){return false;}
    }
    function isDirty(){
      return mode==='open' && (selected==='measurements' ? measurementSnapshot()!==baselineMeasurement || [value,systolic,diastolic,time].some(control=>control.validity.badInput&&!control.closest('.d-none'))
        : selected==='physical_exam' ? examSnapshot()!==baselineExam : false);
    }
    function remember(){
      if(!isDirty()) return;
      setDraft(selected, selected==='measurements' ? measurementSnapshot() : examSnapshot());
    }
    function restore(type, inputDraft=null){
      const raw=inputDraft ?? getDraft(type); if(!raw) return;
      try{
        const draft=JSON.parse(raw);
        if(type==='measurements'){
          const row=observations.find(item=>Number(item.observation_id)===Number(draft.observation_id));
          selectedObservation=row || null;reuseCandidate=draft.reuse_candidate||null;
          code.value=draft.code || 'blood_pressure'; value.value=draft.value || '';
          systolic.value=draft.systolic || ''; diastolic.value=draft.diastolic || '';
          time.value=draft.time || ''; source.value=draft.source || '';
          syncCode();
        } else {
          Object.entries(draft).forEach(([name,item])=>{
            const row=[...examSystems.querySelectorAll('[data-m7-exam-system]')].find(node=>node.dataset.m7ExamSystem===name);
            if(row){ row.querySelector('select').value=item.state || 'NOT_REVIEWED'; row.querySelector('input').value=item.finding || ''; syncExamRow(row); }
          });
        }
      }catch(_){}
    }
    function syncCode(){
      lastCode=code.value;
      const isPressure=code.value==='blood_pressure';
      hide(valueLabel,!isPressure); hide(pressureLabel,isPressure);
      value.required=!isPressure; systolic.required=isPressure; diastolic.required=isPressure;
      unit.value=catalog[code.value]?.[1] || '';
    }
    function measurementPayload(){
      const date=time.value.trim();
      const data={code:code.value,unit:unit.value,source:source.value,effective_at:toUtcDateTime(date),provenance:selectedObservation?.provenance || {}};
      if(code.value==='blood_pressure'){
        data.systolic_mm_hg=Number(systolic.value); data.diastolic_mm_hg=Number(diastolic.value);
      } else data.value_numeric=Number(value.value);
      return data;
    }
    function fillMeasurement(row, discardDraft=true, preserveRetainedDraft=false){
      reuseCandidate=null;
      availableMeasurementDraft='';unavailableMeasurementDraft=false;
      if(!preserveRetainedDraft){retainedMeasurementDraft='';q('[data-m7-measurement-use-draft]').disabled=true;}
      selectedObservation=row || null;
      code.value=row?.code || 'blood_pressure'; value.value=row?.value_numeric ?? '';
      systolic.value=row?.systolic_mm_hg ?? ''; diastolic.value=row?.diastolic_mm_hg ?? '';
      time.value=toLocalDateTime(row?.effective_at);
      source.querySelector('option[value="import"]').disabled=!row;
      source.value=row?.source || ''; syncCode();
      baselineMeasurement=measurementSnapshot();
      if(discardDraft) clearDraft('measurements');
      if(discardDraft){forgetCreateKey();createPending=false;}
      measurementLocked=false; measurementNotice=''; hide(measurementConflict,false);
      paintMeasurement();
    }
    // Presentation only: keep exact canonical numbers in capture and requests.
    const numberText = input => input!==null && input!=='' && Number.isFinite(Number(input)) ? new Intl.NumberFormat('es-MX',{maximumFractionDigits:2,useGrouping:false}).format(Number(input)) : '—';
    const reading = row => `${row.code==='blood_pressure' ? `${numberText(row.systolic_mm_hg)}/${numberText(row.diastolic_mm_hg)}` : numberText(row.value_numeric)} ${row.unit}`;
    function dateParts(row){
      if(row.effective_at_authority!=='EXPLICIT_EFFECTIVE_TIME') return {day:'Fecha de medición no confirmada',time:''};
      const date=new Date(String(row.effective_at).replace(' ','T').slice(0,19)+'Z');
      if(Number.isNaN(date.getTime())) return {day:'Fecha de medición no disponible',time:''};
      return {day:date.toLocaleDateString('es-MX',{day:'numeric',month:'short',year:'numeric'}),time:date.toLocaleTimeString('es-MX',{hour:'numeric',minute:'2-digit'})};
    }
    function canReplaceCapture(){
      if(busy||measurementLocked||createPending||mode!=='open') return false;
      return !isDirty()||window.confirm('Hay una medición pendiente. ¿Descartar esta captura?');
    }
    function renderRows(target,rows,prior=false){
      target.replaceChildren();
      if(!rows.length){const empty=document.createElement('p');empty.className='vis29-empty';empty.textContent=prior?'No hay valores previos elegibles.':'Aún no hay mediciones registradas en esta consulta.';target.append(empty);return;}
      rows.forEach(row=>{
        const dates=dateParts(row);
        const line=document.createElement('div');line.className='vis29-reading';
        const priorSelected=prior&&!!reuseCandidate&&String(row.observation_id)===String(reuseCandidate.observation_id)&&String(row.encounter_key)===String(reuseCandidate.encounter_key);
        line.classList.toggle('vis29-prior-selected',priorSelected);
        const name=document.createElement('span');name.textContent=catalog[row.code]?.[0]||row.code;
        const val=document.createElement('strong');val.textContent=reading(row);
        const timeLabel=document.createElement('small');timeLabel.textContent=[dates.day,dates.time].filter(Boolean).join(' · ');
        line.append(name,val,timeLabel);
        if(mode==='open'){
          const button=document.createElement('button');button.type='button';button.className='btn btn-link';button.textContent=prior?'Usar valor':'Editar';button.disabled=busy||measurementLocked||createPending||!!availableMeasurementDraft;
          if(prior)button.setAttribute('aria-pressed',String(priorSelected));
          button.setAttribute('aria-label',`${button.textContent}: ${name.textContent} · ${val.textContent}`);
          button.addEventListener('click',()=>{
            if(!canReplaceCapture()) return;
            if(prior){
              fillMeasurement(null);reuseCandidate=row;code.value=row.code;syncCode();
              value.value=row.value_numeric??'';systolic.value=row.systolic_mm_hg??'';diastolic.value=row.diastolic_mm_hg??'';
              time.value=toLocalDateTime(new Date().toISOString());source.value='';
              setDraft('measurements',measurementSnapshot());paintMeasurement();
            }else fillMeasurement(row);
            formTitle.scrollIntoView({block:'nearest',behavior:'auto'});
            (code.value==='blood_pressure'?systolic:value).focus();
          });
          if(prior)line.append(button);
          else {
            const actions=document.createElement('div');actions.className='meas01-row-actions';actions.append(button);
            const remove=document.createElement('button');remove.type='button';remove.className='btn btn-link meas01-remove';remove.textContent='Eliminar';
            remove.setAttribute('aria-label',`Eliminar: ${name.textContent} · ${val.textContent}`);
            remove.disabled=busy||measurementLocked||createPending||!!availableMeasurementDraft;
            remove.addEventListener('click',()=>invalidateMeasurement(row));actions.append(remove);line.append(actions);
          }
        }
        target.append(line);
      });
    }
    async function loadPrior(encounterId){
      const run=++priorEpoch, expectedPatient=patient, expectedKey=key;
      priorRows=[];priorList.textContent='Consultando valores previos…';
      try{
        if(!Number.isSafeInteger(Number(encounterId))||Number(encounterId)<1) throw new Error('Missing encounter');
        const response=await fetch(`/api/clinical/index.php/patients/${encodeURIComponent(patient)}/longitudinal/measurements?view=prior&exclude_encounter_id=${encodeURIComponent(encounterId)}`,{credentials:'same-origin',headers:{Accept:'application/json'}});
        const result=await responseJson(response);
        if(run!==priorEpoch||key!==expectedKey||patient!==expectedPatient||currentPatient()!==patient)return;
        priorRows=result.data.items||[];renderRows(priorList,priorRows,true);
      }catch(_){if(run===priorEpoch)priorList.textContent='No se pudieron cargar los valores previos.';}
    }
    function paintMeasurement(){
      renderRows(measurementList,observations);
      if(priorRows.length)renderRows(priorList,priorRows,true);
      formTitle.textContent=selectedObservation?'Editar medición':'Registrar valores';
      hide(recoveredDraft,!!availableMeasurementDraft);
      recoveredDraftCopy.textContent=unavailableMeasurementDraft?'Hay una captura pendiente recuperada, pero la medición original ya no está disponible. Descarta este borrador para continuar.':'Hay una captura pendiente recuperada de esta consulta. Elige si deseas retomarla o descartarla.';
      recoverDraft.disabled=mode!=='open'||busy||unavailableMeasurementDraft;
      discardRecoveredDraft.disabled=mode!=='open'||busy;
      measurementState.textContent=measurementNotice || (mode!=='open'?'Sólo lectura':measurementLocked?'Conflicto: revisa la versión guardada':busy?'Guardando…':reuseCandidate?'Valor previo preparado. Selecciona el origen y agrégalo, o cancela la captura antes de cambiar de paso.':'');
      [...form.elements].forEach(control=>{ if(!control.closest('[data-vis30-measurement-draft]')) control.disabled=mode!=='open'||busy||measurementLocked||createPending||!!availableMeasurementDraft; });
      source.querySelector('option[value="import"]').disabled=!selectedObservation;
      const save=q('[data-m7-measurement-save]'),cancel=q('[data-m7-measurement-new]');
      save.textContent=selectedObservation?'Guardar cambios':'Agregar a esta consulta';
      save.disabled=mode!=='open'||busy||measurementLocked||!!availableMeasurementDraft||!isDirty();
      cancel.textContent=selectedObservation?'Cancelar edición':'Cancelar captura';
      cancel.disabled=mode!=='open'||busy||measurementLocked||createPending||!!availableMeasurementDraft;
      hide(cancel,!!selectedObservation||isDirty());
      hide(form,!!key);
    }
    function syncExamRow(row){
      const state=row.querySelector('select').value;
      const finding=row.querySelector('input');
      finding.disabled=mode!=='open'||state!=='ABNORMAL'||busy||examLocked||!!availableExamDraft;
      finding.required=state==='ABNORMAL';
      if(state!=='ABNORMAL') finding.value='';
    }
    function paintExam(){
      hide(examDraftCue,!!availableExamDraft);
      examDraftRecover.disabled=mode!=='open'||busy;
      examDraftDiscard.disabled=mode!=='open'||busy;
      examState.textContent=examNotice || (mode!=='open'?'Sólo lectura':examLocked?'Conflicto: revisa la versión guardada':busy?'Guardando…':isDirty()?'Cambios sin guardar':'');
      examMeta.textContent=examVersion===null?'Aún no hay exploración guardada. Sin revisión no equivale a normal.':`Versión ${examVersion} · Sin revisión no equivale a normal.`;
      examSystems.querySelectorAll('[data-m7-exam-system]').forEach(row=>{
        row.querySelector('select').disabled=mode!=='open'||busy||examLocked||!!availableExamDraft;
        syncExamRow(row);
      });
      examSave.disabled=mode!=='open'||busy||examLocked||!!availableExamDraft||!isDirty();
      hide(examSave,mode==='open');
    }
    function fillExamFromSaved(){
      const saved=loadedExam.payload?.systems || {};
      examSystems.querySelectorAll('[data-m7-exam-system]').forEach(row=>{
        const entry=saved[row.dataset.m7ExamSystem];
        row.querySelector('select').value=entry?.state || 'NOT_REVIEWED';
        row.querySelector('input').value=entry?.state==='ABNORMAL'?String(entry.finding || ''):'';
      });
      baselineExam=examSnapshot();
    }
    function load(detail, encounterKey, state, restoreDrafts=true){
      if(key!==encounterKey && pendingDialog.open) pendingDialog.close('stay');
      if(key!==encounterKey) createKey='';
      key=encounterKey; patient=String(detail.patient_id || ''); mode=state;
      clearTimeout(noticeTimer);reuseCandidate=null;createPending=false;
      loadPrior(detail.encounter_id);
      observations=Array.isArray(detail.observations)?detail.observations:[];
      loadedExam=detail.sections?.physical_exam || {};
      examVersion=loadedExam.row_version==null?null:Number(loadedExam.row_version);
      availableExamDraft='';
      fillExamFromSaved();
      fillMeasurement(null,false);
      measurementLocked=false; examLocked=false; measurementNotice=''; examNotice=''; hide(examConflict,false); hide(measurementConflict,false);
      if(mode==='open'){
        const savedDraft=getDraft('measurements');
        const classification=classifyMeasurementDraft(savedDraft);
        let unresolvedCreate=false;
        try{unresolvedCreate=!!sessionStorage.getItem(createKeyId());}catch(_){}
        if(savedDraft && classification.meaningful){
          if(unresolvedCreate){
            restore('measurements',savedDraft);
            createPending=!selectedObservation;
            measurementNotice='Hay una captura pendiente recuperada cuyo registro no se confirmó. Reintenta la misma solicitud.';
          }else{
            availableMeasurementDraft=savedDraft;
            unavailableMeasurementDraft=classification.unavailable;
          }
        }else{
          if(savedDraft) clearDraft('measurements');
          if(unresolvedCreate) forgetCreateKey();
        }
        if(restoreDrafts){
          const examDraft=getDraft('physical_exam');
          if(examDraft){
            if(examDraftIsMeaningful(examDraft)) availableExamDraft=examDraft;
            else clearDraft('physical_exam');
          }
        }
      }
      paintMeasurement(); paintExam();
    }
    function select(type){
      selected=type;
      hide(measurementPanel,type==='measurements'); hide(examPanel,type==='physical_exam');
      if(type==='measurements') paintMeasurement();
      if(type==='physical_exam') paintExam();
    }
    function reset(){
      if(invalidationDialog.open)invalidationDialog.close('cancel');
      if(pendingDialog.open)pendingDialog.close('stay');
      ++priorEpoch;priorRows=[];reuseCandidate=null;priorList.replaceChildren();clearTimeout(noticeTimer);createPending=false;
      key=''; patient=''; mode='none'; selected=''; observations=[]; selectedObservation=null;
      createKey=''; retainedMeasurementDraft=''; availableMeasurementDraft=''; unavailableMeasurementDraft=false; availableExamDraft=''; measurementNotice=''; examNotice='';
      hide(measurementPanel,false); hide(examPanel,false);
    }
    async function responseJson(response){
      const data=await response.json().catch(()=>null);
      if(!response.ok || data?.ok!==true){
        const error=new Error(data?.error?.message || data?.message || 'No se pudo guardar.');
        error.status=response.status;
        error.code=typeof data?.error==='string'?data.error:String(data?.error?.code || response.status);
        throw error;
      }
      return data;
    }
    async function reloadCurrent(){
      const response=await fetch(encounterUrl(key),{credentials:'same-origin',headers:{Accept:'application/json'}});
      const data=await responseJson(response);
      if(String(data.data?.patient_id || '')!==patient || currentPatient()!==patient) throw new Error('El contexto del paciente cambió.');
      return data.data;
    }
    function terminal(detail){
      mode='terminal';
      onTerminal?.();
      if(selected==='measurements' && getDraft('measurements')){
        measurementDraft.textContent=getDraft('measurements');
        const row=detail?.observations?.find(item=>Number(item.observation_id)===Number(selectedObservation?.observation_id));
        measurementServer.textContent=row?`${catalog[row.code]?.[0]||row.code}: ${row.code==='blood_pressure'?`${row.systolic_mm_hg}/${row.diastolic_mm_hg}`:row.value_numeric} ${row.unit} · v${row.row_version}`:'Sin versión guardada para esta medición.';
        measurementConflict.querySelector('strong').textContent='La consulta terminó. El borrador no se guardó.';
        hide(measurementConflict,true);
      }
      if(selected==='physical_exam' && getDraft('physical_exam')){
        examDraft.textContent=getDraft('physical_exam');
        examServer.textContent=JSON.stringify(detail?.sections?.physical_exam?.payload?.systems || {},null,2);
        examConflict.querySelector('strong').textContent='La consulta terminó. El borrador no se guardó.';
        hide(examConflict,true);
      }
      root.querySelector('[data-m7-body]').dataset.encounterState=String(detail?.status || 'closed');
      root.querySelector('[data-m7-context]').textContent=`Consulta histórica · ${detail?.status==='voided'?'Anulada':'Finalizada'}`;
      root.querySelector('[data-m7-status]').textContent='Consulta histórica de sólo lectura.';
      paintMeasurement(); paintExam();
    }
    async function invalidateMeasurement(row){
      if(mode!=='open'||busy||measurementLocked||createPending||invalidationDialog.open)return;
      if(selectedObservation?.observation_id===row.observation_id&&isDirty()){
        measurementNotice='Guarda o cancela la edición de este valor antes de eliminarlo.';paintMeasurement();return;
      }
      const oldKey=key,oldPatient=patient;
      invalidationDialog.returnValue='cancel';
      const confirmed=new Promise(resolve=>invalidationDialog.addEventListener('close',()=>resolve(invalidationDialog.returnValue==='confirm'),{once:true}));
      invalidationDialog.showModal();
      if(!await confirmed || key!==oldKey || patient!==oldPatient || currentPatient()!==oldPatient || mode!=='open' || busy)return;
      busy=true;clearTimeout(noticeTimer);measurementNotice='Eliminando…';paintMeasurement();
      try{
        const response=await fetch(`${encounterUrl(oldKey)}/observations/${row.observation_id}/void`,{
          method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json'},
          body:JSON.stringify({patient_id:oldPatient,row_version:Number(row.row_version)})
        });
        const result=await responseJson(response);
        if(!result.data?.invalidated_at || Number(result.data.observation_id)!==Number(row.observation_id))throw new Error('INVALID_RESPONSE');
        if(key!==oldKey||patient!==oldPatient||currentPatient()!==oldPatient)return;
        const detail=await reloadCurrent();
        if(key!==oldKey||patient!==oldPatient||currentPatient()!==oldPatient)return;
        observations=detail.observations||[];
        if(selectedObservation?.observation_id===row.observation_id)fillMeasurement(null);
        measurementNotice='Medición eliminada';
        noticeTimer=window.setTimeout(()=>{if(key===oldKey&&measurementNotice==='Medición eliminada'){measurementNotice='';paintMeasurement();}},2500);
      }catch(error){
        if(key!==oldKey||patient!==oldPatient||currentPatient()!==oldPatient)return;
        measurementNotice=error.code==='VERSION_CONFLICT'?'El valor cambió. Actualiza la consulta y revisa la medición antes de eliminarla.'
          :error.code==='ENCOUNTER_TERMINAL'?'La consulta terminó. No se eliminó la medición.'
          :'No se confirmó la eliminación. El valor permanece visible; actualiza la consulta para comprobar su estado.';
      }finally{busy=false;paintMeasurement();}
    }

    async function saveMeasurement(event, explicitPrior=false){
      event?.preventDefault();
      if(mode!=='open'||busy||measurementLocked) return false;
      if(!isDirty()) return true;
      if(reuseCandidate&&!event&&!explicitPrior){measurementNotice='Confirma el valor previo con “Agregar a esta consulta” o cancela la captura.';paintMeasurement();return false;}
      if(!form.checkValidity()){
        measurementNotice=!source.value?'Completa el origen o cancela esta captura antes de cambiar de paso.':'Completa el valor y la fecha, o cancela esta captura antes de cambiar de paso.';
        paintMeasurement();form.reportValidity();return false;
      }
      clearTimeout(noticeTimer);measurementNotice='';
      const oldKey=key, oldPatient=patient, draft=measurementSnapshot();
      const row=selectedObservation, data=measurementPayload();
      if(!row&&!createKey){
        try{createKey=sessionStorage.getItem(createKeyId()) || '';}catch(_){}
        if(!createKey) createKey=crypto.randomUUID ? crypto.randomUUID() : [...crypto.getRandomValues(new Uint8Array(16))].map(byte=>byte.toString(16).padStart(2,'0')).join('');
        try{sessionStorage.setItem(createKeyId(),createKey);}catch(_){}
      }
      createPending=!row;busy=true; paintMeasurement();
      try{
        const url=row?`${encounterUrl(key)}/observations/${row.observation_id}`:`${encounterUrl(key)}/observations`;
        const payload=row?{...data,row_version:Number(row.row_version)}:data;
        const response=await fetch(url,{method:row?'PATCH':'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json',...(row?{}:{'Idempotency-Key':createKey})},body:JSON.stringify(payload)});
        const result=await responseJson(response);
        if(key!==oldKey||patient!==oldPatient||currentPatient()!==patient) return;
        const saved=result.data;
        observations=[saved,...observations.filter(item=>Number(item.observation_id)!==Number(saved.observation_id))].filter(item=>!item.invalidated_at);
        fillMeasurement(row?saved:null);
        const successNotice=saved.invalidated_at?'Este registro ya fue eliminado; no se agregó a los valores vigentes.':row?'Cambios guardados':'Medición agregada';
        measurementNotice=successNotice;
        noticeTimer=window.setTimeout(()=>{if(key===oldKey&&measurementNotice===successNotice){measurementNotice='';paintMeasurement();}},2500);
        return true;
      }catch(error){
        // An ambiguous create retains its idempotency key and payload for an explicit safe retry.
        if(key!==oldKey||patient!==oldPatient||currentPatient()!==patient)return false;
        createPending=!row&&(!error.code||error.status>=500||String(error.code).includes('IDEMPOTENCY'));
        if(!row&&!createPending)forgetCreateKey();
        setDraft('measurements',draft);
        if(error.code==='VERSION_CONFLICT'){
          measurementLocked=true; hide(measurementConflict,true);
          retainedMeasurementDraft=draft; measurementDraft.textContent=JSON.stringify(JSON.parse(draft),null,2);
          q('[data-m7-measurement-use-draft]').disabled=true;
          try{ const detail=await reloadCurrent(); const server=detail.observations?.find(item=>Number(item.observation_id)===Number(row?.observation_id)); measurementServer.textContent=server?`${catalog[server.code]?.[0]||server.code}: ${server.code==='blood_pressure'?`${server.systolic_mm_hg}/${server.diastolic_mm_hg}`:server.value_numeric} ${server.unit} · ${server.effective_at} · v${server.row_version}`:'Versión guardada no disponible.'; }catch(_){measurementServer.textContent='No se pudo cargar la versión guardada.';}
        } else if(['ENCOUNTER_TERMINAL','ENCOUNTER_CLOSED','ENCOUNTER_VOIDED'].includes(error.code)){
          measurementNotice='La consulta terminó. Tu borrador se conserva para copiarlo.';
          try{terminal(await reloadCurrent());}catch(_){terminal();}
        } else measurementNotice=error.code==='M6_WRITE_WINDOW_BLOCKED'?'Escrituras pausadas. Tu borrador se conserva.':error.code==='SCHEMA_NOT_READY'?'Esquema no disponible. Tu borrador se conserva.':'No se guardó. Tu borrador se conserva.';
        if(createPending)measurementNotice='No se confirmó el registro. Reintenta el registro para comprobar la misma solicitud.';
        return false;
      }finally{ busy=false; if(key===oldKey){paintMeasurement();paintExam();} }
    }
    async function resolvePendingNavigation(){
      if(selected!=='measurements'||!reuseCandidate) return true;
      if(mode!=='open'||busy||measurementLocked||createPending||pendingDecision){
        measurementNotice=measurementLocked?'Resuelve el conflicto de esta medición antes de cambiar de paso.':'No se confirmó el registro. Reintenta o revisa esta captura antes de cambiar de paso.';
        paintMeasurement();return false;
      }
      const expectedKey=key, expectedPatient=patient;
      pendingDecision=true;
      pendingRegister.disabled=!form.checkValidity();
      pendingHint.hidden=!pendingRegister.disabled;
      pendingDialog.returnValue='';
      pendingDialog.showModal();
      const choice=await new Promise(resolve=>pendingDialog.addEventListener('close',()=>resolve(pendingDialog.returnValue),{once:true}));
      pendingDecision=false;
      if(key!==expectedKey||patient!==expectedPatient||currentPatient()!==expectedPatient) return false;
      if(choice==='discard'){fillMeasurement(null);return true;}
      if(choice==='register'&&!pendingRegister.disabled) return (await saveMeasurement(null,true))===true;
      return false;
    }
    async function saveExam(){
      if(mode!=='open'||busy||examLocked) return false;
      if(!isDirty()) return true;
      const draft=examSnapshot(); const oldKey=key, oldPatient=patient;
      const parsed=JSON.parse(draft), payload={systems:{}};
      for(const [name,item] of Object.entries(parsed)){
        if(item.state==='NOT_REVIEWED') continue;
        if(item.state==='ABNORMAL'&&!item.finding.trim()){
          examSystems.querySelector(`[data-m7-exam-system="${name}"] input`).focus();
          examState.textContent='Describe cada hallazgo anormal antes de guardar.'; return false;
        }
        payload.systems[name]=item.state==='ABNORMAL'?{state:'ABNORMAL',finding:item.finding.trim()}:{state:'NORMAL'};
      }
      const data={payload_schema_version:1,payload,narrative_text:''};
      if(examVersion!==null) data.row_version=examVersion;
      busy=true; paintExam();
      try{
        const response=await fetch(`${encounterUrl(key)}/sections/physical_exam`,{method:'PUT',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify(data)});
        const result=await responseJson(response);
        if(key!==oldKey||patient!==oldPatient||currentPatient()!==patient)return;
        loadedExam=result.data||{}; examVersion=Number(loadedExam.row_version);
        baselineExam=examSnapshot(); clearDraft('physical_exam'); examNotice='Guardado';
        return true;
      }catch(error){
        setDraft('physical_exam',draft);
        if(error.code==='VERSION_CONFLICT'){
          examLocked=true; hide(examConflict,true);
          retainedExamDraft=draft; examDraft.textContent=JSON.stringify(JSON.parse(draft),null,2);
          q('[data-m7-exam-use-draft]').disabled=true;
          try{const detail=await reloadCurrent(); const server=detail.sections?.physical_exam; examServer.textContent=server?JSON.stringify(server.payload?.systems||{},null,2):'Sin exploración guardada.';}catch(_){examServer.textContent='No se pudo cargar la versión guardada.';}
        }else if(['ENCOUNTER_TERMINAL','ENCOUNTER_CLOSED','ENCOUNTER_VOIDED'].includes(error.code)){
          examNotice='La consulta terminó. Tu borrador se conserva para copiarlo.';
          try{terminal(await reloadCurrent());}catch(_){terminal();}
        }else examNotice=error.code==='M6_WRITE_WINDOW_BLOCKED'?'Escrituras pausadas. Tu borrador se conserva.':error.code==='SCHEMA_NOT_READY'?'Esquema no disponible. Tu borrador se conserva.':'No se guardó. Tu borrador se conserva.';
        return false;
      }finally{busy=false;if(key===oldKey){paintExam();paintMeasurement();}}
    }
    Object.entries(catalog).forEach(([name,[label]])=>{ const option=document.createElement('option');option.value=name;option.textContent=label;code.append(option); });
    Object.entries(systems).forEach(([name,label])=>{
      const row=document.createElement('div');row.className='m7-exam-row';row.dataset.m7ExamSystem=name;
      const title=document.createElement('strong');title.textContent=label;
      const state=document.createElement('select');state.setAttribute('aria-label',`${label}: estado`);
      [['NOT_REVIEWED','Sin revisión'],['NORMAL','Normal'],['ABNORMAL','Anormal']].forEach(([id,text])=>{const option=document.createElement('option');option.value=id;option.textContent=text;state.append(option);});
      const finding=document.createElement('input');finding.type='text';finding.placeholder='Describe el hallazgo';finding.setAttribute('aria-label',`${label}: hallazgo anormal`);
      state.addEventListener('change',()=>{examNotice='';syncExamRow(row);setDraft('physical_exam',examSnapshot());paintExam();});
      finding.addEventListener('input',()=>{examNotice='';setDraft('physical_exam',examSnapshot());paintExam();});
      row.append(title,state,finding);examSystems.append(row);
    });
    code.addEventListener('change',()=>{
      const nextCode=code.value;code.value=lastCode;
      if(isDirty()||selectedObservation||createPending){measurementNotice='Registra o cancela la captura pendiente antes de cambiar de medición.';paintMeasurement();return;}
      code.value=nextCode;syncCode();measurementNotice='';baselineMeasurement=measurementSnapshot();paintMeasurement();
    });
    function captureInput(event){
      if(event.target===code)return;
      measurementNotice='';clearTimeout(noticeTimer);
      if(!createPending)forgetCreateKey();
      setDraft('measurements',measurementSnapshot());paintMeasurement();
    }
    form.addEventListener('input',captureInput);
    form.addEventListener('change',captureInput);
    form.addEventListener('submit',saveMeasurement);
    recoverDraft.addEventListener('click',()=>{
      if(!availableMeasurementDraft||unavailableMeasurementDraft||mode!=='open'||busy)return;
      const draft=availableMeasurementDraft;
      availableMeasurementDraft='';
      restore('measurements',draft);
      measurementNotice='Captura recuperada. Regístrala o cancélala antes de cambiar de paso.';
      paintMeasurement();
      (reuseCandidate&&!source.value?source:code.value==='blood_pressure'?systolic:value).focus();
    });
    discardRecoveredDraft.addEventListener('click',()=>{
      if(!availableMeasurementDraft||mode!=='open'||busy)return;
      fillMeasurement(null);
    });
    q('[data-m7-measurement-new]').addEventListener('click',()=>{
      if(busy||measurementLocked||createPending||mode!=='open')return;
      // This explicit cancel already authorizes discarding a prepared prior value.
      if(reuseCandidate||canReplaceCapture())fillMeasurement(null);
    });
    q('[data-m7-measurement-reload]').addEventListener('click',async()=>{
      try{const detail=await reloadCurrent();const row=detail.observations?.find(item=>Number(item.observation_id)===Number(selectedObservation?.observation_id));if(row){observations=detail.observations;fillMeasurement(row,true,true);hide(measurementConflict,true);q('[data-m7-measurement-use-draft]').disabled=false;}else{measurementNotice='No se encontró la medición guardada.';paintMeasurement();}}catch(_){measurementNotice='No se pudo cargar la versión guardada.';paintMeasurement();}
    });
    q('[data-m7-measurement-use-draft]').addEventListener('click',()=>{
      if(mode!=='open'||measurementLocked||!retainedMeasurementDraft)return;
      restore('measurements',retainedMeasurementDraft);setDraft('measurements',measurementSnapshot());
      hide(measurementConflict,false);paintMeasurement();
    });
    examDraftRecover.addEventListener('click',()=>{
      if(!availableExamDraft||mode!=='open'||busy)return;
      const draft=availableExamDraft;
      availableExamDraft='';
      restore('physical_exam',draft);
      examNotice='Captura recuperada. Guarda los cambios o revisa antes de cambiar de paso.';
      paintExam();
      examSystems.querySelector('select')?.focus();
    });
    examDraftDiscard.addEventListener('click',()=>{
      if(!availableExamDraft||mode!=='open'||busy)return;
      availableExamDraft='';clearDraft('physical_exam');fillExamFromSaved();examNotice='';paintExam();
    });
    examSave.addEventListener('click',saveExam);
    q('[data-m7-exam-reload]').addEventListener('click',async()=>{
      try{const detail=await reloadCurrent();clearDraft('physical_exam');load(detail,key,detail.status==='open'?'open':'terminal',false);hide(examConflict,true);q('[data-m7-exam-use-draft]').disabled=mode!=='open';}catch(_){examNotice='No se pudo cargar la versión guardada.';paintExam();}
    });
    q('[data-m7-exam-use-draft]').addEventListener('click',()=>{
      if(mode!=='open'||examLocked||!retainedExamDraft)return;
      restore('physical_exam',retainedExamDraft);setDraft('physical_exam',examSnapshot());
      hide(examConflict,false);paintExam();
    });
    syncCode();
    return {load,select,reset,isDirty,remember,resolvePendingNavigation,isBusy:()=>busy||pendingDecision,
      saveSelected:()=>selected==='measurements'?saveMeasurement():selected==='physical_exam'?saveExam():Promise.resolve(true),
      hasSavedDrafts:()=>!!key && (getDraft('measurements') !== null || getDraft('physical_exam') !== null)};
  };
})();
