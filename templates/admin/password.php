<?php use App\Core\View; ?>
<?= View::partial('admin_heading', ['title' => t('admin_password'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>
<form method="POST" action="/admin/password/change" class="card border-0 shadow-sm p-4" style="max-width: 460px;">
    <?= csrf_field() ?>
    <?php foreach (['current_password' => 'profile_current', 'password' => 'new_password', 'password_confirmation' => 'confirm_new_password'] as $field => $label): ?>
        <label class="form-label small fw-semibold" for="<?= $field ?>"><?= e(t($label)) ?></label>
        <input type="password" class="form-control mb-3" id="<?= $field ?>" name="<?= $field ?>" autocomplete="<?= $field === 'current_password' ? 'current-password' : 'new-password' ?>" required>
    <?php endforeach; ?>
    <p class="small text-muted"><?= e(t('admin_password_note')) ?></p>
    <div><button type="submit" class="btn btn-brand btn-sm"><?= e(t('save')) ?></button></div>
</form>
