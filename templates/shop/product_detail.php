<?php use App\Core\Auth; ?>
<div class="mb-4">
    <a href="/products" class="text-success text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> Back to Products
    </a>
</div>

<div class="card border-0 shadow-sm p-4 mb-4">
    <div class="row g-4">
        <div class="col-md-5 text-center">
            <div class="bg-light p-4 rounded d-flex align-items-center justify-content-center" style="height: 300px;">
                <i class="bi bi-flower1 text-success opacity-50" style="font-size: 7rem;"></i>
            </div>
        </div>
        <div class="col-md-7">
            <small class="text-muted text-uppercase fw-semibold"><?= e($product['display_category_name'] ?? 'Herbal') ?></small>
            <h2 class="fw-bold text-success mt-1 mb-2"><?= e($product['display_name']) ?></h2>

            <div class="mb-3">
                <?php if ($product['has_discount']): ?>
                    <span class="fs-4 fw-bold text-success me-2"><?= e(format_price($product['sale_price'])) ?></span>
                    <span class="text-muted text-decoration-line-through"><?= e(format_price($product['base_price'])) ?></span>
                    <span class="badge bg-danger ms-2">-<?= number_format($product['discount_percentage'], 0) ?>% OFF</span>
                <?php else: ?>
                    <span class="fs-4 fw-bold text-success"><?= e(format_price($product['base_price'])) ?></span>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <h6 class="fw-bold text-dark mb-1">Description</h6>
                <p class="text-muted leading-relaxed"><?= nl2br(e($product['display_description'])) ?></p>
            </div>

            <!-- List of Herbs contained in this product -->
            <?php if (!empty($product['herbs'])): ?>
                <div class="mb-4 p-3 bg-light rounded border border-light-subtle">
                    <h6 class="fw-bold text-success mb-2">
                        <i class="bi bi-journal-medical me-1"></i><?= e(t('contains_herbs')) ?>
                    </h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($product['herbs'] as $h): ?>
                            <a href="/herb/<?= (int)$h['herb_id'] ?>" class="btn btn-sm btn-white border shadow-sm text-success fw-semibold">
                                <i class="bi bi-flower1 me-1"></i><?= e($h['display_name']) ?>
                                <small class="text-muted fst-italic ms-1">(<?= e($h['scientific_name']) ?>)</small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="d-flex gap-3 align-items-center">
                <?php if ((int)$product['quantity'] > 0): ?>
                    <form method="POST" action="/cart/add" class="d-flex gap-2 align-items-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">
                        <input type="number" name="quantity" value="1" min="1" max="<?= (int)$product['quantity'] ?>" class="form-control" style="width: 80px;">
                        <button type="submit" class="btn btn-suwoda px-4 py-2 fw-semibold">
                            <i class="bi bi-cart-plus me-1"></i> Add to Cart
                        </button>
                    </form>
                <?php else: ?>
                    <span class="badge bg-secondary p-2">Out of Stock</span>
                    <?php if (Auth::check()): ?>
                        <form method="POST" action="/stock-alert">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-bell me-1"></i> Notify Me When Available
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="/login" class="btn btn-outline-secondary btn-sm">Login for Stock Alert</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
