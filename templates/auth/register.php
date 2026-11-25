<div class="row justify-content-center py-4">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="/assets/images/Logo.jpg" alt="Suwoda Logo" width="56" height="56" class="rounded-circle mb-2">
                    <h3 class="fw-bold text-success"><?= e(t('nav_register')) ?></h3>
                    <p class="text-muted small">Join Suwoda HerbHub Ayurvedic Community</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-2 small"><?= e($success) ?></div>
                <?php endif; ?>

                <form method="POST" action="/register">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label small fw-semibold">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" placeholder="07XXXXXXXX">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label small fw-semibold">Password (min 8 characters)</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label small fw-semibold">Confirm Password</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                    </div>

                    <button type="submit" class="btn btn-suwoda w-100 py-2 fw-semibold">Create Account</button>
                </form>

                <div class="text-center mt-4 pt-2 border-top small text-muted">
                    Already have an account? <a href="/login" class="text-success fw-semibold text-decoration-none">Login</a>
                </div>
            </div>
        </div>
    </div>
</div>
