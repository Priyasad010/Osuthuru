<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/orders" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('back_to_orders')) ?></a>
        <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('order_number', ['id' => (int)$order['order_id']])) ?></h2>
    </div>
    <div class="d-flex gap-2">
        <a href="/orders/<?= (int)$order['order_id'] ?>/invoice" class="btn btn-outline-dark btn-sm" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i> <?= e(t('download_invoice')) ?>
        </a>
        <?php if ($order['status'] === 'Pending'): ?>
            <form method="POST" action="/orders/<?= (int)$order['order_id'] ?>/cancel" onsubmit="return confirm(<?= e(json_encode(t('cancel_confirm'))) ?>);">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-x-circle me-1"></i> <?= e(t('cancel_order')) ?>
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

<?php if (\App\Service\OrderService::needsRefund($order)): ?>
    <div class="alert alert-warning small" role="status"><?= e(t('flash_refund_due')) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Items Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-success"><?= e(t('order_items')) ?></h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= e(t('col_product')) ?></th>
                            <th class="text-end"><?= e(t('col_qty')) ?></th>
                            <th class="text-end"><?= e(t('col_unit_price')) ?></th>
                            <th class="text-end"><?= e(t('col_subtotal')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $itemsSubtotal = 0.0; ?>
                        <?php foreach ($order['items'] as $item): ?>
                            <?php $itemsSubtotal += (float)$item['subtotal']; ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?= e(\App\Repository\ProductRepo::enrichLocale($item)['display_name']) ?>
                                    <?php if (!empty($item['batch_token'])): ?>
                                        <div class="small fw-normal">
                                            <a href="/verify?t=<?= e($item['batch_token']) ?>" class="text-success">
                                                <i class="bi bi-qr-code me-1"></i><?= e(t('batch_code')) ?>: <?= e($item['batch_code']) ?>
                                            </a>
                                            <?php if ($item['batch_status'] === 'recalled'): ?>
                                                <span class="badge bg-danger"><?= e(t('verify_recalled')) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= (int)$item['quantity'] ?></td>
                                <td class="text-end"><?= e(format_price((float)$item['unit_price'])) ?></td>
                                <td class="text-end"><?= e(format_price((float)$item['subtotal'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end"><?= e(t('items_subtotal')) ?></td>
                            <td class="text-end"><?= e(format_price($itemsSubtotal)) ?></td>
                        </tr>
                        <?php if ((float)$order['discount_total'] > 0): ?>
                            <tr class="text-success">
                                <td colspan="3" class="text-end"><?= e(t('coupon_discount', ['code' => $order['coupon_code'] ?? ''])) ?></td>
                                <td class="text-end">- <?= e(format_price((float)$order['discount_total'])) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="3" class="text-end"><?= e(t('delivery_fee_district', ['district' => $order['district'] ?? ''])) ?></td>
                            <td class="text-end"><?= (float)$order['delivery_fee'] > 0 ? e(format_price((float)$order['delivery_fee'])) : e(t('free')) ?></td>
                        </tr>
                        <tr class="fw-bold fs-6 text-success">
                            <td colspan="3" class="text-end"><?= e(t('grand_total')) ?></td>
                            <td class="text-end"><?= e(format_price((float)$order['total_price'])) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php if (\App\Service\PayHereService::canPay($order)): ?>
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold text-success mb-2"><i class="bi bi-phone me-2"></i><?= e(t('payhere_title')) ?></h5>
                <?php $returned = (string)($_GET['payment'] ?? ''); ?>
                <?php if ($returned === 'return'): ?>
                    <div class="alert alert-info small py-2" role="status"><?= e(t('payhere_waiting')) ?></div>
                <?php elseif ($returned === 'cancelled' || $order['payment_status'] === 'failed'): ?>
                    <div class="alert alert-warning small py-2" role="status"><?= e(t('payhere_not_paid')) ?></div>
                <?php endif; ?>
                <?php if (\App\Service\PayHereService::isConfigured()): ?>
                    <div><a href="/orders/<?= (int)$order['order_id'] ?>/pay" class="btn btn-brand"><?= e(t('payhere_pay_now')) ?></a></div>
                <?php else: ?>
                    <p class="small text-muted mb-0"><?= e(t('err_payment_unavailable')) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Bank Transfer Slip Upload (if applicable) -->
        <?php if ($order['payment_method'] === 'bank_transfer' && $order['payment_status'] === 'unpaid' && $order['status'] !== 'Cancelled'): ?>
            <div class="card border-0 shadow-sm p-4 mb-4 border-start border-4 border-primary">
                <h5 class="fw-bold text-primary mb-2"><i class="bi bi-bank me-2"></i><?= e(t('bank_slip_title')) ?></h5>
                <?php if (!empty($order['bank_slip'])): ?>
                    <p class="small mb-2">
                        <?= e(t('latest_slip_status')) ?>
                        <span class="badge bg-info text-dark"><?= e(t_or('slip_', $order['bank_slip']['status'])) ?></span>
                    </p>
                <?php endif; ?>
                <div class="small mb-3"><?= \App\Core\View::partial('bank_details') ?></div>
                <form method="POST" action="/orders/<?= (int)$order['order_id'] ?>/upload-slip" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                    <?= csrf_field() ?>
                    <input aria-label="<?= e(t('bank_slip_title')) ?>" type="file" name="bank_slip" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp" required>
                    <button type="submit" class="btn btn-primary btn-sm text-nowrap"><?= e(t('upload_slip')) ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <!-- Shipping & Payment Snapshot -->
        <div class="card border-0 shadow-sm p-4 mb-4">
            <h5 class="fw-bold text-success mb-3"><?= e(t('shipping_payment')) ?></h5>
            <p class="small mb-1"><strong><?= e(t('recipient')) ?></strong> <?= e($order['shipping_name'] ?? $order['user_name']) ?></p>
            <p class="small mb-1"><strong><?= e(t('phone_label')) ?></strong> <?= e($order['shipping_phone'] ?? '-') ?></p>
            <p class="small mb-1"><strong><?= e(t('district_label')) ?></strong> <?= e($order['district'] ?? '-') ?></p>
            <p class="small mb-3"><strong><?= e(t('address_label')) ?></strong> <?= e($order['shipping_address'] ?? '-') ?></p>
            <hr>
            <p class="small mb-1"><strong><?= e(t('method_label')) ?></strong> <?= e(t_or('method_', $order['payment_method'])) ?></p>
            <?php if (!empty($order['payment_reference'])): ?>
                <p class="small mb-1"><strong><?= e(t('payment_reference_label')) ?></strong> <?= e($order['payment_reference']) ?></p>
            <?php endif; ?>
            <p class="small mb-0">
                <strong><?= e(t('payment_status_label')) ?></strong>
                <span class="badge <?= $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <?= e(t_or('payment_', $order['payment_status'])) ?>
                </span>
            </p>
        </div>

        <!-- Order Status Timeline -->
        <div class="card border-0 shadow-sm p-4">
            <h5 class="fw-bold text-success mb-3"><i class="bi bi-clock-history me-2"></i><?= e(t('order_timeline')) ?></h5>
            <ul class="list-unstyled mb-0">
                <?php foreach ($order['timeline'] as $entry): ?>
                    <li class="border-start border-2 border-success ps-3 pb-3 position-relative">
                        <span class="position-absolute top-0 start-0 translate-middle bg-success rounded-circle" style="width:10px;height:10px;"></span>
                        <div class="fw-bold small text-success"><?= e(t_or('status_', $entry['status'])) ?></div>
                        <?php if (!empty($entry['note'])): ?>
                            <div class="small text-muted"><?= e(t_or('note_', $entry['note'])) ?></div>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size: 0.75rem;"><?= e($entry['created_at']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
