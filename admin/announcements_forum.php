<?php
declare(strict_types=1);

/**
 * announcements_forum.php — Forum Announcements Management
 * Access: admin/index.php?act=announcements_forum
 */

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3"><strong>Error!</strong> Direct access not allowed.</div>');
}

require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_multipage.php';
require_once __DIR__ . '/../cache/smilies.php';

$parser         = new postParser();
$parser_options = [
    'allow_html'      => 1,
    'allow_mycode'    => 1,
    'allow_smilies'   => 1,
    'allow_imgcode'   => 1,
    'allow_videocode' => 1,
    'filter_badwords' => 1,
];

define('AF_VERSION', 'v1.0');

/* ─────────────────────────── Helpers ────────────────────────────── */

function af_redirect(string $msg = ''): never
{
    redirect('admin/index.php?act=announcements_forum', $msg);
}

function af_get_announcement(int $id): array
{
    global $db;
    $row = $db->fetch_array($db->sql_query(
        "SELECT a.*, u.username AS author_name
         FROM announcements a
         LEFT JOIN users u ON u.id = a.uid
         WHERE a.id = {$id} AND a.type IN ('forum','global') LIMIT 1"
    ));
    if (!$row) af_redirect('Announcement not found.');
    return $row;
}

function af_get_forums(): array
{
    global $db;
    $forums = ['-1' => '🌐 Global (All Forums)'];
    $q = $db->sql_query("SELECT fid, name, pid FROM forums ORDER BY disporder ASC");
    while ($f = $db->fetch_array($q)) {
        $prefix = $f['pid'] > 0 ? '   ↳ ' : '';
        $forums[(string)$f['fid']] = $prefix . htmlspecialchars($f['name']);
    }
    return $forums;
}

function af_parse_dates(array &$errors, int &$startdate, int &$enddate): void
{
    global $CURUSER;

    $offset   = (float)$CURUSER['timezone'] * 3600 + (int)$CURUSER['dst'] * 3600;
    $months   = ['01','02','03','04','05','06','07','08','09','10','11','12'];

    // Start date
    $sm = in_array($_POST['starttime_month'] ?? '', $months) ? $_POST['starttime_month'] : '01';
    $sd = (int)($_POST['starttime_day']  ?? 1);
    $sy = (int)($_POST['starttime_year'] ?? date('Y'));
    $st = explode(':', explode(' ', $_POST['starttime_time'] ?? '00:00')[0]);
    $sh = (int)($st[0] ?? 0);
    $si = (int)($st[1] ?? 0);
    if (stristr($_POST['starttime_time'] ?? '', 'pm')) { $sh += 12; if ($sh >= 24) $sh = 0; }

    if (!checkdate((int)$sm, $sd, $sy)) { $errors[] = 'Invalid start date.'; }
    else {
        $startdate = (int)gmmktime($sh, $si, 0, (int)$sm, $sd, $sy) - (int)$offset;
        if ($startdate <= 0) $errors[] = 'Invalid start date.';
    }

    // End date
    if ((int)($_POST['endtime_type'] ?? 1) === 2) {
        $enddate = 0;
        return;
    }

    $em = in_array($_POST['endtime_month'] ?? '', $months) ? $_POST['endtime_month'] : '01';
    $ed = (int)($_POST['endtime_day']  ?? 1);
    $ey = (int)($_POST['endtime_year'] ?? date('Y'));
    $et = explode(':', explode(' ', $_POST['endtime_time'] ?? '00:00')[0]);
    $eh = (int)($et[0] ?? 0);
    $ei = (int)($et[1] ?? 0);
    if (stristr($_POST['endtime_time'] ?? '', 'pm')) { $eh += 12; if ($eh >= 24) $eh = 0; }

    if (!checkdate((int)$em, $ed, $ey)) { $errors[] = 'Invalid end date.'; return; }
    $enddate = (int)gmmktime($eh, $ei, 0, (int)$em, $ed, $ey) - (int)$offset;
    if ($enddate <= 0) { $errors[] = 'Invalid end date.'; return; }
    if ($enddate <= $startdate) $errors[] = 'End date must be after start date.';
}

function af_build_date_selects(int $timestamp): array
{
    global $CURUSER;
    $local = (int)round($timestamp + (float)$CURUSER['timezone'] * 3600 + (int)$CURUSER['dst'] * 3600);
    return [
        'time'  => gmdate('H:i', $local),
        'day'   => (int)gmdate('j', $local),
        'month' => gmdate('m', $local),
        'year'  => (int)gmdate('Y', $local),
    ];
}

//function af_build_editor(string $value = ''): string
function af_build_editor(string $value = '', string $id = 'message'): string
{
    global $smilies, $BASEURL;
    $editor = insert_bbcode_editor($smilies, $BASEURL, 'message');
    return $editor['toolbar']
        . '<textarea class="form-control" id="message" name="message" rows="12"'
        . ' placeholder="Write your announcement using BBCode..." required>'
        . htmlspecialchars($value) . '</textarea>'
        . '<div class="form-text text-end"><span id="charCount">'
        . strlen($value) . '</span> / 5000 characters</div>'
        . $editor['modal'];
}

function af_day_options(int $selected): string
{
    $html = '';
    for ($d = 1; $d <= 31; $d++) {
        $sel   = $selected === $d ? ' selected' : '';
        $html .= "<option value=\"{$d}\"{$sel}>{$d}</option>";
    }
    return $html;
}

function af_month_options(string $selected): string
{
    $months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April',
               '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
               '09'=>'September','10'=>'October','11'=>'November','12'=>'December'];
    $html = '';
    foreach ($months as $val => $name) {
        $sel   = $selected === $val ? ' selected' : '';
        $html .= "<option value=\"{$val}\"{$sel}>{$name}</option>";
    }
    return $html;
}

function af_status_badge(array $row): string
{
    $now = TIMENOW;
    if ($row['startdate'] > $now) return '<span class="badge bg-secondary">Scheduled</span>';
    if ($row['enddate'] > 0 && $row['enddate'] < $now) return '<span class="badge bg-danger">Expired</span>';
    return '<span class="badge bg-success">Active</span>';
}

/* ─────────────────────────── Router ─────────────────────────────── */

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$do     = $_POST['do']     ?? $_GET['do']     ?? '';
$id     = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

match ($action) {
    'list'      => af_handle_list(),
    'view'      => af_handle_view($id),
    'add'       => af_handle_add($do),
    'edit'      => af_handle_edit($id, $do),
    'delete'    => af_handle_delete($id),
    'duplicate' => af_handle_duplicate($id),
    default     => af_handle_list(),
};

/* ═══════════════════════════════════════════════════════════════════
 *  LIST
 * ═══════════════════════════════════════════════════════════════════ */

function af_handle_list(): void
{
    global $db;

    stdhead('Forum Announcements ' . AF_VERSION);

    $total   = (int)$db->fetch_array($db->sql_query(
        "SELECT COUNT(*) AS c FROM announcements WHERE type IN ('forum','global')"))['c'];
    $perpage = 15;
    $page    = max(0, (int)($_GET['page'] ?? 0));
    $offset  = $page * $perpage;

    $res = $db->sql_query(
        "SELECT a.*, u.username AS author_name
         FROM announcements a LEFT JOIN users u ON u.id = a.uid
         WHERE a.type IN ('forum','global')
         ORDER BY a.added DESC LIMIT {$offset}, {$perpage}"
    );

    $forums = af_get_forums();
    $script = htmlspecialchars($_SERVER['SCRIPT_NAME']);
    $now    = TIMENOW;
    ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="fas fa-newspaper me-2 text-success"></i>Forum Announcements</h3>
            <a href="<?= $script ?>?act=announcements_forum&action=add" class="btn btn-success">
                <i class="fas fa-plus me-1"></i> New Announcement
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Announcements</h6>
                <span class="badge bg-light text-dark">Total: <?= number_format($total) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="50">ID</th>
                            <th>Subject</th>
                            <th width="120">Forum</th>
                            <th width="100">Status</th>
                            <th width="100">Start</th>
                            <th width="100">End</th>
                            <th width="130" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($db->num_rows($res) > 0): ?>
                        <?php while ($row = $db->fetch_array($res)): ?>
                        <tr>
                            <td class="text-center fw-bold text-muted"><?= (int)$row['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['subject']) ?></strong>
                                <div class="text-muted small">
                                    by <?= htmlspecialchars($row['author_name'] ?? 'Unknown') ?>
                                </div>
                            </td>
                            <td>
                                <?php $fid = (int)$row['fid']; ?>
                                <?php if ($fid === -1): ?>
                                    <span class="badge bg-primary">🌐 Global</span>
                                <?php elseif ($fid === 0): ?>
                                    <span class="badge bg-secondary">Tracker</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">
                                        <?= htmlspecialchars($forums[(string)$fid] ?? "Forum #{$fid}") ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= af_status_badge($row) ?></td>
                            <td class="small"><?= $row['startdate'] ? my_datee('d M Y', (int)$row['startdate']) : '—' ?></td>
                            <td class="small">
                                <?= $row['enddate'] ? my_datee('d M Y', (int)$row['enddate']) : '<span class="text-success">∞</span>' ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= $script ?>?act=announcements_forum&action=view&id=<?= (int)$row['id'] ?>"
                                       class="btn btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="<?= $script ?>?act=announcements_forum&action=edit&id=<?= (int)$row['id'] ?>"
                                       class="btn btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                    <button class="btn btn-outline-warning" title="Duplicate"
                                            onclick="afDuplicate(<?= (int)$row['id'] ?>)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" title="Delete"
                                            onclick="afDelete(<?= (int)$row['id'] ?>, '<?= htmlspecialchars(addslashes($row['subject'])) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>No forum announcements yet
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total > $perpage): ?>
            <div class="card-footer">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i = 0; $i < ceil($total / $perpage); $i++): ?>
                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                        <a class="page-link" href="?act=announcements_forum&action=list&page=<?= $i ?>"><?= $i + 1 ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?= af_delete_modal($script) ?>

    <script>
    function afDelete(id, subject) {
        document.getElementById('deleteTitle').textContent = subject;
        document.getElementById('deleteConfirmBtn').href =
            '<?= $script ?>?act=announcements_forum&action=delete&id=' + id + '&sure=yes';
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    function afDuplicate(id) {
        if (!confirm('Duplicate this announcement?')) return;
        fetch('<?= $script ?>?act=announcements_forum&action=duplicate&id=' + id, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'do=duplicate'
        })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.message); });
    }
    </script>
    <?php
    stdfoot();
}

/* ═══════════════════════════════════════════════════════════════════
 *  VIEW
 * ═══════════════════════════════════════════════════════════════════ */

function af_handle_view(int $id): void
{
    global $db, $parser, $parser_options;

    if ($id <= 0) af_redirect('Invalid ID.');
    $row    = af_get_announcement($id);
    $forums = af_get_forums();
    $script = htmlspecialchars($_SERVER['SCRIPT_NAME']);

    $db->sql_query("UPDATE announcements SET views = views + 1 WHERE id = {$id}");

    stdhead('View: ' . htmlspecialchars($row['subject']));
    ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="<?= $script ?>?act=announcements_forum">Forum Announcements</a>
                    </li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($row['subject']) ?></li>
                </ol>
            </nav>
            <?= af_status_badge($row) ?>
        </div>

        <div class="row g-4">
            <div class="col-lg-9">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-newspaper me-2"></i><?= htmlspecialchars($row['subject']) ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-3 mb-3 text-muted small border-bottom pb-3">
                            <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($row['author_name'] ?? 'Unknown') ?></span>
                            <span><i class="fas fa-calendar me-1"></i><?= my_datee('relative', (int)$row['added']) ?></span>
                            <span><i class="fas fa-eye me-1"></i><?= (int)$row['views'] + 1 ?> views</span>
                            <?php $fid = (int)$row['fid']; ?>
                            <span><i class="fas fa-folder me-1"></i>
                                <?= $fid === -1 ? '🌐 Global' : htmlspecialchars($forums[(string)$fid] ?? "Forum #{$fid}") ?>
                            </span>
                        </div>

                        <?php if ($row['startdate'] || $row['enddate']): ?>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="fas fa-clock me-1"></i>
                            <strong>Active:</strong>
                            <?= $row['startdate'] ? my_datee('d M Y H:i', (int)$row['startdate']) : 'Now' ?>
                            →
                            <?= $row['enddate'] ? my_datee('d M Y H:i', (int)$row['enddate']) : '∞ (No end)' ?>
                        </div>
                        <?php endif; ?>

                        <div class="announcement-content">
                            <?= $parser->parse_message($row['message'], $parser_options) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-light"><h6 class="mb-0">Actions</h6></div>
                    <div class="card-body d-grid gap-2">
                        <a href="<?= $script ?>?act=announcements_forum&action=edit&id=<?= (int)$row['id'] ?>"
                           class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
                        <button class="btn btn-warning btn-sm"
                                onclick="afDuplicate(<?= (int)$row['id'] ?>)">
                            <i class="fas fa-copy me-1"></i>Duplicate
                        </button>
                        <button class="btn btn-danger btn-sm"
                                onclick="afDelete(<?= (int)$row['id'] ?>, '<?= htmlspecialchars(addslashes($row['subject'])) ?>')">
                            <i class="fas fa-trash me-1"></i>Delete
                        </button>
                        <a href="<?= $script ?>?act=announcements_forum" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </a>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-light"><h6 class="mb-0">Details</h6></div>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">ID</span><strong>#<?= (int)$row['id'] ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Type</span>
                            <span class="badge bg-<?= $row['type'] === 'global' ? 'primary' : 'success' ?>">
                                <?= $row['type'] ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Views</span><strong><?= (int)$row['views'] + 1 ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Words</span>
                            <strong><?= number_format(str_word_count(strip_tags($row['message']))) ?></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?= af_delete_modal($script) ?>

    <script>
    function afDelete(id, subject) {
        document.getElementById('deleteTitle').textContent = subject;
        document.getElementById('deleteConfirmBtn').href =
            '<?= $script ?>?act=announcements_forum&action=delete&id=' + id + '&sure=yes';
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    function afDuplicate(id) {
        if (!confirm('Duplicate this announcement?')) return;
        fetch('<?= $script ?>?act=announcements_forum&action=duplicate&id=' + id, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'do=duplicate'
        })
        .then(r => r.json())
        .then(d => { if (d.success) window.location.href = d.redirect_url; else alert(d.message); });
    }
    </script>
    <?php
    stdfoot();
}

/* ═══════════════════════════════════════════════════════════════════
 *  ADD
 * ═══════════════════════════════════════════════════════════════════ */

function af_handle_add(string $do): void
{
    global $db, $CURUSER, $cache;

    if ($do === 'save') {
        $subject  = trim($_POST['subject'] ?? '');
        $message  = trim($_POST['message'] ?? '');
        $fid      = (int)($_POST['fid'] ?? -1);
        $errors   = [];
        $startdate = $enddate = 0;

        if (empty($subject)) $errors[] = 'Subject is required.';
        if (empty($message)) $errors[] = 'Message is required.';

        af_parse_dates($errors, $startdate, $enddate);

        if (!$errors) {
            $type = $fid === -1 ? 'global' : 'forum';
            $db->insert_query('announcements', [
                'subject'   => $db->escape_string($subject),
                'message'   => $db->escape_string($message),
                'uid'       => (int)$CURUSER['id'],
                'added'     => TIMENOW,
                'updated'   => 0,
                'views'     => 0,
                'startdate' => $startdate,
                'enddate'   => $enddate,
                'fid'       => $fid,
                'type'      => $type,
            ]);
            
			$cache->update_forumsdisplay();
			
			af_redirect('Announcement added successfully.');
        }

        // Show form again with errors
        af_render_form('add', [], $errors);
        return;
    }

    af_render_form('add');
}

/* ═══════════════════════════════════════════════════════════════════
 *  EDIT
 * ═══════════════════════════════════════════════════════════════════ */

function af_handle_edit(int $id, string $do): void
{
    global $db, $CURUSER, $cache;

    if ($id <= 0) af_redirect('Invalid ID.');
    $row = af_get_announcement($id);

    if ($do === 'save') {
        $subject  = trim($_POST['subject'] ?? '');
        $message  = trim($_POST['message'] ?? '');
        $fid      = (int)($_POST['fid'] ?? -1);
        $errors   = [];
        $startdate = $enddate = 0;

        if (empty($subject)) $errors[] = 'Subject is required.';
        if (empty($message)) $errors[] = 'Message is required.';

        af_parse_dates($errors, $startdate, $enddate);

        if (!$errors) {
            $type = $fid === -1 ? 'global' : 'forum';
            $db->update_query('announcements', [
                'subject'   => $db->escape_string($subject),
                'message'   => $db->escape_string($message),
                'uid'       => (int)$CURUSER['id'],
                'updated'   => TIMENOW,
                'startdate' => $startdate,
                'enddate'   => $enddate,
                'fid'       => $fid,
                'type'      => $type,
            ], "id = {$id}");
            
			$cache->update_forumsdisplay();
			
			af_redirect('Announcement updated successfully.');
        }

        af_render_form('edit', $row, $errors);
        return;
    }

    af_render_form('edit', $row);
}

/* ═══════════════════════════════════════════════════════════════════
 *  DELETE
 * ═══════════════════════════════════════════════════════════════════ */

function af_handle_delete(int $id): void
{
    global $db, $cache;

    if ($id <= 0 || ($_GET['sure'] ?? '') !== 'yes') {
        af_redirect('Deletion cancelled.');
    }

    $db->sql_query("DELETE FROM announcements WHERE id = {$id} AND type IN ('forum','global')");
    
	$cache->update_forumsdisplay();
	
	af_redirect('Announcement deleted.');
}

/* ═══════════════════════════════════════════════════════════════════
 *  DUPLICATE
 * ═══════════════════════════════════════════════════════════════════ */

function af_handle_duplicate(int $id): void
{
    global $db, $CURUSER;

    header('Content-Type: application/json');

    if ($id <= 0 || ($_POST['do'] ?? '') !== 'duplicate') {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $row = $db->fetch_array($db->sql_query(
        "SELECT * FROM announcements WHERE id = {$id} AND type IN ('forum','global') LIMIT 1"
    ));
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Not found.']);
        exit;
    }

    $subject = 'Copy of ' . $row['subject'];
    $count   = (int)$db->fetch_array($db->sql_query(
        "SELECT COUNT(*) AS c FROM announcements WHERE subject LIKE '" .
        $db->escape_string($subject) . "%'"))['c'];
    if ($count > 0) $subject .= ' (' . ($count + 1) . ')';

    $new_id = $db->insert_query('announcements', [
        'subject'   => $db->escape_string($subject),
        'message'   => $db->escape_string($row['message']),
        'uid'       => (int)$CURUSER['id'],
        'added'     => TIMENOW,
        'updated'   => 0,
        'views'     => 0,
        'startdate' => (int)$row['startdate'],
        'enddate'   => (int)$row['enddate'],
        'fid'       => (int)$row['fid'],
        'type'      => $row['type'],
    ]);

    $script = htmlspecialchars($_SERVER['SCRIPT_NAME']);
    echo json_encode([
        'success'      => true,
        'message'      => 'Duplicated successfully.',
        'new_id'       => $new_id,
        'redirect_url' => "{$script}?act=announcements_forum&action=view&id={$new_id}",
    ]);
    exit;
}

/* ═══════════════════════════════════════════════════════════════════
 *  FORM (add / edit)
 * ═══════════════════════════════════════════════════════════════════ */

function af_render_form(string $mode, array $data = [], array $errors = []): void
{
    global $BASEURL, $CURUSER, $smilies, $timeformat;

    $is_edit   = $mode === 'edit';
    $title     = $is_edit ? 'Edit Forum Announcement' : 'New Forum Announcement';
    $script    = htmlspecialchars($_SERVER['SCRIPT_NAME']);
    $action_url = $script . '?act=announcements_forum&action=' . $mode
        . ($is_edit ? '&id=' . (int)$data['id'] : '');

    $forums = af_get_forums();

    // Dates
    $local   = TIMENOW + (float)$CURUSER['timezone'] * 3600 + (int)$CURUSER['dst'] * 3600;
    $sd      = $is_edit && $data['startdate'] ? af_build_date_selects((int)$data['startdate'])
                                               : af_build_date_selects(TIMENOW);
    $makeshift_end = $is_edit && !$data['enddate'];
    $ed      = ($is_edit && $data['enddate']) ? af_build_date_selects((int)$data['enddate'])
                                              : af_build_date_selects(TIMENOW + 86400 * 365);

    $end_infinite = !$is_edit || $makeshift_end
        ? 'checked' : '';
    $end_finite   = ($is_edit && $data['enddate']) ? 'checked' : '';

    require_once INC_PATH . '/editor.php';
    stdhead($title . ' ' . AF_VERSION);
    ?>
   

    <div class="container mt-4" style="max-width:960px">
        <div class="d-flex align-items-center gap-2 mb-4">
            <a href="<?= $script ?>?act=announcements_forum" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h4 class="mb-0"><i class="fas fa-newspaper me-2 text-success"></i><?= $title ?></h4>
        </div>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= $action_url ?>">
            <input type="hidden" name="do" value="save">

            <!-- Basic Settings -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light fw-semibold">Basic Settings</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject"
                                   value="<?= htmlspecialchars($data['subject'] ?? '') ?>"
                                   maxlength="120" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Forum</label>
                            <select name="fid" class="form-select">
                                <?php foreach ($forums as $fid_val => $fname): ?>
                                <option value="<?= $fid_val ?>"
                                    <?= isset($data['fid']) && (int)$data['fid'] === (int)$fid_val ? 'selected' : '' ?>>
                                    <?= $fname ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dates -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light fw-semibold">Schedule</div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Start Date -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-success">
                                <i class="fas fa-play me-1"></i>Start Date
                            </label>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" name="starttime_time"
                                       value="<?= $sd['time'] ?>" style="max-width:80px" placeholder="HH:MM">
                                <select name="starttime_day" class="form-select" style="max-width:75px">
                                    <?= af_day_options($sd['day']) ?>
                                </select>
                                <select name="starttime_month" class="form-select">
                                    <?= af_month_options($sd['month']) ?>
                                </select>
                                <input type="number" class="form-control" name="starttime_year"
                                       value="<?= $sd['year'] ?>" style="max-width:85px">
                            </div>
                        </div>

                        <!-- End Date -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-danger">
                                <i class="fas fa-stop me-1"></i>End Date
                            </label>
                            <div class="mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="endtime_type"
                                           value="2" id="endInfinite" <?= $end_infinite ?>
                                           onchange="document.getElementById('endDateFields').style.display='none'">
                                    <label class="form-check-label" for="endInfinite">No end (Permanent)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="endtime_type"
                                           value="1" id="endFinite" <?= $end_finite ?>
                                           onchange="document.getElementById('endDateFields').style.display='flex'">
                                    <label class="form-check-label" for="endFinite">Set end date</label>
                                </div>
                            </div>
                            <div id="endDateFields" class="input-group"
                                 style="display:<?= $end_finite ? 'flex' : 'none' ?>">
                                <input type="text" class="form-control" name="endtime_time"
                                       value="<?= $ed['time'] ?>" style="max-width:80px" placeholder="HH:MM">
                                <select name="endtime_day" class="form-select" style="max-width:75px">
                                    <?= af_day_options($ed['day']) ?>
                                </select>
                                <select name="endtime_month" class="form-select">
                                    <?= af_month_options($ed['month']) ?>
                                </select>
                                <input type="number" class="form-control" name="endtime_year"
                                       value="<?= $ed['year'] ?>" style="max-width:85px">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light fw-semibold">Message <span class="text-danger">*</span></div>
                <div class="card-body">
                    <?= af_build_editor($data['message'] ?? '') ?>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="<?= $script ?>?act=announcements_forum" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-success px-4">
                    <i class="fas fa-save me-1"></i><?= $is_edit ? 'Update' : 'Publish' ?> Announcement
                </button>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('message').addEventListener('input', function() {
        document.getElementById('charCount').textContent = this.value.length;
    });
    </script>
    <?php
    stdfoot();
}

/* ─────────────────────────── Shared UI ──────────────────────────── */

function af_delete_modal(string $script): string
{
    return <<<HTML
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Announcement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" tabindex="-1"></button>
            </div>
            <div class="modal-body">
                <p>You are about to delete:</p>
                <div class="alert alert-warning fw-bold" id="deleteTitle"></div>
                <p class="text-danger mb-0"><strong>This action cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i>Delete
                </a>
            </div>
        </div>
    </div>
</div>
HTML;
}