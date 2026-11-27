<div class="row g-4">
    <?php foreach ($products as $p): ?>
        <?php $pid = (int)$p['product_id']; ?>
        <div class="col-6 col-sm-6 col-md-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="bg-light d-flex align-items-center justify-content-center p-2" style="height: 200px;">
                    <?= picture($p['image'] ?? null, $p['display_name']) ?>
                </div>
                <div class="card-body d-flex flex-column p-3">
                    <small class="text-muted"><?= e($p['display_category_name'] ?? t('category_fallback')) ?></small>
                    <h3 class="h6 fw-bold mt-1 mb-2">
                        <a href="/products/<?= $pid ?>" class="text-reset text-decoration-none"><?= e($p['display_name']) ?></a>
                    </h3>
                    <div class="mt-auto mb-2">
                        <span class="price"><?= e(format_price($p['effective_price'])) ?></span>
                        <?php if ($p['has_discount']): ?>
                            <small class="text-muted text-decoration-line-through ms-1"><?= e(format_price($p['base_price'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <a href="/products/<?= $pid ?>" class="btn btn-outline-success btn-sm w-100"><?= e(t('view_product')) ?></a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
