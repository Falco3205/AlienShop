<?php
$d = $data;
$val = static fn(string $k, mixed $def = '') => e($d[$k] ?? $def);
$allOk = true;
foreach ($requirements as [, $ok, $required]) { if ($required && !$ok) { $allOk = false; } }
?>
<div class="lang"><a href="?lang=it">Italiano</a> · <a href="?lang=en">English</a></div>
<h1><?= e(__('Installazione guidata')) ?></h1>
<p class="sub"><?= e(__('Configura il tuo negozio in pochi minuti.')) ?></p>
<div class="steps" id="steps"><i class="on"></i><i></i><i></i><i></i><i></i></div>
<?php if ($errors): ?><div class="errors" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form method="post" action="<?= e(url('install')) ?>" id="wizard" autocomplete="off">
<input type="hidden" name="lang" value="<?= e($lang) ?>">
<?php if (!empty($keyRequired)): ?><div class="field"><label><?= e(__('Chiave di installazione')) ?></label><input type="password" name="install_key" autocomplete="off" required><div class="hint"><?= e(__('La trovi nel file storage/install.key sul server.')) ?></div></div><?php endif ?>

<section class="step active" data-step="1">
  <h2>1. <?= e(__('Requisiti di sistema')) ?></h2>
  <ul class="req"><?php foreach ($requirements as [$label, $ok, $required]): ?><li><span><?= e($label) ?></span><span class="<?= $ok ? 'ok' : ($required ? 'bad' : 'warn') ?>"><?= $ok ? '✓' : ($required ? '✗' : '!') ?></span></li><?php endforeach ?></ul>
  <?php if (!$allOk): ?><div class="errors"><?= e(__('Risolvi i requisiti mancanti e ricarica la pagina.')) ?></div><?php endif ?>
</section>

<section class="step" data-step="2">
  <h2>2. <?= e(__('Database')) ?></h2>
  <div class="choice">
    <label><input type="radio" name="db_driver" value="sqlite" <?= ($d['db_driver'] ?? '') === 'sqlite' ? 'checked' : '' ?>> SQLite <div class="hint"><?= e(__('Zero configurazione, ideale per iniziare.')) ?></div></label>
    <label><input type="radio" name="db_driver" value="mysql" <?= ($d['db_driver'] ?? '') === 'mysql' ? 'checked' : '' ?>> MySQL / MariaDB <div class="hint"><?= e(__('Consigliato per cataloghi grandi.')) ?></div></label>
  </div>
  <div id="mysql-fields">
    <div class="row"><div class="field"><label><?= e(__('Host')) ?></label><input type="text" name="db_host" value="<?= $val('db_host', 'localhost') ?>"></div><div class="field"><label><?= e(__('Porta')) ?></label><input type="number" name="db_port" value="<?= $val('db_port', 3306) ?>"></div></div>
    <div class="field"><label><?= e(__('Nome database')) ?></label><input type="text" name="db_name" value="<?= $val('db_name') ?>"></div>
    <div class="row"><div class="field"><label><?= e(__('Utente')) ?></label><input type="text" name="db_user" value="<?= $val('db_user') ?>"></div><div class="field"><label><?= e(__('Password')) ?></label><input type="password" name="db_pass"></div></div>
  </div>
  <button type="button" class="btn sec" id="test-db"><?= e(__('Verifica connessione')) ?></button> <span id="db-result"></span>
</section>

<section class="step" data-step="3">
  <h2>3. <?= e(__('Il tuo negozio')) ?></h2>
  <div class="field"><label><?= e(__('Nome del negozio')) ?> *</label><input type="text" name="store_name" required value="<?= $val('store_name') ?>"></div>
  <div class="field"><label><?= e(__('Slogan')) ?></label><input type="text" name="store_tagline" value="<?= $val('store_tagline') ?>"></div>
  <div class="field"><label><?= e(__('Email del negozio')) ?> *</label><input type="email" name="store_email" required value="<?= $val('store_email') ?>"><div class="hint"><?= e(__('Riceverai qui le notifiche degli ordini.')) ?></div></div>
  <div class="row">
    <div class="field"><label><?= e(__('Valuta')) ?></label><select name="currency"><?php foreach ($currencies as $c): ?><option <?= ($d['currency'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach ?></select></div>
    <div class="field"><label><?= e(__('Paese')) ?></label><select name="country"><?php foreach ($countries as $code => $name): ?><option value="<?= $code ?>" <?= ($d['country'] ?? '') === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach ?></select></div>
  </div>
  <div class="row">
    <div class="field"><label><?= e(__('Aliquota IVA / tasse (%)')) ?></label><input type="number" step="0.01" min="0" max="100" name="tax_rate" value="<?= $val('tax_rate', 22) ?>"></div>
    <div class="field"><label><?= e(__('Prezzi')) ?></label><select name="prices_include_tax"><option value="1" <?= !empty($d['prices_include_tax']) ? 'selected' : '' ?>><?= e(__('Tasse incluse nei prezzi')) ?></option><option value="0" <?= empty($d['prices_include_tax']) ? 'selected' : '' ?>><?= e(__('Tasse escluse (aggiunte al checkout)')) ?></option></select></div>
  </div>
  <div class="field"><label><?= e(__('URL del sito')) ?></label><input type="url" name="app_url" value="<?= $val('app_url') ?>"><div class="hint"><?= e(__('Verrà usato per sitemap, link e pagamenti.')) ?></div></div>
  <label style="font-weight:400"><input type="checkbox" name="demo" value="1" <?= !empty($d['demo']) ? 'checked' : '' ?>> <?= e(__('Installa contenuti dimostrativi (categorie e prodotti di esempio)')) ?></label>
</section>

<section class="step" data-step="4">
  <h2>4. <?= e(__('Account amministratore')) ?></h2>
  <div class="field"><label><?= e(__('Nome')) ?></label><input type="text" name="admin_name" value="<?= $val('admin_name') ?>"></div>
  <div class="field"><label><?= e(__('Email')) ?> *</label><input type="email" name="admin_email" required value="<?= $val('admin_email') ?>"></div>
  <div class="row"><div class="field"><label><?= e(__('Password')) ?> * (min. 8)</label><input type="password" name="admin_password" minlength="8" required autocomplete="new-password"></div>
  <div class="field"><label><?= e(__('Conferma password')) ?> *</label><input type="password" name="admin_password2" minlength="8" required autocomplete="new-password"></div></div>
</section>

<section class="step" data-step="5">
  <h2>5. <?= e(__('Scegli il tema')) ?></h2>
  <p class="sub"><?= e(__('Potrai cambiarlo e personalizzarlo in qualsiasi momento.')) ?></p>
  <div class="themes"><?php foreach ($themes as $slug => $t): ?>
    <label class="theme"><input type="radio" name="theme" value="<?= e($slug) ?>" <?= ($d['theme'] ?? 'aurora') === $slug ? 'checked' : '' ?>>
      <div class="sw"><?php foreach ($t['colors'] ?? [] as $c): ?><span style="background:<?= e($c) ?>"></span><?php endforeach ?></div>
      <b><?= e($t['name']) ?></b><small><?= e($t['description'] ?? '') ?></small></label>
  <?php endforeach ?></div>
</section>

<div class="nav">
  <button type="button" class="btn sec" id="prev" hidden><?= e(__('Indietro')) ?></button>
  <button type="button" class="btn" id="next" <?= !$allOk ? 'disabled' : '' ?>><?= e(__('Avanti')) ?></button>
  <button type="submit" class="btn" id="submit" hidden><?= e(__('Installa')) ?></button>
</div>
</form>
<script nonce="<?= e(csp_nonce()) ?>">
(function(){
  var root=document.documentElement;root.classList.add('js');
  var form=document.getElementById('wizard'),steps=[].slice.call(form.querySelectorAll('.step')),bars=document.querySelectorAll('#steps i');
  var cur=<?= $errors ? 3 : 0 ?>;
  var prev=document.getElementById('prev'),next=document.getElementById('next'),submit=document.getElementById('submit');
  function show(){steps.forEach(function(s,i){s.classList.toggle('active',i===cur)});bars.forEach(function(b,i){b.classList.toggle('on',i<=cur)});
    prev.hidden=cur===0;next.hidden=cur===steps.length-1;submit.hidden=cur!==steps.length-1;window.scrollTo(0,0);}
  function valid(){var ok=true;[].slice.call(steps[cur].querySelectorAll('input,select')).forEach(function(i){if(!i.checkValidity()){if(ok)i.reportValidity();ok=false;}});return ok;}
  prev.onclick=function(){cur=Math.max(0,cur-1);show()};
  next.onclick=function(){if(valid()){cur=Math.min(steps.length-1,cur+1);show()}};
  function toggleDb(){document.getElementById('mysql-fields').style.display=form.db_driver.value==='mysql'?'block':'none'}
  [].forEach.call(form.querySelectorAll('input[name=db_driver]'),function(r){r.onchange=toggleDb});toggleDb();
  document.getElementById('test-db').onclick=function(){
    var out=document.getElementById('db-result');out.textContent='…';
    fetch(<?= json_encode(url('install/test-db'), JSON_UNESCAPED_SLASHES) ?>,{method:'POST',body:new FormData(form)}).then(function(r){return r.json()}).then(function(j){out.className=j.ok?'ok':'bad';out.textContent=(j.ok?'✓ ':'✗ ')+j.message}).catch(function(){out.className='bad';out.textContent='✗'});
  };
  show();
})();
</script>
