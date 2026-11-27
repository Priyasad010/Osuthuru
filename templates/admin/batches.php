<?php

use App\Service\BatchService;

// A blank form for a new batch, followed by the existing ones
$forms = array_merge([null], $batches);
?>
<div class="mb-4">
    <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
    <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_batches')) ?></h2>
</div>

<?php if (!empty($flash)): ?><div class="alert alert-success py-2 small"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
<?php if ($localOnly): ?>
    <div class="alert alert-warning py-2 small"><i class="bi bi-phone me-1"></i><?= e(t('admin_qr_local', ['url' => $appUrl])) ?></div>
<?php else: ?>
    <div class="alert alert-info py-2 small"><i class="bi bi-qr-code me-1"></i><?= e(t('admin_qr_url', ['url' => $appUrl])) ?></div>
<?php endif; ?>

<?php if (!empty($expiring)): ?>
    <div class="alert alert-warning small">
        <strong><?= e(t('admin_expiring')) ?></strong>
        <ul class="mb-0">
            <?php foreach ($expiring as $b): ?>
                <li><?= e($b['batch_code']) ?> · <?= e($b['product_name']) ?> · <?= e($b['expires_on']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php foreach ($forms as $b): ?>
    <?php $id = (int)($b['batch_id'] ?? 0); ?>
    <div class="card border-0 shadow-sm p-3 mb-3 <?= $b ? '' : 'border-start border-4 border-success' ?>">
        <h5 class="fw-bold d-flex flex-wrap gap-2 align-items-center">
            <?php if ($b): ?>
                <?= e($b['batch_code']) ?>
                <?php $state = BatchService::status($b); ?>
                <span class="badge <?= ['genuine' => 'bg-success', 'expired' => 'bg-warning text-dark', 'recalled' => 'bg-danger'][$state] ?>"><?= e(t('verify_' . $state)) ?></span>
                <span class="badge bg-light text-dark border"><?= e(t('admin_scan_summary', ['scans' => (int)$b['scan_count'], 'sources' => (int)$b['scan_sources']])) ?></span>
                <span class="badge bg-light text-dark border"><?= e(t('admin_allocated', ['used' => (int)$b['units_allocated'], 'units' => (int)$b['units']])) ?></span>
                <?php if (BatchService::looksDuplicated($b)): ?><span class="badge bg-danger"><?= e(t('verify_duplicate')) ?></span><?php endif; ?>
            <?php else: ?>
                <?= e(t('admin_new_batch')) ?>
            <?php endif; ?>
        </h5>

        <form method="POST" action="/admin/batches/save" enctype="multipart/form-data" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="col-md-4">
                <label class="form-label small" for="p<?= $id ?>"><?= e(t('col_product')) ?></label>
                <select class="form-select form-select-sm" id="p<?= $id ?>" name="product_id" required>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['product_id'] ?>" <?= (int)($b['product_id'] ?? 0) === (int)$p['product_id'] ? 'selected' : '' ?>><?= e($p['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="c<?= $id ?>"><?= e(t('batch_code')) ?></label>
                <input type="text" class="form-control form-control-sm" id="c<?= $id ?>" name="batch_code" maxlength="60" value="<?= e($b['batch_code'] ?? '') ?>" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small" for="m<?= $id ?>"><?= e(t('batch_made')) ?></label>
                <input type="date" class="form-control form-control-sm" id="m<?= $id ?>" name="manufactured_on" value="<?= e($b['manufactured_on'] ?? '') ?>" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small" for="x<?= $id ?>"><?= e(t('batch_expires')) ?></label>
                <input type="date" class="form-control form-control-sm" id="x<?= $id ?>" name="expires_on" value="<?= e($b['expires_on'] ?? '') ?>" required>
            </div>
            <div class="col-md-1">
                <label class="form-label small" for="u<?= $id ?>"><?= e(t('batch_units')) ?></label>
                <input type="number" class="form-control form-control-sm" id="u<?= $id ?>" name="units" min="0" value="<?= (int)($b['units'] ?? 0) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small" for="s<?= $id ?>"><?= e(t('batch_source')) ?></label>
                <input type="text" class="form-control form-control-sm" id="s<?= $id ?>" name="source_location" maxlength="150" value="<?= e($b['source_location'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small" for="sp<?= $id ?>"><?= e(t('batch_supplier')) ?></label>
                <input type="text" class="form-control form-control-sm" id="sp<?= $id ?>" name="supplier_name" maxlength="150" value="<?= e($b['supplier_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small" for="r<?= $id ?>"><?= e(t('admin_lab_upload')) ?></label>
                <input type="file" class="form-control form-control-sm" id="r<?= $id ?>" name="lab_report" accept="application/pdf,.pdf">
                <?php if (!empty($b['lab_report_path'])): ?>
                    <a class="small" href="<?= e($b['lab_report_path']) ?>" target="_blank" rel="noopener"><?= e(t('batch_lab_report')) ?></a>
                <?php endif; ?>
            </div>
            <div class="col-md-10">
                <label class="form-label small" for="n<?= $id ?>"><?= e(t('admin_batch_notes')) ?></label>
                <textarea class="form-control form-control-sm" id="n<?= $id ?>" name="notes" rows="1"><?= e($b['notes'] ?? '') ?></textarea>
            </div>
            <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-suwoda btn-sm w-100"><?= e(t('save')) ?></button></div>
        </form>

        <?php if ($b): ?>
            <div class="d-flex flex-wrap gap-2 align-items-center border-top pt-2 mt-3">
                <form method="GET" action="/admin/batches/labels" target="_blank" class="d-flex gap-1 align-items-center">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="number" name="count" value="24" min="1" max="<?= BatchService::MAX_LABELS ?>" class="form-control form-control-sm" style="width: 80px;" aria-label="<?= e(t('admin_label_count')) ?>">
                    <button type="submit" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer me-1"></i><?= e(t('admin_labels')) ?></button>
                </form>
                <a href="<?= e(BatchService::verifyUrl($b)) ?>" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener"><?= e(t('admin_open_verify')) ?></a>
                <form method="POST" action="/admin/batches/recall" class="ms-auto" onsubmit="return confirm(<?= e(json_encode(t($b['status'] === 'recalled' ? 'admin_unrecall_confirm' : 'admin_recall_confirm'))) ?>);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="recalled" value="<?= $b['status'] === 'recalled' ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-sm <?= $b['status'] === 'recalled' ? 'btn-outline-secondary' : 'btn-danger' ?>">
                        <?= e(t($b['status'] === 'recalled' ? 'admin_unrecall' : 'admin_recall')) ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
