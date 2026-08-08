<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

// FIX: было 20000M (20 ГБ) - не помню, чтобы это было осознанным решением;
// для построчной рассылки PM (fetch_array() в цикле, без загрузки всего
// результата разом) такой объём не нужен даже на большую базу. 512M — с
// хорошим запасом, но не рискует утащить память всего Apache-процесса на
// сервере при реальном сбое/утечке.
@ini_set('memory_limit', '512M');
define('SM_VERSION', '0.8 by xam');

require_once INC_PATH . '/datahandler.php';
require_once(INC_PATH . '/class_parser.php');
$parser = new postParser;

$parser_options = array(
    "allow_html" => 0,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
);

$error = '';
$checked = [];
// FIX: прямой доступ к $_POST['message']/['subject'] без проверки бросал бы
// "Undefined array key" при обычном GET-заходе на страницу (до первого сабмита).
$msgtext = trim($_POST['message'] ?? '');
$subject = trim($_POST['subject'] ?? '');

$useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);
$avatar = '<img src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';
	
if (!empty($_POST['previewpost']) && !empty($msgtext))
{
    $prvp = '<table border="0" cellspacing="0" cellpadding="4" class="none" width="100%">
	<tr>
	<td class="thead" colspan="2"><strong><h2>' . $lang->global['buttonpreview'] . '</h2></strong></td>
	</tr>
	<tr><td class="tcat" width="20%" align="center" valign="middle">' . $avatar . '</td><td class="tcat" width="80%" align="left" valign="top">' . $parser->parse_message($msgtext,$parser_options) . '</td>
	</tr></table><br />';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') 
{
    // FIX: CSRF-защита - раньше отсутствовала полностью на форме массовой
    // рассылки PM по целым юзергруппам. Тот же паттерн, что и везде на сайте.
    // Предпросмотр (previewpost) CSRF не требует - он ничего не пишет в БД.
    $csrfOk = !empty($_POST['previewpost']) || verify_post_check($_POST['my_post_key'] ?? '');

    if (!$csrfOk) {
        $error = '
        <div class="container mt-3">
            <div class="alert alert-danger">
                <i class="fas fa-shield-alt me-2"></i>Security check failed. Please refresh the page and try again.
            </div>
        </div>';
    } else {
        $gids = $_POST['gid'] ?? [];
        $sender_id = ($_POST['sender'] ?? '') === 'system' ? 0 : (int)$CURUSER['id'];

        if (empty($msgtext) || empty($subject) || !is_array($gids)) {
            $error = 'Don\'t leave any fields blank.';
        }

        $checked = [];
        if (is_array($gids))
        {
            foreach ($gids as $gid)
            {
                if (is_valid_id($gid))
                {
                    $checked[] = (int)$gid;
                }
            }
        }

        if (empty($error) && empty($_POST['previewpost']))
        {
            require_once INC_PATH . '/functions_pm.php';

            // Собираем placeholders для IN (0, ?, ?, ?)
            $groupids_array = array_merge([0], $checked);
            $placeholders   = implode(',', array_fill(0, count($groupids_array), '?'));

            $query = $db->sql_query_prepared(
                "SELECT id FROM users WHERE usergroup IN ({$placeholders})",
                $groupids_array
            );

            $qcount = 0;
            while ($query && ($dat = $db->fetch_array($query))) {
                $pm = array(
                    'subject' => $db->escape_string($subject),
                    'message' => $db->escape_string($msgtext),
                    'touid' => $dat['id']
                );

                send_pm($pm, $sender_id, true);
                ++$qcount;
            }

            $error = '
            <div class="container mt-3">
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <i class="fas fa-check-circle me-2"></i><strong>Total&nbsp;' . ts_nf($qcount) . ' message(s) has been sent.</strong>
                </div>
            </div>';
        }
    }
}

stdhead('Mass Message to all Staff members and/or Users', false);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --sm-accent: var(--bs-primary, #0d6efd);
        --sm-accent-strong: var(--bs-primary-text-emphasis, #0a58ca);
        --sm-accent-soft: var(--bs-primary-bg-subtle, rgba(13,110,253,.1));
    }
    .sm-masthead {
        padding: 1.6rem 1.75rem;
        margin-bottom: 1.5rem;
        background: var(--bs-body-bg, #fff);
        border: 1px solid var(--bs-border-color, #e9ecef);
        border-radius: .9rem;
    }
    .sm-masthead__eyebrow {
        display: inline-block;
        font-family: 'Oswald', sans-serif;
        font-weight: 600;
        font-size: .72rem;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--sm-accent-strong);
        background: var(--sm-accent-soft);
        border: 1px solid var(--sm-accent);
        border-radius: 999px;
        padding: .3rem .85rem;
        margin-bottom: .7rem;
    }
    .sm-masthead__title {
        font-family: 'Oswald', sans-serif;
        font-weight: 700;
        text-transform: uppercase;
        font-size: clamp(1.3rem, 2.8vw, 1.7rem);
        margin: 0;
        color: var(--bs-emphasis-color, #212529);
    }
    .sm-panel {
        border: 1px solid var(--bs-border-color, #e9ecef) !important;
        border-radius: .9rem !important;
        overflow: hidden;
    }
    .sm-panel .card-header {
        background: var(--bs-tertiary-bg, #f8f9fa) !important;
        color: var(--bs-emphasis-color, #212529) !important;
        border-bottom: 1px solid var(--bs-border-color, #e9ecef);
        border-left: 4px solid var(--sm-accent);
    }
    .sm-panel .card-header h5,
    .sm-panel .card-header legend {
        font-family: 'Oswald', sans-serif;
        font-weight: 600;
        font-size: .95rem;
        margin: 0;
    }
    .sm-form-label {
        font-family: 'Oswald', sans-serif;
        font-weight: 500;
        font-size: .85rem;
        letter-spacing: .01em;
    }
    .group-chip {
        display: flex;
        align-items: center;
        gap: .6rem;
        border: 1px solid var(--bs-border-color, #e9ecef);
        border-radius: .6rem;
        padding: .55rem .8rem;
        transition: border-color .15s ease, background .15s ease;
        cursor: pointer;
        height: 100%;
    }
    .group-chip:hover {
        border-color: var(--sm-accent);
        background: var(--sm-accent-soft);
    }
    .group-chip input:checked ~ .group-chip-label {
        color: var(--sm-accent-strong);
        font-weight: 600;
    }
    .check-all-link {
        font-family: 'Oswald', sans-serif;
        font-size: .78rem;
        font-weight: 600;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: var(--sm-accent-strong);
        text-decoration: none;
    }
    .check-all-link:hover { text-decoration: underline; }

    .bbcode-toolbar .btn {
        border-color: var(--bs-border-color, #dee2e6);
    }
</style>

<div class="sm-masthead">
    <span class="sm-masthead__eyebrow">Admin / Communication</span>
    <h1 class="sm-masthead__title"><i class="fas fa-bullhorn me-2"></i>Mass Message to Staff / Users</h1>
</div>

<?php
if (!empty($error) && empty($_POST['previewpost'])) {
    echo $error;
}

// Prepare usergroup checkboxes
$query = $db->sql_query_prepared("SELECT gid, title, namestyle FROM usergroups");

$sgids = '
<div class="card sm-panel shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="fas fa-users me-2"></i>Select Usergroup(s)</h5>
        <a href="#" class="check-all-link" onclick="checkAll(document.compose);return false;">
            <i class="fas fa-check-double me-1"></i>Check All
        </a>
    </div>
    <div class="card-body">
        <div class="row g-2">';

while ($query && ($gid = $db->fetch_array($query))) {
    $checkedAttr = (!empty($checked) && in_array($gid['gid'], $checked)) ? ' checked="checked"' : '';
    $sgids .= '
            <div class="col-6 col-md-4 col-lg-3">
                <label class="group-chip w-100 mb-0">
                    <input class="form-check-input mt-0" type="checkbox"
                           id="gid_' . $gid['gid'] . '"
                           name="gid[]"
                           value="' . $gid['gid'] . '"' . $checkedAttr . '>
                    <span class="group-chip-label">' . format_name($gid['title'], $gid['gid']) . '</span>
                </label>
            </div>';
}
$sgids .= '
        </div>
    </div>
</div>';

// Sender select box
$senderOptions = '
<div class="card sm-panel shadow-sm mb-3">
    <div class="card-header">
        <h5><i class="fas fa-user-tag me-2"></i>Select Sender</h5>
    </div>
    <div class="card-body">
        <select name="sender" class="form-select w-auto">
            <option value="system"' . (($_POST['sender'] ?? '') === 'system' ? ' selected' : '') . '>Automatic Message By System</option>
            <option value="' . htmlspecialchars($CURUSER['username']) . '"' . (($_POST['sender'] ?? '') === $CURUSER['username'] ? ' selected' : '') . '>' . htmlspecialchars($CURUSER['username']) . '</option>
        </select>
    </div>
</div>';

// The "check all" JS function
echo <<<JS
<script>
function checkAll(form) {
    var checkboxes = form.querySelectorAll('input[type="checkbox"][name="gid[]"]');
    var allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}
</script>
JS;

// Output the form with Bootstrap styles, and your custom JS below
echo '
<form method="post" name="compose" action="' . htmlspecialchars($_this_script_) . '" class="container-md" id="massMessageForm">
<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '') . '">
';

echo $sgids;
echo $senderOptions;

echo '
<div class="card sm-panel shadow-sm mb-3">
    <div class="card-header">
        <h5><i class="fas fa-envelope-open-text me-2"></i>Message</h5>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label for="subject" class="sm-form-label form-label">Subject</label>
            <input type="text" class="form-control" id="subject" name="subject" value="' . htmlspecialchars($subject) . '" required>
        </div>

        <div class="mb-3">
            <label for="message" class="sm-form-label form-label">Message</label>
            <!-- BBCode Toolbar -->
            <div class="mb-2 d-flex flex-wrap gap-1 bbcode-toolbar">
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[b]\', \'[/b]\');" title="Bold (Ctrl+B)"><strong>B</strong></button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[i]\', \'[/i]\');" title="Italic (Ctrl+I)"><em>I</em></button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[u]\', \'[/u]\');" title="Underline"><u>U</u></button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[s]\', \'[/s]\');" title="Strikethrough"><s>S</s></button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[url]\', \'[/url]\');" title="Insert URL">URL</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[img]\', \'[/img]\');" title="Insert Image">IMG</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[quote]\', \'[/quote]\');" title="Quote">Quote</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[code]\', \'[/code]\');" title="Code">Code</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[list]\n[*]\n[/list]\', \'\');" title="Unordered List">List</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[list=1]\n[*]\n[/list]\', \'\');" title="Ordered List">List 1.</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[color=red]\', \'[/color]\');" title="Color"><span style="color:red;">A</span></button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[size=]\', \'[/size]\');" title="Size">Size</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[center]\', \'[/center]\');" title="Center Text">Center</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[spoiler]\', \'[/spoiler]\');" title="Spoiler">Spoiler</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode(\'[video=youtube]\', \'[/video]\');" title="YouTube Video">YouTube</button>
            </div>
            <textarea class="form-control" id="message" name="message" rows="8" required>' . htmlspecialchars($msgtext) . '</textarea>
            <div id="charCount" class="form-text text-end">0 characters</div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="togglePreviewBtn">Show Markdown Preview</button>
        </div>

        <div id="markdownPreview" class="border rounded p-3 mb-3" style="display:none; white-space: pre-wrap; background:var(--bs-tertiary-bg, #f8f9fa); max-height:300px; overflow-y:auto;"></div>

        <div class="d-flex gap-2">
            <button type="submit" name="submit" class="btn btn-primary px-4">
                <i class="fas fa-paper-plane me-2"></i>Send Message
            </button>
            <button type="button" id="previewModalBtn" class="btn btn-outline-secondary">
                <i class="fas fa-eye me-2"></i>Preview
            </button>
        </div>
    </div>
</div>
</form>';

// Bootstrap modal for preview
echo '
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalLabel">Message Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="previewModalBody" style="white-space: pre-wrap; background:#fff;"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
';

// JavaScript for char count, preview toggle and modal preview
echo <<<JS
<script>
document.addEventListener('DOMContentLoaded', () => {
    const messageEl = document.getElementById('message');
    const charCountEl = document.getElementById('charCount');
    const previewToggleBtn = document.getElementById('togglePreviewBtn');
    const markdownPreview = document.getElementById('markdownPreview');
    const previewModalBtn = document.getElementById('previewModalBtn');
    const previewModalBody = document.getElementById('previewModalBody');
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));

    function updateCharCount() {
        charCountEl.textContent = messageEl.value.length + ' characters';
    }

    messageEl.addEventListener('input', () => {
        updateCharCount();
        if (markdownPreview.style.display !== 'none') {
            updateMarkdownPreview();
        }
    });

    updateCharCount();

    function escapeHtml(text) {
        return text.replace(/&/g, "&amp;")
                   .replace(/</g, "&lt;")
                   .replace(/>/g, "&gt;")
                   .replace(/"/g, "&quot;")
                   .replace(/'/g, "&#039;");
    }

    function updateMarkdownPreview() {
        let text = escapeHtml(messageEl.value);
        text = text
            .replace(/\\*\\*(.*?)\\*\\*/g, "<strong>$1</strong>")
            .replace(/\\*(.*?)\\*/g, "<em>$1</em>")
            .replace(/`(.*?)`/g, "<code>$1</code>")
            .replace(/\\n/g, "<br>");
        markdownPreview.innerHTML = text;
    }

    previewToggleBtn.addEventListener('click', () => {
        if (markdownPreview.style.display === 'none') {
            updateMarkdownPreview();
            markdownPreview.style.display = 'block';
            previewToggleBtn.textContent = 'Hide Markdown Preview';
        } else {
            markdownPreview.style.display = 'none';
            previewToggleBtn.textContent = 'Show Markdown Preview';
        }
    });

    previewModalBtn.addEventListener('click', () => {
        const subject = document.getElementById('subject').value.trim();
        const message = messageEl.value.trim();

        if (!subject || !message) {
            alert('Please enter both subject and message to preview.');
            return;
        }

        let previewContent = '<h4>' + escapeHtml(subject) + '</h4><hr>' +
                             '<p>' + escapeHtml(message).replace(/\\n/g, "<br>") + '</p>';

        previewModalBody.innerHTML = previewContent;
        previewModal.show();
    });
});
</script>

<script>
  const textarea = document.getElementById("message");
 
  function insertBBCode(openTag, closeTag) {
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const selectedText = text.substring(start, end);
    
    const before = text.substring(0, start);
    const after = text.substring(end);
    
    const newText = before + openTag + selectedText + closeTag + after;
    textarea.value = newText;
    
    if (selectedText.length === 0) {
      const cursorPos = start + openTag.length;
      textarea.setSelectionRange(cursorPos, cursorPos);
    } else {
      textarea.setSelectionRange(start, end + openTag.length + closeTag.length);
    }
    
    textarea.focus();
    textarea.dispatchEvent(new Event("input"));
  }
</script>
JS;

stdfoot();
?>