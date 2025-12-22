<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Setari</h2>
    <div class="row">
      <a class="btn small" href="/?r=dashboard/index">Dashboard</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <h3 style="margin-top:0">Nomenclatoare</h3>
  <div class="row" style="gap:8px; flex-wrap:wrap">
    <a class="btn small" href="/?r=suppliers/index">Furnizori</a>
    <a class="btn small" href="/?r=materialtypes/index">Tipuri materiale</a>
    <a class="btn small" href="/?r=categories/index">Categorii produse</a>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <form method="post" action="/?r=settings/index">
    <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="grid">
      <div class="col-6">
        <label>Cost energie electrica (lei / kWh)</label>
        <input name="energy_cost_per_kwh" required value="<?= htmlspecialchars((string) $energy_cost_per_kwh, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6">
        <label>Cost orar operator (lei / ora)</label>
        <input name="operator_hourly_cost" required value="<?= htmlspecialchars((string) $operator_hourly_cost, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 row" style="justify-content:flex-end">
        <button class="btn primary" type="submit">Salveaza</button>
      </div>
    </div>
  </form>
</div>
