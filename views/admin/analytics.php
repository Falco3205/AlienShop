<?php
$labels = [1 => __('Crea l\'account'), 2 => __('Incolla l\'ID'), 3 => __('Opzioni'), 4 => __('Verifica')];
$store = (string)setting('store_name', '');
?>
<?php if ($step === 0): ?>
<div class="card" style="max-width:720px"><h2>✓ <?= e(__('Google Analytics è collegato')) ?></h2>
  <p>ID di misurazione: <code><?= e($id) ?></code></p>
  <ul class="steps"><li class="done"><span class="dot">✓</span><div><strong><?= e(__('Eventi e-commerce')) ?></strong><small><?= e(setting('analytics_ecommerce', '1') === '1' ? __('Attivi: visualizzazione prodotto, carrello, checkout, acquisto.') : __('Disattivati.')) ?></small></div></li>
  <li class="done"><span class="dot">✓</span><div><strong><?= e(__('Banner consenso cookie')) ?></strong><small><?= e(setting('analytics_consent', '1') === '1' ? __('Attivo: il tracciamento parte solo dopo il consenso del visitatore.') : __('Disattivato: il tracciamento parte subito.')) ?></small></div></li></ul>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px"><a class="btn" href="https://analytics.google.com/" target="_blank" rel="noopener">📊 <?= e(__('Apri i report')) ?></a><a class="btn sec" href="<?= e(url('admin/analytics?step=4')) ?>"><?= e(__('Verifica installazione')) ?></a><a class="btn sec" href="<?= e(url('admin/analytics?step=2')) ?>"><?= e(__('Cambia ID')) ?></a>
    <form method="post" data-confirm="<?= e(__('Scollegare Google Analytics?')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="disconnect"><button class="btn danger" type="submit"><?= e(__('Scollega')) ?></button></form></div></div>
<?php else: ?>
<div class="steps-flow" style="max-width:720px"><?php foreach ($labels as $n => $l): ?><span class="<?= $n <= $step ? 'on' : '' ?>"><?= $n ?>. <?= e($l) ?></span><?php endforeach ?></div>

<?php if ($step === 1): ?>
<div class="card" style="max-width:720px"><h2>1. <?= e(__('Crea l\'account e la proprietà su Google Analytics')) ?></h2>
  <p class="muted"><?= e(__('Ci vogliono 3 minuti. Serve un account Google (gratuito).')) ?></p>
  <ol style="line-height:1.9;padding-left:20px">
    <li><?= __('Apri %s e accedi con il tuo account Google.', '<a href="https://analytics.google.com/" target="_blank" rel="noopener">analytics.google.com</a>') ?></li>
    <li><?= e(__('Clicca su "Amministrazione" (icona ingranaggio in basso a sinistra) → "Crea" → "Proprietà".')) ?></li>
    <li><?= e(__('Nome proprietà: %s — fuso orario e valuta: scegli quelli del tuo negozio (%s).', $store, Alien\Core\Money::currency())) ?></li>
    <li><?= e(__('Alla richiesta di piattaforma scegli "Web" e inserisci questo indirizzo:')) ?>
      <div style="display:flex;gap:8px;margin:6px 0"><input readonly value="<?= e(url()) ?>" style="max-width:380px" id="shopurl"><button type="button" class="btn sec sm" data-copy="<?= e(url()) ?>">📋 <?= e(__('Copia')) ?></button></div></li>
    <li><?= e(__('Lascia attiva la "Misurazione avanzata" e conferma.')) ?></li>
  </ol>
  <a class="btn" href="<?= e(url('admin/analytics?step=2')) ?>"><?= e(__('Fatto, continua')) ?> →</a></div>

<?php elseif ($step === 2): ?>
<div class="card" style="max-width:720px"><h2>2. <?= e(__('Incolla l\'ID di misurazione')) ?></h2>
  <p class="muted"><?= e(__('Lo trovi in Google Analytics: Amministrazione → Flussi di dati → clicca sul tuo sito web. In alto a destra c\'è l\'"ID misurazione", che inizia con G-.')) ?></p>
  <form method="post" action="<?= e(url('admin/analytics')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="id">
    <?= a_input('analytics_id', __('ID di misurazione'), $id, 'text', ['placeholder' => 'G-XXXXXXXXXX', 'required' => true, 'pattern' => '[Gg]-[A-Za-z0-9]{6,14}', 'autocomplete' => 'off', 'style' => 'max-width:300px;text-transform:uppercase']) ?>
    <a class="btn sec" href="<?= e(url('admin/analytics?step=1')) ?>">← <?= e(__('Indietro')) ?></a> <button class="btn" type="submit"><?= e(__('Salva e continua')) ?> →</button></form></div>

<?php elseif ($step === 3): ?>
<div class="card" style="max-width:720px"><h2>3. <?= e(__('Scegli cosa tracciare')) ?></h2>
  <form method="post" action="<?= e(url('admin/analytics')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="options">
    <div class="field"><label class="check"><input type="hidden" name="analytics_ecommerce" value="0"><input type="checkbox" name="analytics_ecommerce" value="1" <?= setting('analytics_ecommerce', '1') === '1' ? 'checked' : '' ?>> <strong><?= e(__('Eventi e-commerce')) ?></strong></label>
      <div class="help"><?= e(__('Invia a Google visualizzazione prodotto, aggiunta al carrello, inizio checkout e acquisto con importo: vedrai ricavi e prodotti più venduti nei report.')) ?></div></div>
    <div class="field"><label class="check"><input type="hidden" name="analytics_consent" value="0"><input type="checkbox" name="analytics_consent" value="1" <?= setting('analytics_consent', '1') === '1' ? 'checked' : '' ?>> <strong><?= e(__('Mostra il banner di consenso cookie')) ?></strong></label>
      <div class="help"><?= e(__('Consigliato per i visitatori europei (GDPR): Analytics parte solo se il visitatore accetta. Se lo disattivi, il tracciamento parte subito: assicurati di rispettare la normativa del tuo paese.')) ?></div></div>
    <a class="btn sec" href="<?= e(url('admin/analytics?step=2')) ?>">← <?= e(__('Indietro')) ?></a> <button class="btn" type="submit"><?= e(__('Salva e verifica')) ?> →</button></form></div>

<?php else: ?>
<div class="card" style="max-width:720px"><h2>4. <?= e(__('Verifica')) ?></h2>
  <div class="alert <?= !empty($check['unverified']) ? 'info' : ($check['ok'] ? 'success' : 'error') ?>" style="margin-bottom:16px"><?= !empty($check['unverified']) ? 'ℹ️ ' : ($check['ok'] ? '✓ ' : '✗ ') ?><?= e($check['message']) ?></div>
  <?php if ($check['ok']): ?>
    <p><?= e(__('Ultimo controllo: apri il tuo negozio in una nuova scheda, accetta i cookie e guarda in Google Analytics → Report → Tempo reale: dovresti vederti comparire entro pochi secondi.')) ?></p>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= e(__('Apri il negozio')) ?> ↗</a><a class="btn sec" href="https://analytics.google.com/" target="_blank" rel="noopener"><?= e(__('Apri Tempo reale')) ?> ↗</a><a class="btn sec" href="<?= e(url('admin/analytics')) ?>"><?= e(__('Ho finito')) ?></a></div>
  <?php else: ?>
    <a class="btn sec" href="<?= e(url('admin/analytics?step=4')) ?>">↻ <?= e(__('Riprova')) ?></a> <a class="btn sec" href="<?= e(url('admin/analytics?step=2')) ?>"><?= e(__('Controlla l\'ID')) ?></a>
  <?php endif ?></div>
<?php endif ?>
<?php endif ?>
