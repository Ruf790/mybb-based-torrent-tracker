<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<b>Error!</b> Direct initialization of this file is not allowed.');
}



define('M_VERSION', 'Mass Mail v.3.0');


set_time_limit(0);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function mm_esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function show_error(): void
{
    global $errormessage;
    if (!empty($errormessage)) {
        echo '<div class="alert alert-danger mt-3"><strong>Error:</strong> ' . mm_esc($errormessage) . '</div>';
    }
}

function build_usergroup_checkboxes(): string
{
    global $db;

    $query  = $db->sql_query_prepared('SELECT gid, title, namestyle, image FROM usergroups ORDER BY gid');
    $items  = '';
    $count  = 0;

    while ($row = $db->fetch_array($query)) {
        $label = format_name($row['title'], $row['gid']);
        $icon_class = $row['image'];

        $items .= sprintf(
            '<div class="col-md-4 col-sm-6">
                <div class="form-check form-switch ug-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="usergroup[]" id="ug_%1$d" value="%1$d">
                    <label class="form-check-label" for="ug_%1$d">%3$s%2$s</label>
                </div>
            </div>',
            (int)$row['gid'],
            $label,
            $icon_class
        );
        ++$count;
    }

    return '<div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label fw-semibold mb-0"><i class="fas fa-users me-2 text-primary"></i>Recipients (usergroups)</label>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="ug_select_all" onclick="
                    document.querySelectorAll(\'[name=\\\'usergroup[]\\\']\').forEach(c => c.checked = this.checked);">
                <label class="form-check-label small text-muted" for="ug_select_all">Select all</label>
            </div>
        </div>
        <div class="ug-panel row g-2">' . $items . '</div>
    </div>';
}

function show_form(string $ugids_html): void
{
    global $_this_script_, $mybb;
    $action = mm_esc($_this_script_);
    $token  = mm_esc($mybb->post_code ?? '');
    echo <<<HTML
    <style>
        .mm-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a4fc4 100%);
            border-radius: 16px 16px 0 0;
            padding: 1.75rem 2rem;
            color: #fff;
        }
        .mm-header h4 { margin: 0; font-weight: 700; }
        .mm-header p { margin: .35rem 0 0; opacity: .85; font-size: .9rem; }
        .mm-card { border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .mm-section-label { font-weight: 600; color: #495057; }
        .mm-input-icon { position: relative; }
        .mm-input-icon i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: #adb5bd; pointer-events: none;
        }
        .mm-input-icon input { padding-left: 2.5rem; }
        .ug-panel {
            background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 12px;
            padding: 1rem; margin: 0;
        }
        .ug-switch {
            background: #fff; border: 1px solid #e9ecef; border-radius: 10px;
            padding: .6rem .75rem .6rem 2.5rem; margin: 0; transition: all .15s ease;
        }
        .ug-switch:hover { border-color: #0d6efd; box-shadow: 0 2px 8px rgba(13,110,253,.1); }
        .ug-switch .form-check-input:checked ~ .form-check-label { color: #0d6efd; font-weight: 600; }
        .ug-switch .form-check-label { width: 100%; cursor: pointer; }
        #message { font-family: inherit; resize: vertical; }
        .mm-submit-bar {
            background: #f8f9fa; border-top: 1px solid #e9ecef;
            padding: 1.25rem 2rem; border-radius: 0 0 16px 16px;
        }
    </style>

    <form method="post" action="{$action}" name="massmail"
          onsubmit="this.submit.value='Please wait…'; this.submit.disabled=true;">
        <input type="hidden" name="action" value="sent">
        <input type="hidden" name="my_post_key" value="{$token}">

        <div class="container-md my-4">
            <div class="mm-card">

                <div class="mm-header">
                    <h4><i class="fas fa-paper-plane me-2"></i>Mass Mail to Tracker Users</h4>
                    <p>Send an email to every confirmed member of the selected usergroups.</p>
                </div>

                <div class="card-body p-4">

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label mm-section-label"><i class="fas fa-clock me-1 text-muted"></i>Sleep time <small class="text-muted fw-normal">(seconds between batches)</small></label>
                            <div class="mm-input-icon">
                                <i class="fas fa-hourglass-half"></i>
                                <input type="number" min="1" name="waitbeforeredirect" class="form-control" value="30">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mm-section-label"><i class="fas fa-layer-group me-1 text-muted"></i>Batch size <small class="text-muted fw-normal">(emails per batch)</small></label>
                            <div class="mm-input-icon">
                                <i class="fas fa-envelope-open-text"></i>
                                <input type="number" min="1" name="max_results" class="form-control" value="10">
                            </div>
                        </div>
                    </div>

                    {$ugids_html}

                    <div class="mb-3">
                        <label class="form-label mm-section-label"><i class="fas fa-heading me-1 text-muted"></i>Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="Email subject…">
                    </div>

                    <div class="mb-2">
                        <label class="form-label mm-section-label"><i class="fas fa-align-left me-1 text-muted"></i>Message</label>
                        <textarea name="message" id="message" class="form-control" style="height:260px;" placeholder="Write your message here…"></textarea>
                    </div>

                </div>

                <div class="mm-submit-bar d-flex gap-2">
                    <button type="submit" name="submit" class="btn btn-primary px-4">
                        <i class="fas fa-paper-plane me-1"></i> Send Mail
                    </button>
                    <button type="reset" class="btn btn-outline-secondary">
                        <i class="fas fa-rotate-left me-1"></i>Reset
                    </button>
                </div>

            </div>
        </div>
    </form>
    HTML;
}

// ─── Config path ──────────────────────────────────────────────────────────────

$config_file = TSDIR . '/cache/massmail_config.php';

// ─── Determine action ─────────────────────────────────────────────────────────

$action = 'start';
$page   = 1;

if (isset($_GET['do']) && $_GET['do'] === 'stop') {
    $action = 'start';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    global $mybb;
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        stderr('Error', 'Security check failed. Please refresh the page and try again.', false);
    }

    // Validate config file
    if (!file_exists($config_file) || !is_writable($config_file)) {
        stderr('Error', '<b>' . mm_esc($config_file) . '</b> doesn\'t exist or isn\'t writable!', false);
    }

    $waitbeforeredirect = max(1, (int)($_POST['waitbeforeredirect'] ?? 30));
    $max_results        = max(1, (int)($_POST['max_results'] ?? 10));
    $mmusergroups       = implode(',', array_map('intval', (array)($_POST['usergroup'] ?? [])));
    $subject            = trim((string)($_POST['subject'] ?? ''));
    $message            = trim((string)($_POST['message'] ?? ''));
    $page               = 1;

    $contents = '<?php' . "\n"
        . 'if (!defined(\'M_VERSION\')) die(\'Direct initialization not allowed.\');' . "\n"
        . '// Generated: ' . gmdate('r') . "\n"
        . '$waitbeforeredirect = ' . var_export($waitbeforeredirect, true) . ';' . "\n"
        . '$max_results        = ' . var_export($max_results, true) . ';' . "\n"
        . '$mmusergroups       = ' . var_export($mmusergroups, true) . ';' . "\n"
        . '$subject            = ' . var_export($subject, true) . ';' . "\n"
        . '$message            = ' . var_export($message, true) . ';' . "\n";

    if (file_put_contents($config_file, $contents) === false) {
        stderr('Error', 'Cannot write to <b>' . mm_esc($config_file) . '</b>. Check permissions.', false);
    }

    $action = 'sent';

} elseif (isset($_GET['action'], $_GET['page']) && $_GET['action'] === 'sent') {

    $action = 'sent';
    $page   = max(1, (int)$_GET['page']);
    include_once $config_file;
}

// ─── Sending ──────────────────────────────────────────────────────────────────

if ($action === 'sent') {

    $subject      ??= '';
    $message      ??= '';
    $mmusergroups ??= '';

    if ($subject === '' || $message === '' || $message === '<br />' || $mmusergroups === '') {
        $errormessage = 'Subject, Message and Usergroups are all required!';
        $action       = 'start';
        goto show_start;
    }

    // Преобразуем строку групп вида "1,2,3" обратно в массив int
    $ugids = [];
    if ($mmusergroups !== '') {
        $ugids = array_filter(array_map('intval', explode(',', $mmusergroups)));
    }

    // Формируем WHERE и параметры
    $where  = ["enabled = ?", "ustatus = ?"];
    $params = ['yes', 'confirmed'];

    if (!empty($ugids)) {
        $placeholders = implode(',', array_fill(0, count($ugids), '?'));
        $where[] = "usergroup IN (0, {$placeholders})";
        $params  = array_merge($params, $ugids);
    }

    $whereClause = implode(' AND ', $where);

    $countQuery = $db->sql_query_prepared(
        "SELECT COUNT(email) AS total FROM users WHERE {$whereClause}",
        $params
    );
    $count_row = $db->fetch_field($countQuery, 'total');
    $result    = (int)$count_row;

    if ($result === 0) {
        $errormessage = 'No email addresses found in the database!';
        $action       = 'start';
        goto show_start;
    }

    $from = ($page - 1) * $max_results;

    stdhead(VERSION . ' – SEND');

    echo '<div class="container-md mt-4">
        <div class="mm-card" style="border:0;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,.08);">
            <div class="mm-header" style="background:linear-gradient(135deg, #0d6efd 0%, #0a4fc4 100%);padding:1.75rem 2rem;color:#fff;">
                <h4 style="margin:0;font-weight:700;"><i class="fas fa-envelope-circle-check me-2"></i>Sending Mass Mail</h4>
                <p style="margin:.35rem 0 0;opacity:.85;font-size:.9rem;">
                    <strong>' . $result . '</strong> emails found &bull;
                    <strong>' . $max_results . '</strong> per batch &bull;
                    ' . $waitbeforeredirect . 's between batches
                </p>
            </div>
            <div class="card-body p-4">';

    $limitParams = array_merge($params, [$from, $max_results]);
    $emails = $db->sql_query_prepared(
        "SELECT email FROM users WHERE {$whereClause} LIMIT ?, ?",
        $limitParams
    );

    include_once $rootpath . '/admin/include/staff_languages.php';

    echo '<div class="list-group">';

    while ($row = $db->fetch_array($emails)) {
        $to   = $row['email'];
        $body = ($adminlang['massmail']['header'] ?? '')
              . '<br><hr><br>' . $message . '<br><hr><br>'
              . ($adminlang['massmail']['footer'] ?? '');

        $ok = my_mail($to, $subject, $body, '', '', '', false, 'html', '');

        echo '<div class="list-group-item d-flex justify-content-between align-items-center border-0 border-bottom rounded-0">'
           . '<span><i class="fas fa-envelope me-2 text-muted"></i>' . mm_esc($to) . '</span>'
           . ($ok
               ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check me-1"></i>Sent</span>'
               : '<span class="badge bg-danger rounded-pill"><i class="fas fa-xmark me-1"></i>Error</span>')
           . '</div>';
    }

    echo '</div></div></div></div>';

    $total_pages = (int)ceil($result / $max_results);

    if ($page < $total_pages) {
        $next   = $page + 1;
        $jumpto = json_encode($_this_script_ . '&action=sent&page=' . $next, JSON_UNESCAPED_SLASHES);

        echo '<div class="container-md mt-3 text-center">
            <div id="waitmessage" class="alert alert-primary border-0 shadow-sm rounded-3 py-3">
                <i class="fas fa-hourglass-half me-2"></i>
                Next batch (' . ($page + 1) . ' / ' . $total_pages . ') in <span id="countdown"><strong>' . $waitbeforeredirect . '</strong></span> seconds…
            </div>
        </div>';

        echo '<script>
        (function() {
            var sec = ' . (int)$waitbeforeredirect . ';
            var el  = document.getElementById("countdown");
            var timer = setInterval(function() {
                sec--;
                if (sec <= 0) {
                    clearInterval(timer);
                    window.location.href = ' . $jumpto . ';
                } else {
                    el.innerHTML = "<strong>" + sec + "</strong>";
                }
            }, 1000);
        })();
        </script>';

        stdfoot();
        exit;
    }

    echo '<div class="container-md mt-3">
        <div class="alert alert-success border-0 shadow-sm rounded-3 text-center py-4">
            <i class="fas fa-circle-check fa-2x mb-2 d-block"></i>
            <strong>Done!</strong> Sent to <strong>' . $result . '</strong> address(es).
        </div>
    </div>';

    stdfoot();
    exit;
}

// ─── Start / form ─────────────────────────────────────────────────────────

show_start:
stdhead(VERSION . ' – START', true, '', '');

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/userclass.css" type="text/css" media="screen" />';

show_error();
show_form(build_usergroup_checkboxes());
stdfoot();