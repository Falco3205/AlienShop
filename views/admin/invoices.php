<div class="grid2"><div>
<div class="card"><h2><?= e(__('Esporta per il commercialista')) ?></h2>
<form method="get" action="<?= e(url('admin/invoices/export')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end"><?= a_input('from', __('Dal'), $from, 'date') ?><?= a_input('to', __('Al'), $to, 'date') ?><div class="field"><button class="btn" type="submit">⬇ CSV</button></div></form></div>
<div class="card" style="padding:0"><table><thead><tr><th><?= e(__('Numero')) ?></th><th><?= e(__('Data')) ?></th><th><?= e(__('Ordine')) ?></th><th class="right"><?= e(__('Totale')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($rows as $o): ?><tr><td><strong><?= e($o['invoice_number']) ?></strong></td><td><?= e(substr((string)$o['invoice_date'], 0, 10)) ?></td><td><a href="<?= e(url('admin/orders/' . $o['id'])) ?>"><?= e($o['number']) ?></a></td><td class="right"><?= e(Alien\Core\Money::format((int)$o['total'], $o['currency'])) ?></td><td class="right"><a class="btn sec sm" href="<?= e(url('admin/orders/' . $o['id'] . '/invoice')) ?>">PDF</a></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="5"><?= a_empty('🧾', __('Nessun documento emesso'), __('Apri un ordine e premi "Genera PDF": il documento riceve il numero progressivo.')) ?></td></tr><?php endif ?></tbody></table></div></div>
<div class="card"><h2><?= e(__('Impostazioni')) ?></h2><form method="post" action="<?= e(url('admin/invoices/settings')) ?>"><?= csrf_field() ?>
<?= a_select('invoice_type', __('Tipo di documento'), ['receipt' => __('Ricevuta'), 'invoice' => __('Fattura'), 'sales' => __('Documento di vendita')], setting('invoice_type', 'receipt')) ?>
<?= a_input('invoice_prefix', __('Prefisso numerazione (opzionale)'), setting('invoice_prefix', ''), 'text', [], __('Esempio: FT- → FT-2026/0001. La numerazione riparte ogni anno.')) ?>
<?= a_check('invoices_auto', __('Invia automaticamente al cliente quando l\'ordine risulta pagato'), setting('invoices_auto', '0') === '1') ?>
<?= a_textarea('invoice_note', __('Nota a piè di documento'), setting('invoice_note', ''), 3, __('Vuoto = per ricevute e documenti di vendita: "Documento commerciale non valido ai fini fiscali".')) ?>
<button class="btn" type="submit"><?= e(__('Salva')) ?></button></form>
<p class="muted" style="font-size:.85rem;margin-top:12px"><?= e(__('Nota: il PDF non sostituisce la fattura elettronica (SDI) richiesta in Italia per le vendite a partite IVA: per quella usa il tuo gestionale con l\'esportazione CSV.')) ?></p></div></div>
