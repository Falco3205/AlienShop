<h1><?= e(__('Contatti')) ?></h1>
<?php if (!empty($sent)): ?><div class="alert alert-success" role="status"><?= e(__('Messaggio inviato. Ti risponderemo il prima possibile.')) ?></div><?php endif ?>
<?php if (!empty($errors)): ?><div class="alert alert-error" role="alert"><ul style="margin:0;padding-left:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form action="<?= e(url('contact')) ?>" method="post" style="max-width:560px">
  <?= csrf_field() ?>
  <div class="field"><label for="c-name"><?= e(__('Nome')) ?></label><input id="c-name" name="name" required autocomplete="name" value="<?= e(old('name')) ?>"></div>
  <div class="field"><label for="c-email"><?= e(__('Email')) ?></label><input id="c-email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div>
  <div class="field"><label for="c-msg"><?= e(__('Messaggio')) ?></label><textarea id="c-msg" name="message" rows="6" required><?= e(old('message')) ?></textarea></div>
  <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
  <button class="btn" type="submit"><?= e(__('Invia messaggio')) ?></button>
</form>
<?php unset($_SESSION['_old']) ?>
