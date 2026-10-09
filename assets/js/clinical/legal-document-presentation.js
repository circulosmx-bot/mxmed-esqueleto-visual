/* Shared legal-document presentation values. Missing settings keep historical output. */
(function () {
  'use strict';
  const professionalHeaderMode = payload =>
    payload?.presentation?.professional_header === 'hidden' ? 'hidden' : 'shown';
  window.mxmedLegalDocumentPresentation = Object.freeze({ professionalHeaderMode });
})();
