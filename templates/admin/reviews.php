<?php use App\Core\View; ?>
<?= View::partial('admin_heading', ['title' => t('admin_reviews'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>

<?php if (empty($reviews)): ?><p class="text-muted"><?= e(t('report_none')) ?></p><?php endif; ?>
<?php foreach ($reviews as $r): ?>
    <div class="card border-0 shadow-sm p-3 mb-2">
        <div class="d-flex justify-content-between gap-2">
            <div class="text-break">
                <strong><?= e($r['product_name'] ?? '-') ?></strong> · <?= (int)$r['rating'] ?>/5 · <?= e($r['user_name'] ?? '-') ?>
                <div class="small"><?= nl2br(e($r['comment'])) ?></div>
                <small class="text-muted"><?= e($r['review_date']) ?></small>
            </div>
            <form method="POST" action="/admin/reviews/delete" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_confirm'))) ?>);">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$r['review_id'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm" title="<?= e(t('remove')) ?>"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!empty($pager)): ?>
    <?= View::partial('pagination', ['pager' => $pager, 'path' => '/admin/reviews', 'query' => []]) ?>
<?php endif; ?>
