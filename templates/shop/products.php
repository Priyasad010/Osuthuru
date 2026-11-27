<?php use App\Core\Auth; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-success mb-1"><?= e(t('nav_products')) ?></h2>
        <p class="text-muted small mb-0"><?= e(t('products_subtitle')) ?></p>
    </div>
    <?php if (Auth::check()): ?>
        <a href="/wishlist" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-heart-fill me-1"></i> <?= e(t('wishlist')) ?> (<?= count($wishlistIds ?? []) ?>)
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>

<!-- Category Filter -->
<div class="mb-4 d-flex flex-wrap gap-2">
    <a href="/products" class="btn btn-sm <?= empty($selectedCategory) ? 'btn-suwoda' : 'btn-outline-success' ?>"><?= e(t('filter_all')) ?></a>
    <?php foreach ($categories as $cat): ?>
        <a href="/products?category=<?= (int)$cat['category_id'] ?>"
           class="btn btn-sm <?= ($selectedCategory ?? 0) === (int)$cat['category_id'] ? 'btn-suwoda' : 'btn-outline-success' ?>">
            <?= e($cat['display_name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <?php foreach ($products as $p): ?>
        <?php
            $pid = (int)$p['product_id'];
            $inWishlist = in_array($pid, $wishlistIds ?? [], true);
            $outOfStock = (int)$p['quantity'] <= 0;
        ?>
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="position-relative">
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 180px; overflow:hidden;">
                        <?= picture($p['image'] ?? null, $p['display_name']) ?>
                    </div>
                    <?php if ($p['has_discount']): ?>
                        <span class="badge bg-danger position-absolute top-0 start-0 m-2">
                            -<?= number_format($p['discount_percentage'], 0) ?>%
                        </span>
                    <?php endif; ?>
                    <?php if (Auth::check()): ?>
                        <form method="POST" action="/wishlist/toggle" class="position-absolute top-0 end-0 m-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                            <button type="submit" class="btn btn-sm <?= $inWishlist ? 'btn-danger' : 'btn-light' ?> rounded-circle shadow-sm" title="<?= e(t('wishlist')) ?>">
                                <i class="bi <?= $inWishlist ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <small class="text-muted"><?= e($p['display_category_name'] ?? t('category_fallback')) ?></small>
                    <h6 class="card-title fw-bold mt-1 mb-2">
                        <a href="/products/<?= $pid ?>" class="text-dark text-decoration-none"><?= e($p['display_name']) ?></a>
                    </h6>
                    <p class="card-text small text-muted flex-grow-1"><?= e(mb_strimwidth((string)$p['display_description'], 0, 80, '...')) ?></p>

                    <div class="mb-3">
                        <?php if ($p['has_discount']): ?>
                            <span class="fw-bold text-success fs-6"><?= e(format_price($p['sale_price'])) ?></span>
                            <small class="text-muted text-decoration-line-through ms-1"><?= e(format_price($p['base_price'])) ?></small>
                        <?php else: ?>
                            <span class="fw-bold text-success fs-6"><?= e(format_price($p['base_price'])) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($outOfStock): ?>
                        <span class="badge bg-secondary mb-2"><?= e(t('out_of_stock')) ?></span>
                        <?php if (Auth::check()): ?>
                            <form method="POST" action="/stock-alert">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= $pid ?>">
                                <button type="submit" class="btn btn-outline-warning btn-sm w-100">
                                    <i class="bi bi-bell me-1"></i> <?= e(t('notify_me')) ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="/login" class="btn btn-outline-secondary btn-sm w-100"><?= e(t('login_for_alert')) ?></a>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" action="/cart/add" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="btn btn-suwoda btn-sm w-100">
                                <i class="bi bi-cart-plus me-1"></i> <?= e(t('add_to_cart')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
