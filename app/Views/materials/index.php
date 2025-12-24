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
        <td><?= isset($money) ? $money((float) $m['unit_cost'], 4) : number_format((float) $m['unit_cost'], 4) ?></td>
        <td><?= number_format((float) $m['min_stock'], 4) ?> <?= htmlspecialchars((string) $m['unit_code'], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="row" style="justify-content: flex-end">
          <?php if (isset($m['purchase_url']) && is_string($m['purchase_url']) && trim($m['purchase_url']) !== ''): ?>
            <a
              class="btn small"
              href="<?= htmlspecialchars((string) $m['purchase_url'], ENT_QUOTES, 'UTF-8') ?>"
              target="_blank"
              rel="noopener noreferrer"
              title="Go to URL"
              aria-label="Go to URL"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" style="display:block">
                <path fill="currentColor" d="M10.59 13.41a1.996 1.996 0 0 1 0-2.82l2.82-2.82a2 2 0 0 1 2.83 2.82l-.88.88a1 1 0 1 0 1.41 1.41l.88-.88a4 4 0 0 0-5.66-5.66l-2.82 2.82a4 4 0 0 0 0 5.66 1 1 0 1 0 1.41-1.41Zm2.82-2.82a1.996 1.996 0 0 1 0 2.82l-2.82 2.82a2 2 0 1 1-2.83-2.82l.88-.88a1 1 0 0 0-1.41-1.41l-.88.88a4 4 0 1 0 5.66 5.66l2.82-2.82a4 4 0 0 0 0-5.66 1 1 0 1 0-1.41 1.41Z"/>
              </svg>
            </a>
          <?php endif; ?>
          <a class="btn small" href="/?r=materials/movements&id=<?= (int) $m['id'] ?>">Istoric</a>
          <a class="btn small" href="/?r=materials/edit&id=<?= (int) $m['id'] ?>">Editeaza</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
