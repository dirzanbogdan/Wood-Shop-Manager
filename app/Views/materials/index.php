<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <div class="row">
      <h2 style="margin:0">Materie prima</h2>
      <span class="badge"><?= $showArchived ? 'Arhivate' : 'Active' ?></span>
    </div>
    <div class="row">
      <form method="get" action="/" class="row">
        <input type="hidden" name="r" value="materials/index">
        <input name="q" placeholder="Cauta..." value="<?= htmlspecialchars((string) $q, ENT_QUOTES, 'UTF-8') ?>" style="width: 240px">
        <button class="btn small" type="submit">Cauta</button>
      </form>
      <?php if ($showArchived): ?>
        <a class="btn small" href="/?r=materials/index">Active</a>
      <?php else: ?>
        <a class="btn small" href="/?r=materials/index&archived=1">Arhivate</a>
      <?php endif; ?>
      <a class="btn primary small" href="/?r=materials/create">Adauga</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <table>
    <thead>
    <tr>
      <th>Denumire</th>
      <th>Tip</th>
      <th>Furnizor</th>
      <th>Stoc</th>
      <th>Cost unitar</th>
      <th>Minim</th>
      <th></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($materials as $m): ?>
      <?php $isCritical = ((float) $m['min_stock']) > 0 && ((float) $m['current_qty']) <= ((float) $m['min_stock']); ?>
      <tr>
        <td><?= htmlspecialchars((string) $m['name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $m['type_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) ($m['supplier_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <span class="badge <?= $isCritical ? 'danger' : 'ok' ?>">
            <?= number_format((float) $m['current_qty'], 4) ?> <?= htmlspecialchars((string) $m['unit_code'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        </td>
        <td><?= number_format((float) $m['unit_cost'], 4) ?> lei</td>
        <td><?= number_format((float) $m['min_stock'], 4) ?> <?= htmlspecialchars((string) $m['unit_code'], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="row" style="justify-content: flex-end">
          <a class="btn small" href="/?r=materials/movements&id=<?= (int) $m['id'] ?>">Istoric</a>
          <a class="btn small" href="/?r=materials/edit&id=<?= (int) $m['id'] ?>">Editeaza</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

