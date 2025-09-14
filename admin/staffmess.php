<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

@ini_set('memory_limit', '20000M');
define('SM_VERSION', '0.8 by xam');
define("IN_MYBB", 1);

// Include our base data handler class
require_once INC_PATH . '/datahandler.php';

require_once(INC_PATH . '/class_parser.php');
$parser = new postParser;

$parser_options = array(
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
);

$error = '';
$msgtext = trim ($_POST['message']);
$subject = trim ($_POST['subject']);


$useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);
$avatar = '<img src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';
	
if (($_POST['previewpost'] AND !empty ($msgtext)))
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
    $gids = $_POST['gid'] ?? [];
    $sender_id = ($_POST['sender'] ?? '') === 'system' ? 0 : (int)$CURUSER['id'];
    $dt = $db->sqlesc(get_date_time());
    if (empty($msgtext) || empty($subject) || !is_array($gids)) {
        $error = 'Don\'t leave any fields blank.';
    }

    $groupids = '';
    $checked = [];
    if (is_array($gids)) 
	{
        foreach ($gids as $gid) 
		{
            if (is_valid_id($gid)) 
			{
                $groupids .= ', ' . $gid;
                $checked[] = $gid;
            }
        }
    }

    if (empty($error) && empty($_POST['previewpost'])) 
	{
        require_once INC_PATH . '/functions_pm.php';

        $query = $db->simple_select("users", "id", "usergroup IN (0{$groupids})");

        $qcount = 0;
        while ($dat = $db->fetch_array($query)) {
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
            <div class="alert alert-primary alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <strong>Total&nbsp;' . ts_nf($qcount) . ' message(s) has been sent.</strong>
            </div>
        </div>';
    }
}

stdhead('Mass Message to all Staff members and/or Users', false);

if (!empty($error) && empty($_POST['previewpost'])) {
    echo '
    <table border="0" cellspacing="0" cellpadding="4" class="" width="100%">
    <tr></tr>
    <tr><td>' . $error . '</td></tr>
    </table><br />';
}

// Prepare usergroup checkboxes
$query = $db->simple_select("usergroups", "gid, title, namestyle");

$count = 1;
$sgids = '
<fieldset>
    <legend>Select Usergroup(s)</legend>
    <table border="0" cellspacing="0" cellpadding="2" width="100%"><tr>';
while ($gid = $db->fetch_array($query)) {
    if ($count % 5 == 1 && $count > 1) {
        $sgids .= '</tr><tr>';
    }

    $checkedAttr = (!empty($checked) && in_array($gid['gid'], $checked)) ? ' checked="checked"' : '';
    $sgids .= '
    
    
<td style="border:0">
  <div class="form-check form-switch d-inline-block">
    <input class="form-check-input" type="checkbox"
           id="gid_' . $gid['gid'] . '"
           name="gid[]"
           value="' . $gid['gid'] . '" ' . $checkedAttr . '>
    <label class="form-check-label" for="gid_' . $gid['gid'] . '"></label>
  </div>
</td>



    <td style="border: 0">' . get_user_color($gid['title'], $gid['namestyle']) . '</td>';
    ++$count;
}
$sgids .= '
<td style="border: 0"></td>
<td style="border: 0"><a href="#" onclick="checkAll(document.compose);return false;"><font color="blue" size="1">check all</font></a></td>
</tr></table>
</fieldset>
<br />';

// Sender select box
$senderOptions = '
<fieldset>
    <legend>Select Sender</legend>
    <table border="0" cellspacing="0" cellpadding="2" width="100%">
        <tr>
            <td>
                <select name="sender" class="form-select form-select-sm border pe-5 w-auto">
                    <option value="system"' . (($_POST['sender'] ?? '') === 'system' ? ' selected' : '') . '>Automatic Message By System</option>
                    <option value="' . htmlspecialchars($CURUSER['username']) . '"' . (($_POST['sender'] ?? '') === $CURUSER['username'] ? ' selected' : '') . '>' . htmlspecialchars($CURUSER['username']) . '</option>
                </select>
            </td>
        </tr>
    </table>
</fieldset>
<br />';

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
<div class="card border-0 mb-4">
    <div class="card-header text-19 fw-bold rounded-bottom">
        Mass Message to all Staff members and/or Users
    </div>
    <div class="card-body">
';

echo $sgids;
echo $senderOptions;

echo '
<div class="mb-3">
    <label for="subject" class="form-label">Subject</label>
    <input type="text" class="form-control" id="subject" name="subject" value="' . htmlspecialchars($subject) . '" required>
</div>';

echo '
<div class="mb-3">
    <label for="message" class="form-label">Message</label>
	
	
	
	<!-- BBCode Toolbar -->
    <div class="mb-2 d-flex flex-wrap gap-1">
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
</div>';

echo '<div id="markdownPreview" class="border p-3 mb-3" style="display:none; white-space: pre-wrap; background:#f8f9fa; max-height:300px; overflow-y:auto;"></div>';

echo '
<div class="d-flex gap-2">
    <button type="submit" name="submit" class="btn btn-primary">Send Message</button>
    <button type="button" id="previewModalBtn" class="btn btn-secondary">Preview</button>
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

    // Simple markdown parsing: bold **text**, italic *text*, code `text`, line breaks
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
 
  // Insert BBCode tags at the cursor position or wrap selection
  function insertBBCode(openTag, closeTag) {
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const selectedText = text.substring(start, end);
    
    const before = text.substring(0, start);
    const after = text.substring(end);
    
    // Wrap selection or insert tags at cursor
    const newText = before + openTag + selectedText + closeTag + after;
    textarea.value = newText;
    
    // Reset cursor position after inserted tags
    if (selectedText.length === 0) {
      const cursorPos = start + openTag.length;
      textarea.setSelectionRange(cursorPos, cursorPos);
    } else {
      textarea.setSelectionRange(start, end + openTag.length + closeTag.length);
    }
    
    textarea.focus();

    // Trigger input event to update character count
    textarea.dispatchEvent(new Event("input"));
  }
</script>







JS;

stdfoot();
?>
