<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <h2 class="fw-bold text-success mb-2"><i class="bi bi-stars me-2"></i><?= e(t('interests_title')) ?></h2>
        <p class="text-muted"><?= e(t('interests_intro')) ?></p>

        <?php if (!empty($flash)): ?>
            <div class="alert alert-success py-2 small" role="status"><?= e($flash) ?></div>
        <?php endif; ?>

        <form method="POST" action="/profile/categories" class="card border-0 shadow-sm p-4">
            <?= csrf_field() ?>
            <?php foreach ($categories as $c): ?>
                <?php $cid = (int)$c['category_id']; ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="categories[]" value="<?= $cid ?>" id="interest<?= $cid ?>"
                        <?= in_array($cid, $selected, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="interest<?= $cid ?>"><?= e($c['display_name']) ?></label>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-brand mt-3"><?= e(t('save')) ?></button>
        </form>
    </div>
</div>
