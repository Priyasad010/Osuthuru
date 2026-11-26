<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
        <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_slip_title')) ?></h2>
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
                    <th><?= e(t('admin_slip_number')) ?></th>
                    <th><?= e(t('col_order')) ?></th>
                    <th><?= e(t('admin_customer')) ?></th>
                    <th><?= e(t('admin_amount')) ?></th>
                    <th><?= e(t('admin_slip_image')) ?></th>
                    <th><?= e(t('col_status')) ?></th>
                    <th><?= e(t('admin_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($slips)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><?= e(t('admin_no_slips')) ?></td></tr>
                <?php else: ?>
                    <?php foreach ($slips as $s): ?>
                        <tr>
                            <td>#<?= (int)$s['slip_id'] ?></td>
                            <td class="fw-bold">#<?= (int)$s['order_id'] ?></td>
                            <td><?= e($s['user_name'] ?? t('admin_customer')) ?></td>
                            <td class="fw-semibold"><?= e(format_price((float)$s['total_price'])) ?></td>
                            <td><a href="<?= e($s['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><?= e(t('admin_view_slip')) ?></a></td>
                            <td>
                                <span class="badge <?= $s['status'] === 'verified' ? 'bg-success' : ($s['status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                    <?= e(t_or('slip_', $s['status'])) ?>
                                </span>
                            </td>
                            <td class="d-flex gap-1">
                                <?php if ($s['status'] === 'submitted'): ?>
                                    <form method="POST" action="/admin/bank-slips/review">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="slip_id" value="<?= (int)$s['slip_id'] ?>">
                                        <input type="hidden" name="action" value="verify">
                                        <button type="submit" class="btn btn-sm btn-success"><?= e(t('admin_verify_paid')) ?></button>
                                    </form>
                                    <form method="POST" action="/admin/bank-slips/review">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="slip_id" value="<?= (int)$s['slip_id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(t('admin_reject')) ?></button>
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
