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
use App\Core\SessionGuard;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Validator;
use App\Core\FormRules;
use App\Core\Mailer;
use App\Core\Upload;
use App\Core\Request;
use App\Service\RegistrationService;
use App\Repository\ProductRepo;
use App\Repository\HerbRepo;
use App\Repository\QuizRepo;
use App\Repository\BlogRepo;
use App\Repository\SeasonalRepo;
use App\Service\RecommendationService;
use App\Repository\BatchRepo;
use App\Service\SafetyService;
use App\Service\SearchService;
use App\Service\PasswordResetService;
use App\Service\CheckoutService;
use App\Service\PayHereService;
use App\Service\CartService;
use App\Service\ProductListing;
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

// Basic browser protections for every page
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// 4. Session & Language
Auth::initSession();
Lang::init();

$clientIp = Request::clientIp($_SERVER);
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// 5. Is the signed-in session still good?
if ($ended = SessionGuard::enforce((string)$requestUri)) {
    $_SESSION['auth_notice'] = $ended;
}
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// PHP discards the whole form when an upload exceeds post_max_size
if ($requestMethod === 'POST' && $_POST === [] && $_FILES === []
    && str_starts_with((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data')
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1048576) {
    http_response_code(413);
    echo View::render('upload_too_large', ['pageTitle' => t('err_upload_size')]);
    exit;
}

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
    $sessionDosha = $_SESSION['quiz_result'] ?? null;
    $hasDosha = $sessionDosha || (Auth::check() && QuizRepo::history(Auth::id(), 1));

    echo View::render('home', [
        'season' => SeasonalRepo::current(),
        'latestPosts' => BlogRepo::all(null, 3),
        'recommended' => RecommendationService::forUser(Auth::id(), $sessionDosha, 4)['products'],
        'hasDosha' => (bool)$hasDosha,
        'quizLive' => QuizRepo::isLive(),
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
        if (Auth::check()) {
            RegistrationService::saveLanguage(Auth::id(), $lang);
        }
    }
    $back = back_path('/');
    header('Location: ' . (str_starts_with($back, '/lang') ? '/' : $back));
    exit;
}

// ----------------------------------------------------
// DOSHA QUIZ & SAFETY CHECKER (PHASE 4)
// ----------------------------------------------------
require $rootDir . '/routes/phase4.php';

// QR VERIFICATION & ASSISTANT (PHASE 5)
require $rootDir . '/routes/phase5.php';

// RECOMMENDATIONS & PREFERENCES (PHASE 6)
require $rootDir . '/routes/phase6.php';

// ADMIN ANALYTICS & CATALOGUE (PHASE 7)
require $rootDir . '/routes/content.php';
require $rootDir . '/routes/pages.php';
require $rootDir . '/routes/admin.php';

// ----------------------------------------------------
// SEARCH & AUTOCOMPLETE (PHASE 3)
// ----------------------------------------------------
if ($requestUri === '/search' && $requestMethod === 'GET') {
    $q = mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100);
    $results = SearchService::search($q);
    echo View::render('shop/search', [
        'pageTitle' => t('search_results_title', ['query' => $q]),
        'query' => $q,
        'results' => $results
    ]);
    exit;
}

if ($requestUri === '/api/search/suggest' && $requestMethod === 'GET') {
    $q = mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100);
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
            http_response_code(403);
            echo View::render('auth/login', ['error' => t('err_csrf')]);
            exit;
        }

        $email = (string)($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (!FormRules::isEmail($email) || $password === '') {
            echo View::render('auth/login', ['error' => t('err_login_fields'), 'email' => $email]);
            exit;
        }

        $result = Auth::attemptUser($email, $password, $clientIp);
        if ($result['ok']) {
            RegistrationService::saveLanguage(Auth::id(), Lang::getLocale());
            CartService::mergeGuestCart();
            // Only an address this application stored itself is followed
            $next = ($_SESSION['after_login'] ?? '') === '/checkout' ? '/checkout' : '/';
            unset($_SESSION['after_login'], $_SESSION['flash_success']);
            header('Location: ' . $next);
            exit;
        }

        echo View::render('auth/login', [
            'error' => $result['error'],
            'email' => $email
        ]);
        exit;
    }

    // For example: "sign in to finish your order"
    $success = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);
    $error = isset($_SESSION['auth_notice']) ? t('session_' . $_SESSION['auth_notice']) : null;
    unset($_SESSION['auth_notice']);
    if (isset($_GET['registered'])) {
        if ($_GET['registered'] === 'mailfail') {
            $error = t('msg_registered_mail_failed');
        } else {
            $success = t('msg_registered');
        }
    } elseif (isset($_GET['deleted'])) {
        $success = t('account_deleted');
    } elseif (isset($_GET['verified'])) {
        if ($_GET['verified'] === '1') {
            $success = t('msg_verified');
        } else {
            $error = t('err_verify_invalid');
        }
    }

    echo View::render('auth/login', [
        'pageTitle' => t('nav_login'),
        'success' => $success,
        'error' => $error
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
            http_response_code(403);
            echo View::render('auth/register', ['error' => t('err_csrf')]);
            exit;
        }

        $errors = FormRules::registration($_POST);
        if ($errors === [] && Auth::isLockedOut('register', $clientIp)) {
            $errors['email'] = t('err_too_many');
        }
        if ($errors !== []) {
            FormRules::$pageErrors = $errors;
            echo View::render('auth/register', ['error' => implode(' ', $errors), 'old' => $_POST]);
            exit;
        }
        // At most a few new accounts (and verification e-mails) from one address at a time
        Auth::recordAttempt('register', $clientIp, false);

        $res = RegistrationService::register(
            (string)$_POST['name'],
            (string)$_POST['email'],
            (string)$_POST['password'],
            (string)(FormRules::phone((string)($_POST['phone'] ?? '')) ?? '')
        );

        if (!$res['ok']) {
            echo View::render('auth/register', ['error' => $res['error'], 'old' => $_POST]);
            exit;
        }

        header('Location: /login?registered=' . ($res['mail_sent'] ? '1' : 'mailfail'));
        exit;
    }

    echo View::render('auth/register', ['pageTitle' => t('nav_register')]);
    exit;
}

if ($requestUri === '/verify') {
    $verified = RegistrationService::verify((string)($_GET['token'] ?? ''));
    header('Location: /login?verified=' . ($verified ? '1' : '0'));
    exit;
}

if ($requestUri === '/forgot-password') {
    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            echo View::render('auth/forgot_password', ['error' => t('err_csrf')]);
            exit;
        }

        $email = (string)($_POST['email'] ?? '');
        if (!FormRules::isEmail($email)) {
            echo View::render('auth/forgot_password', ['error' => t('val_email')]);
            exit;
        }
        // Reset e-mails cannot be sent without limit
        if (Auth::isLockedOut('password-reset', $clientIp)) {
            echo View::render('auth/forgot_password', ['error' => t('err_too_many')]);
            exit;
        }
        Auth::recordAttempt('password-reset', $clientIp, false);
        $res = PasswordResetService::requestReset($email);

        echo View::render('auth/forgot_password', [
            'message' => $res['message'] ?? null,
            'error' => $res['error'] ?? null
        ]);
        exit;
    }

    echo View::render('auth/forgot_password', ['pageTitle' => t('forgot_title')]);
    exit;
}

if ($requestUri === '/reset-password') {
    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            echo View::render('auth/reset_password', ['error' => t('err_csrf'), 'token' => '']);
            exit;
        }

        $token = (string)($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirmation'] ?? '');

        if ($problem = FormRules::passwordProblem($password)) {
            echo View::render('auth/reset_password', ['error' => $problem, 'token' => $token]);
            exit;
        }

        if ($password !== $confirm) {
            echo View::render('auth/reset_password', ['error' => t('err_password_mismatch'), 'token' => $token]);
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
    $listing = ProductListing::browse($_GET);
    $catId = $listing['category'];
    $products = $listing['products'];
    $categories = ProductRepo::getAllCategories(null, true);
    $wishlistIds = Auth::check() ? WishlistAndAlertService::getUserWishlistIds(Auth::id()) : [];

    $flash = $_SESSION['flash_success'] ?? null;
    $problem = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);

    echo View::render('shop/products', [
        'error' => $problem,
        'pageTitle' => t('nav_products'),
        'products' => $products,
        'listing' => $listing,
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
        echo View::render('not_found', ['pageTitle' => t('err_product_404'), 'message' => t('err_product_404')]);
        exit;
    }
    $wishlistIds = Auth::check() ? WishlistAndAlertService::getUserWishlistIds(Auth::id()) : [];
    $flash = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);

    $flashError = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);

    echo View::render('shop/product_detail', [
        'error' => $flashError,
        'related' => RecommendationService::forProduct($product, 4),
        'flash' => $flash,
        'pageTitle' => $product['display_name'] ?? $product['product_name'],
        'product' => $product,
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
        echo View::render('not_found', ['pageTitle' => t('err_herb_404'), 'message' => t('err_herb_404')]);
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
        echo View::render('not_found', ['pageTitle' => t('err_concern_404'), 'message' => t('err_concern_404')]);
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
    $cart = CartService::contents();
    echo View::render('shop/cart', [
        'pageTitle' => t('nav_cart'),
        'cartItems' => $cart['items'],
        'subtotal' => $cart['subtotal'],
        'zones' => DeliveryZoneService::getAllZones()
    ]);
    exit;
}

if (in_array($requestUri, ['/cart/add', '/cart/update', '/cart/remove'], true) && $requestMethod === 'POST') {
    if (!Csrf::validateRequest()) {
        http_response_code(403);
        exit(e(t('err_csrf')));
    }
    $pid = (int)($_POST['product_id'] ?? 0);
    $quantity = is_string($_POST['quantity'] ?? null) ? $_POST['quantity'] : ($requestUri === '/cart/add' ? '1' : '');

    if ($requestUri === '/cart/remove') {
        CartService::remove($pid);
    } elseif ($requestUri === '/cart/update') {
        if ($problem = CartService::update($pid, $quantity)) {
            $_SESSION['flash_error'] = $problem;
        }
    } else {
        if ($problem = CartService::add($pid, $quantity)) {
            $_SESSION['flash_error'] = $problem;
        } else {
            $_SESSION['flash_success'] = t('flash_cart_added');
        }
        header('Location: ' . back_path('/cart'));
        exit;
    }
    header('Location: /cart');
    exit;
}

if ($requestUri === '/checkout') {
    // A visitor signs in first; the cart is kept and checkout follows
    if (!Auth::check() && CartService::quantities() !== []) {
        $_SESSION['after_login'] = '/checkout';
        $_SESSION['flash_success'] = t('login_to_checkout');
        header('Location: /login');
        exit;
    }
    Auth::requireUser();
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("
        SELECT c.product_id, c.quantity, p.product_name, p.product_name_si, p.price, p.quantity AS stock,
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
        $enriched = ProductRepo::enrichWithSalePrice(ProductRepo::enrichLocale($row));
        $lineTotal = round((float)$enriched['effective_price'] * (int)$row['quantity'], 2);
        $enriched['line_total'] = $lineTotal;
        $subtotal += $lineTotal;
        $cartItems[] = $enriched;
    }

    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            echo View::render('shop/checkout', [
                'error' => t('err_csrf'),
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
                'zones' => DeliveryZoneService::getAllZones(),
                'old' => $_POST
            ]);
            exit;
        }

        $paymentMethod = (string)($_POST['payment_method'] ?? 'cod');
        // Refuse before an order exists, so no half-made order is left behind
        if (!in_array($paymentMethod, CheckoutService::availableMethods(), true)) {
            echo View::render('shop/checkout', [
                'error' => t('err_payment_unavailable'),
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
                'zones' => DeliveryZoneService::getAllZones(),
                'old' => $_POST
            ]);
            exit;
        }

        // An "avoid" safety match must be acknowledged before paying (nothing is stored)
        if (SafetyService::needsAcknowledgement(array_column($cartItems, 'product_id'), !empty($_POST['safety_ack']))) {
            echo View::render('shop/checkout', [
                'error' => t('safety_ack_required'),
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
                'zones' => DeliveryZoneService::getAllZones(),
                'old' => $_POST
            ]);
            exit;
        }

        $shippingErrors = FormRules::shipping($_POST);
        if ($shippingErrors !== []) {
            FormRules::$pageErrors = $shippingErrors;
            echo View::render('shop/checkout', [
                'error' => implode(' ', $shippingErrors),
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
                'zones' => DeliveryZoneService::getAllZones(),
                'old' => $_POST
            ]);
            exit;
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
                'shipping_phone' => FormRules::phone((string)($_POST['shipping_phone'] ?? '')),
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
        'pageTitle' => t('checkout_title'),
        'cartItems' => $cartItems,
        'subtotal' => $subtotal,
        'zones' => DeliveryZoneService::getAllZones()
    ]);
    exit;
}

if ($requestUri === '/orders' && $requestMethod === 'GET') {
    Auth::requireUser();
    echo View::render('shop/orders', [
        'pageTitle' => t('my_orders'),
        'orders' => OrderService::getUserOrders(Auth::id())
    ]);
    exit;
}

if (preg_match('#^/orders/(\d+)$#', $requestUri, $m) && $requestMethod === 'GET') {
    Auth::requireUser();
    $order = OrderService::getOrderDetails((int)$m[1]);
    if (!$order || (int)$order['user_id'] !== Auth::id()) {
        http_response_code(404);
        echo View::render('not_found', ['pageTitle' => t('err_order_404'), 'message' => t('err_order_404')]);
        exit;
    }

    $flash = $_SESSION['flash_success'] ?? null;
    $err = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);

    echo View::render('shop/order_detail', [
        'pageTitle' => t('order_number', ['id' => $order['order_id']]),
        'order' => $order,
        'flash' => $flash,
        'error' => $err
    ]);
    exit;
}

// Hands the customer over to PayHere to pay for one of their own orders
if (preg_match('#^/orders/(\d+)/pay$#', $requestUri, $m) && $requestMethod === 'GET') {
    Auth::requireUser();
    $order = OrderService::getOrderDetails((int)$m[1]);
    if (!$order || (int)$order['user_id'] !== Auth::id()) {
        http_response_code(404);
        exit(e(t('err_order_404')));
    }
    if (!PayHereService::isConfigured() || !PayHereService::canPay($order)) {
        header('Location: /orders/' . (int)$m[1]);
        exit;
    }
    header('Cache-Control: no-store');
    echo View::render('shop/payhere', [
        'pageTitle' => t('payhere_title'),
        'order' => $order,
        'action' => PayHereService::checkoutUrl(),
        'sandbox' => PayHereService::isSandbox(),
        'fields' => PayHereService::checkoutFields($order, (string)(Auth::user()['email'] ?? '')),
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

    if (Lang::getLocale() === 'si') {
        echo InvoiceService::generateHtml($order, true);
        exit;
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
        http_response_code(403);
        exit(e(t('err_csrf')));
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
        http_response_code(403);
        exit(e(t('err_csrf')));
    }
    $orderId = (int)$m[1];
    $order = OrderService::getOrderDetails($orderId);
    if (!$order || (int)$order['user_id'] !== Auth::id()) {
        http_response_code(404);
        exit('Order not found');
    }

    if ($order['payment_method'] !== 'bank_transfer' || $order['payment_status'] !== 'unpaid' || $order['status'] === 'Cancelled') {
        $_SESSION['flash_error'] = t('err_slip_not_allowed');
        header('Location: /orders/' . $orderId);
        exit;
    }

    $uploadRes = Upload::process($_FILES['bank_slip'] ?? [], 'slips');
    if (!$uploadRes['ok']) {
        $_SESSION['flash_error'] = $uploadRes['error'];
    } else {
        $pdo = Database::getConnection();
        $ins = $pdo->prepare("INSERT INTO bank_slip (order_id, file_path, status, created_at) VALUES (:oid, :path, 'submitted', NOW())");
        $ins->execute([':oid' => $orderId, ':path' => $uploadRes['path']]);
        $_SESSION['flash_success'] = t('flash_slip_uploaded');
    }
    header('Location: /orders/' . $orderId);
    exit;
}

if ($requestUri === '/wishlist' && $requestMethod === 'GET') {
    Auth::requireUser();
    echo View::render('shop/wishlist', [
        'pageTitle' => t('my_wishlist'),
        'products' => WishlistAndAlertService::getUserWishlistProducts(Auth::id())
    ]);
    exit;
}

if ($requestUri === '/wishlist/toggle' && $requestMethod === 'POST') {
    Auth::requireUser();
    if (!Csrf::validateRequest()) {
        http_response_code(403);
        exit(e(t('err_csrf')));
    }
    $pid = (int)($_POST['product_id'] ?? 0);
    if (!ProductRepo::findById($pid)) {
        $_SESSION['flash_error'] = t('err_product_404');
        header('Location: /products');
        exit;
    }
    $added = WishlistAndAlertService::toggleWishlist(Auth::id(), $pid);
    $_SESSION['flash_success'] = $added ? t('flash_wishlist_added') : t('flash_wishlist_removed');
    header('Location: ' . back_path('/products'));
    exit;
}

if ($requestUri === '/stock-alert' && $requestMethod === 'POST') {
    Auth::requireUser();
    if (!Csrf::validateRequest()) {
        http_response_code(403);
        exit(e(t('err_csrf')));
    }
    $pid = (int)($_POST['product_id'] ?? 0);
    if (!ProductRepo::findById($pid)) {
        $_SESSION['flash_error'] = t('err_product_404');
        header('Location: /products');
        exit;
    }
    WishlistAndAlertService::subscribeStockAlert(Auth::id(), $pid);
    $_SESSION['flash_success'] = t('flash_stock_alert');
    header('Location: ' . back_path('/products'));
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
            http_response_code(403);
            echo View::render('auth/admin_login', ['error' => t('err_csrf')]);
            exit;
        }

        $email = (string)($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (!FormRules::isEmail($email) || $password === '') {
            echo View::render('auth/admin_login', ['error' => t('err_login_fields'), 'email' => $email]);
            exit;
        }

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

    $notice = isset($_SESSION['auth_notice']) ? t('session_' . $_SESSION['auth_notice']) : null;
    unset($_SESSION['auth_notice']);
    echo View::render('auth/admin_login', ['pageTitle' => t('admin_login'), 'error' => $notice]);
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

    if ($requestUri === '/admin/orders/status' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            exit(e(t('err_csrf')));
        }
        $orderId = (int)($_POST['order_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255);
        $adminId = Auth::admin()['admin_id'] ?? null;

        $res = OrderService::updateStatus($orderId, $status, $note !== '' ? $note : null, $adminId);
        if (!$res['ok']) {
            $_SESSION['flash_error'] = $res['error'];
        } else {
            $_SESSION['flash_success'] = t('admin_order_updated', ['id' => $orderId, 'status' => t_or('status_', $status)]);
            if (!empty($res['refund_needed'])) {
                $_SESSION['flash_success'] .= ' ' . t('admin_refund_needed');
            }
        }
        header('Location: /admin/orders');
        exit;
    }

    if ($requestUri === '/admin/coupons' && $requestMethod === 'GET') {
        $flash = $_SESSION['flash_success'] ?? null;
        $err = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        echo View::render('admin/coupons', [
            'pageTitle' => t('admin_coupons'),
            'coupons' => CouponService::getAll(),
            'flash' => $flash,
            'error' => $err
        ]);
        exit;
    }

    if ($requestUri === '/admin/coupons/create' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            exit(e(t('err_csrf')));
        }
        $couponErrors = FormRules::coupon($_POST);
        if ($couponErrors !== []) {
            $_SESSION['flash_error'] = implode(' ', $couponErrors);
            header('Location: /admin/coupons');
            exit;
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
            $_SESSION['flash_success'] = t('admin_coupon_created');
        } catch (Throwable $e) {
            error_log('Coupon creation failed: ' . $e->getMessage());
            $_SESSION['flash_error'] = t('admin_coupon_error');
        }
        header('Location: /admin/coupons');
        exit;
    }

    if ($requestUri === '/admin/coupons/toggle' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            exit(e(t('err_csrf')));
        }
        CouponService::toggleActive((int)($_POST['coupon_id'] ?? 0));
        header('Location: /admin/coupons');
        exit;
    }

    if ($requestUri === '/admin/coupons/delete' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            exit(e(t('err_csrf')));
        }
        CouponService::delete((int)($_POST['coupon_id'] ?? 0));
        $_SESSION['flash_success'] = t('admin_coupon_deleted');
        header('Location: /admin/coupons');
        exit;
    }

    if ($requestUri === '/admin/zones' && $requestMethod === 'GET') {
        $flash = $_SESSION['flash_success'] ?? null;
        $zoneError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        echo View::render('admin/zones', [
            'error' => $zoneError,
            'pageTitle' => t('admin_zones'),
            'zones' => DeliveryZoneService::getAllZones(),
            'flash' => $flash
        ]);
        exit;
    }

    if ($requestUri === '/admin/zones/save' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            exit(e(t('err_csrf')));
        }
        $zoneErrors = FormRules::deliveryZone($_POST);
        if ($zoneErrors !== []) {
            $_SESSION['flash_error'] = implode(' ', $zoneErrors);
            header('Location: /admin/zones');
            exit;
        }
        DeliveryZoneService::upsertZone(
            (string)($_POST['district'] ?? ''),
            (float)($_POST['fee'] ?? 0),
            (float)($_POST['free_over'] ?? 0)
        );
        $_SESSION['flash_success'] = t('admin_zone_saved');
        header('Location: /admin/zones');
        exit;
    }

    if ($requestUri === '/admin/zones/delete' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            exit(e(t('err_csrf')));
        }
        DeliveryZoneService::deleteZone((int)($_POST['zone_id'] ?? 0));
        $_SESSION['flash_success'] = t('admin_zone_deleted');
        header('Location: /admin/zones');
        exit;
    }

    if ($requestUri === '/admin/bank-slips/file' && $requestMethod === 'GET') {
        Auth::requireAdmin();
        $find = Database::getConnection()->prepare('SELECT file_path FROM bank_slip WHERE slip_id = :id');
        $find->execute([':id' => (int)($_GET['id'] ?? 0)]);
        $file = Upload::privateFile((string)$find->fetchColumn(), 'slips');
        if ($file === null) {
            http_response_code(404);
            exit(e(t('err_file_404')));
        }
        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . filesize($file['path']));
        header('Content-Disposition: inline; filename="' . basename($file['path']) . '"');
        header('Cache-Control: private, no-store');
        readfile($file['path']);
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
            'pageTitle' => t('admin_slips'),
            'slips' => $slips,
            'flash' => $flash,
            'error' => $err
        ]);
        exit;
    }

    if ($requestUri === '/admin/bank-slips/review' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            exit(e(t('err_csrf')));
        }
        $slipId = (int)($_POST['slip_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $adminId = Auth::admin()['admin_id'] ?? null;
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM bank_slip WHERE slip_id = :id LIMIT 1");
        $stmt->execute([':id' => $slipId]);
        $slip = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($slip) {
            $slipOrder = OrderService::getOrderDetails((int)$slip['order_id']);
            if ($action === 'verify' && ($slipOrder['status'] ?? '') === 'Cancelled') {
                $_SESSION['flash_error'] = t('admin_slip_cancelled', ['id' => $slip['order_id']]);
            } elseif ($action === 'verify') {
                $payRes = CheckoutService::confirmPayment((int)$slip['order_id']);
                if ($payRes['ok']) {
                    $upd = $pdo->prepare("UPDATE bank_slip SET status = 'verified', reviewed_by = :aid WHERE slip_id = :id");
                    $upd->execute([':aid' => $adminId, ':id' => $slipId]);
                    OrderService::updateStatus((int)$slip['order_id'], 'Confirmed', 'Bank slip verified', $adminId);
                    $_SESSION['flash_success'] = t('admin_slip_verified', ['slip' => $slipId, 'order' => $slip['order_id']]);
                } else {
                    $_SESSION['flash_error'] = $payRes['error'];
                }
            } elseif ($action === 'reject') {
                $upd = $pdo->prepare("UPDATE bank_slip SET status = 'rejected', reviewed_by = :aid WHERE slip_id = :id");
                $upd->execute([':aid' => $adminId, ':id' => $slipId]);
                $_SESSION['flash_success'] = t('admin_slip_rejected', ['slip' => $slipId]);
            }
        }
        header('Location: /admin/bank-slips');
        exit;
    }

    if ($requestUri === '/admin/products/stock' && $requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            exit(e(t('err_csrf')));
        }
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = FormRules::whole((string)($_POST['quantity'] ?? ''), 0, 100000);
        if ($qty === null || !ProductRepo::findById($pid)) {
            $_SESSION['flash_error'] = t('err_whole', ['min' => 0, 'max' => 100000]);
            header('Location: /admin/products');
            exit;
        }
        $res = WishlistAndAlertService::updateProductStock($pid, $qty);
        $msg = t('admin_stock_updated', ['id' => $pid, 'qty' => $qty]);
        if (($res['notified_count'] ?? 0) > 0) {
            $msg .= ' ' . t('admin_notified', ['count' => $res['notified_count']]);
        }
        $_SESSION['flash_success'] = $msg;
        header('Location: /admin/products');
        exit;
    }

}

// ----------------------------------------------------
// STRIPE WEBHOOK
// ----------------------------------------------------
if ($requestUri === '/payments/payhere/notify' && $requestMethod === 'POST') {
    $result = PayHereService::handleNotification($_POST);
    if (!$result['ok']) {
        error_log('PayHere notification refused: ' . $result['error']);
        json_response(['ok' => false, 'error' => $result['error']], 400);
    }
    json_response(['ok' => true]);
}

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
    echo View::render('not_found', ['pageTitle' => t('not_found_title')]);
}
