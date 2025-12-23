<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Utilizatori</h2>
    <div class="row">
      <a class="btn primary small" href="/?r=users/create">Adauga</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <table>
    <thead>
      <tr>
        <th>Nume</th>
        <th>Username</th>
        <th>Rol</th>
        <th>Status</th>
        <th>Ultim login</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars((string) $u['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $u['username'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge"><?= htmlspecialchars((string) $u['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td>
            <span class="badge <?= (int) $u['is_active'] === 1 ? 'ok' : 'danger' ?>">
              <?= (int) $u['is_active'] === 1 ? 'Activ' : 'Inactiv' ?>
            </span>
          </td>
          <td><?= $u['last_login_at'] ? htmlspecialchars(isset($date_dmy) ? $date_dmy($u['last_login_at']) : (string) $u['last_login_at'], ENT_QUOTES, 'UTF-8') : '-' ?></td>
          <td class="row" style="justify-content:flex-end">
            <a class="btn small" href="/?r=users/edit&id=<?= (int) $u['id'] ?>">Editeaza</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
