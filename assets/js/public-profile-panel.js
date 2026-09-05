(() => {
  'use strict';

  document.querySelectorAll('[data-mxpp-content-panel]').forEach((panel) => {
    const views = new Map(Array.from(panel.querySelectorAll('[data-mxpp-profile-view]'), (view) => [view.dataset.mxppProfileView, view]));
    const triggers = Array.from(document.querySelectorAll('[data-mxpp-profile-view-trigger]')).filter((button) => button.getAttribute('aria-controls') === panel.id);
    const locationHeading = panel.querySelectorAll('[data-mxpp-location-heading]');
    const title = panel.querySelector('[data-mxpp-content-title]');
    const returnButton = panel.querySelector('[data-mxpp-profile-view-trigger="location"]');
    const locationLabel = panel.getAttribute('aria-label');
    let activeView = 'location';
    let previousTrigger = null;

    if (!views.has('location') || !title || !returnButton) return;

    function setView(next, trigger) {
      if (!views.has(next) || next === activeView) return;
      // Keep the surrounding sections stable when the replacement is shorter.
      if (activeView === 'location') {
        panel.style.minHeight = `${panel.getBoundingClientRect().height}px`;
      }
      const isLocation = next === 'location';
      if (!isLocation) previousTrigger = trigger;
      views.forEach((view, key) => { view.hidden = key !== next; });
      locationHeading.forEach((element) => { element.hidden = !isLocation; });
      title.textContent = isLocation ? '' : views.get(next).dataset.mxppViewTitle;
      title.hidden = isLocation;
      returnButton.hidden = isLocation;
      panel.setAttribute('aria-label', isLocation ? locationLabel : title.textContent);
      triggers.forEach((button) => {
        if (button !== returnButton) button.setAttribute('aria-pressed', String(button.dataset.mxppProfileViewTrigger === next));
      });
      activeView = next;
      panel.dataset.profileView = next;
      if (isLocation) {
        panel.style.removeProperty('min-height');
        // The return button is now hidden; leave keyboard focus on a visible control.
        previousTrigger?.focus({ preventScroll: true });
      }
    }

    triggers.forEach((button) => {
      button.addEventListener('click', () => setView(button.dataset.mxppProfileViewTrigger, button));
    });
  });
})();
