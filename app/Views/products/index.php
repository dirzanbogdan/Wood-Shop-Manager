<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <div class="row">
      <h2 style="margin:0">Produse</h2>
    </div>
    <div class="row">
      <a class="btn small" href="/?r=products/sell">Vanzare</a>
      <form method="get" action="/" class="row">
        <input type="hidden" name="r" value="products/index">
        <input name="q" placeholder="Cauta..." value="<?= htmlspecialchars((string) $q, ENT_QUOTES, 'UTF-8') ?>" style="width: 240px">
        <button class="btn small" type="submit">Cauta</button>
      </form>
      <a class="btn primary small" href="/?r=products/create">Adauga</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <table>
    <thead>
      <tr>
        <th>Nume</th>
        <th>Cod</th>
        <th>Categorie</th>
        <th>Pret</th>
        <th>Manpower</th>
        <th>Status</th>
        <th>Stoc</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $p['sku'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) ($p['category_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= number_format((float) $p['sale_price'], 2) ?> lei</td>
          <td><?= number_format((float) $p['manpower_hours'], 2) ?> ore</td>
          <td><span class="badge"><?= htmlspecialchars((string) $p['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= (int) $p['stock_qty'] ?></td>
          <td class="row" style="justify-content:flex-end">
            <a class="btn small" href="/?r=bom/edit&id=<?= (int) $p['id'] ?>">Reteta</a>
            <a class="btn small" href="/?r=products/edit&id=<?= (int) $p['id'] ?>">Editeaza</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
