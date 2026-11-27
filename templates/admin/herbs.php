<?php

use App\Core\View;

$concerns = $concerns ?? [];
?>
<?= View::partial('admin_heading', ['title' => t('admin_herbs'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>

<div class="alert alert-warning py-2 small">
    <?= e(t('admin_review_warning')) ?>
    <?= e(t('admin_herb_note')) ?>
</div>

<?php foreach (array_merge([null], $herbs) as $h): ?>
    <?php $hid = (int)($h['herb_id'] ?? 0); ?>
    <details class="card border-0 shadow-sm p-3 mb-2">
        <summary class="fw-bold text-success">
            <?= $h ? e($h['name']) : e(t('admin_new_herb')) ?>
            <?php if ($h): ?><small class="text-muted fst-italic fw-normal"><?= e($h['scientific_name']) ?></small><?php endif; ?>
        </summary>
        <form method="POST" action="/admin/herbs/save" enctype="multipart/form-data" class="row g-2 mt-1">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $hid ?>">
            <?php foreach (['name' => ['field_name', 100], 'name_si' => ['admin_flag_si', 150], 'scientific_name' => ['scientific_name', 150]] as $field => [$label, $max]): ?>
                <div class="col-md-4">
                    <label class="form-label small mb-0" for="<?= $field . $hid ?>"><?= e(t($label)) ?></label>
                    <input type="text" class="form-control form-control-sm" id="<?= $field . $hid ?>" name="<?= $field ?>" maxlength="<?= $max ?>" value="<?= e($h[$field] ?? '') ?>" <?= $field === 'name_si' ? '' : 'required' ?>>
                </div>
            <?php endforeach; ?>
            <?php foreach (['uses' => 'admin_uses_en', 'uses_si' => 'admin_uses_si'] as $field => $label): ?>
                <div class="col-md-6">
                    <label class="form-label small mb-0" for="<?= $field . $hid ?>"><?= e(t($label)) ?></label>
                    <textarea class="form-control form-control-sm" id="<?= $field . $hid ?>" name="<?= $field ?>" rows="3"><?= e($h[$field] ?? '') ?></textarea>
                </div>
            <?php endforeach; ?>
            <fieldset class="col-12">
                <legend class="small mb-1"><?= e(t('suggest_concerns')) ?></legend>
                <?php foreach ($concerns as $c): ?>
                    <?php $cid = (int)$c['concern_id']; ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="concern_ids[]" value="<?= $cid ?>" id="hc<?= $hid ?>_<?= $cid ?>" <?= in_array($cid, $h['concern_ids'] ?? [], true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="hc<?= $hid ?>_<?= $cid ?>"><?= e($c['display_name']) ?></label>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <div class="col-md-8">
                <label class="form-label small mb-0" for="img<?= $hid ?>"><?= e(t('admin_image')) ?></label>
                <input type="file" class="form-control form-control-sm" id="img<?= $hid ?>" name="image_file" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-suwoda btn-sm w-100"><?= e(t($h ? 'save' : 'admin_add')) ?></button></div>
        </form>
        <?php if ($h): ?>
            <form method="POST" action="/admin/herbs/delete" class="text-end mt-1" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_herb_confirm'))) ?>);">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $hid ?>">
                <button type="submit" class="btn btn-link btn-sm text-danger"><?= e(t('remove')) ?></button>
            </form>
        <?php endif; ?>
    </details>
<?php endforeach; ?>
