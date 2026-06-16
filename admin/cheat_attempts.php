<?php
declare(strict_types=1);


require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_mkprettytime.php';

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger">Direct initialization not allowed.</div>');
}

$eol = PHP_EOL;

// ── POST обработка ────────────────────────────────────────

$ca_message = '';

if (($_POST['do'] ?? '') === 'apply') {

    // Бан
    if (!empty($_POST['ban']) && is_array($_POST['ban'])) {
        $ids = implode(',', array_map('intval', $_POST['ban']));
        $modcomment = gmdate('Y-m-d') . ' - Banned by ' . $CURUSER['username'] . ' (Cheat Attempt)' . $eol;
        $db->sql_query(
            'UPDATE users SET enabled=\'no\', passkey=\'\',
             modcomment=CONCAT(' . $db->sqlesc($modcomment) . ', modcomment)
             WHERE id IN (' . $ids . ')'
        );
        $ca_message = 'Users have been banned';
    }

    // Предупреждение
    if (!empty($_POST['warn']) && is_array($_POST['warn'])) {
        $ids        = implode(',', array_map('intval', $_POST['warn']));
        $warneduntil = TIMENOW + 604800; // 1 неделя
        $modcomment  = gmdate('Y-m-d') . ' - Warned by ' . $CURUSER['username'] . ' (Cheat Attempt)' . $eol;

        $db->sql_query(
            "UPDATE users SET warned='yes', timeswarned=timeswarned+1,
             lastwarned=" . TIMENOW . ", warnedby=" . (int)$CURUSER['id'] . ",
             warneduntil=" . $warneduntil . ",
             modcomment=CONCAT(" . $db->sqlesc($modcomment) . ", modcomment)
             WHERE id IN ({$ids})"
        );

        require_once INC_PATH . '/functions_pm.php';
        $res = $db->sql_query('SELECT id FROM users WHERE id IN (' . $ids . ')');
        while ($arr = $db->fetch_array($res)) {
            send_pm([
                'subject' => 'Warning: Suspicious Activity Detected',
                'message' => 'Your account has been flagged for suspicious upload activity. Please contact staff if you believe this is an error.',
                'touid'   => (int)$arr['id'],
                'sender'  => ['uid' => -1],
            ], -1, true);
        }
        $ca_message = 'Users have been warned';
    }

    // Удаление записей
    if (!empty($_POST['delete']) && is_array($_POST['delete'])) {
        $ids = implode(',', array_map('intval', $_POST['delete']));
        $db->sql_query('DELETE FROM cheat_attempts WHERE id IN (' . $ids . ')');
        $ca_message = 'Records deleted';
    }

    // Авто-бан: 5+ high severity за последний час
    if (isset($_POST['autoban'])) {
        $res = $db->sql_query(
            "SELECT uid, COUNT(*) AS cnt
             FROM cheat_attempts
             WHERE added > " . (TIMENOW - 3600) . " AND severity = 'high'
             GROUP BY uid
             HAVING cnt >= 5"
        );
        $banned = 0;
        while ($row = $db->fetch_array($res)) {
            $modcomment = gmdate('Y-m-d') . ' - Auto-banned by system (5+ cheat violations/hour)' . $eol;
            $db->sql_query(
                "UPDATE users SET enabled='no', passkey='',
                 modcomment=CONCAT(" . $db->sqlesc($modcomment) . ", modcomment)
                 WHERE id = " . (int)$row['uid']
            );
            $banned++;
        }
        $ca_message = "Auto-ban complete: {$banned} user(s) banned";
    }
}

// ── Пагинация ─────────────────────────────────────────────

$severity  = $_GET['severity'] ?? '';
$whereExtra = match($severity) {
    'high'   => "WHERE c.severity = 'high'",
    'medium' => "WHERE c.severity = 'medium'",
    default  => '',
};

$countRow = $db->fetch_array($db->sql_query("SELECT COUNT(*) AS cnt FROM cheat_attempts c {$whereExtra}"));
$count    = (int)($countRow['cnt'] ?? 0);

$perpage = max(1, (int)($torrentsperpage ?? 20));
$page    = max(1, (int)($mybb->input['page'] ?? 1));
$start   = ($page - 1) * $perpage;
$pages   = $count > 0 ? (int)ceil($count / $perpage) : 1;
if ($page > $pages) { $page = 1; $start = 0; }

$multipage = multipage($count, $perpage, $page, $_this_script_);

// ── Счётчики по severity ──────────────────────────────────
$stats = $db->fetch_array($db->sql_query(
    "SELECT
        SUM(severity='high')   AS high_count,
        SUM(severity='medium') AS medium_count,
        COUNT(*)               AS total
     FROM cheat_attempts"
));

stdhead('Cheat Attempts');
?>

<div class="container mt-3">
  <div class="card shadow-sm border-0">

    <div class="card-header bg-danger text-white py-3 d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Cheat Attempts</h5>
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
            <small class="text-muted">Total Records</small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 bg-danger bg-opacity-10 text-center p-3">
            <div class="fs-4 fw-bold text-danger"><?= (int)($stats['high_count'] ?? 0) ?></div>
            <small class="text-muted">High Severity</small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 bg-warning bg-opacity-10 text-center p-3">
            <div class="fs-4 fw-bold text-warning"><?= (int)($stats['medium_count'] ?? 0) ?></div>
            <small class="text-muted">Medium Severity</small>
          </div>
        </div>
        <div class="col-md-3 d-flex align-items-center gap-2">
          <a href="<?= $_this_script_ ?>&severity=high"   class="btn btn-sm btn-outline-danger w-50">High only</a>
          <a href="<?= $_this_script_ ?>"                 class="btn btn-sm btn-outline-secondary w-50">All</a>
        </div>
      </div>

      <?= $multipage ?>

      <form method="post" action="<?= htmlspecialchars($_this_script_) ?>">
        <input type="hidden" name="do" value="apply">

        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>User</th>
                <th>Date</th>
                <th>Torrent</th>
                <th>Reason</th>
                <th>Detail</th>
                <th>Severity</th>
                <th>IP</th>
                <th class="text-center">Ban</th>
                <th class="text-center">Warn</th>
                <th class="text-center">Del</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $res = $db->sql_query(
                "SELECT c.id, c.uid, c.torrentid, c.added, c.reason, c.detail, c.severity, c.ip,
                        u.username, u.usergroup, u.enabled, u.donor, u.leechwarn, u.warned,
                        u.canupload, u.candownload, u.cancomment,
                        t.name AS torrent_name
                 FROM cheat_attempts c
                 LEFT JOIN users       u ON c.uid       = u.id
                 LEFT JOIN torrents    t ON c.torrentid = t.id
                 {$whereExtra}
                 ORDER BY c.added DESC
                 LIMIT {$start}, {$perpage}"
            );

            $severityBadge = [
                'high'   => 'danger',
                'medium' => 'warning',
                'low'    => 'secondary',
            ];

            $reasonLabel = [
                'fake_completed_event'       => '🎭 Fake Complete',
                'completed_without_download' => '📥 Complete Without Download',
                'fake_seeding'               => '🌱 Fake Seeding',
                'peer_id_changed'            => '🔄 Client Changed',
                'suspicious_peer_id'         => '🕵️ Suspicious Client',
                'negative_values'            => '➖ Negative Values',
                'completed_while_seeding'    => '⚡ Already Seeding',
                'speed_anomaly'              => '🚀 Impossible Speed',
                'port_changed'               => '🔌 Port Changed',
                'announce_spam'              => '📢 Announce Spam',
                'banned_cheat_client'        => '🚫 Banned Client',
                'instant_stop_after_complete'=> '⏱️ Instant Stop',
                'impossible_ratio_new_torrent'=> '📊 Impossible Ratio',
                'extreme_ratio'              => '📈 Extreme Ratio',
                'empty_user_agent'           => '👻 No User Agent',
                'seed_with_left'             => '🌱 Seeding Incomplete',
                'fake_completed_no_data'     => '🎭 Fake Complete (No Data)',
                'multi_ip_same_peer_id'      => '🌐 Multiple IPs',
                'too_many_torrents_single_ip'=> '🌊 IP Flood',
            ];

            // Человеческие описания деталей
            function format_cheat_detail(string $reason, string $detail): string {
                switch ($reason) {
                    case 'announce_spam':
                        preg_match('/Only (\d+)s/', $detail, $m);
                        return 'Announced again after only ' . ($m[1] ?? '?') . ' seconds (minimum: 30s)';
                    case 'speed_anomaly':
                        preg_match('/avg=([\d.]+) MB\/s/', $detail, $m);
                        return 'Average upload speed: ' . ($m[1] ?? '?') . ' MB/s — physically impossible';
                    case 'negative_values':
                        return 'Sent negative upload/download values — possible exploit attempt';
                    case 'peer_id_changed':
                        preg_match('/old=(\S+) new=(\S+)/', $detail, $m);
                        return 'Client changed from ' . htmlspecialchars($m[1] ?? '?') . ' to ' . htmlspecialchars($m[2] ?? '?');
                    case 'fake_completed_event':
                        preg_match('/left=(\d+)/', $detail, $m);
                        $left = isset($m[1]) ? number_format((int)$m[1] / 1024 / 1024, 1) . ' MB' : '?';
                        return 'Claimed download complete but still has ' . $left . ' remaining';
                    case 'fake_completed_no_data':
                        return 'Sent "completed" event but downloaded nothing this session';
                    case 'fake_seeding':
                        preg_match('/left=(\d+)/', $detail, $m);
                        $left = isset($m[1]) ? number_format((int)$m[1] / 1024 / 1024, 1) . ' MB' : '?';
                        return 'Reporting as seeder but file is incomplete (' . $left . ' remaining)';
                    case 'multi_ip_same_peer_id':
                        preg_match('/(\d+) different IPs/', $detail, $m);
                        return 'Same client ID seen from ' . ($m[1] ?? '?') . ' different IP addresses';
                    case 'extreme_ratio':
                        preg_match('/ratio=([\d.]+)/', $detail, $m);
                        return 'Suspiciously high ratio: ' . number_format((float)($m[1] ?? 0), 1) . ':1';
                    case 'instant_stop_after_complete':
                        preg_match('/after only (\d+)s/', $detail, $m);
                        return 'Stopped seeding ' . ($m[1] ?? '?') . ' seconds after completing download';
                    case 'banned_cheat_client':
                        return 'Using a known ratio-cheating client';
                    default:
                        return htmlspecialchars($detail);
                }
            }

            while ($arr = $db->fetch_array($res)):
                $badgeColor  = $severityBadge[$arr['severity']] ?? 'secondary';
                $reasonText  = $reasonLabel[$arr['reason']] ?? htmlspecialchars($arr['reason']);
                $torrentLink = $BASEURL . '/' . get_torrent_link((int)$arr['torrentid']);
                $profileLink = $BASEURL . '/' . get_profile_link((int)$arr['uid']);
            ?>
            <tr class="<?= $arr['severity'] === 'high' ? 'table-danger bg-opacity-25' : '' ?>">
              <td>
                <a href="<?= $profileLink ?>" class="text-decoration-none fw-semibold">
                  <?= format_name(htmlspecialchars_uni($arr['username'] ?? ''), $arr['usergroup']) ?>
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
                  <?= ucfirst($arr['severity'] ?? 'medium') ?>
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
                      <i class="fas fa-check-square me-1"></i>All Ban
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning"  onclick="checkAll('warn[]')">
                      <i class="fas fa-check-square me-1"></i>All Warn
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="checkAll('delete[]')">
                      <i class="fas fa-check-square me-1"></i>All Delete
                    </button>
                    <button type="submit" class="btn btn-sm btn-success">
                      <i class="fas fa-check-circle me-1"></i>Apply
                    </button>
                    <button type="submit" name="autoban" value="1" class="btn btn-sm btn-dark ms-2"
                            onclick="return confirm('Auto-ban users with 5+ high violations in last hour?')">
                      <i class="fas fa-robot me-1"></i>Auto-Ban (5+/h)
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
function checkAll(name) {
    const boxes = document.querySelectorAll('input[name="' + name + '"]');
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
}
</script>

<?php stdfoot(); ?>