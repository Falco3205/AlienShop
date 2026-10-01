<?= partial('breadcrumbs', ['trail' => $trail]) ?>
<article class="prose">
  <h1><?= e($page['title']) ?></h1>
  <?php if ($page['type'] === 'post'): ?><p class="breadcrumbs"><time datetime="<?= e(substr($page['created_at'], 0, 10)) ?>"><?= e(date('d/m/Y', strtotime($page['created_at']))) ?></time></p><?php endif ?>
  <?php if ($page['image']): ?><img src="<?= e(upload_url($page['image'], 800)) ?>" alt="" width="800" height="450" fetchpriority="high"><?php endif ?>
  <?= $page['content'] ?>
</article>
