<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_lib/db.php';
require_once __DIR__ . '/../modules/profiles/repositories/PublicDiscoveryRepository.php';
require_once __DIR__ . '/../modules/profiles/controllers/PublicDiscoveryController.php';

use Profiles\Controllers\PublicDiscoveryController;
use Profiles\Repositories\PublicDiscoveryRepository;

function pdb02h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pdb02Query(array $filters, int $page, int $pageSize): string
{
    $query = array_filter([
        'state' => $filters['state'] ?? '',
        'city' => $filters['city'] ?? '',
        'specialty' => $filters['specialty'] ?? '',
        'page' => $page,
        'page_size' => $pageSize,
    ], static fn($value): bool => $value !== '');
    return http_build_query($query);
}

if (!isset($publicDiscoveryResponse) || !is_array($publicDiscoveryResponse)) {
    try {
        $publicDiscoveryResponse = (new PublicDiscoveryController(new PublicDiscoveryRepository(mxmed_pdo())))->index($_GET);
    } catch (Throwable) {
        $publicDiscoveryResponse = [
            'ok' => false,
            'error' => 'profile_public_unavailable',
            'message' => 'No fue posible cargar especialistas en este momento.',
            'data' => null,
            'meta' => [],
        ];
    }
}

$ok = ($publicDiscoveryResponse['ok'] ?? false) === true;
$data = is_array($publicDiscoveryResponse['data'] ?? null) ? $publicDiscoveryResponse['data'] : [];
$meta = is_array($publicDiscoveryResponse['meta'] ?? null) ? $publicDiscoveryResponse['meta'] : [];
$filters = is_array($meta['filters'] ?? null) ? $meta['filters'] : [
    'state' => trim((string)($_GET['state'] ?? '')),
    'city' => trim((string)($_GET['city'] ?? '')),
    'specialty' => trim((string)($_GET['specialty'] ?? '')),
];
$pagination = is_array($meta['pagination'] ?? null) ? $meta['pagination'] : [];
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$page = max(1, (int)($pagination['page'] ?? 1));
$pageSize = max(1, (int)($pagination['page_size'] ?? PublicDiscoveryController::DEFAULT_PAGE_SIZE));
$total = max(0, (int)($pagination['total_count'] ?? 0));
$totalPages = max(0, (int)($pagination['total_pages'] ?? 0));
?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Especialistas médicos | México Médico</title>
  <meta name="description" content="Consulta perfiles públicos de especialistas médicos por estado, ciudad y especialidad." />
  <meta name="robots" content="noindex,follow" />
  <style>
    :root{color-scheme:light;--ink:#14213d;--muted:#64748b;--line:#dbe4ee;--brand:#0d6efd;--bg:#f4f7fb}
    *{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,sans-serif;color:var(--ink);background:var(--bg)}
    .shell{width:min(1120px,calc(100% - 32px));margin:0 auto;padding:40px 0 64px}.eyebrow{color:var(--brand);font-weight:800;letter-spacing:.08em;text-transform:uppercase;font-size:.75rem}
    h1{font-size:clamp(2rem,5vw,3.5rem);margin:.35rem 0}.lead{color:var(--muted);max-width:720px}.filters{display:grid;grid-template-columns:repeat(3,1fr) auto;gap:12px;padding:20px;background:#fff;border:1px solid var(--line);border-radius:18px;margin:28px 0}
    label{font-size:.8rem;font-weight:750}input{width:100%;margin-top:6px;padding:12px;border:1px solid #bdc9d8;border-radius:10px;font:inherit}.submit{align-self:end;padding:12px 20px;border:0;border-radius:10px;background:var(--brand);color:#fff;font-weight:800;cursor:pointer}
    .summary{color:var(--muted);margin:18px 0}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.card{display:grid;grid-template-columns:92px 1fr;gap:18px;background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
    .portrait{width:92px;height:92px;border-radius:16px;object-fit:cover;background:#e8eef6}.initial{display:grid;place-items:center;font-size:2rem;font-weight:800;color:#4b6483}.card h2{margin:0 0 5px;font-size:1.25rem}.specialty{font-weight:750;color:#31557f}.place,.address{color:var(--muted);font-size:.92rem}.badges{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}.badge{background:#e7f1ff;color:#0755b5;padding:5px 9px;border-radius:999px;font-size:.75rem;font-weight:800}.cta{display:inline-block;margin-top:8px;color:#fff;background:var(--brand);padding:10px 14px;border-radius:9px;text-decoration:none;font-weight:800}.empty,.error{background:#fff;border:1px solid var(--line);border-radius:16px;padding:28px}.error{border-color:#fecaca;color:#991b1b}.pager{display:flex;justify-content:center;gap:10px;margin-top:28px}.pager a{padding:10px 14px;background:#fff;border:1px solid var(--line);border-radius:9px;color:var(--ink);text-decoration:none;font-weight:700}
    @media(max-width:800px){.filters{grid-template-columns:1fr}.grid{grid-template-columns:1fr}}@media(max-width:480px){.card{grid-template-columns:1fr}.portrait{width:76px;height:76px}}
  </style>
</head>
<body>
<main class="shell">
  <header>
    <div class="eyebrow">México Médico</div>
    <h1>Encuentra un especialista</h1>
    <p class="lead">Explora perfiles médicos públicos usando la ubicación y especialidad registradas por cada profesional.</p>
  </header>

  <form class="filters" method="get" action="/profiles/listing.php">
    <label>Estado<input name="state" maxlength="120" value="<?= pdb02h($filters['state'] ?? '') ?>" placeholder="Ej. Jalisco" /></label>
    <label>Ciudad o municipio<input name="city" maxlength="120" value="<?= pdb02h($filters['city'] ?? '') ?>" placeholder="Ej. Guadalajara" /></label>
    <label>Especialidad principal<input name="specialty" maxlength="190" value="<?= pdb02h($filters['specialty'] ?? '') ?>" placeholder="Ej. Cardiología" /></label>
    <input type="hidden" name="page_size" value="<?= $pageSize ?>" />
    <button class="submit" type="submit">Buscar</button>
  </form>

  <?php if (!$ok): ?>
    <div class="error" role="alert"><?= pdb02h($publicDiscoveryResponse['message'] ?? 'No fue posible cargar los resultados.') ?></div>
  <?php else: ?>
    <p class="summary"><?= $total === 1 ? '1 especialista encontrado' : pdb02h($total) . ' especialistas encontrados' ?></p>
    <?php if ($items === []): ?>
      <section class="empty"><h2>Sin resultados</h2><p>Prueba con otra combinación de estado, ciudad o especialidad.</p></section>
    <?php else: ?>
      <section class="grid" aria-label="Especialistas médicos">
        <?php foreach ($items as $item): $location = is_array($item['location'] ?? null) ? $item['location'] : []; ?>
          <article class="card" data-doctor-card>
            <?php if (!empty($item['photo_url'])): ?>
              <img class="portrait" src="<?= pdb02h($item['photo_url']) ?>" alt="Fotografía de <?= pdb02h($item['display_name'] ?? 'especialista') ?>" loading="lazy" />
            <?php else: ?>
              <div class="portrait initial" aria-hidden="true"><?= pdb02h(mb_substr((string)($item['display_name'] ?? 'M'), 0, 1)) ?></div>
            <?php endif; ?>
            <div>
              <h2><?= pdb02h(trim((string)($item['prefix'] ?? '') . ' ' . (string)($item['display_name'] ?? ''))) ?></h2>
              <div class="specialty"><?= pdb02h($item['primary_specialty'] ?? 'Especialidad por confirmar') ?></div>
              <p class="place"><?= pdb02h(implode(', ', array_filter([$location['city'] ?? null, $location['state'] ?? null]))) ?></p>
              <?php if (!empty($location['address_summary'])): ?><p class="address"><?= pdb02h($location['address_summary']) ?></p><?php endif; ?>
              <?php if (($item['has_public_agenda'] ?? false) === true): ?><div class="badges"><span class="badge">Agenda disponible</span></div><?php endif; ?>
              <a class="cta" href="<?= pdb02h($item['profile_url'] ?? '#') ?>">Ver perfil</a>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
      <nav class="pager" aria-label="Paginación">
        <?php if ($page > 1): ?><a rel="prev" href="?<?= pdb02h(pdb02Query($filters, $page - 1, $pageSize)) ?>">Anterior</a><?php endif; ?>
        <span>Página <?= $page ?> de <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a rel="next" href="?<?= pdb02h(pdb02Query($filters, $page + 1, $pageSize)) ?>">Siguiente</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>
