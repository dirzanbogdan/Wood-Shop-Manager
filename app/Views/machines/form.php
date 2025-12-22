<?php

declare(strict_types=1);

$isEdit = is_array($machine);
$action = $isEdit ? '/?r=machines/edit&id=' . (int) $machine['id'] : '/?r=machines/create';
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0"><?= htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="row">
      <a class="btn small" href="/?r=machines/index">Inapoi</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="grid">
      <div class="col-6">
        <label>Nume aparat</label>
        <input name="name" required value="<?= htmlspecialchars((string) ($machine['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6">
        <label>Putere nominala (kW)</label>
        <input name="power_kw" required value="<?= htmlspecialchars((string) ($machine['power_kw'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <?php if ($isEdit): ?>
        <div class="col-12">
          <label>
            <input type="checkbox" name="is_active" <?= (int) ($machine['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
            Activ
          </label>
        </div>
      <?php endif; ?>
      <div class="col-12 row" style="justify-content:flex-end">
        <button class="btn primary" type="submit">Salveaza</button>
      </div>
    </div>
  </form>
</div>

