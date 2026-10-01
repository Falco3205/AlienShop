<?php if ($pages > 1):
    $q = $_GET;
    unset($q['page']);
    $link = static fn(int $n) => rtrim($base . '?' . http_build_query($q + ($n > 1 ? ['page' => $n] : [])), '?');
    $window = array_unique(array_filter(array_merge([1, $pages], range(max(1, $page - 2), min($pages, $page + 2))), static fn($n) => $n >= 1 && $n <= $pages));
    sort($window);
?>
<nav class="pagination" aria-label="<?= e(__('Pagine')) ?>">
  <?php $prev = 0; foreach ($window as $n): if ($prev && $n - $prev > 1): ?><span>…</span><?php endif; $prev = $n ?>
    <?php if ($n === $page): ?><span class="current" aria-current="page"><?= $n ?></span><?php else: ?><a href="<?= e($link($n)) ?>"><?= $n ?></a><?php endif ?>
  <?php endforeach ?>
</nav>
<?php endif ?>
