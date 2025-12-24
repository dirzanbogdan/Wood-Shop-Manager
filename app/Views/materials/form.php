<?php

declare(strict_types=1);

$isEdit = is_array($material);
$action = $isEdit ? '/?r=materials/edit&id=' . (int) $material['id'] : '/?r=materials/create';
$form = isset($form) && is_array($form) ? $form : ($isEdit ? $material : []);
$selectedTypeId = (int) ($form['material_type_id'] ?? 0);
$selectedSupplierId = (int) ($form['supplier_id'] ?? 0);
$selectedUnitId = (int) ($form['unit_id'] ?? 0);
$canArchive = $isEdit && array_key_exists('is_archived', (array) $material) && (int) ($material['is_archived'] ?? 0) === 0;
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
    <?php if (isset($product_code_conflict) && is_array($product_code_conflict)): ?>
      <div class="card" style="margin:0 0 12px 0; border: 1px solid #c53030">
        <div class="row" style="justify-content: space-between; gap: 10px">
          <div>
            <div style="font-weight: 600">Cod produs existent</div>
            <div>
              <?= htmlspecialchars((string) ($product_code_conflict['product_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              —
              <?= htmlspecialchars((string) ($product_code_conflict['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
          <div class="row" style="justify-content: flex-end; gap: 8px">
            <button class="btn danger" type="submit" name="product_code_conflict_action" value="overwrite" onclick="return confirm('Suprascrii produsul existent?');">Suprascrie</button>
            <button class="btn primary" type="button" onclick="try{var i=document.querySelector('input[name=product_code]'); if(i){i.focus(); i.select();} var c=this.closest('.card'); if(c){c.style.display='none';}}catch(e){}">Schimba codul</button>
          </div>
        </div>
      </div>
    <?php endif; ?>
    <div class="grid">
      <div class="col-6">
        <label>Denumire material</label>
        <input name="name" required value="<?= htmlspecialchars((string) ($form['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6">
        <label>Cod produs</label>
        <input name="product_code" value="<?= htmlspecialchars((string) ($form['product_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6">
        <label>Tip material</label>
        <select name="material_type_id" required>
          <?php foreach ($types as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= $selectedTypeId === (int) $t['id'] ? 'selected' : '' ?>>
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
            <option value="<?= (int) $s['id'] ?>" <?= $selectedSupplierId === (int) $s['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string) $s['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6">
        <label>Unitate masura</label>
        <select name="unit_id" required>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= $selectedUnitId === (int) $u['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string) $u['code'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!$isEdit): ?>
        <div class="col-6">
          <label>Cantitate curenta</label>
          <input name="current_qty" required value="<?= htmlspecialchars((string) ($form['current_qty'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
      <?php endif; ?>

      <div class="col-6">
        <label>Cost unitar</label>
        <?php
          $ucLei = (string) ($form['unit_cost'] ?? '0');
          $ucVal = isset($to_currency) ? number_format((float) $to_currency((float) $ucLei), 4, '.', '') : $ucLei;
          $ucCurSel = (string) ($currency ?? 'lei');
        ?>
        <div class="row" style="gap:8px">
          <input name="unit_cost" required value="<?= htmlspecialchars($ucVal, ENT_QUOTES, 'UTF-8') ?>" style="flex:1">
          <select name="unit_cost_currency" style="width:110px">
            <option value="lei" <?= $ucCurSel === 'lei' ? 'selected' : '' ?>>LEI</option>
            <option value="usd" <?= $ucCurSel === 'usd' ? 'selected' : '' ?>>USD</option>
            <option value="eur" <?= $ucCurSel === 'eur' ? 'selected' : '' ?>>EUR</option>
          </select>
        </div>
      </div>

      <div class="col-6">
        <label>Data achizitiei</label>
        <?php $pd = (string) ($form['purchase_date'] ?? ''); ?>
        <input name="purchase_date" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars(isset($date_dmy) ? $date_dmy($pd) : $pd, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-6">
        <label>URL achizitie (optional)</label>
        <input name="purchase_url" value="<?= htmlspecialchars((string) ($form['purchase_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-6">
        <label>Stoc minim</label>
        <input name="min_stock" required value="<?= htmlspecialchars((string) ($form['min_stock'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 row" style="justify-content: flex-end; margin-top: 6px">
        <button class="btn primary" type="submit">Salveaza</button>
      </div>
    </div>
  </form>

  <?php if ($canArchive): ?>
    <form method="post" action="/?r=materials/archive&id=<?= (int) $material['id'] ?>" style="margin-top:12px">
      <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
      <button class="btn danger" type="submit" onclick="return confirm('Arhivezi materialul?');">Arhiveaza</button>
    </form>
  <?php endif; ?>
</div>
