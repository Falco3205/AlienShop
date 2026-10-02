<?php
$heroImg = setting('hero_image');
$heroStyle = $heroImg ? ' style="background-image:url(' . e(upload_url($heroImg)) . ')"' : '';
?>
<section class="hero"<?= $heroStyle ?>>
  <div class="hero-art" aria-hidden="true"><i></i><i></i><i></i></div>
  <div class="container hero-inner">
    <div>
      <h1><?= e(setting('hero_title', setting('store_name'))) ?></h1>
      <?php if ($sub = setting('hero_subtitle', setting('store_description', ''))): ?><p><?= e($sub) ?></p><?php endif ?>
      <a class="btn" href="<?= e(url(setting('hero_link', 'collections/all'))) ?>"><?= e(setting('hero_cta', __('Scopri i prodotti'))) ?></a>
    </div>
    <?php if ($heroImg): ?><div class="hero-media"><img src="<?= e(upload_url($heroImg, 800)) ?>" alt="" width="800" height="600" fetchpriority="high"></div><?php endif ?>
  </div>
</section>
<?php if ($categories): ?>
<section class="section"><div class="section-head"><h2><?= e(__('Categorie')) ?></h2></div>
  <div class="cat-grid">
  <?php foreach ($categories as $c): ?>
    <a class="cat-tile" href="<?= e(url('collections/' . $c['slug'])) ?>">
      <?php if ($c['image']): ?><img src="<?= e(upload_url($c['image'], 400)) ?>" alt="" width="400" height="300" loading="lazy"><?php endif ?>
      <span><?= e($c['name']) ?></span></a>
  <?php endforeach ?></div></section>
<?php endif ?>
<?php if ($featured): ?>
<section class="section"><div class="section-head"><h2><?= e(__('In evidenza')) ?></h2></div>
  <div class="grid<?= count($featured) < 4 ? ' grid-few' : '' ?>" style="--cols:<?= max(2, min(4, count($featured))) ?>"><?php foreach ($featured as $i => $p): ?><?= partial('product-card', ['p' => $p, 'eager' => $i < 2]) ?><?php endforeach ?></div></section>
<?php endif ?>
<section class="section"><div class="section-head"><h2><?= e(__('Ultimi arrivi')) ?></h2><a href="<?= e(url('collections/all')) ?>"><?= e(__('Vedi tutti')) ?> →</a></div>
  <?php if ($latest): ?><div class="grid"><?php foreach ($latest as $p): ?><?= partial('product-card', ['p' => $p]) ?><?php endforeach ?></div>
  <?php else: ?><p class="empty"><?= e(__('Nessun prodotto ancora.')) ?></p><?php endif ?></section>
