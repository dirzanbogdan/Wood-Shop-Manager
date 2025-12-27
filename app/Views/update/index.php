<?php

declare(strict_types=1);

$esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$gitAvailable = isset($git_info) && is_array($git_info) && ($git_info['available'] ?? false) === true;
$canShell = $gitAvailable && (($git_info['can_shell'] ?? false) === true);
$fmtSize = static function (?int $bytes): string {
  if ($bytes === null || $bytes < 0) {
    return '-';
  }
  $units = ['B', 'KB', 'MB', 'GB'];
  $v = (float) $bytes;
  $i = 0;
  while ($v >= 1024 && $i < count($units) - 1) {
    $v /= 1024;
    $i++;
  }
  $dec = $i === 0 ? 0 : 1;
  return number_format($v, $dec, '.', '') . ' ' . $units[$i];
};
?>

<div class="card">
  <h2 style="margin-top:0">Update</h2>
  <div class="muted">Versiune curenta: <?= $esc((string) ($current_version ?? '')) ?><?= $gitAvailable && ($git_info['hash'] ?? '') !== '' ? ' (' . $esc((string) $git_info['hash']) . ')' : '' ?></div>
  <?php if ($gitAvailable && ($git_info['branch'] ?? '') !== ''): ?>
    <div class="muted">Branch: <?= $esc((string) $git_info['branch']) ?></div>
  <?php endif; ?>
  <?php if (isset($update_git_branch) && is_string($update_git_branch) && trim($update_git_branch) !== ''): ?>
    <div class="muted">Update branch (UI): <?= $esc((string) $update_git_branch) ?></div>
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
            ?>
            <details>
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

      <div style="margin-top: 10px">
        <form method="post" style="display:flex; gap:10px; flex-wrap:wrap">
          <input type="hidden" name="<?= $esc((string) ($csrf_key ?? 'csrf_token')) ?>" value="<?= $esc((string) ($csrf ?? '')) ?>">
          <input type="hidden" name="action" value="apply_update">
          <button class="btn danger" type="submit" onclick="return confirm('Aplici update din arhiva GitHub peste fisierele curente?');">Aplica update (arhiva GitHub)</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="grid" style="margin-top: 12px">
  <div class="col-12">
    <div class="card">
      <h3 style="margin-top:0">Config (config/local.php)</h3>
      <p class="muted" style="margin-top:0">Actualizeaza setari locale direct din UI (base URL, branch update, CORS, token API).</p>

      <form method="post" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end">
        <input type="hidden" name="<?= $esc((string) ($csrf_key ?? 'csrf_token')) ?>" value="<?= $esc((string) ($csrf ?? '')) ?>">
        <input type="hidden" name="action" value="update_local_config">

        <div style="display:flex; flex-direction:column; gap:6px; min-width: 280px; flex: 1">
          <label for="base_url">Base URL</label>
          <input id="base_url" name="base_url" type="url" required value="<?= $esc((string) ($cfg_base_url ?? '')) ?>" placeholder="https://exemplu.ro">
        </div>

        <div style="display:flex; flex-direction:column; gap:6px; min-width: 200px">
          <label for="git_branch">Git branch (update)</label>
          <input id="git_branch" name="git_branch" type="text" value="<?= $esc((string) ($update_git_branch ?? 'main')) ?>" placeholder="main">
        </div>

        <div style="display:flex; flex-direction:column; gap:6px; min-width: 280px; flex: 1">
          <label for="cors_origin">CORS origin</label>
          <input id="cors_origin" name="cors_origin" type="url" value="<?= $esc((string) (($cfg_cors_origin ?? '') !== '' ? $cfg_cors_origin : ($cfg_base_url ?? ''))) ?>" placeholder="https://exemplu.ro">
        </div>

        <div style="display:flex; flex-direction:column; gap:6px; min-width: 180px">
          <label for="token_ttl_days">Token TTL (zile)</label>
          <input id="token_ttl_days" name="token_ttl_days" type="number" min="1" max="365" value="<?= $esc((string) (isset($cfg_token_ttl_days) ? (int) $cfg_token_ttl_days : 30)) ?>">
        </div>

        <div style="display:flex; flex-direction:column; gap:6px; min-width: 240px; flex: 1">
          <label for="token_secret">Token secret (64 hex, optional)</label>
          <input id="token_secret" name="token_secret" type="text" value="" placeholder="Lasa gol pentru a pastra/genera">
        </div>

        <label style="display:flex; gap:8px; align-items:center; margin: 0 6px 6px 0">
          <input type="checkbox" name="cors_allow_credentials" value="1" <?= (($cfg_cors_allow_credentials ?? false) === true) ? 'checked' : '' ?>>
          <span>CORS allow credentials</span>
        </label>

        <button class="btn primary" type="submit" onclick="return confirm('Actualizezi config/local.php?');">Salveaza config</button>
      </form>
    </div>
  </div>
</div>

<div class="grid" style="margin-top: 12px">
  <div class="col-12">
    <div class="card">
      <h3 style="margin-top:0">APK / downloads</h3>
      <div class="muted">Ultima versiune (link fix): <a href="<?= $esc((string) ($apk_url ?? '')) ?>"><?= $esc((string) ($apk_rel ?? '')) ?></a></div>
      <div class="muted">
        Status: <?= (($apk_ok ?? null) === true) ? 'OK' : 'Lipseste / invalid' ?>
        · Marime: <?= $esc($fmtSize(isset($apk_size) ? (is_int($apk_size) ? $apk_size : null) : null)) ?>
        <?php if (isset($apk_mtime) && is_int($apk_mtime) && $apk_mtime > 0): ?>
          · Actualizat: <?= $esc(date('Y-m-d H:i:s', $apk_mtime)) ?>
        <?php endif; ?>
      </div>

      <div style="margin-top: 10px">
        <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center">
          <input type="hidden" name="<?= $esc((string) ($csrf_key ?? 'csrf_token')) ?>" value="<?= $esc((string) ($csrf ?? '')) ?>">
          <input type="hidden" name="action" value="upload_download">
          <input type="file" name="download_file" required>
          <button class="btn primary" type="submit">Upload build nou</button>
        </form>
        <div class="muted" style="margin-top:8px">La upload, fisierul existent <code>wsm.&lt;ext&gt;</code> este redenumit automat in <code>wsm_&lt;versiune_curenta&gt;.&lt;ext&gt;</code>.</div>
      </div>

      <?php if (isset($downloads) && is_array($downloads) && $downloads): ?>
        <div style="margin-top:12px">
          <div class="muted">Build-uri in <code>public/downloads</code>:</div>
          <ul style="margin: 8px 0 0 18px">
            <?php foreach ($downloads as $d): ?>
              <?php
                $name = isset($d['name']) ? (string) $d['name'] : '';
                $url = isset($d['url']) ? (string) $d['url'] : '';
                $size = isset($d['size']) ? (is_int($d['size']) ? $d['size'] : null) : null;
                $mtime = isset($d['mtime']) ? (is_int($d['mtime']) ? $d['mtime'] : null) : null;
              ?>
              <li>
                <a href="<?= $esc($url) ?>"><?= $esc($name) ?></a>
                <span class="muted">(<?= $esc($fmtSize($size)) ?><?= $mtime ? ', ' . $esc(date('Y-m-d H:i:s', $mtime)) : '' ?>)</span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php else: ?>
        <div class="muted" style="margin-top:12px">Nu exista fisiere in <code>public/downloads</code>.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
