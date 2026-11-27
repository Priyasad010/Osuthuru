<div class="mb-4">
    <a href="/dictionary" class="text-success text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> <?= e(t('nav_dictionary')) ?>
    </a>
</div>

<div class="card border-0 shadow-sm p-4 mb-4">
    <div class="row g-4 align-items-center">
        <div class="col-md-4 text-center">
            <div class="bg-light p-4 rounded d-flex align-items-center justify-content-center" style="height: 240px;">
                <?= picture($herb['image'] ?? null, $herb['display_name'], 'text-success opacity-50 display-1') ?>
            </div>
        </div>
        <div class="col-md-8">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <?= e(t('nav_dictionary')) ?>
                </span>
                <?php if (!empty($herb['name_si']) && $herb['display_name'] !== $herb['name_si']): ?>
                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1"><?= e($herb['name_si']) ?></span>
                <?php endif; ?>
            </div>

            <h2 class="fw-bold text-success mb-1"><?= e($herb['display_name']) ?></h2>
            <p class="text-muted fst-italic mb-3">
                <strong><?= e(t('scientific_name')) ?>:</strong> <?= e($herb['scientific_name']) ?>
            </p>

            <!-- Health Concerns Tagged -->
            <?php if (!empty($concerns)): ?>
                <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                    <small class="text-muted fw-semibold me-1"><?= e(t('traditionally_used_for')) ?>:</small>
                    <?php foreach ($concerns as $c): ?>
                        <a href="/concern/<?= e($c['slug']) ?>" class="badge text-decoration-none bg-light text-success border border-success-subtle py-1 px-2">
                            <i class="bi <?= e($c['icon']) ?> me-1"></i><?= e($c['display_name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="border-top pt-3">
                <h6 class="fw-bold text-dark mb-2"><?= e(t('traditional_uses')) ?></h6>
                <p class="text-secondary mb-0 leading-relaxed"><?= nl2br(e($herb['display_uses'] ?: t('uses_pending'))) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Educational Disclaimer -->
<div class="disclaimer-box mb-5">
    <i class="bi bi-info-circle-fill text-warning me-2"></i>
    <?= e(t('concern_disclaimer')) ?>
</div>

<!-- Products With This Herb Section -->
<div class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-success mb-0">
            <i class="bi bi-box-seam me-2"></i><?= e(t('products_with_herb')) ?>
        </h4>
        <span class="badge bg-success rounded-pill"><?= count($products) ?></span>
    </div>

    <?php if (empty($products)): ?>
        <div class="card border-0 shadow-sm p-4 text-center text-muted">
            <p class="mb-0 small"><?= e(t('no_products_for_herb')) ?></p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($products as $p): ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 160px;">
                            <i class="bi bi-flower1 text-success opacity-25 fs-1"></i>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <small class="text-muted"><?= e($p['display_category_name'] ?? t('category_fallback')) ?></small>
                            <h6 class="card-title fw-bold mt-1 mb-2">
                                <a href="/products/<?= (int)$p['product_id'] ?>" class="text-dark text-decoration-none">
                                    <?= e($p['display_name']) ?>
                                </a>
                            </h6>
                            <div class="mb-3">
                                <?php if ($p['has_discount']): ?>
                                    <span class="fw-bold text-success"><?= e(format_price($p['sale_price'])) ?></span>
                                    <small class="text-muted text-decoration-line-through ms-1"><?= e(format_price($p['base_price'])) ?></small>
                                <?php else: ?>
                                    <span class="fw-bold text-success"><?= e(format_price($p['base_price'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-auto">
                                <a href="/products/<?= (int)$p['product_id'] ?>" class="btn btn-outline-success btn-sm w-100">
                                    <?= e(t('view_product')) ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
