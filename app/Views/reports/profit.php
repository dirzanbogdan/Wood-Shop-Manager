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
      <tr><th>Produs</th><th>SKU</th><th>Pret/Unit</th><th>Cost mediu/unit</th><th>Impozit</th><th>Marja</th><th>Cant vanduta</th><th>Valoare vanzare</th><th>Profit Net</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php $margin = (float) $r['marja']; ?>
        <tr>
          <td><?= htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $r['sku'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= isset($money) ? $money((float) $r['pret_unit'], 2) : number_format((float) $r['pret_unit'], 2) ?></td>
          <td><?= isset($money) ? $money((float) $r['avg_cost_per_unit'], 2) : number_format((float) $r['avg_cost_per_unit'], 2) ?></td>
          <td><?= isset($money) ? $money((float) $r['impozit'], 2) : number_format((float) $r['impozit'], 2) ?></td>
          <td>
            <span class="badge <?= $margin <= 0 ? 'danger' : 'ok' ?>">
              <?= isset($money) ? $money($margin, 2) : number_format($margin, 2) ?>
            </span>
          </td>
          <td><?= (int) $r['cant_vanduta'] ?></td>
          <td><?= isset($money) ? $money((float) $r['valoare_vanzare'], 2) : number_format((float) $r['valoare_vanzare'], 2) ?></td>
          <td><?= isset($money) ? $money((float) $r['profit_net'], 2) : number_format((float) $r['profit_net'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
