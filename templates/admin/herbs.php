<div class="mb-4">
    <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
    <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_herbs')) ?></h2>
</div>

<div class="alert alert-warning py-2 small">
    <?= e(t('admin_review_warning')) ?>
    <?= e(t('admin_herb_note')) ?>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>

<?php foreach ($herbs as $h): ?>
    <?php $hid = (int)$h['herb_id']; ?>
    <form method="POST" action="/admin/herbs/update" class="card border-0 shadow-sm p-3 mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="herb_id" value="<?= $hid ?>">
        <h5 class="fw-bold text-success mb-3">
            <?= e($h['name']) ?>
            <small class="text-muted fst-italic fw-normal"><?= e($h['scientific_name']) ?></small>
        </h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-semibold" for="uses_<?= $hid ?>"><?= e(t('admin_uses_en')) ?></label>
                <textarea class="form-control" id="uses_<?= $hid ?>" name="uses" rows="3"><?= e($h['uses'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold" for="uses_si_<?= $hid ?>"><?= e(t('admin_uses_si')) ?></label>
                <textarea class="form-control" id="uses_si_<?= $hid ?>" name="uses_si" rows="3"><?= e($h['uses_si'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="text-end mt-3">
            <button type="submit" class="btn btn-suwoda btn-sm"><?= e(t('save')) ?></button>
        </div>
    </form>
<?php endforeach; ?>
