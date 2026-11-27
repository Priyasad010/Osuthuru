<?php

use App\Core\View;

$local = fn(?string $v) => $v ? str_replace(' ', 'T', substr($v, 0, 16)) : '';
?>
<?= View::partial('admin_heading', ['title' => t('admin_discounts'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>

<?php foreach (array_merge([null], $discounts) as $d): ?>
    <?php $id = (int)($d['discount_id'] ?? 0); ?>
    <div class="card border-0 shadow-sm p-2 mb-2 <?= $d ? '' : 'border-start border-4 border-success' ?>">
        <div class="d-flex flex-wrap gap-2 align-items-end">
            <form method="POST" action="/admin/discounts/save" class="row g-2 flex-grow-1 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="col-md-4">
                    <label class="form-label small mb-0" for="dp<?= $id ?>"><?= e(t('col_product')) ?></label>
                    <select name="product_id" id="dp<?= $id ?>" class="form-select form-select-sm" required>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['product_id'] ?>" <?= (int)($d['product_id'] ?? 0) === (int)$p['product_id'] ? 'selected' : '' ?>><?= e($p['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="dv<?= $id ?>"><?= e(t('admin_percent')) ?></label>
                    <input type="number" step="0.01" min="0.01" max="90" name="discount_percentage" id="dv<?= $id ?>" class="form-control form-control-sm" value="<?= e($d['discount_percentage'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="ds<?= $id ?>"><?= e(t('report_from')) ?></label>
                    <input type="datetime-local" name="start_date" id="ds<?= $id ?>" class="form-control form-control-sm" value="<?= e($local($d['start_date'] ?? null)) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="de<?= $id ?>"><?= e(t('report_to')) ?></label>
                    <input type="datetime-local" name="end_date" id="de<?= $id ?>" class="form-control form-control-sm" value="<?= e($local($d['end_date'] ?? null)) ?>" required>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-brand btn-sm w-100"><?= e(t($d ? 'save' : 'admin_add')) ?></button></div>
            </form>
            <?php if ($d): ?>
                <form method="POST" action="/admin/discounts/delete" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_confirm'))) ?>);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="<?= e(t('remove')) ?>"><i class="bi bi-trash"></i></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
