<?php

declare(strict_types=1);

$isEdit = is_array($product);
$action = $isEdit ? '/?r=products/edit&id=' . (int) $product['id'] : '/?r=products/create';
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0"><?= htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="row">
      <a class="btn small" href="/?r=products/index">Inapoi</a>
      <?php if ($isEdit): ?>
        <a class="btn small" href="/?r=bom/edit&id=<?= (int) $product['id'] ?>">Reteta/BOM</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="grid">
      <div class="col-6">
        <label>Nume produs</label>
        <input name="name" required value="<?= htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6">
        <label>Cod intern (SKU)</label>
        <input name="sku" required value="<?= htmlspecialchars((string) ($product['sku'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6">
        <label>Categoria</label>
        <select name="category_id" required>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $isEdit && (int) ($product['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6">
        <label>Pret de vanzare</label>
        <?php
          $spLei = (string) ($product['sale_price'] ?? '0');
          $spVal = isset($to_currency) ? number_format((float) $to_currency((float) $spLei), 4, '.', '') : $spLei;
          $spCurSel = (string) ($currency ?? 'lei');
        ?>
        <div class="row" style="gap:8px">
          <input name="sale_price" required value="<?= htmlspecialchars($spVal, ENT_QUOTES, 'UTF-8') ?>" style="flex:1">
          <select name="sale_price_currency" style="width:110px">
            <option value="lei" <?= $spCurSel === 'lei' ? 'selected' : '' ?>>LEI</option>
            <option value="usd" <?= $spCurSel === 'usd' ? 'selected' : '' ?>>USD</option>
            <option value="eur" <?= $spCurSel === 'eur' ? 'selected' : '' ?>>EUR</option>
          </select>
        </div>
      </div>
      <div class="col-6">
        <label>Timp estimat productie (ore)</label>
        <input name="estimated_hours" required value="<?= htmlspecialchars((string) ($product['estimated_hours'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6">
        <label>Manpower (ore/unitate)</label>
        <input name="manpower_hours" required value="<?= htmlspecialchars((string) ($product['manpower_hours'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <?php if ($isEdit): ?>
        <div class="col-6">
          <label>Status</label>
          <select name="status" required>
            <?php foreach (['In productie', 'Finalizat', 'Vandut'] as $st): ?>
              <option value="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($product['status'] ?? '') === $st ? 'selected' : '' ?>>
                <?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label>Stoc</label>
          <input value="<?= (int) ($product['stock_qty'] ?? 0) ?>" disabled>
        </div>
      <?php endif; ?>
      <div class="col-12 row" style="justify-content:flex-end">
        <button class="btn primary" type="submit">Salveaza</button>
      </div>
    </div>
  </form>
</div>
