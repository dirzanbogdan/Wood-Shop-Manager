<?php

declare(strict_types=1);
?>
<div class="grid">
  <div class="col-12">
    <div class="card" style="max-width: 520px; margin: 30px auto">
      <h2 style="margin-top:0">Autentificare</h2>
      <form method="post" action="/?r=auth/login">
        <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div style="margin-bottom:12px">
          <label>Username</label>
          <input name="username" autocomplete="username" required>
        </div>
        <div style="margin-bottom:16px">
          <label>Parola (minim <?= (int) ($password_min_length ?? 12) ?> caractere)</label>
          <input name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn primary" type="submit">Login</button>
      </form>
      <p class="muted" style="margin-bottom:0;margin-top:14px">
        Daca nu este instalat, deschide <code>/install.php</code>.
      </p>
    </div>
  </div>
</div>
