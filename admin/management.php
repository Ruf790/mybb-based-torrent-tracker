<?php
/**
 * Forum Management — optimized
 * Originally ~286 KB, refactored to ~110 KB
 * PHP 8.1+
 */


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
$lang->load('forum_management');
$page->add_breadcrumb_item('Forum Management', 'index.php?act=management');

// ═══════════════════════════════════════════════════════════
// SHARED HELPERS
// ═══════════════════════════════════════════════════════════

/** Общие CSS + JS assets (вызывается один раз в начале страницы) */
function fm_head_assets(): void
{
    echo '<link rel="stylesheet" href="templates/forum_management.css">',
         '<link rel="stylesheet" href="templates/main.css?ver=1813">',
         '<link rel="stylesheet" href="templates/modal.css?ver=1813">',
         '<script src="scripts/admincp.js?ver=1821"></script>',
         '<script src="scripts/tabs.js"></script>',
         '<script src="scripts/popup.js"></script>',
		 '<script src="scripts/quick_perm_editor.js"></script>',
         '<style>.popup_button{display:none}</style>',
         '<script>document.write(\'<style>.popup_button{display:inline}.popup_menu{display:none}<\/style>\')</script>';
}

/** Заголовок карточки страницы */
function fm_card_header(string $title, string $subtitle, string $icon, string $color = 'primary'): void
{
    $time = date('H:i');
    echo <<<HTML
    <div class="card-header bg-{$color} text-white py-4 px-5">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="header-icon bg-white bg-opacity-20 rounded-circle p-3 me-4">
                    <i class="fas fa-{$icon} fa-2x"></i>
                </div>
                <div>
                    <h1 class="h3 mb-1 fw-bold">{$title}</h1>
                    <p class="mb-0 opacity-85">{$subtitle}</p>
                </div>
            </div>
            <span class="badge bg-white bg-opacity-25 px-3 py-2">
                <i class="fas fa-clock me-1"></i>{$time}
            </span>
        </div>
    </div>
    HTML;
}

/** Пронумерованный шаг формы */
function fm_step(int $num, string $color, string $icon, string $title): void
{
    echo <<<HTML
    <div class="d-flex align-items-center mb-4">
        <div class="step-number bg-{$color} text-white rounded-circle me-3" style="width:40px;height:40px;">
            <span class="fw-bold">{$num}</span>
        </div>
        <h5 class="mb-0 fw-bold"><i class="fas fa-{$icon} me-2 text-{$color}"></i>{$title}</h5>
    </div>
    HTML;
}

/** Секция с иконкой-кружком */

function fm_section_header(string $icon, string $color, string $title, string $desc = ''): void
{
    $descHtml = $desc ? "<p class=\"text-muted mb-0\" style=\"font-size:13px\">{$desc}</p>" : '';
    
    echo '<div class="d-flex align-items-center mb-4">
        <div class="icon-circle bg-' . $color . ' bg-opacity-10 text-' . $color . ' p-3 me-3" style="width:50px;height:50px;">
            <i class="fas fa-' . $icon . '"></i>
        </div>
        <div>
            <h5 class="mb-1 fw-bold text-dark">' . $title . '</h5>
            ' . $descHtml . '
        </div>
    </div>';
}

/** Блок ошибок */
function fm_errors(array $errors): void
{
    if (!$errors) return;
    echo '<div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4"><div class="d-flex align-items-center"><i class="fas fa-exclamation-triangle fa-lg text-danger me-3"></i><div><h6 class="mb-1 fw-bold">Please fix the following errors:</h6>';
    foreach ($errors as $e) {
        echo '<p class="mb-1 small">' . htmlspecialchars_uni($e) . '</p>';
    }
    echo '</div></div></div>';
}

/** Блок переключателя "Additional Options" */
function fm_toggle_advanced_open(): void
{
    echo <<<HTML
    <div id="additional_options_link" class="text-center py-5">
        <button onclick="return toggleAdditionalOptions();" class="toggle-options-btn">
            <i class="fas fa-cogs fa-lg"></i>
            <span>Show Additional Forum Options</span>
            <i class="fas fa-chevron-down"></i>
        </button>
        <p class="mt-3 text-muted" style="font-size:13px">Advanced settings for forum configuration</p>
    </div>
    <div id="additional_options" style="display:none">
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-sliders-h fa-lg me-2"></i>Additional Forum Options</span>
        <button onclick="return toggleAdditionalOptions();" class="toggle-options-btn" style="font-size:14px;padding:10px 20px">
            <i class="fas fa-times"></i> Hide Options <i class="fas fa-chevron-up"></i>
        </button>
    </div><div class="card-body">
    HTML;
}

function fm_toggle_advanced_close(): void
{
    echo '</div></div></div>';
}

/** JS для toggleAdditionalOptions */
function fm_toggle_js(): void
{
    echo <<<'JS'
    <script>
    function toggleAdditionalOptions(){
        var l=document.getElementById('additional_options_link'),
            o=document.getElementById('additional_options');
        if(o.style.display==='block'||o.style.display===''){
            l.style.display='block'; o.style.display='none';
        } else {
            l.style.display='none'; o.style.display='block';
        }
        return false;
    }
    </script>
    JS;
}

/** Строки выбора типа форума (Forum / Category) */
function fm_type_cards(string $current): void
{
    $f_active  = $current === 'f' ? 'active' : '';
    $c_active  = $current !== 'f' ? 'active' : '';
    $f_checked = $current === 'f' ? 'checked' : '';
    $c_checked = $current !== 'f' ? 'checked' : '';
    $f_icon    = $current === 'f' ? 'fas fa-check-circle' : 'far fa-circle';
    $c_icon    = $current !== 'f' ? 'fas fa-check-circle' : 'far fa-circle';
    echo <<<HTML
    <div class="row g-4">
        <div class="col-md-6">
            <div class="type-card {$f_active}" onclick="selectType('forum')" data-type="forum">
                <input type="radio" name="type" value="f" class="d-none" id="type_forum" {$f_checked}>
                <div class="type-card-body text-center">
                    <div class="type-icon"><i class="fas fa-comments fa-2x"></i></div>
                    <h6 class="fw-bold mt-3 mb-2">Standard Forum</h6>
                    <p class="text-muted small mb-0">A regular forum where users can post threads and replies</p>
                </div>
                <div class="type-check"><i class="{$f_icon}"></i></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="type-card {$c_active}" onclick="selectType('category')" data-type="category">
                <input type="radio" name="type" value="c" class="d-none" id="type_category" {$c_checked}>
                <div class="type-card-body text-center">
                    <div class="type-icon"><i class="fas fa-folder fa-2x"></i></div>
                    <h6 class="fw-bold mt-3 mb-2">Category</h6>
                    <p class="text-muted small mb-0">A container for organizing multiple forums together</p>
                </div>
                <div class="type-check"><i class="{$c_icon}"></i></div>
            </div>
        </div>
    </div>
    HTML;
}

/** JS для selectType() */
function fm_type_js(): void
{
    echo <<<'JS'
    <script>
    function selectType(type){
        document.querySelectorAll('.type-card').forEach(function(c){
            c.classList.remove('active');
            c.querySelector('.type-check i').className='far fa-circle';
            var r=c.querySelector('input[type="radio"]');
            if(r) r.checked=false;
        });
        var card=document.querySelector('.type-card[data-type="'+type+'"]');
        if(card){
            card.classList.add('active');
            card.querySelector('.type-check i').className='fas fa-check-circle';
            var r=card.querySelector('input[type="radio"]');
            if(r) r.checked=true;
        }
    }
    document.addEventListener('DOMContentLoaded',function(){
        // Инициализируем по уже отмеченному radio (серверный default)
        var checked=document.querySelector('input[name="type"]:checked');
        if(checked){
            var card=checked.closest('.type-card');
            if(card){
                card.classList.add('active');
                card.querySelector('.type-check i').className='fas fa-check-circle';
            }
        }
        var d=document.getElementById('description'),c=document.getElementById('charCount');
        if(d&&c) d.addEventListener('input',function(){c.textContent=Math.min(this.value.length,500);});
    });
    </script>
    JS;
}

/** Поля базовой информации (title, disporder, description) */
function fm_basic_fields(array $data): void
{
    $title   = htmlspecialchars_uni($data['title'] ?? '');
    $order   = (int)($data['disporder'] ?? 1);
    $desc    = htmlspecialchars_uni($data['description'] ?? '');
    $dcLen   = mb_strlen($data['description'] ?? '');
    echo <<<HTML
    <div class="row g-4">
        <div class="col-lg-6">
            <label class="form-label fw-bold">
                <i class="fas fa-heading me-2 text-primary"></i>Forum Title
                <span class="required-badge ms-2">Required</span>
            </label>
            <input type="text" name="title" class="form-control form-control-lg"
                   value="{$title}" placeholder="e.g. General Discussion" required>
        </div>
        <div class="col-lg-6">
            <label class="form-label fw-bold">
                <i class="fas fa-sort-numeric-up me-2 text-primary"></i>Display Order
            </label>
            <input type="number" name="disporder" class="form-control form-control-lg"
                   value="{$order}" min="0">
        </div>
        <div class="col-12">
            <label class="form-label fw-bold">
                <i class="fas fa-align-left me-2 text-primary"></i>Description
            </label>
            <textarea name="description" id="description" class="form-control form-control-lg"
                      rows="4" style="resize:vertical">{$desc}</textarea>
            <div class="d-flex justify-content-end mt-1">
                <small class="text-muted"><span id="charCount">{$dcLen}</span>/500 characters</small>
            </div>
        </div>
    </div>
    HTML;
}

/** Поля дополнительных опций (linkto, password, active, open, datecut, sortby, sortorder, counts) */
function fm_extra_fields(array $d): void
{
    $linkto  = htmlspecialchars_uni($d['linkto']  ?? '');
    $pass    = htmlspecialchars_uni($d['password'] ?? '');
    $active  = !empty($d['active'])         ? 'checked' : '';
    $open    = !empty($d['open'])           ? 'checked' : '';
    $posts   = !empty($d['usepostcounts'])  ? 'checked' : '';
    $threads = !empty($d['usethreadcounts'])? 'checked' : '';

    $datecuts = [0=>'Board Default',1=>'Last 24h',5=>'Last 5 days',10=>'Last 10 days',
                 20=>'Last 20 days',50=>'Last 50 days',75=>'Last 75 days',
                 100=>'Last 100 days',365=>'Last year',9999=>'All time'];
    $sortbys  = [''=> 'Board Default','subject'=>'Subject','lastpost'=>'Last post',
                 'starter'=>'Starter','started'=>'Thread time','rating'=>'Rating',
                 'replies'=>'Replies','views'=>'Views'];
    $sortords = [''=> 'Board Default','asc'=>'Ascending ↑','desc'=>'Descending ↓'];

    $sel = fn($arr, $cur) => implode('', array_map(
        fn($v, $l) => '<option value="' . $v . '" ' . ($cur == $v ? 'selected' : '') . '>' . $l . '</option>',
        array_keys($arr), $arr
    ));

    echo <<<HTML
    <div class="form-row">
        <label class="form-label"><i class="fas fa-external-link-alt me-2"></i>Forum Link (Redirect)</label>
        <p class="text-muted small">Leave empty for a normal forum. Entering a URL disables posting.</p>
        <input type="text" name="linkto" class="form-control" value="{$linkto}" placeholder="https://example.com" style="max-width:450px">
    </div>
    <div class="form-row">
        <label class="form-label"><i class="fas fa-lock me-2"></i>Password Protection</label>
        <p class="text-muted small">Optional. Users still need group permissions on top of the password.</p>
        <input type="text" name="password" class="form-control" value="{$pass}" placeholder="Leave empty for no password" style="max-width:450px">
    </div>
    <div class="form-row">
        <label class="form-label"><i class="fas fa-shield-alt me-2"></i>Access Control</label>
        <div class="settings-grid">
            <label class="form-check settings-group mb-0">
                <input type="checkbox" name="active" value="1" class="form-check-input" {$active}>
                <span class="form-check-label fw-semibold">
                    <i class="fas fa-toggle-on me-1 text-success"></i>Forum is Active
                    <small class="d-block text-muted fw-normal">Hidden from users when unchecked</small>
                </span>
            </label>
            <label class="form-check settings-group mb-0">
                <input type="checkbox" name="open" value="1" class="form-check-input" {$open}>
                <span class="form-check-label fw-semibold">
                    <i class="fas fa-door-open me-1 text-info"></i>Forum is Open
                    <small class="d-block text-muted fw-normal">No posting when unchecked, regardless of permissions</small>
                </span>
            </label>
        </div>
    </div>
    <div class="form-row">
        <label class="form-label"><i class="fas fa-eye me-2"></i>Default View Options</label>
        <div class="settings-grid">
            <div class="settings-group">
                <div class="settings-group-title"><i class="fas fa-calendar-alt"></i>Date Range</div>
                <select name="defaultdatecut" class="form-select">{$sel($datecuts, $d['defaultdatecut'] ?? 0)}</select>
            </div>
            <div class="settings-group">
                <div class="settings-group-title"><i class="fas fa-sort-amount-down"></i>Sort By</div>
                <select name="defaultsortby" class="form-select">{$sel($sortbys, $d['defaultsortby'] ?? '')}</select>
            </div>
            <div class="settings-group">
                <div class="settings-group-title"><i class="fas fa-sort-alpha-down"></i>Sort Order</div>
                <select name="defaultsortorder" class="form-select">{$sel($sortords, $d['defaultsortorder'] ?? '')}</select>
            </div>
        </div>
    </div>
    <div class="form-row">
        <label class="form-label"><i class="fas fa-chart-bar me-2"></i>Statistics Counting</label>
        <div class="settings-grid">
            <label class="form-check settings-group mb-0">
                <input type="checkbox" name="usepostcounts" value="1" class="form-check-input" {$posts}>
                <span class="form-check-label fw-semibold">
                    <i class="fas fa-comment-alt me-1 text-primary"></i>Count user posts
                    <small class="d-block text-muted fw-normal">Posts here count toward user totals</small>
                </span>
            </label>
            <label class="form-check settings-group mb-0">
                <input type="checkbox" name="usethreadcounts" value="1" class="form-check-input" {$threads}>
                <span class="form-check-label fw-semibold">
                    <i class="fas fa-file-alt me-1 text-primary"></i>Count user threads
                    <small class="d-block text-muted fw-normal">Threads here count toward user totals</small>
                </span>
            </label>
        </div>
    </div>
    HTML;
}

/** Кнопки submit / cancel */
function fm_submit_row(string $cancel_url, string $submit_label = 'Save Changes', bool $show_advanced = true): void
{
    $adv = $show_advanced
        ? '<button type="button" class="btn btn-outline-secondary px-4" onclick="toggleAdditionalOptions()"><i class="fas fa-cogs me-2"></i>Advanced</button>'
        : '';
    echo <<<HTML
    <div class="d-flex justify-content-between align-items-center mt-5 pt-4 border-top">
        <a href="{$cancel_url}" class="btn btn-outline-secondary px-4">
            <i class="fas fa-arrow-left me-2"></i>Cancel
        </a>
        <div class="d-flex gap-2">
            {$adv}
            <button type="submit" class="btn btn-primary px-5">
                <i class="fas fa-save me-2"></i>{$submit_label}
            </button>
        </div>
    </div>
    HTML;
}

/** Модалка подтверждения очистки разрешений */
function fm_clear_permission_modal(): void
{
    echo <<<'HTML'
    <div class="modal fade" id="clearPermissionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-trash-can fa-2x text-danger mb-3"></i>
                    <h5 class="fw-bold mb-2">Clear Custom Permissions</h5>
                    <p class="text-muted mb-4">
                        Clear permissions for <span class="fw-semibold text-primary" id="modalGroupName"></span>?
                        <small class="d-block mt-1">This cannot be undone.</small>
                    </p>
                    <div class="d-flex gap-3 justify-content-center">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" id="confirmClearBtn">Clear</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        var cur={};
        document.querySelectorAll('.clear-permission-btn').forEach(function(b){
            b.addEventListener('click',function(e){
                e.preventDefault();
                cur={pid:this.dataset.pid,fid:this.dataset.fid,gid:this.dataset.gid,
                     groupName:this.dataset.groupName,postKey:this.dataset.postKey};
                document.getElementById('modalGroupName').textContent=cur.groupName;
                new bootstrap.Modal(document.getElementById('clearPermissionModal')).show();
            });
        });
        document.getElementById('confirmClearBtn').addEventListener('click',function(){
            var f=document.createElement('form');
            f.method='post'; f.action='index.php?act=management&action=clear_permission';
            Object.entries({pid:cur.pid,fid:cur.fid,gid:cur.gid,my_post_key:cur.postKey})
                  .forEach(function([n,v]){var i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i);});
            document.body.appendChild(f); f.submit();
        });
    });
    </script>
    HTML;
}

/** Модалка подтверждения удаления модератора */
function fm_delete_mod_modal(): void
{
    echo <<<'HTML'
    <div class="modal fade" id="deleteModeratorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <i class="fas fa-user-slash fa-2x text-danger mb-3"></i>
                    <h5 class="mb-3">Remove this moderator?</h5>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger" id="confirmDeleteModeratorBtn">Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        var d={};
        document.querySelectorAll('.delete-moderator-btn').forEach(function(b){
            b.addEventListener('click',function(e){
                e.preventDefault();
                d={mid:this.dataset.mid,fid:this.dataset.fid,isgroup:this.dataset.isgroup,postKey:this.dataset.postKey};
                new bootstrap.Modal(document.getElementById('deleteModeratorModal')).show();
            });
        });
        document.getElementById('confirmDeleteModeratorBtn').addEventListener('click',function(){
            var f=document.createElement('form');
            f.method='post'; f.action='index.php?act=management&action=deletemod';
            Object.entries({id:d.mid,fid:d.fid,isgroup:d.isgroup,my_post_key:d.postKey})
                  .forEach(function([n,v]){var i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i);});
            document.body.appendChild(f); f.submit();
        });
    });
    </script>
    HTML;
}









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




// ═══════════════════════════════════════════════════════════
// ACTION: COPY
// ═══════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'copy') {
    $plugins->run_hooks('admin_forum_management_copy');

    if ($mybb->request_method === 'post') {
        $from = $mybb->get_input('from', MyBB::INPUT_INT);
        $to   = $mybb->get_input('to',   MyBB::INPUT_INT);

        $query      = $db->simple_select('forums', '*', "fid='{$from}'");
        $from_forum = $db->fetch_array($query);
        if (!$db->num_rows($query)) $errors[] = 'error_invalid_source_forum';

        if ($to === -1) {
            if (empty($mybb->input['title']))                               $errors[] = 'You need to give your new forum a name';
            if ($mybb->input['pid'] == -1 && $mybb->input['type'] === 'f') $errors[] = 'You must select a parent forum';

            if (!$errors) {
                $pid = max(0, $mybb->get_input('pid', MyBB::INPUT_INT));
                $new_forum = array_diff_key($from_forum, array_flip([
                    'fid','threads','posts','lastpost','lastposter','lastposteruid',
                    'lastposttid','lastpostsubject','unapprovedthreads','unapprovedposts'
                ]));
                $new_forum['name']        = $mybb->input['title'];
                $new_forum['description'] = $mybb->input['description'];
                $new_forum['type']        = $mybb->input['type'];
                $new_forum['pid']         = $pid;
                $new_forum['parentlist']  = '';
                $new_forum = array_map([$db, 'escape_string'], $new_forum);

                $to = $db->insert_query('forums', $new_forum);
                $db->update_query('forums', ['parentlist' => make_parent_list($to)], "fid='{$to}'");
            }
        } elseif ($mybb->input['copyforumsettings'] == 1) {
            $query    = $db->simple_select('forums', '*', "fid='{$to}'");
            $to_forum = $db->fetch_array($query);
            if (!$db->num_rows($query)) $errors[] = 'Invalid destination forum';

            if (!$errors) {
                $new_forum = array_diff_key($from_forum, array_flip([
                    'fid','threads','posts','lastpost','lastposter','lastposteruid',
                    'lastposttid','lastpostsubject','unapprovedthreads','unapprovedposts'
                ]));
                $new_forum['name']        = $to_forum['name'];
                $new_forum['description'] = $to_forum['description'];
                $new_forum['pid']         = $to_forum['pid'];
                $new_forum['parentlist']  = $to_forum['parentlist'];
                $new_forum = array_map([$db, 'escape_string'], $new_forum);
                $db->update_query('forums', $new_forum, "fid='{$to}'");
            }
        } else {
            $new_forum['name'] = null;
        }

        if (!$errors) {
            if (!empty($mybb->input['copygroups']) && is_array($mybb->input['copygroups'])) {
                $groups = implode(',', array_map('intval', $mybb->input['copygroups']));
                $query  = $db->simple_select('forumpermissions', '*', "fid='{$from}' AND gid IN ({$groups})");
                $db->delete_query('forumpermissions', "fid='{$to}' AND gid IN ({$groups})", 1);
                while ($p = $db->fetch_array($query)) {
                    unset($p['pid']); $p['fid'] = $to;
                    $db->insert_query('forumpermissions', $p);
                }
                log_admin_action($from, $from_forum['name'], $to, $new_forum['name'], $groups);
            } else {
                log_admin_action($from, $from_forum['name'], $to, $new_forum['name']);
            }

            $plugins->run_hooks('admin_forum_management_copy_commit');
            $cache->update_forums();
            $cache->update_forumpermissions();

            flash_message($lang->forum_management['success_forum_copied'], 'success');
            admin_redirect("index.php?act=management&action=edit&fid={$to}");
        }
    }

    // ── Sub-tabs ──
    if (!empty($mybb->input['fid'])) {
        $sub_tabs = [
            'view_forum'         => ['title'=>'View Forum',         'link'=>"index.php?act=management&fid={$mybb->input['fid']}",                    'description'=>''],
            'add_child_forum'    => ['title'=>'Add Child Forum',    'link'=>"index.php?act=management&action=add&pid={$mybb->input['fid']}",          'description'=>''],
            'edit_forum_settings'=> ['title'=>'Edit Forum Settings','link'=>"index.php?act=management&action=edit&fid={$mybb->input['fid']}",         'description'=>''],
            'copy_forum'         => ['title'=>'Copy Forum',         'link'=>"index.php?act=management&action=copy&fid={$mybb->input['fid']}",         'description'=>''],
        ];
    }

    // ── Defaults ──
    $copy_data = [
        'type' => 'f', 'title' => '', 'description' => '',
        'pid'  => max(0, $mybb->get_input('pid', MyBB::INPUT_INT)),
        'disporder' => 1, 'from' => $mybb->get_input('fid'),
        'to' => -1, 'copyforumsettings' => 0, 'copygroups' => [],
    ];
    if ($errors) {
        foreach ($copy_data as $k => $_) {
            if (isset($mybb->input[$k])) $copy_data[$k] = $mybb->input[$k];
        }
    }

    $usergroupsZZ = [];
    $q = $db->simple_select('usergroups', 'gid, title', "gid != '1'", ['order_by' => 'title']);
    while ($ug = $db->fetch_array($q)) {
        $usergroupsZZ[$ug['gid']] = htmlspecialchars_uni($ug['title']);
    }

    stdhead('Copy Forum');
    fm_head_assets();
    output_nav_tabs($sub_tabs ?? [], 'copy_forum');
    ?>

    <div class="admin-container">
    <div class="container mt-3">
        <div class="card border-0 -lg rounded-3 overflow-hidden">
            <?php fm_card_header('Copy Forum Settings', 'Duplicate forum settings and permissions to another forum', 'clone', 'info'); ?>

            <div class="card-body px-5 py-4">
                <?php fm_errors($errors ?? []); ?>

                <div class="row">
                <div class="col-lg-8">
                <form method="post" action="index.php?act=management&action=copy" id="copyForumForm">
                    <?= generate_post_check() ?>

                    <?php fm_step(1, 'info', 'exchange-alt', 'Select Forums'); ?>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-info bg-opacity-10 py-3">
                                    <h6 class="mb-0 fw-bold"><i class="fas fa-download me-2 text-info"></i>Copy FROM <span class="text-danger">*</span></h6>
                                </div>
                                <div class="card-body">
                                    <?= generate_forum_select('from', $copy_data['from'], ['id'=>'from','class'=>'form-select']) ?>
                                    <small class="text-muted">Forum to copy settings from</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-success bg-opacity-10 py-3">
                                    <h6 class="mb-0 fw-bold"><i class="fas fa-upload me-2 text-success"></i>Copy TO <span class="text-danger">*</span></h6>
                                </div>
                                <div class="card-body">
                                    <?= generate_forum_select('to', $copy_data['to'], ['id'=>'to','class'=>'form-select','main_option'=>'Create New Forum']) ?>
                                    <small class="text-muted">Forum to copy settings to</small>

                                    <!-- New forum fields -->
                                    <div id="newForumSettings" style="display:none" class="mt-3">
                                        <?php fm_type_cards($copy_data['type']); ?>
                                        <div class="mt-3">
                                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars_uni($copy_data['title']) ?>">
                                        </div>
                                        <div class="mt-3">
                                            <label class="form-label fw-semibold">Description</label>
                                            <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars_uni($copy_data['description']) ?></textarea>
                                        </div>
                                        <div class="mt-3" id="parentForumField">
                                            <label class="form-label fw-semibold">Parent Forum <span class="text-danger">*</span></label>
                                            <?= generate_forum_select('pid', $copy_data['pid'], ['id'=>'pid','class'=>'form-select','main_option'=>'None']) ?>
                                        </div>
                                    </div>

                                    <!-- Copy settings toggle (existing forum) -->
                                    <div id="copySettings" style="display:none" class="mt-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="copyforumsettings" id="copyforumsettings" value="1" <?= $copy_data['copyforumsettings'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold" for="copyforumsettings">Copy Forum Settings</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php fm_step(2, 'warning', 'users', 'Copy Permissions'); ?>
                    <div class="card mb-5">
                        <div class="card-header bg-warning bg-opacity-10 py-3">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-shield-alt me-2 text-warning"></i>User Group Permissions</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6">
                                    <label class="form-label fw-semibold">Select User Groups</label>
                                    <?= generate_select_box('copygroups[]', $usergroupsZZ, $copy_data['copygroups'], ['id'=>'copygroups','multiple'=>true,'size'=>8,'class'=>'form-select']) ?>
                                    <small class="text-muted">Hold CTRL for multiple</small>
                                </div>
                                <div class="col-lg-6">
                                    <div class="bg-light rounded p-3 h-100">
                                        <h6 class="fw-bold mb-3">Selected Groups</h6>
                                        <div id="selectedGroupsList"><p class="text-muted small mb-2">No groups selected</p></div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllGroups">Select All</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="deselectAllGroups">Deselect All</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php fm_submit_row('index.php?act=management', 'Copy Forum Settings', false); ?>
                </form>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="sticky-top" style="top:20px">
                        <div class="card border-0 -sm mb-4">
                            <div class="card-header bg-info text-white py-3">
                                <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Quick Tips</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info small mb-2"><strong>New Forum:</strong> Select "Create New Forum" as destination</div>
                                <div class="alert alert-warning small mb-2"><strong>Existing Forum:</strong> Enable "Copy Forum Settings" to overwrite</div>
                                <div class="alert alert-success small mb-0"><strong>Permissions:</strong> Hold CTRL to multi-select groups</div>
                            </div>
                        </div>
                    </div>
                </div>
                </div><!-- /row -->
            </div>
        </div>
    </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded',function(){
        var toSel  = document.getElementById('to');
        var cg     = document.getElementById('copygroups');
        var newF   = document.getElementById('newForumSettings');
        var copyS  = document.getElementById('copySettings');
        var sgList = document.getElementById('selectedGroupsList');

        function syncTo(){
            var v=toSel.value;
            newF.style.display  = v==='-1'  ? 'block':'none';
            copyS.style.display = v!=='-1'&&v!=='' ? 'block':'none';
        }
        function syncGroups(){
            var sel=Array.from(cg.selectedOptions);
            sgList.innerHTML = sel.length
                ? sel.map(o=>'<span class="badge bg-primary me-1 mb-1">'+o.text+'</span>').join('')
                : '<p class="text-muted small mb-0">No groups selected</p>';
        }
        toSel.addEventListener('change', syncTo);
        cg.addEventListener('change', syncGroups);
        document.getElementById('selectAllGroups').addEventListener('click',function(){Array.from(cg.options).forEach(o=>o.selected=true);syncGroups();});
        document.getElementById('deselectAllGroups').addEventListener('click',function(){Array.from(cg.options).forEach(o=>o.selected=false);syncGroups();});
        syncTo(); syncGroups();

        document.getElementById('copyForumForm').addEventListener('submit',function(e){
            var btn=this.querySelector('button[type="submit"]');
            btn.disabled=true;
            btn.innerHTML='<i class="fas fa-spinner fa-spin me-2"></i>Copying...';
        });
    });
    </script>
    <?php
    fm_type_js();
    stdfoot();
	exit;
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
	
	fm_head_assets();
	
	
	

	
echo '<div class="container mt-3">';
	
output_nav_tabs($sub_tabs, 'edit_mod');

$form = new Form("index.php?act=management&action=editmod", "post", "editModForm");
echo $form->generate_hidden_field("mid", $mod_data['mid']);

if($errors)
{
    output_inline_error($errors);
    $mod_data = $mybb->input;
}

echo '<div class="card border-0 -sm">';
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



echo '</div>'; // .container mt-3
	
	

	stdfoot();
	exit;
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












// ============================================================
// ACTION: PERMISSIONS
// ============================================================
if ($mybb->input['action'] === 'permissions') {
    $plugins->run_hooks('admin_forum_management_permissions');

    // ── POST ─────────────────────────────────────────────────
    if ($mybb->request_method === 'post') {
        $pid   = $mybb->get_input('pid', MyBB::INPUT_INT);
        $fid   = $mybb->get_input('fid', MyBB::INPUT_INT);
        $gid   = $mybb->get_input('gid', MyBB::INPUT_INT);
        $forum = get_forum($fid, 1);

        if ((!$fid || !$gid) && $pid) {
            $query  = $db->simple_select('forumpermissions', 'fid, gid', "pid='{$pid}'");
            $result = $db->fetch_array($query);
            $fid    = (int)$result['fid'];
            $gid    = (int)$result['gid'];
            $forum  = get_forum($fid, 1);
        }

        $update_array = [];
        $fields_array = $db->show_fields_from('forumpermissions');
        $input_perms  = $mybb->input['permissions'] ?? null;

        foreach ($fields_array as $field) {
            $fname = $field['Field'];
            if (!str_contains($fname, 'can') && !str_contains($fname, 'mod')) {
                continue;
            }
            $update_array[$db->escape_string($fname)] = $input_perms !== null
                ? (int)($input_perms[$fname] ?? 0)
                : 0;
        }

        if ($fid && !$pid) {
            $update_array['fid'] = $fid;
            $update_array['gid'] = $gid;
            $db->insert_query('forumpermissions', $update_array);
        }

        $plugins->run_hooks('admin_forum_management_permissions_commit');

        if (!($fid && !$pid)) {
            $db->update_query('forumpermissions', $update_array, "pid='{$pid}'");
        }

        $cache->update_forumpermissions();
        log_admin_action($fid, $forum['name'] ?? '');

        if ((int)($mybb->input['ajax'] ?? 0) === 1) {
            $js = "<script type=\"text/javascript\">\n"
                . "document.getElementById('row_{$gid}').innerHTML = '"
                . str_replace(["'", "\t", "\n"], ["\\'", '', ''], retrieve_single_permissions_row($gid, $fid))
                . "';\n"
                . "if (typeof QuickPermEditor !== 'undefined') { QuickPermEditor.init({$gid}); }\n"
                . '</script>';
            echo json_encode($js);
            exit;
        }

        flash_message($lang->forum_management['success_forum_permissions_saved'], 'success');
        admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");
    }

    $is_ajax = (int)($mybb->input['ajax'] ?? 0) === 1;

    // ── Non-AJAX: page setup ──────────────────────────────────
    if (!$is_ajax) {
        $sub_tabs  = [];
        $fid_in    = $mybb->get_input('fid', MyBB::INPUT_INT);
        $gid_in    = $mybb->get_input('gid', MyBB::INPUT_INT);

        if ($fid_in && $gid_in) {
            $sub_tabs['edit_permissions'] = [
                'title'       => $lang->forum_management['forum_permissions2'],
                'link'        => "index.php?act=management&action=permissions&fid={$fid_in}&amp;gid={$gid_in}",
                'description' => $lang->forum_management['forum_permissions_desc'],
            ];
            $page->add_breadcrumb_item(
                $lang->forum_management['forum_permissions2'],
                "index.php?act=management&fid={$fid_in}#tab_permissions"
            );
        } else {
            $pid_in = $mybb->get_input('pid', MyBB::INPUT_INT);
            $query  = $db->simple_select('forumpermissions', 'fid', "pid='{$pid_in}'");
            $mybb->input['fid'] = $db->fetch_field($query, 'fid');

            $sub_tabs['edit_permissions'] = [
                'title'       => $lang->forum_management['forum_permissions'],
                'link'        => "index.php?act=management&action=permissions&pid={$pid_in}",
                'description' => $lang->forum_management['forum_permissions_desc'],
            ];
            $page->add_breadcrumb_item(
                $lang->forum_management['forum_permissions2'],
                "index.php?act=management&fid={$mybb->input['fid']}#tab_permissions"
            );
        }

        $page->add_breadcrumb_item($lang->forum_management['forum_permissions']);

        stdhead('Forum Permissions');
        fm_head_assets();

        output_nav_tabs($sub_tabs, 'edit_permissions');

    } else {
        // ── AJAX mode: scripts for the modal ─────────────────
        echo '<script src="scripts/popup.js"></script>';
        echo '<script src="scripts/tabs.js"></script>';
        echo '<script>
document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("modal_form")?.addEventListener("click", e => {
        const btn = e.target.id === "savePermissions"
            ? e.target
            : e.target.closest("#savePermissions");
        if (!btn) return;
        e.preventDefault();

        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = \'<i class="fas fa-spinner fa-spin me-2"></i>Saving...\';

        const form     = document.getElementById("modal_form");
        const formData = new FormData(form);

        fetch(form.action, { method: "POST", body: formData })
            .then(r => r.json())
            .then(data => {
                if (typeof data === "string" && data.includes("<script>")) {
                    (data.match(/<script[^>]*>([\s\S]*?)<\/script>/g) ?? []).forEach(s => {
                        const code = s.replace(/<script[^>]*>([\s\S]*?)<\/script>/, "$1");
                        try { new Function(code)(); } catch (err) { console.error(err); }
                    });
                }
                bootstrap.Modal.getInstance(document.getElementById("dynamicModal"))?.hide();
                btn.disabled  = false;
                btn.innerHTML = orig;
            })
            .catch(err => {
                console.error(err);
                alert("Failed to save permissions. Please try again.");
                btn.disabled  = false;
                btn.innerHTML = orig;
            });
    });

    document.querySelectorAll(\'#permissionTabs button[data-bs-toggle="tab"]\').forEach(btn => {
        btn.addEventListener("click", e => { e.preventDefault(); new bootstrap.Tab(btn).show(); });
    });
});
</script>';
    }

    // ── Modal with permission form ────────────────────────────
    $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
    $gid = $mybb->get_input('gid', MyBB::INPUT_INT);
    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);

    if (!empty($pid) || (!empty($gid) && !empty($fid))) {
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
                    <div style="overflow-y:auto;max-height:400px">';

        $form = new Form(
            "index.php?act=management&action=permissions&ajax=1&pid={$pid}&gid={$gid}&fid={$fid}",
            'post',
            'modal_form'
        );

        echo $form->generate_hidden_field('usecustom', '1');

        if (!empty($errors)) {
            fm_errors($errors);
            $permission_data = $mybb->input;
            $usergroup = $db->fetch_array($db->simple_select('usergroups', '*', "gid='" . $db->escape_string($permission_data['gid']) . "'"));
            $forum     = $db->fetch_array($db->simple_select('forums',  '*', "fid='" . $db->escape_string($permission_data['fid']) . "'"));
        } else {
            $query = $pid
                ? $db->simple_select('forumpermissions', '*', "pid='{$pid}'")
                : $db->simple_select('forumpermissions', '*', "fid='{$fid}' AND gid='{$gid}'", ['limit' => 1]);

            $permission_data = $db->fetch_array($query);

            if (is_array($permission_data)) {
                $fid = $fid ?: (int)$permission_data['fid'];
                $gid = $gid ?: (int)$permission_data['gid'];
                $pid = $pid ?: (int)$permission_data['pid'];
            }

            $usergroup   = $db->fetch_array($db->simple_select('usergroups', '*', "gid='{$gid}'"));
            $forum       = $db->fetch_array($db->simple_select('forums',  '*', "fid='{$fid}'"));
            $customperms = $db->fetch_array($db->simple_select(
                'forumpermissions', '*',
                build_parent_list($fid) . " AND gid='{$gid}'"
            ));

            if (!empty($permission_data['pid'])) {
                $permission_data['usecustom'] = 1;
                echo $form->generate_hidden_field('pid', $pid);
            } else {
                echo $form->generate_hidden_field('fid', $fid);
                echo $form->generate_hidden_field('gid', $gid);
                $permission_data = empty($customperms['pid'])
                    ? usergroup_permissions($gid)
                    : forum_permissions($fid, 0, $gid);
            }
        }

        // Permission group map
        $groups = [
            'canviewthreads'         => 'viewing',
            'canview'                => 'viewing',
            'canonlyviewownthreads'  => 'viewing',
            'candlattachments'       => 'viewing',
            'canpostthreads'         => 'posting_rating',
            'canpostreplys'          => 'posting_rating',
            'canonlyreplyownthreads' => 'posting_rating',
            'canpostattachments'     => 'posting_rating',
            'canratethreads'         => 'posting_rating',
            'caneditposts'           => 'editing',
            'candeleteposts'         => 'editing',
            'candeletethreads'       => 'editing',
            'caneditattachments'     => 'editing',
            'canviewdeletionnotice'  => 'editing',
            'modposts'               => 'moderate',
            'modthreads'             => 'moderate',
            'modattachments'         => 'moderate',
            'mod_edit_posts'         => 'moderate',
            'canpostpolls'           => 'polls',
            'canvotepolls'           => 'polls',
            'cansearch'              => 'misc',
        ];

        $hidefields = ($usergroup['gid'] == 222)
            ? ['canonlyviewownthreads','canonlyreplyownthreads','caneditposts',
               'candeleteposts','candeletethreads','caneditattachments','canviewdeletionnotice']
            : [];

        $groups = $plugins->run_hooks('admin_forum_management_permission_groups', $groups);
        foreach ($hidefields as $hf) { unset($groups[$hf]); }

        $tab_colors  = ['viewing'=>'bg-primary','posting_rating'=>'bg-success','editing'=>'bg-info','moderate'=>'bg-warning','polls'=>'bg-purple','misc'=>'bg-secondary'];
        $tab_icons   = ['viewing'=>'fa-eye','posting_rating'=>'fa-comment','editing'=>'fa-edit','moderate'=>'fa-gavel','polls'=>'fa-chart-bar','misc'=>'fa-cog'];
        $tab_titles  = ['viewing'=>'Viewing','posting_rating'=>'Posting & Rating','editing'=>'Editing','moderate'=>'Moderation','polls'=>'Polls','misc'=>'Misc'];

        $l = [
            'viewing_field_canview'                       => 'Can view forum?',
            'viewing_field_canviewthreads'                => 'Can view threads within forum?',
            'viewing_field_canonlyviewownthreads'         => 'Can only view own threads?',
            'viewing_field_candlattachments'              => 'Can download attachments?',
            'posting_rating_field_canpostthreads'         => 'Can post threads?',
            'posting_rating_field_canpostreplys'          => 'Can post replies?',
            'posting_rating_field_canonlyreplyownthreads' => 'Can only reply to own threads?',
            'posting_rating_field_canpostattachments'     => 'Can post attachments?',
            'posting_rating_field_canratethreads'         => 'Can rate threads?',
            'editing_field_caneditposts'                  => 'Can edit own posts?',
            'editing_field_candeleteposts'                => 'Can delete own posts?',
            'editing_field_candeletethreads'              => 'Can delete own threads?',
            'editing_field_caneditattachments'            => 'Can update own attachments?',
            'editing_field_canviewdeletionnotice'         => 'Can view deletion notices?',
            'moderate_field_modposts'                     => 'Moderate new posts?',
            'moderate_field_modthreads'                   => 'Moderate new threads?',
            'moderate_field_modattachments'               => 'Moderate new attachments?',
            'moderate_field_mod_edit_posts'               => "Moderate posts after they've been edited?",
            'polls_field_canpostpolls'                    => 'Can post polls?',
            'polls_field_canvotepolls'                    => 'Can vote in polls?',
            'misc_field_cansearch'                        => 'Can search forum?',
        ];

        // Tabs nav
        echo '<div class="container-fluid px-0">
                <ul class="nav nav-tabs nav-justified mb-4" id="permissionTabs" role="tablist">';

        $first = true;
        foreach (array_unique(array_values($groups)) as $group) {
            $active = $first ? ' active' : '';
            $sel    = $first ? 'true' : 'false';
            echo '<li class="nav-item" role="presentation">
                    <button class="nav-link' . $active . '" id="' . $group . '-tab"
                            data-bs-toggle="tab" data-bs-target="#tab_' . $group . '"
                            type="button" role="tab" aria-selected="' . $sel . '">
                        <i class="fas ' . $tab_icons[$group] . ' me-1"></i>' . $tab_titles[$group] . '
                    </button>
                  </li>';
            $first = false;
        }
        echo '</ul><div class="tab-content">';

        // Tab content
        $first = true;
        foreach (array_unique(array_values($groups)) as $group) {
            $show = $first ? ' show active' : '';
            echo '<div class="tab-pane fade' . $show . '" id="tab_' . $group . '" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header ' . $tab_colors[$group] . ' text-white py-3">
                            <h6 class="mb-0">
                                <i class="fas fa-user me-2"></i>"'
                                . htmlspecialchars($usergroup['title'])
                                . '" Custom Permissions for "'
                                . htmlspecialchars($forum['name']) . '"
                            </h6>
                        </div>
                        <div class="card-body"><div class="row">';

            foreach ($db->show_fields_from('forumpermissions') as $field) {
                $fname = $field['Field'];
                if (in_array($fname, $hidefields, true)) { continue; }
                if (!str_starts_with($fname, 'can') && !str_starts_with($fname, 'mod')) { continue; }
                if (!isset($groups[$fname]) || $groups[$fname] !== $group) { continue; }

                $label_key = $group . '_field_' . $fname;
                $checkbox  = $form->generate_check_box(
                    "permissions[{$fname}]", 1, '',
                    ['checked' => !empty($permission_data[$fname]), 'id' => $fname, 'class' => 'form-check-input']
                );

                echo '<div class="col-md-6">
                        <div class="form-check form-switch mb-3">
                            ' . $checkbox . '
                            <label class="form-check-label" for="' . htmlspecialchars($fname) . '">'
                            . ($l[$label_key] ?? $fname) . '
                            </label>
                        </div>
                      </div>';
            }

            echo '</div></div></div></div>';
            $first = false;
        }

        echo '</div></div>';

        echo '<div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="savePermissions">
                    <i class="fas fa-save me-2"></i>Save Permissions
                </button>
              </div>';

        $form->end();

        echo '</div></div></div></div></div>';
    }

    if (!$is_ajax) {
        stdfoot();
		exit;
    }
}










// ═══════════════════════════════════════════════════════════
// ACTION: ADD
// ═══════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'add') {
    $plugins->run_hooks('admin_forum_management_add');

    if ($mybb->request_method === 'post') {
        if (!trim($mybb->input['title'])) $errors[] = 'You must enter a title';
        $pid  = $mybb->get_input('pid', MyBB::INPUT_INT);
        $type = $mybb->input['type'];
        if ($pid <= 0 && $type === 'f') $errors[] = 'You must select a parent forum';

        if (!$errors) {
            $pid = max(0, $pid);
            $insert = [
                'name'             => $db->escape_string($mybb->input['title']),
                'description'      => $db->escape_string($mybb->input['description']),
                'linkto'           => $db->escape_string($mybb->input['linkto']),
                'type'             => $db->escape_string($type),
                'pid'              => $pid,
                'parentlist'       => '',
                'disporder'        => $mybb->get_input('disporder',        MyBB::INPUT_INT),
                'active'           => $mybb->get_input('active',           MyBB::INPUT_INT),
                'open'             => $mybb->get_input('open',             MyBB::INPUT_INT),
                'usepostcounts'    => $mybb->get_input('usepostcounts',    MyBB::INPUT_INT),
                'usethreadcounts'  => $mybb->get_input('usethreadcounts',  MyBB::INPUT_INT),
                'password'         => $db->escape_string($mybb->input['password']),
                'defaultdatecut'   => $mybb->get_input('defaultdatecut',   MyBB::INPUT_INT),
                'defaultsortby'    => $db->escape_string($mybb->input['defaultsortby']),
                'defaultsortorder' => $db->escape_string($mybb->input['defaultsortorder']),
            ];

            $plugins->run_hooks('admin_forum_management_add_start');
            $fid = $db->insert_query('forums', $insert);
            $db->update_query('forums', ['parentlist' => make_parent_list($fid)], "fid='{$fid}'");
            $cache->update_forums();

            $inherit = $mybb->input['default_permissions'] ?? [];
            foreach ($mybb->input as $id => $permission) {
                if (strpos($id, 'fields_') === false) continue;
                [, $gid] = explode('fields_', $id);
                if (!is_array($permission)) {
                    $permission = array_fill_keys(explode(',', $permission), 1);
                }
                foreach (['canview','canpostthreads','canpostreplys','canpostpolls','canpostattachments'] as $n) {
                    $permissions[$n][$gid] = !empty($permission[$n]) ? 1 : 0;
                }
            }
            $canview            = $permissions['canview']            ?? [];
            $canpostthreads     = $permissions['canpostthreads']     ?? [];
            $canpostpolls       = $permissions['canpostpolls']       ?? [];
            $canpostattachments = $permissions['canpostattachments'] ?? [];
            $canpostreplies     = $permissions['canpostreplys']      ?? [];
            save_quick_perms($fid);

            $plugins->run_hooks('admin_forum_management_add_commit');
            log_admin_action($fid, $insert['name']);
            flash_message($lang->forum_management['success_forum_added'], 'success');
            admin_redirect('index.php?act=management');
        }
    }

    $page->add_breadcrumb_item('Add New Forum');

    $forum_data = [
        'type'           => 'f',
        'title'          => '',
        'description'    => '',
        'pid'            => empty($mybb->input['pid']) ? -1 : $mybb->get_input('pid', MyBB::INPUT_INT),
        'disporder'      => 1,
        'linkto'         => '',
        'password'       => '',
        'active'         => 1,
        'open'           => 1,
        'overridestyle'  => '',
        'style'          => '',
        'rulestype'      => '',
        'rulestitle'     => '',
        'rules'          => '',
        'defaultdatecut' => '',
        'defaultsortby'  => '',
        'defaultsortorder' => '',
        'allowhtml'      => '',
        'allowmycode'    => 1,
        'allowsmilies'   => 1,
        'allowimgcode'   => 1,
        'allowvideocode' => 1,
        'allowpicons'    => 1,
        'allowtratings'  => 1,
        'showinjump'     => 1,
        'usepostcounts'  => 1,
        'usethreadcounts'=> 1,
    ];

    if ($errors ?? false) {
        output_inline_error($errors);
        foreach ($forum_data as $k => $_) {
            if (isset($mybb->input[$k])) $forum_data[$k] = $mybb->input[$k];
        }
    }

    stdhead('Add New Forum');
	
    echo '<div class="container mt-3">';
    echo '<div class="breadcrumb">' . $page->_generate_breadcrumb() . '</div>';
    fm_head_assets();
	
	echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css">';
    echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css">';
	
    echo '<link rel="stylesheet" href="templates/forum.css?ver=1813">';
    output_nav_tabs($sub_tabs ?? [], 'add_forum');

    $form = new Form('index.php?act=management&action=add', 'post');
    ?>

    <div class="container mt-4">
    <div class="card border-0 shadow-lg">
        <?php fm_card_header('Create New Forum', 'Configure your new forum with the settings below', 'plus-circle'); ?>
        <div class="card-body p-4">

            <div class="form-section">
                <?php fm_section_header('layer-group', 'primary', 'Forum Type', 'Select the type of forum you are creating'); ?>
                <?php fm_type_cards($forum_data['type']); ?>
            </div>

            <div class="form-section">
                <?php fm_section_header('info-circle', 'info', 'Basic Information', 'Essential details for your new forum'); ?>
                <?php fm_basic_fields($forum_data); ?>
            </div>

            <div class="form-section">
                <?php fm_section_header('sitemap', 'success', 'Forum Hierarchy', 'Organize your forum within the site structure'); ?>
                <label class="form-label fw-bold">Parent Forum <span class="required-badge">Required</span></label>
                <?= generate_forum_select('pid', $forum_data['pid'], ['id'=>'pid','class'=>'form-select form-select-lg','main_option'=>'None (Top Level)']) ?>
            </div>

            <?php fm_submit_row('index.php?act=management', 'Create Forum'); ?>
        </div>
    </div>
    </div>

    <?php
    fm_type_js();
    fm_toggle_js();
    fm_toggle_advanced_open();
    fm_extra_fields(array_merge(['active'=>1,'open'=>1,'usepostcounts'=>1,'usethreadcounts'=>1], $forum_data));
    fm_toggle_advanced_close();

    // ── Permissions table ─────────────────────────────────────
    $field_list2 = [
        'canview'       => 'View',
        'canpostthreads'=> 'Post Threads',
        'canpostreplys' => 'Post Replies',
        'canpostpolls'  => 'Post Polls',
    ];
    $ids = [];

    $q = $db->simple_select('usergroups', '*', '', ['order' => 'name']);
    while ($ug = $db->fetch_array($q)) $ugList[$ug['gid']] = $ug;
    ?>

    <div class="card mt-4">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Forum Permissions Management</h5>
        </div>
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4 py-3" style="width:30%">User Group</th>
                    <th class="py-3">Permissions</th>
                </tr>
            </thead>
            <tbody>
    <?php
    foreach ($ugList ?? [] as $ug) {
        $perms         = $ug;
        $perms_checked = [];
        foreach (array_keys($field_list2) as $fp) {
            $perms_checked[$fp] = ($perms[$fp] ?? 0) == 1 ? 1 : 0;
        }
        $gid      = $ug['gid'];
        $title    = htmlspecialchars_uni($ug['title']);
        $hiddenVal = implode(',', array_keys(array_filter($perms_checked)));

        $enabled_html  = implode('', array_map(
            fn($p) => $perms_checked[$p]
                ? '<span class="badge bg-success bg-opacity-10 text-success me-1 mb-1 permission-badge" data-perm="' . $p . '">' . $field_list2[$p] . '</span>'
                : '',
            array_keys($field_list2)
        ));
        $disabled_html = implode('', array_map(
            fn($p) => !$perms_checked[$p]
                ? '<span class="badge bg-danger bg-opacity-10 text-danger me-1 mb-1 permission-badge" data-perm="' . $p . '">' . $field_list2[$p] . '</span>'
                : '',
            array_keys($field_list2)
        ));

        $ids[] = $gid;

        $group_icon = !empty($ug['image'])
    ? $ug['image']
    : '<div class="icon-compact default-group" data-tooltip="' . $title . '"><i class="bi bi-people-fill" style="color:#6c757d;"></i></div>';

echo <<<HTML
        <tr data-group-id="{$gid}">
            <td class="ps-4">
                <div class="d-flex align-items-center">
                    <div class="group-icon me-3">{$group_icon}</div>
                    <div>
                        <strong class="d-block">{$title}</strong>
                        <small class="text-muted">ID: {$gid}</small>
                    </div>
                </div>
            </td>
            <td>
                <div class="permission-fields" id="permission-fields-{$gid}">
                    <div class="mb-2">
                        <small class="text-muted d-block mb-1">Allowed:</small>
                        <div class="enabled-permissions" id="enabled-{$gid}">
                            {$enabled_html}
                        </div>
                    </div>
                    <div>
                        <small class="text-muted d-block mb-1">Denied:</small>
                        <div class="disabled-permissions" id="disabled-{$gid}">
                            {$disabled_html}
                        </div>
                    </div>
                    <input type="hidden" name="fields_{$gid}" id="fields_{$gid}" value="{$hiddenVal}">
                </div>
            </td>
        </tr>
        HTML;
    }
    ?>
            </tbody>
        </table>
        <div class="card-footer bg-light py-3 text-end">
            <button type="submit" name="save" class="btn btn-primary px-5">
                <i class="fas fa-save me-2"></i>Save Forum Permissions
            </button>
        </div>
    </div>

    <?php
    // Инициализация QuickPermEditor для каждой группы
    echo '<script>document.addEventListener("DOMContentLoaded",function(){';
    foreach ($ids as $id) {
        echo "if(typeof QuickPermEditor!=='undefined')QuickPermEditor.init({$id});";
    }
    echo '});</script>';

    $form->end();
    echo '</div>'; // container
    stdfoot();
    exit;
}






// ═══════════════════════════════════════════════════════════
// ACTION: EDIT
// ═══════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'edit') {
    if (!$mybb->input['fid']) {
        flash_message($lang->forum_management['error_invalid_fid'], 'error');
        admin_redirect('index.php?act=management');
    }
    $fid   = $mybb->get_input('fid', MyBB::INPUT_INT);
    $query = $db->simple_select('forums', '*', "fid='{$fid}'");
    $forum_data = $db->fetch_array($query);
    if (!$forum_data) {
        flash_message($lang->forum_management['error_invalid_fid'], 'error');
        admin_redirect('index.php?act=management');
    }

    $plugins->run_hooks('admin_forum_management_edit');

    if ($mybb->request_method === 'post') {	
		
        if (!trim($mybb->input['title']))    $errors[] = 'You must enter a title';
        $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
        if ($pid === $fid)                   $errors[] = 'The forum parent cannot be the forum itself';
        else {
            $parents = explode(',', $db->fetch_field($db->simple_select('forums','parentlist',"fid='{$pid}'"), 'parentlist'));
            if (in_array($fid, $parents))    $errors[] = 'Cannot set parent to a child forum';
        }
        $type = $mybb->input['type'];
        if ($pid <= 0 && $type === 'f')      $errors[] = 'You must select a parent forum';
        if ($type === 'c' && $forum_data['type'] === 'f') {
            if ($db->fetch_field($db->simple_select('threads','COUNT(tid) as n',"fid='{$fid}'"), 'n') > 0)
                $errors[] = 'Forums with threads cannot be converted to categories';
        }
        if (!empty($mybb->input['linkto']) && empty($forum_data['linkto'])) {
            if ($db->fetch_field($db->simple_select('threads','COUNT(tid) as n',"fid='{$fid}'",[]), 'n') > 0)
                $errors[] = 'Forums with threads cannot be redirected';
        }

        if (!$errors) {
            $pid = max(0, $pid);
            $update = [
                'name'             => $db->escape_string($mybb->input['title']),
                'description'      => $db->escape_string($mybb->input['description']),
                'linkto'           => $db->escape_string($mybb->input['linkto']),
                'type'             => $db->escape_string($type),
                'pid'              => $pid,
                'disporder'        => $mybb->get_input('disporder',        MyBB::INPUT_INT),
                'active'           => $mybb->get_input('active',           MyBB::INPUT_INT),
                'open'             => $mybb->get_input('open',             MyBB::INPUT_INT),
                'usepostcounts'    => $mybb->get_input('usepostcounts',    MyBB::INPUT_INT),
                'usethreadcounts'  => $mybb->get_input('usethreadcounts',  MyBB::INPUT_INT),
                'password'         => $db->escape_string($mybb->input['password']),
                'defaultdatecut'   => $mybb->get_input('defaultdatecut',   MyBB::INPUT_INT),
                'defaultsortby'    => $db->escape_string($mybb->input['defaultsortby']),
                'defaultsortorder' => $db->escape_string($mybb->input['defaultsortorder']),
            ];
            $db->update_query('forums', $update, "fid='{$fid}'");

            if ($pid !== (int)$forum_data['pid']) {
                $db->update_query('forums', ['parentlist' => make_parent_list($fid)], "fid='{$fid}'");
                $col = $db->type === 'pgsql' || $db->type === 'sqlite'
                    ? "','||parentlist||',' LIKE '%,{$fid},%'"
                    : "CONCAT(',',parentlist,',') LIKE '%,{$fid},%'";
                $q2 = $db->simple_select('forums', 'fid', $col);
                while ($ch = $db->fetch_array($q2)) {
                    $db->update_query('forums', ['parentlist' => make_parent_list($ch['fid'])], "fid='{$ch['fid']}'");
                }
            }

            // Quick perms
            $inherit = $mybb->input['default_permissions'] ?? [];
            foreach ($mybb->input as $id => $permission) {
                if (strpos($id, 'fields_') === false || strpos($id, 'fields_default_') !== false || strpos($id, 'fields_inherit_') !== false) continue;
                [, $gid] = explode('fields_', $id);
                if ($mybb->input['fields_default_'.$gid] == $permission && $mybb->input['fields_inherit_'.$gid] == 1) { $inherit[$gid]=1; continue; }
                $inherit[$gid] = 0;
                if (!is_array($permission)) $permission = array_fill_keys(explode(',', $permission), 1);
                foreach (['canview','canpostthreads','canpostreplys','canpostpolls'] as $n) {
                    $permissions[$n][$gid] = !empty($permission[$n]) ? 1 : 0;
                }
            }
            $canview = $permissions['canview'] ?? [];
            $canpostthreads = $permissions['canpostthreads'] ?? [];
            $canpostpolls   = $permissions['canpostpolls']   ?? [];
            $canpostattachments = $permissions['canpostattachments'] ?? [];
            $canpostreplies = $permissions['canpostreplys']  ?? [];
            save_quick_perms($fid);
            $cache->update_forums();

            $plugins->run_hooks('admin_forum_management_edit_commit');
            log_admin_action($fid, $mybb->input['title']);
            flash_message('The forum settings have been updated successfully', 'success');
            admin_redirect("index.php?act=management&fid={$fid}");
        }
    }

    if ($errors ?? false) {
        output_inline_error($errors);
        $forum_data = array_merge($forum_data, $mybb->input);
    } else {
        $forum_data['title'] = $forum_data['name'];
    }

    //$extra_header = "<script src=\"scripts/quick_perm_editor.js\"></script>\n";
    $page->add_breadcrumb_item('Edit Forum');

    stdhead('Edit Forum');
    echo '<div class="container mt-3">';
    echo '<div class="breadcrumb">' . $page->_generate_breadcrumb() . '</div>';
  
    fm_head_assets();
	
	

echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css">';
echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css">';
	
	
    echo '<link rel="stylesheet" href="templates/forum.css?ver=1813">';
    output_nav_tabs($sub_tabs ?? [], 'edit_forum_settings');

    $form = new Form('index.php?act=management&action=edit', 'post');
    echo $form->generate_hidden_field('fid', $fid);
    ?>

    <div class="container mt-4">
    <div class="card border-0 -lg">
        <?php fm_card_header('Edit Forum: ' . htmlspecialchars_uni($forum_data['title']), 'Update and configure your forum settings', 'edit'); ?>
        <div class="card-body p-4">

            <div class="form-section">
                <?php fm_section_header('layer-group','primary','Forum Type','Select the type of forum'); ?>
                <?php fm_type_cards($forum_data['type']); ?>
            </div>

            <div class="form-section">
                <?php fm_section_header('info-circle','info','Basic Information'); ?>
                <?php fm_basic_fields($forum_data); ?>
            </div>

            <div class="form-section">
                <?php fm_section_header('sitemap','success','Forum Hierarchy'); ?>
                <label class="form-label fw-bold">Parent Forum <span class="required-badge">Required</span></label>
                <?= generate_forum_select('pid', $forum_data['pid'], ['id'=>'pid','class'=>'form-select form-select-lg','main_option'=>'None (Top Level)']) ?>
            </div>

            <?php fm_submit_row('index.php?act=management', 'Save Changes'); ?>
        </div>
    </div>
    </div>

    <?php
    fm_type_js();
    fm_toggle_js();
    fm_toggle_advanced_open();
    fm_extra_fields($forum_data);
    fm_toggle_advanced_close();

    //echo '</form>';

    // ── Permissions table (same as add, but with existing data) ──
    $cached_forum_perms = $cache->read('forumpermissions');
    $field_list2 = ['canview'=>'View','canpostthreads'=>'Post Threads','canpostreplys'=>'Post Replies','canpostpolls'=>'Post Polls'];
    $ids = [];
    $existing_permissions = [];

    $q = $db->simple_select('forumpermissions', '*', "fid='{$fid}'");
    while ($ex = $db->fetch_array($q)) $existing_permissions[$ex['gid']] = $ex;

    $q = $db->simple_select('usergroups', '*', '', ['order_dir' => 'name']);
    while ($ug = $db->fetch_array($q)) $ugList2[$ug['gid']] = $ug;
    ?>

    <div class="card mt-4">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Forum Permissions — <?= htmlspecialchars_uni($forum_data['name']) ?></h5>
        </div>
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4 py-3" style="width:25%">User Group</th>
                    <th class="py-3" style="width:45%">Permissions</th>
                    <th class="py-3" style="width:15%">Status</th>
                    <th class="text-end pe-4 py-3" style="width:15%">Actions</th>
                </tr>
            </thead>
            <tbody>
    <?php foreach ($ugList2 ?? [] as $ug):
        $gid   = $ug['gid'];
        $title = htmlspecialchars_uni($ug['title']);
        if (!empty($existing_permissions[$gid])) {
            $perms = $existing_permissions[$gid]; $default_checked = false;
        } elseif (!empty($cached_forum_perms[$fid][$gid])) {
            $perms = $cached_forum_perms[$fid][$gid]; $default_checked = true;
        } else {
            $perms = $ug; $default_checked = true;
        }
        $pc = [];
        foreach (array_keys($field_list2) as $fp) $pc[$fp] = ($perms[$fp] ?? 0) == 1 ? 1 : 0;
        //$enabled  = implode('', array_map(fn($p) => $pc[$p] ? '<span class="badge bg-success bg-opacity-10 text-success me-1 mb-1 permission-badge">' . $field_list2[$p] . '</span>' : '', array_keys($field_list2)));
        //$disabled = implode('', array_map(fn($p) => !$pc[$p] ? '<span class="badge bg-danger bg-opacity-10 text-danger me-1 mb-1 permission-badge">' . $field_list2[$p] . '</span>' : '', array_keys($field_list2)));
		
		$enabled  = implode('', array_map(fn($p) => $pc[$p]
    ? '<span class="badge bg-success bg-opacity-10 text-success me-1 mb-1 permission-badge" data-perm="'.$p.'">' . $field_list2[$p] . '</span>'
    : '', array_keys($field_list2)));
$disabled = implode('', array_map(fn($p) => !$pc[$p]
    ? '<span class="badge bg-danger bg-opacity-10 text-danger me-1 mb-1 permission-badge" data-perm="'.$p.'">' . $field_list2[$p] . '</span>'
    : '', array_keys($field_list2)));
		
		
		
		
		
		
        $hiddenVal = implode(',', array_keys(array_filter($pc)));
        $status    = $default_checked ? 'Inherited' : 'Custom';
        $statusCls = $default_checked ? 'bg-info bg-opacity-10 text-info' : 'bg-warning bg-opacity-10 text-warning';
        $ids[]     = $gid;
    ?>
            <tr data-group-id="<?= $gid ?>">
                <td class="ps-4">
                    <div class="d-flex align-items-center">
                        <div class="group-icon me-3">
                            <?= !empty($ug['image']) ? $ug['image'] : '<div class="icon-compact default-group" data-tooltip="' . $title . '"><i class="bi bi-people-fill" style="color:#6c757d;"></i></div>' ?>
                        </div>
                        <div>
                            <strong class="d-block"><?= $title ?></strong>
                            <small class="text-muted">ID: <?= $gid ?></small>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="d-flex gap-2">
                        <div class="flex-fill border rounded p-2 bg-success bg-opacity-10">
                            <small class="text-muted d-block mb-1">Allowed:</small>
                            <div class="enabled-permissions" id="enabled-<?= $gid ?>"><?= $enabled ?: '<span class="text-muted">None</span>' ?></div>
                        </div>
                        <div class="flex-fill border rounded p-2 bg-danger bg-opacity-10">
                            <small class="text-muted d-block mb-1">Denied:</small>
                            <div class="disabled-permissions" id="disabled-<?= $gid ?>"><?= $disabled ?: '<span class="text-muted">None</span>' ?></div>
                        </div>
                    </div>
                    <input type="hidden" name="fields_<?= $gid ?>" id="fields_<?= $gid ?>" value="<?= $hiddenVal ?>">
                    <input type="hidden" name="fields_inherit_<?= $gid ?>" value="<?= (int)$default_checked ?>">
                    <input type="hidden" name="fields_default_<?= $gid ?>" value="<?= $hiddenVal ?>">
                </td>
                <td><span class="badge <?= $statusCls ?> px-3 py-2"><?= $status ?></span></td>
                <td class="text-end pe-4">
                    <?php if (!$default_checked): ?>
                    <a href="index.php?act=management&action=permissions&pid=<?= $perms['pid'] ?>"
                       class="btn btn-outline-primary btn-sm"
                       onclick="popupWindow(this.href,null,true);return false;">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm ms-1 clear-permission-btn"
                       data-pid="<?= $perms['pid'] ?>" data-fid="<?= $fid ?>"
                       data-gid="<?= $gid ?>" data-group-name="<?= addslashes($title) ?>"
                       data-post-key="<?= $mybb->post_code ?>">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php else: ?>
                    <a href="index.php?act=management&action=permissions&gid=<?= $gid ?>&fid=<?= $fid ?>"
                       class="btn btn-outline-secondary btn-sm"
                       onclick="popupWindow(this.href,null,true);return false;">
                        <i class="fas fa-cog"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
    <?php endforeach; ?>
            </tbody>
        </table>
        <div class="card-footer bg-light py-3 d-flex justify-content-end">
            <button type="submit" name="save_forum" class="btn btn-primary px-5">
                <i class="fas fa-save me-2"></i>Save Permissions
            </button>
        </div>
    </div>

    <?php
    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded",function(){';
    foreach ($ids as $id) echo "if(typeof QuickPermEditor!=='undefined')QuickPermEditor.init({$id});";
    echo '});';
    echo '</script>';

    fm_clear_permission_modal();
    $form->end();
    echo '</div>';
    stdfoot();
	exit; // ← добавь это
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
    $query = $db->simple_select('forums', '*', "fid='{$fid}'");
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
                'forums',
                '*',
                "','||parentlist||',' LIKE '%,$fid,%'"
            );
            break;

        default:
            $query = $db->simple_select(
                'forums',
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
        'threads',
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
    $db->delete_query('forums', "fid='{$fid}'");

    switch ($db->type)
    {
        case 'pgsql':
        case 'sqlite':
            $db->delete_query(
                'forums',
                "','||parentlist||',' LIKE '%,$fid,%'"
            );
            break;

        default:
            $db->delete_query(
                'forums',
                "CONCAT(',', parentlist, ',') LIKE '%,$fid,%'"
            );
    }

    // -------------------------------------------------
    // Чистим связанные таблицы
    // -------------------------------------------------
    $db->delete_query('moderators', "fid='{$fid}' {$delquery}");
    $db->delete_query('forumsubscriptions', "fid='{$fid}' {$delquery}");
    $db->delete_query('forumpermissions', "fid='{$fid}' {$delquery}");
    $db->delete_query('announcements', "type IN ('forum', 'global') AND fid='{$fid}' {$delquery}");
    $db->delete_query('forumsread', "fid='{$fid}' {$delquery}");

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









// ============================================================
// DEFAULT ACTION: MAIN VIEW
// ============================================================
if (!$action) {
    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
    if ($fid) {
        $forum = get_forum($fid, 1);
    }

    $plugins->run_hooks('admin_forum_management_start');

    // ── POST ─────────────────────────────────────────────────
    if ($mybb->request_method === 'post') {

        if ($mybb->get_input('update') === 'permissions') {
            $inherit     = [];
            $permissions = [];

            foreach ($mybb->input as $id => $permission) {
                if (!str_contains($id, 'fields_')
                    || str_contains($id, 'fields_default_')
                    || str_contains($id, 'fields_inherit_')
                ) { continue; }

                [, $gid] = explode('fields_', $id, 2);

                if (($mybb->input['fields_default_' . $gid] ?? null) == $permission
                    && (int)($mybb->input['fields_inherit_' . $gid] ?? 0) === 1
                ) {
                    $inherit[$gid] = 1;
                    continue;
                }
                $inherit[$gid] = 0;

                if (!is_array($permission)) {
                    $permission = array_fill_keys(explode(',', $permission), 1);
                }

                foreach (['canview', 'canpostthreads', 'canpostreplys', 'canpostpolls'] as $name) {
                    $permissions[$name][$gid] = !empty($permission[$name]) ? 1 : 0;
                }
            }

            $canview            = $permissions['canview']            ?? [];
            $canpostthreads     = $permissions['canpostthreads']     ?? [];
            $canpostpolls       = $permissions['canpostpolls']       ?? [];
            $canpostattachments = $permissions['canpostattachments'] ?? [];
            $canpostreplies     = $permissions['canpostreplys']      ?? [];

            save_quick_perms($fid);
            $plugins->run_hooks('admin_forum_management_start_permissions_commit');
            $cache->update_forums();
            log_admin_action('quickpermissions', $fid, $forum['name'] ?? '');

            flash_message($lang->forum_management['success_forum_permissions_updated'], 'success');
            admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");

        } elseif ($mybb->get_input('add') === 'moderators') {
            $forum = get_forum($fid, 1);
            if (!$forum) {
                flash_message($lang->forum_management['error_invalid_forum'], 'error');
                admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
            }

            if (!empty($mybb->input['usergroup'])) {
                $isgroup = 1;
                $gid     = $mybb->get_input('usergroup', MyBB::INPUT_INT);
                if (empty($groupscache[$gid])) {
                    flash_message($lang->forum_management['error_moderator_not_found'], 'error');
                    admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
                }
                $newmod = ['id' => $gid, 'name' => $groupscache[$gid]['title']];
            } else {
                $options    = ['fields' => ['id AS id', 'username AS name', 'usergroup', 'additionalgroups']];
                $newmod     = $newmoduser = get_user_by_username($mybb->input['username'] ?? '', $options);
                $isgroup    = 0;
                if (empty($newmod['id'])) {
                    flash_message($lang->forum_management['error_moderator_not_found'], 'error');
                    admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
                }
            }

            if (!empty($newmod['id'])) {
                $query = $db->simple_select('moderators', 'id',
                    "id='{$newmod['id']}' AND fid='{$fid}' AND isgroup='{$isgroup}'",
                    ['limit' => 1]
                );

                if (!$db->num_rows($query)) {
                    $new_mod = [
                        'fid' => $fid, 'id' => $newmod['id'], 'isgroup' => $isgroup,
                        'caneditposts' => 1, 'cansoftdeleteposts' => 1, 'canrestoreposts' => 1,
                        'candeleteposts' => 1, 'cansoftdeletethreads' => 1, 'canrestorethreads' => 1,
                        'candeletethreads' => 1, 'canviewips' => 1, 'canviewunapprove' => 1,
                        'canviewdeleted' => 1, 'canopenclosethreads' => 1, 'canstickunstickthreads' => 1,
                        'canapproveunapprovethreads' => 1, 'canapproveunapproveposts' => 1,
                        'canapproveunapproveattachs' => 1, 'canmanagethreads' => 1, 'canmanagepolls' => 1,
                        'canpostclosedthreads' => 1, 'canmovetononmodforum' => 1, 'canusecustomtools' => 1,
                        'canmanageannouncements' => 1, 'canmanagereportedposts' => 1, 'canviewmodlog' => 1,
                    ];
                    $mid = $db->insert_query('moderators', $new_mod);

                    if (!$isgroup) {
                        $newmodgroups = $newmoduser['usergroup'] ?? '';
                        if (!empty($newmoduser['additionalgroups'])) {
                            $newmodgroups .= ',' . $newmoduser['additionalgroups'];
                        }
                        $groupperms = usergroup_permissions($newmodgroups);
                        if (($groupperms['canmodcp'] ?? 0) != 1) {
                            $uid = $newmoduser['id'];
                            if (in_array((int)($newmoduser['usergroup'] ?? 0), [2, 5], true)) {
                                $db->update_query('users', ['usergroup' => 6], "id='{$uid}'");
                            } else {
                                join_usergroup($uid, 6);
                            }
                        }
                    }

                    $plugins->run_hooks('admin_forum_management_start_moderators_commit');
                    $cache->update_moderators();
                    log_admin_action('addmod', $mid, $newmod['name'], $fid, $forum['name'] ?? '');

                    flash_message($lang->forum_management['success_moderator_added'], 'success');
                    admin_redirect("index.php?act=management&action=editmod&mid={$mid}");
                } else {
                    flash_message($lang->forum_management['error_moderator_already_added'], 'error');
                    admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
                }
            } else {
                flash_message($lang->forum_management['error_moderator_not_found'], 'error');
                admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
            }

        } else {
            // Save display order
            if (isset($mybb->input['save_forum_orders']) || isset($mybb->input['save_order'])) {
                $disporders = $mybb->input['disporder'] ?? [];
                if (!empty($disporders) && is_array($disporders)) {
                    foreach ($disporders as $update_fid => $order) {
                        $db->update_query('forums', ['disporder' => (int)$order], "fid='" . (int)$update_fid . "'");
                    }
                    $plugins->run_hooks('admin_forum_management_start_disporder_commit');
                    $cache->update_forums();

                    if (!empty($forum)) {
                        log_admin_action('orders', $forum['fid'], $forum['name'] ?? '');
                    } else {
                        log_admin_action('orders', 0);
                    }

                    flash_message($lang->forum_management['success_forum_disporder_updated'], 'success');
                    admin_redirect('index.php?act=management&fid=' . ($mybb->input['fid'] ?? 0));
                }
            }
        }
    }

    // ── Page setup ────────────────────────────────────────────
    //$extra_header .= "<script src=\"scripts/quick_perm_editor.js\"></script>\n";

    if ($fid) {
        $page->add_breadcrumb_item('View Forum', 'index.php?act=management');
    }

    if (!isset($forum_cache) || !is_array($forum_cache)) {
        cache_forums();
    }

    $form_container = $fid && isset($forum_cache[$fid])
        ? new FormContainer('Forums in ' . htmlspecialchars($forum_cache[$fid]['name']))
        : new FormContainer('Manage Forums');

    $form = new Form('index.php?act=management', 'post', 'management');

    stdhead('Forum Management');
    //echo $extra_header;
    fm_head_assets();
    echo '<link rel="stylesheet" href="templates/forum.css?ver=1813">';
    echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css">';
    echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css">';
	
	

    echo '<div class="container mt-3"><div class="breadcrumb">' . $page->_generate_breadcrumb() . '</div></div>';

    output_nav_tabs($sub_tabs, $fid ? 'view_forum' : 'forum_management');

    $forum_name_esc = $fid && isset($forum_cache[$fid])
        ? htmlspecialchars($forum_cache[$fid]['name'])
        : '';

    echo '
<div class="admin-container">
<div class="container mt-3">

    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-white rounded-3 py-2 px-3">'
        . ($fid && isset($forum_cache[$fid]) ? '
            <li class="breadcrumb-item">
                <a href="index.php?act=management" class="text-decoration-none text-primary">
                    <i class="fas fa-home me-1"></i>Forums
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                <i class="fas fa-folder me-1"></i>' . $forum_name_esc . '
            </li>' : '
            <li class="breadcrumb-item active" aria-current="page">
                <i class="fas fa-tachometer-alt me-1"></i>Forum Management
            </li>') . '
        </ol>
    </nav>

    <div class="card border-0 rounded-3 overflow-hidden">

        <div class="card-header bg-primary text-white py-4 px-5">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="header-icon bg-white bg-opacity-20 rounded-circle p-3 me-4">
                        <i class="fas fa-sitemap fa-2x"></i>
                    </div>
                    <div>
                        <h1 class="h3 mb-2 fw-bold">'
                        . ($fid && isset($forum_cache[$fid])
                            ? '<i class="fas fa-folder-open me-2"></i>Manage: ' . $forum_name_esc
                            : '<i class="fas fa-layer-group me-2"></i>Forum Management') . '
                        </h1>
                        <p class="mb-0 opacity-85">
                            <i class="fas fa-info-circle me-1"></i>'
                            . ($fid ? 'Manage forum hierarchy, permissions and moderators'
                                    : 'Organize your forum structure and settings') . '
                        </p>
                    </div>
                </div>
                <div class="status-badges">'
                . ($fid ? '<span class="badge bg-white bg-opacity-25 px-3 py-2 me-2"><i class="fas fa-hashtag me-1"></i>ID: ' . $fid . '</span>' : '') . '
                    <span class="badge bg-white bg-opacity-25 px-3 py-2">
                        <i class="fas fa-clock me-1"></i>' . date('H:i') . '
                    </span>
                </div>
            </div>
        </div>

        <!-- Nav tabs -->
        <div class="card-body px-5 pt-4 pb-0">
            <ul class="nav nav-tabs nav-tabs-modern border-0" id="forumTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="subforums-tab"
                            data-bs-toggle="tab" data-bs-target="#subforums"
                            type="button" role="tab">
                        <i class="fas fa-folder-tree me-2"></i>'
                        . ($fid ? 'Sub Forums' : 'All Forums') . '
                    </button>
                </li>'
                . ($fid ? '
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="permissions-tab"
                            data-bs-toggle="tab" data-bs-target="#permissions"
                            type="button" role="tab">
                        <i class="fas fa-shield-alt me-2"></i>Permissions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="moderators-tab"
                            data-bs-toggle="tab" data-bs-target="#moderators"
                            type="button" role="tab">
                        <i class="fas fa-user-shield me-2"></i>Moderators
                    </button>
                </li>' : '') . '
            </ul>
        </div>

        <div class="card-body">
        <div class="tab-content" id="forumTabsContent">

            <!-- Tab: Forums -->
            <div class="tab-pane fade show active" id="subforums" role="tabpanel">
                <div class="mb-4">
                    <h3 class="h5 mb-3 text-dark fw-bold">
                        <i class="fas fa-list-ol me-2 text-primary"></i>Forum Structure
                    </h3>
                    <p class="text-muted mb-4">Use the input fields to reorder forums, then save.</p>
                </div>

                <form method="post" action="index.php?act=management">
                    ' . $form->generate_hidden_field('fid', $fid) . '
                    ' . generate_post_check() . '

                    <div class="table-container">
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4 py-3 fw-semibold text-dark" style="width:50%;">
                                            <i class="fas fa-align-left me-2 text-muted"></i>Forum Details
                                        </th>
                                        <th class="text-center py-3 fw-semibold text-dark" style="width:20%;">
                                            <i class="fas fa-sort-numeric-up me-2 text-muted"></i>Display Order
                                        </th>
                                        <th class="text-end pe-4 py-3 fw-semibold text-dark" style="width:30%;">
                                            <i class="fas fa-sliders-h me-2 text-muted"></i>Quick Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>';

    build_admincp_forums_list($form_container, $form, $fid);

    if ($form_container->num_rows() === 0) {
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
                    </div>';

    if ($form_container->num_rows() > 0) {
        echo '
                    <div class="mt-4">
                        <div class="card border-0 bg-light-subtle">
                            <div class="card-body py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        ' . $form_container->num_rows() . ' forum(s) found
                                    </span>
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
                    </div>';
    }

    echo '
                </form>
            </div>';

    // ── Permissions tab ───────────────────────────────────────
    if ($fid && isset($forum_cache[$fid])) {
        $query = $db->simple_select('usergroups', '*', '', ['order' => 'name']);
        $usergroups22 = [];
        while ($ug = $db->fetch_array($query)) { $usergroups22[$ug['gid']] = $ug; }

        $query = $db->simple_select('forumpermissions', '*', "fid='{$fid}'");
        $existing_permissions = [];
        while ($ep = $db->fetch_array($query)) { $existing_permissions[$ep['gid']] = $ep; }

        $cached_forum_perms = $cache->read('forumpermissions');
        $field_list  = ['canview'=>'Can view?','canpostthreads'=>'Can post threads?','canpostreplys'=>'Can post replies?','canpostpolls'=>'Can post polls?'];
        $field_list2 = ['canview'=>'&#149; View','canpostthreads'=>'&#149; Post Threads','canpostreplys'=>'&#149; Post Replies','canpostpolls'=>'&#149; Post Polls'];

        echo '
            <div class="tab-pane fade" id="permissions" role="tabpanel">
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h3 class="h5 mb-2 text-dark fw-bold">
                                <i class="fas fa-key me-2 text-warning"></i>Forum Permissions
                            </h3>
                            <p class="text-muted mb-0">
                                Configure access rights for: <strong>' . $forum_name_esc . '</strong>
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
                                    <th class="ps-4 py-3 fw-semibold text-dark" style="width:25%;">User Group</th>
                                    <th class="py-3 fw-semibold text-dark" style="width:35%;">Allowed Permissions</th>
                                    <th class="py-3 fw-semibold text-dark" style="width:25%;">Status</th>
                                    <th class="text-end pe-4 py-3 fw-semibold text-dark" style="width:15%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>';

        $perm_ids = [];
        foreach ($usergroups22 as $usergroup) {
            $gid    = $usergroup['gid'];
            $utitle = htmlspecialchars_uni($usergroup['title']);

            if (!empty($existing_permissions[$gid])) {
                $perms           = $existing_permissions[$gid];
                $default_checked = false;
            } elseif (!empty($cached_forum_perms[$fid][$gid])) {
                $perms           = $cached_forum_perms[$fid][$gid];
                $default_checked = true;
            } else {
                $perms           = $usergroup;
                $default_checked = true;
            }

            $perms_checked = [];
            foreach ($field_list as $fp => $fpt) {
                $perms_checked[$fp] = ($perms[$fp] ?? 0) == 1 ? 1 : 0;
            }

            $inherited_text = $default_checked ? 'Inherited' : 'Custom';
            $status_class   = $default_checked ? 'bg-info bg-opacity-10 text-info' : 'bg-warning bg-opacity-10 text-warning';
            $status_icon    = $default_checked ? 'fas fa-link' : 'fas fa-pen';

            $enabled_html  = '';
            $disabled_html = '';
            foreach ($field_list2 as $perm => $label) {
                $badge = '<span class="badge ' . ($perms_checked[$perm]
                    ? 'bg-success bg-opacity-10 text-success'
                    : 'bg-danger bg-opacity-10 text-danger') . ' me-2 mb-1 permission-badge" data-perm="' . $perm . '">'
                    . strip_tags($label) . '</span>';
                if ($perms_checked[$perm]) { $enabled_html  .= $badge; }
                else                       { $disabled_html .= $badge; }
            }

            $fields_val = implode(',', array_keys(array_filter($perms_checked)));

            $group_icon = !empty($usergroup['image'])
                ? $usergroup['image']
                : '<div class="icon-compact default-group" data-tooltip="' . $utitle . '"><i class="bi bi-people-fill" style="color:#6c757d;"></i></div>';

            echo "
                            <tr data-group-id=\"{$gid}\">
                                <td class=\"ps-4\">
                                    <div class=\"d-flex align-items-center\">
                                        <div class=\"group-icon me-3\">{$group_icon}</div>
                                        <div>
                                            <strong class=\"d-block\">{$utitle}</strong>
                                            <small class=\"text-muted\">ID: {$gid}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class=\"permission-fields\" id=\"permission-fields-{$gid}\">
                                        <div class=\"mb-2\">
                                            <small class=\"text-muted d-block mb-1\">Allowed:</small>
                                            <div class=\"enabled-permissions\" id=\"enabled-{$gid}\">"
                                            . ($enabled_html ?: '<span class="text-muted">No permissions</span>') . "
                                            </div>
                                        </div>
                                        <div>
                                            <small class=\"text-muted d-block mb-1\">Denied:</small>
                                            <div class=\"disabled-permissions\" id=\"disabled-{$gid}\">"
                                            . ($disabled_html ?: '<span class="text-muted">No restrictions</span>') . "
                                            </div>
                                        </div>
                                        <input type=\"hidden\" name=\"fields_{$gid}\" id=\"fields_{$gid}\" value=\"{$fields_val}\">
                                        <input type=\"hidden\" name=\"fields_inherit_{$gid}\" id=\"fields_inherit_{$gid}\" value=\"" . (int)$default_checked . "\">
                                        <input type=\"hidden\" name=\"fields_default_{$gid}\" id=\"fields_default_{$gid}\" value=\"{$fields_val}\">
                                    </div>
                                </td>
                                <td>
                                    <span class=\"badge {$status_class} px-3 py-2\">
                                        <i class=\"{$status_icon} me-1\"></i>{$inherited_text}
                                    </span>
                                </td>
                                <td class=\"text-end pe-4\">
                                    <div class=\"btn-group btn-group-sm\">
                                        <a href=\"index.php?act=management&action=permissions&gid={$gid}&fid={$fid}\"
                                           class=\"btn btn-outline-secondary btn-sm\"
                                           data-bs-toggle=\"tooltip\" title=\"Set Custom Permissions\"
                                           onclick=\"popupWindow(this.href,null,true);return false;\">
                                            <i class=\"fas fa-cog\"></i>
                                        </a>";

            if (!$default_checked) {
                echo "
                                        <a href=\"javascript:void(0);\"
                                           class=\"btn btn-outline-danger btn-sm ms-1 clear-permission-btn\"
                                           data-pid=\"{$perms['pid']}\"
                                           data-fid=\"{$fid}\"
                                           data-gid=\"{$gid}\"
                                           data-group-name=\"" . addslashes(htmlspecialchars($usergroup['title'])) . "\"
                                           data-post-key=\"{$mybb->post_code}\"
                                           data-bs-toggle=\"tooltip\" title=\"Clear Custom Permissions\">
                                            <i class=\"fas fa-trash\"></i>
                                        </a>";
            }

            echo '
                                    </div>
                                </td>
                            </tr>';

            $perm_ids[] = $gid;
        }

        echo '
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <div class="card border-0 bg-light-subtle">
                            <div class="card-body py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Drag permissions between lists to enable/disable
                                    </span>
                                    <div class="btn-group">
                                        <button type="submit" class="btn btn-warning px-4 py-2">
                                            <i class="fas fa-save me-2"></i>Save Permissions
                                        </button>
                                        <button type="reset" class="btn btn-outline-secondary px-4 py-2 ms-2">
                                            <i class="fas fa-undo me-2"></i>Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>';

        // ── Moderators tab ────────────────────────────────────
        $query = $db->sql_query("
            SELECT m.mid, m.id, m.isgroup, u.username, g.title
            FROM moderators m
            LEFT JOIN users u ON (m.isgroup='0' AND m.id=u.id)
            LEFT JOIN usergroups g ON (m.isgroup='1' AND m.id=g.gid)
            WHERE m.fid='{$fid}'
            ORDER BY m.isgroup DESC, u.username ASC, g.title ASC
        ");
        $current_moderators = [];
        while ($mod = $db->fetch_array($query)) { $current_moderators[] = $mod; }

        $query = $db->simple_select('usergroups', '*', '', ['order' => 'title']);
        $user_groups = [];
        while ($group = $db->fetch_array($query)) { $user_groups[$group['gid']] = $group; }

        echo '
            <div class="tab-pane fade" id="moderators" role="tabpanel">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card border-0 mb-4">
                            <div class="card-header bg-white border-bottom py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold">
                                        <i class="fas fa-user-shield me-2 text-primary"></i>Current Moderators
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

        if (empty($current_moderators)) {
            echo '
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted">
                                                    <i class="fas fa-user-slash fa-2x mb-2"></i><br>
                                                    No moderators assigned yet
                                                </td>
                                            </tr>';
        } else {
            foreach ($current_moderators as $moderator) {
                $type_badge = $moderator['isgroup']
                    ? '<span class="badge bg-info bg-opacity-10 text-info px-2 py-1">Group</span>'
                    : '<span class="badge bg-success bg-opacity-10 text-success px-2 py-1">User</span>';
                $mod_name = $moderator['isgroup']
                    ? htmlspecialchars_uni($moderator['title'] ?? '')
                    : htmlspecialchars_uni($moderator['username'] ?? '');
                $mod_icon = $moderator['isgroup'] ? 'fas fa-users' : 'fas fa-user';

                echo "
                                            <tr>
                                                <td class=\"ps-4\">
                                                    <div class=\"d-flex align-items-center\">
                                                        <div class=\"moderator-icon bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3\">
                                                            <i class=\"{$mod_icon}\"></i>
                                                        </div>
                                                        <div>
                                                            <strong class=\"d-block\">{$mod_name}</strong>
                                                            <small class=\"text-muted\">ID: {$moderator['id']}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class=\"text-center\">{$type_badge}</td>
                                                <td class=\"text-end pe-4\">
                                                    <div class=\"btn-group btn-group-sm\">
                                                        <a href=\"index.php?act=management&action=editmod&mid={$moderator['mid']}\"
                                                           class=\"btn btn-outline-primary btn-sm\"
                                                           data-bs-toggle=\"tooltip\" title=\"Edit Moderator\">
                                                            <i class=\"fas fa-edit\"></i>
                                                        </a>
                                                        <a href=\"#\"
                                                           class=\"btn btn-outline-danger btn-sm ms-1 delete-moderator-btn\"
                                                           data-mid=\"{$moderator['id']}\"
                                                           data-fid=\"{$fid}\"
                                                           data-isgroup=\"{$moderator['isgroup']}\"
                                                           data-post-key=\"{$mybb->post_code}\"
                                                           data-bs-toggle=\"tooltip\" title=\"Remove Moderator\">
                                                            <i class=\"fas fa-trash\"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>";
            }
        }

        echo '
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="sticky-top" style="top:20px">
                            <div class="card border-0 mb-4">
                                <div class="card-header bg-success text-white py-3">
                                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Add User Group</h6>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="index.php?act=management">
                                        <input type="hidden" name="fid" value="' . $fid . '">
                                        <input type="hidden" name="add" value="moderators">
                                        ' . generate_post_check() . '
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Select User Group</label>
                                            <select name="usergroup" class="form-select" required>
                                                <option value="">-- Select Group --</option>';

        foreach ($user_groups as $group) {
            echo '<option value="' . $group['gid'] . '">'
                . htmlspecialchars_uni($group['title']) . ' (ID: ' . $group['gid'] . ')</option>';
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

                            <div class="card border-0">
                                <div class="card-header bg-info text-white py-3">
                                    <h6 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add User</h6>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="index.php?act=management">
                                        <input type="hidden" name="fid" value="' . $fid . '">
                                        <input type="hidden" name="add" value="moderators">
                                        ' . generate_post_check() . '
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Username</label>
                                            <input type="text" name="username" class="form-control"
                                                   placeholder="Enter username" required
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

        fm_clear_permission_modal();
        fm_delete_mod_modal();
    }

    echo '
        </div>
        </div>
    </div>
</div>
</div>';

    $plugins->run_hooks('admin_forum_management_start_graph');
    echo '<script src="scripts/deleteForum.js"></script>';
	stdfoot();
	exit;
}














/**
 * @param DefaultFormContainer $form_container
 * @param DefaultForm $form
 * @param int $pid
 * @param int $depth
 */
 
 





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






echo '<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-bs-toggle=popover]").forEach(el =>
        new bootstrap.Popover(el, { container: "body", trigger: "hover focus", html: true })
    );
    document.querySelectorAll(".forum-row").forEach((row, i) =>
        row.style.setProperty("--row-index", i)
    );
});
</script>';







/**
 * @param int $gid
 * @param int $fid
 *
 * @return string
 */
function retrieve_single_permissions_row(int $gid, int $fid): string
{
    global $cache, $db, $mybb;

    $usergroup  = $db->fetch_array($db->simple_select('usergroups', '*', "gid='{$gid}'"));
    $forum_data = $db->fetch_array($db->simple_select('forums', '*', "fid='{$fid}'"));

    $existing_permissions = [];
    $q = $db->simple_select('forumpermissions', '*', "fid='{$fid}'");
    while ($row = $db->fetch_array($q)) {
        $existing_permissions[$row['gid']] = $row;
    }

    $cached_forum_perms = $cache->read('forumpermissions');

    $field_list2 = [
        'canview'       => 'View',
        'canpostthreads'=> 'Post Threads',
        'canpostreplys' => 'Post Replies',
        'canpostpolls'  => 'Post Polls',
    ];

    // Определяем права
    if (!empty($existing_permissions[$gid])) {
        $perms           = $existing_permissions[$gid];
        $default_checked = false;
    } elseif (!empty($cached_forum_perms[$forum_data['fid']][$gid])) {
        $perms           = $cached_forum_perms[$forum_data['fid']][$gid];
        $default_checked = true;
    } elseif (!empty($cached_forum_perms[$forum_data['pid']][$gid])) {
        $perms           = $cached_forum_perms[$forum_data['pid']][$gid];
        $default_checked = true;
    } else {
        $perms           = $usergroup;
        $default_checked = true;
    }

    $perms_checked = [];
    foreach (array_keys($field_list2) as $fp) {
        $perms_checked[$fp] = ($perms[$fp] ?? 0) == 1 ? 1 : 0;
    }

    $title          = htmlspecialchars_uni($usergroup['title']);
    $inherited_text = $default_checked ? 'Inherited' : 'Custom';
    $status_class   = $default_checked ? 'bg-info bg-opacity-10 text-info' : 'bg-warning bg-opacity-10 text-warning';
    $fields_val     = implode(',', array_keys(array_filter($perms_checked)));

    $group_icon = !empty($usergroup['image'])
        ? $usergroup['image']
        : '<div class="icon-compact default-group" data-tooltip="' . $title . '"><i class="bi bi-people-fill" style="color:#6c757d;"></i></div>';

    $enabled_html  = '';
    $disabled_html = '';
    foreach ($field_list2 as $perm => $label) {
        $badge = '<span class="badge '
            . ($perms_checked[$perm] ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger')
            . ' me-2 mb-1 permission-badge" data-perm="' . $perm . '">' . $label . '</span>';
        if ($perms_checked[$perm]) $enabled_html  .= $badge;
        else                       $disabled_html .= $badge;
    }

    $hidden_fields = '
        <input type="hidden" name="fields_' . $gid . '" id="fields_' . $gid . '" value="' . $fields_val . '">
        <input type="hidden" name="fields_inherit_' . $gid . '" id="fields_inherit_' . $gid . '" value="' . (int)$default_checked . '">
        <input type="hidden" name="fields_default_' . $gid . '" id="fields_default_' . $gid . '" value="' . $fields_val . '">';

    $actions = $default_checked
        ? '<a href="index.php?act=management&action=permissions&gid=' . $gid . '&fid=' . $fid . '"
               class="btn btn-outline-secondary btn-sm"
               onclick="popupWindow(this.href,null,true);return false;">
               <i class="fas fa-cog"></i>
           </a>'
        : '<a href="index.php?act=management&action=permissions&pid=' . $perms['pid'] . '"
               class="btn btn-outline-primary btn-sm"
               onclick="popupWindow(this.href,null,true);return false;">
               <i class="fas fa-edit"></i>
           </a>
           <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm ms-1 clear-permission-btn"
               data-pid="' . $perms['pid'] . '" data-fid="' . $fid . '" data-gid="' . $gid . '"
               data-group-name="' . addslashes($title) . '" data-post-key="' . $mybb->post_code . '">
               <i class="fas fa-trash"></i>
           </a>';

    return '
        <td class="ps-4">
            <div class="d-flex align-items-center">
                <div class="group-icon me-3">' . $group_icon . '</div>
                <div>
                    <strong class="d-block">' . $title . '</strong>
                    <small class="text-muted">ID: ' . $gid . '</small>
                </div>
            </div>
        </td>
        <td>
            <div class="permission-fields" id="permission-fields-' . $gid . '">
                <div class="mb-2">
                    <small class="text-muted d-block mb-1">Allowed:</small>
                    <div class="enabled-permissions" id="enabled-' . $gid . '">'
                    . ($enabled_html ?: '<span class="text-muted">No permissions</span>') . '
                    </div>
                </div>
                <div>
                    <small class="text-muted d-block mb-1">Denied:</small>
                    <div class="disabled-permissions" id="disabled-' . $gid . '">'
                    . ($disabled_html ?: '<span class="text-muted">No restrictions</span>') . '
                    </div>
                </div>
                ' . $hidden_fields . '
            </div>
        </td>
        <td>
            <span class="badge ' . $status_class . ' px-3 py-2">' . $inherited_text . '</span>
        </td>
        <td class="text-end pe-4">
            <div class="btn-group btn-group-sm">' . $actions . '</div>
        </td>';
}






?>
<script>
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
</script>

<?php




