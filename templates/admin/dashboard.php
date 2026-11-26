<?php
use App\Core\Auth;
$admin = Auth::admin();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-success mb-1"><?= e(t('admin_dashboard')) ?></h2>
        <p class="text-muted small mb-0"><?= e(t('admin_welcome', ['name' => $admin['name'] ?? 'Admin', 'email' => $admin['email'] ?? ''])) ?></p>
    </div>
    <form action="/admin/logout" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i> <?= e(t('admin_logout')) ?>
        </button>
    </form>
</div>

<div class="row g-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3">
            <span class="text-muted small"><?= e(t('admin_total_products')) ?></span>
            <h3 class="fw-bold text-success mb-0"><?= e($stats['products'] ?? 0) ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3">
            <span class="text-muted small"><?= e(t('admin_total_herbs')) ?></span>
            <h3 class="fw-bold text-success mb-0"><?= e($stats['herbs'] ?? 0) ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3">
            <span class="text-muted small"><?= e(t('admin_total_orders')) ?></span>
            <h3 class="fw-bold text-success mb-0"><?= e($stats['orders'] ?? 0) ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3">
            <span class="text-muted small"><?= e(t('admin_total_users')) ?></span>
            <h3 class="fw-bold text-success mb-0"><?= e($stats['users'] ?? 0) ?></h3>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0 text-success"><?= e(t('admin_quick_actions')) ?></h5>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="/admin/products" class="btn btn-outline-success btn-sm"><i class="bi bi-box-seam me-1"></i> <?= e(t('admin_products')) ?></a>
            <a href="/admin/herbs" class="btn btn-outline-success btn-sm"><i class="bi bi-journal-medical me-1"></i> <?= e(t('admin_herbs')) ?></a>
            <a href="/admin/orders" class="btn btn-outline-success btn-sm"><i class="bi bi-receipt me-1"></i> <?= e(t('admin_orders')) ?></a>
            <a href="/admin/coupons" class="btn btn-outline-success btn-sm"><i class="bi bi-ticket-perforated me-1"></i> <?= e(t('admin_coupons')) ?></a>
            <a href="/admin/zones" class="btn btn-outline-success btn-sm"><i class="bi bi-truck me-1"></i> <?= e(t('admin_zones')) ?></a>
            <a href="/admin/bank-slips" class="btn btn-outline-success btn-sm"><i class="bi bi-bank me-1"></i> <?= e(t('admin_slips')) ?></a>
        </div>
    </div>
</div>
