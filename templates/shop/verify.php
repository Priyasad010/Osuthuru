<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <h2 class="fw-bold text-success mb-3"><i class="bi bi-qr-code-scan me-2"></i><?= e(t('verify_title')) ?></h2>

        <?php if (!$batch): ?>
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading"><i class="bi bi-x-octagon me-2"></i><?= e(t('verify_unknown')) ?></h4>
                <p class="mb-0"><?= e(t('verify_unknown_help')) ?> <a href="mailto:support@suwoda.lk">support@suwoda.lk</a></p>
            </div>
        <?php else: ?>
            <?php
            $style = ['genuine' => ['alert-success', 'bi-patch-check-fill'], 'expired' => ['alert-warning', 'bi-calendar-x'], 'recalled' => ['alert-danger', 'bi-exclamation-octagon-fill']][$status];
            ?>
            <div class="alert <?= $style[0] ?> text-center" role="status">
                <i class="bi <?= $style[1] ?> fs-1 d-block"></i>
                <h3 class="fw-bold mb-1"><?= e(t('verify_' . $status)) ?></h3>
                <p class="mb-0 small"><?= e(t('verify_' . $status . '_help')) ?></p>
            </div>

            <?php if ($duplicated): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-copy me-2"></i><strong><?= e(t('verify_duplicate')) ?></strong>
                    <a href="mailto:support@suwoda.lk">support@suwoda.lk</a>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <ul class="list-group list-group-flush">
                    <?php
                    $rows = [
                        'col_product' => $batch['display_name'],
                        'batch_code' => $batch['batch_code'],
                        'batch_made' => $batch['manufactured_on'],
                        'batch_expires' => $batch['expires_on'],
                        'batch_source' => $batch['source_location'] ?: '-',
                        'batch_supplier' => $batch['supplier_name'] ?: '-',
                        'verify_scans' => (string)(int)$batch['scan_count'],
                    ];
                    ?>
                    <?php foreach ($rows as $label => $value): ?>
                        <li class="list-group-item d-flex justify-content-between gap-3">
                            <span class="text-muted"><?= e(t($label)) ?></span>
                            <strong class="text-end text-break"><?= e($value) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="card-body d-grid gap-2">
                    <?php if (!empty($batch['lab_report_path'])): ?>
                        <a href="<?= e($batch['lab_report_path']) ?>" class="btn btn-outline-success" target="_blank" rel="noopener">
                            <i class="bi bi-file-earmark-pdf me-1"></i><?= e(t('batch_lab_report')) ?>
                        </a>
                    <?php endif; ?>
                    <a href="/products/<?= (int)$batch['product_id'] ?>" class="btn btn-suwoda"><?= e(t('view_product')) ?></a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
