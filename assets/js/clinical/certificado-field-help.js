/* CERT-HELP01: type-aware examples through the shared ExampleHelp primitive. */
(function () {
  const modal = document.getElementById('modalCertificadoMedico');
  if (!modal || !window.mxmedExampleFieldHelp) return;

  const labels = {
    certificado_general: 'Certificado médico general',
    constancia_atencion: 'Constancia de atención médica',
    reposo_medico: 'Constancia de incapacidad temporal'
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
    recipient
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
      cm_purpose_other: 'recipient'
    },
    sections: initialSections, resolveScenario,
    contextLabel: 'Caso ficticio', contextText: '',
    note: 'Referencia de redacción; adapta cada campo al paciente real.',
    canUseExample: ({section}) => section.key !== 'recipient',
    onUseExample: ({section, destination}) => {
      destination.value = section.text;
      destination.dispatchEvent(new Event('input', {bubbles: true}));
    }
  });
})();
