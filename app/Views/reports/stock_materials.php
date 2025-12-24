<?php

declare(strict_types=1);
?>
<div class="page-reports">
  <div class="card">
    <div class="row" style="justify-content: space-between">
      <h2 style="margin:0">Stoc materie prima</h2>
      <div class="row">
        <a class="btn small" href="/?r=reports/stockMaterials&export=csv">Export CSV</a>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:12px">
    <table>
      <thead>
        <tr>
          <th>Tip</th>
          <th>Material</th>
          <th>Furnizor</th>
          <th>UM</th>
          <th>Cantitate</th>
          <th>Cost unitar</th>
          <th>Valoare</th>
          <th>Minim</th>
          <th>Critic</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string) $r['type_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $r['material_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($r['supplier_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $r['unit_code'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((float) $r['current_qty'], 4) ?></td>
            <td><?= isset($money) ? $money((float) $r['unit_cost'], 4) : number_format((float) $r['unit_cost'], 4) ?></td>
            <td><?= isset($money) ? $money((float) $r['stock_value'], 2) : number_format((float) $r['stock_value'], 2) ?></td>
            <td><?= number_format((float) $r['min_stock'], 4) ?></td>
            <td>
              <span class="badge <?= (int) $r['is_critical'] === 1 ? 'danger' : 'ok' ?>">
                <?= (int) $r['is_critical'] === 1 ? 'DA' : 'NU' ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
