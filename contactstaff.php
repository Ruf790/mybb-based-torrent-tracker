<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

require_once 'global.php';

require_once 'cache/smilies.php';


require_once(INC_PATH.'/class_parser.php');
$parser = new postParser;

$parser_options = array(
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
);

gzip();
maxsysop();
define ('STF_VERSION', '0.6');

$lang->load('contactstaff');

$query = $db->sql_query('SELECT added FROM staffmessages WHERE sender = ' . $db->sqlesc($CURUSER['id']) . ' ORDER by added DESC LIMIT 1');
if (0 < $db->num_rows($query)) {
    $Result = mysqli_fetch_assoc($query);
    $last_staffmsg = $Result["added"];
    flood_check($lang->contactstaff['floodcomment'], $last_staffmsg);
}

$msgtext = trim($_POST['msgtext'] ?? '');
$subject = trim($_POST['subject'] ?? '');




if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) 
{
    header('Content-Type: application/json');

    $msgtext = trim($_POST['msgtext'] ?? '');
    $subject = trim($_POST['subject'] ?? '');

    if (empty($msgtext) || empty($subject)) 
	{
        echo json_encode(["success" => false, "message" => $lang->global['dontleavefieldsblank']]);
        exit;
    }

    $activationarray = array(
        "sender" => (int)$CURUSER['id'],
        "added" => TIMENOW,
        "msg" => $db->escape_string($msgtext),
        "subject" => $db->escape_string($subject)
    );

    $db->insert_query("staffmessages", $activationarray);

    echo json_encode(["success" => true, "message" => $lang->global['msgsend']]);
    exit;
}




if ((($_GET['subject'] ?? '') == 'invalid_link') && (($_GET['link'] ?? '') && substr($_GET['link'], 0, 7) == 'http://')) {
    $link = htmlspecialchars_uni($_GET['link']);
    $link = str_replace('http://referhide.com/?g=', '', $link);
    $subject = sprintf($lang->contactstaff['invalidlink'], $link);
}

stdhead($lang->contactstaff['contactstaff'], false);

echo '<div class="container mt-3">
        <div class="red_alert mb-3" role="alert">' . $lang->contactstaff['info'] . '</div>
      </div>';

$returnto = isset($_GET['returnto']) ? fix_url($_GET['returnto']) : fix_url($_SERVER['HTTP_REFERER']);
?>



<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
  <div id="formToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="formToastBody">
        <!-- Message will go here -->
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>







<div class="container mt-3">
  <form id="staffForm" method="post" action="<?= htmlspecialchars($_SERVER['SCRIPT_NAME']); ?>">
    <input type="hidden" name="returnto" value="<?= htmlspecialchars($returnto); ?>">

    <div class="mb-3">
      <label for="subject" class="form-label"><?= $lang->contactstaff['subject']; ?></label>
      <input type="text" class="form-control" id="subject" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required>
    </div>

    <label class="form-label"><?= $lang->contactstaff['message']; ?></label>

    <script>
      const smilies = <?= json_encode($smilies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/bbcode.css">
    <script src="<?= $BASEURL ?>/scripts/bbcode_tools.js"></script>

    <div class="mb-2 d-flex flex-wrap gap-1">
      <!-- BBCode buttons -->
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[b]', '[/b]', 'staffMessage')"><b>B</b></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[i]', '[/i]', 'staffMessage')"><i>I</i></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[u]', '[/u]', 'staffMessage')"><u>U</u></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[s]', '[/s]', 'staffMessage')">S</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[url]', '[/url]', 'staffMessage')">URL</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[img]', '[/img]', 'staffMessage')">IMG</button>

      <div class="btn-group position-relative">
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle bbcode-color-btn" data-textarea="staffMessage">🎨 Color</button>
        <div class="color-palette d-none"></div>
      </div>

      <div class="btn-group position-relative">
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" id="smileyBtn5">😊</button>
        <div class="smiley-panel d-none border p-2 bg-white shadow-sm position-absolute" id="smileyPanel5" style="z-index:1000;"></div>
      </div>

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[size=14]', '[/size]', 'staffMessage')">Size</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[font=Arial]', '[/font]', 'staffMessage')">Font</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[quote]', '[/quote]', 'staffMessage')">Quote</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[code]', '[/code]', 'staffMessage')">Code</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[list]\\n[*]Item 1\\n[*]Item 2\\n[/list]', '', 'staffMessage')">List</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[spoiler]', '[/spoiler]', 'staffMessage')">Spoiler</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[video=youtube]', '[/video]', 'staffMessage')">YouTube</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="togglePreviewBtn5">Preview</button>
    </div>

    <div class="mb-3">
      <textarea class="form-control" id="staffMessage" name="msgtext" rows="8" maxlength="1000" placeholder="Write your message here..."><?= htmlspecialchars($_POST['msgtext'] ?? '') ?></textarea>
      <div id="charCount5" class="form-text text-end">0 / 1000</div>
    </div>

    <button type="submit" name="submit" class="btn btn-primary"><?= $lang->contactstaff['sendmessage']; ?></button>
  </form>

 
</div>











<script>
document.getElementById('staffForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);
    formData.append('ajax', '1');

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            form.reset();
        }
    })
    .catch(() => {
        showToast("Submission failed. Please try again.", 'danger');
    });
});

function showToast(message, type = 'success') {
    const toastEl = document.getElementById('formToast');
    const toastBody = document.getElementById('formToastBody');

    toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
    toastBody.textContent = message;

    const toast = new bootstrap.Toast(toastEl);
    toast.show();
}
</script>





<?php stdfoot(); ?>
