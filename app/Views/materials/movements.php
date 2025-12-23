<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Istoric miscari: <?= htmlspecialchars((string) $material['name'], ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="row">
      <a class="btn small" href="/?r=materials/edit&id=<?= (int) $material['id'] ?>">Editeaza</a>
      <a class="btn small" href="/?r=materials/index">Inapoi</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <h3 style="margin-top:0">Adauga miscare</h3>
  <form method="post" action="/?r=materials/adjust&id=<?= (int) $material['id'] ?>">
    <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="grid">
      <div class="col-6">
        <label>Tip</label>
        <select name="movement_type" required>
          <option value="in">Intrare</option>
          <option value="out">Iesire</option>
          <option value="adjust">Ajustare (seteaza stoc)</option>
        </select>
      </div>
      <div class="col-6">
        <label>Cantitate</label>
        <input name="qty" required>
      </div>
      <div class="col-6">
        <label>Cost unitar (optional)</label>
        <?php $ucCurSel = (string) ($currency ?? 'lei'); ?>
        <div class="row" style="gap:8px">
          <input name="unit_cost" style="flex:1">
          <select name="unit_cost_currency" style="width:110px">
            <option value="lei" <?= $ucCurSel === 'lei' ? 'selected' : '' ?>>LEI</option>
            <option value="usd" <?= $ucCurSel === 'usd' ? 'selected' : '' ?>>USD</option>
            <option value="eur" <?= $ucCurSel === 'eur' ? 'selected' : '' ?>>EUR</option>
          </select>
        </div>
      </div>
      <div class="col-6">
        <label>Nota (optional)</label>
        <input name="note">
      </div>
      <div class="col-12 row" style="justify-content:flex-end">
        <button class="btn primary" type="submit">Salveaza miscare</button>
      </div>
    </div>
  </form>
</div>

<div class="card" style="margin-top:12px">
  <h3 style="margin-top:0">Istoric (ultimele 200)</h3>
  <table>
    <thead>
      <tr><th>Data</th><th>Tip</th><th>Cant.</th><th>Cost</th><th>User</th><th>Referinta</th><th>Nota</th></tr>
    </thead>
    <tbody>
      <?php foreach ($movements as $mv): ?>
        <tr>
          <td><?= htmlspecialchars(isset($date_dmy) ? $date_dmy($mv['created_at']) : (string) $mv['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $mv['movement_type'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= number_format((float) $mv['qty'], 4) ?></td>
          <td><?= $mv['unit_cost'] === null ? '-' : number_format((float) $mv['unit_cost'], 4) ?></td>
          <td><?= htmlspecialchars((string) $mv['user_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) ($mv['ref_type'] ?? '-'), ENT_QUOTES, 'UTF-8') ?><?= $mv['ref_id'] ? ' #' . (int) $mv['ref_id'] : '' ?></td>
          <td><?= htmlspecialchars((string) ($mv['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
