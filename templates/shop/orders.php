<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-success mb-0"><i class="bi bi-receipt me-2"></i>My Orders</h2>
    <a href="/products" class="btn btn-outline-success btn-sm">Continue Shopping</a>
</div>

<?php if (empty($orders)): ?>
    <div class="card border-0 shadow-sm p-5 text-center">
        <i class="bi bi-bag text-muted fs-1 mb-3"></i>
        <h5>You haven't placed any orders yet</h5>
        <a href="/products" class="btn btn-suwoda mt-2">Explore Products</a>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-bold">#<?= (int)$o['order_id'] ?></td>
                            <td class="small"><?= e($o['order_date']) ?></td>
                            <td class="small text-uppercase"><?= e(str_replace('_', ' ', (string)$o['payment_method'])) ?></td>
                            <td>
                                <span class="badge <?= $o['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= e(strtoupper((string)$o['payment_status'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $o['status'] === 'Cancelled' ? 'bg-danger' : 'bg-info text-dark' ?>">
                                    <?= e($o['status']) ?>
                                </span>
                            </td>
                            <td class="fw-semibold"><?= e(format_price((float)$o['total_price'])) ?></td>
                            <td class="text-end">
                                <a href="/orders/<?= (int)$o['order_id'] ?>" class="btn btn-sm btn-outline-success">
                                    View Timeline
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
