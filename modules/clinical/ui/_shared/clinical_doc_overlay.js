(function (window, document) {
  window.MXMed = window.MXMed || {};

  function asString(value) {
    return String(value == null ? '' : value).trim();
  }

  function parseUrlSafe(href) {
    try {
      return new URL(href, window.location.origin);
    } catch (_) {
      return null;
    }
  }

  function isEmbedRequest() {
    var url = parseUrlSafe(window.location.href);
    return !!(url && url.searchParams.get('embed') === '1');
  }

  function getDocUuidFromHash(hashValue) {
    var raw = asString(hashValue == null ? window.location.hash : hashValue);
    if (!raw) return '';
    if (raw.charAt(0) === '#') raw = raw.slice(1);
    if (!raw) return '';
    var params = new URLSearchParams(raw);
    return asString(params.get('doc'));
  }

  function buildTitle(docType, docTitle) {
    var type = asString(docType);
    var title = asString(docTitle);
    if (type && title) return 'Documento: ' + type + ' · ' + title;
    if (type) return 'Documento: ' + type;
    if (title) return 'Documento: ' + title;
    return 'Documento';
  }

  function getUuidFromHref(href) {
    var url = parseUrlSafe(href);
    if (!url) return '';
    return asString(url.searchParams.get('uuid'));
  }

  function buildCleanReturnTo(href) {
    var url = parseUrlSafe(href);
    if (!url) return window.location.href;
    url.searchParams.delete('doc_uuid');
    if (getDocUuidFromHash(url.hash)) {
      url.hash = '';
    }
    return url.toString();
  }

  function buildUrlWithoutDocUuidKeepHash(href) {
    var url = parseUrlSafe(href);
    if (!url) return window.location.href;
    url.searchParams.delete('doc_uuid');
    return url.toString();
  }

  function buildDocumentHref(uuid) {
    var cleanReturnTo = buildCleanReturnTo(window.location.href);
    return '/modules/clinical/ui/document.php?uuid='
      + encodeURIComponent(uuid)
      + '&embed=1&return_to='
      + encodeURIComponent(cleanReturnTo);
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
    var activeDocUuid = '';
    var suppressHashHandler = false;

    function showLoader() {
      if (loaderEl) loaderEl.classList.remove('d-none');
    }

    function hideLoader() {
      if (loaderEl) loaderEl.classList.add('d-none');
    }

    function setDocHash(uuid) {
      uuid = asString(uuid);
      if (!uuid) return;
      var nextHash = 'doc=' + encodeURIComponent(uuid);
      var currentHash = asString(window.location.hash).replace(/^#/, '');
      if (currentHash === nextHash) return;
      suppressHashHandler = true;
      window.location.hash = nextHash;
    }

    function clearDocHash() {
      if (!getDocUuidFromHash(window.location.hash)) return;
      suppressHashHandler = true;
      window.location.hash = '';
    }

    function openOverlayWithHref(href, docType, docTitle, docUuid, syncHash) {
      href = asString(href);
      if (!href) return;
      if (titleEl) titleEl.textContent = buildTitle(docType, docTitle);
      if (openNewEl) openNewEl.setAttribute('href', href);
      showLoader();
      iframeEl.src = href;
      overlayEl.hidden = false;
      overlayEl.setAttribute('aria-hidden', 'false');
      activeDocUuid = asString(docUuid);
      if (syncHash && activeDocUuid) {
        setDocHash(activeDocUuid);
      }
    }

    function openOverlayByUuid(uuid, syncHash) {
      uuid = asString(uuid);
      if (!uuid) return;
      openOverlayWithHref(buildDocumentHref(uuid), '', '', uuid, !!syncHash);
    }

    function closeOverlay(updateHash) {
      iframeEl.src = 'about:blank';
      overlayEl.hidden = true;
      overlayEl.setAttribute('aria-hidden', 'true');
      if (titleEl) titleEl.textContent = 'Documento';
      if (openNewEl) openNewEl.setAttribute('href', '');
      hideLoader();
      activeDocUuid = '';
      if (updateHash) {
        clearDocHash();
      }
    }

    function syncFromHash() {
      var hashUuid = getDocUuidFromHash(window.location.hash);
      if (hashUuid) {
        if (!overlayEl.hidden && hashUuid === activeDocUuid) {
          return;
        }
        openOverlayByUuid(hashUuid, false);
      } else if (!overlayEl.hidden) {
        closeOverlay(false);
      }
    }

    document.addEventListener('click', function (event) {
      var target = event.target;
      var openLink = target && target.closest ? target.closest('a[data-role="open-doc-overlay"]') : null;
      if (openLink) {
        event.preventDefault();
        var href = asString(openLink.getAttribute('href'));
        if (!href) return;
        var uuid = getUuidFromHref(href);
        openOverlayWithHref(
          href,
          openLink.getAttribute('data-doc-type'),
          openLink.getAttribute('data-doc-title'),
          uuid,
          true
        );
        return;
      }

      var closeBtn = target && target.closest ? target.closest('[data-role="doc-overlay-close"]') : null;
      if (closeBtn) {
        event.preventDefault();
        closeOverlay(true);
        return;
      }

      var backdrop = target && target.closest ? target.closest('[data-role="doc-overlay-backdrop"]') : null;
      if (backdrop) {
        event.preventDefault();
        closeOverlay(true);
        return;
      }

      if (overlayEl && !overlayEl.hidden && target === overlayEl) {
        event.preventDefault();
        closeOverlay(true);
      }
    }, true);

    iframeEl.addEventListener('load', function () {
      hideLoader();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      if (overlayEl && !overlayEl.hidden) {
        closeOverlay(true);
      }
    });

    window.addEventListener('hashchange', function () {
      if (suppressHashHandler) {
        suppressHashHandler = false;
        return;
      }
      syncFromHash();
    });

    if (embedOnly && isEmbedRequest()) {
      var hashUuid = getDocUuidFromHash(window.location.hash);
      if (hashUuid) {
        openOverlayByUuid(hashUuid, false);
        return;
      }

      var currentUrl = parseUrlSafe(window.location.href);
      var queryUuid = asString(currentUrl && currentUrl.searchParams ? currentUrl.searchParams.get('doc_uuid') : '');
      if (queryUuid) {
        openOverlayByUuid(queryUuid, true);
        try {
          if (window.history && typeof window.history.replaceState === 'function') {
            window.history.replaceState(null, '', buildUrlWithoutDocUuidKeepHash(window.location.href));
          }
        } catch (_) {}
      }
    }
  };
})(window, document);
