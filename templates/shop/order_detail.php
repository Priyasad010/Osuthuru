<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/orders" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to Orders</a>
        <h2 class="fw-bold text-success mt-1 mb-0">Order #<?= (int)$order['order_id'] ?></h2>
    </div>
    <div class="d-flex gap-2">
        <a href="/orders/<?= (int)$order['order_id'] ?>/invoice" class="btn btn-outline-dark btn-sm" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF Invoice
        </a>
        <?php if ($order['status'] === 'Pending'): ?>
            <form method="POST" action="/orders/<?= (int)$order['order_id'] ?>/cancel" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-x-circle me-1"></i> Cancel Order
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Items Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-success">Order Items</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $itemsSubtotal = 0.0; ?>
                        <?php foreach ($order['items'] as $item): ?>
                            <?php $itemsSubtotal += (float)$item['subtotal']; ?>
                            <tr>
                                <td class="fw-semibold"><?= e($item['product_name']) ?></td>
                                <td class="text-end"><?= (int)$item['quantity'] ?></td>
                                <td class="text-end"><?= e(format_price((float)$item['unit_price'])) ?></td>
                                <td class="text-end"><?= e(format_price((float)$item['subtotal'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end">Items Subtotal:</td>
                            <td class="text-end"><?= e(format_price($itemsSubtotal)) ?></td>
                        </tr>
                        <?php if ((float)$order['discount_total'] > 0): ?>
                            <tr class="text-success">
                                <td colspan="3" class="text-end">Coupon Discount (<?= e($order['coupon_code'] ?? '') ?>):</td>
                                <td class="text-end">- <?= e(format_price((float)$order['discount_total'])) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="3" class="text-end">Delivery Fee (<?= e($order['district'] ?? '') ?>):</td>
                            <td class="text-end"><?= (float)$order['delivery_fee'] > 0 ? e(format_price((float)$order['delivery_fee'])) : 'FREE' ?></td>
                        </tr>
                        <tr class="fw-bold fs-6 text-success">
                            <td colspan="3" class="text-end">Grand Total:</td>
                            <td class="text-end"><?= e(format_price((float)$order['total_price'])) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Bank Transfer Slip Upload (if applicable) -->
        <?php if ($order['payment_method'] === 'bank_transfer' && $order['payment_status'] === 'unpaid' && $order['status'] !== 'Cancelled'): ?>
            <div class="card border-0 shadow-sm p-4 mb-4 border-start border-4 border-primary">
                <h5 class="fw-bold text-primary mb-2"><i class="bi bi-bank me-2"></i>Bank Transfer Slip</h5>
                <?php if (!empty($order['bank_slip'])): ?>
                    <p class="small mb-2">
                        Latest slip status:
                        <span class="badge bg-info text-dark"><?= e(strtoupper($order['bank_slip']['status'])) ?></span>
                    </p>
                <?php endif; ?>
                <form method="POST" action="/orders/<?= (int)$order['order_id'] ?>/upload-slip" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                    <?= csrf_field() ?>
                    <input type="file" name="bank_slip" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp" required>
                    <button type="submit" class="btn btn-primary btn-sm text-nowrap">Upload Slip</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <!-- Shipping & Payment Snapshot -->
        <div class="card border-0 shadow-sm p-4 mb-4">
            <h5 class="fw-bold text-success mb-3">Shipping &amp; Payment</h5>
            <p class="small mb-1"><strong>Recipient:</strong> <?= e($order['shipping_name'] ?? $order['user_name']) ?></p>
            <p class="small mb-1"><strong>Phone:</strong> <?= e($order['shipping_phone'] ?? '-') ?></p>
            <p class="small mb-1"><strong>District:</strong> <?= e($order['district'] ?? '-') ?></p>
            <p class="small mb-3"><strong>Address:</strong> <?= e($order['shipping_address'] ?? '-') ?></p>
            <hr>
            <p class="small mb-1"><strong>Method:</strong> <?= e(strtoupper(str_replace('_', ' ', (string)$order['payment_method']))) ?></p>
            <p class="small mb-0">
                <strong>Payment Status:</strong>
                <span class="badge <?= $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <?= e(strtoupper((string)$order['payment_status'])) ?>
                </span>
            </p>
        </div>

        <!-- Order Status Timeline -->
        <div class="card border-0 shadow-sm p-4">
            <h5 class="fw-bold text-success mb-3"><i class="bi bi-clock-history me-2"></i>Order Timeline</h5>
            <ul class="list-unstyled mb-0">
                <?php foreach ($order['timeline'] as $entry): ?>
                    <li class="border-start border-2 border-success ps-3 pb-3 position-relative">
                        <span class="position-absolute top-0 start-0 translate-middle bg-success rounded-circle" style="width:10px;height:10px;"></span>
                        <div class="fw-bold small text-success"><?= e($entry['status']) ?></div>
                        <?php if (!empty($entry['note'])): ?>
                            <div class="small text-muted"><?= e($entry['note']) ?></div>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size: 0.75rem;"><?= e($entry['created_at']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
