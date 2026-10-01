<?php
$p = $product;
$onSale = $p['type'] === 'simple' && (int)$p['compare_price'] > (int)$p['price'];
$priceHtml = static fn(int $price, int $compare = 0) => ($compare > $price ? '<del>' . e(money($compare)) . '</del>' : '') . e(money($price));
$range = (int)$p['price_min'] !== (int)$p['price_max'] ? '<span class="from">' . e(__('Da')) . '</span> ' . e(money($p['price_min'])) : $priceHtml((int)$p['price_min']);
$images = $p['images'];
$first = $images[0]['path'] ?? null;
$jsVariants = [];
foreach ($p['variants'] as $v) {
    if (!$v['active']) { continue; }
    $jsVariants[] = [
        'id' => (int)$v['id'], 'options' => $v['options'],
        'html' => $priceHtml((int)$v['final_price'], (int)($v['compare_price'] ?? 0)),
        'stock_ok' => Alien\Services\Catalog::inStock($p, $v),
        'image' => $v['image'] ? upload_url($v['image']) : null,
    ];
}
$data = ['attributes' => array_column($p['attributes'], 'name'), 'variants' => $jsVariants, 'range' => $range, 't' => ['in' => __('Disponibile'), 'out' => __('Esaurito')]];
?>
<?= partial('breadcrumbs', ['trail' => $trail]) ?>
<div class="product">
  <div class="gallery">
    <div class="gallery-main"><img id="main-image" src="<?= e(upload_url($first, 800)) ?>" <?php if ($ss = srcset($first)): ?>srcset="<?= e($ss) ?>" sizes="(max-width:900px) 100vw, 55vw"<?php endif ?> alt="<?= e($images[0]['alt'] ?? '' ?: $p['name']) ?>" width="800" height="800" fetchpriority="high"></div>
    <?php if (count($images) > 1): ?><div class="thumbs"><?php foreach ($images as $i => $im): ?>
      <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-src="<?= e(upload_url($im['path'], 800)) ?>" data-srcset="<?= e(srcset($im['path'])) ?>" aria-label="<?= e(__('Immagine %d', $i + 1)) ?>"><img src="<?= e(upload_url($im['path'], 400)) ?>" alt="" width="72" height="72" loading="lazy"></button>
    <?php endforeach ?></div><?php endif ?>
  </div>
  <div class="product-info">
    <?php if ($p['vendor'] !== ''): ?><div class="breadcrumbs" style="margin:0"><?= e($p['vendor']) ?></div><?php endif ?>
    <h1><?= e($p['name']) ?></h1>
    <div class="price" id="price"><?= $p['type'] === 'variable' ? $range : $priceHtml((int)$p['price'], (int)$p['compare_price']) ?></div>
    <?php if ($p['short_description']): ?><div class="prose"><?= $p['short_description'] ?></div><?php endif ?>
    <form id="add-form" action="<?= e(url('cart/add')) ?>" method="post">
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <input type="hidden" name="variant_id" id="variant-id" value="">
      <?php foreach ($p['attributes'] as $a): ?>
        <div class="opt-group" role="radiogroup" aria-label="<?= e($a['name']) ?>"><span><?= e($a['name']) ?></span>
          <div class="opts"><?php foreach ($a['values'] as $i => $val): $id = 'o' . $a['id'] . '_' . $val['id']; ?>
            <input type="radio" id="<?= $id ?>" name="opt[<?= e($a['name']) ?>]" value="<?= e($val['value']) ?>" required <?= $i === 0 && count($a['values']) === 1 ? 'checked' : '' ?>>
            <label for="<?= $id ?>"><?= e($val['value']) ?><?php if ((int)$val['price_delta']): ?><small><?= (int)$val['price_delta'] > 0 ? '+' : '−' ?><?= e(money(abs((int)$val['price_delta']))) ?></small><?php endif ?></label>
          <?php endforeach ?></div></div>
      <?php endforeach ?>
      <div id="stock" class="stock <?= $p['in_stock'] ? 'in' : 'out' ?>"><?= $p['type'] === 'simple' ? e($p['in_stock'] ? __('Disponibile') : __('Esaurito')) : '' ?></div>
      <div class="buy">
        <div class="qty"><button type="button" data-step="-1" aria-label="−">−</button><input type="number" name="qty" value="1" min="1" max="99" aria-label="<?= e(__('Quantità')) ?>"><button type="button" data-step="1" aria-label="+">+</button></div>
        <button id="add-btn" class="btn" type="submit" <?= !$p['in_stock'] || $p['type'] === 'variable' ? 'disabled' : '' ?> style="flex:1"><?= e(__('Aggiungi al carrello')) ?></button>
      </div>
    </form>
    <div class="meta-list">
      <?php if ($p['sku'] !== ''): ?><div><?= e(__('SKU')) ?>: <?= e($p['sku']) ?></div><?php endif ?>
      <?php if ($p['categories']): ?><div><?= e(__('Categorie')) ?>: <?php foreach ($p['categories'] as $i => $c): ?><?= $i ? ', ' : '' ?><a href="<?= e(url('collections/' . $c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach ?></div><?php endif ?>
    </div>
  </div>
</div>
<?php if ($p['description']): ?><section class="description prose"><h2><?= e(__('Descrizione')) ?></h2><?= $p['description'] ?></section><?php endif ?>
<?php if ($related): ?><section class="section" style="margin-top:56px"><div class="section-head"><h2><?= e(__('Potrebbero interessarti')) ?></h2></div>
  <div class="grid"><?php foreach ($related as $r): ?><?= partial('product-card', ['p' => $r]) ?><?php endforeach ?></div></section><?php endif ?>
<script type="application/json" id="product-data"><?= json_encode($data, json_flags()) ?></script>
