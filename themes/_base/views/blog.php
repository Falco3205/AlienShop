<h1><?= e(__('Blog')) ?></h1>
<div class="post-list">
<?php foreach ($posts as $p): ?>
  <article class="card c-border"><a class="card-link" href="<?= e(url('blog/' . $p['slug'])) ?>">
    <?php if ($p['image']): ?><div class="card-media"><img src="<?= e(upload_url($p['image'], 400)) ?>" alt="" width="400" height="400" loading="lazy"></div><?php endif ?>
    <div class="card-body"><h3 class="card-title"><?= e($p['title']) ?></h3><p><?= e(Alien\Core\Str::excerpt($p['excerpt'] ?: $p['content'], 140)) ?></p></div></a></article>
<?php endforeach ?>
</div>
<?php if (!$posts): ?><p class="empty"><?= e(__('Nessun articolo.')) ?></p><?php endif ?>
