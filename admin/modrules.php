<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/***********************************************/

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$lang->load('modrules');

require_once(INC_PATH.'/class_parser.php');
$parser = new postParser;

$parser_options = [
    "allow_html"      => 1,
    "allow_mycode"    => 1,
    "allow_smilies"   => 1,
    "allow_imgcode"   => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

// === DELETE RULE ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['do'] ?? '') === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $db->delete_query('rules', "id='{$id}'");
    }
}

// === SAVE (UPDATE) RULE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'save') {
    $id    = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $text  = trim($_POST['text'] ?? '');

    $ugs = '[0]';
    if (!empty($_POST['usergroups']) && is_array($_POST['usergroups'])) {
        $valid_groups = [];
        foreach ($_POST['usergroups'] as $ug) {
            if (is_valid_id($ug)) {
                $valid_groups[] = '[' . intval($ug) . ']';
            }
        }
        if ($valid_groups) {
            $ugs = implode('', $valid_groups);
        }
    }

    if ($id > 0) {
        $update_data = [
            "title"      => $db->escape_string($title),
            "text"       => $db->escape_string($text),
            "usergroups" => $db->escape_string($ugs)
        ];
        $db->update_query('rules', $update_data, "id='{$id}'");
    }
}

// === NEW RULE ===
$error = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'new') {
    $title = trim($_POST['title'] ?? '');
    $text  = trim($_POST['text'] ?? '');

    $ugs = '[0]';
    if (!empty($_POST['usergroups']) && is_array($_POST['usergroups'])) {
        $valid_groups = [];
        foreach ($_POST['usergroups'] as $ug) {
            if (is_valid_id($ug)) {
                $valid_groups[] = '[' . intval($ug) . ']';
            }
        }
        if ($valid_groups) {
            $ugs = implode('', $valid_groups);
        }
    }

    if ($title === '' || $text === '') {
        $error[] = $lang->modrules['error'];
    } else {
        $insert_data = [
            "title"      => $db->escape_string($title),
            "text"       => $db->escape_string($text),
            "usergroups" => $db->escape_string($ugs)
        ];
        $db->insert_query('rules', $insert_data);
    }
}

// === USERGROUPS LIST ===
$ugarray = [];
$query2 = $db->simple_select('usergroups', 'gid, title, namestyle', "isbannedgroup != '1'");
while ($gid = $db->fetch_array($query2)) {
    $ugarray[] = [
        'gid'       => $gid['gid'],
        'namestyle' => get_user_color($gid['title'], $gid['namestyle'])
    ];
}

// === HEADER ===
stdhead($lang->modrules['title']);

// === NEW RULE FORM ===
?>
<div id="new_rule" style="display: <?= !empty($error) ? 'inline' : 'none' ?>;">
<form method="POST" action="<?= $_this_script_ ?>&do=new">
<input type="hidden" name="do" value="new" />

<div class="container-md">
  <div class="card border-0 mb-4">
    <div class="card-header rounded-bottom text-19 fw-bold">
      <?= $lang->modrules['new'] ?>
    </div>
  </div>
</div>

<div class="container mt-3">
<div class="card p-3">
    <?= !empty($error) ? '<div class="alert alert-danger">'.htmlspecialchars_uni($error[0]).'</div>' : '' ?>

    <div class="mb-3">
      <label class="form-label"><?= $lang->modrules['title2'] ?></label>
      <input type="text" class="form-control" name="title" value="<?= htmlspecialchars_uni($_POST['title'] ?? '') ?>" />
    </div>

    <div class="mb-3">
      <label class="form-label"><?= $lang->modrules['title3'] ?></label>
      <textarea name="text" class="form-control" rows="6"><?= htmlspecialchars_uni($_POST['text'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label"><?= $lang->modrules['title4'] ?></label>
      <div class="row">
      <?php foreach ($ugarray as $g): ?>
        <div class="col-2 form-check">
          <input class="form-check-input" type="checkbox" name="usergroups[]" value="<?= $g['gid'] ?>" />
          <label class="form-check-label"><?= $g['namestyle'] ?></label>
        </div>
      <?php endforeach; ?>
      </div>
    </div>

    <button type="submit" class="btn btn-primary"><?= $lang->modrules['save'] ?></button>
</div>
</div>
</form>
</div>

<script>
function create_new_rule() {
  document.getElementById("new_rule_button").style.display="none";
  document.getElementById("new_rule").style.display="inline";
}
</script>
<span style="float: right; margin-bottom: 5px;" id="new_rule_button">
  <input type="button" onclick="create_new_rule(); return false;" value="<?= $lang->modrules['new'] ?>">
</span>

<?php
// === EXISTING RULES LIST ===
$query = $db->simple_select('rules', '*', '', ['order_by' => 'id']);
if ($db->num_rows($query) > 0):
?>
<div class="container-md">
  <div class="card border-0 mb-4">
    <div class="card-header rounded-bottom text-19 fw-bold">
      <?= $lang->modrules['title'] ?>
    </div>
  </div>
</div>

<?php while ($rule = $db->fetch_array($query)): ?>
<form method="POST" action="<?= $_this_script_ ?>&do=save&id=<?= $rule['id'] ?>#title_<?= $rule['id'] ?>">
<input type="hidden" name="id" value="<?= $rule['id'] ?>" />
<input type="hidden" name="do" value="save" />

<div class="container mt-3">
  <div class="card">
    <div class="card-header text-19 fw-bold">
      <span id="title_<?= $rule['id'] ?>"><?= $parser->parse_message($rule['title'], $parser_options) ?></span>
      <span style="display:none;" id="inputtitle_<?= $rule['id'] ?>">
        <input type="text" class="form-control" name="title" value="<?= htmlspecialchars_uni($rule['title']) ?>" />
      </span>
    </div>

    <div class="card-body">
      <div style="float:right;">
        <a href="<?= $_this_script_ ?>&do=edit&id=<?= $rule['id'] ?>" onclick="edit_rule(<?= $rule['id'] ?>); return false;">
          <i class="fa-solid fa-pen-to-square fa-xl text-primary" title="<?= $lang->modrules['edit'] ?>"></i>
        </a>
        <a href="<?= $_this_script_ ?>&do=delete&id=<?= $rule['id'] ?>" onclick="confirm_rule_delete(<?= $rule['id'] ?>); return false;">
          <i class="fa-solid fa-trash-can fa-xl text-danger" title="<?= $lang->modrules['delete'] ?>"></i>
        </a>
      </div>

      <span id="text_<?= $rule['id'] ?>"><?= $parser->parse_message($rule['text'], $parser_options) ?></span>
      <span style="display:none;" id="textareaipnut_<?= $rule['id'] ?>">
        <textarea name="text" class="form-control" rows="6"><?= htmlspecialchars_uni($rule['text']) ?></textarea>
        <br />

        <fieldset>
          <legend><?= $lang->modrules['title4'] ?></legend>
          <div class="row">
            <?php foreach ($ugarray as $g): ?>
            <div class="col-2 form-check">
              <input class="form-check-input" type="checkbox" name="usergroups[]" value="<?= $g['gid'] ?>" <?= preg_match('#\['.$g['gid'].'\]#', $rule['usergroups']) ? 'checked' : '' ?> />
              <label class="form-check-label"><?= $g['namestyle'] ?></label>
            </div>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <br />
        <button type="submit" class="btn btn-primary"><?= $lang->modrules['save'] ?></button>
        <button type="reset" class="btn btn-secondary"><?= $lang->modrules['reset'] ?></button>
      </span>
    </div>
  </div>
</div>
</form>
<?php endwhile; endif; ?>

<script>
function confirm_rule_delete(RuleID) {
  if (confirm("<?= $lang->modrules['confirm'] ?>")) {
    window.location = "<?= $_this_script_ ?>&do=delete&id=" + RuleID;
  }
}
function edit_rule(RuleID) {
  const title = document.getElementById("title_"+RuleID);
  const inputTitle = document.getElementById("inputtitle_"+RuleID);
  const text = document.getElementById("text_"+RuleID);
  const textarea = document.getElementById("textareaipnut_"+RuleID);

  if (inputTitle.style.display === "none") {
    title.style.display="none"; inputTitle.style.display="inline";
    text.style.display="none"; textarea.style.display="inline";
  } else {
    title.style.display="inline"; inputTitle.style.display="none";
    text.style.display="inline"; textarea.style.display="none";
  }
}
</script>
<?php
stdfoot();
