<div class="row justify-content-center py-5">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <i class="bi bi-key-fill text-success fs-1 mb-2"></i>
                    <h3 class="fw-bold text-success"><?= e(t('forgot_title')) ?></h3>
                    <p class="text-muted small"><?= e(t('forgot_subtitle')) ?></p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-success py-2 small"><?= e($message) ?></div>
                <?php endif; ?>

                <?php include __DIR__ . '/../partials/test_inbox.php'; ?>

                <form method="POST" action="/forgot-password">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold"><?= e(t('registered_email')) ?></label>
                        <input type="email" class="form-control" id="email" name="email" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-brand w-100 py-2 fw-semibold"><?= e(t('send_reset_link')) ?></button>
                </form>

                <div class="text-center mt-4 pt-2 border-top small text-muted">
                    <?= e(t('remember_password')) ?> <a href="/login" class="text-success fw-semibold text-decoration-none"><?= e(t('back_to_login')) ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
