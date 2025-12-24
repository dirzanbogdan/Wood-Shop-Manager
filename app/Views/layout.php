<?php

declare(strict_types=1);

use App\Core\Auth;

$user = Auth::user();
$route = isset($_GET['r']) && is_string($_GET['r']) ? (string) $_GET['r'] : '';
$section = explode('/', $route, 2)[0] ?? '';
?>
<!doctype html>
<html lang="<?= htmlspecialchars((string) ($lang ?? 'ro'), ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'GreenSh3ll Wood Shop Manager', ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <div class="topbar">
    <div class="container topbar-inner">
      <div class="row">
        <strong>GreenSh3ll WSM</strong>
        <?php if ($user): ?>
          <span class="muted"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>)</span>
        <?php endif; ?>
      </div>
      <div class="row">
        <button class="btn small" type="button" data-theme-toggle>Tema</button>
        <?php if ($user): ?>
          <a class="btn small" href="/?r=auth/logout">Logout</a>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($user): ?>
      <div class="container">
        <nav class="nav">
          <a class="<?= $section === 'dashboard' ? 'active' : '' ?>" href="/?r=dashboard/index">Dashboard</a>
          <a class="<?= $section === 'materials' ? 'active' : '' ?>" href="/?r=materials/index">Materie prima</a>
          <a class="<?= $section === 'machines' ? 'active' : '' ?>" href="/?r=machines/index">Utilaje</a>
          <a class="<?= $section === 'products' ? 'active' : '' ?>" href="/?r=products/index">Produse</a>
          <a class="<?= $section === 'bom' ? 'active' : '' ?>" href="/?r=bom/index">Retete/BOM</a>
          <a class="<?= $section === 'production' ? 'active' : '' ?>" href="/?r=production/index">Productie</a>
          <a class="<?= $section === 'reports' ? 'active' : '' ?>" href="/?r=reports/index">Rapoarte</a>
          <a class="<?= $section === 'settings' ? 'active' : '' ?>" href="/?r=settings/index">Setari</a>
          <?php if ($user['role'] === 'SuperAdmin'): ?>
            <a class="<?= $section === 'update' ? 'active' : '' ?>" href="/?r=update/index">Update</a>
          <?php endif; ?>
          <?php if ($user['role'] === 'SuperAdmin' || $user['role'] === 'Admin'): ?>
            <a class="<?= $section === 'users' ? 'active' : '' ?>" href="/?r=users/index">Utilizatori</a>
          <?php endif; ?>
        </nav>
      </div>
      <?php if ($section === 'reports' && $route !== 'reports/index'): ?>
        <div class="container">
          <nav class="subnav">
            <a class="<?= $route === 'reports/stockMaterials' ? 'active' : '' ?>" href="/?r=reports/stockMaterials">Stoc materie prima</a>
            <a class="<?= $route === 'reports/stockProducts' ? 'active' : '' ?>" href="/?r=reports/stockProducts">Produse finite disponibile</a>
            <a class="<?= $route === 'reports/materialsConsumption' ? 'active' : '' ?>" href="/?r=reports/materialsConsumption">Consum materie prima</a>
            <a class="<?= $route === 'reports/energyConsumption' ? 'active' : '' ?>" href="/?r=reports/energyConsumption">Consum energie electrica</a>
            <a class="<?= $route === 'reports/hours' ? 'active' : '' ?>" href="/?r=reports/hours">Ore lucrate</a>
            <a class="<?= $route === 'reports/monthlyCost' ? 'active' : '' ?>" href="/?r=reports/monthlyCost">Cost productie lunar</a>
            <a class="<?= $route === 'reports/profit' ? 'active' : '' ?>" href="/?r=reports/profit">Profit estimat</a>
          </nav>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="container">
    <?php $sectionSafe = preg_replace('/[^a-z0-9_-]/i', '', (string) $section); ?>
    <div class="page page-<?= htmlspecialchars($sectionSafe, ENT_QUOTES, 'UTF-8') ?>">
    <?php if (isset($flash) && is_array($flash)): ?>
      <?php if (isset($flash['error']) && $flash['error'] !== ''): ?>
        <div class="card" style="border-color: rgba(220,38,38,.4)">
          <pre class="error" style="margin:0; white-space: pre-wrap"><?= htmlspecialchars((string) $flash['error'], ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
      <?php endif; ?>
      <?php if (isset($flash['success']) && $flash['success'] !== ''): ?>
        <div class="card" style="border-color: rgba(22,163,74,.4)">
          <pre style="margin:0; white-space: pre-wrap; color: var(--ok)"><?= htmlspecialchars((string) $flash['success'], ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?= $content ?? '' ?>
    </div>
  </div>

  <div class="container" style="margin-top: 18px; padding-bottom: 18px">
    <div class="row" style="justify-content: space-between; gap: 12px">
      <div class="muted">© 2025 Green Sh3ll ® – All rights reserved.</div>
      <div class="muted">
        <?= htmlspecialchars((string) ($app_version ?? ''), ENT_QUOTES, 'UTF-8') ?>
        <?php if (isset($git_hash) && is_string($git_hash) && $git_hash !== ''): ?>
          (<?= htmlspecialchars($git_hash, ENT_QUOTES, 'UTF-8') ?>)
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="/assets/app.js"></script>
</body>
</html>
