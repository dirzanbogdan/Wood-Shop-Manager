<?php

declare(strict_types=1);

$qs = http_build_query(['r' => 'reports/hours', 'range' => $range, 'from' => $from, 'to' => $to, 'product_id' => $product_id ?? '', 'export' => 'csv']);
?>
<div class="page-reports">
  <div class="card">
    <div class="row" style="justify-content: space-between">
      <h2 style="margin:0">Ore lucrate</h2>
      <div class="row">
        <form method="get" action="/" class="row">
          <input type="hidden" name="r" value="reports/hours">
          <select name="range">
            <option value="" <?= ($range ?? '') === '' ? 'selected' : '' ?>>Custom</option>
            <option value="7d" <?= ($range ?? '') === '7d' ? 'selected' : '' ?>>Ultimele 7 zile</option>
            <option value="30d" <?= ($range ?? '') === '30d' ? 'selected' : '' ?>>Ultimele 30 zile</option>
            <option value="this_month" <?= ($range ?? '') === 'this_month' ? 'selected' : '' ?>>Luna curenta</option>
            <option value="last_month" <?= ($range ?? '') === 'last_month' ? 'selected' : '' ?>>Luna trecuta</option>
            <option value="this_year" <?= ($range ?? '') === 'this_year' ? 'selected' : '' ?>>Anul curent</option>
            <option value="last_year" <?= ($range ?? '') === 'last_year' ? 'selected' : '' ?>>Anul trecut</option>
          </select>
          <?php $pid = $product_id ?? null; ?>
          <select name="product_id" style="min-width:220px">
            <option value="">Toate produsele</option>
            <?php foreach (($products ?? []) as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= $pid !== null && (int) $pid === (int) $p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $p['sku'], ENT_QUOTES, 'UTF-8') ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <input name="from" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars(isset($date_dmy) ? $date_dmy((string) $from) : (string) $from, ENT_QUOTES, 'UTF-8') ?>">
          <input name="to" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars(isset($date_dmy) ? $date_dmy((string) $to) : (string) $to, ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn small" type="submit">Filtreaza</button>
        </form>
        <a class="btn small" href="/?<?= htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') ?>">Export CSV</a>
      </div>
    </div>
    <p class="muted" style="margin-bottom:0">Cost orar operator: <?= isset($money) ? $money((float) $hourly, 2) : number_format((float) $hourly, 2) ?> /ora</p>
  </div>

  <div class="card" style="margin-top:12px">
    <table>
      <thead>
        <tr><th>Operator</th><th>Ore</th><th>Cost (estimativ)</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string) $r['operator_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((float) $r['hours_worked'], 2) ?></td>
            <td><?= isset($money) ? $money((float) $r['cost'], 2) : number_format((float) $r['cost'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
