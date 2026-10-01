<?php $errors = $_SESSION['import_errors'] ?? []; unset($_SESSION['import_errors']); ?>
<?php if ($errors): ?><div class="alert error"><strong><?= e(__('Avvisi durante l\'import')) ?></strong><ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul></div><?php endif ?>
<div class="grid2"><div>
<div class="card"><h2><?= e(__('Importa prodotti')) ?></h2>
<p class="muted"><?= e(__('Carica il file CSV esportato da WooCommerce (Prodotti → Esporta) o da Shopify (Prodotti → Esporta). Categorie, attributi, varianti, prezzi, scorte, SEO e immagini vengono importati; gli URL Shopify (/products/handle) restano identici e quelli WooCommerce (/product/slug) vengono reindirizzati con 301.')) ?></p>
<form method="post" enctype="multipart/form-data" action="<?= e(url('admin/import')) ?>"><?= csrf_field() ?>
<?= a_select('format', __('Piattaforma di origine'), ['woocommerce' => 'WooCommerce', 'shopify' => 'Shopify']) ?>
<div class="field"><label for="csv"><?= e(__('File CSV')) ?></label><input id="csv" type="file" name="csv" accept=".csv,text/csv" required></div>
<label class="check"><input type="checkbox" name="update_existing" value="1" checked> <?= e(__('Aggiorna i prodotti già presenti (stesso SKU / ID / handle)')) ?></label>
<label class="check" style="margin:10px 0 18px"><input type="checkbox" name="download_images" value="1" checked> <?= e(__('Scarica e ottimizza le immagini dagli URL (più lento)')) ?></label>
<button class="btn" type="submit"><?= e(__('Avvia importazione')) ?></button></form>
<p class="help" style="margin-top:14px"><?= e(__('Per cataloghi molto grandi usa la riga di comando:')) ?> <code>php bin/console import:woocommerce file.csv</code></p></div>
</div><div>
<div class="card"><h2><?= e(__('Esporta prodotti')) ?></h2>
<p class="muted"><?= e(__('Scarica il catalogo in formato compatibile per migrare verso WooCommerce o Shopify.')) ?></p>
<p><a class="btn sec" href="<?= e(url('admin/export/woocommerce')) ?>">⬇ WooCommerce CSV</a></p>
<p><a class="btn sec" href="<?= e(url('admin/export/shopify')) ?>">⬇ Shopify CSV</a></p></div>
</div></div>
