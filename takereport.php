<?php
// takereport.php
declare(strict_types=1);

require_once 'global.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

// CSRF
if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid security token. Please refresh the page and try again.']));
}



/**
 * Возвращает безопасный URL для редиректа: только относительный путь
 * или абсолютный URL на том же домене. Никогда не доверяем HTTP_REFERER
 * напрямую — это заголовок, который полностью контролирует клиент.
 */
function safe_redirect_target(?string $url, string $fallback = 'index.php'): string
{
    global $BASEURL;

    if (empty($url)) {
        return $fallback;
    }

    // Относительный путь (не начинается с схемы/двух слэшей) - безопасен как есть
    if (!preg_match('#^https?://#i', $url) && !str_starts_with($url, '//')) {
        return $url;
    }

    // Абсолютный URL - разрешаем только если хост совпадает с BASEURL
    $urlHost  = parse_url($url, PHP_URL_HOST);
    $baseHost = parse_url($BASEURL, PHP_URL_HOST);

    if ($urlHost !== null && $baseHost !== null && strcasecmp($urlHost, $baseHost) === 0) {
        return $url;
    }

    return $fallback;
}

// Получаем и валидируем данные с использованием фильтров PHP 8.5
$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'torrent';
$reported_id = filter_input(INPUT_POST, 'reported_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0;
$reported_user_id = filter_input(INPUT_POST, 'reported_user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?? 0;
$reason = trim($_POST['reason'] ?? '');
$description = trim($_POST['description'] ?? '');
$addedby = filter_input(INPUT_POST, 'addedby', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0;

// Валидация с использованием match (PHP 8.0+)
$allowed_types = ['torrent', 'comment', 'user', 'forumpost', 'post'];
if (!in_array($type, $allowed_types, true)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid report type']));
}

// Проверка обязательных полей
$errors = [];
if (empty($reason)) {
    $errors[] = 'Reason is required';
}

if ($reported_id <= 0) {
    $errors[] = 'Invalid item ID';
}

// Проверка что пользователь авторизован
if (empty($CURUSER['id'])) {
    $errors[] = 'You must be logged in to submit a report';
}

// Проверка что addedby совпадает с текущим пользователем
if ($addedby !== (int)($CURUSER['id'] ?? 0)) {
    $errors[] = 'Invalid user ID';
}

// Проверка длины описания
if (strlen($description) > 2000) {
    $errors[] = 'Description is too long (max 2000 characters)';
}

// Если есть ошибки - возвращаем их
if (!empty($errors)) {
    http_response_code(400);
    die(json_encode(['errors' => $errors]));
}

// Проверка CAPTCHA (реальная, серверная - см. report_captcha.php)
$captcha_response = trim($_POST['captcha_response'] ?? '');
$session_captcha  = $_SESSION['report_captcha'] ?? null;

// Код одноразовый - удаляем сразу, независимо от результата проверки,
// чтобы его нельзя было подобрать перебором на одном и том же запросе.
unset($_SESSION['report_captcha']);

$captcha_valid = false;
if ($session_captcha !== null && !empty($captcha_response)) {
    $not_expired = (time() - (int)($session_captcha['created'] ?? 0)) < 600; // 10 минут
    if ($not_expired && hash_equals(strtoupper($session_captcha['code']), strtoupper($captcha_response))) {
        $captcha_valid = true;
    }
}

if (!$captcha_valid) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid or expired security code. Please try again.']));
}

// Примечание: жалобы принимаются только от залогиненных пользователей —
// это уже гарантирует проверка выше ('You must be logged in to submit a report').
// Анонимные жалобы через CAPTCHA сейчас не поддерживаются; если это нужно —
// потребуется отдельно генерировать/показывать капчу в самой форме жалобы.

// Проверка частоты отправки (анти-спам) - не применяется к модераторам и выше
$is_mod = is_mod($usergroups);
if (!$is_mod && !can_submit_report((int)$CURUSER['id'])) {
    http_response_code(429);
    die(json_encode(['error' => 'Too many reports submitted recently. Please wait before submitting another.']));
}

// Подготавливаем данные
$added = time();
$ip = get_ip();



// Получаем дополнительные поля
$additional_info = trim($_POST['additional_info'] ?? '');
$evidence_links = trim($_POST['evidence_links'] ?? '');



// Объединяем все в одно поле description
$full_description = $description;
if (!empty($additional_info)) 
{
    $full_description .= "\n\n--- ADDITIONAL INFORMATION ---\n" . $additional_info;
}
if (!empty($evidence_links)) 
{
    $full_description .= "\n\n--- EVIDENCE LINKS ---\n" . $evidence_links;
}



// Подготавливаем массив для вставки
$insert_report = [
    "addedby" => $addedby,
    "added" => $added,
    "reported_id" => $reported_id,
    "reported_user_id" => $reported_user_id,
    "type" => $type,
    "reason" => $reason,
    "description" => $full_description,
    "ip_address" => $ip
];



// ДОПОЛНИТЕЛЬНЫЕ ПОЛЯ ДЛЯ FORUMPOST
if ($type === 'forumpost') 
{
    // Эти поля приходят из формы (скрытые inputs)
    $forum_id = (int)($_POST['forum_id'] ?? 0);
    $thread_id = (int)($_POST['thread_id'] ?? 0);
    
    $insert_report['forum_id'] = $forum_id;
    $insert_report['thread_id'] = $thread_id;
    
    // Опциональные поля из формы
    $rule_violation = trim($_POST['rule_violation'] ?? '');
    if ($rule_violation) {
        $insert_report['rule_violation'] = $rule_violation;
    }
}



try {
    // Вставляем отчет в базу
    $columns      = array_keys($insert_report);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $db->sql_query_prepared(
        "INSERT INTO reports (" . implode(', ', $columns) . ") VALUES ({$placeholders})",
        array_values($insert_report)
    );
    
    // Получаем ID вставленной записи
    $report_id = $db->insert_id();
    
    if (!$report_id) {
        throw new RuntimeException("Failed to insert report into database");
    }
    
    // Логируем успешное создание отчета
    write_log(
        "Report #{$report_id} created successfully by user #{$addedby} for {$type} #{$reported_id}"
    );
    
    // Отправляем уведомление модераторам
    send_moderator_notification($type, $reported_id, $reason, $report_id);
    
    // Подготавливаем ответ
    $response = [
        'success' => true,
        'message' => 'Report submitted successfully',
        'report_id' => $report_id,
        'redirect' => get_redirect_url()
    ];
    
    // Если это AJAX запрос
    if (is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Для обычных запросов - редирект
    header("Location: " . $response['redirect']);
    exit;
    
} catch (Throwable $e) {
    // Логируем ошибку
    error_log("Report submission error: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
    
    // Подготавливаем сообщение об ошибке
    $error_response = [
        'success' => false,
        'error' => 'Sorry, there was an error submitting your report. Please try again.'
    ];
    
    if (is_ajax_request()) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode($error_response);
        exit;
    }
    
    // Для обычных запросов
    $error_message = urlencode($error_response['error']);
    $errorRedirect = safe_redirect_target($_SERVER['HTTP_REFERER'] ?? null);
    $separator = str_contains($errorRedirect, '?') ? '&' : '?';
    header("Location: " . $errorRedirect . $separator . "reporterror=1&msg=" . $error_message);
    exit;
}


/**
 * Проверяет частоту отправки отчетов
 */
function can_submit_report(int $user_id): bool
{
    global $db;
    
    $time_limit = time() - 3600; // 1 час
    $query = $db->sql_query_prepared(
        "SELECT COUNT(*) AS cnt FROM reports WHERE addedby = ? AND added > ?",
        [$user_id, $time_limit]
    );
    $row = $db->fetch_array($query);
    $count = (int)($row['cnt'] ?? 0);
    
    return $count < 5; // максимум 5 отчетов в час
}

/**
 * Проверяет AJAX запрос
 */
function is_ajax_request(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Получает URL для редиректа
 */
function get_redirect_url(): string
{
    $redirect_url = safe_redirect_target($_SERVER['HTTP_REFERER'] ?? null);
    
    // Убираем существующие параметры success/error
    $redirect_url = preg_replace('/[?&](reportsuccess|reporterror)=\d+/', '', $redirect_url);
    
    // Добавляем параметр успеха
    $separator = str_contains($redirect_url, '?') ? '&' : '?';
    return $redirect_url . $separator . "reportsuccess=1";
}

/**
 * Отправляет уведомление модераторам
 */
function send_moderator_notification(string $type, int $reported_id, string $reason, int $report_id): void
{
    global $db, $BASEURL;
    
    // Получаем список модераторов
    $moderators = get_moderators();
    
    if (empty($moderators)) {
        return;
    }
    
    $subject = "📢 New Report #{$report_id}";
    $message = "A new report has been submitted:\n\n";
    $message .= "🔹 Report ID: #{$report_id}\n";
    $message .= "🔹 Type: " . ucfirst($type) . "\n";
    $message .= "🔹 Reported ID: {$reported_id}\n";
    $message .= "🔹 Reason: {$reason}\n";
    $message .= "🔗 Link: {$BASEURL}/admin/reports.php?action=view&id={$report_id}\n";
    $message .= "\nPlease review it as soon as possible.";
    
    foreach ($moderators as $mod) {
        // Реализуйте отправку уведомлений (PM, email и т.д.)
        // send_notification($mod['id'], $subject, $message);
    }
}

/**
 * Получает список модераторов
 */
function get_moderators(): array
{
    global $db;
    
    // Реализуйте получение списка модераторов
    // $query = $db->query("SELECT id, email FROM users WHERE is_moderator = 1 AND status = 'active'");
    // return $db->fetch_all($query) ?? [];
    
    return [];
}
?>