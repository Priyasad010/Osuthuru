<?php use App\Core\View; ?>
<?= View::partial('admin_heading', ['title' => t('admin_messages'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>
<?php if (empty($messages)): ?><p class="text-muted"><?= e(t('report_none')) ?></p><?php endif; ?>
<?php foreach ($messages as $m): ?>
    <div class="card border-0 shadow-sm p-3 mb-2 <?= (int)$m['handled'] ? 'opacity-75' : '' ?>">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div class="text-break">
                <h2 class="h6 mb-1"><?= e($m['subject']) ?>
                    <span class="badge <?= (int)$m['handled'] ? 'bg-secondary' : 'bg-warning text-dark' ?>"><?= e(t((int)$m['handled'] ? 'message_done' : 'message_new')) ?></span></h2>
                <p class="small text-muted mb-2"><?= e($m['name']) ?> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a> · <?= e($m['created_at']) ?></p>
                <p class="small mb-0"><?= nl2br(e($m['message'])) ?></p>
            </div>
            <div class="d-flex gap-2 align-items-start">
                <form method="POST" action="/admin/messages/handled">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$m['message_id'] ?>">
                    <input type="hidden" name="handled" value="<?= (int)$m['handled'] ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-outline-success btn-sm"><?= e(t((int)$m['handled'] ? 'message_reopen' : 'message_mark_done')) ?></button>
                </form>
                <form method="POST" action="/admin/messages/delete" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_confirm'))) ?>);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$m['message_id'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="<?= e(t('remove')) ?>" aria-label="<?= e(t('remove')) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?= View::partial('pagination', ['pager' => $pager, 'path' => '/admin/messages', 'query' => []]) ?>
