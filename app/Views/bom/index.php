<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Retete / BOM</h2>
    <div class="row">
      <a class="btn small" href="/?r=products/index">Produse</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <table>
    <thead>
      <tr><th>Produs</th><th>SKU</th><th>Materii</th><th>Utilaje</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $p['sku'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int) $p['materials_cnt'] ?></td>
          <td><?= (int) $p['machines_cnt'] ?></td>
          <td class="row" style="justify-content:flex-end">
            <a class="btn small" href="/?r=bom/edit&id=<?= (int) $p['id'] ?>">Editeaza reteta</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

