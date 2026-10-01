<div class="grid2"><div class="card" style="padding:0"><table><thead><tr><th><?= e(__('Messaggio')) ?></th><th><?= e(__('Stato')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($rows as $id => $r): ?><tr>
<td><strong><?= e($r['label']) ?></strong><div class="muted"><?= e($r['hint']) ?></div></td>
<td><?= $r['enabled'] ? '<span class="pill ok">' . e(__('Attiva')) . '</span>' : '<span class="pill warn">' . e(__('Disattivata')) . '</span>' ?><?= $r['customized'] ? ' <span class="pill info">' . e(__('Personalizzata')) . '</span>' : '' ?></td>
<td class="right"><a class="btn sec sm" href="<?= e(url('admin/emails/' . $id)) ?>"><?= e(__('Modifica')) ?></a></td></tr><?php endforeach ?></tbody></table></div>
<div class="card"><h2><?= e(__('Aspetto di tutte le email')) ?></h2><form method="post" action="<?= e(url('admin/emails/design')) ?>"><?= csrf_field() ?>
<?= a_input('mail_color', __('Colore principale'), $color, 'color') ?>
<?= a_textarea('mail_footer', __('Testo a piè di pagina'), $footer, 3, e(__('Per esempio ragione sociale, indirizzo, contatti. Se vuoto compare il nome del negozio.'))) ?>
<p class="muted"><?= e(__('Il logo è quello impostato in "Aspetto e temi".')) ?></p>
<button class="btn" type="submit"><?= e(__('Salva')) ?></button></form></div></div>
