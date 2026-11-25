<div class="row justify-content-center py-5">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0 border-top border-success border-4">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <span class="badge bg-dark px-3 py-2 rounded-pill mb-2">Staff Portal</span>
                    <h3 class="fw-bold text-success">Admin Login</h3>
                    <p class="text-muted small">Sign in to manage Suwoda HerbHub catalog & orders</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/admin/login">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">Admin Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email ?? '') ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label small fw-semibold">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-2 fw-semibold">Sign In as Admin</button>
                </form>

                <div class="text-center mt-4 pt-2 border-top small text-muted">
                    <a href="/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to Store</a>
                </div>
            </div>
        </div>
    </div>
</div>
