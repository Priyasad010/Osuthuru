<?php

use App\Core\View;

$blocked = $customer['status'] === 'blocked';
?>
<?= View::partial('admin_heading', ['title' => (string)$customer['name'], 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>

<div class="card border-0 shadow-sm p-3 mb-3">
    <p class="small mb-1"><strong><?= e(t('field_email')) ?>:</strong> <?= e($customer['email']) ?></p>
    <p class="small mb-1"><strong><?= e(t('phone_label')) ?></strong> <?= e($customer['phone'] ?: '-') ?></p>
    <p class="small mb-1"><strong><?= e(t('address_label')) ?></strong> <?= e($customer['address'] ?: '-') ?></p>
    <p class="small mb-3"><strong><?= e(t('col_status')) ?>:</strong> <?= e(t_or('user_', $customer['status'])) ?></p>
    <form method="POST" action="/admin/users/block" onsubmit="return confirm(<?= e(json_encode(t($blocked ? 'admin_unblock_confirm' : 'admin_block_confirm'))) ?>);">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$customer['user_id'] ?>">
        <input type="hidden" name="blocked" value="<?= $blocked ? '0' : '1' ?>">
        <button type="submit" class="btn btn-sm <?= $blocked ? 'btn-outline-success' : 'btn-danger' ?>"><?= e(t($blocked ? 'admin_unblock' : 'admin_block')) ?></button>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr><th><?= e(t('col_order')) ?></th><th><?= e(t('col_date')) ?></th><th><?= e(t('col_method')) ?></th><th><?= e(t('col_status')) ?></th><th class="text-end"><?= e(t('col_total')) ?></th></tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?><tr><td colspan="5" class="text-center text-muted py-4"><?= e(t('report_none')) ?></td></tr><?php endif; ?>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= (int)$o['order_id'] ?></td>
                        <td><?= e($o['order_date']) ?></td>
                        <td><?= e(t_or('method_', $o['payment_method'])) ?></td>
                        <td><?= e(t_or('status_', $o['status'])) ?></td>
                        <td class="text-end"><?= e(format_price((float)$o['total_price'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-danger shadow-sm p-3 mt-3">
    <h3 class="h6 fw-bold text-danger"><?= e(t('admin_user_delete')) ?></h3>
    <?php if (!empty($hasOpenOrders)): ?>
        <p class="small mb-0"><?= e(t('admin_user_delete_open')) ?></p>
    <?php else: ?>
        <p class="small"><?= e(t('admin_user_delete_warning', ['orders' => count($orders)])) ?></p>
        <form method="POST" action="/admin/users/delete" class="row g-2 align-items-start" onsubmit="return confirm(<?= e(json_encode(t('admin_user_delete_confirm'))) ?>);">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$customer['user_id'] ?>">
            <div class="col-md-5">
                <label for="confirm_email" class="form-label small fw-semibold"><?= e(t('admin_user_delete_type', ['email' => (string)$customer['email']])) ?></label>
                <input type="email" class="form-control form-control-sm" id="confirm_email" name="confirm_email" required maxlength="255" autocomplete="off">
            </div>
            <div class="col-md-4">
                <label for="admin_password" class="form-label small fw-semibold"><?= e(t('admin_user_delete_password')) ?></label>
                <input type="password" class="form-control form-control-sm" id="admin_password" name="admin_password" required maxlength="72" autocomplete="current-password">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-danger btn-sm"><?= e(t('admin_user_delete_button')) ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>
