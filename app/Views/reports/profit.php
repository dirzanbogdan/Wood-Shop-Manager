<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Profit estimat</h2>
    <div class="row">
      <a class="btn small" href="/?r=reports/profit&export=csv">Export CSV</a>
    </div>
  </div>
  <p class="muted" style="margin-bottom:0">Estimare bazata pe costul mediu/unit din productii finalizate.</p>
</div>

<div class="card" style="margin-top:12px">
  <table>
    <thead>
      <tr><th>Produs</th><th>SKU</th><th>Pret</th><th>Cost mediu/unit</th><th>Marja</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php $margin = (float) $r['margin']; ?>
        <tr>
          <td><?= htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $r['sku'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= number_format((float) $r['sale_price'], 2) ?> lei</td>
          <td><?= number_format((float) $r['avg_cost_per_unit'], 2) ?> lei</td>
          <td>
            <span class="badge <?= $margin <= 0 ? 'danger' : 'ok' ?>">
              <?= number_format($margin, 2) ?> lei
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

