</main>

<footer class="py-4 mt-auto">
    <div class="container">
        <!-- Medical Disclaimer -->
        <div class="disclaimer-box mb-4">
            <i class="bi bi-shield-exclamation text-warning me-2 fs-6"></i>
            <?= e(t('medical_disclaimer')) ?>
        </div>

        <div class="row g-4 text-start">
            <div class="col-12 col-md-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <img src="/assets/images/logo.png" alt="Logo" width="35" height="35" class="rounded-circle">
                    <span class="fw-bold fs-6 text-white"><?= e(t('app_name')) ?></span>
                </div>
                <p class="text-white-50 small mb-0"><?= e(t('tagline')) ?></p>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-white mb-2"><?= e(t('quick_links')) ?></h6>
                <ul class="list-unstyled small mb-0">
                    <li><a href="/products"><?= e(t('nav_products')) ?></a></li>
                    <li><a href="/dictionary"><?= e(t('nav_dictionary')) ?></a></li>
                    <li><a href="/blogs"><?= e(t('nav_blogs')) ?></a></li>
                    <li><a href="/credits"><?= e(t('credits_title')) ?></a></li>
                    <?php foreach (['about', 'contact', 'privacy', 'terms', 'returns'] as $footerPage): ?>
                        <li><a href="/<?= $footerPage ?>"><?= e(t('page_' . $footerPage)) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" id="installApp" class="btn btn-outline-light btn-sm mt-2 d-none"><i class="bi bi-phone me-1"></i><?= e(t('install_app')) ?></button>
                <p id="installHint" class="small text-white mt-2 mb-0 d-none" role="status"><?= e(t('install_ios_hint')) ?></p>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-white mb-2"><?= e(t('contact_title')) ?></h6>
                <p class="text-white-50 small mb-1"><i class="bi bi-geo-alt me-1"></i> <?= e(t('contact_city')) ?></p>
                <?php foreach (['support_email' => 'bi-envelope', 'support_phone' => 'bi-telephone'] as $detail => $icon): ?>
                    <?php if (\App\Repository\SettingRepo::get($detail) !== ''): ?>
                        <p class="text-white-50 small mb-1"><i class="bi <?= $icon ?> me-1"></i> <?= e(\App\Repository\SettingRepo::get($detail)) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
                <p class="small mb-0"><a href="/contact"><i class="bi bi-chat-left-text me-1"></i> <?= e(t('contact_send_us')) ?></a></p>
            </div>
        </div>

        <hr class="border-secondary my-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center small text-white-50">
            <span>&copy; <?= date('Y') ?> <?= e(t('app_name')) ?>. <?= e(t('all_rights_reserved')) ?></span>
        </div>
    </div>
</footer>

<?= \App\Core\View::partial('assistant_widget') ?>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$formMessages = [];
foreach (['required', 'choose', 'tick', 'email', 'short', 'long', 'min', 'max', 'whole', 'number', 'date', 'pattern', 'name', 'phone', 'password', 'match', 'future', 'notfuture', 'after', 'notbefore', 'monthday', 'files', 'filetype', 'filesize'] as $formKey) {
    $formMessages[$formKey] = t('form_' . $formKey);
}
?>
<script type="application/json" id="formMessages"><?= json_encode($formMessages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="/assets/js/forms.js?v=2"></script>
<script src="/assets/js/search.js?v=3"></script>
<script src="/assets/js/pwa.js?v=2" defer></script>
</body>
</html>
