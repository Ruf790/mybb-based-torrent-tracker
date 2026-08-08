<?php
/**
 * User Groups Management — refactored
 * PHP 8.1+
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



if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once $thispath . 'include/class_page.php';
require_once $thispath . 'include/class_form.php';
require_once $thispath . 'include/class_table.php';

if (file_exists('include/style.php')) {
    require_once 'include/style.php';
}

foreach ([
    'Page'          => DefaultPage::class,
    'Table'         => DefaultTable::class,
    'Form'          => DefaultForm::class,
    'FormContainer' => DefaultFormContainer::class,
] as $alias => $class) {
    if (!class_exists($alias, false)) {
        class_exists($class) ? class_alias($class, $alias)
            : throw new RuntimeException("Required class $class not found");
    }
}

$page = new Page();
$page->add_breadcrumb_item('User Groups', 'index.php?act=groups');

if (in_array($mybb->input['action'] ?? '', ['add', '']) || !($mybb->input['action'] ?? '')) {
    $sub_tabs['manage_groups'] = [
        'title'       => 'Manage User Groups',
        'link'        => 'index.php?act=groups',
        'description' => 'Manage the various user groups on your board.',
    ];
    $sub_tabs['add_group'] = [
        'title'       => 'Add New User Group',
        'link'        => 'index.php?act=groups&action=add',
        'description' => 'Create a new user group and optionally copy permissions from another group.',
    ];
}

$plugins->run_hooks('admin_user_groups_begin');

// ═══════════════════════════════════════════════════════════
// SHARED HELPERS
// ═══════════════════════════════════════════════════════════

function ug_head_assets(): void
{
    global $BASEURL;
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/userclass.css">';
}

function ug_breadcrumb(array $items): void
{
    echo '<nav aria-label="breadcrumb" class="mb-4"><ol class="breadcrumb">';
    echo '<li class="breadcrumb-item"><a href="index.php?module=home">Home</a></li>';
    echo '<li class="breadcrumb-item"><a href="index.php?act=groups">User Groups</a></li>';
    foreach ($items as $label => $url) {
        if ($url) {
            echo '<li class="breadcrumb-item"><a href="' . $url . '">' . $label . '</a></li>';
        } else {
            echo '<li class="breadcrumb-item active">' . $label . '</li>';
        }
    }
    echo '</ol></nav>';
}

function ug_errors(array $errors): void
{
    if (!$errors) return;
    echo '<div class="alert alert-danger"><h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Please correct the following errors:</h6><ul class="mb-0">';
    foreach ($errors as $e) echo '<li>' . htmlspecialchars_uni($e) . '</li>';
    echo '</ul></div>';
}

function ug_switch(object $form, string $name, string $label, mixed $checked): void
{
    echo '<div class="form-check form-switch mb-2">';
    echo $form->generate_check_box($name, 1, $label, ['checked' => $checked, 'class' => 'form-check-input']);
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

    echo '<div class="container mt-4">';
    ug_breadcrumb(['Add New Group' => '']);
    ug_errors($errors ?? []);

    echo '<div class="card border-0 shadow-sm">';
    echo '<div class="card-header bg-primary text-white py-3">';
    echo '<h5 class="mb-0"><i class="fas fa-users me-2"></i>Add New User Group</h5>';
    echo '</div>';
    echo '<div class="card-body p-4">';

    $form = new Form('index.php?act=groups&action=add', 'post', 'addGroupForm');

    echo '<div class="row g-4">';

    // Left column
    echo '<div class="col-lg-6">';

    echo '<div class="mb-4">';
    echo '<label class="form-label fw-semibold">Group Title <span class="text-danger">*</span></label>';
    echo $form->generate_text_box('title', $mybb->get_input('title'), ['class' => 'form-control form-control-lg', 'placeholder' => 'Enter group title']);
    echo '<div class="form-text">The name that will identify this user group</div>';
    echo '</div>';

    echo '<div class="mb-4">';
    echo '<label class="form-label fw-semibold">Short Description</label>';
    echo $form->generate_text_box('description', $mybb->get_input('description'), ['class' => 'form-control', 'placeholder' => 'Brief description']);
    echo '</div>';

    echo '</div>';

    // Right column
    echo '<div class="col-lg-6">';

    echo '<div class="mb-4">';
    echo '<label class="form-label fw-semibold">Username Style</label>';
    echo $form->generate_text_box('namestyle', $mybb->get_input('namestyle') ?: '{username}', ['class' => 'form-control', 'placeholder' => '{username}']);
    echo '<div class="form-text">Use <code>{username}</code> to represent the user\'s name</div>';
    echo '</div>';

    echo '<div class="mb-4">';
    echo '<label class="form-label fw-semibold">Default User Title</label>';
    echo $form->generate_text_box('usertitle', $mybb->get_input('usertitle'), ['class' => 'form-control', 'placeholder' => 'Default title for users']);
    echo '</div>';

    echo '<div class="mb-4">';
    echo '<label class="form-label fw-semibold">Group Image</label>';
    echo $form->generate_text_box('image', $mybb->get_input('image'), ['class' => 'form-control', 'placeholder' => 'path/to/image.png']);
    echo '<div class="form-text">Use <strong>{lang}</strong> for language-specific images</div>';
    echo '</div>';

    echo '</div>';
    echo '</div>'; // row

    // Copy permissions
    echo '<div class="card border-0 bg-light mt-3">';
    echo '<div class="card-body">';
    echo '<h6 class="mb-3"><i class="fas fa-copy me-2"></i>Copy Permissions</h6>';

    $options = [0 => 'Create with default permissions (no copying)'];
    $q = $db->sql_query_prepared("SELECT gid, title FROM usergroups WHERE gid != '1' ORDER BY title");
    while ($q && ($ug = $db->fetch_array($q))) {
        $options[$ug['gid']] = htmlspecialchars_uni($ug['title']);
    }

    echo '<label class="form-label fw-semibold">Copy permissions from existing group</label>';
    echo $form->generate_select_box('copyfrom', $options, $mybb->get_input('copyfrom'), ['class' => 'form-select']);
    echo '<div class="form-text">Optionally copy all permissions from an existing group</div>';
    echo '</div>';
    echo '</div>';

    // Submit
    echo '<div class="text-center mt-4">';
    echo $form->generate_submit_button('Create User Group', ['class' => 'btn btn-primary btn-lg px-5 me-2']);
    echo '<a href="index.php?act=groups" class="btn btn-outline-secondary btn-lg px-4">Cancel</a>';
    echo '</div>';

    $form->end();
    echo '</div>'; // card-body
    echo '</div>'; // card
    echo '</div>'; // container

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
            $errors[] = 'error_missing_title';
        if (my_strpos($mybb->get_input('namestyle'), '{username}') === false)
            $errors[] = 'error_missing_namestyle_username';
        if ($mybb->get_input('moderate') == 1 && $mybb->get_input('invite') == 1)
            $errors[] = 'error_cannot_have_both_types';

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
				'max_screenshots' => max(0, (int)$mybb->input['max_screenshots']),
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

    echo '<div class="container mt-4">';
    ug_breadcrumb(['Edit Group' => '']);
    ug_errors($errors ?? []);

    echo '<div class="card border-0 shadow-sm mb-4">';
    echo '<div class="card-header bg-primary text-white py-3">';
    echo '<h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Edit User Group: ' . htmlspecialchars_uni($usergroup['title']) . '</h5>';
    echo '</div>';
    echo '<div class="card-body">';

    $form = new Form("index.php?act=groups&action=edit&amp;gid={$usergroup['gid']}", 'post', 'userGroupForm');

    // Tabs
    $tabs = [
        'general'           => '<i class="fas fa-cog me-1"></i> General',
        'forums_posts'      => '<i class="fas fa-comments me-1"></i> Forums & Posts',
        'users_permissions' => '<i class="fas fa-user-shield me-1"></i> Users & Permissions',
        'misc'              => '<i class="fas fa-star me-1"></i> Miscellaneous',
        'modcp'             => '<i class="fas fa-gavel me-1"></i> Moderator CP',
    ];

    echo '<ul class="nav nav-tabs mb-4" role="tablist">';
    $first = true;
    foreach ($tabs as $id => $title) {
        echo '<li class="nav-item"><a class="nav-link' . ($first ? ' active' : '') . '" data-bs-toggle="tab" href="#tab_' . $id . '">' . $title . '</a></li>';
        $first = false;
    }
    echo '</ul><div class="tab-content">';

    // ── General tab ─────────────────────────────────────────
    echo '<div class="tab-pane fade show active" id="tab_general">';
    echo '<div class="row g-4">';

    echo '<div class="col-md-6">';
    echo '<div class="mb-3"><label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>';
    echo $form->generate_text_box('title', $mybb->input['title'], ['class' => 'form-control']);
    echo '</div>';
    echo '<div class="mb-3"><label class="form-label fw-semibold">Short Description</label>';
    echo $form->generate_text_box('description', $mybb->input['description'], ['class' => 'form-control']);
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-6">';
    echo '<div class="mb-3"><label class="form-label fw-semibold">Username Style</label>';
    echo '<div class="form-text mb-1">Use {username} to represent the users name</div>';
    echo $form->generate_text_box('namestyle', $mybb->input['namestyle'], ['class' => 'form-control']);
    echo '</div>';
    echo '<div class="mb-3"><label class="form-label fw-semibold">Default User Title</label>';
    echo $form->generate_text_box('usertitle', $mybb->input['usertitle'], ['class' => 'form-control']);
    echo '</div>';
    echo '<div class="mb-3"><label class="form-label fw-semibold">Group Image</label>';
    echo '<div class="form-text mb-1">Use {lang} for language-specific images</div>';
    echo $form->generate_text_box('image', $mybb->input['image'], ['class' => 'form-control']);
    echo '</div>';
    echo '</div>';
    echo '</div>'; // row

    echo '<div class="row mt-3">';
    echo '<div class="col-md-6">';
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-sliders-h me-2"></i>General Options</h6>';
    ug_switch($form, 'showforumteam', 'Show this group on forum team page', $mybb->input['showforumteam']);
    ug_switch($form, 'isbannedgroup', 'This is a banned group', $mybb->input['isbannedgroup']);
    echo '</div>';
    
	echo '<div class="col-md-6">';
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-shield-alt me-2"></i>Administration Options</h6>';
    ug_switch($form, 'issupermod',     'Users are super moderators', $mybb->input['issupermod']);
    ug_switch($form, 'canstaffpanel',  'Can access Staff Panel', $mybb->input['canstaffpanel']);
    ug_switch($form, 'cansettingspanel','Can access Settings Panel', $mybb->input['cansettingspanel']);
    echo '</div>';
    echo '</div>';
	
    echo '</div>'; // general tab

    // ── Forums & Posts tab ───────────────────────────────────
    echo '<div class="tab-pane fade" id="tab_forums_posts">';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-eye me-2"></i>Viewing Options</h6>';
    ug_switch($form, 'canview',           'Can view board?',                $mybb->input['canview']);
    ug_switch($form, 'canviewthreads',    'Can view threads?',              $mybb->input['canviewthreads']);
    ug_switch($form, 'cansearch',         'Can search forums?',             $mybb->input['cansearch']);
    ug_switch($form, 'candlattachments',  'Can download attachments?',      $mybb->input['candlattachments']);
    ug_switch($form, 'canviewboardclosed','Can view board when closed?',    $mybb->input['canviewboardclosed']);
    echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-paper-plane me-2"></i>Posting Options</h6>';
    ug_switch($form, 'canpostthreads', 'Can post new threads?',         $mybb->input['canpostthreads']);
    ug_switch($form, 'canpostreplys',  'Can post replies to threads?',  $mybb->input['canpostreplys']);

    
    echo '</div>';

    echo '<div class="col-md-6">';
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-edit me-2"></i>Editing Options</h6>';
    ug_switch($form, 'caneditposts',       'Can edit own posts?',        $mybb->input['caneditposts']);
    ug_switch($form, 'candeleteposts',     'Can delete own posts?',      $mybb->input['candeleteposts']);
    ug_switch($form, 'candeletethreads',   'Can delete own threads?',    $mybb->input['candeletethreads']);
    ug_switch($form, 'caneditattachments', 'Can edit own attachments?',  $mybb->input['caneditattachments']);
    echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-paper-clip me-2"></i>Attachments</h6>';
    ug_switch($form, 'canpostattachments', 'Can post attachments?', $mybb->input['canpostattachments']);
    echo '<div class="mb-3 mt-3"><label class="form-label fw-semibold">Attachment Quota (KB)</label>';
    echo '<div class="form-text mb-1">0 for unlimited</div>';
    echo $form->generate_numeric_field('attachquota', $mybb->input['attachquota'], ['class' => 'form-control']);
    echo '</div>';
	
	
	echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-camera me-2"></i>Screenshots</h6>';
    echo '<div class="mb-3"><label class="form-label fw-semibold">Max Screenshots per Upload</label>';
    echo '<div class="form-text mb-1">Maximum number of screenshots a user can upload per torrent. 0 = not allowed.</div>';
    echo $form->generate_numeric_field('max_screenshots', $mybb->input['max_screenshots'] ?? 3, ['class' => 'form-control', 'min' => 0, 'max' => 299]);
    echo '</div>';
	
	
    echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-poll me-2"></i>Poll Options</h6>';
    ug_switch($form, 'canpostpolls', 'Can post new polls?',      $mybb->input['canpostpolls']);
    ug_switch($form, 'canvotepolls', 'Can vote on polls?',       $mybb->input['canvotepolls']);
    ug_switch($form, 'canundovotes', 'Can undo own poll votes?', $mybb->input['canundovotes']);
    echo '</div>';
    echo '</div>';
    echo '</div>'; // forums_posts tab

    // ── Users & Permissions tab ──────────────────────────────
    echo '<div class="tab-pane fade" id="tab_users_permissions">';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-envelope-open-text me-2"></i>Private Messaging</h6>';
    ug_switch($form, 'canusepms',         'Can use Private Messaging?',     $mybb->input['canusepms']);
    ug_switch($form, 'cansendpms',        'Can send Private Messages?',     $mybb->input['cansendpms']);
    ug_switch($form, 'cantrackpms',       'Can track Private Messages?',    $mybb->input['cantrackpms']);
    ug_switch($form, 'candenypmreceipts', 'Can deny read receipts?',        $mybb->input['candenypmreceipts']);
    ug_switch($form, 'canoverridepm',     'Can bypass PM limits?',          $mybb->input['canoverridepm']);
    echo '<div class="mb-3 mt-3"><label class="form-label fw-semibold">PM Quota</label>';
    echo '<div class="form-text mb-1">0 for unlimited</div>';
    echo $form->generate_numeric_field('pmquota', $mybb->input['pmquota'], ['class' => 'form-control']);
    echo '</div>';
    echo '<div class="mb-3"><label class="form-label fw-semibold">Max PM Recipients</label>';
    echo $form->generate_numeric_field('maxpmrecipients', $mybb->input['maxpmrecipients'], ['class' => 'form-control']);
    echo '</div>';
    echo '</div>';
    echo '<div class="col-md-6">';
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-at me-2"></i>Email Options</h6>';
    ug_switch($form, 'cansendemail',         'Can send email to other users?',      $mybb->input['cansendemail']);
    ug_switch($form, 'cansendemailoverride', 'Can override email flood check?',     $mybb->input['cansendemailoverride']);
    echo '</div>';
    echo '</div>';
    echo '</div>'; // users_permissions tab

    // ── Misc tab ─────────────────────────────────────────────
    echo '<div class="tab-pane fade" id="tab_misc">';
    echo '<div class="row"><div class="col-md-6">';
    //echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-star me-2"></i>Miscellaneous</h6>';
   
    //echo '<div class="mb-3"><label class="form-label fw-semibold">Number of Stars</label>';
    //echo $form->generate_numeric_field('stars', $mybb->input['stars'], ['class' => 'form-control']);
    //echo '</div>';
	
    echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-info-circle me-2"></i>Information Options</h6>';
    ug_switch($form, 'canviewwolinvis',     'Can view invisible users?',  $mybb->get_input('canviewwolinvis', MyBB::INPUT_INT));
    echo '</div></div>';
    echo '</div>'; // misc tab

    // ── Moderator CP tab ─────────────────────────────────────
    echo '<div class="tab-pane fade" id="tab_modcp">';
    echo '<div class="row"><div class="col-md-6">';
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-gavel me-2"></i>Moderation Options</h6>';
    ug_switch($form, 'modposts',       'Moderate new posts?',       $mybb->input['modposts']);
    ug_switch($form, 'modthreads',     'Moderate new threads?',     $mybb->input['modthreads']);
    ug_switch($form, 'mod_edit_posts', 'Moderate edited posts?',    $mybb->input['mod_edit_posts']);
    ug_switch($form, 'modattachments', 'Moderate new attachments?', $mybb->input['modattachments']);
    echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-trash-alt me-2"></i>Deletion Options</h6>';
    ug_switch($form, 'candeletetorrent', 'Can delete torrents?', $mybb->input['candeletetorrent']);
    echo '</div></div>';
    echo '</div>'; // modcp tab

    echo '</div>'; // tab-content

    echo '<div class="text-center mt-4">';
    echo $form->generate_submit_button('Save User Group', ['class' => 'btn btn-primary btn-lg px-5']);
    echo '</div>';

    $form->end();
    echo '</div>'; // card-body
    echo '</div>'; // card
    echo '</div>'; // container

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
        if ($gid && $order) {
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

    echo '<div class="container mt-4">';
    ug_breadcrumb(['User Groups' => '']);

    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h2 class="mb-0"><i class="fas fa-users me-2 text-primary"></i>User Groups Management</h2>';
    echo '<a href="index.php?act=groups&action=add" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add New Group</a>';
    echo '</div>';

    $form = new Form('index.php?act=groups', 'post', 'groupsForm');

    // Count primary users
    $primaryusers = $secondaryusers = [];
    $q = $db->sql_query_prepared('SELECT g.gid, COUNT(u.id) AS users FROM users u LEFT JOIN usergroups g ON (g.gid=u.usergroup) GROUP BY g.gid');
    while ($q && ($row = $db->fetch_array($q))) $primaryusers[$row['gid']] = $row['users'];

    $col = $db->type === 'pgsql' || $db->type === 'sqlite'
        ? "','||u.additionalgroups||',' LIKE '%,'||g.gid||',%'"
        : "CONCAT(',',u.additionalgroups,',') LIKE CONCAT('%,',g.gid,',%')";
    $q = $db->sql_query_prepared("SELECT g.gid, COUNT(u.id) AS users FROM users u LEFT JOIN usergroups g ON ({$col}) WHERE g.gid != '0' AND g.gid IS NOT NULL GROUP BY g.gid");
    while ($q && ($row = $db->fetch_array($q))) $secondaryusers[$row['gid']] = $row['users'];

    echo '<div class="card border-0 shadow-sm">';
    echo '<div class="card-header bg-white py-3"><h5 class="mb-0"><i class="fas fa-list me-2"></i>All User Groups</h5></div>';
    echo '<div class="card-body p-0"><div class="table-responsive">';
    echo '<table class="table table-hover mb-0"><thead class="table-light"><tr>';
    echo '<th width="40%">Group Information</th>';
    echo '<th width="15%" class="text-center">Users</th>';
    echo '<th width="15%" class="text-center">Order</th>';
    echo '<th width="30%" class="text-center">Actions</th>';
    echo '</tr></thead><tbody>';

    $q = $db->sql_query_prepared("SELECT * FROM usergroups ORDER BY disporder");
    while ($q && ($ug = $db->fetch_array($q))) {
        $icon = !empty($ug['image'])
            ? $ug['image']
            : ($ug['type'] > 1
                ? '<i class="fas fa-cog text-secondary me-2"></i>'
                : '<i class="fas fa-user text-primary me-2"></i>');

        $badge = $ug['type'] > 1
            ? '<span class="badge bg-secondary ms-2">Custom</span>'
            : '<span class="badge bg-primary ms-2">Default</span>';

        $numusers = ($primaryusers[$ug['gid']] ?? 0) + ($secondaryusers[$ug['gid']] ?? 0);

        echo '<tr>';

        // Group info
        echo '<td><div class="d-flex align-items-center">' . $icon;
        echo '<div><h6 class="mb-0">';
        echo '<a href="index.php?act=groups&action=edit&gid=' . $ug['gid'] . '" class="text-decoration-none">';
        echo format_name(htmlspecialchars_uni($ug['title']), $ug['gid']);
        echo '</a>' . $badge . '</h6>';
        if (!empty($ug['description'])) {
            echo '<p class="text-muted mb-0 small">' . htmlspecialchars_uni($ug['description']) . '</p>';
        }
        echo '</div></div></td>';

        // Users count
        echo '<td class="text-center align-middle"><span class="badge bg-info rounded-pill">' . ts_nf($numusers) . '</span></td>';

        // Display order
        echo '<td class="text-center align-middle">';
        if ($ug['showforumteam'] == 1) {
            echo '<input type="number" name="disporder[' . $ug['gid'] . ']" value="' . $ug['disporder'] . '" min="0" class="form-control form-control-sm w-75 mx-auto">';
        } else {
            echo '<span class="text-muted">-</span>';
        }
        echo '</td>';

        // Actions
        echo '<td class="text-center align-middle">';
        echo '<div class="dropdown">';
        echo '<button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">';
        echo '<i class="fas fa-cog me-1"></i>Options</button>';
        echo '<div class="dropdown-menu dropdown-menu-end shadow">';
        echo '<h6 class="dropdown-header">Manage Group</h6>';
        echo '<a class="dropdown-item" href="index.php?act=groups&action=edit&gid=' . $ug['gid'] . '"><i class="fas fa-edit me-2 text-primary"></i>Edit Group</a>';
        echo '<a class="dropdown-item" href="index.php?act=groups&action=search&results=1&conditions[usergroup]=' . $ug['gid'] . '"><i class="fas fa-users me-2 text-info"></i>List Users</a>';
        if ($ug['type'] > 1) {
            echo '<div class="dropdown-divider"></div>';
            echo '<a class="dropdown-item text-danger delete_employee" href="javascript:void(0)" data-emp-id="' . $ug['gid'] . '"><i class="fas fa-trash me-2"></i>Delete Group</a>';
        }
        echo '</div></div>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table></div></div>';

    echo '<div class="card-footer bg-light">';
    echo '<div class="d-flex justify-content-between align-items-center">';
    echo '<small class="text-muted"><i class="fas fa-info-circle me-1"></i>Custom groups can be reordered using the order field</small>';
    echo $form->generate_submit_button('Update Display Order', ['class' => 'btn btn-primary']);
    echo '</div></div>';
    echo '</div>'; // card

    $form->end();
    echo '</div>'; // container

    stdfoot();
    exit;
}