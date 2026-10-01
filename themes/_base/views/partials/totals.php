<dl>
  <div><dt><?= e(__('Subtotale')) ?></dt><dd><?= e(money($totals['subtotal'])) ?></dd></div>
  <?php if ($totals['discount']): ?><div><dt><?= e(__('Sconto')) ?> <?= e($totals['coupon']['code'] ?? '') ?></dt><dd>-<?= e(money($totals['discount'])) ?></dd></div><?php endif ?>
  <div><dt><?= e(__('Spedizione')) ?></dt><dd><?= $totals['shipping_method'] ? ($totals['shipping'] ? e(money($totals['shipping'])) : e(__('Gratuita'))) : '—' ?></dd></div>
  <?php if ($totals['tax']): ?><div><dt><?= $totals['tax_included'] ? e(__('di cui IVA')) : e(__('IVA')) ?> (<?= e(rtrim(rtrim(number_format($totals['tax_rate'], 2, ',', ''), '0'), ',')) ?>%)</dt><dd><?= e(money($totals['tax'])) ?></dd></div><?php endif ?>
  <div class="total"><dt><?= e(__('Totale')) ?></dt><dd><?= e(money($totals['total'])) ?></dd></div>
</dl>
