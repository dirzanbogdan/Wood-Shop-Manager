<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="row" style="justify-content: space-between">
    <h2 style="margin:0">Inregistreaza vanzare</h2>
    <div class="row">
      <a class="btn small" href="/?r=products/index">Inapoi</a>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <form method="post" action="/?r=products/sell">
    <input type="hidden" name="<?= htmlspecialchars((string) $csrf_key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="grid">
      <div class="col-12">
        <label>Produs (stoc curent)</label>
        <select name="product_id" required>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int) $p['id'] ?>">
              <?= htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') ?>
              (<?= htmlspecialchars((string) $p['sku'], ENT_QUOTES, 'UTF-8') ?>) - stoc: <?= (int) $p['stock_qty'] ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6">
        <label>Cantitate</label>
        <input name="qty" required value="1">
      </div>
      <div class="col-6">
        <label>Pret vanzare / buc (lei)</label>
        <input name="sale_price" required>
      </div>
      <div class="col-6">
        <label>Client (optional)</label>
        <input name="customer_name">
      </div>
      <div class="col-6">
        <label>Canal (optional)</label>
        <input name="channel" placeholder="ex: Etsy, OLX, showroom">
      </div>
      <div class="col-12 row" style="justify-content:flex-end">
        <button class="btn primary" type="submit">Salveaza</button>
      </div>
    </div>
  </form>
</div>

<script>
(() => {
  const products = <?= json_encode(array_map(static function (array $p): array {
      return ['id' => (int) $p['id'], 'sale_price' => (string) $p['sale_price']];
  }, $products), JSON_UNESCAPED_UNICODE) ?>;
  const sel = document.querySelector('select[name="product_id"]');
  const price = document.querySelector('input[name="sale_price"]');
  if (!sel || !price) return;
  const fill = () => {
    const id = parseInt(sel.value, 10);
    const p = products.find(x => x.id === id);
    if (p && (!price.value || price.value === "0")) price.value = p.sale_price;
  };
  sel.addEventListener('change', fill);
  fill();
})();
</script>

