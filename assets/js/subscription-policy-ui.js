// MXMed subscription policy presentation adapter. Backend read-model remains authoritative.
(function(window){
  'use strict';

  var DENIALS = Object.freeze({
    profile_not_approved: Object.freeze({ state: 'suspended_policy', tone: 'warning', message: 'Tu perfil debe completar la aprobación antes de administrar o contratar.', action: 'wait_for_profile_approval' }),
    ownership_required: Object.freeze({ state: 'suspended_policy', tone: 'warning', message: 'Reclama y verifica la titularidad del perfil para continuar.', action: 'claim_profile' }),
    ownership_disputed: Object.freeze({ state: 'suspended_policy', tone: 'danger', message: 'La titularidad está en disputa; contacta a soporte para resolverla.', action: 'contact_support' }),
    ownership_suspended: Object.freeze({ state: 'suspended_policy', tone: 'danger', message: 'La administración del perfil está suspendida por política.', action: 'contact_support' }),
    capability_not_in_plan: Object.freeze({ state: 'locked_upsell', tone: 'info', message: 'Esta función no está incluida en tu plan actual.', action: 'compare_plans' }),
    addon_required: Object.freeze({ state: 'locked_upsell', tone: 'info', message: 'Esta función requiere un complemento que todavía no está disponible para compra.', action: 'review_addon_when_available' }),
    addon_not_eligible: Object.freeze({ state: 'not_applicable', tone: 'secondary', message: 'Tu plan o tipo de perfil no es elegible para este complemento.', action: 'review_eligible_plan' }),
    implementation_not_available: Object.freeze({ state: 'blocked_dependency', tone: 'secondary', message: 'Función documentada para una fase futura; no está operativa ni disponible para compra.', action: 'none' }),
    capability_pending_activation: Object.freeze({ state: 'pending_activation', tone: 'info', message: 'La función está pendiente de activación confirmada por el backend.', action: 'wait_for_activation' }),
    dependency_missing: Object.freeze({ state: 'blocked_dependency', tone: 'warning', message: 'Falta completar una dependencia requerida para usar esta función.', action: 'complete_dependency' }),
    quota_exhausted: Object.freeze({ state: 'suspended_policy', tone: 'warning', message: 'Alcanzaste la cuota vigente de esta función.', action: 'wait_for_quota_reset' }),
    actor_role_not_allowed: Object.freeze({ state: 'suspended_policy', tone: 'danger', message: 'Tu rol actual no autoriza esta acción.', action: 'request_authorized_role' }),
    actor_scope_not_allowed: Object.freeze({ state: 'suspended_policy', tone: 'danger', message: 'La sesión actual no tiene alcance sobre este perfil.', action: 'switch_authorized_profile' }),
    subscription_pending_payment: Object.freeze({ state: 'pending_activation', tone: 'warning', message: 'La suscripción sigue pendiente de pago y aún no concede funciones.', action: 'complete_payment' }),
    subscription_in_grace: Object.freeze({ state: 'grace_limited', tone: 'warning', message: 'Tu suscripción está en periodo de regularización; algunas acciones pueden limitarse.', action: 'regularize_payment' }),
    capability_grace_limited: Object.freeze({ state: 'grace_limited', tone: 'warning', message: 'Esta acción está limitada durante el periodo de regularización.', action: 'regularize_payment' }),
    capability_read_only: Object.freeze({ state: 'archived_read_only', tone: 'secondary', message: 'Los datos se conservan en modo de sólo lectura; reactiva el plan requerido para editarlos.', action: 'reactivate_required_plan' }),
    capability_suspended: Object.freeze({ state: 'suspended_policy', tone: 'danger', message: 'La función está suspendida por una política de seguridad.', action: 'contact_support' }),
    profile_type_not_supported: Object.freeze({ state: 'not_applicable', tone: 'secondary', message: 'Esta función no aplica al tipo de perfil actual.', action: 'none' })
  });

  var COMMERCIAL = Object.freeze({
    free: Object.freeze({ tone: 'secondary', title: 'Plan Gratuito', message: 'Tu perfil conserva las funciones habilitadas del plan Gratuito.' }),
    draft: Object.freeze({ tone: 'secondary', title: 'Contratación en borrador', message: 'El borrador no concede funciones pagadas.' }),
    pending_payment: Object.freeze({ tone: 'warning', title: 'Pago pendiente', message: 'Completa el pago para continuar; pagar no equivale todavía a activar.' }),
    pending_activation: Object.freeze({ tone: 'info', title: 'Activación pendiente', message: 'El pago está registrado y la activación backend sigue pendiente.' }),
    active: Object.freeze({ tone: 'success', title: 'Suscripción activa', message: 'Las funciones se resuelven según plan, permisos, cuotas y dependencias.' }),
    past_due: Object.freeze({ tone: 'warning', title: 'Pago vencido · días 1–3', message: 'Regulariza el pago para evitar entrar al periodo de gracia.' }),
    grace: Object.freeze({ tone: 'warning', title: 'Periodo de gracia · días 4–15', message: 'Tus datos se conservan; algunas funciones pueden estar limitadas.' }),
    restricted: Object.freeze({ tone: 'danger', title: 'Suscripción restringida', message: 'Desde el día 16 las funciones premium quedan en sólo lectura cuando corresponde.' }),
    expired: Object.freeze({ tone: 'secondary', title: 'Suscripción vencida', message: 'El perfil vuelve a sus capacidades efectivas vigentes y conserva datos premium archivados.' }),
    cancelled: Object.freeze({ tone: 'secondary', title: 'Suscripción cancelada', message: 'La cancelación no elimina los datos preservados.' }),
    superseded: Object.freeze({ tone: 'secondary', title: 'Suscripción sustituida', message: 'El registro se conserva como historial comercial.' }),
    failed: Object.freeze({ tone: 'danger', title: 'Contratación no completada', message: 'La contratación falló de forma controlada y no activó funciones.' })
  });

  // Candidate A presentation metadata only. Prices, ranks and entitlements are
  // intentionally absent: those remain in the backend read-model plan catalog.
  var COMMERCIAL_PLAN_PRESENTATION = Object.freeze({
    free: Object.freeze({
      planCode: 'free',
      displayName: 'Gratuito',
      themeToken: 'free',
      iconKey: 'person',
      shortDescription: 'Perfil en Modo Gratuito',
      featuredBenefits: Object.freeze(['Perfil en línea']),
      badge: '',
      accessibilityLabel: 'Plan Gratuito'
    }),
    basic: Object.freeze({
      planCode: 'basic',
      displayName: 'Básico',
      themeToken: 'basico',
      iconKey: 'person',
      shortDescription: 'Incluye 1 de las 5 funciones',
      featuredBenefits: Object.freeze(['Perfil en línea']),
      badge: '',
      accessibilityLabel: 'Plan Básico'
    }),
    standard: Object.freeze({
      planCode: 'standard',
      displayName: 'Estándar',
      themeToken: 'estandar',
      iconKey: 'calendar_month',
      shortDescription: 'Incluye 2 de las 5 funciones',
      featuredBenefits: Object.freeze(['Perfil en línea', 'Agenda']),
      badge: '',
      accessibilityLabel: 'Plan Estándar'
    }),
    optimum: Object.freeze({
      planCode: 'optimum',
      displayName: 'Óptimo',
      themeToken: 'optimo',
      iconKey: 'clinical_notes',
      shortDescription: 'Incluye 4 de las 5 funciones',
      featuredBenefits: Object.freeze(['Perfil en línea', 'Agenda', 'Expediente', 'Recetas']),
      badge: '',
      accessibilityLabel: 'Plan Óptimo'
    }),
    professional: Object.freeze({
      planCode: 'professional',
      displayName: 'Profesional',
      themeToken: 'pro',
      iconKey: 'psychology',
      shortDescription: 'Incluye las funciones operativas disponibles del plan profesional.',
      featuredBenefits: Object.freeze(['Perfil en línea', 'Agenda', 'Expediente', 'Recetas']),
      badge: '',
      accessibilityLabel: 'Plan Profesional'
    })
  });

  var QA_REVIEW_PLAN_CATALOG = Object.freeze([
    Object.freeze({ code: 'free', label: 'Gratuito', rank: 0, capabilities: Object.freeze([
      Object.freeze({ code: 'profile_publication', label: 'Perfil en directorio', operational: true })
    ]), prices: Object.freeze([]) }),
    Object.freeze({ code: 'basic', label: 'Básico', rank: 1, capabilities: Object.freeze([
      Object.freeze({ code: 'profile_publication', label: 'Perfil en directorio', operational: true })
    ]), prices: Object.freeze([
      Object.freeze({ billing_period: 'annual', amount_cents: 699000, currency: 'MXN', price_version: 'qa-visual-v1' }),
      Object.freeze({ billing_period: 'monthly', amount_cents: 79000, currency: 'MXN', price_version: 'qa-visual-v1' })
    ]) }),
    Object.freeze({ code: 'standard', label: 'Estándar', rank: 2, capabilities: Object.freeze([
      Object.freeze({ code: 'profile_publication', label: 'Perfil en directorio', operational: true }),
      Object.freeze({ code: 'agenda', label: 'Agenda en línea', operational: true })
    ]), prices: Object.freeze([
      Object.freeze({ billing_period: 'annual', amount_cents: 999000, currency: 'MXN', price_version: 'qa-visual-v1' }),
      Object.freeze({ billing_period: 'monthly', amount_cents: 109000, currency: 'MXN', price_version: 'qa-visual-v1' })
    ]) }),
    Object.freeze({ code: 'optimum', label: 'Óptimo', rank: 3, capabilities: Object.freeze([
      Object.freeze({ code: 'profile_publication', label: 'Perfil en directorio', operational: true }),
      Object.freeze({ code: 'agenda', label: 'Agenda en línea', operational: true }),
      Object.freeze({ code: 'clinical_record', label: 'Expediente clínico', operational: true }),
      Object.freeze({ code: 'prescriptions', label: 'Recetas digitales', operational: true })
    ]), prices: Object.freeze([
      Object.freeze({ billing_period: 'annual', amount_cents: 1299000, currency: 'MXN', price_version: 'qa-visual-v1' }),
      Object.freeze({ billing_period: 'monthly', amount_cents: 139000, currency: 'MXN', price_version: 'qa-visual-v1' })
    ]) }),
    Object.freeze({ code: 'professional', label: 'Profesional', rank: 4, capabilities: Object.freeze([
      Object.freeze({ code: 'profile_publication', label: 'Perfil en directorio', operational: true }),
      Object.freeze({ code: 'agenda', label: 'Agenda en línea', operational: true }),
      Object.freeze({ code: 'clinical_record', label: 'Expediente clínico', operational: true }),
      Object.freeze({ code: 'prescriptions', label: 'Recetas digitales', operational: true }),
      Object.freeze({ code: 'ai_agenda_agent', label: 'Agente de Agenda con IA', operational: false })
    ]), prices: Object.freeze([
      Object.freeze({ billing_period: 'annual', amount_cents: 2199000, currency: 'MXN', price_version: 'qa-visual-v1' }),
      Object.freeze({ billing_period: 'monthly', amount_cents: 239000, currency: 'MXN', price_version: 'qa-visual-v1' })
    ]) })
  ]);

  function text(value){
    return String(value == null ? '' : value).trim();
  }

  function planCatalog(readModel){
    return Array.isArray(readModel && readModel.plan_catalog)
      ? readModel.plan_catalog.filter(function(plan){
        return plan && typeof plan === 'object' && text(plan.code) && Number.isInteger(Number(plan.rank));
      })
      : [];
  }

  function normalizePlanCode(value, readModel){
    var raw = text(value).toLocaleLowerCase('es-MX');
    if(!raw) return null;
    var aliases = readModel && readModel.plan_aliases && typeof readModel.plan_aliases === 'object'
      ? readModel.plan_aliases
      : {};
    var canonical = text(aliases[raw] || raw);
    return planCatalog(readModel).some(function(plan){ return plan.code === canonical; }) ? canonical : null;
  }

  function commercialPlanPresentation(value, readModel){
    var code = normalizePlanCode(value, readModel);
    return code && COMMERCIAL_PLAN_PRESENTATION[code]
      ? COMMERCIAL_PLAN_PRESENTATION[code]
      : null;
  }

  function themeTokenForPlan(value, readModel){
    var presentation = commercialPlanPresentation(value, readModel);
    return presentation ? presentation.themeToken : '';
  }

  function qaReviewFixtureEnabled(locationLike){
    var location = locationLike && typeof locationLike === 'object' ? locationLike : {};
    var hostname = text(location.hostname).toLowerCase();
    var protocol = text(location.protocol).toLowerCase();
    var search = text(location.search);
    var localHost = hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1';
    var explicitReview = /(?:^|[?&])mxmed_subscription_review=1(?:&|$)/.test(search);
    return localHost && protocol === 'http:' && explicitReview;
  }

  function qaReviewReadModel(locationLike){
    if(!qaReviewFixtureEnabled(locationLike)) return null;
    return {
      policy_version: 'MXMED_PLAN_CAPABILITY_POLICY_V1',
      plan_aliases: {
        free: 'free', gratis: 'free', gratuito: 'free', free_default: 'free',
        basic: 'basic', basico: 'basic', 'básico': 'basic',
        standard: 'standard', estandar: 'standard', 'estándar': 'standard',
        optimum: 'optimum', optimo: 'optimum', 'óptimo': 'optimum',
        professional: 'professional', profesional: 'professional', pro: 'professional'
      },
      plan_catalog: QA_REVIEW_PLAN_CATALOG.map(function(plan){
        return {
          code: plan.code,
          label: plan.label,
          rank: plan.rank,
          capabilities: plan.capabilities.map(function(capability){ return Object.assign({}, capability); }),
          prices: plan.prices.map(function(price){ return Object.assign({ source: 'subscription_plan_prices_backend' }, price); })
        };
      }),
      profile_approval_state: 'approved',
      ownership_state: 'claimed',
      purchase_allowed: true,
      admin_allowed: true,
      commercial_state: 'free',
      status: 'free_default',
      capabilities: {},
      denial_reasons: [],
      archived_module_summaries: [],
      future_capabilities: [{ code: 'ai_agenda_agent', operational: false }],
      addon_eligibility: {},
      qa_review_fixture: true,
      qa_review_fixture_version: 'MXMED_SUBSCRIPTIONS_VISUAL_QA_FIXTURE_V1'
    };
  }

  function pricesByPeriod(plan){
    var result = {};
    (Array.isArray(plan && plan.prices) ? plan.prices : []).forEach(function(price){
      var period = text(price && price.billing_period).toLowerCase();
      var cents = Number(price && price.amount_cents);
      if(!period || !Number.isInteger(cents) || cents < 0) return;
      result[period] = {
        amount_cents: cents,
        currency: text(price.currency || 'MXN').toUpperCase(),
        price_version: text(price.price_version),
        source: 'subscription_plan_prices_backend'
      };
    });
    return result;
  }

  function quotaFeatureLabels(plan){
    var quotas = plan && plan.quotas && typeof plan.quotas === 'object' ? plan.quotas : {};
    var labels = [];
    var gallery = quotas.public_gallery && quotas.public_gallery.value;
    var aiImages = quotas.ai_image_generation && quotas.ai_image_generation.value;
    var aiWriting = quotas.ai_content_writing && quotas.ai_content_writing.value;
    var agenda = quotas.agenda && quotas.agenda.value;
    if(Number.isInteger(Number(gallery))){
      labels.push('Galería: ' + Number(gallery) + ' espacios activos');
    }
    if(Number.isInteger(Number(aiImages)) && Number(aiImages) > 0){
      labels.push('IA imágenes: ' + Number(aiImages) + '/mes (futura; no operativa)');
    }
    if(Number.isInteger(Number(aiWriting)) && Number(aiWriting) > 0){
      labels.push('IA redacción: ' + Number(aiWriting) + '/mes (futura; no operativa)');
    }
    if(agenda === 'unlimited'){
      labels.push('Agenda: uso ilimitado');
    }
    return labels;
  }

  function plansFromReadModel(readModel, options){
    var includeFree = options && options.includeFree === true;
    return planCatalog(readModel)
      .filter(function(plan){ return includeFree || plan.code !== 'free'; })
      .sort(function(a, b){ return Number(a.rank) - Number(b.rank); })
      .map(function(plan){
        var presentation = commercialPlanPresentation(plan.code, readModel);
        if(!presentation) return null;
        return {
          id: plan.code,
          code: plan.code,
          name: presentation.displayName || text(plan.label || plan.code),
          rank: Number(plan.rank),
          tagline: presentation.shortDescription,
          features: Array.prototype.slice.call(presentation.featuredBenefits),
          themeToken: presentation.themeToken,
          iconKey: presentation.iconKey,
          badge: presentation.badge,
          accessibilityLabel: presentation.accessibilityLabel,
          prices: pricesByPeriod(plan),
          priceAuthority: 'subscription_plan_prices_backend'
        };
      })
      .filter(Boolean);
  }

  function mapDenial(code){
    var canonical = text(code).toLowerCase();
    var definition = DENIALS[canonical];
    return definition
      ? Object.assign({ code: canonical }, definition)
      : { code: canonical || 'unknown', state: 'suspended_policy', tone: 'danger', message: 'La acción no está disponible con el estado actual.', action: 'contact_support' };
  }

  function statusPresentation(readModel){
    var state = text(readModel && (readModel.commercial_state || readModel.status)).toLowerCase();
    var base = COMMERCIAL[state] || COMMERCIAL.draft;
    var denials = Array.isArray(readModel && readModel.denial_reasons)
      ? readModel.denial_reasons.map(mapDenial)
      : [];
    var capabilityStates = [];
    var capabilities = readModel && readModel.capabilities && typeof readModel.capabilities === 'object'
      ? readModel.capabilities
      : {};
    Object.keys(capabilities).forEach(function(code){
      var resolved = capabilities[code] || {};
      capabilityStates.push({
        code: code,
        state: text(resolved.state) || 'not_applicable',
        allowed: resolved.allowed === true,
        denial: resolved.denial_reason ? mapDenial(resolved.denial_reason) : null
      });
    });
    var purchaseAllowed = readModel && readModel.purchase_allowed === true;
    var tone = purchaseAllowed ? base.tone : (state === 'active' || state === 'free' ? 'warning' : base.tone);
    return {
      state: state || 'draft',
      tone: tone,
      title: base.title,
      message: base.message,
      approvalState: text(readModel && readModel.profile_approval_state) || 'pending_review',
      ownershipState: text(readModel && readModel.ownership_state) || 'unclaimed',
      purchaseAllowed: purchaseAllowed,
      adminAllowed: readModel && readModel.admin_allowed === true,
      scheduledPlan: readModel && readModel.scheduled_plan || null,
      scheduledEffectiveAt: text(readModel && readModel.scheduled_effective_at) || null,
      cancelScheduledChangeAllowed: readModel && readModel.cancel_scheduled_change_allowed === true,
      scheduledAddOnImpacts: Array.isArray(readModel && readModel.scheduled_addon_impacts) ? readModel.scheduled_addon_impacts : [],
      grace: readModel && readModel.grace && typeof readModel.grace === 'object' ? readModel.grace : {},
      addOnEligibility: readModel && readModel.addon_eligibility && typeof readModel.addon_eligibility === 'object'
        ? Object.keys(readModel.addon_eligibility)
          .map(function(code){ return readModel.addon_eligibility[code]; })
          .filter(function(item){ return item && typeof item === 'object'; })
        : [],
      archivedModules: Array.isArray(readModel && readModel.archived_module_summaries) ? readModel.archived_module_summaries : [],
      futureCapabilities: Array.isArray(readModel && readModel.future_capabilities) ? readModel.future_capabilities : [],
      denials: denials,
      capabilityStates: capabilityStates
    };
  }

  function summaryFeatureChips(readModel){
    var definitions = [
      ['profile_publication', 'Perfil en línea'],
      ['agenda', 'Agenda en línea'],
      ['clinical_record', 'Expediente clínico'],
      ['prescriptions', 'Recetas digitales'],
      ['ai_agenda_agent', 'Agente de Agenda con IA']
    ];
    var catalog = planCatalog(readModel);
    return definitions.map(function(definition){
      var code = definition[0];
      return {
        key: code,
        capabilityCode: code,
        label: definition[1],
        plans: catalog.filter(function(plan){
          return (Array.isArray(plan.capabilities) ? plan.capabilities : []).some(function(capability){
            return capability && capability.code === code;
          });
        }).map(function(plan){ return plan.code; }),
        operational: catalog.some(function(plan){
          return (Array.isArray(plan.capabilities) ? plan.capabilities : []).some(function(capability){
            return capability && capability.code === code && capability.operational === true;
          });
        })
      };
    });
  }

  window.MXMedPlanCapabilityUI = Object.freeze({
    commercialPlanPresentation: commercialPlanPresentation,
    denialCodes: Object.freeze(Object.keys(DENIALS)),
    mapDenial: mapDenial,
    normalizePlanCode: normalizePlanCode,
    plansFromReadModel: plansFromReadModel,
    pricesByPeriod: pricesByPeriod,
    qaReviewFixtureEnabled: qaReviewFixtureEnabled,
    qaReviewReadModel: qaReviewReadModel,
    quotaFeatureLabels: quotaFeatureLabels,
    statusPresentation: statusPresentation,
    summaryFeatureChips: summaryFeatureChips,
    themeTokenForPlan: themeTokenForPlan
  });
})(typeof window !== 'undefined' ? window : this);
