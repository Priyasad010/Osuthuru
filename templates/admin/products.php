<?php

use App\Core\View;

$categories = $categories ?? [];
?>
<?= View::partial('admin_heading', ['title' => t('admin_products_title'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>

<div class="alert alert-warning py-2 small"><?= e(t('admin_review_warning')) ?></div>

<?php foreach (array_merge([null], $products) as $p): ?>
    <?php
    $pid = (int)($p['product_id'] ?? 0);
    $linked = array_column($p['herbs'] ?? [], 'herb_id');
    ?>
    <div class="card border-0 shadow-sm p-3 mb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <?php if ($p): ?>
                <div>
                    <strong>#<?= $pid ?> <?= e($p['product_name']) ?></strong>
                    <div class="small text-muted">
                        <?= e($p['category_name'] ?? '') ?> · <?= e(format_price($p['effective_price'])) ?>
                        <?php if ($p['has_discount']): ?><span class="badge bg-danger">-<?= number_format($p['discount_percentage'], 0) ?>%</span><?php endif; ?>
                    </div>
                </div>
                <form method="POST" action="/admin/products/stock" class="d-flex gap-1 align-items-center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= $pid ?>">
                    <label class="small" for="q<?= $pid ?>"><?= e(t('admin_stock')) ?></label>
                    <input type="number" id="q<?= $pid ?>" name="quantity" value="<?= (int)$p['quantity'] ?>" min="0" class="form-control form-control-sm" style="width: 90px;">
                    <button type="submit" class="btn btn-sm btn-suwoda" title="<?= e(t('admin_update_stock')) ?>"><?= e(t('save')) ?></button>
                </form>
            <?php else: ?>
                <strong class="text-success"><?= e(t('admin_new_product')) ?></strong>
            <?php endif; ?>
        </div>
        <details class="mt-2">
            <summary class="small text-success"><?= e(t($p ? 'admin_edit' : 'admin_add')) ?></summary>
            <form method="POST" action="/admin/products/save" enctype="multipart/form-data" class="row g-2 mt-1">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $pid ?>">
                <div class="col-md-6">
                    <label class="form-label small mb-0" for="n<?= $pid ?>"><?= e(t('admin_name_en')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="n<?= $pid ?>" name="product_name" maxlength="255" value="<?= e($p['product_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-0" for="ns<?= $pid ?>"><?= e(t('admin_name_si')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="ns<?= $pid ?>" name="product_name_si" maxlength="255" value="<?= e($p['product_name_si'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label small mb-0" for="c<?= $pid ?>"><?= e(t('admin_category')) ?></label>
                    <select class="form-select form-select-sm" id="c<?= $pid ?>" name="category_id" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['category_id'] ?>" <?= (int)($p['category_id'] ?? 0) === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0" for="pr<?= $pid ?>"><?= e(t('col_price')) ?> (Rs.)</label>
                    <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" id="pr<?= $pid ?>" name="price" value="<?= e($p['price'] ?? '') ?>" required>
                </div>
                <?php if (!$p): ?>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0" for="nq"><?= e(t('admin_stock')) ?></label>
                        <input type="number" min="0" class="form-control form-control-sm" id="nq" name="quantity" value="0">
                    </div>
                <?php endif; ?>
                <div class="col-md">
                    <label class="form-label small mb-0" for="im<?= $pid ?>"><?= e(t('admin_image')) ?></label>
                    <input type="file" class="form-control form-control-sm" id="im<?= $pid ?>" name="image_file" accept=".jpg,.jpeg,.png,.webp">
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-0" for="d<?= $pid ?>"><?= e(t('admin_desc_en')) ?></label>
                    <textarea class="form-control form-control-sm" id="d<?= $pid ?>" name="description" rows="3"><?= e($p['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-0" for="ds<?= $pid ?>"><?= e(t('admin_desc_si')) ?></label>
                    <textarea class="form-control form-control-sm" id="ds<?= $pid ?>" name="description_si" rows="3"><?= e($p['description_si'] ?? '') ?></textarea>
                </div>
                <fieldset class="col-12">
                    <legend class="small mb-1"><?= e(t('admin_linked_herbs')) ?></legend>
                    <?php foreach ($allHerbs as $h): ?>
                        <?php $hid = (int)$h['herb_id']; ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="herb_ids[]" value="<?= $hid ?>" id="ph<?= $pid ?>_<?= $hid ?>" <?= in_array($hid, array_map('intval', $linked), true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="ph<?= $pid ?>_<?= $hid ?>"><?= e($h['display_name']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                <div class="col-12"><button type="submit" class="btn btn-suwoda btn-sm"><?= e(t($p ? 'admin_save_changes' : 'admin_add')) ?></button></div>
            </form>
            <?php if ($p): ?>
                <form method="POST" action="/admin/products/delete" class="text-end" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_confirm'))) ?>);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $pid ?>">
                    <button type="submit" class="btn btn-link btn-sm text-danger"><?= e(t('remove')) ?></button>
                </form>
            <?php endif; ?>
        </details>
    </div>
<?php endforeach; ?>
