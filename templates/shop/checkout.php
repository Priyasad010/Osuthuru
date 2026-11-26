<?php use App\Core\Auth; $user = Auth::user(); ?>
<h2 class="fw-bold text-success mb-4"><i class="bi bi-bag-check me-2"></i>Checkout</h2>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="/checkout" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold text-success mb-3">1. Shipping Details (Sri Lanka)</h5>

                <div class="mb-3">
                    <label for="shipping_name" class="form-label small fw-semibold">Recipient Full Name</label>
                    <input type="text" class="form-control" id="shipping_name" name="shipping_name"
                           value="<?= e($old['shipping_name'] ?? $user['name'] ?? '') ?>" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="shipping_phone" class="form-label small fw-semibold">Phone Number</label>
                        <input type="text" class="form-control" id="shipping_phone" name="shipping_phone"
                               value="<?= e($old['shipping_phone'] ?? $user['phone'] ?? '') ?>" placeholder="07XXXXXXXX" required>
                    </div>
                    <div class="col-md-6">
                        <label for="district" class="form-label small fw-semibold">District</label>
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
                    <label for="shipping_address" class="form-label small fw-semibold">Delivery Address</label>
                    <textarea class="form-control" id="shipping_address" name="shipping_address" rows="2" required><?= e($old['shipping_address'] ?? $user['address'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold text-success mb-3">2. Payment Method</h5>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="payment_method" id="pm_cod" value="cod" checked onchange="toggleBankSlipBox()">
                    <label class="form-check-label fw-semibold" for="pm_cod">
                        <i class="bi bi-cash-coin text-success me-1"></i> Cash on Delivery (Pay when delivered)
                    </label>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="payment_method" id="pm_bank" value="bank_transfer" onchange="toggleBankSlipBox()">
                    <label class="form-check-label fw-semibold" for="pm_bank">
                        <i class="bi bi-bank text-primary me-1"></i> Bank Transfer (Upload deposit slip)
                    </label>
                </div>

                <div id="bankSlipUploadBox" class="bg-light p-3 rounded border mb-3 d-none">
                    <p class="small text-muted mb-2">
                        <strong>Bank Details:</strong> Bank of Ceylon (Colombo) · Acct: 0012345678 · Name: Suwoda HerbHub (Pvt) Ltd
                    </p>
                    <label for="bank_slip" class="form-label small fw-semibold">Upload Bank Slip (Optional now, or upload later on Order page)</label>
                    <input type="file" class="form-control form-control-sm" id="bank_slip" name="bank_slip" accept=".jpg,.jpeg,.png,.webp">
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_method" id="pm_card" value="card" onchange="toggleBankSlipBox()">
                    <label class="form-check-label fw-semibold" for="pm_card">
                        <i class="bi bi-credit-card text-dark me-1"></i> Credit / Debit Card (Stripe)
                    </label>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold text-success mb-3">Order Summary</h5>

                <ul class="list-group list-group-flush mb-3 small">
                    <?php foreach ($cartItems as $item): ?>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span><?= e($item['product_name']) ?> &times; <?= (int)$item['quantity'] ?></span>
                            <span class="fw-semibold"><?= e(format_price($item['line_total'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="mb-3">
                    <label for="coupon_code" class="form-label small fw-semibold">Coupon Code (Optional)</label>
                    <input type="text" class="form-control form-control-sm" id="coupon_code" name="coupon_code"
                           value="<?= e($old['coupon_code'] ?? '') ?>" placeholder="e.g. AYUR10">
                </div>

                <div class="d-flex justify-content-between small mb-2">
                    <span>Items Subtotal:</span>
                    <span class="fw-semibold"><?= e(format_price($subtotal)) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                    <span>Delivery Fee (<span id="chkDistrictName">Colombo</span>):</span>
                    <span id="chkDeliveryFee" class="fw-semibold">-</span>
                </div>
                <small id="chkFreeOverNote" class="text-success d-block mb-2"></small>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5 text-success mb-4">
                    <span>Total Payable:</span>
                    <span id="chkGrandTotal"><?= e(format_price($subtotal)) ?></span>
                </div>

                <button type="submit" class="btn btn-suwoda w-100 py-2 fw-semibold">
                    <i class="bi bi-lock-fill me-1"></i> Place Order
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
        document.getElementById('chkDeliveryFee').textContent = appliedFee === 0 ? 'FREE' : 'Rs. ' + appliedFee.toFixed(2);
        document.getElementById('chkFreeOverNote').textContent = freeOver > 0
            ? (appliedFee === 0 ? 'Free delivery unlocked!' : 'Free delivery on orders over Rs. ' + freeOver.toFixed(2))
            : '';
        document.getElementById('chkGrandTotal').textContent = 'Rs. ' + (subtotal + appliedFee).toFixed(2);
    }

    recalcCheckoutTotals();
</script>
