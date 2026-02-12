<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */
 

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





//foreach(array('action', 'do', 'module') as $input)
//{
	//if(!isset($mybb->input[$input]))
	//{
		//$mybb->input[$input] = '';
	//}
//}





$lang->load('forum_management');





$page->add_breadcrumb_item('Forum Management', "index.php?act=management");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "edit" || $mybb->input['action'] == "copy" || $mybb->input['action'] == "permissions" || !$mybb->input['action'])
{
	if(!empty($mybb->input['fid']) && ($mybb->input['action'] == "management" || $mybb->input['action'] == "edit" || $mybb->input['action'] == "copy" || !$mybb->input['action']))
	{
	
		
		$sub_tabs['view_forum'] = array(
			'title' =>'View Forum',
			'link' => "index.php?act=management&fid=".$mybb->input['fid'],
			'description' => 'Here you can view sub forums, quickly edit permissions and add moderators to your forum'
		);

		$sub_tabs['add_child_forum'] = array(
			'title' => 'Add Child Forum',
			'link' => "index.php?act=management&action=add&pid=".$mybb->input['fid'],
			'description' => 'Here you can view sub forums, quickly edit permissions and add moderators to your forum'
		);

		$sub_tabs['edit_forum_settings'] = array(
			'title' => 'Edit Forum Settings',
			'link' => "index.php?act=management&action=edit&fid=".$mybb->input['fid'],
			'description' => 'Here you can edit an existing forums settings and its permissions'
		);

		$sub_tabs['copy_forum'] = array(
			'title' => 'Copy Forum',
			'link' => "index.php?act=management&action=copy&fid=".$mybb->input['fid'],
			'description' => 'Here you can copy forum settings or permissions from an existing forum to another or to a new forum'
		);
	}
	else
	{
		$sub_tabs['forum_management'] = array(
			'title' => 'Forum Management',
			'link' => "index.php?act=management",
			'description' => 'This section allows you to manage the categories and forums on your board. You can manage forum permissions and forum-specific moderators as well. If you change the display order for one or more forums or categories, make sure you submit the form at the bottom of the page'
		);

		$sub_tabs['add_forum'] = array(
			'title' => 'Add New Forum',
			'link' => "index.php?act=management&action=add",
			'description' => 'Here you can add a new forum or category to your board. You may also set initial permissions for this forum'
		);
	}
}

$plugins->run_hooks("admin_forum_management_begin");




if($mybb->input['action'] == "copy")
{
    $plugins->run_hooks("admin_forum_management_copy");

    if($mybb->request_method == "post")
    {
        $from = $mybb->get_input('from', MyBB::INPUT_INT);
        $to = $mybb->get_input('to', MyBB::INPUT_INT);

        // Find the source forum
        $query = $db->simple_select("tsf_forums", '*', "fid='{$from}'");
        $from_forum = $db->fetch_array($query);
        if(!$db->num_rows($query))
        {
            $errors[] ='error_invalid_source_forum';
        }

        if($to == -1)
        {
            // Create a new forum
            if(empty($mybb->input['title']))
            {
                $errors[] = 'You need to give your new forum a name';
            }

            if($mybb->input['pid'] == -1 && $mybb->input['type'] == 'f')
            {
                $errors[] = 'You must select a parent forum';
            }

            if(!$errors)
            {
                if($mybb->input['pid'] < 0)
                {
                    $mybb->input['pid'] = 0;
                }
                $new_forum = $from_forum;
                unset($new_forum['fid'], $new_forum['threads'], $new_forum['posts'], $new_forum['lastpost'], $new_forum['lastposter'], $new_forum['lastposteruid'], $new_forum['lastposttid'], $new_forum['lastpostsubject'], $new_forum['unapprovedthreads'], $new_forum['unapprovedposts']);
                $new_forum['name'] = $mybb->input['title'];
                $new_forum['description'] = $mybb->input['description'];
                $new_forum['type'] = $mybb->input['type'];
                $new_forum['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
                $new_forum['parentlist'] = '';

                foreach($new_forum as $key => $value)
                {
                    $new_forum[$key] = $db->escape_string($value);
                }

                $to = $db->insert_query("tsf_forums", $new_forum);

                // Generate parent list
                $parentlist = make_parent_list($to);
                $updatearray = array(
                    'parentlist' => $parentlist
                );
                $db->update_query("tsf_forums", $updatearray, "fid='{$to}'");
            }
        }
        elseif($mybb->input['copyforumsettings'] == 1)
        {
            // Copy settings to existing forum
            $query = $db->simple_select("tsf_forums", '*', "fid='{$to}'");
            $to_forum = $db->fetch_array($query);
            if(!$db->num_rows($query))
            {
                $errors[] = 'Invalid destination forum';
            }

            if(!$errors)
            {
                $new_forum = $from_forum;
                unset($new_forum['fid'], $new_forum['threads'], $new_forum['posts'], $new_forum['lastpost'], $new_forum['lastposter'], $new_forum['lastposteruid'], $newbb->input['lastposttid'], $new_forum['lastpostsubject'], $new_forum['unapprovedthreads'], $new_forum['unapprovedposts']);
                $new_forum['name'] = $to_forum['name'];
                $new_forum['description'] = $to_forum['description'];
                $new_forum['pid'] = $to_forum['pid'];
                $new_forum['parentlist'] = $to_forum['parentlist'];

                foreach($new_forum as $key => $value)
                {
                    $new_forum[$key] = $db->escape_string($value);
                }

                $db->update_query("tsf_forums", $new_forum, "fid='{$to}'");
            }
        }
        else
        {
            $new_forum['name'] = null;
        }

        if(!$errors)
        {
            // Copy permissions
            if(isset($mybb->input['copygroups']) && is_array($mybb->input['copygroups']) && count($mybb->input['copygroups']) > 0)
            {
                foreach($mybb->input['copygroups'] as $gid)
                {
                    $groups[] = (int)$gid;
                }
                $groups = implode(',', $groups);
                $query = $db->simple_select("forumpermissions", '*', "fid='{$from}' AND gid IN ({$groups})");
                $db->delete_query("forumpermissions", "fid='{$to}' AND gid IN ({$groups})", 1);
                while($permissions = $db->fetch_array($query))
                {
                    unset($permissions['pid']);
                    $permissions['fid'] = $to;

                    $db->insert_query("forumpermissions", $permissions);
                }

                // Log admin action
                log_admin_action($from, $from_forum['name'], $to, $new_forum['name'], $groups);
            }
            else
            {
                // Log admin action (no group permissions)
                log_admin_action($from, $from_forum['name'], $to, $new_forum['name']);
            }

            $plugins->run_hooks("admin_forum_management_copy_commit");

            $cache->update_forums();
            $cache->update_forumpermissions();

            flash_message($lang->forum_management['success_forum_copied'], 'success');
            admin_redirect("index.php?act=management&action=edit&fid={$to}");
        }
    }

    // ============================================
    // СОВРЕМЕННЫЙ ДИЗАЙН СТРАНИЦЫ
    // ============================================
    
    stdhead('Copy Forum');
    
    echo "    <link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
    echo "    <link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
    echo "    <script type=\"text/javascript\" src=\"scripts/admincp.js?ver=1821\"></script>\n";
    echo "    <script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";

    echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
    echo "  <script type=\"text/javascript\">\n".
            "//<![CDATA[\n".
            "    document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
            "//]]>\n".
            "</script>\n";
    
    // Современный дизайн
    echo '
    <!-- Современный дизайн админ-панели -->
    <div class="admin-container">
        <div class="container mt-3">
            
            <!-- Хлебные крошки -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb bg-white rounded-3 shadow-sm py-2 px-3">
                    <li class="breadcrumb-item">
                        <a href="index.php?act=management" class="text-decoration-none text-primary">
                            <i class="fas fa-home me-1"></i>Forums
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <i class="fas fa-copy me-1"></i>Copy Forum
                    </li>
                </ol>
            </nav>

            <!-- Основной контейнер -->
            <div class="card border-0 shadow-lg rounded-3 overflow-hidden">
                
                <!-- Заголовок страницы -->
                <div class="card-header bg-info text-white py-4 px-5">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="header-icon bg-white bg-opacity-20 rounded-circle p-3 me-4">
                                <i class="fas fa-clone fa-2x"></i>
                            </div>
                            <div>
                                <h1 class="h3 mb-2 fw-bold">
                                    <i class="fas fa-copy me-2"></i>
                                    Copy Forum Settings
                                </h1>
                                <p class="mb-0 opacity-85">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Duplicate forum settings and permissions to another forum
                                </p>
                            </div>
                        </div>
                        <div class="status-badges">
                            <span class="badge bg-white bg-opacity-25 px-3 py-2">
                                <i class="fas fa-clock me-1"></i>' . date('H:i') . '
                            </span>
                        </div>
                    </div>
                </div>';

    // Выводим ошибки если есть
    if(isset($errors) && $errors)
    {
        echo '
                <div class="card-body px-5 pt-4 pb-0">
                    <div class="alert alert-danger border-0 rounded-3 shadow-sm">
                        <div class="d-flex align-items-center">
                            <div class="alert-icon bg-danger bg-opacity-10 rounded-circle p-2 me-3">
                                <i class="fas fa-exclamation-triangle fa-lg text-danger"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">Please fix the following errors:</h6>';
                                foreach($errors as $error)
                                {
                                    echo '<p class="mb-1 small">' . htmlspecialchars_uni($error) . '</p>';
                                }
        echo '              </div>
                        </div>
                    </div>
                </div>';
    }
	
	
	
	
	
	$copy_data['type'] = "f";
	$copy_data['title'] = "";
	$copy_data['description'] = "";

	if(empty($mybb->input['pid']))
	{
		$copy_data['pid'] = "-1";
	}
	else
	{
		$copy_data['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
	}
	$copy_data['disporder'] = "1";
	$copy_data['from'] = $mybb->get_input('fid');
	$copy_data['to'] = -1;
	$copy_data['copyforumsettings'] = 0;
	$copy_data['copygroups'] = array();
	$copy_data['pid'] = 0;

	if($errors)
	{
		output_inline_error($errors);

		foreach($copy_data as $key => $value)
		{
			if(isset($mybb->input[$key]))
			{
				$copy_data[$key] = $mybb->input[$key];
			}
		}
	}

	$types = array(
		'f' => $lang->forum_management['forum'],
		'c' => $lang->forum_management['category']
	);

	$create_a_options_f = array(
		'id' => 'forum'
	);

	$create_a_options_c = array(
		'id' => 'category'
	);

	if($copy_data['type'] == "f")
	{
		$create_a_options_f['checked'] = true;
	}
	else
	{
		$create_a_options_c['checked'] = true;
	}
	
	
	
	
	
	
	$usergroupsZZ = array();

	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroupsZZ[$usergroup['gid']] = htmlspecialchars_uni($usergroup['title']);
	}

    echo '
                <!-- Содержимое формы -->
                <div class="card-body px-5 pb-5">
                    <div class="row">
                        <div class="col-lg-8">
                            <form method="post" action="index.php?act=management&action=copy" id="copyForumForm">
                                ' . generate_post_check() . '

                                <!-- Шаг 1: Выбор форумов -->
                                <div class="mb-5">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="step-number bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <span class="fw-bold">1</span>
                                        </div>
                                        <h5 class="mb-0 fw-bold text-dark">
                                            <i class="fas fa-exchange-alt me-2 text-info"></i>
                                            Select Forums
                                        </h5>
                                    </div>
                                    
                                    <div class="row g-4">
                                        <!-- Source Forum -->
                                        <div class="col-md-6">
                                            <div class="card border h-100">
                                                <div class="card-header bg-info bg-opacity-10 border-bottom py-3">
                                                    <h6 class="mb-0 fw-bold">
                                                        <i class="fas fa-download me-2 text-info"></i>
                                                        Source Forum
                                                        <span class="text-danger">*</span>
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">
                                                            <i class="fas fa-folder-open me-1"></i>
                                                            Copy FROM
                                                        </label>
                                                        ' . generate_forum_select('from', $copy_data['from'], array('id' => 'from', 'class' => 'form-select')) . '
                                                        <small class="text-muted">Forum to copy settings from</small>
                                                    </div>
                                                    <div class="source-info" id="sourceInfo" style="display: none;">
                                                        <hr class="my-3">
                                                        <div class="d-flex align-items-center">
                                                            <div class="forum-icon bg-info bg-opacity-10 rounded-circle p-2 me-3">
                                                                <i class="fas fa-folder text-info"></i>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-1" id="sourceForumName">-</h6>
                                                                <p class="mb-0 text-muted small" id="sourceForumDesc">-</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Destination Forum -->
                                        <div class="col-md-6">
                                            <div class="card border h-100">
                                                <div class="card-header bg-success bg-opacity-10 border-bottom py-3">
                                                    <h6 class="mb-0 fw-bold">
                                                        <i class="fas fa-upload me-2 text-success"></i>
                                                        Destination Forum
                                                        <span class="text-danger">*</span>
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">
                                                            <i class="fas fa-folder-plus me-1"></i>
                                                            Copy TO
                                                        </label>
                                                        ' . generate_forum_select('to', $copy_data['to'], array('id' => 'to', 'class' => 'form-select', 'main_option' => 'Create New Forum')) . '
                                                        <small class="text-muted">Forum to copy settings to</small>
                                                    </div>
                                                    
                                                    <!-- Новые настройки форума (показывается при выборе "Create New Forum") -->
                                                    <div class="new-forum-settings mt-4" id="newForumSettings" style="display: none;">
                                                        <hr class="my-3">
                                                        <h6 class="fw-bold mb-3">
                                                            <i class="fas fa-plus-circle me-2 text-success"></i>
                                                            New Forum Settings
                                                        </h6>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Forum Type</label>
                                                            <div class="d-flex gap-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="radio" name="type" id="typeForum" value="f" ' . ($copy_data['type'] == "f" ? 'checked' : '') . '>
                                                                    <label class="form-check-label" for="typeForum">
                                                                        <i class="fas fa-comments me-1"></i> Forum
                                                                    </label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="radio" name="type" id="typeCategory" value="c" ' . ($copy_data['type'] != "f" ? 'checked' : '') . '>
                                                                    <label class="form-check-label" for="typeCategory">
                                                                        <i class="fas fa-layer-group me-1"></i> Category
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                Title <span class="text-danger">*</span>
                                                            </label>
                                                            <input type="text" name="title" id="title" class="form-control" value="' . htmlspecialchars_uni($copy_data['title']) . '" placeholder="Enter forum name">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Description</label>
                                                            <textarea name="description" id="description" class="form-control" rows="2" placeholder="Optional forum description">' . htmlspecialchars_uni($copy_data['description']) . '</textarea>
                                                        </div>
                                                        
                                                        <div class="mb-3" id="parentForumField">
                                                            <label class="form-label fw-semibold">
                                                                Parent Forum <span class="text-danger">*</span>
                                                            </label>
                                                            ' . generate_forum_select('pid', $copy_data['pid'], array('id' => 'pid', 'class' => 'form-select', 'main_option' => 'None')) . '
                                                            <small class="text-muted">Categories don\'t need a parent forum</small>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Копирование настроек существующего форума -->
                                                    <div class="copy-settings mt-3" id="copySettings" style="display: none;">
                                                        <hr class="my-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" role="switch" name="copyforumsettings" id="copyforumsettings" value="1" ' . ($copy_data['copyforumsettings'] ? 'checked' : '') . '>
                                                            <label class="form-check-label fw-semibold" for="copyforumsettings">
                                                                <i class="fas fa-copy me-1"></i>
                                                                Copy Forum Settings
                                                            </label>
                                                            <small class="d-block text-muted">Overwrite destination forum settings with source settings</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Шаг 2: Выбор групп -->
                                <div class="mb-5">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="step-number bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <span class="fw-bold">2</span>
                                        </div>
                                        <h5 class="mb-0 fw-bold text-dark">
                                            <i class="fas fa-users me-2 text-warning"></i>
                                            Copy Permissions
                                        </h5>
                                    </div>
                                    
                                    <div class="card border">
                                        <div class="card-header bg-warning bg-opacity-10 border-bottom py-3">
                                            <h6 class="mb-0 fw-bold">
                                                <i class="fas fa-shield-alt me-2 text-warning"></i>
                                                User Group Permissions
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    
													<div class="mb-3">
    <label class="form-label fw-semibold">
        <i class="fas fa-user-group me-1"></i>
        Select User Groups
    </label>
    ' . generate_select_box('copygroups[]', $usergroupsZZ, $copy_data['copygroups'], array('id' => 'copygroups', 'multiple' => true, 'size' => 8, 'class' => 'form-select')) . '
    <small class="text-muted">Hold CTRL to select multiple groups</small>
</div>
													
													
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="selected-groups bg-light rounded p-3 h-100">
                                                        <h6 class="fw-bold mb-3">
                                                            <i class="fas fa-list-check me-2"></i>
                                                            Selected Groups
                                                        </h6>
                                                        <div id="selectedGroupsList" class="mb-3">
                                                            <p class="text-muted small mb-0">No groups selected</p>
                                                        </div>
                                                        <div class="mt-3">
                                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllGroups">
                                                                <i class="fas fa-check-double me-1"></i>Select All
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="deselectAllGroups">
                                                                <i class="fas fa-times me-1"></i>Deselect All
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Шаг 3: Подтверждение -->
                                <div>
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="step-number bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <span class="fw-bold">3</span>
                                        </div>
                                        <h5 class="mb-0 fw-bold text-dark">
                                            <i class="fas fa-check-circle me-2 text-success"></i>
                                            Review & Confirm
                                        </h5>
                                    </div>
                                    
                                    <div class="card border">
                                        <div class="card-header bg-success bg-opacity-10 border-bottom py-3">
                                            <h6 class="mb-0 fw-bold">
                                                <i class="fas fa-clipboard-check me-2 text-success"></i>
                                                Copy Summary
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="summary-content">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <h6 class="fw-bold text-muted mb-2">
                                                                <i class="fas fa-download me-1"></i>
                                                                From:
                                                            </h6>
                                                            <div class="d-flex align-items-center" id="summarySource">
                                                                <div class="forum-icon bg-info bg-opacity-10 rounded-circle p-2 me-3">
                                                                    <i class="fas fa-question text-info"></i>
                                                                </div>
                                                                <div>
                                                                    <p class="mb-0 fw-semibold" id="summarySourceName">Not selected</p>
                                                                    <p class="mb-0 text-muted small" id="summarySourceDesc">-</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <h6 class="fw-bold text-muted mb-2">
                                                                <i class="fas fa-upload me-1"></i>
                                                                To:
                                                            </h6>
                                                            <div class="d-flex align-items-center" id="summaryDestination">
                                                                <div class="forum-icon bg-success bg-opacity-10 rounded-circle p-2 me-3">
                                                                    <i class="fas fa-question text-success"></i>
                                                                </div>
                                                                <div>
                                                                    <p class="mb-0 fw-semibold" id="summaryDestinationName">Not selected</p>
                                                                    <p class="mb-0 text-muted small" id="summaryDestinationType">-</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mt-3">
                                                    <div class="col-12">
                                                        <div class="summary-details bg-light rounded p-3">
                                                            <h6 class="fw-bold mb-3">
                                                                <i class="fas fa-gears me-2"></i>
                                                                What will be copied:
                                                            </h6>
                                                            <ul class="list-group list-group-flush">
                                                                <li class="list-group-item d-flex align-items-center py-2" id="summarySettings">
                                                                    <i class="fas fa-cog text-muted me-3"></i>
                                                                    <span>Forum settings and properties</span>
                                                                    <span class="badge bg-info ms-auto" id="settingsStatus">No</span>
                                                                </li>
                                                                <li class="list-group-item d-flex align-items-center py-2" id="summaryPermissions">
                                                                    <i class="fas fa-shield-alt text-muted me-3"></i>
                                                                    <span>User group permissions</span>
                                                                    <span class="badge bg-info ms-auto" id="permissionsStatus">0 groups</span>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Кнопки отправки -->
                                <div class="mt-5 pt-4 border-top">
                                    <div class="d-flex justify-content-between">
                                        <a href="index.php?act=management" class="btn btn-outline-secondary px-4">
                                            <i class="fas fa-arrow-left me-2"></i>Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary px-5">
                                            <i class="fas fa-copy me-2"></i>
                                            Copy Forum Settings
                                            <span class="spinner-border spinner-border-sm ms-2 d-none" id="submitSpinner"></span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Боковая панель с информацией -->
                        <div class="col-lg-4">
                            <div class="sticky-top" style="top: 20px;">
                                <!-- Информационная карточка -->
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-primary text-white py-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-info-circle me-2"></i>How It Works
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="info-item d-flex mb-3">
                                            <div class="info-icon bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                                                <i class="fas fa-1 fa-sm"></i>
                                            </div>
                                            <div>
                                                <p class="mb-1 fw-semibold">Select Forums</p>
                                                <p class="mb-0 small text-muted">Choose source and destination forums</p>
                                            </div>
                                        </div>
                                        <div class="info-item d-flex mb-3">
                                            <div class="info-icon bg-warning bg-opacity-10 text-warning rounded-circle p-2 me-3">
                                                <i class="fas fa-2 fa-sm"></i>
                                            </div>
                                            <div>
                                                <p class="mb-1 fw-semibold">Choose Permissions</p>
                                                <p class="mb-0 small text-muted">Select which user groups to copy permissions from</p>
                                            </div>
                                        </div>
                                        <div class="info-item d-flex">
                                            <div class="info-icon bg-success bg-opacity-10 text-success rounded-circle p-2 me-3">
                                                <i class="fas fa-3 fa-sm"></i>
                                            </div>
                                            <div>
                                                <p class="mb-1 fw-semibold">Review & Copy</p>
                                                <p class="mb-0 small text-muted">Check summary and confirm the copy operation</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Быстрые подсказки -->
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-info text-white py-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-lightbulb me-2"></i>Quick Tips
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 small mb-3">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            <strong>New Forum:</strong> Select "Create New Forum" as destination
                                        </div>
                                        <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 small mb-3">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <strong>Existing Forum:</strong> Enable "Copy Forum Settings" to overwrite
                                        </div>
                                        <div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 small">
                                            <i class="fas fa-check-circle me-1"></i>
                                            <strong>Permissions:</strong> Hold CTRL to select multiple user groups
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    // JavaScript для интерактивности
    echo '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const fromSelect = document.getElementById("from");
        const toSelect = document.getElementById("to");
        const copySettings = document.getElementById("copySettings");
        const newForumSettings = document.getElementById("newForumSettings");
        const copygroups = document.getElementById("copygroups");
        const selectedGroupsList = document.getElementById("selectedGroupsList");
        
        // Обновление информации об исходном форуме
        function updateSourceInfo() {
            const selectedOption = fromSelect.options[fromSelect.selectedIndex];
            const sourceInfo = document.getElementById("sourceInfo");
            const sourceForumName = document.getElementById("sourceForumName");
            const summarySourceName = document.getElementById("summarySourceName");
            const summarySourceDesc = document.getElementById("summarySourceDesc");
            
            if (fromSelect.value !== "") {
                sourceInfo.style.display = "block";
                sourceForumName.textContent = selectedOption.text;
                summarySourceName.textContent = selectedOption.text;
                summarySourceDesc.textContent = "ID: " + fromSelect.value;
            } else {
                sourceInfo.style.display = "none";
                summarySourceName.textContent = "Not selected";
                summarySourceDesc.textContent = "-";
            }
        }
        
        // Обновление информации о целевом форуме
        function updateDestinationInfo() {
            const toValue = toSelect.value;
            const summaryDestinationName = document.getElementById("summaryDestinationName");
            const summaryDestinationType = document.getElementById("summaryDestinationType");
            
            // Показываем/скрываем блоки настроек
            if (toValue === "-1") {
                // Создание нового форума
                newForumSettings.style.display = "block";
                copySettings.style.display = "none";
                summaryDestinationName.textContent = "New Forum";
                summaryDestinationType.textContent = "Will be created";
            } else if (toValue !== "") {
                // Существующий форум
                newForumSettings.style.display = "none";
                copySettings.style.display = "block";
                const selectedOption = toSelect.options[toSelect.selectedIndex];
                summaryDestinationName.textContent = selectedOption.text;
                summaryDestinationType.textContent = "Existing forum (ID: " + toValue + ")";
            } else {
                // Не выбрано
                newForumSettings.style.display = "none";
                copySettings.style.display = "none";
                summaryDestinationName.textContent = "Not selected";
                summaryDestinationType.textContent = "-";
            }
            
            // Обновляем статус копирования настроек
            updateCopyStatus();
        }
        
        // Обновление выбранных групп
        function updateSelectedGroups() {
            const selectedOptions = Array.from(copygroups.selectedOptions);
            const permissionsStatus = document.getElementById("permissionsStatus");
            
            if (selectedOptions.length > 0) {
                selectedGroupsList.innerHTML = selectedOptions.map(option => 
                    `<span class="badge bg-primary me-1 mb-1">${option.text}</span>`
                ).join("");
                permissionsStatus.textContent = selectedOptions.length + " groups";
                permissionsStatus.className = "badge bg-success ms-auto";
            } else {
                selectedGroupsList.innerHTML = \'<p class="text-muted small mb-0">No groups selected</p>\';
                permissionsStatus.textContent = "0 groups";
                permissionsStatus.className = "badge bg-secondary ms-auto";
            }
        }
        
        // Обновление статуса копирования настроек
        function updateCopyStatus() {
            const copyforumsettings = document.getElementById("copyforumsettings");
            const settingsStatus = document.getElementById("settingsStatus");
            
            if (toSelect.value !== "-1" && toSelect.value !== "") {
                if (copyforumsettings.checked) {
                    settingsStatus.textContent = "Yes";
                    settingsStatus.className = "badge bg-success ms-auto";
                } else {
                    settingsStatus.textContent = "No";
                    settingsStatus.className = "badge bg-danger ms-auto";
                }
            } else {
                settingsStatus.textContent = "New forum";
                settingsStatus.className = "badge bg-info ms-auto";
            }
        }
        
        // Показать/скрыть поле родительского форума в зависимости от типа
        function toggleParentField() {
            const typeForum = document.getElementById("typeForum");
            const parentForumField = document.getElementById("parentForumField");
            
            if (typeForum.checked) {
                parentForumField.style.display = "block";
            } else {
                parentForumField.style.display = "none";
            }
        }
        
        // Кнопки выбора всех групп
        document.getElementById("selectAllGroups").addEventListener("click", function() {
            Array.from(copygroups.options).forEach(option => option.selected = true);
            updateSelectedGroups();
        });
        
        document.getElementById("deselectAllGroups").addEventListener("click", function() {
            Array.from(copygroups.options).forEach(option => option.selected = false);
            updateSelectedGroups();
        });
        
        // Инициализация событий
        fromSelect.addEventListener("change", updateSourceInfo);
        toSelect.addEventListener("change", updateDestinationInfo);
        copygroups.addEventListener("change", updateSelectedGroups);
        
        const copyforumsettings = document.getElementById("copyforumsettings");
        if (copyforumsettings) {
            copyforumsettings.addEventListener("change", updateCopyStatus);
        }
        
        const typeForum = document.getElementById("typeForum");
        const typeCategory = document.getElementById("typeCategory");
        if (typeForum && typeCategory) {
            typeForum.addEventListener("change", toggleParentField);
            typeCategory.addEventListener("change", toggleParentField);
        }
        
        // Инициализация формы
        updateSourceInfo();
        updateDestinationInfo();
        updateSelectedGroups();
        toggleParentField();
        
        // Обработчик отправки формы
        document.getElementById("copyForumForm").addEventListener("submit", function(e) {
            const submitSpinner = document.getElementById("submitSpinner");
            const submitBtn = this.querySelector(\'button[type="submit"]\');
            
            // Показываем спиннер
            submitSpinner.classList.remove("d-none");
            submitBtn.disabled = true;
            
            // Валидация
            if (!fromSelect.value) {
                e.preventDefault();
                alert("Please select a source forum");
                submitSpinner.classList.add("d-none");
                submitBtn.disabled = false;
                return;
            }
            
            if (!toSelect.value) {
                e.preventDefault();
                alert("Please select a destination forum");
                submitSpinner.classList.add("d-none");
                submitBtn.disabled = false;
                return;
            }
            
            if (toSelect.value === "-1") {
                // Проверка для нового форума
                const title = document.getElementById("title").value;
                if (!title.trim()) {
                    e.preventDefault();
                    alert("Please enter a title for the new forum");
                    submitSpinner.classList.add("d-none");
                    submitBtn.disabled = false;
                    return;
                }
                
                if (typeForum.checked && document.getElementById("pid").value === "") {
                    e.preventDefault();
                    alert("Please select a parent forum for regular forums");
                    submitSpinner.classList.add("d-none");
                    submitBtn.disabled = false;
                    return;
                }
            }
        });
    });
    </script>
    
    <style>
    .step-number {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        flex-shrink: 0;
    }
    
    .forum-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .info-icon {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .admin-container .card {
        border: none;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .admin-container .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .breadcrumb {
        background-color: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .btn {
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        border: none;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    }
    
    .form-select:focus, .form-control:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    
    .form-check-input:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .form-switch .form-check-input:focus {
        background-image: url("data:image/svg+xml,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%27-4 -4 8 8%27%3e%3ccircle r=%273%27 fill=%27%23fff%27/%3e%3c/svg%3e");
    }
    
    .sticky-top {
        position: sticky;
        z-index: 10;
    }
    
    .selected-groups {
        min-height: 200px;
    }
    
    .summary-details .list-group-item {
        background-color: transparent;
        border-color: rgba(0,0,0,0.05);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .fade-in {
        animation: fadeIn 0.3s ease forwards;
    }
    
    .badge {
        border-radius: 10px;
        padding: 0.35em 0.65em;
        font-weight: 600;
    }
    </style>';

    stdfoot();
}


if($mybb->input['action'] == "editmod")
{
	$query = $db->simple_select("moderators", "*", "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");
	$mod_data = $db->fetch_array($query);

	if(!$mod_data['id'])
	{
		flash_message($lang->forum_management['error_incorrect_moderator'], 'error');
		admin_redirect("index.php?act=management");
	}

	$plugins->run_hooks("admin_forum_management_editmod");

	if($mod_data['isgroup'])
	{
		$fieldname = "title";
	}
	else
	{
		$fieldname = "username";
	}

	if($mybb->request_method == "post")
	{
		$mid = $mybb->get_input('mid', MyBB::INPUT_INT);
		if(!$mid)
		{
			flash_message($lang->forum_management['error_incorrect_moderator'], 'error');
			admin_redirect("index.php?act=management");
		}

		if(!$errors)
		{
			$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
			$forum = get_forum($fid, 1);
			if($mod_data['isgroup'])
			{
				$mod = $groupscache[$mod_data['id']];
			}
			else
			{
				$mod = get_user($mod_data['id']);
			}
			$update_array = array(
				'fid' => (int)$fid,
				'caneditposts' => $mybb->get_input('caneditposts', MyBB::INPUT_INT),
				'cansoftdeleteposts' => $mybb->get_input('cansoftdeleteposts', MyBB::INPUT_INT),
				'canrestoreposts' => $mybb->get_input('canrestoreposts', MyBB::INPUT_INT),
				'candeleteposts' => $mybb->get_input('candeleteposts', MyBB::INPUT_INT),
				'cansoftdeletethreads' => $mybb->get_input('cansoftdeletethreads', MyBB::INPUT_INT),
				'canrestorethreads' => $mybb->get_input('canrestorethreads', MyBB::INPUT_INT),
				'candeletethreads' => $mybb->get_input('candeletethreads', MyBB::INPUT_INT),
				'canviewips' => $mybb->get_input('canviewips', MyBB::INPUT_INT),
				'canviewunapprove' => $mybb->get_input('canviewunapprove', MyBB::INPUT_INT),
				'canviewdeleted' => $mybb->get_input('canviewdeleted', MyBB::INPUT_INT),
				'canopenclosethreads' => $mybb->get_input('canopenclosethreads', MyBB::INPUT_INT),
				'canstickunstickthreads' => $mybb->get_input('canstickunstickthreads', MyBB::INPUT_INT),
				'canapproveunapprovethreads' => $mybb->get_input('canapproveunapprovethreads', MyBB::INPUT_INT),
				'canapproveunapproveposts' => $mybb->get_input('canapproveunapproveposts', MyBB::INPUT_INT),
				'canapproveunapproveattachs' => $mybb->get_input('canapproveunapproveattachs', MyBB::INPUT_INT),
				'canmanagethreads' => $mybb->get_input('canmanagethreads', MyBB::INPUT_INT),
				'canmanagepolls' => $mybb->get_input('canmanagepolls', MyBB::INPUT_INT),
				'canpostclosedthreads' => $mybb->get_input('canpostclosedthreads', MyBB::INPUT_INT),
				'canmovetononmodforum' => $mybb->get_input('canmovetononmodforum', MyBB::INPUT_INT),
				'canusecustomtools' => $mybb->get_input('canusecustomtools', MyBB::INPUT_INT),
				'canmanageannouncements' => $mybb->get_input('canmanageannouncements', MyBB::INPUT_INT),
				'canmanagereportedposts' => $mybb->get_input('canmanagereportedposts', MyBB::INPUT_INT),
				'canviewmodlog' => $mybb->get_input('canviewmodlog', MyBB::INPUT_INT)
			);

			$plugins->run_hooks("admin_forum_management_editmod_commit");

			$db->update_query("moderators", $update_array, "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");

			$cache->update_moderators();

			// Log admin action
			log_admin_action($fid, $forum['name'], $mid, $mod[$fieldname]);

			flash_message($lang->forum_management['success_moderator_updated'], 'success');
			admin_redirect("index.php?act=management&fid=".$mybb->get_input('fid', MyBB::INPUT_INT)."#tab_moderators");
		}
	}

	if($mod_data['isgroup'])
	{
		$query = $db->simple_select("usergroups", "title", "gid='{$mod_data['id']}'");
		$mod_data[$fieldname] = $db->fetch_field($query, 'title');
	}
	else
	{
		$query = $db->simple_select("users", "username", "id='{$mod_data['id']}'");
		$mod_data[$fieldname] = $db->fetch_field($query, 'username');
	}

	$sub_tabs = array();

	$sub_tabs['edit_mod'] = array(
		'title' => $lang->forum_management['edit_mod'],
		'link' => "index.php?act=management&action=editmod&mid=".$mybb->input['mid'],
		'description' => $lang->forum_management['edit_mod_desc']
	);

	$page->add_breadcrumb_item('forum_moderators', "index.php?act=management&amp;fid={$mod_data['fid']}#tab_moderators");
	$page->add_breadcrumb_item('edit_forum');
	
	

	
	stdhead('edit_mod');
	
	
	

	
echo '<div class="container mt-3">';
	
output_nav_tabs($sub_tabs, 'edit_mod');

$form = new Form("index.php?act=management&action=editmod", "post", "editModForm");
echo $form->generate_hidden_field("mid", $mod_data['mid']);

if($errors)
{
    output_inline_error($errors);
    $mod_data = $mybb->input;
}

echo '<div class="card border-0 shadow-sm">';
    echo '<div class="card-header bg-primary text-white py-3">';
        echo '<h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>'.sprintf($lang->forum_management['edit_mod_for'], htmlspecialchars_uni($mod_data[$fieldname])).'</h5>';
    echo '</div>';
    echo '<div class="card-body">';

// Форма выбора форума
$form_container = new FormContainer('');
echo '<div class="mb-4">'; // Добавляем отступ вместо set_class
$form_container->output_row(
    $lang->forum_management['forum'], 
    $lang->forum_management['forum_desc'], 
    $form->generate_forum_select('fid', $mod_data['fid'], array('id' => 'fid', 'class' => 'form-select')), 
    'fid'
);
echo '</div>';

// Moderator Permissions
echo '<div class="row mb-4">';
    echo '<div class="col-12">';
        echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-shield-alt me-2 text-warning"></i>'.$lang->forum_management['moderator_permissions'].'</h6>';
        echo '<div class="row">';

$moderator_permissions = array(
    array('caneditposts', $lang->forum_management['can_edit_posts'], 'fa-edit', 'primary'),
    array('cansoftdeleteposts', $lang->forum_management['can_soft_delete_posts'], 'fa-trash-alt', 'secondary'),
    array('canrestoreposts', $lang->forum_management['can_restore_posts'], 'fa-undo', 'success'),
    array('candeleteposts', $lang->forum_management['can_delete_posts'], 'fa-trash', 'danger'),
    array('cansoftdeletethreads', $lang->forum_management['can_soft_delete_threads'], 'fa-trash-alt', 'secondary'),
    array('canrestorethreads', $lang->forum_management['can_restore_threads'], 'fa-undo', 'success'),
    array('candeletethreads', $lang->forum_management['can_delete_threads'], 'fa-trash', 'danger'),
    array('canviewips', $lang->forum_management['can_view_ips'], 'fa-search', 'info'),
    array('canviewunapprove', $lang->forum_management['can_view_unapprove'], 'fa-eye-slash', 'warning'),
    array('canviewdeleted', $lang->forum_management['can_view_deleted'], 'fa-eye', 'info'),
    array('canopenclosethreads', $lang->forum_management['can_open_close_threads'], 'fa-lock-open', 'success'),
    array('canstickunstickthreads', $lang->forum_management['can_stick_unstick_threads'], 'fa-thumbtack', 'warning'),
    array('canapproveunapprovethreads', $lang->forum_management['can_approve_unapprove_threads'], 'fa-check-circle', 'success'),
    array('canapproveunapproveposts', $lang->forum_management['can_approve_unapprove_posts'], 'fa-check-circle', 'success'),
    array('canapproveunapproveattachs', $lang->forum_management['can_approve_unapprove_attachments'], 'fa-check-circle', 'success'),
    array('canmanagethreads', $lang->forum_management['can_manage_threads'], 'fa-tasks', 'primary'),
    array('canmanagepolls', $lang->forum_management['can_manage_polls'], 'fa-chart-bar', 'info'),
    array('canpostclosedthreads', $lang->forum_management['can_post_closed_threads'], 'fa-comment', 'success'),
    array('canmovetononmodforum', $lang->forum_management['can_move_to_other_forums'], 'fa-exchange-alt', 'warning'),
    array('canusecustomtools', $lang->forum_management['can_use_custom_tools'], 'fa-tools', 'primary')
);

foreach(array_chunk($moderator_permissions, 2) as $chunk)
{
    echo '<div class="col-md-6">';
    foreach($chunk as $permission)
    {
        echo '<div class="form-check form-switch mb-3">';
        echo generate_check_box($permission[0], 1, '', array(
            'checked' => $mod_data[$permission[0]],
            'id' => $permission[0],
            'class' => 'form-check-input'
        ));
        echo '<label class="form-check-label" for="'.$permission[0].'">';
        echo '<i class="fas '.$permission[2].' me-2 text-'.$permission[3].'"></i>';
        echo htmlspecialchars_uni($permission[1]);
        echo '</label>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div></div></div>';

// Moderator CP Permissions
echo '<div class="row mb-4">';
    echo '<div class="col-12">';
        echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-cog me-2 text-purple"></i>'.$lang->forum_management['moderator_cp_permissions'].'</h6>';
        echo '<p class="text-muted mb-3">'.$lang->forum_management['moderator_cp_permissions_desc'].'</p>';
        echo '<div class="row">';

$moderator_cp_permissions = array(
    array('canmanageannouncements', $lang->forum_management['can_manage_announcements'], 'fa-bullhorn', 'warning'),
    array('canmanagereportedposts', $lang->forum_management['can_manage_reported_posts'], 'fa-flag', 'danger'),
    array('canviewmodlog', $lang->forum_management['can_view_mod_log'], 'fa-history', 'info')
);

foreach(array_chunk($moderator_cp_permissions, 2) as $chunk)
{
    echo '<div class="col-md-6">';
    foreach($chunk as $permission)
    {
        echo '<div class="form-check form-switch mb-3">';
        echo generate_check_box($permission[0], 1, '', array(
            'checked' => $mod_data[$permission[0]],
            'id' => $permission[0],
            'class' => 'form-check-input'
        ));
        echo '<label class="form-check-label" for="'.$permission[0].'">';
        echo '<i class="fas '.$permission[2].' me-2 text-'.$permission[3].'"></i>';
        echo htmlspecialchars_uni($permission[1]);
        echo '</label>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div></div></div>';

$form_container->end();

echo '</div>'; // .card-body
echo '<div class="card-footer bg-light">';
    $buttons = array();
    $buttons[] = $form->generate_submit_button($lang->forum_management['save_mod'], array('class' => 'btn btn-primary px-4'));
    $buttons[] = $form->generate_reset_button($lang->reset, array('class' => 'btn btn-outline-secondary ms-2'));
    echo '<div class="d-flex">';
    $form->output_submit_wrapper($buttons);
    echo '</div>';
echo '</div>';

echo '</div>'; // .card

$form->end();


echo '
<style>
.form-switch .form-check-input {
    width: 3em;
    height: 1.5em;
}
.form-switch .form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
.card-header {
    border-radius: 0.375rem 0.375rem 0 0 !important;
}
.text-purple {
    color: #6f42c1 !important;
}
</style>';


echo '</div>'; // .container mt-3
	
	

	stdfoot();
}

if($mybb->input['action'] == "clear_permission")
{
	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);

	// User clicked no
	if(!empty($mybb->input['no']))
	{
		admin_redirect("index.php?act=management&fid={$fid}");
	}

	$plugins->run_hooks("admin_forum_management_clear_permission");

	if($mybb->request_method == "post")
	{
		if((!$fid || !$gid) && $pid)
		{
			$query = $db->simple_select("forumpermissions", "fid, gid", "pid='{$pid}'");
			$result = $db->fetch_array($query);
			$fid = $result['fid'];
			$gid = $result['gid'];
		}

		if($pid)
		{
			$db->delete_query("forumpermissions", "pid='{$pid}'");
		}
		else
		{
			$db->delete_query("forumpermissions", "gid='{$gid}' AND fid='{$fid}'");
		}

		$plugins->run_hooks('admin_forum_management_clear_permission_commit');

		$cache->update_forumpermissions();

		flash_message($lang->forum_management['success_custom_permission_cleared'], 'success');
		admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");
	}
}

if($mybb->input['action'] == "permissions")
{
	$plugins->run_hooks("admin_forum_management_permissions");

	if($mybb->request_method == "post")
	{
		$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
		$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
		$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
		$forum = get_forum($fid, 1);

		if((!$fid || !$gid) && $pid)
		{
			$query = $db->simple_select("forumpermissions", "fid, gid", "pid='{$pid}'");
			$result = $db->fetch_array($query);
			$fid = $result['fid'];
			$gid = $result['gid'];
			$forum = get_forum($fid, 1);
		}

		$update_array = $field_list = array();
		$fields_array = $db->show_fields_from("forumpermissions");
		if(isset($mybb->input['permissions']))
		{
			// User has set permissions for this group...
			foreach($fields_array as $field)
			{
				if(strpos($field['Field'], 'can') !== false || strpos($field['Field'], 'mod') !== false)
				{
					if(array_key_exists($field['Field'], $mybb->input['permissions']))
					{
						$update_array[$db->escape_string($field['Field'])] = (int)$mybb->input['permissions'][$field['Field']];
					}
					else
					{
						$update_array[$db->escape_string($field['Field'])] = 0;
					}
				}
			}
		}
		else
		{
			// Else, we assume that the group has no permissions...
			foreach($fields_array as $field)
			{
				if(strpos($field['Field'], 'can') !== false || strpos($field['Field'], 'mod') !== false)
				{
					$update_array[$db->escape_string($field['Field'])] = 0;
				}
			}
		}

		if($fid && !$pid)
		{
			$update_array['fid'] = $fid;
			$update_array['gid'] = $mybb->get_input('gid', MyBB::INPUT_INT);
			$db->insert_query("forumpermissions", $update_array);
		}

		$plugins->run_hooks("admin_forum_management_permissions_commit");

		if(!($fid && !$pid))
		{
			$db->update_query("forumpermissions", $update_array, "pid='{$pid}'");
		}

		$cache->update_forumpermissions();

		// Log admin action
		log_admin_action($fid, $forum['name']);

		if($mybb->input['ajax'] == 1)
		{
			
			echo json_encode("<script type=\"text/javascript\">
    document.getElementById('row_{$gid}').innerHTML = '" . str_replace(array("'", "\t", "\n"), array("\\'", "", ""), retrieve_single_permissions_row($gid, $fid)) . "';
    if (typeof QuickPermEditor !== 'undefined') {
        QuickPermEditor.init({$gid});
    }
</script>");
			
			
			
			die;
		}
		else
		{
			flash_message($lang->forum_management['success_forum_permissions_saved'], 'success');
			admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");
		}
	}

	if($mybb->input['ajax'] != 1)
	{
		$sub_tabs = array();

		if($mybb->input['fid'] && $mybb->input['gid'])
		{
			$sub_tabs['edit_permissions'] = array(
				'title' => 'forum_permissions2',
				'link' => "index.php?act=management&action=permissions&fid=".$mybb->input['fid']."&amp;gid=".$mybb->input['gid'],
				'description' => 'forum_permissions_desc'
			);

			$page->add_breadcrumb_item('forum_permissions2', "index.php?act=management&fid=".$mybb->input['fid']."#tab_permissions");
		}
		else
		{
			$query = $db->simple_select("forumpermissions", "fid", "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
			$mybb->input['fid'] = $db->fetch_field($query, "fid");

			$sub_tabs['edit_permissions'] = array(
				'title' => 'forum_permissions33',
				'link' => "index.php?act=management&action=permissions&pid=".$mybb->get_input('pid', MyBB::INPUT_INT),
				'description' => 'forum_permissions_desc'
			);

			$page->add_breadcrumb_item('forum_permissions2', "index.php?act=management&fid=".$mybb->input['fid']."#tab_permissions");
		}

		$page->add_breadcrumb_item('forum_permissions444');
		
		
		stdhead();
		
		
	
		
		
		echo "	<link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
	    echo "	<script type=\"text/javascript\" src=\"scripts/admincp.js?ver=1821\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";

		//echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.min.css\" />\n";
		//echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.structure.min.css\" />\n";
		//echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.theme.min.css\" />\n";
		//echo "	<script src=\"scripts/jquery-ui.min.js?ver=1813\"></script>\n";

		// Stop JS elements showing while page is loading (JS supported browsers only)
		echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
		echo "  <script type=\"text/javascript\">\n".
				"//<![CDATA[\n".
				"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
                "//]]>\n".
                "</script>\n";
		
		
		
		

		
		
		output_nav_tabs($sub_tabs, 'edit_permissions');
	}
	
	
	
	
   else
   {
	   
	   
	    echo '
<script src="scripts/popup.js" type="text/javascript"></script>
<script src="scripts/tabs.js" type="text/javascript"></script>
<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    // Save permissions handler
    document.getElementById("modal_form")?.addEventListener("click", function(e) {
        if (e.target.id === "savePermissions" || e.target.closest("#savePermissions")) {
            e.preventDefault();
            const submitBtn = e.target.id === "savePermissions" ? e.target : e.target.closest("#savePermissions");
            const originalHTML = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = \'<i class="fas fa-spinner fa-spin me-2"></i>Saving...\';

            const form = document.getElementById("modal_form");
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Execute any scripts returned in the response
                if (typeof data === "string" && data.includes("<script>")) {
                    const scripts = data.match(/<script[^>]*>([\\s\\S]*?)<\\/script>/g);
                    if (scripts) {
                        scripts.forEach(scriptText => {
                            const code = scriptText.replace(/<script[^>]*>([\\s\\S]*?)<\\/script>/, "$1");
                            try {
                                new Function(code)();
                            } catch (e) {
                                console.error("Error executing script: ", e);
                            }
                        });
                    }
                }
                
                const modal = bootstrap.Modal.getInstance(document.getElementById("dynamicModal")); 
                if (modal) modal.hide();
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Failed to save permissions. Please try again.");
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            });
        }
    });
    
    // Initialize Bootstrap tabs
    const tabButtons = document.querySelectorAll("#permissionTabs button[data-bs-toggle=\"tab\"]");
    tabButtons.forEach(button => {
        button.addEventListener("click", function(e) {
            e.preventDefault();
            const tab = new bootstrap.Tab(this);
            tab.show();
        });
    });
});
</script>';
	   
	   
	   
	   
	   
	   
	   
	   
   }
	
	

	
	
	if(!empty($mybb->input['pid']) || (!empty($mybb->input['gid']) && !empty($mybb->input['fid'])))
	{
	
	echo '
    <div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-shield-alt me-2"></i>Forum Permissions
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-4">
                        <div style="overflow-y: auto; max-height: 400px">';


    // Create form with sanitized inputs
    $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
    $gid = $mybb->get_input('gid', MyBB::INPUT_INT);
    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);

    $form = new Form(
        "index.php?act=management&action=permissions&ajax=1&pid=" . (int)$pid . "&gid=" . (int)$gid . "&fid=" . (int)$fid,
        "post",
        "modal_form"
    );
   
 
   
   
    echo $form->generate_hidden_field("usecustom", "1");

    if ($errors) {
        output_inline_error($errors);
        $permission_data = $mybb->input;

        $query = $db->simple_select("usergroups", "*", "gid = '" . $db->escape_string($permission_data['gid']) . "'");
        $usergroup = $db->fetch_array($query);

        $query = $db->simple_select("tsf_forums", "*", "fid = '" . $db->escape_string($permission_data['fid']) . "'");
        $forum = $db->fetch_array($query);
    } 
	else 
	{
        // Fetch permission data with proper sanitization
        if ($pid) 
		{
            $query = $db->simple_select("forumpermissions", "*", "pid = '" . $db->escape_string($pid) . "'");
        } 
		else 
		{
            $query = $db->simple_select(
                "forumpermissions",
                "*",
                "fid = '" . $db->escape_string($fid) . "' AND gid = '" . $db->escape_string($gid) . "'",
                array('limit' => 1)
            );
        }

        $permission_data = $db->fetch_array($query);

        if (is_array($permission_data)) 
		{
            $fid = $fid ?: $permission_data['fid'];
            $gid = $gid ?: $permission_data['gid'];
            $pid = $pid ?: $permission_data['pid'];
        }

        $query = $db->simple_select("usergroups", "*", "gid = '" . $db->escape_string($gid) . "'");
        $usergroup = $db->fetch_array($query);

        $query = $db->simple_select("tsf_forums", "*", "fid = '" . $db->escape_string($fid) . "'");
        $forum = $db->fetch_array($query);

        $sperms = $permission_data;

        $sql = build_parent_list($fid);
        $query = $db->simple_select(
            "forumpermissions",
            "*",
            $sql . " AND gid = '" . $db->escape_string($gid) . "'"
        );
        $customperms = $db->fetch_array($query);

        if (!empty($permission_data['pid'])) 
		{
            $permission_data['usecustom'] = 1;
            echo $form->generate_hidden_field("pid", (int)$pid);
        } 
		else 
		{
            echo $form->generate_hidden_field("fid", (int)$fid);
            echo $form->generate_hidden_field("gid", (int)$gid);
            $permission_data = empty($customperms['pid'])
                ? usergroup_permissions($gid)
                : forum_permissions($fid, 0, $gid);
        }
    }

    $groups = [
        'canviewthreads' => 'viewing',
        'canview' => 'viewing',
        'canonlyviewownthreads' => 'viewing',
        'candlattachments' => 'viewing',
        'canpostthreads' => 'posting_rating',
        'canpostreplys' => 'posting_rating',
        'canonlyreplyownthreads' => 'posting_rating',
        'canpostattachments' => 'posting_rating',
        'canratethreads' => 'posting_rating',
        'caneditposts' => 'editing',
        'candeleteposts' => 'editing',
        'candeletethreads' => 'editing',
        'caneditattachments' => 'editing',
        'canviewdeletionnotice' => 'editing',
        'modposts' => 'moderate',
        'modthreads' => 'moderate',
        'modattachments' => 'moderate',
        'mod_edit_posts' => 'moderate',
        'canpostpolls' => 'polls',
        'canvotepolls' => 'polls',
        'cansearch' => 'misc',
    ];

    $hidefields = ($usergroup['gid'] == 222)
        ? ['canonlyviewownthreads', 'canonlyreplyownthreads', 'caneditposts', 'candeleteposts', 'candeletethreads', 'caneditattachments', 'canviewdeletionnotice']
        : [];

    $groups = $plugins->run_hooks("admin_forum_management_permission_groups", $groups);

    foreach ($hidefields as $field) {
        unset($groups[$field]);
    }

    // Define tab colors, icons, and titles
    $tab_colors = [
        'viewing' => 'bg-primary',
        'posting_rating' => 'bg-success',
        'editing' => 'bg-info',
        'moderate' => 'bg-warning',
        'polls' => 'bg-purple',
        'misc' => 'bg-secondary'
    ];

    $tab_icons = [
        'viewing' => 'fa-eye',
        'posting_rating' => 'fa-comment',
        'editing' => 'fa-edit',
        'moderate' => 'fa-gavel',
        'polls' => 'fa-chart-bar',
        'misc' => 'fa-cog'
    ];

    $tab_titles = [
        'viewing' => 'Viewing',
        'posting_rating' => 'Posting & Rating',
        'editing' => 'Editing',
        'moderate' => 'Moderation',
        'polls' => 'Polls',
        'misc' => 'Misc'
    ];
	
	
	
	
	$l['viewing_field_canview'] = "Can view forum?";
$l['viewing_field_canviewthreads'] = "Can view threads within forum?";
$l['viewing_field_canonlyviewownthreads'] = "Can only view own threads?";
$l['viewing_field_candlattachments'] = "Can download attachments?";

$l['posting_rating_field_canpostthreads'] = "Can post threads?";
$l['posting_rating_field_canpostreplys'] = "Can post replies?";
$l['posting_rating_field_canonlyreplyownthreads'] = "Can only reply to own threads?";
$l['posting_rating_field_canpostattachments'] = "Can post attachments?";
$l['posting_rating_field_canratethreads'] = "Can rate threads?";

$l['editing_field_caneditposts'] = "Can edit own posts?";
$l['editing_field_candeleteposts'] = "Can delete own posts?";
$l['editing_field_candeletethreads'] = "Can delete own threads?";
$l['editing_field_caneditattachments'] = "Can update own attachments?";
$l['editing_field_canviewdeletionnotice'] = "Can view deletion notices?";

$l['moderate_field_modposts'] = "Moderate new posts?";
$l['moderate_field_modthreads'] = "Moderate new threads?";
$l['moderate_field_modattachments'] = "Moderate new attachments?";
$l['moderate_field_mod_edit_posts'] = "Moderate posts after they've been edited?";

$l['polls_field_canpostpolls'] = "Can post polls?";
$l['polls_field_canvotepolls'] = "Can vote in polls?";

$l['misc_field_cansearch'] = "Can search forum?";

	
	
	
	
	
	
	
	
	
	

    // Output navigation tabs
    echo '<div class="container-fluid px-0">
            <ul class="nav nav-tabs nav-justified mb-4" id="permissionTabs" role="tablist">';

    $first = true;
    foreach (array_unique(array_values($groups)) as $group) {
        echo '<li class="nav-item" role="presentation">
                <button class="nav-link' . ($first ? ' active' : '') . '" id="' . htmlspecialchars($group) . '-tab" data-bs-toggle="tab" data-bs-target="#tab_' . htmlspecialchars($group) . '" type="button" role="tab" aria-controls="tab_' . htmlspecialchars($group) . '" aria-selected="' . ($first ? 'true' : 'false') . '">
                    <i class="fas ' . htmlspecialchars($tab_icons[$group]) . ' me-1"></i>' . htmlspecialchars($tab_titles[$group]) . '
                </button>
            </li>';
        $first = false;
    }

    echo '</ul><div class="tab-content">';

    // Output tab content
    $first = true;
    foreach (array_unique(array_values($groups)) as $group) {
        echo '<div class="tab-pane fade' . ($first ? ' show active' : '') . '" id="tab_' . htmlspecialchars($group) . '" role="tabpanel" aria-labelledby="' . htmlspecialchars($group) . '-tab">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header ' . htmlspecialchars($tab_colors[$group]) . ' text-white py-3">
                        <h6 class="mb-0">
                            <i class="fas fa-user me-2"></i>"' . htmlspecialchars($usergroup['title']) . '" Custom Permissions for "' . htmlspecialchars($forum['name']) . '"
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">';

        foreach ($db->show_fields_from("forumpermissions") as $field) {
            if (!in_array($field['Field'], $hidefields) && 
                (strpos($field['Field'], 'can') === 0 || strpos($field['Field'], 'mod') === 0) &&
                isset($groups[$field['Field']]) && $groups[$field['Field']] == $group) {
                
                $checkbox = $form->generate_check_box(
                    "permissions[{$field['Field']}]",
                    1,
                    "",
                    [
                        'checked' => !empty($permission_data[$field['Field']]),
                        'id' => $field['Field'],
                        'class' => 'form-check-input'
                    ]
                );

	
				echo '<div class="col-md-6">
        <div class="form-check form-switch mb-3">
            ' . $checkbox . '
            <label class="form-check-label" for="' . htmlspecialchars($field['Field']) . '">' . $l[$group . '_field_' . $field['Field']] . '</label>
        </div>
    </div>';	
							
		
            }
        }

        echo '</div></div></div></div>';
        $first = false;
    }

    echo '</div></div>';

    // Output buttons
    echo '<div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-2"></i>Cancel
            </button>
           
		   
		   
		   <button type="submit" class="btn btn-primary" id="savePermissions"><i class="fas fa-save me-2"></i>
		   Save Permissions
		   </button>
		   
		   
        </div>';

    // End form
    $form->end();

    echo '</div></div></div></div>';

    // Output CSS styles
    echo '<style>
        .nav-tabs .nav-link { 
            border: none; 
            border-bottom: 3px solid transparent; 
            color: #6c757d; 
            padding: 12px 16px; 
            transition: all 0.3s ease; 
        }
        .nav-tabs .nav-link:hover { 
            border-color: #dee2e6; 
            color: #495057; 
        }
        .nav-tabs .nav-link.active { 
            border-color: #0d6efd; 
            color: #0d6efd; 
            background: transparent; 
            font-weight: 600; 
        }
        .form-check-input:checked { 
            background-color: #0d6efd; 
            border-color: #0d6efd; 
        }
        .card { 
            border-radius: 12px; 
        }
        .modal-content { 
            border-radius: 16px; 
            border: none; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
        }
        .bg-purple { 
            background-color: #6f42c1 !important; 
        }
        .btn-primary { 
            background-color: #0d6efd; 
            border-color: #0d6efd; 
            padding: 8px 16px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
    </style>';
	
	}
	
	
	
	
	
	
	
	
	

	if($mybb->input['ajax'] != 1)
	{
		stdfoot();
	}
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_forum_management_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = 'You must enter in a title';
		}

		$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
		$type = $mybb->input['type'];

		if($pid <= 0 && $type == "f")
		{
			$errors[] = 'You must select a parent forum';
		}

		if(!$errors)
		{
			if($pid < 0)
			{
				$pid = 0;
			}
			$insert_array = array(
				"name" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"linkto" => $db->escape_string($mybb->input['linkto']),
				"type" => $db->escape_string($type),
				"pid" => $pid,
				"parentlist" => '',
				"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT),
				"active" => $mybb->get_input('active', MyBB::INPUT_INT),
				"open" => $mybb->get_input('open', MyBB::INPUT_INT),
				"usepostcounts" => $mybb->get_input('usepostcounts', MyBB::INPUT_INT),
				"usethreadcounts" => $mybb->get_input('usethreadcounts', MyBB::INPUT_INT),
				"password" => $db->escape_string($mybb->input['password']),
				"defaultdatecut" => $mybb->get_input('defaultdatecut', MyBB::INPUT_INT),
				"defaultsortby" => $db->escape_string($mybb->input['defaultsortby']),
				"defaultsortorder" => $db->escape_string($mybb->input['defaultsortorder']),
			);

			$plugins->run_hooks("admin_forum_management_add_start");

			$fid = $db->insert_query("tsf_forums", $insert_array);

			$parentlist = make_parent_list($fid);
			$db->update_query("tsf_forums", array("parentlist" => $parentlist), "fid='$fid'");

			$cache->update_forums();

			$inherit = $mybb->input['default_permissions'];

			foreach($mybb->input as $id => $permission)
			{
				if(strpos($id, 'fields_') === false)
				{
					continue;
				}

				list(, $gid) = explode('fields_', $id);

				// If it isn't an array then it came from the javascript form
				if(!is_array($permission))
				{
					$permission = explode(',', $permission);
					$permission = array_flip($permission);
					foreach($permission as $name => $value)
					{
						$permission[$name] = 1;
					}
				}

				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls','canpostattachments') as $name)
				{
					if(in_array($name, $permission) || !empty($permission[$name]))
					{
						$permissions[$name][$gid] = 1;
					}
					else
					{
						$permissions[$name][$gid] = 0;
					}
				}
			}

			$canview = $permissions['canview'];
			$canpostthreads = $permissions['canpostthreads'];
			$canpostpolls = $permissions['canpostpolls'];
			$canpostattachments = $permissions['canpostattachments'];
			$canpostreplies = $permissions['canpostreplys'];
			save_quick_perms($fid);

			$plugins->run_hooks("admin_forum_management_add_commit");

			// Log admin action
			log_admin_action($fid, $insert_array['name']);

			flash_message($lang->forum_management['success_forum_added'], 'success');
			admin_redirect("index.php?act=management");
		}
	}

	$extra_header .=  "<script src=\"scripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

	$page->add_breadcrumb_item('Add New Forum');
	
	
	
	stdhead('Add New Forum');
	
	
	echo "		<div class=\"container mt-3\">\n";
	echo "		<div id=\"content\">\n";
	echo "			<div class=\"breadcrumb\">\n";
	echo $page->_generate_breadcrumb();
	echo "			</div>\n";
	echo "			</div>\n";
	
	
	
	
	
	
	echo $extra_header;
	
	
	echo "	<link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
	echo "	<link rel=\"stylesheet\" href=\"templates/forum.css?ver=1813\" type=\"text/css\" />\n";
	echo "	<link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
	echo "	<script type=\"text/javascript\" src=\"scripts/admincp.js?ver=1821\"></script>\n";
    echo "	<script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";
	echo "	<script type=\"text/javascript\" src=\"scripts/popup.js\"></script>\n";


	// Stop JS elements showing while page is loading (JS supported browsers only)
	echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
	echo "  <script type=\"text/javascript\">\n".
				"//<![CDATA[\n".
				"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
                "//]]>\n".
                "</script>\n";
	

	
	
	output_nav_tabs($sub_tabs, 'Add New Forum');

	$form = new Form("index.php?act=management&action=add", "post");

	$forum_data['type'] = "f";
	$forum_data['title'] = "";
	$forum_data['description'] = "";

	if(empty($mybb->input['pid']))
	{
		$forum_data['pid'] = "-1";
	}
	else
	{
		$forum_data['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
	}
	$forum_data['disporder'] = "1";
	$forum_data['linkto'] = "";
	$forum_data['password'] = "";
	$forum_data['active'] = 1;
	$forum_data['open'] = 1;
	$forum_data['overridestyle'] = "";
	$forum_data['style'] = "";
	$forum_data['rulestype'] = "";
	$forum_data['rulestitle'] = "";
	$forum_data['rules'] = "";
	$forum_data['defaultdatecut'] = "";
	$forum_data['defaultsortby'] = "";
	$forum_data['defaultsortorder'] = "";
	$forum_data['allowhtml'] = "";
	$forum_data['allowmycode'] = 1;
	$forum_data['allowsmilies'] = 1;
	$forum_data['allowimgcode'] = 1;
	$forum_data['allowvideocode'] = 1;
	$forum_data['allowpicons'] = 1;
	$forum_data['allowtratings'] = 1;
	$forum_data['showinjump'] = 1;
	$forum_data['usepostcounts'] = 1;
	$forum_data['usethreadcounts'] = 1;
	

	if($errors)
	{
		output_inline_error($errors);
		
		//inline_error($errors);
		

		foreach ($forum_data as $key => $value)
		{
			if (isset($mybb->input[$key]))
			{
				$forum_data[$key] = $mybb->input[$key];
			}
		}
	}

	$types = array(
		'f' => 'forum',
		'c' => 'category'
	);

	$create_a_options_f = array(
		'id' => 'forum'
	);

	$create_a_options_c = array(
		'id' => 'category'
	);

	if($forum_data['type'] == "f")
	{
		$create_a_options_f['checked'] = true;
	}
	else
	{
		$create_a_options_c['checked'] = true;
	}

	
	
	
	


echo <<<HTML
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white rounded-top py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-plus-circle fa-lg me-3"></i>
                <h4 class="mb-0 fw-bold">Create New Forum</h4>
                <span class="badge bg-light text-primary ms-3">v2.0</span>
            </div>
            <p class="mb-0 mt-2 opacity-75" style="font-size: 13px;">
                <i class="fas fa-info-circle me-1"></i>
                Configure your new forum with the settings below
            </p>
        </div>
        
        <div class="card-body p-4">
            <!-- Forum Type Selection -->
            <div class="form-section mb-5">
                <div class="section-header d-flex align-items-center mb-4">
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary me-3">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">Forum Type</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">
                            Select the type of forum you are creating
                        </p>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="type-card active" onclick="selectType('forum')" data-type="forum">
                            <input type="radio" name="type" value="f" class="d-none" id="forum" checked>
                            <div class="type-card-body">
                                <div class="type-icon">
                                    <i class="fas fa-comments fa-2x"></i>
                                </div>
                                <h6 class="fw-bold mt-3 mb-2">Standard Forum</h6>
                                <p class="text-muted small mb-0">
                                    A regular forum where users can post threads and replies
                                </p>
                                <div class="type-badge mt-2">
                                    <span class="badge bg-primary">Recommended</span>
                                </div>
                            </div>
                            <div class="type-check">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="type-card" onclick="selectType('category')" data-type="category">
                            <input type="radio" name="type" value="c" class="d-none" id="category">
                            <div class="type-card-body">
                                <div class="type-icon">
                                    <i class="fas fa-folder fa-2x"></i>
                                </div>
                                <h6 class="fw-bold mt-3 mb-2">Category</h6>
                                <p class="text-muted small mb-0">
                                    A container for organizing multiple forums together
                                </p>
                            </div>
                            <div class="type-check">
                                <i class="far fa-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Basic Information -->
            <div class="form-section mb-5">
                <div class="section-header d-flex align-items-center mb-4">
                    <div class="icon-circle bg-info bg-opacity-10 text-info me-3">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">Basic Information</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">
                            Essential details for your new forum
                        </p>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label fw-bold d-flex align-items-center mb-2">
                                <i class="fas fa-heading me-2 text-primary"></i>
                                Forum Title
                                <span class="required-badge ms-2">Required</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-font text-muted"></i>
                                </span>
                                <input type="text" 
                                       class="form-control form-control-lg border-start-0 ps-0" 
                                       name="title" 
                                       placeholder="Enter forum title (e.g., 'General Discussion')"
                                       required>
                            </div>
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-lightbulb me-1"></i>
                                Choose a clear, descriptive name for your forum
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label fw-bold d-flex align-items-center mb-2">
                                <i class="fas fa-sort-numeric-up me-2 text-primary"></i>
                                Display Order
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-list-ol text-muted"></i>
                                </span>
                                <input type="number" 
                                       class="form-control form-control-lg border-start-0 ps-0" 
                                       name="disporder" 
                                       value="1" 
                                       min="0"
                                       placeholder="Position in forum list">
                            </div>
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Lower numbers appear first in the forum list
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label fw-bold d-flex align-items-center mb-2">
                                <i class="fas fa-align-left me-2 text-primary"></i>
                                Description
                            </label>
                            <textarea name="description" 
                                      class="form-control form-control-lg" 
                                      id="description" 
                                      rows="4" 
                                      placeholder="Briefly describe the purpose of this forum (optional)"
                                      style="resize: vertical;"></textarea>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div class="form-text text-muted">
                                    <i class="fas fa-keyboard me-1"></i>
                                    Optional description shown below forum title
                                </div>
                                <div class="character-count text-muted small">
                                    <span id="charCount">0</span>/500 characters
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Parent Forum Selection -->
            <div class="form-section mb-5">
                <div class="section-header d-flex align-items-center mb-4">
                    <div class="icon-circle bg-success bg-opacity-10 text-success me-3">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">Forum Hierarchy</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">
                            Organize your forum within the site structure
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="form-group">
                            <label class="form-label fw-bold d-flex align-items-center mb-2">
                                <i class="fas fa-folder-tree me-2 text-primary"></i>
                                Parent Forum
                                <span class="required-badge ms-2">Required</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-level-up-alt text-muted"></i>
                                </span>
HTML;

$ff = generate_forum_select('pid', $forum_data['pid'], array('id' => 'pid', 'main_option' => 'None (Top Level)', 'class' => 'form-select form-select-lg border-start-0 ps-0'));

echo $ff;

echo <<<HTML
                            </div>
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Select where this forum should appear in the hierarchy
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="hierarchy-preview p-3 border rounded bg-light">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-eye me-2"></i>
                                Structure Preview
                            </h6>
                            <div class="hierarchy-tree small">
                                <div class="tree-item active">
                                    <i class="fas fa-folder-open me-1 text-warning"></i>
                                    <span class="text-muted">Selected Parent</span>
                                </div>
                                <div class="tree-item ms-3 mt-2">
                                    <i class="fas fa-arrow-right me-1 text-success"></i>
                                    <i class="fas fa-plus-circle me-1 text-primary"></i>
                                    <strong>Your New Forum</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Actions -->
            <div class="form-section pt-4 border-top">
                <div class="row">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-outline-secondary btn-lg px-4" onclick="window.history.back()">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back
                        </button>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="submit" name="save" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-check-circle me-2"></i>
                            Create Forum
                        </button>
                        <button type="button" class="btn btn-primary btn-lg ms-2" onclick="showAdvancedOptions()">
                            <i class="fas fa-cogs me-2"></i>
                            Advanced
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .form-section {
        padding: 25px;
        background: #fff;
        border-radius: 12px;
        border: 1px solid #eef2f7;
        transition: all 0.3s ease;
    }
    
    .form-section:hover {
        border-color: #c6d4e9;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    }
    
    .section-header {
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f4f8;
    }
    
    .icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    
    .type-card {
        border: 2px solid #eef2f7;
        border-radius: 12px;
        padding: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        background: #fff;
        height: 100%;
    }
    
    .type-card:hover {
        transform: translateY(-3px);
        border-color: #c6d4e9;
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .type-card.active {
        border-color: #4d6dee;
        background: linear-gradient(135deg, #f8fafe 0%, #f0f4ff 100%);
    }
    
    .type-card-body {
        text-align: center;
    }
    
    .type-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        color: #667eea;
    }
    
    .type-check {
        position: absolute;
        top: 15px;
        right: 15px;
        color: #4d6dee;
        font-size: 20px;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .type-card.active .type-check {
        opacity: 1;
    }
    
    .form-label {
        font-size: 15px;
        color: #2d3748;
    }
    
    .required-badge {
        background: #fee2e2;
        color: #dc2626;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .input-group-text {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #64748b;
    }
    
    .form-control, .form-select {
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #4d6dee;
        box-shadow: 0 0 0 3px rgba(77, 109, 238, 0.1);
    }
    
    .form-control-lg {
        padding: 12px 16px;
        font-size: 15px;
    }
    
    .hierarchy-tree .tree-item {
        padding: 6px 12px;
        background: #fff;
        border-radius: 6px;
        margin-bottom: 5px;
        border: 1px solid #e2e8f0;
    }
    
    .hierarchy-tree .tree-item.active {
        background: #f0f4ff;
        border-color: #c6d4e9;
    }
    
    .character-count {
        font-size: 12px;
        color: #94a3b8;
    }
    
    .btn-lg {
        padding: 12px 30px;
        font-weight: 600;
        border-radius: 8px;
    }
    
    
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
    }
    
    @media (max-width: 768px) {
        .form-section {
            padding: 20px;
        }
        
        .type-card {
            padding: 20px;
        }
    }
</style>

<script>
function selectType(type) {
    if (!type || type === '#') {
        console.error('Invalid type parameter:', type);
        return;
    }
    
    // Remove active class from all type cards
    document.querySelectorAll('.type-card').forEach(card => {
        card.classList.remove('active');
        const checkIcon = card.querySelector('.type-check i');
        if (checkIcon) {
            checkIcon.className = 'far fa-circle';
        }
    });
    
    // Add active class to selected card
    const selectedCard = document.querySelector('.type-card[data-type="' + type + '"]');
    if (selectedCard) {
        selectedCard.classList.add('active');
        const checkIcon = selectedCard.querySelector('.type-check i');
        if (checkIcon) {
            checkIcon.className = 'fas fa-check-circle';
        }
        
        // Update radio button
        const radio = document.getElementById(type);
        if (radio) {
            radio.checked = true;
        }
    }
}

// Character counter for description
const descriptionField = document.getElementById('description');
if (descriptionField) {
    descriptionField.addEventListener('input', function() {
        const charCount = this.value.length;
        const charCountElement = document.getElementById('charCount');
        if (charCountElement) {
            charCountElement.textContent = charCount;
        }
        
        if (charCount > 500) {
            this.value = this.value.substring(0, 500);
            if (charCountElement) {
                charCountElement.textContent = 500;
            }
        }
    });
}

// Update preview when parent forum changes
const parentSelect = document.getElementById('pid');
if (parentSelect) {
    parentSelect.addEventListener('change', function() {
        const preview = document.querySelector('.hierarchy-tree .tree-item.active span');
        if (preview && this.value) {
            preview.textContent = 'Selected: ' + this.options[this.selectedIndex].text;
        } else if (preview) {
            preview.textContent = 'Top Level (No Parent)';
        }
    });
}

function showAdvancedOptions() {
    // You can integrate this with your existing advanced options toggle
    const advancedOptions = document.getElementById('additional_options');
    const advancedOptionsLink = document.getElementById('additional_options_link');
    
    if (advancedOptions && advancedOptionsLink) {
        if (advancedOptions.style.display === 'block' || advancedOptions.style.display === '') {
            advancedOptionsLink.style.display = 'block';
            advancedOptions.style.display = 'none';
        } else {
            advancedOptionsLink.style.display = 'none';
            advancedOptions.style.display = 'block';
        }
    } else {
        alert('Advanced options would open here!');
    }
}
</script>
HTML;















	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	

echo <<<HTML
<style>
    .toggle-options-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.25);
    }
    
    .toggle-options-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
    }
    
    .toggle-options-btn i {
        transition: transform 0.4s ease;
        font-size: 18px;
    }
    
    #additional_options_link .toggle-options-btn i {
        transform: rotate(0deg);
    }
    
    #additional_options .toggle-options-btn i {
        transform: rotate(180deg);
    }
    
    #additional_options {
        animation: slideDown 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        transform-origin: top;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-15px) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
   
    
    .form-row {
        margin-bottom: 28px;
        padding-bottom: 28px;
        border-bottom: 2px solid #f0f2f5;
        position: relative;
    }
    
    .form-row:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .form-row::before {
        content: '';
        position: absolute;
        left: -28px;
        top: 0;
        height: 100%;
        width: 4px;
        background: linear-gradient(to bottom, #667eea, #764ba2);
        border-radius: 0 4px 4px 0;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .form-row:hover::before {
        opacity: 1;
    }
    
    .form-label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 12px;
        font-size: 15px;
    }
    
    .form-label i {
        color: #667eea;
        font-size: 16px;
    }
    
    .form-description {
        color: #718096;
        font-size: 14px;
        margin-bottom: 16px;
        line-height: 1.6;
        padding-left: 26px;
        position: relative;
    }
    
    .form-description::before {
        content: 'ℹ️';
        position: absolute;
        left: 0;
        top: 2px;
    }
    
    .form-control {
        width: 100%;
        max-width: 450px;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
        background: white;
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        outline: none;
        transform: translateY(-2px);
    }
    
    .form-check {
        margin-bottom: 16px;
        display: flex;
        align-items: flex-start;
        background: white;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        transition: all 0.3s;
    }
    
    .form-check:hover {
        border-color: #667eea;
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .form-check-input {
        margin-top: 3px;
        margin-right: 12px;
        transform: scale(1.2);
        accent-color: #667eea;
    }
    
    .form-check-label {
        color: #4a5568;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .form-check-label i {
        color: #667eea;
        margin-right: 8px;
        font-size: 14px;
    }
    
    .form-check-label small {
        display: block;
        color: #718096;
        margin-top: 4px;
        font-size: 13px;
        font-weight: normal;
    }
    
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-top: 20px;
    }
    
    .settings-group {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 20px;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }
    
    .settings-group::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }
    
    .settings-group:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border-color: #cbd5e0;
    }
    
    .settings-group-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 16px;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .settings-group-title i {
        color: #667eea;
        font-size: 16px;
    }
    
    .form-select {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23667eea' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 16px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }
    
    .form-select option {
        padding: 8px;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
        color: #667eea;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 10px;
    }
</style>

<script>
function toggleAdditionalOptions() {
    var link = document.getElementById('additional_options_link');
    var options = document.getElementById('additional_options');
    
    if (options.style.display === 'block' || options.style.display === '') {
        link.style.display = 'block';
        options.style.display = 'none';
    } else {
        link.style.display = 'none';
        options.style.display = 'block';
    }
    return false;
}
</script>

<div id="additional_options_link" class="text-center" style="padding: 40px 0;">
    <button onclick="return toggleAdditionalOptions();" class="toggle-options-btn">
        <i class="fas fa-cogs fa-lg"></i>
        <span>Show Additional Forum Options</span>
        <i class="fas fa-chevron-down"></i>
    </button>
    <p style="margin-top: 15px; color: #718096; font-size: 13px;">
        <i class="fas fa-info-circle"></i> Advanced settings for forum configuration
    </p>
</div>

<div id="additional_options" style="display: none;">
<div class="card">
    <div class="card-header rounded-bottom">
        <span>
            <i class="fas fa-sliders-h fa-lg"></i>
            Additional Forum Options
            <span class="badge">
                <i class="fas fa-star"></i> Advanced
            </span>
        </span>
        <button onclick="return toggleAdditionalOptions();" class="toggle-options-btn" style="font-size: 14px; padding: 10px 20px;">
            <i class="fas fa-times"></i>
            Hide Options
            <i class="fas fa-chevron-up"></i>
        </button>
    </div>
    <div class="card-body">
HTML;

echo <<<HTML
        <div class="form-row">
            <label class="form-label" for="linkto">
                <i class="fas fa-external-link-alt"></i>
                Forum Link (Redirect)
            </label>
            <div class="form-description">
                To make a forum redirect to another location, enter the URL to the destination you wish to redirect to. 
                Entering a URL in this field will remove the forum functionality; however, permissions can still be set for it.
            </div>
            <div style="position: relative; max-width: 450px;">
                <i class="fas fa-link" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                <input type="text" name="linkto" value="" class="form-control" id="linkto" placeholder="https://example.com" style="padding-left: 45px;" />
            </div>
        </div>
        
        <div class="form-row">
            <label class="form-label" for="password">
                <i class="fas fa-lock"></i>
                Forum Password Protection
            </label>
            <div class="form-description">
                To protect this forum further, you can choose a password that must be entered for access. 
                Note: User groups still need permissions to access this forum.
            </div>
            <div style="position: relative; max-width: 450px;">
                <i class="fas fa-key" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                <input type="text" name="password" value="" class="form-control" id="password" placeholder="Enter password (optional)" style="padding-left: 45px;" />
            </div>
        </div>
        
        <div class="form-row">
            <label class="form-label">
                <i class="fas fa-shield-alt"></i>
                Access Control Options
            </label>
            <div class="settings-grid">
                <div class="form-check">
                    <input type="checkbox" name="active" value="1" class="form-check-input" id="active" checked="checked" />
                    <label class="form-check-label" for="active">
                        <i class="fas fa-toggle-on"></i>
                        Forum is Active
                        <small>If unselected, this forum will not be shown to users and will not "exist".</small>
                    </label>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" name="open" value="1" class="form-check-input" id="open" checked="checked" />
                    <label class="form-check-label" for="open">
                        <i class="fas fa-door-open"></i>
                        Forum is Open
                        <small>If unselected, users will not be able to post in this forum regardless of permissions.</small>
                    </label>
                </div>
            </div>
        </div>
        
        <div class="form-row">
            <label class="form-label">
                <i class="fas fa-eye"></i>
                Default View & Display Options
            </label>
            <div class="settings-grid">
                <div class="settings-group">
                    <div class="settings-group-title">
                        <i class="fas fa-calendar-alt"></i>
                        Date Range Filter
                    </div>
                    <select class="form-select" name="defaultdatecut" id="defaultdatecut">
                        <option value="0">📅 Board Default</option>
                        <option value="1">⏰ Last 24 hours</option>
                        <option value="5">📆 Last 5 days</option>
                        <option value="10">🗓️ Last 10 days</option>
                        <option value="20">📅 Last 20 days</option>
                        <option value="50">🗓️ Last 50 days</option>
                        <option value="75">📆 Last 75 days</option>
                        <option value="100">📅 Last 100 days</option>
                        <option value="365">🎄 Last year</option>
                        <option value="9999">🚀 All time</option>
                    </select>
                </div>
                
                <div class="settings-group">
                    <div class="settings-group-title">
                        <i class="fas fa-sort-amount-down"></i>
                        Sort By Field
                    </div>
                    <select class="form-select" name="defaultsortby" id="defaultsortby">
                        <option value="" selected="selected">⚙️ Board Default</option>
                        <option value="subject">📝 Thread subject</option>
                        <option value="lastpost">🕒 Last post time</option>
                        <option value="starter">👤 Thread starter</option>
                        <option value="started">⏱️ Thread creation time</option>
                        <option value="rating">⭐ Thread rating</option>
                        <option value="replies">💬 Number of replies</option>
                        <option value="views">👁️ Number of views</option>
                    </select>
                </div>
                
                <div class="settings-group">
                    <div class="settings-group-title">
                        <i class="fas fa-sort-alpha-down"></i>
                        Sort Order Direction
                    </div>
                    <select class="form-select" name="defaultsortorder" id="defaultsortorder">
                        <option value="" selected="selected">⚙️ Board Default</option>
                        <option value="asc">⬆️ Ascending (A-Z, Old-New)</option>
                        <option value="desc">⬇️ Descending (Z-A, New-Old)</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="form-row">
            <label class="form-label">
                <i class="fas fa-cogs"></i>
                Statistics & Counting Options
            </label>
            <div class="settings-grid">
                <div class="form-check">
                    <input type="checkbox" name="usepostcounts" value="1" class="form-check-input" id="usepostcounts" checked="checked" />
                    <label class="form-check-label" for="usepostcounts">
                        <i class="fas fa-comment-alt"></i>
                        Count posts in user statistics
                        <small>Posts in this forum will contribute to user's total post count</small>
                    </label>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" name="usethreadcounts" value="1" class="form-check-input" id="usethreadcounts" checked="checked" />
                    <label class="form-check-label" for="usethreadcounts">
                        <i class="fas fa-file-alt"></i>
                        Count threads in user statistics
                        <small>Threads in this forum will contribute to user's total thread count</small>
                    </label>
                </div>
                
               
                
    
				
            </div>
        </div>
HTML;

echo "</div></div></div>";














echo <<<HTML
<style>
    .permissions-card {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        margin-bottom: 30px;
    }
    
    .permissions-header {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        padding: 24px 30px;
        border-bottom: none;
    }
    
    .permissions-header h5 {
        margin: 0;
        font-weight: 700;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .permissions-header h5 i {
        background: rgba(255, 255, 255, 0.2);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .permissions-table {
        margin: 0;
        border: none;
    }
    
    .permissions-table thead th {
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        color: #475569;
        font-weight: 700;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 18px 20px;
        white-space: nowrap;
    }
    
    .permissions-table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .permissions-table tbody tr:hover {
        background: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .permissions-table td {
        padding: 20px;
        vertical-align: top;
        border: none;
        color: #334155;
    }
    
    .group-info {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        min-width: 250px;
    }
    
    
    .group-details {
        flex-grow: 1;
    }
    
    .group-name {
        font-weight: 700;
        color: #1e293b;
        font-size: 15px;
        margin-bottom: 5px;
        display: block;
    }
    
    .group-default {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #64748b;
    }
    
    .custom-switch {
        position: relative;
        display: inline-block;
        width: 36px;
        height: 20px;
    }
    
    .custom-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .custom-switch-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .4s;
        border-radius: 34px;
    }
    
    .custom-switch-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    
    .custom-switch input:checked + .custom-switch-slider {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    
    .custom-switch input:checked + .custom-switch-slider:before {
        transform: translateX(16px);
    }
    
    .permissions-lists {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        min-width: 350px;
    }
    
    .permissions-column {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    
    .permissions-column-header {
        background: #f8fafc;
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        font-size: 13px;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .permissions-column-header.allowed {
        color: #059669;
    }
    
    .permissions-column-header.disallowed {
        color: #dc2626;
    }
    
    .permissions-count {
        background: rgba(5, 150, 105, 0.1);
        color: #059669;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 700;
    }
    
    .permissions-count.disallowed {
        background: rgba(220, 38, 38, 0.1);
        color: #dc2626;
    }
    
    .permissions-list {
        padding: 12px;
        min-height: 120px;
        background: white;
    }
    
    .permissions-list ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .permissions-list li {
        padding: 10px 12px;
        margin-bottom: 6px;
        background: #f8fafc;
        border-radius: 8px;
        font-size: 13px;
        color: #334155;
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: move;
        transition: all 0.2s ease;
        border-left: 3px solid transparent;
    }
    
    .permissions-list li:hover {
        background: #f1f5f9;
        transform: translateX(3px);
    }
    
    .permissions-list li.allowed {
        border-left-color: #10b981;
    }
    
    .permissions-list li.disallowed {
        border-left-color: #ef4444;
    }
    
    .permission-icon {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        color: white;
        flex-shrink: 0;
    }
    
    .permission-icon.view { background: #3b82f6; }
    .permission-icon.threads { background: #8b5cf6; }
    .permission-icon.replies { background: #10b981; }
    .permission-icon.polls { background: #f59e0b; }
    
    .no-permissions {
        color: #94a3b8;
        font-size: 13px;
        text-align: center;
        padding: 20px;
        font-style: italic;
    }
    
    .permissions-footer {
        background: #f8fafc;
        padding: 25px;
        border-top: 1px solid #e2e8f0;
        text-align: center;
        border-radius: 0 0 16px 16px;
    }
    
    .save-button {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        border: none;
        padding: 14px 40px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 10px;
        color: white;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.3);
    }
    
    .save-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
    }
    
    .permissions-summary {
        margin-top: 15px;
        color: #64748b;
        font-size: 13px;
    }
    
    .permissions-summary i {
        margin-right: 5px;
    }
    
    .sortable-placeholder {
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        height: 42px;
        margin-bottom: 6px;
        background: #f8fafc;
    }
    
    @media (max-width: 1200px) {
        .permissions-table thead {
            display: none;
        }
        
        .permissions-table tbody tr {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .permissions-table td {
            border-bottom: 1px solid #f1f5f9;
        }
        
        .permissions-table td:last-child {
            border-bottom: none;
        }
    }
</style>

<script src="https://ruff-tracker.eu/admin/scripts/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация перетаскивания для каждой группы
    var groupIds = [];
HTML;

// Добавляем каждый ID по отдельности
foreach($ids as $id) {
    echo "    groupIds.push(" . intval($id) . ");\n";
}

echo <<<HTML
    
    groupIds.forEach(function(groupId) {
        var enabledList = document.getElementById('fields_enabled_' + groupId);
        var disabledList = document.getElementById('fields_disabled_' + groupId);
        
        if (enabledList && disabledList) {
            Sortable.create(enabledList, {
                group: 'permissions_' + groupId,
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    updatePermissionsField(groupId);
                }
            });
            
            Sortable.create(disabledList, {
                group: 'permissions_' + groupId,
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    updatePermissionsField(groupId);
                }
            });
        }
    });
});

function updatePermissionsField(groupId) {
    var enabledList = document.getElementById('fields_enabled_' + groupId);
    var permissions = [];
    
    if (enabledList) {
        var items = enabledList.querySelectorAll('li');
        items.forEach(function(item) {
            var perm = item.id.replace('field-', '');
            if (perm) {
                permissions.push(perm);
            }
        });
    }
    
    var hiddenField = document.getElementById('fields_' + groupId);
    if (hiddenField) {
        hiddenField.value = permissions.join(',');
    }
    
    // Обновляем счетчики
    var allowedCount = document.querySelector('[data-group="' + groupId + '"] .allowed-count');
    var disallowedCount = document.querySelector('[data-group="' + groupId + '"] .disallowed-count');
    
    if (allowedCount) {
        allowedCount.textContent = permissions.length;
    }
    if (disallowedCount) {
        var totalPermissions = 4; // Всего 4 типа разрешений
        disallowedCount.textContent = totalPermissions - permissions.length;
    }
}

function toggleDefaultPermissions(groupId, checkbox) {
    var groupRow = document.querySelector('[data-group="' + groupId + '"]');
    var permissionsLists = groupRow.querySelector('.permissions-lists');
    
    if (checkbox.checked) {
        permissionsLists.style.opacity = '0.5';
        permissionsLists.style.pointerEvents = 'none';
    } else {
        permissionsLists.style.opacity = '1';
        permissionsLists.style.pointerEvents = 'all';
    }
}
</script>
HTML;

// Получаем список групп пользователей
$query = $db->simple_select("usergroups", "*", "", array("order" => "name"));
$usergroupsSS = array();
while($usergroup = $db->fetch_array($query))
{
    $usergroupsSS[$usergroup['gid']] = $usergroup;
}

$cached_forum_perms = $cache->read("forumpermissions");
$field_list = array(
    'canview' => 'Can view?',
    'canpostthreads' => 'Can post threads?',
    'canpostreplys' => 'Can post replies?',
    'canpostpolls' => 'Can post polls?',
);

$field_list2 = array(
    'canview' => 'View Forum',
    'canpostthreads' => 'Post Threads',
    'canpostreplys' => 'Post Replies',
    'canpostpolls' => 'Post Polls',
);

$ids = array();

echo <<<HTML
<div class="card">
    <div class="card-header bg-primary text-white rounded-top py-3">
        <h5>
            <i class="fas fa-shield-alt"></i>
            Forum Permissions Management
        </h5>
    </div>
    
    <table class="table permissions-table">
        <thead>
            <tr>
                <th>User Group</th>
                <th>Permissions Configuration</th>
            </tr>
        </thead>
        <tbody>
HTML;

// Обработка POST данных
if($mybb->request_method == "post")
{
    foreach($usergroupsSS as $usergroup)
    {
        if(isset($mybb->input['fields_'.$usergroup['gid']]))
        {
            $input_permissions = $mybb->input['fields_'.$usergroup['gid']];
            if(!is_array($input_permissions))
            {
                $input_permissions = explode(',', $input_permissions);
            }
            foreach($input_permissions as $input_permission)
            {
                $mybb->input['permissions'][$usergroup['gid']][$input_permission] = 1;
            }
        }
    }
}

// Генерация строк для каждой группы
foreach($usergroupsSS as $usergroup)
{
    $perms = array();
    $default_checked = true;
    
    // Определяем текущие разрешения
    if(!empty($mybb->input['default_permissions'][$usergroup['gid']]))
    {
        if(isset($existing_permissions) && is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
        {
            $perms = $existing_permissions[$usergroup['gid']];
            $default_checked = false;
        }
        elseif(is_array($cached_forum_perms) && isset($forum_data['fid']) && !empty($cached_forum_perms[$forum_data['fid']][$usergroup['gid']]))
        {
            $perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
        }
        else if(is_array($cached_forum_perms) && isset($forum_data['fid']) && !empty($cached_forum_perms[$forum_data['pid']][$usergroup['gid']]))
        {
            $perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
        }
    }

    if(!$perms)
    {
        $perms = $usergroup;
    }

    // Определяем выбранные разрешения
    foreach($field_list as $forum_permission => $forum_perm_title)
    {
        if(isset($mybb->input['permissions']))
        {
            if(!empty($mybb->input['default_permissions'][$usergroup['gid']]))
            {
                $default_checked = true;
            }
            else
            {
                $default_checked = false;
            }

            if(!empty($mybb->input['permissions'][$usergroup['gid']][$forum_permission]))
            {
                $perms_checked[$forum_permission] = 1;
            }
            else
            {
                $perms_checked[$forum_permission] = 0;
            }
        }
        else
        {
            if($perms[$forum_permission] == 1)
            {
                $perms_checked[$forum_permission] = 1;
            }
            else
            {
                $perms_checked[$forum_permission] = 0;
            }
        }
    }
    
    $usergroup['title'] = htmlspecialchars_uni($usergroup['title']);
    
    
	
	$group_icon = !empty($usergroup['image']) 
    ? $usergroup['image']
    : (isset($group_icons[$usergroup['gid']]) 
        ? '<i class="' . $group_icons[$usergroup['gid']] . '"></i>' 
        : '<i class="fas fa-users"></i>');
	
	
$gid = $usergroup['gid'];
$title = $usergroup['title'];
$defaultChecked = $default_checked ? 'checked' : '';

echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" type="text/css" media="screen" />';
echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css" type="text/css" media="screen" />';

echo <<<HTML
<tr data-group="$gid">
    <td>
        <div class="group-info">
            <div>
                $group_icon
            </div>
            <div class="group-details">
                <span class="group-name">$title</span>
                <div class="group-default">
                    <label class="custom-switch">
                        <input type="checkbox" 
                               name="default_permissions[$gid]" 
                               value="1" 
                               id="default_permissions_$gid" 
                               onchange="toggleDefaultPermissions($gid, this)"
                               $defaultChecked>
                        <span class="custom-switch-slider"></span>
                    </label>
                    <label for="default_permissions_$gid" style="cursor: pointer;">
                        Use group default permissions
                    </label>
                </div>
            </div>
        </div>
    </td>
    <td>
        <div class="permissions-lists">
            <div class="permissions-column">
                <div class="permissions-column-header allowed">
                    <span>Allowed Actions</span>
                    <span class="permissions-count allowed allowed-count">0</span>
                </div>
                <div class="permissions-list">
                    <ul id="fields_enabled_$gid">
HTML;


    // Разрешенные действия
    foreach($perms_checked as $perm => $value)
    {
        if($value == 1)
        {
            $icon_class = str_replace('can', '', $perm);
            echo <<<HTML
            <li id="field-{$perm}" class="allowed">
                <div class="permission-icon {$icon_class}">
                    <i class="fas fa-check"></i>
                </div>
                <span>{$field_list2[$perm]}</span>
            </li>
HTML;
        }
    }

    echo <<<HTML
                        </ul>
                    </div>
                </div>
                
                <div class="permissions-column">
                    <div class="permissions-column-header disallowed">
                        <span>Disallowed Actions</span>
                        <span class="permissions-count disallowed disallowed-count">0</span>
                    </div>
                    <div class="permissions-list">
                        <ul id="fields_disabled_{$usergroup['gid']}">
HTML;

    // Запрещенные действия
    foreach($perms_checked as $perm => $value)
    {
        if($value == 0)
        {
            $icon_class = str_replace('can', '', $perm);
            echo <<<HTML
            <li id="field-{$perm}" class="disallowed">
                <div class="permission-icon {$icon_class}">
                    <i class="fas fa-times"></i>
                </div>
                <span>{$field_list2[$perm]}</span>
            </li>
HTML;
        }
    }

    echo <<<HTML
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Скрытое поле с выбранными разрешениями -->
            <input type="hidden" 
                   name="fields_{$usergroup['gid']}" 
                   id="fields_{$usergroup['gid']}" 
                   value="">
        </td>
    </tr>
HTML;

    $ids[] = $usergroup['gid'];
}

echo <<<HTML
        </tbody>
    </table>
    
    <div class="permissions-footer">
        <button type="submit" name="save" class="save-button">
            <i class="fas fa-save"></i>
            Save Forum Permissions
        </button>
        <div class="permissions-summary">
            <i class="fas fa-info-circle"></i>
            Drag and drop permissions between columns to customize access for each user group
        </div>
    </div>
</div>
HTML;

// Преобразуем массив ID в JSON для JavaScript
echo '<script>';
echo 'var groupIds = ' . json_encode($ids) . ';';
echo '</script>';



	
	
	$form->end();



	stdfoot();
}

if($mybb->input['action'] == "edit")
{
	if(!$mybb->input['fid'])
	{
		flash_message($lang->forum_management['error_invalid_fid'], 'error');
		admin_redirect("index.php?act=management");
	}

	$query = $db->simple_select("tsf_forums", "*", "fid='{$mybb->input['fid']}'");
	$forum_data = $db->fetch_array($query);
	if(!$forum_data)
	{
		flash_message($lang->forum_management['error_invalid_fid'], 'error');
		admin_redirect("index.php?act=management");
	}

	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

	$plugins->run_hooks("admin_forum_management_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = 'You must enter in a title';
		}

		$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

		if($pid == $mybb->input['fid'])
		{
			$errors[] = 'The forum parent cannot be the forum itself';
		}
		else
		{
			$query = $db->simple_select('tsf_forums', 'parentlist', "fid='{$pid}'");
			$parents = explode(',', $db->fetch_field($query, 'parentlist'));
			if(in_array($mybb->input['fid'], $parents))
			{
				$errors[] = 'You cant set the parent forum of this forum to one of its children';
			}
		}

		$type = $mybb->input['type'];

		if($pid <= 0 && $type == "f")
		{
			$errors[] = 'You must select a parent forum';
		}

		if($type == 'c' && $forum_data['type'] == 'f')
		{
			$query = $db->simple_select('tsf_threads', 'COUNT(tid) as num_threads', "fid = '{$fid}'");
			if($db->fetch_field($query, "num_threads") > 0)
			{
				$errors[] = 'Forums with threads cannot be converted to categories';
			}
		}

		if(!empty($mybb->input['linkto']) && empty($forum_data['linkto']))
		{
			$query = $db->simple_select('tsf_threads', 'COUNT(tid) as num_threads', "fid = '{$fid}'", array("limit" => 1));
			if($db->fetch_field($query, "num_threads") > 0)
			{
				$errors[] = 'Forums with threads cannot be redirected to another webpage';
			}
		}

		if(!$errors) {
			if ($pid < 0) {
				$pid = 0;
			}
			$update_array = array(
				"name" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"linkto" => $db->escape_string($mybb->input['linkto']),
				"type" => $db->escape_string($type),
				"pid" => $pid,
				"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT),
				"active" => $mybb->get_input('active', MyBB::INPUT_INT),
				"open" => $mybb->get_input('open', MyBB::INPUT_INT),
				"usepostcounts" => $mybb->get_input('usepostcounts', MyBB::INPUT_INT),
				"usethreadcounts" => $mybb->get_input('usethreadcounts', MyBB::INPUT_INT),
				"password" => $db->escape_string($mybb->input['password']),
				"defaultdatecut" => $mybb->get_input('defaultdatecut', MyBB::INPUT_INT),
				"defaultsortby" => $db->escape_string($mybb->input['defaultsortby']),
				"defaultsortorder" => $db->escape_string($mybb->input['defaultsortorder']),
			
			);
			$db->update_query("tsf_forums", $update_array, "fid='{$fid}'");
			if ($pid != $forum_data['pid']) {
				// Update the parentlist of this forum.
				$db->update_query("tsf_forums", array("parentlist" => make_parent_list($fid)), "fid='{$fid}'");

				// Rebuild the parentlist of all of the subforums of this forum
				switch ($db->type) {
					case "sqlite":
					case "pgsql":
						$query = $db->simple_select("tsf_forums", "fid", "','||parentlist||',' LIKE '%,$fid,%'");
						break;
					default:
						$query = $db->simple_select("tsf_forums", "fid", "CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
				}

				while ($child = $db->fetch_array($query)) {
					$db->update_query("tsf_forums", array("parentlist" => make_parent_list($child['fid'])), "fid='{$child['fid']}'");
				}
			}

			if(!empty($mybb->input['default_permissions']))
			{
				$inherit = $mybb->input['default_permissions'];
			}
			else
			{
				$inherit = array();
			}

			foreach($mybb->input as $id => $permission)
			{
				// Make sure we're only skipping inputs that don't start with "fields_" and aren't fields_default_ or fields_inherit_
				if(strpos($id, 'fields_') === false || (strpos($id, 'fields_default_') !== false || strpos($id, 'fields_inherit_') !== false))
				{
					continue;
				}

				list(, $gid) = explode('fields_', $id);

				if($mybb->input['fields_default_'.$gid] == $permission && $mybb->input['fields_inherit_'.$gid] == 1)
				{
					$inherit[$gid] = 1;
					continue;
				}
				$inherit[$gid] = 0;

				// If it isn't an array then it came from the javascript form
				if(!is_array($permission))
				{
					$permission = explode(',', $permission);
					$permission = array_flip($permission);
					foreach($permission as $name => $value)
					{
						$permission[$name] = 1;
					}
				}

				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls') as $name)
				{
					if(in_array($name, $permission) || !empty($permission[$name]))
					{
						$permissions[$name][$gid] = 1;
					}
					else
					{
						$permissions[$name][$gid] = 0;
					}
				}
			}

			$cache->update_forums();

			if(isset($permissions['canview']))
			{
				$canview = $permissions['canview'];
			}
			if(isset($permissions['canpostthreads']))
			{
				$canpostthreads = $permissions['canpostthreads'];
			}
			if(isset($permissions['canpostpolls']))
			{
				$canpostpolls = $permissions['canpostpolls'];
			}
			if(isset($permissions['canpostattachments']))
			{
				$canpostattachments = $permissions['canpostattachments'];
			}
			if(isset($permissions['canpostreplys']))
			{
				$canpostreplies = $permissions['canpostreplys'];
			}

			save_quick_perms($fid);

			$plugins->run_hooks("admin_forum_management_edit_commit");

			// Log admin action
			log_admin_action($fid, $mybb->input['title']);

			flash_message('The forum settings have been updated successfully', 'success');
			admin_redirect("index.php?act=management&fid={$fid}");
		}
	}

	$extra_header .=  "<script src=\"scripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

	$page->add_breadcrumb_item('Edit Forum');
	
	
	
	
	stdhead('Edit Forum');
	
	
	
	echo "		<div class=\"container mt-3\">\n";
	echo "		<div id=\"content\">\n";
	echo "			<div class=\"breadcrumb\">\n";
	echo $page->_generate_breadcrumb();
	echo "			</div>\n";
	echo "			</div>\n";
	
	
	
	
	
	
	echo $extra_header;
	
	  echo "	<link rel=\"stylesheet\" href=\"templates/forum.css?ver=1813\" type=\"text/css\" />\n";
	 echo "	<link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
	    echo "	<script type=\"text/javascript\" src=\"scripts/admincp.js?ver=1821\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/popup.js\"></script>\n";

		//echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.min.css\" />\n";
		//echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.structure.min.css\" />\n";
		//echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.theme.min.css\" />\n";
		//echo "	<script src=\"scripts/jquery-ui.min.js?ver=1813\"></script>\n";

		// Stop JS elements showing while page is loading (JS supported browsers only)
		echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
		echo "  <script type=\"text/javascript\">\n".
				"//<![CDATA[\n".
				"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
                "//]]>\n".
                "</script>\n";
	
	
	
	
	
   output_nav_tabs($sub_tabs, 'Edit Forum Settings88888888');
	


	$form = new Form("index.php?act=management&action=edit", "post");
	echo $form->generate_hidden_field("fid", $fid);

	if($errors)
	{
		output_inline_error($errors);
		$forum_data = $mybb->input;
	}
	else
	{
		$forum_data['title'] = $forum_data['name'];
	}

	$query = $db->simple_select("usergroups", "*", "", array("order_dir" => "name"));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroupsSSS[$usergroup['gid']] = $usergroup;
	}

	$query = $db->simple_select("forumpermissions", "*", "fid='{$fid}'");
	while($existing = $db->fetch_array($query))
	{
		$existing_permissions[$existing['gid']] = $existing;
	}

	$types = array(
		'f' => 'forum',
		'c' => 'category'
	);

	$create_a_options_f = array(
		'id' => 'forum'
	);

	$create_a_options_c = array(
		'id' => 'category'
	);

	if($forum_data['type'] == "f")
	{
		$create_a_options_f['checked'] = true;
	}
	else
	{
		$create_a_options_c['checked'] = true;
	}

	
	
// Инициализируем FormContainer в начале, если он нужен
// Инициализируем FormContainer в начале, если он нужен
$form_container = new FormContainer('Edit Forum');

// Подготовим переменные для активного состояния
$isForumActive = ($create_a_options_f == 'checked="checked"') ? 'active' : '';
$isCategoryActive = ($create_a_options_c == 'checked="checked"') ? 'active' : '';
$forumCheckIcon = ($create_a_options_f == 'checked="checked"') ? 'fas fa-check-circle' : 'far fa-circle';
$categoryCheckIcon = ($create_a_options_c == 'checked="checked"') ? 'fas fa-check-circle' : 'far fa-circle';
$descriptionLength = $forum_data['description'] ? strlen($forum_data['description']) : 0;

echo <<<HTML
<style>
    .form-section {
        margin-bottom: 40px;
        padding-bottom: 40px;
        border-bottom: 2px solid #f0f2f5;
    }
    
    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .section-header {
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .icon-circle {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    
    .type-card {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        background: white;
        height: 100%;
    }
    
    .type-card:hover {
        border-color: #667eea;
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.1);
    }
    
    .type-card.active {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.15);
    }
    
    .type-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin-bottom: 15px;
    }
    
    .type-check {
        position: absolute;
        top: 15px;
        right: 15px;
        color: #667eea;
        font-size: 20px;
    }
    
    .type-badge .badge {
        font-size: 11px;
        padding: 5px 10px;
        border-radius: 6px;
    }
    
    .required-badge {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        color: white;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-label {
        font-size: 15px;
        color: #2d3748;
        margin-bottom: 10px;
    }
    
    .input-group-text {
        background-color: #f8f9fa;
        border-right: none;
    }
    
    .form-control-lg {
        padding: 14px 16px;
        font-size: 15px;
        border-radius: 8px;
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
    }
    
    .form-text {
        font-size: 13px;
        color: #718096;
    }
    
    .character-count {
        font-size: 12px;
    }
    
    .hierarchy-preview {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 1px solid #e2e8f0;
        height: 100%;
    }
    
    .tree-item {
        padding: 5px 0;
        color: #4a5568;
    }
    
    .tree-item.active {
        color: #667eea;
        font-weight: 500;
    }
    
    .btn-lg {
        padding: 12px 30px;
        font-size: 15px;
        font-weight: 600;
        border-radius: 8px;
    }
    
   
    
    
    .badge.bg-light {
        background-color: rgba(255, 255, 255, 0.9) !important;
        color: #667eea !important;
        font-weight: 600;
    }
    
    .opacity-75 {
        opacity: 0.85;
    }
</style>

<div class="container mt-4">
    <div class="card border-0 shadow-lg">
        <div class="card-header bg-primary text-white rounded-top py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-edit fa-lg me-3"></i>
                <h4 class="mb-0 fw-bold">Edit Forum: {$forum_data['title']}</h4>
                <span class="badge bg-light text-primary ms-3">v2.0</span>
            </div>
            <p class="mb-0 mt-2 opacity-75" style="font-size: 13px;">
                <i class="fas fa-info-circle me-1"></i>
                Update and configure your forum settings
            </p>
        </div>
        
        <div class="card-body p-4">
            <!-- Forum Type Selection -->
            <div class="form-section mb-5">
                <div class="section-header d-flex align-items-center mb-4">
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary me-3">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">Forum Type</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">
                            Select the type of forum you are editing
                        </p>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="type-card {$isForumActive}" onclick="selectType('forum')" data-type="forum">
HTML;

// Выводим radio button для форума
echo generate_radio_button('type', 'f', 'Forum', $create_a_options_f);

echo <<<HTML
                            <div class="type-card-body">
                                <div class="type-icon">
                                    <i class="fas fa-comments fa-2x"></i>
                                </div>
                                <h6 class="fw-bold mt-3 mb-2">Standard Forum</h6>
                                <p class="text-muted small mb-0">
                                    A regular forum where users can post threads and replies
                                </p>
HTML;

if ($isForumActive == 'active') {
    echo '<div class="type-badge mt-2"><span class="badge bg-primary">Current</span></div>';
}

echo <<<HTML
                            </div>
                            <div class="type-check">
                                <i class="{$forumCheckIcon}"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="type-card {$isCategoryActive}" onclick="selectType('category')" data-type="category">
HTML;

// Выводим radio button для категории
echo generate_radio_button('type', 'c', 'Category', $create_a_options_c);

echo <<<HTML
                            <div class="type-card-body">
                                <div class="type-icon">
                                    <i class="fas fa-folder fa-2x"></i>
                                </div>
                                <h6 class="fw-bold mt-3 mb-2">Category</h6>
                                <p class="text-muted small mb-0">
                                    A container for organizing multiple forums together
                                </p>
                            </div>
                            <div class="type-check">
                                <i class="{$categoryCheckIcon}"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Basic Information -->
            <div class="form-section mb-5">
                <div class="section-header d-flex align-items-center mb-4">
                    <div class="icon-circle bg-info bg-opacity-10 text-info me-3">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">Basic Information</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">
                            Essential details for your forum
                        </p>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label fw-bold d-flex align-items-center mb-2">
                                <i class="fas fa-heading me-2 text-primary"></i>
                                Forum Title
                                <span class="required-badge ms-2">Required</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-font text-muted"></i>
                                </span>
                                <input type="text" 
                                       class="form-control form-control-lg border-start-0 ps-0" 
                                       name="title" 
                                       value="{$forum_data['title']}"
                                       placeholder="Enter forum title (e.g., 'General Discussion')"
                                       required>
                            </div>
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-lightbulb me-1"></i>
                                Choose a clear, descriptive name for your forum
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label fw-bold d-flex align-items-center mb-2">
                                <i class="fas fa-sort-numeric-up me-2 text-primary"></i>
                                Display Order
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-list-ol text-muted"></i>
                                </span>
                                <input type="number" 
                                       class="form-control form-control-lg border-start-0 ps-0" 
                                       name="disporder" 
                                       value="{$forum_data['disporder']}" 
                                       min="0"
                                       placeholder="Position in forum list">
                            </div>
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Lower numbers appear first in the forum list
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label fw-bold d-flex align-items-center mb-2">
                                <i class="fas fa-align-left me-2 text-primary"></i>
                                Description
                            </label>
                            <textarea name="description" 
                                      class="form-control form-control-lg" 
                                      id="description" 
                                      rows="4" 
                                      placeholder="Briefly describe the purpose of this forum (optional)"
                                      style="resize: vertical;">{$forum_data['description']}</textarea>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div class="form-text text-muted">
                                    <i class="fas fa-keyboard me-1"></i>
                                    Optional description shown below forum title
                                </div>
                                <div class="character-count text-muted small">
                                    <span id="charCount">{$descriptionLength}</span>/500 characters
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Parent Forum Selection -->
            <div class="form-section mb-5">
                <div class="section-header d-flex align-items-center mb-4">
                    <div class="icon-circle bg-success bg-opacity-10 text-success me-3">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">Forum Hierarchy</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">
                            Organize your forum within the site structure
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="form-group">
                            <label class="form-label fw-bold d-flex align-items-center mb-2">
                                <i class="fas fa-folder-tree me-2 text-primary"></i>
                                Parent Forum
                                <span class="required-badge ms-2">Required</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-level-up-alt text-muted"></i>
                                </span>
HTML;

$fff = generate_forum_select('pid', $forum_data['pid'], array(
    'id' => 'pid', 
    'main_option' => 'None (Top Level)', 
    'class' => 'form-select form-select-lg border-start-0 ps-0'
));

echo $fff;

echo <<<HTML
                            </div>
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Select where this forum should appear in the hierarchy
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="hierarchy-preview p-3 border rounded bg-light">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-eye me-2"></i>
                                Structure Preview
                            </h6>
                            <div class="hierarchy-tree small">
                                <div class="tree-item active">
                                    <i class="fas fa-folder-open me-1 text-warning"></i>
                                    <span class="text-muted">Selected Parent</span>
                                </div>
                                <div class="tree-item ms-3 mt-2">
                                    <i class="fas fa-arrow-right me-1 text-success"></i>
                                    <i class="fas fa-edit me-1 text-primary"></i>
                                    <strong>Editing: {$forum_data['title']}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Actions -->
            <div class="form-section pt-4 border-top">
                <div class="row">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-outline-secondary btn-lg px-4" onclick="window.history.back()">
                            <i class="fas fa-arrow-left me-2"></i>
                            Cancel
                        </button>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="submit" name="save" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-save me-2"></i>
                            Save Changes
                        </button>
                        <button type="button" class="btn btn-primary btn-lg ms-2" onclick="showAdvancedOptions()">
                            <i class="fas fa-cogs me-2"></i>
                            Advanced
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectType(type) {
    // Update radio buttons
    document.querySelectorAll('.type-card').forEach(card => {
        card.classList.remove('active');
        const checkIcon = card.querySelector('.type-check i');
        checkIcon.className = 'far fa-circle';
    });
    
    const selectedCard = document.querySelector(`[data-type="\${type}"]`);
    if (selectedCard) {
        selectedCard.classList.add('active');
        const selectedCheckIcon = selectedCard.querySelector('.type-check i');
        selectedCheckIcon.className = 'fas fa-check-circle';
        
        // Update the actual radio button
        const radio = selectedCard.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
        }
    }
}

// Initialize type selection based on current value
document.addEventListener('DOMContentLoaded', function() {
    const activeRadio = document.querySelector('input[name="type"]:checked');
    if (activeRadio) {
        const activeType = activeRadio.value === 'f' ? 'forum' : 'category';
        selectType(activeType);
    }
    
    // Character count for description
    const description = document.getElementById('description');
    const charCount = document.getElementById('charCount');
    
    if (description && charCount) {
        description.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
    }
});
</script>
HTML;


















	
	
	
echo '</br>';

// Создаем новый FormContainer для дополнительных опций (как в оригинале)
$form_container = new FormContainer('Additional Forum Options66666666666678677');

// Здесь начинается ваш стилизованный блок дополнительных опций
echo <<<HTML
<style>
    .toggle-options-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.25);
    }
    
    .toggle-options-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
    }
    
    .toggle-options-btn i {
        transition: transform 0.4s ease;
        font-size: 18px;
    }
    
    #additional_options_link .toggle-options-btn i {
        transform: rotate(0deg);
    }
    
    #additional_options .toggle-options-btn i {
        transform: rotate(180deg);
    }
    
    #additional_options {
        animation: slideDown 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        transform-origin: top;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-15px) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .form-row {
        margin-bottom: 28px;
        padding-bottom: 28px;
        border-bottom: 2px solid #f0f2f5;
        position: relative;
    }
    
    .form-row:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .form-row::before {
        content: '';
        position: absolute;
        left: -28px;
        top: 0;
        height: 100%;
        width: 4px;
        background: linear-gradient(to bottom, #667eea, #764ba2);
        border-radius: 0 4px 4px 0;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .form-row:hover::before {
        opacity: 1;
    }
    
    .form-label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 12px;
        font-size: 15px;
    }
    
    .form-label i {
        color: #667eea;
        font-size: 16px;
    }
    
    .form-description {
        color: #718096;
        font-size: 14px;
        margin-bottom: 16px;
        line-height: 1.6;
        padding-left: 26px;
        position: relative;
    }
    
    .form-description::before {
        content: 'ℹ️';
        position: absolute;
        left: 0;
        top: 2px;
    }
    
    .form-control {
        width: 100%;
        max-width: 450px;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
        background: white;
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        outline: none;
        transform: translateY(-2px);
    }
    
    .form-check {
        margin-bottom: 16px;
        display: flex;
        align-items: flex-start;
        background: white;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        transition: all 0.3s;
    }
    
    .form-check:hover {
        border-color: #667eea;
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .form-check-input {
        margin-top: 3px;
        margin-right: 12px;
        transform: scale(1.2);
        accent-color: #667eea;
    }
    
    .form-check-label {
        color: #4a5568;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .form-check-label i {
        color: #667eea;
        margin-right: 8px;
        font-size: 14px;
    }
    
    .form-check-label small {
        display: block;
        color: #718096;
        margin-top: 4px;
        font-size: 13px;
        font-weight: normal;
    }
    
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-top: 20px;
    }
    
    .settings-group {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 20px;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }
    
    .settings-group::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }
    
    .settings-group:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border-color: #cbd5e0;
    }
    
    .settings-group-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 16px;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .settings-group-title i {
        color: #667eea;
        font-size: 16px;
    }
    
    .form-select {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23667eea' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 16px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }
    
    .form-select option {
        padding: 8px;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
        color: #667eea;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 10px;
    }
    
    .form-control-sm {
        max-width: 450px;
    }
    
    .card-header .toggle-options-btn {
        font-size: 14px;
        padding: 10px 20px;
    }
    
    .forum-type-options {
        display: flex;
        gap: 30px;
        margin-top: 10px;
    }
    
    .forum-type-option {
        flex: 1;
    }
</style>

<script>
function toggleAdditionalOptions() {
    var link = document.getElementById('additional_options_link');
    var options = document.getElementById('additional_options');
    
    if (options.style.display === 'block' || options.style.display === '') {
        link.style.display = 'block';
        options.style.display = 'none';
    } else {
        link.style.display = 'none';
        options.style.display = 'block';
    }
    return false;
}
</script>

<div id="additional_options_link" class="text-center" style="padding: 40px 0;">
    <button onclick="return toggleAdditionalOptions();" class="toggle-options-btn">
        <i class="fas fa-cogs fa-lg"></i>
        <span>Show Additional Forum Options</span>
        <i class="fas fa-chevron-down"></i>
    </button>
    <p style="margin-top: 15px; color: #718096; font-size: 13px;">
        <i class="fas fa-info-circle"></i> Advanced settings for forum configuration
    </p>
</div>

<div id="additional_options" style="display: none;">
<div class="card">
    <div class="card-header rounded-bottom">
        <span>
            <i class="fas fa-sliders-h fa-lg"></i>
            Additional Forum Options
            <span class="badge">
                <i class="fas fa-star"></i> Advanced
            </span>
        </span>
        <button onclick="return toggleAdditionalOptions();" class="toggle-options-btn" style="font-size: 14px; padding: 10px 20px;">
            <i class="fas fa-times"></i>
            Hide Options
            <i class="fas fa-chevron-up"></i>
        </button>
    </div>
    <div class="card-body">
HTML;

// Код дополнительных опций с вашими данными
echo <<<HTML
        <div class="form-row">
            <label class="form-label" for="linkto">
                <i class="fas fa-external-link-alt"></i>
                Forum Link (Redirect)
            </label>
            <div class="form-description">
                To make a forum redirect to another location, enter the URL to the destination you wish to redirect to. 
                Entering a URL in this field will remove the forum functionality; however, permissions can still be set for it.
            </div>
            <div style="position: relative; max-width: 450px;">
                <i class="fas fa-link" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                <input type="text" name="linkto" value="{$forum_data['linkto']}" class="form-control" id="linkto" placeholder="https://example.com" style="padding-left: 45px;" />
            </div>
        </div>
        
        <div class="form-row">
            <label class="form-label" for="password">
                <i class="fas fa-lock"></i>
                Forum Password Protection
            </label>
            <div class="form-description">
                To protect this forum further, you can choose a password that must be entered for access. 
                Note: User groups still need permissions to access this forum.
            </div>
            <div style="position: relative; max-width: 450px;">
                <i class="fas fa-key" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                <input type="text" name="password" value="{$forum_data['password']}" class="form-control" id="password" placeholder="Enter password (optional)" style="padding-left: 45px;" />
            </div>
        </div>
        
        <div class="form-row">
            <label class="form-label">
                <i class="fas fa-shield-alt"></i>
                Access Control Options
            </label>
            <div class="settings-grid">
                <div class="form-check">
                    <input type="checkbox" name="active" value="1" class="form-check-input" id="active" 
HTML;

echo ($forum_data['active'] ? 'checked="checked"' : '') . ' />';

echo <<<HTML
                    <label class="form-check-label" for="active">
                        <i class="fas fa-toggle-on"></i>
                        Forum is Active
                        <small>If unselected, this forum will not be shown to users and will not "exist".</small>
                    </label>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" name="open" value="1" class="form-check-input" id="open" 
HTML;

echo ($forum_data['open'] ? 'checked="checked"' : '') . ' />';

echo <<<HTML
                    <label class="form-check-label" for="open">
                        <i class="fas fa-door-open"></i>
                        Forum is Open
                        <small>If unselected, users will not be able to post in this forum regardless of permissions.</small>
                    </label>
                </div>
            </div>
        </div>
        
        <div class="form-row">
            <label class="form-label">
                <i class="fas fa-eye"></i>
                Default View & Display Options
            </label>
            <div class="settings-grid">
                <div class="settings-group">
                    <div class="settings-group-title">
                        <i class="fas fa-calendar-alt"></i>
                        Date Range Filter
                    </div>
                    <select class="form-select" name="defaultdatecut" id="defaultdatecut">
HTML;

$default_date_cut = array(
    0 => '📅 Board Default',
    1 => '⏰ Last 24 hours',
    5 => '📆 Last 5 days',
    10 => '🗓️ Last 10 days',
    20 => '📅 Last 20 days',
    50 => '🗓️ Last 50 days',
    75 => '📆 Last 75 days',
    100 => '📅 Last 100 days',
    365 => '🎄 Last year',
    9999 => '🚀 All time',
);

foreach ($default_date_cut as $value => $label) {
    $selected = ($forum_data['defaultdatecut'] == $value) ? 'selected="selected"' : '';
    echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
}

echo <<<HTML
                    </select>
                </div>
                
                <div class="settings-group">
                    <div class="settings-group-title">
                        <i class="fas fa-sort-amount-down"></i>
                        Sort By Field
                    </div>
                    <select class="form-select" name="defaultsortby" id="defaultsortby">
HTML;

$default_sort_by = array(
    "" => '⚙️ Board Default',
    "subject" => '📝 Thread subject',
    "lastpost" => '🕒 Last post time',
    "starter" => '👤 Thread starter',
    "started" => '⏱️ Thread creation time',
    "rating" => '⭐ Thread rating',
    "replies" => '💬 Number of replies',
    "views" => '👁️ Number of views',
);

foreach ($default_sort_by as $value => $label) {
    $selected = ($forum_data['defaultsortby'] == $value) ? 'selected="selected"' : '';
    echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
}

echo <<<HTML
                    </select>
                </div>
                
                <div class="settings-group">
                    <div class="settings-group-title">
                        <i class="fas fa-sort-alpha-down"></i>
                        Sort Order Direction
                    </div>
                    <select class="form-select" name="defaultsortorder" id="defaultsortorder">
HTML;

$default_sort_order = array(
    "" => '⚙️ Board Default',
    "asc" => '⬆️ Ascending (A-Z, Old-New)',
    "desc" => '⬇️ Descending (Z-A, New-Old)',
);

foreach ($default_sort_order as $value => $label) {
    $selected = ($forum_data['defaultsortorder'] == $value) ? 'selected="selected"' : '';
    echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
}

echo <<<HTML
                    </select>
                </div>
            </div>
        </div>
        
        <div class="form-row">
            <label class="form-label">
                <i class="fas fa-cogs"></i>
                Statistics & Counting Options
            </label>
            <div class="settings-grid">
                <div class="form-check">
                    <input type="checkbox" name="usepostcounts" value="1" class="form-check-input" id="usepostcounts" 
HTML;

echo ($forum_data['usepostcounts'] ? 'checked="checked"' : '') . ' />';

echo <<<HTML
                    <label class="form-check-label" for="usepostcounts">
                        <i class="fas fa-comment-alt"></i>
                        Count posts in user statistics
                        <small>Posts in this forum will contribute to user's total post count</small>
                    </label>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" name="usethreadcounts" value="1" class="form-check-input" id="usethreadcounts" 
HTML;

echo ($forum_data['usethreadcounts'] ? 'checked="checked"' : '') . ' />';

echo <<<HTML
                    <label class="form-check-label" for="usethreadcounts">
                        <i class="fas fa-file-alt"></i>
                        Count threads in user statistics
                        <small>Threads in this forum will contribute to user's total thread count</small>
                    </label>
                </div>
            </div>
        </div>
HTML;

echo "</div></div>";

// Закрываем FormContainer и форму
// $form_container->end();
	

		
		


	
	//$form_container->end();
	
	
	echo "</form>";

	
	echo "</br>";

	$cached_forum_perms = $cache->read("forumpermissions");
	$field_list = array(
		'canview' => 'Can view?',
		'canpostthreads' => 'Can post threads?',
		'canpostreplys' => 'Can post replies?',
		'canpostpolls' => 'Can post polls?',
	);

	$field_list2 = array(
		'canview' => '&#149; View',
		'canpostthreads' => '&#149; Post Threads',
		'canpostreplys' => '&#149; Post Replies',
		'canpostpolls' => '&#149; Post Polls',
	);

	$ids = array();

	$form_container = new FormContainer(sprintf('Forum22222222 Permissions in '.$forum_data['name'].''));
	//$form_container->output_row_header('Group', array("class" => "align_center", 'style' => 'width: 30%'));
	//$form_container->output_row_header('Overview: Allowed Actions', array("class" => "align_center"));
	//$form_container->output_row_header('Overview: Disallowed Actions', array("class" => "align_center"));
	//$form_container->output_row_header('Controls', array("class" => "align_center", 'style' => 'width: 120px', 'colspan' => 2));
	
	
	
	
	
	echo '
	
	
	
       <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		  Forum Permissions in '.$forum_data['name'].'
	      </div>
	   </div>
	';


		
		echo '
	
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Group</th>
        <th>Overview: Allowed Actions</th>
        <th>Overview: Disallowed Actions</th>
		<th>Controls</th>
     
      </tr>
    </thead>';
	
	
	
	
	
	

	if($mybb->request_method == "post")
	{
		foreach($usergroupsSSS as $usergroup)
		{
			if(isset($mybb->input['fields_'.$usergroup['gid']]))
			{
				$input_permissions = $mybb->input['fields_'.$usergroup['gid']];
				if(!is_array($input_permissions))
				{
					// Convering the comma separated list from Javascript form into a variable
					$input_permissions = explode(',' , $input_permissions);
				}
				foreach($input_permissions as $input_permission)
				{
					$mybb->input['permissions'][$usergroup['gid']][$input_permission] = 1;
				}
			}
		}
	}

	foreach($usergroupsSSS as $usergroup)
	{
		$perms = array();
		if(isset($mybb->input['default_permissions']))
		{
			if($mybb->input['default_permissions'][$usergroup['gid']])
			{
				if(is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
				{
					$perms = $existing_permissions[$usergroup['gid']];
					$default_checked = false;
				}
				elseif(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['fid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
					$default_checked = true;
				}
				else if(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['pid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
					$default_checked = true;
				}
			}

			if(!$perms)
			{
				$perms = $usergroup;
				$default_checked = true;
			}
		}
		else
		{
			if(isset($existing_permissions) && is_array($existing_permissions) && !empty($existing_permissions[$usergroup['gid']]))
			{
				$perms = $existing_permissions[$usergroup['gid']];
				$default_checked = false;
			}
			elseif(is_array($cached_forum_perms) && !empty($cached_forum_perms[$forum_data['fid']][$usergroup['gid']]))
			{
				$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
				$default_checked = true;
			}
			else if(is_array($cached_forum_perms) && !empty($cached_forum_perms[$forum_data['pid']][$usergroup['gid']]))
			{
				$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
				$default_checked = true;
			}

			if(!$perms)
			{
				$perms = $usergroup;
				$default_checked = true;
			}
		}

		foreach($field_list as $forum_permission => $forum_perm_title)
		{
			if(isset($mybb->input['permissions']))
			{
				if($mybb->input['permissions'][$usergroup['gid']][$forum_permission])
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
			}
			else
			{
				if($perms[$forum_permission] == 1)
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
			}
		}
		$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);

		if($default_checked)
		{
			$inherited_text = 'inherited';
		}
		else
		{
			$inherited_text = 'custom';
		}

		//$form_container->output_cell("<strong>{$usergroup['title']}</strong> <small style=\"vertical-align: middle;\">({$inherited_text})</small>");
		
		echo '
			<tr>
			<td><strong>'.$usergroup['title'].'</strong> <small>('.$inherited_text.')</small></td>
			
			';
		
		

		$field_select = "<div class=\"quick_perm_fields\">\n";
		$field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_{$usergroup['gid']}\">\n";
		foreach($perms_checked as $perm => $value)
		{
			if($value == 1)
			{
				$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
			}
		}
		$field_select .= "</ul></div>\n";
		$field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_{$usergroup['gid']}\">\n";
		foreach($perms_checked as $perm => $value)
		{
			if($value == 0)
			{
				$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
			}
		}
		//$field_select .= "</ul></div></div>\n";
		$field_select .= "</ul></div></div></td>\n";
		$field_select .= $form->generate_hidden_field("fields_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_'.$usergroup['gid']));
		$field_select .= $form->generate_hidden_field("fields_inherit_".$usergroup['gid'], (int)$default_checked, array('id' => 'fields_inherit_'.$usergroup['gid']));
		$field_select .= $form->generate_hidden_field("fields_default_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_default_'.$usergroup['gid']));
		$field_select = str_replace("'", "\\'", $field_select);
		$field_select = str_replace("\n", "", $field_select);

		$field_select = "<script type=\"text/javascript\">
//<![CDATA[
document.write('".str_replace("/", "\/", $field_select)."');
//]]>
</script>\n";

		$field_selected = array();
		foreach($field_list as $forum_permission => $permission_title)
		{
			$field_options[$forum_permission] = $permission_title;
			if($perms_checked[$forum_permission])
			{
				$field_selected[] = $forum_permission;
			}
		}

		$field_select .= "<noscript>".$form->generate_select_box('fields_'.$usergroup['gid'].'[]', $field_options, $field_selected, array('id' => 'fields_'.$usergroup['gid'].'[]', 'multiple' => true))."</noscript>\n";
		//$form_container->output_cell($field_select, array('colspan' => 2));
		
		echo '<td>'.$field_select.'</td>';

		if(!$default_checked)
		{
			
			
			echo "<td align=right>
			<a href=\"index.php?act=management&action=permissions&amp;pid={$perms['pid']}\" onclick=\"popupWindow('index.php?act=management&action=permissions&pid={$perms['pid']}&ajax=1', null, true); return false;\">Edit Permissions3333333</a></td>";
			
			
			echo "<td align=right><a href=\"index.php?act=management&action=clear_permission&amp;pid={$perms['pid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{confirm_clear_custom_permission55555555}')\">Clear Custom Permissions</a></td>";
		}
		else
		{
			
			echo "
			<td align=right>
			<a href=\"index.php?act=management&action=permissions&amp;gid={$usergroup['gid']}&amp;fid={$fid}\" onclick=\"popupWindow('index.php?act=management&action=permissions&gid={$usergroup['gid']}&fid={$fid}&ajax=1', null, true); return false;\">Set Custom Permissions</a>
			</td>
			</tr>";
		}

		$form_container->construct_row(array('id' => 'row_'.$usergroup['gid']));

		$ids[] = $usergroup['gid'];
	}
	//$form_container->end();
	
	//echo "</form>";

	//$buttons[] = $form->generate_submit_button('Save Forum');
	//$form->output_submit_wrapper($buttons);
	
	
	echo '</table></div>';
	
	
	
	
	
	echo '<div class="card-footer text-center">
	<tr><td colspan=3 align=center>
<input type="submit" value="Save Forum" class="btn btn-primary"> 
</td></tr>
</div>';
	
	
	echo "</form>";

	// Write in our JS based field selector
echo "<script type=\"text/javascript\">\n<!--\n";
echo "document.addEventListener('DOMContentLoaded', function() {\n";
foreach($ids as $id)
{
    echo "    if(typeof QuickPermEditor !== 'undefined') QuickPermEditor.init(".$id.");\n";
}
echo "});\n";
echo "// -->\n</script>\n";

	stdfoot();
}

if($mybb->input['action'] == "deletemod")
{
	$modid = $mybb->get_input('id', MyBB::INPUT_INT);
	$isgroup = $mybb->get_input('isgroup', MyBB::INPUT_INT);
	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

	$query = $db->simple_select("moderators", "*", "id='{$modid}' AND isgroup = '{$isgroup}' AND fid='{$fid}'");
	$mod = $db->fetch_array($query);

	// Does the forum not exist?
	if(!$mod)
	{
		flash_message($lang->forum_management['error_invalid_moderator'], 'error');
		admin_redirect("index.php?act=management&fid={$fid}");
	}

	// User clicked no
	if(!empty($mybb->input['no']))
	{
		admin_redirect("index.php?act=management&fid={$fid}");
	}

	$plugins->run_hooks("admin_forum_management_deletemod");

	if($mybb->request_method == "post")
	{
		$mid = $mod['mid'];
		if($mybb->input['isgroup'])
		{
			$query = $db->sql_query("
				SELECT m.*, g.title
				FROM moderators m
				LEFT JOIN usergroups g ON (g.gid=m.id)
				WHERE m.mid='{$mid}'
			");
		}
		else
		{
			$query = $db->sql_query("
				SELECT m.*, u.username, u.usergroup
				FROM moderators m
				LEFT JOIN users u ON (u.id=m.id)
				WHERE m.mid='{$mid}'
			");
		}
		$mod = $db->fetch_array($query);

		$db->delete_query("moderators", "mid='{$mid}'");

		$plugins->run_hooks("admin_forum_management_deletemod_commit");

		$cache->update_moderators();

		$forum = get_forum($fid, 1);

		// Log admin action
		if($isgroup)
		{
			log_admin_action($mid, $mod['title'], $forum['fid'], $forum['name']);
		}
		else
		{
			log_admin_action($mid, $mod['username'], $forum['fid'], $forum['name']);
		}

		flash_message($lang->forum_management['success_moderator_deleted'], 'success');
		admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
	}
}







if ($mybb->input['action'] === 'delete')
{
    // Разрешаем ТОЛЬКО POST
    if ($mybb->request_method !== 'post')
    {
        http_response_code(405);
        echo 'Method not allowed';
        exit;
    }

    // CSRF (если используешь my_post_key)
    if (!verify_post_check($mybb->get_input('my_post_key')))
    {
        http_response_code(403);
        echo 'Invalid security token';
        exit;
    }

    // Получаем fid
    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
    if (!$fid)
    {
        http_response_code(400);
        echo 'Invalid forum id';
        exit;
    }

    // Проверяем существование форума
    $query = $db->simple_select('tsf_forums', '*', "fid='{$fid}'");
    $forum = $db->fetch_array($query);

    if (!$forum)
    {
        http_response_code(404);
        echo 'Forum not found';
        exit;
    }

    $plugins->run_hooks('admin_forum_management_delete');

    // -------------------------------------------------
    // Ищем подфорумы
    // -------------------------------------------------
    $delquery = '';
    $fids = [];

    switch ($db->type)
    {
        case 'pgsql':
        case 'sqlite':
            $query = $db->simple_select(
                'tsf_forums',
                '*',
                "','||parentlist||',' LIKE '%,$fid,%'"
            );
            break;

        default:
            $query = $db->simple_select(
                'tsf_forums',
                '*',
                "CONCAT(',', parentlist, ',') LIKE '%,$fid,%'"
            );
    }

    while ($subforum = $db->fetch_array($query))
    {
        $fids[] = (int)$subforum['fid'];
        $delquery .= " OR fid='{$subforum['fid']}'";
    }

    // -------------------------------------------------
    // Удаляем темы
    // -------------------------------------------------
    require_once INC_PATH . '/class_moderation.php';
    $moderation = new Moderation();

    // Удаляем ВСЕ темы сразу (без HTML-пагинации)
    $query = $db->simple_select(
        'tsf_threads',
        'tid',
        "fid='{$fid}' {$delquery}"
    );

    while ($tid = $db->fetch_field($query, 'tid'))
    {
        $moderation->delete_thread((int)$tid);
    }

    // -------------------------------------------------
    // Удаляем форум и подфорумы
    // -------------------------------------------------
    $db->delete_query('tsf_forums', "fid='{$fid}'");

    switch ($db->type)
    {
        case 'pgsql':
        case 'sqlite':
            $db->delete_query(
                'tsf_forums',
                "','||parentlist||',' LIKE '%,$fid,%'"
            );
            break;

        default:
            $db->delete_query(
                'tsf_forums',
                "CONCAT(',', parentlist, ',') LIKE '%,$fid,%'"
            );
    }

    // -------------------------------------------------
    // Чистим связанные таблицы
    // -------------------------------------------------
    $db->delete_query('moderators', "fid='{$fid}' {$delquery}");
    $db->delete_query('tsf_forumsubscriptions', "fid='{$fid}' {$delquery}");
    $db->delete_query('forumpermissions', "fid='{$fid}' {$delquery}");
    $db->delete_query('tsf_announcements', "fid='{$fid}' {$delquery}");
    $db->delete_query('tsf_forumsread', "fid='{$fid}' {$delquery}");

    // -------------------------------------------------
    // Хуки, кеши, лог
    // -------------------------------------------------
    $plugins->run_hooks('admin_forum_management_delete_commit');

    $cache->update_forums();
    $cache->update_moderators();
    $cache->update_forumpermissions();
    $cache->update_forumsdisplay();

    log_admin_action($fid, $forum['name']);

    // -------------------------------------------------
    // AJAX-ответ
    // -------------------------------------------------
    echo 'Forum deleted successfully';
    exit;
}













if (!$mybb->input['action']) {
    if (!isset($mybb->input['fid'])) {
        $mybb->input['fid'] = 0;
    }

    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
    if ($fid) {
        $forum = get_forum($fid, 1);
    }

    $plugins->run_hooks("admin_forum_management_start");

    if($mybb->request_method == "post")
	{
		if($mybb->get_input('update') == "permissions")
		{
			$inherit = array();
			foreach($mybb->input as $id => $permission)
			{
				// Make sure we're only skipping inputs that don't start with "fields_" and aren't fields_default_ or fields_inherit_
				if(strpos($id, 'fields_') === false || (strpos($id, 'fields_default_') !== false || strpos($id, 'fields_inherit_') !== false))
				{
					continue;
				}

				list(, $gid) = explode('fields_', $id);

				if($mybb->input['fields_default_'.$gid] == $permission && $mybb->input['fields_inherit_'.$gid] == 1)
				{
					$inherit[$gid] = 1;
					continue;
				}
				$inherit[$gid] = 0;

				// If it isn't an array then it came from the javascript form
				if(!is_array($permission))
				{
					$permission = explode(',', $permission);
					$permission = array_flip($permission);
					foreach($permission as $name => $value)
					{
						$permission[$name] = 1;
					}
				}
				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls') as $name)
				{
					if(!empty($permission[$name]))
					{
						$permissions[$name][$gid] = 1;
					}
					else
					{
						$permissions[$name][$gid] = 0;
					}
				}
			}

			if(isset($permissions['canview']))
			{
				$canview = $permissions['canview'];
			}
			if(isset($permissions['canpostthreads']))
			{
				$canpostthreads = $permissions['canpostthreads'];
			}
			if(isset($permissions['canpostpolls']))
			{
				$canpostpolls = $permissions['canpostpolls'];
			}
			if(isset($permissions['canpostattachments']))
			{
				$canpostattachments = $permissions['canpostattachments'];
			}
			if(isset($permissions['canpostreplys']))
			{
				$canpostreplies = $permissions['canpostreplys'];
			}

			save_quick_perms($fid);

			$plugins->run_hooks("admin_forum_management_start_permissions_commit");

			$cache->update_forums();

			// Log admin action
			log_admin_action('quickpermissions', $fid, $forum['name']);

			flash_message($lang->forum_management['success_forum_permissions_updated'], 'success');
			admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");
		}
		elseif($mybb->get_input('add') == "moderators")
		{
			$forum = get_forum($fid, 1);
			if(!$forum)
			{
				flash_message($lang->forum_management['error_invalid_forum'], 'error');
				admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
			}
			if(!empty($mybb->input['usergroup']))
			{
				$isgroup = 1;
				$gid = $mybb->get_input('usergroup', MyBB::INPUT_INT);

				if(!$groupscache[$gid])
 				{
 					// Didn't select a valid moderator
 					flash_message($lang->forum_management['error_moderator_not_found'], 'error');
 					admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
 				}

				$newmod = array(
					"id" => $gid,
					"name" => $groupscache[$gid]['title']
				);
			}
			else
			{
				$options = array(
					'fields' => array('id AS id', 'username AS name', 'usergroup', 'additionalgroups')
				);
				$newmod = $newmoduser = get_user_by_username($mybb->input['username'], $options);

				if(empty($newmod['id']))
				{
					flash_message($lang->forum_management['error_moderator_not_found'], 'error');
					admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
				}

				$isgroup = 0;
			}

			if($newmod['id'])
			{
				$query = $db->simple_select("moderators", "id", "id='".$newmod['id']."' AND fid='".$fid."' AND isgroup='{$isgroup}'", array('limit' => 1));

				if(!$db->num_rows($query))
				{
					$new_mod = array(
						"fid" => $fid,
						"id" => $newmod['id'],
						"isgroup" => $isgroup,
						"caneditposts" => 1,
						"cansoftdeleteposts" => 1,
						"canrestoreposts" => 1,
						"candeleteposts" => 1,
						"cansoftdeletethreads" => 1,
						"canrestorethreads" => 1,
						"candeletethreads" => 1,
						"canviewips" => 1,
						"canviewunapprove" => 1,
						"canviewdeleted" => 1,
						"canopenclosethreads" => 1,
						"canstickunstickthreads" => 1,
						"canapproveunapprovethreads" => 1,
						"canapproveunapproveposts" => 1,
						"canapproveunapproveattachs" => 1,
						"canmanagethreads" => 1,
						"canmanagepolls" => 1,
						"canpostclosedthreads" => 1,
						"canmovetononmodforum" => 1,
						"canusecustomtools" => 1,
						"canmanageannouncements" => 1,
						"canmanagereportedposts" => 1,
						"canviewmodlog" => 1
					);

					$mid = $db->insert_query("moderators", $new_mod);

					if(!$isgroup)
					{
						$newmodgroups = $newmoduser['usergroup'];
						if(!empty($newmoduser['additionalgroups']))
						{
							$newmodgroups .= ','.$newmoduser['additionalgroups'];
						}
						$groupperms = usergroup_permissions($newmodgroups);

						// Check if new moderator already belongs to a moderators group
						if($groupperms['canmodcp'] != 1)
						{
							if($newmoduser['usergroup'] == 2 || $newmoduser['usergroup'] == 5)
							{
								// Primary group is default registered or awaiting activation group so change primary group to Moderators
								$db->update_query("users", array('usergroup' => 6), "id='{$newmoduser['id']}'");
							}
							else
							{
								// Primary group is another usergroup without canmodcp so add Moderators to additional groups
								join_usergroup($newmoduser['id'], 6);
							}
						}
					}

					$plugins->run_hooks("admin_forum_management_start_moderators_commit");

					$cache->update_moderators();

					// Log admin action
					log_admin_action('addmod', $mid, $newmod['name'], $fid, $forum['name']);

					flash_message($lang->forum_management['success_moderator_added'], 'success');
					admin_redirect("index.php?act=management&action=editmod&mid={$mid}");
				}
				else
				{
					flash_message($lang->forum_management['error_moderator_already_added'], 'error');
					admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
				}
			}
			else
			{
				flash_message($lang->forum_management['error_moderator_not_found'], 'error');
				admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
			}
		}
		else
		{
			// ============================================
			// ИСПРАВЛЕНИЕ: Обработка кнопки "Save Changes"
			// ============================================
			if (isset($mybb->input['save_forum_orders']) || isset($mybb->input['save_order'])) {
				if (!empty($mybb->input['disporder']) && is_array($mybb->input['disporder'])) {
					foreach ($mybb->input['disporder'] as $update_fid => $order) {
						$db->update_query("tsf_forums", array('disporder' => (int)$order), "fid='" . (int)$update_fid . "'");
					}

					$plugins->run_hooks("admin_forum_management_start_disporder_commit");

					$cache->update_forums();

					// Log admin action
					if (!empty($forum)) {
						log_admin_action('orders', $forum['fid'], $forum['name']);
					} else {
						log_admin_action('orders', 0);
					}

					flash_message($lang->forum_management['success_forum_disporder_updated'], 'success');
					admin_redirect("index.php?act=management&fid=" . $mybb->input['fid']);
				}
			}
		}
	}

    // ============================================
    // НАЧАЛО - СОВРЕМЕННЫЙ ДИЗАЙН
    // ============================================

    $extra_header .= "<script src=\"scripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";
	
	if($fid)
	{
		$page->add_breadcrumb_item('View Forum', "index.php?act=management");
    }

    // Инициализируем объекты формы ДО их использования
    $form = new Form("index.php?act=management", "post", "management");
    echo $form->generate_hidden_field("fid", $mybb->input['fid']);
    
    // Загружаем кеш форумов если нужно
    if (!isset($forum_cache) || !is_array($forum_cache)) {
        cache_forums();
    }
    
    // Создаем контейнер формы
    if ($fid && isset($forum_cache[$fid])) {
        $form_container = new FormContainer('Forums in ' . htmlspecialchars($forum_cache[$fid]['name']));
    } else {
        $form_container = new FormContainer('Manage Forums');
    }

    // Заголовок страницы
    stdhead('Forum Management');
	
	echo "		<div class=\"container mt-3\">\n";
	echo "		<div id=\"content\">\n";
	echo "			<div class=\"breadcrumb\">\n";
	echo $page->_generate_breadcrumb();
	echo "			</div>\n";
	echo "			</div>\n";
	
	echo $extra_header;
	
	echo "	<link rel=\"stylesheet\" href=\"templates/forum.css?ver=1813\" type=\"text/css\" />\n";
	echo "	<link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
	echo "	<link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
	
	echo "	<script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";
	echo "	<script type=\"text/javascript\" src=\"scripts/popup.js\"></script>\n";

	// Stop JS elements showing while page is loading (JS supported browsers only)
	echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
	echo "  <script type=\"text/javascript\">\n".
			"//<![CDATA[\n".
			"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
			"//]]>\n".
			"</script>\n";

	if($fid)
	{
		output_nav_tabs($sub_tabs, 'View Forum');
	}
	else
	{
		output_nav_tabs($sub_tabs, 'Forum Management');
	}

    echo '
    <!-- Современный дизайн админ-панели -->
    <div class="admin-container">
        <div class="container mt-3">
            
            <!-- Хлебные крошки -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb bg-white rounded-3 shadow-sm py-2 px-3">
                    ' . ($fid && isset($forum_cache[$fid]) ? '
                    <li class="breadcrumb-item">
                        <a href="index.php?act=management" class="text-decoration-none text-primary">
                            <i class="fas fa-home me-1"></i>Forums
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <i class="fas fa-folder me-1"></i>' . htmlspecialchars($forum_cache[$fid]['name']) . '
                    </li>' : '
                    <li class="breadcrumb-item active" aria-current="page">
                        <i class="fas fa-tachometer-alt me-1"></i>Forum Management
                    </li>') . '
                </ol>
            </nav>

            <!-- Основной контейнер -->
            <div class="card border-0 shadow-lg rounded-3 overflow-hidden">
                
                <!-- Заголовок страницы -->
                <div class="card-header bg-primary text-white py-4 px-5">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="header-icon bg-white bg-opacity-20 rounded-circle p-3 me-4">
                                <i class="fas fa-sitemap fa-2x"></i>
                            </div>
                            <div>
                                <h1 class="h3 mb-2 fw-bold">
                                    ' . ($fid && isset($forum_cache[$fid]) ? '
                                    <i class="fas fa-folder-open me-2"></i>
                                    Manage: ' . htmlspecialchars($forum_cache[$fid]['name']) : '
                                    <i class="fas fa-layer-group me-2"></i>
                                    Forum Management') . '
                                </h1>
                                <p class="mb-0 opacity-85">
                                    <i class="fas fa-info-circle me-1"></i>
                                    ' . ($fid ? 'Manage forum hierarchy, permissions and moderators' : 'Organize your forum structure and settings') . '
                                </p>
                            </div>
                        </div>
                        <div class="status-badges">
                            ' . ($fid ? '
                            <span class="badge bg-white bg-opacity-25 px-3 py-2 me-2">
                                <i class="fas fa-hashtag me-1"></i>ID: ' . $fid . '
                            </span>' : '') . '
                            <span class="badge bg-white bg-opacity-25 px-3 py-2">
                                <i class="fas fa-clock me-1"></i>' . date('H:i') . '
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Навигационные табы -->
                <div class="card-body px-5 pt-4 pb-0">
                    <ul class="nav nav-tabs nav-tabs-modern border-0" id="forumTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="subforums-tab" data-bs-toggle="tab" data-bs-target="#subforums" type="button" role="tab">
                                <i class="fas fa-folder-tree me-2"></i>
                                ' . ($fid ? 'Sub Forums' : 'All Forums') . '
                            </button>
                        </li>
                        ' . ($fid ? '
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab">
                                <i class="fas fa-shield-alt me-2"></i>Permissions
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="moderators-tab" data-bs-toggle="tab" data-bs-target="#moderators" type="button" role="tab">
                                <i class="fas fa-user-shield me-2"></i>Moderators
                            </button>
                        </li>' : '') . '
                    </ul>
                </div>

                <!-- Содержимое табов -->
                <div class="card-body px-5 pb-5">
                    <div class="tab-content" id="forumTabsContent">
                        
                        <!-- Таб: Субфорумы -->
                        <div class="tab-pane fade show active" id="subforums" role="tabpanel">
                            <div class="mb-4">
                                <h3 class="h5 mb-3 text-dark fw-bold">
                                    <i class="fas fa-list-ol me-2 text-primary"></i>
                                    Forum Structure
                                </h3>
                                <p class="text-muted mb-4">
                                    Drag forums to reorder or use the input fields. Changes are saved automatically.
                                </p>
                            </div>

                            <form method="post" action="index.php?act=management">
                                ' . $form->generate_hidden_field("fid", $mybb->input['fid']) . '
                                ' . generate_post_check() . '

                            <div class="table-container">
                                <div class="table-responsive rounded-3 border">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4 py-3 fw-semibold text-dark" style="width: 50%;">
                                                    <i class="fas fa-align-left me-2 text-muted"></i>Forum Details
                                                </th>
                                                <th class="text-center py-3 fw-semibold text-dark" style="width: 20%;">
                                                    <i class="fas fa-sort-numeric-up me-2 text-muted"></i>Display Order
                                                </th>
                                                <th class="text-end pe-4 py-3 fw-semibold text-dark" style="width: 30%;">
                                                    <i class="fas fa-sliders-h me-2 text-muted"></i>Quick Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>';

    // Выводим список форумов - передаем созданные объекты
    build_admincp_forums_list($form_container, $form, $fid);

    // Если нет результатов
    if ($form_container->num_rows() == 0) {
        echo '
                                            <tr>
                                                <td colspan="3" class="text-center py-5">
                                                    <div class="empty-state">
                                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                        <h5 class="text-muted mb-2">No forums found</h5>
                                                        <p class="text-muted mb-0">Start by creating your first forum</p>
                                                    </div>
                                                </td>
                                            </tr>';
    }

    echo '
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Кнопки действий -->
                            ' . ($form_container->num_rows() > 0 ? '
                            <div class="mt-4">
                                <div class="card border-0 bg-light-subtle">
                                    <div class="card-body py-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    ' . $form_container->num_rows() . ' forum(s) found
                                                </span>
                                            </div>
                                            <div class="btn-group">
                                                <button type="submit" name="save_forum_orders" class="btn btn-primary px-4 py-2">
                                                    <i class="fas fa-save me-2"></i>Save Changes
                                                </button>
                                                <button type="reset" class="btn btn-outline-secondary px-4 py-2 ms-2">
                                                    <i class="fas fa-undo me-2"></i>Reset
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>' : '') . '
                            </form>
                        </div>';

    // ============================================
    // ТАБ: PERMISSIONS (только если есть fid)
    // ============================================
    if ($fid && isset($forum_cache[$fid])) {
        // Загружаем данные для вкладки разрешений
        $query = $db->simple_select("usergroups", "*", "", array("order" => "name"));
        $usergroups22 = array();
        while ($usergroup = $db->fetch_array($query)) {
            $usergroups22[$usergroup['gid']] = $usergroup;
        }

        // Получаем существующие разрешения
        $query = $db->simple_select("forumpermissions", "*", "fid='{$fid}'");
        $existing_permissions = array();
        while ($existing = $db->fetch_array($query)) {
            $existing_permissions[$existing['gid']] = $existing;
        }

        $cached_forum_perms = $cache->read("forumpermissions");
        $field_list = array(
            'canview' => 'Can view?',
            'canpostthreads' => 'Can post threads?',
            'canpostreplys' => 'Can post replies?',
            'canpostpolls' => 'Can post polls?',
        );

        $field_list2 = array(
            'canview' => '&#149; View',
            'canpostthreads' => '&#149; Post Threads',
            'canpostreplys' => '&#149; Post Replies',
            'canpostpolls' => '&#149; Post Polls',
        );

        $ids = array();
        
        echo '
                        <!-- Таб: Разрешения -->
                        <div class="tab-pane fade" id="permissions" role="tabpanel">
                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h3 class="h5 mb-2 text-dark fw-bold">
                                            <i class="fas fa-key me-2 text-warning"></i>
                                            Forum Permissions
                                        </h3>
                                        <p class="text-muted mb-0">
                                            Configure access rights for: <strong>' . htmlspecialchars($forum_cache[$fid]['name']) . '</strong>
                                        </p>
                                    </div>
                                    <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2">
                                        <i class="fas fa-users me-1"></i>' . count($usergroups22) . ' Groups
                                    </span>
                                </div>
                            </div>

                            <form method="post" action="index.php?act=management" id="permissionsForm">
                                <input type="hidden" name="fid" value="' . $fid . '">
                                <input type="hidden" name="update" value="permissions">
                                ' . generate_post_check() . '

                            <div class="table-responsive rounded-3 border">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4 py-3 fw-semibold text-dark" style="width: 25%;">
                                                User Group
                                            </th>
                                            <th class="py-3 fw-semibold text-dark" style="width: 35%;">
                                                Allowed Permissions
                                            </th>
                                            <th class="py-3 fw-semibold text-dark" style="width: 25%;">
                                                Status
                                            </th>
                                            <th class="text-end pe-4 py-3 fw-semibold text-dark" style="width: 15%;">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>';

        // Выводим разрешения для групп пользователей
        foreach ($usergroups22 as $usergroup) {
            $perms = array();
            $default_checked = false;
            
            // Определяем текущие разрешения
            if (isset($existing_permissions[$usergroup['gid']])) {
                $perms = $existing_permissions[$usergroup['gid']];
                $default_checked = false;
            } elseif (isset($cached_forum_perms[$fid][$usergroup['gid']])) {
                $perms = $cached_forum_perms[$fid][$usergroup['gid']];
                $default_checked = true;
            } else {
                $perms = $usergroup;
                $default_checked = true;
            }

            // Определяем какие разрешения активны
            $perms_checked = array();
            foreach ($field_list as $forum_permission => $forum_perm_title) {
                $perms_checked[$forum_permission] = ($perms[$forum_permission] == 1) ? 1 : 0;
            }

            $inherited_text = $default_checked ? 'Inherited' : 'Custom';
            $status_class = $default_checked ? 'bg-info bg-opacity-10 text-info' : 'bg-warning bg-opacity-10 text-warning';
            $status_icon = $default_checked ? 'fas fa-link' : 'fas fa-pen';
            
            // Генерируем HTML для полей разрешений
            $enabled_permissions = '';
            $disabled_permissions = '';
            
            foreach ($field_list2 as $perm => $label) {
                if ($perms_checked[$perm]) {
                    $enabled_permissions .= '<span class="badge bg-success bg-opacity-10 text-success me-2 mb-1 permission-badge" data-perm="' . $perm . '">' . strip_tags($label) . '</span>';
                } else {
                    $disabled_permissions .= '<span class="badge bg-danger bg-opacity-10 text-danger me-2 mb-1 permission-badge" data-perm="' . $perm . '">' . strip_tags($label) . '</span>';
                }
            }
			
			
			
		
		

			
			
			
			
            
            // Создаем скрытые поля для формы
            $hidden_fields = '
                <input type="hidden" name="fields_' . $usergroup['gid'] . '" id="fields_' . $usergroup['gid'] . '" value="' . implode(",", array_keys(array_filter($perms_checked))) . '">
                <input type="hidden" name="fields_inherit_' . $usergroup['gid'] . '" id="fields_inherit_' . $usergroup['gid'] . '" value="' . (int)$default_checked . '">
                <input type="hidden" name="fields_default_' . $usergroup['gid'] . '" id="fields_default_' . $usergroup['gid'] . '" value="' . implode(",", array_keys(array_filter($perms_checked))) . '">
            ';
			
			
			echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" type="text/css" media="screen" />';
            echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css" type="text/css" media="screen" />';
            
            
			echo '
<tr data-group-id="' . $usergroup['gid'] . '">
    <td class="ps-4">
        <div class="d-flex align-items-center">
            <div class="group-icon me-3">';
            
			
			// Проверяем, есть ли изображение группы
            if(!empty($usergroup['image'])) 
			{
                // Выводим HTML код иконки прямо из базы
                echo $usergroup['image'];
            } 
			else 
			{
                // Если нет иконки, показываем дефолтную
                echo '<div class="icon-compact default-group" data-tooltip="' . htmlspecialchars_uni($usergroup['title']) . '">
                        <i class="bi bi-people-fill" style="color: #6c757d;"></i>
                      </div>';
            }
            
echo '      </div>
            <div>
                <strong class="d-block">' . htmlspecialchars_uni($usergroup['title']) . '</strong>
                <small class="text-muted">ID: ' . $usergroup['gid'] . '</small>
            </div>
        </div>
    </td>
		   
		   
		   
		   
                                            <td>
                                                <div class="permission-fields" id="permission-fields-' . $usergroup['gid'] . '">
                                                    <div class="mb-2">
                                                        <small class="text-muted d-block mb-1">Allowed:</small>
                                                        <div class="enabled-permissions" id="enabled-' . $usergroup['gid'] . '">
                                                            ' . ($enabled_permissions ?: '<span class="text-muted">No permissions</span>') . '
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-1">Denied:</small>
                                                        <div class="disabled-permissions" id="disabled-' . $usergroup['gid'] . '">
                                                            ' . ($disabled_permissions ?: '<span class="text-muted">No restrictions</span>') . '
                                                        </div>
                                                    </div>
                                                    ' . $hidden_fields . '
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge ' . $status_class . ' px-3 py-2">
                                                    <i class="' . $status_icon . ' me-1"></i>
                                                    ' . $inherited_text . '
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="index.php?act=management&action=permissions&gid=' . $usergroup['gid'] . '&fid=' . $fid . '" 
                                                       class="btn btn-outline-secondary btn-sm" 
                                                       data-bs-toggle="tooltip" 
                                                       title="Set Custom Permissions"
                                                       onclick="popupWindow(this.href, null, true); return false;">
                                                        <i class="fas fa-cog"></i>
                                                    </a>';


if (!$default_checked) {
    echo '<a href="javascript:void(0);" 
            class="btn btn-outline-danger btn-sm ms-1 clear-permission-btn"
            data-pid="' . $perms['pid'] . '"
            data-fid="' . $fid . '"
            data-gid="' . $usergroup['gid'] . '"
            data-group-name="' . addslashes(htmlspecialchars($usergroup['title'])) . '"
            data-post-key="' . $mybb->post_code . '"
            data-bs-toggle="tooltip"
            title="Clear Custom Permissions">
            <i class="fas fa-trash"></i>
          </a>';
}



 echo '</div>
      </td>
    </tr>';
	
	$ids[] = $usergroup['gid'];
}
		

		

        echo '
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">
                                <div class="card border-0 bg-light-subtle">
                                    <div class="card-body py-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Drag permissions between lists to enable/disable
                                                </span>
                                            </div>
                                            <div class="btn-group">
                                                <button type="submit" class="btn btn-warning px-4 py-2">
                                                    <i class="fas fa-save me-2"></i>Save Permissions2
                                                </button>
                                                <button type="reset" class="btn btn-outline-secondary px-4 py-2 ms-2" onclick="resetPermissions()">
                                                    <i class="fas fa-undo me-2"></i>Reset
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>
                        </div>';

        // ============================================
        // ТАБ: MODERATORS
        // ============================================
        // Получаем список текущих модераторов
        $query = $db->sql_query("
            SELECT m.mid, m.id, m.isgroup, u.username, g.title
            FROM moderators m
            LEFT JOIN users u ON (m.isgroup='0' AND m.id=u.id)
            LEFT JOIN usergroups g ON (m.isgroup='1' AND m.id=g.gid)
            WHERE m.fid='{$fid}'
            ORDER BY m.isgroup DESC, u.username ASC, g.title ASC
        ");
        
        $current_moderators = array();
        while ($mod = $db->fetch_array($query)) {
            $current_moderators[] = $mod;
        }
        
        // Получаем список групп пользователей для выпадающего списка
        $query = $db->simple_select("usergroups", "*", "", array("order" => "title"));
        $user_groups = array();
        while ($group = $db->fetch_array($query)) {
            $user_groups[$group['gid']] = $group;
        }
        
        echo '
                        <!-- Таб: Модераторы -->
                        <div class="tab-pane fade" id="moderators" role="tabpanel">
                            <div class="row">
                                <!-- Список текущих модераторов -->
                                <div class="col-lg-8">
                                    <div class="card border-0 shadow-sm mb-4">
                                        <div class="card-header bg-white border-bottom py-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0 fw-bold">
                                                    <i class="fas fa-user-shield me-2 text-primary"></i>
                                                    Current Moderators
                                                </h5>
                                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1">
                                                    ' . count($current_moderators) . ' moderator(s)
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="ps-4 py-3">Moderator</th>
                                                            <th class="text-center py-3">Type</th>
                                                            <th class="text-end pe-4 py-3">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>';
        
        if (count($current_moderators) == 0) {
            echo '
                                                        <tr>
                                                            <td colspan="3" class="text-center py-4 text-muted">
                                                                <i class="fas fa-user-slash fa-2x mb-2"></i><br>
                                                                No moderators assigned yet
                                                            </td>
                                                        </tr>';
        } else {
            foreach ($current_moderators as $moderator) {
                $type_badge = $moderator['isgroup'] ? 
                    '<span class="badge bg-info bg-opacity-10 text-info px-2 py-1">Group</span>' : 
                    '<span class="badge bg-success bg-opacity-10 text-success px-2 py-1">User</span>';
                
                $mod_name = $moderator['isgroup'] ? 
                    htmlspecialchars_uni($moderator['title']) : 
                    htmlspecialchars_uni($moderator['username']);
                
                $mod_icon = $moderator['isgroup'] ? 'fas fa-users' : 'fas fa-user';
                
                echo '
                                                        <tr>
                                                            <td class="ps-4">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="moderator-icon bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                                                                        <i class="' . $mod_icon . '"></i>
                                                                    </div>
                                                                    <div>
                                                                        <strong class="d-block">' . $mod_name . '</strong>
                                                                        <small class="text-muted">ID: ' . $moderator['id'] . '</small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                ' . $type_badge . '
                                                            </td>
                                                            <td class="text-end pe-4">
                                                                <div class="btn-group btn-group-sm">
                                                                    <a href="index.php?act=management&action=editmod&mid=' . $moderator['mid'] . '" 
                                                                       class="btn btn-outline-primary btn-sm"
                                                                       data-bs-toggle="tooltip"
                                                                       title="Edit Moderator">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                    
																	<a href="#"
   class="btn btn-outline-danger btn-sm ms-1 delete-moderator-btn"
   data-mid="' . (int)$moderator['id'] . '"
   data-fid="' . (int)$fid . '"
   data-isgroup="' . (int)$moderator['isgroup'] . '"
   data-post-key="' . $mybb->post_code . '"
   data-bs-toggle="tooltip"
   title="Remove Moderator">
    <i class="fas fa-trash"></i>
</a>

																	
																	
																	
																	
																	
																	
                                                                </div>
                                                            </td>
                                                        </tr>';
            }
        }
        
        echo '
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Формы добавления -->
                                <div class="col-lg-4">
                                    <div class="sticky-top" style="top: 20px;">
                                        <!-- Добавить группу -->
                                        <div class="card border-0 shadow-sm mb-4">
                                            <div class="card-header bg-success text-white py-3">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-users me-2"></i>Add User Group
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <form method="post" action="index.php?act=management" id="addGroupForm">
                                                    <input type="hidden" name="fid" value="' . $fid . '">
                                                    <input type="hidden" name="add" value="moderators">
                                                    ' . generate_post_check() . '
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Select User Group</label>
                                                        <select name="usergroup" class="form-select" required>
                                                            <option value="">-- Select Group --</option>';
        
        foreach ($user_groups as $group) {
            echo '
                                                            <option value="' . $group['gid'] . '">' . htmlspecialchars_uni($group['title']) . ' (ID: ' . $group['gid'] . ')</option>';
        }
        
        echo '
                                                        </select>
                                                        <small class="text-muted">All users in this group will become moderators</small>
                                                    </div>
                                                    <button type="submit" class="btn btn-success w-100 py-2">
                                                        <i class="fas fa-plus me-2"></i>Add Group as Moderator
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Добавить пользователя -->
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-info text-white py-3">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-user-plus me-2"></i>Add User
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <form method="post" action="index.php?act=management" id="addUserForm">
                                                    <input type="hidden" name="fid" value="' . $fid . '">
                                                    <input type="hidden" name="add" value="moderators">
                                                    ' . generate_post_check() . '
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Username</label>
                                                        <input type="text" name="username" class="form-control" 
                                                               placeholder="Enter username" 
                                                               required
                                                               data-autocomplete-url="../xmlhttp.php?action=get_users">
                                                        <small class="text-muted">Type username and select from suggestions</small>
                                                    </div>
                                                    <button type="submit" class="btn btn-info w-100 py-2">
                                                        <i class="fas fa-user-plus me-2"></i>Add User as Moderator
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
    }

    echo '
                    </div>
                </div>
            </div>
        </div>
    </div>';
	

	// ============================================
    // CSS стили для современного дизайна
    // ============================================
    echo '
    <style>
    
    
    .bg-gradient-primary {
        background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%) !important;
    }
    
    .header-icon {
        transition: transform 0.3s ease;
    }
    
    .header-icon:hover {
        transform: rotate(15deg);
    }
    
    .nav-tabs-modern {
        border-bottom: 2px solid #dee2e6;
    }
    
    .nav-tabs-modern .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #6c757d;
        padding: 1rem 1.5rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border-radius: 0;
    }
    
    .nav-tabs-modern .nav-link:hover {
        color: #0d6efd;
        border-bottom-color: #dee2e6;
    }
    
    .nav-tabs-modern .nav-link.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
        background-color: transparent;
    }
    
    .table-container {
        position: relative;
    }
    
    .empty-state {
        padding: 3rem 1rem;
    }
    
    .group-icon, .moderator-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .sticky-top {
        position: sticky;
        z-index: 10;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.04);
        transform: translateX(2px);
        transition: all 0.2s ease;
    }
    
    .card {
        border: none;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .breadcrumb {
        background-color: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .btn {
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        border: none;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    }
    
    .btn-outline-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(108, 117, 125, 0.2);
    }
    
    /* Стили для редактора разрешений */
    .permission-fields {
        min-height: 80px;
    }
    
    .enabled-permissions, .disabled-permissions {
        min-height: 36px;
        padding: 0.25rem;
        border-radius: 4px;
        transition: background-color 0.2s ease;
    }
    
    .enabled-permissions {
        background-color: rgba(25, 135, 84, 0.05);
        border: 1px dashed rgba(25, 135, 84, 0.3);
    }
    
    .disabled-permissions {
        background-color: rgba(220, 53, 69, 0.05);
        border: 1px dashed rgba(220, 53, 69, 0.3);
    }
    
    .permission-badge {
        cursor: move;
        user-select: none;
        transition: all 0.2s ease;
    }
    
    .permission-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .permission-badge.dragging {
        opacity: 0.5;
        transform: scale(1.1);
    }
    
    /* Анимации */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .fade-in {
        animation: fadeIn 0.5s ease forwards;
        opacity: 0;
    }
    
    .order-input.changed {
        border-color: #0d6efd;
        background-color: rgba(13, 110, 253, 0.05);
    }
    
    .table th {
        position: sticky;
        top: 0;
        background-color: #f8f9fa;
        z-index: 10;
    }
    
    /* Автодополнение */
    .autocomplete-suggestions {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        background: white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
    }
    
    .autocomplete-suggestion {
        padding: 8px 12px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .autocomplete-suggestion:hover {
        background-color: #f8f9fa;
    }
    
    .autocomplete-suggestion.selected {
        background-color: #e7f1ff;
    }
    </style>';
	
	

    // ============================================
    // JavaScript для разрешений
    // ============================================
    if ($fid && isset($ids)) {
        echo '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Инициализация редактора разрешений для каждой группы
        var groupIds = ' . json_encode($ids) . ';
        groupIds.forEach(function(gid) {
            if (typeof QuickPermEditor !== "undefined") {
                QuickPermEditor.init(gid);
            }
        });
        
        // Функция сброса разрешений
        window.resetPermissions = function() {
            if (confirm("Are you sure you want to reset all permission changes?")) {
                document.querySelectorAll(".permission-fields").forEach(function(container) {
                    var gid = container.id.replace("permission-fields-", "");
                    var defaultPerms = document.getElementById("fields_default_" + gid).value.split(",");
                    
                    // Сброс отображения
                    var enabledDiv = container.querySelector(".enabled-permissions");
                    var disabledDiv = container.querySelector(".disabled-permissions");
                    
                    // Здесь нужно добавить логику сброса к исходным значениям
                    // Это будет зависеть от реализации QuickPermEditor
                });
            }
        };
        
        // Инициализация автодополнения для имен пользователей
        var usernameInputs = document.querySelectorAll(\'input[data-autocomplete-url]\');
        usernameInputs.forEach(function(input) {
            input.addEventListener("input", function() {
                var query = this.value;
                if (query.length < 2) return;
                
                // Реализация автодополнения
                // Можно использовать существующий JavaScript код
            });
        });
    });
    
    // Функция для обновления статуса разрешений
    function updatePermissionStatus(gid, isInherited) {
        var statusBadge = document.querySelector(\'tr[data-group-id="\' + gid + \'"] .badge\');
        if (statusBadge) {
            if (isInherited) {
                statusBadge.className = "badge bg-info bg-opacity-10 text-info px-3 py-2";
                statusBadge.innerHTML = \'<i class="fas fa-link me-1"></i>Inherited\';
            } else {
                statusBadge.className = "badge bg-warning bg-opacity-10 text-warning px-3 py-2";
                statusBadge.innerHTML = \'<i class="fas fa-pen me-1"></i>Custom\';
            }
        }
    }
    </script>';
    }

    echo '</div>'; // Закрываем admin-container

    $plugins->run_hooks("admin_forum_management_start_graph");
    
    // Закрываем страницу
    stdfoot();
}














/**
 * @param DefaultFormContainer $form_container
 * @param DefaultForm $form
 * @param int $pid
 * @param int $depth
 */
 
 



//echo "	<script type=\"text/javascript\" src=\"scripts/bootbox.min.js\"></script>\n";
echo "	<script type=\"text/javascript\" src=\"scripts/deleteForum.js\"></script>\n";

echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/popover.js"></script>';

function build_admincp_forums_list(&$form_container, &$form, $pid=0, $depth=1)
{
    global $mybb, $lang, $db, $sub_forums;
    static $forums_by_parent;

    if(!is_array($forums_by_parent))
    {
        $forum_cache = cache_forums();
        foreach($forum_cache as $forum)
        {
            $forums_by_parent[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
        }
    }

    if(!isset($forums_by_parent[$pid]) || !is_array($forums_by_parent[$pid]))
    {
        return;
    }
    
    $subforumsindex = "2";
    $donecount = 0;
    $comma = '';
    
    foreach($forums_by_parent[$pid] as $children)
    {
        foreach($children as $forum)
        {
            $forum['name'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['name']);
            
            // Определяем иконку и цвет в зависимости от типа форума
            if($forum['type'] == "c")
            {
                $icon = '<i class="fas fa-folder me-2 text-warning"></i>';
                $badge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Category</span>';
                $bg_class = $forum['active'] == 0 ? 'bg-light text-muted' : 'bg-white';
            }
            else
            {
                $icon = '<i class="fas fa-comments me-2 text-primary"></i>';
                $badge = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">Forum</span>';
                $bg_class = $forum['active'] == 0 ? 'bg-light text-muted opacity-75' : 'bg-white';
            }
            
            // Форматируем имя форума
            $forum_name = $forum['active'] == 0 ? "<em>{$forum['name']}</em>" : $forum['name'];
			
			$form_container->construct_row();
			
            
            if($forum['type'] == "c" && ($depth == 1 || $depth == 2))
            {
                $sub_forums = '';
                if(isset($forums_by_parent[$forum['fid']]) && $depth == 2)
                {
                    build_admincp_forums_list($form_container, $form, $forum['fid'], $depth+1);
                }
                if($sub_forums)
                {
                    $sub_forums = "<div class=\"text-muted small mt-1\"><i class=\"fas fa-sitemap me-1\"></i>Subforums: {$sub_forums}</div>";
                }
                
                // Выводим категорию
                echo '
                <tr class="forum-row category-row">
                    <td class="forum-name-cell">
                        <div class="d-flex align-items-center ps-' . ($depth * 2) . '">
                            
							
							
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1">
                                    ' . $icon . '
                                    <a href="index.php?act=management&fid=' . $forum['fid'] . '" class="forum-name-link fw-bold text-dark text-decoration-none">
                                        ' . $forum_name . '
                                    </a>
                                    ' . $badge . '
                                </div>
                                ' . $sub_forums . '
                            </div>
                        </div>
                    </td>
                    
                    <td class="align-middle">
                        <div class="order-input-wrapper">
                            <input type="number" 
                                   name="disporder[' . $forum['fid'] . ']" 
                                   value="' . $forum['disporder'] . '" 
                                   min="0" 
                                   class="form-control form-control-sm order-input" 
                                   style="width: 80px;" />
                        </div>
                    </td>
                    
                    <td class="align-middle text-end">
                        ' . generate_forum_actions($forum) . '
                    </td>
                </tr>';
				
				
				$form_container->construct_row();
                
                // Рекурсивно обрабатываем подфорумы
                if(!empty($forums_by_parent[$forum['fid']]))
                {
                    build_admincp_forums_list($form_container, $form, $forum['fid'], $depth+1);
                }
            }
            elseif($forum['type'] == "f" && ($depth == 1 || $depth == 2))
            {
                if($forum['description'])
                {
                    $forum['description'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['description']);
                    $description = '<div class="text-muted small mt-1"><i class="fas fa-align-left me-1"></i>' . $forum['description'] . '</div>';
                }
                else
                {
                    $description = '';
                }
                
                $sub_forums = '';
                if(isset($forums_by_parent[$forum['fid']]) && $depth == 2)
                {
                    build_admincp_forums_list($form_container, $form, $forum['fid'], $depth+1);
                }
                if($sub_forums)
                {
                    $sub_forums = "<div class=\"text-muted small mt-1\"><i class=\"fas fa-sitemap me-1\"></i>Subforums: {$sub_forums}</div>";
                }
                
                // Выводим форум
                echo '
                <tr class="forum-row ' . $bg_class . '">
                    <td class="forum-name-cell">
                        <div class="d-flex align-items-center ps-' . ($depth * 2) . '">
                           
						   
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1">
                                    ' . $icon . '
                                    <a href="index.php?act=management&fid=' . $forum['fid'] . '" class="forum-name-link text-dark text-decoration-none">
                                        ' . $forum_name . '
                                    </a>
                                    ' . $badge . '
                                </div>
                                ' . $description . $sub_forums . '
                            </div>
                        </div>
                    </td>
                    
                    <td class="align-middle">
                        <div class="order-input-wrapper">
                            <input type="number" 
                                   name="disporder[' . $forum['fid'] . ']" 
                                   value="' . $forum['disporder'] . '" 
                                   min="0" 
                                   class="form-control form-control-sm order-input" 
                                   style="width: 80px;" />
                        </div>
                    </td>
                    
                    <td class="align-middle text-end">
                        ' . generate_forum_actions($forum) . '
                    </td>
                </tr>';
                
                if(isset($forums_by_parent[$forum['fid']]) && $depth == 1)
                {
                    build_admincp_forums_list($form_container, $form, $forum['fid'], $depth+1);
                }
            }
            elseif($depth == 3)
            {
                if($donecount < $subforumsindex)
                {
                    $sub_forums .= "{$comma} <a href=\"index.php?act=management&fid={$forum['fid']}\" class=\"small\">{$forum['name']}</a>";
                    $comma = ', ';
                }
                
                ++$donecount;
                if($donecount == $subforumsindex)
                {
                    if(subforums_count2($forums_by_parent[$pid]) > $donecount)
                    {
                        $sub_forums .= $comma . '...';
                        return;
                    }
                }
            }
        }
    }
}

/**
 * Генерирует кнопки действий для форума
 */
function generate_forum_actions($forum)
{
    global $mybb; // Добавьте это для my_post_key
    
    $actions = '
    <div class="btn-group btn-group-sm forum-actions-dropdown" role="group">
        
        <a href="index.php?act=management&action=edit&fid=' . $forum['fid'] . '" 
   class="btn btn-outline-primary" 
   data-bs-toggle="popover" 
   data-bs-trigger="hover focus"
   data-bs-content="Edit Forum">
    <i class="fas fa-edit"></i>
</a>

		
		<a href="index.php?act=management&fid=' . $forum['fid'] . '" 
   class="btn btn-outline-info" 
   data-bs-toggle="popover" 
   data-bs-trigger="hover focus"
   data-bs-content="View Subforums">
    <i class="fas fa-sitemap"></i>
</a>


        
        
        <div class="dropdown" style="position: static;">
            <button class="btn btn-outline-secondary dropdown-toggle" 
                    type="button" 
                    id="dropdownMenuButton_' . $forum['fid'] . '"
                    data-bs-toggle="dropdown" 
                    aria-expanded="false"
                    data-bs-boundary="viewport">
                <i class="fas fa-cog"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" 
                aria-labelledby="dropdownMenuButton_' . $forum['fid'] . '"
                data-bs-popper="static">
                <li>
                    <a class="dropdown-item" href="index.php?act=management&fid=' . $forum['fid'] . '#tab_moderators">
                        <i class="fas fa-user-shield me-2"></i>Moderators
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?act=management&fid=' . $forum['fid'] . '#tab_permissions">
                        <i class="fas fa-shield-alt me-2"></i>Permissions
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="management.php?module=config-thread_prefixes&fid=' . $forum['fid'] . '">
                        <i class="fas fa-tags me-2"></i>Thread Prefixes
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?act=management&action=add&pid=' . $forum['fid'] . '">
                        <i class="fas fa-plus-circle me-2"></i>Add Child Forum
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?act=management&action=copy&fid=' . $forum['fid'] . '">
                        <i class="fas fa-copy me-2"></i>Copy Forum
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger delete_employee" 
                       href="javascript:void(0)" 
                       data-emp-id="' . $forum['fid'] . '"
                       data-forum-name="' . htmlspecialchars($forum['name']) . '">
                        <i class="fas fa-trash me-2"></i>Delete Forum
                    </a>
                </li>
            </ul>
        </div>
    </div>';
    
    return $actions;
}

/**
 * Подсчитывает количество подфорумов
 */
function subforums_count2($forums)
{
    $count = 0;
    foreach($forums as $children)
    {
        $count += count($children);
    }
    return $count;
}

echo <<<HTML
<style>
/* Добавьте в ваш CSS файл */

/* Анимация для пульсирующего кольца */
@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.5;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.pulse-ring {
    position: absolute;
    top: -10px;
    left: -10px;
    right: -10px;
    bottom: -10px;
    border: 2px solid rgba(220, 53, 69, 0.3);
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.delete-icon-wrapper {
    position: relative;
    display: inline-block;
    width: 80px;
    height: 80px;
}

/* Стили для модального окна */
.modal-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bg-gradient {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

/* Анимация появления модального окна */
.modal.fade .modal-dialog {
    transform: scale(0.8);
    transition: transform 0.3s ease-out;
}

.modal.show .modal-dialog {
    transform: scale(1);
}

/* Эффект при наведении на кнопку */
.btn-danger:not(:disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    transition: all 0.2s ease;
}

/* Анимация для удаляемой строки */
.forum-row.deleting {
    animation: slideOutLeft 0.5s ease forwards;
}

@keyframes slideOutLeft {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(-100%);
    }
}
</style>

<script>
// Инициализация Bootstrap popover и установка индексов строк
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация popover для всех элементов с data-bs-toggle="popover"
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(el) {
        return new bootstrap.Popover(el, {
            container: 'body',
            trigger: 'hover focus',
            html: true
        });
    });

    // Установка индексов строк для анимации
    var rows = document.querySelectorAll('.forum-row');
    rows.forEach(function(row, index) {
        row.style.setProperty('--row-index', index);
    });
});
</script>
HTML;







/**
 * @param int $gid
 * @param int $fid
 *
 * @return string
 */
function retrieve_single_permissions_row($gid, $fid)
{
	global $mybb, $lang, $cache, $db;

	$query = $db->simple_select("usergroups", "*", "gid='{$gid}'");
	$usergroup = $db->fetch_array($query);

	$query = $db->simple_select("tsf_forums", "*", "fid='{$fid}'");
	$forum_data = $db->fetch_array($query);

	$query = $db->simple_select("forumpermissions", "*", "fid='{$fid}'");
	while($existing = $db->fetch_array($query))
	{
		$existing_permissions[$existing['gid']] = $existing;
	}

	$cached_forum_perms = $cache->read("forumpermissions");
	$field_list = array(
		'canview' => 'Can view?',
		'canpostthreads' => 'Can post threads?',
		'canpostreplys' => 'Can post replies?',
		'canpostpolls' => 'Can post polls?',
	);

	$field_list2 = array(
		'canview' => '&#149; View',
		'canpostthreads' => '&#149; Post Threads',
		'canpostreplys' => '&#149; Post Replies',
		'canpostpolls' => '&#149; Post Polls',
	);

	$form = new Form('', '', "", 0, "", true);
	$form_container = new FormContainer();

	$perms = array();

	if(is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
	{
		$perms = $existing_permissions[$usergroup['gid']];
		$default_checked = false;
	}
	elseif(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['fid']][$usergroup['gid']])
	{
		$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
		$default_checked = true;
	}
	else if(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['pid']][$usergroup['gid']])
	{
		$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
		$default_checked = true;
	}

	if(!$perms)
	{
		$perms = $usergroup;
		$default_checked = true;
	}

	foreach($field_list as $forum_permission => $forum_perm_title)
	{
		if($perms[$forum_permission] == 1)
		{
			$perms_checked[$forum_permission] = 1;
		}
		else
		{
			$perms_checked[$forum_permission] = 0;
		}
	}

	$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);

	if($default_checked == 1)
	{
		$inherited_text = 'inherited';
	}
	else
	{
		$inherited_text = 'custom_permission';
	}

	$form_container->output_cell("<strong>{$usergroup['title']}</strong> <small style=\"vertical-align: middle;\">({$inherited_text})</small>");

	$field_select = "<div class=\"quick_perm_fields\">\n";
	$field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_{$usergroup['gid']}\">\n";
	foreach($perms_checked as $perm => $value)
	{
		if($value == 1)
		{
			$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
		}
	}
	$field_select .= "</ul></div>\n";
	$field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_{$usergroup['gid']}\">\n";
	foreach($perms_checked as $perm => $value)
	{
		if($value == 0)
		{
			$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
		}
	}
	$field_select .= "</ul></div></div>\n";
	$field_select .= $form->generate_hidden_field("fields_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, 1)), array('id' => 'fields_'.$usergroup['gid']));
	$field_select = str_replace("\n", "", $field_select);

	foreach($field_list as $forum_permission => $permission_title)
	{
		$field_options[$forum_permission] = $permission_title;
	}
	$form_container->output_cell($field_select, array('colspan' => 2));

	if(!$default_checked)
	{
		$form_container->output_cell("<a href=\"index.php?act=management&action=permissions&amp;pid={$perms['pid']}\" onclick=\"popupWindow('index.php?act=management&action=permissions&pid={$perms['pid']}&ajax=1', null, true); return false;\">edit_permissions</a>", array("class" => "align_center"));
		$form_container->output_cell("<a href=\"index.php?act=management&action=clear_permission&amp;pid={$perms['pid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, 'confirm_clear_custom_permission')\">clear_custom_perms</a>", array("class" => "align_center"));
	}
	else
	{
		$form_container->output_cell("<a href=\"index.php?act=management&action=permissions&amp;gid={$usergroup['gid']}&amp;fid={$fid}\"  onclick=\"popupWindow('index.php?act=management&action=permissions&gid={$usergroup['gid']}&fid={$fid}&ajax=1', null, true); return false;\">Set Custom Permissions</a>", array("class" => "align_center", "colspan" => 2));
	}
	$form_container->construct_row();
	return $form_container->output_row_cells(0, true);
}




// Модалка и JS после цикла
echo '
<!-- Модалка -->
<div class="modal fade" id="clearPermissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-body p-4 text-center">
                <div class="mb-4">
                    <div class="icon-wrapper mx-auto">
                        <div class="icon-circle bg-danger bg-opacity-10 rounded-circle p-3 d-inline-block">
                            <i class="fas fa-trash-can fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-2">Clear Custom Permissions</h5>
                <p class="text-muted mb-4">
                    Clear custom permissions for 
                    <span class="fw-semibold text-primary" id="modalGroupName"></span>?
                    <br><small class="text-muted mt-2 d-block">This action cannot be undone.</small>
                </p>
                <div class="d-flex gap-3 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-4" id="confirmClearBtn">Clear Permissions</button>
                </div>
            </div>
        </div>
    </div>
</div>
';
?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    let currentData = {};

    // Кнопки очистки
    document.querySelectorAll('.clear-permission-btn').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();
            currentData = {
                pid: this.dataset.pid,
                fid: this.dataset.fid,
                gid: this.dataset.gid,
                groupName: this.dataset.groupName,
                postKey: this.dataset.postKey
            };
            document.getElementById('modalGroupName').textContent = currentData.groupName;
            new bootstrap.Modal(document.getElementById('clearPermissionModal')).show();
        });
    });

    // Подтверждение очистки
    document.getElementById('confirmClearBtn').addEventListener('click', function(e){
        e.preventDefault();

        // Создаём форму POST
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'index.php?act=management&action=clear_permission';
        form.style.display = 'none';

        // Добавляем скрытые поля
        const fields = {
            pid: currentData.pid,
            fid: currentData.fid,
            gid: currentData.gid,
            my_post_key: currentData.postKey  // обратите внимание на имя поля!
        };

        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit(); // POST-запрос
    });

    // Тултипы Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle='tooltip']"));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>	
<?php


echo '
<div class="modal fade" id="deleteModeratorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">

                <i class="fas fa-user-slash fa-2x text-danger mb-3"></i>

                <h5 class="mb-3">
                    Are you sure you want to remove this moderator?
                </h5>

                <div class="d-flex justify-content-center gap-2">
                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="button"
                            class="btn btn-danger"
                            id="confirmDeleteModeratorBtn">
                        Remove
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>';

?>
<script>
document.addEventListener("DOMContentLoaded", function () {

    let deleteData = {};

    // Открываем модалку
    document.querySelectorAll('.delete-moderator-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();

            deleteData = {
                mid: this.dataset.mid,
                fid: this.dataset.fid,
                isgroup: this.dataset.isgroup,
                postKey: this.dataset.postKey
            };

            new bootstrap.Modal(
                document.getElementById('deleteModeratorModal')
            ).show();
        });
    });

    // Подтверждение удаления
    document.getElementById('confirmDeleteModeratorBtn')
        .addEventListener('click', function () {

            const form = document.createElement('form');
            form.method = 'post';
            form.action = 'index.php?act=management&action=deletemod';

            const fields = {
                id: deleteData.mid,      // ← ВОТ ЭТО РЕШАЕТ ВСЁ
                fid: deleteData.fid,
                isgroup: deleteData.isgroup,
                my_post_key: deleteData.postKey
            };

            for (const [name, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
        });

});
</script>

<?php




