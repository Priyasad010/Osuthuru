<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Admin Dashboard</a>
        <h2 class="fw-bold text-success mt-1 mb-0">Manage Products &amp; Stock</h2>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Base Price</th>
                    <th>Active Sale Price</th>
                    <th>Stock Quantity</th>
                    <th>Update Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td>#<?= (int)$p['product_id'] ?></td>
                        <td>
                            <div class="fw-bold"><?= e($p['product_name']) ?></div>
                            <span class="text-muted"><?= e($p['category_name'] ?? '') ?></span>
                        </td>
                        <td><?= e(format_price($p['base_price'])) ?></td>
                        <td>
                            <?php if ($p['has_discount']): ?>
                                <span class="text-success fw-bold"><?= e(format_price($p['sale_price'])) ?></span>
                                <span class="badge bg-danger">-<?= number_format($p['discount_percentage'], 0) ?>%</span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= (int)$p['quantity'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                                <?= (int)$p['quantity'] ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" action="/admin/products/stock" class="d-flex gap-1" style="max-width: 180px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">
                                <input type="number" name="quantity" value="<?= (int)$p['quantity'] ?>" min="0" class="form-control form-control-sm">
                                <button type="submit" class="btn btn-sm btn-suwoda">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
