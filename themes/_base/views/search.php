<h1><?= e($q !== '' ? __('Risultati per "%s"', $q) : __('Cerca')) ?></h1>
<form action="<?= e(url('search')) ?>" method="get" class="toolbar" role="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('Cerca prodotti…')) ?>" style="max-width:420px"><button class="btn" type="submit"><?= e(__('Cerca')) ?></button></form>
<?php if ($q !== ''): ?>
  <?php if ($result['items']): ?>
    <div class="grid"><?php foreach ($result['items'] as $p): ?><?= partial('product-card', ['p' => $p]) ?><?php endforeach ?></div>
    <?= partial('pagination', ['pages' => $result['pages'], 'page' => $result['page'], 'base' => url('search')]) ?>
  <?php else: ?><p class="empty"><?= e(__('Nessun risultato.')) ?></p><?php endif ?>
<?php endif ?>
