<?php
$labels = [1 => __('Chi sei'), 2 => __('Come vendi'), 3 => __('Privacy e cookie'), 4 => __('Pubblica')];
$v = static fn(string $k) => $p[$k] ?? '';
$next = static fn(int $from) => '<input type="hidden" name="action" value="save"><input type="hidden" name="from" value="' . $from . '">';
?>
<div class="alert info" style="max-width:820px"><?= e(__('Questi documenti sono modelli generati dalle risposte che dai: coprono i casi più comuni di un negozio online europeo ma non sostituiscono la consulenza di un professionista. Rileggili prima di pubblicarli.')) ?></div>
<div class="steps-flow" style="max-width:820px"><?php foreach ($labels as $n => $l): ?><span class="<?= $n <= $step ? 'on' : '' ?>"><?= $n ?>. <?= e($l) ?></span><?php endforeach ?></div>

<?php if ($step === 1): ?>
<form method="post" action="<?= e(url('admin/legal')) ?>"><?= csrf_field() ?><?= $next(1) ?>
<div class="card" style="max-width:820px"><h2>1. <?= e(__('I dati della tua attività')) ?></h2><p class="muted"><?= e(__('Compaiono nelle pagine legali e nelle informazioni obbligatorie per chi vende online.')) ?></p>
  <div class="row"><?= a_input('company', __('Ragione sociale o nome e cognome'), $v('company'), 'text', ['required' => true]) ?><?= a_input('legal_form', __('Forma giuridica'), $v('legal_form'), 'text', ['placeholder' => 'S.r.l., ditta individuale…']) ?></div>
  <?= a_input('address', __('Sede legale (via, CAP, città, paese)'), $v('address')) ?>
  <div class="row"><?= a_input('vat', __('Partita IVA'), $v('vat')) ?><?= a_input('tax_code', __('Codice fiscale'), $v('tax_code')) ?></div>
  <?= a_input('registry', __('Iscrizione registro imprese / REA (se presente)'), $v('registry')) ?>
  <div class="row"><?= a_input('email', __('Email di contatto'), $v('email'), 'email', ['required' => true]) ?><?= a_input('pec', 'PEC', $v('pec'), 'email') ?></div>
  <?= a_input('phone', __('Telefono'), $v('phone')) ?>
  <button class="btn" type="submit"><?= e(__('Continua')) ?> →</button></div></form>

<?php elseif ($step === 2): ?>
<form method="post" action="<?= e(url('admin/legal')) ?>"><?= csrf_field() ?><?= $next(2) ?>
<div class="card" style="max-width:820px"><h2>2. <?= e(__('Spedizioni, resi e garanzia')) ?></h2>
  <div class="row"><?= a_input('ship_min', __('Spedizione: giorni minimi'), $v('ship_min'), 'number', ['min' => 0]) ?><?= a_input('ship_max', __('Spedizione: giorni massimi'), $v('ship_max'), 'number', ['min' => 1]) ?></div>
  <?= a_input('ship_countries', __('Paesi in cui spedisci (testo libero, opzionale)'), $v('ship_countries'), 'text', ['placeholder' => 'Italia, Francia, Germania…']) ?>
  <div class="row"><?= a_input('withdrawal_days', __('Giorni per il recesso'), $v('withdrawal_days'), 'number', ['min' => 0], __('Per legge in UE almeno 14 giorni per i consumatori.')) ?><?= a_input('refund_days', __('Giorni per il rimborso'), $v('refund_days'), 'number', ['min' => 0], __('Entro 14 giorni dalla comunicazione di recesso.')) ?></div>
  <?= a_select('return_shipping', __('Chi paga la spedizione di reso?'), ['customer' => __('Il cliente'), 'seller' => __('Il negozio')], $v('return_shipping')) ?>
  <?= a_input('warranty_months', __('Garanzia legale (mesi)'), $v('warranty_months'), 'number', ['min' => 0], __('In Italia e UE: 24 mesi per i consumatori.')) ?>
  <?= a_check('excluded_custom', __('Vendo anche prodotti personalizzati, sigillati o deperibili (esclusi dal recesso)'), $v('excluded_custom')) ?>
  <a class="btn sec" href="<?= e(url('admin/legal?step=1')) ?>">← <?= e(__('Indietro')) ?></a> <button class="btn" type="submit"><?= e(__('Continua')) ?> →</button></div></form>

<?php elseif ($step === 3): ?>
<form method="post" action="<?= e(url('admin/legal')) ?>"><?= csrf_field() ?><?= $next(3) ?>
<div class="card" style="max-width:820px"><h2>3. <?= e(__('Privacy e cookie')) ?></h2>
  <p><strong><?= e(__('Strumenti rilevati nel tuo negozio')) ?></strong></p>
  <ul class="steps"><li class="done"><span class="dot">✓</span><div><strong><?= e(__('Cookie tecnici (sessione, carrello)')) ?></strong><small><?= e(__('Sempre inclusi nella Cookie Policy.')) ?></small></div></li>
    <li class="<?= $tools['payments'] ? 'done' : '' ?>"><span class="dot"><?= $tools['payments'] ? '✓' : '' ?></span><div><strong><?= e(__('Pagamenti online')) ?>: <?= e($tools['payments'] ? implode(', ', $tools['payments']) : __('nessuno attivo')) ?></strong><small><?= e(__('Vengono citati come destinatari dei dati.')) ?></small></div></li>
    <li class="<?= $tools['analytics'] ? 'done' : '' ?>"><span class="dot"><?= $tools['analytics'] ? '✓' : '' ?></span><div><strong>Google Analytics: <?= e($tools['analytics'] ? __('collegato') : __('non collegato')) ?></strong><small><?php if ($tools['analytics']): ?><?= e(__('Aggiungiamo i cookie analitici e il trasferimento dati extra-UE.')) ?><?php else: ?><a href="<?= e(url('admin/analytics')) ?>"><?= e(__('Collegalo con la procedura guidata')) ?></a><?php endif ?></small></div></li></ul>
  <?php if ($tools['analytics']): ?><div class="field"><label class="check"><input type="hidden" name="analytics_consent" value="0"><input type="checkbox" name="analytics_consent" value="1" <?= setting('analytics_consent', '1') === '1' ? 'checked' : '' ?>> <strong><?= e(__('Mostra il banner di consenso cookie (consigliato in UE)')) ?></strong></label></div><?php endif ?>
  <div class="row"><?= a_input('retention_years', __('Anni di conservazione dei dati fiscali'), $v('retention_years'), 'number', ['min' => 1], __('In Italia 10 anni.')) ?><?= a_input('hosting', __('Fornitore di hosting (opzionale)'), $v('hosting')) ?></div>
  <?= a_check('newsletter', __('Raccolgo iscrizioni a una newsletter'), $v('newsletter')) ?>
  <a class="btn sec" href="<?= e(url('admin/legal?step=2')) ?>">← <?= e(__('Indietro')) ?></a> <button class="btn" type="submit"><?= e(__('Genera anteprima')) ?> →</button></div></form>

<?php else: ?>
<?php if ($done): ?><div class="alert success" style="max-width:820px">✓ <?= e(__('Pagine pubblicate! Sono già nel footer del tuo negozio e collegate dal checkout.')) ?> <a href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= e(__('Vedi il negozio')) ?> ↗</a></div><?php endif ?>
<form method="post" action="<?= e(url('admin/legal')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="publish">
<div class="card" style="max-width:820px"><h2>4. <?= e(__('Controlla e pubblica')) ?></h2>
  <p class="muted"><?= e(__('Scegli quali pagine creare. Le pagine già esistenti verranno sostituite con il nuovo testo.')) ?></p>
  <?php foreach ($preview as $slug => $pg): ?>
    <details style="border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:8px"><summary style="cursor:pointer;display:flex;gap:10px;align-items:center;list-style:none">
      <input type="checkbox" name="pages[]" value="<?= e($slug) ?>" checked data-stop> <strong><?= e($pg['title']) ?></strong>
      <?php if (in_array($slug, $existing, true)): ?><span class="pill warn"><?= e(__('sostituisce la pagina esistente')) ?></span><?php else: ?><span class="pill ok"><?= e(__('nuova')) ?></span><?php endif ?></summary>
      <div style="margin-top:10px;max-height:360px;overflow:auto;font-size:.9rem" class="legal-preview"><?= $pg['content'] ?></div></details>
  <?php endforeach ?>
  <div style="margin-top:14px"><a class="btn sec" href="<?= e(url('admin/legal?step=3')) ?>">← <?= e(__('Indietro')) ?></a> <button class="btn" type="submit">✓ <?= e(__('Pubblica le pagine selezionate')) ?></button></div></div></form>
<?php endif ?>
