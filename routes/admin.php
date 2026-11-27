<?php

/**
 * Phase 7 routes: admin dashboard, reports, orders, customers and catalogue
 * management, plus the customer's profile.
 * Included by public/index.php, which provides $requestUri and $requestMethod.
 * Every handled route ends the request.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Pager;
use App\Core\Request;
use App\Core\SessionGuard;
use App\Repository\ContactRepo;
use App\Repository\SettingRepo;
use App\Service\AccountService;
use App\Core\Database;
use App\Core\Upload;
use App\Core\View;
use App\Repository\BatchRepo;
use App\Repository\HerbRepo;
use App\Repository\ProductRepo;
use App\Service\CatalogService;
use App\Service\CustomerService;
use App\Service\ForecastService;
use App\Service\OrderService;
use App\Service\ReportService;

$input = fn(string $key): string => isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
$query = fn(string $key): string => isset($_GET[$key]) && is_string($_GET[$key]) ? $_GET[$key] : '';
$to = function (string $path): never {
    header('Location: ' . $path);
    exit;
};
$checkToken = function (): void {
    if (!Csrf::validateRequest()) {
        http_response_code(403);
        exit(e(t('err_csrf')));
    }
};
$notices = function (): array {
    $out = ['flash' => $_SESSION['flash_success'] ?? null, 'error' => $_SESSION['flash_error'] ?? null];
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    return $out;
};
$finish = function (array $result, string $path) use ($to): never {
    if ($result['ok']) {
        $_SESSION['flash_success'] = t('admin_saved');
    } else {
        $_SESSION['flash_error'] = $result['error'] ?? t('admin_not_saved');
    }
    $to($path);
};
$page = function (string $template, array $data) use ($notices): never {
    echo View::render($template, $data + $notices());
    exit;
};

// ---------------------------------------------------------------- Customer profile
if ($requestUri === '/profile/delete' && $requestMethod === 'POST') {
    Auth::requireUser();
    $checkToken();
    $ip = Request::clientIp($_SERVER);
    if (Auth::isLockedOut('account-delete', $ip)) {
        $finish(['ok' => false, 'error' => t('err_too_many')], '/profile');
    }
    if ($input('confirm') !== '1') {
        $finish(['ok' => false, 'error' => t('account_err_confirm')], '/profile');
    }
    $deleted = AccountService::deleteCustomer(Auth::id(), $input('password'));
    if (!$deleted['ok']) {
        Auth::recordAttempt('account-delete', $ip, false);
        $finish($deleted, '/profile');
    }
    Auth::logout();
    $to('/login?deleted=1');
}

if ($requestUri === '/profile/sign-out-others' && $requestMethod === 'POST') {
    Auth::requireUser();
    $checkToken();
    SessionGuard::revoke('user', Auth::id(), true);
    $_SESSION['flash_success'] = t('signed_out_others');
    $to('/profile');
}

if ($requestUri === '/profile' || $requestUri === '/profile/password') {
    Auth::requireUser();
    if ($requestMethod === 'POST') {
        $checkToken();
        if ($requestUri === '/profile') {
            $res = CustomerService::updateProfile(Auth::id(), $_POST);
            if ($res['ok']) {
                $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $res['user']);
            }
        } else {
            $res = CustomerService::changePassword(Auth::id(), $input('current_password'), $input('password'), $input('password_confirmation'));
        }
        $finish($res, '/profile');
    }
    $page('shop/profile', ['pageTitle' => t('profile_title'), 'profile' => CustomerService::find(Auth::id())]);
}

// ---------------------------------------------------------------- Admin
$sections = ['dashboard', 'reports', 'orders', 'users', 'products', 'categories', 'discounts', 'herbs', 'settings', 'password', 'messages'];
if (!preg_match('#^/admin(?:/(' . implode('|', $sections) . '))?(?:/|$)#', $requestUri, $match) || $requestUri === '/admin/login') {
    return;
}
// Order status changes and stock updates stay in index.php
if (in_array($requestUri, ['/admin/orders/status', '/admin/products/stock'], true)) {
    return;
}
Auth::requireAdmin();

if ($requestMethod === 'GET') {
    if ($requestUri === '/admin' || $requestUri === '/admin/' || $requestUri === '/admin/dashboard') {
        [$from, $until] = ReportService::range(null, null);
        $page('admin/dashboard', [
            'pageTitle' => t('admin_dashboard'),
            'kpis' => ReportService::kpis(),
            'daily' => ReportService::daily($from, $until),
            'forecast' => ForecastService::sales(30),
            'top' => ReportService::topProducts($from, $until),
            'categories' => ReportService::categorySales($from, $until),
            'lowStock' => ReportService::lowStock(),
            'refundsDue' => OrderService::refundsDue(),
            'openMessages' => ContactRepo::count(true),
            'expiring' => BatchRepo::expiringWithin(30),
        ]);
    }

    if ($requestUri === '/admin/reports') {
        [$from, $until] = ReportService::range($query('from'), $query('to'));
        $page('admin/reports', ['pageTitle' => t('admin_reports'), 'report' => ReportService::report($from, $until)]);
    }

    if ($requestUri === '/admin/reports/export') {
        [$from, $until] = ReportService::range($query('from'), $query('to'));
        $report = ReportService::report($from, $until);
        $name = "sales-{$from}-to-{$until}";
        if ($query('format') === 'pdf') {
            $body = ReportService::pdf($report);
            header('Content-Type: application/pdf');
            header("Content-Disposition: attachment; filename=\"{$name}.pdf\"");
        } else {
            $body = ReportService::csv($report);
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$name}.csv\"");
        }
        echo $body;
        exit;
    }

    if ($requestUri === '/admin/orders') {
        $filters = ['from' => $query('from'), 'to' => $query('to'), 'status' => $query('status'), 'method' => $query('method'), 'refund' => $query('refund')];
        $pager = Pager::of(OrderService::countMatching($filters), $query('page'), 25);
        $page('admin/orders', [
            'pageTitle' => t('admin_orders'),
            'orders' => OrderService::search($filters, $pager['per'], $pager['offset']),
            'filters' => $filters,
            'pager' => $pager,
        ]);
    }

    if ($requestUri === '/admin/users') {
        $pager = Pager::of(CustomerService::countMatching($query('q')), $query('page'), 25);
        $page('admin/users', [
            'pageTitle' => t('admin_users'),
            'users' => CustomerService::list($query('q'), $pager['per'], $pager['offset']),
            'search' => $query('q'),
            'pager' => $pager,
        ]);
    }

    if (preg_match('#^/admin/users/(\d+)$#', $requestUri, $m)) {
        $customer = CustomerService::find((int)$m[1]);
        if (!$customer) {
            $to('/admin/users');
        }
        $page('admin/user_detail', [
            'pageTitle' => $customer['name'],
            'customer' => $customer,
            'orders' => OrderService::getUserOrders((int)$m[1]),
            'hasOpenOrders' => AccountService::hasOpenOrders((int)$m[1]),
        ]);
    }

    if ($requestUri === '/admin/products') {
        $products = ProductRepo::findAll();
        foreach ($products as &$p) {
            $p['herbs'] = HerbRepo::getHerbsByProduct((int)$p['product_id']);
        }
        unset($p);
        $page('admin/products', [
            'pageTitle' => t('admin_products'),
            'products' => $products,
            'allHerbs' => HerbRepo::findAll(),
            'categories' => ProductRepo::getAllCategories(),
        ]);
    }

    if ($requestUri === '/admin/categories') {
        $page('admin/categories', ['pageTitle' => t('admin_categories'), 'categories' => ProductRepo::getAllCategories()]);
    }

    if ($requestUri === '/admin/discounts') {
        $page('admin/discounts', [
            'pageTitle' => t('admin_discounts'),
            'discounts' => CatalogService::discounts(),
            'products' => ProductRepo::findAll(),
        ]);
    }

    if ($requestUri === '/admin/settings') {
        $page('admin/settings', ['pageTitle' => t('admin_settings'), 'settings' => SettingRepo::all()]);
    }

    if ($requestUri === '/admin/password') {
        $page('admin/password', ['pageTitle' => t('admin_password')]);
    }

    if ($requestUri === '/admin/messages') {
        $pager = Pager::of(ContactRepo::count(), $query('page'), 25);
        $page('admin/messages', [
            'pageTitle' => t('admin_messages'),
            'messages' => ContactRepo::page($pager['page'], $pager['per']),
            'pager' => $pager,
        ]);
    }

    if ($requestUri === '/admin/herbs') {
        $herbs = HerbRepo::findAll(null, 'en');
        foreach ($herbs as &$h) {
            $h['concern_ids'] = array_map('intval', array_column(HerbRepo::getConcernsByHerb((int)$h['herb_id']), 'concern_id'));
        }
        unset($h);
        $page('admin/herbs', ['pageTitle' => t('admin_herbs'), 'herbs' => $herbs, 'concerns' => HerbRepo::getAllConcerns()]);
    }
}

if ($requestMethod === 'POST') {
    $checkToken();
    $id = (int)$input('id');

    // Store an uploaded picture, if one was chosen
    $withImage = function (array $data, string $folder, string $back) use ($finish): array {
        unset($data['image']);
        $file = $_FILES['image_file'] ?? null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = Upload::process($file, $folder);
            if (!$upload['ok']) {
                $finish($upload, $back);
            }
            $data['image'] = $upload['path'];
        }
        return $data;
    };

    switch ($requestUri) {
        case '/admin/orders/paid':
            $finish(OrderService::markBankPayment($id, (int)(Auth::admin()['admin_id'] ?? 0) ?: null), '/admin/orders');
        case '/admin/orders/refunded':
            $finish(OrderService::markRefunded($id, (int)(Auth::admin()['admin_id'] ?? 0) ?: null), '/admin/orders');
        case '/admin/settings/save':
            $finish(SettingRepo::save($_POST), '/admin/settings');
        case '/admin/messages/handled':
            ContactRepo::setHandled($id, $input('handled') === '1');
            $finish(['ok' => true], '/admin/messages');
        case '/admin/messages/delete':
            ContactRepo::delete($id);
            $finish(['ok' => true], '/admin/messages');
        case '/admin/password/change':
            $ip = Request::clientIp($_SERVER);
            if (Auth::isLockedOut('admin-password', $ip)) {
                $finish(['ok' => false, 'error' => t('err_too_many')], '/admin/password');
            }
            $changed = AccountService::changeAdminPassword((int)(Auth::admin()['admin_id'] ?? 0), $input('current_password'), $input('password'), $input('password_confirmation'));
            if (!empty($changed['wrong_current'])) {
                Auth::recordAttempt('admin-password', $ip, false);
            }
            $finish($changed, '/admin/password');
        case '/admin/password/sign-out-others':
            SessionGuard::revoke('admin', (int)(Auth::admin()['admin_id'] ?? 0), true);
            $_SESSION['flash_success'] = t('signed_out_others');
            $to('/admin/password');
        case '/admin/users/delete':
            $ip = Request::clientIp($_SERVER);
            if (Auth::isLockedOut('admin-delete', $ip)) {
                $finish(['ok' => false, 'error' => t('err_too_many')], '/admin/users/' . $id);
            }
            $removed = AccountService::purgeCustomer($id, $input('confirm_email'), (int)(Auth::admin()['admin_id'] ?? 0), $input('admin_password'));
            if (!$removed['ok']) {
                if (!empty($removed['wrong_password'])) {
                    Auth::recordAttempt('admin-delete', $ip, false);
                }
                $finish($removed, '/admin/users/' . $id);
            }
            $_SESSION['flash_success'] = t('admin_user_deleted', ['orders' => $removed['orders']]);
            $to('/admin/users');
        case '/admin/users/block':
            $finish(['ok' => CustomerService::setBlocked($id, $input('blocked') === '1')], '/admin/users/' . $id);
        case '/admin/products/save':
            $finish(CatalogService::saveProduct($id, $withImage($_POST, 'products', '/admin/products')), '/admin/products');
        case '/admin/products/delete':
            $finish(CatalogService::deleteProduct($id), '/admin/products');
        case '/admin/categories/save':
            $finish(CatalogService::saveCategory($id, $_POST), '/admin/categories');
        case '/admin/categories/delete':
            $finish(CatalogService::deleteCategory($id), '/admin/categories');
        case '/admin/discounts/save':
            $finish(CatalogService::saveDiscount($id, $_POST), '/admin/discounts');
        case '/admin/discounts/delete':
            $finish(CatalogService::deleteDiscount($id), '/admin/discounts');
        case '/admin/herbs/save':
            $finish(CatalogService::saveHerb($id, $withImage($_POST, 'herbs', '/admin/herbs')), '/admin/herbs');
        case '/admin/herbs/topic':
            $finish(['ok' => HerbRepo::renameConcern($id, $input('name_en'), $input('name_si'))], '/admin/herbs');
        case '/admin/herbs/delete':
            $finish(CatalogService::deleteHerb($id), '/admin/herbs');
    }
}
