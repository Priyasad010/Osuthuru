<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Admin Dashboard</a>
        <h2 class="fw-bold text-success mt-1 mb-0">Verify Bank Transfer Slips</h2>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Slip #</th>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Slip Image</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($slips)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No bank transfer slips submitted yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($slips as $s): ?>
                        <tr>
                            <td>#<?= (int)$s['slip_id'] ?></td>
                            <td class="fw-bold">#<?= (int)$s['order_id'] ?></td>
                            <td><?= e($s['user_name'] ?? 'Customer') ?></td>
                            <td class="fw-semibold"><?= e(format_price((float)$s['total_price'])) ?></td>
                            <td><a href="<?= e($s['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Slip</a></td>
                            <td>
                                <span class="badge <?= $s['status'] === 'verified' ? 'bg-success' : ($s['status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                    <?= e(strtoupper($s['status'])) ?>
                                </span>
                            </td>
                            <td class="d-flex gap-1">
                                <?php if ($s['status'] === 'submitted'): ?>
                                    <form method="POST" action="/admin/bank-slips/review">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="slip_id" value="<?= (int)$s['slip_id'] ?>">
                                        <input type="hidden" name="action" value="verify">
                                        <button type="submit" class="btn btn-sm btn-success">Verify &amp; Mark Paid</button>
                                    </form>
                                    <form method="POST" action="/admin/bank-slips/review">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="slip_id" value="<?= (int)$s['slip_id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
