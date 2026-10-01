<div class="toolbar">
  <form method="get" action="<?= e(url('admin/products')) ?>">
    <input type="search" name="q" placeholder="<?= e(__('Cerca per nome, SKU, tag…')) ?>" value="<?= e($filters['q']) ?>">
    <select name="status"><option value=""><?= e(__('Tutti gli stati')) ?></option><option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>><?= e(__('Attivi')) ?></option><option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>><?= e(__('Bozze')) ?></option></select>
    <select name="stock"><option value=""><?= e(__('Tutte le scorte')) ?></option><option value="low" <?= ($_GET['stock'] ?? '') === 'low' ? 'selected' : '' ?>><?= e(__('In esaurimento')) ?></option><option value="out" <?= ($_GET['stock'] ?? '') === 'out' ? 'selected' : '' ?>><?= e(__('Esauriti')) ?></option></select>
    <select name="category"><option value="0"><?= e(__('Tutte le categorie')) ?></option><?php foreach ($categories as $id => $name): ?><option value="<?= $id ?>" <?= (int)$filters['category_id'] === $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach ?></select>
    <button class="btn sec" type="submit"><?= e(__('Filtra')) ?></button>
  </form>
</div>
<form method="post" action="<?= e(url('admin/products-bulk')) ?>" class="card" style="padding:0" data-confirm="<?= e(__('Confermi l\'operazione?')) ?>">
  <?= csrf_field() ?>
  <table>
    <thead><tr><th><input type="checkbox" data-toggle-all aria-label="all"></th><th></th><th><?= e(__('Prodotto')) ?></th><th><?= e(__('Stato')) ?></th><th><?= e(__('Magazzino')) ?></th><th><?= e(__('Prezzo')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($result['items'] as $p): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>"></td>
        <td><img class="thumb" src="<?= e(upload_url($p['image'], 400)) ?>" alt="" width="42" height="42" loading="lazy"></td>
        <td><a href="<?= e(url('admin/products/' . $p['id'])) ?>"><strong><?= e($p['name']) ?></strong></a><div class="muted"><?= e($p['sku']) ?> <?= $p['type'] === 'variable' ? '· ' . e(__('Variabile')) : '' ?></div></td>
        <td><?= a_status($p['status']) ?></td>
        <td><?= $p['manage_stock'] ? ($p['in_stock'] ? (int)$p['stock_qty'] : '<span class="pill bad">' . e(__('Esaurito')) . '</span>') : '<span class="muted">—</span>' ?></td>
        <td class="nowrap"><?= (int)$p['price_min'] !== (int)$p['price_max'] ? e(money($p['price_min'])) . ' – ' . e(money($p['price_max'])) : e(money($p['price_min'])) ?></td>
      </tr>
    <?php endforeach ?>
    <?php if (!$result['items']): ?><tr><td colspan="6"><?= a_empty('📦', __('Nessun prodotto'), __('Aggiungi il primo prodotto o importa il tuo catalogo da WooCommerce / Shopify.'), 'admin/products/new', __('Aggiungi prodotto')) ?></td></tr><?php endif ?>
    </tbody>
  </table>
  <div class="toolbar" style="padding:14px"><select name="action"><option value="publish"><?= e(__('Pubblica')) ?></option><option value="draft"><?= e(__('Metti in bozza')) ?></option><option value="delete"><?= e(__('Elimina')) ?></option></select><button class="btn sec sm" type="submit"><?= e(__('Applica ai selezionati')) ?></button>
    <span class="muted" style="margin-left:auto"><?= e(__('%d prodotti', $result['total'])) ?></span></div>
</form>
<?php if ($result['pages'] > 1): $qs = $_GET; ?><div class="pager"><?php for ($i = 1; $i <= $result['pages']; $i++): $qs['page'] = $i; ?><?= $i === $result['page'] ? '<span class="cur">' . $i . '</span>' : '<a href="' . e(url('admin/products?' . http_build_query($qs))) . '">' . $i . '</a>' ?><?php endfor ?></div><?php endif ?>
