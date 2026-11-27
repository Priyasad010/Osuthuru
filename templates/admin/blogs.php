<?php use App\Core\View; ?>
<?= View::partial('admin_heading', ['title' => t('admin_blogs'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>

<div class="alert alert-warning py-2 small"><?= e(t('admin_review_warning')) ?></div>

<div class="card border-0 shadow-sm p-3 mb-3">
    <h6 class="fw-bold text-success"><?= e(t('blog_tags')) ?></h6>
    <div class="d-flex flex-wrap gap-1 mb-2">
        <?php foreach ($tags as $tag): ?>
            <form method="POST" action="/admin/blogs/tag/delete">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$tag['tag_id'] ?>">
                <button type="submit" class="btn btn-sm btn-light border" title="<?= e(t('remove')) ?>"><?= e($tag['name_en']) ?> <i class="bi bi-x"></i></button>
            </form>
        <?php endforeach; ?>
    </div>
    <form method="POST" action="/admin/blogs/tag" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-md-5"><input type="text" name="name_en" class="form-control form-control-sm" maxlength="80" placeholder="<?= e(t('admin_flag_en')) ?>" aria-label="<?= e(t('admin_flag_en')) ?>" required></div>
        <div class="col-md-5"><input type="text" name="name_si" class="form-control form-control-sm" maxlength="80" placeholder="<?= e(t('admin_flag_si')) ?>" aria-label="<?= e(t('admin_flag_si')) ?>"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-brand btn-sm w-100"><?= e(t('admin_add')) ?></button></div>
    </form>
</div>

<?php foreach (array_merge([null], $posts) as $post): ?>
    <?php $id = (int)($post['blog_id'] ?? 0); ?>
    <details class="card border-0 shadow-sm p-3 mb-2">
        <summary class="fw-bold text-success"><?= $post ? e($post['title']) : e(t('blog_new')) ?></summary>
        <form method="POST" action="/admin/blogs/save" enctype="multipart/form-data" class="row g-2 mt-1">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <?php foreach (['title' => 'blog_title_en', 'title_si' => 'blog_title_si'] as $name => $label): ?>
                <div class="col-md-6">
                    <label class="form-label small mb-0" for="<?= $name . $id ?>"><?= e(t($label)) ?></label>
                    <input type="text" class="form-control form-control-sm" id="<?= $name . $id ?>" name="<?= $name ?>" maxlength="255" value="<?= e($post[$name] ?? '') ?>" <?= $name === 'title' ? 'required' : '' ?>>
                </div>
            <?php endforeach; ?>
            <?php foreach (['content' => 'blog_content_en', 'content_si' => 'blog_content_si'] as $name => $label): ?>
                <div class="col-md-6">
                    <label class="form-label small mb-0" for="<?= $name . $id ?>"><?= e(t($label)) ?></label>
                    <textarea class="form-control form-control-sm" id="<?= $name . $id ?>" name="<?= $name ?>" rows="6" <?= $name === 'content' ? 'required' : '' ?>><?= e($post[$name] ?? '') ?></textarea>
                </div>
            <?php endforeach; ?>
            <fieldset class="col-12">
                <legend class="small mb-1"><?= e(t('blog_tags')) ?></legend>
                <?php $chosen = array_map('intval', array_column($post['tags'] ?? [], 'tag_id')); ?>
                <?php foreach ($tags as $tag): ?>
                    <?php $tid = (int)$tag['tag_id']; ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="tag_ids[]" value="<?= $tid ?>" id="bt<?= $id ?>_<?= $tid ?>" <?= in_array($tid, $chosen, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="bt<?= $id ?>_<?= $tid ?>"><?= e($tag['display_name']) ?></label>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="col-12">
                <legend class="small mb-1"><?= e(t('blog_related')) ?></legend>
                <?php foreach ($products as $p): ?>
                    <?php $pid = (int)$p['product_id']; ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="product_ids[]" value="<?= $pid ?>" id="bp<?= $id ?>_<?= $pid ?>" <?= in_array($pid, $post['product_ids'] ?? [], true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="bp<?= $id ?>_<?= $pid ?>"><?= e($p['display_name']) ?></label>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <div class="col-md-8">
                <label class="form-label small mb-0" for="bimg<?= $id ?>"><?= e(t('admin_image')) ?></label>
                <input type="file" class="form-control form-control-sm" id="bimg<?= $id ?>" name="image_file" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-brand btn-sm w-100"><?= e(t($post ? 'save' : 'admin_add')) ?></button></div>
        </form>
        <?php if ($post): ?>
            <form method="POST" action="/admin/blogs/delete" class="text-end mt-1" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_confirm'))) ?>);">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-link btn-sm text-danger"><?= e(t('remove')) ?></button>
            </form>
        <?php endif; ?>
    </details>
<?php endforeach; ?>
