<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);

// 1. Autoloader
if (file_exists($rootDir . '/vendor/autoload.php')) {
    require_once $rootDir . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) use ($rootDir) {
        $prefix = 'App\\';
        $baseDir = $rootDir . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

// 2. Helpers
require_once $rootDir . '/src/Core/helpers.php';

use App\Core\Env;
use App\Core\Lang;
use App\Core\View;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Validator;
use App\Core\Mailer;
use App\Core\Upload;
use App\Repository\ProductRepo;
use App\Repository\HerbRepo;
use App\Service\SearchService;
use App\Service\PasswordResetService;
use App\Service\CheckoutService;
use App\Service\DeliveryZoneService;
use App\Service\CouponService;
use App\Service\OrderService;
use App\Service\InvoiceService;
use App\Service\WishlistAndAlertService;

// 3. Environment & Error Reporting
Env::load($rootDir);

$debug = (bool)Env::get('APP_DEBUG', true);
if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 4. Session & Language
Auth::initSession();
Lang::init();

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ----------------------------------------------------
// PUBLIC API & HEALTH
// ----------------------------------------------------
if ($requestUri === '/api/health' && $requestMethod === 'GET') {
    json_response(['ok' => true]);
}

// ----------------------------------------------------
// HOME PAGE & LANGUAGE SWITCH
// ----------------------------------------------------
if ($requestUri === '/' || $requestUri === '') {
    echo View::render('home', [
        'pageTitle' => t('welcome_title'),
        'herbOfTheDay' => HerbRepo::getHerbOfTheDay(),
        'concerns' => HerbRepo::getAllConcerns(),
        'featuredProducts' => array_slice(ProductRepo::findAll(), 0, 4)
    ]);
    exit;
}

if ($requestUri === '/lang' && $requestMethod === 'POST') {
    $lang = (string)($_POST['lang'] ?? 'en');
    if (in_array($lang, ['en', 'si'], true)) {
        Lang::setLocale($lang);
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '/';
    if (parse_url($referer, PHP_URL_PATH) === '/lang') {
        $referer = '/';
    }
    header('Location: ' . $referer);
    exit;
}

// ----------------------------------------------------
// SEARCH & AUTOCOMPLETE (PHASE 3)
// ----------------------------------------------------
if ($requestUri === '/search' && $requestMethod === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $results = SearchService::search($q);
    echo View::render('shop/search', [
        'pageTitle' => t('search_results_title', ['query' => $q]),
        'query' => $q,
        'results' => $results
    ]);
    exit;
}

if ($requestUri === '/api/search/suggest' && $requestMethod === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $res = SearchService::suggest($q);
    json_response($res);
}

// ----------------------------------------------------
// USER AUTHENTICATION
// ----------------------------------------------------
if ($requestUri === '/login') {
    if (Auth::check()) {
        header('Location: /');
        exit;
    }

    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('auth/login', ['error' => 'Invalid or expired CSRF token. Please try again.']);
            exit;
        }

        $email = (string)($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $result = Auth::attemptUser($email, $password, $clientIp);
        if ($result['ok']) {
            header('Location: /');
            exit;
        }

        echo View::render('auth/login', [
            'error' => $result['error'],
            'email' => $email
        ]);
        exit;
    }

    echo View::render('auth/login', [
        'pageTitle' => t('nav_login'),
        'success' => $_GET['registered'] ? 'Registration successful! You may now login.' : null
    ]);
    exit;
}

if ($requestUri === '/register') {
    if (Auth::check()) {
        header('Location: /');
        exit;
    }

    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('auth/register', ['error' => 'Invalid or expired CSRF token. Please try again.']);
            exit;
        }

        $validator = Validator::make($_POST, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|max:100',
            'password' => 'required|min:8|matches:password_confirmation',
        ]);

        if ($validator->fails()) {
            echo View::render('auth/register', [
                'error' => $validator->first('name') ?? $validator->first('email') ?? $validator->first('password'),
                'old' => $_POST
            ]);
            exit;
        }

        $pdo = Database::getConnection();
        $email = strtolower(trim((string)$_POST['email']));

        // Check if email already registered
        $stmt = $pdo->prepare("SELECT user_id FROM `user` WHERE LOWER(email) = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            echo View::render('auth/register', [
                'error' => 'This email address is already registered.',
                'old' => $_POST
            ]);
            exit;
        }

        // Create new user (hashed password only)
        $passwordHash = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
        $verifyToken = bin2hex(random_bytes(32));

        $ins = $pdo->prepare("
            INSERT INTO `user` (name, email, password, phone, status, verification_token)
            VALUES (:name, :email, :password, :phone, 'active', :token)
        ");
        $ins->execute([
            ':name' => trim((string)$_POST['name']),
            ':email' => $email,
            ':password' => $passwordHash,
            ':phone' => trim((string)($_POST['phone'] ?? '')),
            ':token' => $verifyToken
        ]);

        // Optional verification email dispatch
        Mailer::sendVerification($email, trim((string)$_POST['name']), $verifyToken);

        header('Location: /login?registered=1');
        exit;
    }

    echo View::render('auth/register', ['pageTitle' => t('nav_register')]);
    exit;
}

if ($requestUri === '/verify') {
    $token = (string)($_GET['token'] ?? '');
    if (!empty($token)) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE `user` SET status = 'active', verification_token = NULL WHERE verification_token = :token");
        $stmt->execute([':token' => $token]);
    }
    header('Location: /login?verified=1');
    exit;
}

if ($requestUri === '/forgot-password') {
    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('auth/forgot_password', ['error' => 'Invalid CSRF token.']);
            exit;
        }

        $email = (string)($_POST['email'] ?? '');
        $res = PasswordResetService::requestReset($email);

        echo View::render('auth/forgot_password', [
            'message' => $res['message'] ?? null,
            'error' => $res['error'] ?? null
        ]);
        exit;
    }

    echo View::render('auth/forgot_password', ['pageTitle' => 'Forgot Password']);
    exit;
}

if ($requestUri === '/reset-password') {
    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('auth/reset_password', ['error' => 'Invalid CSRF token.', 'token' => '']);
            exit;
        }

        $token = (string)($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirmation'] ?? '');

        if (strlen($password) < 8) {
            echo View::render('auth/reset_password', ['error' => 'Password must be at least 8 characters.', 'token' => $token]);
            exit;
        }

        if ($password !== $confirm) {
            echo View::render('auth/reset_password', ['error' => 'Passwords do not match.', 'token' => $token]);
            exit;
        }

        $res = PasswordResetService::resetPassword($token, $password);
        if ($res['ok']) {
            echo View::render('auth/reset_password', ['success' => $res['message'], 'token' => $token]);
            exit;
        }

        echo View::render('auth/reset_password', ['error' => $res['error'], 'token' => $token]);
        exit;
    }

    $token = (string)($_GET['token'] ?? '');
    $check = PasswordResetService::validateToken($token);

    if (!$check['ok']) {
        echo View::render('auth/reset_password', ['error' => $check['error'], 'token' => '']);
        exit;
    }

    echo View::render('auth/reset_password', ['token' => $token]);
    exit;
}

if ($requestUri === '/logout' && $requestMethod === 'POST') {
    if (Csrf::validateRequest()) {
        Auth::logout();
    }
    header('Location: /');
    exit;
}

// ----------------------------------------------------
// PHASE 2 & 3: STOREFRONT & HERBAL KNOWLEDGE
// ----------------------------------------------------
if ($requestUri === '/products' && $requestMethod === 'GET') {
    $catId = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : null;
    $products = ProductRepo::findAll($catId);
    $categories = ProductRepo::getAllCategories();
    $wishlistIds = Auth::check() ? WishlistAndAlertService::getUserWishlistIds(Auth::id()) : [];

    $flash = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);

    echo View::render('shop/products', [
        'pageTitle' => t('nav_products'),
        'products' => $products,
        'categories' => $categories,
        'selectedCategory' => $catId,
        'wishlistIds' => $wishlistIds,
        'flash' => $flash
    ]);
    exit;
}

if (preg_match('#^/products/(\d+)$#', $requestUri, $m) && $requestMethod === 'GET') {
    $productId = (int)$m[1];
    $product = ProductRepo::findById($productId);
    if (!$product) {
        http_response_code(404);
        echo "<h1>404 Product Not Found</h1>";
        exit;
    }
    $herbs = HerbRepo::getHerbsByProduct($productId);
    $wishlistIds = Auth::check() ? WishlistAndAlertService::getUserWishlistIds(Auth::id()) : [];
    echo View::render('shop/product_detail', [
        'pageTitle' => $product['display_name'] ?? $product['product_name'],
        'product' => $product,
        'herbs' => $herbs,
        'isWishlisted' => in_array($productId, $wishlistIds, true)
    ]);
    exit;
}

if ($requestUri === '/dictionary' && $requestMethod === 'GET') {
    $letter = isset($_GET['letter']) ? strtoupper(trim((string)$_GET['letter'])) : null;
    $herbs = HerbRepo::findAll($letter);
    echo View::render('shop/dictionary', [
        'pageTitle' => t('nav_dictionary'),
        'herbs' => $herbs,
        'selectedLetter' => $letter
    ]);
    exit;
}

if (preg_match('#^/herb/(\d+)$#', $requestUri, $m) && $requestMethod === 'GET') {
    $herbId = (int)$m[1];
    $herb = HerbRepo::findById($herbId);
    if (!$herb) {
        http_response_code(404);
        echo "<h1>404 Herb Not Found</h1>";
        exit;
    }
    $products = HerbRepo::getProductsByHerb($herbId);
    $concerns = HerbRepo::getConcernsByHerb($herbId);
    echo View::render('shop/herb_detail', [
        'pageTitle' => $herb['display_name'],
        'herb' => $herb,
        'products' => $products,
        'concerns' => $concerns
    ]);
    exit;
}

if (preg_match('#^/concern/([a-z0-9-]+)$#', $requestUri, $m) && $requestMethod === 'GET') {
    $slug = $m[1];
    $concern = HerbRepo::getConcernBySlug($slug);
    if (!$concern) {
        http_response_code(404);
        echo "<h1>404 Health Concern Not Found</h1>";
        exit;
    }
    $herbs = HerbRepo::getHerbsByConcern((int)$concern['concern_id']);
    
    // Products prepared with these herbs
    $products = [];
    $seenPids = [];
    foreach ($herbs as $h) {
        $hProds = HerbRepo::getProductsByHerb((int)$h['herb_id']);
        foreach ($hProds as $hp) {
            if (!in_array($hp['product_id'], $seenPids, true)) {
                $seenPids[] = $hp['product_id'];
                $products[] = $hp;
            }
        }
    }

    echo View::render('shop/concern', [
        'pageTitle' => $concern['display_name'],
        'concern' => $concern,
        'herbs' => $herbs,
        'products' => $products
    ]);
    exit;
}

if ($requestUri === '/cart' && $requestMethod === 'GET') {
    Auth::requireUser();
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        SELECT c.product_id, c.quantity, p.product_name, p.price, p.quantity AS stock,
               (SELECT MAX(d.discount_percentage)
                FROM discount d
                WHERE d.product_id = p.product_id
                  AND (d.start_date IS NULL OR d.start_date <= NOW())
                  AND (d.end_date IS NULL OR d.end_date >= NOW())
               ) AS active_discount_percentage
        FROM cart c
        JOIN product p ON p.product_id = c.product_id
        WHERE c.user_id = :uid
    ");
    $stmt->execute([':uid' => Auth::id()]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cartItems = [];
    $subtotal = 0.0;
    foreach ($rows as $row) {
        $enriched = ProductRepo::enrichWithSalePrice($row);
        $lineTotal = round((float)$enriched['effective_price'] * (int)$row['quantity'], 2);
        $enriched['line_total'] = $lineTotal;
        $subtotal += $lineTotal;
        $cartItems[] = $enriched;
    }

    echo View::render('shop/cart', [
        'pageTitle' => t('nav_cart'),
        'cartItems' => $cartItems,
        'subtotal' => $subtotal,
        'zones' => DeliveryZoneService::getAllZones()
    ]);
    exit;
}

if ($requestUri === '/cart/add' && $requestMethod === 'POST') {
    Auth::requireUser();
    if (!Csrf::validateRequest()) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }

    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $pdo = Database::getConnection();

    $check = $pdo->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = :uid AND product_id = :pid");
    $check->execute([':uid' => Auth::id(), ':pid' => $pid]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $upd = $pdo->prepare("UPDATE cart SET quantity = quantity + :qty WHERE cart_id = :cid");
        $upd->execute([':qty' => $qty, ':cid' => $existing['cart_id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (:uid, :pid, :qty)");
        $ins->execute([':uid' => Auth::id(), ':pid' => $pid, ':qty' => $qty]);
    }

    $_SESSION['flash_success'] = 'Added to cart!';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/cart'));
    exit;
}

if ($requestUri === '/cart/update' && $requestMethod === 'POST') {
    Auth::requireUser();
    if (!Csrf::validateRequest()) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $pdo = Database::getConnection();
    $upd = $pdo->prepare("UPDATE cart SET quantity = :qty WHERE user_id = :uid AND product_id = :pid");
    $upd->execute([':qty' => $qty, ':uid' => Auth::id(), ':pid' => $pid]);
    header('Location: /cart');
    exit;
}

if ($requestUri === '/cart/remove' && $requestMethod === 'POST') {
    Auth::requireUser();
    if (!Csrf::validateRequest()) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
    $pid = (int)($_POST['product_id'] ?? 0);
    $pdo = Database::getConnection();
    $del = $pdo->prepare("DELETE FROM cart WHERE user_id = :uid AND product_id = :pid");
    $del->execute([':uid' => Auth::id(), ':pid' => $pid]);
    header('Location: /cart');
    exit;
}

if ($requestUri === '/checkout') {
    Auth::requireUser();
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("
        SELECT c.product_id, c.quantity, p.product_name, p.price, p.quantity AS stock,
               (SELECT MAX(d.discount_percentage)
                FROM discount d
                WHERE d.product_id = p.product_id
                  AND (d.start_date IS NULL OR d.start_date <= NOW())
                  AND (d.end_date IS NULL OR d.end_date >= NOW())
               ) AS active_discount_percentage
        FROM cart c
        JOIN product p ON p.product_id = c.product_id
        WHERE c.user_id = :uid
    ");
    $stmt->execute([':uid' => Auth::id()]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        header('Location: /cart');
        exit;
    }

    $cartItems = [];
    $subtotal = 0.0;
    foreach ($rows as $row) {
        $enriched = ProductRepo::enrichWithSalePrice($row);
        $lineTotal = round((float)$enriched['effective_price'] * (int)$row['quantity'], 2);
        $enriched['line_total'] = $lineTotal;
        $subtotal += $lineTotal;
        $cartItems[] = $enriched;
    }

    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('shop/checkout', [
                'error' => 'Invalid CSRF token.',
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
                'zones' => DeliveryZoneService::getAllZones(),
                'old' => $_POST
            ]);
            exit;
        }

        $paymentMethod = (string)($_POST['payment_method'] ?? 'cod');
        if (!in_array($paymentMethod, ['cod', 'bank_transfer', 'card'], true)) {
            $paymentMethod = 'cod';
        }

        $slipPath = null;
        if ($paymentMethod === 'bank_transfer' && !empty($_FILES['bank_slip']['name'])) {
            $uploadRes = Upload::process($_FILES['bank_slip'], 'slips');
            if (!$uploadRes['ok']) {
                echo View::render('shop/checkout', [
                    'error' => $uploadRes['error'],
                    'cartItems' => $cartItems,
                    'subtotal' => $subtotal,
                    'zones' => DeliveryZoneService::getAllZones(),
                    'old' => $_POST
                ]);
                exit;
            }
            $slipPath = $uploadRes['path'];
        }

        $orderRes = CheckoutService::createOrder(
            Auth::id(),
            $cartItems,
            $paymentMethod,
            null,
            [
                'shipping_name' => trim((string)($_POST['shipping_name'] ?? '')),
                'shipping_phone' => trim((string)($_POST['shipping_phone'] ?? '')),
                'shipping_address' => trim((string)($_POST['shipping_address'] ?? '')),
                'district' => trim((string)($_POST['district'] ?? '')),
                'coupon_code' => trim((string)($_POST['coupon_code'] ?? '')),
            ]
        );

        if (!$orderRes['ok']) {
            echo View::render('shop/checkout', [
                'error' => $orderRes['error'],
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
                'zones' => DeliveryZoneService::getAllZones(),
                'old' => $_POST
            ]);
            exit;
        }

        $gateway = CheckoutService::getGateway($paymentMethod);
        $payRes = $gateway->initiatePayment(
            (int)$orderRes['order_id'],
            (float)$orderRes['total_price'],
            [
                'email' => Auth::user()['email'] ?? '',
                'slip_path' => $slipPath
            ]
        );

        if (!$payRes['ok']) {
            echo View::render('shop/checkout', [
                'error' => $payRes['error'],
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
                'zones' => DeliveryZoneService::getAllZones(),
                'old' => $_POST
            ]);
            exit;
        }

        header('Location: ' . $payRes['redirect_url']);
        exit;
    }

    echo View::render('shop/checkout', [
        'pageTitle' => 'Checkout',
        'cartItems' => $cartItems,
        'subtotal' => $subtotal,
        'zones' => DeliveryZoneService::getAllZones()
    ]);
    exit;
}

if ($requestUri === '/orders' && $requestMethod === 'GET') {
    Auth::requireUser();
    echo View::render('shop/orders', [
        'pageTitle' => 'My Orders',
        'orders' => OrderService::getUserOrders(Auth::id())
    ]);
    exit;
}

if (preg_match('#^/orders/(\d+)$#', $requestUri, $m) && $requestMethod === 'GET') {
    Auth::requireUser();
    $order = OrderService::getOrderDetails((int)$m[1]);
    if (!$order || (int)$order['user_id'] !== Auth::id()) {
        http_response_code(404);
        echo "<h1>404 Order Not Found</h1>";
        exit;
    }

    $flash = $_SESSION['flash_success'] ?? null;
    $err = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);

    echo View::render('shop/order_detail', [
        'pageTitle' => 'Order #' . $order['order_id'],
        'order' => $order,
        'flash' => $flash,
        'error' => $err
    ]);
    exit;
}

if (preg_match('#^/orders/(\d+)/invoice$#', $requestUri, $m) && $requestMethod === 'GET') {
    Auth::requireUser();
    $order = OrderService::getOrderDetails((int)$m[1]);
    if (!$order || (int)$order['user_id'] !== Auth::id()) {
        http_response_code(404);
        exit('Order not found');
    }

    $pdf = InvoiceService::renderPdf($order);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="invoice-' . (int)$order['order_id'] . '.pdf"');
    echo $pdf;
    exit;
}

if (preg_match('#^/orders/(\d+)/cancel$#', $requestUri, $m) && $requestMethod === 'POST') {
    Auth::requireUser();
    if (!Csrf::validateRequest()) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
    $res = OrderService::cancelByCustomer((int)$m[1], Auth::id());
    if ($res['ok']) {
        $_SESSION['flash_success'] = $res['message'];
    } else {
        $_SESSION['flash_error'] = $res['error'];
    }
    header('Location: /orders/' . (int)$m[1]);
    exit;
}

if (preg_match('#^/orders/(\d+)/upload-slip$#', $requestUri, $m) && $requestMethod === 'POST') {
    Auth::requireUser();
    if (!Csrf::validateRequest()) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
    $orderId = (int)$m[1];
    $order = OrderService::getOrderDetails($orderId);
    if (!$order || (int)$order['user_id'] !== Auth::id()) {
        http_response_code(404);
        exit('Order not found');
    }

    $uploadRes = Upload::process($_FILES['bank_slip'] ?? [], 'slips');
    if (!$uploadRes['ok']) {
        $_SESSION['flash_error'] = $uploadRes['error'];
    } else {
        $pdo = Database::getConnection();
        $ins = $pdo->prepare("INSERT INTO bank_slip (order_id, file_path, status, created_at) VALUES (:oid, :path, 'submitted', NOW())");
        $ins->execute([':oid' => $orderId, ':path' => $uploadRes['path']]);
        $_SESSION['flash_success'] = 'Bank slip uploaded! Admin will verify it shortly.';
    }
    header('Location: /orders/' . $orderId);
    exit;
}

if ($requestUri === '/wishlist' && $requestMethod === 'GET') {
    Auth::requireUser();
    echo View::render('shop/wishlist', [
        'pageTitle' => 'My Wishlist',
        'products' => WishlistAndAlertService::getUserWishlistProducts(Auth::id())
    ]);
    exit;
}

if ($requestUri === '/wishlist/toggle' && $requestMethod === 'POST') {
    Auth::requireUser();
    if (!Csrf::validateRequest()) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
    $pid = (int)($_POST['product_id'] ?? 0);
    $added = WishlistAndAlertService::toggleWishlist(Auth::id(), $pid);
    $_SESSION['flash_success'] = $added ? 'Added to wishlist.' : 'Removed from wishlist.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/products'));
    exit;
}

if ($requestUri === '/stock-alert' && $requestMethod === 'POST') {
    Auth::requireUser();
    if (!Csrf::validateRequest()) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
    $pid = (int)($_POST['product_id'] ?? 0);
    WishlistAndAlertService::subscribeStockAlert(Auth::id(), $pid);
    $_SESSION['flash_success'] = 'You will be notified by email once this item is back in stock!';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/products'));
    exit;
}

// ----------------------------------------------------
// ADMIN ROUTES & GUARDS
// ----------------------------------------------------
if ($requestUri === '/admin/login') {
    if (Auth::isAdmin()) {
        header('Location: /admin/dashboard');
        exit;
    }

    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('auth/admin_login', ['error' => 'Invalid CSRF token.']);
            exit;
        }

        $email = (string)($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $res = Auth::attemptAdmin($email, $password, $clientIp);
        if ($res['ok']) {
            header('Location: /admin/dashboard');
            exit;
        }

        echo View::render('auth/admin_login', [
            'error' => $res['error'],
            'email' => $email
        ]);
        exit;
    }

    echo View::render('auth/admin_login', ['pageTitle' => 'Admin Login']);
    exit;
}

if ($requestUri === '/admin/logout' && $requestMethod === 'POST') {
    if (Csrf::validateRequest()) {
        Auth::logout();
    }
    header('Location: /admin/login');
    exit;
}

// GUARD: Protect all other /admin/* routes
if (str_starts_with($requestUri, '/admin/')) {
    Auth::requireAdmin();

    if ($requestUri === '/admin/dashboard' || $requestUri === '/admin/') {
        $pdo = Database::getConnection();
        $stats = [
            'products' => (int)$pdo->query("SELECT COUNT(*) FROM `product`")->fetchColumn(),
            'herbs'    => (int)$pdo->query("SELECT COUNT(*) FROM `herb`")->fetchColumn(),
            'orders'   => (int)$pdo->query("SELECT COUNT(*) FROM `orders`")->fetchColumn(),
            'users'    => (int)$pdo->query("SELECT COUNT(*) FROM `user`")->fetchColumn(),
        ];

        echo View::render('admin/dashboard', [
            'pageTitle' => 'Admin Dashboard',
            'stats' => $stats
        ]);
        exit;
    }

    if ($requestUri === '/admin/orders' && $requestMethod === 'GET') {
        $pdo = Database::getConnection();
        $orders = $pdo->query("
            SELECT o.*, u.name AS user_name
            FROM orders o
            JOIN user u ON u.user_id = o.user_id
            ORDER BY o.order_date DESC, o.order_id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        echo View::render('admin/orders', [
            'pageTitle' => 'Manage Orders',
            'orders' => $orders,
            'flash' => $flash
        ]);
        exit;
    }

    if ($requestUri === '/admin/orders/status' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
        $orderId = (int)($_POST['order_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));
        $adminId = Auth::admin()['admin_id'] ?? null;

        OrderService::updateStatus($orderId, $status, $note !== '' ? $note : null, $adminId);
        $_SESSION['flash_success'] = "Order #{$orderId} updated to {$status}.";
        header('Location: /admin/orders');
        exit;
    }

    if ($requestUri === '/admin/coupons' && $requestMethod === 'GET') {
        $flash = $_SESSION['flash_success'] ?? null;
        $err = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        echo View::render('admin/coupons', [
            'pageTitle' => 'Manage Coupons',
            'coupons' => CouponService::getAll(),
            'flash' => $flash,
            'error' => $err
        ]);
        exit;
    }

    if ($requestUri === '/admin/coupons/create' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
        try {
            CouponService::create([
                'code' => $_POST['code'] ?? '',
                'type' => $_POST['type'] ?? 'percent',
                'value' => $_POST['value'] ?? 0,
                'min_order' => $_POST['min_order'] ?? 0,
                'max_uses' => $_POST['max_uses'] ?? 100,
                'starts_at' => date('Y-m-d H:i:s'),
                'ends_at' => str_replace('T', ' ', (string)($_POST['ends_at'] ?? '')) . ':00',
            ]);
            $_SESSION['flash_success'] = 'Coupon created!';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Error creating coupon: ' . $e->getMessage();
        }
        header('Location: /admin/coupons');
        exit;
    }

    if ($requestUri === '/admin/coupons/toggle' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
        CouponService::toggleActive((int)($_POST['coupon_id'] ?? 0));
        header('Location: /admin/coupons');
        exit;
    }

    if ($requestUri === '/admin/coupons/delete' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
        CouponService::delete((int)($_POST['coupon_id'] ?? 0));
        $_SESSION['flash_success'] = 'Coupon deleted.';
        header('Location: /admin/coupons');
        exit;
    }

    if ($requestUri === '/admin/zones' && $requestMethod === 'GET') {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);
        echo View::render('admin/zones', [
            'pageTitle' => 'Delivery Zones',
            'zones' => DeliveryZoneService::getAllZones(),
            'flash' => $flash
        ]);
        exit;
    }

    if ($requestUri === '/admin/zones/save' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
        DeliveryZoneService::upsertZone(
            (string)($_POST['district'] ?? ''),
            (float)($_POST['fee'] ?? 0),
            (float)($_POST['free_over'] ?? 0)
        );
        $_SESSION['flash_success'] = 'Delivery zone saved.';
        header('Location: /admin/zones');
        exit;
    }

    if ($requestUri === '/admin/zones/delete' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
        DeliveryZoneService::deleteZone((int)($_POST['zone_id'] ?? 0));
        $_SESSION['flash_success'] = 'Delivery zone deleted.';
        header('Location: /admin/zones');
        exit;
    }

    if ($requestUri === '/admin/bank-slips' && $requestMethod === 'GET') {
        $pdo = Database::getConnection();
        $slips = $pdo->query("
            SELECT bs.*, o.total_price, u.name AS user_name
            FROM bank_slip bs
            JOIN orders o ON o.order_id = bs.order_id
            JOIN user u ON u.user_id = o.user_id
            ORDER BY bs.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash_success'] ?? null;
        $err = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        echo View::render('admin/bank_slips', [
            'pageTitle' => 'Bank Slips',
            'slips' => $slips,
            'flash' => $flash,
            'error' => $err
        ]);
        exit;
    }

    if ($requestUri === '/admin/bank-slips/review' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
        $slipId = (int)($_POST['slip_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $adminId = Auth::admin()['admin_id'] ?? null;
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM bank_slip WHERE slip_id = :id LIMIT 1");
        $stmt->execute([':id' => $slipId]);
        $slip = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($slip) {
            if ($action === 'verify') {
                $payRes = CheckoutService::confirmPayment((int)$slip['order_id']);
                if ($payRes['ok']) {
                    $upd = $pdo->prepare("UPDATE bank_slip SET status = 'verified', reviewed_by = :aid WHERE slip_id = :id");
                    $upd->execute([':aid' => $adminId, ':id' => $slipId]);
                    OrderService::updateStatus((int)$slip['order_id'], 'Confirmed', 'Bank slip verified', $adminId);
                    $_SESSION['flash_success'] = "Slip #{$slipId} verified and Order #{$slip['order_id']} marked paid.";
                } else {
                    $_SESSION['flash_error'] = $payRes['error'];
                }
            } elseif ($action === 'reject') {
                $upd = $pdo->prepare("UPDATE bank_slip SET status = 'rejected', reviewed_by = :aid WHERE slip_id = :id");
                $upd->execute([':aid' => $adminId, ':id' => $slipId]);
                $_SESSION['flash_success'] = "Slip #{$slipId} rejected.";
            }
        }
        header('Location: /admin/bank-slips');
        exit;
    }

    if ($requestUri === '/admin/products' && $requestMethod === 'GET') {
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        $products = ProductRepo::findAll();
        foreach ($products as &$p) {
            $p['herbs'] = HerbRepo::getHerbsByProduct((int)$p['product_id']);
        }
        unset($p);

        echo View::render('admin/products', [
            'pageTitle' => 'Manage Products & Stock',
            'products' => $products,
            'allHerbs' => HerbRepo::findAll(),
            'flash' => $flash
        ]);
        exit;
    }

    if ($requestUri === '/admin/products/stock' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = max(0, (int)($_POST['quantity'] ?? 0));
        $res = WishlistAndAlertService::updateProductStock($pid, $qty);
        $msg = "Product #{$pid} stock updated to {$qty}.";
        if (($res['notified_count'] ?? 0) > 0) {
            $msg .= " Notified {$res['notified_count']} waiting user(s).";
        }
        $_SESSION['flash_success'] = $msg;
        header('Location: /admin/products');
        exit;
    }

    if ($requestUri === '/admin/products/update' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
        $pid = (int)($_POST['product_id'] ?? 0);
        $herbIds = isset($_POST['herb_ids']) && is_array($_POST['herb_ids']) ? array_map('intval', $_POST['herb_ids']) : [];

        ProductRepo::updateDetails($pid, [
            'product_name_si' => $_POST['product_name_si'] ?? null,
            'description_si' => $_POST['description_si'] ?? null,
            'herb_ids' => $herbIds
        ]);

        $_SESSION['flash_success'] = "Product #{$pid} details and linked herbs updated.";
        header('Location: /admin/products');
        exit;
    }
}

// ----------------------------------------------------
// STRIPE WEBHOOK
// ----------------------------------------------------
if ($requestUri === '/webhooks/stripe' && $requestMethod === 'POST') {
    $payload = @file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    $result = CheckoutService::handleWebhook($payload, $sigHeader);
    if ($result['ok']) {
        json_response(['ok' => true]);
    } else {
        json_response(['ok' => false, 'error' => $result['error'] ?? 'Webhook error'], 400);
    }
}

// ----------------------------------------------------
// 404 NOT FOUND
// ----------------------------------------------------
http_response_code(404);
if (str_starts_with($requestUri, '/api/')) {
    json_response(['ok' => false, 'error' => 'Endpoint not found'], 404);
} else {
    echo "<h1>404 Not Found</h1><p>The page requested was not found.</p>";
}
