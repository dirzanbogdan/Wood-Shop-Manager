<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Furnizori</h2>
    <div class="row">
      <a class="btn small" href="/?r=settings/index">Setari</a>
      <a class="btn primary small" href="/?r=suppliers/create">Adauga</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <table>
    <thead>
      <tr><th>Nume</th><th>Telefon</th><th>Email</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) ($r['phone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) ($r['email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <span class="badge <?= (int) $r['is_active'] === 1 ? 'ok' : 'danger' ?>">
              <?= (int) $r['is_active'] === 1 ? 'Activ' : 'Inactiv' ?>
            </span>
          </td>
          <td class="row" style="justify-content:flex-end">
            <a class="btn small" href="/?r=suppliers/edit&id=<?= (int) $r['id'] ?>">Editeaza</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

