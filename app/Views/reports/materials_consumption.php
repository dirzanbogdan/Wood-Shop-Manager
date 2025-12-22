<?php

declare(strict_types=1);

$qs = http_build_query(['r' => 'reports/materialsConsumption', 'from' => $from, 'to' => $to, 'export' => 'csv']);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Consum materie prima</h2>
    <div class="row">
      <form method="get" action="/" class="row">
        <input type="hidden" name="r" value="reports/materialsConsumption">
        <input type="date" name="from" value="<?= htmlspecialchars((string) $from, ENT_QUOTES, 'UTF-8') ?>">
        <input type="date" name="to" value="<?= htmlspecialchars((string) $to, ENT_QUOTES, 'UTF-8') ?>">
        <button class="btn small" type="submit">Filtreaza</button>
      </form>
      <a class="btn small" href="/?<?= htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') ?>">Export CSV</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <table>
    <thead>
      <tr><th>Material</th><th>Tip</th><th>UM</th><th>Cantitate</th><th>Cost</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) $r['material_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $r['type_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $r['unit_code'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= number_format((float) $r['qty_used'], 4) ?></td>
          <td><?= number_format((float) $r['cost'], 2) ?> lei</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

