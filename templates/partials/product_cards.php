<div class="row g-4">
    <?php foreach ($products as $p): ?>
        <div class="col-sm-6 col-md-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 140px;">
                    <?= picture($p['image'] ?? null, $p['display_name']) ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <small class="text-muted"><?= e($p['display_category_name'] ?? t('category_fallback')) ?></small>
                    <h6 class="card-title fw-bold mt-1 mb-2">
                        <a href="/products/<?= (int)$p['product_id'] ?>" class="text-dark text-decoration-none"><?= e($p['display_name']) ?></a>
                    </h6>
                    <div class="mb-3">
                        <span class="fw-bold text-success"><?= e(format_price($p['effective_price'])) ?></span>
                        <?php if ($p['has_discount']): ?>
                            <small class="text-muted text-decoration-line-through ms-1"><?= e(format_price($p['base_price'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="mt-auto">
                        <a href="/products/<?= (int)$p['product_id'] ?>" class="btn btn-outline-success btn-sm w-100"><?= e(t('view_product')) ?></a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
