<?php $cur = $o['currency']; $f = static fn($c) => Alien\Core\Money::format((int)$c, $cur); $s = $o['shipping_address']; ?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($o['number']) ?></title>
<style>body{font:14px/1.5 Arial,sans-serif;max-width:780px;margin:30px auto;color:#111}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #ddd;text-align:left}.r{text-align:right}h1{margin:0}@media print{button{display:none}}</style></head>
<body><button id="print-btn"><?= e(__('Stampa')) ?></button>
<h1><?= e(setting('store_name', '')) ?></h1><p><strong><?= e(__('Ordine')) ?> <?= e($o['number']) ?></strong> — <?= e(a_dt($o['created_at'])) ?></p>
<p><strong><?= e(__('Spedizione a')) ?></strong><br><?= e($s['name'] ?? '') ?><br><?= e($s['address'] ?? '') ?><br><?= e(($s['zip'] ?? '') . ' ' . ($s['city'] ?? '') . ' ' . ($s['state'] ?? '')) ?> <?= e($s['country'] ?? '') ?><br><?= e($s['phone'] ?? '') ?></p>
<table><thead><tr><th><?= e(__('Prodotto')) ?></th><th>SKU</th><th class="r"><?= e(__('Quantità')) ?></th><th class="r"><?= e(__('Totale')) ?></th></tr></thead><tbody>
<?php foreach ($o['items'] as $it): ?><tr><td><?= e($it['name']) ?><?= $it['variant_label'] ? ' (' . e($it['variant_label']) . ')' : '' ?></td><td><?= e($it['sku']) ?></td><td class="r"><?= (int)$it['qty'] ?></td><td class="r"><?= e($f($it['total'])) ?></td></tr><?php endforeach ?>
<tr><td colspan="3" class="r"><?= e(__('Spedizione')) ?></td><td class="r"><?= e($f($o['shipping'])) ?></td></tr>
<tr><td colspan="3" class="r"><strong><?= e(__('Totale')) ?></strong></td><td class="r"><strong><?= e($f($o['total'])) ?></strong></td></tr></tbody></table>
<?php if ($o['note']): ?><p><strong><?= e(__('Note')) ?>:</strong> <?= e($o['note']) ?></p><?php endif ?><script nonce="<?= e(csp_nonce()) ?>">document.getElementById('print-btn').addEventListener('click',function(){window.print()});window.print();</script></body></html>
