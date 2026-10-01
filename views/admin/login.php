<!doctype html>
<html lang="<?= e(Alien\Core\Lang::locale()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e(__('Accesso amministrazione')) ?></title><link rel="stylesheet" href="<?= e(asset('admin.css')) ?>?v=<?= ALIEN_VERSION ?>"></head>
<body><div class="login"><div class="card">
  <h2 style="font-size:1.4rem">AlienShop</h2><p class="muted"><?= e(__('Accedi al pannello di amministrazione')) ?></p>
  <?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="<?= e(url('admin/login')) ?>"><?= csrf_field() ?>
    <?= a_input('email', __('Email'), $email ?? '', 'email', ['required' => true, 'autofocus' => true, 'autocomplete' => 'username']) ?>
    <?= a_input('password', __('Password'), '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
    <button class="btn" style="width:100%;justify-content:center" type="submit"><?= e(__('Accedi')) ?></button>
  </form>
  <p style="margin-top:14px;font-size:.9rem"><a href="<?= e(url('account/forgot')) ?>"><?= e(__('Password dimenticata?')) ?></a></p>
</div></div></body></html>
