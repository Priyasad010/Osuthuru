<?php use App\Service\OrderService; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
        <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_orders')) ?></h2>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th><?= e(t('col_order')) ?></th>
                    <th><?= e(t('admin_customer')) ?></th>
                    <th><?= e(t('district')) ?></th>
                    <th><?= e(t('admin_method_payment')) ?></th>
                    <th><?= e(t('col_total')) ?></th>
                    <th><?= e(t('admin_current_status')) ?></th>
                    <th><?= e(t('admin_update_status')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td class="fw-bold">#<?= (int)$o['order_id'] ?></td>
                        <td>
                            <div><?= e($o['shipping_name'] ?? $o['user_name']) ?></div>
                            <span class="text-muted"><?= e($o['shipping_phone'] ?? '') ?></span>
                        </td>
                        <td><?= e($o['district'] ?? '-') ?></td>
                        <td>
                            <div class="fw-semibold"><?= e(t_or('method_', $o['payment_method'])) ?></div>
                            <span class="badge <?= $o['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= e(t_or('payment_', $o['payment_status'])) ?>
                            </span>
                        </td>
                        <td class="fw-semibold"><?= e(format_price((float)$o['total_price'])) ?></td>
                        <td><span class="badge bg-info text-dark"><?= e(t_or('status_', $o['status'])) ?></span></td>
                        <td>
                            <form method="POST" action="/admin/orders/status" class="d-flex gap-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                                <select name="status" class="form-select form-select-sm" style="width:140px;">
                                    <?php foreach (OrderService::STATUSES as $st): ?>
                                        <option value="<?= e($st) ?>" <?= $o['status'] === $st ? 'selected' : '' ?>><?= e(t_or('status_', $st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="note" class="form-control form-control-sm" placeholder="<?= e(t('admin_note_placeholder')) ?>" style="width:130px;">
                                <button type="submit" class="btn btn-sm btn-suwoda"><?= e(t('save')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
