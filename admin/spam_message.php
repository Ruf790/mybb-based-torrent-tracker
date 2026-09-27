<?php

$rootpath = './../';
$thispath = './';
define("IN_MYBB", 1);

require_once $rootpath . 'global.php';

// Только модераторы и выше - это инструмент разбора жалоб, а не обычный
// просмотр личных сообщений. Раньше эта проверка отсутствовала вообще,
// позволяя любому читать чужую переписку по прямой ссылке.
if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    die("Access denied. Staff only.");
}

require_once(INC_PATH.'/class_parser.php');

$parser = new postParser;
$parser_options = array(
    "allow_html" => 0,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
);

$pmid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($pmid <= 0) {
    die('<div class="alert alert-warning mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Invalid message ID</div>');
}

// Fetch full message
$query = "SELECT pm.*,
                 u.username AS sender_name, u.avatar AS sender_avatar, u.avatardimensions AS sender_avatardimensions, u.email AS sender_email,
                 u2.username AS receiver_name, u2.avatar AS receiver_avatar, u2.avatardimensions AS receiver_avatardimensions, u2.email AS receiver_email
          FROM privatemessages pm
          LEFT JOIN users u ON pm.fromid = u.id
          LEFT JOIN users u2 ON pm.toid = u2.id
          WHERE pm.pmid = ?";

$row = $db->fetch_array($db->sql_query_prepared($query, [$pmid]));

if (!$row) {
    die('<div class="alert alert-secondary mb-0"><i class="fa-solid fa-envelope-circle-check me-2"></i>Message not found</div>');
}

// Примечание: раньше здесь было "UPDATE ... SET status = 1 ..." при
// открытии. Это убрано - теперь файл доступен только стаффу для разбора
// жалоб, и статус "прочитано" у настоящих участников переписки не должен
// меняться от того, что кто-то из модерации сюда заглянул.


// ---------------------------------------------------------------------------
// Подготовка данных для вывода
// ---------------------------------------------------------------------------

$max_dimensions = '48x48';

$fromid = (int)$row['fromid'];
$toid   = (int)$row['toid'];

$sender = [
    'mod'    => 'from',
    'role'   => 'Sender',
    'icon'   => 'fa-paper-plane',
    'name'   => $row['sender_name'] ?? ($fromid <= 0 ? 'System' : 'Deleted user #' . $fromid),
    'email'  => $row['sender_email'] ?? '',
    'avatar' => !empty($row['sender_avatar'])
        ? format_avatar($row['sender_avatar'], $row['sender_avatardimensions'], $max_dimensions)['image']
        : '',
    'empty_icon' => $fromid <= 0 ? 'fa-robot' : 'fa-user',
];

$receiver = [
    'mod'    => 'to',
    'role'   => 'Recipient',
    'icon'   => 'fa-inbox',
    'name'   => $row['receiver_name'] ?? 'Deleted user #' . $toid,
    'email'  => $row['receiver_email'] ?? '',
    'avatar' => !empty($row['receiver_avatar'])
        ? format_avatar($row['receiver_avatar'], $row['receiver_avatardimensions'], $max_dimensions)['image']
        : '',
    'empty_icon' => 'fa-user',
];

// Статусы MyBB: 0 - не прочитано, 1 - прочитано, 3 - отвечено, 4 - переслано
$status = (int)($row['status'] ?? 0);
[$status_label, $status_icon, $status_mod] = match ($status) {
    0       => ['Unread',    'fa-envelope',        'unread'],
    1       => ['Read',      'fa-envelope-open',   'read'],
    3       => ['Replied',   'fa-reply',           'replied'],
    4       => ['Forwarded', 'fa-share',           'forwarded'],
    default => ['Status ' . $status, 'fa-circle-question', ''],
};

$dateline  = (int)$row['dateline'];
$sent_full = date('d.m.Y H:i:s', $dateline);
$sent_file = date('Y-m-d_H-i-s', $dateline);

// inet_ntop() на пустом/битом значении вернёт false - показываем прочерк
$ip_raw = (string)($row['ipaddress'] ?? '');
$ip = (strlen($ip_raw) === 4 || strlen($ip_raw) === 16) ? inet_ntop($ip_raw) : false;

$message_raw = (string)($row['message'] ?? '');
$message_len = mb_strlen($message_raw);

// Карточка участника переписки
function pmv_party(array $p): string
{
    // 'avatar' уже экранирован внутри format_avatar()
    $avatar = $p['avatar'] !== ''
        ? '<img src="' . $p['avatar'] . '" class="pmv-avatar" alt="" loading="lazy">'
        : '<div class="pmv-avatar pmv-avatar--empty"><i class="fa-solid ' . $p['empty_icon'] . '"></i></div>';

    $email = $p['email'] !== ''
        ? '<div class="pmv-email" title="' . htmlspecialchars($p['email']) . '"><i class="fa-solid fa-at"></i>' . htmlspecialchars($p['email']) . '</div>'
        : '';

    return '<div class="pmv-person pmv-person--' . $p['mod'] . '">'
         .    $avatar
         .   '<div class="pmv-person-info">'
         .     '<div class="pmv-role"><i class="fa-solid ' . $p['icon'] . '"></i>' . $p['role'] . '</div>'
         .     '<div class="pmv-name" title="' . htmlspecialchars($p['name']) . '">' . htmlspecialchars($p['name']) . '</div>'
         .      $email
         .   '</div>'
         . '</div>';
}

// ВНИМАНИЕ: этот файл вставляется в модалку spam.php через innerHTML.
// <script> внутри innerHTML НЕ выполняется, поэтому своих скриптов и
// тостов здесь нет - copyRawMessage()/downloadRawMessage() и #copyToast
// живут в spam.php. Раньше тут было ещё два #copyToast (дубли id) и
// мёртвый <script>. Id #rawMessage и .message-content[data-pmid][data-sent]
// нужны JS из spam.php - не переименовывать.
?>
<div class="pmv">

  <!-- Участники -->
  <div class="pmv-people">
    <?= pmv_party($sender) ?>
    <div class="pmv-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
    <?= pmv_party($receiver) ?>
  </div>

  <!-- Мета -->
  <div class="pmv-meta">
    <span class="pmv-chip pmv-chip--status pmv-chip--<?= $status_mod ?>">
      <i class="fa-solid <?= $status_icon ?>"></i><?= $status_label ?>
    </span>
    <span class="pmv-chip" title="Sent date">
      <i class="fa-solid fa-calendar-days"></i><?= $sent_full ?>
    </span>
    <span class="pmv-chip" title="Sender IP address">
      <i class="fa-solid fa-network-wired"></i>
      <?= $ip !== false ? '<code>' . htmlspecialchars($ip) . '</code>' : '<span class="text-body-secondary">—</span>' ?>
    </span>
    <span class="pmv-chip" title="Message ID">
      <i class="fa-solid fa-hashtag"></i><?= (int)$pmid ?>
    </span>
    <span class="pmv-chip" title="Message length">
      <i class="fa-solid fa-text-width"></i><?= number_format($message_len, 0, '.', ' ') ?> chars
    </span>
  </div>

  <!-- Вкладки + действия -->
  <div class="pmv-toolbar">
    <ul class="nav pmv-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pmv-rendered-tab" data-bs-toggle="tab" data-bs-target="#pmv-rendered"
                type="button" role="tab" aria-controls="pmv-rendered" aria-selected="true">
          <i class="fa-solid fa-eye"></i>Rendered
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="pmv-raw-tab" data-bs-toggle="tab" data-bs-target="#pmv-raw"
                type="button" role="tab" aria-controls="pmv-raw" aria-selected="false">
          <i class="fa-solid fa-code"></i>Raw
        </button>
      </li>
    </ul>

    <div class="pmv-actions">
      <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill"
              onclick="copyRawMessage()" title="Copy raw text to clipboard">
        <i class="fa-solid fa-copy"></i>Copy
      </button>
      <button type="button" class="btn btn-sm btn-outline-primary rounded-pill"
              onclick="downloadRawMessage()" title="Download as .txt">
        <i class="fa-solid fa-download"></i>.txt
      </button>
    </div>
  </div>

  <div class="tab-content">
    <!-- Rendered -->
    <div class="tab-pane fade show active" id="pmv-rendered" role="tabpanel" aria-labelledby="pmv-rendered-tab">
      <div class="pmv-body">
        <?= $parser->parse_message($message_raw, $parser_options) ?>
      </div>
    </div>

    <!-- Raw -->
    <div class="tab-pane fade" id="pmv-raw" role="tabpanel" aria-labelledby="pmv-raw-tab">
      <div class="pmv-body pmv-raw message-content"
           data-pmid="<?= (int)$pmid ?>"
           data-sent="<?= $sent_file ?>">
        <pre id="rawMessage"><?= htmlspecialchars($message_raw) ?></pre>
      </div>
    </div>
  </div>

</div>