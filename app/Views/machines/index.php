<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <div class="row">
      <h2 style="margin:0">Utilaje</h2>
      <span class="muted">Cost energie: <strong><?= number_format((float) $energyCost, 2) ?> lei/kWh</strong></span>
    </div>
    <div class="row">
      <a class="btn small" href="/?r=settings/index">Setari</a>
      <a class="btn primary small" href="/?r=machines/create">Adauga</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <table>
    <thead>
    <tr>
      <th>Nume</th>
      <th>Putere (kW)</th>
      <th>Cost/ora (estimativ)</th>
      <th>Status</th>
      <th></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($machines as $m): ?>
      <?php
        $power = (float) $m['power_kw'];
        $costPerHour = $power * (float) $energyCost;
      ?>
      <tr>
        <td><?= htmlspecialchars((string) $m['name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format($power, 3) ?></td>
        <td><?= number_format($costPerHour, 2) ?> lei/ora</td>
        <td>
          <span class="badge <?= (int) $m['is_active'] === 1 ? 'ok' : 'danger' ?>">
            <?= (int) $m['is_active'] === 1 ? 'Activ' : 'Inactiv' ?>
          </span>
        </td>
        <td class="row" style="justify-content:flex-end">
          <a class="btn small" href="/?r=machines/edit&id=<?= (int) $m['id'] ?>">Editeaza</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

