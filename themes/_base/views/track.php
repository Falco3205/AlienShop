<h1><?= e(__('Traccia il tuo ordine')) ?></h1>
<p><?= e(__('Inserisci il numero d\'ordine e l\'email usata per l\'acquisto.')) ?></p>
<?php if (!empty($errors)): ?><div class="alert alert-error" role="alert"><ul style="margin:0;padding-left:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form action="<?= e(url('track')) ?>" method="post" style="max-width:420px">
  <?= csrf_field() ?>
  <div class="field"><label for="t-num"><?= e(__('Numero ordine')) ?></label><input id="t-num" name="number" required value="<?= e(old('number')) ?>"></div>
  <div class="field"><label for="t-email"><?= e(__('Email')) ?></label><input id="t-email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div>
  <button class="btn" type="submit"><?= e(__('Cerca ordine')) ?></button>
</form>
<?php unset($_SESSION['_old']) ?>
