// MXMed subscriptions: safe error mapping helpers.
(function(window){
  'use strict';

  var SEVERITIES = {
    info: true,
    warning: true,
    error: true,
    critical: true
  };

  var REQUEST_MESSAGE = 'No pudimos procesar la solicitud. Actualiza la página y vuelve a intentarlo.';
  var IDEMPOTENCY_MESSAGE = 'Esta operación ya fue procesada o no puede repetirse con los mismos datos. Actualiza la página y revisa el estado de la suscripción.';
  var NOT_FOUND_MESSAGE = 'No encontramos la información necesaria para completar la activación. Actualiza la página o contacta a soporte.';
  var MISMATCH_MESSAGE = 'No pudimos validar la relación entre el pago y la contratación. Contacta a soporte.';
  var LOCK_MESSAGE = 'El sistema está procesando esta operación. Espera unos segundos y vuelve a intentar.';
  var FALLBACK_MESSAGE = 'No pudimos completar la activación en este momento. Intenta más tarde o contacta a soporte.';

  var SENSITIVE_RE = /\b(stacktrace|trace|sql|select\s+|insert\s+|update\s+|delete\s+|pdo|exception|password|secret|token|bearer|authorization|idempotency[_ -]?key[_ -]?hash|request_hash|payload|provider[_ -]?secret)\b/i;
  var CODE_RE = /^[a-z0-9_.:-]{1,96}$/;

  var ERROR_DEFINITIONS = {
    method_not_allowed: {
      group: 'request/base',
      message: REQUEST_MESSAGE,
      severity: 'error',
      retryable: true,
      supportHint: 'Validar método HTTP permitido para el endpoint de activación.'
    },
    invalid_payment_intent_activation_payload: {
      group: 'request/base',
      message: REQUEST_MESSAGE,
      severity: 'error',
      retryable: true,
      supportHint: 'Validar payload requerido para activación post-pago.'
    },
    invalid_payload: {
      group: 'request/base',
      message: REQUEST_MESSAGE,
      severity: 'error',
      retryable: true,
      supportHint: 'Validar formato del payload enviado.'
    },
    idempotency_key_invalid: {
      group: 'request/base',
      message: REQUEST_MESSAGE,
      severity: 'error',
      retryable: true,
      supportHint: 'Validar presencia y formato de Idempotency-Key.'
    },
    idempotency_key_reused_with_different_payload: {
      group: 'idempotencia',
      message: IDEMPOTENCY_MESSAGE,
      severity: 'warning',
      retryable: false,
      supportHint: 'La llave idempotente se reutilizó con datos distintos.'
    },
    idempotency_key_not_reusable: {
      group: 'idempotencia',
      message: IDEMPOTENCY_MESSAGE,
      severity: 'warning',
      retryable: false,
      supportHint: 'La operación idempotente no puede repetirse con esta llave.'
    },
    idempotency_result_unavailable: {
      group: 'idempotencia',
      message: IDEMPOTENCY_MESSAGE,
      severity: 'warning',
      retryable: true,
      supportHint: 'El resultado idempotente no está disponible para replay seguro.'
    },
    payment_intent_not_found: {
      group: 'recursos no encontrados',
      message: NOT_FOUND_MESSAGE,
      severity: 'error',
      retryable: false,
      supportHint: 'No se encontró el payment intent esperado.'
    },
    checkout_intent_not_found: {
      group: 'recursos no encontrados',
      message: NOT_FOUND_MESSAGE,
      severity: 'error',
      retryable: false,
      supportHint: 'No se encontró el checkout intent esperado.'
    },
    payment_event_not_found: {
      group: 'recursos no encontrados',
      message: NOT_FOUND_MESSAGE,
      severity: 'error',
      retryable: false,
      supportHint: 'No se encontró el evento de pago esperado.'
    },
    contract_acceptance_not_found: {
      group: 'recursos no encontrados',
      message: NOT_FOUND_MESSAGE,
      severity: 'error',
      retryable: false,
      supportHint: 'No se encontró la aceptación contractual asociada.'
    },
    payment_intent_checkout_mismatch: {
      group: 'mismatch/scope',
      message: MISMATCH_MESSAGE,
      severity: 'critical',
      retryable: false,
      supportHint: 'El payment intent no pertenece al checkout esperado.'
    },
    payment_event_payment_intent_mismatch: {
      group: 'mismatch/scope',
      message: MISMATCH_MESSAGE,
      severity: 'critical',
      retryable: false,
      supportHint: 'El evento de pago no pertenece al payment intent esperado.'
    },
    checkout_intent_entity_mismatch: {
      group: 'mismatch/scope',
      message: MISMATCH_MESSAGE,
      severity: 'critical',
      retryable: false,
      supportHint: 'El checkout no pertenece a la entidad esperada.'
    },
    payment_intent_not_paid: {
      group: 'estados inválidos',
      message: 'El pago todavía no aparece como confirmado. Espera unos momentos y vuelve a revisar.',
      severity: 'warning',
      retryable: true,
      supportHint: 'El payment intent aún no está en estado paid/mock_paid.'
    },
    payment_event_not_processed: {
      group: 'estados inválidos',
      message: 'La confirmación del pago aún no está lista. Intenta nuevamente más tarde.',
      severity: 'warning',
      retryable: true,
      supportHint: 'El evento de pago aún no está procesado.'
    },
    checkout_intent_not_pending_payment: {
      group: 'estados inválidos',
      message: 'Esta contratación ya no está disponible para activación.',
      severity: 'warning',
      retryable: false,
      supportHint: 'El checkout ya no está en pending_payment.'
    },
    checkout_intent_expired: {
      group: 'estados inválidos',
      message: 'Esta contratación expiró. Inicia nuevamente la mejora de plan.',
      severity: 'warning',
      retryable: false,
      supportHint: 'El checkout superó su vigencia y no existe evidencia válida para activarlo con la política actual.'
    },
    contract_acceptance_not_pending_payment: {
      group: 'estados inválidos',
      message: 'La aceptación contractual ya no está disponible para esta activación.',
      severity: 'warning',
      retryable: false,
      supportHint: 'La aceptación contractual ya no está en estado pendiente post-pago.'
    },
    active_subscription_exists: {
      group: 'estados inválidos',
      message: 'Este perfil ya tiene una suscripción activa.',
      severity: 'info',
      retryable: false,
      supportHint: 'La entidad ya tiene una suscripción activa vigente.'
    },
    payment_intent_activate_subscription_lock_timeout: {
      group: 'lock timeout',
      message: LOCK_MESSAGE,
      severity: 'warning',
      retryable: true,
      supportHint: 'No se pudo adquirir el lock de activación post-pago.'
    },
    payment_intent_lock_timeout: {
      group: 'lock timeout',
      message: LOCK_MESSAGE,
      severity: 'warning',
      retryable: true,
      supportHint: 'No se pudo adquirir el lock del payment intent.'
    },
    subscription_lock_acquire_failed: {
      group: 'lock timeout',
      message: LOCK_MESSAGE,
      severity: 'warning',
      retryable: true,
      supportHint: 'No se pudo adquirir el lock de suscripción.'
    },
    payment_intent_activation_unavailable: {
      group: 'fallback interno',
      message: FALLBACK_MESSAGE,
      severity: 'error',
      retryable: true,
      supportHint: 'La activación post-pago no está disponible.'
    },
    payment_activation_ready: {
      group: 'state read-model',
      message: 'La activación post-pago está lista para revisión.',
      severity: 'info',
      retryable: false,
      supportHint: 'El state read-model indica can_activate=true.'
    },
    payment_activation_upgrade_ready: {
      group: 'state read-model',
      message: 'El pago está confirmado y la mejora de plan está lista para activarse.',
      severity: 'info',
      retryable: false,
      supportHint: 'El state read-model indica upgrade can_activate=true.'
    },
    payment_activation_not_ready: {
      group: 'state read-model',
      message: 'La activación todavía no está lista. Revisa el estado del pago o intenta más tarde.',
      severity: 'warning',
      retryable: true,
      supportHint: 'El state read-model indica can_activate=false.'
    },
    payment_activation_blocked: {
      group: 'state read-model',
      message: 'La activación post-pago aún no está disponible.',
      severity: 'warning',
      retryable: true,
      supportHint: 'Revisar reasons del state read-model antes de habilitar acciones.'
    },
    payment_activation_unavailable: {
      group: 'state read-model',
      message: 'No pudimos consultar el estado de activación post-pago.',
      severity: 'error',
      retryable: true,
      supportHint: 'Validar disponibilidad del endpoint payment-activation-state.'
    },
    payment_activation_already_done: {
      group: 'state read-model',
      message: 'La suscripción ya fue activada.',
      severity: 'info',
      retryable: false,
      supportHint: 'El state read-model indica activación previa o suscripción activa.'
    },
    unknown: {
      group: 'fallback interno',
      message: FALLBACK_MESSAGE,
      severity: 'error',
      retryable: true,
      supportHint: 'Código no reconocido por el mapper de suscripciones.'
    }
  };

  function cleanText(value){
    return String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
  }

  function normalizeCode(value){
    var code = cleanText(value).toLowerCase();
    return CODE_RE.test(code) ? code : '';
  }

  function normalizeAudience(value){
    var audience = cleanText(value).toLowerCase();
    return audience === 'support' || audience === 'dev' ? audience : 'user';
  }

  function normalizeContext(value){
    var context = cleanText(value).toLowerCase();
    if(context === 'activation' || context === 'checkout' || context === 'payment_intent' || context === 'support'){
      return context;
    }
    return 'activation';
  }

  function normalizeHttpStatus(value){
    var status = Number(value);
    return Number.isFinite(status) && status > 0 ? Math.floor(status) : 0;
  }

  function normalizeSeverity(value){
    var severity = cleanText(value).toLowerCase();
    return SEVERITIES[severity] ? severity : 'error';
  }

  function isSafeFallback(value){
    var text = cleanText(value);
    return text.length > 0 && text.length <= 240 && !SENSITIVE_RE.test(text);
  }

  function readInput(input){
    if(typeof input === 'string'){
      return {
        code: input,
        httpStatus: 0,
        context: 'activation',
        audience: 'user',
        fallback: ''
      };
    }
    if(input && typeof input === 'object'){
      return {
        code: input.code,
        httpStatus: input.httpStatus,
        context: input.context,
        audience: input.audience,
        fallback: input.fallback
      };
    }
    return {
      code: '',
      httpStatus: 0,
      context: 'activation',
      audience: 'user',
      fallback: ''
    };
  }

  function buildSupportHint(definition, code, context, httpStatus, audience){
    if(audience !== 'support' && audience !== 'dev') return '';
    var parts = [];
    parts.push('Grupo: ' + cleanText(definition.group || 'fallback interno') + '.');
    parts.push('Contexto: ' + context + '.');
    if(httpStatus) parts.push('HTTP: ' + httpStatus + '.');
    if(code && code !== 'unknown') parts.push('Código: ' + code + '.');
    if(definition.supportHint) parts.push(cleanText(definition.supportHint));
    return parts.join(' ');
  }

  function mapActivationError(input){
    var parsed = readInput(input);
    var requestedCode = normalizeCode(parsed.code);
    var audience = normalizeAudience(parsed.audience);
    var context = normalizeContext(parsed.context);
    var httpStatus = normalizeHttpStatus(parsed.httpStatus);
    var code = requestedCode || 'unknown';
    var nonCanonical = code === 'payment_event_checkout_mismatch';
    if(nonCanonical){
      code = 'unknown';
    }

    var definition = ERROR_DEFINITIONS[code] || ERROR_DEFINITIONS.unknown;
    var message = definition.message || FALLBACK_MESSAGE;

    if(code === 'unknown' && isSafeFallback(parsed.fallback)){
      message = cleanText(parsed.fallback);
    }

    return {
      message: message,
      severity: normalizeSeverity(definition.severity),
      retryable: definition.retryable === true,
      supportHint: buildSupportHint(definition, code, context, httpStatus, audience),
      exposeCode: audience === 'support' || audience === 'dev',
      code: code,
      httpStatus: httpStatus
    };
  }

  var namespace = window.MXMedSubscriptions && typeof window.MXMedSubscriptions === 'object'
    ? window.MXMedSubscriptions
    : {};

  namespace.mapActivationError = mapActivationError;
  namespace.errorMessageFor = mapActivationError;
  namespace.mxmedSubscriptionErrorMapper = mapActivationError;

  window.MXMedSubscriptions = namespace;
})(window);
