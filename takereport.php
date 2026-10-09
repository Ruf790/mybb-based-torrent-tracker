<?php
// takereport.php
declare(strict_types=1);


define("IN_MYBB", 1);

require_once 'global.php';

require_once INC_PATH.'/datahandler.php';

require_once INC_PATH . '/functions_pm.php';

$lang->load('takereport');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



// Максимальная длина описания (символы, не байты) — та же, что в report_user.js
const REPORT_DESC_MAX = 2000;

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => $lang->takereport['err_method']]));
}

// CSRF
if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
    http_response_code(403);
    die(json_encode(['error' => $lang->takereport['err_csrf']]));
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
    die(json_encode(['error' => $lang->takereport['err_type']]));
}

// Проверка обязательных полей.
// $fields — имена полей формы с ошибками: по ним JS подсвечивает поля,
// не разбирая текст ошибки (он теперь переведён).
$errors = [];
$fields = [];
if (empty($reason)) {
    $errors[] = $lang->takereport['err_reason_required'];
    $fields[] = 'reason';
}

if ($reported_id <= 0) {
    $errors[] = $lang->takereport['err_item_id'];
}

// Проверка что пользователь авторизован
if (empty($CURUSER['id'])) {
    $errors[] = $lang->takereport['err_login'];
}

// Проверка что addedby совпадает с текущим пользователем
if ($addedby !== (int)($CURUSER['id'] ?? 0)) {
    $errors[] = $lang->takereport['err_user_id'];
}

// Проверка длины описания — в символах: strlen() считал байты,
// и русский текст отсекался примерно на 1000 символах
if (mb_strlen($description, 'UTF-8') > REPORT_DESC_MAX) {
    $errors[] = sprintf($lang->takereport['err_desc_long'], REPORT_DESC_MAX);
    $fields[] = 'description';
}

// Если есть ошибки - возвращаем их
if (!empty($errors)) {
    http_response_code(400);
    die(json_encode(['errors' => $errors, 'fields' => $fields]));
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
    die(json_encode(['error' => $lang->takereport['err_captcha'], 'fields' => ['captcha']]));
}

// Примечание: жалобы принимаются только от залогиненных пользователей —
// это уже гарантирует проверка выше (err_login).
// Анонимные жалобы через CAPTCHA сейчас не поддерживаются; если это нужно —
// потребуется отдельно генерировать/показывать капчу в самой форме жалобы.

// Проверка частоты отправки (анти-спам) - не применяется к модераторам и выше
$is_mod = is_mod($usergroups);
if (!$is_mod && !can_submit_report((int)$CURUSER['id'])) {
    http_response_code(429);
    die(json_encode(['error' => $lang->takereport['err_rate_limit']]));
}

// Подготавливаем данные
$added = time();
$ip = get_ip();



// Получаем дополнительные поля
$additional_info = trim($_POST['additional_info'] ?? '');
$evidence_links = trim($_POST['evidence_links'] ?? '');



// Объединяем все в одно поле description.
// Разделители пишутся в БД и читаются стаффом в админке — как и логи,
// они остаются на английском, чтобы данные не зависели от языка автора жалобы.
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
    send_moderator_notification($type, $reported_id, $reason, (int)$report_id);

    // Подготавливаем ответ
    $response = [
        'success' => true,
        'message' => $lang->takereport['msg_success'],
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
        'error' => $lang->takereport['err_submit_failed']
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
    global $BASEURL;

    $moderators = get_moderators();

    if (empty($moderators)) {
        return;
    }

    // Не даём пользователю вставить свой BBCode в ЛС от системы
    $reason = str_replace(['[', ']'], ['(', ')'], $reason);

    $report_url = $BASEURL . '/admin/reports.php?action=view&id=' . $report_id;
    $link       = '[url=' . $report_url . ']' . $report_url . '[/url]';

    foreach ($moderators as $mod) {
       
		$t = get_lang_section('takereport', (string)($mod['language'] ?? ''));

        $type_label = $t['opt_type_' . $type] ?? ucfirst($type);

        $pm = [
            'subject' => sprintf($t['pm_mod_subject'], $report_id),
            'message' => sprintf($t['pm_mod_body'], $report_id, $type_label, $reported_id, $reason, $link),
            'touid'   => (int)$mod['id'],
        ];
        $pm['sender']['uid'] = -1;

        try {
            if (!send_pm($pm, -1, true)) {
                error_log("Report PM to user #{$mod['id']} was not sent (send_pm returned false)");
            }
        } catch (Throwable $e) {
            error_log("Report PM to user #{$mod['id']} failed: {$e->getMessage()}");
        }
    }
}




/**
 * Получает список модераторов
 */
function get_moderators(): array
{
    global $db;

    $result = $db->sql_query_prepared(
        "SELECT id, language FROM users
         WHERE usergroup IN (SELECT gid FROM usergroups WHERE issupermod = ?)",
        [1]
    );

    $mods = [];
    while ($row = $db->fetch_array($result)) {
        $mods[] = $row;
    }
    return $mods;
}



?>
