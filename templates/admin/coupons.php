<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
        <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_coupons')) ?></h2>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm p-4 mb-4">
    <h6 class="fw-bold text-success mb-3"><?= e(t('admin_new_coupon')) ?></h6>
    <form method="POST" action="/admin/coupons/create" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-2">
            <label class="form-label small fw-semibold"><?= e(t('admin_code')) ?></label>
            <input type="text" name="code" class="form-control form-control-sm" placeholder="AYUR10" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold"><?= e(t('admin_type')) ?></label>
            <select name="type" class="form-select form-select-sm">
                <option value="percent"><?= e(t('admin_percent')) ?></option>
                <option value="fixed"><?= e(t('admin_fixed')) ?></option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold"><?= e(t('admin_value')) ?></label>
            <input type="number" step="0.01" name="value" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold"><?= e(t('admin_min_order')) ?></label>
            <input type="number" step="0.01" name="min_order" value="0" class="form-control form-control-sm">
        </div>
        <div class="col-md-1">
            <label class="form-label small fw-semibold"><?= e(t('admin_max_uses')) ?></label>
            <input type="number" name="max_uses" value="100" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold"><?= e(t('admin_valid_until')) ?></label>
            <input type="datetime-local" name="ends_at" class="form-control form-control-sm" required>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-suwoda btn-sm"><?= e(t('admin_add_coupon')) ?></button>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th><?= e(t('admin_code')) ?></th>
                    <th><?= e(t('admin_discount')) ?></th>
                    <th><?= e(t('admin_min_order')) ?></th>
                    <th><?= e(t('admin_uses')) ?></th>
                    <th><?= e(t('admin_valid_window')) ?></th>
                    <th><?= e(t('col_status')) ?></th>
                    <th><?= e(t('admin_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coupons as $c): ?>
                    <tr>
                        <td class="fw-bold"><?= e($c['code']) ?></td>
                        <td><?= $c['type'] === 'percent' ? e($c['value']) . '%' : e(format_price((float)$c['value'])) ?></td>
                        <td><?= e(format_price((float)$c['min_order'])) ?></td>
                        <td><?= (int)$c['used_count'] ?> / <?= (int)$c['max_uses'] ?></td>
                        <td><?= e($c['starts_at']) ?> &rarr; <?= e($c['ends_at']) ?></td>
                        <td>
                            <span class="badge <?= (int)$c['active'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                <?= e((int)$c['active'] === 1 ? t('admin_active') : t('admin_inactive')) ?>
                            </span>
                        </td>
                        <td class="d-flex gap-1">
                            <form method="POST" action="/admin/coupons/toggle">
                                <?= csrf_field() ?>
                                <input type="hidden" name="coupon_id" value="<?= (int)$c['coupon_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(t('admin_toggle')) ?></button>
                            </form>
                            <form method="POST" action="/admin/coupons/delete" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_coupon_confirm'))) ?>);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="coupon_id" value="<?= (int)$c['coupon_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
