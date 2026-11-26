<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/dashboard" class="text-success small text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= e(t('admin_dashboard')) ?></a>
        <h2 class="fw-bold text-success mt-1 mb-0"><?= e(t('admin_products_title')) ?></h2>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th><?= e(t('admin_product_details')) ?></th>
                    <th><?= e(t('admin_linked_herbs')) ?></th>
                    <th><?= e(t('col_price')) ?></th>
                    <th><?= e(t('admin_stock')) ?></th>
                    <th><?= e(t('admin_stock_action')) ?></th>
                    <th><?= e(t('admin_edit')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <?php 
                    $linkedHerbIds = array_column($p['herbs'] ?? [], 'herb_id');
                    ?>
                    <tr>
                        <td>#<?= (int)$p['product_id'] ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($p['product_name']) ?></div>
                            <?php if (!empty($p['product_name_si'])): ?>
                                <span class="badge bg-light text-success border border-success-subtle"><?= e($p['product_name_si']) ?></span>
                            <?php endif; ?>
                            <span class="text-muted d-block small"><?= e($p['category_name'] ?? '') ?></span>
                        </td>
                        <td>
                            <?php if (!empty($p['herbs'])): ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($p['herbs'] as $h): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <?= e($h['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small"><?= e(t('admin_none_linked')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['has_discount']): ?>
                                <div class="fw-bold text-success"><?= e(format_price($p['sale_price'])) ?></div>
                                <small class="text-muted text-decoration-line-through"><?= e(format_price($p['base_price'])) ?></small>
                                <span class="badge bg-danger ms-1">-<?= number_format($p['discount_percentage'], 0) ?>%</span>
                            <?php else: ?>
                                <div class="fw-bold text-dark"><?= e(format_price($p['base_price'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= (int)$p['quantity'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                                <?= (int)$p['quantity'] ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" action="/admin/products/stock" class="d-flex gap-1" style="max-width: 150px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">
                                <input type="number" name="quantity" value="<?= (int)$p['quantity'] ?>" min="0" class="form-control form-control-sm">
                                <button type="submit" class="btn btn-sm btn-suwoda" title="<?= e(t('admin_update_stock')) ?>"><?= e(t('save')) ?></button>
                            </form>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$p['product_id'] ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>

                            <!-- Edit Product Details Modal -->
                            <div class="modal fade" id="editModal<?= (int)$p['product_id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="/admin/products/update">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">

                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold text-success"><?= e(t('admin_edit_product', ['id' => (int)$p['product_id']])) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold"><?= e(t('admin_name_en')) ?></label>
                                                    <input type="text" class="form-control bg-light" value="<?= e($p['product_name']) ?>" disabled>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold"><?= e(t('admin_name_si')) ?></label>
                                                    <input type="text" name="product_name_si" class="form-control" value="<?= e($p['product_name_si'] ?? '') ?>" placeholder="උදා: සුවෝදා කොහොඹ සබන්">
                                                </div>
                                                <div class="alert alert-warning py-2 small"><?= e(t('admin_review_warning')) ?></div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold"><?= e(t('admin_desc_en')) ?></label>
                                                    <textarea name="description" class="form-control" rows="3"><?= e($p['description'] ?? '') ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold"><?= e(t('admin_desc_si')) ?></label>
                                                    <textarea name="description_si" class="form-control" rows="3" placeholder="සිංහල විස්තරය..."><?= e($p['description_si'] ?? '') ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold d-block"><?= e(t('admin_linked_herbs')) ?></label>
                                                    <div class="p-2 border rounded bg-light" style="max-height: 180px; overflow-y: auto;">
                                                        <?php foreach ($allHerbs as $ah): ?>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="herb_ids[]" value="<?= (int)$ah['herb_id'] ?>" id="herb_<?= $p['product_id'] ?>_<?= $ah['herb_id'] ?>"
                                                                    <?= in_array((int)$ah['herb_id'], $linkedHerbIds, true) ? 'checked' : '' ?>>
                                                                <label class="form-check-label small" for="herb_<?= $p['product_id'] ?>_<?= $ah['herb_id'] ?>">
                                                                    <strong><?= e($ah['name']) ?></strong> (<?= e($ah['name_si'] ?? '') ?>) - <em><?= e($ah['scientific_name']) ?></em>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"><?= e(t('cancel')) ?></button>
                                                <button type="submit" class="btn btn-sm btn-suwoda"><?= e(t('admin_save_changes')) ?></button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
