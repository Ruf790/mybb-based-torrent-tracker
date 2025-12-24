<?php
// takereport.php
declare(strict_types=1);

require_once 'global.php';

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}



// Получаем и валидируем данные с использованием фильтров PHP 8.5
$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'torrent';
$reported_id = filter_input(INPUT_POST, 'reported_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0;
$reported_user_id = filter_input(INPUT_POST, 'reported_user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?? 0;
$reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
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

// Проверка капчи для неавторизованных пользователей (если нужно)
if (empty($CURUSER['id'])) {
    $captcha = $_POST['captcha'] ?? '';
    $session_captcha = $_SESSION['report_captcha'] ?? '';
    
    if (empty($captcha) || !hash_equals(strtolower($session_captcha), strtolower($captcha))) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid CAPTCHA code']));
    }
    
    unset($_SESSION['report_captcha']);
}

// Проверка частоты отправки (анти-спам)
//if (!can_submit_report($CURUSER['id'])) {
    //http_response_code(429);
    //die(json_encode(['error' => 'Too many reports submitted recently. Please wait before submitting another.']));
//}

// Подготавливаем данные
$added = time();
$ip = get_ip();



// Получаем дополнительные поля
$additional_info = filter_input(INPUT_POST, 'additional_info', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$evidence_links = filter_input(INPUT_POST, 'evidence_links', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';



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
    $db->insert_query("reports", $insert_report);
    
    // Получаем ID вставленной записи
    $report_id = $db->insert_id();
    
    if (!$report_id) {
        throw new RuntimeException("Failed to insert report into database");
    }
    
    // Логируем успешное создание отчета
    write_log(
        "Report #{$report_id} created successfully by user #{$addedby} for {$type} #{$reported_id}",
        'reports',
        $report_id
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
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php') . "?reporterror=1&msg=" . $error_message);
    exit;
}


/**
 * Проверяет частоту отправки отчетов
 */
function can_submit_report(int $user_id): bool
{
    global $db;
    
    $time_limit = time() - 3600; // 1 час
    $query = $db->prepare("SELECT COUNT(*) FROM reports WHERE addedby = ? AND added > ?");
    $query->bind_param('ii', $user_id, $time_limit);
    $query->execute();
    $result = $query->get_result();
    $count = $result->fetch_row()[0] ?? 0;
    
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
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    
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