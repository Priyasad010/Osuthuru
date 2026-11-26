<div class="mb-4">
    <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Admin Dashboard</a>
    <h2 class="fw-bold text-success mt-1 mb-0">Herb Text</h2>
</div>

<div class="alert alert-warning py-2 small">
    Enter only text that a qualified practitioner has reviewed. Do not claim that a herb treats or cures a disease.
    Empty fields show "awaiting review" to customers.
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
                <label class="form-label small fw-semibold" for="uses_<?= $hid ?>">Uses (English)</label>
                <textarea class="form-control" id="uses_<?= $hid ?>" name="uses" rows="3"><?= e($h['uses'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold" for="uses_si_<?= $hid ?>">Uses (Sinhala)</label>
                <textarea class="form-control" id="uses_si_<?= $hid ?>" name="uses_si" rows="3"><?= e($h['uses_si'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="text-end mt-3">
            <button type="submit" class="btn btn-suwoda btn-sm">Save</button>
        </div>
    </form>
<?php endforeach; ?>
