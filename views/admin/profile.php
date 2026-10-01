<form method="post" action="<?= e(url('admin/profile')) ?>"><?= csrf_field() ?>
<div class="card" style="max-width:560px">
<?= a_input('name', __('Nome'), $u['name']) ?>
<?= a_input('email', __('Email'), $u['email'], 'email', ['required' => true]) ?>
<?= a_input('new_password', __('Nuova password'), '', 'password', ['autocomplete' => 'new-password'], __('Lascia vuoto per non cambiarla.')) ?>
<?= a_input('current_password', __('Password attuale (obbligatoria per confermare)'), '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
<button class="btn" type="submit"><?= e(__('Salva')) ?></button>
</div></form>
<div class="card" style="max-width:560px"><h2><?= e(__('Sicurezza')) ?></h2><p><a class="btn sec" href="<?= e(url('admin/security')) ?>"><?= e(__('Verifica in due passaggi')) ?></a></p></div>
