<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Productie</h2>
    <div class="row">
      <a class="btn primary small" href="/?r=production/start">Porneste productie</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Produs</th>
        <th>Cant.</th>
        <th>Status</th>
        <th>Operator</th>
        <th>Start</th>
        <th>Final</th>
        <th>Cost total</th>
        <th>Cost/unit</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?= (int) $o['id'] ?></td>
          <td><?= htmlspecialchars((string) $o['product_name'], ENT_QUOTES, 'UTF-8') ?> <span class="muted">(<?= htmlspecialchars((string) $o['sku'], ENT_QUOTES, 'UTF-8') ?>)</span></td>
          <td><?= (int) $o['qty'] ?></td>
          <td><span class="badge"><?= htmlspecialchars((string) $o['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= htmlspecialchars((string) $o['operator_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $o['started_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= $o['completed_at'] ? htmlspecialchars((string) $o['completed_at'], ENT_QUOTES, 'UTF-8') : '-' ?></td>
          <td><?= $o['total_cost'] === null ? '-' : (isset($money) ? $money((float) $o['total_cost'], 2) : number_format((float) $o['total_cost'], 2)) ?></td>
          <td><?= $o['cost_per_unit'] === null ? '-' : (isset($money) ? $money((float) $o['cost_per_unit'], 2) : number_format((float) $o['cost_per_unit'], 2)) ?></td>
          <td class="row" style="justify-content:flex-end">
            <?php if ((string) $o['status'] === 'Pornita'): ?>
              <form method="post" action="/?r=production/finalize&id=<?= (int) $o['id'] ?>">
                <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button class="btn primary small" type="submit" onclick="return confirm('Finalizezi comanda si scazi stocurile?');">Finalizeaza</button>
              </form>
              <form method="post" action="/?r=production/cancel&id=<?= (int) $o['id'] ?>">
                <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button class="btn danger small" type="submit" onclick="return confirm('Anulezi comanda?');">Anuleaza</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
