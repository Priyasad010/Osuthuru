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
                    <img src="/assets/images/Logo.jpg" alt="Logo" width="35" height="35" class="rounded-circle">
                    <span class="fw-bold fs-6 text-white"><?= e(t('app_name')) ?></span>
                </div>
                <p class="text-white-50 small mb-0"><?= e(t('tagline')) ?></p>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-white mb-2"><?= e(t('quick_links')) ?></h6>
                <ul class="list-unstyled small mb-0">
                    <li><a href="/products"><?= e(t('nav_products')) ?></a></li>
                    <li><a href="/dictionary"><?= e(t('nav_dictionary')) ?></a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-white mb-2"><?= e(t('contact_title')) ?></h6>
                <p class="text-white-50 small mb-1"><i class="bi bi-geo-alt me-1"></i> <?= e(t('contact_city')) ?></p>
                <p class="text-white-50 small mb-0"><i class="bi bi-envelope me-1"></i> support@suwoda.lk</p>
            </div>
        </div>

        <hr class="border-secondary my-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center small text-white-50">
            <span>&copy; <?= date('Y') ?> <?= e(t('app_name')) ?>. <?= e(t('all_rights_reserved')) ?></span>
        </div>
    </div>
</footer>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/search.js?v=2"></script>
</body>
</html>
