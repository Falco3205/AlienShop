<nav class="breadcrumbs" aria-label="breadcrumb">
<?php foreach ($trail as $i => [$name, $link]): ?>
  <span><?php if ($i < count($trail) - 1): ?><a href="<?= e($link) ?>"><?= e($name) ?></a><?php else: ?><?= e($name) ?><?php endif ?></span>
<?php endforeach ?>
</nav>
