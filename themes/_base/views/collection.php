<?= partial('breadcrumbs', ['trail' => $trail]) ?>
<h1><?= e($title) ?></h1>
<?php if (!empty($description)): ?><div class="prose"><?= $description ?></div><?php endif ?>
<div class="layout-sidebar" style="margin-top:24px">
  <aside class="sidebar">
    <h3><?= e(__('Categorie')) ?></h3>
    <ul>
      <li><a href="<?= e(url('collections/all')) ?>" class="<?= $slug === 'all' ? 'active' : '' ?>"><?= e(__('Tutti i prodotti')) ?></a></li>
      <?php foreach ($tree as $c): ?>
        <li><a href="<?= e(url('collections/' . $c['slug'])) ?>" class="<?= $slug === $c['slug'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
        <?php if ($c['children']): ?><ul style="margin:4px 0 0 14px"><?php foreach ($c['children'] as $s): ?><li><a href="<?= e(url('collections/' . $s['slug'])) ?>" class="<?= $slug === $s['slug'] ? 'active' : '' ?>"><?= e($s['name']) ?></a></li><?php endforeach ?></ul><?php endif ?></li>
      <?php endforeach ?>
    </ul>
    <?php foreach ($facets as $facetName => $values): ?>
      <h3><?= e($facetName) ?></h3><ul>
      <?php foreach ($values as $fv): $q = $_GET; unset($q['page']); $on = ($activeAttrs[$facetName] ?? null) === $fv['value'];
          if ($on) { unset($q['attr'][$facetName]); if (empty($q['attr'])) { unset($q['attr']); } } else { $q['attr'][$facetName] = $fv['value']; } ?>
        <li><a href="<?= e($base . ($q ? '?' . http_build_query($q) : '')) ?>" class="<?= $on ? 'active' : '' ?>" rel="nofollow"><?= $on ? '✓ ' : '' ?><?= e($fv['value']) ?> <small class="muted">(<?= (int)$fv['n'] ?>)</small></a></li>
      <?php endforeach ?></ul>
    <?php endforeach ?>
    <form method="get" action="<?= e($base) ?>">
      <?php foreach ($activeAttrs as $an => $av): ?><input type="hidden" name="attr[<?= e($an) ?>]" value="<?= e($av) ?>"><?php endforeach ?>
      <h3><?= e(__('Prezzo')) ?></h3>
      <div class="row"><input type="number" name="min" min="0" step="1" placeholder="Min" value="<?= e($_GET['min'] ?? '') ?>" aria-label="Min"><input type="number" name="max" min="0" step="1" placeholder="Max" value="<?= e($_GET['max'] ?? '') ?>" aria-label="Max"></div>
      <?php if (!empty($_GET['sort'])): ?><input type="hidden" name="sort" value="<?= e($_GET['sort']) ?>"><?php endif ?>
      <button class="btn btn-outline btn-sm btn-block" style="margin-top:10px" type="submit"><?= e(__('Filtra')) ?></button>
    </form>
  </aside>
  <section>
    <div class="toolbar"><span><?= e(__('%d prodotti', $result['total'])) ?></span>
      <form method="get" action="<?= e($base) ?>"><?php foreach (['min', 'max'] as $k): if (!empty($_GET[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= e($_GET[$k]) ?>"><?php endif; endforeach ?>
        <select name="sort" data-autosubmit aria-label="<?= e(__('Ordina')) ?>">
          <?php foreach (['new' => __('Novità'), 'price_asc' => __('Prezzo crescente'), 'price_desc' => __('Prezzo decrescente'), 'name' => __('Nome')] as $k => $l): ?>
          <option value="<?= $k ?>" <?= ($_GET['sort'] ?? 'new') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?>
        </select><noscript><button class="btn btn-sm" type="submit">OK</button></noscript></form></div>
    <?php if ($result['items']): ?>
      <div class="grid"><?php foreach ($result['items'] as $i => $p): ?><?= partial('product-card', ['p' => $p, 'eager' => $i < 4]) ?><?php endforeach ?></div>
      <?= partial('pagination', ['pages' => $result['pages'], 'page' => $result['page'], 'base' => $base]) ?>
    <?php else: ?><p class="empty"><?= e(__('Nessun prodotto trovato.')) ?></p><?php endif ?>
  </section>
</div>
