<h2 class="fw-bold text-success mb-3"><i class="bi bi-journal-richtext me-2"></i><?= e(t('nav_blogs')) ?></h2>

<?php if (!empty($tags)): ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="/blogs" class="btn btn-sm <?= empty($selectedTag) ? 'btn-brand' : 'btn-outline-success' ?>"><?= e(t('filter_all')) ?></a>
        <?php foreach ($tags as $tag): ?>
            <a href="/blogs?tag=<?= (int)$tag['tag_id'] ?>" class="btn btn-sm <?= ($selectedTag ?? 0) === (int)$tag['tag_id'] ? 'btn-brand' : 'btn-outline-success' ?>"><?= e($tag['display_name']) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($posts)): ?>
    <p class="text-muted"><?= e(t('report_none')) ?></p>
<?php else: ?>
    <div class="row g-4 mb-4">
        <?php foreach ($posts as $post): ?>
            <div class="col-md-6 col-lg-4">
                <article class="card h-100 border-0 shadow-sm">
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 160px; overflow: hidden;">
                        <?= picture($post['image'] ?? null, $post['display_title']) ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <small class="text-muted"><?= e(substr((string)$post['published_date'], 0, 10)) ?></small>
                        <h5 class="fw-bold mt-1"><a href="/blog/<?= (int)$post['blog_id'] ?>" class="text-success text-decoration-none"><?= e($post['display_title']) ?></a></h5>
                        <p class="small text-secondary mb-0"><?= e(mb_strimwidth((string)$post['display_content'], 0, 130, '...')) ?></p>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="disclaimer-box mb-3"><i class="bi bi-shield-exclamation text-warning me-2"></i><?= e(t('medical_disclaimer')) ?></div>
