<div class="card" style="padding:0"><table><thead><tr><th><?= e(__('Titolo')) ?></th><th><?= e(__('Tipo')) ?></th><th>URL</th><th><?= e(__('Stato')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $p): ?><tr><td><a href="<?= e(url('admin/pages/' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a></td><td><?= e($p['type'] === 'post' ? __('Articolo') : __('Pagina')) ?></td><td class="muted">/<?= $p['type'] === 'post' ? 'blog' : 'pages' ?>/<?= e($p['slug']) ?></td><td><?= a_status($p['is_active'] ? 'active' : 'draft') ?></td></tr><?php endforeach ?>
</tbody></table></div>
