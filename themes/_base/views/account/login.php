<div class="auth-box">
  <h1><?= e(__('Accedi')) ?></h1>
  <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="<?= e(url('account/login')) ?>"><?= csrf_field() ?>
    <div class="field"><label for="email"><?= e(__('Email')) ?></label><input id="email" type="email" name="email" required autocomplete="email" value="<?= e($email ?? '') ?>"></div>
    <div class="field"><label for="password"><?= e(__('Password')) ?></label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
    <button class="btn btn-block" type="submit"><?= e(__('Accedi')) ?></button>
  </form>
  <p style="margin-top:16px"><a href="<?= e(url('account/forgot')) ?>"><?= e(__('Password dimenticata?')) ?></a></p>
  <p><?= e(__('Non hai un account?')) ?> <a href="<?= e(url('account/register')) ?>"><?= e(__('Registrati')) ?></a></p>
</div>
