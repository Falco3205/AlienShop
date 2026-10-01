<h1><?= e(__('I miei preferiti')) ?></h1>
<div id="wish-grid" class="grid" data-url="<?= e(url('wishlist/cards')) ?>"></div>
<p id="wish-empty" class="empty" hidden><?= e(__('Non hai ancora salvato nessun prodotto.')) ?> <a href="<?= e(url('collections/all')) ?>"><?= e(__('Scopri i prodotti')) ?></a></p>
