// Small JSON-state baseline comparison shared by opt-in explicit-save forms.
(function(){
  function normalized(value){
    if(Array.isArray(value)) return value.map(normalized);
    if(value && typeof value === 'object'){
      return Object.fromEntries(Object.keys(value).sort().map(key=> [key, normalized(value[key])]));
    }
    return value;
  }
  window.mxmedCreateDirtyTracker = function({ readState }){
    let persistedBaseline = null;
    const currentState = ()=> normalized(readState());
    return {
      captureBaseline(value = currentState()) { persistedBaseline = normalized(value); },
      currentState,
      isDirty() { return persistedBaseline !== null && JSON.stringify(currentState()) !== JSON.stringify(persistedBaseline); }
    };
  };
})();
