<h1><?= e(__('Carrello')) ?></h1>
<?php if (!$lines): ?>
  <div class="empty"><p><?= e(__('Il tuo carrello è vuoto.')) ?></p><a class="btn" href="<?= e(url('collections/all')) ?>"><?= e(__('Continua lo shopping')) ?></a></div>
<?php else: ?>
<div class="layout-checkout">
  <form action="<?= e(url('cart/update')) ?>" method="post">
    <table class="cart"><thead><tr><th><?= e(__('Prodotto')) ?></th><th><?= e(__('Prezzo')) ?></th><th><?= e(__('Quantità')) ?></th><th><?= e(__('Totale')) ?></th><th></th></tr></thead><tbody>
    <?php foreach ($lines as $l): ?>
      <tr>
        <td><div class="cart-item"><img src="<?= e(upload_url($l['image'], 400)) ?>" alt="" width="72" height="72" loading="lazy"><div><a href="<?= e(url($l['url'])) ?>"><strong><?= e($l['name']) ?></strong></a><?php if ($l['label']): ?><small><?= e($l['label']) ?></small><?php endif ?></div></div></td>
        <td><?= e(money($l['unit'])) ?></td>
        <td><input type="number" name="qty[<?= e($l['key']) ?>]" value="<?= (int)$l['qty'] ?>" min="0" max="99" style="width:80px" aria-label="<?= e(__('Quantità')) ?>"></td>
        <td><strong><?= e(money($l['total'])) ?></strong></td>
        <td><button class="btn btn-outline btn-sm" name="remove" value="<?= e($l['key']) ?>" aria-label="<?= e(__('Rimuovi')) ?>">✕</button></td>
      </tr>
    <?php endforeach ?></tbody></table>
    <p style="margin-top:16px"><button class="btn btn-outline" type="submit"><?= e(__('Aggiorna carrello')) ?></button></p>
  </form>
  <aside class="summary">
    <form action="<?= e(url('cart/coupon')) ?>" method="post" class="field" style="display:flex;gap:8px">
      <input name="code" placeholder="<?= e(__('Codice sconto')) ?>" value="<?= e($totals['coupon']['code'] ?? '') ?>" aria-label="<?= e(__('Codice sconto')) ?>"><button class="btn btn-outline" type="submit"><?= e(__('Applica')) ?></button>
    </form>
    <?= partial('totals', ['totals' => $totals]) ?>
    <a class="btn btn-block" href="<?= e(url('checkout')) ?>" style="margin-top:16px"><?= e(__('Procedi al pagamento')) ?></a>
  </aside>
</div>
<?php endif ?>
