<div class="mb-4">
    <a href="/" class="text-success text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> <?= e(t('nav_home')) ?>
    </a>
</div>

<div class="card border-0 shadow-sm p-4 mb-4 bg-white border-top border-4 border-success">
    <div class="d-flex align-items-center gap-3">
        <div class="bg-success-subtle p-3 rounded-circle text-success fs-2">
            <i class="bi <?= e($concern['icon']) ?>"></i>
        </div>
        <div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 mb-1">
                <?= e(t('health_concerns_title')) ?>
            </span>
            <h2 class="fw-bold text-success mb-1"><?= e($concern['display_name']) ?></h2>
            <p class="text-muted small mb-0">
                <?= e(t('traditionally_used_for')) ?>: <?= e($concern['name_en']) ?> / <?= e($concern['name_si']) ?>
            </p>
        </div>
    </div>
</div>

<!-- Educational Disclaimer on Concern Landing Page -->
<div class="disclaimer-box mb-4">
    <i class="bi bi-shield-exclamation text-warning me-2 fs-6"></i>
    <strong><?= e(t('traditionally_used_for')) ?>:</strong> <?= e(t('concern_disclaimer')) ?>
</div>

<!-- Herbs for this Concern -->
<div class="mb-5">
    <h4 class="fw-bold text-success mb-3">
        <i class="bi bi-journal-medical me-2"></i>Herbal Remedies Traditionally Associated
    </h4>

    <?php if (empty($herbs)): ?>
        <p class="text-muted small">No specific herbs catalogued for this health concern yet.</p>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($herbs as $h): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-success mb-0">
                                <a href="/herb/<?= (int)$h['herb_id'] ?>" class="text-success text-decoration-none">
                                    <?= e($h['display_name']) ?>
                                </a>
                            </h6>
                            <i class="bi bi-flower1 text-success opacity-50"></i>
                        </div>
                        <small class="text-muted fst-italic mb-2"><?= e($h['scientific_name']) ?></small>
                        <p class="small text-secondary mb-3 flex-grow-1"><?= e(mb_strimwidth((string)$h['display_uses'], 0, 100, '...')) ?></p>
                        <div>
                            <a href="/herb/<?= (int)$h['herb_id'] ?>" class="btn btn-outline-success btn-sm w-100">
                                Explore Herb Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Products for this Concern -->
<?php if (!empty($products)): ?>
    <div class="mb-5">
        <h4 class="fw-bold text-success mb-3">
            <i class="bi bi-box-seam me-2"></i>Products Prepared with These Herbs
        </h4>
        <div class="row g-4">
            <?php foreach ($products as $p): ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                            <i class="bi bi-flower1 text-success opacity-25 fs-1"></i>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <small class="text-muted"><?= e($p['display_category_name'] ?? 'Herbal') ?></small>
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
                                    View Product
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
