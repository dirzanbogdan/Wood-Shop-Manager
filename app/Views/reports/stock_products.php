<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Produse finite disponibile</h2>
    <div class="row">
      <a class="btn small" href="/?r=reports/stockProducts&export=csv">Export CSV</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <table>
    <thead>
      <tr><th>Produs</th><th>SKU</th><th>Categorie</th><th>Status</th><th>Stoc</th><th>Pret</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $r['sku'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) ($r['category_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge"><?= htmlspecialchars((string) $r['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= (int) $r['stock_qty'] ?></td>
          <td><?= isset($money) ? $money((float) $r['sale_price'], 2) : number_format((float) $r['sale_price'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
