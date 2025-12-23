<?php

declare(strict_types=1);

$qs = http_build_query(['r' => 'reports/monthlyCost', 'range' => $range, 'from' => $from, 'to' => $to, 'export' => 'csv']);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Cost total productie (lunar)</h2>
    <div class="row">
      <form method="get" action="/" class="row">
        <input type="hidden" name="r" value="reports/monthlyCost">
        <select name="range">
          <option value="" <?= ($range ?? '') === '' ? 'selected' : '' ?>>Custom</option>
          <option value="7d" <?= ($range ?? '') === '7d' ? 'selected' : '' ?>>Ultimele 7 zile</option>
          <option value="30d" <?= ($range ?? '') === '30d' ? 'selected' : '' ?>>Ultimele 30 zile</option>
          <option value="this_month" <?= ($range ?? '') === 'this_month' ? 'selected' : '' ?>>Luna curenta</option>
          <option value="last_month" <?= ($range ?? '') === 'last_month' ? 'selected' : '' ?>>Luna trecuta</option>
          <option value="this_year" <?= ($range ?? '') === 'this_year' ? 'selected' : '' ?>>Anul curent</option>
          <option value="last_year" <?= ($range ?? '') === 'last_year' ? 'selected' : '' ?>>Anul trecut</option>
        </select>
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
      <tr><th>Luna</th><th>Materii</th><th>Energie</th><th>Manpower</th><th>Total</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) $r['ym'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= isset($money) ? $money((float) $r['materials_cost'], 2) : number_format((float) $r['materials_cost'], 2) ?></td>
          <td><?= isset($money) ? $money((float) $r['energy_cost'], 2) : number_format((float) $r['energy_cost'], 2) ?></td>
          <td><?= isset($money) ? $money((float) $r['manpower_cost'], 2) : number_format((float) $r['manpower_cost'], 2) ?></td>
          <td><strong><?= isset($money) ? $money((float) $r['total_cost'], 2) : number_format((float) $r['total_cost'], 2) ?></strong></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
