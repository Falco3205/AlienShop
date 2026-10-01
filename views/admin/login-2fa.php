<!doctype html>
<html lang="<?= e(Alien\Core\Lang::locale()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e(__('Verifica in due passaggi')) ?></title><link rel="stylesheet" href="<?= e(asset('admin.css')) ?>?v=<?= ALIEN_VERSION ?>"></head>
<body><div class="login"><div class="card">
  <h2 style="font-size:1.4rem"><?= e(__('Verifica in due passaggi')) ?></h2><p class="muted"><?= e(__('Inserisci il codice a 6 cifre della tua app di autenticazione, oppure un codice di recupero.')) ?></p>
  <?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="<?= e(url('admin/login/2fa')) ?>"><?= csrf_field() ?>
    <?= a_input('code', __('Codice'), '', 'text', ['required' => true, 'autofocus' => true, 'autocomplete' => 'one-time-code', 'inputmode' => 'numeric', 'maxlength' => 12]) ?>
    <button class="btn" style="width:100%;justify-content:center" type="submit"><?= e(__('Verifica')) ?></button>
  </form>
  <p style="margin-top:14px;font-size:.9rem"><a href="<?= e(url('admin/login')) ?>"><?= e(__('Torna all\'accesso')) ?></a></p>
</div></div></body></html>
