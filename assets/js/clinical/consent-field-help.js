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
    modal, id: 'ci-example', fieldMap: fields, sections: example.sections,
    contextLabel: 'Procedimiento de referencia', contextText: example.procedure,
    note: 'Este ejemplo es únicamente una referencia de redacción. Debe adaptarse a las características del procedimiento y del paciente.',
    onUseExample: ({section, destination}) => {
      destination.value = section.text;
      destination.dispatchEvent(new Event('input', {bubbles: true}));
    }
  });
})();
