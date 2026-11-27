<article class="mx-auto" style="max-width: 760px;">
    <div class="text-center mb-4">
        <img src="/assets/images/logo.png" alt="" width="96" height="96">
        <h1 class="h2 fw-bold mt-3"><?= e(t('page_about')) ?></h1>
        <p class="lead"><?= e(t('about_lead')) ?></p>
    </div>
    <?php for ($i = 1; $i <= 4; $i++): ?>
        <h2 class="h5 fw-bold mt-4"><?= e(t("about_h{$i}")) ?></h2>
        <p><?= e(t("about_p{$i}")) ?></p>
    <?php endfor; ?>
    <div class="disclaimer-box mt-4"><i class="bi bi-shield-exclamation text-warning me-2" aria-hidden="true"></i><?= e(t('medical_disclaimer')) ?></div>
    <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
        <a href="/products" class="btn btn-brand px-4"><?= e(t('hero_shop')) ?></a>
        <a href="/contact" class="btn btn-outline-success px-4"><?= e(t('page_contact')) ?></a>
    </div>
</article>
