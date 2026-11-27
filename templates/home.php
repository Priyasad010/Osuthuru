<?php
use App\Repository\HerbRepo;
use App\Repository\ProductRepo;

$herbOfTheDay = $herbOfTheDay ?? HerbRepo::getHerbOfTheDay();
$concerns = $concerns ?? HerbRepo::getAllConcerns();
$featuredProducts = $featuredProducts ?? array_slice(ProductRepo::findAll(), 0, 4);
$shelf = HerbRepo::shelf(4);
$ranges = ProductRepo::getAllCategories(null, true);
?>

<div class="hero bleed">
    <div class="container hero-grid">
        <div>
            <h1><?= e(t('hero_line1')) ?> <span><?= e(t('hero_line2')) ?></span></h1>
            <p><?= e(t('hero_text')) ?></p>
            <div class="d-flex flex-wrap gap-2">
                <a href="/products" class="btn btn-saffron btn-lg px-4"><?= e(t('hero_shop')) ?></a>
                <a href="/dictionary" class="btn btn-outline-light btn-lg px-4"><?= e(t('hero_herbs')) ?></a>
            </div>
        </div>

        <?php if ($shelf): ?>
            <div class="shelf">
                <?php foreach ($shelf as $i => $herb): ?>
                    <div data-shelf-panel="<?= (int)$herb['herb_id'] ?>" <?= $i ? 'hidden' : '' ?>>
                        <div class="shelf-photo">
                            <img src="<?= e(image_url($herb['image'])) ?>" alt="<?= e($herb['display_name']) ?>" <?= $i ? 'loading="lazy"' : '' ?>>
                            <div class="shelf-label">
                                <a href="/herb/<?= (int)$herb['herb_id'] ?>"><?= e($herb['display_name']) ?></a>
                                <i><?= e($herb['scientific_name']) ?></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="shelf-picks" role="group" aria-label="<?= e(t('shelf_choose')) ?>">
                    <?php foreach ($shelf as $i => $herb): ?>
                        <button type="button" data-shelf-pick="<?= (int)$herb['herb_id'] ?>" aria-pressed="<?= $i ? 'false' : 'true' ?>"><?= e($herb['display_name']) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($shelf as $i => $herb): ?>
                    <div class="shelf-made" data-shelf-panel="<?= (int)$herb['herb_id'] ?>" <?= $i ? 'hidden' : '' ?>>
                        <?php foreach ($herb['products'] as $p): ?>
                            <a href="/products/<?= (int)$p['product_id'] ?>">
                                <span class="thumb"><?= picture($p['image'] ?? null, '') ?></span>
                                <span><strong><?= e($p['display_name']) ?></strong><br><?= e(format_price($p['effective_price'] ?? $p['price'])) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <script src="/assets/js/shelf.js" defer></script>
        <?php endif; ?>
    </div>
</div>

<?php if ($ranges): ?>
    <div class="home-section mb-5">
        <div class="home-head"><h2><?= e(t('ranges_title')) ?></h2></div>
        <div class="ranges">
            <?php foreach ($ranges as $range): ?>
                <a href="/products?category=<?= (int)$range['category_id'] ?>">
                    <span class="disc"><?= picture($range['image'] ?? null, '') ?></span>
                    <?= e($range['display_name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

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
    <div class="quiz-band mb-5">
        <div class="quiz-text">
            <h2 class="mb-3"><?= e(t('quiz_title')) ?></h2>
            <p class="mb-4"><?= e(t('quiz_intro')) ?></p>
            <a href="/quiz" class="btn btn-saffron btn-lg px-4"><?= e(t('quiz_start')) ?></a>
        </div>
        <img src="/assets/images/catalog/banners/hero.jpg" alt="" loading="lazy">
    </div>
<?php endif; ?>

<?php if (!empty($season)): ?>
    <div class="card border-0 shadow-sm p-4 mb-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="fw-bold text-success mb-0"><i class="bi bi-cloud-sun me-2"></i><?= e($season['display_name']) ?></h3>
            <a href="/concern/<?= e($season['slug']) ?>" class="btn btn-outline-success btn-sm"><?= e(t('explore_guide')) ?></a>
        </div>
        <?php if (!empty($season['products'])): ?>
            <?= \App\Core\View::partial('product_cards', ['products' => $season['products']]) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Herb of the Day Feature -->
<?php if (!empty($herbOfTheDay)): ?>
    <div class="spotlight mb-5">
        <div class="spot-photo align-self-stretch"><?= picture($herbOfTheDay['image'] ?? null, $herbOfTheDay['display_name'], 'text-success display-3') ?></div>
        <div class="spot-text">
            <span class="badge rounded-pill text-bg-warning mb-2"><?= e(t('herb_of_the_day')) ?></span>
            <h3 class="mb-1"><a href="/herb/<?= (int)$herbOfTheDay['herb_id'] ?>" class="text-decoration-none text-reset"><?= e($herbOfTheDay['display_name']) ?></a></h3>
            <p class="text-muted fst-italic small mb-2"><?= e($herbOfTheDay['scientific_name']) ?></p>
            <p class="mb-3"><?= e(mb_strimwidth((string)($herbOfTheDay['display_uses'] ?: t('uses_pending')), 0, 180, '...')) ?></p>
            <a href="/herb/<?= (int)$herbOfTheDay['herb_id'] ?>" class="btn btn-suwoda"><?= e(t('read_herb_profile')) ?></a>
        </div>
    </div>
<?php endif; ?>

<!-- Shop by health concern -->
<div class="mb-5">
    <div class="home-head">
        <div>
            <h2><?= e(t('health_concerns_title')) ?></h2>
            <p class="text-muted mb-0 mt-1"><?= e(t('health_concerns_subtitle')) ?></p>
        </div>
    </div>
    <div class="topics">
        <?php foreach ($concerns as $c): ?>
            <a href="/concern/<?= e($c['slug']) ?>">
                <span class="bubble"><i class="bi <?= e($c['icon']) ?>" aria-hidden="true"></i></span>
                <span><?= e($c['display_name']) ?></span>
            </a>
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

        <?= \App\Core\View::partial('product_cards', ['products' => $featuredProducts]) ?>
    </div>
<?php endif; ?>

<?php if (!empty($latestPosts)): ?>
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold text-success mb-0"><?= e(t('nav_blogs')) ?></h3>
            <a href="/blogs" class="btn btn-outline-success btn-sm"><?= e(t('view_all')) ?></a>
        </div>
        <div class="row g-3">
            <?php foreach ($latestPosts as $post): ?>
                <div class="col-md-4">
                    <a href="/blog/<?= (int)$post['blog_id'] ?>" class="card h-100 border-0 shadow-sm p-3 text-decoration-none">
                        <span class="fw-bold text-success"><?= e($post['display_title']) ?></span>
                        <span class="small text-secondary"><?= e(mb_strimwidth((string)$post['display_content'], 0, 100, '...')) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="promises mb-5">
    <?php foreach (['pay' => 'bi-cash-coin', 'pack' => 'bi-qr-code-scan', 'ask' => 'bi-chat-dots'] as $key => $icon): ?>
        <div>
            <i class="bi <?= $icon ?>" aria-hidden="true"></i>
            <h3><?= e(t("promise_{$key}_title")) ?></h3>
            <p><?= e(t("promise_{$key}_text")) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<!-- Medical Disclaimer Banner -->
<div class="disclaimer-box text-center mb-4">
    <i class="bi bi-shield-exclamation text-warning me-2"></i>
    <?= e(t('medical_disclaimer')) ?>
</div>
