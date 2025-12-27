<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <div class="row">
      <h2 style="margin:0">Reteta: <?= htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8') ?></h2>
      <span class="badge"><?= htmlspecialchars((string) $product['sku'], ENT_QUOTES, 'UTF-8') ?></span>
      <span class="muted">Manpower: <strong><?= number_format((float) ($product['manpower_hours'] ?? 0), 2) ?> ore/unit</strong></span>
    </div>
    <div class="row">
      <a class="btn small" href="/?r=products/edit&id=<?= (int) $product['id'] ?>">Produs</a>
      <a class="btn small" href="/?r=bom/index">Inapoi</a>
    </div>
  </div>
</div>

<div class="grid" style="margin-top:12px">
  <div class="col-6">
    <div class="card">
      <div class="row" style="justify-content: space-between">
        <h3 style="margin:0">Materii prime</h3>
      </div>

      <?php if ($canEdit): ?>
        <form method="post" action="/?r=bom/addMaterial&id=<?= (int) $product['id'] ?>" style="margin-top:12px">
          <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
          <div class="grid">
            <div class="col-12">
              <label>Material</label>
              <select name="material_id" required>
                <?php foreach ($allMaterials as $m): ?>
                  <option value="<?= (int) $m['id'] ?>">
                    <?= htmlspecialchars((string) $m['name'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $m['unit_code'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($m['unit_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label>Cantitate / unitate</label>
              <input name="qty" required>
            </div>
            <div class="col-6">
              <label>Waste %</label>
              <input name="waste_percent" required value="0">
            </div>
            <div class="col-12 row" style="justify-content:flex-end">
              <button class="btn primary small" type="submit">Adauga/Update</button>
            </div>
          </div>
        </form>
      <?php endif; ?>

      <table style="margin-top:12px">
        <thead>
        <tr><th>Material</th><th>Qty/unit</th><th>Waste</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($materials as $bm): ?>
          <tr>
            <td><?= htmlspecialchars((string) $bm['material_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((float) $bm['qty'], 4) ?> <?= htmlspecialchars((string) $bm['unit_code'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((float) $bm['waste_percent'], 2) ?>%</td>
            <td class="row" style="justify-content:flex-end">
              <?php if ($canEdit): ?>
                <form method="post" action="/?r=bom/deleteMaterial&id=<?= (int) $product['id'] ?>&bom_id=<?= (int) $bm['id'] ?>">
                  <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
                  <button class="btn danger small" type="submit" onclick="return confirm('Stergi linia?');">Sterge</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h3 style="margin:0">Utilaje</h3>

      <?php if ($canEdit): ?>
        <form method="post" action="/?r=bom/addMachine&id=<?= (int) $product['id'] ?>" style="margin-top:12px">
          <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
          <div class="grid">
            <div class="col-12">
              <label>Utilaj</label>
              <select name="machine_id" required>
                <?php foreach ($allMachines as $mc): ?>
                  <option value="<?= (int) $mc['id'] ?>">
                    <?= htmlspecialchars((string) $mc['name'], ENT_QUOTES, 'UTF-8') ?>
                    <?= (int) $mc['is_active'] === 1 ? '' : '(inactiv)' ?>
                    (<?= number_format((float) $mc['power_kw'], 3) ?> kW)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label>Minute / unitate</label>
              <input name="minutes" type="number" min="1" step="1" required>
            </div>
            <div class="col-12 row" style="justify-content:flex-end">
              <button class="btn primary small" type="submit">Adauga/Update</button>
            </div>
          </div>
        </form>
      <?php endif; ?>

      <table style="margin-top:12px">
        <thead>
        <tr><th>Utilaj</th><th>Minute/unit</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($machines as $bmc): ?>
          <tr>
            <td><?= htmlspecialchars((string) $bmc['machine_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((float) $bmc['hours'] * 60, 0) ?></td>
            <td>
              <span class="badge <?= (int) $bmc['is_active'] === 1 ? 'ok' : 'danger' ?>">
                <?= (int) $bmc['is_active'] === 1 ? 'Activ' : 'Inactiv' ?>
              </span>
            </td>
            <td class="row" style="justify-content:flex-end">
              <?php if ($canEdit): ?>
                <form method="post" action="/?r=bom/deleteMachine&id=<?= (int) $product['id'] ?>&bom_id=<?= (int) $bmc['id'] ?>">
                  <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
                  <button class="btn danger small" type="submit" onclick="return confirm('Stergi linia?');">Sterge</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
