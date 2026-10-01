<div class="auth-box">
  <h1><?= e(__('Password dimenticata')) ?></h1>
  <?php if (!empty($sent)): ?><div class="alert alert-success"><?= e(__('Se l\'indirizzo è registrato, riceverai un\'email con le istruzioni.')) ?></div><?php endif ?>
  <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="<?= e(url('account/forgot')) ?>"><?= csrf_field() ?>
    <div class="field"><label for="email"><?= e(__('Email')) ?></label><input id="email" type="email" name="email" required autocomplete="email"></div>
    <button class="btn btn-block" type="submit"><?= e(__('Invia link di reimpostazione')) ?></button>
  </form>
  <p style="margin-top:16px"><a href="<?= e(url('account/login')) ?>"><?= e(__('Torna al login')) ?></a></p>
</div>
