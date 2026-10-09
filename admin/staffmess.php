<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-light border m-3"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

$lang->load('staffmess');

@ini_set('memory_limit', '512M');
define('SM_VERSION', '0.8 by xam');
const SM_ASSET_VER = 1; // поднимать при изменении staffmess.css / staffmess.js

require_once INC_PATH . '/datahandler.php';
require_once(INC_PATH . '/class_parser.php');
require_once TSDIR .'/cache/smilies.php';

require_once INC_PATH . '/editor.php';

/**
 * Fill {1}, {2}… placeholders. $lang->load() turns {N} into %N$s,
 * so both forms are replaced (strtr, not sprintf: a literal % is safe).
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

$parser = new postParser;

$parser_options = array(
    "allow_html" => 0,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
);

$error   = '';   // не пусто = отправка заблокирована
$flash   = null; // ['type' => success|danger|warning, 'icon' => ..., 'text' => ...]
$checked = [];

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
    $csrfOk = !empty($_POST['previewpost']) || verify_post_check($_POST['my_post_key'] ?? '');

    if (!$csrfOk) {
        $error = 'csrf';
        $flash = ['type' => 'danger', 'icon' => 'fa-shield-halved', 'text' => $lang->staffmess['flash_csrf_failed']];
    } else {
        $gids = $_POST['gid'] ?? [];
        $sender_id = ($_POST['sender'] ?? '') === 'system' ? 0 : (int)$CURUSER['id'];

        if (empty($msgtext) || empty($subject) || !is_array($gids)) {
            $error = 'blank';
            $flash = ['type' => 'warning', 'icon' => 'fa-triangle-exclamation', 'text' => $lang->staffmess['flash_fields_blank']];
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

            $flash = ['type' => 'success', 'icon' => 'fa-circle-check', 'text' => ags_fmt($lang->staffmess['flash_sent'], ts_nf($qcount))];
        }
    }
}

// ---------------------------------------------------------------------
// Usergroups + user counts (для чипов и KPI)
// ---------------------------------------------------------------------
$group_counts = [];
$res_counts = $db->sql_query_prepared("SELECT usergroup, COUNT(*) AS cnt FROM users GROUP BY usergroup");
while ($res_counts && ($row = $db->fetch_array($res_counts))) {
    $group_counts[(int)$row['usergroup']] = (int)$row['cnt'];
}

$groups = [];
$query = $db->sql_query_prepared("SELECT gid, title, namestyle FROM usergroups");
while ($query && ($row = $db->fetch_array($query))) {
    $groups[] = $row;
}

$staff_gids = [
    defined('UC_MODERATOR')     ? (int)UC_MODERATOR     : 6,
    defined('UC_ADMINISTRATOR') ? (int)UC_ADMINISTRATOR : 7,
    defined('UC_SYSOP')         ? (int)UC_SYSOP         : 8,
];

$stat_users  = array_sum($group_counts);
$stat_staff  = 0;
foreach ($staff_gids as $sg) {
    $stat_staff += $group_counts[$sg] ?? 0;
}
$stat_groups = count($groups);
$stat_recipients = 0;
foreach ($checked as $cg) {
    $stat_recipients += $group_counts[$cg] ?? 0;
}

// JS-строки: ключи js_* без префикса
$agsJsLang = [];
foreach ($lang->staffmess as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $agsJsLang[substr((string)$k, 3)] = $v;
    }
}

$L = static fn(string $key): string => htmlspecialchars($lang->staffmess[$key], ENT_QUOTES);

stdhead($lang->staffmess['page_title'], false);

echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/staffmess.css?ver=' . SM_ASSET_VER . '">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">';
echo '<script>const AGS_LANG = ' . json_encode($agsJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
echo '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js" defer></script>';
echo '<script src="' . $BASEURL . '/admin/scripts/staffmess.js?ver=' . SM_ASSET_VER . '" defer></script>';

// Общий BBCode-редактор для поля #message (та же логика, что и везде на сайте)
$editorParts = insert_bbcode_editor($smilies, $BASEURL, 'message');
?>

<div class="container mt-3 py-4 smm-page">

    <!-- Header -->
    <div class="smm-card smm-head mb-3">
        <div class="smm-head__icon"><i class="fa-solid fa-bullhorn"></i></div>
        <div class="flex-grow-1">
            <h1 class="smm-title"><?= $L('sec_title') ?></h1>
            <p><?= $L('sec_subtitle') ?></p>
        </div>
        <span class="smm-badge smm-soft-primary d-none d-md-inline-flex"><i class="fa-solid fa-tower-broadcast"></i><?= $L('badge_section') ?></span>
    </div>

    <?php if ($flash !== null && empty($_POST['previewpost'])): ?>
    <div class="alert alert-dismissible fade show smm-alert smm-soft-<?= $flash['type'] ?>" role="alert">
        <i class="fa-solid <?= $flash['icon'] ?>"></i>
        <span><?= htmlspecialchars($flash['text'], ENT_QUOTES) ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= $L('lbl_close') ?>"></button>
    </div>
    <?php endif; ?>

    <!-- KPI tiles -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="smm-card smm-kpi">
                <div class="smm-kpi__icon smm-soft-primary"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="smm-kpi__value"><?= ts_nf($stat_users) ?></div>
                    <div class="smm-kpi__label"><?= $L('kpi_users') ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="smm-card smm-kpi">
                <div class="smm-kpi__icon smm-soft-danger"><i class="fa-solid fa-user-shield"></i></div>
                <div>
                    <div class="smm-kpi__value"><?= ts_nf($stat_staff) ?></div>
                    <div class="smm-kpi__label"><?= $L('kpi_staff') ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="smm-card smm-kpi">
                <div class="smm-kpi__icon smm-soft-info"><i class="fa-solid fa-layer-group"></i></div>
                <div>
                    <div class="smm-kpi__value"><?= ts_nf($stat_groups) ?></div>
                    <div class="smm-kpi__label"><?= $L('kpi_groups') ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="smm-card smm-kpi">
                <div class="smm-kpi__icon smm-soft-success"><i class="fa-solid fa-paper-plane"></i></div>
                <div>
                    <div class="smm-kpi__value" id="smmRecipients"><?= ts_nf($stat_recipients) ?></div>
                    <div class="smm-kpi__label"><?= $L('kpi_recipients') ?></div>
                </div>
            </div>
        </div>
    </div>

    <form method="post" name="compose" action="<?= htmlspecialchars($_this_script_) ?>" id="massMessageForm">
        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code ?? '') ?>">

        <div class="row g-3">

            <!-- Recipients & sender -->
            <div class="col-lg-4 order-1 order-lg-2">
                <div class="smm-card mb-3">
                    <div class="smm-card__head">
                        <h5><i class="fa-solid fa-users"></i><?= $L('sec_groups') ?></h5>
                        <a href="#" class="smm-link check-all-link" onclick="checkAll(document.compose);return false;">
                            <i class="fa-solid fa-check-double me-1"></i><?= $L('lbl_check_all') ?>
                        </a>
                    </div>
                    <div class="smm-card__body">
                        <div class="d-grid gap-2">
                        <?php foreach ($groups as $g):
                            $gidInt  = (int)$g['gid'];
                            $gCount  = $group_counts[$gidInt] ?? 0;
                            $isOn    = in_array($gidInt, $checked, true); ?>
                            <label class="smm-group group-chip" for="gid_<?= $gidInt ?>" title="<?= htmlspecialchars(ags_fmt($lang->staffmess['tip_group_users'], ts_nf($gCount)), ENT_QUOTES) ?>">
                                <input class="form-check-input mt-0" type="checkbox"
                                       id="gid_<?= $gidInt ?>"
                                       name="gid[]"
                                       value="<?= $gidInt ?>"
                                       data-count="<?= $gCount ?>"<?= $isOn ? ' checked="checked"' : '' ?>>
                                <span class="smm-group__name group-chip-label"><?= format_name($g['title'], $g['gid']) ?></span>
                                <span class="smm-count"><?= ts_nf($gCount) ?></span>
                            </label>
                        <?php endforeach; ?>
                        </div>
                        <div class="smm-hint mt-3"><i class="fa-solid fa-circle-info me-1"></i><?= $L('hint_groups') ?></div>
                    </div>
                </div>

                <div class="smm-card">
                    <div class="smm-card__head">
                        <h5><i class="fa-solid fa-user-tag"></i><?= $L('sec_sender') ?></h5>
                    </div>
                    <div class="smm-card__body">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-signature"></i></span>
                            <select name="sender" class="form-select" aria-label="<?= $L('sec_sender') ?>">
                                <option value="system"<?= ($_POST['sender'] ?? '') === 'system' ? ' selected' : '' ?>><?= $L('opt_sender_system') ?></option>
                                <option value="<?= htmlspecialchars($CURUSER['username']) ?>"<?= ($_POST['sender'] ?? '') === $CURUSER['username'] ? ' selected' : '' ?>><?= htmlspecialchars($CURUSER['username']) ?></option>
                            </select>
                        </div>
                        <div class="smm-hint mt-2"><i class="fa-solid fa-circle-info me-1"></i><?= $L('hint_sender') ?></div>
                    </div>
                </div>
            </div>

            <!-- Message -->
            <div class="col-lg-8 order-2 order-lg-1">
                <div class="smm-card h-100">
                    <div class="smm-card__head">
                        <h5><i class="fa-solid fa-envelope-open-text"></i><?= $L('sec_message') ?></h5>
                    </div>
                    <div class="smm-card__body">
                        <div class="mb-3">
                            <label for="subject" class="form-label"><i class="fa-solid fa-heading me-1"></i><?= $L('lbl_subject') ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-pen"></i></span>
                                <input type="text" class="form-control" id="subject" name="subject"
                                       value="<?= htmlspecialchars($subject) ?>"
                                       placeholder="<?= $L('ph_subject') ?>" required>
                            </div>
                        </div>

                        <div>
                            <label for="message" class="form-label"><i class="fa-solid fa-align-left me-1"></i><?= $L('lbl_message') ?></label>
                            <?= $editorParts['toolbar'] ?>
                            <textarea class="form-control" id="message" name="message" rows="10" required><?= htmlspecialchars($msgtext) ?></textarea>
                            <div class="d-flex justify-content-between flex-wrap gap-2 mt-2">
                                <span class="smm-hint"><i class="fa-solid fa-code me-1"></i><?= $L('hint_bbcode') ?></span>
                                <span id="charCount" class="form-text smm-hint m-0"><?= htmlspecialchars(ags_fmt($lang->staffmess['lbl_char_count'], 0)) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky action bar -->
        <div class="smm-card smm-actionbar mt-3">
            <span class="smm-meta">
                <i class="fa-solid fa-user-group me-1"></i><?= $L('lbl_recipients') ?>
                <b id="smmRecipientsBar"><?= ts_nf($stat_recipients) ?></b>
            </span>
            <button type="submit" name="submit" class="btn btn-primary smm-pill px-4">
                <i class="fa-solid fa-paper-plane me-2"></i><?= $L('btn_send') ?>
            </button>
        </div>

        <div id="fileIdsContainer"></div>
    </form>
</div>

<?php
// Модалки редактора (картинка/видео) - ВНЕ формы, как и требует сама функция
echo $editorParts['modal'];

stdfoot();
