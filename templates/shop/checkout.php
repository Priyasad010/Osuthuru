<?php use App\Core\Auth; $user = Auth::user(); ?>
<h2 class="fw-bold text-success mb-4"><i class="bi bi-bag-check me-2"></i><?= e(t('checkout_title')) ?></h2>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
<?php endif; ?>

<?php
$productIds = array_column($cartItems, 'product_id');
$safety = \App\Service\SafetyService::currentResult($productIds);
?>
<?= \App\Core\View::partial('safety_panel', ['productIds' => $productIds, 'back' => 'checkout']) ?>

<form method="POST" action="/checkout" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold text-success mb-3"><?= e(t('shipping_details')) ?></h5>

                <div class="mb-3">
                    <label for="shipping_name" class="form-label small fw-semibold"><?= e(t('recipient_name')) ?></label>
                    <input type="text" class="form-control" id="shipping_name" name="shipping_name"
                           value="<?= e($old['shipping_name'] ?? $user['name'] ?? '') ?>" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="shipping_phone" class="form-label small fw-semibold"><?= e(t('phone_number')) ?></label>
                        <input type="text" class="form-control" id="shipping_phone" name="shipping_phone"
                               value="<?= e($old['shipping_phone'] ?? $user['phone'] ?? '') ?>" placeholder="07XXXXXXXX" required>
                    </div>
                    <div class="col-md-6">
                        <label for="district" class="form-label small fw-semibold"><?= e(t('district')) ?></label>
                        <select class="form-select" id="district" name="district" required onchange="recalcCheckoutTotals()">
                            <?php foreach ($zones as $z): ?>
                                <option value="<?= e($z['district']) ?>"
                                        data-fee="<?= e((string)$z['fee']) ?>"
                                        data-free-over="<?= e((string)$z['free_over']) ?>"
                                        <?= ($old['district'] ?? 'Colombo') === $z['district'] ? 'selected' : '' ?>>
                                    <?= e($z['district']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="shipping_address" class="form-label small fw-semibold"><?= e(t('delivery_address')) ?></label>
                    <textarea class="form-control" id="shipping_address" name="shipping_address" rows="2" required><?= e($old['shipping_address'] ?? $user['address'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold text-success mb-3"><?= e(t('payment_method_step')) ?></h5>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="payment_method" id="pm_cod" value="cod" checked onchange="toggleBankSlipBox()">
                    <label class="form-check-label fw-semibold" for="pm_cod">
                        <i class="bi bi-cash-coin text-success me-1"></i> <?= e(t('pay_cod')) ?>
                    </label>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="payment_method" id="pm_bank" value="bank_transfer" onchange="toggleBankSlipBox()">
                    <label class="form-check-label fw-semibold" for="pm_bank">
                        <i class="bi bi-bank text-primary me-1"></i> <?= e(t('pay_bank')) ?>
                    </label>
                </div>

                <div id="bankSlipUploadBox" class="bg-light p-3 rounded border mb-3 d-none">
                    <p class="small text-muted mb-2">
                        <strong><?= e(t('bank_details_label')) ?></strong> <?= e(t('bank_details')) ?>
                    </p>
                    <label for="bank_slip" class="form-label small fw-semibold"><?= e(t('upload_slip_optional')) ?></label>
                    <input type="file" class="form-control form-control-sm" id="bank_slip" name="bank_slip" accept=".jpg,.jpeg,.png,.webp">
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_method" id="pm_card" value="card" onchange="toggleBankSlipBox()">
                    <label class="form-check-label fw-semibold" for="pm_card">
                        <i class="bi bi-credit-card text-dark me-1"></i> <?= e(t('pay_card')) ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold text-success mb-3"><?= e(t('order_summary')) ?></h5>

                <ul class="list-group list-group-flush mb-3 small">
                    <?php foreach ($cartItems as $item): ?>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span><?= e($item['display_name']) ?> &times; <?= (int)$item['quantity'] ?></span>
                            <span class="fw-semibold"><?= e(format_price($item['line_total'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="mb-3">
                    <label for="coupon_code" class="form-label small fw-semibold"><?= e(t('coupon_label')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="coupon_code" name="coupon_code"
                           value="<?= e($old['coupon_code'] ?? '') ?>" placeholder="<?= e(t('coupon_placeholder')) ?>">
                </div>

                <div class="d-flex justify-content-between small mb-2">
                    <span><?= e(t('items_subtotal')) ?></span>
                    <span class="fw-semibold"><?= e(format_price($subtotal)) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                    <span><?= e(t('delivery_fee_label')) ?> (<span id="chkDistrictName">Colombo</span>):</span>
                    <span id="chkDeliveryFee" class="fw-semibold">-</span>
                </div>
                <small id="chkFreeOverNote" class="text-success d-block mb-2"></small>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5 text-success mb-4">
                    <span><?= e(t('total_payable')) ?></span>
                    <span id="chkGrandTotal"><?= e(format_price($subtotal)) ?></span>
                </div>

                <?php if (!empty($safety['has_avoid'])): ?>
                    <div class="alert alert-danger py-2 small">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="safety_ack" name="safety_ack" value="1" required>
                            <label class="form-check-label fw-semibold" for="safety_ack"><?= e(t('safety_ack')) ?></label>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-suwoda w-100 py-2 fw-semibold">
                    <i class="bi bi-lock-fill me-1"></i> <?= e(t('place_order')) ?>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
    function toggleBankSlipBox() {
        const isBank = document.getElementById('pm_bank').checked;
        document.getElementById('bankSlipUploadBox').classList.toggle('d-none', !isBank);
    }

    function recalcCheckoutTotals() {
        const subtotal = <?= json_encode((float)$subtotal) ?>;
        const sel = document.getElementById('district');
        const opt = sel.options[sel.selectedIndex];
        const fee = parseFloat(opt.dataset.fee || '0');
        const freeOver = parseFloat(opt.dataset.freeOver || '0');
        const appliedFee = (freeOver > 0 && subtotal >= freeOver) ? 0 : fee;

        document.getElementById('chkDistrictName').textContent = opt.value;
        document.getElementById('chkDeliveryFee').textContent = appliedFee === 0 ? <?= json_encode(t('free')) ?> : 'Rs. ' + appliedFee.toFixed(2);
        const freeOverText = <?= json_encode(t('free_over_note')) ?>.replace(':amount', 'Rs. ' + freeOver.toFixed(2));
        document.getElementById('chkFreeOverNote').textContent = freeOver > 0
            ? (appliedFee === 0 ? <?= json_encode(t('free_unlocked')) ?> : freeOverText)
            : '';
        document.getElementById('chkGrandTotal').textContent = 'Rs. ' + (subtotal + appliedFee).toFixed(2);
    }

    recalcCheckoutTotals();
</script>
