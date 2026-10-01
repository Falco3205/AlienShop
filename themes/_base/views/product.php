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
        'id' => (int)$v['id'], 'options' => $v['options'], 'value' => Alien\Services\Analytics::amount((int)$v['final_price']),
        'html' => $priceHtml((int)$v['final_price'], (int)($v['compare_price'] ?? 0)),
        'stock_ok' => Alien\Services\Catalog::inStock($p, $v),
        'image' => $v['image'] ? upload_url($v['image']) : null,
    ];
}
$data = ['id' => (int)$p['id'], 'beacon' => url('t'), 'item' => Alien\Services\Analytics::item($p, (int)$p['price_min']), 'attributes' => array_column($p['attributes'], 'name'), 'variants' => $jsVariants, 'range' => $range, 't' => ['in' => __('Disponibile'), 'out' => __('Esaurito')]];
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
    <?php if (!$p['in_stock'] && Alien\Services\Modules::on('stock_alerts')): ?>
    <form id="notify-form" class="notify" method="post" action="<?= e(url('products/' . $p['slug'] . '/notify')) ?>"><strong><?= e(__('Avvisami quando torna disponibile')) ?></strong>
      <div style="display:flex;gap:8px;margin-top:8px"><input type="email" name="email" required placeholder="<?= e(__('La tua email')) ?>" aria-label="Email"><div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div><button class="btn btn-sm" type="submit"><?= e(__('Avvisami')) ?></button></div><span id="notify-msg" role="status"></span></form>
    <?php endif ?>
    <?php if (Alien\Services\Modules::on('wishlist')): ?><button type="button" class="btn btn-outline btn-sm wish-btn" data-wish="<?= (int)$p['id'] ?>" data-label-on="<?= e(__('Salvato nei preferiti')) ?>" data-label-off="<?= e(__('Aggiungi ai preferiti')) ?>" aria-pressed="false">♡ <?= e(__('Aggiungi ai preferiti')) ?></button><?php endif ?>
    <div class="meta-list">
      <?php if ($p['sku'] !== ''): ?><div><?= e(__('SKU')) ?>: <?= e($p['sku']) ?></div><?php endif ?>
      <?php if ($p['categories']): ?><div><?= e(__('Categorie')) ?>: <?php foreach ($p['categories'] as $i => $c): ?><?= $i ? ', ' : '' ?><a href="<?= e(url('collections/' . $c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach ?></div><?php endif ?>
    </div>
  </div>
</div>
<?php if ($p['description']): ?><section class="description prose"><h2><?= e(__('Descrizione')) ?></h2><?= $p['description'] ?></section><?php endif ?>
<?php if (Alien\Services\Modules::on('reviews')): ?>
<section id="reviews" class="description"><h2><?= e(__('Recensioni')) ?><?php if ($p['rating_count']): ?> <span class="stars"><span style="--r:<?= (int)$p['rating_avg'] / 50 * 100 ?>%">★★★★★</span> <small><?= e(number_format($p['rating_avg'] / 10, 1)) ?>/5 · <?= (int)$p['rating_count'] ?></small></span><?php endif ?></h2>
  <?php if (isset($_GET['reviewed'])): ?><div class="alert alert-<?= $_GET['reviewed'] === 'ok' ? 'success' : 'error' ?>"><?= e(($_GET['reviewed'] ?? '') === 'ok' ? ((string)setting('reviews_auto', '0') === '1' ? __('Grazie! La tua recensione è stata pubblicata.') : __('Grazie! La tua recensione sarà pubblicata dopo la moderazione.')) : __('Non è stato possibile inviare la recensione: controlla i campi e riprova.')) ?></div><?php endif ?>
  <?php foreach ($reviews as $rv): ?><article class="review"><div class="stars"><span style="--r:<?= (int)$rv['rating'] * 20 ?>%">★★★★★</span></div><strong><?= e($rv['title']) ?></strong>
    <p><?= nl2br(e($rv['body'])) ?></p><small><?= e($rv['author']) ?> · <?= e(date('d/m/Y', strtotime($rv['created_at']))) ?><?= $rv['verified'] ? ' · ✓ ' . e(__('Acquisto verificato')) : '' ?></small></article><?php endforeach ?>
  <?php if (!$reviews): ?><p class="muted"><?= e(__('Ancora nessuna recensione: scrivi la prima!')) ?></p><?php endif ?>
  <details class="review-form"><summary class="btn btn-outline btn-sm"><?= e(__('Scrivi una recensione')) ?></summary>
    <form id="review-form" method="post" action="<?= e(url('products/' . $p['slug'] . '/reviews')) ?>" style="margin-top:14px;max-width:520px">
      <div class="field"><label><?= e(__('Il tuo voto')) ?></label><div class="rate"><?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" id="rt<?= $i ?>" name="rating" value="<?= $i ?>" required><label for="rt<?= $i ?>" title="<?= $i ?>">★</label><?php endfor ?></div></div>
      <div class="row"><div class="field"><label for="rv-author"><?= e(__('Nome')) ?></label><input id="rv-author" name="author" required maxlength="120"></div><div class="field"><label for="rv-email"><?= e(__('Email (non pubblicata)')) ?></label><input id="rv-email" type="email" name="email"></div></div>
      <div class="field"><label for="rv-title"><?= e(__('Titolo')) ?></label><input id="rv-title" name="title" maxlength="190"></div>
      <div class="field"><label for="rv-body"><?= e(__('La tua recensione')) ?></label><textarea id="rv-body" name="body" rows="4" required minlength="10"></textarea></div>
      <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
      <button class="btn" type="submit"><?= e(__('Invia recensione')) ?></button><span id="review-msg" role="status" style="margin-left:10px"></span></form></details></section>
<?php endif ?>
<?php if ($related): ?><section class="section" style="margin-top:56px"><div class="section-head"><h2><?= e(__('Potrebbero interessarti')) ?></h2></div>
  <div class="grid"><?php foreach ($related as $r): ?><?= partial('product-card', ['p' => $r]) ?><?php endforeach ?></div></section><?php endif ?>
<script type="application/json" id="product-data"><?= json_encode($data, json_flags()) ?></script>
