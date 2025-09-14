<?php



function send_pm(array $pm, int $fromid = 0, bool $admin_override = false): bool
{
    global $lang, $mybb, $db, $session, $CURUSER;

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
    $pmhandler->admin_override = (int)$admin_override;

    $pmhandler->set_data($pm_data);

    if ($pmhandler->validate_pm()) {
        $pmhandler->insert_pm();
        return true;
    }

    return false;
}
