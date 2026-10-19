/* Scoped native dialog behavior. Domain controllers retain cancellation and recovery decisions. */
(() => {
'use strict';
const dialogs=[...document.querySelectorAll('dialog')];
function viewport(){const z=parseFloat(getComputedStyle(document.documentElement).zoom)||1,v=window.visualViewport;for(const d of dialogs){d.style.setProperty('--team-visible-height',((v?.height||innerHeight)/z)+'px');d.style.setProperty('--team-visible-width',((v?.width||innerWidth)/z)+'px');}}
viewport();window.addEventListener('resize',viewport);window.visualViewport?.addEventListener('resize',viewport);new MutationObserver(viewport).observe(document.documentElement,{attributes:true,attributeFilter:['style']});let overflow=null,lastFocus=null;
document.addEventListener('keydown',e=>{if(e.key!=='Tab')return;const d=dialogs.filter(x=>x.open).at(-1);if(!d||d.contains(document.activeElement))return;e.preventDefault();const controls=[...d.querySelectorAll('button,input,select,textarea,a[href],[tabindex]')].filter(x=>!x.disabled&&x.tabIndex>=0&&x.getClientRects().length);(e.shiftKey?controls.at(-1):controls[0])?.focus();},true);
for(const event of ['pointerdown','click'])document.addEventListener(event,e=>{if(!e.target.closest('dialog'))lastFocus=e.target.closest('button,input,select,textarea,a[href],[tabindex]')||lastFocus;},true);
document.addEventListener('focusin',e=>{if(!e.target.closest('dialog'))lastFocus=e.target;});
function lock(){const open=dialogs.some(d=>d.open);if(open&&overflow===null){overflow=document.body.style.overflow;document.body.style.overflow='hidden';}else if(!open&&overflow!==null){document.body.style.overflow=overflow;overflow=null;}}
for(const d of dialogs){let down=false,returnTo=null;const outside=e=>{const b=d.getBoundingClientRect();return e.target===d&&(e.clientX<b.left||e.clientX>b.right||e.clientY<b.top||e.clientY>b.bottom);};
 d.addEventListener('pointerdown',e=>{down=outside(e);});d.addEventListener('click',e=>{if(down&&outside(e)){const event=new Event('cancel',{cancelable:true});if(d.dispatchEvent(event))d.close();}down=false;});
 d.addEventListener('keydown',e=>{if(e.key!=='Tab'||d.onkeydown)return;const controls=[...d.querySelectorAll('button,input,select,textarea,a[href],[tabindex]')].filter(x=>!x.disabled&&x.tabIndex>=0&&x.getClientRects().length);e.preventDefault();if(!controls.length){d.focus();return;}const i=controls.indexOf(document.activeElement),n=e.shiftKey?-1:1;controls[(i+n+controls.length)%controls.length].focus();});
 new MutationObserver(()=>{if(d.open){returnTo=returnTo||lastFocus;}lock();}).observe(d,{attributes:true,attributeFilter:['open']});
 d.addEventListener('close',()=>{lock();const parent=dialogs.filter(x=>x.open).at(-1);if(parent){if(!parent.contains(document.activeElement))parent.querySelector('h2,button,input')?.focus();}else{const target=returnTo?.isConnected?returnTo:document.getElementById(returnTo?.id||'')||document.getElementById('detail-title');for(let parent=target?.parentElement;parent;parent=parent.parentElement)if(parent.tagName==='DETAILS')parent.open=true;target?.focus({preventScroll:true});}returnTo=null;});
}
// BFCache must revalidate the canonical session before revealing retained staff contents.
const login='/public/identity/acceso.php?next='+encodeURIComponent('/internal/media-review/index.php?section=equipo');
window.teamSessionExpired=()=>{document.body.style.visibility='hidden';location.replace(login+'&reason=session_expired');};
async function validate(){document.body.style.visibility='hidden';try{const r=await fetch('/api/identity/index.php/current-session',{credentials:'same-origin',cache:'no-store'}),b=await r.json();if(!r.ok||!b.authenticated){location.replace(login);return;}document.body.style.visibility='';}catch(_){location.replace(login);}}
window.addEventListener('pagehide',()=>{document.body.style.visibility='hidden';});window.addEventListener('pageshow',e=>{if(e.persisted)validate();else document.body.style.visibility='';});
window.addEventListener('storage',e=>{if(e.key==='mxmed.session.signal')validate();});
try{const c=new BroadcastChannel('mxmed-session');c.onmessage=e=>{if(e.data?.type==='logout')validate();};}catch(_){}
})();
