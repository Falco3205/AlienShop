<!doctype html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Accesso — AlienShop Hub</title><link rel="stylesheet" href="<?= e(url('assets/admin.css')) ?>?v=<?= ALIEN_VERSION ?>"></head>
<body><div class="login"><div class="card">
  <h2 style="font-size:1.4rem">AlienShop Hub</h2><p class="muted">Pannello di gestione dei negozi</p>
  <?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="<?= e(url('login')) ?>"><?= csrf_field() ?>
    <?= a_input('email', 'Email', $email ?? '', 'email', ['required' => true, 'autofocus' => true, 'autocomplete' => 'username']) ?>
    <?= a_input('password', 'Password', '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
    <button class="btn" style="width:100%;justify-content:center" type="submit">Accedi</button>
  </form>
</div></div></body></html>
