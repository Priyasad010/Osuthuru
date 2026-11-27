<?php use App\Core\View; ?>
<?= View::partial('admin_heading', ['title' => t('admin_settings'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>
<form method="POST" action="/admin/settings/save" class="card border-0 shadow-sm p-4" style="max-width: 720px;">
    <?= csrf_field() ?>
    <?php foreach (['settings_bank' => ['bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number'], 'settings_contact' => ['support_email', 'support_phone', 'business_address']] as $group => $fields): ?>
        <h2 class="h5"><?= e(t($group)) ?></h2>
        <p class="small text-muted"><?= e(t($group . '_note')) ?></p>
        <div class="row g-3 mb-4">
            <?php foreach ($fields as $field): ?>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="<?= $field ?>"><?= e(t('setting_' . $field)) ?></label>
                    <input type="<?= $field === 'support_email' ? 'email' : 'text' ?>" class="form-control form-control-sm" id="<?= $field ?>" name="<?= $field ?>"
                           maxlength="<?= \App\Repository\SettingRepo::FIELDS[$field] ?>" value="<?= e($settings[$field]) ?>">
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="policies_reviewed" name="policies_reviewed" value="1" <?= $settings['policies_reviewed'] === '1' ? 'checked' : '' ?>>
        <label class="form-check-label small" for="policies_reviewed"><?= e(t('setting_policies_reviewed_text')) ?></label>
    </div>
    <div><button type="submit" class="btn btn-brand btn-sm"><?= e(t('save')) ?></button></div>
</form>
