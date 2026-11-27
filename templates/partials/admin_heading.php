<div class="mb-4">
    <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
    <h2 class="fw-bold text-success mt-1 mb-0"><?= e($title) ?></h2>
</div>
<?php if (!empty($flash)): ?><div class="alert alert-success py-2 small" role="status"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small" role="alert"><?= e($error) ?></div><?php endif; ?>
