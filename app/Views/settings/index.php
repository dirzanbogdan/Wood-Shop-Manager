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
    <input type="hidden" name="action" value="settings_save">
    <div class="grid">
      <div class="col-6">
        <label>Cost energie electrica / kWh</label>
        <?php $energyCurSel = (string) ($currency ?? 'lei'); ?>
        <div class="row" style="gap:8px">
          <input name="energy_cost_per_kwh" required value="<?= htmlspecialchars((string) $energy_cost_per_kwh, ENT_QUOTES, 'UTF-8') ?>" style="flex:1">
          <select name="energy_cost_per_kwh_currency" style="width:110px">
            <option value="lei" <?= $energyCurSel === 'lei' ? 'selected' : '' ?>>LEI</option>
            <option value="usd" <?= $energyCurSel === 'usd' ? 'selected' : '' ?>>USD</option>
            <option value="eur" <?= $energyCurSel === 'eur' ? 'selected' : '' ?>>EUR</option>
          </select>
        </div>
      </div>
      <div class="col-6">
        <label>Cost orar operator / ora</label>
        <?php $hourlyCurSel = (string) ($currency ?? 'lei'); ?>
        <div class="row" style="gap:8px">
          <input name="operator_hourly_cost" required value="<?= htmlspecialchars((string) $operator_hourly_cost, ENT_QUOTES, 'UTF-8') ?>" style="flex:1">
          <select name="operator_hourly_cost_currency" style="width:110px">
            <option value="lei" <?= $hourlyCurSel === 'lei' ? 'selected' : '' ?>>LEI</option>
            <option value="usd" <?= $hourlyCurSel === 'usd' ? 'selected' : '' ?>>USD</option>
            <option value="eur" <?= $hourlyCurSel === 'eur' ? 'selected' : '' ?>>EUR</option>
          </select>
        </div>
      </div>
      <div class="col-6">
        <label>Timezone</label>
          <?php
            $tzCurrent = (string) ($timezone ?? 'Europe/Bucharest');
          ?>
        <input name="timezone" required list="tz_list" value="<?= htmlspecialchars($tzCurrent, ENT_QUOTES, 'UTF-8') ?>">
        <datalist id="tz_list">
          <?php foreach (($timezones ?? []) as $tz): ?>
            <option value="<?= htmlspecialchars((string) $tz, ENT_QUOTES, 'UTF-8') ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-6">
        <label>Limba</label>
        <?php $langCurrent = (string) ($language ?? 'ro'); ?>
        <select name="language" required>
          <option value="ro" <?= $langCurrent === 'ro' ? 'selected' : '' ?>>RO</option>
          <option value="en" <?= $langCurrent === 'en' ? 'selected' : '' ?>>EN</option>
        </select>
      </div>
      <div class="col-6">
        <label>Moneda</label>
        <?php $curCurrent = (string) ($currency ?? 'lei'); ?>
        <select name="currency" required>
          <option value="lei" <?= $curCurrent === 'lei' ? 'selected' : '' ?>>Lei</option>
          <option value="usd" <?= $curCurrent === 'usd' ? 'selected' : '' ?>>USD</option>
          <option value="eur" <?= $curCurrent === 'eur' ? 'selected' : '' ?>>EUR</option>
        </select>
      </div>
      <div class="col-12 row" style="justify-content:flex-end">
        <button class="btn primary" type="submit">Salveaza</button>
      </div>
    </div>
  </form>
</div>

<div class="card" style="margin-top:12px">
  <h3 style="margin-top:0">Unitati de masura</h3>

  <form method="post" action="/?r=settings/index" class="row" style="gap:10px; align-items:flex-end; flex-wrap:wrap">
    <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="unit_create">
    <div style="min-width:120px">
      <label>Cod</label>
      <input name="code" required placeholder="ex: buc">
    </div>
    <div style="min-width:220px; flex:1">
      <label>Denumire</label>
      <input name="name" required placeholder="ex: Bucata">
    </div>
    <button class="btn primary" type="submit">Adauga</button>
  </form>

  <div style="margin-top:12px; overflow:auto">
    <table>
      <thead>
        <tr>
          <th style="width:90px">Cod</th>
          <th>Denumire</th>
          <th style="width:140px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($units ?? []) as $u): ?>
          <tr>
            <td colspan="3">
              <form method="post" action="/?r=settings/index" class="row" style="gap:10px; align-items:flex-end; flex-wrap:wrap">
                <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="unit_update">
                <input type="hidden" name="unit_id" value="<?= (int) $u['id'] ?>">
                <div style="min-width:120px">
                  <label>Cod</label>
                  <input name="code" required value="<?= htmlspecialchars((string) $u['code'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div style="min-width:220px; flex:1">
                  <label>Denumire</label>
                  <input name="name" required value="<?= htmlspecialchars((string) $u['name'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <button class="btn" type="submit">Salveaza</button>
              </form>
              <form method="post" action="/?r=settings/index" style="margin-top:8px">
                <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="unit_delete">
                <input type="hidden" name="unit_id" value="<?= (int) $u['id'] ?>">
                <button class="btn danger small" type="submit" onclick="return confirm('Stergi unitatea?');">Sterge</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
