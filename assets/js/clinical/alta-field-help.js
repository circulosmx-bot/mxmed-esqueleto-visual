/* DOC-CLOSE02C: one fictional uncomplicated discharge, shared field help. */
(function () {
  const modal = document.getElementById('modalAltaMedica');
  if (!modal || !window.mxmedExampleFieldHelp) return;
  const sections = [
    {key:'reason',label:'MOTIVO DE EGRESO',explanation:'Explica por qué termina esta atención y en qué condición se decide el egreso.',text:'Egreso por mejoría clínica tras valoración y manejo de un cuadro gastrointestinal agudo no complicado.'},
    {key:'course',label:'RESUMEN CLÍNICO',explanation:'Resume el curso de la atención y la evolución relevante, sin convertirlo en indicaciones para casa.',text:'Persona adulta valorada por malestar gastrointestinal agudo. Recibió manejo de soporte y vigilancia clínica; los síntomas disminuyeron progresivamente y mantuvo adecuada tolerancia a líquidos.'},
    {key:'diagnosis',label:'DIAGNÓSTICO FINAL',explanation:'Registra el diagnóstico o la impresión clínica al momento del alta.',text:'Cuadro gastrointestinal agudo no complicado, en mejoría clínica.'},
    {key:'condition',label:'ESTADO ACTUAL DEL PACIENTE',explanation:'Describe la condición observada justo al egreso, distinta del curso previo.',text:'Al egreso se encuentra consciente, orientado, clínicamente estable y con tolerancia a líquidos, sin datos de alarma en esta valoración.'},
    {key:'treatment',label:'TRATAMIENTO',explanation:'Indica el manejo que debe continuar o realizar después del egreso; precisa el plan real de cada paciente.',text:'Continuar las medidas de soporte indicadas durante la consulta y seguir únicamente el tratamiento individual documentado por el médico responsable.'},
    {key:'care',label:'CUIDADOS GENERALES',explanation:'Anota medidas prácticas de autocuidado en domicilio, sin repetir el diagnóstico.',text:'Mantener hidratación frecuente, alimentación según tolerancia y reposo relativo durante la recuperación.'},
    {key:'alarms',label:'SIGNOS DE ALARMA',explanation:'Señala síntomas concretos que requieren una nueva valoración urgente.',text:'Acudir a valoración urgente si presenta dolor intenso o progresivo, vómito persistente, imposibilidad de tolerar líquidos, fiebre sostenida o deterioro del estado general.'},
    {key:'followup',label:'MOTIVO DE CONTROL',explanation:'Explica qué se revisará en la cita de control y por qué.',text:'Revaloración clínica para confirmar la resolución de síntomas y revisar la tolerancia a la alimentación e hidratación.'},
    {key:'recommendations',label:'RECOMENDACIONES',explanation:'Añade consejos útiles que aún no figuren en tratamiento, cuidados o signos de alarma.',text:'Retomar las actividades habituales de forma gradual según evolución y conservar este documento para la próxima valoración.'}
  ];
  window.mxmedExampleFieldHelp.mount({
    modal,id:'am-example',
    fieldMap:{am_motivo_egreso:'reason',am_resumen_evolucion:'course',am_diagnostico_final:'diagnosis',
      am_estado_paciente:'condition',am_tratamiento:'treatment',am_cuidados_generales:'care',
      am_signos_alarma:'alarms',am_followup_note:'followup',am_recomendaciones:'recommendations'},
    sections,contextLabel:'Caso ficticio',
    contextText:'Persona adulta con un cuadro gastrointestinal agudo no complicado, tratada con medidas de soporte, clínicamente mejor y estable al egreso.',
    note:'Ejemplo educativo sintético. Adapta cada campo a los hallazgos y decisiones reales. Insertar un ejemplo modifica solo el campo elegido.',
    onUseExample:({section,destination})=>{
      destination.value=section.text;
      destination.dispatchEvent(new Event('input',{bubbles:true}));
    }
  });
})();
