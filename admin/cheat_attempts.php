<?php
declare(strict_types=1);


require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_icons.php';

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger">Direct initialization not allowed.</div>');
}

$lang->load('cheat_attempts');

if (!function_exists('ags_fmt')) {
    /**
     * Substitutes {1}, {2}… (and %1$s… produced by $lang->load()) with the given args.
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

$eol = PHP_EOL;

/**
 * Fully bans a user: moves them to the banned usergroup, inserts a record
 * into the `banned` table, clears their passkey and disables the account.
 * Skips users who are already banned.
 */
function cheat_full_ban(int $uid, string $reason, int $banned_by): void
{
    global $db, $cache;

    // Already banned? Skip.
    $bq = $db->sql_query_prepared("SELECT uid FROM banned WHERE uid = ?", [$uid]);
    if ($bq && $db->num_rows($bq)) {
        return;
    }

    // Find the banned usergroup
    $banned_gid  = 9; // fallback — gid 9 = Banned group
    $groupscache = $cache->read('usergroups');
    if (is_array($groupscache)) {
        foreach ($groupscache as $group) {
            if (!empty($group['isbannedgroup'])) {
                $banned_gid = (int)$group['gid'];
                break;
            }
        }
    } else {
        // Cache miss — query directly
        $gres = $db->sql_query_prepared("SELECT gid FROM usergroups WHERE isbannedgroup='1' LIMIT 1");
        $grow = $gres ? $db->fetch_array($gres) : null;
        if ($grow) {
            $banned_gid = (int)$grow['gid'];
        }
    }

    $uq = $db->sql_query_prepared("SELECT usergroup, additionalgroups, displaygroup FROM users WHERE id = ?", [$uid]);
    $user = $uq ? $db->fetch_array($uq) : null;
    if (!$user) {
        return;
    }

    // Insert into banned table
    $db->sql_query_prepared(
        "INSERT INTO banned (`uid`,`gid`,`oldgroup`,`oldadditionalgroups`,`olddisplaygroup`,`admin`,`dateline`,`bantime`,`lifted`,`reason`) VALUES (?,?,?,?,?,?,?,?,?,?)",
        [
            $uid,
            $banned_gid,
            (int)$user['usergroup'],
            $user['additionalgroups'],
            (int)$user['displaygroup'],
            $banned_by,
            TIMENOW,
            '---',
            0,
            $reason,
        ]
    );

    // Move to banned group and disable account — mirrors banning.php processBanAction()
    $db->sql_query_prepared(
        "UPDATE users SET usergroup = ?, displaygroup = 0, additionalgroups = '', enabled = 'no', passkey = '' WHERE id = ?",
        [$banned_gid, $uid]
    );

    $db->sql_query_prepared("DELETE FROM forumsubscriptions WHERE uid = ?", [$uid]);
    $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE uid = ?", [$uid]);
}

// ── POST обработка ────────────────────────────────────────

$ca_message = '';

if (($_POST['do'] ?? '') === 'apply') {

    verify_post_check($mybb->get_input('my_post_key'));

    // Бан
    if (!empty($_POST['ban']) && is_array($_POST['ban'])) {
        $ids    = array_map('intval', $_POST['ban']);
        $reason = 'Banned by ' . $CURUSER['username'] . ' via Cheat Attempts panel';
        $modcomment = gmdate('Y-m-d') . ' - Banned by ' . $CURUSER['username'] . ' (Cheat Attempt)' . $eol;

        foreach ($ids as $uid) {
            cheat_full_ban($uid, $reason, (int)$CURUSER['id']);
            $db->sql_query_prepared(
                'UPDATE users SET modcomment=CONCAT(?, modcomment) WHERE id = ?',
                [$modcomment, $uid]
            );
        }
        $ca_message = $lang->cheat_attempts['flash_banned'];
    }

    // Предупреждение
    if (!empty($_POST['warn']) && is_array($_POST['warn'])) {
        $ids        = array_map('intval', $_POST['warn']);
        $ids_ph     = implode(',', array_fill(0, count($ids), '?'));
        $warneduntil = TIMENOW + 604800; // 1 неделя
        $modcomment  = gmdate('Y-m-d') . ' - Warned by ' . $CURUSER['username'] . ' (Cheat Attempt)' . $eol;

        $db->sql_query_prepared(
            "UPDATE users SET warned='yes', timeswarned=timeswarned+1,
             lastwarned=?, warnedby=?,
             warneduntil=?,
             modcomment=CONCAT(?, modcomment)
             WHERE id IN ({$ids_ph})",
            [TIMENOW, (int)$CURUSER['id'], $warneduntil, $modcomment, ...$ids]
        );

        require_once INC_PATH . '/functions_pm.php';
        $res = $db->sql_query_prepared("SELECT id FROM users WHERE id IN ({$ids_ph})", $ids);
        while ($res && ($arr = $db->fetch_array($res))) {
            send_pm([
                'subject' => $lang->cheat_attempts['pm_warn_subject'],
                'message' => $lang->cheat_attempts['pm_warn_message'],
                'touid'   => (int)$arr['id'],
                'sender'  => ['uid' => -1],
            ], -1, true);
        }
        $ca_message = $lang->cheat_attempts['flash_warned'];
    }

    // Удаление записей
    if (!empty($_POST['delete']) && is_array($_POST['delete'])) {
        $ids = array_map('intval', $_POST['delete']);
        $ids_ph = implode(',', array_fill(0, count($ids), '?'));
        $db->sql_query_prepared("DELETE FROM cheat_attempts WHERE id IN ({$ids_ph})", $ids);
        $ca_message = $lang->cheat_attempts['flash_deleted'];
    }

    // Авто-бан: 5+ high severity за последний час
    if (isset($_POST['autoban'])) {
        $res = $db->sql_query_prepared(
            "SELECT uid, COUNT(*) AS cnt
             FROM cheat_attempts
             WHERE added > ? AND severity = 'high'
             GROUP BY uid
             HAVING cnt >= 5",
            [TIMENOW - 3600]
        );
        $banned  = 0;
        $modcomment = gmdate('Y-m-d') . ' - Auto-banned by system (5+ cheat violations/hour)' . $eol;
        while ($res && ($row = $db->fetch_array($res))) {
            $uid = (int)$row['uid'];
            cheat_full_ban($uid, 'Auto-banned: 5+ high severity cheat violations in one hour', (int)$CURUSER['id']);
            $db->sql_query_prepared(
                'UPDATE users SET modcomment=CONCAT(?, modcomment) WHERE id = ?',
                [$modcomment, $uid]
            );
            $banned++;
        }
        $ca_message = ags_fmt($lang->cheat_attempts['flash_autoban'], $banned);
    }
}

// ── Пагинация ─────────────────────────────────────────────

$severity  = $_GET['severity'] ?? '';
$whereExtra = match($severity) {
    'high'   => "WHERE c.severity = 'high'",
    'medium' => "WHERE c.severity = 'medium'",
    default  => '',
};

$countQuery = $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM cheat_attempts c {$whereExtra}");
$countRow = $countQuery ? $db->fetch_array($countQuery) : null;
$count    = (int)($countRow['cnt'] ?? 0);

$perpage = max(1, (int)($torrentsperpage ?? 20));
$page    = max(1, (int)($mybb->input['page'] ?? 1));
$start   = ($page - 1) * $perpage;
$pages   = $count > 0 ? (int)ceil($count / $perpage) : 1;
if ($page > $pages) { $page = 1; $start = 0; }

$multipage = multipage($count, $perpage, $page, $_this_script_);

// ── Счётчики по severity ──────────────────────────────────
$statsQuery = $db->sql_query_prepared(
    "SELECT
        SUM(severity='high')   AS high_count,
        SUM(severity='medium') AS medium_count,
        COUNT(*)               AS total
     FROM cheat_attempts"
);
$stats = $statsQuery ? $db->fetch_array($statsQuery) : null;

$severityLabel = [
    'high'   => $lang->cheat_attempts['opt_severity_high'],
    'medium' => $lang->cheat_attempts['opt_severity_medium'],
    'low'    => $lang->cheat_attempts['opt_severity_low'],
];

// JS strings: js_* keys → AGS_LANG without the prefix
$ca_js_lang = [];
foreach ($lang->cheat_attempts as $ca_k => $ca_v) {
    if (str_starts_with((string)$ca_k, 'js_')) {
        $ca_js_lang[substr((string)$ca_k, 3)] = $ca_v;
    }
}

stdhead($lang->cheat_attempts['page_title']);
?>

<div class="container mt-3">
  <div class="card shadow-sm border-0">

    <div class="card-header bg-danger text-white py-3 d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i><?= htmlspecialchars($lang->cheat_attempts['pane_title']) ?></h5>
      <?php if ($ca_message): ?>
      <span class="badge bg-white text-danger"><?= htmlspecialchars($ca_message) ?></span>
      <?php endif; ?>
    </div>

    <div class="card-body">

      <!-- Статистика -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card border-0 bg-light text-center p-3">
            <div class="fs-4 fw-bold"><?= (int)($stats['total'] ?? 0) ?></div>
            <small class="text-muted"><?= htmlspecialchars($lang->cheat_attempts['lbl_total_records']) ?></small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 bg-danger bg-opacity-10 text-center p-3">
            <div class="fs-4 fw-bold text-danger"><?= (int)($stats['high_count'] ?? 0) ?></div>
            <small class="text-muted"><?= htmlspecialchars($lang->cheat_attempts['lbl_high_severity']) ?></small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 bg-warning bg-opacity-10 text-center p-3">
            <div class="fs-4 fw-bold text-warning"><?= (int)($stats['medium_count'] ?? 0) ?></div>
            <small class="text-muted"><?= htmlspecialchars($lang->cheat_attempts['lbl_medium_severity']) ?></small>
          </div>
        </div>
        <div class="col-md-3 d-flex align-items-center gap-2">
          <a href="<?= $_this_script_ ?>&severity=high"   class="btn btn-sm btn-outline-danger w-50"><?= htmlspecialchars($lang->cheat_attempts['btn_filter_high']) ?></a>
          <a href="<?= $_this_script_ ?>"                 class="btn btn-sm btn-outline-secondary w-50"><?= htmlspecialchars($lang->cheat_attempts['btn_filter_all']) ?></a>
        </div>
      </div>

      <?= $multipage ?>

      <form method="post" action="<?= htmlspecialchars($_this_script_) ?>">
        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
        <input type="hidden" name="do" value="apply">

        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th><?= htmlspecialchars($lang->cheat_attempts['th_user']) ?></th>
                <th><?= htmlspecialchars($lang->cheat_attempts['th_date']) ?></th>
                <th><?= htmlspecialchars($lang->cheat_attempts['th_torrent']) ?></th>
                <th><?= htmlspecialchars($lang->cheat_attempts['th_reason']) ?></th>
                <th><?= htmlspecialchars($lang->cheat_attempts['th_detail']) ?></th>
                <th><?= htmlspecialchars($lang->cheat_attempts['th_severity']) ?></th>
                <th><?= htmlspecialchars($lang->cheat_attempts['th_ip']) ?></th>
                <th class="text-center"><?= htmlspecialchars($lang->cheat_attempts['th_ban']) ?></th>
                <th class="text-center"><?= htmlspecialchars($lang->cheat_attempts['th_warn']) ?></th>
                <th class="text-center"><?= htmlspecialchars($lang->cheat_attempts['th_delete']) ?></th>
              </tr>
            </thead>
            <tbody>
            <?php
            $res = $db->sql_query_prepared(
                "SELECT c.id, c.uid, c.torrentid, c.added, c.reason, c.detail, c.severity, c.ip,
                        u.username, u.usergroup, u.enabled, u.donor, u.leechwarn, u.warned,
                        u.canupload, u.candownload, u.cancomment,
                        t.name AS torrent_name
                 FROM cheat_attempts c
                 LEFT JOIN users       u ON c.uid       = u.id
                 LEFT JOIN torrents    t ON c.torrentid = t.id
                 {$whereExtra}
                 ORDER BY c.added DESC
                 LIMIT ?, ?",
                [$start, $perpage]
            );

            $severityBadge = [
                'high'   => 'danger',
                'medium' => 'warning',
                'low'    => 'secondary',
            ];

            $reasonLabel = [
                'fake_completed_event'         => $lang->cheat_attempts['reason_fake_completed_event'],
                'completed_without_download'   => $lang->cheat_attempts['reason_completed_without_download'],
                'fake_seeding'                 => $lang->cheat_attempts['reason_fake_seeding'],
                'peer_id_changed'              => $lang->cheat_attempts['reason_peer_id_changed'],
                'suspicious_peer_id'           => $lang->cheat_attempts['reason_suspicious_peer_id'],
                'negative_values'              => $lang->cheat_attempts['reason_negative_values'],
                'completed_while_seeding'      => $lang->cheat_attempts['reason_completed_while_seeding'],
                'speed_anomaly'                => $lang->cheat_attempts['reason_speed_anomaly'],
                'port_changed'                 => $lang->cheat_attempts['reason_port_changed'],
                'announce_spam'                => $lang->cheat_attempts['reason_announce_spam'],
                'banned_cheat_client'          => $lang->cheat_attempts['reason_banned_cheat_client'],
                'instant_stop_after_complete'  => $lang->cheat_attempts['reason_instant_stop_after_complete'],
                'impossible_ratio_new_torrent' => $lang->cheat_attempts['reason_impossible_ratio_new_torrent'],
                'extreme_ratio'                => $lang->cheat_attempts['reason_extreme_ratio'],
                'empty_user_agent'             => $lang->cheat_attempts['reason_empty_user_agent'],
                'seed_with_left'               => $lang->cheat_attempts['reason_seed_with_left'],
                'fake_completed_no_data'       => $lang->cheat_attempts['reason_fake_completed_no_data'],
                'multi_ip_same_peer_id'        => $lang->cheat_attempts['reason_multi_ip_same_peer_id'],
                'too_many_torrents_single_ip'  => $lang->cheat_attempts['reason_too_many_torrents_single_ip'],
            ];

            // Человеческие описания деталей
            function format_cheat_detail(string $reason, string $detail): string {
                global $lang;
                $mb = ' ' . $lang->cheat_attempts['unit_mb'];
                switch ($reason) {
                    case 'announce_spam':
                        preg_match('/Only (\d+)s/', $detail, $m);
                        return ags_fmt($lang->cheat_attempts['detail_announce_spam'], $m[1] ?? '?');
                    case 'speed_anomaly':
                        preg_match('/avg=([\d.]+) MB\/s/', $detail, $m);
                        return ags_fmt($lang->cheat_attempts['detail_speed_anomaly'], $m[1] ?? '?');
                    case 'negative_values':
                        return $lang->cheat_attempts['detail_negative_values'];
                    case 'peer_id_changed':
                        preg_match('/old=(\S+) new=(\S+)/', $detail, $m);
                        return ags_fmt($lang->cheat_attempts['detail_peer_id_changed'], htmlspecialchars($m[1] ?? '?'), htmlspecialchars($m[2] ?? '?'));
                    case 'fake_completed_event':
                        preg_match('/left=(\d+)/', $detail, $m);
                        $left = isset($m[1]) ? number_format((int)$m[1] / 1024 / 1024, 1) . $mb : '?';
                        return ags_fmt($lang->cheat_attempts['detail_fake_completed_event'], $left);
                    case 'fake_completed_no_data':
                        return $lang->cheat_attempts['detail_fake_completed_no_data'];
                    case 'fake_seeding':
                        preg_match('/left=(\d+)/', $detail, $m);
                        $left = isset($m[1]) ? number_format((int)$m[1] / 1024 / 1024, 1) . $mb : '?';
                        return ags_fmt($lang->cheat_attempts['detail_fake_seeding'], $left);
                    case 'multi_ip_same_peer_id':
                        preg_match('/(\d+) different IPs/', $detail, $m);
                        return ags_fmt($lang->cheat_attempts['detail_multi_ip_same_peer_id'], $m[1] ?? '?');
                    case 'extreme_ratio':
                        preg_match('/ratio=([\d.]+)/', $detail, $m);
                        return ags_fmt($lang->cheat_attempts['detail_extreme_ratio'], number_format((float)($m[1] ?? 0), 1));
                    case 'instant_stop_after_complete':
                        preg_match('/after only (\d+)s/', $detail, $m);
                        return ags_fmt($lang->cheat_attempts['detail_instant_stop_after_complete'], $m[1] ?? '?');
                    case 'banned_cheat_client':
                        return $lang->cheat_attempts['detail_banned_cheat_client'];
                    default:
                        return htmlspecialchars($detail);
                }
            }

            while ($res && ($arr = $db->fetch_array($res))):
                $badgeColor  = $severityBadge[$arr['severity']] ?? 'secondary';
                $reasonText  = $reasonLabel[$arr['reason']] ?? htmlspecialchars($arr['reason']);
                $torrentLink = $BASEURL . '/' . get_torrent_link((int)$arr['torrentid']);
                $profileLink = $BASEURL . '/' . get_profile_link((int)$arr['uid']);
            ?>
            <tr class="<?= $arr['severity'] === 'high' ? 'table-danger bg-opacity-25' : '' ?>">
              <td>
                <a href="<?= $profileLink ?>" class="text-decoration-none fw-semibold">
                  <?= format_name(htmlspecialchars_uni($arr['username'] ?? ''), (string)$arr['usergroup']) ?>
                </a>
                <?= get_user_icons($arr) ?>
              </td>
              <td>
                <small class="text-muted d-block"><?= my_datee($dateformat, (int)$arr['added']) ?></small>
                <small class="text-muted"><?= my_datee($timeformat, (int)$arr['added']) ?></small>
              </td>
              <td>
                <?php if ($arr['torrentid']): ?>
                <a href="<?= $torrentLink ?>" class="text-decoration-none small"
                   title="<?= htmlspecialchars_uni($arr['torrent_name'] ?? '') ?>">
                  #<?= (int)$arr['torrentid'] ?>
                </a>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-<?= $badgeColor ?>"><?= $reasonText ?></span></td>
              <td>
                <small class="text-muted" style="max-width:300px;display:block;word-break:break-word">
                  <?= format_cheat_detail($arr['reason'] ?? '', $arr['detail'] ?? '') ?>
                </small>
              </td>
              <td>
                <span class="badge bg-<?= $badgeColor ?>">
                  <?= htmlspecialchars($severityLabel[$arr['severity'] ?? 'medium'] ?? ucfirst((string)$arr['severity'])) ?>
                </span>
              </td>
              <td><code class="small"><?= htmlspecialchars($arr['ip'] ?? '') ?></code></td>
              <td class="text-center">
                <div class="form-check form-switch d-inline-block">
                  <input type="checkbox" class="form-check-input" name="ban[]"    value="<?= (int)$arr['uid'] ?>">
                </div>
              </td>
              <td class="text-center">
                <div class="form-check form-switch d-inline-block">
                  <input type="checkbox" class="form-check-input" name="warn[]"   value="<?= (int)$arr['uid'] ?>">
                </div>
              </td>
              <td class="text-center">
                <div class="form-check form-switch d-inline-block">
                  <input type="checkbox" class="form-check-input" name="delete[]" value="<?= (int)$arr['id'] ?>">
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="10" class="text-end py-3">
                  <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-danger"   onclick="checkAll('ban[]')">
                      <i class="fas fa-check-square me-1"></i><?= htmlspecialchars($lang->cheat_attempts['btn_all_ban']) ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning"  onclick="checkAll('warn[]')">
                      <i class="fas fa-check-square me-1"></i><?= htmlspecialchars($lang->cheat_attempts['btn_all_warn']) ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="checkAll('delete[]')">
                      <i class="fas fa-check-square me-1"></i><?= htmlspecialchars($lang->cheat_attempts['btn_all_delete']) ?>
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                      <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($lang->cheat_attempts['btn_apply']) ?>
                    </button>
                    <button type="submit" name="autoban" value="1" class="btn btn-sm btn-dark ms-2"
                            onclick="return confirm(t('autoban_confirm', 'Auto-ban users with 5+ high violations in last hour?'))">
                      <i class="fas fa-robot me-1"></i><?= htmlspecialchars($lang->cheat_attempts['btn_autoban']) ?>
                    </button>
                  </div>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </form>

      <?= $multipage ?>
    </div>
  </div>
</div>

<script>
const AGS_LANG = <?= json_encode($ca_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function t(key, fallback, ...args) {
    let s = (AGS_LANG && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
    args.forEach((a, i) => {
        const n = i + 1;
        s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
    });
    return s;
}

function checkAll(name) {
    const boxes = document.querySelectorAll('input[name="' + name + '"]');
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
}
</script>

<?php stdfoot(); ?>