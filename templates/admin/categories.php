<?php use App\Core\View; ?>
<?= View::partial('admin_heading', ['title' => t('admin_categories'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>

<?php foreach (array_merge([null], $categories) as $c): ?>
    <?php $id = (int)($c['category_id'] ?? 0); ?>
    <div class="card border-0 shadow-sm p-2 mb-2 <?= $c ? '' : 'border-start border-4 border-success' ?>">
        <div class="d-flex gap-2 align-items-start">
            <form method="POST" action="/admin/categories/save" class="row g-2 flex-grow-1">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="col-md-5"><input type="text" name="name" class="form-control form-control-sm" value="<?= e($c['name'] ?? '') ?>" placeholder="<?= e(t('admin_flag_en')) ?>" aria-label="<?= e(t('admin_flag_en')) ?>" maxlength="255" required></div>
                <div class="col-md-5"><input type="text" name="name_si" class="form-control form-control-sm" value="<?= e($c['name_si'] ?? '') ?>" placeholder="<?= e(t('admin_flag_si')) ?>" aria-label="<?= e(t('admin_flag_si')) ?>" maxlength="255"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-suwoda btn-sm w-100"><?= e(t($c ? 'save' : 'admin_add')) ?></button></div>
            </form>
            <?php if ($c): ?>
                <form method="POST" action="/admin/categories/delete" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_confirm'))) ?>);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="<?= e(t('remove')) ?>"><i class="bi bi-trash"></i></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
