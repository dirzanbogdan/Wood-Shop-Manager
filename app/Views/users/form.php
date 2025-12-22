<?php

declare(strict_types=1);

$isEdit = is_array($userRow);
$action = $isEdit ? '/?r=users/edit&id=' . (int) $userRow['id'] : '/?r=users/create';
$canSetSuperAdmin = ($currentRole ?? '') === 'SuperAdmin';
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0"><?= htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="row">
      <a class="btn small" href="/?r=users/index">Inapoi</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="grid">
      <div class="col-6">
        <label>Nume</label>
        <input name="name" required value="<?= htmlspecialchars((string) ($userRow['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6">
        <label>Username</label>
        <input name="username" required value="<?= htmlspecialchars((string) ($userRow['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-6">
        <label>Rol</label>
        <select name="role" required>
          <?php if ($canSetSuperAdmin): ?>
            <option value="SuperAdmin" <?= $isEdit && (string) $userRow['role'] === 'SuperAdmin' ? 'selected' : '' ?>>SuperAdmin</option>
          <?php endif; ?>
          <option value="Admin" <?= $isEdit && (string) $userRow['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
          <option value="Operator" <?= $isEdit && (string) $userRow['role'] === 'Operator' ? 'selected' : '' ?>>Operator</option>
        </select>
      </div>
      <div class="col-6">
        <label>Parola <?= $isEdit ? '(optional)' : '(obligatoriu)' ?></label>
        <input name="password" type="password" <?= $isEdit ? '' : 'required' ?>>
        <div class="muted" style="font-size: 12px; margin-top: 4px">
          Minim <?= (int) ($password_min_length ?? 12) ?> + litere mari/mici + cifra + simbol
        </div>
      </div>
      <div class="col-12">
        <label>
          <input type="checkbox" name="is_active" <?= !$isEdit || (int) ($userRow['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
          Activ
        </label>
      </div>
      <div class="col-12 row" style="justify-content:flex-end">
        <button class="btn primary" type="submit">Salveaza</button>
      </div>
    </div>
  </form>
</div>

