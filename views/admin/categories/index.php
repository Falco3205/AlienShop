<div class="card" style="padding:0"><table>
<thead><tr><th><?= e(__('Nome')) ?></th><th>Slug</th><th><?= e(__('Prodotti')) ?></th><th><?= e(__('Stato')) ?></th></tr></thead><tbody>
<?php foreach ($options as $id => $label): $c = $all[$id]; ?>
<tr><td><a href="<?= e(url('admin/categories/' . $id)) ?>"><?= e($label) ?></a></td><td class="muted"><?= e($c['slug']) ?></td><td><?= (int)($counts[$id] ?? 0) ?></td><td><?= a_status($c['is_active'] ? 'active' : 'draft') ?></td></tr>
<?php endforeach ?>
<?php if (!$options): ?><tr><td colspan="4" class="muted"><?= e(__('Nessuna categoria.')) ?></td></tr><?php endif ?></tbody></table></div>
