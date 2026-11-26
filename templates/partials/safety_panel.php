<?php

use App\Repository\SafetyRepo;
use App\Service\SafetyService;

/**
 * "Check safety" panel.
 * Expects $productIds; optional $productId (product page) or $back = 'checkout'.
 */
$productId = $productId ?? 0;
$back = $back ?? 'cart';
$flags = SafetyRepo::flags();
$selected = SafetyService::answers() ?? [];
$result = SafetyService::currentResult($productIds);
$severityClass = ['avoid' => 'alert-danger', 'caution' => 'alert-warning', 'info' => 'alert-info'];
?>
<section id="safety" class="card border-0 shadow-sm p-4 mb-4" aria-labelledby="safetyTitle">
    <h5 id="safetyTitle" class="fw-bold text-success mb-1"><i class="bi bi-shield-check me-2"></i><?= e(t('safety_title')) ?></h5>
    <p class="text-muted small mb-3"><?= e(t('safety_intro')) ?></p>

    <form method="POST" action="/safety" id="safetyForm"
        <?= $back === 'checkout' ? '' : 'data-ajax="1"' ?>
        data-avoid-label="<?= e(t('severity_avoid')) ?>"
        data-unknown="<?= e(t('safety_unknown')) ?>"
        data-none="<?= e(t('safety_no_match')) ?>"
        data-nothing="<?= e(t('safety_nothing_selected')) ?>"
        data-error="<?= e(t('safety_err_request')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <div class="row g-1 mb-3">
            <?php foreach ($flags as $f): ?>
                <div class="col-sm-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="flags[]" value="<?= e($f['code']) ?>" id="flag_<?= (int)$f['flag_id'] ?>"
                            <?= in_array($f['code'], $selected, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="flag_<?= (int)$f['flag_id'] ?>"><?= e($f['display_name']) ?></label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="safetyRemember">
            <label class="form-check-label small text-muted" for="safetyRemember"><?= e(t('safety_remember')) ?></label>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-suwoda btn-sm"><?= e(t('safety_check')) ?></button>
            <button type="submit" class="btn btn-outline-secondary btn-sm" formaction="/safety/clear" id="safetyClear"><?= e(t('safety_clear')) ?></button>
        </div>
    </form>

    <div id="safetyResults" class="mt-3" aria-live="polite">
        <?php if ($result !== null): ?>
            <?php if ($result['selected'] === 0): ?>
                <p class="small text-muted mb-2"><?= e(t('safety_nothing_selected')) ?></p>
            <?php elseif (empty($result['warnings'])): ?>
                <p class="small mb-2"><?= e(t('safety_no_match')) ?></p>
            <?php endif; ?>
            <?php foreach ($result['warnings'] as $w): ?>
                <div class="alert <?= $severityClass[$w['severity']] ?> py-2 small mb-2">
                    <strong><?= e($w['severity_label']) ?></strong> · <?= e($w['product_name']) ?> (<?= e($w['herb_name']) ?>) · <?= e($w['flag_name']) ?>
                    <div><?= e($w['note']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!empty($result['unknown'])): ?>
                <div class="alert alert-secondary py-2 small mb-0">
                    <?= e(t('safety_unknown')) ?> <?= e(implode(', ', array_column($result['unknown'], 'product_name'))) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <p class="small text-muted border-top pt-2 mt-3 mb-0"><?= e(t('safety_disclaimer')) ?></p>
</section>
<script src="/assets/js/safety.js" defer></script>
