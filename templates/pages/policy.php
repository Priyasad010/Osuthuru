<?php use App\Repository\SettingRepo; ?>
<article class="mx-auto" style="max-width: 760px;">
    <h1 class="h2 fw-bold mb-3"><?= e(t("page_{$name}")) ?></h1>
    <?php if ($draft): ?>
        <div class="alert alert-warning small" role="note"><?= e(t('policy_draft')) ?></div>
    <?php endif; ?>
    <p class="text-muted"><?= e(t("page_{$name}_intro")) ?></p>
    <?php for ($i = 1; $i <= $sections; $i++): ?>
        <h2 class="h5 fw-bold mt-4"><?= e(t("page_{$name}_h{$i}")) ?></h2>
        <p><?= e(t("page_{$name}_p{$i}")) ?></p>
    <?php endfor; ?>
    <hr>
    <p class="small"><?= e(t('policy_questions')) ?> <a href="/contact"><?= e(t('page_contact')) ?></a>
        <?php if (SettingRepo::get('support_email') !== ''): ?> (<?= e(SettingRepo::get('support_email')) ?>)<?php endif; ?></p>
</article>
