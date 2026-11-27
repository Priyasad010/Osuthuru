<?php

/**
 * Phase 4 routes: dosha quiz and safety checker.
 * Included by public/index.php, which provides $requestUri and $requestMethod.
 * Every handled route ends the request.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Repository\HerbRepo;
use App\Repository\ProductRepo;
use App\Repository\QuizRepo;
use App\Repository\SafetyRepo;
use App\Service\DoshaService;
use App\Service\SafetyService;
use App\Service\CartService;

$postString = fn(string $key): string => isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
$postArray = fn(string $key): array => isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];

$redirect = function (string $path): never {
    header('Location: ' . $path);
    exit;
};

$requireCsrf = function (bool $json = false): void {
    if (Csrf::validateRequest()) {
        return;
    }
    if ($json) {
        json_response(['ok' => false, 'error' => t('err_csrf')], 403);
    }
    http_response_code(403);
    exit(e(t('err_csrf')));
};

$cartProductIds = fn(): array => CartService::productIds();

// ---------------------------------------------------------------- Dosha quiz

// Admins may preview an unpublished quiz
$quizOpen = fn(): bool => QuizRepo::isLive() || (Auth::isAdmin() && QuizRepo::problems(QuizRepo::questions()) === []);

// Score the answers and keep only the result in the session
$submitQuiz = function () use ($postArray, $quizOpen): array {
    if (!$quizOpen()) {
        return ['ok' => false, 'error' => t('quiz_err_unavailable')];
    }
    $res = DoshaService::score(QuizRepo::questions(), $postArray('answers'));
    if ($res['ok']) {
        $_SESSION['quiz_result'] = $res['result'] + ['token' => bin2hex(random_bytes(16))];
    }
    return $res;
};

$showQuiz = function (?string $error = null) use ($quizOpen, $postArray): never {
    $open = $quizOpen();
    echo View::render('quiz/quiz', [
        'pageTitle' => t('quiz_title'),
        'open' => $open,
        'preview' => $open && !QuizRepo::isLive(),
        'questions' => $open ? QuizRepo::questions() : [],
        'answers' => $postArray('answers'),
        'error' => $error,
    ]);
    exit;
};

if ($requestUri === '/quiz' && $requestMethod === 'GET') {
    $showQuiz();
}

// Form submission without JavaScript
if ($requestUri === '/quiz' && $requestMethod === 'POST') {
    $requireCsrf();
    $res = $submitQuiz();
    if (!$res['ok']) {
        $showQuiz($res['error']);
    }
    $redirect('/quiz/result');
}

if ($requestUri === '/api/quiz/submit' && $requestMethod === 'POST') {
    $requireCsrf(true);
    $res = $submitQuiz();
    if (!$res['ok']) {
        json_response(['ok' => false, 'error' => $res['error']], 422);
    }
    json_response(['ok' => true, 'data' => $res['result'] + ['redirect' => '/quiz/result']]);
}

if ($requestUri === '/quiz/result' && $requestMethod === 'GET') {
    $history = Auth::check() ? QuizRepo::history(Auth::id()) : [];
    // This session's attempt, otherwise the user's latest saved one
    $result = $_SESSION['quiz_result'] ?? null;
    $saved = false;
    if ($result !== null) {
        $saved = Auth::check() && QuizRepo::isSaved(Auth::id(), $result['token']);
    } elseif ($history) {
        $result = $history[0];
        $saved = true;
    }
    if ($result === null) {
        $redirect('/quiz');
    }

    $flash = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);

    echo View::render('quiz/result', [
        'pageTitle' => t('quiz_result_title'),
        'result' => $result,
        'saved' => $saved,
        'history' => $history,
        'profiles' => QuizRepo::profiles(),
        'products' => QuizRepo::recommendedProducts($result, 8),
        'flash' => $flash,
    ]);
    exit;
}

if ($requestUri === '/quiz/save' && $requestMethod === 'POST') {
    Auth::requireUser();
    $requireCsrf();
    if (isset($_SESSION['quiz_result'])) {
        QuizRepo::saveResult(Auth::id(), $_SESSION['quiz_result']);
        $_SESSION['flash_success'] = t('quiz_saved');
    }
    $redirect('/quiz/result');
}

// ---------------------------------------------------------------- Safety checker

if ($requestUri === '/api/safety/check' && $requestMethod === 'POST') {
    $requireCsrf(true);
    $productId = (int)$postString('product_id');
    // Without a product the visitor's or customer's own cart is checked
    $selected = SafetyService::setAnswers($postArray('flags'));
    $ids = $productId > 0 ? [$productId] : $cartProductIds();
    json_response(['ok' => true, 'data' => SafetyService::check($ids, $selected)]);
}

// Form submission without JavaScript, and from the checkout page
if ($requestUri === '/safety' && $requestMethod === 'POST') {
    $requireCsrf();
    SafetyService::setAnswers($postArray('flags'));
    $productId = (int)$postString('product_id');
    if ($productId > 0) {
        $redirect('/products/' . $productId . '#safety');
    }
    $redirect($postString('back') === 'checkout' ? '/checkout#safety' : '/cart#safety');
}

if ($requestUri === '/safety/clear' && $requestMethod === 'POST') {
    $requireCsrf();
    SafetyService::clearAnswers();
    $productId = (int)$postString('product_id');
    if ($productId > 0) {
        $redirect('/products/' . $productId);
    }
    $redirect($postString('back') === 'checkout' ? '/checkout' : '/cart');
}

// ---------------------------------------------------------------- Admin

$adminPrefixes = ['/admin/quiz', '/admin/dosha', '/admin/safety'];
$isPhase4Admin = false;
foreach ($adminPrefixes as $prefix) {
    $isPhase4Admin = $isPhase4Admin || $requestUri === $prefix || str_starts_with($requestUri, $prefix . '/');
}
if (!$isPhase4Admin) {
    return;
}

Auth::requireAdmin();

$takeFlash = function (): array {
    $messages = ['flash' => $_SESSION['flash_success'] ?? null, 'error' => $_SESSION['flash_error'] ?? null];
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    return $messages;
};

// Store the outcome of an admin action and return to its page
$done = function (array $res, string $path) use ($redirect): never {
    if ($res['ok']) {
        $_SESSION['flash_success'] = t('admin_saved');
    } else {
        $_SESSION['flash_error'] = $res['error'] ?? t('admin_not_saved');
    }
    $redirect($path);
};

if ($requestMethod === 'GET') {
    if ($requestUri === '/admin/quiz') {
        $questions = QuizRepo::questions('en');
        echo View::render('admin/quiz', $takeFlash() + [
            'pageTitle' => t('admin_quiz'),
            'questions' => $questions,
            'problems' => QuizRepo::problems($questions),
            'published' => QuizRepo::isPublished(),
            'profiles' => QuizRepo::profiles('en'),
        ]);
        exit;
    }

    if ($requestUri === '/admin/dosha') {
        echo View::render('admin/dosha', $takeFlash() + [
            'pageTitle' => t('admin_dosha'),
            'products' => ProductRepo::findAll(),
            'strengths' => QuizRepo::productStrengths(),
        ]);
        exit;
    }

    if ($requestUri === '/admin/safety') {
        echo View::render('admin/safety', $takeFlash() + [
            'pageTitle' => t('admin_safety'),
            'flags' => SafetyRepo::flags(),
            'entries' => SafetyRepo::entries(),
            'herbs' => HerbRepo::findAll(),
        ]);
        exit;
    }
}

if ($requestMethod === 'POST') {
    $requireCsrf();
    $id = (int)$postString('id');

    switch ($requestUri) {
        case '/admin/quiz/publish':
            $done(QuizRepo::setPublished($postString('published') === '1'), '/admin/quiz');
        case '/admin/quiz/question':
            $done(['ok' => QuizRepo::saveQuestion($id, $postString('text_en'), $postString('text_si'), (int)$postString('sort_order'))], '/admin/quiz');
        case '/admin/quiz/question/delete':
            QuizRepo::deleteQuestion($id);
            $done(['ok' => true], '/admin/quiz');
        case '/admin/quiz/option':
            $points = [];
            foreach (DoshaService::DOSHAS as $dosha) {
                $points[$dosha] = $postString($dosha);
            }
            $done(['ok' => QuizRepo::saveOption($id, (int)$postString('question_id'), $postString('text_en'), $postString('text_si'), $points)], '/admin/quiz');
        case '/admin/quiz/option/delete':
            QuizRepo::deleteOption($id);
            $done(['ok' => true], '/admin/quiz');
        case '/admin/quiz/profile':
            $fields = [];
            foreach (['description_en', 'description_si', 'tips_en', 'tips_si'] as $field) {
                $fields[$field] = $postString($field);
            }
            $done(['ok' => QuizRepo::saveProfile($postString('dosha'), $fields)], '/admin/quiz');
        case '/admin/dosha/save':
            $done(['ok' => QuizRepo::saveProductStrengths((int)$postString('product_id'), $postArray('strength'))], '/admin/dosha');
        case '/admin/safety/flag':
            $done(SafetyRepo::saveFlag($id, $postString('code'), $postString('name_en'), $postString('name_si')), '/admin/safety');
        case '/admin/safety/flag/delete':
            SafetyRepo::deleteFlag($id);
            $done(['ok' => true], '/admin/safety');
        case '/admin/safety/entry':
            $done(SafetyRepo::saveEntry($id, [
                'herb_id' => $postString('herb_id'),
                'flag_id' => $postString('flag_id'),
                'severity' => $postString('severity'),
                'note_en' => $postString('note_en'),
                'note_si' => $postString('note_si'),
                'source_note' => $postString('source_note'),
                'published' => $postString('published') === '1',
            ]), '/admin/safety');
        case '/admin/safety/entry/delete':
            SafetyRepo::deleteEntry($id);
            $done(['ok' => true], '/admin/safety');
    }
}
