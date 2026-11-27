<?php

/**
 * Phase 5 routes: QR batch traceability and the shop assistant.
 * Included by public/index.php, which provides $requestUri, $requestMethod and $clientIp.
 * Every handled route ends the request.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Upload;
use App\Core\View;
use App\Llm\GeminiClient;
use App\Repository\AssistantRepo;
use App\Repository\BatchRepo;
use App\Repository\HerbRepo;
use App\Repository\ProductRepo;
use App\Service\AssistantService;
use App\Service\BatchService;

$postText = fn(string $key): string => isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
$goTo = function (string $path): never {
    header('Location: ' . $path);
    exit;
};

// ---------------------------------------------------------------- Public verification
// "/verify?t=..." is a pack QR code; "/verify?token=..." (email address) is handled elsewhere.
if ($requestUri === '/verify' && $requestMethod === 'GET' && isset($_GET['t'])) {
    $token = is_string($_GET['t']) ? $_GET['t'] : '';
    $batch = BatchRepo::findByToken($token);

    if ($batch) {
        BatchService::recordScan((int)$batch['batch_id'], $clientIp, (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $batch = BatchRepo::findByToken($token);
    } else {
        http_response_code(404);
    }

    // Scan results must always be current
    header('Cache-Control: no-store');
    echo View::render('shop/verify', [
        'pageTitle' => t('verify_title'),
        'batch' => $batch ? ProductRepo::enrichLocale($batch) : null,
        'status' => $batch ? BatchService::status($batch) : null,
        'duplicated' => $batch && BatchService::looksDuplicated($batch),
    ]);
    exit;
}

// ---------------------------------------------------------------- Assistant API
if ($requestUri === '/api/assistant' && $requestMethod === 'POST') {
    if (!Csrf::validateRequest()) {
        json_response(['ok' => false, 'error' => t('err_csrf')], 403);
    }

    $ipHash = Auth::hashIp($clientIp);
    $chatId = AssistantRepo::ensureSession($_SESSION['chat_session_id'] ?? null, Auth::id(), $ipHash);
    $_SESSION['chat_session_id'] = $chatId;
    // Do not hold the session lock while waiting for the model
    session_write_close();

    $res = AssistantService::ask($postText('message'), $chatId, $ipHash, GeminiClient::fromEnv());
    if (!$res['ok']) {
        json_response(['ok' => false, 'error' => $res['error']], $res['status']);
    }
    json_response(['ok' => true, 'data' => $res['data']]);
}

// ---------------------------------------------------------------- Admin
$isPhase5Admin = false;
foreach (['/admin/batches', '/admin/assistant'] as $prefix) {
    $isPhase5Admin = $isPhase5Admin || $requestUri === $prefix || str_starts_with($requestUri, $prefix . '/');
}
if (!$isPhase5Admin) {
    return;
}

Auth::requireAdmin();

$messages = function (): array {
    $out = ['flash' => $_SESSION['flash_success'] ?? null, 'error' => $_SESSION['flash_error'] ?? null];
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    return $out;
};

if ($requestMethod === 'GET') {
    if ($requestUri === '/admin/batches') {
        $host = (string)parse_url((string)Env::get('APP_URL', 'http://localhost:8080'), PHP_URL_HOST);
        echo View::render('admin/batches', $messages() + [
            'pageTitle' => t('admin_batches'),
            'batches' => BatchRepo::all(),
            'expiring' => BatchRepo::expiringWithin(30),
            'products' => ProductRepo::findAll(),
            'localOnly' => in_array($host, ['localhost', '127.0.0.1'], true),
            'appUrl' => url(),
        ]);
        exit;
    }

    if ($requestUri === '/admin/batches/labels') {
        $batch = BatchRepo::find((int)($_GET['id'] ?? 0));
        if (!$batch) {
            http_response_code(404);
            exit(e(t('batch_err_required')));
        }
        $pdf = BatchService::labelPdf($batch, (int)($_GET['count'] ?? 24));
        header('Content-Type: application/pdf');
        // Shown in the browser: browsers block downloads from plain-HTTP network addresses
        header('Content-Disposition: inline; filename="labels-' . (int)$batch['batch_id'] . '.pdf"');
        echo $pdf;
        exit;
    }

    if ($requestUri === '/admin/assistant') {
        AssistantRepo::purge();
        echo View::render('admin/assistant', $messages() + [
            'pageTitle' => t('admin_assistant'),
            'questions' => AssistantRepo::weakQuestions(),
            'concerns' => HerbRepo::getAllConcerns(),
            'keywords' => AssistantRepo::keywords(),
            'configured' => GeminiClient::fromEnv() !== null,
        ]);
        exit;
    }
}

if ($requestMethod === 'POST') {
    if (!Csrf::validateRequest()) {
        http_response_code(403);
        exit(e(t('err_csrf')));
    }
    $id = (int)$postText('id');

    if ($requestUri === '/admin/batches/save') {
        $data = [];
        foreach (['product_id', 'batch_code', 'manufactured_on', 'expires_on', 'source_location', 'supplier_name', 'notes', 'units'] as $field) {
            $data[$field] = $postText($field);
        }

        $file = $_FILES['lab_report'] ?? null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = Upload::processPdf($file, 'reports');
            if (!$upload['ok']) {
                $_SESSION['flash_error'] = $upload['error'];
                $goTo('/admin/batches');
            }
            $data['lab_report_path'] = $upload['path'];
        }

        $res = BatchRepo::save($id, $data);
        $_SESSION[$res['ok'] ? 'flash_success' : 'flash_error'] = $res['ok'] ? t('admin_saved') : $res['error'];
        $goTo('/admin/batches');
    }

    if ($requestUri === '/admin/batches/recall') {
        $ok = BatchRepo::setRecalled($id, $postText('recalled') === '1');
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? t('admin_saved') : t('admin_not_saved');
        $goTo('/admin/batches');
    }

    if ($requestUri === '/admin/assistant/dismiss') {
        AssistantRepo::dismiss($id);
        $goTo('/admin/assistant');
    }

    if ($requestUri === '/admin/assistant/keyword') {
        $ok = AssistantRepo::addKeyword((int)$postText('concern_id'), $postText('keyword'));
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok ? t('admin_saved') : t('admin_not_saved');
        $goTo('/admin/assistant');
    }

    if ($requestUri === '/admin/assistant/keyword/delete') {
        AssistantRepo::deleteKeyword($id);
        $goTo('/admin/assistant');
    }
}
