<?php
$labels = [1 => __('I tuoi dati'), 2 => __('Invio e PEC'), 3 => __('Ricezione'), 4 => __('Automazioni'), 5 => __('Verifica')];
$f = static fn(string $k, string $d = '') => $v($k, $d);
?>
<div class="alert info" style="max-width:860px"><?= e(__('Il sistema genera fatture elettroniche in formato FatturaPA 1.2.2, validate con lo schema ufficiale, e le invia al Sistema di Interscambio (SdI) tramite la tua PEC. Le fatture dei fornitori arrivano sulla stessa PEC e vengono archiviate qui. Nessun costo e nessun intermediario.')) ?></div>
<div class="steps-flow" style="max-width:860px"><?php foreach ($labels as $n => $l): ?><span class="<?= $n <= $step ? 'on' : '' ?>"><?= $n ?>. <?= e($l) ?></span><?php endforeach ?></div>

<?php if ($step === 1): ?>
<form method="post" action="<?= e(url('admin/einvoice/setup')) ?>"><?= csrf_field() ?><input type="hidden" name="from" value="1">
<div class="card" style="max-width:860px"><h2>1. <?= e(__('Dati fiscali della tua attività')) ?></h2>
  <div class="row"><?= a_input('name', __('Denominazione / ragione sociale'), $f('einv_name', (string)setting('store_name')), 'text', ['required' => true]) ?><?= a_select('regime', __('Regime fiscale'), array_map('__', $regimes), $f('einv_regime', 'RF01')) ?></div>
  <div class="row"><?= a_input('vat', __('Partita IVA (11 cifre)'), $f('einv_vat'), 'text', ['required' => true, 'inputmode' => 'numeric', 'maxlength' => 11]) ?><?= a_input('cf', __('Codice fiscale'), $f('einv_cf')) ?></div>
  <?= a_input('address', __('Indirizzo sede (via e numero civico)'), $f('einv_address'), 'text', ['required' => true]) ?>
  <div class="row"><?= a_input('cap', 'CAP', $f('einv_cap'), 'text', ['required' => true, 'maxlength' => 5]) ?><?= a_input('city', __('Comune'), $f('einv_city'), 'text', ['required' => true]) ?></div>
  <div class="row"><?= a_input('prov', __('Provincia (sigla)'), $f('einv_prov'), 'text', ['required' => true, 'maxlength' => 2, 'style' => 'text-transform:uppercase']) ?><?= a_input('email', 'Email', $f('einv_email', (string)setting('store_email')), 'email') ?></div>
  <div class="row"><?= a_input('phone', __('Telefono'), $f('einv_phone')) ?><?= a_input('iban', 'IBAN (facoltativo)', $f('einv_iban')) ?></div>
  <div class="row"><?= a_input('rea_office', __('Ufficio REA (sigla provincia, facoltativo)'), $f('einv_rea_office'), 'text', ['maxlength' => 2]) ?><?= a_input('rea_number', __('Numero REA (facoltativo)'), $f('einv_rea_number')) ?></div>
  <?= a_select('nature', __('Natura per operazioni senza IVA (aliquota 0%)'), array_map('__', $natures), $f('einv_nature', 'N2.2'), __('Usata solo se vendi con IVA 0% (es. regime forfettario: N2.2).')) ?>
  <?= a_check('bollo', __('Applica l\'imposta di bollo virtuale (2 €) alle fatture senza IVA sopra 77,47 €'), $f('einv_bollo') === '1') ?>
  <button class="btn" type="submit"><?= e(__('Salva e continua')) ?> →</button></div></form>

<?php elseif ($step === 2): ?>
<form method="post" action="<?= e(url('admin/einvoice/setup')) ?>"><?= csrf_field() ?><input type="hidden" name="from" value="2">
<div class="card" style="max-width:860px"><h2>2. <?= e(__('Come invii le fatture a SdI')) ?></h2>
  <div class="type-cards"><label><input type="radio" name="transport" value="pec" <?= $f('einv_transport', 'manual') === 'pec' ? 'checked' : '' ?>>📨 <?= e(__('Automatico via PEC')) ?><small><?= e(__('Consigliato: invio e ricezione direttamente da qui.')) ?></small></label>
    <label><input type="radio" name="transport" value="manual" <?= $f('einv_transport', 'manual') !== 'pec' ? 'checked' : '' ?>>📁 <?= e(__('Manuale')) ?><small><?= e(__('Scarichi l\'XML e lo carichi sul portale dell\'Agenzia delle Entrate.')) ?></small></label></div>
  <div id="pec-box" style="margin-top:14px">
    <p class="muted"><?= e(__('Ti serve una casella PEC (Aruba, Legalmail, Register…). Inserisci i dati del server dal sito del tuo gestore.')) ?> <button type="button" class="btn sec sm" id="preset-aruba">Aruba PEC</button></p>
    <div class="row"><?= a_input('pec_address', __('Indirizzo PEC'), $f('pec_address'), 'email') ?><?= a_input('sdi_address', __('PEC di SdI (non cambiarla)'), $f('sdi_address', 'sdi01@pec.fatturapa.it'), 'email') ?></div>
    <div class="row"><?= a_input('pec_user', __('Utente (di solito l\'indirizzo PEC)'), $f('pec_user'), 'text', ['autocomplete' => 'off']) ?><?= a_input('pec_pass', 'Password', '', 'password', ['autocomplete' => 'new-password', 'placeholder' => $hasPass ? '••••••••' : ''], __('Viene conservata cifrata.')) ?></div>
    <div class="row"><?= a_input('pec_smtp_host', __('Server in uscita (SMTP)'), $f('pec_smtp_host')) ?><div class="row"><?= a_input('pec_smtp_port', __('Porta'), $f('pec_smtp_port', '465'), 'number') ?><?= a_select('pec_smtp_secure', __('Sicurezza'), ['ssl' => 'SSL', 'tls' => 'STARTTLS', 'none' => __('Nessuna')], $f('pec_smtp_secure', 'ssl')) ?></div></div>
    <div class="row"><?= a_input('pec_imap_host', __('Server in entrata (IMAP)'), $f('pec_imap_host')) ?><div class="row"><?= a_input('pec_imap_port', __('Porta'), $f('pec_imap_port', '993'), 'number') ?><?= a_select('pec_imap_secure', __('Sicurezza'), ['ssl' => 'SSL', 'tls' => 'STARTTLS', 'none' => __('Nessuna')], $f('pec_imap_secure', 'ssl')) ?></div></div></div>
  <a class="btn sec" href="<?= e(url('admin/einvoice/setup?step=1')) ?>">← <?= e(__('Indietro')) ?></a> <button class="btn" type="submit"><?= e(__('Salva e continua')) ?> →</button></div></form>
<script>document.getElementById('preset-aruba').onclick=function(){var s=function(n,v){document.getElementById('f_'+n).value=v};s('pec_smtp_host','smtps.pec.aruba.it');s('pec_smtp_port','465');s('pec_smtp_secure','ssl');s('pec_imap_host','imaps.pec.aruba.it');s('pec_imap_port','993');s('pec_imap_secure','ssl')};</script>

<?php elseif ($step === 3): ?>
<div class="card" style="max-width:860px"><h2>3. <?= e(__('Ricezione delle fatture dei fornitori')) ?></h2>
  <ol style="line-height:1.9;padding-left:20px">
    <li><?= e(__('Accedi al portale "Fatture e Corrispettivi" dell\'Agenzia delle Entrate (con SPID, CIE o CNS).')) ?></li>
    <li><?= e(__('Vai su "Registrazione dell\'indirizzo telematico" e registra la tua PEC come canale di ricezione.')) ?></li>
    <li><?= e(__('Comunica ai fornitori il tuo "Codice destinatario" 0000000 e la tua PEC (oppure solo la PEC).')) ?></li>
    <li><?= e(__('Le fatture arriveranno sulla PEC e AlienShop le leggerà da sola ogni 10 minuti.')) ?></li></ol>
  <div class="alert info"><?= e(__('Per le fatture già ricevute puoi scaricare i file XML dal tuo cassetto fiscale e caricarli in "Fatture ricevute".')) ?></div>
  <h3 style="margin:18px 0 8px"><?= e(__('Prova la connessione')) ?></h3>
  <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn sec" href="<?= e(url('admin/einvoice/setup?step=3&test=smtp')) ?>">📤 <?= e(__('Prova invio (SMTP)')) ?></a><a class="btn sec" href="<?= e(url('admin/einvoice/setup?step=3&test=imap')) ?>">📥 <?= e(__('Prova ricezione (IMAP)')) ?></a></div>
  <?php if ($check): ?><div class="alert <?= $check[1] === null ? 'success' : 'error' ?>" style="margin-top:12px"><?= $check[1] === null ? '✓ ' . e($check[0] === 'smtp' ? __('Invio riuscito: abbiamo spedito una mail di prova alla tua PEC.') : __('Connessione alla casella riuscita.')) : '✗ ' . e($check[1]) ?></div><?php endif ?>
  <div style="margin-top:18px"><a class="btn sec" href="<?= e(url('admin/einvoice/setup?step=2')) ?>">← <?= e(__('Indietro')) ?></a> <a class="btn" href="<?= e(url('admin/einvoice/setup?step=4')) ?>"><?= e(__('Continua')) ?> →</a></div></div>

<?php elseif ($step === 4): ?>
<form method="post" action="<?= e(url('admin/einvoice/setup')) ?>"><?= csrf_field() ?><input type="hidden" name="from" value="4">
<div class="card" style="max-width:860px"><h2>4. <?= e(__('Automazioni')) ?></h2>
  <?= a_select('einv_auto', __('Quando emettere la fattura'), ['requested' => __('Solo se il cliente la richiede al checkout (consigliato)'), 'all' => __('Per tutti gli ordini pagati (serve il codice fiscale del cliente)'), 'off' => __('Mai in automatico: solo a mano')], $f('einv_auto', 'requested'), __('L\'emissione avviene quando l\'ordine risulta pagato.')) ?>
  <?= a_check('einv_autosend', __('Invia a SdI subito dopo l\'emissione (richiede la PEC)'), $f('einv_autosend', '1') === '1') ?>
  <?= a_check('einv_courtesy', __('Invia al cliente una copia di cortesia in PDF'), $f('einv_courtesy', '1') === '1') ?>
  <?= a_check('einv_sync', __('Leggi la PEC in automatico ogni 10 minuti (ricevute SdI e fatture fornitori)'), $f('einv_sync', '1') === '1') ?>
  <a class="btn sec" href="<?= e(url('admin/einvoice/setup?step=3')) ?>">← <?= e(__('Indietro')) ?></a> <button class="btn" type="submit"><?= e(__('Salva e verifica')) ?> →</button></div></form>

<?php else: ?>
<form method="post" action="<?= e(url('admin/einvoice/setup')) ?>"><?= csrf_field() ?><input type="hidden" name="from" value="5">
<div class="card" style="max-width:860px"><h2>5. <?= e(__('Verifica finale')) ?></h2>
  <ul class="steps">
    <li class="<?= !$final['seller'] ? 'done' : '' ?>"><span class="dot"><?= !$final['seller'] ? '✓' : '' ?></span><div><strong><?= e(__('Dati fiscali')) ?></strong><small><?= e($final['seller'] ? implode(' ', $final['seller']) : __('Completi e validi.')) ?></small></div></li>
    <li class="<?= !$final['seller'] && !$final['xml'] ? 'done' : '' ?>"><span class="dot"><?= !$final['seller'] && !$final['xml'] ? '✓' : '' ?></span><div><strong><?= e(__('Fattura di prova validata con lo schema ufficiale FatturaPA')) ?></strong><small><?= e($final['xml'] ? implode(' | ', $final['xml']) : ($final['seller'] ? __('In attesa dei dati fiscali.') : __('Il file XML generato è conforme.'))) ?></small></div></li>
    <li class="<?= $final['smtp'] ? 'done' : '' ?>"><span class="dot"><?= $final['smtp'] ? '✓' : '' ?></span><div><strong><?= e(__('Invio via PEC')) ?></strong><small><?= e($final['smtp'] ? __('Configurato.') : __('Non configurato: potrai scaricare gli XML e caricarli a mano.')) ?></small></div></li>
    <li class="<?= $final['imap'] ? 'done' : '' ?>"><span class="dot"><?= $final['imap'] ? '✓' : '' ?></span><div><strong><?= e(__('Ricezione via PEC')) ?></strong><small><?= e($final['imap'] ? __('Configurata.') : __('Non configurata: potrai caricare a mano le fatture ricevute.')) ?></small></div></li></ul>
  <p class="muted" style="font-size:.85rem"><?= e(__('Consiglio: prima di usarla davvero, emetti una fattura di prova verso un tuo codice fiscale e controllane l\'esito (ricevuta di consegna) in "Fatture elettroniche".')) ?></p>
  <a class="btn sec" href="<?= e(url('admin/einvoice/setup?step=4')) ?>">← <?= e(__('Indietro')) ?></a> <button class="btn" type="submit" <?= $final['seller'] ? 'disabled' : '' ?>>✓ <?= e(__('Attiva la fatturazione elettronica')) ?></button></div></form>
<?php endif ?>
