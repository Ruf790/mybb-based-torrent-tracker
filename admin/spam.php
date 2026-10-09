<?php

declare(strict_types=1);


if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3"><strong>Error!</strong> Direct access not allowed.</div>');
}

require_once INC_PATH . '/functions_multipage.php';

$lang->load('spam');

if (!function_exists('ags_fmt')) {
    /** Подстановка {1}, {2}… в строку из языкового файла */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $a) {
            $map['{' . ($i + 1) . '}'] = (string)$a;
        }
        return $map ? strtr($str, $map) : $str;
    }
}


// Pagination settings
$perPage = 25;
// Читаем и POST, и GET - форма "Jump to Page" в multipage() отправляет
// через POST, а обычные ссылки-страницы (1,2,3...) идут через GET.
// Раньше тут проверялся только $_GET, поэтому "Jump to Page" не работал.
$page = isset($_POST['page']) ? max(1, (int)$_POST['page'])
      : (isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1);
$offset = ($page - 1) * $perPage;

// Search and filter parameters
// is_string() - под strict_types ?q[]=1 иначе валит trim() в TypeError
$search       = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$filterFrom   = isset($_GET['from']) ? max(0, (int)$_GET['from']) : 0;
$filterTo     = isset($_GET['to'])   ? max(0, (int)$_GET['to'])   : 0;
$filterStatus = isset($_GET['status']) && in_array($_GET['status'], ['all', 'read', 'unread'], true)
              ? $_GET['status'] : 'all';

// Build WHERE conditions
$where = [];
$where_params = [];

if ($search !== '') {
    // Экранируем спецсимволы LIKE, иначе "%" или "_" в запросе
    // работают как маски и находят всё подряд
    $like = '%' . addcslashes($search, '\\%_') . '%';
    $where[] = "(pm.subject LIKE ? OR pm.message LIKE ?)";
    $where_params[] = $like;
    $where_params[] = $like;
}

if ($filterFrom > 0) {
    $where[] = "pm.fromid = ?";
    $where_params[] = $filterFrom;
}

if ($filterTo > 0) {
    $where[] = "pm.toid = ?";
    $where_params[] = $filterTo;
}

// "Read" = всё, что открыто получателем: 1 (прочитано), 3 (отвечено),
// 4 (переслано). Раньше было status = 1, и отвеченные/пересланные
// не попадали ни в "Read", ни в "Unread".
if ($filterStatus === 'read') {
    $where[] = "pm.status <> 0";
} elseif ($filterStatus === 'unread') {
    $where[] = "pm.status = 0";
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
$hasFilters  = (bool)$where;

// Get total messages count (с учётом фильтров)
$totalRow = $db->fetch_array($db->sql_query_prepared(
    "SELECT COUNT(*) AS c FROM privatemessages pm $whereClause",
    $where_params
));
$total = (int)$totalRow['c'];

// KPI - по всей таблице, без фильтров
$statsRow = $db->fetch_array($db->sql_query_prepared(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 0), 0)   AS unread,
            COALESCE(SUM(dateline >= ?), 0) AS last24
       FROM privatemessages",
    [TIMENOW - 86400]
));
$kpiTotal  = (int)$statsRow['total'];
$kpiUnread = (int)$statsRow['unread'];
$kpiLast24 = (int)$statsRow['last24'];

// Get messages with user names
$query = "SELECT pm.*,
                 u.username  AS sender_name,   u.usergroup  AS sender_group,
                 u.avatar    AS sender_avatar, u.avatardimensions  AS sender_avatardimensions,
                 u2.username AS receiver_name, u2.usergroup AS receiver_group,
                 u2.avatar   AS receiver_avatar, u2.avatardimensions AS receiver_avatardimensions
          FROM privatemessages pm
          LEFT JOIN users u  ON pm.fromid = u.id
          LEFT JOIN users u2 ON pm.toid   = u2.id
          $whereClause
          ORDER BY pm.dateline DESC
          LIMIT ?, ?";

$res = $db->sql_query_prepared($query, array_merge($where_params, [(int)$offset, (int)$perPage]));

$rows = [];
while ($r = $db->fetch_array($res)) {
    $rows[] = $r;
}


// Если не задан — берём текущий путь + QS.
// SCRIPT_NAME вместо PHP_SELF: PHP_SELF включает PATH_INFO
// (index.php/"><script>...), а этот путь попадает в ссылки.
if (empty($_this_script_)) {
    $_this_script_ = $_SERVER['SCRIPT_NAME'] . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?'.$_SERVER['QUERY_STRING'] : '');
}

/**
 * Собирает URL на основе $_this_script_, $_GET и overrides,
 * с фиксированным порядком ключей: act, page, q, from, to, status.
 */
function build_url(array $overrides = []): string {
    global $_this_script_;
    $base   = $_this_script_;
    $path   = strtok($base, '?'); // путь без QS
    $baseQs = [];
    $qpos   = strpos($base, '?');
    if ($qpos !== false) {
        parse_str(substr($base, $qpos + 1), $baseQs);
    }

    // Базовые дефолты, чтобы получить именно q=&from=0&to=0&status=all
    $defaults = ['q' => '', 'from' => 0, 'to' => 0, 'status' => 'all'];

    // Объединяем: дефолты → QS из $_this_script_ → текущий $_GET → overrides
    // $_POST тоже учитываем - форма "Jump to Page" шлёт через POST,
    // иначе после перехода по ней остальные фильтры сбросились бы
    // до дефолтных значений.
    $params = array_merge($defaults, $baseQs, $_GET, $_POST);
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($params[$k]); // можно удалить ключ, если нужно
        else $params[$k] = $v;
    }

    // Переупорядочим ключи
    $order   = ['act','page','q','from','to','status'];
    $ordered = [];
    foreach ($order as $k) {
        if (array_key_exists($k, $params)) {
            $ordered[$k] = $params[$k];
            unset($params[$k]);
        }
    }
    // Хвост — любые прочие параметры
    $params = $ordered + $params;

    // RFC3986 — чтобы было красиво и стандартизировано
    $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return $path . ($qs ? '?'.$qs : '');
}

/** URL, экранированный для href */
function spam_href(array $overrides = []): string {
    return htmlspecialchars(build_url($overrides), ENT_QUOTES);
}

/** Бинарный IP -> строка ('' если пусто/битое) */
function spam_ip(mixed $bin): string {
    $bin = (string)$bin;
    if (strlen($bin) !== 4 && strlen($bin) !== 16) {
        return '';
    }
    $ip = inet_ntop($bin);
    return $ip === false ? '' : $ip;
}

/** Статус ЛС (MyBB): [label, fa-иконка, модификатор класса] */
function spam_status(int $status): array {
    global $lang;
    return match ($status) {
        0       => [$lang->spam['status_unread'],    'fa-envelope',      'unread'],
        1       => [$lang->spam['status_read'],      'fa-envelope-open', 'read'],
        3       => [$lang->spam['status_replied'],   'fa-reply',         'replied'],
        4       => [$lang->spam['status_forwarded'], 'fa-share',         'forwarded'],
        default => ['#' . $status, 'fa-circle-question', ''],
    };
}

/** Аватар или заглушка */
function spam_avatar(mixed $avatar, mixed $dims, bool $system = false): string {
    if (!empty($avatar)) {
        // 'image' уже экранирован внутри format_avatar()
        $a = format_avatar((string)$avatar, (string)$dims, '36x36');
        return '<img src="' . $a['image'] . '" class="sp-avatar" alt="" loading="lazy">';
    }
    return '<span class="sp-avatar sp-avatar--empty"><i class="fa-solid ' . ($system ? 'fa-robot' : 'fa-user') . '"></i></span>';
}

/** Имя пользователя: экранируем ДО format_name() */
function spam_name(mixed $name, mixed $group, string $fallback): string {
    if ($name === null || $name === '') {
        return '<span class="sp-muted fst-italic">' . htmlspecialchars($fallback) . '</span>';
    }
    return format_name(htmlspecialchars((string)$name), (int)$group);
}

// act для скрытого поля формы - GET-форма заменяет весь query string,
// и без него фильтр уводил с ?act=spam на главную админки
$act = isset($_GET['act']) && is_string($_GET['act']) ? $_GET['act'] : 'spam';


// Строки для spam.js: ключи js_* уходят в AGS_LANG без префикса
$agsLang = [];
foreach ($lang->spam as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $agsLang[substr((string)$k, 3)] = $v;
    }
}

stdhead();

?>
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/spam.css?ver=1">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/spam_message.css?ver=1">
<script>const AGS_LANG = <?= json_encode($agsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/admin/scripts/spam.js?ver=2" defer></script>

<div class="spam-page container mt-3">

  <!-- Header -->
  <div class="sp-card sp-head">
    <div class="sp-head-icon"><i class="fa-solid fa-comments"></i></div>
    <div class="sp-head-text">
      <h1 class="sp-title"><?= $lang->spam['pane_title'] ?></h1>
      <div class="sp-subtitle"><?= $lang->spam['pane_subtitle'] ?></div>
    </div>
  </div>

  <!-- KPI -->
  <div class="sp-kpis">
    <div class="sp-kpi sp-kpi--primary">
      <div class="sp-kpi-icon"><i class="fa-solid fa-envelopes-bulk"></i></div>
      <div>
        <div class="sp-kpi-value"><?= ts_nf($kpiTotal) ?></div>
        <div class="sp-kpi-label"><?= $lang->spam['kpi_total'] ?></div>
      </div>
    </div>
    <div class="sp-kpi sp-kpi--warning">
      <div class="sp-kpi-icon"><i class="fa-solid fa-envelope"></i></div>
      <div>
        <div class="sp-kpi-value"><?= ts_nf($kpiUnread) ?></div>
        <div class="sp-kpi-label"><?= $lang->spam['kpi_unread'] ?></div>
      </div>
    </div>
    <div class="sp-kpi sp-kpi--success">
      <div class="sp-kpi-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
      <div>
        <div class="sp-kpi-value"><?= ts_nf($kpiLast24) ?></div>
        <div class="sp-kpi-label"><?= $lang->spam['kpi_last24'] ?></div>
      </div>
    </div>
    <div class="sp-kpi sp-kpi--info">
      <div class="sp-kpi-icon"><i class="fa-solid fa-filter"></i></div>
      <div>
        <div class="sp-kpi-value"><?= ts_nf($total) ?></div>
        <div class="sp-kpi-label"><?= $hasFilters ? $lang->spam['kpi_matching'] : $lang->spam['kpi_shown_all'] ?></div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="sp-card">
    <form method="get" class="sp-filters">
      <input type="hidden" name="act" value="<?= htmlspecialchars($act, ENT_QUOTES) ?>">

      <div class="sp-field sp-field--grow">
        <label class="sp-label" for="sp-q"><?= $lang->spam['lbl_search'] ?></label>
        <div class="sp-input-icon">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="sp-q" name="q" class="form-control"
                 placeholder="<?= $lang->spam['ph_search'] ?>" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
        </div>
      </div>

      <div class="sp-field">
        <label class="sp-label" for="sp-from"><?= $lang->spam['lbl_sender_uid'] ?></label>
        <div class="sp-input-icon">
          <i class="fa-solid fa-paper-plane"></i>
          <input type="number" min="0" id="sp-from" name="from" class="form-control"
                 placeholder="<?= $lang->spam['ph_any'] ?>" value="<?= $filterFrom > 0 ? $filterFrom : '' ?>">
        </div>
      </div>

      <div class="sp-field">
        <label class="sp-label" for="sp-to"><?= $lang->spam['lbl_recipient_uid'] ?></label>
        <div class="sp-input-icon">
          <i class="fa-solid fa-inbox"></i>
          <input type="number" min="0" id="sp-to" name="to" class="form-control"
                 placeholder="<?= $lang->spam['ph_any'] ?>" value="<?= $filterTo > 0 ? $filterTo : '' ?>">
        </div>
      </div>

      <div class="sp-field">
        <label class="sp-label" for="sp-status"><?= $lang->spam['lbl_status'] ?></label>
        <select id="sp-status" name="status" class="form-select">
          <option value="all"    <?= $filterStatus === 'all'    ? 'selected' : '' ?>><?= $lang->spam['opt_status_all'] ?></option>
          <option value="unread" <?= $filterStatus === 'unread' ? 'selected' : '' ?>><?= $lang->spam['opt_status_unread'] ?></option>
          <option value="read"   <?= $filterStatus === 'read'   ? 'selected' : '' ?>><?= $lang->spam['opt_status_read'] ?></option>
        </select>
      </div>

      <div class="sp-field sp-field--actions">
        <button type="submit" class="btn btn-primary rounded-pill">
          <i class="fa-solid fa-filter me-1"></i><?= $lang->spam['btn_filter'] ?>
        </button>
        <?php if ($hasFilters): ?>
          <a href="<?= spam_href(['q' => null, 'from' => null, 'to' => null, 'status' => null, 'page' => null]) ?>"
             class="btn btn-outline-secondary rounded-pill">
            <i class="fa-solid fa-rotate-left me-1"></i><?= $lang->spam['btn_reset'] ?>
          </a>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($hasFilters): ?>
      <div class="sp-active">
        <span class="sp-active-label"><i class="fa-solid fa-sliders"></i><?= $lang->spam['lbl_active'] ?></span>
        <?php if ($search !== ''): ?>
          <a class="sp-tag" href="<?= spam_href(['q' => null, 'page' => null]) ?>" title="<?= $lang->spam['tip_remove'] ?>">
            <i class="fa-solid fa-magnifying-glass"></i><?= ags_fmt($lang->spam['tag_search'], htmlspecialchars(mb_strimwidth($search, 0, 40, '…'))) ?><i class="fa-solid fa-xmark sp-tag-x"></i>
          </a>
        <?php endif; ?>
        <?php if ($filterFrom > 0): ?>
          <a class="sp-tag" href="<?= spam_href(['from' => null, 'page' => null]) ?>" title="<?= $lang->spam['tip_remove'] ?>">
            <i class="fa-solid fa-paper-plane"></i><?= ags_fmt($lang->spam['tag_from_uid'], $filterFrom) ?><i class="fa-solid fa-xmark sp-tag-x"></i>
          </a>
        <?php endif; ?>
        <?php if ($filterTo > 0): ?>
          <a class="sp-tag" href="<?= spam_href(['to' => null, 'page' => null]) ?>" title="<?= $lang->spam['tip_remove'] ?>">
            <i class="fa-solid fa-inbox"></i><?= ags_fmt($lang->spam['tag_to_uid'], $filterTo) ?><i class="fa-solid fa-xmark sp-tag-x"></i>
          </a>
        <?php endif; ?>
        <?php if ($filterStatus !== 'all'): ?>
          <a class="sp-tag" href="<?= spam_href(['status' => null, 'page' => null]) ?>" title="<?= $lang->spam['tip_remove'] ?>">
            <i class="fa-solid fa-circle-half-stroke"></i><?= $filterStatus === 'read' ? $lang->spam['status_read'] : $lang->spam['status_unread'] ?><i class="fa-solid fa-xmark sp-tag-x"></i>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Messages table -->
  <div class="sp-card sp-card--flush">
    <?php if (!$rows): ?>
      <div class="sp-empty">
        <div class="sp-empty-icon"><i class="fa-solid fa-inbox"></i></div>
        <div class="sp-empty-title"><?= $lang->spam['msg_empty_title'] ?></div>
        <div class="sp-muted"><?= $hasFilters ? $lang->spam['msg_empty_filters'] : $lang->spam['msg_empty_none'] ?></div>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table sp-table mb-0">
          <thead>
            <tr>
              <th class="sp-col-id">#</th>
              <th><?= $lang->spam['th_sender'] ?></th>
              <th><?= $lang->spam['th_recipient'] ?></th>
              <th><?= $lang->spam['th_subject'] ?></th>
              <th class="sp-col-date"><?= $lang->spam['th_date'] ?></th>
              <th class="sp-col-status"><?= $lang->spam['th_status'] ?></th>
              <th class="sp-col-ip"><?= $lang->spam['th_ip'] ?></th>
              <th class="sp-col-act"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $row):
              $pmid   = (int)$row['pmid'];
              $fromid = (int)$row['fromid'];
              $toid   = (int)$row['toid'];
              $status = (int)$row['status'];
              [$stLabel, $stIcon, $stMod] = spam_status($status);
              $subject = (string)($row['subject'] ?? '');
              $ip      = spam_ip($row['ipaddress'] ?? '');
              $ts      = (int)$row['dateline'];
          ?>
            <tr class="<?= $status === 0 ? 'sp-row--unread' : '' ?>">
              <td class="sp-col-id sp-muted">#<?= $pmid ?></td>

              <td>
                <a href="<?= spam_href(['from' => $fromid, 'page' => null]) ?>" class="sp-user"
                   data-bs-toggle="tooltip" title="<?= $lang->spam['tip_filter_sender'] ?>">
                  <?= spam_avatar($row['sender_avatar'], $row['sender_avatardimensions'], $fromid <= 0) ?>
                  <span class="sp-user-name">
                    <?= spam_name($row['sender_name'], $row['sender_group'], $fromid <= 0 ? $lang->spam['name_system'] : ags_fmt($lang->spam['name_deleted'], $fromid)) ?>
                  </span>
                </a>
              </td>

              <td>
                <a href="<?= spam_href(['to' => $toid, 'page' => null]) ?>" class="sp-user"
                   data-bs-toggle="tooltip" title="<?= $lang->spam['tip_filter_recipient'] ?>">
                  <?= spam_avatar($row['receiver_avatar'], $row['receiver_avatardimensions']) ?>
                  <span class="sp-user-name">
                    <?= spam_name($row['receiver_name'], $row['receiver_group'], ags_fmt($lang->spam['name_deleted'], $toid)) ?>
                  </span>
                </a>
              </td>

              <td>
                <div class="sp-subject" title="<?= htmlspecialchars($subject, ENT_QUOTES) ?>">
                  <?= $subject !== '' ? htmlspecialchars($subject) : '<span class="sp-muted fst-italic">' . htmlspecialchars($lang->spam['lbl_no_subject']) . '</span>' ?>
                </div>
              </td>

              <td class="sp-col-date">
                <div title="<?= date('Y-m-d H:i:s', $ts) ?>">
                  <?= date('d.m.Y', $ts) ?>
                  <div class="sp-time"><i class="fa-regular fa-clock"></i><?= date('H:i', $ts) ?></div>
                </div>
              </td>

              <td class="sp-col-status">
                <span class="sp-status sp-status--<?= $stMod ?>">
                  <i class="fa-solid <?= $stIcon ?>"></i><?= $stLabel ?>
                </span>
              </td>

              <td class="sp-col-ip">
                <?= $ip !== '' ? '<code class="sp-ip">' . htmlspecialchars($ip) . '</code>' : '<span class="sp-muted">—</span>' ?>
              </td>

              <td class="sp-col-act">
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill sp-view"
                        data-bs-toggle="modal" data-bs-target="#msgModal"
                        data-pmid="<?= $pmid ?>"
                        data-subject="<?= htmlspecialchars($subject, ENT_QUOTES) ?>">
                  <i class="fa-solid fa-eye"></i><span class="d-none d-xl-inline ms-1"><?= $lang->spam['btn_view'] ?></span>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total > $perPage): ?>
    <div class="sp-pager">
      <?= multipage($total, $perPage, $page, build_url(['page' => null])) ?>
    </div>
  <?php endif; ?>


  <!-- Modal for viewing a message (id'ы используются spam.js) -->
  <div class="modal fade sp-modal" id="msgModal" tabindex="-1" aria-labelledby="msgModalTitle" aria-hidden="true"
       data-endpoint="spam_message.php">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <div class="sp-modal-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
          <h5 class="modal-title" id="msgModalTitle"><?= $lang->spam['lbl_modal_title'] ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $lang->spam['aria_close'] ?>"></button>
        </div>
        <div class="modal-body" id="msgModalBody"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">
            <i class="fa-solid fa-xmark me-1"></i><?= $lang->spam['btn_close'] ?>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast (global) -->
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080;">
    <div id="copyToast" class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body"><i class="fa-solid fa-circle-check me-2"></i><span class="sp-toast-text"><?= $lang->spam['lbl_toast_done'] ?></span></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="<?= $lang->spam['aria_close'] ?>"></button>
      </div>
    </div>
  </div>

</div>

<?php
stdfoot();