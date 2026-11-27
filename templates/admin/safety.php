<?php

use App\Repository\SafetyRepo;

// Existing entries, followed by a blank form for a new one
$forms = array_merge([null], $entries);
?>
<div class="mb-4">
    <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
    <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_safety')) ?></h2>
</div>

<div class="alert alert-danger py-2"><i class="bi bi-exclamation-octagon me-1"></i><strong><?= e(t('admin_safety_warning')) ?></strong></div>
<?php if (!empty($flash)): ?><div class="alert alert-success py-2 small"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>

<h4 class="fw-bold text-success mb-3"><?= e(t('admin_entries')) ?> (<?= count($entries) ?>)</h4>
<?php foreach ($forms as $s): ?>
    <?php $id = (int)($s['id'] ?? 0); ?>
    <div class="card border-0 shadow-sm p-3 mb-3 <?= $s ? '' : 'border-start border-4 border-success' ?>">
        <h6 class="fw-bold">
            <?php if ($s): ?>
                <?= e($s['herb_name']) ?>
                <span class="badge <?= (int)$s['published'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                    <?= e((int)$s['published'] === 1 ? t('admin_published') : t('admin_unpublished')) ?>
                </span>
            <?php else: ?>
                <?= e(t('admin_new_entry')) ?>
            <?php endif; ?>
        </h6>
        <form method="POST" action="/admin/safety/entry" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="col-md-4">
                <label class="form-label small" for="herb<?= $id ?>"><?= e(t('admin_herb')) ?></label>
                <select class="form-select form-select-sm" id="herb<?= $id ?>" name="herb_id" required>
                    <?php foreach ($herbs as $h): ?>
                        <option value="<?= (int)$h['herb_id'] ?>" <?= (int)($s['herb_id'] ?? 0) === (int)$h['herb_id'] ? 'selected' : '' ?>><?= e($h['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small" for="flag<?= $id ?>"><?= e(t('admin_flag')) ?></label>
                <select class="form-select form-select-sm" id="flag<?= $id ?>" name="flag_id" required>
                    <?php foreach ($flags as $f): ?>
                        <option value="<?= (int)$f['flag_id'] ?>" <?= (int)($s['flag_id'] ?? 0) === (int)$f['flag_id'] ? 'selected' : '' ?>><?= e($f['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="sev<?= $id ?>"><?= e(t('admin_severity')) ?></label>
                <select class="form-select form-select-sm" id="sev<?= $id ?>" name="severity">
                    <?php foreach (SafetyRepo::SEVERITIES as $severity): ?>
                        <option value="<?= e($severity) ?>" <?= ($s['severity'] ?? 'caution') === $severity ? 'selected' : '' ?>><?= e(t('severity_' . $severity)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small" for="en<?= $id ?>"><?= e(t('admin_warning_en')) ?></label>
                <textarea class="form-control form-control-sm" rows="2" id="en<?= $id ?>" name="note_en"><?= e($s['note_en'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label small" for="si<?= $id ?>"><?= e(t('admin_warning_si')) ?></label>
                <textarea class="form-control form-control-sm" rows="2" id="si<?= $id ?>" name="note_si"><?= e($s['note_si'] ?? '') ?></textarea>
            </div>
            <div class="col-md-8">
                <label class="form-label small" for="src<?= $id ?>"><?= e(t('admin_source')) ?></label>
                <input type="text" class="form-control form-control-sm" id="src<?= $id ?>" name="source_note" maxlength="500" value="<?= e($s['source_note'] ?? '') ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="pub<?= $id ?>" name="published" value="1" <?= !empty($s['published']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="pub<?= $id ?>"><?= e(t('admin_published')) ?></label>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-brand btn-sm w-100"><?= e(t('save')) ?></button></div>
        </form>
        <?php if ($s): ?>
            <form method="POST" action="/admin/safety/entry/delete" class="text-end mt-1" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_confirm'))) ?>);">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-link btn-sm text-danger"><?= e(t('remove')) ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<h4 class="fw-bold text-success mt-4 mb-3"><?= e(t('admin_flags')) ?></h4>
<div class="card border-0 shadow-sm p-3">
    <?php foreach (array_merge($flags, [null]) as $f): ?>
        <?php $fid = (int)($f['flag_id'] ?? 0); ?>
        <div class="d-flex gap-1 align-items-start mb-2">
            <form method="POST" action="/admin/safety/flag" class="row g-1 flex-grow-1">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $fid ?>">
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm" name="code" value="<?= e($f['code'] ?? '') ?>" placeholder="<?= e(t('admin_code')) ?>" aria-label="<?= e(t('admin_code')) ?>" maxlength="50" <?= $f ? 'readonly' : 'required pattern="[a-z0-9_]{2,50}"' ?>>
                </div>
                <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="name_en" value="<?= e($f['name_en'] ?? '') ?>" placeholder="<?= e(t('admin_flag_en')) ?>" aria-label="<?= e(t('admin_flag_en')) ?>" maxlength="150" required></div>
                <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="name_si" value="<?= e($f['name_si'] ?? '') ?>" placeholder="<?= e(t('admin_flag_si')) ?>" aria-label="<?= e(t('admin_flag_si')) ?>" maxlength="150"></div>
                <div class="col-md-1"><button type="submit" class="btn btn-outline-success btn-sm w-100"><?= e(t('save')) ?></button></div>
            </form>
            <?php if ($f): ?>
                <form method="POST" action="/admin/safety/flag/delete" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_flag_confirm'))) ?>);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $fid ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="<?= e(t('remove')) ?>"><i class="bi bi-trash"></i></button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
