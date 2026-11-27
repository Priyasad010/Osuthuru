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

<form method="POST" action="/admin/password/sign-out-others" class="card border-0 shadow-sm p-4 mt-4" style="max-width: 460px;">
    <?= csrf_field() ?>
    <h2 class="h5 fw-bold"><?= e(t('sign_out_others_title')) ?></h2>
    <p class="small text-muted"><?= e(t('sign_out_others_text')) ?></p>
    <div><button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i><?= e(t('sign_out_others_button')) ?></button></div>
</form>
