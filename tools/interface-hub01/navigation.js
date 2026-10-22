(config => {
  'use strict';
  if (window.top !== window || !config.origins.includes(location.origin)) return;
  const install = () => {
    if (document.getElementById('mxmed-review-navigation')) return;
    const host = document.createElement('div'); host.id = 'mxmed-review-navigation';
    const root = host.attachShadow({mode:'open'});
    root.innerHTML = `<style>
      :host{display:block;position:sticky;top:0;z-index:20;margin:0;padding:0;color:#173d35;font:13px/1.4 system-ui}
      details{background:#f5faf9;border-bottom:1px solid #b9d2ca;padding:6px 12px;box-sizing:border-box}
      summary{cursor:pointer;font-weight:650}small{font-weight:400}ul{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;padding:0;list-style:none;max-height:35vh;overflow:auto;margin:8px 0}
      li{border:1px solid #b9d2ca;border-radius:6px;padding:8px;min-width:0}strong,span{display:block}span{font-size:12px;margin:4px 0}button{font:inherit;padding:5px 9px;cursor:pointer}button:disabled{cursor:default}p{margin:4px 0}a{color:inherit}
      @media(max-width:700px){ul{grid-template-columns:1fr}small{display:block}}
    </style><details><summary>Interfaces · México Médico <small>Revisión local · sesiones independientes</small></summary><p>Contexto de referencia: Leticia · mxmed-local-development:1. Cada ventana conserva su propia selección y autoridad.</p><ul></ul><p role="status"></p></details>`;
    document.body.prepend(host);
    // Replace the old copy-command index only in this managed review browser.
    const previous = document.getElementById('development-interface-index'); if(previous) previous.hidden = true;
    const list=root.querySelector('ul'), feedback=root.querySelector('[role=status]');
    const render = state => {
      list.replaceChildren();
      for(const item of state.interfaces){
        const li=document.createElement('li'), title=document.createElement('strong'), status=document.createElement('span'), button=document.createElement('button');
        title.textContent=item.title;status.textContent=item.status+' · '+item.reason;
        button.textContent=item.open?'Mostrar ventana':item.enabled?'Abrir ventana':'No disponible';button.disabled=!item.enabled;
        button.addEventListener('click',async event=>{if(!event.isTrusted)return;button.disabled=true;try{await window.mxmedReviewNavigation(item.id);feedback.textContent='Ventana solicitada. Su identidad y permisos se verifican en esa ventana.';await refresh();}catch{feedback.textContent='No se pudo abrir. Verifica la vigencia del entorno local.';}finally{button.disabled=!item.enabled;}});
        li.append(title,status,button);list.append(li);
      }
    };
    async function refresh(){try{render(await window.mxmedReviewNavigation('status'));}catch{feedback.textContent='Controlador de revisión no disponible. No se cambió la sesión.';}}
    root.querySelector('details').addEventListener('toggle',()=>{if(root.querySelector('details').open)refresh();});
    refresh();
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',install,{once:true});else install();
})
