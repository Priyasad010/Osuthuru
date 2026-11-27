<?php

use App\Core\Auth;

$admin = Auth::admin();
$kpis = $kpis ?? [];
$daily = $daily ?? [];
$forecastDays = $forecast['days'] ?? [];
$lowStock = $lowStock ?? [];

// History followed by forecast on one time axis
$chart = [
    'labels' => array_merge(array_column($daily, 'day'), array_column($forecastDays, 'date')),
    'actual' => array_merge(array_column($daily, 'products'), array_fill(0, count($forecastDays), null)),
    'forecast' => array_merge(array_fill(0, count($daily), null), array_column($forecastDays, 'value')),
    'lower' => array_merge(array_fill(0, count($daily), null), array_column($forecastDays, 'lower')),
    'upper' => array_merge(array_fill(0, count($daily), null), array_column($forecastDays, 'upper')),
    'names' => [t('dash_actual'), t('dash_forecast'), t('dash_lower'), t('dash_upper')],
];

$cards = [
    ['dash_today', format_price((float)($kpis['today_revenue'] ?? 0)), '/admin/reports'],
    ['dash_pending', (string)($kpis['pending_orders'] ?? 0), '/admin/orders?status=Pending'],
    ['dash_low_stock', (string)count($lowStock), '#lowStock'],
    ['dash_expiring', (string)($kpis['expiring_batches'] ?? 0), '/admin/batches'],
    ['dash_new_customers', (string)($kpis['new_customers'] ?? 0), '/admin/users'],
];
$links = [
    ['reports', 'bi-graph-up', 'admin_reports'], ['orders', 'bi-receipt', 'admin_orders'], ['users', 'bi-people', 'admin_users'],
    ['products', 'bi-box-seam', 'admin_products'], ['categories', 'bi-tags', 'admin_categories'], ['discounts', 'bi-percent', 'admin_discounts'],
    ['herbs', 'bi-journal-medical', 'admin_herbs'], ['coupons', 'bi-ticket-perforated', 'admin_coupons'], ['zones', 'bi-truck', 'admin_zones'],
    ['bank-slips', 'bi-bank', 'admin_slips'], ['batches', 'bi-qr-code', 'admin_batches'], ['assistant', 'bi-chat-dots', 'admin_assistant'],
    ['quiz', 'bi-clipboard2-pulse', 'admin_quiz'], ['dosha', 'bi-diagram-3', 'admin_dosha'], ['safety', 'bi-shield-check', 'admin_safety'],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h2 class="fw-bold text-success mb-1"><?= e(t('admin_dashboard')) ?></h2>
        <p class="text-muted small mb-0"><?= e(t('admin_welcome', ['name' => $admin['name'] ?? 'Admin', 'email' => $admin['email'] ?? ''])) ?></p>
    </div>
    <form action="/admin/logout" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i> <?= e(t('admin_logout')) ?></button>
    </form>
</div>

<nav class="d-flex flex-wrap gap-2 mb-4" aria-label="<?= e(t('admin_quick_actions')) ?>">
    <?php foreach ($links as [$path, $icon, $key]): ?>
        <a href="/admin/<?= $path ?>" class="btn btn-outline-success btn-sm"><i class="bi <?= $icon ?> me-1"></i><?= e(t($key)) ?></a>
    <?php endforeach; ?>
</nav>

<div class="row row-cols-2 row-cols-lg-5 g-3 mb-4">
    <?php foreach ($cards as [$key, $value, $href]): ?>
        <div class="col">
            <a href="<?= $href ?>" class="card border-0 shadow-sm p-3 h-100 text-decoration-none">
                <span class="text-muted small"><?= e(t($key)) ?></span>
                <span class="fs-4 fw-bold text-success text-break"><?= e($value) ?></span>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm p-3 mb-4">
    <h5 class="fw-bold text-success"><?= e(t('dash_trend')) ?></h5>
    <p class="small text-muted mb-2">
        <?= e(t(($forecast['method'] ?? '') === 'holt_winters' ? 'dash_method_model' : 'dash_method_average')) ?>
    </p>
    <div style="height: 300px;">
        <canvas id="salesChart" role="img" aria-label="<?= e(t('dash_trend')) ?>" data-chart="<?= e(json_encode($chart, JSON_UNESCAPED_UNICODE)) ?>"></canvas>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm p-3 h-100">
            <h5 class="fw-bold text-success"><?= e(t('report_top')) ?></h5>
            <?php if (empty($top)): ?><p class="small text-muted mb-0"><?= e(t('report_none')) ?></p><?php else: ?>
                <div class="table-responsive"><table class="table table-sm small mb-0">
                    <thead><tr><th><?= e(t('col_product')) ?></th><th class="text-end"><?= e(t('report_units')) ?></th><th class="text-end"><?= e(t('report_product_sales')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($top as $p): ?>
                            <tr><td><?= e($p['product_name']) ?></td><td class="text-end"><?= (int)$p['units'] ?></td><td class="text-end"><?= e(format_price((float)$p['sales'])) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm p-3 h-100">
            <h5 class="fw-bold text-success"><?= e(t('report_categories')) ?></h5>
            <?php if (empty($categories)): ?><p class="small text-muted mb-0"><?= e(t('report_none')) ?></p><?php else: ?>
                <div style="height: 240px;">
                    <canvas id="categoryChart" role="img" aria-label="<?= e(t('report_categories')) ?>"
                        data-chart="<?= e(json_encode(['labels' => array_column($categories, 'category'), 'values' => array_map('floatval', array_column($categories, 'sales'))], JSON_UNESCAPED_UNICODE)) ?>"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm p-3 h-100" id="lowStock">
            <h5 class="fw-bold text-success"><?= e(t('dash_low_stock')) ?></h5>
            <?php if (empty($lowStock)): ?><p class="small text-muted mb-0"><?= e(t('report_none')) ?></p><?php else: ?>
                <div class="table-responsive"><table class="table table-sm small mb-0">
                    <thead><tr><th><?= e(t('col_product')) ?></th><th class="text-end"><?= e(t('admin_stock')) ?></th><th class="text-end"><?= e(t('dash_days_left')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($lowStock as $p): ?>
                            <tr><td><?= e($p['product_name']) ?></td><td class="text-end"><?= (int)$p['stock'] ?></td><td class="text-end"><?= $p['days_until_stockout'] === null ? '–' : (int)$p['days_until_stockout'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm p-3 h-100">
            <h5 class="fw-bold text-success"><?= e(t('admin_expiring')) ?></h5>
            <?php if (empty($expiring)): ?><p class="small text-muted mb-0"><?= e(t('report_none')) ?></p><?php else: ?>
                <ul class="small mb-0">
                    <?php foreach ($expiring as $b): ?>
                        <li><a href="/admin/batches" class="text-reset"><?= e($b['batch_code']) ?></a> · <?= e($b['product_name']) ?> · <?= e($b['expires_on']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="/assets/js/admin.js" defer></script>
