<?php
$img = $p['image'];
$onSale = (int)$p['compare_price'] > (int)$p['price_min'] && $p['type'] === 'simple';
?>
<article class="card">
  <a class="card-link" href="<?= e(url('products/' . $p['slug'])) ?>">
    <div class="card-media">
      <?php if (!$p['in_stock']): ?><span class="tag out"><?= e(__('Esaurito')) ?></span><?php elseif ($onSale): ?><span class="tag">-<?= (int)round(100 - $p['price_min'] / $p['compare_price'] * 100) ?>%</span><?php endif ?>
      <img src="<?= e(upload_url($img, 400)) ?>" <?php if ($ss = srcset($img)): ?>srcset="<?= e($ss) ?>" sizes="(max-width:760px) 50vw, 25vw"<?php endif ?> alt="<?= e($p['name']) ?>" width="400" height="400" loading="<?= !empty($eager) ? 'eager' : 'lazy' ?>" decoding="async">
    </div>
    <div class="card-body">
      <h3 class="card-title"><?= e($p['name']) ?></h3>
      <div class="price">
        <?php if ((int)$p['price_min'] !== (int)$p['price_max']): ?><span class="from"><?= e(__('Da')) ?></span> <?= e(money($p['price_min'])) ?>
        <?php else: ?><?php if ($onSale): ?><del><?= e(money($p['compare_price'])) ?></del><?php endif ?><?= e(money($p['price_min'])) ?><?php endif ?>
      </div>
    </div>
  </a>
</article>
