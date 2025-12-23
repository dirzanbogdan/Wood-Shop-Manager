<?php

declare(strict_types=1);

$esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$gitAvailable = isset($git_info) && is_array($git_info) && ($git_info['available'] ?? false) === true;
$canShell = $gitAvailable && (($git_info['can_shell'] ?? false) === true);
?>

<div class="card">
  <h2 style="margin-top:0">Update</h2>
  <div class="muted">Versiune curenta: <?= $esc((string) ($current_version ?? '')) ?><?= $gitAvailable && ($git_info['hash'] ?? '') !== '' ? ' (' . $esc((string) $git_info['hash']) . ')' : '' ?></div>
  <?php if ($gitAvailable && ($git_info['branch'] ?? '') !== ''): ?>
    <div class="muted">Branch: <?= $esc((string) $git_info['branch']) ?></div>
  <?php endif; ?>
</div>

<div class="grid" style="margin-top: 12px">
  <div class="col-6">
    <div class="card">
      <h3 style="margin-top:0">1) Backup DB</h3>
      <p class="muted" style="margin-top:0">Recomandat inainte de update.</p>

      <form method="post" style="display:flex; gap:10px; flex-wrap:wrap">
        <input type="hidden" name="<?= $esc((string) ($csrf_key ?? 'csrf_token')) ?>" value="<?= $esc((string) ($csrf ?? '')) ?>">
        <input type="hidden" name="action" value="backup_download">
        <button class="btn primary" type="submit">Descarca backup (.sql)</button>
      </form>

      <form method="post" style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap">
        <input type="hidden" name="<?= $esc((string) ($csrf_key ?? 'csrf_token')) ?>" value="<?= $esc((string) ($csrf ?? '')) ?>">
        <input type="hidden" name="action" value="backup_server">
        <button class="btn" type="submit">Salveaza backup pe server</button>
      </form>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h3 style="margin-top:0">2) Modificari incluse</h3>
      <?php if (isset($changelog) && is_array($changelog) && $changelog): ?>
        <div style="display:flex; flex-direction:column; gap:8px; margin-top:8px">
          <?php foreach ($changelog as $entry): ?>
            <?php
              $ver = isset($entry['version']) ? (string) $entry['version'] : '';
              $items = isset($entry['items']) && is_array($entry['items']) ? $entry['items'] : [];
              $open = $ver !== '' && (string) ($current_version ?? '') !== '' && str_starts_with((string) ($current_version ?? ''), $ver);
            ?>
            <details <?= $open ? 'open' : '' ?>>
              <summary style="cursor:pointer; user-select:none"><?= $esc($ver) ?></summary>
              <?php if ($items): ?>
                <ul style="margin: 8px 0 0 18px">
                  <?php foreach ($items as $c): ?>
                    <li><?= $esc((string) $c) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="muted" style="margin-top:8px">Nu exista informatii.</div>
              <?php endif; ?>
            </details>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="muted">Nu exista informatii. Lipseste fisierul <code>CHANGELOG.md</code>.</div>
      <?php endif; ?>

      <div style="margin-top: 12px">
        <?php if (!$gitAvailable): ?>
          <div class="muted">Update automat din git nu este disponibil (lipseste folderul <code>.git</code>).</div>
        <?php elseif (!$canShell): ?>
          <div class="muted">Update automat din git nu este disponibil (proc_open dezactivat).</div>
        <?php else: ?>
          <form method="post" style="display:flex; gap:10px; flex-wrap:wrap">
            <input type="hidden" name="<?= $esc((string) ($csrf_key ?? 'csrf_token')) ?>" value="<?= $esc((string) ($csrf ?? '')) ?>">
            <input type="hidden" name="action" value="pull_update">
            <button class="btn danger" type="submit">Aplica update (git pull)</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
