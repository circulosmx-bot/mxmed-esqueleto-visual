/* CERT-HELP01: type-aware examples through the shared ExampleHelp primitive. */
(function () {
  const modal = document.getElementById('modalCertificadoMedico');
  if (!modal || !window.mxmedExampleFieldHelp) return;

  const labels = {
    certificado_general: 'Certificado médico general',
    constancia_atencion: 'Constancia de atención médica',
    reposo_medico: 'Constancia de incapacidad temporal',
    aptitud_fisica: 'Certificado de aptitud física',
    actividad_deportiva: 'Certificado para actividad deportiva'
  };
  const recipient = {
    key: 'recipient', label: 'Destinatario específico',
    explanation: 'Identifica a la persona, área o entidad ante la que se presentará el documento. No indica para qué se utilizará; eso corresponde al campo Uso del documento.',
    text: 'Dirección de Recursos Humanos de [institución]; Coordinación escolar de [plantel]; Área administrativa de [entidad].'
  };
  const initialSections = [
    {key: 'declaration', label: 'Declaración principal', explanation: '', text: ''},
    {key: 'findings', label: 'Hallazgos clínicos adicionales', explanation: '', text: ''},
    {key: 'return_note', label: 'Nota de reincorporación', explanation: '', text: ''},
    recipient,
    {key:'assessment_activity', label:'Actividad evaluada', explanation:'', text:''},
    {key:'assessment_intensity', label:'Nivel o intensidad', explanation:'', text:''},
    {key:'assessment_findings', label:'Hallazgos actuales', explanation:'', text:''},
    {key:'assessment_conclusion', label:'Conclusión estructurada', explanation:'', text:''},
    {key:'assessment_restriction_mode', label:'Estado de restricciones', explanation:'', text:''},
    {key:'assessment_restriction_detail', label:'Detalle de restricciones', explanation:'', text:''},
    {key:'assessment_recommendations', label:'Recomendaciones', explanation:'', text:''}
  ];
  const scenarios = {
    certificado_general: {
      context: 'Valoración en consulta, estabilidad clínica al momento del examen y presentación para un trámite administrativo (caso ficticio).',
      sections: [
        {
          key: 'declaration', label: 'Declaración principal',
          explanation: 'Resume lo que el médico puede certificar a partir de la valoración actual y el alcance del documento. Evita declarar salud general o aptitud laboral sin sustento.',
          text: 'Se hace constar que la persona identificada en este certificado fue valorada en consulta. Al momento de la evaluación se encontraba clínicamente estable y no se identificaron datos de alarma aguda. Se expide el presente documento para el trámite administrativo indicado.'
        },
        {
          key: 'findings', label: 'Hallazgos clínicos adicionales',
          explanation: 'Registra hechos clínicos breves que respaldan la declaración, sin repetirla ni añadir diagnósticos no documentados.',
          text: 'Durante la valoración, la persona se encontró consciente y orientada, sin datos de alarma aguda observables.'
        },
        recipient
      ]
    },
    constancia_atencion: {
      context: 'Asistencia a consulta el 9 de octubre de 2026 a las 10:30 h (caso ficticio).',
      sections: [
        {
          key: 'declaration', label: 'Declaración principal',
          explanation: 'Confirma la asistencia y su fecha o contexto. Evita incluir detalles clínicos innecesarios o afirmar incapacidad laboral.',
          text: 'Se hace constar que la persona identificada en esta constancia acudió a consulta médica el 9 de octubre de 2026 a las 10:30 h. El presente documento acredita únicamente la atención recibida en esa fecha.'
        },
        recipient
      ]
    },
    reposo_medico: {
      context: 'Valoración con indicación de reposo temporal de tres días, del 9 al 11 de octubre de 2026 (caso ficticio).',
      sections: [
        {
          key: 'declaration', label: 'Declaración principal',
          explanation: 'Relaciona la valoración con el periodo temporal de reposo indicado. Ajusta fechas y duración a los campos del certificado; no emitas una opinión laboral general.',
          text: 'Se hace constar que la persona identificada en este documento fue valorada clínicamente y que, por la condición observada, se indicó reposo temporal durante tres días, del 9 al 11 de octubre de 2026. Se recomienda seguimiento según su evolución.'
        },
        {
          key: 'return_note', label: 'Nota de reincorporación',
          explanation: 'Campo opcional para indicar cuándo o bajo qué condición podría retomarse la actividad habitual, si la valoración lo permite.',
          text: 'Se sugiere reincorporación a actividades habituales al término del periodo indicado, siempre que la evolución sea favorable.'
        },
        recipient
      ]
    },
    aptitud_fisica: {
      context: 'Persona adulta ficticia valorada para actividad física habitual de intensidad ligera a moderada, estable al momento de la consulta y sin hallazgos agudos de alarma.',
      sections: [
        {key:'declaration',label:'Declaración principal',explanation:'Resume el alcance de la valoración. Si la editas, conserva coherencia con los campos estructurados.',text:'Con base en la valoración actual para actividad física habitual de intensidad ligera a moderada, se documenta estabilidad clínica sin datos agudos de alarma. La conclusión se limita a la actividad indicada.'},
        {key:'assessment_activity',label:'Actividad evaluada',explanation:'Precisa el alcance de la valoración; evita certificar todas las actividades.',text:'Actividad física habitual de intensidad ligera a moderada.'},
        {key:'assessment_findings',label:'Hallazgos actuales',explanation:'Describe solo hallazgos observados en la valoración actual.',text:'En la valoración actual se encontró estabilidad clínica y no se identificaron datos agudos de alarma.'},
        {key:'assessment_conclusion',label:'Conclusión estructurada',explanation:'Selecciona una conclusión sustentada por la valoración. Este ejemplo no se aplica al abrir la ayuda.',text:'Apto para la actividad evaluada',value:'apto_para_actividad_indicada'},
        {key:'assessment_restriction_mode',label:'Estado de restricciones',explanation:'Registra lo evaluado expresamente; no infieras ausencia de restricciones.',text:'Sin restricciones documentadas',value:'sin_restricciones_documentadas'},
        {key:'assessment_restriction_detail',label:'Detalle de restricciones',explanation:'Úsalo solo si seleccionaste «Con restricciones» y documenta límites concretos.',text:'Limitar la actividad a intensidad ligera; suspenderla y solicitar nueva valoración ante síntomas.'},
        {key:'assessment_recommendations',label:'Recomendaciones',explanation:'Indica una recomendación limitada al alcance evaluado.',text:'Iniciar de forma gradual y solicitar valoración ante síntomas nuevos.'}
      ]
    },
    actividad_deportiva: {
      context: 'Persona adulta ficticia valorada para carrera recreativa de intensidad moderada; el ejemplo no certifica competencia ni alto rendimiento.',
      sections: [
        {key:'declaration',label:'Declaración principal',explanation:'Relaciona hallazgos y conclusión con el deporte e intensidad documentados.',text:'Con base en la valoración actual para carrera recreativa de intensidad moderada, se documenta estabilidad clínica sin datos agudos de alarma. La conclusión se limita a esa práctica recreativa.'},
        {key:'assessment_activity',label:'Deporte o actividad',explanation:'Campo libre: describe el deporte concreto sin limitarte a un catálogo.',text:'Carrera recreativa.'},
        {key:'assessment_intensity',label:'Nivel o intensidad',explanation:'Define el nivel previsto para limitar el alcance de la conclusión.',text:'Recreativa, intensidad moderada.'},
        {key:'assessment_findings',label:'Hallazgos actuales',explanation:'Documenta hallazgos de la valoración actual, sin afirmar evaluaciones que no se realizaron.',text:'En la valoración actual se encontró estabilidad clínica y no se identificaron datos agudos de alarma.'},
        {key:'assessment_conclusion',label:'Conclusión estructurada',explanation:'Selecciona una conclusión referida a la actividad descrita; abrir esta ayuda no selecciona nada.',text:'Apto para la actividad deportiva indicada',value:'apto_para_actividad_indicada'},
        {key:'assessment_restriction_mode',label:'Estado de restricciones',explanation:'Expresa si se evaluaron restricciones; no las deduzcas de un campo vacío.',text:'Sin restricciones documentadas',value:'sin_restricciones_documentadas'},
        {key:'assessment_restriction_detail',label:'Detalle de restricciones',explanation:'Úsalo cuando la valoración indique límites para la práctica.',text:'Limitar la participación a carrera recreativa de intensidad moderada; no incluye competencia.'},
        {key:'assessment_recommendations',label:'Recomendaciones',explanation:'Mantén la recomendación en el alcance de la práctica descrita.',text:'Progresar de forma gradual y suspender la actividad si aparecen síntomas.'}
      ]
    }
  };
  function resolveScenario() {
    const type = modal.querySelector('#cm_type')?.value || '';
    const scenario = scenarios[type];
    if (!scenario) {
      return {
        sections: initialSections.map(section => ({
          ...section,
          explanation: 'Selecciona un tipo de certificado para mostrar un ejemplo relacionado con ese documento.',
          text: 'Selecciona un tipo de certificado para mostrar un ejemplo relacionado con ese documento.'
        })),
        categoryLabel: 'Ejemplo correspondiente a: tipo de certificado sin seleccionar',
        contextLabel: 'Orientación', contextText: 'Selecciona un tipo de certificado para mostrar un ejemplo relacionado con ese documento.',
        note: 'No hay texto para insertar hasta seleccionar un tipo de certificado.',
        canUseExample: false
      };
    }
    return {
      sections: scenario.sections,
      categoryLabel: `Ejemplo correspondiente a: ${labels[type]}`,
      contextLabel: 'Caso ficticio', contextText: scenario.context,
      note: 'Referencia de redacción. Adapta el texto, las fechas y los hallazgos a la valoración real antes de usarlo.',
      canUseExample: true
    };
  }
  window.mxmedExampleFieldHelp.mount({
    modal, id: 'cm-example',
    fieldMap: {
      cm_declaration_text: 'declaration',
      cm_observations: 'findings',
      cm_return_note: 'return_note',
      cm_purpose_other: 'recipient',
      cm_assessment_activity: 'assessment_activity',
      cm_assessment_intensity: 'assessment_intensity',
      cm_assessment_findings: 'assessment_findings',
      cm_assessment_conclusion: 'assessment_conclusion',
      cm_assessment_restriction_mode: 'assessment_restriction_mode',
      cm_assessment_restriction_detail: 'assessment_restriction_detail',
      cm_assessment_recommendations: 'assessment_recommendations'
    },
    sections: initialSections, resolveScenario,
    contextLabel: 'Caso ficticio', contextText: '',
    note: 'Referencia de redacción; adapta cada campo al paciente real.',
    canUseExample: ({section}) => section.key !== 'recipient',
    onUseExample: ({section, destination}) => {
      destination.value = destination.tagName === 'SELECT' ? (section.value || '') : section.text;
      destination.dispatchEvent(new Event(destination.tagName === 'SELECT' ? 'change' : 'input', {bubbles: true}));
    }
  });
})();
