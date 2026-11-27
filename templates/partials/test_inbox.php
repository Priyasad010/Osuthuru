<?php if (!empty($mailInbox)): ?>
    <div class="alert alert-warning py-2 small">
        <?= e(t('mail_test_notice')) ?>
        <a href="<?= e($mailInbox) ?>" class="alert-link" target="_blank" rel="noopener"><?= e(t('mail_test_open')) ?></a>
    </div>
<?php endif; ?>
