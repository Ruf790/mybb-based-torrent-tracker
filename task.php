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
// KPI tiles: always this month, whichever leaderboard tab is open
$kpiRows = $lbLast
    ? exam_task_leaderboard((int)strtotime('first day of this month 00:00', TIMENOW), (int)strtotime('first day of next month 00:00', TIMENOW))
    : $lbAll;
$kpiMine = null;
foreach ($kpiRows as $row) {
    if ($row['uid'] === $uid) { $kpiMine = $row; break; }
}
$kpiOngoing = count($mine);

$lbUser = static function (array $r) use ($BASEURL, $e): string {
    $name = $e($r['username']);
    if (function_exists('format_name')) {
        $name = format_name($name, (int)$r['usergroup']);
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
        // $lang->load() turned {1} into %1$s; task.js t() expects {1}
        $agsLang[substr((string)$k, 3)] = preg_replace('/%(\d+)\$s/', '{$1}', (string)$v);
    }
}

// ── Output ───────────────────────────────────────────────

// Icon for each requirement (exam index)
$tqIcons = [
    EXAM_INDEX_UPLOADED             => 'fa-cloud-arrow-up',
    EXAM_INDEX_DOWNLOADED           => 'fa-cloud-arrow-down',
    EXAM_INDEX_SEED_TIME_AVERAGE    => 'fa-clock',
    EXAM_INDEX_SEED_BONUS           => 'fa-coins',
    EXAM_INDEX_SEED_POINTS          => 'fa-seedling',
    EXAM_INDEX_UPLOAD_TORRENT_COUNT => 'fa-upload',
    EXAM_INDEX_SEEDING_COUNT        => 'fa-arrow-up-from-bracket',
    EXAM_INDEX_RATIO                => 'fa-scale-balanced',
    EXAM_INDEX_REQUESTS_FILLED      => 'fa-hand-holding-heart',
    EXAM_INDEX_CLAIMS_TAKEN         => 'fa-shield-heart',
    EXAM_INDEX_COMMENTS             => 'fa-comment',
    EXAM_INDEX_FORUM_POSTS          => 'fa-comments',
];
$tqL = static fn(string $k, string $fb): string => (string)($lang->task[$k] ?? $fb);

stdhead($lang->task['pg_title']);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/st-ui.css?ver=7">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/task.css?ver=1">
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>

<div class="container-md st-page tq-page py-3">

  <!-- Header -->
  <header class="st-card st-head mb-3">
    <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-list-check"></i></div>
    <div class="flex-grow-1 min-w-0">
      <h1 class="st-title"><?= $e($lang->task['pane_tasks']) ?></h1>
      <div class="st-meta mt-1"><?= $e(EXAM_TASK_ABANDON_PENALTY_PERCENT >= 100
          ? $lang->task['hint_intro_full']
          : ags_fmt($lang->task['hint_intro_pct'], EXAM_TASK_ABANDON_PENALTY_PERCENT)) ?></div>
    </div>
    <a class="st-badge st-soft-warning d-none d-md-inline-flex text-decoration-none" href="#tq-leaders"><i class="fa-solid fa-ranking-star"></i><?= $e($lang->task['sec_leaderboard']) ?></a>
  </header>

  <!-- KPI tiles -->
  <div class="tq-kpis mb-3">
    <div class="st-kpi st-kpi--muted">
      <span class="st-ico st-soft-primary"><i class="fa-solid fa-list-check"></i></span>
      <div class="min-w-0">
        <div class="st-kpi__val"><?= exam_num($total) ?></div>
        <div class="st-kpi__lbl"><?= $e($tqL('kpi_available', 'Available tasks')) ?></div>
      </div>
      <i class="fa-solid fa-list-check st-kpi__bg" aria-hidden="true"></i>
    </div>
    <div class="st-kpi st-kpi--muted">
      <span class="st-ico st-soft-success"><i class="fa-solid fa-circle-check"></i></span>
      <div class="min-w-0">
        <div class="st-kpi__val"><?= exam_num((int)($kpiMine['n'] ?? 0)) ?></div>
        <div class="st-kpi__lbl"><?= $e($tqL('kpi_done', 'Completed this month')) ?></div>
      </div>
      <i class="fa-solid fa-circle-check st-kpi__bg" aria-hidden="true"></i>
    </div>
    <div class="st-kpi st-kpi--muted">
      <span class="st-ico st-soft-warning"><i class="fa-solid fa-coins"></i></span>
      <div class="min-w-0">
        <div class="st-kpi__val">+<?= exam_num((int)($kpiMine['bp'] ?? 0)) ?></div>
        <div class="st-kpi__lbl"><?= $e($tqL('kpi_bonus', 'Bonus this month')) ?></div>
      </div>
      <i class="fa-solid fa-coins st-kpi__bg" aria-hidden="true"></i>
    </div>
    <div class="st-kpi st-kpi--muted">
      <span class="st-ico st-soft-danger"><i class="fa-solid fa-trophy"></i></span>
      <div class="min-w-0">
        <div class="st-kpi__val"><?= $kpiMine ? '#' . (int)$kpiMine['rank'] : '—' ?></div>
        <div class="st-kpi__lbl"><?= $e($tqL('kpi_rank', 'My place this month')) ?></div>
      </div>
      <i class="fa-solid fa-trophy st-kpi__bg" aria-hidden="true"></i>
    </div>
  </div>

  <?php if (is_array($flash) && isset($flash['t'], $flash['m'])): ?>
    <div class="tq-alert tq-alert--<?= $flash['t'] === 'success' ? 'success' : 'danger' ?> mb-3" role="alert">
      <i class="fa-solid <?= $flash['t'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
      <span><?= $e((string)$flash['m']) ?></span>
    </div>
  <?php endif; ?>

  <?php // Current task with its progress (same card as under the site header) ?>
  <?= exam_render_header_notice($uid) ?>

  <!-- Tasks -->
  <div class="tq-sec mb-2">
    <h2><i class="fa-solid fa-layer-group"></i><?= $e($tqL('sec_available', 'Available tasks')) ?></h2>
    <?php if ($kpiOngoing > 0): ?>
      <span class="tq-sec__note"><i class="fa-solid fa-circle-info"></i><?= $e($tqL('hint_one_at_time', 'One task at a time: finish or abandon the current one to take another.')) ?></span>
    <?php endif; ?>
  </div>

  <?php if (!$tasks): ?>
    <div class="st-card st-empty mb-3">
      <span class="st-ico st-soft-muted"><i class="fa-solid fa-mug-hot"></i></span>
      <div><?= $e($lang->task['msg_no_tasks']) ?></div>
    </div>
  <?php else: ?>
    <div class="tq-grid mb-3">
    <?php foreach ($tasks as $t):
        $id      = (int)$t['id'];
        $claimed = isset($mine[$id]);
        $max     = (int)$t['max_user_count'];
        $ongoingN = (int)$t['ongoing'];
        $full    = $max > 0 && $ongoingN >= $max;
        $tone    = EXAM_BG[(string)($t['background_color'] ?? 'blue')] ?? 'primary';
        try {
            $tb    = exam_begin_for_user($t);
            $te    = exam_end_for_user($t);
            $range = date('d.m.Y H:i', $tb) . ' ~ ' . date('d.m.Y H:i', $te);
            $days  = max(1, (int)round(($te - $tb) / 86400));
        } catch (ExamException) {
            $range = '-';
            $days  = 0;
        }
        $who = [];
        if (!empty($t['filters'][EXAM_FILTER_USER_CLASS])) {
            $who[] = implode(', ', array_map(static fn($g) => $groupNames[(int)$g] ?? "#{$g}", $t['filters'][EXAM_FILTER_USER_CLASS]));
        }
        $d = $t['filters'][EXAM_FILTER_USER_REGISTER_DAYS_RANGE] ?? [];
        if (isset($d[0]) && $d[0] !== null) $who[] = ags_fmt($lang->task['lbl_reg_min'], (string)$d[0]);
        if (isset($d[1]) && $d[1] !== null) $who[] = ags_fmt($lang->task['lbl_reg_max'], (string)$d[1]);

        $rewards = [];
        foreach ([
            ['success_reward_bonus', 'fa-coins',              'rw_bonus'],
            ['reward_upload_gb',     'fa-cloud-arrow-up',     'rw_upload'],
            ['reward_vip_days',      'fa-crown',              'rw_vip'],
            ['reward_invites',       'fa-envelope-open-text', 'rw_invites'],
        ] as [$col, $icon, $key]) {
            if ((int)($t[$col] ?? 0) > 0) $rewards[] = [$icon, exam_t($key, exam_num((int)$t[$col]))];
        }
        $penalty = (int)$t['fail_deduct_bonus'];
        $fillPct = $max > 0 ? (int)min(100, round($ongoingN / $max * 100)) : 0;
    ?>
      <article class="st-card tq-card tq-tone-<?= $tone ?><?= $claimed ? ' is-claimed' : '' ?><?= $full && !$claimed ? ' is-full' : '' ?>">
        <div class="tq-card__head">
          <span class="tq-card__ico"><i class="fa-solid fa-flag-checkered"></i></span>
          <div class="min-w-0 flex-grow-1">
            <h3 class="tq-card__title"><?= $e((string)$t['name']) ?></h3>
            <div class="tq-card__meta">
              <span><i class="fa-regular fa-clock"></i><?= $days > 0 ? $e(ags_fmt($tqL('lbl_days', '{1} day(s)'), $days)) : '-' ?></span>
              <span title="<?= $e($range) ?>"><i class="fa-regular fa-calendar"></i><?= $e($range) ?></span>
            </div>
          </div>
          <?php if ($claimed): ?>
            <span class="tq-status tq-status--mine"><i class="fa-solid fa-check"></i><?= $e($lang->task['btn_claimed']) ?></span>
          <?php elseif ($full): ?>
            <span class="tq-status tq-status--full"><i class="fa-solid fa-lock"></i><?= $e($lang->task['btn_full']) ?></span>
          <?php endif; ?>
        </div>

        <?php if (trim((string)$t['description']) !== ''): ?>
          <p class="tq-desc"><?= nl2br($e((string)$t['description'])) ?></p>
        <?php endif; ?>

        <div class="tq-label"><?= $e($tqL('lbl_requirements', 'Requirements')) ?></div>
        <ul class="tq-reqs">
          <?php foreach (exam_checked_indexes($t) as $i): $ik = (int)$i['index']; ?>
            <li><i class="fa-solid <?= $tqIcons[$ik] ?? 'fa-circle' ?>"></i><span><?= $e(exam_index_name($ik)) ?></span><strong><?= $e(exam_require_text($ik, (int)$i['require_value'])) ?></strong></li>
          <?php endforeach; ?>
        </ul>

        <div class="tq-label"><?= $e($tqL('lbl_reward', 'Reward')) ?></div>
        <div class="tq-chips">
          <?php if ($rewards): foreach ($rewards as [$icon, $text]): ?>
            <span class="tq-chip tq-chip--plus"><i class="fa-solid <?= $icon ?>"></i>+<?= $e($text) ?></span>
          <?php endforeach; else: ?>
            <span class="tq-chip tq-chip--muted"><?= $e($tqL('lbl_no_reward', 'No reward — just for the leaderboard')) ?></span>
          <?php endif; ?>
          <?php if ($penalty > 0): ?>
            <span class="tq-chip tq-chip--minus" title="<?= $e($tqL('tip_penalty', 'Deducted if the task is failed')) ?>"><i class="fa-solid fa-circle-minus"></i>−<?= $e(exam_t('rw_bonus', exam_num($penalty))) ?></span>
          <?php endif; ?>
        </div>

        <div class="tq-card__foot">
          <div class="tq-who">
            <div class="tq-who__line">
              <span><i class="fa-solid fa-users"></i><?= $e(ags_fmt($lang->task['lbl_claimed_count'], exam_num($ongoingN), $max ? exam_num($max) : '∞')) ?></span>
              <?php if ($who): ?><span class="tq-who__groups" title="<?= $e(implode(', ', $who)) ?>"><i class="fa-solid fa-user-check"></i><?= $e(implode(', ', $who)) ?></span><?php endif; ?>
            </div>
            <?php if ($max > 0): ?>
              <div class="tq-fill"><div style="width:<?= $fillPct ?>%"></div></div>
            <?php endif; ?>
          </div>

          <div class="tq-actions">
          <?php if ($claimed):
              $abPenalty = exam_task_abandon_penalty($t, (int)$mine[$id]['is_done'] === 1); ?>
            <form method="post" action="<?= $BASEURL ?>/task.php" data-tk-abandon="<?= $e((string)$t['name']) ?>"
                  data-tk-abandon-penalty="<?= $e(exam_num($abPenalty)) ?>" data-tk-abandon-done="<?= (int)$mine[$id]['is_done'] ?>"
                  title="<?= $e($abPenalty > 0 ? ags_fmt($lang->task['lbl_abandon_penalty'], exam_num($abPenalty)) : $lang->task['lbl_abandon_free']) ?>">
              <input type="hidden" name="my_post_key" value="<?= $e($postKey) ?>">
              <input type="hidden" name="action" value="abandon">
              <input type="hidden" name="euid" value="<?= (int)$mine[$id]['id'] ?>">
              <button class="btn btn-sm btn-outline-danger st-pill"><i class="fa-solid fa-person-walking-arrow-right me-1"></i><?= $e($lang->task['btn_abandon']) ?></button>
            </form>
          <?php elseif ($full): ?>
            <button class="btn btn-sm btn-outline-secondary st-pill" disabled><i class="fa-solid fa-lock me-1"></i><?= $e($lang->task['btn_full']) ?></button>
          <?php else: ?>
            <form method="post" action="<?= $BASEURL ?>/task.php" data-tk-claim="<?= $e((string)$t['name']) ?>"
                  data-tk-penalty="<?= $penalty ?>">
              <input type="hidden" name="my_post_key" value="<?= $e($postKey) ?>">
              <input type="hidden" name="exam_id" value="<?= $id ?>">
              <button class="btn btn-sm btn-primary st-pill"<?= $kpiOngoing > 0 ? ' disabled title="' . $e($tqL('hint_one_at_time', '')) . '"' : '' ?>><i class="fa-solid fa-hand me-1"></i><?= $e($lang->task['btn_claim']) ?></button>
            </form>
          <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
    </div>

    <?php $pages = (int)ceil($total / $perPage); if ($pages > 1): ?>
      <nav class="tq-pages mb-3">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= $BASEURL ?>/task.php?page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

<?php
  $lbRow = static function (array $r, bool $me) use ($lbUser): string {
      $medal = [1 => 'gold', 2 => 'silver', 3 => 'bronze'][$r['rank']] ?? '';
      return '<tr' . ($me ? ' class="is-me"' : '') . '>'
          . '<td class="tq-rank">' . ($medal !== ''
                ? '<span class="tq-medal tq-medal--' . $medal . '"><i class="fa-solid fa-trophy"></i>' . $r['rank'] . '</span>'
                : $r['rank']) . '</td>'
          . '<td>' . $lbUser($r) . '</td>'
          . '<td class="tq-num"><strong>' . exam_num($r['n']) . '</strong></td>'
          . '<td class="tq-num">+' . exam_num($r['bp']) . '</td></tr>';
  };
  $lbInTop = $lbMine !== null && $lbMine['rank'] <= count($lbTop);
?>
  <!-- Leaderboard -->
  <section class="st-card tq-lb" id="tq-leaders">
    <div class="tq-lb__head">
      <div class="min-w-0">
        <h2><i class="fa-solid fa-ranking-star"></i><?= $e($lang->task['sec_leaderboard']) ?></h2>
        <div class="st-meta"><?= $e(ags_fmt($lang->task['lbl_lb_sub'], $lang->task['mon_' . (int)date('n', $lbFrom)], date('Y', $lbFrom))) ?></div>
      </div>
      <nav class="tq-pills">
        <a class="<?= $lbLast ? '' : 'is-active' ?>" href="<?= $BASEURL ?>/task.php#tq-leaders"><?= $e($lang->task['tab_this_month']) ?></a>
        <a class="<?= $lbLast ? 'is-active' : '' ?>" href="<?= $BASEURL ?>/task.php?lb=last#tq-leaders"><?= $e($lang->task['tab_last_month']) ?></a>
      </nav>
    </div>

    <?php if (!$lbTop): ?>
      <div class="st-empty">
        <span class="st-ico st-soft-muted"><i class="fa-solid fa-flag-checkered"></i></span>
        <div><?= $e($lbLast ? $lang->task['msg_lb_empty_last'] : $lang->task['msg_lb_empty_this']) ?></div>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="tq-table">
          <thead><tr><th><?= $e($lang->task['th_rank']) ?></th><th><?= $e($lang->task['th_user']) ?></th><th class="tq-num"><?= $e($lang->task['th_tasks']) ?></th><th class="tq-num"><?= $e($lang->task['th_bonus']) ?></th></tr></thead>
          <tbody>
            <?php foreach ($lbTop as $r) echo $lbRow($r, $r['uid'] === $uid); ?>
            <?php if ($lbMine !== null && !$lbInTop): ?>
              <tr class="tq-gap"><td colspan="4">&hellip;</td></tr>
              <?= $lbRow($lbMine, true) ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($lbMine === null && !$lbLast): ?>
        <div class="st-meta tq-lb__note"><?= $e($lang->task['msg_lb_you_none']) ?></div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<script>
const AGS_LANG = <?= json_encode($agsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $BASEURL ?>/scripts/task.js?ver=24"></script>
<?php
stdfoot();
