<?php

declare(strict_types=1);

$isEdit = is_array($material);
$action = $isEdit ? '/?r=materials/edit&id=' . (int) $material['id'] : '/?r=materials/create';
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0"><?= htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="row">
      <a class="btn small" href="/?r=materials/index">Inapoi</a>
      <?php if ($isEdit): ?>
        <a class="btn small" href="/?r=materials/movements&id=<?= (int) $material['id'] ?>">Istoric</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="grid">
      <div class="col-6">
        <label>Denumire material</label>
        <input name="name" required value="<?= htmlspecialchars((string) ($material['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6">
        <label>Tip material</label>
        <select name="material_type_id" required>
          <?php foreach ($types as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= $isEdit && (int) $material['material_type_id'] === (int) $t['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string) $t['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6">
        <label>Furnizor</label>
        <select name="supplier_id">
          <option value="">-</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= $isEdit && (int) ($material['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string) $s['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6">
        <label>Unitate masura</label>
        <select name="unit_id" required>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= $isEdit && (int) $material['unit_id'] === (int) $u['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string) $u['code'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!$isEdit): ?>
        <div class="col-6">
          <label>Cantitate curenta</label>
          <input name="current_qty" required value="0">
        </div>
      <?php endif; ?>

      <div class="col-6">
        <label>Cost unitar (lei)</label>
        <input name="unit_cost" required value="<?= htmlspecialchars((string) ($material['unit_cost'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-6">
        <label>Data achizitiei</label>
        <input name="purchase_date" type="date" value="<?= htmlspecialchars((string) ($material['purchase_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-6">
        <label>Stoc minim</label>
        <input name="min_stock" required value="<?= htmlspecialchars((string) ($material['min_stock'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 row" style="justify-content: flex-end; margin-top: 6px">
        <button class="btn primary" type="submit">Salveaza</button>
      </div>
    </div>
  </form>

  <?php if ($isEdit && (int) ($material['is_archived'] ?? 0) === 0): ?>
    <form method="post" action="/?r=materials/archive&id=<?= (int) $material['id'] ?>" style="margin-top:12px">
      <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
      <button class="btn danger" type="submit" onclick="return confirm('Arhivezi materialul?');">Arhiveaza</button>
    </form>
  <?php endif; ?>
</div>

