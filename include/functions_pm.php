<?php

declare(strict_types=1);

/**
 * Возвращает строки раздела $section на языке $name (с кэшем).
 * Пустой язык считается английским. Если файла нет, отдаёт строки текущего языка.
 */
if (!function_exists('get_lang_section')) {
    function get_lang_section(string $section, string $name): array
    {
        global $lang;
        static $cache = [];

        if (!preg_match('/^[A-Za-z0-9_]+$/', $section)) {
            return [];
        }

        $name = str_replace(['/', '\\', '..'], '', trim($name));
        if ($name === '') {
            $name = 'english';
        }

        // Язык получателя совпадает с текущим: берём из $lang
        if ($name === $lang->language) {
            if (empty($lang->$section)) {
                $lang->load($section);
            }
            return (array)$lang->$section;
        }

        $key = $section . '|' . $name;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        // load() делает exit() при отсутствии файла, поэтому проверяем заранее
        if (!is_file($lang->path . '/' . $name . '/' . $section . '.lang.php')) {
            return (array)$lang->$section;
        }

        $userlang = new trackerlanguage();
        $userlang->set_path($lang->path);
        $userlang->set_language($name);
        $userlang->load($section); // сам заменит {1} на %1$s

        $strings = (array)($userlang->$section ?? []);

        return $cache[$key] = $strings ?: (array)$lang->$section;
    }
}

function send_pm(array $pm, int $fromid = 0, bool $admin_override = false): bool
{
    global $lang, $mybb, $db, $session, $CURUSER;

    // Как в MyBB: 'subject' => ['ключ', арг1, арг2...] собираем из языка получателя
    if (is_array($pm['subject'] ?? null) || is_array($pm['message'] ?? null)) {
        $t = get_lang_section((string)($pm['language_file'] ?? ''), (string)($pm['language'] ?? ''));

        foreach (['subject', 'message'] as $field) {
            if (is_array($pm[$field] ?? null)) {
                $args = $pm[$field];
                $key  = (string)array_shift($args);

                if (!isset($t[$key])) {
                    error_log("send_pm: language key '{$key}' not found (file=" . ($pm['language_file'] ?? '') . ", lang=" . ($pm['language'] ?? '') . ")");
                    return false;
                }

                try {
                    $pm[$field] = sprintf($t[$key], ...$args);
                } catch (Throwable $e) {
                    error_log("send_pm: sprintf failed for '{$key}': {$e->getMessage()}");
                    return false;
                }
            }
        }
    }

    // Проверка минимальных данных
    if (empty($pm['subject']) || empty($pm['message']) || empty($pm['touid']) || (empty($pm['receivepms']) && !$admin_override)) {
        return false;
    }

    require_once INC_PATH . "/datahandlers/pm.php";

    $pmhandler = new PMDataHandler();

    $subject = $pm['subject'];
    $message = $pm['message'];
    $toid = $pm['touid'];

    // Получаем получателей
    $recipients_to = is_array($toid) ? $toid : [$toid];
    $recipients_bcc = [];

    // Workaround для PHP 8: специальный sender
    if (isset($pm['sender']['uid']) && $pm['sender']['uid'] === -1 && $fromid === -1) {
        $sender = [
            "uid" => 0,
            "username" => ''
        ];
    }

    // Определяем ID отправителя
    if ($fromid === 0 && isset($CURUSER['id'])) {
        $fromid = (int)$CURUSER['id'];
    } elseif ($fromid < 0) {
        $fromid = 0;
    }

    // Структура PM
    $pm_data = [
        "subject" => $subject,
        "message" => $message,
        "icon" => -1,
        "fromid" => $fromid,
        "toid" => $recipients_to,
        "bccid" => $recipients_bcc,
        "do" => '',
        "pmid" => ''
    ];

    if (isset($sender)) {
        $pm_data['sender'] = $sender;
    }

    if (isset($session)) {
        $pm_data['ipaddress'] = $session->packedip ?? '';
    }

    $pm_data['options'] = [
        "disablesmilies" => 0,
        "savecopy" => 0,
        "readreceipt" => 0
    ];

    $pm_data['saveasdraft'] = 0;

    // Admin override
    $pmhandler->admin_override = $admin_override;

    $pmhandler->set_data($pm_data);

    if ($pmhandler->validate_pm()) {
        $pmhandler->insert_pm();
        return true;
    }

    return false;
}