<?php
declare(strict_types=1);

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_mkprettytime.php';

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

define('CA_VERSION', '0.6 by xam');

// ── POST actions ──────────────────────────────────────────────────────────────
$ca_message = '';

if (($_POST['do'] ?? '') === 'apply') {

    if (!empty($_POST['ban']) && is_array($_POST['ban'])) {
        $ids         = implode(',', array_map('intval', $_POST['ban']));
        $modcomment  = gmdate('Y-m-d') . ' - Banned by ' . $CURUSER['username'] . ' (Cheat Attempt)' . PHP_EOL;
        $db->sql_query("UPDATE users SET enabled='no', passkey='', modcomment=CONCAT(" . $db->sqlesc($modcomment) . ", modcomment) WHERE id IN ({$ids})");
        $ca_message = 'Users have been banned';
    }

    if (!empty($_POST['warn']) && is_array($_POST['warn'])) {
        $ids         = implode(',', array_map('intval', $_POST['warn']));
        $warneduntil = TIMENOW + 604800;
        $modcomment  = gmdate('Y-m-d') . ' - Warned by ' . $CURUSER['username'] . ' (Cheat Attempt)' . PHP_EOL;

        $db->sql_query("UPDATE users SET
            warned       = 'yes',
            timeswarned  = timeswarned + 1,
            lastwarned   = " . TIMENOW . ",
            warnedby     = " . (int)$CURUSER['id'] . ",
            warneduntil  = " . (int)$warneduntil . ",
            modcomment   = CONCAT(" . $db->sqlesc($modcomment) . ", modcomment)
            WHERE id IN ({$ids})");

        require_once INC_PATH . '/functions_pm.php';
        $res = $db->sql_query("SELECT id FROM users WHERE id IN ({$ids})");
        while ($arr = $db->fetch_array($res)) {
            send_pm([
                'subject'        => 'You have been warned!',
                'message'        => 'You have been warned for 1 week because of Possible Cheat Attempt!',
                'touid'          => (int)$arr['id'],
                'sender'         => ['uid' => -1],
            ], -1, true);
        }
        $ca_message = 'Users have been warned';
    }

    if (!empty($_POST['delete']) && is_array($_POST['delete'])) {
        $ids = implode(',', array_map('intval', $_POST['delete']));
        $db->sql_query("DELETE FROM cheat_attempts WHERE id IN ({$ids})");
        $ca_message = 'Cheat Attempts have been deleted!';
    }
}

// ── Pagination ────────────────────────────────────────────────────────────────
$row   = $db->fetch_array($db->sql_query('SELECT COUNT(*) AS cnt FROM cheat_attempts'));
$count = (int)($row['cnt'] ?? 0);
$perpage = max(1, (int)($ts_perpage ?? 20));
$page    = max(1, (int)($mybb->input['page'] ?? 1));
$pages   = (int)ceil($count / $perpage);

if ($page > $pages) $page = 1;

$start     = ($page - 1) * $perpage;
$page_url  = $_this_script_ ?? '';
$multipage = multipage($count, $perpage, $page, $page_url);

// ── Output ────────────────────────────────────────────────────────────────────
stdhead('Cheat Attempts');
?>

<div class="container mt-3">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Cheat Attempts
                        <?php if ($ca_message): ?>
                            <span class="badge bg-danger ms-2"><?= htmlspecialchars($ca_message) ?></span>
                        <?php endif; ?>
                    </h5>
                </div>

                <div class="card-body">
                    <div class="mb-4"><?= $multipage ?></div>

                    <form method="post" action="">
                        <input type="hidden" name="do" value="apply">

                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="fas fa-user me-1"></i> User</th>
                                        <th><i class="fas fa-calendar me-1"></i> Added</th>
                                        <th><i class="fas fa-file-alt me-1"></i> Torrent</th>
                                        <th><i class="fas fa-desktop me-1"></i> Agent</th>
                                        <th><i class="fas fa-tachometer-alt me-1"></i> Upload Speed</th>
                                        <th><i class="fas fa-upload me-1"></i> Uploaded</th>
                                        <th><i class="fas fa-clock me-1"></i> Within</th>
                                        <th><i class="fas fa-globe me-1"></i> IP</th>
                                        <th class="text-center"><i class="fas fa-ban me-1"></i> Ban</th>
                                        <th class="text-center"><i class="fas fa-exclamation-triangle me-1"></i> Warn</th>
                                        <th class="text-center"><i class="fas fa-trash me-1"></i> Del</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $res = $db->sql_query("
                                    SELECT c.*, u.id AS userid, u.username, u.usergroup,
                                           u.uploaded, u.enabled, u.donor, u.leechwarn, u.warned,
                                           t.name, t.added
                                    FROM cheat_attempts c
                                    LEFT JOIN users u    ON c.uid      = u.id
                                    LEFT JOIN torrents t ON c.torrentid = t.id
                                    ORDER BY c.added DESC
                                    LIMIT {$start}, {$perpage}
                                ");

                                if ($db->num_rows($res) === 0):
                                ?>
                                    <tr>
                                        <td colspan="11">
                                            <div class="text-center py-5">
                                                <i class="fas fa-shield-alt fa-4x text-muted mb-3"></i>
                                                <h5 class="text-muted">No cheat attempts found.</h5>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: while ($arr = $db->fetch_array($res)): ?>
                                    <?php
                                    $torrent_name  = htmlspecialchars_uni($arr['name'] ?? 'Unknown Torrent');
                                    $torrent_added = my_datee($dateformat, (int)$arr['added']);
                                    $formatted_name = format_name($arr['username'] ?? '', $arr['usergroup'] ?? 0);

                                    $popover_title   = htmlspecialchars('📁 ' . cutename($arr['name'] ?? 'Unknown', 20), ENT_QUOTES);
                                    $popover_content = htmlspecialchars('
                                        <div class="torrent-popover">
                                            <div class="mb-2">
                                                <strong>📂 Full Name:</strong><br>
                                                <span class="text-break">' . $torrent_name . '</span>
                                            </div>
                                            <div class="d-flex justify-content-between border-top pt-2 small text-muted">
                                                <span>' . $formatted_name . '</span>
                                                <span>' . $torrent_added . '</span>
                                            </div>
                                        </div>', ENT_QUOTES);
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?= $BASEURL . '/' . get_profile_link((int)$arr['userid']) ?>" class="text-decoration-none">
                                                <?= format_name(htmlspecialchars_uni($arr['username'] ?? ''), $arr['usergroup'] ?? 0) ?>
                                            </a>
                                            <span class="user-icons"><?= get_user_icons($arr) ?></span>
                                        </td>
                                        <td>
                                            <div class="small"><?= my_datee($dateformat, (int)$arr['added']) ?></div>
                                            <div class="text-muted smaller"><?= my_datee($timeformat, (int)$arr['added']) ?></div>
                                        </td>
                                        <td>
                                            <a href="<?= $BASEURL . '/' . get_torrent_link((int)$arr['torrentid']) ?>"
                                               data-bs-toggle="popover"
                                               data-bs-placement="top"
                                               data-bs-title="<?= $popover_title ?>"
                                               data-bs-content="<?= $popover_content ?>"
                                               data-bs-html="true"
                                               class="badge bg-info text-decoration-none">
                                                #<?= (int)$arr['torrentid'] ?>
                                            </a>
                                        </td>
                                        <td><span class="font-monospace small"><?= htmlspecialchars_uni($arr['agent'] ?? '') ?></span></td>
                                        <td><span class="badge bg-warning text-dark"><?= mksize((int)($arr['transfer_rate'] ?? 0)) ?>/s</span></td>
                                        <td><?= mksize((int)($arr['upthis'] ?? 0)) ?></td>
                                        <td><span class="badge bg-secondary"><?= mkprettytime((int)($arr['timediff'] ?? 0)) ?></span></td>
                                        <td><span class="font-monospace"><?= htmlspecialchars_uni($arr['ip'] ?? '') ?></span></td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-block">
                                                <input type="checkbox" class="form-check-input ban-checkbox" name="ban[]" value="<?= (int)$arr['userid'] ?>">
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-block">
                                                <input type="checkbox" class="form-check-input warn-checkbox" name="warn[]" value="<?= (int)$arr['userid'] ?>">
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-block">
                                                <input type="checkbox" class="form-check-input delete-checkbox" name="delete[]" value="<?= (int)$arr['id'] ?>">
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="11" class="text-end">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-outline-primary" onclick="checkAll('ban[]')">
                                                    <i class="fas fa-check-square me-1"></i>Check All Ban
                                                </button>
                                                <button type="button" class="btn btn-outline-warning" onclick="checkAll('warn[]')">
                                                    <i class="fas fa-check-square me-1"></i>Check All Warn
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="checkAll('delete[]')">
                                                    <i class="fas fa-check-square me-1"></i>Check All Delete
                                                </button>
                                                <button type="submit" name="submit" value="Apply Changes" class="btn btn-success">
                                                    <i class="fas fa-check-circle me-1"></i>Apply Changes
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </form>

                    <div class="mt-4"><?= $multipage ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($BASEURL) ?>/scripts/popover.js"></script>
<script>
function checkAll(type) {
    document.querySelectorAll('input[name="' + type + '"]').forEach(cb => {
        cb.checked = !cb.checked;
    });
}
</script>

<style>
.torrent-popover { max-width: 320px; font-size: .875rem; }
.popover { border: 1px solid rgba(0,0,0,.1); border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,.15); }
.popover-header { background: var(--bs-primary); color: #fff; border-bottom: none; border-radius: 8px 8px 0 0; font-weight: 600; }
.popover-body { padding: 12px 16px; }
.text-break { word-break: break-word; }
</style>

<?php stdfoot(); ?>