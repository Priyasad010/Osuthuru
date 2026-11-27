<?php

use App\Core\Auth;
use App\Service\ReviewService;

/** Expects $productId. */
$viewer = Auth::check() ? Auth::id() : null;
$reviews = ReviewService::forProduct($productId, $viewer);
$mayReview = $viewer !== null && ReviewService::canReview($viewer, $productId);
$average = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$stars = fn(int $n) => str_repeat('★', $n) . str_repeat('☆', 5 - $n);
?>
<section id="reviews" class="mb-4" aria-labelledby="reviewsTitle">
    <h4 id="reviewsTitle" class="fw-bold text-success mb-1"><?= e(t('review_title')) ?></h4>
    <p class="text-muted small">
        <?= $reviews ? e(t('review_average', ['average' => number_format($average, 1), 'count' => count($reviews)])) : e(t('review_none')) ?>
    </p>

    <?php if ($mayReview): ?>
        <form method="POST" action="/products/<?= (int)$productId ?>/reviews" enctype="multipart/form-data" class="card border-0 shadow-sm p-3 mb-3">
            <?= csrf_field() ?>
            <label class="form-label small fw-semibold" for="rating"><?= e(t('review_rating')) ?></label>
            <select class="form-select form-select-sm mb-2" id="rating" name="rating" required>
                <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> <?= $stars($i) ?></option><?php endfor; ?>
            </select>
            <label class="form-label small fw-semibold" for="comment"><?= e(t('review_comment')) ?></label>
            <textarea class="form-control mb-2" id="comment" name="comment" rows="3" maxlength="2000" required></textarea>
            <label class="form-label small fw-semibold" for="photos"><?= e(t('review_photos', ['max' => ReviewService::MAX_PHOTOS])) ?></label>
            <input type="file" class="form-control form-control-sm mb-3" id="photos" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple>
            <button type="submit" class="btn btn-suwoda btn-sm align-self-start"><?= e(t('review_submit')) ?></button>
        </form>
    <?php endif; ?>

    <?php foreach ($reviews as $r): ?>
        <article class="card border-0 shadow-sm p-3 mb-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <strong><?= e($r['user_name'] ?? t('user_fallback')) ?></strong>
                <span class="text-warning-emphasis" aria-label="<?= (int)$r['rating'] ?> / 5"><?= $stars(max(0, min(5, (int)$r['rating']))) ?></span>
                <?php if ($r['verified']): ?>
                    <span class="badge bg-success"><i class="bi bi-patch-check me-1"></i><?= e(t('review_verified')) ?></span>
                <?php endif; ?>
                <small class="text-muted ms-auto"><?= e(substr((string)$r['review_date'], 0, 10)) ?></small>
            </div>
            <p class="mb-2 mt-1"><?= nl2br(e($r['comment'])) ?></p>
            <?php if ($r['photos']): ?>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php foreach ($r['photos'] as $photo): ?>
                        <?php if (image_url($photo)): ?>
                            <a href="<?= e($photo) ?>" target="_blank" rel="noopener"><img src="<?= e($photo) ?>" alt="<?= e(t('review_photo_alt')) ?>" width="84" height="84" class="rounded" style="object-fit: cover;" loading="lazy"></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="small">
                <?php if ($viewer !== null && $viewer !== (int)$r['user_id']): ?>
                    <form method="POST" action="/reviews/<?= (int)$r['review_id'] ?>/helpful" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm <?= $r['voted'] ? 'btn-success' : 'btn-outline-secondary' ?>" aria-pressed="<?= $r['voted'] ? 'true' : 'false' ?>">
                            <i class="bi bi-hand-thumbs-up me-1"></i><?= e(t('review_helpful')) ?> (<?= (int)$r['helpful'] ?>)
                        </button>
                    </form>
                <?php else: ?>
                    <span class="text-muted"><i class="bi bi-hand-thumbs-up me-1"></i><?= e(t('review_helpful')) ?> (<?= (int)$r['helpful'] ?>)</span>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>
