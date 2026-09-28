// Director context preview only; all observations remain canonical and unchanged.
(function(){
  const context=new URL(location.href).searchParams.get('review_vitalref');
  const base=window.fetch.bind(window);
  if(['child','adolescent','infant','missing'].includes(context)){
    window.fetch=(input,options)=>{
      if(String(input)==='/api/clinical/index.php/patients/p_plan02ux_review/vital-references'){
        return base(`/__director_vitalref01_context?context=${encodeURIComponent(context)}`,options);
      }
      return base(input,options);
    };
  }
  const timer=setInterval(()=>{
    const step=document.querySelector('[data-m7-section="measurements"]');
    const current=document.querySelector('[data-m7-section][aria-current="true"]');
    if(current&&step&&!step.disabled){clearInterval(timer);step.click();}
  },100);
  setTimeout(()=>clearInterval(timer),60000);
})();
