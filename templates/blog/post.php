<a href="/blogs" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('nav_blogs')) ?></a>

<?php if (!$post): ?>
    <div class="alert alert-warning mt-3"><?= e(t('blog_err_missing')) ?></div>
<?php else: ?>
    <article class="card border-0 shadow-sm p-4 mt-2 mb-4">
        <small class="text-muted"><?= e(substr((string)$post['published_date'], 0, 10)) ?></small>
        <h2 class="fw-bold text-success"><?= e($post['display_title']) ?></h2>
        <?php if ($post['tags']): ?>
            <div class="d-flex flex-wrap gap-1 mb-3">
                <?php foreach ($post['tags'] as $tag): ?>
                    <a href="/blogs?tag=<?= (int)$tag['tag_id'] ?>" class="badge text-bg-light border text-success text-decoration-none"><?= e($tag['display_name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (image_url($post['image'] ?? null)): ?>
            <img src="<?= e($post['image']) ?>" alt="<?= e($post['display_title']) ?>" class="img-fluid rounded mb-3">
        <?php endif; ?>
        <div class="text-break"><?= nl2br(e($post['display_content'])) ?></div>
    </article>

    <?php if ($post['products']): ?>
        <h4 class="fw-bold text-success mb-3"><?= e(t('blog_related')) ?></h4>
        <div class="mb-4"><?= \App\Core\View::partial('product_cards', ['products' => $post['products']]) ?></div>
    <?php endif; ?>
<?php endif; ?>

<div class="disclaimer-box my-3"><i class="bi bi-shield-exclamation text-warning me-2"></i><?= e(t('medical_disclaimer')) ?></div>
