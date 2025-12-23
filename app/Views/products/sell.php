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
        <label>Pret vanzare / buc</label>
        <?php $spCurSel = (string) ($currency ?? 'lei'); ?>
        <div class="row" style="gap:8px">
          <input name="sale_price" required style="flex:1">
          <select name="sale_price_currency" style="width:110px">
            <option value="lei" <?= $spCurSel === 'lei' ? 'selected' : '' ?>>LEI</option>
            <option value="usd" <?= $spCurSel === 'usd' ? 'selected' : '' ?>>USD</option>
            <option value="eur" <?= $spCurSel === 'eur' ? 'selected' : '' ?>>EUR</option>
          </select>
        </div>
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
      return ['id' => (int) $p['id'], 'sale_price_lei' => (string) $p['sale_price']];
  }, $products), JSON_UNESCAPED_UNICODE) ?>;
  const sel = document.querySelector('select[name="product_id"]');
  const price = document.querySelector('input[name="sale_price"]');
  const cur = document.querySelector('select[name="sale_price_currency"]');
  const fx = <?= json_encode((array) ($fx_rates ?? []), JSON_UNESCAPED_UNICODE) ?>;
  if (!sel || !price) return;
  if (!cur) return;
  const rateRon = (code) => {
    const k = (code || '').toLowerCase();
    if (k === 'lei') return 1;
    const v = fx[k];
    return typeof v === 'number' && v > 0 ? v : 1;
  };
  const fmt = (n) => {
    const x = Math.round((n + Number.EPSILON) * 10000) / 10000;
    return x.toFixed(4).replace(/\.?0+$/, m => m.startsWith('.') ? '' : m);
  };
  const fill = () => {
    const id = parseInt(sel.value, 10);
    const p = products.find(x => x.id === id);
    if (!p) return;
    const lei = parseFloat((p.sale_price_lei || '0').toString().replace(',', '.')) || 0;
    const k = cur.value || 'lei';
    const v = lei / rateRon(k);
    if (!price.value || price.value === "0") price.value = fmt(v);
    price.dataset.baseLei = lei.toString();
  };
  const onCur = () => {
    const base = parseFloat(price.dataset.baseLei || '0') || 0;
    if (base <= 0) return;
    const k = cur.value || 'lei';
    price.value = fmt(base / rateRon(k));
  };
  sel.addEventListener('change', fill);
  cur.addEventListener('change', onCur);
  fill();
})();
</script>
