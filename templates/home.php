<?php
use App\Repository\HerbRepo;
use App\Repository\ProductRepo;

$herbOfTheDay = $herbOfTheDay ?? HerbRepo::getHerbOfTheDay();
$concerns = $concerns ?? HerbRepo::getAllConcerns();
$featuredProducts = $featuredProducts ?? array_slice(ProductRepo::findAll(), 0, 4);
?>

<!-- Hero Banner -->
<div class="card border-0 shadow-sm p-4 p-md-5 mb-5 text-white" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%); border-radius: 1rem;">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <span class="badge bg-white text-success px-3 py-2 rounded-pill mb-3 fw-semibold">
                <i class="bi bi-patch-check-fill me-1 text-success"></i> <?= e(t('home_badge')) ?>
            </span>
            <h1 class="display-5 fw-bold mb-3"><?= e(t('welcome_title')) ?></h1>
            <p class="lead opacity-90 mb-4"><?= e(t('welcome_subtitle')) ?></p>
            
            <div class="d-flex flex-wrap gap-2">
                <a href="/products" class="btn btn-light text-success fw-bold px-4 py-2">
                    <i class="bi bi-box-seam me-1"></i> <?= e(t('nav_products')) ?>
                </a>
                <a href="/dictionary" class="btn btn-outline-light px-4 py-2">
                    <i class="bi bi-journal-medical me-1"></i> <?= e(t('nav_dictionary')) ?>
                </a>
            </div>
        </div>
        <div class="col-lg-4 text-center mt-4 mt-lg-0">
            <i class="bi bi-flower1 opacity-25" style="font-size: 8rem; color: #b7e4c7;"></i>
        </div>
    </div>
</div>

<?php if (!empty($recommended)): ?>
    <div class="mb-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="fw-bold text-success mb-0"><?= e(t('recommended_title')) ?></h3>
            <div class="d-flex flex-wrap gap-2">
                <?php if (\App\Core\Auth::check()): ?>
                    <a href="/profile/categories" class="btn btn-outline-success btn-sm"><i class="bi bi-stars me-1"></i><?= e(t('interests_title')) ?></a>
                <?php endif; ?>
                <?php if (!empty($hasDosha)): ?>
                    <a href="/quiz/result" class="btn btn-outline-success btn-sm"><?= e(t('quiz_result_title')) ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?= \App\Core\View::partial('product_cards', ['products' => $recommended]) ?>
    </div>
<?php endif; ?>

<?php if (!empty($quizLive) && empty($hasDosha)): ?>
    <div class="card border-0 shadow-sm p-4 mb-5 d-flex flex-row flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="fw-bold text-success mb-1"><?= e(t('quiz_title')) ?></h5>
            <p class="text-muted small mb-0"><?= e(t('quiz_intro')) ?></p>
        </div>
        <a href="/quiz" class="btn btn-suwoda"><?= e(t('quiz_start')) ?></a>
    </div>
<?php endif; ?>

<!-- Herb of the Day Feature -->
<?php if (!empty($herbOfTheDay)): ?>
    <div class="card border-0 shadow-sm mb-5 overflow-hidden" style="border-left: 6px solid #2d6a4f !important;">
        <div class="card-body p-4">
            <div class="row align-items-center g-4">
                <div class="col-md-3 text-center">
                    <div class="bg-success-subtle p-3 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 110px; height: 110px;">
                        <i class="bi bi-flower1 text-success" style="font-size: 3.5rem;"></i>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-success text-white px-2 py-1">
                            <i class="bi bi-calendar-event me-1"></i><?= e(t('herb_of_the_day')) ?>
                        </span>
                        <?php if (!empty($herbOfTheDay['name_si']) && $herbOfTheDay['display_name'] !== $herbOfTheDay['name_si']): ?>
                            <span class="badge bg-secondary-subtle text-secondary px-2 py-1"><?= e($herbOfTheDay['name_si']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h3 class="fw-bold text-success mb-1">
                        <a href="/herb/<?= (int)$herbOfTheDay['herb_id'] ?>" class="text-success text-decoration-none">
                            <?= e($herbOfTheDay['display_name']) ?>
                        </a>
                    </h3>
                    <p class="text-muted fst-italic small mb-2">
                        <strong><?= e(t('scientific_name')) ?>:</strong> <?= e($herbOfTheDay['scientific_name']) ?>
                    </p>
                    <p class="text-secondary mb-3 small leading-relaxed">
                        <?= e(mb_strimwidth((string)($herbOfTheDay['display_uses'] ?: t('uses_pending')), 0, 180, '...')) ?>
                    </p>
                    <div>
                        <a href="/herb/<?= (int)$herbOfTheDay['herb_id'] ?>" class="btn btn-outline-success btn-sm fw-semibold">
                            <?= e(t('read_herb_profile')) ?> <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Shop by Health Concern Grid -->
<div class="mb-5">
    <div class="text-center mb-4">
        <h3 class="fw-bold text-success mb-2"><?= e(t('health_concerns_title')) ?></h3>
        <p class="text-muted small mx-auto" style="max-width: 600px;"><?= e(t('health_concerns_subtitle')) ?></p>
    </div>

    <div class="row g-3">
        <?php foreach ($concerns as $c): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="/concern/<?= e($c['slug']) ?>" class="card h-100 border-0 shadow-sm text-decoration-none p-3 text-center health-concern-card transition-all">
                    <div class="bg-success-subtle text-success rounded-circle mx-auto p-3 mb-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi <?= e($c['icon']) ?> fs-3"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1"><?= e($c['display_name']) ?></h6>
                    <?php if ($c['display_name'] !== $c['name_en']): ?>
                        <small class="text-muted"><?= e($c['name_en']) ?></small>
                    <?php endif; ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Featured Herbal Products -->
<?php if (!empty($featuredProducts)): ?>
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-success mb-1"><?= e(t('featured_title')) ?></h3>
                <p class="text-muted small mb-0"><?= e(t('featured_subtitle')) ?></p>
            </div>
            <a href="/products" class="btn btn-outline-success btn-sm">
                <?= e(t('view_all')) ?> <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($featuredProducts as $p): ?>
                <div class="col-sm-6 col-md-3">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 160px;">
                            <?= picture($p['image'] ?? null, $p['display_name']) ?>
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
                                    <?= e(t('view_details')) ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Medical Disclaimer Banner -->
<div class="disclaimer-box text-center mb-4">
    <i class="bi bi-shield-exclamation text-warning me-2"></i>
    <?= e(t('medical_disclaimer')) ?>
</div>
