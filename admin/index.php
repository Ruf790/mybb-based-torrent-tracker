<?php
/**
 * Staff Panel — Main Entry Point
 *
 * Handles routing for:
 *   - securitycheck    — Security Console
 *   - managestafftools — CRUD for staff tools
 *   - stafftools       — List of available tools
 *   - <dynamic>        — Any registered tool file
 */

/* ─────────────────────────── Bootstrap ──────────────────────────── */

$templatelist = implode(',', [
    'multipage', 'multipage_breadcrumb', 'multipage_end', 'multipage_jump_page',
    'multipage_nextpage', 'multipage_page', 'multipage_page_current',
    'multipage_page_link_current', 'multipage_prevpage', 'multipage_start',
]);

$rootpath = './../';
$thispath = './';

define('IN_ADMIN_PANEL',      true);
define('STAFF_PANEL_TSSEv56', true);
define('SKIP_CRON_JOBS',      true);
define('SKIP_LOCATION_SAVE',  true);
define('IN_MYBB',             1);
define('IN_ADMINCP',          1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $rootpath . 'global.php';
gzip();

maxsysop();
if (!is_mod($usergroups)) {
    print_no_permission(true);
    exit();
}

require_once $thispath . 'include/adminfunctions.php';

flash_message();

/* ──────────────────────── Helpers ───────────────────────────────── */

function get_act(): string
{
    if (isset($_POST['act'])) return htmlspecialchars($_POST['act']);
    if (isset($_GET['act']))  return htmlspecialchars($_GET['act']);
    return '';
}

function platform_eol(): string
{
    $os = strtoupper(substr(PHP_OS, 0, 3));
    if ($os === 'WIN') return "\r\n";
    if ($os === 'MAC') return "\r";
    return "\n";
}

function get_count(string $col, string $table, string $extra = ''): int
{
    global $db;
    $res = $db->sql_query("SELECT COUNT(*) AS {$col} FROM {$table} " . $extra);
    [$count] = mysqli_fetch_array($res);
    return (int) $count;
}

/**
 * Inject shared CSS/JS assets (call once per page after stdhead).
 */
function enqueue_staff_assets(): void
{
    global $BASEURL;
    echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/staff.css">' . "\n";
    echo '<script defer src="' . $BASEURL . '/admin/scripts/staff.js"></script>' . "\n";
}

/**
 * Output CSS variables for the accent colour used by staff.css.
 */
function render_accent_vars(string $accent = '#3b82f6', string $accentHover = '#1d4ed8'): void
{
    echo "<style>:root{--staff-accent:{$accent};--staff-accent-hover:{$accentHover};}</style>\n";
}

/* ──────────────────────── Routing ───────────────────────────────── */

$act                 = get_act();
$_this_script_       = htmlspecialchars($_SERVER['SCRIPT_NAME']) . '?act=' . $act;
$_this_script_no_act = htmlspecialchars($_SERVER['SCRIPT_NAME']);
$eol                 = platform_eol();

$act_array = ['securitycheck', 'managestafftools', 'stafftools'];

// ── Dynamic tool files ────────────────────────────────────────────
if (!empty($act) && !in_array($act, $act_array, true) && file_exists($thispath . $act . '.php')) {
    _file_access_check_($act);
    include $thispath . $act . '.php';
    render_admin_floating_bar();
    exit();
}

if ($act === 'stafftools')       { render_stafftools_page();  exit(); }
if ($act === 'managestafftools') { handle_managestafftools(); exit(); }
if ($act === 'securitycheck')    { handle_securitycheck();    exit(); }

render_dashboard();
exit();


/* ═══════════════════════════════════════════════════════════════════
 *  PAGE RENDERERS
 * ═══════════════════════════════════════════════════════════════════ */

/* ──────────── Admin floating bar ───────────────────────────────── */
function render_admin_floating_bar(): void
{
    global $BASEURL;
    enqueue_staff_assets();
    echo <<<HTML
    <div id="adminFloatingBar" class="admin-floating-bar">
        <div class="fb-pulse"></div>
        <div class="fb-badge">ADMIN</div>
        <div class="fb-icon"><i class="fas fa-user-shield"></i></div>
        <div>
            <strong>Staff Panel</strong>
            <div style="font-size:11px;opacity:.9">Administrative Access</div>
        </div>
        <a href="{$BASEURL}/admin/index.php" class="fb-link">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <button class="fb-close" title="Close"><i class="fas fa-times"></i></button>
    </div>
HTML;
}

/* ──────────── Staff Tools list ─────────────────────────────────── */
function render_stafftools_page(): void
{
    stdhead('Staff Tools');
    enqueue_staff_assets();
    menu('stafftools');
    echo '<div class="container mt-3">';
    echo '<table align="center" width="100%" cellpadding="0" cellspacing="0" border="0"><tbody><tr><td>';
    echo '<table width="100%" cellpadding="6" cellspacing="0" border="0"><tbody>';
    echo '<tr><td colspan="4" align="center">Staff Tools</td></tr>';
    echo '<tr><td colspan="4" align="center" width="100%">Tool Name - Description</td></tr>';
    get_list();
    echo '</tbody></table></td></tr></tbody></table></div>';
    close_menu();
    stdfoot();
}

/* ──────────── Manage Staff Tools (CRUD) ────────────────────────── */
function handle_managestafftools(): void
{
    global $_this_script_, $_this_script_no_act, $db, $thispath;

    _access_check_();

    $do = $_GET['do'] ?? '';
    $id = (isset($_GET['id']) && is_valid_id($_GET['id'])) ? (int) $_GET['id'] : null;

    if ($do === 'newtool')                  { render_tool_form('create');                return; }
    if ($do === 'savenewtool')              { save_tool('create');                       return; }
    if ($do === 'delete'   && $id !== null) { delete_tool($id);                         return; }
    if ($do === 'edit'     && $id !== null) { render_tool_form('edit', fetch_tool($id)); return; }
    if ($do === 'savetool' && $id !== null) { save_tool('edit', $id);                   return; }

    // ── Listing ───────────────────────────────────────────────────
    stdhead('Manage Staff Tools');
    enqueue_staff_assets();
    menu('managestafftools');
    $add_btn = '<p align="right"><input type="button" class="hoptobutton" value="Add New Tool"'
             . ' onClick="jumpto(\'' . $_this_script_no_act . '?act=managestafftools&do=newtool\')"></p>';
    echo $add_btn;
    _form_header_open_('Manage Staff Tools', 6);
    get_list2();
    echo '</table></tbody></td></tr></table></tbody></div></td></tr></table>';
    echo $add_btn;
    stdfoot();
}

function fetch_tool(int $id): array
{
    global $db;
    $sql = $db->sql_query('SELECT * FROM staffpanel WHERE id = ' . $db->escape_string($id));
    if ($db->num_rows($sql) === 0) { stderr('Error! Tool not found in database.'); exit(); }
    return $db->fetch_array($sql);
}

function save_tool(string $mode, int $id = 0): void
{
    global $db, $thispath;

    $name        = htmlspecialchars_uni($_POST['name']        ?? '');
    $description = htmlspecialchars_uni($_POST['description'] ?? '');
    $filename    = $name . '.php';
    $groups      = !empty($_POST['gid']) ? implode(',', $_POST['gid']) : '';

    if (empty($name) || empty($description) || empty($groups)) {
        stderr('Error! Don\'t leave any fields blank!'); return;
    }
    if (!file_exists($thispath . $filename)) {
        stderr('Error: File <b>' . htmlspecialchars($thispath . 'admin/' . $filename) . '</b> does not exist.', false); return;
    }

    $data = [
        'name'        => $db->escape_string($name),
        'description' => $db->escape_string($description),
        'filename'    => $db->escape_string($filename),
        'usergroups'  => $db->escape_string($groups),
    ];

    if ($mode === 'create') {
        $db->insert_query('staffpanel', $data);
        redirect('admin/index.php?act=' . $name, 'The new tool has been added.');
    } else {
        $db->update_query('staffpanel', $data, "id='" . $id . "'");
        redirect('index.php?act=managestafftools', 'The tool has been updated.');
    }
}

function delete_tool(int $id): void
{
    global $db, $_this_script_;

    if (($_GET['sure'] ?? '') !== 'yes') {
        stderr(
            'Sanity check — are you sure you want to delete this tool?<br><br>'
            . '<strong><a href="' . $_this_script_ . '&do=delete&id=' . $id . '&sure=yes" style="color:red">Yes, delete it</a></strong>'
            . ' &nbsp; <a href="' . $_this_script_ . '">No, go back</a>',
            false
        );
        return;
    }
    $db->sql_query('DELETE FROM staffpanel WHERE id = ' . $db->escape_string($id));
    redirect('admin/index.php?act=managestafftools', 'The tool has been deleted.');
}

function render_tool_form(string $mode, array $tool = null): void
{
    global $_this_script_, $_this_script_no_act, $db;

    $is_edit     = $mode === 'edit';
    $title       = $is_edit ? 'Edit Tool'    : 'Create New Tool';
    $accent      = $is_edit ? '#f59e0b'      : '#3b82f6';
    $accentHover = $is_edit ? '#d97706'      : '#1d4ed8';
    $icon        = $is_edit ? 'fa-edit'      : 'fa-plus-circle';
    $btn_class   = $is_edit ? 'btn-warning'  : 'btn-primary';
    $btn_label   = $is_edit ? 'Update Tool'  : 'Create Tool';
    $form_action = $is_edit
        ? $_this_script_ . '&do=savetool&id=' . $tool['id']
        : $_this_script_ . '&do=savenewtool';

    $val_name    = $is_edit ? htmlspecialchars($tool['name'])       : '';
    $val_desc    = $is_edit ? htmlspecialchars($tool['description']) : '';
    $val_file    = $is_edit ? htmlspecialchars($tool['filename'])    : '';
    $tool_groups = $is_edit ? explode(',', $tool['usergroups'])      : [];

    stdhead($title);
    enqueue_staff_assets();
    render_accent_vars($accent, $accentHover);
    menu('managestafftools');

    echo <<<HTML
    <div class="container-fluid py-4">
      <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
          <div class="staff-card">
            <div class="staff-card-header d-flex align-items-center gap-3"
                 style="background:linear-gradient(135deg,var(--staff-accent),var(--staff-accent-hover))">
              <div class="staff-header-icon"><i class="fas {$icon}"></i></div>
              <div>
                <h4 class="mb-1 fw-bold">{$title}</h4>
                <p class="mb-0" style="opacity:.8;font-size:.9em">Manage staff tool settings and permissions</p>
              </div>
            </div>
            <div class="p-4">
              <form method="post" action="{$form_action}" class="needs-validation" novalidate>
                <div class="mb-4">
                  <label class="fw-semibold mb-1">Tool Name</label>
                  <input type="text" class="form-control staff-form-control" name="name"
                         value="{$val_name}" placeholder="e.g. User Manager" required>
                </div>
                <div class="mb-4">
                  <label class="fw-semibold mb-1">Description</label>
                  <input type="text" class="form-control staff-form-control" name="description"
                         value="{$val_desc}" placeholder="What does this tool do?" required>
                </div>
                <div class="mb-4">
                  <label class="fw-semibold mb-1">File Name</label>
                  <input type="text" class="form-control staff-form-control" id="toolFilename"
                         name="filename" value="{$val_file}" placeholder="tool.php" required>
                  <div class="form-text text-muted">The PHP file name (no path needed).</div>
                </div>
                <div class="mb-4">
                  <label class="fw-semibold mb-2">Access Permissions</label>
                  <div class="staff-permissions">
HTML;

    $sql = $db->sql_query("SELECT gid, title, namestyle FROM usergroups WHERE canstaffpanel = '1' ORDER BY disporder");
    while ($group = $db->fetch_array($sql)) {
        $checked     = ($is_edit ? in_array('[' . $group['gid'] . ']', $tool_groups) : $group['gid'] == UC_SYSOP) ? 'checked' : '';
        $label       = get_user_color($group['title'], $group['namestyle']);
        $default_tag = (!$is_edit && $group['gid'] == UC_SYSOP)
                       ? ' <span class="badge bg-primary ms-1" style="font-size:.7em">Default</span>' : '';
        echo <<<HTML
                    <div class="form-check">
                      <input class="form-check-input perm-cb" type="checkbox"
                             name="gid[]" value="[{$group['gid']}]"
                             id="group_{$group['gid']}" {$checked}>
                      <label class="form-check-label" for="group_{$group['gid']}">
                        {$label}{$default_tag}
                      </label>
                    </div>
HTML;
    }

    echo <<<HTML
                  </div>
                  <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="setPerms(true)">
                      <i class="fas fa-check-double me-1"></i>Check All
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setPerms(false)">
                      <i class="fas fa-times me-1"></i>Uncheck All
                    </button>
                  </div>
                </div>
HTML;

    if ($is_edit) {
        $created = date('Y-m-d H:i', $tool['added']);
        echo <<<HTML
                <div class="alert alert-info border-0 rounded-3 mb-4">
                  <i class="fas fa-info-circle me-2"></i>
                  <strong>ID:</strong> {$tool['id']} &nbsp;|&nbsp; <strong>Created:</strong> {$created}
                </div>
HTML;
    }

    echo <<<HTML
                <div class="d-flex justify-content-between align-items-center pt-3 border-top staff-form-actions">
                  <a href="{$_this_script_no_act}?act=managestafftools" class="btn btn-outline-secondary staff-btn">
                    <i class="fas fa-arrow-left me-1"></i> Back
                  </a>
                  <div class="d-flex gap-2 staff-btn-group">
                    <button type="reset" class="btn btn-outline-danger staff-btn">
                      <i class="fas fa-undo me-1"></i> Reset
                    </button>
                    <button type="submit" class="btn {$btn_class} staff-btn px-4">
                      <i class="fas fa-save me-1"></i> {$btn_label}
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
HTML;

    close_menu();
    stdfoot();
}

/* ──────────── Security Console ─────────────────────────────────── */
function handle_securitycheck(): void
{
    global $db, $BASEURL, $iv, $securelogin, $bannedclientdetect,
           $maxloginattempts, $privatetrackerpatch, $disablerightclick,
           $securehash, $trackerlog, $accountlockout, $disallowjavascript, $SITEURL;

    _access_check_();
    stdhead('Security Console');
    enqueue_staff_assets();
    menu('securitycheck');

    require INC_PATH . '/readconfig_announce.php';

    $checks       = collect_security_checks();
    $passed       = array_sum(array_column($checks, 2));
    $total        = count($checks);
    $total_failed = $total - $passed;
    $score        = round(($passed / $total) * 100, 1);
    [$level, $level_color, $level_icon] = security_level($score);

    echo <<<HTML
    <div class="container mt-3">
      <div class="card border-0 shadow-sm mb-4"
           style="background:linear-gradient(135deg,#f8fafc,#e2e8f0);border-radius:20px">
        <div class="card-body text-center py-5">
          <div style="width:80px;height:80px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);
                      border-radius:50%;display:flex;align-items:center;justify-content:center;
                      margin:0 auto 1rem;color:#fff;font-size:2.5em;
                      box-shadow:0 8px 25px rgba(59,130,246,.3)">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h2 class="fw-bold mb-3">Security Console</h2>
          <div style="display:inline-block;background:conic-gradient(#10b981 {$score}%,#e2e8f0 0);
                      width:100px;height:100px;border-radius:50%;position:relative;margin-bottom:.75rem">
            <div style="position:absolute;inset:10px;background:#fff;border-radius:50%;
                        display:flex;flex-direction:column;align-items:center;justify-content:center">
              <span style="font-size:1.2em;font-weight:700">{$score}%</span>
              <span style="font-size:.65em;color:#64748b">Score</span>
            </div>
          </div><br>
          <span class="badge bg-{$level_color} fs-6 mb-3">
            <i class="{$level_icon} me-1"></i>{$level}
          </span>
          <div class="d-flex justify-content-center gap-4 mt-2">
            <span><i class="fas fa-check-circle text-success me-1"></i>{$passed} Passed</span>
            <span><i class="fas fa-times-circle text-danger me-1"></i>{$total_failed} Failed</span>
            <span><i class="fas fa-list-alt text-primary me-1"></i>{$total} Total</span>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light border-0 py-3">
          <h5 class="mb-0"><i class="fas fa-tasks me-2 text-primary"></i>Security Checks ({$total} performed)</h5>
        </div>
        <div class="card-body p-0">
HTML;

    foreach ($checks as $check) {
        render_security_item($check);
    }

    echo <<<HTML
        </div>
      </div>

      <div class="card border-warning mb-4">
        <div class="card-body d-flex align-items-start gap-3">
          <i class="fas fa-exclamation-triangle text-warning fs-4 mt-1"></i>
          <div>
            <h6 class="text-warning mb-1">Important Notice</h6>
            <p class="text-muted mb-1">These checks are not a guarantee of full security. Always keep the following up-to-date:</p>
            <div>
              <span class="badge bg-light text-dark me-1">TS Special Edition</span>
              <span class="badge bg-light text-dark me-1">Apache</span>
              <span class="badge bg-light text-dark me-1">PHP</span>
              <span class="badge bg-light text-dark me-1">MySQL</span>
              <span class="badge bg-light text-dark">phpMyAdmin</span>
            </div>
            <p class="text-muted mb-0 mt-2"><strong>Remember:</strong> Perfect security on the Internet does not exist.</p>
          </div>
        </div>
      </div>
    </div>
HTML;

    echo '</td></tr></table>';
    stdfoot();
}

function collect_security_checks(): array
{
    global $db, $BASEURL, $iv, $securelogin, $bannedclientdetect, $maxloginattempts,
           $privatetrackerpatch, $disablerightclick, $securehash, $trackerlog,
           $accountlockout, $disallowjavascript, $SITEURL;

    $cfg_dir  = @file_get_contents($BASEURL . '/config/DATABASE', 'r');
    $cfg_file = @file_get_contents($BASEURL . '/include/config.php', 'r');
    $hash_ok  = (bool) (preg_match('/[A-Z]/', $securehash)
                     && preg_match('/[0-9]/', $securehash)
                     && preg_match('/[a-z]/', $securehash)
                     && strlen($securehash) >= 10);

    $empty_pw = (int) $db->fetch_array($db->sql_query(
        "SELECT COUNT(*) AS c FROM users WHERE password = '' OR password IS NULL"))['c'];
    $weak_pw  = (int) $db->fetch_array($db->sql_query(
        "SELECT COUNT(*) AS c FROM users WHERE LENGTH(password) < 6"))['c'];
    $mysql_ver         = $db->fetch_array($db->sql_query("SELECT VERSION() AS v"))['v'];
    $has_default_table = $db->sql_query("SHOW TABLES LIKE 'users'")->num_rows > 0;
    $https             = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    return [
        ['Directory Protection (Config Folder)',  3, !str_contains((string)$cfg_dir, 'mysql_pass'),
            'Contact the TS Team — config folder is publicly readable.'],
        ['Directory Protection (Important Files)',3,
            $cfg_file === '<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>',
            'Important files are directly accessible from the web.'],
        ['Secure Hash Strength',                  3, $hash_ok,
            'Use at least 10 chars with uppercase, lowercase and digits.'],
        ['Image Verification (CAPTCHA)',           2, $iv === 'yes',
            'Turn on Image Verification to deter automated attacks.'],
        ['Secure Login',                           2, $securelogin === 'yes',
            'Enable Secure Login for improved protection.'],
        ['Banned Client Detection',                2, $bannedclientdetect === 'yes',
            'Enable to deny seeding/leeching by banned clients.'],
        ['Failed Login Attempts ≤ 7',              2, $maxloginattempts <= 7,
            'Keep this value at 7 or below.'],
        ['Private Tracker Patch',                  2, $privatetrackerpatch === 'yes',
            'Enable the Private Tracker Patch.'],
        ['Right-Click Disabled',                   1, $disablerightclick === 'yes',
            'Enable to prevent casual content copying.'],
        ['Config File Permissions (644)',          3, substr(sprintf('%o', @fileperms('config.php')), -4) === '0644',
            'config.php should not be writable by group/others.'],
        ['No Backup Files in Web Root',            3, !file_exists('backup.sql') && !file_exists('database_backup.zip'),
            'Remove backup files from the public web root.'],
        ['Install Directory Removed',              3, !is_dir('install') && !is_dir('setup'),
            'Delete the install/setup directory from production.'],
        ['No Debug Files (phpinfo, test)',          2, !file_exists('phpinfo.php') && !file_exists('test.php'),
            'Remove phpinfo.php / test.php from production.'],
        ['PHP Error Display Off',                  2, ini_get('display_errors') === '0' || ini_get('display_errors') === '',
            'Set display_errors = Off in production.'],
        ['PHP Error Logging Enabled',              1, ini_get('log_errors') === '1',
            'Enable error logging for diagnostics.'],
        ['PHP Version ≥ 7.4',                      2, version_compare(PHP_VERSION, '7.4.0', '>='),
            'Upgrade to PHP 7.4 or newer.'],
        ['No Default Table Names',                 2, !$has_default_table,
            'Use table prefixes to obscure default table names.'],
        ['No Empty Passwords',                     3, $empty_pw === 0,
            'Some users have empty or NULL passwords.'],
        ['No Weak Passwords (< 6 chars)',           2, $weak_pw === 0,
            'Some users have passwords shorter than 6 characters.'],
        ['HTTPS Active',                           2, $https,
            'Serve the site over HTTPS.'],
        ['Site URL Uses HTTPS',                    1, str_starts_with((string)$SITEURL, 'https://'),
            'Update SITEURL to use https://.'],
        ['Login Attempt Limit ≤ 5',                2, $maxloginattempts <= 5,
            'Reduce max login attempts to 5 or fewer.'],
        ['Account Lockout Enabled',                2, ($accountlockout ?? '') === 'yes',
            'Enable account lockout after failed attempts.'],
        ['CSRF Protection',                        2, function_exists('csrf_token') || $securelogin === 'yes',
            'Implement CSRF tokens on all forms.'],
        ['JavaScript Restriction',                 2, ($disallowjavascript ?? '') === 'yes',
            'Restrict user-submitted JavaScript.'],
        ['MySQL Version ≥ 5.7',                    2, version_compare($mysql_ver, '5.7.0', '>='),
            'Upgrade to MySQL 5.7 or newer.'],
        ['Access Logging Enabled',                 1, ($trackerlog ?? '') === 'yes',
            'Enable access logging for auditing.'],
        ['HTTPOnly Session Cookies',               2, ini_get('session.cookie_httponly') === '1',
            'Set session.cookie_httponly = 1 in php.ini.'],
        ['Secure Session Cookies',                 2, ini_get('session.cookie_secure') === '1' || $https,
            'Set session.cookie_secure = 1 in php.ini.'],
    ];
}

function security_level(float $score): array
{
    if ($score >= 90) return ['Excellent', 'success', 'fas fa-shield-alt'];
    if ($score >= 70) return ['Good',      'info',    'fas fa-check-circle'];
    if ($score >= 50) return ['Fair',      'warning', 'fas fa-exclamation-triangle'];
    return                    ['Poor',     'danger',  'fas fa-radiation-alt'];
}

function render_security_item(array $check): void
{
    [$label, $risk, $passed, $notice] = $check;

    $risk_colors = [1 => 'success', 2 => 'warning', 3 => 'danger'];
    $risk_labels = [1 => 'Low Risk', 2 => 'Medium Risk', 3 => 'High Risk'];
    $risk_icons  = [1 => 'fas fa-shield-alt', 2 => 'fas fa-exclamation-triangle', 3 => 'fas fa-radiation-alt'];

    $status_icon = $passed ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger';
    $notice_html = $passed
        ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Passed — no issues found.</span>'
        : '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>' . $notice . '</span>';
    $row_class = $passed ? '' : ' failed';

    echo <<<HTML
    <div class="sec-item{$row_class}">
      <div class="sec-item-inner">
        <div class="sec-icon"><i class="{$status_icon}"></i></div>
        <div class="sec-title">
          <h6>{$label}</h6>
          <div class="sec-notice">{$notice_html}</div>
        </div>
        <span class="badge bg-{$risk_colors[$risk]} risk-badge">
          <i class="{$risk_icons[$risk]} me-1"></i>{$risk_labels[$risk]}
        </span>
      </div>
    </div>
HTML;
}


// ── DB Size ───────────────────────────────────────────────
function getDbSize(): string
{
    global $db, $config;
 
    // Берём имя БД из конфига трекера
    $dbname = $config['database']['database'] ?? null;
    if (!$dbname) return '—';
 
    $result = $db->sql_query(
        "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size_mb
         FROM information_schema.tables
         WHERE table_schema = '" . $db->escape_string($dbname) . "'"
    );
    if (!$result) return '—';
    $row = $db->fetch_array($result);
    return ($row['size_mb'] ?? 0) . ' MB';
}

// ── Disk Free ─────────────────────────────────────────────
function getDiskFree(): string
{
    $path = defined('TSDIR') ? TSDIR : __DIR__;
    $free = disk_free_space($path);
    if ($free === false) return '—';

    $gb = $free / (1024 ** 3);
    if ($gb >= 1) return round($gb, 1) . ' GB';
    return round($free / (1024 ** 2), 0) . ' MB';
}


// ── Recent Staff Activity ─────────────────────────────────
function getRecentActivity(int $limit = 5): string
{
    global $db, $BASEURL;

    // Таблица stafflog — есть в большинстве трекеров на базе TS
    // Если таблицы нет — возвращаем заглушку
    $result = $db->sql_query(
        "SELECT l.*, u.username
         FROM sitelog l
         LEFT JOIN users u ON l.uid = u.id
         ORDER BY l.id DESC
         LIMIT " . (int)$limit
    );

    if (!$result || $db->num_rows($result) === 0) {
        return '<div class="text-center text-muted py-3">
                    <i class="fas fa-history opacity-50 me-1"></i>No recent activity
                </div>';
    }

    $html = '<ul class="list-unstyled mb-0">';
    while ($row = $db->fetch_array($result)) {
        $user    = htmlspecialchars($row['username'] ?? 'System');
        $action  = htmlspecialchars($row['txt']   ?? '');
        $time    = date('d.m H:i', (int)($row['added'] ?? 0));
        $html   .= sprintf(
            '<li class="d-flex align-items-start gap-2 mb-2">
                <i class="fas fa-circle text-secondary mt-1" style="font-size:.5em;flex-shrink:0"></i>
                <div>
                    <span class="fw-semibold">%s</span>
                    <span class="text-muted">%s</span>
                    <div><small class="text-muted">%s</small></div>
                </div>
            </li>',
            $user, $action, $time
        );
    }
    $html .= '</ul>';
    return $html;
}





/* ──────────── Welcome Dashboard ─────────────────────────────────── */
function render_dashboard(): void
{
    global $db, $CURUSER, $SITENAME;

    $timecut       = TIMENOW - 86400;
    $totalusers    = get_count('c', 'users',    "WHERE ustatus='confirmed'");
    $newuserstoday = get_count('c', 'users',    'WHERE added > ' . $db->escape_string($timecut));
    $pendingusers  = get_count('c', 'users',    "WHERE ustatus='pending'");
    $todaycomments = get_count('c', 'comments', 'WHERE dateline > ' . $db->escape_string($timecut));
    $todayvisits   = get_count('c', 'users',    'WHERE lastactive > ' . $db->escape_string($timecut));
    $peers         = get_count('c', 'peers');
    $seeders       = get_count('c', 'peers',    "WHERE seeder='yes'");
    $leechers      = get_count('c', 'peers',    "WHERE seeder='no'");
    $totaltorrents = get_count('c', 'torrents');

    $row      = $db->fetch_array($db->sql_query('SELECT SUM(downloaded) AS totaldl, SUM(uploaded) AS totalul FROM users'));
    $dl_bytes = (float) $row['totaldl'];
    $ul_bytes = (float) $row['totalul'];
    $dl_disp  = mksize($dl_bytes);
    $ul_disp  = mksize($ul_bytes);
    $ratio    = $dl_bytes > 0 ? round($ul_bytes / $dl_bytes, 2) : '∞';

    $username = htmlspecialchars_uni($CURUSER['username']);
    $date_str = date('d M Y');
    $time_str = date('H:i:s');

    $col_total_users  = stat_col('fa-user-plus text-primary',      'Total Users',  ts_nf($totalusers));
    $col_new_today    = stat_col('fa-user-clock text-success',     'New Today',    ts_nf($newuserstoday),  'text-success');
    $col_unconfirmed  = stat_col('fa-user-times text-warning',     'Unconfirmed',  ts_nf($pendingusers),   'text-warning');
    $col_active_users = stat_col('fa-eye text-info',               'Active Users', ts_nf($todayvisits),    'text-info');
    $col_comments     = stat_col('fa-comment-dots text-secondary', 'Comments',     ts_nf($todaycomments),  'text-secondary');
    $col_peers        = stat_col('fa-users text-danger',           'Peers',        ts_nf($peers),          'text-danger');
    $col_seeders      = stat_col('fa-arrow-up text-success',       'Seeders',      ts_nf($seeders),        'text-success');
    $col_leechers     = stat_col('fa-arrow-down text-primary',     'Leechers',     ts_nf($leechers),       'text-primary');
    $col_torrents     = stat_col('fa-file-alt text-info',          'Torrents',     ts_nf($totaltorrents),  'text-info');
    $col_uploaded     = stat_col('fa-upload text-success',         'Uploaded',     $ul_disp,               'text-success');
    $col_downloaded   = stat_col('fa-download text-danger',        'Downloaded',   $dl_disp,               'text-danger');
    $col_ratio        = stat_col('fa-balance-scale text-warning',  'Ratio',        $ratio,                 'text-warning');
	
	


// ── Memory Usage ──────────────────────────────────────────
$memory_usage = get_memory_usage();
$memory_display = $memory_usage 
    ? mksize($memory_usage)
    : '';

// ── Заполняем переменные ──────────────────────────────────
$dbsize        = getDbSize();
$diskfree      = getDiskFree();
$recentactivity = getRecentActivity();

// Для heredoc
$dbSizeVal     = $dbsize;
$diskFreeVal   = $diskfree;
$recentActivity = $recentactivity;
	
	
$serverload = get_server_load();	
	


    stdhead('Staff Panel Dashboard');
    enqueue_staff_assets();
    menu('welcome');




echo <<<HTML
<div class="container mt-3">

    <!-- Welcome Card -->
    <div class="card bg-primary text-white rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3 class="fw-bold mb-1"><i class="fas fa-user-shield me-2"></i>{$username}</h3>
                    <h5 class="mb-2">Welcome to {$SITENAME} Staff Panel</h5>
                    <p class="mb-0 opacity-75">Manage your tracker quickly and efficiently.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="bg-white bg-opacity-25 rounded-3 p-3 d-inline-block text-center">
                        <i class="fas fa-clock fa-2x mb-1"></i>
                        <div class="fw-bold" id="live-clock">{$time_str}</div>
                        <small>{$date_str}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User & Activity Stats -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>User Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">{$col_total_users}{$col_new_today}{$col_unconfirmed}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Activity Today</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">{$col_active_users}{$col_comments}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Peers & Traffic -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-download me-2"></i>Peers &amp; Torrents</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">{$col_peers}{$col_seeders}{$col_leechers}{$col_torrents}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Total Traffic</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">{$col_uploaded}{$col_downloaded}{$col_ratio}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health & Recent Activity -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="fas fa-heartbeat me-2"></i>System Health</h6>
                </div>
                <div class="card-body">
                    <div class="row align-items-center mb-3">
                        <div class="col-8">
                            <h6 class="mb-1">Server Load</h6>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar {$serverLoadBar}" style="width:{$serverLoadPct}%"></div>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <span class="fw-bold">{$serverload}%</span>
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <i class="fas fa-database text-primary fs-3"></i>
                            <h6 class="text-muted mb-1 mt-1">DB Size</h6>
                            <small class="fw-bold">{$dbSizeVal}</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <i class="fas fa-hdd text-info fs-3"></i>
                            <h6 class="text-muted mb-1 mt-1">Disk Free</h6>
                            <small class="fw-bold">{$diskFreeVal}</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <i class="fas fa-memory text-success fs-3"></i>
                            <h6 class="text-muted mb-1 mt-1">Memory</h6>
                            <small class="fw-bold">{$memory_display}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Staff Activity</h6>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="flex-grow-1">{$recentActivity}</div>
                    <div class="text-center mt-3">
                        <a href="index.php?act=log" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>View All Logs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
HTML;



    echo '</td></tr></table>';
    stdfoot();
}

function stat_col(string $icon, string $label, $value, string $value_class = 'text-dark'): string
{
    return <<<HTML
    <div class="col">
      <div class="stat-card">
        <i class="fas {$icon} fs-2 mb-1"></i>
        <div class="small text-muted">{$label}</div>
        <div class="fw-bold fs-5 {$value_class}">{$value}</div>
      </div>
    </div>
HTML;
}