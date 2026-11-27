<?php use App\Repository\SettingRepo; ?>
<?php if (SettingRepo::bankConfigured()): ?>
    <span class="d-block"><strong><?= e(t('bank_details_label')) ?></strong></span>
    <?php foreach (['bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number'] as $detail): ?>
        <?php if (SettingRepo::get($detail) !== ''): ?>
            <span class="d-block"><?= e(t('setting_' . $detail)) ?>: <strong><?= e(SettingRepo::get($detail)) ?></strong></span>
        <?php endif; ?>
    <?php endforeach; ?>
<?php else: ?>
    <span class="d-block"><?= e(t('bank_not_set')) ?> <a href="/contact"><?= e(t('page_contact')) ?></a></span>
<?php endif; ?>
