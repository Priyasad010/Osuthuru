<?php

use App\Core\View;

$show = fn(?string $mmdd) => $mmdd ? substr($mmdd, 0, 2) . '-' . substr($mmdd, 2) : '';
?>
<?= View::partial('admin_heading', ['title' => t('admin_seasonal'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>
<p class="small text-muted"><?= e(t('seasonal_help')) ?></p>

<?php foreach (array_merge([null], $collections) as $c): ?>
    <?php $id = (int)($c['id'] ?? 0); ?>
    <div class="card border-0 shadow-sm p-2 mb-2 <?= $c ? '' : 'border-start border-4 border-success' ?>">
        <form method="POST" action="/admin/seasonal/save" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="col-md-3"><label class="form-label small mb-0" for="se<?= $id ?>"><?= e(t('admin_flag_en')) ?></label><input type="text" class="form-control form-control-sm" id="se<?= $id ?>" name="name_en" maxlength="150" value="<?= e($c['name_en'] ?? '') ?>" required></div>
            <div class="col-md-3"><label class="form-label small mb-0" for="ss<?= $id ?>"><?= e(t('admin_flag_si')) ?></label><input type="text" class="form-control form-control-sm" id="ss<?= $id ?>" name="name_si" maxlength="150" value="<?= e($c['name_si'] ?? '') ?>"></div>
            <div class="col-6 col-md-1"><label class="form-label small mb-0" for="sf<?= $id ?>"><?= e(t('report_from')) ?></label><input type="text" class="form-control form-control-sm" id="sf<?= $id ?>" name="start" pattern="\d{2}-\d{2}" placeholder="05-01" value="<?= e($show($c['start_mmdd'] ?? null)) ?>" required></div>
            <div class="col-6 col-md-1"><label class="form-label small mb-0" for="st<?= $id ?>"><?= e(t('report_to')) ?></label><input type="text" class="form-control form-control-sm" id="st<?= $id ?>" name="end" pattern="\d{2}-\d{2}" placeholder="07-31" value="<?= e($show($c['end_mmdd'] ?? null)) ?>" required></div>
            <div class="col-md-2"><label class="form-label small mb-0" for="sc<?= $id ?>"><?= e(t('suggest_concern')) ?></label>
                <select class="form-select form-select-sm" id="sc<?= $id ?>" name="concern_id">
                    <?php foreach ($concerns as $concern): ?>
                        <option value="<?= (int)$concern['concern_id'] ?>" <?= (int)($c['concern_id'] ?? 0) === (int)$concern['concern_id'] ? 'selected' : '' ?>><?= e($concern['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-brand btn-sm w-100"><?= e(t($c ? 'save' : 'admin_add')) ?></button></div>
        </form>
        <?php if ($c): ?>
            <form method="POST" action="/admin/seasonal/delete" class="text-end" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_confirm'))) ?>);">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-link btn-sm text-danger"><?= e(t('remove')) ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
