// Simple active context + event bus
(function(){
  const STORAGE_KEY = 'mxmed.activeContext';
  const KEYS = ['patient_id','encounter_id','hospital_stay_id','care_setting','service'];
  const emptyContext = () => ({
    patient_id: null,
    encounter_id: null,
    hospital_stay_id: null,
    care_setting: null,
    service: null
  });

  let activeContext = emptyContext();

  const readStored = () => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      const data = JSON.parse(raw);
      return (data && typeof data === 'object') ? data : null;
    } catch (_) {
      return null;
    }
  };

  const normalize = (input) => {
    const next = emptyContext();
    if (!input || typeof input !== 'object') return next;
    KEYS.forEach((k) => {
      if (!Object.prototype.hasOwnProperty.call(input, k)) return;
      const val = input[k];
      if (val === undefined || val === '') {
        next[k] = null;
      } else {
        next[k] = val;
      }
    });
    return next;
  };

  const persist = () => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(activeContext));
    } catch (_) {}
  };

  const emit = () => {
    try {
      document.dispatchEvent(new CustomEvent('mxmed:context:changed', { detail: { ...activeContext } }));
    } catch (_) {}
  };

  const getContext = () => ({ ...activeContext });

  const setContext = (partial) => {
    if (!partial || typeof partial !== 'object') return getContext();
    let changed = false;
    const next = { ...activeContext };
    KEYS.forEach((k) => {
      if (!Object.prototype.hasOwnProperty.call(partial, k)) return;
      const val = partial[k] === '' ? null : partial[k];
      if (next[k] !== val) {
        next[k] = val;
        changed = true;
      }
    });
    if (!changed) return getContext();
    activeContext = next;
    persist();
    emit();
    return getContext();
  };

  const clearContext = () => {
    activeContext = emptyContext();
    persist();
    emit();
    return getContext();
  };

  const onContextChanged = (callback) => {
    if (typeof callback !== 'function') return () => {};
    const handler = (ev) => callback(ev?.detail || getContext());
    document.addEventListener('mxmed:context:changed', handler);
    return () => document.removeEventListener('mxmed:context:changed', handler);
  };

  const stored = readStored();
  if (stored) activeContext = { ...activeContext, ...normalize(stored) };

  window.mxmedContext = { getContext, setContext, clearContext, onContextChanged };
  window.mxmedDebugContext = () => { console.log(getContext()); return getContext(); };
  window.mxmedSetContextDemo = () => setContext({ patient_id: 'demo|1990-01-01|M', care_setting: 'consulta' });
})();
