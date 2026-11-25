<?php
use App\Core\Auth;
$admin = Auth::admin();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-success mb-1">Admin Dashboard</h2>
        <p class="text-muted small mb-0">Welcome back, <?= e($admin['name'] ?? 'Admin') ?> (<?= e($admin['email'] ?? '') ?>)</p>
    </div>
    <form action="/admin/logout" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i> Logout Admin
        </button>
    </form>
</div>

<div class="row g-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3">
            <span class="text-muted small">Total Products</span>
            <h3 class="fw-bold text-success mb-0"><?= e($stats['products'] ?? 0) ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3">
            <span class="text-muted small">Herbs in Dictionary</span>
            <h3 class="fw-bold text-success mb-0"><?= e($stats['herbs'] ?? 0) ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3">
            <span class="text-muted small">Orders</span>
            <h3 class="fw-bold text-success mb-0"><?= e($stats['orders'] ?? 0) ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3">
            <span class="text-muted small">Active Users</span>
            <h3 class="fw-bold text-success mb-0"><?= e($stats['users'] ?? 0) ?></h3>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0 text-success">Quick Actions</h5>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="/admin/products" class="btn btn-outline-success btn-sm"><i class="bi bi-box-seam me-1"></i> Manage Products</a>
            <a href="/admin/categories" class="btn btn-outline-success btn-sm"><i class="bi bi-tags me-1"></i> Manage Categories</a>
            <a href="/admin/herbs" class="btn btn-outline-success btn-sm"><i class="bi bi-journal-medical me-1"></i> Manage Herbs</a>
            <a href="/admin/orders" class="btn btn-outline-success btn-sm"><i class="bi bi-receipt me-1"></i> Manage Orders</a>
        </div>
    </div>
</div>
