(function (window, document) {
  window.MXMed = window.MXMed || {};

  window.MXMed.initClinicalEmbedKit = function initClinicalEmbedKit(opts) {
    opts = opts || {};
    var embedOnly = opts.embedOnly !== false;
    var root = opts.root && typeof opts.root.querySelector === 'function' ? opts.root : document;

    if (!root || root.__mxmedClinicalEmbedKitInit) {
      return;
    }
    root.__mxmedClinicalEmbedKitInit = true;

    if (typeof window.MXMed.initDocOverlay === 'function') {
      window.MXMed.initDocOverlay({
        embedOnly: embedOnly,
        root: root
      });
    }
  };
})(window, document);
