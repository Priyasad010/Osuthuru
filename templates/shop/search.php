<div class="mb-4">
    <a href="/products" class="text-success text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> Back to Products
    </a>
</div>

<!-- Header / Search Summary -->
<div class="mb-4">
    <h2 class="fw-bold text-success mb-1">
        <?= e(t('search_results_title', ['query' => $query])) ?>
    </h2>
    <p class="text-muted small">
        Found <?= (int)($results['total_count'] ?? 0) ?> result(s)
    </p>
</div>

<!-- Health Concern Shortcut Banner -->
<?php if (!empty($results['concern_shortcut'])): ?>
    <?php $sc = $results['concern_shortcut']; ?>
    <div class="alert alert-success border-success-subtle shadow-sm d-flex align-items-center justify-content-between p-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-3 text-success"><i class="bi <?= e($sc['icon']) ?>"></i></span>
            <div>
                <strong class="d-block text-success"><?= e(t('concern_shortcut_banner', ['concern' => $sc['display_name']])) ?></strong>
                <small class="text-muted"><?= e(t('traditionally_used_for')) ?>: <?= e($sc['name_en']) ?> / <?= e($sc['name_si']) ?></small>
            </div>
        </div>
        <a href="<?= e($sc['url']) ?>" class="btn btn-suwoda btn-sm text-nowrap ms-3">
            Explore Guide <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
<?php endif; ?>

<!-- Did You Mean Suggestion -->
<?php if (!empty($results['did_you_mean'])): ?>
    <div class="alert alert-warning border-warning-subtle shadow-sm mb-4">
        <i class="bi bi-lightbulb-fill text-warning me-2"></i>
        <span><?= e(t('did_you_mean', ['suggestion' => ''])) ?></span>
        <a href="/search?q=<?= urlencode($results['did_you_mean']) ?>" class="fw-bold text-dark text-decoration-underline ms-1">
            <?= e($results['did_you_mean']) ?>
        </a>
    </div>
<?php endif; ?>

<!-- Empty State -->
<?php if (($results['total_count'] ?? 0) === 0): ?>
    <div class="card border-0 shadow-sm p-5 text-center my-4">
        <div class="mb-3 text-muted">
            <i class="bi bi-search fs-1"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">No matching products or herbs found</h5>
        <p class="text-muted small mx-auto mb-4" style="max-width: 500px;">
            <?= e(t('no_search_results', ['query' => $query])) ?>
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="/dictionary" class="btn btn-outline-success btn-sm">
                <i class="bi bi-journal-medical me-1"></i> Browse Herbal Dictionary
            </a>
            <a href="/products" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-seam me-1"></i> Browse All Products
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Herbs Results -->
<?php if (!empty($results['herbs'])): ?>
    <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <h4 class="fw-bold text-success mb-0">
                <i class="bi bi-flower1 me-2"></i>Herbal Knowledge &amp; Plant Profiles
            </h4>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                <?= count($results['herbs']) ?>
            </span>
        </div>
        <div class="row g-4">
            <?php foreach ($results['herbs'] as $h): ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 140px;">
                            <i class="bi bi-flower1 text-success opacity-50 fs-1"></i>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h6 class="fw-bold text-success mb-1">
                                <a href="/herb/<?= (int)$h['herb_id'] ?>" class="text-success text-decoration-none">
                                    <?= e($h['display_name']) ?>
                                </a>
                            </h6>
                            <small class="text-muted fst-italic mb-2"><?= e($h['scientific_name']) ?></small>
                            <p class="small text-secondary mb-3 flex-grow-1">
                                <?= e(mb_strimwidth((string)$h['display_uses'], 0, 90, '...')) ?>
                            </p>
                            <div class="mt-auto">
                                <a href="/herb/<?= (int)$h['herb_id'] ?>" class="btn btn-outline-success btn-sm w-100">
                                    View Herb Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Products Results -->
<?php if (!empty($results['products'])): ?>
    <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <h4 class="fw-bold text-success mb-0">
                <i class="bi bi-box-seam me-2"></i>Herbal Products &amp; Remedies
            </h4>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                <?= count($results['products']) ?>
            </span>
        </div>
        <div class="row g-4">
            <?php foreach ($results['products'] as $p): ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 160px;">
                            <i class="bi bi-flower1 text-success opacity-25 fs-1"></i>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <small class="text-muted"><?= e($p['display_category_name'] ?? 'Ayurvedic') ?></small>
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

<!-- Educational Disclaimer -->
<div class="disclaimer-box text-center">
    <i class="bi bi-shield-exclamation text-warning me-2"></i>
    <?= e(t('concern_disclaimer')) ?>
</div>
