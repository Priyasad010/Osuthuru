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

<?php $filters = $filters ?? []; ?>
<form method="GET" action="/admin/orders" class="card border-0 shadow-sm p-3 mb-3 row g-2 flex-row align-items-end">
    <div class="col-6 col-md-2">
        <label class="form-label small" for="from"><?= e(t('report_from')) ?></label>
        <input type="date" class="form-control form-control-sm" id="from" name="from" value="<?= e($filters['from'] ?? '') ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="to"><?= e(t('report_to')) ?></label>
        <input type="date" class="form-control form-control-sm" id="to" name="to" value="<?= e($filters['to'] ?? '') ?>">
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small" for="status"><?= e(t('col_status')) ?></label>
        <select class="form-select form-select-sm" id="status" name="status">
            <option value=""><?= e(t('filter_all')) ?></option>
            <?php foreach (OrderService::STATUSES as $st): ?>
                <option value="<?= e($st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(t_or('status_', $st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small" for="method"><?= e(t('col_method')) ?></label>
        <select class="form-select form-select-sm" id="method" name="method">
            <option value=""><?= e(t('filter_all')) ?></option>
            <?php foreach (['cod', 'bank_transfer', 'card', 'payhere'] as $method): ?>
                <option value="<?= $method ?>" <?= ($filters['method'] ?? '') === $method ? 'selected' : '' ?>><?= e(t_or('method_', $method)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><button type="submit" class="btn btn-suwoda btn-sm w-100"><?= e(t('report_show')) ?></button></div>
</form>

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
