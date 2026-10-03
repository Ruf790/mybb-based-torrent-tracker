<?php

declare(strict_types=1);

/**
 * User Groups Management — refactored
 * PHP 8.1+
 *
*/

// Array of usergroup permission fields and their default values.
$usergroup_permissions = [
    'isbannedgroup'        => 0, 'canview'              => 1,
    'canviewthreads'       => 1, 'candlattachments'     => 1,
    'canviewboardclosed'   => 1, 'canpostthreads'       => 1,
    'canpostreplys'        => 1, 'canpostattachments'   => 1,
    'modposts'             => 0,
    'modthreads'           => 0, 'modattachments'       => 0,
    'mod_edit_posts'       => 0, 'caneditposts'         => 1,
    'candeletetorrent'     => 1, 'candeleteposts'       => 1,
    'candeletethreads'     => 1, 'caneditattachments'   => 1,
    'canpostpolls'         => 1,
    'canvotepolls'         => 1, 'canundovotes'         => 0,
    'canusepms'            => 1, 'cansendpms'           => 1,
    'cantrackpms'          => 1, 'candenypmreceipts'    => 1,
    'pmquota'              => 100,'maxpmrecipients'     => 5,
    'cansendemail'         => 1, 'cansendemailoverride' => 0,
    'canviewwolinvis'      => 0, 'cansettingspanel'     => 0,
    'issupermod'           => 0, 'cansearch'            => 1, 
	'showforumteam'        => 0, 'attachquota'          => 5000,
	'canstaffpanel'      => 0,   'canoverridepm'        => 0, 
	'max_screenshots' => 3,
];

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger"><strong>Error!</strong> Direct initialization is not allowed.</div>');
}

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}



$plugins->run_hooks('admin_user_groups_begin');
// ═══════════════════════════════════════════════════════════
// SHARED HELPERS
// ═══════════════════════════════════════════════════════════

function ug_head_assets(): void
{
    global $BASEURL;
    $v = '20260926'; // cache-busting: менять при правке usergroups.css / usergroups.js
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/userclass.css">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/usergroups.css?v=' . $v . '">';
    echo '<script src="' . $BASEURL . '/admin/scripts/usergroups.js?v=' . $v . '" defer></script>';
}

function ug_breadcrumb(array $items): void
{
    echo '<nav class="ug-crumbs" aria-label="breadcrumb">';
    echo '<a href="index.php?module=home"><i class="fa-solid fa-house"></i></a><i class="fa-solid fa-chevron-right sep"></i>';
    $last = array_key_last($items);
    echo '<a href="index.php?act=groups"><i class="fa-solid fa-users me-1"></i>User Groups</a>';
    foreach ($items as $label => $url) {
        echo '<i class="fa-solid fa-chevron-right sep"></i>';
        echo $url ? '<a href="' . $url . '">' . $label . '</a>' : '<span class="cur">' . $label . '</span>';
    }
    echo '</nav>';
}

function ug_errors(array $errors): void
{
    if (!$errors) return;
    echo '<div class="alert alert-danger rounded-4 d-flex gap-3"><i class="fa-solid fa-triangle-exclamation mt-1"></i><div><div class="fw-semibold mb-1">Please correct the following errors:</div><ul class="mb-0 ps-3">';
    foreach ($errors as $e) echo '<li>' . htmlspecialchars_uni($e) . '</li>';
    echo '</ul></div></div>';
}

function ug_hero(string $icon, string $cls, string $title, string $sub, string $right = ''): void
{
    echo '<div class="ug-card mb-3"><div class="ug-head">'
       . '<span class="ug-head-icon ' . $cls . '"><i class="fa-solid ' . $icon . '"></i></span>'
       . '<div style="min-width:0"><h1 class="ug-title">' . $title . '</h1><div class="ug-sub">' . $sub . '</div></div>'
       . ($right !== '' ? '<div class="ms-auto d-flex flex-wrap gap-2">' . $right . '</div>' : '')
       . '</div></div>';
}

function ug_sec_open(string $icon, string $cls, string $title): void
{
    echo '<div class="ug-sec"><div class="ug-sec-head"><span class="ug-sec-icon ' . $cls . '"><i class="fa-solid ' . $icon . '"></i></span>' . $title . '</div>';
}

function ug_sec_close(): void
{
    echo '</div>';
}

/** Картинка группы: путь из настройки → <img>. Раньше в список выводился сам текст пути. */
function ug_group_image(string $image, string $fallback_icon): string
{
    $image = trim($image);
    if ($image === '') {
        return '<span class="ug-gicon"><i class="fa-solid ' . $fallback_icon . '"></i></span>';
    }
    // На трекере в image обычно хранится HTML-иконка (<i class="…">), а не путь
    if (str_starts_with($image, '<')) {
        return '<span class="ug-gicon">' . $image . '</span>';
    }
    $src = str_replace('{lang}', 'english', $image);
    if (!preg_match('#^(https?:)?//#i', $src) && !str_starts_with($src, '/')) {
        $src = '../' . ltrim($src, './');
    }
    return '<span class="ug-gicon"><img src="' . htmlspecialchars_uni($src) . '" alt="" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement(\'i\'),{className:\'fa-solid ' . $fallback_icon . '\'}))"></span>';
}

// ── Прямой HTML взамен DefaultForm ──────────────────────────

function ug_form_open(string $action, string $id = ''): void
{
    global $mybb;
    echo '<form action="' . $action . '" method="post"' . ($id !== '' ? ' id="' . $id . '"' : '') . '>' . "\n";
    echo '<input type="hidden" name="my_post_key" value="' . htmlspecialchars_uni($mybb->post_code) . '" />' . "\n";
}

function ug_form_close(): void
{
    echo '</form>';
}

function ug_text_box(string $name, string $value = '', array $options = []): string
{
    $input = '<input type="text" name="' . $name . '" value="' . htmlspecialchars_uni($value) . '"';
    $input .= ' class="form-control ' . ($options['class'] ?? '') . '"';
    if (isset($options['id']))          $input .= ' id="' . $options['id'] . '"';
    if (isset($options['style']))       $input .= ' style="' . $options['style'] . '"';
    if (isset($options['placeholder'])) $input .= ' placeholder="' . htmlspecialchars_uni($options['placeholder']) . '"';
    return $input . ' autocomplete="off" />';
}

function ug_numeric_field(string $name, int|float|string|null $value = 0, array $options = []): string
{
    $value = is_numeric($value) ? (float)$value : '';
    $input = '<input type="number" name="' . $name . '" value="' . $value . '"';
    if (isset($options['min']))  $input .= ' min="' . $options['min'] . '"';
    if (isset($options['max']))  $input .= ' max="' . $options['max'] . '"';
    if (isset($options['step'])) $input .= ' step="' . $options['step'] . '"';
    // Раньше класс был "text_input" — поле оставалось без стилей Bootstrap
    $input .= ' class="form-control ' . ($options['class'] ?? '') . '"';
    return $input . ' />';
}

/** Числовое поле с иконкой, подписью и единицей измерения */
function ug_number_row(string $name, string $icon, string $label, $value, string $hint = '', string $unit = '', array $opt = []): void
{
    echo '<div class="mb-3"><label class="form-label"><i class="fa-solid ' . $icon . '"></i>' . $label . '</label>';
    echo '<div class="input-group" style="max-width:240px">' . ug_numeric_field($name, $value, $opt + ['min' => 0])
       . ($unit !== '' ? '<span class="input-group-text">' . $unit . '</span>' : '') . '</div>';
    if ($hint !== '') echo '<span class="ug-help">' . $hint . '</span>';
    echo '</div>';
}

function ug_select_box(string $name, array $option_list, mixed $selected = '', array $options = []): string
{
    $select = '<select name="' . $name . '" class="' . ($options['class'] ?? 'form-select') . '"' . (isset($options['id']) ? ' id="' . $options['id'] . '"' : '') . ">\n";
    foreach ($option_list as $value => $option) {
        $sel = (string)$value === (string)$selected ? ' selected="selected"' : '';
        $select .= '<option value="' . $value . '"' . $sel . '>' . $option . "</option>\n";
    }
    return $select . "</select>\n";
}

/** Переключатель-строка с иконкой. $danger — красная подсветка (бан, супермод и т.п.) */
function ug_switch(string $name, string $label, mixed $checked, string $icon = 'fa-toggle-on', bool $danger = false): void
{
    $checked_attr = ($checked === true || $checked == 1) ? ' checked' : '';
    $id = 'sw_' . $name;
    // hidden 0 — чтобы снятая галочка сохранялась как 0 явно
    echo '<label class="ug-switch' . ($danger ? ' is-danger' : '') . '" for="' . $id . '">'
       . '<span class="ug-switch-icon"><i class="fa-solid ' . $icon . '"></i></span>'
       . '<span class="ug-switch-text">' . $label . '</span>'
       . '<input type="hidden" name="' . $name . '" value="0">'
       . '<input type="checkbox" name="' . $name . '" id="' . $id . '" value="1" class="form-check-input" role="switch"' . $checked_attr . '>'
       . '</label>';
}

/** Общие поля «Название / стиль / картинка» для добавления и редактирования */
function ug_identity_fields(array $v, bool $is_add): void
{
    echo '<div class="row g-3">';
    echo '<div class="col-md-6">';
    echo '<label class="form-label" for="ug_title"><i class="fa-solid fa-tag"></i>Group title <span class="ug-req">*</span></label>';
    echo ug_text_box('title', (string)($v['title'] ?? ''), ['id' => 'ug_title', 'placeholder' => 'e.g. Power Users']);
    echo '</div>';
    echo '<div class="col-md-6">';
    echo '<label class="form-label" for="ug_desc"><i class="fa-solid fa-align-left"></i>Short description</label>';
    echo ug_text_box('description', (string)($v['description'] ?? ''), ['id' => 'ug_desc', 'placeholder' => 'Shown on the team page']);
    echo '</div>';
    echo '<div class="col-md-6">';
    echo '<label class="form-label" for="ug_style"><i class="fa-solid fa-palette"></i>Username style</label>';
    echo ug_text_box('namestyle', (string)(($v['namestyle'] ?? '') ?: '{username}'), ['id' => 'ug_style', 'class' => 'font-monospace', 'placeholder' => '<span style="color:#e67e22">{username}</span>']);
    echo '<span class="ug-help">Must contain <code>{username}</code>, e.g. <code>&lt;b style="color:#e67e22"&gt;{username}&lt;/b&gt;</code></span>';
    echo '</div>';
    echo '<div class="col-md-6">';
    echo '<label class="form-label" for="ug_usertitle"><i class="fa-solid fa-id-badge"></i>Default user title</label>';
    echo ug_text_box('usertitle', (string)($v['usertitle'] ?? ''), ['id' => 'ug_usertitle', 'placeholder' => 'e.g. Power User']);
    echo '</div>';
    echo '<div class="col-md-6">';
    echo '<label class="form-label" for="ug_image"><i class="fa-solid fa-image"></i>Group image</label>';
    echo ug_text_box('image', (string)($v['image'] ?? ''), ['id' => 'ug_image', 'class' => 'font-monospace', 'placeholder' => '<i class="fa-solid fa-star" style="color:#f59e0b"></i>']);
    echo '<span class="ug-help">Icon HTML (<code>&lt;i class="fa-solid fa-star"&gt;</code>) or an image path; <code>{lang}</code> = user language</span>';
    echo '</div>';
    echo '<div class="col-md-6">';
    echo '<label class="form-label"><i class="fa-solid fa-eye"></i>Preview</label>';
    echo '<div class="ug-preview"><div class="flex-grow-1"><div class="ug-preview-label">Name</div><div id="ugNamePreview" class="fw-semibold">Username</div></div>'
       . '<div class="text-end"><div class="ug-preview-label">Image</div><div id="ugImagePreview">—</div></div></div>';
    echo '</div>';
    echo '</div>';
}


// ═══════════════════════════════════════════════════════════
// ACTION: ADD
// ═══════════════════════════════════════════════════════════
if (($mybb->input['action'] ?? '') === 'add') {
    $plugins->run_hooks('admin_user_groups_add');

    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        $errors = [];
        if (!trim($mybb->input['title']))
            $errors[] = 'You did not enter a title for this new user group';
        if (my_strpos($mybb->input['namestyle'], '{username}') === false)
            $errors[] = 'The username style must contain {username}';
        if (preg_match('#<((m[^a])|(b[^diloru>])|(s[^aemptu >]))(\s*[^>]*)>#si', $mybb->input['namestyle']))
            $errors[] = 'You cant use script, meta or base tags in the username style';

        if (!$errors) {
            $new_usergroup = [
                'type'        => 2,
                'title'       => $mybb->input['title'],
                'description' => $mybb->input['description'],
                'namestyle'   => $mybb->input['namestyle'],
                'usertitle'   => $mybb->input['usertitle'],
                'image'       => $mybb->input['image'],
                'disporder'   => 0,
            ];

            if ($mybb->input['copyfrom'] == 0) {
                $new_usergroup = array_merge($new_usergroup, $usergroup_permissions);
            } else {
                $q = $db->sql_query_prepared("SELECT * FROM usergroups WHERE gid = ?", [$mybb->get_input('copyfrom', MyBB::INPUT_INT)]);
                $existing = $q ? $db->fetch_array($q) : null;
                foreach (array_keys($usergroup_permissions) as $field) {
                    $new_usergroup[$field] = $existing[$field];
                }
            }

            $plugins->run_hooks('admin_user_groups_add_commit');
            $columns      = array_keys($new_usergroup);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $db->sql_query_prepared(
                "INSERT INTO usergroups (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                array_values($new_usergroup)
            );
            $gid = $db->insert_id();
            $plugins->run_hooks('admin_user_groups_add_commit_end');

            if ($mybb->input['copyfrom'] > 0) {
                $q = $db->sql_query_prepared("SELECT * FROM forumpermissions WHERE gid = ?", [$mybb->get_input('copyfrom', MyBB::INPUT_INT)]);
                while ($q && ($fp = $db->fetch_array($q))) {
                    unset($fp['pid']);
                    $fp['gid'] = $gid;
                    $fp_columns      = array_keys($fp);
                    $fp_placeholders = implode(',', array_fill(0, count($fp_columns), '?'));
                    $db->sql_query_prepared(
                        "INSERT INTO forumpermissions (`" . implode('`,`', $fp_columns) . "`) VALUES ({$fp_placeholders})",
                        array_values($fp)
                    );
                }
            }

            $cache->update_usergroups();
            $cache->update_forumpermissions();
            log_admin_action($gid, $mybb->input['title']);

            flash_message('User group created successfully', 'success');
            admin_redirect("index.php?act=groups&action=edit&gid={$gid}");
        }
    }

    stdhead('Add New User Group');
    ug_head_assets();

    echo '<div class="container mt-3 mb-4 ug">';
    ug_breadcrumb(['Add New Group' => '']);
    ug_hero('fa-user-plus', 'ic-green', 'Add New User Group', 'Create a group, then fine-tune its permissions on the next screen');
    ug_errors($errors ?? []);

    ug_form_open('index.php?act=groups&action=add', 'addGroupForm');

    echo '<div class="ug-card">';
    ug_sec_open('fa-id-card', 'ic-blue', 'Identity');
    ug_identity_fields($mybb->input, true);
    ug_sec_close();

    ug_sec_open('fa-copy', 'ic-purple', 'Starting permissions');
    $options = [0 => 'Default permissions (don\'t copy)'];
    $q = $db->sql_query_prepared("SELECT gid, title FROM usergroups WHERE gid != '1' ORDER BY title");
    while ($q && ($ug = $db->fetch_array($q))) {
        $options[$ug['gid']] = htmlspecialchars_uni($ug['title']);
    }
    echo '<label class="form-label" for="copyfrom"><i class="fa-solid fa-clone"></i>Copy permissions from</label>';
    echo '<div style="max-width:420px">' . ug_select_box('copyfrom', $options, $mybb->get_input('copyfrom'), ['id' => 'copyfrom']) . '</div>';
    echo '<span class="ug-help">All group permissions and forum permissions are copied from the selected group</span>';
    ug_sec_close();
    echo '</div>';

    echo '<div class="d-flex justify-content-end gap-2 mt-3">';
    echo '<a href="index.php?act=groups" class="btn btn-outline-secondary rounded-pill px-3"><i class="fa-solid fa-xmark me-1"></i>Cancel</a>';
    echo '<button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-circle-plus me-1"></i>Create Group</button>';
    echo '</div>';

    ug_form_close();
    echo '</div>';

    stdfoot();
    exit;
}



// ═══════════════════════════════════════════════════════════
// ACTION: EDIT
// ═══════════════════════════════════════════════════════════
if (($mybb->input['action'] ?? '') === 'edit') {
    $gid_input = $mybb->get_input('gid', MyBB::INPUT_INT);
    $q = $db->sql_query_prepared("SELECT * FROM usergroups WHERE gid = ?", [$gid_input]);
    $usergroup = $q ? $db->fetch_array($q) : null;

    if (!$usergroup) {
        flash_message('You have selected an invalid user group', 'error');
        admin_redirect('index.php?act=groups');
    }

    $errors = [];
    if (preg_match('#<((m[^a])|(b[^diloru>])|(s[^aemptu >]))(\s*[^>]*)>#si', $mybb->get_input('namestyle'))) {
        $errors[] = 'You cant use script, meta or base tags in the username style';
        $mybb->input['namestyle'] = $usergroup['namestyle'];
    }

    $plugins->run_hooks('admin_user_groups_edit');

    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        if (!trim($mybb->get_input('title')))
            $errors[] = 'You did not enter a title for this user group';
        if (my_strpos($mybb->get_input('namestyle'), '{username}') === false)
            $errors[] = 'The username style must contain {username}';
        if ($mybb->get_input('moderate') == 1 && $mybb->get_input('invite') == 1)
            $errors[] = 'A group can\'t be both "approval required" and "invite only"';

        if (!$errors) {
            if ($mybb->get_input('joinable') == 1) {
                $mybb->input['type'] = $mybb->get_input('moderate') == 1 ? '4'
                    : ($mybb->get_input('invite') == 1 ? '5' : '3');
            } else {
                $mybb->input['type'] = '2';
            }
            if ($usergroup['type'] == 1) $mybb->input['type'] = 1;
            if ($mybb->get_input('stars') < 1) $mybb->input['stars'] = 0;

            $g = fn(string $k) => $mybb->get_input($k, MyBB::INPUT_INT);
            $updated_group = [
                'type'                  => $g('type'),
                'title'                 => $mybb->input['title'],
                'description'           => $mybb->input['description'],
                'namestyle'             => $mybb->input['namestyle'],
                'usertitle'             => $mybb->input['usertitle'],
                'image'                 => $mybb->input['image'],
                'isbannedgroup'         => $g('isbannedgroup'),
                'canview'               => $g('canview'),
                'canviewthreads'        => $g('canviewthreads'),
                'candlattachments'      => $g('candlattachments'),
                'canviewboardclosed'    => $g('canviewboardclosed'),
                'canpostthreads'        => $g('canpostthreads'),
                'canpostreplys'         => $g('canpostreplys'),
                'canpostattachments'    => $g('canpostattachments'),
                'modposts'              => $g('modposts'),
                'modthreads'            => $g('modthreads'),
                'mod_edit_posts'        => $g('mod_edit_posts'),
                'modattachments'        => $g('modattachments'),
                'caneditposts'          => $g('caneditposts'),
                'candeletetorrent'      => $g('candeletetorrent'),
                'candeleteposts'        => $g('candeleteposts'),
                'candeletethreads'      => $g('candeletethreads'),
                'caneditattachments'    => $g('caneditattachments'),
                'canpostpolls'          => $g('canpostpolls'),
                'canvotepolls'          => $g('canvotepolls'),
                'canundovotes'          => $g('canundovotes'),
                'canusepms'             => $g('canusepms'),
                'cansendpms'            => $g('cansendpms'),
                'cantrackpms'           => $g('cantrackpms'),
                'candenypmreceipts'     => $g('candenypmreceipts'),
                'pmquota'               => $g('pmquota'),
                'maxpmrecipients'       => $g('maxpmrecipients'),
                'cansendemail'          => $g('cansendemail'),
                'cansendemailoverride'  => $g('cansendemailoverride'),
                'cansettingspanel'      => $g('cansettingspanel'),
                'canviewwolinvis'       => $g('canviewwolinvis'),
                'issupermod'            => $g('issupermod'),
                'cansearch'             => $g('cansearch'),
                'showforumteam'         => $g('showforumteam'),
                'attachquota'           => $g('attachquota'),
                'canstaffpanel'         => $g('canstaffpanel'),
                'canoverridepm'         => $g('canoverridepm'),
				'max_screenshots'       => max(0, $mybb->get_input('max_screenshots', MyBB::INPUT_INT)),
            ];

            $plugins->run_hooks('admin_user_groups_edit_commit');
            $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($updated_group)));
            $params = array_values($updated_group);
            $params[] = $usergroup['gid'];
            $db->sql_query_prepared("UPDATE usergroups SET {$set} WHERE gid = ?", $params);
            $cache->update_usergroups();
            $cache->update_forumpermissions();
            log_admin_action($usergroup['gid'], $mybb->input['title']);

            flash_message('The selected user group has been updated successfully', 'success');
            admin_redirect('index.php?act=groups');
        }
    } else {
        $usergroup['joinable'] = in_array($usergroup['type'], [3, 4, 5]) ? 1 : 0;
        $usergroup['moderate'] = $usergroup['type'] == 4 ? 1 : 0;
        $usergroup['invite']   = $usergroup['type'] == 5 ? 1 : 0;
        $mybb->input = array_merge($mybb->input, $usergroup);
    }

    stdhead('Edit User Group');
    ug_head_assets();

    $in  = $mybb->input;
    $gid = (int)$usergroup['gid'];

    $tags = '<span class="ug-tag ' . ((int)$usergroup['type'] === 1 ? 't-default"><i class="fa-solid fa-lock"></i>Default' : 't-custom"><i class="fa-solid fa-wand-magic-sparkles"></i>Custom') . '</span>';

    echo '<div class="container mt-3 mb-4 ug">';
    ug_breadcrumb([htmlspecialchars_uni($usergroup['title']) => '']);
    ug_hero('fa-users-gear', 'ic-blue',
        'Edit group: ' . format_name(htmlspecialchars_uni($usergroup['title']), $gid),
        '<span class="ug-gid">GID ' . $gid . '</span> ' . $tags);
    ug_errors($errors ?? []);

    ug_form_open("index.php?act=groups&action=edit&amp;gid={$gid}", 'userGroupForm');

    $tabs = [
        'general'           => ['fa-gear',          'General'],
        'forums_posts'      => ['fa-comments',      'Forums & Posts'],
        'users_permissions' => ['fa-envelope',      'Messaging'],
        'modcp'             => ['fa-gavel',         'Moderation'],
    ];
    echo '<ul class="nav ug-tabs" role="tablist">';
    $first = true;
    foreach ($tabs as $id => [$ic, $label]) {
        echo '<li class="nav-item"><button type="button" class="nav-link' . ($first ? ' active' : '') . '" data-bs-toggle="tab" data-bs-target="#tab_' . $id . '" role="tab"><i class="fa-solid ' . $ic . '"></i>' . $label . '</button></li>';
        $first = false;
    }
    echo '</ul><div class="tab-content">';

    // ── General ──────────────────────────────────────────────
    echo '<div class="tab-pane fade show active" id="tab_general" role="tabpanel"><div class="ug-card">';
    ug_sec_open('fa-id-card', 'ic-blue', 'Identity');
    ug_identity_fields($in, false);
    ug_sec_close();

    echo '<div class="row g-0">';
    echo '<div class="col-lg-6">';
    ug_sec_open('fa-sliders', 'ic-teal', 'General options');
    ug_switch('showforumteam', 'Show this group on the team page', $in['showforumteam'] ?? 0, 'fa-people-group');
    ug_switch('isbannedgroup', 'This is a banned group',           $in['isbannedgroup'] ?? 0, 'fa-ban', true);
    ug_switch('canviewwolinvis', 'Can see invisible users',        $in['canviewwolinvis'] ?? 0, 'fa-user-ninja');
    ug_sec_close();

    // Раньше полей joinable/moderate/invite в форме не было, а обработчик их читал —
    // каждое сохранение превращало «открытую» группу (type 3/4/5) в обычную (type 2)
    if ((int)$usergroup['type'] !== 1) {
        ug_sec_open('fa-door-open', 'ic-green', 'Joining');
        ug_switch('joinable', 'Users can join this group themselves', $in['joinable'] ?? 0, 'fa-right-to-bracket');
        ug_switch('moderate', 'Join requests must be approved',      $in['moderate'] ?? 0, 'fa-user-check');
        ug_switch('invite',   'Invite only',                          $in['invite'] ?? 0,   'fa-envelope-open-text');
        ug_sec_close();
    }
    echo '</div><div class="col-lg-6">';
    ug_sec_open('fa-shield-halved', 'ic-red', 'Administration');
    ug_switch('issupermod',       'Users are super moderators', $in['issupermod'] ?? 0,       'fa-user-shield', true);
    ug_switch('canstaffpanel',    'Can access the Staff Panel', $in['canstaffpanel'] ?? 0,    'fa-screwdriver-wrench', true);
    ug_switch('cansettingspanel', 'Can access the Settings Panel', $in['cansettingspanel'] ?? 0, 'fa-sliders', true);
    echo '<div class="ug-help mt-2"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Red switches grant powerful rights — enable only for trusted staff groups.</div>';
    ug_sec_close();
    echo '</div></div>';
    echo '</div></div>';

    // ── Forums & Posts ───────────────────────────────────────
    echo '<div class="tab-pane fade" id="tab_forums_posts" role="tabpanel"><div class="ug-card"><div class="row g-0">';
    echo '<div class="col-lg-6">';
    ug_sec_open('fa-eye', 'ic-blue', 'Viewing');
    ug_switch('canview',            'Can view the board',             $in['canview'] ?? 0,            'fa-house');
    ug_switch('canviewthreads',     'Can view threads',               $in['canviewthreads'] ?? 0,     'fa-list');
    ug_switch('cansearch',          'Can search forums',              $in['cansearch'] ?? 0,          'fa-magnifying-glass');
    ug_switch('candlattachments',   'Can download attachments',       $in['candlattachments'] ?? 0,   'fa-download');
    ug_switch('canviewboardclosed', 'Can view the board when closed', $in['canviewboardclosed'] ?? 0, 'fa-door-closed');
    ug_sec_close();
    ug_sec_open('fa-paper-plane', 'ic-green', 'Posting');
    ug_switch('canpostthreads', 'Can post new threads', $in['canpostthreads'] ?? 0, 'fa-square-plus');
    ug_switch('canpostreplys',  'Can reply to threads', $in['canpostreplys'] ?? 0,  'fa-reply');
    ug_sec_close();
    ug_sec_open('fa-square-poll-vertical', 'ic-purple', 'Polls');
    ug_switch('canpostpolls', 'Can create polls',        $in['canpostpolls'] ?? 0, 'fa-chart-simple');
    ug_switch('canvotepolls', 'Can vote in polls',       $in['canvotepolls'] ?? 0, 'fa-check-to-slot');
    ug_switch('canundovotes', 'Can undo own poll votes', $in['canundovotes'] ?? 0, 'fa-rotate-left');
    ug_sec_close();
    echo '</div><div class="col-lg-6">';
    ug_sec_open('fa-pen-to-square', 'ic-amber', 'Editing');
    ug_switch('caneditposts',       'Can edit own posts',        $in['caneditposts'] ?? 0,       'fa-pen');
    ug_switch('candeleteposts',     'Can delete own posts',      $in['candeleteposts'] ?? 0,     'fa-eraser');
    ug_switch('candeletethreads',   'Can delete own threads',    $in['candeletethreads'] ?? 0,   'fa-trash-can');
    ug_switch('caneditattachments', 'Can edit own attachments',  $in['caneditattachments'] ?? 0, 'fa-file-pen');
    ug_sec_close();
    ug_sec_open('fa-paperclip', 'ic-teal', 'Attachments & screenshots');
    ug_switch('canpostattachments', 'Can post attachments', $in['canpostattachments'] ?? 0, 'fa-paperclip');
    ug_number_row('attachquota', 'fa-hard-drive', 'Attachment quota', $in['attachquota'] ?? 0, '0 = unlimited', 'KB');
    ug_number_row('max_screenshots', 'fa-camera', 'Screenshots per torrent', $in['max_screenshots'] ?? 3, '0 = not allowed', '', ['max' => 299]);
    ug_sec_close();
    echo '</div></div></div></div>';

    // ── Messaging ────────────────────────────────────────────
    echo '<div class="tab-pane fade" id="tab_users_permissions" role="tabpanel"><div class="ug-card"><div class="row g-0">';
    echo '<div class="col-lg-6">';
    ug_sec_open('fa-envelope-open-text', 'ic-blue', 'Private messages');
    ug_switch('canusepms',         'Can use private messaging', $in['canusepms'] ?? 0,         'fa-inbox');
    ug_switch('cansendpms',        'Can send messages',         $in['cansendpms'] ?? 0,        'fa-paper-plane');
    ug_switch('cantrackpms',       'Can track messages',        $in['cantrackpms'] ?? 0,       'fa-location-crosshairs');
    ug_switch('candenypmreceipts', 'Can deny read receipts',    $in['candenypmreceipts'] ?? 0, 'fa-eye-slash');
    ug_switch('canoverridepm',     'Can bypass PM limits',      $in['canoverridepm'] ?? 0,     'fa-forward-fast', true);
    ug_sec_close();
    echo '</div><div class="col-lg-6">';
    ug_sec_open('fa-gauge', 'ic-amber', 'Limits');
    ug_number_row('pmquota',         'fa-box-archive', 'PM quota',          $in['pmquota'] ?? 0,         '0 = unlimited', 'messages');
    ug_number_row('maxpmrecipients', 'fa-users',       'Max PM recipients', $in['maxpmrecipients'] ?? 0, 'Per message',   'users');
    ug_sec_close();
    ug_sec_open('fa-at', 'ic-purple', 'Email');
    ug_switch('cansendemail',         'Can email other users',       $in['cansendemail'] ?? 0,         'fa-envelope');
    ug_switch('cansendemailoverride', 'Can bypass email flood check', $in['cansendemailoverride'] ?? 0, 'fa-bolt');
    ug_sec_close();
    echo '</div></div></div></div>';

    // ── Moderation ───────────────────────────────────────────
    echo '<div class="tab-pane fade" id="tab_modcp" role="tabpanel"><div class="ug-card"><div class="row g-0">';
    echo '<div class="col-lg-6">';
    ug_sec_open('fa-hourglass-half', 'ic-amber', 'Require approval for');
    ug_switch('modposts',       'New posts',       $in['modposts'] ?? 0,       'fa-comment');
    ug_switch('modthreads',     'New threads',     $in['modthreads'] ?? 0,     'fa-list');
    ug_switch('mod_edit_posts', 'Edited posts',    $in['mod_edit_posts'] ?? 0, 'fa-pen');
    ug_switch('modattachments', 'New attachments', $in['modattachments'] ?? 0, 'fa-paperclip');
    ug_sec_close();
    echo '</div><div class="col-lg-6">';
    ug_sec_open('fa-trash-can', 'ic-red', 'Deletion');
    ug_switch('candeletetorrent', 'Can delete torrents', $in['candeletetorrent'] ?? 0, 'fa-magnet', true);
    ug_sec_close();
    echo '</div></div></div></div>';

    echo '</div>'; // tab-content

    echo '<div class="ug-card ug-savebar">';
    echo '<span class="ug-muted"><i class="fa-solid fa-circle-info me-1"></i>Changes apply to every member of the group</span>';
    echo '<div class="d-flex gap-2"><a href="index.php?act=groups" class="btn btn-outline-secondary rounded-pill px-3"><i class="fa-solid fa-xmark me-1"></i>Cancel</a>';
    echo '<button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-floppy-disk me-1"></i>Save Group</button></div>';
    echo '</div>';

    ug_form_close();
    echo '</div>';

    stdfoot();
    exit;
}


// ═══════════════════════════════════════════════════════════
// ACTION: DELETE
// ═══════════════════════════════════════════════════════════
if (($mybb->input['action'] ?? '') === 'delete') {
    $q = $db->sql_query_prepared("SELECT * FROM usergroups WHERE gid = ?", [$mybb->get_input('gid', MyBB::INPUT_INT)]);
    $usergroup = $q ? $db->fetch_array($q) : null;

    if (!$usergroup) {
        flash_message('You have selected an invalid user group', 'error');
        admin_redirect('index.php?act=groups');
    }
    if ($usergroup['type'] == 1) {
        flash_message('Default groups cannot be deleted', 'error');
        admin_redirect('index.php?act=groups');
    }
    if ($mybb->get_input('no')) {
        admin_redirect('index.php?act=groups');
    }

    $plugins->run_hooks('admin_user_groups_delete');

    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        $newGroup = $usergroup['isbannedgroup'] == 1 ? 9 : 1;
        $db->sql_query_prepared("UPDATE users SET usergroup = ? WHERE usergroup = ?", [$newGroup, $usergroup['gid']]);
        // displaygroup = usergroup — копируем значение из колонки usergroup (не строковый литерал!)
        $db->sql_query_prepared("UPDATE users SET displaygroup = usergroup WHERE displaygroup = ?", [$usergroup['gid']]);

        $db->sql_query_prepared("UPDATE banned SET gid = 9 WHERE gid = ?", [$usergroup['gid']]);
        $db->sql_query_prepared("UPDATE banned SET oldgroup = 1 WHERE oldgroup = ?", [$usergroup['gid']]);
        // olddisplaygroup = oldgroup — та же логика, копирование значения колонки
        $db->sql_query_prepared("UPDATE banned SET olddisplaygroup = oldgroup WHERE olddisplaygroup = ?", [$usergroup['gid']]);

        $db->sql_query_prepared("DELETE FROM forumpermissions WHERE gid = ?", [$usergroup['gid']]);
        $db->sql_query_prepared("DELETE FROM moderators WHERE id = ? AND isgroup = '1'", [$usergroup['gid']]);
        $db->sql_query_prepared("DELETE FROM usergroups WHERE gid = ?", [$usergroup['gid']]);

        $plugins->run_hooks('admin_user_groups_delete_commit');
        $plugins->run_hooks('admin_user_groups_delete_commit_end');

        $cache->update_moderators();
        $cache->update_usergroups();
        $cache->update_forumpermissions();
        log_admin_action($usergroup['gid'], $usergroup['title']);

        echo 'The selected Group has been deleted successfully';
        exit;
    }
}

// ═══════════════════════════════════════════════════════════
// ACTION: DISPORDER
// ═══════════════════════════════════════════════════════════
if (($mybb->input['action'] ?? '') === 'disporder' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $plugins->run_hooks('admin_user_groups_disporder');
    foreach ($mybb->input['disporder'] as $gid => $order) {
        $gid = (int)$gid; $order = (int)$order;
        if ($gid) { // раньше && $order — порядок 0 нельзя было сохранить
            $db->sql_query_prepared("UPDATE usergroups SET disporder = ? WHERE gid = ?", [$order, $gid]);
        }
    }
    log_admin_action();
    $plugins->run_hooks('admin_user_groups_disporder_commit');
    flash_message('The user group display orders have been updated successfully', 'success');
    admin_redirect('index.php?act=groups');
}

// ═══════════════════════════════════════════════════════════
// DEFAULT ACTION: MAIN VIEW
// ═══════════════════════════════════════════════════════════
if (!($mybb->input['action'] ?? '')) {
    $plugins->run_hooks('admin_user_groups_start');

    if ($mybb->request_method === 'post' && !empty($mybb->input['disporder'])) {
        foreach ($mybb->input['disporder'] as $gid => $order) {
            $db->sql_query_prepared("UPDATE usergroups SET disporder = ? WHERE gid = ?", [(int)$order, (int)$gid]);
        }
        $plugins->run_hooks('admin_user_groups_start_commit');
        $cache->update_usergroups();
        flash_message('The user group display orders have been updated successfully', 'success');
        admin_redirect('index.php?act=groups');
    }

    stdhead('Manage User Groups');
    ug_head_assets();

    echo '<script src="scripts/deleteGroup.js"></script>';
    echo '<script>window.my_post_key = "' . $mybb->post_code . '";</script>';

    // Кол-во пользователей: основная группа + дополнительные
    $primaryusers = $secondaryusers = [];
    $q = $db->sql_query_prepared('SELECT g.gid, COUNT(u.id) AS users FROM users u LEFT JOIN usergroups g ON (g.gid=u.usergroup) GROUP BY g.gid');
    while ($q && ($row = $db->fetch_array($q))) $primaryusers[$row['gid']] = (int)$row['users'];

    $col = $db->type === 'pgsql' || $db->type === 'sqlite'
        ? "','||u.additionalgroups||',' LIKE '%,'||g.gid||',%'"
        : "CONCAT(',',u.additionalgroups,',') LIKE CONCAT('%,',g.gid,',%')";
    $q = $db->sql_query_prepared("SELECT g.gid, COUNT(u.id) AS users FROM users u LEFT JOIN usergroups g ON ({$col}) WHERE g.gid != '0' AND g.gid IS NOT NULL GROUP BY g.gid");
    while ($q && ($row = $db->fetch_array($q))) $secondaryusers[$row['gid']] = (int)$row['users'];

    $groups = [];
    $q = $db->sql_query_prepared("SELECT * FROM usergroups ORDER BY disporder, title");
    while ($q && ($ug = $db->fetch_array($q))) $groups[] = $ug;

    $n_custom = count(array_filter($groups, fn($g) => (int)$g['type'] > 1));
    $n_staff  = count(array_filter($groups, fn($g) => (int)$g['canstaffpanel'] === 1 || (int)$g['issupermod'] === 1));
    $n_users  = array_sum($primaryusers);

    echo '<div class="container mt-3 mb-4 ug">';
    ug_breadcrumb([]);
    ug_hero('fa-users', 'ic-blue', 'User Groups', 'Permissions, name styles and team-page order for every group',
        '<a href="index.php?act=groups&amp;action=add" class="btn btn-primary rounded-pill px-3"><i class="fa-solid fa-plus me-1"></i>Add Group</a>');

    echo '<div class="row g-3 mb-3">';
    foreach ([
        ['fa-layer-group',  'ic-blue',   'Groups',       ts_nf(count($groups))],
        ['fa-wand-magic-sparkles', 'ic-slate', 'Custom', ts_nf($n_custom)],
        ['fa-user-shield',  'ic-purple', 'Staff groups', ts_nf($n_staff)],
        ['fa-user',         'ic-green',  'Users',        ts_nf($n_users)],
    ] as [$ic, $cls, $label, $val]) {
        echo '<div class="col-6 col-md-3"><div class="ug-card ug-stat"><span class="ug-stat-icon ' . $cls . '"><i class="fa-solid ' . $ic . '"></i></span>'
           . '<div><div class="ug-stat-label">' . $label . '</div><div class="ug-stat-value">' . $val . '</div></div></div></div>';
    }
    echo '</div>';

    ug_form_open('index.php?act=groups', 'groupsForm');

    echo '<div class="ug-card overflow-hidden"><div class="table-responsive"><table class="table ug-table"><thead><tr>';
    echo '<th><i class="fa-solid fa-users"></i>Group</th>';
    echo '<th><i class="fa-solid fa-tags"></i>Flags</th>';
    echo '<th class="text-center"><i class="fa-solid fa-user"></i>Members</th>';
    echo '<th class="text-center"><i class="fa-solid fa-arrow-down-1-9"></i>Team order</th>';
    echo '<th class="text-end"><i class="fa-solid fa-bolt"></i>Actions</th>';
    echo '</tr></thead><tbody>';

    foreach ($groups as $ug) {
        $gid     = (int)$ug['gid'];
        $type    = (int)$ug['type'];
        $primary = $primaryusers[$gid] ?? 0;
        $second  = $secondaryusers[$gid] ?? 0;

        $flags = $type === 1 ? '<span class="ug-tag t-default"><i class="fa-solid fa-lock"></i>Default</span>' : '<span class="ug-tag t-custom"><i class="fa-solid fa-wand-magic-sparkles"></i>Custom</span>';
        if ((int)$ug['issupermod'] === 1 || (int)$ug['canstaffpanel'] === 1) $flags .= ' <span class="ug-tag t-staff"><i class="fa-solid fa-user-shield"></i>Staff</span>';
        if ((int)$ug['isbannedgroup'] === 1) $flags .= ' <span class="ug-tag t-banned"><i class="fa-solid fa-ban"></i>Banned</span>';
        if (in_array($type, [3, 4, 5], true)) $flags .= ' <span class="ug-tag t-join"><i class="fa-solid fa-door-open"></i>' . ($type === 5 ? 'Invite' : ($type === 4 ? 'Request' : 'Open')) . '</span>';
        if ((int)$ug['showforumteam'] === 1) $flags .= ' <span class="ug-tag t-team"><i class="fa-solid fa-people-group"></i>Team page</span>';

        $fallback = (int)$ug['isbannedgroup'] === 1 ? 'fa-ban text-danger' : ($type === 1 ? 'fa-user text-primary' : 'fa-users text-secondary');

        echo '<tr>';
        echo '<td><div class="d-flex align-items-center gap-3">' . ug_group_image((string)$ug['image'], $fallback)
           . '<div style="min-width:0">'
           . '<a href="index.php?act=groups&amp;action=edit&amp;gid=' . $gid . '" class="ug-gname">' . format_name(htmlspecialchars_uni($ug['title']), $gid) . '</a> <span class="ug-gid">#' . $gid . '</span>'
           . (!empty($ug['description']) ? '<div class="ug-muted">' . htmlspecialchars_uni($ug['description']) . '</div>' : '')
           . '</div></div></td>';

        echo '<td>' . $flags . '</td>';

        echo '<td class="text-center"><span class="ug-users"><i class="fa-solid fa-user"></i>' . ts_nf($primary + $second) . '</span>'
           . ($second > 0 ? '<div class="ug-muted">' . ts_nf($second) . ' additional</div>' : '') . '</td>';

        echo '<td class="text-center">';
        echo (int)$ug['showforumteam'] === 1
            ? '<input type="number" name="disporder[' . $gid . ']" value="' . (int)$ug['disporder'] . '" min="0" class="form-control form-control-sm ug-order" aria-label="Display order">'
            : '<span class="ug-muted" title="Only groups shown on the team page are ordered">—</span>';
        echo '</td>';

        echo '<td class="text-end text-nowrap">';
        echo '<a class="ug-act" href="index.php?act=groups&amp;action=edit&amp;gid=' . $gid . '" title="Edit"><i class="fa-solid fa-pen"></i></a>';
        echo '<a class="ug-act" href="index.php?act=groups&amp;action=search&amp;results=1&amp;conditions[usergroup]=' . $gid . '" title="List users"><i class="fa-solid fa-list-ul"></i></a>';
        echo $type > 1
            ? '<a class="ug-act danger delete_employee" href="javascript:void(0)" data-emp-id="' . $gid . '" title="Delete"><i class="fa-solid fa-trash"></i></a>'
            : '<span class="ug-act is-locked" title="Default groups cannot be deleted"><i class="fa-solid fa-lock"></i></span>';
        echo '</td>';
        echo '</tr>';
    }

    if (!$groups) {
        echo '<tr><td colspan="5"><div class="ug-empty"><i class="fa-solid fa-users fa-2x mb-2 d-block opacity-50"></i>No user groups found.</div></td></tr>';
    }

    echo '</tbody></table></div>';
    echo '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">';
    echo '<span class="ug-muted"><i class="fa-solid fa-circle-info me-1"></i>Order applies to groups shown on the team page</span>';
    echo '<button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="fa-solid fa-arrow-down-1-9 me-1"></i>Save order</button>';
    echo '</div></div>';

    ug_form_close();
    echo '</div>';

    stdfoot();
    exit;
}