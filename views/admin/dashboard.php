<?php
$done = count(array_filter($checklist, static fn($c) => $c[0]));
$total = count($checklist);
$delta = static function (int $now, int $prev): string {
    if ($prev === 0) { return ''; }
    $p = (int)round(($now - $prev) / $prev * 100);
    return '<span class="delta ' . ($p >= 0 ? 'up' : 'down') . '">' . ($p >= 0 ? '▲ ' : '▼ ') . abs($p) . '%</span>';
};
$max = max(1, ...array_column($stats['daily'], 't'));
$hour = (int)date('G');
?>
<div class="welcome"><div><h2><?= e($hour < 13 ? __('Buongiorno') : ($hour < 19 ? __('Buon pomeriggio') : __('Buonasera'))) ?>, <?= e($name) ?> 👋</h2>
  <p><?= $todo['to_ship'] ? e(__('Hai %d ordini da spedire.', $todo['to_ship'])) : e(__('Nessun ordine in attesa di spedizione.')) ?></p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" href="<?= e(url('admin/orders?status=processing')) ?>"><?= e(__('Vai agli ordini')) ?></a><a class="btn ghost" href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= e(__('Vedi il negozio')) ?> ↗</a></div></div>

<?php if ($checklist && $done < $total): ?>
<div class="card"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><h2 style="margin:0"><?= e(__('Primi passi per aprire il negozio')) ?></h2>
  <form method="post" action="<?= e(url('admin/dismiss-onboarding')) ?>"><?= csrf_field() ?><button class="btn sec sm" type="submit"><?= e(__('Nascondi')) ?></button></form></div>
  <div class="progress"><i style="width:<?= (int)round($done / $total * 100) ?>%"></i></div><p class="muted" style="margin-top:-6px"><?= e(__('%d di %d completati', $done, $total)) ?></p>
  <ul class="steps"><?php foreach ($checklist as [$ok, $label, $hint, $href]): ?><li class="<?= $ok ? 'done' : '' ?>"><span class="dot"><?= $ok ? '✓' : '' ?></span><div><a href="<?= e(url($href)) ?>"><strong><?= e($label) ?></strong></a><small><?= e($hint) ?></small></div></li><?php endforeach ?></ul>
  <?php if ($demo): ?><form method="post" action="<?= e(url('admin/remove-demo')) ?>" data-confirm="<?= e(__('Eliminare i prodotti e le categorie dimostrative?')) ?>" style="margin-top:14px"><?= csrf_field() ?><button class="btn sec sm" type="submit">🧹 <?= e(__('Rimuovi i contenuti dimostrativi')) ?></button></form><?php endif ?></div>
<?php endif ?>

<h2 style="font-size:1rem;margin:0 0 10px"><?= e(__('Da fare')) ?></h2>
<div class="todo">
  <a href="<?= e(url('admin/orders?status=processing')) ?>" class="<?= $todo['to_ship'] ? 'hot' : '' ?>"><strong><?= (int)$todo['to_ship'] ?></strong><span><?= e(__('Ordini da spedire')) ?></span></a>
  <a href="<?= e(url('admin/orders?status=pending')) ?>"><strong><?= (int)$todo['awaiting'] ?></strong><span><?= e(__('In attesa di pagamento')) ?></span></a>
  <a href="<?= e(url('admin/products?stock=low')) ?>" class="<?= $todo['low'] ? 'hot' : '' ?>"><strong><?= (int)$todo['low'] ?></strong><span><?= e(__('Prodotti in esaurimento')) ?></span></a>
  <a href="<?= e(url('admin/products?stock=out')) ?>"><strong><?= (int)$todo['out'] ?></strong><span><?= e(__('Prodotti esauriti')) ?></span></a>
  <a href="<?= e(url('admin/products?status=draft')) ?>"><strong><?= (int)$todo['drafts'] ?></strong><span><?= e(__('Prodotti in bozza')) ?></span></a>
</div>

<div class="toolbar" style="justify-content:space-between"><h2 style="font-size:1rem;margin:0"><?= e(__('Andamento')) ?></h2>
  <div><?php foreach ([7, 30, 90] as $d): ?><a class="btn sm <?= $days === $d ? '' : 'sec' ?>" href="<?= e(url('admin?days=' . $d)) ?>"><?= e(__('%d giorni', $d)) ?></a> <?php endforeach ?></div></div>
<div class="grid3" style="margin-bottom:20px">
  <div class="stat"><small><?= e(__('Vendite')) ?></small><strong><?= e(money($stats['revenue'])) ?><?= $delta($stats['revenue'], $stats['prev_revenue']) ?></strong></div>
  <div class="stat"><small><?= e(__('Ordini')) ?></small><strong><?= (int)$stats['orders'] ?><?= $delta($stats['orders'], $stats['prev_orders']) ?></strong></div>
  <div class="stat"><small><?= e(__('Valore medio ordine')) ?></small><strong><?= e(money($stats['orders'] ? intdiv($stats['revenue'], $stats['orders']) : 0)) ?></strong></div>
</div>
<div class="grid2">
  <div>
    <div class="card"><h2><?= e(__('Vendite giorno per giorno')) ?></h2>
      <div class="chart"><?php foreach ($stats['daily'] as $d): ?><i style="height:<?= $d['t'] ? max(4, round($d['t'] / $max * 100)) : 2 ?>%" title="<?= e(date('d/m', strtotime($d['d'])) . ': ' . money($d['t'])) ?>"></i><?php endforeach ?></div>
      <div class="chart-axis"><span><?= e(date('d/m', strtotime($stats['daily'][0]['d']))) ?></span><span><?= e(date('d/m', strtotime(end($stats['daily'])['d']))) ?></span></div></div>
    <div class="card"><div style="display:flex;justify-content:space-between"><h2><?= e(__('Ultimi ordini')) ?></h2><a href="<?= e(url('admin/orders')) ?>"><?= e(__('Vedi tutti')) ?></a></div>
      <?php if ($recent): ?><table><tbody><?php foreach ($recent as $o): ?><tr><td><a href="<?= e(url('admin/orders/' . $o['id'])) ?>"><strong><?= e($o['number']) ?></strong></a></td><td class="muted"><?= e($o['email']) ?></td><td><?= a_status($o['status']) ?></td><td class="right"><?= e(money($o['total'])) ?></td></tr><?php endforeach ?></tbody></table>
      <?php else: ?><?= a_empty('🛍️', __('Ancora nessun ordine'), __('Quando i clienti ordinano, li vedrai qui.')) ?><?php endif ?></div>
  </div>
  <div>
    <div class="card"><h2><?= e(__('Più venduti')) ?></h2><table><tbody><?php foreach ($stats['top'] as $t): ?><tr><td><?= e($t['name']) ?></td><td class="right muted"><?= (int)$t['qty'] ?>×</td></tr><?php endforeach ?>
      <?php if (!$stats['top']): ?><tr><td class="muted"><?= e(__('Nessuna vendita nel periodo.')) ?></td></tr><?php endif ?></tbody></table></div>
    <?php if ($lowStock): ?><div class="card"><h2><?= e(__('Da rifornire')) ?></h2><table><tbody><?php foreach ($lowStock as $p): ?><tr><td><a href="<?= e(url('admin/products/' . $p['id'])) ?>"><?= e($p['name']) ?></a></td><td class="right"><span class="pill warn"><?= (int)$p['stock_qty'] ?></span></td></tr><?php endforeach ?></tbody></table></div><?php endif ?>
    <div class="card"><h2><?= e(__('Scorciatoie')) ?></h2><div style="display:grid;gap:8px"><a class="btn sec" href="<?= e(url('admin/products/new')) ?>">+ <?= e(__('Nuovo prodotto')) ?></a><a class="btn sec" href="<?= e(url('admin/coupons/new')) ?>">+ <?= e(__('Nuovo codice sconto')) ?></a><a class="btn sec" href="<?= e(url('admin/import')) ?>">⬆ <?= e(__('Importa da WooCommerce / Shopify')) ?></a><a class="btn sec" href="<?= e(url('admin/themes')) ?>">🎨 <?= e(__('Cambia tema')) ?></a></div></div>
  </div>
</div>
