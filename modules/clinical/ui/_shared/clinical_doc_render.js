(function (window) {
  window.MXMed = window.MXMed || {};

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>\"]/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[m];
    });
  }

  function toTs(value) {
    var s = String(value == null ? '' : value).trim();
    if (!s || s === '-') return null;
    var t = Date.parse(s.replace(' ', 'T'));
    return Number.isFinite(t) ? t : null;
  }

  function getBadgeClass(type) {
    var t = String(type == null ? '' : type).trim().toLowerCase();
    if (t === 'prescription' || t === 'rx') return 'text-bg-primary';
    if (t === 'vitals') return 'text-bg-info';
    if (t === 'lab') return 'text-bg-warning';
    if (t === 'imaging') return 'text-bg-dark';
    if (t === 'note') return 'text-bg-secondary';
    return 'text-bg-secondary';
  }

  window.MXMed.renderClinicalDocuments = function (docs, opts) {
    opts = opts || {};
    var list = Array.isArray(docs) ? docs.slice() : [];
    list.sort(function (a, b) {
      var ta = toTs(a && a.event_datetime);
      var tb = toTs(b && b.event_datetime);
      var va = ta == null ? -Infinity : ta;
      var vb = tb == null ? -Infinity : tb;
      if (va === vb) return 0;
      return vb - va;
    });

    if (list.length === 0) {
      if (typeof opts.emptyHtml === 'string') {
        return opts.emptyHtml;
      }
      return ''
        + '<div class="border rounded p-3 bg-light">'
        + '  <div class="small text-secondary">Sin documentos</div>'
        + '</div>';
    }

    var embedLink = opts.embedLink !== false;
    var returnTo = (typeof opts.returnTo === 'string' ? opts.returnTo : '').trim();
    var openInOverlay = opts.openInOverlay === true;
    var bodyHtml = list.map(function (doc) {
      var type = String((doc && (doc.document_type || doc.type)) || '-');
      var title = String((doc && doc.title) || '');
      var summary = String((doc && doc.summary) || '-');
      var dt = String((doc && doc.event_datetime) || '-');
      var uuid = String((doc && (doc.document_uuid || doc.document_id)) || '').trim();
      var header = title ? (type + ' · ' + title) : type;
      var href = '';
      var badgeClass = getBadgeClass(type);
      if (uuid) {
        href = '/modules/clinical/ui/document.php?uuid=' + encodeURIComponent(uuid) + (embedLink ? '&embed=1' : '');
        if (returnTo) {
          href += '&return_to=' + encodeURIComponent(returnTo);
        }
      }

      return ''
        + '<div class="border rounded p-2">'
        + '  <div class="mb-1"><span class="badge ' + esc(badgeClass) + '">' + esc(type) + '</span></div>'
        + '  <div class="small"><strong>' + esc(header) + '</strong></div>'
        + '  <div class="small text-secondary">' + esc(dt) + '</div>'
        + '  <div class="small">' + esc(summary) + '</div>'
        + (href
          ? '  <div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="' + esc(href) + '"' + (openInOverlay ? ' data-role="open-doc-overlay"' : '') + ' data-doc-type="' + esc(type) + '" data-doc-title="' + esc(title) + '">Abrir documento</a></div>'
          : '')
        + '</div>';
    }).join('');

    return ''
      + '<div class="d-flex justify-content-between align-items-center mb-2">'
      + '  <div class="small text-secondary">Documentos (' + String(list.length) + ')</div>'
      + '</div>'
      + bodyHtml;
  };
})(window);
