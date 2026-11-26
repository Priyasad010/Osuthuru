<?php use App\Service\OrderService; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Admin Dashboard</a>
        <h2 class="fw-bold text-success mt-1 mb-0">Manage Orders &amp; Status</h2>
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
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>District</th>
                    <th>Method / Payment</th>
                    <th>Total</th>
                    <th>Current Status</th>
                    <th>Update Status</th>
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
                            <div class="text-uppercase fw-semibold"><?= e($o['payment_method']) ?></div>
                            <span class="badge <?= $o['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= e(strtoupper($o['payment_status'])) ?>
                            </span>
                        </td>
                        <td class="fw-semibold"><?= e(format_price((float)$o['total_price'])) ?></td>
                        <td><span class="badge bg-info text-dark"><?= e($o['status']) ?></span></td>
                        <td>
                            <form method="POST" action="/admin/orders/status" class="d-flex gap-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                                <select name="status" class="form-select form-select-sm" style="width:140px;">
                                    <?php foreach (OrderService::STATUSES as $st): ?>
                                        <option value="<?= e($st) ?>" <?= $o['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="note" class="form-control form-control-sm" placeholder="Optional note" style="width:130px;">
                                <button type="submit" class="btn btn-sm btn-suwoda">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
