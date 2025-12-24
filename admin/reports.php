<?php

declare(strict_types=1);

/**
 * reports.php - Complete Report Management System
 */
 
define("IN_MYBB", 1);

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed2222222.</font>');
}

require_once(INC_PATH.'/class_parser.php');

$parser = new postParser();
$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

$action = $_GET['action'] ?? 'list';
$report_id = (int)($_GET['id'] ?? 0);





function get_report_reasons_map($type = null)
{
    // Маппинг для комментариев
    $comment_reasons = [
        'spam' => [
            'text' => 'Spam / Advertising',
            'color' => 'bg-danger',
            'icon' => 'fa-bullhorn',
            'severity' => 'high',
            'category' => 'Content Issues'
        ],
        'offensive' => [
            'text' => 'Offensive / Abusive Language',
            'color' => 'bg-danger',
            'icon' => 'fa-comment-slash',
            'severity' => 'high',
            'category' => 'Content Issues'
        ],
        'harassment' => [
            'text' => 'Harassment / Bullying',
            'color' => 'bg-danger',
            'icon' => 'fa-user-slash',
            'severity' => 'high',
            'category' => 'Content Issues'
        ],
        'hate_speech' => [
            'text' => 'Hate Speech / Discrimination',
            'color' => 'bg-danger',
            'icon' => 'fa-triangle-exclamation',
            'severity' => 'high',
            'category' => 'Content Issues'
        ],
        'inappropriate' => [
            'text' => 'Inappropriate Content',
            'color' => 'bg-warning',
            'icon' => 'fa-eye-slash',
            'severity' => 'medium',
            'category' => 'Content Issues'
        ],
        'spoiler' => [
            'text' => 'Spoiler / Leaked Content',
            'color' => 'bg-info',
            'icon' => 'fa-mask',
            'severity' => 'low',
            'category' => 'Other Issues'
        ],
        'misinformation' => [
            'text' => 'Misinformation / Fake News',
            'color' => 'bg-warning',
            'icon' => 'fa-circle-exclamation',
            'severity' => 'medium',
            'category' => 'Other Issues'
        ],
        'off_topic' => [
            'text' => 'Off Topic / Irrelevant',
            'color' => 'bg-secondary',
            'icon' => 'fa-signs-post',
            'severity' => 'low',
            'category' => 'Other Issues'
        ],
        'personal_info' => [
            'text' => 'Personal Information',
            'color' => 'bg-danger',
            'icon' => 'fa-id-card',
            'severity' => 'high',
            'category' => 'Other Issues'
        ],
        'other' => [
            'text' => 'Other Reason',
            'color' => 'bg-dark',
            'icon' => 'fa-ellipsis',
            'severity' => 'unknown',
            'category' => 'Other Issues'
        ]
    ];
    
    // Маппинг для торрентов
    $torrent_reasons = [
        'copyright' => [
            'text' => 'Copyright Infringement',
            'color' => 'bg-danger',
            'icon' => 'fa-copyright',
            'severity' => 'high',
            'category' => 'Legal Issues'
        ],
        'malware' => [
            'text' => 'Malware/Virus',
            'color' => 'bg-danger',
            'icon' => 'fa-bug',
            'severity' => 'high',
            'category' => 'Security Issues'
        ],
        'fake' => [
            'text' => 'Fake/Incorrect Content',
            'color' => 'bg-warning',
            'icon' => 'fa-ban',
            'severity' => 'medium',
            'category' => 'Content Issues'
        ],
        'broken' => [
            'text' => 'Broken/Dead Torrent',
            'color' => 'bg-info',
            'icon' => 'fa-link-slash',
            'severity' => 'low',
            'category' => 'Technical Issues'
        ],
        'inappropriate' => [
            'text' => 'Inappropriate Content',
            'color' => 'bg-warning',
            'icon' => 'fa-eye-slash',
            'severity' => 'medium',
            'category' => 'Content Issues'
        ],
        'other' => [
            'text' => 'Other Reason',
            'color' => 'bg-dark',
            'icon' => 'fa-ellipsis',
            'severity' => 'unknown',
            'category' => 'Other Issues'
        ]
    ];
    


// Маппинг для форумных постов
$forum_post_reasons = [
    // Основные причины из первого select
    'spam' => [
        'text' => 'Spam / Advertising',
        'color' => 'bg-danger',
        'icon' => 'fa-bullhorn',
        'severity' => 'high',
        'category' => 'Content Violations'
    ],
    'offensive' => [
        'text' => 'Offensive / Abusive Language',
        'color' => 'bg-danger',
        'icon' => 'fa-comment-slash',
        'severity' => 'high',
        'category' => 'Content Violations'
    ],
    'harassment' => [
        'text' => 'Harassment / Bullying',
        'color' => 'bg-danger',
        'icon' => 'fa-user-slash',
        'severity' => 'high',
        'category' => 'Content Violations'
    ],
    'hate_speech' => [
        'text' => 'Hate Speech / Discrimination',
        'color' => 'bg-danger',
        'icon' => 'fa-triangle-exclamation',
        'severity' => 'high',
        'category' => 'Content Violations'
    ],
    'explicit' => [
        'text' => 'Explicit / Adult Content',
        'color' => 'bg-danger',
        'icon' => 'fa-eye-slash',
        'severity' => 'high',
        'category' => 'Content Violations'
    ],
    'illegal' => [
        'text' => 'Illegal Content / Warez',
        'color' => 'bg-danger',
        'icon' => 'fa-ban',
        'severity' => 'high',
        'category' => 'Content Violations'
    ],
    
    // Причины из раздела Forum Rules
    'off_topic' => [
        'text' => 'Off Topic / Wrong Forum',
        'color' => 'bg-warning',
        'icon' => 'fa-signs-post',
        'severity' => 'medium',
        'category' => 'Forum Rules'
    ],
    'double_post' => [
        'text' => 'Double Post / Cross-Posting',
        'color' => 'bg-info',
        'icon' => 'fa-copy',
        'severity' => 'low',
        'category' => 'Forum Rules'
    ],
    'flame' => [
        'text' => 'Flaming / Trolling',
        'color' => 'bg-warning',
        'icon' => 'fa-fire',
        'severity' => 'medium',
        'category' => 'Forum Rules'
    ],
    'personal_attack' => [
        'text' => 'Personal Attack',
        'color' => 'bg-danger',
        'icon' => 'fa-user-slash',
        'severity' => 'high',
        'category' => 'Forum Rules'
    ],
    'spoiler' => [
        'text' => 'Unmarked Spoilers',
        'color' => 'bg-warning',
        'icon' => 'fa-mask',
        'severity' => 'medium',
        'category' => 'Forum Rules'
    ],
    
    // Причины из раздела Other Issues
    'copyright' => [
        'text' => 'Copyright Infringement',
        'color' => 'bg-danger',
        'icon' => 'fa-copyright',
        'severity' => 'high',
        'category' => 'Other Issues'
    ],
    'personal_info' => [
        'text' => 'Personal Information',
        'color' => 'bg-danger',
        'icon' => 'fa-id-card',
        'severity' => 'high',
        'category' => 'Other Issues'
    ],
    'malware' => [
        'text' => 'Malware Link',
        'color' => 'bg-danger',
        'icon' => 'fa-bug',
        'severity' => 'high',
        'category' => 'Other Issues'
    ],
    'scam' => [
        'text' => 'Scam / Fraud',
        'color' => 'bg-danger',
        'icon' => 'fa-skull-crossbones',
        'severity' => 'high',
        'category' => 'Other Issues'
    ],
    'other' => [
        'text' => 'Other Reason',
        'color' => 'bg-dark',
        'icon' => 'fa-ellipsis',
        'severity' => 'unknown',
        'category' => 'Other Issues'
    ],
    
    // Правила форума (rule_violation)
    'rule_1' => [
        'text' => 'Rule 1: No spamming or advertising',
        'color' => 'bg-danger',
        'icon' => 'fa-megaphone',
        'severity' => 'high',
        'category' => 'Forum Rules'
    ],
    'rule_2' => [
        'text' => 'Rule 2: No offensive language',
        'color' => 'bg-danger',
        'icon' => 'fa-comment-slash',
        'severity' => 'high',
        'category' => 'Forum Rules'
    ],
    'rule_3' => [
        'text' => 'Rule 3: No harassment or bullying',
        'color' => 'bg-danger',
        'icon' => 'fa-user-group-slash',
        'severity' => 'high',
        'category' => 'Forum Rules'
    ],
    'rule_4' => [
        'text' => 'Rule 4: Stay on topic',
        'color' => 'bg-warning',
        'icon' => 'fa-signs-post',
        'severity' => 'medium',
        'category' => 'Forum Rules'
    ],
    'rule_5' => [
        'text' => 'Rule 5: No warez or illegal content',
        'color' => 'bg-danger',
        'icon' => 'fa-ban',
        'severity' => 'high',
        'category' => 'Forum Rules'
    ],
    'rule_6' => [
        'text' => 'Rule 6: Respect other members',
        'color' => 'bg-warning',
        'icon' => 'fa-handshake',
        'severity' => 'medium',
        'category' => 'Forum Rules'
    ],
    'rule_7' => [
        'text' => 'Rule 7: No double posting',
        'color' => 'bg-info',
        'icon' => 'fa-copy',
        'severity' => 'low',
        'category' => 'Forum Rules'
    ],
    'rule_8' => [
        'text' => 'Rule 8: Use appropriate language',
        'color' => 'bg-warning',
        'icon' => 'fa-language',
        'severity' => 'medium',
        'category' => 'Forum Rules'
    ]
];
  
  
    
    // Маппинг для пользователей
    $user_reasons = [
        'spam' => [
            'text' => 'Spam Account',
            'color' => 'bg-danger',
            'icon' => 'fa-user-slash',
            'severity' => 'high',
            'category' => 'Account Issues',
            'description' => 'User is posting spam content',
            'recommended_action' => 'Review user posts and consider temporary suspension'
        ],
        'harassment' => [
            'text' => 'Harassment/Bullying',
            'color' => 'bg-danger',
            'icon' => 'fa-ban',
            'severity' => 'high',
            'category' => 'Behavior Issues',
            'description' => 'User is harassing or bullying others',
            'recommended_action' => 'Immediate warning or temporary ban'
        ],
        'fake' => [
            'text' => 'Fake Account',
            'color' => 'bg-warning',
            'icon' => 'fa-mask',
            'severity' => 'medium',
            'category' => 'Account Issues',
            'description' => 'User is pretending to be someone else',
            'recommended_action' => 'Verify identity and take appropriate action'
        ],
        'impersonation' => [
            'text' => 'Impersonation',
            'color' => 'bg-danger',
            'icon' => 'fa-id-badge',
            'severity' => 'high',
            'category' => 'Account Issues',
            'description' => 'User is impersonating another user',
            'recommended_action' => 'Immediate account suspension'
        ],
        'inappropriate' => [
            'text' => 'Inappropriate Profile',
            'color' => 'bg-warning',
            'icon' => 'fa-eye-slash',
            'severity' => 'medium',
            'category' => 'Content Issues',
            'description' => 'User has inappropriate profile content',
            'recommended_action' => 'Request profile cleanup or temporary restriction'
        ],
        'scam' => [
            'text' => 'Scam/Fraud',
            'color' => 'bg-danger',
            'icon' => 'fa-skull-crossbones',
            'severity' => 'high',
            'category' => 'Legal Issues',
            'description' => 'User is involved in scams or fraud',
            'recommended_action' => 'Immediate ban and report if necessary'
        ],
        'copyright' => [
            'text' => 'Copyright Infringement',
            'color' => 'bg-danger',
            'icon' => 'fa-copyright',
            'severity' => 'high',
            'category' => 'Legal Issues',
            'description' => 'User is sharing copyrighted content',
            'recommended_action' => 'Remove infringing content and issue warning'
        ],
        'malware' => [
            'text' => 'Malware Distribution',
            'color' => 'bg-danger',
            'icon' => 'fa-bug',
            'severity' => 'high',
            'category' => 'Security Issues',
            'description' => 'User is distributing malware/viruses',
            'recommended_action' => 'Immediate ban and content removal'
        ],
        'other' => [
            'text' => 'Other Reason',
            'color' => 'bg-dark',
            'icon' => 'fa-ellipsis',
            'severity' => 'unknown',
            'category' => 'Other Issues',
            'description' => 'Select for other reasons',
            'recommended_action' => 'Review report description carefully'
        ],
        // Дополнительные причины, которые могут быть полезны
        'racism' => [
            'text' => 'Racism/Hate Speech',
            'color' => 'bg-danger',
            'icon' => 'fa-comment-slash',
            'severity' => 'high',
            'category' => 'Behavior Issues',
            'description' => 'User is posting racist or hateful content',
            'recommended_action' => 'Immediate suspension or ban'
        ],
        'threats' => [
            'text' => 'Threats/Violence',
            'color' => 'bg-danger',
            'icon' => 'fa-exclamation-triangle',
            'severity' => 'high',
            'category' => 'Behavior Issues',
            'description' => 'User is making threats or promoting violence',
            'recommended_action' => 'Immediate permanent ban'
        ],
        'underage' => [
            'text' => 'Underage User',
            'color' => 'bg-warning',
            'icon' => 'fa-child',
            'severity' => 'medium',
            'category' => 'Account Issues',
            'description' => 'User appears to be underage',
            'recommended_action' => 'Suspend until age verification'
        ],
        'cheating' => [
            'text' => 'Cheating/Gaming System',
            'color' => 'bg-warning',
            'icon' => 'fa-gamepad',
            'severity' => 'medium',
            'category' => 'Behavior Issues',
            'description' => 'User is cheating or exploiting the system',
            'recommended_action' => 'Reset stats and issue warning'
        ]
    ];
    
    
	// Возвращаем нужный маппинг
    if ($type === 'comment') {
        return $comment_reasons;
    } elseif ($type === 'torrent') {
        return $torrent_reasons;
    } elseif ($type === 'forumpost' || $type === 'forum_post') {
        return $forum_post_reasons;
    } elseif ($type === 'user') {
        return $user_reasons;
    } else {
        // Возвращаем все маппинги, если тип не указан
        return [
            'comment' => $comment_reasons,
            'torrent' => $torrent_reasons,
            'forumpost' => $forum_post_reasons,
            'user' => $user_reasons
        ];
    }
}








/**
 * Получить данные о посте форума для репорта
 */
function getForumPostData(int $post_id, array $report): ?array
{
    global $db;
    
    $sql = "SELECT 
                p.*, 
                t.subject as thread_subject, 
                t.tid as thread_id,
                f.name as forum_name,
                f.fid as forum_id,
                u.username as author_name,
                u.id as author_id
            FROM tsf_posts p
            LEFT JOIN tsf_threads t ON p.tid = t.tid
            LEFT JOIN tsf_forums f ON p.fid = f.fid
            LEFT JOIN users u ON p.uid = u.id
            WHERE p.pid = ?";
    
    $result = $db->sql_query_prepared($sql, [$post_id]);
    
    if ($result && $db->num_rows($result) > 0) {
        $post_data = $db->fetch_array($result);
        $db->free_result($result);
        
        // Добавляем дополнительные данные из репорта если нужно
        $post_data['report_forum_id'] = $report['forum_id'] ?? 0;
        $post_data['report_thread_id'] = $report['thread_id'] ?? 0;
        $post_data['rule_violation'] = $report['rule_violation'] ?? '';
        
        return $post_data;
    }
    
    return null;
}






/**
 * Получение цвета для типа репорта
 */
function getTypeColor(string $type): string
{
    return match ($type) {
        'torrent' => 'primary',
        'comment' => 'info',
        'user' => 'warning',
        'forumpost' => 'success',
        default => 'secondary'
    };
}

/**
 * Обработка действий с репортами
 */
function handleAction(): never
{
    global $db, $CURUSER;
    
    $do = $_GET['do'] ?? $_POST['do'] ?? '';
    $report_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if ($report_id <= 0) {
        sendResponse('Invalid report ID', false);
    }
    
    // Проверяем существование репорта
    $stmt = $db->sql_query_prepared("SELECT * FROM reports WHERE id = ?", [$report_id]);
    $report = $stmt ? $db->fetch_array($stmt) : false;
    
    if (!$report) {
        sendResponse('Report not found', false);
    }
    
    match ($do) {
        'resolve' => handleResolve($report_id, $report),
        'delete' => handleDelete($report_id),
        'deletecomment' => handleDeleteComment($report_id, $report),
        'deleteforumpost' => handleDeleteForumPost($report_id, $report), // НОВЫЙ
        default => sendResponse('Invalid action', false)
    };
}


/**
 * Удалить пост форума
 */
function handleDeleteForumPost(int $report_id, array $report): void
{
    global $db, $CURUSER;
    
    if ($report['type'] !== 'forumpost') {
        sendResponse('Invalid report type for forum post deletion', false);
    }
    
    $post_id = (int)$report['reported_id'];
    
    // Проверяем существование поста перед удалением
    $post_check = $db->sql_query_prepared("SELECT pid FROM tsf_posts WHERE pid = ?", [$post_id]);
    if (!$post_check || $db->num_rows($post_check) === 0) {
        // Пост уже удален, просто обновляем репорт
        $db->sql_query_prepared(
            "UPDATE reports SET dealtwith = 1, dealtby = ?, updated_at = ? WHERE id = ?",
            [$CURUSER['id'], time(), $report_id]
        );
        sendResponse('Forum post already deleted, report marked as resolved');
    }
    
    // Загружаем класс модерации
    if (!class_exists('Moderation')) {
        require_once INC_PATH . "/class_moderation.php";
    }
    
    try {
        $moderation = new Moderation;
        
        // Удаляем пост через класс модерации
        $result = $moderation->delete_post($post_id);
        
        if ($result === false) {
            // Если delete_post вернул false (пост не найден)
            $db->sql_query_prepared(
                "UPDATE reports SET dealtwith = 1, dealtby = ?, updated_at = ? WHERE id = ?",
                [$CURUSER['id'], time(), $report_id]
            );
            sendResponse('Forum post not found (may be already deleted), report resolved');
        }
        
        // Обновляем репорт
        $db->sql_query_prepared(
            "UPDATE reports SET dealtwith = 1, dealtby = ?, updated_at = ? WHERE id = ?",
            [$CURUSER['id'], time(), $report_id]
        );
        
        // Логируем действие
        // logStaffAction($CURUSER['id'], "Deleted forum post #$post_id from report #$report_id");
        
        sendResponse('Forum post deleted and report resolved');
        
    } catch (Exception $e) {
        // Обработка ошибок
        error_log("Error deleting forum post #$post_id: " . $e->getMessage());
        
        // Попытка обновить репорт даже при ошибке
        $db->sql_query_prepared(
            "UPDATE reports SET dealtwith = 1, dealtby = ?, updated_at = ? WHERE id = ?",
            [$CURUSER['id'], time(), $report_id]
        );
        
        sendResponse('Forum post deletion attempted, report marked as resolved. Error: ' . $e->getMessage(), true);
    }
}








function parseUserReportDescription(string $description): array
{
    $result = [
        'formatted_description' => $description,
        'additional_info' => '',
        'evidence_links' => ''
    ];
    
    // Проверяем формат с разделителями (как в takereport.php)
    if (strpos($description, '===== USER REPORT =====') !== false) {
        // Формат с новыми разделителями
        $sections = explode('=====', $description);
        
        foreach ($sections as $section) {
            $section = trim($section);
            
            if (strpos($section, 'DESCRIPTION') !== false) {
                $result['formatted_description'] = trim(str_replace('DESCRIPTION =====', '', $section));
            }
            if (strpos($section, 'ADDITIONAL INFORMATION') !== false) {
                $result['additional_info'] = trim(str_replace('ADDITIONAL INFORMATION =====', '', $section));
            }
            if (strpos($section, 'EVIDENCE LINKS') !== false) {
                $result['evidence_links'] = trim(str_replace('EVIDENCE LINKS =====', '', $section));
            }
        }
    } 
    // Старый формат (из предыдущих записей)
    elseif (strpos($description, 'I am reporting this user for:') !== false) {
        $result['formatted_description'] = $description;
    }
    
    return $result;
}




/**
 * Отметить репорт как решенный
 */
function handleResolve(int $report_id, array $report): void
{
    global $db, $CURUSER;
    
    $notes = trim($_POST['notes'] ?? '');
    $time = time();
    
    if ($notes) {
        $notes = "\n\n--- ADMIN NOTES ---\n" . $notes;
    }
    
    $db->sql_query_prepared(
        "UPDATE reports SET 
         dealtwith = 1,
         dealtby = ?,
         updated_at = ?,
         description = CONCAT(COALESCE(description, ''), ?)
         WHERE id = ?",
        [$CURUSER['id'], $time, $notes, $report_id]
    );
    
    // Логирование действия
    //logStaffAction($CURUSER['id'], "Resolved report #$report_id");
    
    sendResponse('Report resolved');
}

/**
 * Удалить репорт
 */
function handleDelete(int $report_id): void
{
    global $db, $CURUSER;
    
    $db->sql_query_prepared("DELETE FROM reports WHERE id = ?", [$report_id]);
    
    // Логирование действия
    //logStaffAction($CURUSER['id'], "Deleted report #$report_id");
    
    sendResponse('Report deleted');
}

/**
 * Удалить комментарий
 */
function handleDeleteComment(int $report_id, array $report): void
{
    global $db, $CURUSER, $BASEURL, $kpscomment;
    
    if ($report['type'] !== 'comment') 
	{
        sendResponse('Invalid report type for comment deletion', false);
    }
    
    $comment_id = (int)$report['reported_id'];
    
    // Получаем информацию о комментарии, включая torrent ID
    $res = $db->sql_query('SELECT torrent, user FROM comments WHERE id = ' . $db->escape_string($comment_id));
    $comment_data = $db->fetch_array($res);
    
    if (!$comment_data) {
        sendResponse('Comment not found', false);
    }
    
    $torrentid = (int)$comment_data['torrent']; // Получаем torrent ID из комментария
    $userpostid = (int)$comment_data['user'];
    

    $files = $db->simple_select("comment_files", "*", "comment_id = " . $comment_id);
    while ($file = $db->fetch_array($files)) {
        if (!empty($file['file_path']) && is_file($file['file_path'])) {
            @unlink($file['file_path']);
        }
    }
    $db->delete_query("comment_files", "comment_id = " . $comment_id);
    
    // Удаляем комментарий
    $db->delete_query("comments", "id = " . $comment_id);
    
    // Обновляем счетчики ТОЛЬКО если комментарий был найден и удален
    if ($torrentid > 0 && $db->affected_rows() > 0) 
	{
        $db->sql_query('UPDATE torrents SET comments = IF(comments > 0, comments - 1, 0) WHERE id = ' . $db->escape_string($torrentid));
        
        if ($userpostid > 0) 
		{
            $db->sql_query('UPDATE users SET comms = IF(comms > 0, comms - 1, 0) WHERE id = ' . $db->escape_string($userpostid));
        }
    }
    
    // KPS система (если используется)
    if (isset($kpscomment) && $userpostid > 0) 
	{
        kps('-', $kpscomment, $userpostid);
    }
    
    
    // Обновляем репорт
    $db->sql_query_prepared(
        "UPDATE reports SET dealtwith = 1, dealtby = ?, updated_at = ? WHERE id = ?",
        [$CURUSER['id'], time(), $report_id]
    );
    
    sendResponse('Comment deleted and report resolved');
}













/**
 * Логирование действий персонала
 */
function logStaffAction(int $user_id, string $action): void
{
    global $db;
    $db->sql_query_prepared(
        "INSERT INTO staff_log (added, userid, action) VALUES (?, ?, ?)",
        [time(), $user_id, $action]
    );
}

/**
 * Отправка JSON ответа
 */
function sendResponse(string $message, bool $success = true): never
{
    global $_this_script_;
	
	if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success, 
            'message' => $message
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    
    // Для обычных запросов делаем редирект
    $action = $_GET['action'] ?? 'list';
    $id = (int)($_GET['id'] ?? 0);
    
    $params = ['action' => $action === 'takeaction' ? 'list' : $action];
    
    if ($success) {
        $params['success'] = match ($message) {
            'Report resolved' => 'resolved',
            'Report deleted' => 'deleted',
            'Comment deleted and report resolved' => 'comment_deleted',
            'Report ignored' => 'ignored',
            default => 'success'
        };
    } else {
        $params['error'] = match ($message) {
            'Invalid report ID' => 'invalid_id',
            'Report not found' => 'not_found',
            'Invalid action' => 'invalid_action',
            'No user to warn' => 'no_user',
            default => 'error'
        };
    }
    
    if ($id > 0 && $action !== 'takeaction') {
        $params['id'] = $id;
    }
    
    header('Location: '.$_this_script_.'&' . http_build_query($params));
    exit;
}

/**
 * Проверка AJAX запроса
 */
function isAjaxRequest(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Обработка действий
if ($action === 'takeaction' && $report_id) {
    handleAction();
}

// Вывод страницы
stdhead("Report Management - Admin Panel");


echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/toast.js"></script>';

?>

<div class="container mt-3">
    <h1 class="h3 mb-4">
        <i class="fa-solid fa-flag text-danger me-2"></i>Report Management
        <small class="text-muted fs-6">Admin Panel</small>
    </h1>
    
    <!-- Меню -->
    <div class="btn-group mb-4" role="group">
        <?php foreach (['list' => 'All Reports', 'pending' => 'Pending', 'resolved' => 'Resolved', 'stats' => 'Statistics'] as $act => $label): ?>
            <a href="<?= $_this_script_ ?>&action=<?= $act ?>" 
               class="btn btn-outline-primary <?= $action === $act ? 'active' : '' ?>">
                <i class="fa-solid fa-<?= match($act) {
                    'list' => 'list',
                    'pending' => 'clock',
                    'resolved' => 'check-circle',
                    'stats' => 'chart-bar'
                } ?> me-1"></i><?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <?php
    match ($action) {
        'view' => showReportDetails($report_id),
        'pending' => showPendingReports(),
        'resolved' => showResolvedReports(),
        'stats' => showStatistics(),
        default => showAllReports()
    };
    ?>
</div>

<?php
stdfoot();

// ==================== ФУНКЦИИ ОТОБРАЖЕНИЯ ====================

/**
 * Показать все репорты с фильтрацией
 */

function showAllReports(): void
{
    global $db, $_this_script_;
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perpage = 20;
    $offset = ($page - 1) * $perpage;
    
    // Фильтры
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    // Построение запроса с подготовленными параметрами
    $where_parts = [];
    $params = [];
    
    if ($type && in_array($type, ['torrent', 'user', 'comment', 'forumpost'])) {
        $where_parts[] = "r.type = ?";
        $params[] = $type;
    }
    
    if ($status === 'pending') {
        $where_parts[] = "r.dealtwith = 0";
    } elseif ($status === 'resolved') {
        $where_parts[] = "r.dealtwith = 1";
    }
    
    if ($search) {
        $search_like = "%$search%";
        $where_parts[] = "(r.reason LIKE ? OR r.description LIKE ? OR u1.username LIKE ? OR u2.username LIKE ?)";
        array_push($params, $search_like, $search_like, $search_like, $search_like);
    }
    
    $where_sql = $where_parts ? "WHERE " . implode(" AND ", $where_parts) : "";
    
    // Общее количество
    $count_sql = "SELECT COUNT(*) as total FROM reports r 
                  LEFT JOIN users u1 ON r.addedby = u1.id 
                  LEFT JOIN users u2 ON r.reported_user_id = u2.id 
                  $where_sql";
    
    $count_result = $db->sql_query_prepared($count_sql, $params);
    $total = $count_result ? (int)($db->fetch_array($count_result)['total'] ?? 0) : 0;
    
    // Получаем репорты
    $sql = "SELECT r.*, 
                   u1.username as reporter_name,
                   u2.username as reported_user_name,
                   u3.username as dealtby_name,
				   r.reason,
                   r.rule_violation
            FROM reports r
            LEFT JOIN users u1 ON r.addedby = u1.id
            LEFT JOIN users u2 ON r.reported_user_id = u2.id
            LEFT JOIN users u3 ON r.dealtby = u3.id
            $where_sql
            ORDER BY r.added DESC
            LIMIT ?, ?";
    
    // Добавляем параметры для LIMIT
    $limit_params = [...$params, $offset, $perpage];
    $result = $db->sql_query_prepared($sql, $limit_params);
    

   
    
    // Остальной код HTML вывода остается без изменений
    ?>
    
   <!-- Форма фильтров -->
	
    <div class="card mb-4">
        <div class="card-body">
                <form method="GET" action="<?= $_this_script_ ?>" class="row g-3">
				   <input type="hidden" name="act" value="reports">
                
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach (['torrent' => 'Torrent', 'comment' => 'Comment', 'user' => 'User', 'forumpost' => 'Forum Post'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $type === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search in reason, description or usernames..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Таблица репортов -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Reports (<?= number_format($total) ?>)</h5>
            <div class="btn-group">
                <a href="<?= $_this_script_ ?>&action=list&export=csv" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-file-export me-1"></i> Export CSV
                </a>
            </div>
        </div>
        
        <?php if ($total > 0 && $result): ?>
        <div class="table-responsive">
             <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Reporter</th>
                        <th>Reported User</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $db->fetch_array($result)): 
                        // Используем функцию get_report_reasons_map для получения данных о причине
                        $reasons_map = get_report_reasons_map($row['type']);
                        $reason_data = isset($reasons_map[$row['reason']]) ? $reasons_map[$row['reason']] : null;
						
						$ads = my_datee('relative', $row['added']);
						
					
						
						
                    ?>
                    <tr class="<?= $row['dealtwith'] ? 'table2-success' : 'table2-warning' ?>">
                        <td>#<?= $row['id'] ?></td>
                        <td>
                            <span class="badge bg-<?= getTypeColor($row['type']) ?>">
                                <?= ucfirst($row['type']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($reason_data): ?>
                                <span class="badge <?= $reason_data['color'] ?>" 
                                      title="<?= htmlspecialchars($reason_data['text']) ?> (<?= htmlspecialchars($reason_data['category']) ?>)">
                                    <i class="fa-solid <?= $reason_data['icon'] ?> me-1"></i>
                                    <?= htmlspecialchars(truncateString($reason_data['text'], 25)) ?>
                                </span>
                                <?php if ($reason_data['severity'] === 'high' && !$row['dealtwith']): ?>
                                <span class="badge bg-danger blink ms-1" title="High Priority">
                                    <i class="fa-solid fa-exclamation"></i>
                                </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary">
                                    <i class="fa-solid fa-question me-1"></i>
                                    <?= htmlspecialchars(truncateString($row['reason'], 30)) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['addedby']): ?>
                            <a href="user-<?= $row['addedby'] ?>.html" target="_blank" class="text-decoration-none">
                                <?= htmlspecialchars($row['reporter_name'] ?? 'User #' . $row['addedby']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">Guest</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['reported_user_id']): ?>
                            <a href="user-<?= $row['reported_user_id'] ?>.html" target="_blank" class="text-decoration-none">
                                <?= htmlspecialchars($row['reported_user_name'] ?? 'User #' . $row['reported_user_id']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $ads ?></td>
                        <td>
                            <?php if ($row['dealtwith']): ?>
                            <span class="badge bg-success">
                                <i class="fa-solid fa-check me-1"></i>Resolved
                            </span>
                            <?php if ($row['dealtby_name']): ?>
                            <div class="small text-muted">by <?= htmlspecialchars($row['dealtby_name']) ?></div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark">
                                <i class="fa-solid fa-clock me-1"></i>Pending
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                              
							  <a href="<?= htmlspecialchars((string)$_this_script_) ?>&action=view&id=<?= (int)$row['id'] ?>" class="btn btn-outline-primary" title="View Details">
                                  <i class="fa-solid fa-eye"></i>
                              </a>
							  
                                <?php if (!$row['dealtwith']): ?>
                                
								
								<a href="<?= $_this_script_ ?>&action=takeaction&do=resolve&id=<?= $row['id'] ?>" 
   class="btn btn-outline-success btn-sm resolve-report" 
   data-id="<?= $row['id'] ?>" 
   data-bs-toggle="popover" 
   data-bs-trigger="hover focus"
   data-bs-placement="top"
   data-bs-title="<i class='fa-solid fa-check-circle me-1'></i> Mark as Resolved"
   data-bs-content="
        <div class='text-start'>
            <p class='mb-2'>Click to mark this report as resolved.</p>
            <div class='alert alert-success alert-sm py-1 px-2 mb-0'>
                <i class='fa-solid fa-circle-info me-1'></i>
                <small>Status will be updated immediately</small>
            </div>
        </div>"
   data-bs-html="true"
   data-bs-container="body">
    <i class="fa-solid fa-check"></i>
</a>
								
								
								
								
                                <?php endif; ?>
                                
								<a href="<?= $_this_script_ ?>&action=takeaction&do=delete&id=<?= $row['id'] ?>" 
   class="btn btn-outline-danger btn-sm delete-report" 
   data-id="<?= $row['id'] ?>" 
   data-bs-toggle="popover" 
   data-bs-trigger="hover focus"
   data-bs-placement="top"
   data-bs-title="Delete Report"
   data-bs-content="Permanently delete this report. This action cannot be undone."
   data-bs-html="true"
   data-bs-container="body">
    <i class="fa-solid fa-trash"></i>
</a>
								
								
								
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
               
        <?php else: ?>
        
        <tr>
            <td colspan="8" class="text-center text-muted py-5">
                <div class="empty-state">
                    <i class="fa-solid fa-inbox fa-3x mb-3" style="color: #6c757d;"></i>
                    <h5 class="mb-2">No reports found</h5>
                    <p class="text-muted mb-4">Try adjusting your filters or check back later</p>
                    <a href="<?= $_this_script_ ?>&action=list" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-sync-alt me-1"></i> Clear Filters
                    </a>
                </div>
            </td>
        </tr>
        
        <?php endif; ?>
        
        </tbody>
        </table>
    </div>
        
        <?php if ($total > $perpage): ?>
        <div class="card-footer">
            <?php
            $totalPages = ceil($total / $perpage);
            $query = http_build_query([
                'action' => 'list',
                'type' => $type,
                'status' => $status,
                'search' => $search
            ]);
            ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $_this_script_ ?>&<?= $query ?>&page=<?= $page - 1 ?>">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $_this_script_ ?>&<?= $query ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $_this_script_ ?>&<?= $query ?>&page=<?= $page + 1 ?>">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
    .empty-state {
        padding: 2rem 0;
        text-align: center;
    }

    .empty-state i {
        opacity: 0.5;
        transition: opacity 0.3s ease;
        margin-bottom: 1rem;
    }

    .empty-state:hover i {
        opacity: 0.8;
    }

    .empty-state h5 {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        font-size: 0.95rem;
        max-width: 300px;
        margin: 0 auto 1rem;
        color: #6c757d;
    }

    .table-hover tbody tr:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        transition: all 0.2s ease;
    }

    .table-responsive {
        min-height: 200px;
    }

    /* Анимация для удаления строк */
    tr {
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
    
    /* Анимация мигания для высокоприоритетных репортов */
    .blink {
        animation: blink-animation 1s infinite;
    }
    
    @keyframes blink-animation {
        0%, 50% { opacity: 1; }
        51%, 100% { opacity: 0.5; }
    }
    
 
    </style>
    
    
	
	
	<script>
    document.addEventListener('DOMContentLoaded', function() {
   
    // Modal для удаления
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    let currentDeleteBtn = null;
    let currentReportId = null;
    let currentUrl = null;
    
    // Обработчики для кнопок удаления
    document.querySelectorAll('.delete-report').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            currentDeleteBtn = this;
            currentReportId = this.getAttribute('data-id');
            currentUrl = this.href;
            
            // Устанавливаем ID в модальное окно
            document.getElementById('reportIdText').textContent = `#${currentReportId}`;
            
            // Показываем модальное окно
            deleteModal.show();
        });
    });
    
    // Подтверждение удаления в модальном окне
    document.getElementById('confirmDelete').addEventListener('click', function() {
        const confirmBtn = this;
        const spinner = confirmBtn.querySelector('.spinner-border');
        
        // Показываем спиннер и блокируем кнопку
        spinner.classList.remove('d-none');
        confirmBtn.disabled = true;
        
        // Показываем загрузку на оригинальной кнопке
        if (currentDeleteBtn) {
            const originalHTML = currentDeleteBtn.innerHTML;
            currentDeleteBtn.setAttribute('data-original-html', originalHTML);
            currentDeleteBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>';
            currentDeleteBtn.classList.add('disabled');
        }
        
        // AJAX запрос на удаление
        fetch(currentUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Скрываем модальное окно
            deleteModal.hide();
            
            // Восстанавливаем кнопку подтверждения
            spinner.classList.add('d-none');
            confirmBtn.disabled = false;
            
            if (data.success) {
                // Успешное удаление
                const row = currentDeleteBtn.closest('tr');
                if (row) {
                    // Добавляем класс для анимации
                    row.classList.add('table-danger', 'deleting');
                    
                    // Анимация удаления с помощью Bootstrap классов
                    setTimeout(() => {
                        row.style.opacity = '0';
                        row.style.height = '0';
                        row.style.padding = '0';
                        row.style.overflow = 'hidden';
                        row.style.transition = 'all 0.3s ease';
                    }, 100);
                    
                    // Удаление из DOM после анимации
                    setTimeout(() => {
                        row.remove();
                        
                        // Показываем уведомление
                        showToast(
                            `<i class="bi bi-check-circle-fill me-2"></i>${data.message || 'Report deleted successfully'}`,
                            'success'
                        );
                        
                        // Проверяем пустую таблицу
                        const tbody = document.querySelector('tbody');
                        if (tbody && tbody.children.length === 0) {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="bi bi-inbox fs-1 mb-3"></i>
                                        <h5 class="mt-2">No reports found</h5>
                                        <p class="mb-0">Try adjusting your filters</p>
                                    </td>
                                </tr>
                            `;
                        }
                    }, 400);
                }
            } else {
                // Ошибка
                if (currentDeleteBtn) {
                    currentDeleteBtn.innerHTML = currentDeleteBtn.getAttribute('data-original-html');
                    currentDeleteBtn.classList.remove('disabled');
                }
                
                showToast(
                    `<i class="bi bi-exclamation-circle-fill me-2"></i>${data.error || 'Failed to delete report'}`,
                    'danger'
                );
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            
            // Скрываем модальное окно
            deleteModal.hide();
            
            // Восстанавливаем кнопки
            spinner.classList.add('d-none');
            confirmBtn.disabled = false;
            
            if (currentDeleteBtn) {
                currentDeleteBtn.innerHTML = currentDeleteBtn.getAttribute('data-original-html');
                currentDeleteBtn.classList.remove('disabled');
            }
            
            showToast(
                `<i class="bi bi-wifi-off me-2"></i>Connection error, please try again`,
                'warning'
            );
        });
    });
    
    // Сброс состояния модального окна при закрытии
    document.getElementById('deleteModal').addEventListener('hidden.bs.modal', function () {
        const confirmBtn = document.getElementById('confirmDelete');
        const spinner = confirmBtn.querySelector('.spinner-border');
        
        spinner.classList.add('d-none');
        confirmBtn.disabled = false;
        currentDeleteBtn = null;
    });
});
</script>
	
	
	
	
    <?php
    // Освобождаем ресурсы
    if ($result) $db->free_result($result);
}




















/**
 * Показать детали репорта
 */
function showReportDetails(int $report_id): void
{
    global $db, $parser, $parser_options, $_this_script_;
    
    if ($report_id <= 0) {
        echo '<div class="alert alert-danger">Invalid report ID</div>';
        return;
    }
    
    // ОБНОВЛЕННЫЙ SQL запрос с LEFT JOIN для forumpost
    $sql = "SELECT r.*, 
                   u1.username as reporter_name, u1.email as reporter_email,
                   u2.username as reported_user_name, u2.email as reported_user_email,
                   u3.username as dealtby_name,
                   t.name as torrent_name,
                   c.text as comment_text, c.torrent as comment_torrent_id,
                   -- Добавляем поля для forumpost
                   f.name as forum_name, f.fid as forum_db_id,
                   th.subject as thread_subject, th.tid as thread_db_id
            FROM reports r
            LEFT JOIN users u1 ON r.addedby = u1.id
            LEFT JOIN users u2 ON r.reported_user_id = u2.id
            LEFT JOIN users u3 ON r.dealtby = u3.id
            LEFT JOIN torrents t ON r.type = 'torrent' AND r.reported_id = t.id
            LEFT JOIN comments c ON r.type = 'comment' AND r.reported_id = c.id
            LEFT JOIN tsf_forums f ON r.type = 'forumpost' AND r.forum_id = f.fid
            LEFT JOIN tsf_threads th ON r.type = 'forumpost' AND r.thread_id = th.tid
            WHERE r.id = ?";
            
    $result = $db->sql_query_prepared($sql, [$report_id]);
    
    if (!$result) {
        echo '<div class="alert alert-danger">Error loading report</div>';
        return;
    }
    
    $report = $db->fetch_array($result);
    
    if (!$report) {
        echo '<div class="alert alert-danger">Report not found</div>';
        return;
    }
    ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Report Details #<?= $report['id'] ?></h5>
                    <div class="btn-group">
                        <?php if (!$report['dealtwith']): ?>
                        <a href="<?= $_this_script_ ?>&action=takeaction&do=resolve&id=<?= $report['id'] ?>" 
                           class="btn btn-success btn-sm">
                            <i class="fa-solid fa-check me-1"></i> Mark as Resolved
                        </a>
                        <?php endif; ?>
                        <a href="<?= $_this_script_ ?>&action=list" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back to List
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <?= renderReportDetails($report) ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Форма действий -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-cogs me-2"></i>Actions</h6>
                </div>
                <div class="card-body">
                    <?= renderActionForm($report['id'], $report['type']) ?>
                </div>
            </div>
            
            <!-- Статистика пользователя -->
            <?php if ($report['reported_user_id']): ?>
            <?= renderUserReportStats($report['reported_user_id'], $report['reported_user_name']) ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    // Если это forumpost, получаем дополнительную информацию о посте
    if ($report['type'] === 'forumpost') {
        echo renderForumPostDetails($report);
    }
	
	if ($report['type'] === 'user') {
    echo renderUserReportDetails($report);
    }
	
    
    $db->free_result($result);
}






/**
 * Рендеринг деталей поста форума
 */
function renderForumPostDetails(array $report): string
{
    global $BASEURL, $db, $parser, $parser_options, $_this_script_;
    
    // Получаем данные о посте
    $post_id = (int)$report['reported_id'];
    $post_data = getForumPostData($post_id, $report);
	
	$postlink = $BASEURL."/".get_post_link($post_data['pid'], $post_data['thread_id'])."#pid{$post_data['pid']}";
	
	
	$postdate = my_datee('relative', $post_data['dateline']);
	
    
    if (!$post_data) {
        return '<div class="alert alert-warning mt-4">Forum post data not found (post may have been deleted)</div>';
    }
    
   
    // Маппинг кодов правил с цветами
$rules_map = [
    'rule_1' => [
        'text' => 'Rule 1: No spamming or advertising',
        'color' => 'bg-danger',
        'icon' => 'fa-megaphone'
    ],
    'rule_2' => [
        'text' => 'Rule 2: No offensive language',
        'color' => 'bg-danger',
        'icon' => 'fa-comment-slash'
    ],
    'rule_3' => [
        'text' => 'Rule 3: No harassment or bullying',
        'color' => 'bg-danger',
        'icon' => 'fa-user-group-slash'
    ],
    'rule_4' => [
        'text' => 'Rule 4: Stay on topic',
        'color' => 'bg-warning',
        'icon' => 'fa-signs-post'
    ],
    'rule_5' => [
        'text' => 'Rule 5: No warez or illegal content',
        'color' => 'bg-danger',
        'icon' => 'fa-ban'
    ],
    'rule_6' => [
        'text' => 'Rule 6: Respect other members',
        'color' => 'bg-warning',
        'icon' => 'fa-handshake'
    ],
    'rule_7' => [
        'text' => 'Rule 7: No double posting',
        'color' => 'bg-info',
        'icon' => 'fa-copy'
    ],
    'rule_8' => [
        'text' => 'Rule 8: Use appropriate language',
        'color' => 'bg-warning',
        'icon' => 'fa-language'
    ],
];

// Получаем данные правила для отображения
$rule_data = !empty($report['rule_violation']) && isset($rules_map[$report['rule_violation']])
    ? $rules_map[$report['rule_violation']]
    : null;
	
	
	
   
    
    ob_start(); ?>
    
    <div class="card mt-4 border-success">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fa-solid fa-comments me-2"></i> Forum Post Details</h6>
            <div class="btn-group">
                <span class="badge bg-light text-dark">
                    <i class="fa-solid fa-hashtag me-1"></i> Post ID: <?= $post_data['pid'] ?>
                </span>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Информация о посте -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Post Information</h6>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Post ID:</th>
                            <td>#<?= $post_data['pid'] ?></td>
                        </tr>
                        <tr>
                            <th>Author:</th>
                            <td>
                                <a href="user-<?= $post_data['author_id'] ?>.html" target="_blank" class="text-decoration-none">
                                    <i class="fa-solid fa-user me-1"></i>
                                    <?= htmlspecialchars($post_data['author_name'] ?? 'Unknown') ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Post Date:</th>
                            <td><?= $postdate ?></td>
                        </tr>
                        <?php if (!empty($post_data['subject'])): ?>
                        <tr>
                            <th>Subject:</th>
                            <td><strong><?= htmlspecialchars($post_data['subject']) ?></strong></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <h6>Forum Information</h6>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Forum:</th>
                            <td>
                                <a href="forumdisplay.php?fid=<?= $post_data['forum_id'] ?>" target="_blank" class="text-decoration-none">
                                    <i class="fa-solid fa-comments me-1"></i>
                                    <?= htmlspecialchars($post_data['forum_name'] ?? 'Unknown Forum') ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Thread:</th>
                            <td>
                                <a href="showthread.php?tid=<?= $post_data['thread_id'] ?>" target="_blank" class="text-decoration-none">
                                    <i class="fa-solid fa-file-alt me-1"></i>
                                    <?= htmlspecialchars($post_data['thread_subject'] ?? 'Unknown Thread') ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Thread ID:</th>
                            <td><?= $post_data['thread_id'] ?></td>
                        </tr>
                        
						
						<?php if ($rule_data): ?>
<tr>
    <th>Rule Violation:</th>
    <td>
        <span class="badge <?= $rule_data['color'] ?> text-white" 
              title="Code: <?= htmlspecialchars($report['rule_violation']) ?>">
            <i class="fa-solid <?= $rule_data['icon'] ?> me-1"></i>
            <?= htmlspecialchars($rule_data['text']) ?>
        </span>
        <?php if ($report['rule_violation'] === 'rule_7'): ?>
        <small class="text-muted ms-2">
            <i class="fa-solid fa-circle-info"></i> Posting multiple times in a row
        </small>
        <?php endif; ?>
    </td>
</tr>
<?php endif; ?>
						
						
						
                    </table>
                </div>
            </div>
            
            <!-- Содержимое поста -->
            <h6>Post Content</h6>
            <div class="card border-warning mb-3">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-comment-dots me-1"></i> Reported Post Content</span>
                    <div class="btn-group">
                        <span class="badge bg-dark">
                            <i class="fa-solid fa-eye me-1"></i> Views: <?= (int)$post_data['views'] ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($post_data['message'])): ?>
                        <div class="forum-post-content">
                            <?= $parser->parse_message($post_data['message'], $parser_options) ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted">Post content is empty</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Действия с постом -->
            <h6>Post Actions</h6>
            <div class="row g-2">
                <div class="col-md-6">
                   <a href="<?= htmlspecialchars($postlink) ?>"  
                       target="_blank" class="btn btn-outline-primary w-100">
                        <i class="fa-solid fa-external-link-alt me-1"></i> View in Forum
                    </a>
                </div>
                <div class="col-md-6">
                    <div class="dropdown">
                        <button class="btn btn-outline-danger w-100 dropdown-toggle" type="button" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-trash me-1"></i> Moderate Post
                        </button>
                        <ul class="dropdown-menu w-100">
                            <li>
                                <a class="dropdown-item text-danger" href="#"
                                   onclick="if(confirm('Delete this forum post permanently?')) {
                                       window.location.href='<?= htmlspecialchars($_this_script_) ?>&action=takeaction&do=deleteforumpost&id=<?= $report['id'] ?>';
                                   }">
                                    <i class="fa-solid fa-trash me-2"></i> Delete Post
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item text-warning" href="#"
                                   onclick="if(confirm('Edit this forum post?')) {
                                       window.open('editpost.php?pid=<?= $post_data['pid'] ?>', '_blank');
                                   }">
                                    <i class="fa-solid fa-edit me-2"></i> Edit Post
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-info" href="#"
                                   onclick="if(confirm('Warn the author of this post?')) {
                                       window.open('warn.php?uid=<?= $post_data['author_id'] ?>', '_blank');
                                   }">
                                    <i class="fa-solid fa-exclamation-triangle me-2"></i> Warn Author
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Информация о модерации -->
            <?php if (!empty($post_data['visible'])): ?>
            <div class="alert alert-info mt-3 small">
                <i class="fa-solid fa-info-circle me-1"></i>
                <strong>Post Status:</strong> 
                <?= match((int)$post_data['visible']) {
                    0 => '<span class="badge bg-danger">Deleted/Hidden</span>',
                    1 => '<span class="badge bg-success">Visible</span>',
                    2 => '<span class="badge bg-warning">Unapproved</span>',
                    default => 'Unknown'
                } ?>
                
                <?php if (!empty($post_data['moderated'])): ?>
                <br><strong>Moderated:</strong> <?= htmlspecialchars($post_data['moderated']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    .forum-post-content {
        max-height: 400px;
        overflow-y: auto;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }
    
    .forum-post-content img {
        max-width: 100%;
        height: auto;
    }
    
    .forum-post-content pre {
        background: #2b2b2b;
        color: #f8f8f2;
        padding: 10px;
        border-radius: 3px;
        overflow-x: auto;
    }
    </style>
    
    <?php
    return ob_get_clean();
}






/**
 * Показать ожидающие репорты
 */
function showPendingReports(): void
{
    global $db, $_this_script_;
    
    $result = $db->sql_query_prepared("
        SELECT r.*, u1.username as reporter_name, u2.username as reported_user_name
        FROM reports r
        LEFT JOIN users u1 ON r.addedby = u1.id
        LEFT JOIN users u2 ON r.reported_user_id = u2.id
        WHERE r.dealtwith = 0
        ORDER BY r.added DESC
        LIMIT 100
    ");
    ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Pending Reports (Require Attention)</h5>
        </div>
        
        <?php if ($result): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-warning">
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Reporter</th>
                        <th>Reported User</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $db->fetch_array($result)): ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td>
                            <span class="badge bg-<?= getTypeColor($row['type']) ?>">
                                <?= ucfirst($row['type']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(truncateString($row['reason'], 30)) ?></td>
                        <td><?= htmlspecialchars($row['reporter_name'] ?? 'User #' . $row['addedby']) ?></td>
                        <td><?= htmlspecialchars($row['reported_user_name'] ?? 'User #' . $row['reported_user_id']) ?></td>
                        <td><?= date('Y-m-d H:i', (int)$row['added']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= $_this_script_ ?>&action=view&id=<?= $row['id'] ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="<?= $_this_script_ ?>&action=takeaction&do=resolve&id=<?= $row['id'] ?>" 
                                   class="btn btn-outline-success">
                                    <i class="fa-solid fa-check"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card-body text-center text-success py-5">
            <i class="fa-solid fa-check-circle fa-3x mb-3"></i>
            <h5>No pending reports!</h5>
            <p class="mb-0">All reports have been resolved.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php
    if ($result) $db->free_result($result);
}

/**
 * Показать решенные репорты
 */
function showResolvedReports(): void
{
    global $db, $_this_script_;
    
    $result = $db->sql_query_prepared("
        SELECT r.*, u1.username as reporter_name, u2.username as reported_user_name, u3.username as dealtby_name
        FROM reports r
        LEFT JOIN users u1 ON r.addedby = u1.id
        LEFT JOIN users u2 ON r.reported_user_id = u2.id
        LEFT JOIN users u3 ON r.dealtby = u3.id
        WHERE r.dealtwith = 1
        ORDER BY r.updated_at DESC
        LIMIT 50
    ");
    ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Resolved Reports</h5>
        </div>
        
        <?php if ($result): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-success">
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Reporter</th>
                        <th>Reported User</th>
                        <th>Resolved By</th>
                        <th>Date Resolved</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $db->fetch_array($result)): ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td>
                            <span class="badge bg-<?= getTypeColor($row['type']) ?>">
                                <?= ucfirst($row['type']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(truncateString($row['reason'], 25)) ?></td>
                        <td><?= htmlspecialchars($row['reporter_name'] ?? 'User #' . $row['addedby']) ?></td>
                        <td><?= htmlspecialchars($row['reported_user_name'] ?? 'User #' . $row['reported_user_id']) ?></td>
                        <td><?= htmlspecialchars($row['dealtby_name']) ?></td>
                        <td><?= date('Y-m-d H:i', (int)$row['updated_at']) ?></td>
                        <td>
                            <a href="<?= $_this_script_ ?>&action=view&id=<?= $row['id'] ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card-body text-center text-muted py-5">
            <i class="fa-solid fa-inbox fa-3x mb-3"></i>
            <h5>No resolved reports found</h5>
        </div>
        <?php endif; ?>
    </div>
    <?php
    if ($result) $db->free_result($result);
}

/**
 * Показать статистику
 */
function showStatistics(): void
{
    global $db, $_this_script_;
    
    // Статистика за последние 30 дней
    $thirty_days_ago = time() - (30 * 24 * 60 * 60);
    
    $stats_result = $db->sql_query_prepared("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN dealtwith = 1 THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN dealtwith = 0 THEN 1 ELSE 0 END) as pending,
            COUNT(DISTINCT addedby) as unique_reporters,
            COUNT(DISTINCT reported_user_id) as unique_reported_users
        FROM reports 
        WHERE added > ?
    ", [$thirty_days_ago]);
    
    $stats = $stats_result ? $db->fetch_array($stats_result) : [
        'total' => 0, 
        'resolved' => 0, 
        'pending' => 0, 
        'unique_reporters' => 0, 
        'unique_reported_users' => 0
    ];
    
    // Статистика по типам
    $type_stats_result = $db->sql_query_prepared("
        SELECT type, 
               COUNT(*) as count,
               SUM(CASE WHEN dealtwith = 1 THEN 1 ELSE 0 END) as resolved
        FROM reports 
        WHERE added > ?
        GROUP BY type
        ORDER BY count DESC
    ", [$thirty_days_ago]);
    
    // Топ пользователей по репортам
    $top_reported_result = $db->sql_query_prepared("
        SELECT reported_user_id, u.username, COUNT(*) as report_count
        FROM reports r
        LEFT JOIN users u ON r.reported_user_id = u.id
        WHERE reported_user_id > 0
        GROUP BY reported_user_id
        ORDER BY report_count DESC
        LIMIT 10
    ");
    
    // Топ репортеров
    $top_reporters_result = $db->sql_query_prepared("
        SELECT addedby, u.username, COUNT(*) as report_count
        FROM reports r
        LEFT JOIN users u ON r.addedby = u.id
        WHERE addedby > 0
        GROUP BY addedby
        ORDER BY report_count DESC
        LIMIT 10
    ");
    ?>
    
    <div class="row">
        <!-- Карточки статистики -->
        <?php foreach ([
            ['Primary', 'Total Reports (30 days)', $stats['total'] ?? 0],
            ['Success', 'Resolved', $stats['resolved'] ?? 0],
            ['Warning', 'Pending', $stats['pending'] ?? 0],
            ['Info', 'Unique Reporters', $stats['unique_reporters'] ?? 0]
        ] as [$color, $label, $value]): ?>
        <div class="col-md-3 mb-4">
            <div class="card bg-<?= strtolower($color) ?> text-<?= $color === 'Warning' ? 'dark' : 'white' ?>">
                <div class="card-body text-center py-4">
                    <div class="display-5 fw-bold"><?= $value ?></div>
                    <div><?= $label ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="row">
        <!-- Статистика по типам -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-chart-pie me-2"></i>Reports by Type (30 days)</h6>
                </div>
                <div class="card-body">
                    <?php if ($type_stats_result): ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Total</th>
                                <th>Resolved</th>
                                <th>Pending</th>
                                <th>% Resolved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $db->fetch_array($type_stats_result)): 
                                $pending = (int)$row['count'] - (int)$row['resolved'];
                                $percent = $row['count'] > 0 ? round(((int)$row['resolved'] / (int)$row['count']) * 100) : 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?= getTypeColor($row['type']) ?>">
                                        <?= ucfirst($row['type']) ?>
                                    </span>
                                </td>
                                <td><?= $row['count'] ?></td>
                                <td><span class="text-success"><?= $row['resolved'] ?></span></td>
                                <td><span class="text-warning"><?= $pending ?></span></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-success" style="width: <?= $percent ?>%">
                                            <?= $percent ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-muted text-center">No type statistics available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Топ репортируемых пользователей -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-user-slash me-2"></i>Most Reported Users</h6>
                </div>
                <div class="card-body">
                    <?php if ($top_reported_result): ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Report Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $db->fetch_array($top_reported_result)): ?>
                            <tr>
                                <td>
                                    <a href="user-<?= $row['reported_user_id'] ?>.html" target="_blank">
                                        <?= htmlspecialchars($row['username'] ?? 'User #' . $row['reported_user_id']) ?>
                                    </a>
                                </td>
                                <td><span class="badge bg-danger"><?= $row['report_count'] ?></span></td>
                                <td>
                                    <a href="<?= $_this_script_ ?>&action=list&search=<?= urlencode($row['username'] ?? '') ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        View Reports
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-muted text-center">No user statistics available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Топ репортеров -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-user-check me-2"></i>Top Reporters</h6>
                </div>
                <div class="card-body">
                    <?php if ($top_reporters_result): ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Reports Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $db->fetch_array($top_reporters_result)): ?>
                            <tr>
                                <td>
                                    <a href="user-<?= $row['addedby'] ?>.html" target="_blank">
                                        <?= htmlspecialchars($row['username'] ?? 'User #' . $row['addedby']) ?>
                                    </a>
                                </td>
                                <td><span class="badge bg-info"><?= $row['report_count'] ?></span></td>
                                <td>
                                    <a href="<?= $_this_script_ ?>&action=list&search=<?= urlencode($row['username'] ?? '') ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        View Reports
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-muted text-center">No reporter statistics available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Быстрые действия -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= $_this_script_ ?>&action=pending" class="btn btn-warning">
                            <i class="fa-solid fa-clock me-2"></i> View Pending Reports
                        </a>
                        <a href="<?= $_this_script_ ?>&action=list&export=csv" class="btn btn-secondary">
                            <i class="fa-solid fa-file-export me-2"></i> Export All Reports (CSV)
                        </a>
                        
                        <button class="btn btn-danger" 
                             onclick="if(confirm('Clear all resolved reports?')) location.href='<?= htmlspecialchars($_this_script_) ?>&action=takeaction&do=clearold'">
                             <i class="fa-solid fa-broom me-2"></i> Clear Old Resolved Reports
                        </button>
						
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    // Освобождаем ресурсы
    if ($stats_result) $db->free_result($stats_result);
    if ($type_stats_result) $db->free_result($type_stats_result);
    if ($top_reported_result) $db->free_result($top_reported_result);
    if ($top_reporters_result) $db->free_result($top_reporters_result);
}

// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================

/**
 * Обрезать строку
 */
function truncateString(string $string, int $length = 30): string
{
    return mb_strlen($string) > $length ? mb_substr($string, 0, $length) . '...' : $string;
}

/**
 * Рендеринг деталей репорта
 */

function renderReportDetails(array $report): string
{
    global $BASEURL, $parser, $parser_options, $_this_script_;
    
    // Используем функцию get_report_reasons_map для получения нужного маппинга
    $reasons_map = get_report_reasons_map($report['type']);
    
    // Получаем данные о причине
    $reason_data = isset($reasons_map[$report['reason']]) 
        ? $reasons_map[$report['reason']] 
        : null;
    
    $commentlink = $BASEURL."/".get_comment_link($report['reported_id'], $report['comment_torrent_id'])."#pid{$report['reported_id']}";
	
	$TorentLink = $BASEURL."/".get_torrent_link($report['reported_id']);
	
	$adss = my_datee('relative', $report['added']);
	
	
	$resolveds = my_datee('relative', $report['updated_at']);
	

    
    ob_start(); ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <h6>Basic Information</h6>
            <table class="table table-sm">
                <tr>
                    <th width="40%">Report ID:</th>
                    <td>#<?= $report['id'] ?></td>
                </tr>
                <tr>
                    <th>Type:</th>
                    <td>
                        <span class="badge bg-<?= getTypeColor($report['type']) ?>">
                            <i class="fa-solid fa-<?= 
                                $report['type'] === 'comment' ? 'comment' : 
                                ($report['type'] === 'torrent' ? 'download' : 'file')
                            ?> me-1"></i>
                            <?= ucfirst($report['type']) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Reported Item ID:</th>
                    <td>
                        <?= $report['reported_id'] ?>
                        <?php if ($report['type'] === 'torrent' && $report['torrent_name']): ?>
                        <a href="<?= $TorentLink ?>" 
                           class="btn btn-sm btn-outline-primary ms-2">
                            <i class="fa-solid fa-external-link-alt"></i> View Torrent
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Reason:</th>
                    <td>
                        <?php if ($reason_data): ?>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <!-- Основной бейдж причины -->
                            
							
							<span class="badge <?= $reason_data['color'] ?> text-white px-3 py-2 d-flex align-items-center reason-badge"
      data-bs-toggle="popover"
      data-bs-trigger="hover focus"
      data-bs-placement="top"
      data-bs-title="Report Details"
      data-bs-content="
        <div class='text-start'>
            <div class='d-flex align-items-center mb-3'>
                <div class='me-3'>
                    <div class='bg-<?= str_replace('bg-', '', $reason_data['color']) ?> bg-opacity-10 p-2 rounded-circle'>
                        <i class='fa-solid <?= $reason_data['icon'] ?> text-<?= str_replace('bg-', '', $reason_data['color']) ?>'></i>
                    </div>
                </div>
                <div>
                    <h6 class='mb-0'><?= htmlspecialchars($reason_data['text']) ?></h6>
                    <small class='text-muted'><?= htmlspecialchars($reason_data['category']) ?></small>
                </div>
            </div>
            
            <div class='border-top pt-2'>
                <div class='row small text-muted'>
                    <div class='col-6'>
                        <i class='fa-solid fa-layer-group me-1'></i>
                        Category
                    </div>
                    <div class='col-6 text-end'>
                        <?= htmlspecialchars($reason_data['category']) ?>
                    </div>
                </div>
                
                <?php if (!empty($reason_data['severity'])): ?>
                <div class='row small text-muted mt-1'>
                    <div class='col-6'>
                        <i class='fa-solid fa-triangle-exclamation me-1'></i>
                        Severity
                    </div>
                    <div class='col-6 text-end'>
                        <span class='badge bg-<?= $reason_data['severity_color'] ?? 'warning' ?>'>
                            <?= htmlspecialchars($reason_data['severity']) ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($reason_data['count'])): ?>
                <div class='row small text-muted mt-1'>
                    <div class='col-6'>
                        <i class='fa-solid fa-chart-bar me-1'></i>
                        Total Reports
                    </div>
                    <div class='col-6 text-end'>
                        <?= (int)$reason_data['count'] ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>"
      data-bs-html="true"
      data-bs-container="body">
    <i class="fa-solid <?= $reason_data['icon'] ?> me-2 fs-6"></i>
    <span class="fw-medium"><?= htmlspecialchars($reason_data['text']) ?></span>
</span>
							
							
							
                            
                            <!-- Бейдж серьезности -->
                            <?php if ($reason_data['severity'] !== 'unknown'): ?>
                            <span class="badge bg-<?= 
                                $reason_data['severity'] === 'high' ? 'danger' : 
                                ($reason_data['severity'] === 'medium' ? 'warning' : 'info')
                            ?>-subtle text-<?= 
                                $reason_data['severity'] === 'high' ? 'danger' : 
                                ($reason_data['severity'] === 'medium' ? 'warning' : 'info')
                            ?> border border-<?= 
                                $reason_data['severity'] === 'high' ? 'danger' : 
                                ($reason_data['severity'] === 'medium' ? 'warning' : 'info')
                            ?>">
                                <i class="fa-solid fa-<?= 
                                    $reason_data['severity'] === 'high' ? 'fire' : 
                                    ($reason_data['severity'] === 'medium' ? 'clock' : 'info-circle')
                                ?> me-1"></i>
                                <?= ucfirst($reason_data['severity']) ?> Priority
                            </span>
                            <?php endif; ?>
                            
                            <!-- Категория -->
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid fa-tag me-1"></i>
                                <?= htmlspecialchars($reason_data['category']) ?>
                            </span>
                        </div>
                        <?php else: ?>
                        <!-- Если причина не найдена в маппинге -->
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary">
                                <i class="fa-solid fa-question me-1"></i>
                                <?= htmlspecialchars($report['reason']) ?>
                            </span>
                            <small class="text-muted">
                                Unknown reason code
                            </small>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td>
                        <?php if ($report['dealtwith']): ?>
                        <span class="badge bg-success">
                            <i class="fa-solid fa-check me-1"></i>Resolved
                        </span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark">
                            <i class="fa-solid fa-clock me-1"></i>Pending
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6>Timestamps</h6>
            <table class="table table-sm">
                <tr>
                    <th width="40%">Reported:</th>
                    <td><?= $adss ?></td>
                </tr>
                <tr>
                    <th>IP Address:</th>
                    <td>
                        <?= htmlspecialchars($report['ip_address'] ?? '') ?>
                        <?php if (!empty($report['ip_address'])): ?>
                        <a href="<?= $_this_script_ ?>&action=iplookup&ip=<?= urlencode($report['ip_address']) ?>" 
                           class="btn btn-sm btn-outline-info ms-1" title="IP Lookup">
                            <i class="fa-solid fa-search"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($report['dealtwith']): ?>
                <tr>
                    <th>Resolved:</th>
                    <td><?= $resolveds ?></td>
                </tr>
                <?php if ($report['dealtby_name']): ?>
                <tr>
                    <th>Resolved by:</th>
                    <td><?= htmlspecialchars($report['dealtby_name']) ?></td>
                </tr>
                <?php endif; ?>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <?php if ($reason_data): ?>
    <!-- Рекомендации по обработке -->
    <div class="alert alert-<?= 
        $reason_data['severity'] === 'high' ? 'danger' : 
        ($reason_data['severity'] === 'medium' ? 'warning' : 'info')
    ?> mt-3 mb-3" role="alert">
        <div class="d-flex align-items-start">
            <i class="fa-solid <?= 
                $reason_data['severity'] === 'high' ? 'fire text-danger' : 
                ($reason_data['severity'] === 'medium' ? 'clock text-warning' : 'info-circle text-info')
            ?> fa-2x me-3 mt-1"></i>
            <div class="flex-grow-1">
                <h6 class="alert-heading mb-2">
                    <?= 
                        $reason_data['severity'] === 'high' ? '🚨 High Priority Action Required' : 
                        ($reason_data['severity'] === 'medium' ? '⚠️ Medium Priority Review' : 'ℹ️ Standard Review')
                    ?>
                </h6>
                
                <?php 
                // Рекомендации по обработке
                if ($report['type'] === 'comment') {
                    $comment_recommendations = [
                        'spam' => 'Consider deleting the comment and warning the user about spam policies.',
                        'offensive' => 'Review the language used and consider deletion with a user warning.',
                        'harassment' => 'Immediate action recommended. Delete comment and consider user ban.',
                        'hate_speech' => 'Zero tolerance policy. Delete immediately and consider permanent ban.',
                        'inappropriate' => 'Review content against community guidelines. Edit or delete as needed.',
                        'spoiler' => 'Consider adding spoiler tags or moving to appropriate section.',
                        'misinformation' => 'Verify information and add correction notice if false.',
                        'off_topic' => 'Move to appropriate thread or delete if completely irrelevant.',
                        'personal_info' => 'Delete immediately. Do not share personal information.',
                        'other' => 'Review based on description provided.'
                    ];
                    $recommendation = $comment_recommendations[$report['reason']] ?? 'Review based on provided information.';
                } elseif ($report['type'] === 'torrent') {
                    $torrent_recommendations = [
                        'copyright' => 'Verify copyright claim. Remove torrent if infringement is confirmed.',
                        'malware' => 'Scan files for malware. Remove immediately if infected.',
                        'fake' => 'Verify content authenticity. Remove if fake or mislabeled.',
                        'broken' => 'Check tracker and seed status. Mark as broken if dead.',
                        'inappropriate' => 'Review against content policies. Remove if violates guidelines.',
                        'other' => 'Review based on description provided.'
                    ];
                    $recommendation = $torrent_recommendations[$report['reason']] ?? 'Review based on provided information.';
                } else {
                    $recommendation = 'Review based on provided information.';
                }
                
                $urgency = $reason_data['severity'] === 'high' ? 'Requires immediate attention.' : 
                          ($reason_data['severity'] === 'medium' ? 'Review within 24 hours.' : 'Review when available.');
                ?>
                
                <p class='mb-1'><strong>Recommended Action:</strong> <?= $recommendation ?></p>
                <p class='mb-0'><strong>Urgency:</strong> <?= $urgency ?></p>
                
                <hr class="my-2">
                <p class="mb-0 small">
                    <strong>Category:</strong> <?= htmlspecialchars($reason_data['category']) ?> 
                    | <strong>Type:</strong> <?= ucfirst($report['type']) ?>
                    | <strong>Reported:</strong> <?= date('H:i', (int)$report['added']) ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <h6>Reporter Information</h6>
            <table class="table table-sm">
                <tr>
                    <th width="40%">Username:</th>
                    <td>
                        <?php if ($report['addedby']): ?>
                        <a href="user-<?= $report['addedby'] ?>.html" target="_blank">
                            <?= htmlspecialchars($report['reporter_name'] ?? 'User #' . $report['addedby']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">Guest</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>User ID:</th>
                    <td><?= $report['addedby'] ?: 'N/A' ?></td>
                </tr>
                <?php if ($report['reporter_email']): ?>
                <tr>
                    <th>Email:</th>
                    <td>
                        <a href="mailto:<?= htmlspecialchars($report['reporter_email']) ?>">
                            <?= htmlspecialchars($report['reporter_email']) ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6>Reported User</h6>
            <table class="table table-sm">
                <?php if ($report['reported_user_id']): ?>
                <tr>
                    <th width="40%">Username:</th>
                    <td>
                        <a href="user-<?= $report['reported_user_id'] ?>.html" target="_blank">
                            <?= htmlspecialchars($report['reported_user_name'] ?? 'User #' . $report['reported_user_id']) ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th>User ID:</th>
                    <td><?= $report['reported_user_id'] ?></td>
                </tr>
                <?php if ($report['reported_user_email']): ?>
                <tr>
                    <th>Email:</th>
                    <td>
                        <a href="mailto:<?= htmlspecialchars($report['reported_user_email']) ?>">
                            <?= htmlspecialchars($report['reported_user_email']) ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
                <?php else: ?>
                <tr>
                    <td colspan="2" class="text-muted text-center">No user information available</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <h6>Report Description</h6>
    <div class="card bg-light mb-3">
        <div class="card-body">
            <?php if (!empty($report['description'])): ?>
                <?= $parser->parse_message($report['description'], $parser_options) ?>
            <?php else: ?>
                <span class="text-muted">No additional details provided</span>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($report['type'] === 'comment' && !empty($report['comment_text'])): ?>
    <h6>Comment Content</h6>
    <div class="card border-<?= 
        $reason_data && $reason_data['severity'] === 'high' ? 'danger' : 
        ($reason_data && $reason_data['severity'] === 'medium' ? 'warning' : 'warning')
    ?> mb-3">
        <div class="card-header bg-<?= 
            $reason_data && $reason_data['severity'] === 'high' ? 'danger' : 
            ($reason_data && $reason_data['severity'] === 'medium' ? 'warning' : 'warning')
        ?> text-white d-flex justify-content-between align-items-center">
            <span>
                <i class="fa-solid <?= $reason_data['icon'] ?? 'fa-comment' ?> me-1"></i>
                Reported Comment: <?= $reason_data ? $reason_data['text'] : '' ?>
            </span>
			
			
	
			
            
            <!-- Быстрые действия для комментариев -->
            <div class="btn-group btn-group-sm">
                		
				<a href="<?= $commentlink ?>" 
  class="btn btn-outline-light"
   data-bs-toggle="popover"
   data-bs-trigger="hover"
   data-bs-placement="top"
   data-bs-title="<i class='bi bi-chat-left me-2'></i>Comment #<?= $report['reported_id'] ?>"
   data-bs-content="
        <div class='text-start small'>
            <div class='mb-2'>
                <strong>Comment ID:</strong> <?= $report['reported_id'] ?>
            </div>
            <div class='mb-2'>
                <strong>Torrent ID:</strong> <?= $report['comment_torrent_id'] ?>
            </div>
            <div>
                <i class='bi bi-box-arrow-up-right me-1'></i>
                Opens in new tab
            </div>
        </div>"
   data-bs-html="true"
  target="_blank">
    <i class="fa-solid fa-external-link-alt me-1"></i> View
</a>
				
				
					
				
				
                
                <?php if ($reason_data && in_array($reason_data['severity'], ['high', 'medium'])): ?>
                <a href="<?= $_this_script_ ?>&action=takeaction&do=deletecomment&id=<?= $report['id'] ?>" 
                   class="btn btn-danger" onclick="return confirm('Delete this comment?')">
                    <i class="fa-solid fa-trash me-1"></i> Delete
                </a>
                <?php else: ?>
                <a href="<?= $_this_script_ ?>&action=takeaction&do=deletecomment&id=<?= $report['id'] ?>" 
                   class="btn btn-outline-light" onclick="return confirm('Delete this comment?')">
                    <i class="fa-solid fa-trash me-1"></i> Delete
                </a>
                <?php endif; ?>
                
                <?php if ($report['reported_user_id'] && $reason_data && $reason_data['severity'] === 'high'): ?>
                <a href="warn.php?uid=<?= $report['reported_user_id'] ?>&reason=<?= urlencode($reason_data['text']) ?>" 
                   class="btn btn-warning" target="_blank">
                    <i class="fa-solid fa-exclamation-triangle me-1"></i> Warn
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?= $parser->parse_message($report['comment_text'], $parser_options) ?>
			
            <?php if (!empty($report['comment_torrent_id'])): ?>
            <div class="mt-3"> 
				<a href="<?= $commentlink ?>" 
                   target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-external-link-alt me-1"></i> View in Context
                </a>

                <a href="<?= $_this_script_ ?>&action=takeaction&do=deletecomment&id=<?= $report['id'] ?>" 
                   class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this comment?')">
                    <i class="fa-solid fa-trash me-1"></i> Delete Comment
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif;
    
    
    ?>
 
    
    <?php
    return ob_get_clean();
}





















/**
 * Рендеринг формы действий
 */

function renderActionForm(int $report_id, string $report_type): string
{
    global $_this_script_;
	
	ob_start(); ?>
    <form method="POST" action="<?= $_this_script_ ?>&action=takeaction&id=<?= $report_id ?>">
        <input type="hidden" name="id" value="<?= $report_id ?>">
        
        <div class="mb-3">
            <label class="form-label">Action</label>
            <select name="do" class="form-select" required>
                <option value="">Select action...</option>
                <option value="resolve">Mark as Resolved</option>
                
                <?php if ($report_type === 'forumpost'): ?>
                <option value="deleteforumpost">Delete Forum Post</option>
                <option value="editforumpost">Edit Forum Post</option>
                <option value="warnpostauthor">Warn Post Author</option>
                <?php elseif ($report_type === 'comment'): ?>
                <option value="deletecomment">Delete Comment</option>
                <?php endif; ?>
                
                <option value="warn_user">Warn Reported User</option>
                <option value="ban_user">Ban Reported User</option>
                <option value="ignore">Ignore Report</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Notes (Optional)</label>
            <textarea name="notes" class="form-control" rows="3" 
                      placeholder="Add notes about how this report was handled..."></textarea>
        </div>
        
        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-check me-1"></i> Apply Action
            </button>
            <a href="<?= $_this_script_ ?>&action=takeaction&do=delete&id=<?= $report_id ?>" 
               class="btn btn-outline-danger" onclick="return confirm('Delete this report permanently?')">
                <i class="fa-solid fa-trash me-1"></i> Delete Report
            </a>
        </div>
    </form>
    <?php return ob_get_clean();
}

/**
 * Рендеринг статистики пользователя
 */
function renderUserReportStats(int $user_id, ?string $username): string
{
    global $db, $_this_script_;
    
    $user_stats_result = $db->sql_query_prepared("SELECT 
        COUNT(*) as total_reports,
        SUM(CASE WHEN dealtwith = 1 THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN dealtwith = 0 THEN 1 ELSE 0 END) as pending
        FROM reports WHERE reported_user_id = ?", 
        [$user_id]);
    
    $user_stats = $user_stats_result ? $db->fetch_array($user_stats_result) : [
        'total_reports' => 0, 
        'resolved' => 0, 
        'pending' => 0
    ];
    
    ob_start(); ?>
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="fa-solid fa-chart-bar me-2"></i>User Report History</h6>
        </div>
        <div class="card-body">
            <div class="text-center">
                <div class="display-6 text-primary"><?= $user_stats['total_reports'] ?></div>
                <div class="text-muted">Total Reports</div>
            </div>
            
            <div class="row mt-3">
                <div class="col-6 text-center">
                    <div class="text-success fw-bold"><?= $user_stats['resolved'] ?></div>
                    <small class="text-muted">Resolved</small>
                </div>
                <div class="col-6 text-center">
                    <div class="text-warning fw-bold"><?= $user_stats['pending'] ?></div>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="<?= $_this_script_ ?>&action=list&search=<?= urlencode($username ?? '') ?>" 
                   class="btn btn-sm btn-outline-primary w-100">
                    <i class="fa-solid fa-list me-1"></i> View All Reports for this User
                </a>
            </div>
        </div>
    </div>
    <?php 
    if ($user_stats_result) $db->free_result($user_stats_result);
    return ob_get_clean();
}








/**
 * Рендеринг деталей репорта для пользователей
 */
function renderUserReportDetails(array $report): string
{
    global $BASEURL, $parser, $parser_options, $_this_script_, $db;
    
    $user_id = (int)$report['reported_user_id'];
    
    // Получаем дополнительную информацию о пользователе
    $user_result = $db->sql_query_prepared("
        SELECT u.*, 
               COUNT(r2.id) as total_reports,
               COUNT(CASE WHEN r2.dealtwith = 1 THEN 1 END) as resolved_reports,
               COUNT(CASE WHEN r2.dealtwith = 0 THEN 1 END) as pending_reports
        FROM users u
        LEFT JOIN reports r2 ON u.id = r2.reported_user_id
        WHERE u.id = ?
        GROUP BY u.id
    ", [$user_id]);
    
    $user_info = $user_result ? $db->fetch_array($user_result) : null;
    
    ob_start(); ?>
    
    <!-- Дополнительная информация о пользователе -->
    <?php if ($user_info): ?>
    <div class="card border-primary mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fa-solid fa-user-circle me-2"></i> User Information</h6>
            <div class="btn-group">
                <a href="user-<?= $user_id ?>.html" target="_blank" class="btn btn-sm btn-light">
                    <i class="fa-solid fa-external-link-alt me-1"></i> View Profile
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>User Details</h6>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Username:</th>
                            <td>
                                <a href="user-<?= $user_id ?>.html" target="_blank" class="fw-bold">
                                    <?= htmlspecialchars($user_info['username'] ?? 'Unknown') ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>User ID:</th>
                            <td><?= $user_id ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td>
                                <?php if (!empty($user_info['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($user_info['email']) ?>">
                                    <?= htmlspecialchars($user_info['email']) ?>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">Not available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Registered:</th>
                            <td><?= my_datee('relative', $user_info['added']) ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <?php if ($user_info['enabled'] == 'yes'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Disabled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <h6>Report Statistics</h6>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Total Reports:</th>
                            <td><span class="badge bg-primary"><?= $user_info['total_reports'] ?? 0 ?></span></td>
                        </tr>
                        <tr>
                            <th>Resolved Reports:</th>
                            <td><span class="badge bg-success"><?= $user_info['resolved_reports'] ?? 0 ?></span></td>
                        </tr>
                        <tr>
                            <th>Pending Reports:</th>
                            <td><span class="badge bg-warning"><?= $user_info['pending_reports'] ?? 0 ?></span></td>
                        </tr>
                        <tr>
                            <th>Report Rate:</th>
                            <td>
                                <?php 
                                $days_registered = max(1, floor((time() - $user_info['added']) / 86400));
                                $report_rate = $days_registered > 0 ? ($user_info['total_reports'] / $days_registered) : 0;
                                ?>
                                <?= number_format($report_rate, 2) ?> reports/day
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Быстрые действия для пользователя -->
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?= $_this_script_ ?>&action=list&search=<?= urlencode($user_info['username'] ?? '') ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-list me-1"></i> View All Reports
                        </a>
                        <a href="warn.php?uid=<?= $user_id ?>&reason=<?= urlencode($report['reason']) ?>" 
                           class="btn btn-sm btn-outline-warning" target="_blank">
                            <i class="fa-solid fa-exclamation-triangle me-1"></i> Warn User
                        </a>
                        <button class="btn btn-sm btn-outline-danger" 
                                onclick="if(confirm('Ban user <?= htmlspecialchars($user_info['username'] ?? '') ?>?')) {
                                    window.open('bans.php?action=add&uid=<?= $user_id ?>', '_blank');
                                }">
                            <i class="fa-solid fa-ban me-1"></i> Ban User
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Детали репорта -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0"><i class="fa-solid fa-flag me-2"></i> Report Details</h6>
        </div>
        <div class="card-body">
            <?php
            // Парсим description для извлечения структурированных данных
            $description = $report['description'] ?? '';
            $parsed_data = parseUserReportDescription($description);
            ?>
            
            <h6>Report Description</h6>
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <?php if (!empty($parsed_data['formatted_description'])): ?>
                        <?= $parser->parse_message($parsed_data['formatted_description'], $parser_options) ?>
                    <?php else: ?>
                        <?= $parser->parse_message($description, $parser_options) ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Извлеченные данные -->
            <?php if (!empty($parsed_data['additional_info']) || !empty($parsed_data['evidence_links'])): ?>
            <h6>Additional Information</h6>
            <div class="row">
                <?php if (!empty($parsed_data['additional_info'])): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fa-solid fa-info-circle me-2"></i> Additional Info</h6>
                        </div>
                        <div class="card-body">
                            <?= nl2br(htmlspecialchars($parsed_data['additional_info'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($parsed_data['evidence_links'])): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fa-solid fa-link me-2"></i> Evidence Links</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $links = explode("\n", $parsed_data['evidence_links']);
                            foreach ($links as $link):
                                $link = trim($link);
                                if (!empty($link)):
                            ?>
                            <div class="mb-2">
                                <a href="<?= htmlspecialchars($link) ?>" target="_blank" class="text-decoration-none">
                                    <i class="fa-solid fa-external-link-alt me-1"></i>
                                    <?= htmlspecialchars(truncateString($link, 50)) ?>
                                </a>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- История репортов этого пользователя -->
            <?php if ($user_id): ?>
            <h6>Recent Reports for This User</h6>
            <?php
            $recent_reports_result = $db->sql_query_prepared("
                SELECT r.*, u.username as reporter_name
                FROM reports r
                LEFT JOIN users u ON r.addedby = u.id
                WHERE r.reported_user_id = ? AND r.id != ?
                ORDER BY r.added DESC
                LIMIT 5
            ", [$user_id, $report['id']]);
            
            if ($recent_reports_result && $db->num_rows($recent_reports_result) > 0):
            ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Reporter</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($recent = $db->fetch_array($recent_reports_result)): ?>
                        <tr>
                            <td><?= date('Y-m-d', (int)$recent['added']) ?></td>
                            <td>
                                <span class="badge bg-<?= getTypeColor($recent['type']) ?>">
                                    <?= ucfirst($recent['type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(truncateString($recent['reason'], 20)) ?></td>
                            <td><?= htmlspecialchars($recent['reporter_name'] ?? 'User #' . $recent['addedby']) ?></td>
                            <td>
                                <?php if ($recent['dealtwith']): ?>
                                <span class="badge bg-success">Resolved</span>
                                <?php else: ?>
                                <span class="badge bg-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= $_this_script_ ?>&action=view&id=<?= $recent['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle me-2"></i>
                This is the only report for this user.
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Форма быстрых действий -->
    <div class="card border-danger">
        <div class="card-header bg-danger text-white">
            <h6 class="mb-0"><i class="fa-solid fa-shield-alt me-2"></i> Moderation Actions</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <a href="warn.php?uid=<?= $user_id ?>&reason=Report%20#<?= $report['id'] ?>:<?= urlencode($report['reason']) ?>" 
                       class="btn btn-warning w-100" target="_blank">
                        <i class="fa-solid fa-exclamation-triangle me-1"></i> Issue Warning
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="edituser.php?action=edituser&userid=<?= $user_id ?>" 
                       class="btn btn-info w-100" target="_blank">
                        <i class="fa-solid fa-user-edit me-1"></i> Edit User
                    </a>
                </div>
                <div class="col-md-6">
                    <button class="btn btn-outline-danger w-100" 
                            onclick="if(confirm('Temporarily suspend this user?')) {
                                window.open('staff.php?act=users&do=suspend&uid=<?= $user_id ?>', '_blank');
                            }">
                        <i class="fa-solid fa-clock me-1"></i> Suspend User
                    </button>
                </div>
                <div class="col-md-6">
                    <button class="btn btn-danger w-100" 
                            onclick="if(confirm('Permanently ban this user?')) {
                                window.open('bans.php?action=add&uid=<?= $user_id ?>', '_blank');
                            }">
                        <i class="fa-solid fa-ban me-1"></i> Ban User
                    </button>
                </div>
            </div>
            
            <hr>
            
            <!-- Форма для пометки как решенного -->
            <form method="POST" action="<?= $_this_script_ ?>&action=takeaction" class="mt-3">
                <input type="hidden" name="do" value="resolve">
                <input type="hidden" name="id" value="<?= $report['id'] ?>">
                
                <div class="mb-3">
                    <label class="form-label">Resolution Notes</label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="Add notes about how this user report was handled..."></textarea>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-check me-1"></i> Mark as Resolved
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php
    if (isset($user_result)) $db->free_result($user_result);
    if (isset($recent_reports_result)) $db->free_result($recent_reports_result);
    
    return ob_get_clean();
}










?>

<script type="text/javascript" src="<?= htmlspecialchars($BASEURL ?? '') ?>/scripts/toast.js"></script>
<script type="text/javascript" src="<?= htmlspecialchars($BASEURL ?? '') ?>/scripts/popover.js"></script>

<script>
// Показываем сообщения об успехе/ошибке из URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    const successMessages = {
        'resolved': 'Report marked as resolved',
        'deleted': 'Report deleted successfully',
        'comment_deleted': 'Comment deleted and report resolved'
    };
    
    const errorMessages = {
        'invalid_id': 'Invalid report ID',
        'not_found': 'Report not found',
        'invalid_action': 'Invalid action'
    };
    
    if (urlParams.has('success')) {
        const msg = urlParams.get('success');
        if (successMessages[msg]) {
            showToast(successMessages[msg], 'success');
        }
    }
    
    if (urlParams.has('error')) {
        const msg = urlParams.get('error');
        if (errorMessages[msg]) {
            showToast(errorMessages[msg], 'error');
        }
    }
});
</script>







<!-- Modal для подтверждения удаления -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Confirm Deletion
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete report <strong id="reportIdText"></strong>?</p>
                <p class="text-danger small">
                    <i class="bi bi-info-circle me-1"></i>
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    <i class="bi bi-trash"></i>Delete Report
                </button>
            </div>
        </div>
    </div>
</div>