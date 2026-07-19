// Deterministic DEV/QA-only fixtures for visual review of the existing backend preview contract.
(function(window){
  'use strict';

  var CONTRACT_VERSION = 'MXMED_SUBSCRIPTIONS_PREVIEW_QA_FIXTURE_V1';
  var NEW_SUBSCRIPTION = Object.freeze({
    'basic:annual': Object.freeze({ amount_cents: 699000, unit_amount_cents: 699000, annual_amount_cents: 699000, monthly_annualized_amount_cents: null, annual_savings_amount_cents: 0, initial_cycles: 1, period_days: 365, payment_execution_enabled: true }),
    'basic:monthly': Object.freeze({ amount_cents: 237000, unit_amount_cents: 79000, annual_amount_cents: 699000, monthly_annualized_amount_cents: 948000, annual_savings_amount_cents: 249000, initial_cycles: 3, period_days: 30, payment_execution_enabled: false }),
    'standard:annual': Object.freeze({ amount_cents: 999000, unit_amount_cents: 999000, annual_amount_cents: 999000, monthly_annualized_amount_cents: null, annual_savings_amount_cents: 0, initial_cycles: 1, period_days: 365, payment_execution_enabled: true }),
    'standard:monthly': Object.freeze({ amount_cents: 327000, unit_amount_cents: 109000, annual_amount_cents: 999000, monthly_annualized_amount_cents: 1308000, annual_savings_amount_cents: 309000, initial_cycles: 3, period_days: 30, payment_execution_enabled: false }),
    'optimum:annual': Object.freeze({ amount_cents: 1299000, unit_amount_cents: 1299000, annual_amount_cents: 1299000, monthly_annualized_amount_cents: null, annual_savings_amount_cents: 0, initial_cycles: 1, period_days: 365, payment_execution_enabled: true }),
    'optimum:monthly': Object.freeze({ amount_cents: 417000, unit_amount_cents: 139000, annual_amount_cents: 1299000, monthly_annualized_amount_cents: 1668000, annual_savings_amount_cents: 369000, initial_cycles: 3, period_days: 30, payment_execution_enabled: false }),
    'professional:annual': Object.freeze({ amount_cents: 2199000, unit_amount_cents: 2199000, annual_amount_cents: 2199000, monthly_annualized_amount_cents: null, annual_savings_amount_cents: 0, initial_cycles: 1, period_days: 365, payment_execution_enabled: true }),
    'professional:monthly': Object.freeze({ amount_cents: 717000, unit_amount_cents: 239000, annual_amount_cents: 2199000, monthly_annualized_amount_cents: 2868000, annual_savings_amount_cents: 669000, initial_cycles: 3, period_days: 30, payment_execution_enabled: false })
  });
  var UPGRADE = Object.freeze({
    'basic:standard': Object.freeze({ current_price_cents: 699000, target_price_cents: 999000, adjustment_amount_cents: 290137 }),
    'basic:optimum': Object.freeze({ current_price_cents: 699000, target_price_cents: 1299000, adjustment_amount_cents: 580274 }),
    'basic:professional': Object.freeze({ current_price_cents: 699000, target_price_cents: 2199000, adjustment_amount_cents: 1450685 }),
    'standard:optimum': Object.freeze({ current_price_cents: 999000, target_price_cents: 1299000, adjustment_amount_cents: 290137 }),
    'standard:professional': Object.freeze({ current_price_cents: 999000, target_price_cents: 2199000, adjustment_amount_cents: 1160548 }),
    'optimum:professional': Object.freeze({ current_price_cents: 1299000, target_price_cents: 2199000, adjustment_amount_cents: 870411 })
  });

  function text(value){
    return String(value == null ? '' : value).trim();
  }

  function queryHas(search, key, expected){
    var expression = new RegExp('(?:^|[?&])' + key + '=' + expected + '(?:&|$)');
    return expression.test(text(search));
  }

  function enabled(locationLike, readModel){
    var location = locationLike && typeof locationLike === 'object' ? locationLike : {};
    var hostname = text(location.hostname).toLowerCase();
    var protocol = text(location.protocol).toLowerCase();
    var localHost = hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1';
    return localHost
      && protocol === 'http:'
      && queryHas(location.search, 'mxmed_subscription_review', '1')
      && readModel
      && readModel.qa_review_fixture === true;
  }

  function failureMode(locationLike){
    return queryHas(locationLike && locationLike.search, 'mxmed_preview', 'error');
  }

  function nextAction(routeType, billingPeriod){
    if(routeType === 'new_subscription' && billingPeriod === 'monthly'){
      return { type: 'monthly_recurring_not_ready', enabled: false };
    }
    return { type: 'review_secure_payment', enabled: true };
  }

  function newSubscriptionPreview(payload){
    var target = text(payload && payload.target_plan_code).toLowerCase();
    var period = text(payload && payload.billing_period).toLowerCase();
    var fixture = NEW_SUBSCRIPTION[target + ':' + period];
    if(!fixture) return null;
    var monthly = period === 'monthly';
    return {
      route_type: 'new_subscription',
      current_plan_code: null,
      target_plan_code: target,
      billing_period: period,
      currency: 'MXN',
      amount_cents: fixture.amount_cents,
      target_price_cents: fixture.unit_amount_cents,
      adjustment_amount_cents: 0,
      remaining_days: null,
      period_days: fixture.period_days,
      amount_source: 'server_recalculated_qa_fixture',
      pricing_contract: {
        contract_version: 'free_monthly_advance_v1',
        plan_code: target,
        billing_period: period,
        currency: 'MXN',
        unit_amount_cents: fixture.unit_amount_cents,
        initial_cycles: fixture.initial_cycles,
        initial_amount_cents: fixture.amount_cents,
        regular_recurring_amount_cents: fixture.unit_amount_cents,
        annual_amount_cents: fixture.annual_amount_cents,
        monthly_annualized_amount_cents: fixture.monthly_annualized_amount_cents,
        annual_savings_amount_cents: fixture.annual_savings_amount_cents,
        is_prorated: false,
        payment_execution_enabled: fixture.payment_execution_enabled,
        payment_execution_block_reason: monthly ? 'monthly_recurring_not_ready' : null,
        price_source: 'subscription_plan_prices_backend_qa_fixture',
        price_version: 'qa-visual-v1'
      },
      warnings: monthly ? ['monthly_recurring_not_ready'] : [],
      reasons: [],
      next_action: nextAction('new_subscription', period),
      qa_fixture_version: CONTRACT_VERSION
    };
  }

  function upgradePreview(payload){
    var current = text(payload && payload.current_plan_code).toLowerCase();
    var target = text(payload && payload.target_plan_code).toLowerCase();
    var period = text(payload && payload.billing_period).toLowerCase() || 'annual';
    var fixture = UPGRADE[current + ':' + target];
    if(!fixture) return null;
    return {
      route_type: 'upgrade_subscription',
      current_plan_code: current,
      target_plan_code: target,
      billing_period: period,
      currency: 'MXN',
      amount_cents: fixture.adjustment_amount_cents,
      adjustment_amount_cents: fixture.adjustment_amount_cents,
      current_price_cents: fixture.current_price_cents,
      target_price_cents: fixture.target_price_cents,
      renewal_amount_cents: fixture.target_price_cents,
      remaining_days: 353,
      period_days: period === 'monthly' ? 30 : 365,
      current_expires_at: '2027-07-06 00:00:00',
      amount_source: 'server_recalculated_qa_fixture',
      warnings: [],
      reasons: ['upgrade_prorated_for_remaining_period'],
      next_action: nextAction('upgrade_subscription', period),
      qa_fixture_version: CONTRACT_VERSION
    };
  }

  async function preview(payload, options){
    var location = options && options.location;
    var readModel = options && options.readModel;
    if(!enabled(location, readModel)){
      return { ok: false, httpStatus: 0, errorCode: 'qa_fixture_disabled', message: 'QA preview fixture is disabled.' };
    }
    if(failureMode(location)){
      return { ok: false, httpStatus: 503, errorCode: 'qa_preview_unavailable', message: 'No pudimos calcular el ajuste en este momento.' };
    }
    var routeType = text(payload && payload.route_type).toLowerCase();
    var data = routeType === 'new_subscription'
      ? newSubscriptionPreview(payload)
      : routeType === 'upgrade_subscription'
        ? upgradePreview(payload)
        : null;
    if(!data){
      return { ok: false, httpStatus: 409, errorCode: 'qa_fixture_not_found', message: 'No existe una respuesta QA para esta combinación.' };
    }
    return { ok: true, httpStatus: 200, data: data };
  }

  window.MXMedSubscriptionPreviewQAAdapter = Object.freeze({
    contractVersion: CONTRACT_VERSION,
    enabled: enabled,
    failureMode: failureMode,
    preview: preview
  });
})(typeof window !== 'undefined' ? window : this);
