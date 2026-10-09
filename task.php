<?php
declare(strict_types=1);

/**
 * Tasks - users claim them themselves (port of public/task.php from NexusPHP).
 */
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'task.php');
define('SCRIPTNAME', 'task.php');

require_once 'global.php';

$lang->load('task');


require_once INC_PATH . '/functions_exam.php';
require_once INC_PATH . '/functions_exam_header.php';

if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        return preg_replace_callback(
            '/\{(\d+)\}|%(\d+)\$s/',
            static function (array $m) use ($args): string {
                $i = (int)($m[1] !== '' ? $m[1] : $m[2]) - 1;
                return array_key_exists($i, $args) ? (string)$args[$i] : $m[0];
            },
            $str
        ) ?? $str;
    }
}

if (empty($CURUSER['id'])) {
    if (function_exists('loggedinorreturn')) {
        loggedinorreturn();
    }
    header('Location: ' . $BASEURL . '/login.php');
    exit;
}

$uid = (int)$CURUSER['id'];
$e   = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

function task_redirect(string $type, string $msg): never
{
    global $BASEURL;
    setcookie('task_flash', json_encode(['t' => $type, 'm' => $msg], JSON_UNESCAPED_UNICODE), [
        'expires' => TIMENOW + 60, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure'  => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    header('Location: ' . $BASEURL . '/task.php', true, 303);
    exit;
}

// ── Claim a task (claimTask) ─────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        task_redirect('danger', $lang->task['flash_token_expired']);
    }
    // ── Abandon own task (our addition) ──
    if (($_POST['action'] ?? '') === 'abandon') {
        try {
            $deduct = exam_task_abandon($uid, (int)($_POST['euid'] ?? 0));
            if (function_exists('write_log')) {
                write_log("[Tasks] {$CURUSER['username']} abandoned task attempt #" . (int)$_POST['euid'] . ", penalty {$deduct}");
            }
            task_redirect('success', $deduct > 0
                ? ags_fmt($lang->task['flash_abandoned_penalty'], exam_num($deduct))
                : $lang->task['flash_abandoned']);
        } catch (ExamException $ex) {
            task_redirect('danger', $ex->getMessage());
        }
    }

    try {
        exam_assign_to_user($uid, (int)($_POST['exam_id'] ?? 0), ['id' => $uid, 'staff' => false]);
        if (function_exists('write_log')) {
            write_log("[Tasks] {$CURUSER['username']} claimed task #" . (int)$_POST['exam_id']);
        }
        task_redirect('success', $lang->task['flash_claimed']);
    } catch (ExamException $ex) {
        task_redirect('danger', $ex->getMessage());
    }
}

// ── Data ─────────────────────────────────────────────────
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
// Only tasks that can be claimed right now: enabled and inside their time
// (same rule as exam_list_valid(): fixed window that has ended or not started
// yet is hidden; duration and recurring tasks are always open).
$valid = exam_list_valid(null, EXAM_TYPE_TASK);
$total = count($valid);
$tasks = array_slice($valid, ($page - 1) * $perPage, $perPage);

// Ongoing participants per task, one query
$ongoing = [];
if ($tasks) {
    $ids = array_map(static fn(array $t): int => (int)$t['id'], $tasks);
    $q   = $db->sql_query_prepared(
        'SELECT exam_id, COUNT(*) AS n FROM exam_users WHERE status = ? AND exam_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY exam_id',
        [EXAM_USER_STATUS_NORMAL, ...$ids]
    );
    while ($r = $db->fetch_array($q)) $ongoing[(int)$r['exam_id']] = (int)$r['n'];
}
foreach ($tasks as &$t) { $t['ongoing'] = $ongoing[(int)$t['id']] ?? 0; }
unset($t);

$mine = []; // exam_id => attempt row
$q = $db->sql_query_prepared('SELECT * FROM exam_users WHERE uid = ? AND status = ?', [$uid, EXAM_USER_STATUS_NORMAL]);
while ($r = $db->fetch_array($q)) $mine[(int)$r['exam_id']] = $r;

$groupNames = [];
$q = $db->sql_query_prepared('SELECT gid, title FROM usergroups', []);
while ($r = $db->fetch_array($q)) $groupNames[(int)$r['gid']] = (string)$r['title'];

// ── Leaderboard: tasks completed this month / last month ──
$lbLast  = ($_GET['lb'] ?? '') === 'last';
$lbFrom  = (int)strtotime($lbLast ? 'first day of last month 00:00' : 'first day of this month 00:00', TIMENOW);
$lbTo    = (int)strtotime('first day of next month 00:00', $lbFrom);
$lbAll   = exam_task_leaderboard($lbFrom, $lbTo);
$lbTop   = array_slice($lbAll, 0, 10);
$lbMine  = null;
foreach ($lbAll as $row) {
    if ($row['uid'] === $uid) { $lbMine = $row; break; }
}
$lbUser = static function (array $r) use ($BASEURL, $e): string {
    $name = $e($r['username']);
    if (function_exists('format_name')) {
        $name = format_name($name, $r['usergroup'], $r['displaygroup']);
    }
    $url = function_exists('get_profile_link') ? (string)get_profile_link($r['uid']) : $BASEURL . '/member.php?action=profile&uid=' . $r['uid'];
    return '<a href="' . $e($url) . '">' . $name . '</a>';
};

$flash = null;
if (!empty($_COOKIE['task_flash'])) {
    $flash = json_decode((string)$_COOKIE['task_flash'], true);
    setcookie('task_flash', '', ['expires' => 1, 'path' => '/']);
}
$postKey = function_exists('generate_post_check') ? (string)generate_post_check() : (string)($mybb->post_code ?? '');

// JS strings: every 'js_*' key of the language file, prefix stripped
$agsLang = [];
foreach ($lang->task as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $agsLang[substr((string)$k, 3)] = $v;
    }
}

// ── Output ───────────────────────────────────────────────
stdhead($lang->task['pg_title']);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/exam.css?ver=122">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/task_leaders.css?ver=2">
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>

<div class="tk">
  <div class="tk-head">
    <i class="fa-solid fa-list-check"></i>
    <div>
      <h1><?= $e($lang->task['pane_tasks']) ?></h1>
      <p><?= $e(EXAM_TASK_ABANDON_PENALTY_PERCENT >= 100
          ? $lang->task['hint_intro_full']
          : ags_fmt($lang->task['hint_intro_pct'], EXAM_TASK_ABANDON_PENALTY_PERCENT)) ?></p>
    </div>
  </div>

  <?= exam_render_header_notice($uid) ?>

  <?php if (is_array($flash) && isset($flash['t'], $flash['m'])): ?>
    <div class="tk-alert tk-alert--<?= $flash['t'] === 'success' ? 'success' : 'danger' ?>" role="alert">
      <i class="fa-solid <?= $flash['t'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
      <span><?= $e((string)$flash['m']) ?></span>
    </div>
  <?php endif; ?>

  <?php if (!$tasks): ?>
    <div class="tk-empty"><i class="fa-solid fa-mug-hot"></i><p><?= $e($lang->task['msg_no_tasks']) ?></p></div>
  <?php else: ?>
    <div class="tk-list">
    <?php foreach ($tasks as $t):
        $id      = (int)$t['id'];
        $claimed = isset($mine[$id]);
        $max     = (int)$t['max_user_count'];
        $full    = $max > 0 && (int)$t['ongoing'] >= $max;
        try {
            $range = date('d.m.Y H:i', exam_begin_for_user($t)) . ' ~ ' . date('d.m.Y H:i', exam_end_for_user($t));
        } catch (ExamException) {
            $range = '-';
        }
        $who = [];
        if (!empty($t['filters'][EXAM_FILTER_USER_CLASS])) {
            $who[] = implode(', ', array_map(static fn($g) => $groupNames[(int)$g] ?? "#{$g}", $t['filters'][EXAM_FILTER_USER_CLASS]));
        }
        $d = $t['filters'][EXAM_FILTER_USER_REGISTER_DAYS_RANGE] ?? [];
        if (isset($d[0]) && $d[0] !== null) $who[] = ags_fmt($lang->task['lbl_reg_min'], (string)$d[0]);
        if (isset($d[1]) && $d[1] !== null) $who[] = ags_fmt($lang->task['lbl_reg_max'], (string)$d[1]);
    ?>
      <article class="tk-card<?= $claimed ? ' is-claimed' : '' ?>">
        <div class="tk-card__main">
          <h2><?= $e((string)$t['name']) ?></h2>
          <?php if (trim((string)$t['description']) !== ''): ?>
            <p class="tk-desc"><?= nl2br($e((string)$t['description'])) ?></p>
          <?php endif; ?>
          <ul class="tk-reqs">
            <?php foreach (exam_checked_indexes($t) as $i): $m = EXAM_INDEXES[(int)$i['index']]; ?>
              <li><?= $e($m['name']) ?>: <strong><?= exam_num((int)$i['require_value']) ?> <?= $e($m['unit']) ?></strong></li>
            <?php endforeach; ?>
          </ul>
          <div class="tk-meta">
            <span><i class="fa-regular fa-clock"></i><?= $e($range) ?></span>
            <?php if ($who): ?><span><i class="fa-solid fa-user-check"></i><?= $e(implode(', ', $who)) ?></span><?php endif; ?>
            <span><i class="fa-solid fa-users"></i><?= $e(ags_fmt($lang->task['lbl_claimed_count'], exam_num((int)$t['ongoing']), $max ? exam_num($max) : '∞')) ?></span>
          </div>
        </div>
        <div class="tk-card__side">
          <div class="tk-bonus tk-bonus--plus"><i class="fa-solid fa-gift"></i>+<?= exam_num((int)$t['success_reward_bonus']) ?></div>
          <div class="tk-bonus tk-bonus--minus"><i class="fa-solid fa-circle-minus"></i>−<?= exam_num((int)$t['fail_deduct_bonus']) ?></div>
          <?php if ($claimed):
              $abPenalty = exam_task_abandon_penalty($t, (int)$mine[$id]['is_done'] === 1); ?>
            <button class="tk-btn" disabled><i class="fa-solid fa-check"></i><?= $e($lang->task['btn_claimed']) ?></button>
            <form method="post" action="<?= $BASEURL ?>/task.php" data-tk-abandon="<?= $e((string)$t['name']) ?>"
                  data-tk-abandon-penalty="<?= $e(exam_num($abPenalty)) ?>" data-tk-abandon-done="<?= (int)$mine[$id]['is_done'] ?>">
              <input type="hidden" name="my_post_key" value="<?= $e($postKey) ?>">
              <input type="hidden" name="action" value="abandon">
              <input type="hidden" name="euid" value="<?= (int)$mine[$id]['id'] ?>">
              <button class="tk-btn tk-btn--abandon"><i class="fa-solid fa-person-walking-arrow-right"></i><?= $e($lang->task['btn_abandon']) ?></button>
            </form>
            <small class="tk-abandon-note"><?= $e($abPenalty > 0 ? ags_fmt($lang->task['lbl_abandon_penalty'], exam_num($abPenalty)) : $lang->task['lbl_abandon_free']) ?></small>
          <?php elseif ($full): ?>
            <button class="tk-btn" disabled><?= $e($lang->task['btn_full']) ?></button>
          <?php else: ?>
            <form method="post" action="<?= $BASEURL ?>/task.php" data-tk-claim="<?= $e((string)$t['name']) ?>"
                  data-tk-penalty="<?= (int)$t['fail_deduct_bonus'] ?>">
              <input type="hidden" name="my_post_key" value="<?= $e($postKey) ?>">
              <input type="hidden" name="exam_id" value="<?= $id ?>">
              <button class="tk-btn tk-btn--primary"><i class="fa-solid fa-hand"></i><?= $e($lang->task['btn_claim']) ?></button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    </div>

    <?php $pages = (int)ceil($total / $perPage); if ($pages > 1): ?>
      <nav class="tk-pages">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= $BASEURL ?>/task.php?page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
  $lbRow = static function (array $r, bool $me) use ($lbUser): string {
      $medal = [1 => 'gold', 2 => 'silver', 3 => 'bronze'][$r['rank']] ?? '';
      return '<tr' . ($me ? ' class="is-me"' : '') . '>'
          . '<td class="tl-rank">' . ($medal !== ''
                ? '<span class="tl-medal tl-medal--' . $medal . '"><i class="fa-solid fa-trophy"></i>' . $r['rank'] . '</span>'
                : $r['rank']) . '</td>'
          . '<td>' . $lbUser($r) . '</td>'
          . '<td class="tl-num"><strong>' . exam_num($r['n']) . '</strong></td>'
          . '<td class="tl-num">+' . exam_num($r['bp']) . '</td></tr>';
  };
  $lbInTop = $lbMine !== null && $lbMine['rank'] <= count($lbTop);
?>
<div class="tl">
  <div class="tl-card">
    <div class="tl-head">
      <h2><i class="fa-solid fa-ranking-star"></i><?= $e($lang->task['sec_leaderboard']) ?></h2>
      <nav class="tl-tabs">
        <a class="<?= $lbLast ? '' : 'is-active' ?>" href="<?= $BASEURL ?>/task.php"><?= $e($lang->task['tab_this_month']) ?></a>
        <a class="<?= $lbLast ? 'is-active' : '' ?>" href="<?= $BASEURL ?>/task.php?lb=last"><?= $e($lang->task['tab_last_month']) ?></a>
      </nav>
    </div>
    <p class="tl-sub"><?= $e(ags_fmt($lang->task['lbl_lb_sub'], $lang->task['mon_' . (int)date('n', $lbFrom)], date('Y', $lbFrom))) ?></p>

    <?php if (!$lbTop): ?>
      <div class="tl-empty"><i class="fa-solid fa-flag-checkered"></i><?= $e($lbLast ? $lang->task['msg_lb_empty_last'] : $lang->task['msg_lb_empty_this']) ?></div>
    <?php else: ?>
      <div class="tl-wrap">
        <table class="tl-table">
          <thead><tr><th><?= $e($lang->task['th_rank']) ?></th><th><?= $e($lang->task['th_user']) ?></th><th class="tl-num"><?= $e($lang->task['th_tasks']) ?></th><th class="tl-num"><?= $e($lang->task['th_bonus']) ?></th></tr></thead>
          <tbody>
            <?php foreach ($lbTop as $r) echo $lbRow($r, $r['uid'] === $uid); ?>
            <?php if ($lbMine !== null && !$lbInTop): ?>
              <tr class="tl-gap"><td colspan="4">&hellip;</td></tr>
              <?= $lbRow($lbMine, true) ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($lbMine === null && !$lbLast): ?>
        <p class="tl-sub"><?= $e($lang->task['msg_lb_you_none']) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
const AGS_LANG = <?= json_encode($agsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $BASEURL ?>/scripts/task.js?ver=23"></script>
<?php
stdfoot();
