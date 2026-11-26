<h2 class="fw-bold text-success mb-4"><i class="bi bi-cart3 me-2"></i><?= e(t('nav_cart')) ?></h2>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
<?php endif; ?>

<?php if (empty($cartItems)): ?>
    <div class="card border-0 shadow-sm p-5 text-center">
        <i class="bi bi-cart-x text-muted fs-1 mb-3"></i>
        <h5><?= e(t('cart_empty')) ?></h5>
        <p class="text-muted small"><?= e(t('cart_empty_hint')) ?></p>
        <div>
            <a href="/products" class="btn btn-suwoda"><?= e(t('browse_products')) ?></a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= e(t('col_product')) ?></th>
                                <th><?= e(t('col_price')) ?></th>
                                <th style="width: 120px;"><?= e(t('col_qty')) ?></th>
                                <th><?= e(t('col_subtotal')) ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($item['display_name']) ?></div>
                                        <?php if ($item['has_discount']): ?>
                                            <span class="badge bg-danger-subtle text-danger small">-<?= number_format($item['discount_percentage'], 0) ?>%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(format_price($item['effective_price'])) ?></td>
                                    <td>
                                        <form method="POST" action="/cart/update" class="d-flex gap-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                                            <input type="number" name="quantity" value="<?= (int)$item['quantity'] ?>" min="1" max="<?= (int)$item['stock'] ?>" class="form-control form-control-sm">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="<?= e(t('update')) ?>"><i class="bi bi-check"></i></button>
                                        </form>
                                    </td>
                                    <td class="fw-semibold"><?= e(format_price($item['line_total'])) ?></td>
                                    <td>
                                        <form method="POST" action="/cart/remove">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= e(t('remove')) ?>"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold text-success mb-3"><?= e(t('order_summary')) ?></h5>

                <div class="mb-3">
                    <label for="cartDistrict" class="form-label small fw-semibold"><?= e(t('estimate_district')) ?></label>
                    <select id="cartDistrict" class="form-select form-select-sm" onchange="updateCartDeliveryEstimate()">
                        <?php foreach ($zones as $z): ?>
                            <option value="<?= e($z['district']) ?>"
                                    data-fee="<?= e((string)$z['fee']) ?>"
                                    data-free-over="<?= e((string)$z['free_over']) ?>"
                                    <?= $z['district'] === 'Colombo' ? 'selected' : '' ?>>
                                <?= e(t('zone_option', ['district' => $z['district'], 'fee' => format_price((float)$z['fee']), 'free' => format_price((float)$z['free_over'])])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-flex justify-content-between small mb-2">
                    <span><?= e(t('items_subtotal')) ?></span>
                    <span class="fw-semibold"><?= e(format_price($subtotal)) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                    <span><?= e(t('estimated_delivery')) ?></span>
                    <span id="estDeliveryText" class="fw-semibold">-</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5 text-success mb-3">
                    <span><?= e(t('estimated_total')) ?></span>
                    <span id="estTotalText"><?= e(format_price($subtotal)) ?></span>
                </div>

                <a href="/checkout" class="btn btn-suwoda w-100 py-2 fw-semibold">
                    <?= e(t('proceed_checkout')) ?> <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-8">
            <?= \App\Core\View::partial('safety_panel', ['productIds' => array_column($cartItems, 'product_id')]) ?>
        </div>
    </div>

    <script>
        function updateCartDeliveryEstimate() {
            const subtotal = <?= json_encode((float)$subtotal) ?>;
            const sel = document.getElementById('cartDistrict');
            const opt = sel.options[sel.selectedIndex];
            const fee = parseFloat(opt.dataset.fee || '0');
            const freeOver = parseFloat(opt.dataset.freeOver || '0');
            const appliedFee = (freeOver > 0 && subtotal >= freeOver) ? 0 : fee;
            document.getElementById('estDeliveryText').textContent = appliedFee === 0 ? <?= json_encode(t('free')) ?> : 'Rs. ' + appliedFee.toFixed(2);
            document.getElementById('estTotalText').textContent = 'Rs. ' + (subtotal + appliedFee).toFixed(2);
        }
        updateCartDeliveryEstimate();
    </script>
<?php endif; ?>
