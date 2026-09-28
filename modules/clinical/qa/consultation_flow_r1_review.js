// Explicit loopback-only Director presentation fixture. Never loaded by product HTML.
(function(){
  const q=new URLSearchParams(location.search);
  if(!['127.0.0.1','localhost','::1'].includes(location.hostname)||q.get('review_flow')!=='r1'||q.get('review_patient')!=='plan02ux'||q.get('review_encounter')!=='open')return;
  const nativeGet=Storage.prototype.getItem,nativeSet=Storage.prototype.setItem,nativeRemove=Storage.prototype.removeItem;
  const mapped=k=>String(k).startsWith('mxmed-plan02b:')?'review-flow-r1:'+k:k;
  Storage.prototype.getItem=function(k){return nativeGet.call(this,mapped(k));};
  Storage.prototype.setItem=function(k,v){return nativeSet.call(this,mapped(k),v);};
  Storage.prototype.removeItem=function(k){return nativeRemove.call(this,mapped(k));};
  const key='mxmed-plan02b:1:p_plan02ux_review:1016',d=new Date();d.setDate(d.getDate()+10);
  const date=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  const action=extra=>({id:crypto.randomUUID(),key:crypto.randomUUID(),state:'DRAFT',event:new Date().toISOString().slice(0,19).replace('T',' '),payload:null,result:null,error:'',...extra});
  if(!sessionStorage.getItem(key)){
    const order=action({title:'Biometría hemática',summary:'Estudio de control',review:{mode:'days',days:10,date:''}});
    sessionStorage.setItem(key,JSON.stringify({orders:[order],prescription:action({items:[{medicamento:'Medicamento de ejemplo 1',dosis:'',via:'',frecuencia:'',duracion:'',indicaciones:''},{medicamento:'Medicamento de ejemplo 2',dosis:'',via:'',frecuencia:'',duracion:'',indicaciones:''}],observaciones:''}),appointment:action({mode:'new',selection:{start_at:date+' 09:30:00',end_at:date+' 10:00:00',consultorio_id:'1',consultorio_name:'Consultorio'}}),orderReviews:[action({derivedFromOrder:order.id,title:'Revisar resultado de Biometría hemática',due:date+'T12:00',link:false,reviewMode:'days',reviewDays:10})],followup:null}));
  }
  const original=window.fetch;
  window.fetch=function(input,options){const request=input instanceof Request?input:null,url=new URL(request?.url||String(input),location.href),method=String(options?.method||request?.method||'GET').toUpperCase();
    if(url.origin===location.origin&&['/api/clinical/','/api/agenda/'].some(p=>url.pathname.startsWith(p))&&!['GET','HEAD'].includes(method)&&!url.pathname.endsWith('/patient-id/resolve'))return Promise.resolve(new Response(JSON.stringify({ok:false,error:'REVIEW_ONLY'}),{status:403,headers:{'Content-Type':'application/json'}}));
    return original.call(this,input,options);
  };
})();
