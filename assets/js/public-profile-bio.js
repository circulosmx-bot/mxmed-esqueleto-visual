(() => {
  'use strict';

  const bio = document.querySelector('.mxpp-bio:not(.mxpp-bio--pending)');
  if (!bio) return;

  const minimumFontSize = 16;
  const step = 0.5;

  function fitBio() {
    // Reset first so wider layouts and newly available fonts can use normal type.
    bio.style.removeProperty('font-size');
    const normalSize = parseFloat(getComputedStyle(bio).fontSize);
    if (!Number.isFinite(normalSize) || bio.clientWidth === 0) return;

    const maximumLines = window.matchMedia('(max-width: 768px)').matches ? 3 : 2;
    let size = Math.max(minimumFontSize, normalSize);
    if (normalSize < minimumFontSize) bio.style.fontSize = `${size}px`;

    while (size > minimumFontSize) {
      const lineHeight = parseFloat(getComputedStyle(bio).lineHeight);
      // Use line-box height: scrollHeight can also include this font's glyph overhang.
      if (bio.getBoundingClientRect().height <= lineHeight * maximumLines + 1) break;
      size = Math.max(minimumFontSize, size - step);
      bio.style.fontSize = `${size}px`;
    }
    // At the floor, extra natural lines remain visible rather than being clipped.
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fitBio, {once: true});
  } else {
    fitBio();
  }
  document.fonts?.ready.then(fitBio);

  let resizeTimer;
  window.addEventListener('resize', () => {
    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(fitBio, 120);
  });
})();
