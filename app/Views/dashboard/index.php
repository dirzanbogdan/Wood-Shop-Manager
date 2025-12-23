<?php

declare(strict_types=1);
?>
<div class="grid">
  <div class="col-12">
    <div class="card">
      <div class="row" style="justify-content: space-between">
        <h2 style="margin:0">Dashboard</h2>
        <div class="muted">Ultimele 30 zile energie: <strong><?= number_format((float) ($energy30['kwh'] ?? 0), 2) ?> kWh</strong>, cost <strong><?= isset($money) ? $money((float) ($energy30['cost'] ?? 0), 2) : number_format((float) ($energy30['cost'] ?? 0), 2) ?></strong></div>
      </div>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h3 style="margin-top:0">Stocuri critice</h3>
      <?php if (!$critical): ?>
        <div class="muted">Nicio alerta.</div>
      <?php else: ?>
        <table>
          <thead>
          <tr><th>Material</th><th>Curent</th><th>Minim</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach ($critical as $m): ?>
            <tr>
              <td><?= htmlspecialchars((string) $m['name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><span class="badge danger"><?= number_format((float) $m['current_qty'], 4) ?> <?= htmlspecialchars((string) $m['unit_code'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td><?= number_format((float) $m['min_stock'], 4) ?> <?= htmlspecialchars((string) $m['unit_code'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><a class="btn small" href="/?r=materials/edit&id=<?= (int) $m['id'] ?>">Detalii</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h3 style="margin-top:0">Productii in curs</h3>
      <?php if (!$inProgress): ?>
        <div class="muted">Nicio productie pornita.</div>
      <?php else: ?>
        <table>
          <thead>
          <tr><th>ID</th><th>Produs</th><th>Cant.</th><th>Operator</th><th>Start</th></tr>
          </thead>
          <tbody>
          <?php foreach ($inProgress as $po): ?>
            <tr>
              <td>#<?= (int) $po['id'] ?></td>
              <td><?= htmlspecialchars((string) $po['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int) $po['qty'] ?></td>
              <td><?= htmlspecialchars((string) $po['operator_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars(isset($date_dmy) ? $date_dmy($po['started_at']) : (string) $po['started_at'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <div style="margin-top:12px">
        <a class="btn small" href="/?r=production/index">Mergi la Productie</a>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <h3 style="margin-top:0">Produse cu profit mic/negativ (estimare)</h3>
      <table>
        <thead>
        <tr><th>Produs</th><th>SKU</th><th>Pret</th><th>Cost mediu/unit</th><th>Marja</th></tr>
        </thead>
        <tbody>
        <?php foreach ($lowProfit as $p): ?>
          <?php $margin = (float) $p['margin']; ?>
          <tr>
            <td><?= htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $p['sku'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= isset($money) ? $money((float) $p['sale_price'], 2) : number_format((float) $p['sale_price'], 2) ?></td>
            <td><?= isset($money) ? $money((float) $p['avg_cost_per_unit'], 2) : number_format((float) $p['avg_cost_per_unit'], 2) ?></td>
            <td>
              <span class="badge <?= $margin <= 0 ? 'danger' : 'ok' ?>">
                <?= isset($money) ? $money($margin, 2) : number_format($margin, 2) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div style="margin-top:12px">
        <a class="btn small" href="/?r=reports/profit">Raport profit</a>
      </div>
    </div>
  </div>
</div>
