(() => {
  'use strict';

  document.querySelectorAll('[data-mxpp-content-panel]').forEach((panel) => {
    const views = new Map(Array.from(panel.querySelectorAll('[data-mxpp-profile-view]'), (view) => [view.dataset.mxppProfileView, view]));
    const triggers = Array.from(document.querySelectorAll('[data-mxpp-profile-view-trigger]')).filter((button) => button.getAttribute('aria-controls') === panel.id);
    const locationLabel = panel.getAttribute('aria-label');
    let activeView = 'location';
    let previousTrigger = null;

    if (!views.has('location')) return;

    function syncOfficeActions() {
      const selected = panel.querySelector('[data-mxpp-consultorio-panel]:not([hidden])');
      panel.querySelectorAll('[data-mxpp-office-actions]').forEach((actions) => {
        actions.hidden = actions.dataset.mxppOfficeActions !== selected?.id || !actions.querySelector('a');
      });
      const closure = panel.querySelector('.mxpp-content-panel__closure');
      if (closure) closure.hidden = !closure.querySelector('[data-mxpp-office-actions]:not([hidden]) a, [data-mxpp-agenda-jump]');
    }
    // Keep the inactive consultation's intrinsic size current as offices change.
    const officeObserver = new MutationObserver(syncOfficeActions);
    panel.querySelectorAll('[data-mxpp-consultorio-panel]').forEach((office) => {
      officeObserver.observe(office, { attributes: true, attributeFilter: ['hidden'] });
    });
    syncOfficeActions();

    panel.querySelectorAll('[data-mxpp-agenda-jump]').forEach((link) => {
      link.addEventListener('click', (event) => {
        const agenda = document.getElementById('proximas-citas');
        if (!agenda) return;
        event.preventDefault();
        // The existing agenda controls its own office availability. Preserve its state.
        agenda.setAttribute('tabindex', '-1');
        agenda.focus({ preventScroll: true });
        agenda.scrollIntoView({ block: 'start', behavior: 'auto' });
      });
    });

    function setView(next, trigger) {
      if (!views.has(next) || next === activeView) return;
      syncOfficeActions();
      const isLocation = next === 'location';
      if (!isLocation) previousTrigger = trigger;
      views.forEach((view, key) => {
        const inactive = key !== next;
        view.hidden = inactive;
        view.inert = inactive;
        view.setAttribute('aria-hidden', String(inactive));
      });
      panel.setAttribute('aria-label', isLocation ? locationLabel : views.get(next).dataset.mxppViewTitle);
      triggers.forEach((button) => {
        if (button.dataset.mxppProfileViewTrigger !== 'location') button.setAttribute('aria-pressed', String(button.dataset.mxppProfileViewTrigger === next));
      });
      activeView = next;
      panel.dataset.profileView = next;
      if (isLocation) {
        // The return button is now hidden; leave keyboard focus on a visible control.
        previousTrigger?.focus({ preventScroll: true });
      }
    }

    triggers.forEach((button) => {
      button.addEventListener('click', () => setView(button.dataset.mxppProfileViewTrigger, button));
    });
  });
})();
