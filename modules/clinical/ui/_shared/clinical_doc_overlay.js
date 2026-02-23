(function (window, document) {
  window.MXMed = window.MXMed || {};

  function isEmbedRequest() {
    try {
      return new URLSearchParams(window.location.search || '').get('embed') === '1';
    } catch (_) {
      return false;
    }
  }

  function asString(value) {
    return String(value == null ? '' : value).trim();
  }

  function buildTitle(docType, docTitle) {
    var type = asString(docType);
    var title = asString(docTitle);
    if (type && title) return 'Documento: ' + type + ' · ' + title;
    if (type) return 'Documento: ' + type;
    if (title) return 'Documento: ' + title;
    return 'Documento';
  }

  window.MXMed.initDocOverlay = function initDocOverlay(opts) {
    opts = opts || {};
    var root = opts.root && typeof opts.root.querySelector === 'function' ? opts.root : document;
    var embedOnly = opts.embedOnly === true;

    if (embedOnly && !isEmbedRequest()) {
      return;
    }

    var overlayEl = root.querySelector('[data-role="doc-overlay"]');
    var iframeEl = root.querySelector('[data-role="doc-overlay-iframe"]');
    if (!overlayEl || !iframeEl) {
      return;
    }

    if (overlayEl.__mxmedDocOverlayInit) {
      return;
    }
    overlayEl.__mxmedDocOverlayInit = true;

    var titleEl = root.querySelector('[data-role="doc-overlay-title"]');
    var openNewEl = root.querySelector('[data-role="doc-overlay-open-new"]');
    var loaderEl = root.querySelector('[data-role="doc-overlay-loader"]');

    function showLoader() {
      if (loaderEl) loaderEl.classList.remove('d-none');
    }

    function hideLoader() {
      if (loaderEl) loaderEl.classList.add('d-none');
    }

    function closeOverlay() {
      iframeEl.src = 'about:blank';
      overlayEl.hidden = true;
      overlayEl.setAttribute('aria-hidden', 'true');
      if (titleEl) titleEl.textContent = 'Documento';
      if (openNewEl) openNewEl.setAttribute('href', '');
      hideLoader();
    }

    function openOverlayFromLink(link) {
      var href = asString(link && link.getAttribute('href'));
      if (!href) return;
      if (titleEl) {
        titleEl.textContent = buildTitle(
          link.getAttribute('data-doc-type'),
          link.getAttribute('data-doc-title')
        );
      }
      if (openNewEl) {
        openNewEl.setAttribute('href', href);
      }
      showLoader();
      iframeEl.src = href;
      overlayEl.hidden = false;
      overlayEl.setAttribute('aria-hidden', 'false');
    }

    document.addEventListener('click', function (event) {
      var target = event.target;
      var openLink = target && target.closest ? target.closest('a[data-role="open-doc-overlay"]') : null;
      if (openLink) {
        event.preventDefault();
        openOverlayFromLink(openLink);
        return;
      }

      var closeBtn = target && target.closest ? target.closest('[data-role="doc-overlay-close"]') : null;
      if (closeBtn) {
        event.preventDefault();
        closeOverlay();
        return;
      }

      var backdrop = target && target.closest ? target.closest('[data-role="doc-overlay-backdrop"]') : null;
      if (backdrop) {
        event.preventDefault();
        closeOverlay();
        return;
      }

      if (overlayEl && !overlayEl.hidden && target === overlayEl) {
        event.preventDefault();
        closeOverlay();
      }
    }, true);

    iframeEl.addEventListener('load', function () {
      hideLoader();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      if (overlayEl && !overlayEl.hidden) {
        closeOverlay();
      }
    });
  };
})(window, document);
