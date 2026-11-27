<div class="mb-4">
    <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
    <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_assistant')) ?></h2>
</div>

<?php if (!empty($flash)): ?><div class="alert alert-success py-2 small"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
<div class="alert <?= $configured ? 'alert-success' : 'alert-warning' ?> py-2 small">
    <?= e(t($configured ? 'admin_llm_on' : 'admin_llm_off')) ?>
</div>

<h4 class="fw-bold text-success mb-2"><?= e(t('admin_unanswered')) ?></h4>
<p class="small text-muted"><?= e(t('admin_unanswered_help')) ?></p>
<div class="card border-0 shadow-sm mb-4">
    <ul class="list-group list-group-flush">
        <?php if (empty($questions)): ?>
            <li class="list-group-item text-muted small"><?= e(t('admin_unanswered_none')) ?></li>
        <?php endif; ?>
        <?php foreach ($questions as $q): ?>
            <li class="list-group-item d-flex justify-content-between gap-2">
                <div class="text-break">
                    <div><?= e($q['content']) ?></div>
                    <small class="text-muted"><?= e($q['created_at']) ?></small>
                </div>
                <form method="POST" action="/admin/assistant/dismiss">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$q['message_id'] ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><?= e(t('admin_dismiss')) ?></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<h4 class="fw-bold text-success mb-2"><?= e(t('admin_keywords')) ?></h4>
<p class="small text-muted"><?= e(t('admin_keywords_help')) ?></p>
<?php foreach ($concerns as $c): ?>
    <div class="card border-0 shadow-sm p-3 mb-2">
        <h6 class="fw-bold"><?= e($c['display_name']) ?></h6>
        <div class="d-flex flex-wrap gap-1 mb-2">
            <?php foreach ($keywords as $k): ?>
                <?php if ((int)$k['concern_id'] === (int)$c['concern_id']): ?>
                    <form method="POST" action="/admin/assistant/keyword/delete">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$k['keyword_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-light border" title="<?= e(t('remove')) ?>"><?= e($k['keyword']) ?> <i class="bi bi-x"></i></button>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <form method="POST" action="/admin/assistant/keyword" class="d-flex gap-1">
            <?= csrf_field() ?>
            <input type="hidden" name="concern_id" value="<?= (int)$c['concern_id'] ?>">
            <input type="text" name="keyword" class="form-control form-control-sm" maxlength="100" minlength="2" placeholder="<?= e(t('admin_new_keyword')) ?>" aria-label="<?= e(t('admin_new_keyword')) ?>" required>
            <button type="submit" class="btn btn-brand btn-sm"><?= e(t('save')) ?></button>
        </form>
    </div>
<?php endforeach; ?>
