<div class="row g-4 justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm p-4">
            <h1 class="h2 fw-bold mb-2"><?= e(t('page_contact')) ?></h1>
            <p class="text-muted"><?= e(t('contact_intro')) ?></p>
            <?php if ($sent): ?><div class="alert alert-success" role="status"><?= e(t('contact_sent')) ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="POST" action="/contact">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="cname" class="form-label small fw-semibold"><?= e(t('full_name')) ?></label>
                        <input type="text" class="form-control" id="cname" name="name" maxlength="100" value="<?= e($old['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="cemail" class="form-label small fw-semibold"><?= e(t('email_address')) ?></label>
                        <input type="email" class="form-control" id="cemail" name="email" maxlength="100" value="<?= e($old['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="csubject" class="form-label small fw-semibold"><?= e(t('contact_subject')) ?></label>
                        <input type="text" class="form-control" id="csubject" name="subject" maxlength="150" value="<?= e($old['subject'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="cmessage" class="form-label small fw-semibold"><?= e(t('contact_message')) ?></label>
                        <textarea class="form-control" id="cmessage" name="message" rows="5" maxlength="2000" required><?= e($old['message'] ?? '') ?></textarea>
                        <div class="form-text"><?= e(t('contact_privacy')) ?></div>
                    </div>
                    <div class="col-12"><button type="submit" class="btn btn-brand px-4"><?= e(t('contact_send')) ?></button></div>
                </div>
            </form>
        </div>
    </div>
    <?php if ($settings['support_email'] !== '' || $settings['support_phone'] !== '' || $settings['business_address'] !== ''): ?>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4">
                <h2 class="h5 mb-3"><?= e(t('contact_title')) ?></h2>
                <?php if ($settings['support_email'] !== ''): ?>
                    <p class="mb-2"><i class="bi bi-envelope me-2" aria-hidden="true"></i><a href="mailto:<?= e($settings['support_email']) ?>"><?= e($settings['support_email']) ?></a></p>
                <?php endif; ?>
                <?php if ($settings['support_phone'] !== ''): ?>
                    <p class="mb-2"><i class="bi bi-telephone me-2" aria-hidden="true"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $settings['support_phone'])) ?>"><?= e($settings['support_phone']) ?></a></p>
                <?php endif; ?>
                <?php if ($settings['business_address'] !== ''): ?>
                    <p class="mb-0"><i class="bi bi-geo-alt me-2" aria-hidden="true"></i><?= e($settings['business_address']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
