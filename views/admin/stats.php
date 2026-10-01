<?php
$f = $r['funnel'];
$pct = static fn(int $a, int $b) => $b > 0 ? round($a / $b * 100, 1) . '%' : '—';
$table = static function (string $title, string $hint, array $rows, array $cols) {
    ob_start(); ?>
    <div class="card"><h2><?= e($title) ?></h2><p class="muted" style="margin-top:-8px;font-size:.85rem"><?= e($hint) ?></p>
    <?php if (!$rows): ?><p class="muted"><?= e(__('Ancora nessun dato.')) ?></p><?php else: ?>
    <table><tbody><?php foreach ($rows as $i => $p): ?><tr><td class="muted" style="width:26px"><?= $i + 1 ?></td>
      <td><?php if ($p['slug']): ?><a href="<?= e(url('admin/products/' . $p['id'])) ?>"><?= e($p['name']) ?></a><?php else: ?><?= e($p['name']) ?><?php endif ?></td>
      <?php foreach ($cols as $key => $fmt): ?><td class="right nowrap"><strong><?= e($fmt($p)) ?></strong></td><?php endforeach ?></tr><?php endforeach ?></tbody></table><?php endif ?></div>
    <?php return (string)ob_get_clean();
};
?>
<div class="toolbar" style="justify-content:space-between"><div>
  <?php foreach ([7, 30, 90] as $d): ?><a class="btn sm <?= $days === $d ? '' : 'sec' ?>" href="<?= e(url('admin/stats?days=' . $d)) ?>"><?= e(__('%d giorni', $d)) ?></a> <?php endforeach ?></div>
  <span class="muted"><?= e(__('Dati raccolti dal tuo negozio, senza cookie e senza dati personali.')) ?></span></div>

<div class="card"><h2><?= e(__('Dal clic all\'acquisto')) ?></h2>
  <div class="funnel">
    <?php foreach ([[__('Visite alle schede prodotto'), $f['views'], null], [__('Aggiunte al carrello'), $f['carts'], $pct($f['carts'], $f['views'])], [__('Checkout iniziati'), $f['checkouts'], $pct($f['checkouts'], $f['carts'])], [__('Ordini'), $f['orders'], $pct($f['orders'], $f['checkouts'])]] as $i => [$label, $n, $rate]): ?>
      <div class="fstep"><small><?= e($label) ?></small><strong><?= (int)$n ?></strong><?php if ($rate !== null): ?><span class="muted">↳ <?= e($rate) ?></span><?php endif ?></div>
    <?php endforeach ?></div>
  <p class="muted" style="margin:10px 0 0"><?= e(__('Tasso di conversione visite → ordini: %s', $pct($f['orders'], $f['views']))) ?></p></div>

<div class="grid2" style="grid-template-columns:1fr 1fr">
<?= $table(__('🔥 Più cliccati'), __('Prodotti con più visite alla scheda.'), $r['viewed'], ['v' => static fn($p) => '👁 ' . $p['views']]) ?>
<?= $table(__('🏆 Più venduti'), __('Per quantità venduta nel periodo.'), $r['sold'], ['q' => static fn($p) => $p['sold'] . '×', 'r' => static fn($p) => money($p['revenue'])]) ?>
<?= $table(__('🛒 Più messi nel carrello'), __('Quante volte sono stati aggiunti al carrello.'), $r['carted'], ['c' => static fn($p) => '🛒 ' . $p['carts']]) ?>
<?= $table(__('⚠️ Nel carrello ma non comprati'), __('Aggiunte al carrello meno unità vendute: qui perdi più vendite. Controlla prezzo, spedizione e disponibilità.'), $r['abandoned'], ['a' => static fn($p) => $p['abandoned'] . '×']) ?>
<?= $table(__('👀 Visti ma mai comprati'), __('Molte visite e zero vendite: migliora foto, descrizione o prezzo.'), $r['neverBought'], ['v' => static fn($p) => '👁 ' . $p['views']]) ?>
<div class="card"><h2><?= e(__('🔎 Cosa cercano i clienti')) ?></h2>
  <?php if (!$r['searches']): ?><p class="muted"><?= e(__('Ancora nessuna ricerca.')) ?></p><?php else: ?>
  <table><tbody><?php foreach ($r['searches'] as $s): ?><tr><td><?= e($s['term']) ?></td><td class="right"><?= (int)$s['hits'] ?>×</td><td class="right"><?= $s['zero'] ? '<span class="pill bad">' . e(__('0 risultati')) . '</span>' : '' ?></td></tr><?php endforeach ?></tbody></table>
  <?php if ($r['noResults']): ?><p class="muted" style="margin-top:10px;font-size:.85rem"><?= e(__('Le ricerche senza risultati sono prodotti che i clienti vorrebbero: aggiungili o crea un redirect.')) ?></p><?php endif ?><?php endif ?></div>
</div>
