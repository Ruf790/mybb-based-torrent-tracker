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

    $query  = $db->sql_query('SELECT gid, title, namestyle FROM usergroups ORDER BY gid');
    $items  = '';
    $count  = 0;

    while ($row = $db->fetch_array($query)) {
        $label  = format_name($row['title'], $row['gid']);
        $items .= sprintf(
            '<div class="form-check form-check-inline me-3 mb-2">
                <input class="form-check-input" type="checkbox" name="usergroup[]" id="ug_%1$d" value="%1$d">
                <label class="form-check-label" for="ug_%1$d">%2$s</label>
            </div>',
            (int)$row['gid'],
            $label
        );
        ++$count;
    }

    return '<fieldset class="border rounded p-3 mb-3">
        <legend class="float-none w-auto px-2 fs-6 fw-bold">Recipients (usergroups)</legend>
        <div class="d-flex flex-wrap">' . $items . '</div>
        <a href="#" class="btn btn-sm btn-outline-secondary mt-2" onclick="
            document.querySelectorAll(\'[name=\\\'usergroup[]\\\']\'
            ).forEach(c => c.checked = true); return false;">
            Check all
        </a>
    </fieldset>';
}

function show_form(string $ugids_html): void
{
    global $_this_script_;
    $action = mm_esc($_this_script_);
    echo <<<HTML
    <form method="post" action="{$action}" name="massmail"
          onsubmit="this.submit.value='Please wait…'; this.submit.disabled=true;">
        <input type="hidden" name="action" value="sent">

        <div class="container-md">
            <div class="card mb-4">
                <div class="card-header fw-bold fs-5">Mass Mail to Tracker Users</div>
                <div class="card-body">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sleep time <small class="text-muted">(seconds)</small></label>
                            <input type="number" min="1" name="waitbeforeredirect" class="form-control" value="30">
                            <div class="form-text">Wait X seconds before sending next batch.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Batch size</label>
                            <input type="number" min="1" name="max_results" class="form-control" value="10">
                            <div class="form-text">Emails per batch. Keep low for better performance.</div>
                        </div>
                    </div>

                    {$ugids_html}

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject</label>
                        <input type="text" name="subject" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message</label>
                        <textarea name="message" id="message" class="form-control" style="height:260px;"></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Send Mail
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
                    </div>

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

    $contents = '<?php
if (!defined(\'M_VERSION\')) die(\'Direct initialization not allowed.\');
// Generated: ' . gmdate('r') . '
$waitbeforeredirect = ' . $waitbeforeredirect . ';
$max_results        = ' . $max_results . ';
$mmusergroups       = "' . $mmusergroups . '";
$subject            = "' . addslashes($subject) . '";
$message            = "' . addslashes($message) . '";
';

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

    $ug_sql   = $mmusergroups !== '-'
        ? 'AND usergroup IN (0,' . $mmusergroups . ')'
        : '';
    $base_where = "enabled='yes' AND ustatus='confirmed' {$ug_sql}";

    $count_row = $db->fetch_field($db->sql_query("SELECT COUNT(email) AS total FROM users WHERE {$base_where}"), 'total');
    $result    = (int)$count_row;

    if ($result === 0) {
        $errormessage = 'No email addresses found in the database!';
        $action       = 'start';
        goto show_start;
    }

    $from   = ($page - 1) * $max_results;

    stdhead(VERSION . ' – SEND');

    echo '<div class="container mt-3"><div class="card p-4">';
    echo '<p class="text-center"><strong>' . $result . '</strong> emails found. '
       . 'Sending <strong>' . $max_results . '</strong> per batch, '
       . 'sleeping <strong>' . $waitbeforeredirect . '</strong> s between batches.</p>';
    echo '<p class="text-center text-muted">Please wait…</p></div></div>';

    $emails = $db->sql_query(
        "SELECT email FROM users WHERE {$base_where} LIMIT {$from}, {$max_results}"
    );

    include_once $rootpath . '/admin/include/staff_languages.php';

    echo '<div class="container mt-3"><div class="list-group">';

    while ($row = $db->fetch_array($emails)) {
        $to   = $row['email'];
        $body = ($adminlang['massmail']['header'] ?? '')
              . '<br><hr><br>' . $message . '<br><hr><br>'
              . ($adminlang['massmail']['footer'] ?? '');

        $ok = my_mail($to, $subject, $body, '', '', '', false, 'html', '');

        echo '<div class="list-group-item d-flex justify-content-between align-items-center">'
           . mm_esc($to)
           . ($ok
               ? '<span class="badge bg-success">Sent</span>'
               : '<span class="badge bg-danger">Error</span>')
           . '</div>';
    }

    echo '</div></div>';

    $total_pages = (int)ceil($result / $max_results);

    if ($page < $total_pages) {
        $next   = $page + 1;
        $jumpto = mm_esc($_this_script_ . '&action=sent&page=' . $next);

        echo '<div class="container mt-3 text-center">
            <div id="waitmessage" class="alert alert-info">
                Please wait <span id="countdown"><strong>' . $waitbeforeredirect . '</strong></span> seconds…
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
                    window.location.href = "' . $jumpto . '";
                } else {
                    el.innerHTML = "<strong>" + sec + "</strong>";
                }
            }, 1000);
        })();
        </script>';

        stdfoot();
        exit;
    }

    echo '<div class="container mt-3"><div class="alert alert-success text-center">
        ✅ Done! Sent to <strong>' . $result . '</strong> addresses.
    </div></div>';

    stdfoot();
    exit;
}

// ─── Start / form ─────────────────────────────────────────────────────────────

show_start:
stdhead(VERSION . ' – START', true, '', '');
show_error();
show_form(build_usergroup_checkboxes());
stdfoot();