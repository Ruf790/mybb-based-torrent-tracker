<?php
/**
 * Staff Panel — Main Entry Point (refactored)
 */

/* ─────────────────────────── Bootstrap ──────────────────────────── */

// Prevent the browser (or any intermediate proxy/cache) from ever serving
// a cached copy of this page. The admin 2FA gate must be re-evaluated by
// PHP on every single request — a cached page would silently bypass it.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$rootpath = './../';
$thispath = './';

define('IN_ADMIN_PANEL',      true);
define('STAFF_PANEL', true);
define('SKIP_CRON_JOBS',      true);
define('SKIP_LOCATION_SAVE',  true);
define('IN_MYBB',             1);
define('IN_ADMINCP',          1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $rootpath . 'global.php';
$lang->load('staffpanel');

/**
 * Подстановка {1}, {2}… в строку ланга.
 * $lang->load() превращает {N} в %N$s, поэтому заменяем оба формата.
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string) $arg;
            $map['%' . $n . '$s'] = (string) $arg;
        }
        return strtr($str, $map);
    }
}

gzip();

maxsysop();
if (!is_mod($usergroups)) {
    print_no_permission(true);
    exit();
}

/* ──────────────────────── Admin 2FA Gateway ─────────────────────── */
require_once $rootpath . 'include/functions_2fa.php';

$admin_uid     = (int)$CURUSER['id'];
$admin_2fa_key = 'admin_2fa_ok_' . $admin_uid;
$admin_2fa_err = '';



if (totp_is_enabled($admin_uid)) {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_totp_code'])) {
        $code   = preg_replace('/\D/', '', $_POST['admin_totp_code'] ?? '');
        $secret = totp_get_secret($admin_uid);

        if ($secret && totp_verify($secret, $code)) {
            $_SESSION[$admin_2fa_key] = [
                'verified_at' => time(),
                'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
            ];
            unset($_SESSION['admin_2fa_fail_count']);
        } else {
            $admin_2fa_err = $lang->staffpanel['err_2fa_invalid'];

            $_SESSION['admin_2fa_fail_count'] = ($_SESSION['admin_2fa_fail_count'] ?? 0) + 1;

            write_log(
                "Failed admin 2FA attempt: Username: {$CURUSER['username']}"
                . " - UserID: {$admin_uid}"
                . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
                . " - Attempt #: " . $_SESSION['admin_2fa_fail_count'],
                'Warning: Admin 2FA Failure'
            );
        }
    }

    $verified = $_SESSION[$admin_2fa_key] ?? null;
    $ok = $verified
        && (time() - $verified['verified_at']) < 28800
        && ($verified['ip'] === ($_SERVER['REMOTE_ADDR'] ?? ''));

    if (!$ok) {
        $base = rtrim($BASEURL, '/');
        echo '<!DOCTYPE html>
<html lang="' . htmlspecialchars($lang->staffpanel['html_lang']) . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . htmlspecialchars($SITENAME) . ' — ' . htmlspecialchars($lang->staffpanel['title_2fa']) . '</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container-md mt-5" style="max-width:420px">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white text-center">
                <h5 class="mb-0">
                    <i class="fa-solid fa-shield-halved me-2"></i>
                    ' . htmlspecialchars($lang->staffpanel['head_2fa']) . '
                </h5>
            </div>
            <div class="card-body">
                ' . ($admin_2fa_err ? '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>' . htmlspecialchars($admin_2fa_err) . '</div>' : '') . '
                <p class="text-muted small mb-3">
                    ' . htmlspecialchars($lang->staffpanel['hint_2fa_enter']) . '
                </p>
                <form method="post" action="">
                    <div class="mb-3">
                        <label class="form-label fw-bold">' . htmlspecialchars($lang->staffpanel['lbl_2fa_code']) . '</label>
                        <input type="text" name="admin_totp_code"
                               class="form-control form-control-lg text-center fw-bold"
                               style="letter-spacing:.3rem"
                               placeholder="000 000" maxlength="6"
                               autocomplete="one-time-code" autofocus
                               inputmode="numeric" pattern="[0-9]{6}">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>' . htmlspecialchars($lang->staffpanel['btn_2fa_verify']) . '
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <a href="' . $base . '/index.php" class="small text-muted">
                    <i class="fa-solid fa-arrow-left me-1"></i>' . htmlspecialchars($lang->staffpanel['lnk_back_to_site']) . '
                </a>
                &nbsp;&bull;&nbsp;
                <small class="text-muted">' . ags_fmt($lang->staffpanel['logged_in_as'], htmlspecialchars($CURUSER['username'])) . '</small>
            </div>
        </div>
    </div>
</body>
</html>';
        exit();
    }

} else {

    // 2FA not enabled — mandatory block. Admin cannot access the panel
    // until 2FA is set up; there is no bypass.
    $base = rtrim($BASEURL, '/');

    $usercp_link = '<a href="' . $base . '/usercp.php?action=2fa" target="_blank">'
                 . '<i class="fa-solid fa-shield-halved me-1"></i>'
                 . htmlspecialchars($lang->staffpanel['lnk_2fa_usercp'])
                 . '</a>';

    echo '<!DOCTYPE html>
<html lang="' . htmlspecialchars($lang->staffpanel['html_lang']) . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . htmlspecialchars($SITENAME) . ' — ' . htmlspecialchars($lang->staffpanel['title_2fa_required']) . '</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container-md mt-5" style="max-width:480px">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger text-white text-center">
                <h5 class="mb-0">
                    <i class="fa-solid fa-shield-halved me-2"></i>
                    ' . htmlspecialchars($lang->staffpanel['head_2fa_required']) . '
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <strong>' . htmlspecialchars($lang->staffpanel['msg_2fa_required']) . '</strong><br>
                    <small>' . htmlspecialchars($lang->staffpanel['msg_2fa_not_enabled']) . '</small>
                </div>
                <p class="text-muted small">
                    ' . ags_fmt(htmlspecialchars($lang->staffpanel['hint_2fa_setup']), $usercp_link) . '
                </p>
                <a href="' . $base . '/usercp.php?action=2fa" class="btn btn-danger w-100">
                    <i class="fa-solid fa-shield-halved me-2"></i>' . htmlspecialchars($lang->staffpanel['btn_2fa_enable']) . '
                </a>
            </div>
            <div class="card-footer text-center">
                <small class="text-muted">' . ags_fmt($lang->staffpanel['logged_in_as'], htmlspecialchars($CURUSER['username'])) . '</small>
            </div>
        </div>
    </div>
</body>
</html>';
    exit();
}
/* ────────────────────────────────────────────────────────────────── */

require_once $thispath . 'include/adminfunctions.php';
flash_message();

/* ──────────────────────── Helpers ───────────────────────────────── */

/**
 * Подключает staff.css + staff.js один раз за запрос.
 * Перед скриптом выводит AGS_LANG — все ключи js_* ланга без префикса.
 * Вызывать после stdhead().
 */
function enqueue_staff_assets(bool $legacy_css = true): void
{
    global $BASEURL, $lang;
    static $done = false;
    if ($done) return;
    $done = true;

    $js_lang = [];
    foreach ($lang->staffpanel as $k => $v) {
        if (str_starts_with((string) $k, 'js_')) {
            $js_lang[substr((string) $k, 3)] = $v;
        }
    }

    // staff.css глобально переопределяет .card/.btn/.form-control — на страницах
    // в новом стиле (st-ui.css + staffpanel.css) его не подключаем.
    if ($legacy_css) {
        echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/staff.css">' . "\n";
    }
    echo '<script>const AGS_LANG = '
       . json_encode($js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
       . ';</script>' . "\n";
    echo '<script defer src="'           . $BASEURL . '/admin/scripts/staff.js?ver=2"></script>' . "\n";
    echo '<div class="sp-root">' . "\n";
}

function get_act(): string
{
    if (isset($_POST['act'])) return htmlspecialchars($_POST['act']);
    if (isset($_GET['act']))  return htmlspecialchars($_GET['act']);
    return '';
}

function get_count(string $col, string $table, string $extra = '', array $params = []): int
{
    global $db;
    $res = $db->sql_query_prepared("SELECT COUNT(*) AS {$col} FROM {$table} {$extra}", $params);
    if (!$res) return 0;
    $row = $db->fetch_array($res);
    return (int) ($row[$col] ?? 0);
}

/* ──────────────────────── Routing ───────────────────────────────── */

$act                 = get_act();
$_this_script_       = htmlspecialchars($_SERVER['SCRIPT_NAME']) . '?act=' . $act;
$_this_script_no_act = htmlspecialchars($_SERVER['SCRIPT_NAME']);

$act_array = ['securitycheck', 'managestafftools', 'stafftools'];

// Динамические инструменты
if (!empty($act) && !in_array($act, $act_array, true) && file_exists($thispath . $act . '.php')) {
    _file_access_check_($act);
    include $thispath . $act . '.php';
    render_floating_bar();
    exit();
}

if ($act === 'stafftools')       { render_stafftools_page();  exit(); }
if ($act === 'managestafftools') { handle_managestafftools(); exit(); }
if ($act === 'securitycheck')    { handle_securitycheck();    exit(); }

render_dashboard();
exit();


/* ═══════════════════════════════════════════════════════════════════
 *  FLOATING BAR
 * ═══════════════════════════════════════════════════════════════════ */

function render_floating_bar(): void
{
    global $BASEURL, $lang;

    $fb_badge     = htmlspecialchars($lang->staffpanel['fb_badge']);
    $fb_title     = htmlspecialchars($lang->staffpanel['fb_title']);
    $fb_subtitle  = htmlspecialchars($lang->staffpanel['fb_subtitle']);
    $fb_dashboard = htmlspecialchars($lang->staffpanel['fb_dashboard']);
    $fb_close     = htmlspecialchars($lang->staffpanel['fb_close'], ENT_QUOTES);

    // Только минимальный инлайн CSS для floating bar —
    // staff.css не подключаем чтобы не конфликтовать с Bootstrap на динамических страницах
    echo <<<HTML
<style>
.admin-floating-bar{position:fixed;top:20px;right:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:1px solid rgba(255,255,255,.2);border-radius:16px;padding:14px 18px;box-shadow:0 10px 40px rgba(59,130,246,.3);z-index:10000;display:flex;align-items:center;gap:10px;color:#fff;font-family:"Segoe UI",system-ui,sans-serif;font-size:14px;font-weight:500;transition:all .4s;animation:fbSlideIn .5s ease-out}
.admin-floating-bar:hover{transform:translateY(-2px) scale(1.02);background:linear-gradient(135deg,#2563eb,#1e40af)}
.admin-floating-bar.hidden{opacity:0;transform:translateX(100px);pointer-events:none}
.floating-bar-content{display:flex;align-items:center;gap:10px}
.floating-bar-icon{width:34px;height:34px;background:rgba(255,255,255,.2);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;border:1px solid rgba(255,255,255,.15)}
.floating-bar-text{flex:1}
.floating-bar-link{color:#fff;text-decoration:none;padding:7px 14px;background:rgba(255,255,255,.15);border-radius:9px;border:1px solid rgba(255,255,255,.2);font-weight:600;font-size:13px;display:flex;align-items:center;gap:5px;transition:background .2s}
.floating-bar-link:hover{background:rgba(255,255,255,.25);color:#fff}
.floating-bar-close{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);border-radius:9px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;color:#fff;cursor:pointer;font-size:13px;margin-left:6px;transition:all .2s}
.floating-bar-close:hover{background:rgba(255,255,255,.3);transform:rotate(90deg)}
.floating-bar-pulse{position:absolute;top:-2px;right:-2px;width:11px;height:11px;background:#60a5fa;border-radius:50%;box-shadow:0 0 12px #3b82f6;animation:fbPulse 2s infinite}
.floating-bar-badge{position:absolute;top:-7px;left:-7px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;padding:2px 5px;border-radius:8px;font-size:9px;font-weight:700;animation:fbBounce 2s infinite}
@keyframes fbSlideIn{from{opacity:0;transform:translateX(80px)}to{opacity:1;transform:translateX(0)}}
@keyframes fbPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.3);opacity:.6}}
@keyframes fbBounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-3px)}}
@media(max-width:768px){.admin-floating-bar{top:10px;right:10px;left:10px}}
</style>
HTML;
    echo <<<HTML
<div id="adminFloatingBar" class="admin-floating-bar">
    <div class="floating-bar-pulse"></div>
    <div class="floating-bar-badge">{$fb_badge}</div>
    <div class="floating-bar-content">
        <div class="floating-bar-icon"><i class="fas fa-user-shield"></i></div>
        <div class="floating-bar-text">
            <strong>{$fb_title}</strong>
            <div style="font-size:11px;opacity:.9">{$fb_subtitle}</div>
        </div>
        <div class="floating-bar-actions">
            <a href="{$BASEURL}/admin/index.php" class="floating-bar-link">
                <i class="fas fa-tachometer-alt"></i> {$fb_dashboard}
            </a>
        </div>
        <button class="floating-bar-close" title="{$fb_close}" aria-label="{$fb_close}"><i class="fas fa-times"></i></button>
    </div>
</div>
<script>
(function(){
    var bar = document.getElementById('adminFloatingBar');
    var btn = bar && bar.querySelector('.floating-bar-close');
    if(btn) btn.addEventListener('click', function(){
        bar.classList.add('hidden');
        setTimeout(function(){ bar.style.display='none'; }, 400);
    });
})();
</script>
HTML;
}


/* ═══════════════════════════════════════════════════════════════════
 *  STAFF TOOLS LIST
 * ═══════════════════════════════════════════════════════════════════ */

function render_stafftools_page(): void
{
    global $thispath, $lang;

	require_once $thispath . 'include/stafftoolsfunctions.php';


    stdhead($lang->staffpanel['page_stafftools']);
    enqueue_staff_assets(false);
    menu('stafftools');

    get_list();

    echo '</div>';                  // .sp-root (раньше не закрывался)
    echo '</td></tr></table>';
    stdfoot();
}


/* ═══════════════════════════════════════════════════════════════════
 *  MANAGE STAFF TOOLS
 * ═══════════════════════════════════════════════════════════════════ */

function handle_managestafftools(): void
{
    global $_this_script_, $_this_script_no_act, $db, $thispath, $mybb, $lang;

	require_once $thispath . 'include/stafftoolsfunctions.php';

	_access_check_();

    // CSRF - раньше отсутствовал вообще везде в этом блоке. save_tool()
    // меняет, какие группы имеют доступ к каким инструментам админки
    // (включая execute_sql_query) - без защиты это прямой путь к
    // эскалации привилегий через подделанный запрос со стороннего сайта.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            print_no_permission(true);
        }
    }

    $do = $_GET['do'] ?? '';
    $id = (isset($_GET['id']) && is_valid_id($_GET['id'])) ? (int) $_GET['id'] : null;

    if ($do === 'newtool')                  { render_tool_form('create');                return; }
    if ($do === 'savenewtool')              { save_tool('create');                       return; }
    if ($do === 'delete'   && $id !== null && $_SERVER['REQUEST_METHOD'] === 'POST') { delete_tool($id); return; }
    if ($do === 'delete'   && $id !== null) { render_delete_confirm($id);               return; }
    if ($do === 'edit'     && $id !== null) { render_tool_form('edit', fetch_tool($id)); return; }
    if ($do === 'savetool' && $id !== null) { save_tool('edit', $id);                   return; }




    // Список инструментов
    stdhead($lang->staffpanel['page_manage']);
    enqueue_staff_assets(false);
    menu('managestafftools');
    sp_swal_assets();

    get_list2();

    echo '</div>';                  // .sp-root
    echo '</td></tr></table>';
    stdfoot();
}

/* ── Tool CRUD helpers ───────────────────────────────────────────── */

function fetch_tool(int $id): array
{
    global $db, $lang;
    $sql = $db->sql_query_prepared('SELECT * FROM staffpanel WHERE id = ?', [$id]);
    if (!$sql || $db->num_rows($sql) === 0) { stderr($lang->staffpanel['err_tool_not_found']); exit(); }
    return $db->fetch_array($sql);
}

function save_tool(string $mode, int $id = 0): void
{
    global $db, $thispath, $lang;

    // Имя инструмента = ?act=<name> и файл admin/<name>.php, поэтому только [a-z0-9_].
    // Раньше имя проходило через htmlspecialchars_uni() и могло содержать «../».
    $name        = strtolower(trim((string) ($_POST['name'] ?? '')));
    $description = trim((string) ($_POST['description'] ?? ''));
    $filename    = $name . '.php';

    // Группы: только [число], а не произвольные строки из POST
    $groups = [];
    foreach ((array) ($_POST['gid'] ?? []) as $g) {
        $g = (int) trim((string) $g, '[] ');
        if ($g > 0) $groups['[' . $g . ']'] = true;
    }
    $groups = implode(',', array_keys($groups));

    if ($name === '' || $description === '' || $groups === '') {
        stderr($lang->staffpanel['err_fields_blank']); return;
    }
    if (!preg_match('/^[a-z0-9_]{1,64}$/', $name)) {
        stderr($lang->staffpanel['err_tool_name_invalid'] ?? 'Tool name may contain only latin letters, digits and _.'); return;
    }
    if (!file_exists($thispath . $filename)) {
        stderr(ags_fmt($lang->staffpanel['err_file_missing'], htmlspecialchars('admin/' . $filename)), false); return;
    }

    $data = [
        'name'        => $name,
        'description' => htmlspecialchars_uni($description),
        'filename'    => $filename,
        'usergroups'  => $groups,
    ];

    if ($mode === 'create') {
        $columns      = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $db->sql_query_prepared(
            "INSERT INTO staffpanel (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
            array_values($data)
        );
        write_log('Staff tool added: ' . $name, 'staff');
        redirect('admin/index.php?act=' . $name, $lang->staffpanel['flash_tool_added']);
    } else {
        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $params = array_values($data);
        $params[] = $id;
        $db->sql_query_prepared("UPDATE staffpanel SET {$set} WHERE id = ?", $params);
        write_log('Staff tool updated: ' . $name . ' (ID ' . $id . ')', 'staff');
        redirect('index.php?act=managestafftools', $lang->staffpanel['flash_tool_updated']);
    }
}

/** SweetAlert2 для подтверждений (с confirm() как запасным вариантом в JS). */
function sp_swal_assets(): void
{
    global $BASEURL;
    static $done = false;
    if ($done) return;
    $done = true;
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">' . "\n";
    echo '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js" defer></script>' . "\n";
    echo '<script src="' . $BASEURL . '/admin/scripts/stafftools-manage.js?ver=1" defer></script>' . "\n";
}

function render_delete_confirm(int $id): void
{
    global $_this_script_, $mybb, $lang;

    $tool = fetch_tool($id);
    $L    = $lang->staffpanel;
    $e    = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    stdhead($L['page_confirm_delete']);
    enqueue_staff_assets(false);
    menu('managestafftools');

    echo '<div class="container-md st-page sp-page">'
       .   '<div class="st-card sp-confirm">'
       .     '<div class="st-card__body">'
       .       '<div class="st-ico st-soft-danger sp-confirm__ico"><i class="fa-solid fa-trash-can"></i></div>'
       .       '<div class="sp-confirm__title">' . $e($L['del_question']) . '</div>'
       .       '<div class="sp-confirm__tool"><code>' . $e($tool['name']) . '</code> — ' . $tool['description'] . '</div>'
       .       '<form method="post" action="' . $_this_script_ . '&amp;do=delete&amp;id=' . $id . '" class="sp-confirm__actions">'
       .         '<input type="hidden" name="my_post_key" value="' . $e($mybb->post_code ?? '') . '">'
       .         '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-arrow-left"></i> ' . $e($L['btn_del_no']) . '</a>'
       .         '<button type="submit" class="btn btn-danger st-pill"><i class="fa-solid fa-trash-can"></i> ' . $e($L['btn_del_yes']) . '</button>'
       .       '</form>'
       .     '</div>'
       .   '</div>'
       . '</div>';

    echo '</div>';                  // .sp-root
    echo '</td></tr></table>';
    stdfoot();
}

function delete_tool(int $id): void
{
    global $db, $lang;

    $tool = fetch_tool($id);
    $db->sql_query_prepared('DELETE FROM staffpanel WHERE id = ?', [$id]);
    write_log('Staff tool deleted: ' . $tool['name'] . ' (ID ' . $id . ')', 'staff');
    redirect('admin/index.php?act=managestafftools', $lang->staffpanel['flash_tool_deleted']);
}

function render_tool_form(string $mode, ?array $tool = null): void
{
    global $_this_script_, $_this_script_no_act, $db, $thispath, $mybb, $lang;

    require_once $thispath . 'include/stafftoolsfunctions.php';

    $L       = $lang->staffpanel;
    $e       = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $is_edit = $mode === 'edit';

    $form_action = $is_edit
        ? $_this_script_ . '&amp;do=savetool&amp;id=' . (int) $tool['id']
        : $_this_script_ . '&amp;do=savenewtool';

    $val_name    = $is_edit ? $e($tool['name']) : '';
    // description хранится уже экранированным (htmlspecialchars_uni при сохранении)
    $val_desc    = $is_edit ? (string) $tool['description'] : '';
    $tool_groups = $is_edit ? explode(',', (string) $tool['usergroups']) : [];

    // Группы с доступом к панели
    $perms = '';
    $sql = $db->sql_query_prepared("SELECT gid, title, namestyle FROM usergroups WHERE canstaffpanel = '1' ORDER BY disporder");
    while ($sql && ($g = $db->fetch_array($sql))) {
        $gid     = (int) $g['gid'];
        $checked = $is_edit ? in_array('[' . $gid . ']', $tool_groups, true) : $gid === UC_SYSOP;
        $label   = str_replace('{username}', htmlspecialchars_uni((string) $g['title']), (string) $g['namestyle']);
        $default = (!$is_edit && $gid === UC_SYSOP)
            ? ' <span class="st-badge st-soft-primary">' . $e($L['badge_default']) . '</span>' : '';
        $perms .= '<label class="sp-perm" for="group_' . $gid . '">'
                . '<input class="form-check-input perm-cb" type="checkbox" name="gid[]" value="[' . $gid . ']" id="group_' . $gid . '"' . ($checked ? ' checked' : '') . '>'
                . '<span class="sp-perm__name">' . $label . '</span>' . $default
                . '</label>';
    }

    $title = $is_edit ? $L['form_title_edit'] : $L['form_title_create'];

    stdhead($title);
    enqueue_staff_assets(false);
    menu($is_edit ? 'managestafftools' : 'newtool');

    $info = '';
    if ($is_edit) {
        $added = (int) ($tool['added'] ?? 0);
        $info  = '<div class="st-head__meta">'
               . '<span><i class="fa-solid fa-hashtag"></i>' . $e($L['lbl_id']) . ' ' . (int) $tool['id'] . '</span>'
               . ($added > 0 ? '<span><i class="fa-solid fa-calendar-plus"></i>' . $e($L['lbl_created']) . ' ' . date('Y-m-d H:i', $added) . '</span>' : '')
               . '</div>';
    } else {
        $info = '<div class="st-head__meta"><span>' . $e($L['form_subtitle']) . '</span></div>';
    }

    $ph_file = $is_edit ? $e($tool['filename']) : 'tool_name.php';

    echo '<div class="container-md st-page sp-page">'
       . '<header class="st-card st-head mb-3">'
       .   '<div class="st-head__icon ' . ($is_edit ? 'st-soft-warning' : 'st-soft-primary') . '"><i class="fa-solid ' . ($is_edit ? 'fa-pen-to-square' : 'fa-square-plus') . '"></i></div>'
       .   '<div class="flex-grow-1 min-w-0"><h1 class="st-title">' . $e($title) . '</h1>' . $info . '</div>'
       . '</header>'

       . '<form method="post" action="' . $form_action . '" class="st-card sp-form needs-validation" novalidate>'
       .   '<input type="hidden" name="my_post_key" value="' . $e($mybb->post_code ?? '') . '">'

       .   '<div class="st-card__head"><span class="st-ico st-soft-primary"><i class="fa-solid fa-toolbox"></i></span>' . $e($L['form_subtitle']) . '</div>'
       .   '<div class="st-card__body">'
       .     '<div class="row g-3">'
       .       '<div class="col-md-6">'
       .         '<label class="form-label sp-lbl" for="toolName"><i class="fa-solid fa-tag"></i>' . $e($L['lbl_tool_name']) . '</label>'
       .         '<input type="text" class="form-control" id="toolName" name="name" value="' . $val_name . '"'
       .           ' placeholder="' . $e($L['ph_tool_name']) . '" pattern="[A-Za-z0-9_]{1,64}" maxlength="64" required autocomplete="off">'
       .       '</div>'
       .       '<div class="col-md-6">'
       .         '<label class="form-label sp-lbl" for="toolFilename"><i class="fa-solid fa-file-code"></i>' . $e($L['lbl_filename']) . '</label>'
       .         '<div class="input-group">'
       .           '<span class="input-group-text">admin/</span>'
       .           '<input type="text" class="form-control sp-mono" id="toolFilename" value="' . ($is_edit ? $e($tool['filename']) : '') . '" placeholder="' . $ph_file . '" readonly tabindex="-1">'
       .         '</div>'
       .         '<div class="form-text">' . $e($L['hint_filename_auto'] ?? 'Generated from the tool name — the file must already exist.') . '</div>'
       .       '</div>'
       .       '<div class="col-12">'
       .         '<label class="form-label sp-lbl" for="toolDesc"><i class="fa-solid fa-align-left"></i>' . $e($L['lbl_description']) . '</label>'
       .         '<input type="text" class="form-control" id="toolDesc" name="description" value="' . $val_desc . '"'
       .           ' placeholder="' . $e($L['ph_description']) . '" required>'
       .       '</div>'
       .     '</div>'
       .   '</div>'

       .   '<div class="st-card__head sp-form__sec">'
       .     '<span class="st-ico st-soft-success"><i class="fa-solid fa-user-shield"></i></span>' . $e($L['lbl_permissions'])
       .     '<div class="ms-auto d-flex gap-2">'
       .       '<button type="button" class="btn btn-sm btn-outline-secondary st-pill" data-sp-perms="1"><i class="fa-solid fa-check-double"></i> ' . $e($L['btn_check_all']) . '</button>'
       .       '<button type="button" class="btn btn-sm btn-outline-secondary st-pill" data-sp-perms="0"><i class="fa-solid fa-xmark"></i> ' . $e($L['btn_uncheck_all']) . '</button>'
       .     '</div>'
       .   '</div>'
       .   '<div class="st-card__body"><div class="sp-perms">' . $perms . '</div></div>'

       .   '<div class="st-toolbar st-toolbar--bottom">'
       .     '<a href="' . $_this_script_no_act . '?act=managestafftools" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-arrow-left"></i> ' . $e($L['btn_back']) . '</a>'
       .     '<div class="d-flex gap-2">'
       .       '<button type="reset" class="btn btn-outline-danger st-pill"><i class="fa-solid fa-rotate-left"></i> ' . $e($L['btn_reset']) . '</button>'
       .       '<button type="submit" class="btn ' . ($is_edit ? 'btn-warning' : 'btn-primary') . ' st-pill"><i class="fa-solid fa-floppy-disk"></i> ' . $e($is_edit ? $L['btn_update'] : $L['btn_create']) . '</button>'
       .     '</div>'
       .   '</div>'
       . '</form>'
       . '</div>';

    sp_swal_assets();               // stafftools-manage.js: «отметить все», имя файла из названия

    echo '</div>';                  // .sp-root
    echo '</td></tr></table>';
    stdfoot();
}


/* ═══════════════════════════════════════════════════════════════════
 *  SECURITY CONSOLE
 * ═══════════════════════════════════════════════════════════════════ */

function handle_securitycheck(): void
{
    global $db, $BASEURL, $iv, $securelogin, $bannedclientdetect, $maxloginattempts,
           $privatetrackerpatch, $disablerightclick, $trackerlog,
           $thispath, $accountlockout, $disallowjavascript, $SITEURL, $check__10, $lang;

    require_once $thispath . 'include/stafftoolsfunctions.php';

	_access_check_();
    stdhead($lang->staffpanel['page_security']);



    enqueue_staff_assets();
    menu('securitycheck');



    // ── Проверки ──────────────────────────────────────────────────
    $cfg_dir  = @file_get_contents($BASEURL . '/config/DATABASE', 'r');
    $cfg_file = @file_get_contents($BASEURL . '/include/config.php', 'r');



    $empty_pw_q = $db->sql_query_prepared("SELECT COUNT(*) AS c FROM users WHERE password='' OR password IS NULL");
    $empty_pw = $empty_pw_q ? (int) $db->fetch_array($empty_pw_q)['c'] : 0;

    $weak_pw_q = $db->sql_query_prepared("SELECT COUNT(*) AS c FROM users WHERE LENGTH(password)<6");
    $weak_pw = $weak_pw_q ? (int) $db->fetch_array($weak_pw_q)['c'] : 0;

    $mysql_ver_q = $db->sql_query_prepared("SELECT VERSION() AS v");
    $mysql_ver = $mysql_ver_q ? $db->fetch_array($mysql_ver_q)['v'] : '0.0.0';

	$safeTableName = $db->escape_string('users');
    $tables_q = $db->sql_query_prepared("SHOW TABLES LIKE '{$safeTableName}'");
    $has_default_table = $tables_q && $db->num_rows($tables_q) > 0;



    $https             = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    // [label, risk, passed, notice] — label/notice выводятся как есть (HTML из ланга)
    $checks = [
        [$lang->staffpanel['chk_cfg_dir'],          3, !str_contains((string)$cfg_dir, 'mysql_pass'),
            $lang->staffpanel['chk_cfg_dir_fail']],
        [$lang->staffpanel['chk_cfg_files'],        3,
            $cfg_file === '<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>',
            $lang->staffpanel['chk_cfg_files_fail']],
        [$lang->staffpanel['chk_vkeyboard'],        2, !empty($check__10),
            $lang->staffpanel['chk_vkeyboard_fail']],
        [$lang->staffpanel['chk_captcha'],          2, $iv === 'yes',
            $lang->staffpanel['chk_captcha_fail']],
        [$lang->staffpanel['chk_securelogin'],      2, $securelogin === 'yes',
            $lang->staffpanel['chk_securelogin_fail']],
        [$lang->staffpanel['chk_bannedclient'],     2, $bannedclientdetect === 'yes',
            $lang->staffpanel['chk_bannedclient_fail']],
        [$lang->staffpanel['chk_loginattempts7'],   2, $maxloginattempts <= 7,
            $lang->staffpanel['chk_loginattempts7_fail']],
        [$lang->staffpanel['chk_ptpatch'],          2, $privatetrackerpatch === 'yes',
            $lang->staffpanel['chk_ptpatch_fail']],
        [$lang->staffpanel['chk_rightclick'],       1, $disablerightclick === 'yes',
            $lang->staffpanel['chk_rightclick_fail']],
        [$lang->staffpanel['chk_cfgperms'],         3, substr(sprintf('%o', @fileperms('config.php')), -4) === '0644',
            $lang->staffpanel['chk_cfgperms_fail']],
        [$lang->staffpanel['chk_backups'],          3, !file_exists('backup.sql') && !file_exists('database_backup.zip'),
            $lang->staffpanel['chk_backups_fail']],
        [$lang->staffpanel['chk_installdir'],       3, !is_dir('install') && !is_dir('setup'),
            $lang->staffpanel['chk_installdir_fail']],
        [$lang->staffpanel['chk_debugfiles'],       2, !file_exists('phpinfo.php') && !file_exists('test.php'),
            $lang->staffpanel['chk_debugfiles_fail']],
        [$lang->staffpanel['chk_displayerrors'],    2, ini_get('display_errors') === '0' || ini_get('display_errors') === '',
            $lang->staffpanel['chk_displayerrors_fail']],
        [$lang->staffpanel['chk_logerrors'],        1, ini_get('log_errors') === '1',
            $lang->staffpanel['chk_logerrors_fail']],
        [$lang->staffpanel['chk_phpver'],           2, version_compare(PHP_VERSION, '7.4.0', '>='),
            $lang->staffpanel['chk_phpver_fail']],
        [$lang->staffpanel['chk_tableprefix'],      2, !$has_default_table,
            $lang->staffpanel['chk_tableprefix_fail']],
        [$lang->staffpanel['chk_emptypw'],          3, $empty_pw === 0,
            $lang->staffpanel['chk_emptypw_fail']],
        [$lang->staffpanel['chk_weakpw'],           2, $weak_pw === 0,
            $lang->staffpanel['chk_weakpw_fail']],
        [$lang->staffpanel['chk_https'],            2, $https,
            $lang->staffpanel['chk_https_fail']],
        [$lang->staffpanel['chk_siteurl'],          1, str_starts_with((string)$SITEURL, 'https://'),
            $lang->staffpanel['chk_siteurl_fail']],
        [$lang->staffpanel['chk_loginattempts5'],   2, $maxloginattempts <= 5,
            $lang->staffpanel['chk_loginattempts5_fail']],
        [$lang->staffpanel['chk_lockout'],          2, ($accountlockout ?? '') === 'yes',
            $lang->staffpanel['chk_lockout_fail']],
        [$lang->staffpanel['chk_csrf'],             2, function_exists('csrf_token') || $securelogin === 'yes',
            $lang->staffpanel['chk_csrf_fail']],
        [$lang->staffpanel['chk_jsrestrict'],       2, ($disallowjavascript ?? '') === 'yes',
            $lang->staffpanel['chk_jsrestrict_fail']],
        [$lang->staffpanel['chk_mysqlver'],         2, version_compare($mysql_ver, '5.7.0', '>='),
            $lang->staffpanel['chk_mysqlver_fail']],
        [$lang->staffpanel['chk_accesslog'],        1, ($trackerlog ?? '') === 'yes',
            $lang->staffpanel['chk_accesslog_fail']],
        [$lang->staffpanel['chk_httponly'],         2, ini_get('session.cookie_httponly') === '1',
            $lang->staffpanel['chk_httponly_fail']],
        [$lang->staffpanel['chk_securecookie'],     2, ini_get('session.cookie_secure') === '1' || $https,
            $lang->staffpanel['chk_securecookie_fail']],
    ];

    $passed       = array_sum(array_column($checks, 2));
    $total        = count($checks);
    $score        = round(($passed / $total) * 100, 1);
    $failed       = $total - $passed;

    $level_map = [
        90 => [$lang->staffpanel['level_excellent'], 'success', 'fas fa-shield-alt'],
        70 => [$lang->staffpanel['level_good'],      'info',    'fas fa-check-circle'],
        50 => [$lang->staffpanel['level_fair'],      'warning', 'fas fa-exclamation-triangle'],
         0 => [$lang->staffpanel['level_poor'],      'danger',  'fas fa-radiation-alt'],
    ];
    $level = $level_map[0];
    foreach ($level_map as $threshold => $data) {
        if ($score >= $threshold) { $level = $data; break; }
    }
    [$level_name, $level_color, $level_icon] = $level;
    $level_name = htmlspecialchars($level_name);

    $t_sec_title   = htmlspecialchars($lang->staffpanel['sec_title']);
    $t_sec_score   = htmlspecialchars($lang->staffpanel['sec_score']);
    $t_sec_passed  = htmlspecialchars(ags_fmt($lang->staffpanel['sec_passed'], $passed));
    $t_sec_failed  = htmlspecialchars(ags_fmt($lang->staffpanel['sec_failed'], $failed));
    $t_sec_total   = htmlspecialchars(ags_fmt($lang->staffpanel['sec_total'], $total));
    $t_sec_head    = htmlspecialchars(ags_fmt($lang->staffpanel['sec_checks_head'], $total));
    $t_check_ok    = htmlspecialchars($lang->staffpanel['sec_check_ok']);
    $t_notice      = htmlspecialchars($lang->staffpanel['notice_title']);
    $t_notice_text = htmlspecialchars($lang->staffpanel['notice_text']);
    $t_remember    = $lang->staffpanel['notice_remember']; // содержит <strong>

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
      <h2 class="fw-bold mb-3">{$t_sec_title}</h2>
      <div style="display:inline-block;background:conic-gradient(#10b981 {$score}%,#e2e8f0 0);
                  width:100px;height:100px;border-radius:50%;position:relative;margin-bottom:.75rem">
        <div style="position:absolute;inset:10px;background:#fff;border-radius:50%;
                    display:flex;flex-direction:column;align-items:center;justify-content:center">
          <span style="font-size:1.2em;font-weight:700">{$score}%</span>
          <span style="font-size:.65em;color:#64748b">{$t_sec_score}</span>
        </div>
      </div><br>
      <span class="badge bg-{$level_color} fs-6 mb-3">
        <i class="{$level_icon} me-1"></i>{$level_name}
      </span>
      <div class="d-flex justify-content-center gap-4 mt-2">
        <span><i class="fas fa-check-circle text-success me-1"></i>{$t_sec_passed}</span>
        <span><i class="fas fa-times-circle text-danger me-1"></i>{$t_sec_failed}</span>
        <span><i class="fas fa-list-alt text-primary me-1"></i>{$t_sec_total}</span>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light border-0 py-3">
      <h5 class="mb-0"><i class="fas fa-tasks me-2 text-primary"></i>{$t_sec_head}</h5>
    </div>
    <div class="card-body p-0">
HTML;

    $risk_colors = [1 => 'success', 2 => 'warning', 3 => 'danger'];
    $risk_labels = [
        1 => htmlspecialchars($lang->staffpanel['risk_low']),
        2 => htmlspecialchars($lang->staffpanel['risk_medium']),
        3 => htmlspecialchars($lang->staffpanel['risk_high']),
    ];
    $risk_icons  = [1 => 'fas fa-shield-alt', 2 => 'fas fa-exclamation-triangle', 3 => 'fas fa-radiation-alt'];

    foreach ($checks as [$label, $risk, $ok, $notice]) {
        $status   = $ok ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger';
        $msg      = $ok
            ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>' . $t_check_ok . '</span>'
            : '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>' . $notice . '</span>';
        $row_cls  = $ok ? '' : ' failed';
        echo <<<HTML
      <div class="sec-item{$row_cls}">
        <div class="sec-item-inner">
          <div class="sec-icon"><i class="{$status}"></i></div>
          <div class="sec-title">
            <h6>{$label}</h6>
            <div class="sec-notice">{$msg}</div>
          </div>
          <span class="badge bg-{$risk_colors[$risk]} risk-badge">
            <i class="{$risk_icons[$risk]} me-1"></i>{$risk_labels[$risk]}
          </span>
        </div>
      </div>
HTML;
    }

    echo <<<HTML
    </div>
  </div>

  <div class="card border-warning mb-4">
    <div class="card-body d-flex align-items-start gap-3">
      <i class="fas fa-exclamation-triangle text-warning fs-4 mt-1"></i>
      <div>
        <h6 class="text-warning mb-1">{$t_notice}</h6>
        <p class="text-muted mb-1">{$t_notice_text}</p>
        <div>
          <span class="badge bg-light text-dark me-1">TS Special Edition</span>
          <span class="badge bg-light text-dark me-1">Apache</span>
          <span class="badge bg-light text-dark me-1">PHP</span>
          <span class="badge bg-light text-dark me-1">MySQL</span>
          <span class="badge bg-light text-dark">phpMyAdmin</span>
        </div>
        <p class="text-muted mb-0 mt-2">{$t_remember}</p>
      </div>
    </div>
  </div>
</div>
HTML;

    echo '</div></td></tr></table>';
    stdfoot();
}


/* ═══════════════════════════════════════════════════════════════════
 *  DASHBOARD
 * ═══════════════════════════════════════════════════════════════════ */

function render_dashboard(): void
{
    global $db, $CURUSER, $SITENAME, $thispath, $BASEURL, $lang;

    require_once $thispath . 'include/stafftoolsfunctions.php';

    $L   = $lang->staffpanel;
    $e   = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $cut = TIMENOW - 86400;

    // Пользователи — один запрос вместо пяти
    $u = $db->fetch_array($db->sql_query_prepared(
        "SELECT COALESCE(SUM(ustatus = 'confirmed'), 0) AS total,
                COALESCE(SUM(added > ?), 0)             AS new_today,
                COALESCE(SUM(ustatus = 'pending'), 0)   AS pending,
                COALESCE(SUM(lastactive > ?), 0)        AS active_today,
                COALESCE(SUM(uploaded), 0)              AS ul,
                COALESCE(SUM(downloaded), 0)            AS dl
         FROM users",
        [$cut, $cut]
    )) ?: [];

    // Пиры — один запрос вместо трёх
    $p = $db->fetch_array($db->sql_query_prepared(
        "SELECT COUNT(*) AS peers, COALESCE(SUM(seeder = 'yes'), 0) AS seeders FROM peers"
    )) ?: [];

    $totalusers    = (int) ($u['total'] ?? 0);
    $newuserstoday = (int) ($u['new_today'] ?? 0);
    $pendingusers  = (int) ($u['pending'] ?? 0);
    $todayvisits   = (int) ($u['active_today'] ?? 0);
    $peers         = (int) ($p['peers'] ?? 0);
    $seeders       = (int) ($p['seeders'] ?? 0);
    $leechers      = max(0, $peers - $seeders);
    $totaltorrents = get_count('c', 'torrents');
    $todaycomments = get_count('c', 'comments', 'WHERE dateline > ?', [$cut]);

    $ul    = (float) ($u['ul'] ?? 0);
    $dl    = (float) ($u['dl'] ?? 0);
    $ratio = $dl > 0 ? number_format($ul / $dl, 2) : '∞';

    // Системные метрики
    $serverload     = function_exists('get_server_load')  ? (float) get_server_load() : 0.0;
    $memory_display = function_exists('get_memory_usage') ? mksize(get_memory_usage()) : '—';
    $dbsize         = getDbSize();
    $diskfree       = getDiskFree();
    $recentactivity = getRecentActivity(6);

    $load_pct  = (int) round(min(max($serverload, 0), 100));
    $load_tone = $load_pct > 80 ? 'danger' : ($load_pct > 60 ? 'warning' : 'success');

    stdhead($L['page_dashboard']);
    enqueue_staff_assets(false);
    menu('welcome');

    // ── KPI-плитки ──
    $tile = static function (string $icon, string $soft, string $val, string $lbl, string $sub = ''): string {
        return '<div class="st-kpi st-kpi--muted">'
             .   '<span class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
             .   '<div class="min-w-0">'
             .     '<div class="st-kpi__val">' . $val . '</div>'
             .     '<div class="st-kpi__lbl">' . $lbl . '</div>'
             .     ($sub !== '' ? '<div class="sp-kpi__sub">' . $sub . '</div>' : '')
             .   '</div>'
             .   '<i class="fa-solid ' . $icon . ' st-kpi__bg" aria-hidden="true"></i>'
             . '</div>';
    };

    // ── Строка «метка — значение» (st-rows из st-ui.css) ──
    $row = static fn(string $icon, string $lbl, string $val, string $cls = ''): string =>
        '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid ' . $icon . '"></i>' . $lbl . '</span>'
        . '<span class="st-row__val' . ($cls !== '' ? ' ' . $cls : '') . '">' . $val . '</span></div>';

    $kpis = $tile('fa-users',      'st-soft-primary', ts_nf($totalusers),    $e($L['stat_total_users']),
                  '<i class="fa-solid fa-plus text-success"></i> ' . ts_nf($newuserstoday) . ' ' . $e(mb_strtolower($L['stat_new_today'])))
          . $tile('fa-eye',        'st-soft-info',    ts_nf($todayvisits),   $e($L['stat_active_users']),
                  '<i class="fa-solid fa-comment-dots"></i> ' . ts_nf($todaycomments) . ' ' . $e(mb_strtolower($L['stat_comments'])))
          . $tile('fa-users-line', 'st-soft-success', ts_nf($peers),         $e($L['stat_peers']),
                  '<i class="fa-solid fa-arrow-up text-success"></i> ' . ts_nf($seeders)
                  . ' <i class="fa-solid fa-arrow-down text-danger ms-1"></i> ' . ts_nf($leechers))
          . $tile('fa-magnet',     'st-soft-danger',  ts_nf($totaltorrents), $e($L['stat_torrents']));

    $users_rows = $row('fa-user-check',  $e($L['stat_total_users']),  ts_nf($totalusers))
                . $row('fa-user-plus',   $e($L['stat_new_today']),    ts_nf($newuserstoday), 'text-success')
                . $row('fa-user-clock',  $e($L['stat_unconfirmed']),  ts_nf($pendingusers),  $pendingusers > 0 ? 'text-warning' : '')
                . $row('fa-eye',         $e($L['stat_active_users']), ts_nf($todayvisits))
                . $row('fa-comment-dots',$e($L['stat_comments']),     ts_nf($todaycomments));

    $traffic_rows = $row('fa-arrow-up',        $e($L['stat_uploaded']),   mksize($ul), 'text-success')
                  . $row('fa-arrow-down',      $e($L['stat_downloaded']), mksize($dl), 'text-danger')
                  . $row('fa-scale-balanced',  $e($L['stat_ratio']),      $ratio)
                  . $row('fa-seedling',        $e($L['stat_seeders']),    ts_nf($seeders), 'text-success')
                  . $row('fa-download',        $e($L['stat_leechers']),   ts_nf($leechers), 'text-danger');

    $health_rows = $row('fa-database', $e($L['lbl_db_size']),   $e($dbsize))
                 . $row('fa-hard-drive', $e($L['lbl_disk_free']), $e($diskfree))
                 . $row('fa-memory',   $e($L['lbl_memory']),    $e($memory_display));

    $t_welcome = ags_fmt($e($L['dash_welcome']), $e($SITENAME));
    $username  = htmlspecialchars_uni($CURUSER['username']);
    $date_str  = $e(date($L['fmt_date_short']));
    $time_str  = date('H:i:s');

    echo <<<HTML
<div class="container-md st-page sp-page">

  <header class="st-card st-head mb-3">
    <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-user-shield"></i></div>
    <div class="flex-grow-1 min-w-0">
      <h1 class="st-title">{$t_welcome}</h1>
      <div class="st-head__meta">
        <span><i class="fa-solid fa-user"></i>{$username}</span>
        <span>{$e($L['dash_tagline'])}</span>
      </div>
    </div>
    <div class="sp-clock" aria-live="off">
      <i class="fa-solid fa-clock"></i>
      <div>
        <div class="sp-clock__time" id="live-clock">{$time_str}</div>
        <div class="sp-clock__date">{$date_str}</div>
      </div>
    </div>
  </header>

  <div class="sp-kpis mb-3">{$kpis}</div>

  <div class="sp-grid mb-3">
    <section class="st-card">
      <div class="st-card__head"><span class="st-ico st-soft-primary"><i class="fa-solid fa-users"></i></span>{$e($L['card_user_stats'])}</div>
      <div class="st-card__body"><div class="st-rows">{$users_rows}</div></div>
    </section>
    <section class="st-card">
      <div class="st-card__head"><span class="st-ico st-soft-info"><i class="fa-solid fa-right-left"></i></span>{$e($L['card_traffic'])}</div>
      <div class="st-card__body"><div class="st-rows">{$traffic_rows}</div></div>
    </section>
  </div>

  <div class="sp-grid mb-3">
    <section class="st-card">
      <div class="st-card__head"><span class="st-ico st-soft-danger"><i class="fa-solid fa-heart-pulse"></i></span>{$e($L['card_health'])}</div>
      <div class="st-card__body">
        <div class="sp-load">
          <div class="sp-load__top"><span><i class="fa-solid fa-gauge-high"></i>{$e($L['lbl_server_load'])}</span><strong>{$load_pct}%</strong></div>
          <div class="progress sp-load__bar" role="progressbar" aria-valuenow="{$load_pct}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-{$load_tone}" style="width:{$load_pct}%"></div>
          </div>
        </div>
        <div class="st-rows">{$health_rows}</div>
      </div>
    </section>
    <section class="st-card sp-recent">
      <div class="st-card__head">
        <span class="st-ico st-soft-muted"><i class="fa-solid fa-clock-rotate-left"></i></span>{$e($L['card_recent'])}
        <a href="index.php?act=log" class="ms-auto btn btn-sm btn-outline-secondary st-pill"><i class="fa-solid fa-list"></i> {$e($L['btn_view_logs'])}</a>
      </div>
      <div class="st-card__body">{$recentactivity}</div>
    </section>
  </div>

</div>
HTML;

    echo '</div></td></tr></table>';   // .sp-root + layout
    stdfoot();
}


/* ═══════════════════════════════════════════════════════════════════
 *  DB SIZE / DISK / MEMORY / RECENT ACTIVITY
 * ═══════════════════════════════════════════════════════════════════ */

function getDbSize(): string
{
    global $db, $config, $lang;
    $dbname = $config['database']['database'] ?? null;
    if (!$dbname) return '—';
    $r = $db->sql_query_prepared(
        "SELECT ROUND(SUM(data_length+index_length)/1024/1024,1) AS mb
         FROM information_schema.tables
         WHERE table_schema = ?", [$dbname]);
    if (!$r) return '—';
    return ($db->fetch_array($r)['mb'] ?? 0) . ' ' . $lang->staffpanel['unit_mb'];
}

function getDiskFree(): string
{
    global $lang;
    $free = disk_free_space(defined('TSDIR') ? TSDIR : __DIR__);
    if ($free === false) return '—';
    $gb = $free / (1024 ** 3);
    return $gb >= 1
        ? round($gb, 1) . ' ' . $lang->staffpanel['unit_gb']
        : round($free / (1024 ** 2)) . ' ' . $lang->staffpanel['unit_mb'];
}

function getRecentActivity(int $limit = 5): string
{
    global $db, $lang;
    $r = $db->sql_query_prepared(
        "SELECT l.*, u.username FROM sitelog l
         LEFT JOIN users u ON l.uid=u.id
         ORDER BY l.added DESC, l.id DESC LIMIT ?", [$limit]);

    if (!$r || $db->num_rows($r) === 0) {
        return '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-solid fa-clock-rotate-left"></i></div>'
             . htmlspecialchars($lang->staffpanel['no_recent_activity']) . '</div>';
    }

    $html = '<ul class="sp-log">';
    while ($row = $db->fetch_array($r)) {
        $user   = htmlspecialchars($row['username'] ?? $lang->staffpanel['user_system']);
        $action = htmlspecialchars($row['txt']      ?? '');
        // Admin actions keep a short code in txt (edit, delete …) — show the module with it
        if (($row['category'] ?? '') === 'admin' && !empty($row['module'])) {
            $action = '<span class="st-badge st-soft-danger me-1">' . htmlspecialchars((string)$row['module']) . '</span>' . $action;
        } elseif (($row['category'] ?? '') === 'moderator') {
            $action = '<span class="st-badge st-soft-success me-1"><i class="fa-solid fa-user-shield"></i></span>' . $action;
        }
        $time   = date('d.m H:i', (int)($row['added'] ?? 0));
        $html  .= '<li class="sp-log__item">'
                . '<span class="sp-log__dot" aria-hidden="true"></span>'
                . '<div class="min-w-0"><div class="sp-log__text"><strong>' . $user . '</strong> ' . $action . '</div>'
                . '<div class="sp-log__time"><i class="fa-solid fa-clock"></i>' . $time . '</div></div></li>';
    }
    return $html . '</ul>';
}
