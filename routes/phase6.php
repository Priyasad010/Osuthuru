<?php

/**
 * Phase 6 routes: recommendation API and the customer's preferred categories.
 * Included by public/index.php, which provides $requestUri and $requestMethod.
 * Every handled route ends the request.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Repository\ProductRepo;
use App\Repository\RecommendationRepo;
use App\Service\RecommendationService;

if ($requestUri === '/api/recommendations' && $requestMethod === 'GET') {
    $userId = Auth::id();
    $dosha = $_SESSION['quiz_result'] ?? null;
    // Do not hold the session lock while waiting for the service
    session_write_close();

    $result = RecommendationService::forUser($userId, $dosha, 8);
    json_response(['ok' => true, 'data' => [
        'products' => RecommendationService::toApi($result['products']),
        'source' => $result['source'],
    ]]);
}

if (preg_match('#^/api/products/(\d+)/similar$#', $requestUri, $m) && $requestMethod === 'GET') {
    session_write_close();
    $product = ProductRepo::findById((int)$m[1]);
    if (!$product) {
        json_response(['ok' => false, 'error' => t('err_product_404')], 404);
    }
    $result = RecommendationService::forProduct($product, 6);
    json_response(['ok' => true, 'data' => [
        'similar' => RecommendationService::toApi($result['similar']),
        'also_bought' => RecommendationService::toApi($result['also_bought']),
        'source' => $result['source'],
    ]]);
}

if ($requestUri === '/profile/categories') {
    Auth::requireUser();

    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            exit(e(t('err_csrf')));
        }
        RecommendationRepo::saveUserCategories(Auth::id(), is_array($_POST['categories'] ?? null) ? $_POST['categories'] : []);
        $_SESSION['flash_success'] = t('interests_saved');
        header('Location: /profile/categories');
        exit;
    }

    $flash = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);

    echo View::render('shop/categories', [
        'pageTitle' => t('interests_title'),
        'categories' => ProductRepo::getAllCategories(null, true),
        'selected' => RecommendationRepo::userCategories(Auth::id()),
        'flash' => $flash,
    ]);
    exit;
}
