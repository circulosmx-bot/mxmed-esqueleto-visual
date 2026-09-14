// ===== Sección Mi Perfil =====

(function(){
  // Poblar resumen desde localStorage (Datos Generales)
  const fsTitulo = document.getElementById('fs-titulo');
  const fsUni = document.getElementById('fs-uni');
  const fsEsp = document.getElementById('fs-esp');
  if(fsTitulo || fsUni || fsEsp){
    const esp1 = localStorage.getItem('dp:esp-1') || '';
    const uni = localStorage.getItem('dp:uni-prof') || localStorage.getItem('dp:uni-esp') || '';
    if(fsTitulo && esp1){ fsTitulo.textContent = 'Médico ' + (esp1.includes('Cirugía') ? 'Cirujano' : 'Especialista'); }
    if(fsUni && uni){ fsUni.textContent = uni; }
    if(fsEsp && esp1){ fsEsp.textContent = esp1; }
  }

})();
