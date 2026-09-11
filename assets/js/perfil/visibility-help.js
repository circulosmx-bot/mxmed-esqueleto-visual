(() => {
  'use strict';

  const modalElement = document.getElementById('mx-visibility-help-modal');
  const trigger = document.getElementById('mx-visibility-help-trigger');
  const infoPanel = document.getElementById('p-info');
  const generalTab = document.getElementById('t-info-datos-tab');
  const generalPane = document.getElementById('t-info-datos');
  if (!modalElement || !trigger || !infoPanel || !generalTab || !generalPane || !window.bootstrap?.Modal) return;

  const identity = String(document.body.dataset.doctorId || document.body.dataset.userId || 'browser').trim() || 'browser';
  const seenKey = `mxmed.ui.visibility_help_seen.v1:${identity}`;
  const modal = bootstrap.Modal.getOrCreateInstance(modalElement, {backdrop: true, keyboard: true, focus: true});
  let autoOpenAttempted = false;
  let lastManualTrigger = false;

  const hasSeen = () => {
    try { return localStorage.getItem(seenKey) === '1'; } catch (_) { return false; }
  };
  const markSeen = () => {
    try { localStorage.setItem(seenKey, '1'); } catch (_) {}
  };
  const isGeneralInformationVisible = () => {
    const panelVisible = !infoPanel.classList.contains('d-none') && getComputedStyle(infoPanel).display !== 'none';
    return panelVisible && generalTab.classList.contains('active') && generalPane.classList.contains('active');
  };
  const maybeAutoOpen = () => {
    if (autoOpenAttempted || hasSeen() || !isGeneralInformationVisible()) return;
    autoOpenAttempted = true;
    lastManualTrigger = false;
    modal.show();
  };

  trigger.addEventListener('click', () => { lastManualTrigger = true; });
  generalTab.addEventListener('shown.bs.tab', () => setTimeout(maybeAutoOpen, 0));
  document.addEventListener('click', event => {
    if (event.target.closest('[data-panel="p-info"]')) setTimeout(maybeAutoOpen, 0);
  });
  modalElement.addEventListener('hide.bs.modal', markSeen);
  modalElement.addEventListener('hidden.bs.modal', () => {
    if (lastManualTrigger) trigger.focus();
    lastManualTrigger = false;
  });

  const observer = new MutationObserver(maybeAutoOpen);
  observer.observe(infoPanel, {attributes: true, attributeFilter: ['class']});
  observer.observe(generalPane, {attributes: true, attributeFilter: ['class']});
  setTimeout(maybeAutoOpen, 0);
})();
