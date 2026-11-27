<h2 class="fw-bold text-success mb-2"><?= e(t('credits_title')) ?></h2>
<p class="text-muted"><?= e(t('credits_intro')) ?></p>

<ul class="list-unstyled row g-3">
    <?php foreach ($credits as $c): ?>
        <li class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm p-2 d-flex flex-row gap-3">
                <img src="/assets/images/catalog/<?= e($c['file']) ?>" alt="" width="96" height="72" loading="lazy" style="object-fit:cover;" class="rounded flex-shrink-0">
                <div class="small text-break">
                    <a href="<?= e($c['source']) ?>" rel="noopener" target="_blank"><?= e($c['title']) ?></a><br>
                    <?= e($c['author']) ?><br>
                    <?php if (!empty($c['licence_url'])): ?>
                        <a href="<?= e($c['licence_url']) ?>" rel="noopener license" target="_blank"><?= e($c['licence']) ?></a>
                    <?php else: ?>
                        <?= e($c['licence']) ?>
                    <?php endif; ?>
                    &middot; <?= e(t('credits_changed')) ?>
                </div>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
