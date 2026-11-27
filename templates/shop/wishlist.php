<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-success mb-0"><i class="bi bi-heart-fill text-danger me-2"></i><?= e(t('my_wishlist')) ?></h2>
    <a href="/products" class="btn btn-outline-success btn-sm"><?= e(t('browse_catalog')) ?></a>
</div>

<?php if (empty($products)): ?>
    <div class="card border-0 shadow-sm p-5 text-center">
        <i class="bi bi-heart text-muted fs-1 mb-3"></i>
        <h5><?= e(t('wishlist_empty')) ?></h5>
        <p class="text-muted small"><?= e(t('wishlist_empty_hint')) ?></p>
        <div>
            <a href="/products" class="btn btn-brand"><?= e(t('browse_products')) ?></a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($products as $p): ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="card h-100 shadow-sm border-0 p-3">
                    <small class="text-muted"><?= e($p['display_category_name'] ?? t('category_fallback')) ?></small>
                    <h6 class="fw-bold mt-1 mb-2"><?= e($p['display_name']) ?></h6>
                    <div class="mb-3">
                        <span class="fw-bold text-success"><?= e(format_price($p['sale_price'])) ?></span>
                        <?php if ($p['has_discount']): ?>
                            <small class="text-muted text-decoration-line-through ms-1"><?= e(format_price($p['base_price'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="mt-auto d-flex gap-2">
                        <?php if ((int)$p['quantity'] > 0): ?>
                            <form method="POST" action="/cart/add" class="flex-grow-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-brand btn-sm w-100"><?= e(t('add_to_cart')) ?></button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="/stock-alert" class="flex-grow-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">
                                <button type="submit" class="btn btn-outline-warning btn-sm w-100"><?= e(t('notify_short')) ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="/wishlist/toggle">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="<?= e(t('remove')) ?>"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
