<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Admin Dashboard</a>
        <h2 class="fw-bold text-success mt-1 mb-0">Delivery Zones (Districts)</h2>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm p-4 mb-4">
    <h6 class="fw-bold text-success mb-3">Add or Update Delivery Zone</h6>
    <form method="POST" action="/admin/zones/save" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">District</label>
            <input type="text" name="district" class="form-control form-control-sm" required placeholder="e.g. Colombo">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Delivery Fee (Rs.)</label>
            <input type="number" step="0.01" name="fee" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Free Over (Rs.)</label>
            <input type="number" step="0.01" name="free_over" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-suwoda btn-sm w-100">Save Zone</button>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>District</th>
                    <th>Delivery Fee</th>
                    <th>Free Delivery Threshold</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($zones as $z): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($z['district']) ?></td>
                        <td><?= e(format_price((float)$z['fee'])) ?></td>
                        <td><?= e(format_price((float)$z['free_over'])) ?></td>
                        <td>
                            <form method="POST" action="/admin/zones/delete" onsubmit="return confirm('Delete zone?');">
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
