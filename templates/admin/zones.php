<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
        <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_zones')) ?></h2>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm p-4 mb-4">
    <h6 class="fw-bold text-success mb-3"><?= e(t('admin_zone_form')) ?></h6>
    <form method="POST" action="/admin/zones/save" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <label class="form-label small fw-semibold"><?= e(t('district')) ?></label>
            <input type="text" name="district" class="form-control form-control-sm" required placeholder="<?= e(t('admin_zone_example')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold"><?= e(t('admin_delivery_fee')) ?></label>
            <input type="number" step="0.01" name="fee" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold"><?= e(t('admin_free_over')) ?></label>
            <input type="number" step="0.01" name="free_over" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-suwoda btn-sm w-100"><?= e(t('admin_save_zone')) ?></button>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th><?= e(t('district')) ?></th>
                    <th><?= e(t('admin_delivery_fee')) ?></th>
                    <th><?= e(t('admin_free_over')) ?></th>
                    <th><?= e(t('admin_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($zones as $z): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($z['district']) ?></td>
                        <td><?= e(format_price((float)$z['fee'])) ?></td>
                        <td><?= e(format_price((float)$z['free_over'])) ?></td>
                        <td>
                            <form method="POST" action="/admin/zones/delete" onsubmit="return confirm(<?= e(json_encode(t('admin_delete_zone_confirm'))) ?>);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="zone_id" value="<?= (int)$z['zone_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
