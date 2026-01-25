(function enforceMxmedTitle(){
  try{
    var desired = 'MXMed 2025 \u00B7 Perfil M\u00E9dico';
    if(document && document.title !== desired){
      document.title = desired;
    }
  }catch(_){}
})();
