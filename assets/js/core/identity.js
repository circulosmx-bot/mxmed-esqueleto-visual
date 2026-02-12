// Identity helper for clinical UI (legacy -> canonical bridge).
// Runtime export: window.mxmedIdentity.{buildLegacyPatientId, resolveCanonicalPatientId}
(function (global) {
  'use strict';

  const cache = new Map();
  const pending = new Map();
  const hasOwn = Object.prototype.hasOwnProperty;
  const globalCache = (global.__mxmed_canonical_cache = global.__mxmed_canonical_cache || {});

  function clean(value) {
    return String(value ?? '').trim();
  }

  function remember(legacy, canonical) {
    const value = clean(canonical) || null;
    cache.set(legacy, value);
    globalCache[legacy] = value;
    return value;
  }

  function buildLegacyPatientId(nombreCompleto, dob, sexoVal, normalizeFn) {
    const normalize = typeof normalizeFn === 'function' ? normalizeFn : (v) => clean(v);
    const legacy = normalize([nombreCompleto, dob, sexoVal].join('|'));
    return legacy || 'anon';
  }

  async function resolveCanonicalPatientId(legacyPatientId) {
    const legacy = clean(legacyPatientId);
    if (legacy === '' || legacy === 'anon') return null;

    if (cache.has(legacy)) return cache.get(legacy);
    if (hasOwn.call(globalCache, legacy)) return remember(legacy, globalCache[legacy]);
    if (pending.has(legacy)) return pending.get(legacy);

    const request = fetch('api/clinical/index.php/patient-id/resolve', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        legacy: {
          legacy_patient_id: legacy,
          source: 'ui'
        }
      })
    }).then(async (res) => {
      let payload = null;
      try {
        payload = await res.json();
      } catch (_) {
        return remember(legacy, null);
      }

      if (!payload || typeof payload !== 'object') return remember(legacy, null);
      if (payload.ok === true) return remember(legacy, payload?.data?.patient_id ?? null);
      return remember(legacy, null);
    }).catch(() => {
      return remember(legacy, null);
    }).finally(() => {
      pending.delete(legacy);
    });

    pending.set(legacy, request);
    return request;
  }

  global.mxmedIdentity = global.mxmedIdentity || {};
  global.mxmedIdentity.buildLegacyPatientId = buildLegacyPatientId;
  global.mxmedIdentity.resolveCanonicalPatientId = resolveCanonicalPatientId;
})(window);
