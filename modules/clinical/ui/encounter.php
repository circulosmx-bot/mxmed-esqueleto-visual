<?php
declare(strict_types=1);

function get_api_base(): string
{
    $env = trim((string)getenv('MXMED_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $env = trim((string)getenv('MXMED_TIMELINE_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
    $proto = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $proto = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = (string)$_SERVER['HTTP_X_FORWARDED_PROTO'];
    }

    return $proto . '://' . $host;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function render_embed_css(bool $embed): void
{
    if (!$embed) {
        return;
    }

    echo '<link rel="stylesheet" href="/assets/css/style.css">' . "\n";
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">' . "\n";
}

$encounterKey = trim((string)($_GET['encounter_key'] ?? ''));
$errorMessage = '';
$encounter = null;

if ($encounterKey !== '') {
    // ✅ CORRECTO: encounters/{encounter_key}
    $url = get_api_base() . '/api/clinical/index.php/encounters/' . rawurlencode($encounterKey);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        $errorMessage = 'No se pudo consultar la atención.';
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $errorMessage = 'Respuesta inválida del endpoint de encounters.';
        } elseif (($decoded['ok'] ?? false) !== true) {
            $errorMessage = (string)($decoded['message'] ?? 'Error consultando atención.');
        } else {
            $data = $decoded['data'] ?? null;
            $encounter = is_array($data) ? $data : null;
            if ($encounter === null) {
                $errorMessage = 'Atención no disponible.';
            }
        }
    }
}

$documents = is_array($encounter['documents'] ?? null) ? $encounter['documents'] : [];
$prescriptions = is_array($encounter['prescriptions'] ?? null) ? $encounter['prescriptions'] : [];
$orders = is_array($encounter['orders'] ?? null) ? $encounter['orders'] : [];
$results = is_array($encounter['results'] ?? null) ? $encounter['results'] : [];
$documentsJson = json_encode($documents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($documentsJson)) {
    $documentsJson = '[]';
}
require_once __DIR__ . '/../../_partials/clinical_embed.php';
$embed = is_embed_request();

// Shell MXMed
if (!$embed) {
    $pageTitle = 'Atención clínica';
    require_once __DIR__ . '/../../_partials/mm_shell_top.php';
} else {
    render_embed_css($embed);
    clinical_embed_start();
}
?>
<style>
  [data-role="doc-overlay"]{
    position: fixed;
    inset: 0;
    z-index: 1060;
  }
  [data-role="doc-overlay"][hidden]{
    display: none !important;
  }
  [data-role="doc-overlay-backdrop"]{
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.55);
  }
  [data-role="doc-overlay-panel"]{
    position: relative;
    width: min(1200px, calc(100vw - 2rem));
    height: 90vh;
    margin: 5vh auto;
    background: #fff;
    border-radius: .75rem;
    border: 1px solid rgba(0,0,0,.08);
    box-shadow: 0 20px 40px rgba(0,0,0,.25);
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
  [data-role="doc-overlay-head"]{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .65rem .85rem;
    border-bottom: 1px solid rgba(0,0,0,.1);
  }
  [data-role="doc-overlay-iframe"]{
    width: 100%;
    height: 100%;
    border: 0;
    flex: 1 1 auto;
    background: #fff;
  }
</style>
<div class="<?php echo $embed ? 'py-1' : 'container py-4'; ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Atención clínica</h1>
    <div class="d-flex gap-2">
      <?php if ($encounterKey !== ''): ?>
        <a class="btn btn-outline-primary btn-sm" href="/modules/clinical/ui/historial.php?<?php echo h(carry_embed_params(['encounter_key' => $encounterKey, 'include' => 'agenda,clinical', 'limit' => 20])); ?>">Ver historial del paciente</a>
      <?php endif; ?>
      <a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">Volver</a>
    </div>
  </div>

  <p class="text-secondary">encounter_key: <code><?php echo h($encounterKey !== '' ? $encounterKey : '-'); ?></code></p>

  <?php if ($encounterKey === ''): ?>
    <div class="alert alert-warning">encounter_key requerido.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php else: ?>
    <div class="mm-card mb-3">
      <div class="head"><h5>Resumen</h5></div>
      <div class="body small">
        <div><strong>Fecha:</strong> <?php echo h((string)($encounter['event_datetime'] ?? '-')); ?></div>
        <div><strong>patient_id:</strong> <?php echo h((string)($encounter['patient_id'] ?? '-')); ?></div>
        <div><strong>appointment_id:</strong> <?php echo h((string)($encounter['appointment_id'] ?? '-')); ?></div>
      </div>
    </div>

    <div class="mm-card mb-3">
      <div class="head"><h5>Documentos</h5></div>
      <div class="body">
        <div id="encounterDocumentsList" class="vstack gap-2"></div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="mm-card h-100">
          <div class="head"><h5>Recetas</h5></div>
          <div class="body small text-secondary"><?php echo $prescriptions === [] ? 'Sin recetas' : h((string)count($prescriptions)); ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="mm-card h-100">
          <div class="head"><h5>Órdenes</h5></div>
          <div class="body small text-secondary"><?php echo $orders === [] ? 'Sin órdenes' : h((string)count($orders)); ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="mm-card h-100">
          <div class="head"><h5>Resultados</h5></div>
          <div class="body small text-secondary"><?php echo $results === [] ? 'Sin resultados' : h((string)count($results)); ?></div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<div data-role="doc-overlay" hidden aria-hidden="true">
  <div data-role="doc-overlay-backdrop"></div>
  <div data-role="doc-overlay-panel" role="dialog" aria-modal="true" aria-label="Documento">
    <div data-role="doc-overlay-head">
      <strong>Documento</strong>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-role="doc-overlay-close">Cerrar</button>
    </div>
    <iframe data-role="doc-overlay-iframe" src="about:blank" loading="lazy"></iframe>
  </div>
</div>
<script src="/modules/clinical/ui/_shared/clinical_doc_render.js"></script>
<script>
  (function () {
    var isEmbed = <?php echo $embed ? 'true' : 'false'; ?>;
    var el = document.getElementById('encounterDocumentsList');
    if (!el) return;
    var docOverlayEl = document.querySelector('[data-role="doc-overlay"]');
    var docOverlayIframe = document.querySelector('[data-role="doc-overlay-iframe"]');

    function openDocOverlay(url) {
      if (!docOverlayEl || !docOverlayIframe || !url) return;
      docOverlayIframe.src = url;
      docOverlayEl.hidden = false;
      docOverlayEl.setAttribute('aria-hidden', 'false');
    }

    function closeDocOverlay() {
      if (!docOverlayEl || !docOverlayIframe) return;
      docOverlayIframe.src = 'about:blank';
      docOverlayEl.hidden = true;
      docOverlayEl.setAttribute('aria-hidden', 'true');
    }

    var docs = <?php echo $documentsJson; ?>;
    var renderer = window.MXMed && typeof window.MXMed.renderClinicalDocuments === 'function'
      ? window.MXMed.renderClinicalDocuments
      : null;
    if (!renderer) {
      el.innerHTML = '<div class="small text-secondary">Sin documentos</div>';
      return;
    }
    el.innerHTML = renderer(docs, {
      embedLink: <?php echo $embed ? 'true' : 'false'; ?>,
      returnTo: window.location.href,
      openInOverlay: isEmbed,
      emptyHtml: '<div class="small text-secondary">Sin documentos</div>'
    });

    document.addEventListener('click', function (event) {
      var openLink = event.target && event.target.closest ? event.target.closest('a[data-role="open-doc-overlay"]') : null;
      if (openLink && isEmbed) {
        var href = String(openLink.getAttribute('href') || '').trim();
        if (!href) return;
        event.preventDefault();
        openDocOverlay(href);
        return;
      }

      var closeBtn = event.target && event.target.closest ? event.target.closest('[data-role="doc-overlay-close"], [data-role="doc-overlay-backdrop"]') : null;
      if (closeBtn) {
        event.preventDefault();
        closeDocOverlay();
      }
    }, true);

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      if (docOverlayEl && !docOverlayEl.hidden) {
        closeDocOverlay();
      }
    });
  })();
</script>
<?php if ($embed): ?>
<?php clinical_embed_end(); ?>
<?php else: ?>
<?php require_once __DIR__ . '/../../_partials/mm_shell_bottom.php'; ?>
<?php endif; ?>
