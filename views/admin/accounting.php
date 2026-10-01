<?php
$qs = static fn(array $o = []) => http_build_query(array_merge(['p' => $type, 'v' => $value, 'tab' => $tab], $o));
$tabs = ['overview' => __('Riepilogo'), 'sales' => __('Registro vendite'), 'purchases' => __('Registro acquisti'), 'vat' => __('IVA'), 'deadlines' => __('Scadenze'), 'suppliers' => __('Fornitori')];
$year = (int)substr($from, 0, 4);
$periods = ['month' => [date('Y-m'), date('Y-m', strtotime('-1 month')), date('Y-m', strtotime('-2 months'))], 'quarter' => [], 'year' => [(string)$year, (string)($year - 1)]];
for ($i = 0; $i < 4; $i++) { $t = strtotime('-' . ($i * 3) . ' months'); $periods['quarter'][] = date('Y', $t) . '-Q' . (int)ceil((int)date('n', $t) / 3); }
$m = static fn(int $c) => money($c);
?>
<div class="toolbar" style="justify-content:space-between"><div style="display:flex;gap:6px;flex-wrap:wrap">
<?php foreach (['month' => __('Mese'), 'quarter' => __('Trimestre'), 'year' => __('Anno')] as $pk => $pl): ?><a class="btn sm <?= $type === $pk ? '' : 'sec' ?>" href="<?= e(url('admin/accounting?' . http_build_query(['p' => $pk, 'tab' => $tab]))) ?>"><?= e($pl) ?></a><?php endforeach ?>
<form method="get" action="<?= e(url('admin/accounting')) ?>" style="display:flex;gap:6px"><input type="hidden" name="p" value="<?= e($type) ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>"><select name="v" data-autosubmit><?php foreach ($periods[$type] as $pv): ?><option value="<?= e($pv) ?>" <?= $pv === $value ? 'selected' : '' ?>><?= e($pv) ?></option><?php endforeach ?><?php if (!in_array($value, $periods[$type], true)): ?><option selected><?= e($value) ?></option><?php endif ?></select></form></div>
<div style="display:flex;gap:6px;flex-wrap:wrap"><a class="btn sec sm" href="<?= e(url('admin/accounting/export/vendite?' . $qs())) ?>">⬇ <?= e(__('Vendite CSV')) ?></a><a class="btn sec sm" href="<?= e(url('admin/accounting/export/acquisti?' . $qs())) ?>">⬇ <?= e(__('Acquisti CSV')) ?></a><a class="btn sec sm" href="<?= e(url('admin/accounting/export/iva?' . $qs())) ?>">⬇ <?= e(__('IVA CSV')) ?></a><a class="btn sec sm" href="<?= e(url('admin/accounting/export/xml?' . $qs())) ?>">🗄 <?= e(__('Archivio XML')) ?></a></div></div>
<div class="tabs"><?php foreach ($tabs as $k => $l): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= e(url('admin/accounting?' . $qs(['tab' => $k]))) ?>"><?= e($l) ?></a><?php endforeach ?></div>

<?php if ($tab === 'overview'): ?>
<div class="grid4" style="margin-bottom:20px">
  <div class="stat"><small><?= e(__('Ricavi (imponibile)')) ?></small><strong><?= e($m($o['sales_net'])) ?></strong></div>
  <div class="stat"><small><?= e(__('Costi (imponibile)')) ?></small><strong><?= e($m($o['costs'])) ?></strong></div>
  <div class="stat"><small><?= e(__('Margine lordo')) ?></small><strong style="color:<?= $o['margin'] >= 0 ? 'var(--ok)' : 'var(--bad)' ?>"><?= e($m($o['margin'])) ?></strong></div>
  <div class="stat"><small><?= e($o['vat_balance'] >= 0 ? __('IVA da versare (stima)') : __('IVA a credito (stima)')) ?></small><strong><?= e($m(abs($o['vat_balance']))) ?></strong></div></div>
<div class="grid2"><div class="card"><h2><?= e(__('Andamento %d: ricavi e costi', $year)) ?></h2>
  <?php $max = max(1, ...array_map(static fn($r) => max($r['sales'], $r['costs']), $monthly)); ?>
  <div style="display:flex;gap:8px;align-items:flex-end;height:150px"><?php foreach ($monthly as $mm): ?><div style="flex:1;display:flex;gap:2px;align-items:flex-end;height:100%" title="<?= e(sprintf('%02d: %s / %s', $mm['month'], money($mm['sales']), money($mm['costs']))) ?>"><i style="flex:1;background:var(--primary);border-radius:3px 3px 0 0;height:<?= max(2, (int)round(max(0, $mm['sales']) / $max * 100)) ?>%"></i><i style="flex:1;background:#f0a1b0;border-radius:3px 3px 0 0;height:<?= max(2, (int)round(max(0, $mm['costs']) / $max * 100)) ?>%"></i></div><?php endforeach ?></div>
  <div class="chart-axis"><span><?= e(__('Gen')) ?></span><span><?= e(__('Dic')) ?></span></div><p class="muted" style="font-size:.82rem"><span style="color:var(--primary)">■</span> <?= e(__('Ricavi')) ?> &nbsp; <span style="color:#f0a1b0">■</span> <?= e(__('Costi')) ?></p></div>
<div class="card"><h2><?= e(__('Periodo %s', $label)) ?></h2><table><tbody>
  <tr><td><?= e(__('Fatture emesse')) ?></td><td class="right"><?= (int)$o['invoiced'] ?></td></tr><tr><td><?= e(__('Corrispettivi (ordini senza fattura)')) ?></td><td class="right"><?= (int)$o['receipts'] ?></td></tr>
  <tr><td><?= e(__('IVA sulle vendite')) ?></td><td class="right"><?= e($m($o['sales_vat'])) ?></td></tr><tr><td><?= e(__('IVA detraibile sugli acquisti')) ?></td><td class="right"><?= e($m($o['purchases_vat_deductible'])) ?></td></tr>
  <tr><td><strong><?= e(__('Saldo IVA')) ?></strong></td><td class="right"><strong><?= e($m($o['vat_balance'])) ?></strong></td></tr></tbody></table>
  <p class="muted" style="font-size:.8rem;margin:10px 0 0"><?= e(__('Dati indicativi calcolati dai documenti registrati. Non sostituiscono la contabilità del commercialista né la liquidazione IVA ufficiale.')) ?></p></div></div>

<?php elseif ($tab === 'sales' || $tab === 'purchases'): $rows = $tab === 'sales' ? $o['sales'] : $o['purchases']; ?>
<div class="card" style="padding:0"><table><thead><tr><th><?= e(__('Data')) ?></th><th><?= e(__('Tipo')) ?></th><th><?= e(__('Numero')) ?></th><th><?= e($tab === 'sales' ? __('Cliente') : __('Fornitore')) ?></th><th class="right"><?= e(__('Imponibile')) ?></th><th class="right">IVA</th><th class="right"><?= e(__('Totale')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['date']) ?></td><td class="muted"><?= e($r['type']) ?></td><td><?php if ($tab === 'purchases' && $r['kind'] === 'invoice'): ?><a href="<?= e(url('admin/purchases/' . $r['id'])) ?>"><?= e($r['number']) ?></a><?php else: ?><?= e($r['number']) ?><?php endif ?></td><td><?= e($r['party']) ?></td><td class="right"><?= e($m($r['net'])) ?></td><td class="right"><?= e($m($r['vat'])) ?></td><td class="right"><?= e($m($r['total'])) ?></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="7" class="muted"><?= e(__('Nessun documento nel periodo.')) ?></td></tr><?php else: ?><tr><td colspan="4" class="right"><strong><?= e(__('Totali')) ?></strong></td><td class="right"><strong><?= e($m(array_sum(array_column($rows, 'net')))) ?></strong></td><td class="right"><strong><?= e($m(array_sum(array_column($rows, 'vat')))) ?></strong></td><td class="right"><strong><?= e($m(array_sum(array_column($rows, 'total')))) ?></strong></td></tr><?php endif ?></tbody></table></div>

<?php elseif ($tab === 'vat'): ?>
<div class="grid2"><div class="card"><h2><?= e(__('IVA sulle vendite per aliquota')) ?></h2><table><thead><tr><th><?= e(__('Aliquota')) ?></th><th class="right"><?= e(__('Imponibile')) ?></th><th class="right">IVA</th></tr></thead><tbody>
<?php foreach ($o['sales_by_rate'] as $r): ?><tr><td><?= e(rtrim(rtrim(number_format($r['rate'], 2, ',', ''), '0'), ',')) ?>%</td><td class="right"><?= e($m($r['net'])) ?></td><td class="right"><?= e($m($r['vat'])) ?></td></tr><?php endforeach ?><?php if (!$o['sales_by_rate']): ?><tr><td colspan="3" class="muted">—</td></tr><?php endif ?></tbody></table></div>
<div class="card"><h2><?= e(__('IVA detraibile sugli acquisti per aliquota')) ?></h2><table><thead><tr><th><?= e(__('Aliquota')) ?></th><th class="right"><?= e(__('Imponibile')) ?></th><th class="right">IVA</th></tr></thead><tbody>
<?php foreach ($o['purchases_by_rate'] as $r): ?><tr><td><?= e(rtrim(rtrim(number_format($r['rate'], 2, ',', ''), '0'), ',')) ?>%</td><td class="right"><?= e($m($r['net'])) ?></td><td class="right"><?= e($m($r['vat'])) ?></td></tr><?php endforeach ?><?php if (!$o['purchases_by_rate']): ?><tr><td colspan="3" class="muted">—</td></tr><?php endif ?></tbody></table></div></div>
<div class="card"><h2><?= e($o['vat_balance'] >= 0 ? __('IVA da versare nel periodo') : __('IVA a credito nel periodo')) ?>: <?= e($m(abs($o['vat_balance']))) ?></h2><p class="muted"><?= e(__('Vendite %s − acquisti detraibili %s. Stima indicativa: la liquidazione ufficiale va confermata con il tuo commercialista.', $m($o['sales_vat']), $m($o['purchases_vat_deductible']))) ?></p></div>

<?php elseif ($tab === 'deadlines'): ?>
<div class="grid2"><div class="card"><h2><?= e(__('Da pagare ai fornitori')) ?> — <?= e($m($dl['payable_total'])) ?></h2><table><tbody>
<?php foreach ($dl['payable'] as $r): ?><tr><td><a href="<?= e(url('admin/purchases/' . $r['id'])) ?>"><?= e($r['party']) ?></a><div class="muted"><?= e($r['number']) ?></div></td><td><?= $r['due'] < date('Y-m-d') ? '<span class="pill bad">' . e($r['due']) . '</span>' : e($r['due']) ?></td><td class="right"><?= e($m($r['amount'])) ?></td></tr><?php endforeach ?><?php if (!$dl['payable']): ?><tr><td class="muted"><?= e(__('Nessuna scadenza.')) ?></td></tr><?php endif ?></tbody></table></div>
<div class="card"><h2><?= e(__('Da incassare dai clienti')) ?> — <?= e($m($dl['receivable_total'])) ?></h2><table><tbody>
<?php foreach ($dl['receivable'] as $r): ?><tr><td><a href="<?= e(url('admin/orders/' . $r['id'])) ?>"><?= e($r['number']) ?></a><div class="muted"><?= e($r['email']) ?> · <?= e($r['payment_method']) ?></div></td><td class="muted"><?= e(substr($r['created_at'], 0, 10)) ?></td><td class="right"><?= e(Alien\Core\Money::format((int)$r['total'], $r['currency'])) ?></td></tr><?php endforeach ?><?php if (!$dl['receivable']): ?><tr><td class="muted"><?= e(__('Nessun ordine da incassare.')) ?></td></tr><?php endif ?></tbody></table></div></div>

<?php else: ?>
<div class="card" style="padding:0"><table><thead><tr><th><?= e(__('Fornitore')) ?></th><th><?= e(__('Documenti')) ?></th><th class="right"><?= e(__('Imponibile')) ?></th><th class="right"><?= e(__('Totale')) ?></th></tr></thead><tbody>
<?php foreach ($suppliers as $s): ?><tr><td><strong><?= e($s['name']) ?></strong><div class="muted"><?= e($s['vat_id']) ?></div></td><td><?= (int)$s['count'] ?></td><td class="right"><?= e($m($s['net'])) ?></td><td class="right"><?= e($m($s['total'])) ?></td></tr><?php endforeach ?>
<?php if (!$suppliers): ?><tr><td colspan="4" class="muted"><?= e(__('Nessun acquisto nel periodo.')) ?></td></tr><?php endif ?></tbody></table></div>
<?php endif ?>
