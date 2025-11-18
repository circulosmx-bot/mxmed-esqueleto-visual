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

  // Reutilizar lógica de chips para múltiples scopes
  function setupChips(scope, lim){
    const input = document.getElementById(scope+'-input');
    const btn   = document.getElementById(scope+'-add');
    const cnt   = document.getElementById(scope+'-count');
    const list  = document.getElementById(scope+'-list');
    if(!input || !btn || !cnt || !list) return;
    function load(){ try { return JSON.parse(localStorage.getItem('chips:'+scope)||'[]'); } catch(e){ return []; } }
    function save(arr){ localStorage.setItem('chips:'+scope, JSON.stringify(arr)); render(); }
    function update(){ const used=(input.value||'').length; const left=Math.max(0, lim-used); cnt.textContent=left+'/'+lim; cnt.style.visibility = left<10 ? 'visible':'hidden'; btn.disabled= used===0 || used>lim; }
    function render(){ list.innerHTML=''; load().forEach((txt,i)=>{ const chip=document.createElement('span'); chip.className='chip'; chip.textContent=txt; const x=document.createElement('button'); x.type='button'; x.className='chip-x'; x.textContent='×'; x.addEventListener('click',()=>{ const a=load(); a.splice(i,1); save(a); }); chip.appendChild(x); list.appendChild(chip); }); }
    btn.addEventListener('click', ()=>{ const v=(input.value||'').trim(); if(!v|| v.length>lim) return; const a=load(); a.push(v); save(a); input.value=''; update(); });
    input.addEventListener('input', update); input.addEventListener('blur', update);
    render(); update();
  }
  setupChips('cert', 50);
  setupChips('cursos', 50);
  setupChips('dipl', 50);
  setupChips('miem', 50);
})();



