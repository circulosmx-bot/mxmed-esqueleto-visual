(function(){
  'use strict';
  const selector='[data-rx-public-profile-qr]';
  const hydrate=scope=>{
    if(typeof window.QRCode!=='function')return;
    const hosts=[];
    if(scope?.matches?.(selector))hosts.push(scope);
    scope?.querySelectorAll?.(selector).forEach(host=>hosts.push(host));
    hosts.forEach(host=>{
      if(host.dataset.rxPublicProfileQrHydrated==='true')return;
      const value=String(host.dataset.rxPublicProfileQr||'').trim();
      let url;
      try{url=new URL(value);}catch(_){return;}
      if(url.protocol!=='https:')return;
      host.replaceChildren();
      new window.QRCode(host,{text:url.href,width:92,height:92,colorDark:'#000000',colorLight:'#ffffff',
        correctLevel:window.QRCode.CorrectLevel.M});
      host.dataset.rxPublicProfileQrHydrated='true';
    });
  };
  const start=()=>{
    hydrate(document);
    new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(node=>{
      if(node.nodeType===1)hydrate(node);
    }))).observe(document.documentElement,{childList:true,subtree:true});
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
  window.mxmedHydratePrescriptionDocumentQr=hydrate;
})();
