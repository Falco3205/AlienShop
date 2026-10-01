<div class="auth-box">
  <h1><?= e(__('Reimposta password')) ?></h1>
  <?php if (!$valid): ?><div class="alert alert-error"><?= e(__('Il link non è valido o è scaduto.')) ?></div><p><a href="<?= e(url('account/forgot')) ?>"><?= e(__('Richiedi un nuovo link')) ?></a></p>
  <?php else: ?>
  <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="<?= e(url('account/reset/' . $token)) ?>"><?= csrf_field() ?>
    <div class="field"><label for="password"><?= e(__('Nuova password')) ?> (min. 8)</label><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
    <button class="btn btn-block" type="submit"><?= e(__('Salva password')) ?></button>
  </form>
  <?php endif ?>
</div>
