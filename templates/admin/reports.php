<?php

use App\Core\View;

$s = $report['summary'];
$query = http_build_query(['from' => $report['from'], 'to' => $report['to']]);
$tables = [
    ['report_top', ['col_product', 'report_units', 'report_product_sales'],
        array_map(fn($p) => [$p['product_name'], $p['units'], format_price((float)$p['sales'])], $report['top'])],
    ['report_categories', ['admin_category', 'report_units', 'report_product_sales'],
        array_map(fn($c) => [$c['category'], $c['units'], format_price((float)$c['sales'])], $report['categories'])],
    ['report_statuses', ['col_status', 'report_orders', 'col_total'],
        array_map(fn($r) => [t_or('status_', $r['label']), $r['orders'], format_price((float)$r['total'])], $report['statuses'])],
    ['report_methods', ['col_method', 'report_orders', 'col_total'],
        array_map(fn($r) => [t_or('method_', $r['label']), $r['orders'], format_price((float)$r['total'])], $report['methods'])],
    ['report_daily', ['col_date', 'report_orders', 'report_revenue'],
        array_map(fn($d) => [$d['day'], $d['orders'], format_price($d['revenue'])], array_values(array_filter($report['daily'], fn($d) => $d['orders'] > 0)))],
];
?>
<?= View::partial('admin_heading', ['title' => t('admin_reports'), 'flash' => $flash ?? null, 'error' => $error ?? null]) ?>

<form method="GET" action="/admin/reports" class="card border-0 shadow-sm p-3 mb-3 row g-2 flex-row align-items-end">
    <div class="col-6 col-md-3">
        <label class="form-label small" for="from"><?= e(t('report_from')) ?></label>
        <input type="date" class="form-control form-control-sm" id="from" name="from" value="<?= e($report['from']) ?>">
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small" for="to"><?= e(t('report_to')) ?></label>
        <input type="date" class="form-control form-control-sm" id="to" name="to" value="<?= e($report['to']) ?>">
    </div>
    <div class="col-md-6 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-suwoda btn-sm"><?= e(t('report_show')) ?></button>
        <a class="btn btn-outline-dark btn-sm" href="/admin/reports/export?<?= e($query) ?>&amp;format=csv"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
        <a class="btn btn-outline-dark btn-sm" href="/admin/reports/export?<?= e($query) ?>&amp;format=pdf"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    </div>
</form>

<div class="row g-3 mb-4">
    <?php foreach (['report_orders' => (string)$s['orders'], 'report_revenue' => format_price($s['revenue']), 'report_average' => format_price($s['average_order_value'])] as $label => $value): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3">
                <span class="text-muted small"><?= e(t($label)) ?></span>
                <span class="fs-4 fw-bold text-success"><?= e($value) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php foreach ($tables as [$title, $columns, $rows]): ?>
    <div class="card border-0 shadow-sm p-3 mb-3">
        <h5 class="fw-bold text-success"><?= e(t($title)) ?></h5>
        <?php if (empty($rows)): ?><p class="small text-muted mb-0"><?= e(t('report_none')) ?></p><?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm small mb-0">
                    <thead><tr><?php foreach ($columns as $c): ?><th><?= e(t($c)) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr><?php foreach ($row as $cell): ?><td><?= e((string)$cell) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
