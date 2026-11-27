<?php

/**
 * Phase 7 routes: reviews, blog and seasonal picks.
 * Included by public/index.php, which provides $requestUri and $requestMethod.
 * Every handled route ends the request.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Upload;
use App\Core\View;
use App\Repository\BlogRepo;
use App\Repository\HerbRepo;
use App\Repository\ProductRepo;
use App\Repository\SeasonalRepo;
use App\Service\ReviewService;

$field = fn(string $key): string => isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
$redirectTo = function (string $path): never {
    header('Location: ' . $path);
    exit;
};
$requireToken = function (): void {
    if (!Csrf::validateRequest()) {
        http_response_code(403);
        exit(e(t('err_csrf')));
    }
};
$report = function (array $result, string $path, string $success) use ($redirectTo): never {
    $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['ok'] ? $success : $result['error'];
    $redirectTo($path);
};
$takeNotices = function (): array {
    $out = ['flash' => $_SESSION['flash_success'] ?? null, 'error' => $_SESSION['flash_error'] ?? null];
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    return $out;
};

// ---------------------------------------------------------------- Reviews
if (preg_match('#^/products/(\d+)/reviews$#', $requestUri, $m) && $requestMethod === 'POST') {
    Auth::requireUser();
    $requireToken();
    $productId = (int)$m[1];
    $result = ReviewService::add(Auth::id(), $productId, $_POST['rating'] ?? 0, $_POST['comment'] ?? '', ReviewService::files($_FILES['photos'] ?? []));
    $report($result, "/products/{$productId}#reviews", t('review_thanks'));
}

if (preg_match('#^/reviews/(\d+)/helpful$#', $requestUri, $m) && $requestMethod === 'POST') {
    Auth::requireUser();
    $requireToken();
    $productId = ReviewService::toggleHelpful((int)$m[1], Auth::id());
    $redirectTo($productId ? "/products/{$productId}#reviews" : '/products');
}

// ---------------------------------------------------------------- Blog
if (($requestUri === '/blogs' || $requestUri === '/blog') && $requestMethod === 'GET') {
    $tag = isset($_GET['tag']) && is_numeric($_GET['tag']) ? (int)$_GET['tag'] : null;
    echo View::render('blog/list', [
        'pageTitle' => t('nav_blogs'),
        'posts' => BlogRepo::all($tag),
        'tags' => BlogRepo::tags(),
        'selectedTag' => $tag,
    ]);
    exit;
}

if (preg_match('#^/blog/(\d+)$#', $requestUri, $m) && $requestMethod === 'GET') {
    $post = BlogRepo::find((int)$m[1]);
    if (!$post) {
        http_response_code(404);
    }
    echo View::render('blog/post', ['pageTitle' => $post['display_title'] ?? t('blog_err_missing'), 'post' => $post]);
    exit;
}

// ---------------------------------------------------------------- Admin
if (!preg_match('#^/admin/(blogs|seasonal|reviews)(?:/|$)#', $requestUri)) {
    return;
}
Auth::requireAdmin();

if ($requestMethod === 'GET') {
    if ($requestUri === '/admin/blogs') {
        echo View::render('admin/blogs', $takeNotices() + [
            'pageTitle' => t('admin_blogs'),
            'posts' => array_map(fn($p) => BlogRepo::find((int)$p['blog_id'], 'en'), BlogRepo::all(null, 100, 'en')),
            'tags' => BlogRepo::tags(),
            'products' => ProductRepo::findAll(),
        ]);
        exit;
    }
    if ($requestUri === '/admin/seasonal') {
        echo View::render('admin/seasonal', $takeNotices() + [
            'pageTitle' => t('admin_seasonal'),
            'collections' => SeasonalRepo::all(),
            'concerns' => HerbRepo::getAllConcerns(),
        ]);
        exit;
    }
    if ($requestUri === '/admin/reviews') {
        echo View::render('admin/reviews', $takeNotices() + ['pageTitle' => t('admin_reviews'), 'reviews' => ReviewService::latest()]);
        exit;
    }
}

if ($requestMethod === 'POST') {
    $requireToken();
    $id = (int)$field('id');
    $saved = t('admin_saved');

    switch ($requestUri) {
        case '/admin/blogs/save':
            $data = $_POST;
            unset($data['image']);
            $file = $_FILES['image_file'] ?? null;
            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $upload = Upload::process($file, 'blog');
                if (!$upload['ok']) {
                    $report($upload, '/admin/blogs', $saved);
                }
                $data['image'] = $upload['path'];
            }
            $report(BlogRepo::save($id, $data), '/admin/blogs', $saved);
        case '/admin/blogs/delete':
            BlogRepo::delete($id);
            $report(['ok' => true], '/admin/blogs', $saved);
        case '/admin/blogs/tag':
            $report(BlogRepo::addTag($field('name_en'), $field('name_si')), '/admin/blogs', $saved);
        case '/admin/blogs/tag/delete':
            BlogRepo::deleteTag($id);
            $report(['ok' => true], '/admin/blogs', $saved);
        case '/admin/seasonal/save':
            $report(SeasonalRepo::save($id, $_POST), '/admin/seasonal', $saved);
        case '/admin/seasonal/delete':
            SeasonalRepo::delete($id);
            $report(['ok' => true], '/admin/seasonal', $saved);
        case '/admin/reviews/delete':
            ReviewService::delete($id);
            $report(['ok' => true], '/admin/reviews', $saved);
    }
}
