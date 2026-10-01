<div class="auth-box">
  <h1><?= e(__('Crea account')) ?></h1>
  <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="<?= e(url('account/register')) ?>"><?= csrf_field() ?>
    <div class="field"><label for="name"><?= e(__('Nome e cognome')) ?></label><input id="name" name="name" required autocomplete="name" value="<?= e($name ?? '') ?>"></div>
    <div class="field"><label for="email"><?= e(__('Email')) ?></label><input id="email" type="email" name="email" required autocomplete="email" value="<?= e($email ?? '') ?>"></div>
    <div class="field"><label for="password"><?= e(__('Password')) ?> (min. 8)</label><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
    <button class="btn btn-block" type="submit"><?= e(__('Registrati')) ?></button>
  </form>
  <p style="margin-top:16px"><?= e(__('Hai già un account?')) ?> <a href="<?= e(url('account/login')) ?>"><?= e(__('Accedi')) ?></a></p>
</div>
