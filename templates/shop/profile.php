<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <h2 class="fw-bold text-success mb-3"><i class="bi bi-person-circle me-2"></i><?= e(t('profile_title')) ?></h2>
        <?php if (!empty($flash)): ?><div class="alert alert-success py-2 small" role="status"><?= e($flash) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger py-2 small" role="alert"><?= e($error) ?></div><?php endif; ?>

        <form method="POST" action="/profile" class="card border-0 shadow-sm p-4 mb-4">
            <?= csrf_field() ?>
            <p class="small text-muted"><?= e($profile['email'] ?? '') ?></p>
            <label class="form-label small fw-semibold" for="name"><?= e(t('full_name')) ?></label>
            <input type="text" class="form-control mb-3" id="name" name="name" value="<?= e($profile['name'] ?? '') ?>" minlength="2" maxlength="100" required>
            <label class="form-label small fw-semibold" for="phone"><?= e(t('phone_number')) ?></label>
            <input type="tel" class="form-control mb-3" id="phone" name="phone" value="<?= e($profile['phone'] ?? '') ?>" maxlength="20">
            <label class="form-label small fw-semibold" for="address"><?= e(t('delivery_address')) ?></label>
            <textarea class="form-control mb-3" id="address" name="address" rows="2" maxlength="1000"><?= e($profile['address'] ?? '') ?></textarea>
            <button type="submit" class="btn btn-brand"><?= e(t('save')) ?></button>
        </form>

        <form method="POST" action="/profile/password" class="card border-0 shadow-sm p-4">
            <?= csrf_field() ?>
            <h5 class="fw-bold text-success"><?= e(t('profile_password_title')) ?></h5>
            <?php foreach (['current_password' => 'profile_current', 'password' => 'new_password', 'password_confirmation' => 'confirm_new_password'] as $field => $label): ?>
                <label class="form-label small fw-semibold" for="<?= $field ?>"><?= e(t($label)) ?></label>
                <input type="password" class="form-control mb-3" id="<?= $field ?>" name="<?= $field ?>" autocomplete="<?= $field === 'current_password' ? 'current-password' : 'new-password' ?>" required>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-brand"><?= e(t('update_password')) ?></button>
        </form>

        <div class="text-center mt-3">
            <a href="/profile/categories" class="btn btn-outline-success btn-sm"><i class="bi bi-stars me-1"></i><?= e(t('interests_title')) ?></a>
        </div>
    </div>
</div>
