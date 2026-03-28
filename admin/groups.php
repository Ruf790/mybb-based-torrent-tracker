<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Array of usergroup permission fields and their default values.
$usergroup_permissions = array(
	"isbannedgroup" => 0,
	"canview" => 1,
	"canviewthreads" => 1,
	
	"candlattachments" => 1,
	"canviewboardclosed" => 1,
	"canpostthreads" => 1,
	"canpostreplys" => 1,
	"canpostattachments" => 1,
	"canratethreads" => 1,
	"modposts" => 0,
	"modthreads" => 0,
	"modattachments" => 0,
	"mod_edit_posts" => 0,
	"caneditposts" => 1,
	"candeletetorrent" => 1,
	"candeleteposts" => 1,
	"candeletethreads" => 1,
	"caneditattachments" => 1,
	"canviewdeletionnotice" => 1,
	"canpostpolls" => 1,
	"canvotepolls" => 1,
	"canundovotes" => 0,
	"canusepms" => 1,
	"cansendpms" => 1,
	"cantrackpms" => 1,
	"candenypmreceipts" => 1,
	"pmquota" => 100,
	"maxpmrecipients" => 5,
	"cansendemail" => 1,
	"cansendemailoverride" => 0,
	//"maxemails" => 4,
	//"emailfloodtime" => 5,
	//"canviewmemberlist" => 1,
	//"canviewcalendar" => 1,
	//"canaddevents" => 1,
	//"canbypasseventmod" => 0,
	//"canmoderateevents" => 0,
	//"canviewonline" => 1,
	"canviewwolinvis" => 0,
	//"canviewonlineips" => 0,
	"cansettingspanel" => 0,
	"issupermod" => 0,
	"canuserdetails" => 0,
	"cansearch" => 1,
	//"canusercp" => 1,
	//"canuploadavatars" => 1,
	//"canratemembers" => 1,
	//"canchangename" => 0,
	//"canbeinvisible" => 1,
	//"canbereported" => 0,
	//"canchangewebsite" => 1,
	"showforumteam" => 0,
	//"usereputationsystem" => 1,
	//"cangivereputations" => 1,
	//"candeletereputations" => 1,
	//"reputationpower" => 1,
	//"maxreputationsday" => 5,
	//"maxreputationsperuser" => 0,
	//"maxreputationsperthread" => 0,
	//"candisplaygroup" => 0,
	"attachquota" => 5000,
	//"cancustomtitle" => 0,
	//"canwarnusers" => 0,
	//"canreceivewarnings" => 1,
	//"maxwarningsday" => 0,
	"canstaffpanel" => 0,
	//"showinbirthdaylist" => 0,
	"canoverridepm" => 0,
	//"canusesig" => 0,
	//"canusesigxposts" => 0,
	//"signofollow" => 0,
	//"edittimelimit" => 0,
	"maxposts" => 0
	//"showmemberlist" => 1,
	//"canmanageannounce" => 0,
	//"canmanagemodqueue" => 0,
	//"canmanagereportedcontent" => 0,
	//"canviewmodlogs" => 0,
	//"caneditprofiles" => 0,
	//"canbanusers" => 0,
	//"canviewwarnlogs" => 0,
	//"canuseipsearch" => 0
);




define("IN_MYBB", 1);
define("IN_ADMINCP", 1);
define ('TSF_FORUMS_TSSEv56', true);
define ('TSF_FORUMS_GLOBAL_TSSEv56', true);
define ('TSF_VERSION', 'v1.5 by xam');


// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}




require_once $thispath . 'include/class_page.php';
require_once $thispath . 'include/class_form.php';
require_once $thispath . 'include/class_table.php';



// Include the layout generation class overrides for this style
if (file_exists('include/style.php')) {
    require_once 'include/style.php';
}

// Map style classes to their default implementations
$classMap = [
    'Page'          => DefaultPage::class,
    'Table'         => DefaultTable::class,
    'Form'          => DefaultForm::class,
    'FormContainer' => DefaultFormContainer::class,
];

// Create class aliases for any classes not overridden by the style
foreach ($classMap as $styleClass => $defaultClass) {
    if (!class_exists($styleClass, false)) {
        if (class_exists($defaultClass)) {
            class_alias($defaultClass, $styleClass);
        } else {
            throw new RuntimeException(
                sprintf('Required class %s not found while creating alias for %s', 
                        $defaultClass, 
                        $styleClass
                )
            );
        }
    }
}

$page = new Page();








$page->add_breadcrumb_item('User Groups', "index.php?act=groups");

if($mybb->input['action'] == "add" || !$mybb->input['action'])
{
	$sub_tabs['manage_groups'] = array(
		'title' => 'Manage User Groups',
		'link' => "index.php?act=groups",
		'description' => 'Here you can manage the various user groups on your board. In addition, for staff groups, you can manage their display order on the forum team page. Leave all as \"0\" sorts the group alphabetically. If you change the order of these groups, be sure to submit the form at the bottom of the page'
	);
	$sub_tabs['add_group'] = array(
		'title' => 'Add New User Group',
		'link' => "index.php?act=groups&action=add",
		'description' => 'Here you can create a new user group and optionally copy the permissions from another user group. After saving the user group you will be taken to the full edit page for this user group'
	);
}

$plugins->run_hooks("admin_user_groups_begin");







if($mybb->input['action'] == "add")
{
    $plugins->run_hooks("admin_user_groups_add");

    if($mybb->request_method == "post")
    {
        if(!trim($mybb->input['title']))
        {
            $errors[] = 'You did not enter a title for this new user group';
        }

        if(my_strpos($mybb->input['namestyle'], "{username}") === false)
        {
            $errors[] = 'The username style must contain {username}';
        }

        if(!$errors)
        {
            $new_usergroup = array(
                "type" => 2,
                "title" => $db->escape_string($mybb->input['title']),
                "description" => $db->escape_string($mybb->input['description']),
                "namestyle" => $db->escape_string($mybb->input['namestyle']),
                "usertitle" => $db->escape_string($mybb->input['usertitle']),
				"image" => $db->escape_string($mybb->input['image']),
                "disporder" => 0
            );

            // Set default permissions
            if($mybb->input['copyfrom'] == 0)
            {
                $new_usergroup = array_merge($new_usergroup, $usergroup_permissions);
            }
            // Copying permissions from another group
            else
            {
                $query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('copyfrom', MyBB::INPUT_INT)."'");
                $existing_usergroup = $db->fetch_array($query);
                foreach(array_keys($usergroup_permissions) as $field)
                {
                    $new_usergroup[$field] = $existing_usergroup[$field];
                }
            }

            $plugins->run_hooks("admin_user_groups_add_commit");

            $gid = $db->insert_query("usergroups", $new_usergroup);

            $plugins->run_hooks("admin_user_groups_add_commit_end");

            // Are we copying permissions? If so, copy all forum permissions too
            if($mybb->input['copyfrom'] > 0)
            {
                $query = $db->simple_select("forumpermissions", "*", "gid='".$mybb->get_input('copyfrom', MyBB::INPUT_INT)."'");
                while($forum_permission = $db->fetch_array($query))
                {
                    unset($forum_permission['pid']);
                    $forum_permission['gid'] = $gid;
                    $db->insert_query("forumpermissions", $forum_permission);
                }
            }

            // Update the caches
            $cache->update_usergroups();
            $cache->update_forumpermissions();
			
			// Log admin action
			log_admin_action($gid, $mybb->input['title']);

            $groups = $cache->read('usergroups');
            $grouptitles = array_column($groups, 'title');

            $message = 'User group created successfully';
            if(in_array($mybb->input['title'], $grouptitles) && count(array_keys($grouptitles, $mybb->input['title'])) > 1)
            {
                $message = sprintf('Group created with duplicate title: %s', htmlspecialchars_uni($mybb->input['title']));
            }

            flash_message($message, 'success');
            admin_redirect("index.php?act=groups&action=edit&gid={$gid}");
        }
    }

    stdhead('Add New User Group');
	
	
	
	
	
	
	
	
	

    echo '<div class="container mt-4">';
    
    // Хлебные крошки
    echo '<nav aria-label="breadcrumb" class="mb-4">';
    echo '<ol class="breadcrumb">';
    echo '<li class="breadcrumb-item"><a href="index.php?module=home">Home</a></li>';
    echo '<li class="breadcrumb-item"><a href="index.php?act=groups">User Groups</a></li>';
    echo '<li class="breadcrumb-item active">Add New Group</li>';
    echo '</ol>';
    echo '</nav>';

    // Основная карточка
    echo '<div class="card border-0 shadow-sm">';
    echo '<div class="card-header bg-primary text-white py-3">';
    echo '<h5 class="mb-0"><i class="fas fa-users me-2"></i>Add New User Group</h5>';
    echo '</div>';
    echo '<div class="card-body p-4">';

    $form = new Form("index.php?act=groups&action=add", "post", "addGroupForm");

    // Вывод ошибок
    if($errors)
    {
        echo '<div class="alert alert-danger">';
        echo '<h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Please correct the following errors:</h6>';
        echo '<ul class="mb-0">';
        foreach($errors as $error)
        {
            echo '<li>' . $error . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    // Основная форма
    echo '<div class="row">';
    echo '<div class="col-lg-6">';
    
    // Название группы
    echo '<div class="mb-4">';
    echo '<label class="form-label fw-semibold">Group Title <span class="text-danger">*</span></label>';
    echo $form->generate_text_box('title', $mybb->get_input('title'), array(
        'class' => 'form-control form-control-lg',
        'placeholder' => 'Enter group title',
        'required' => true
    ));
    echo '<div class="form-text">The name that will identify this user group</div>';
    echo '</div>';

    // Описание
    echo '<div class="mb-4">';
    echo '<label class="form-label fw-semibold">Short Description</label>';
    echo $form->generate_text_box('description', $mybb->get_input('description'), array(
        'class' => 'form-control',
        'placeholder' => 'Brief description of this group'
    ));
    echo '<div class="form-text">A short description of this group\'s purpose</div>';
    echo '</div>';

    echo '</div>';
    echo '<div class="col-lg-6">';

    // Стиль имени пользователя
    echo '<div class="mb-4">';
    echo '<label class="form-label fw-semibold">Username Style</label>';
    echo $form->generate_text_box('namestyle', $mybb->get_input('namestyle', MyBB::INPUT_STRING, array('default' => '{username}')), array(
        'class' => 'form-control',
        'placeholder' => '{username}'
    ));
    echo '<div class="form-text">Use {username} to represent the user\'s name. Example: <code>&lt;span style="color: blue;"&gt;{username}&lt;/span&gt;</code></div>';
    echo '</div>';

    // Заголовок пользователя по умолчанию
    echo '<div class="mb-4">';
    echo '<label class="form-label fw-semibold">Default User Title</label>';
    echo $form->generate_text_box('usertitle', $mybb->get_input('usertitle'), array(
        'class' => 'form-control',
        'placeholder' => 'Default title for users'
    ));
    echo '<div class="form-text">Displayed if user doesn\'t have a custom title</div>';
    echo '</div>';
	
	
	
	
	// Изображение группы
    echo '<div class="mb-4">';
    echo '<label class="form-label fw-semibold">Group Image</label>';
    echo $form->generate_text_box('image', $mybb->get_input('image'), array(
        'class' => 'form-control',
        'id' => 'image',
        'placeholder' => 'path/to/group/image.png'
    ));
    echo '<div class="form-text">Group image that will show on user posts. Use <strong>{lang}</strong> for language-specific images</div>';
    echo '</div>';
	
	
	
	
	
	
	
	
	
	
	
	
	

    echo '</div>';
    echo '</div>';

    // Копирование прав
    echo '<div class="row mt-4">';
    echo '<div class="col-12">';
    echo '<div class="card border-0 bg-light">';
    echo '<div class="card-body">';
    
    echo '<h6 class="card-title mb-3"><i class="fas fa-copy me-2"></i>Copy Permissions</h6>';
    
    $options[0] = 'Create with default permissions (no copying)';
    $query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
    while($usergroup = $db->fetch_array($query))
    {
        $options[$usergroup['gid']] = htmlspecialchars_uni($usergroup['title']);
    }
    
    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">Copy permissions from existing group</label>';
    echo $form->generate_select_box('copyfrom', $options, $mybb->get_input('copyfrom'), array(
        'class' => 'form-select',
        'id' => 'copyfrom'
    ));
    echo '<div class="form-text">Optionally copy all permissions and settings from an existing group</div>';
    echo '</div>';

    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Кнопки отправки
    echo '<div class="row mt-5">';
    echo '<div class="col-12 text-center">';
    echo '<div class="d-grid gap-2 d-md-block">';
    echo $form->generate_submit_button('Create User Group', array(
        'class' => 'btn btn-primary btn-lg px-5 me-2'
    ));
    echo '<a href="index.php?act=groups" class="btn btn-outline-secondary btn-lg px-4">Cancel</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    $form->end();

    echo '</div>'; // card-body
    echo '</div>'; // card
    
    // Информационная панель
    echo '<div class="row mt-4">';
    echo '<div class="col-md-4">';
    echo '<div class="card border-0 bg-info bg-opacity-10">';
    echo '<div class="card-body text-center">';
    echo '<i class="fas fa-users fa-2x text-info mb-2"></i>';
    echo '<h6>User Management</h6>';
    echo '<p class="small mb-0">Create groups to organize users with similar permissions</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="col-md-4">';
    echo '<div class="card border-0 bg-success bg-opacity-10">';
    echo '<div class="card-body text-center">';
    echo '<i class="fas fa-shield-alt fa-2x text-success mb-2"></i>';
    echo '<h6>Permission Control</h6>';
    echo '<p class="small mb-0">Set specific permissions for each user group</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="col-md-4">';
    echo '<div class="card border-0 bg-warning bg-opacity-10">';
    echo '<div class="card-body text-center">';
    echo '<i class="fas fa-copy fa-2x text-warning mb-2"></i>';
    echo '<h6>Easy Setup</h6>';
    echo '<p class="small mb-0">Copy permissions from existing groups for quick setup</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '</div>'; // container

    stdfoot();
}

















echo '<div class="container mt-3">';

if($mybb->input['action'] == "edit")
{
    $query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('gid', MyBB::INPUT_INT)."'");
    $usergroup = $db->fetch_array($query);

    if(!$usergroup)
    {
        flash_message('You have selected an invalid user group', 'error');
        admin_redirect("index.php?module=user-group");
    }
    else
    {
        if(preg_match("#<((m[^a])|(b[^diloru>])|(s[^aemptu >]))(\s*[^>]*)>#si", $mybb->get_input('namestyle')))
        {
            $errors[] = 'You cant use script, meta or base tags in the username style';
            $mybb->input['namestyle'] = $usergroup['namestyle'];
        }
    }

    $plugins->run_hooks("admin_user_groups_edit");

    if($mybb->request_method == "post")
    {
        if(!trim($mybb->get_input('title')))
        {
            $errors[] = 'error_missing_title';
        }

        if(my_strpos($mybb->get_input('namestyle'), "{username}") === false)
        {
            $errors[] = 'error_missing_namestyle_username';
        }

        if($mybb->get_input('moderate') == 1 && $mybb->get_input('invite') == 1)
        {
            $errors[] = 'error_cannot_have_both_types';
        }

        if(!$errors)
        {
            if($mybb->get_input('joinable') == 1)
            {
                if($mybb->get_input('moderate') == 1)
                {
                    $mybb->input['type'] = "4";
                }
                elseif($mybb->get_input('invite') == 1)
                {
                    $mybb->input['type'] = "5";
                }
                else
                {
                    $mybb->input['type'] = "3";
                }
            }
            else
            {
                $mybb->input['type'] = "2";
            }

            if($usergroup['type'] == 1)
            {
                $mybb->input['type'] = 1;
            }

            if($mybb->get_input('stars') < 1)
            {
                $mybb->input['stars'] = 0;
            }

            $updated_group = array(
                "type" => $mybb->get_input('type', MyBB::INPUT_INT),
                "title" => $db->escape_string($mybb->input['title']),
                "description" => $db->escape_string($mybb->input['description']),
                "namestyle" => $db->escape_string($mybb->input['namestyle']),
                "usertitle" => $db->escape_string($mybb->input['usertitle']),
                "image" => $db->escape_string($mybb->input['image']),
                "isbannedgroup" => $mybb->get_input('isbannedgroup', MyBB::INPUT_INT),
                "canview" => $mybb->get_input('canview', MyBB::INPUT_INT),
                "canviewthreads" => $mybb->get_input('canviewthreads', MyBB::INPUT_INT),
                "candlattachments" => $mybb->get_input('candlattachments', MyBB::INPUT_INT),
                "canviewboardclosed" => $mybb->get_input('canviewboardclosed', MyBB::INPUT_INT),
                "canpostthreads" => $mybb->get_input('canpostthreads', MyBB::INPUT_INT),
                "canpostreplys" => $mybb->get_input('canpostreplys', MyBB::INPUT_INT),
                "canpostattachments" => $mybb->get_input('canpostattachments', MyBB::INPUT_INT),
                "canratethreads" => $mybb->get_input('canratethreads', MyBB::INPUT_INT),
                "modposts" => $mybb->get_input('modposts', MyBB::INPUT_INT),
                "modthreads" => $mybb->get_input('modthreads', MyBB::INPUT_INT),
                "mod_edit_posts" => $mybb->get_input('mod_edit_posts', MyBB::INPUT_INT),
                "modattachments" => $mybb->get_input('modattachments', MyBB::INPUT_INT),
                "caneditposts" => $mybb->get_input('caneditposts', MyBB::INPUT_INT),
                "candeletetorrent" => $mybb->get_input('candeletetorrent', MyBB::INPUT_INT),
                "candeleteposts" => $mybb->get_input('candeleteposts', MyBB::INPUT_INT),
                "candeletethreads" => $mybb->get_input('candeletethreads', MyBB::INPUT_INT),
                "caneditattachments" => $mybb->get_input('caneditattachments', MyBB::INPUT_INT),
                "canviewdeletionnotice" => $mybb->get_input('canviewdeletionnotice', MyBB::INPUT_INT),
                "canpostpolls" => $mybb->get_input('canpostpolls', MyBB::INPUT_INT),
                "canvotepolls" => $mybb->get_input('canvotepolls', MyBB::INPUT_INT),
                "canundovotes" => $mybb->get_input('canundovotes', MyBB::INPUT_INT),
                "canusepms" => $mybb->get_input('canusepms', MyBB::INPUT_INT),
                "cansendpms" => $mybb->get_input('cansendpms', MyBB::INPUT_INT),
                "cantrackpms" => $mybb->get_input('cantrackpms', MyBB::INPUT_INT),
                "candenypmreceipts" => $mybb->get_input('candenypmreceipts', MyBB::INPUT_INT),
                "pmquota" => $mybb->get_input('pmquota', MyBB::INPUT_INT),
                "maxpmrecipients" => $mybb->get_input('maxpmrecipients', MyBB::INPUT_INT),
                "cansendemail" => $mybb->get_input('cansendemail', MyBB::INPUT_INT),
                "cansendemailoverride" => $mybb->get_input('cansendemailoverride', MyBB::INPUT_INT),
                "cansettingspanel" => $mybb->get_input('cansettingspanel', MyBB::INPUT_INT),
				"canviewwolinvis" => $mybb->get_input('canviewwolinvis', MyBB::INPUT_INT),
                "issupermod" => $mybb->get_input('issupermod', MyBB::INPUT_INT),
                "canuserdetails" => $mybb->get_input('canuserdetails', MyBB::INPUT_INT),
                "cansearch" => $mybb->get_input('cansearch', MyBB::INPUT_INT),
                "showforumteam" => $mybb->get_input('showforumteam', MyBB::INPUT_INT),
                "attachquota" => $mybb->get_input('attachquota', MyBB::INPUT_INT),
                "canstaffpanel" => $mybb->get_input('canstaffpanel', MyBB::INPUT_INT),
                "canoverridepm" => $mybb->get_input('canoverridepm', MyBB::INPUT_INT),
                "maxposts" => $mybb->get_input('maxposts', MyBB::INPUT_INT)
            );

            $plugins->run_hooks("admin_user_groups_edit_commit");
            $db->update_query("usergroups", $updated_group, "gid='{$usergroup['gid']}'");

            
			
			// Update the caches
			$cache->update_usergroups();
			$cache->update_forumpermissions();

			// Log admin action
			log_admin_action($usergroup['gid'], $mybb->input['title']);

			$groups = $cache->read('usergroups');
			$grouptitles = array_column($groups, 'title');

			$message = 'The selected user group has been updated successfully';
			if(in_array($mybb->input['title'], $grouptitles) && count(array_keys($grouptitles, $mybb->input['title'])) > 1)
			{
				$message = sprintf('The selected user group has been updated successfully. Please note that there are multiple user groups named '.htmlspecialchars_uni($mybb->input['title']).'');
			}
			
			flash_message($message, 'success');
			admin_redirect("index.php?act=groups");
			
			
			
			
			
        }
    }
    else
    {
        // Заполняем поля из базы данных, если форма не была отправлена
        if($usergroup['type'] == "3")
        {
            $usergroup['joinable'] = 1;
            $usergroup['moderate'] = 0;
            $usergroup['invite'] = 0;
        }
        elseif($usergroup['type'] == "4")
        {
            $usergroup['joinable'] = 1;
            $usergroup['moderate'] = 1;
            $usergroup['invite'] = 0;
        }
        elseif($usergroup['type'] == "5")
        {
            $usergroup['joinable'] = 1;
            $usergroup['moderate'] = 0;
            $usergroup['invite'] = 1;
        }
        else
        {
            $usergroup['joinable'] = 0;
            $usergroup['moderate'] = 0;
            $usergroup['invite'] = 0;
        }

        $mybb->input = array_merge($mybb->input, $usergroup);
    }
    
    
	
	
	stdhead('Edit User Group');
	
	
	
	// Хлебные крошки
    echo '<nav aria-label="breadcrumb" class="mb-4">';
    echo '<ol class="breadcrumb">';
    echo '<li class="breadcrumb-item"><a href="index.php?module=home">Home</a></li>';
    echo '<li class="breadcrumb-item"><a href="index.php?act=groups">User Groups</a></li>';
    echo '<li class="breadcrumb-item active">Edit Group</li>';
    echo '</ol>';
    echo '</nav>';
	
	

    echo '<div class="card border-0 shadow-sm mb-4">';
    echo '<div class="card-header bg-primary text-white py-3">';
    echo '<h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Edit User Group: ' . htmlspecialchars_uni($usergroup['title']) . '</h5>';
    echo '</div>';
    echo '<div class="card-body">';

    $form = new Form("index.php?act=groups&action=edit&amp;gid={$usergroup['gid']}", "post", "userGroupForm");

    if($errors)
    {
        echo '<div class="alert alert-danger">';
        foreach($errors as $error)
        {
            echo '<div class="error">' . $lang->$error . '</div>';
        }
        echo '</div>';
    }

    $tabs = array(
        "general" => '<i class="fas fa-cog me-1"></i> General',
        "forums_posts" => '<i class="fas fa-comments me-1"></i> Forums & Posts',
        "users_permissions" => '<i class="fas fa-user-shield me-1"></i> Users & Permissions',
        "misc" => '<i class="fas fa-star me-1"></i> Miscellaneous',
        "modcp" => '<i class="fas fa-gavel me-1"></i> Moderator CP'
    );

    echo '<ul class="nav nav-tabs mb-4" role="tablist">';
    $first = true;
    foreach($tabs as $id => $title)
    {
        echo '<li class="nav-item">';
        echo '<a class="nav-link' . ($first ? ' active' : '') . '" data-bs-toggle="tab" href="#tab_' . $id . '">' . $title . '</a>';
        echo '</li>';
        $first = false;
    }
    echo '</ul>';

    echo '<div class="tab-content">';

    // General Tab
    echo '<div class="tab-pane fade show active" id="tab_general">';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    
    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>';
    echo $form->generate_text_box('title', $mybb->input['title'], array('class' => 'form-control'));
    echo '</div>';

    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">Short Description</label>';
    echo $form->generate_text_box('description', $mybb->input['description'], array('class' => 'form-control'));
    echo '</div>';

    echo '</div>';
    echo '<div class="col-md-6">';

    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">Username Style</label>';
    echo '<div class="form-text mb-2">Use {username} to represent the users name</div>';
    echo $form->generate_text_box('namestyle', $mybb->input['namestyle'], array('class' => 'form-control'));
    echo '</div>';

    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">Default User Title</label>';
    echo $form->generate_text_box('usertitle', $mybb->input['usertitle'], array('class' => 'form-control'));
    echo '</div>';
    
    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">Group Image</label>';
    echo '<div class="form-text mb-2">Use {lang} for language-specific images</div>';
    echo $form->generate_text_box('image', $mybb->input['image'], array('class' => 'form-control'));
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Public Joinable Options
    

    // General Options
    echo '<div class="row mt-4">';
    echo '<div class="col-12">';
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-sliders-h me-2"></i>General Options</h6>';
    
    $general_options = array(
        $form->generate_check_box("showforumteam", 1, 'Show this group on forum team page',
            array("checked" => $mybb->input['showforumteam'], 'class' => 'form-check-input')),
        $form->generate_check_box("isbannedgroup", 1, 'This is a banned group',
            array("checked" => $mybb->input['isbannedgroup'], 'class' => 'form-check-input'))
    );

    foreach($general_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';

    // Admin Options
    echo '<div class="row mt-4">';
    echo '<div class="col-12">';
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-shield-alt me-2"></i>Administration Options</h6>';
    
    $admin_options = array(
        $form->generate_check_box("canuserdetails", 1, 'Can update user details',
            array("checked" => $mybb->input['canuserdetails'], 'class' => 'form-check-input')),
        $form->generate_check_box("issupermod", 1, 'Users are super moderators',
            array("checked" => $mybb->input['issupermod'], 'class' => 'form-check-input')),
        $form->generate_check_box("canstaffpanel", 1, 'Can access Staff Panel',
            array("checked" => $mybb->input['canstaffpanel'], 'class' => 'form-check-input')),
        $form->generate_check_box("cansettingspanel", 1, 'Can access Settings Panel',
            array("checked" => $mybb->input['cansettingspanel'], 'class' => 'form-check-input'))
    );

    foreach($admin_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';

    echo '</div>'; // закрываем general tab

    // Forums & Posts Tab
    echo '<div class="tab-pane fade" id="tab_forums_posts">';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-eye me-2"></i>Viewing Options</h6>';
    $viewing_options = array(
        $form->generate_check_box("canview", 1, 'Can view board?', array("checked" => $mybb->input['canview'], 'class' => 'form-check-input')),
        $form->generate_check_box("canviewthreads", 1, 'Can view threads?', array("checked" => $mybb->input['canviewthreads'], 'class' => 'form-check-input')),
        $form->generate_check_box("cansearch", 1, 'Can search forums?', array("checked" => $mybb->input['cansearch'], 'class' => 'form-check-input')),
        $form->generate_check_box("candlattachments", 1, 'Can download attachments?', array("checked" => $mybb->input['candlattachments'], 'class' => 'form-check-input')),
        $form->generate_check_box("canviewboardclosed", 1, 'Can view board when closed?', array("checked" => $mybb->input['canviewboardclosed'], 'class' => 'form-check-input'))
    );
    foreach($viewing_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }
    
    echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-paper-plane me-2"></i>Posting/Rating Options</h6>';
    $posting_options = array(
        $form->generate_check_box("canpostthreads", 1, 'Can post new threads?', array("checked" => $mybb->input['canpostthreads'], 'class' => 'form-check-input')),
        $form->generate_check_box("canpostreplys", 1, 'Can post replies to threads?', array("checked" => $mybb->input['canpostreplys'], 'class' => 'form-check-input')),
        $form->generate_check_box("canratethreads", 1, 'Can rate threads?', array("checked" => $mybb->input['canratethreads'], 'class' => 'form-check-input'))
    );
    foreach($posting_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }
    
    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">Maximum Posts Per Day</label>';
    echo '<div class="form-text mb-2">The total number of posts allowed per user per day. 0 for unlimited.</div>';
    echo $form->generate_numeric_field('maxposts', $mybb->input['maxposts'], array('class' => 'form-control'));
    echo '</div>';

    echo '</div>';
    echo '<div class="col-md-6">';
    
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-edit me-2"></i>Editing Options</h6>';
    $editing_options = array(
        $form->generate_check_box("caneditposts", 1, 'Can edit own posts?', array("checked" => $mybb->input['caneditposts'], 'class' => 'form-check-input')),
        $form->generate_check_box("candeleteposts", 1, 'Can delete own posts?', array("checked" => $mybb->input['candeleteposts'], 'class' => 'form-check-input')),
        $form->generate_check_box("candeletethreads", 1, 'Can delete own threads?', array("checked" => $mybb->input['candeletethreads'], 'class' => 'form-check-input')),
        $form->generate_check_box("caneditattachments", 1, 'Can edit own attachments?', array("checked" => $mybb->input['caneditattachments'], 'class' => 'form-check-input'))
    );
    foreach($editing_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }
    
    echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-paper-clip me-2"></i>Attachment Options</h6>';
    $attachment_options = array(
        $form->generate_check_box("canpostattachments", 1, 'Can post attachments?', array("checked" => $mybb->input['canpostattachments'], 'class' => 'form-check-input'))
    );
    foreach($attachment_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }
    
    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">Attachment Quota</label>';
    echo '<div class="form-text mb-2">Total disk space allowed for attachments (in Kilobytes). 0 for unlimited.</div>';
    echo $form->generate_numeric_field('attachquota', $mybb->input['attachquota'], array('class' => 'form-control'));
    echo '</div>';
    
    echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-poll me-2"></i>Poll Options</h6>';
    $poll_options = array(
        $form->generate_check_box("canpostpolls", 1, 'Can post new polls?', array("checked" => $mybb->input['canpostpolls'], 'class' => 'form-check-input')),
        $form->generate_check_box("canvotepolls", 1, 'Can vote on polls?', array("checked" => $mybb->input['canvotepolls'], 'class' => 'form-check-input')),
        $form->generate_check_box("canundovotes", 1, 'Can undo own poll votes?', array("checked" => $mybb->input['canundovotes'], 'class' => 'form-check-input'))
    );
    foreach($poll_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>'; // закрываем forums & posts tab
    
    // Users & Permissions Tab
    echo '<div class="tab-pane fade" id="tab_users_permissions">';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-envelope-open-text me-2"></i>Private Messaging Options</h6>';
    $pm_options = array(
        $form->generate_check_box("canusepms", 1, 'Can use Private Messaging System?', array("checked" => $mybb->input['canusepms'], 'class' => 'form-check-input')),
        $form->generate_check_box("cansendpms", 1, 'Can send Private Messages?', array("checked" => $mybb->input['cansendpms'], 'class' => 'form-check-input')),
        $form->generate_check_box("cantrackpms", 1, 'Can track Private Messages?', array("checked" => $mybb->input['cantrackpms'], 'class' => 'form-check-input')),
        $form->generate_check_box("candenypmreceipts", 1, 'Can deny read receipts?', array("checked" => $mybb->input['candenypmreceipts'], 'class' => 'form-check-input')),
        $form->generate_check_box("canoverridepm", 1, 'Can bypass PM limits?', array("checked" => $mybb->input['canoverridepm'], 'class' => 'form-check-input'))
    );
    foreach($pm_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }
    
    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">PM Quota</label>';
    echo '<div class="form-text mb-2">Maximum number of PMs allowed in folders. 0 for unlimited.</div>';
    echo $form->generate_numeric_field('pmquota', $mybb->input['pmquota'], array('class' => 'form-control'));
    echo '</div>';
    
    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">Max PM Recipients</label>';
    echo '<div class="form-text mb-2">Maximum number of recipients allowed per PM. 0 for unlimited.</div>';
    echo $form->generate_numeric_field('maxpmrecipients', $mybb->input['maxpmrecipients'], array('class' => 'form-control'));
    echo '</div>';

    echo '</div>';
    echo '<div class="col-md-6">';
    
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-at me-2"></i>Email Options</h6>';
    $email_options = array(
        $form->generate_check_box("cansendemail", 1, 'Can send email to other users?', array("checked" => $mybb->input['cansendemail'], 'class' => 'form-check-input')),
        $form->generate_check_box("cansendemailoverride", 1, 'Can override email flood check?', array("checked" => $mybb->input['cansendemailoverride'], 'class' => 'form-check-input'))
    );
    foreach($email_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
    echo '</div>'; // закрываем users & permissions tab

    // Miscellaneous Tab
    echo '<div class="tab-pane fade" id="tab_misc">';
    echo '<div class="row">';
    echo '<div class="col-12">';
    
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-star me-2"></i>Miscellaneous Options</h6>';
    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold">Number of Stars</label>';
    echo '<div class="form-text mb-2">The number of stars this group displays.</div>';
    echo $form->generate_numeric_field('stars', $mybb->input['stars'], array('class' => 'form-control'));
    echo '</div>';
    
    echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-info-circle me-2"></i>Information Options</h6>';
    $misc_options = array(
        $form->generate_check_box("canviewwolinvis", 1, 'Can view invisible users?', array("checked" => $mybb->get_input('canviewwolinvis', MyBB::INPUT_INT))),
		$form->generate_check_box("canviewdeletionnotice", 1, 'Can view deletion notice?', array("checked" => $mybb->input['canviewdeletionnotice'], 'class' => 'form-check-input'))
    );
    foreach($misc_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>'; // закрываем misc tab
    
    // Moderator CP Tab
    echo '<div class="tab-pane fade" id="tab_modcp">';
    echo '<div class="row">';
    echo '<div class="col-12">';
    
    echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-gavel me-2"></i>Moderation Options</h6>';
    $mod_options = array(
        $form->generate_check_box("modposts", 1, 'Moderate new posts?', array("checked" => $mybb->input['modposts'], 'class' => 'form-check-input')),
        $form->generate_check_box("modthreads", 1, 'Moderate new threads?', array("checked" => $mybb->input['modthreads'], 'class' => 'form-check-input')),
        $form->generate_check_box("mod_edit_posts", 1, 'Moderate edited posts?', array("checked" => $mybb->input['mod_edit_posts'], 'class' => 'form-check-input')),
        $form->generate_check_box("modattachments", 1, 'Moderate new attachments?', array("checked" => $mybb->input['modattachments'], 'class' => 'form-check-input'))
    );
    foreach($mod_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }
    
    echo '<h6 class="border-bottom pb-2 mt-4 mb-3"><i class="fas fa-trash-alt me-2"></i>Deletion Options</h6>';
    $deletion_options = array(
        $form->generate_check_box("candeletetorrent", 1, 'Can delete torrents?', array("checked" => $mybb->input['candeletetorrent'], 'class' => 'form-check-input'))
    );
    foreach($deletion_options as $option)
    {
        echo '<div class="form-check form-switch mb-2">';
        echo $option;
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>'; // закрываем modcp tab
    
    echo '</div>'; // закрываем tab-content

    // Кнопка сохранения
    echo '<div class="text-center mt-4">';
    echo $form->generate_submit_button('Save User Group',
        array('class' => 'btn btn-primary btn-lg px-5'));
    echo '</div>';

    $form->end();
	
    echo '</div>'; // закрываем card-body
    echo '</div>'; // закрываем card
	stdfoot();
}

echo '</div>'; // закрываем container




























if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('gid', MyBB::INPUT_INT)."'");
	$usergroup = $db->fetch_array($query);

	if(!$usergroup)
	{
		flash_message('You have selected an invalid user group', 'error');
		admin_redirect("index.php?act=groups");
	}
	if($usergroup['type'] == 1)
	{
		flash_message('Default groups cannot be deleted', 'error');
		admin_redirect("index.php?act=groups");
	}

	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?act=groups");
	}

	$plugins->run_hooks("admin_user_groups_delete");

	if($mybb->request_method == "post")
	{
		if($usergroup['isbannedgroup'] == 1)
		{
			// If banned group, move users to default banned group
			$updated_users = array("usergroup" => 9);
		}
		else
		{
			// Move any users back to the registered group
			$updated_users = array("usergroup" => 1);
		}

		$db->update_query("users", $updated_users, "usergroup='{$usergroup['gid']}'");

		$updated_users = array("displaygroup" => "usergroup");
		$plugins->run_hooks("admin_user_groups_delete_commit");

		$db->update_query("users", $updated_users, "displaygroup='{$usergroup['gid']}'", "", true); // No quotes = displaygroup=usergroup

		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$query = $db->simple_select("users", "id", "','||additionalgroups||',' LIKE '%,{$usergroup['gid']},%'");
				break;
			default:
				$query = $db->simple_select("users", "id", "CONCAT(',',additionalgroups,',') LIKE '%,{$usergroup['gid']},%'");
		}
		while($user = $db->fetch_array($query))
		{
			//leave_usergroup($user['id'], $usergroup['gid']);
		}

		$db->update_query("banned", array("gid" => 9), "gid='{$usergroup['gid']}'");
		$db->update_query("banned", array("oldgroup" => 1), "oldgroup='{$usergroup['gid']}'");
		$db->update_query("banned", array("olddisplaygroup" => "oldgroup"), "olddisplaygroup='{$usergroup['gid']}'", "", true); // No quotes = displaygroup=usergroup

		$db->delete_query("forumpermissions", "gid='{$usergroup['gid']}'");
		
		$db->delete_query("moderators", "id='{$usergroup['gid']}' AND isgroup='1'");
		$db->delete_query("usergroups", "gid='{$usergroup['gid']}'");

		$plugins->run_hooks("admin_user_groups_delete_commit_end");

		
		$cache->update_moderators();
		$cache->update_usergroups();
		$cache->update_forumpermissions();

		// Log admin action
		log_admin_action($usergroup['gid'], $usergroup['title']);

		flash_message('The selected user group has been deleted successfully', 'success');
		//admin_redirect("index.php?act=groups");
		echo "The selected Group has been deleted successfully";
		
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-groups&amp;action=delete&amp;gid={$usergroup['gid']}", $lang->confirm_group_deletion);
	}
}

if($mybb->input['action'] == "disporder" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_user_groups_disporder");

	foreach($mybb->input['disporder'] as $gid=>$order)
	{
		$gid = (int)$gid;
		$order = (int)$order;
		if($gid != 0 && $order != 0)
		{
			$sql_array = array(
				'disporder' => $order,
			);
			$db->update_query('usergroups', $sql_array, "gid = '{$gid}'");
		}
	}

	// Log admin action
	log_admin_action();

	$plugins->run_hooks("admin_user_groups_disporder_commit");

	flash_message('The user group display orders have been updated successfully', 'success');
	admin_redirect("index.php?act=groups");
}

















if(!$mybb->input['action'])
{
    $plugins->run_hooks("admin_user_groups_start");

    if($mybb->request_method == "post")
    {
        if(!empty($mybb->input['disporder']))
        {
            foreach($mybb->input['disporder'] as $gid => $order)
            {
                $db->update_query("usergroups", array('disporder' => (int)$order), "gid='".(int)$gid."'");
            }

            $plugins->run_hooks("admin_user_groups_start_commit");

            $cache->update_usergroups();

            flash_message('The user group display orders have been updated successfully', 'success');
            admin_redirect("index.php?act=groups");
        }
    }

    stdhead('Manage User Groups');
	
	

	
	
    echo "	<script type=\"text/javascript\" src=\"scripts/deleteGroup.js\"></script>\n";
	
    
    echo '<div class="container mt-4">';
    
    // Хлебные крошки
    echo '<nav aria-label="breadcrumb" class="mb-4">';
    echo '<ol class="breadcrumb">';
    echo '<li class="breadcrumb-item"><a href="index.php?module=home">Home</a></li>';
    echo '<li class="breadcrumb-item active">User Groups</li>';
    echo '</ol>';
    echo '</nav>';

    // Заголовок и кнопка добавления
    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h2 class="mb-0"><i class="fas fa-users me-2 text-primary"></i>User Groups Management</h2>';
    echo '<a href="index.php?act=groups&action=add" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add New Group</a>';
    echo '</div>';

    $form = new Form("index.php?act=groups", "post", "groupsForm");

    $primaryusers = $secondaryusers = array();
    $query = $db->sql_query("
        SELECT g.gid, COUNT(u.id) AS users
        FROM users u
        LEFT JOIN usergroups g ON (g.gid=u.usergroup)
        GROUP BY g.gid
    ");
    while($groupcount = $db->fetch_array($query))
    {
        $primaryusers[$groupcount['gid']] = $groupcount['users'];
    }

    switch($db->type)
    {
        case "pgsql":
        case "sqlite":
            $query = $db->sql_query("
                SELECT g.gid, COUNT(u.id) AS users
                FROM users u
                LEFT JOIN usergroups g ON (','|| u.additionalgroups|| ',' LIKE '%,'|| g.gid|| ',%')
                WHERE g.gid != '0' AND g.gid is not NULL GROUP BY g.gid
            ");
            break;
        default:
            $query = $db->sql_query("
                SELECT g.gid, COUNT(u.id) AS users
                FROM users u
                LEFT JOIN usergroups g ON (CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%'))
                WHERE g.gid != '0' AND g.gid is not NULL GROUP BY g.gid
            ");
    }
    while($groupcount = $db->fetch_array($query))
    {
        $secondaryusers[$groupcount['gid']] = $groupcount['users'];
    }

   



    // Таблица групп
    echo '<div class="card border-0 shadow-sm">';
    echo '<div class="card-header bg-white py-3">';
    echo '<h5 class="mb-0"><i class="fas fa-list me-2"></i>All User Groups</h5>';
    echo '</div>';
    echo '<div class="card-body p-0">';

    echo '<div class="table-responsive">';
    echo '<table class="table table-hover mb-0">';
    echo '<thead class="table-light">';
    echo '<tr>';
    echo '<th width="40%">Group Information</th>';
    echo '<th width="15%" class="text-center">Users</th>';
    echo '<th width="15%" class="text-center">Order</th>';
    echo '<th width="30%" class="text-center">Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
	
	
	echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" type="text/css" media="screen" />';
   echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css" type="text/css" media="screen" />';

    $query = $db->simple_select("usergroups", "*", "", array('order_by' => 'disporder'));
    while($usergroup = $db->fetch_array($query))
    {
        
	     $group_type_icon = !empty($usergroup['image']) 
         ? $usergroup['image']
         : (isset($group_icons[$usergroup['gid']]) 
         ? '<i class="' . $group_icons[$usergroup['gid']] . '"></i>' 
         : ($usergroup['type'] > 1 
            ? '<i class="fas fa-cog text-secondary"></i>' 
            : '<i class="fas fa-user text-primary"></i>'));	
		
        
        $group_type_badge = ($usergroup['type'] > 1) 
            ? '<span class="badge bg-secondary ms-2">Custom</span>' 
            : '<span class="badge bg-primary ms-2">Default</span>';



        // Подсчет пользователей
        if(!isset($primaryusers[$usergroup['gid']])) $primaryusers[$usergroup['gid']] = 0;
        if(!isset($secondaryusers[$usergroup['gid']])) $secondaryusers[$usergroup['gid']] = 0;
        $numusers = $primaryusers[$usergroup['gid']] + $secondaryusers[$usergroup['gid']];

        
        // Подготовка опций для dropdown
         $del_group = ($usergroup['type'] > 1) 
            ? '<a class="delete_employee text-danger" data-emp-id="' . $usergroup['gid'] . '" href="javascript:void(0)">
                 <i class="fas fa-trash me-2"></i>Delete Group
               </a>' 
            : '';

			

        echo '<tr>';
        
        // Колонка с информацией о группе
        echo '<td>';
        echo '<div class="d-flex align-items-center">';
        echo $group_type_icon;
        echo '<div class="flex-grow-1">';
        echo '<h6 class="mb-0">';
        echo '<a href="index.php?act=groups&action=edit&amp;gid=' . $usergroup['gid'] . '" class="text-decoration-none">';
        echo format_name(htmlspecialchars_uni($usergroup['title']), $usergroup['gid']);
        echo '</a>';
        echo $group_type_badge;
        //echo $join_requests;
        echo '</h6>';
        if(!empty($usergroup['description']))
        {
            echo '<p class="text-muted mb-0 small">' . htmlspecialchars_uni($usergroup['description']) . '</p>';
        }
       
        echo '</div>';
        echo '</div>';
        echo '</td>';

        // Колонка с количеством пользователей
        echo '<td class="text-center align-middle">';
        echo '<span class="badge bg-info rounded-pill">' . ts_nf($numusers) . '</span>';
        echo '</td>';

        // Колонка с порядком отображения
        echo '<td class="text-center align-middle">';
        if($usergroup['showforumteam'] == 1)
        {
            echo '<input type="number" name="disporder[' . $usergroup['gid'] . ']" value="' . $usergroup['disporder'] . '" min="0" class="form-control form-control-sm w-75 mx-auto" />';
        }
        else
        {
            echo '<span class="text-muted">-</span>';
        }
        echo '</td>';

        // Колонка с действиями - Dropdown меню
        echo '<td class="text-center align-middle">';
        echo '<div class="dropdown">';
        echo '<button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
        echo '<i class="fas fa-cog me-1"></i>Options';
        echo '</button>';
        echo '<div class="dropdown-menu dropdown-menu-end shadow">';
        echo '<h6 class="dropdown-header">Manage Group</h6>';
        echo '<a class="dropdown-item" href="index.php?act=groups&action=edit&amp;gid=' . $usergroup['gid'] . '">';
        echo '<i class="fas fa-edit me-2 text-primary"></i>Edit Group';
        echo '</a>';
        echo '<a class="dropdown-item" href="index.php?act=groups&action=search&amp;results=1&amp;conditions[usergroup]=' . $usergroup['gid'] . '">';
        echo '<i class="fas fa-users me-2 text-info"></i>List Users';
        echo '</a>';
		
		
	
		
		
		
        echo '<div class="dropdown-divider"></div>';
        echo $del_group;
        echo '</div>'; // dropdown-menu
        echo '</div>'; // dropdown
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>'; // table-responsive
    echo '</div>'; // card-body

    // Футер с кнопкой сохранения
    echo '<div class="card-footer bg-light">';
    echo '<div class="row align-items-center">';
    echo '<div class="col-md-6">';
    echo '<small class="text-muted"><i class="fas fa-info-circle me-1"></i>Custom groups can be reordered using the order field</small>';
    echo '</div>';
    echo '<div class="col-md-6 text-end">';
    echo $form->generate_submit_button('Update Display Order', array('class' => 'btn btn-primary'));
    echo '</div>';
    echo '</div>';
    echo '</div>'; // card-footer

    echo '</div>'; // card

    $form->end();

    // Легенда
    echo '<div class="card border-0 bg-light mt-4">';
    echo '<div class="card-body">';
    echo '<h6 class="card-title mb-3"><i class="fas fa-info-circle me-2"></i>Legend</h6>';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<p class="mb-1"><i class="fas fa-id-card text-primary me-2"></i> Default User Group</p>';
    echo '<p class="mb-1"><i class="fas fa-gear text-secondary me-2"></i> Custom User Group</p>';
    echo '</div>';
    echo '<div class="col-md-6">';
    echo '<p class="mb-1"><span class="badge bg-danger me-2"><i class="fas fa-clock"></i></span> Pending Join Requests</p>';
    echo '<p class="mb-1"><span class="badge bg-info me-2">#</span> Number of Users</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '</div>'; // container

   

    stdfoot();
}