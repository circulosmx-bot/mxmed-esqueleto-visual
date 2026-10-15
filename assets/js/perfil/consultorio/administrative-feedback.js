(function(){
  'use strict';
  const message = 'No tienes permisos para modificar este consultorio. Esta acción requiere acceso de propietario o administrador.';
  const element = ()=> document.querySelector('[data-cons-admin-feedback]');
  const show = ()=>{
    const target = element();
    if(!target) return false;
    target.textContent = message;
    target.classList.remove('d-none');
    return true;
  };
  const clear = ()=>{
    const target = element();
    if(!target) return false;
    target.textContent = '';
    target.classList.add('d-none');
    return true;
  };
  window.mxmedConsultorioAuthorizationFeedback = Object.freeze({ show, clear, message });
  window.addEventListener('mxmed:authorization-denied', event=>{
    const input = event?.detail?.input;
    const url = typeof input === 'string' ? input : String(input?.url || '');
    if(url.includes('/api/agenda/index.php/consultorios')) show();
  });
})();
