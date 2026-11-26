<?php use App\Service\DoshaService; ?>
<div class="mb-4">
    <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
    <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_dosha')) ?></h2>
</div>

<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e(t('admin_dosha_note')) ?></div>
<?php if (!empty($flash)): ?><div class="alert alert-success py-2 small"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>

<?php foreach ($products as $p): ?>
    <?php $pid = (int)$p['product_id']; ?>
    <form method="POST" action="/admin/dosha/save" class="card border-0 shadow-sm p-3 mb-2">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= $pid ?>">
        <div class="row g-2 align-items-end">
            <div class="col-md-5 fw-semibold"><?= e($p['display_name']) ?></div>
            <?php foreach (DoshaService::DOSHAS as $dosha): ?>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="s<?= $pid . $dosha ?>"><?= e(t('dosha_' . $dosha)) ?></label>
                    <select class="form-select form-select-sm" id="s<?= $pid . $dosha ?>" name="strength[<?= e($dosha) ?>]">
                        <?php foreach ([0, 1, 2, 3] as $value): ?>
                            <option value="<?= $value ?>" <?= ($strengths[$pid][$dosha] ?? 0) === $value ? 'selected' : '' ?>><?= $value === 0 ? '–' : $value ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
            <div class="col-md-1"><button type="submit" class="btn btn-suwoda btn-sm w-100"><?= e(t('save')) ?></button></div>
        </div>
    </form>
<?php endforeach; ?>
