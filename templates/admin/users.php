<?php use App\Core\View; ?>
<?= View::partial('admin_heading', ['title' => t('admin_users'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>

<form method="GET" action="/admin/users" class="d-flex gap-2 mb-3">
    <input type="search" name="q" class="form-control form-control-sm" value="<?= e($search ?? '') ?>" placeholder="<?= e(t('admin_user_search')) ?>" aria-label="<?= e(t('admin_user_search')) ?>">
    <button type="submit" class="btn btn-brand btn-sm"><?= e(t('search_button')) ?></button>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th><?= e(t('field_name')) ?></th><th><?= e(t('field_email')) ?></th><th><?= e(t('col_status')) ?></th>
                    <th class="text-end"><?= e(t('report_orders')) ?></th><th class="text-end"><?= e(t('admin_spent')) ?></th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(t('report_none')) ?></td></tr><?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= e($u['name']) ?></td>
                        <td class="text-break"><?= e($u['email']) ?></td>
                        <td><span class="badge <?= ['active' => 'bg-success', 'blocked' => 'bg-danger'][$u['status']] ?? 'bg-secondary' ?>"><?= e(t_or('user_', $u['status'])) ?></span></td>
                        <td class="text-end"><?= (int)$u['orders'] ?></td>
                        <td class="text-end"><?= e(format_price((float)$u['spent'])) ?></td>
                        <td class="text-end"><a href="/admin/users/<?= (int)$u['user_id'] ?>" class="btn btn-outline-success btn-sm"><?= e(t('view_details')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
