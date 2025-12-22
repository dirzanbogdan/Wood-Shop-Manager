<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Pornire comanda de productie</h2>
    <div class="row">
      <a class="btn small" href="/?r=production/index">Inapoi</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <form method="post" action="/?r=production/start">
    <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="grid">
      <div class="col-12">
        <label>Produs</label>
        <select name="product_id" required>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int) $p['id'] ?>">
              <?= htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $p['sku'], ENT_QUOTES, 'UTF-8') ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6">
        <label>Cantitate</label>
        <input name="qty" required value="1">
      </div>
      <div class="col-12">
        <label>Note (optional)</label>
        <textarea name="notes"></textarea>
      </div>
      <div class="col-12 row" style="justify-content:flex-end">
        <button class="btn primary" type="submit">Porneste</button>
      </div>
    </div>
  </form>
  <p class="muted" style="margin-bottom:0">
    Validarea stocurilor si a utilajelor se face la pornire si la finalizare.
  </p>
</div>

