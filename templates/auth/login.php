<div class="row justify-content-center py-5">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="/assets/images/Logo.jpg" alt="Suwoda Logo" width="56" height="56" class="rounded-circle mb-2">
                    <h3 class="fw-bold text-success"><?= e(t('nav_login')) ?></h3>
                    <p class="text-muted small">Access your herbal wellness account</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-2 small"><?= e($success) ?></div>
                <?php endif; ?>

                <form method="POST" action="/login">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email ?? '') ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label for="password" class="form-label small fw-semibold">Password</label>
                            <a href="/forgot-password" class="small text-success text-decoration-none">Forgot?</a>
                        </div>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-suwoda w-100 py-2 fw-semibold">Login</button>
                </form>

                <div class="text-center mt-4 pt-2 border-top small text-muted">
                    Don't have an account? <a href="/register" class="text-success fw-semibold text-decoration-none">Register here</a>
                </div>
            </div>
        </div>
    </div>
</div>
