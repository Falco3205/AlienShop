<p><a href="<?= e(url('admin/emails')) ?>">← <?= e(__('Tutte le email')) ?></a></p>
<div class="grid2"><div class="card">
<form id="mail-form" method="post" action="<?= e(url('admin/emails/' . $id)) ?>"><?= csrf_field() ?>
<?= a_check('enabled', __('Invia questa email'), $enabled) ?>
<?= a_input('subject', __('Oggetto'), $t['subject']) ?>
<?= a_textarea('body', __('Testo'), $t['body'], 12, e(__('Lascia una riga vuota per iniziare un nuovo paragrafo.'))) ?>
<div class="field"><label><?= e(__('Segnaposto (clicca per inserirli)')) ?></label><div id="chips" style="display:flex;flex-wrap:wrap;gap:6px">
<?php foreach ($t['vars'] as $v): ?><button type="button" class="btn sec sm" data-ins="{<?= e($v) ?>}" title="<?= e($help[$v] ?? '') ?>">{<?= e($v) ?>}</button><?php endforeach ?></div></div>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<button class="btn" type="submit"><?= e(__('Salva')) ?></button>
<button class="btn sec" type="submit" formaction="<?= e(url('admin/emails/' . $id . '/preview')) ?>" formtarget="mail-preview"><?= e(__('Anteprima')) ?></button>
<button class="btn sec" type="submit" formaction="<?= e(url('admin/emails/' . $id . '/test')) ?>"><?= e(__('Invia una prova a me')) ?></button></div></form>
<form method="post" action="<?= e(url('admin/emails/' . $id . '/reset')) ?>" style="margin-top:14px" onsubmit="return confirm('<?= e(__('Tornare al testo originale?')) ?>')"><?= csrf_field() ?><button class="btn sec sm" type="submit"><?= e(__('Ripristina il testo originale')) ?></button></form></div>
<div class="card"><h2><?= e(__('Anteprima')) ?></h2><iframe name="mail-preview" src="about:blank" style="width:100%;height:560px;border:1px solid #e5e7eb;border-radius:8px;background:#fff" title="<?= e(__('Anteprima')) ?>"></iframe></div></div>
<script>
(function () {
  var body = document.getElementById('f_body');
  document.querySelectorAll('[data-ins]').forEach(function (b) {
    b.addEventListener('click', function () {
      var s = body.selectionStart, e = body.selectionEnd, t = b.dataset.ins;
      body.value = body.value.slice(0, s) + t + body.value.slice(e);
      body.focus(); body.selectionStart = body.selectionEnd = s + t.length;
    });
  });
  document.getElementById('mail-form').querySelector('[formtarget]').click();
})();
</script>
