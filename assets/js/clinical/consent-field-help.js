/* Consentimiento examples use the shared explicit field insertion action. */
(function () {
  const modal = document.getElementById('modalConsentimientoInformado');
  if (!modal || !window.mxmedExampleFieldHelp) return;

  const example = {
    procedure: 'Extirpación de una lesión cutánea superficial en el antebrazo con anestesia local (caso ficticio)',
    sections: [
      {
        key: 'description', label: 'Descripción del procedimiento',
        explanation: 'Explica en qué consiste el procedimiento y cómo se realizará.',
        text: 'Se limpiará la piel del antebrazo, se aplicará anestesia local y se retirará una lesión cutánea superficial. La herida se cerrará según su tamaño y se indicarán cuidados posteriores. El tejido podrá enviarse a análisis si la valoración clínica lo requiere.'
      },
      {
        key: 'objective', label: 'Objetivo',
        explanation: 'Describe qué se pretende lograr con el procedimiento.',
        text: 'Retirar la lesión y, si corresponde, obtener tejido para aclarar su naturaleza mediante análisis.'
      },
      {
        key: 'common', label: 'Riesgos comunes',
        explanation: 'Describe eventos adversos relativamente frecuentes asociados al procedimiento.',
        text: 'Puede haber molestia transitoria, sensibilidad local, un pequeño hematoma y una cicatriz en el sitio tratado.'
      },
      {
        key: 'uncommon', label: 'Riesgos poco frecuentes',
        explanation: 'Describe eventos menos habituales pero clínicamente relevantes.',
        text: 'Puede presentarse infección local, sangrado posterior o apertura de la herida que requiera atención adicional.'
      },
      {
        key: 'complications', label: 'Complicaciones posibles',
        explanation: 'Describe eventos de mayor gravedad que requieren especial consideración.',
        text: 'De forma excepcional puede ocurrir una reacción grave al anestésico local o un problema de cicatrización que requiera tratamiento adicional.'
      },
      {
        key: 'benefits', label: 'Beneficios esperados',
        explanation: 'Describe los resultados favorables que razonablemente podrían obtenerse.',
        text: 'Se espera retirar la lesión. El análisis del tejido, cuando esté indicado, puede aportar información para decidir el seguimiento.'
      },
      {
        key: 'alternatives', label: 'Alternativas',
        explanation: 'Describe otras opciones que pueden considerarse en lugar del procedimiento propuesto.',
        text: 'Según la valoración individual, puede considerarse observación con seguimiento o solicitar una opinión dermatológica antes de decidir la extirpación.'
      },
      {
        key: 'refusal', label: 'Consecuencias de no aceptar',
        explanation: 'Describe qué podría ocurrir si el paciente decide no realizar el procedimiento.',
        text: 'La lesión permanecerá y podría cambiar con el tiempo. Si se había recomendado analizarla, posponer la toma de tejido puede retrasar la aclaración del diagnóstico.'
      }
    ]
  };
  // One fictional case per canonical selector value. Section labels and explanations
  // come from the approved invasive baseline, so guided and full capture stay alike.
  const scenarios = {
    procedimiento: {id: 'skin_lesion_excision', procedure: example.procedure,
      sections: example.sections},
    procedimiento_no_invasivo: {id: 'external_splint',
      procedure: 'Colocación de una férula externa en la muñeca tras una lesión estable (caso ficticio)',
      texts: [
        'Se colocará una férula externa para mantener la muñeca en una posición de reposo. Se revisará el ajuste y se indicará cuándo retirarla o acudir a revisión.',
        'Limitar el movimiento de la muñeca lesionada mientras se valora su evolución.',
        'Puede haber incomodidad, calor o presión leve en la zona de contacto de la férula.',
        'Puede aparecer irritación de la piel o un ajuste inadecuado que requiera modificar la férula.',
        'Una presión excesiva podría afectar la circulación o la sensibilidad de los dedos y requerir retirar o reajustar la férula con prontitud.',
        'Se espera mayor comodidad y protección de la muñeca durante la recuperación, sin garantizar un resultado específico.',
        'Según la valoración de la lesión, pueden considerarse otra forma de inmovilización o seguimiento sin férula.',
        'La muñeca podría moverse más de lo recomendado, con persistencia de dolor o retraso en la recuperación.'
      ]},
    procedimiento_diagnostico: {id: 'contrast_ct',
      procedure: 'Tomografía computarizada de abdomen con contraste intravenoso por una duda diagnóstica concreta (caso ficticio)',
      texts: [
        'Se realizará una tomografía computarizada del abdomen. Se administrará contraste intravenoso y se obtendrán imágenes con el equipo de rayos X; antes se revisarán antecedentes relevantes y función renal cuando corresponda.',
        'Obtener imágenes que ayuden a aclarar la causa de los síntomas abdominales de este caso.',
        'Puede sentirse calor pasajero o molestia en el sitio de la vía intravenosa durante la administración del contraste.',
        'Puede presentarse una reacción al contraste, como urticaria, o extravasación en el sitio de inyección que requiera atención.',
        'De manera excepcional puede ocurrir una reacción grave al contraste; en personas con factores de riesgo también puede empeorar la función renal. El estudio utiliza radiación ionizante.',
        'Las imágenes pueden aportar información para orientar el diagnóstico y decidir los siguientes pasos, aunque no garantizan encontrar la causa.',
        'Según la pregunta clínica, puede valorarse una tomografía sin contraste, ultrasonido u otra prueba, con distintas limitaciones diagnósticas.',
        'La causa de los síntomas podría permanecer sin aclararse y podrían retrasarse decisiones sobre el tratamiento o seguimiento.'
      ]},
    procedimiento_terapeutico: {id: 'wound_care',
      procedure: 'Limpieza y cuidado de una herida superficial en la pierna (caso ficticio)',
      texts: [
        'Se limpiará una herida superficial de la pierna, se retirarán residuos visibles si los hay y se colocará un apósito. Se darán indicaciones de cuidado y revisión.',
        'Favorecer el cuidado local de la herida y vigilar su evolución.',
        'Puede haber molestia durante la limpieza y sensibilidad temporal después del cambio de apósito.',
        'Puede aparecer irritación por el apósito o persistir secreción que motive una nueva valoración.',
        'La herida puede infectarse o no cicatrizar como se espera y requerir atención adicional; este cuidado no elimina todos los riesgos.',
        'Se espera mantener la herida limpia y facilitar su seguimiento, sin asegurar una cicatrización determinada.',
        'Según la evaluación, pueden considerarse otros apósitos o un plan de cuidado distinto.',
        'La herida podría acumular residuos o deteriorarse sin el cuidado propuesto, y retrasarse la detección de problemas.'
      ]},
    anestesia: {id: 'procedural_sedation',
      procedure: 'Sedación para reducir y colocar una articulación del dedo, con vigilancia clínica (caso ficticio; no es anestesia general)',
      texts: [
        'Se administrará un sedante para facilitar la reducción de una articulación del dedo. Se vigilarán respiración, oxigenación y signos vitales durante la sedación y la recuperación.',
        'Disminuir la ansiedad y la incomodidad durante la reducción de la articulación del dedo.',
        'Puede haber somnolencia, mareo o náusea transitoria después de recibir el sedante.',
        'Puede presentarse una disminución de la respiración o de la presión arterial que requiera medidas de apoyo y vigilancia adicional.',
        'Excepcionalmente puede requerirse asistencia para mantener la vía aérea o atención urgente por una reacción grave al medicamento.',
        'Se espera que el procedimiento sea más tolerable, sin garantizar ausencia completa de molestias.',
        'Según la valoración, puede realizarse con anestesia local, otras medidas de control del dolor o diferirse para valorar otra estrategia.',
        'Sin la sedación propuesta, la reducción podría ser más difícil de tolerar o requerir otro método de control del dolor.'
      ]},
    transfusion: {id: 'red_cell_transfusion',
      procedure: 'Administración de concentrado de eritrocitos por anemia sintomática (caso ficticio)',
      texts: [
        'Se administrará por vía intravenosa un componente de glóbulos rojos compatible, con verificación de identidad y vigilancia durante la transfusión.',
        'Aumentar la capacidad de transporte de oxígeno en este caso de anemia sintomática.',
        'Puede haber molestia local durante la colocación o permanencia de la vía intravenosa; muchas personas no presentan otros efectos.',
        'Puede ocurrir fiebre o una reacción alérgica, como erupción cutánea, que obligue a detener la transfusión y valorar al paciente.',
        'Excepcionalmente puede presentarse una reacción hemolítica grave, sobrecarga circulatoria o dificultad respiratoria que requiera atención urgente.',
        'Se espera mejorar los síntomas atribuibles a la anemia, aunque la respuesta y la causa de fondo requieren seguimiento.',
        'Según la causa y urgencia de la anemia, pueden considerarse tratamiento de la causa, suplementos u observación; no siempre sustituyen una transfusión indicada.',
        'Los síntomas de la anemia podrían persistir o empeorar y podría retrasarse la recuperación mientras se define otra opción.'
      ]},
    tratamiento_farmacologico: {id: 'oral_anticoagulant',
      procedure: 'Inicio de apixabán oral para reducir riesgo de embolia en fibrilación auricular, sujeto a valoración individual (caso ficticio)',
      texts: [
        'Se iniciará apixabán por vía oral según la dosis y pauta indicadas tras valorar función renal, otros medicamentos y riesgo de sangrado. Se explicará el seguimiento y cuándo solicitar atención.',
        'Reducir el riesgo de formación de coágulos y embolia asociado a la fibrilación auricular de este caso.',
        'Pueden aparecer moretones o sangrado leve, por ejemplo de encías o nariz.',
        'Puede presentarse sangrado que requiera evaluación médica o una reacción al medicamento.',
        'Puede ocurrir una hemorragia grave, incluida intracraneal o digestiva, que exija atención urgente.',
        'Se espera disminuir el riesgo de embolia mientras el tratamiento sea apropiado y se siga la pauta, sin eliminarlo por completo.',
        'Según la valoración individual, pueden considerarse otros anticoagulantes o una estrategia distinta, con riesgos y controles propios.',
        'Sin el tratamiento indicado, podría mantenerse un mayor riesgo de coágulos y embolia relacionado con la fibrilación auricular.'
      ]}
  };
  const guidance = {
    investigacion: {id: 'research_guidance_only', procedure: 'Investigación clínica: adaptación al protocolo específico (orientación, no texto para insertar)',
      texts: [
        'Describe el protocolo concreto, las actividades de investigación y qué parte difiere de la atención habitual.',
        'Explica la pregunta y finalidad del estudio, sin prometer beneficio individual.',
        'Enumera las molestias o riesgos previsibles del protocolo particular y su frecuencia conocida.',
        'Incluye los riesgos menos habituales identificados en el protocolo y cómo se vigilarán.',
        'Explica los posibles daños graves, incertidumbres y medidas de atención previstas por el estudio.',
        'Distingue beneficios posibles para la persona de beneficios para el conocimiento; puede no haber beneficio directo.',
        'Describe la atención disponible fuera del estudio y la opción de no participar.',
        'Explica que rechazar o retirarse del estudio no debe presentarse como rechazo de la atención clínica habitual.'
      ]},
    otro: {id: 'other_guidance_only', procedure: 'Otro: documenta el acto clínico específico antes de redactar (orientación, no texto para insertar)',
      texts: [
        'Describe el acto concreto, sus pasos y el contexto individual; no se infiere del texto libre.',
        'Explica el objetivo clínico específico y qué se intenta lograr.',
        'Identifica los riesgos previsibles frecuentes de ese acto y paciente.',
        'Añade riesgos menos habituales que sean pertinentes para el caso.',
        'Explica complicaciones graves posibles y medidas de respuesta pertinentes.',
        'Describe los beneficios razonables sin garantizar un resultado.',
        'Presenta opciones reales para este paciente y sus diferencias.',
        'Explica las consecuencias previsibles de no realizar este acto en el contexto concreto.'
      ]}
  };
  const neutral = {id: 'no_type_guidance', procedure: 'Sin tipo seleccionado (orientación, no texto para insertar)',
    texts: Array(8).fill('Selecciona un tipo de procedimiento para mostrar un ejemplo relacionado con esa categoría.')};
  function resolveScenario() {
    const selector = modal.querySelector('#ci_template');
    const type = selector?.value || '';
    const definition = scenarios[type] || guidance[type] || neutral;
    return {
      sections: definition.sections || example.sections.map((section, index) => ({...section, text: definition.texts[index]})),
      categoryLabel: `Ejemplo correspondiente a: ${type ? selector.selectedOptions[0]?.textContent : 'tipo de procedimiento sin seleccionar'}`,
      contextLabel: 'Caso de referencia', contextText: definition.procedure,
      note: Object.hasOwn(scenarios, type)
        ? 'Caso ficticio únicamente como referencia de redacción. Adapta cada apartado al procedimiento y al paciente.'
        : 'Orientación para redactar el consentimiento específico. No hay texto de ejemplo para insertar.',
      canUseExample: Object.hasOwn(scenarios, type)
    };
  }
  const fields = {
    ci_procedimiento: 'description', ci_full_procedimiento: 'description',
    ci_objetivo: 'objective', ci_full_objetivo: 'objective',
    ci_risk_common: 'common', ci_full_risk_common: 'common',
    ci_risk_infrequent: 'uncommon', ci_full_risk_infrequent: 'uncommon',
    ci_risk_rare_serious: 'complications', ci_full_risk_rare_serious: 'complications',
    ci_beneficios_esperados: 'benefits', ci_full_beneficios: 'benefits',
    ci_alternativas: 'alternatives', ci_full_alternativas: 'alternatives',
    ci_consecuencias_no_aceptar: 'refusal', ci_full_consecuencias: 'refusal'
  };

  window.mxmedExampleFieldHelp.mount({
    modal, id: 'ci-example', fieldMap: fields, sections: example.sections, resolveScenario,
    contextLabel: 'Procedimiento de referencia', contextText: example.procedure,
    note: 'Este ejemplo es únicamente una referencia de redacción. Debe adaptarse a las características del procedimiento y del paciente.',
    onUseExample: ({section, destination}) => {
      destination.value = section.text;
      destination.dispatchEvent(new Event('input', {bubbles: true}));
    }
  });
})();
