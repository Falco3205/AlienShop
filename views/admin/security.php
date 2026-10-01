<div class="grid2"><div>
<div class="card"><h2><?= e(__('Verifica in due passaggi')) ?> <?= $enabled ? '<span class="pill ok">' . e(__('Attiva')) . '</span>' : '<span class="pill warn">' . e(__('Non attiva')) . '</span>' ?></h2>
<?php if ($codes): ?>
  <div class="alert info"><strong><?= e(__('Codici di recupero')) ?></strong><br><?= e(__('Conservali in un posto sicuro: ognuno vale una sola volta e non verranno più mostrati.')) ?>
  <pre style="font-size:1.05rem;line-height:1.7;margin:10px 0 0"><?= e(implode("\n", $codes)) ?></pre></div>
<?php endif ?>
<?php if (!$enabled && !$pending): ?>
  <p><?= e(__('Oltre alla password serve un codice a 6 cifre generato da un\'app (Google Authenticator, Authy, 1Password…). Chi scopre la tua password non può entrare.')) ?></p>
  <form method="post" action="<?= e(url('admin/security/2fa/start')) ?>"><?= csrf_field() ?>
  <?= a_input('password', __('La tua password'), '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
  <button class="btn" type="submit"><?= e(__('Attiva')) ?></button></form>
<?php elseif (!$enabled && $pending): ?>
  <p><?= e(__('1. Nell\'app scegli "Aggiungi account" e inserisci questa chiave a mano (oppure apri il link dal telefono):')) ?></p>
  <p><code style="font-size:1.1rem;letter-spacing:2px"><?= e(implode(' ', str_split($pending, 4))) ?></code></p>
  <p class="muted" style="word-break:break-all"><a href="<?= e($uri) ?>"><?= e(__('Apri nell\'app di autenticazione')) ?></a></p>
  <p><?= e(__('2. Inserisci il codice che l\'app ti mostra per confermare:')) ?></p>
  <form method="post" action="<?= e(url('admin/security/2fa/confirm')) ?>"><?= csrf_field() ?>
  <?= a_input('code', __('Codice a 6 cifre'), '', 'text', ['required' => true, 'inputmode' => 'numeric', 'maxlength' => 8, 'autocomplete' => 'one-time-code']) ?>
  <button class="btn" type="submit"><?= e(__('Conferma e attiva')) ?></button></form>
<?php else: ?>
  <p><?= e(__('L\'accesso richiede la password e il codice dell\'app.')) ?></p>
  <form method="post" action="<?= e(url('admin/security/2fa/recovery')) ?>" style="margin-bottom:18px"><?= csrf_field() ?>
  <?= a_input('password', __('La tua password'), '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
  <button class="btn sec" type="submit"><?= e(__('Genera nuovi codici di recupero')) ?></button></form>
  <form method="post" action="<?= e(url('admin/security/2fa/disable')) ?>"><?= csrf_field() ?>
  <?= a_input('password', __('La tua password'), '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
  <?= a_input('code', __('Codice a 6 cifre'), '', 'text', ['required' => true, 'inputmode' => 'numeric', 'maxlength' => 12]) ?>
  <button class="btn sec" type="submit"><?= e(__('Disattiva')) ?></button></form>
<?php endif ?></div></div>
<div><div class="card"><h2><?= e(__('Protezione dei contenuti (CSP)')) ?></h2>
<p class="muted"><?= e(__('Il browser carica solo script e risorse da fonti autorizzate: limita i danni di eventuali iniezioni di codice. Se inserisci codice di terze parti (pixel, chat, mappe) autorizza qui i loro domini.')) ?></p>
<form method="post" action="<?= e(url('admin/security/csp')) ?>"><?= csrf_field() ?>
<?= a_select('csp', __('Modalità'), ['standard' => __('Attiva (consigliata)'), 'off' => __('Disattivata')], $csp) ?>
<?= a_textarea('csp_extra', __('Domini aggiuntivi consentiti (uno per riga)'), $cspExtra, 4, e(__('Esempio: https://connect.facebook.net')) ) ?>
<button class="btn" type="submit"><?= e(__('Salva')) ?></button></form></div>
<div class="card"><h2><?= e(__('Se perdi il telefono')) ?></h2><p><?= e(__('Usa un codice di recupero al posto del codice a 6 cifre. Se non li hai più, da terminale sul server:')) ?></p><pre>php bin/console user:2fa-off email@dominio.it</pre></div></div></div>
