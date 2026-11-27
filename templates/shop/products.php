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

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" role="alert"><?= e($error) ?></div>
<?php endif; ?>
<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>

<?php
use App\Core\Pager;

$listing = $listing ?? ['filters' => [], 'sort' => 'newest', 'pager' => ['page' => 1, 'pages' => 1, 'total' => count($products)]];
$chosen = $listing['filters'];
?>
<!-- Category Filter: keeps the sort order and price range -->
<div class="mb-3 d-flex flex-wrap gap-2">
    <a href="<?= e(Pager::url('/products', ['category' => ''] + $chosen, 1)) ?>" class="btn btn-sm <?= empty($selectedCategory) ? 'btn-brand' : 'btn-outline-success' ?>"><?= e(t('filter_all')) ?></a>
    <?php foreach ($categories as $cat): ?>
        <a href="<?= e(Pager::url('/products', ['category' => (string)(int)$cat['category_id']] + $chosen, 1)) ?>"
           class="btn btn-sm <?= ($selectedCategory ?? 0) === (int)$cat['category_id'] ? 'btn-brand' : 'btn-outline-success' ?>">
            <?= e($cat['display_name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<form method="GET" action="/products" class="card border-0 shadow-sm p-3 mb-3 row g-2 flex-row align-items-end mx-0">
    <?php if (!empty($chosen['category'])): ?><input type="hidden" name="category" value="<?= e($chosen['category']) ?>"><?php endif; ?>
    <div class="col-12 col-md-4">
        <label class="form-label small mb-1" for="sort"><?= e(t('sort_label')) ?></label>
        <select class="form-select form-select-sm" id="sort" name="sort">
            <?php foreach (\App\Service\ProductListing::SORTS as $sort): ?>
                <option value="<?= $sort ?>" <?= $listing['sort'] === $sort ? 'selected' : '' ?>><?= e(t('sort_' . $sort)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small mb-1" for="min"><?= e(t('price_min')) ?></label>
        <input type="number" class="form-control form-control-sm" id="min" name="min" value="<?= e($chosen['min'] ?? '') ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small mb-1" for="max"><?= e(t('price_max')) ?></label>
        <input type="number" class="form-control form-control-sm" id="max" name="max" value="<?= e($chosen['max'] ?? '') ?>">
    </div>
    <div class="col-6 col-md-2">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="stock" name="stock" value="1" <?= !empty($chosen['stock']) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="stock"><?= e(t('filter_in_stock')) ?></label>
        </div>
    </div>
    <div class="col-6 col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-brand btn-sm flex-fill"><?= e(t('filter_apply')) ?></button>
        <a href="/products" class="btn btn-outline-secondary btn-sm" title="<?= e(t('filter_reset')) ?>" aria-label="<?= e(t('filter_reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
    </div>
</form>
<p class="small text-muted" role="status"><?= e(t('results_count', ['count' => $listing['pager']['total']])) ?></p>

<?php if (empty($products)): ?>
    <div class="card border-0 shadow-sm p-5 text-center">
        <p class="mb-3"><?= e(t('no_products_match')) ?></p>
        <div><a href="/products" class="btn btn-brand"><?= e(t('filter_reset')) ?></a></div>
    </div>
<?php endif; ?>

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
                            <button type="submit" class="btn btn-brand btn-sm w-100">
                                <i class="bi bi-cart-plus me-1"></i> <?= e(t('add_to_cart')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?= \App\Core\View::partial('pagination', ['pager' => $listing['pager'] + ['per' => 0], 'path' => '/products', 'query' => $chosen]) ?>
