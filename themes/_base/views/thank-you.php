<div class="prose" style="margin:0 auto;text-align:center">
  <?php if ($order['payment_status'] === 'paid'): ?>
    <h1>✓ <?= e(__('Grazie per il tuo ordine!')) ?></h1><p><?= e(__('Abbiamo ricevuto il pagamento. Ti abbiamo inviato una email di conferma a %s.', $order['email'])) ?></p>
  <?php elseif ($order['payment_status'] === 'failed'): ?>
    <h1><?= e(__('Pagamento non riuscito')) ?></h1><p><?= e(__('Il pagamento non è andato a buon fine. Puoi riprovare dal checkout.')) ?></p>
  <?php else: ?>
    <h1>✓ <?= e(__('Ordine ricevuto!')) ?></h1><p><?= e(__('Il tuo ordine %s è in attesa di pagamento.', $order['number'])) ?></p>
    <?php if ($gateway && $gateway->description()): ?><div class="alert" style="text-align:left"><?= nl2br(e($gateway->description())) ?></div><?php endif ?>
  <?php endif ?>
  <p><strong><?= e(__('Ordine')) ?> <?= e($order['number']) ?></strong> — <?= e(money($order['total'])) ?></p>
  <table class="cart" style="text-align:left"><tbody>
  <?php foreach ($order['items'] as $it): ?><tr><td><?= e($it['name']) ?><?= $it['variant_label'] ? ' <small>(' . e($it['variant_label']) . ')</small>' : '' ?> × <?= (int)$it['qty'] ?></td><td style="text-align:right"><?= e(money($it['total'])) ?></td></tr><?php endforeach ?>
  </tbody></table>
  <p style="margin-top:24px"><a class="btn" href="<?= e(url('collections/all')) ?>"><?= e(__('Continua lo shopping')) ?></a></p>
</div>
