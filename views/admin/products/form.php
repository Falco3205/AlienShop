<?php
$p = $p ?? [];
$isNew = empty($p['id']);
$v = static fn(string $k, mixed $d = '') => $p[$k] ?? $d;
$selectedCats = array_map(static fn($c) => (int)$c['id'], $p['categories'] ?? []);
$attrs = $p['attributes'] ?? [];
$tpl = static function (string $i, string $name = '', string $values = ''): string {
    return '<div style="display:flex;justify-content:space-between;gap:10px"><div style="flex:1">' . a_input('attributes[' . $i . '][name]', __('Nome attributo (es. Taglia, Colore)'), $name)
        . '</div><button type="button" class="btn danger sm" data-remove-attr style="align-self:flex-start;margin-top:22px">✕</button></div>'
        . a_textarea('attributes[' . $i . '][values]', __('Valori, uno per riga. Variazione di prezzo opzionale dopo "|"'), $values, 4, __('Esempio: <code>XL|+2.00</code> aggiunge 2,00 al prezzo base; <code>S|-1</code> lo riduce.'));
};
?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('admin/products/' . ($isNew ? 'new' : $p['id']))) ?>">
<?= csrf_field() ?>
<div class="grid2">
<div>
  <div class="card"><h2><?= e(__('Informazioni')) ?></h2>
    <?= a_input('name', __('Nome'), $v('name'), 'text', ['required' => true]) ?>
    <?= a_textarea('short_description', __('Descrizione breve'), $v('short_description'), 3) ?>
    <?= a_textarea('description', __('Descrizione completa (HTML)'), $v('description'), 10, '', true) ?>
  </div>

  <div class="card"><h2><?= e(__('Immagini')) ?></h2>
    <?php if (!empty($p['images'])): ?><div class="imgs"><?php foreach ($p['images'] as $im): ?><figure><img src="<?= e(upload_url($im['path'], 400)) ?>" alt=""><label title="<?= e(__('Rimuovi')) ?>" style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.65);color:#fff;border-radius:6px;padding:2px 6px;font-size:.75rem;cursor:pointer;margin:0"><input type="checkbox" name="remove_image[]" value="<?= (int)$im['id'] ?>"> ✕</label><label style="display:block;text-align:center;font-size:.72rem;font-weight:500"><input type="radio" name="main_image" value="<?= (int)$im['id'] ?>"> <?= e(__('Principale')) ?></label></figure><?php endforeach ?></div><div class="help" style="margin-bottom:12px"><?= e(__('La prima immagine è quella principale. Spunta ✕ per rimuovere.')) ?></div><?php endif ?>
    <div class="field"><label for="images"><?= e(__('Carica immagini')) ?></label><input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple><div class="help"><?= e(__('Vengono ottimizzate e convertite automaticamente in WebP con più dimensioni.')) ?></div></div>
    <?= a_textarea('image_urls', __('Oppure importa da URL (uno per riga)'), '', 2) ?>
  </div>

  <div class="card"><h2><?= e(__('Prezzo')) ?></h2>
    <div class="row">
      <?= a_input('price', __('Prezzo') . ' (' . Alien\Core\Money::symbol() . ')', Alien\Core\Money::input((int)$v('price', 0)), 'text', ['inputmode' => 'decimal'], '<span data-only-variable>' . e(__('Prezzo base: le variazioni degli attributi vengono sommate a questo valore.')) . '</span>') ?>
      <div data-only-simple><?= a_input('compare_price', __('Prezzo di listino barrato (opzionale)'), (int)$v('compare_price', 0) ? Alien\Core\Money::input((int)$v('compare_price')) : '', 'text', ['inputmode' => 'decimal']) ?></div>
    </div>
  </div>

  <div class="card" data-only-variable><h2><?= e(__('Attributi e variazioni di prezzo')) ?></h2>
    <p class="muted"><?= e(__('Definisci gli attributi (Taglia, Colore, Materiale…). Le varianti vengono generate automaticamente da tutte le combinazioni; il prezzo di ogni variante è il prezzo base più le variazioni dei valori scelti, sovrascrivibile nella tabella sotto.')) ?></p>
    <div id="attrs">
      <?php foreach ($attrs as $i => $a): ?><div class="attr-box"><?= $tpl((string)$i, $a['name'], Alien\Services\Catalog::formatAttributeLines($a['values'])) ?></div><?php endforeach ?>
    </div>
    <button type="button" class="btn sec sm" id="add-attr">+ <?= e(__('Aggiungi attributo')) ?></button>
    <template id="attr-tpl"><?= $tpl('__I__') ?></template>

    <?php if (!empty($p['variants'])): ?>
      <h2 style="margin-top:24px"><?= e(__('Varianti')) ?> (<?= count($p['variants']) ?>)</h2>
      <div class="bulkbar"><strong><?= e(__('Applica a tutte:')) ?></strong>
        <input type="number" id="bulk-stock" placeholder="<?= e(__('Scorta')) ?>"><button type="button" class="btn sec sm" data-bulk="stock"><?= e(__('Imposta scorta')) ?></button>
        <input type="text" id="bulk-price" placeholder="<?= e(__('Prezzo')) ?>" inputmode="decimal"><button type="button" class="btn sec sm" data-bulk="price"><?= e(__('Imposta prezzo')) ?></button>
        <button type="button" class="btn sec sm" data-bulk="clear"><?= e(__('Azzera prezzi personalizzati')) ?></button></div>
      <table><thead><tr><th><?= e(__('Variante')) ?></th><th>SKU</th><th><?= e(__('Prezzo (override)')) ?></th><th><?= e(__('Listino')) ?></th><th><?= e(__('Stock')) ?></th><th><?= e(__('Attiva')) ?></th></tr></thead><tbody>
      <?php foreach ($p['variants'] as $i => $var): ?>
        <tr>
          <td><input type="hidden" name="variants[<?= $i ?>][key]" value="<?= e($var['options_key']) ?>"><strong><?= e(implode(' / ', $var['options'])) ?></strong><div class="muted"><?= e(__('Calcolato')) ?>: <?= e(money($var['final_price'])) ?></div></td>
          <td><input name="variants[<?= $i ?>][sku]" value="<?= e($var['sku']) ?>" style="min-width:90px"></td>
          <td><input name="variants[<?= $i ?>][price]" value="<?= $var['price'] === null ? '' : e(Alien\Core\Money::input((int)$var['price'])) ?>" placeholder="<?= e(Alien\Core\Money::input((int)$var['final_price'])) ?>" style="width:100px"></td>
          <td><input name="variants[<?= $i ?>][compare_price]" value="<?= $var['compare_price'] === null ? '' : e(Alien\Core\Money::input((int)$var['compare_price'])) ?>" style="width:90px"></td>
          <td><input type="number" name="variants[<?= $i ?>][stock]" value="<?= (int)$var['stock'] ?>" style="width:80px"></td>
          <td><input type="checkbox" name="variants[<?= $i ?>][active]" value="1" <?= $var['active'] ? 'checked' : '' ?>></td>
        </tr>
      <?php endforeach ?></tbody></table>
    <?php endif ?>
  </div>

  <div class="card"><h2><?= e(__('SEO (Google, Bing)')) ?></h2>
    <div class="gpreview" id="gpreview" data-base="<?= e(url('products/')) ?>"><div class="u"></div><div class="t"></div><div class="d"></div></div>
    <?= a_input('slug', __('URL (slug)'), $v('slug'), 'text', ['data-slug-from' => 'f_name'], e(url('products/')) . '<strong>' . e($v('slug', '…')) . '</strong> — ' . e(__('se lo cambi, viene creato automaticamente un redirect 301 dal vecchio indirizzo.')) ) ?>
    <?= a_input('seo_title', __('Titolo SEO'), $v('seo_title'), 'text', ['maxlength' => 190], __('Consigliati max 60 caratteri. Vuoto = nome prodotto.')) ?>
    <?= a_textarea('seo_description', __('Meta description'), $v('seo_description'), 2, __('Consigliati max 160 caratteri. Vuoto = generata dalla descrizione.')) ?>
    <?= a_check('noindex', __('Nascondi ai motori di ricerca (noindex)'), $v('noindex')) ?>
  </div>
</div>

<div>
  <div class="card"><h2><?= e(__('Pubblicazione')) ?></h2>
    <?= a_select('status', __('Stato'), ['active' => __('Attivo'), 'draft' => __('Bozza')], $v('status', 'active')) ?>
    <div class="field"><label><?= e(__('Tipo di prodotto')) ?></label><div class="type-cards">
      <label><input type="radio" name="type" value="simple" <?= $v('type', 'simple') === 'simple' ? 'checked' : '' ?>>📦 <?= e(__('Semplice')) ?><small><?= e(__('Un solo prezzo e una sola scorta.')) ?></small></label>
      <label><input type="radio" name="type" value="variable" <?= $v('type', 'simple') === 'variable' ? 'checked' : '' ?>>🎛️ <?= e(__('Con varianti')) ?><small><?= e(__('Taglie, colori, materiali… con prezzi diversi.')) ?></small></label></div></div>
    <?= a_check('featured', __('In evidenza nella home'), $v('featured')) ?>
    <?php if (!$isNew): ?><p><a href="<?= e(url('products/' . $p['slug'])) ?>" target="_blank" rel="noopener">↗ <?= e(__('Vedi nel negozio')) ?></a></p><?php endif ?>
  </div>
  <div class="card"><h2><?= e(__('Organizzazione')) ?></h2>
    <div class="field"><label><?= e(__('Categorie')) ?></label>
      <?php foreach ($categories as $cid => $name): ?><label class="check"><input type="checkbox" name="category_ids[]" value="<?= $cid ?>" <?= in_array($cid, $selectedCats, true) ? 'checked' : '' ?>> <?= e($name) ?></label><?php endforeach ?>
      <?php if (!$categories): ?><div class="help"><a href="<?= e(url('admin/categories/new')) ?>"><?= e(__('Crea la prima categoria')) ?></a></div><?php endif ?></div>
    <?= a_input('vendor', __('Marca / Fornitore'), $v('vendor')) ?>
    <?= a_input('tags', __('Tag (separati da virgola)'), $v('tags')) ?>
  </div>
  <div class="card"><h2><?= e(__('Magazzino')) ?></h2>
    <?= a_input('sku', 'SKU', $v('sku')) ?>
    <?= a_check('manage_stock', __('Gestisci le scorte'), $v('manage_stock')) ?>
    <div data-only-simple><?= a_input('stock_qty', __('Quantità disponibile'), $v('stock_qty', 0), 'number') ?></div>
    <div data-only-variable class="help"><?= e(__('Per i prodotti variabili la quantità si imposta su ogni variante.')) ?></div>
    <?= a_input('weight', __('Peso (grammi)'), $v('weight', 0), 'number') ?>
  </div>
  <?php if (!$isNew): ?>
  <div class="card"><h2><?= e(__('Zona pericolosa')) ?></h2>
    <button class="btn sec" type="submit" form="duplicate-form"><?= e(__('Duplica')) ?></button>
    <button class="btn danger" type="submit" form="delete-form"><?= e(__('Elimina prodotto')) ?></button></div>
  <?php endif ?>
</div>
</div>
<div class="sticky-save"><button class="btn" type="submit"><?= e(__('Salva prodotto')) ?></button><a class="btn sec" href="<?= e(url('admin/products')) ?>"><?= e(__('Annulla')) ?></a></div>
</form>
<?php if (!$isNew): ?><form id="duplicate-form" method="post" action="<?= e(url('admin/products/' . $p['id'] . '/duplicate')) ?>"><?= csrf_field() ?></form>
<form id="delete-form" method="post" action="<?= e(url('admin/products/' . $p['id'] . '/delete')) ?>" data-confirm="<?= e(__('Eliminare definitivamente questo prodotto?')) ?>"><?= csrf_field() ?></form><?php endif ?>
