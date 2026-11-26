<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Admin Dashboard</a>
        <h2 class="fw-bold text-success mt-1 mb-0">Manage Coupons</h2>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm p-4 mb-4">
    <h6 class="fw-bold text-success mb-3">Create New Coupon</h6>
    <form method="POST" action="/admin/coupons/create" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Code</label>
            <input type="text" name="code" class="form-control form-control-sm" placeholder="AYUR10" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Type</label>
            <select name="type" class="form-select form-select-sm">
                <option value="percent">Percent (%)</option>
                <option value="fixed">Fixed (Rs.)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Value</label>
            <input type="number" step="0.01" name="value" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Min Order (Rs.)</label>
            <input type="number" step="0.01" name="min_order" value="0" class="form-control form-control-sm">
        </div>
        <div class="col-md-1">
            <label class="form-label small fw-semibold">Max Uses</label>
            <input type="number" name="max_uses" value="100" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Valid Until</label>
            <input type="datetime-local" name="ends_at" class="form-control form-control-sm" required>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-suwoda btn-sm">Add Coupon</button>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Discount</th>
                    <th>Min Order</th>
                    <th>Uses</th>
                    <th>Valid Window</th>
                    <th>Status</th>
                    <th>Actions</th>
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
                                <?= (int)$c['active'] === 1 ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="d-flex gap-1">
                            <form method="POST" action="/admin/coupons/toggle">
                                <?= csrf_field() ?>
                                <input type="hidden" name="coupon_id" value="<?= (int)$c['coupon_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Toggle</button>
                            </form>
                            <form method="POST" action="/admin/coupons/delete" onsubmit="return confirm('Delete coupon?');">
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
