<div class="row justify-content-center py-5">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <i class="bi bi-shield-lock-fill text-success fs-1 mb-2"></i>
                    <h3 class="fw-bold text-success"><?= e(t('reset_title')) ?></h3>
                    <p class="text-muted small"><?= e(t('reset_subtitle')) ?></p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-2 small">
                        <?= e($success) ?>
                        <div class="mt-2">
                            <a href="/login" class="btn btn-sm btn-success"><?= e(t('login_new_password')) ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" action="/reset-password">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div class="mb-3">
                            <label for="password" class="form-label small fw-semibold"><?= e(t('new_password')) ?></label>
                            <input type="password" class="form-control" id="password" name="password" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label small fw-semibold"><?= e(t('confirm_new_password')) ?></label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 py-2 fw-semibold"><?= e(t('update_password')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
