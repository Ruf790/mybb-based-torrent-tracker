<?php
/**
 * MyBB 1.8 - Attachment Types Manager
 * Modernized for PHP 8.5 with enhanced security and structure
 */

declare(strict_types=1);

define('IN_MYBB', 1);
define('IN_ADMINCP', 1);

// Добавляем глобальные переменные, которые используются в функциях
global $mybb, $db, $plugins, $cache, $lang;

/**
 * Generate JavaScript for selection toggles
 */
function print_selection_javascript(): void
{
    static $already_printed = false;

    if ($already_printed) {
        return;
    }

    $already_printed = true;

    echo <<<HTML
<script type="text/javascript">
// Функция для переключения видимости
function checkAction(id) {
    const checkedRadio = document.querySelector('.' + id + '_forums_groups_check:checked');
    if (!checkedRadio) return;
    
    const checkedValue = checkedRadio.value;
    
    // Скрыть все блоки
    document.querySelectorAll('.' + id + '_forums_groups').forEach(el => {
        el.style.display = 'none';
    });
    
    // Показать выбранный блок
    const targetEl = document.getElementById(id + '_forums_groups_' + checkedValue);
    if (targetEl) {
        targetEl.style.display = 'block';
    }
}

// Вешаем обработчики событий на все radio buttons
document.addEventListener('DOMContentLoaded', function() {
    // Обработчик для групп
    document.querySelectorAll('.groups_forums_groups_check').forEach(radio => {
        radio.addEventListener('change', function() {
            checkAction('groups');
        });
    });
    
    // Обработчик для форумов
    document.querySelectorAll('.forums_forums_groups_check').forEach(radio => {
        radio.addEventListener('change', function() {
            checkAction('forums');
        });
    });
    
    // Инициализация при загрузке
    checkAction('groups');
    checkAction('forums');
});
</script>
HTML;
}

/**
 * Generate yes/no radio buttons with modern PHP features
 */
function generate_yes_no_radio(
    string $name, 
    $value = "1", 
    bool $int = true, 
    array $yes_options = [], 
    array $no_options = []
): string {
    global $lang;
    
    // Determine checked status using strict comparison
    $is_no = $value === "no" || $value === '0' || $value === 0;
    $yes_checked = $is_no ? 0 : 1;
    $no_checked = $is_no ? 1 : 0;
    
    // Determine values
    $yes_value = $int ? 1 : "yes";
    $no_value = $int ? 0 : "no";
    
    // Set default classes
    $yes_options['class'] = ($yes_options['class'] ?? '') . ' radio_yes';
    $no_options['class'] = ($no_options['class'] ?? '') . ' radio_no';
    
    // Set checked status
    $yes_options['checked'] = $yes_checked;
    $no_options['checked'] = $no_checked;
    
    $yes = generate_radio_button($name, $yes_value, 'yes', $yes_options);
    $no = generate_radio_button($name, $no_value, 'no', $no_options);
    
    return "{$yes} {$no}";
}

/**
 * Generate group select with type safety
 */
function generate_group_select(string $name, $selected = [], array $options = []): string
{
    global $cache;
    
    $multiple = $options['multiple'] ?? false;
    $class = $options['class'] ?? '';
    $id = $options['id'] ?? '';
    $size = $options['size'] ?? '';
    
    $select_attrs = [
        'name' => $name,
        'class' => $class,
        'id' => $id,
        'size' => $size,
        'multiple' => $multiple ? 'multiple' : null
    ];
    
    $select = '<select' . build_attributes($select_attrs) . ">\n";
    
    $groups_cache = $cache->read('usergroups') ?: [];
    $selected = is_array($selected) ? $selected : [$selected];
    
    foreach ($groups_cache as $group) {
        $is_selected = in_array($group['gid'], $selected, true);
        $selected_attr = $is_selected ? ' selected="selected"' : '';
        
        $group_title = htmlspecialchars_uni($group['title'] ?? '');
        $select .= "<option value=\"{$group['gid']}\"{$selected_attr}>{$group_title}</option>\n";
    }
    
    $select .= "</select>";
    return $select;
}

/**
 * Helper function to build HTML attributes
 */
function build_attributes(array $attributes): string
{
    $attrs = [];
    foreach ($attributes as $key => $value) {
        if ($value !== null && $value !== '') {
            $attrs[] = $key . '="' . htmlspecialchars((string)$value, ENT_QUOTES) . '"';
        }
    }
    return $attrs ? ' ' . implode(' ', $attrs) : '';
}

/**
 * Process and display attachment icon with modern parsing
 */
function process_attachment_icon(array &$attachment_type): void
{
    $icon_html = trim($attachment_type['icon'] ?? '');
    
    // Handle HTML Font Awesome icons
    if (!empty($icon_html) && $icon_html !== "pic/attachtypes/" && str_starts_with($icon_html, '<')) {
        $name = htmlspecialchars_uni($attachment_type['name'] ?? '');
        
        // Add title if missing
        if (!str_contains($icon_html, 'title=')) {
            $pos = strpos($icon_html, '>');
            if ($pos !== false) {
                $icon_html = substr($icon_html, 0, $pos) . ' title="' . $name . '"' . substr($icon_html, $pos);
            }
        }
        
        // Adjust font size for admin panel
        if (!str_contains($icon_html, 'style=')) {
            $pos = strpos($icon_html, '>');
            if ($pos !== false) {
                $icon_html = substr($icon_html, 0, $pos) . ' style="font-size: 18px;"' . substr($icon_html, $pos);
            }
        } else {
            $icon_html = str_replace('style="', 'style="font-size: 18px; ', $icon_html);
        }
        
        $attachment_type['icon'] = $icon_html;
    } 
    // Handle empty or default icons
    elseif (empty($icon_html) || $icon_html === "pic/attachtypes/") {
        $name = htmlspecialchars_uni($attachment_type['name'] ?? '');
        $attachment_type['icon'] = '<i class="fas fa-file" title="' . $name . '" style="font-size: 18px; color: #ccc;"></i>';
    }
    // Handle legacy image paths
    else {
        $processed_icon = htmlspecialchars_uni(str_replace("{theme}", "images", $icon_html));
        
        $image = my_validate_url($processed_icon, true) 
            ? $processed_icon 
            : "../" . $processed_icon;
        
        if (empty($processed_icon) || $processed_icon === "pic/attachtypes/") {
            $attachment_type['icon'] = '<i class="fas fa-file" style="font-size: 18px; color: #ccc;"></i>';
        } else {
            $name = htmlspecialchars_uni($attachment_type['name'] ?? '');
            $attachment_type['icon'] = sprintf(
                '<img src="%s" title="%s" alt="" style="height: 18px; width: 18px;" />',
                $image,
                $name
            );
        }
    }
}

/**
 * Generate selection form HTML for groups/forums
 */
function generate_selection_html(string $field, string $selected_value, array $selected_ids = []): string
{
    $checked = [
        'all' => $selected_value == -1 ? 'checked="checked"' : '',
        'custom' => $selected_value != '' && $selected_value != -1 ? 'checked="checked"' : '',
        'none' => $selected_value == '' ? 'checked="checked"' : ''
    ];
    
    $field_label = ucfirst($field);
    
    return <<<HTML
    <dl style="margin-top: 0; margin-bottom: 0; width: 100%">
        <dt><label style="display: block;">
            <input type="radio" name="{$field}" value="all" {$checked['all']} 
                   class="{$field}_forums_groups_check" onclick="checkAction('{$field}');" 
                   style="vertical-align: middle;" /> 
            <strong>All {$field_label}</strong>
        </label></dt>
        <dt><label style="display: block;">
            <input type="radio" name="{$field}" value="custom" {$checked['custom']} 
                   class="{$field}_forums_groups_check" onclick="checkAction('{$field}');" 
                   style="vertical-align: middle;" /> 
            <strong>Select {$field}</strong>
        </label></dt>
        <dd style="margin-top: 4px;" id="{$field}_forums_groups_custom" class="{$field}_forums_groups">
            <table cellpadding="4">
                <tr>
                    <td valign="top"><small>{$field_label}:</small></td>
                    <td>
    HTML . generate_group_select("select[{$field}][]", $selected_ids, [
        'id' => $field, 
        'multiple' => true, 
        'size' => 5
    ]) . <<<HTML
                    </td>
                </tr>
            </table>
        </dd>
        <dt><label style="display: block;">
            <input type="radio" name="{$field}" value="none" {$checked['none']} 
                   class="{$field}_forums_groups_check" onclick="checkAction('{$field}');" 
                   style="vertical-align: middle;" /> 
            <strong>None</strong>
        </label></dt>
    </dl>
    <script type="text/javascript">checkAction('{$field}');</script>
    HTML;
}

/**
 * Render add form
 */
function render_add_form(array $errors = []): void
{
    global $mybb, $lang;
    
    stdhead('Attachment Types - Add New Attachment Type');
    output_admin_resources();
    
    $sub_tabs = [
        'attachment_types' => [
            'title' => 'Attachment Types',
            'link' => "index.php?act=attachment_types"
        ],
        'add_attachment_type' => [
            'title' => 'Add New Attachment Type',
            'link' => "index.php?act=attachment_types&action=add",
            'description' => 'Adding a new attachment type will allow members to attach files of this type to their posts.'
        ]
    ];
    
    output_nav_tabs($sub_tabs, 'add_attachment_type');
    
    // PHP settings for file size limits
    $upload_max_filesize = @ini_get('upload_max_filesize');
    $post_max_size = @ini_get('post_max_size');
    $limit_string = '';
    
    if ($upload_max_filesize || $post_max_size) {
        $limit_string = '<br><br>Please ensure the maximum file size is below the smallest of the following PHP limits:';
        if ($upload_max_filesize) {
            $limit_string .= '<br>Upload Max File Size: ' . htmlspecialchars($upload_max_filesize);
        }
        if ($post_max_size) {
            $limit_string .= '<br>Max Post Size: ' . htmlspecialchars($post_max_size);
        }
    }
    
    // Generate form fields
    $attach_name = generate_text_box('name', $mybb->get_input('name'), ['id' => 'name']);
    $file_exten = generate_text_box('extension', $mybb->get_input('extension'), ['id' => 'extension']);
    $mime_type = generate_text_box('mimetype', $mybb->get_input('mimetype'), ['id' => 'mimetype']);
    $max_size = generate_numeric_field('maxsize', $mybb->get_input('maxsize', 1024), ['id' => 'maxsize', 'min' => 0]);
    
    $icon_description = <<<HTML
    <div class="description">
        Enter HTML code for Font Awesome icon with color. Examples:<br>
        <code>&lt;i class="fas fa-file-pdf" style="color: #e74c3c;"&gt;&lt;/i&gt;</code> - PDF file<br>
        <code>&lt;i class="fas fa-file-image" style="color: #1abc9c;"&gt;&lt;/i&gt;</code> - WEBP image<br>
        <code>&lt;i class="fas fa-file-archive" style="color: #e67e22;"&gt;&lt;/i&gt;</code> - Archive<br>
        <code>&lt;i class="fas fa-file-word" style="color: #2b579a;"&gt;&lt;/i&gt;</code> - Word document<br>
        <code>&lt;i class="fas fa-file-excel" style="color: #217346;"&gt;&lt;/i&gt;</code> - Excel document<br>
        <code>&lt;i class="fas fa-file-powerpoint" style="color: #d24726;"&gt;&lt;/i&gt;</code> - PowerPoint document
    </div>
    HTML;
    
   $attach_icon = $icon_description . '<div class="form_row">' . 
               generate_text_box('icon', $mybb->get_input('icon', MyBB::INPUT_STRING) ?: 'pic/attachtypes/', [
                   'id' => 'icon', 
                   'style' => 'width: 400px;'
               ]) . '</div>';
    $enabled = generate_yes_no_radio('enabled', $mybb->get_input('enabled', 1));
    $force_download = generate_yes_no_radio('forcedownload', $mybb->get_input('forcedownload', 0));
    $avatar_file = generate_yes_no_radio('avatarfile', $mybb->get_input('avatarfile', 0));
    
    // Groups selection
    $selected_groups = $mybb->get_input('groups') != '' && $mybb->get_input('groups') != -1 
        ? explode(',', $mybb->get_input('groups')) 
        : [];
    $groups_select = generate_selection_html('groups', $mybb->get_input('groups', MyBB::INPUT_STRING), $selected_groups);
    
    // Forums selection
    $selected_forums = $mybb->get_input('forums') != '' && $mybb->get_input('forums') != -1 
        ? explode(',', $mybb->get_input('forums')) 
        : [];
    
    // Подготавливаем переменные для форумов
   $forum_input = $mybb->get_input('forums', MyBB::INPUT_STRING);
$forum_checked = [
    'all'    => $forum_input == -1 ? 'checked="checked"' : '',
    'custom' => $forum_input != '' && $forum_input != -1 ? 'checked="checked"' : '',
    'none'   => $forum_input == '' ? 'checked="checked"' : ''
];
    
    // Определяем переменную $selected_values для generate_forum_select()
    $selected_values = $selected_forums;
    
    // Сюда вставляем код из вашего сообщения
    $forums_select = "
    <dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
        <dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"all\" {$forum_checked['all']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>All Forums</strong></label></dt>
        <dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"custom\" {$forum_checked['custom']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>Select forums</strong></label></dt>
        <dd style=\"margin-top: 4px;\" id=\"forums_forums_groups_custom\" class=\"forums_forums_groups\">
            <table cellpadding=\"4\">
                <tr>
                    <td valign=\"top\"><small>Forums:</small></td>
                    <td>".generate_forum_select('select[forums][]', $selected_values, array('id' => 'forums', 'multiple' => true, 'size' => 5))."</td>
                </tr>
            </table>
        </dd>
        <dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"none\" {$forum_checked['none']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>None</strong></label></dt>
    </dl>
    <script type=\"text/javascript\">
        checkAction('forums');
    </script>";
    
    // Output form
    echo <<<HTML
    <form action="index.php?act=attachment_types&action=add" method="post" id="add">
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
        
        <div class="container mt-3">
            <div class="card">
                <div class="card-header rounded-bottom text-19 fw-bold">
                    Add New Attachment Type
                </div>
                <div class="card-body">
    HTML;
    
    if (!empty($errors)) {
        output_inline_error($errors);
    }
    
    echo <<<HTML
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <div class="description">Enter the name of the attachment type</div>
                        {$attach_name}
                    </div>
                    
                    <div class="mb-3">
                        <label for="extension" class="form-label">File Extension *</label>
                        <div class="description">Enter the file extension (without the period)</div>
                        {$file_exten}
                    </div>
                    
                    <div class="mb-3">
                        <label for="mimetype" class="form-label">MIME Type *</label>
                        <div class="description">Enter the MIME type for this file type</div>
                        {$mime_type}
                    </div>
                    
                    <div class="mb-3">
                        <label for="maxsize" class="form-label">Maximum File Size (Kilobytes)</label>
                        <div class="description">Maximum size in KB (1 MB = 1024 KB){$limit_string}</div>
                        {$max_size}
                    </div>
                    
                    <div class="mb-3">
                        <label for="icon" class="form-label">Attachment Icon</label>
                        {$attach_icon}
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Enabled?</label>
                        <div class="form_row">{$enabled}</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Force Download</label>
                        <div class="description">Always force download instead of displaying</div>
                        <div class="form_row">{$force_download}</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Available to Groups</label>
                        {$groups_select}
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Available in Forums</label>
                        {$forums_select}
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Avatar File</label>
                        <div class="description">Allow this type for avatars?</div>
                        <div class="form_row">{$avatar_file}</div>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <input type="submit" value="Save Attachment Type" class="btn btn-primary">
                </div>
            </div>
        </div>
    </form>
    HTML;
    
    stdfoot();
}

/**
 * Render edit form
 */
function render_edit_form(array $attachment_type, array $errors = []): void
{
    global $mybb, $lang;
    
    stdhead('Attachment Types - Edit Attachment Type');
    output_admin_resources();
    
    $sub_tabs = [
        'edit_attachment_type' => [
            'title' => 'Edit Attachment Type',
            'link' => "index.php?act=attachment_types&action=edit&atid={$attachment_type['atid']}",
            'description' => 'Edit attachment type settings'
        ]
    ];
    
    output_nav_tabs($sub_tabs, 'edit_attachment_type');
    
    // PHP settings for file size limits
    $upload_max_filesize = @ini_get('upload_max_filesize');
    $post_max_size = @ini_get('post_max_size');
    $limit_string = '';
    
    if ($upload_max_filesize || $post_max_size) {
        $limit_string = '<br><br>PHP limits:';
        if ($upload_max_filesize) {
            $limit_string .= '<br>Upload Max: ' . htmlspecialchars($upload_max_filesize);
        }
        if ($post_max_size) {
            $limit_string .= '<br>Post Max: ' . htmlspecialchars($post_max_size);
        }
    }
    
    // Generate form fields
    $name_field = generate_text_box('name', $attachment_type['name'] ?? '', ['id' => 'name']);
    $extension_field = generate_text_box('extension', $attachment_type['extension'] ?? '', ['id' => 'extension']);
    $mime_field = generate_text_box('mimetype', $attachment_type['mimetype'] ?? '', ['id' => 'mimetype']);
    $maxsize_field = generate_numeric_field('maxsize', $attachment_type['maxsize'] ?? 1024, [
        'id' => 'maxsize', 
        'min' => 0
    ]);
    
    $icon_description = 
    '<div class="description">
        HTML Font Awesome icon code with color<br>
        Example: &lt;i class="fas fa-file-pdf" style="color: #e74c3c;"&gt;&lt;/i&gt;
    </div>'
    ;
    
    $icon_field = $icon_description . '<div class="form_row">' . 
                  generate_text_box('icon', $attachment_type['icon'] ?? 'pic/attachtypes/', [
                      'id' => 'icon', 
                      'style' => 'width: 400px;'
                  ]) . '</div>';
    
    $enabled_field = generate_yes_no_radio('enabled', $attachment_type['enabled'] ?? 1);
    $forcedownload_field = generate_yes_no_radio('forcedownload', $attachment_type['forcedownload'] ?? 0);
    $avatarfile_field = generate_yes_no_radio('avatarfile', $attachment_type['avatarfile'] ?? 0);
    
    // Groups selection
    $selected_groups = ($attachment_type['groups'] ?? '') != '' && ($attachment_type['groups'] ?? '') != -1 
        ? explode(',', $attachment_type['groups'] ?? '') 
        : [];
    $groups_select = generate_selection_html('groups', $attachment_type['groups'] ?? '', $selected_groups);
    
    // Forums selection
    $selected_forums = ($attachment_type['forums'] ?? '') != '' && ($attachment_type['forums'] ?? '') != -1 
        ? explode(',', $attachment_type['forums'] ?? '') 
        : [];
    
    // Подготавливаем переменные для форумов
    $forum_checked = [
        'all' => ($attachment_type['forums'] ?? '') == -1 ? 'checked="checked"' : '',
        'custom' => ($attachment_type['forums'] ?? '') != '' && ($attachment_type['forums'] ?? '') != -1 ? 'checked="checked"' : '',
        'none' => ($attachment_type['forums'] ?? '') == '' ? 'checked="checked"' : ''
    ];
    
    // Определяем переменную $selected_values для generate_forum_select()
    $selected_values = $selected_forums;
    
    // Сюда вставляем код из вашего сообщения
    $forums_select = "
    <dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
        <dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"all\" {$forum_checked['all']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>All Forums</strong></label></dt>
        <dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"custom\" {$forum_checked['custom']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>Select forums</strong></label></dt>
        <dd style=\"margin-top: 4px;\" id=\"forums_forums_groups_custom\" class=\"forums_forums_groups\">
            <table cellpadding=\"4\">
                <tr>
                    <td valign=\"top\"><small>Forums:</small></td>
                    <td>".generate_forum_select('select[forums][]', $selected_values, array('id' => 'forums', 'multiple' => true, 'size' => 5))."</td>
                </tr>
            </table>
        </dd>
        <dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"none\" {$forum_checked['none']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>None</strong></label></dt>
    </dl>
    <script type=\"text/javascript\">
        checkAction('forums');
    </script>";
    
    // Output form
    echo <<<HTML
    <form action="index.php?act=attachment_types&action=edit&atid={$attachment_type['atid']}" method="post" id="edit">
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
        
        <div class="container mt-3">
            <div class="card">
                <div class="card-header rounded-bottom text-19 fw-bold">
                    Edit Attachment Type
                </div>
                <div class="card-body">
    HTML;
    
    if (!empty($errors)) {
        output_inline_error($errors);
    }
    
    echo <<<HTML
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <div class="description">Attachment type name</div>
                        {$name_field}
                    </div>
                    
                    <div class="mb-3">
                        <label for="extension" class="form-label">File Extension *</label>
                        <div class="description">File extension (without period)</div>
                        {$extension_field}
                    </div>
                    
                    <div class="mb-3">
                        <label for="mimetype" class="form-label">MIME Type *</label>
                        <div class="description">Server MIME type</div>
                        {$mime_field}
                    </div>
                    
                    <div class="mb-3">
                        <label for="maxsize" class="form-label">Maximum Size (KB)</label>
                        <div class="description">Max file size in kilobytes{$limit_string}</div>
                        {$maxsize_field}
                    </div>
                    
                    <div class="mb-3">
                        <label for="icon" class="form-label">Attachment Icon</label>
                        {$icon_field}
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Enabled?</label>
                        <div class="form_row">{$enabled_field}</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Force Download</label>
                        <div class="description">Force file download</div>
                        <div class="form_row">{$forcedownload_field}</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Available to Groups</label>
                        {$groups_select}
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Available in Forums</label>
                        {$forums_select}
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Avatar File</label>
                        <div class="description">Allow for avatars?</div>
                        <div class="form_row">{$avatarfile_field}</div>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <input type="submit" value="Save Attachment Type" class="btn btn-primary">
                </div>
            </div>
        </div>
    </form>
    HTML;
    
    stdfoot();
}

// Run plugin hooks
$plugins->run_hooks("admin_config_attachment_types_begin");

// Main action router
switch ($mybb->input['action'] ?? '') {
    case 'add':
        handle_add_action();
        break;
    case 'edit':
        handle_edit_action();
        break;
    case 'delete':
        handle_delete_action();
        break;
    case 'toggle_status':
        handle_toggle_status_action();
        break;
    default:
        handle_list_action();
        break;
}

// ... остальные функции остаются без изменений ...

/**
 * Handle add new attachment type
 */
function handle_add_action(): void
{
    global $mybb, $db, $plugins, $cache, $lang;
    
    $plugins->run_hooks("admin_config_attachment_types_add");
    
    if ($mybb->request_method === "post") {
        $errors = validate_attachment_type_input($mybb->input);
        
        if (empty($errors)) {
            $attachment_type_data = prepare_attachment_type_data($mybb->input);
            $atid = $db->insert_query("attachtypes", $attachment_type_data);
            
            $plugins->run_hooks("admin_config_attachment_types_add_commit");
            $cache->update_attachtypes();
            
            flash_message('success_attachment_type_created', 'success');
            admin_redirect("index.php?act=attachment_types");
        }
    }
    
    render_add_form($errors ?? []);
}

/**
 * Handle edit attachment type
 */
function handle_edit_action(): void
{
    global $mybb, $db, $plugins, $cache;
    
    $atid = $mybb->get_input('atid', MyBB::INPUT_INT);
    $attachment_type = $db->fetch_array(
        $db->simple_select("attachtypes", "*", "atid='{$atid}'")
    );
    
    if (!$attachment_type['atid']) {
        flash_message('error_invalid_attachment_type', 'error');
        admin_redirect("index.php?act=attachment_types");
    }
    
    $plugins->run_hooks("admin_config_attachment_types_edit");
    
    if ($mybb->request_method === "post") {
        $errors = validate_attachment_type_input($mybb->input);
        
        if (empty($errors)) {
            $updated_data = prepare_attachment_type_data($mybb->input);
            $db->update_query("attachtypes", $updated_data, "atid='{$atid}'");
            
            $plugins->run_hooks("admin_config_attachment_types_edit_commit");
            $cache->update_attachtypes();
            
            flash_message('success_attachment_type_updated', 'success');
            admin_redirect("index.php?act=attachment_types");
        }
    } else {
        $mybb->input = array_merge($mybb->input, $attachment_type);
    }
    
    render_edit_form($attachment_type, $errors ?? []);
}

/**
 * Handle delete action
 */
function handle_delete_action(): void
{
    global $mybb, $db, $plugins, $cache, $lang;
    
    $atid = $mybb->get_input('atid', MyBB::INPUT_INT);
    $attachment_type = $db->fetch_array(
        $db->simple_select("attachtypes", "*", "atid='{$atid}'")
    );
    
    if (!$attachment_type['atid']) {
        flash_message($lang->error_invalid_attachment_type, 'error');
        admin_redirect("index.php?module=config-attachment_types");
    }
    
    $plugins->run_hooks("admin_config_attachment_types_delete");
    
    if ($mybb->request_method === "post") {
        $db->delete_query("attachtypes", "atid='{$atid}'");
        
        $plugins->run_hooks("admin_config_attachment_types_delete_commit");
        $cache->update_attachtypes();
        
        flash_message($lang->success_attachment_type_deleted, 'success');
        admin_redirect("index.php?module=config-attachment_types");
    } else {
        $page->output_confirm_action(
            "index.php?module=config-attachment_types&amp;action=delete&amp;atid={$atid}",
            'Are you sure you wish to delete this attachment type?'
        );
    }
}

/**
 * Handle toggle status action
 */
function handle_toggle_status_action(): void
{
    global $mybb, $db, $plugins, $cache, $lang;
    
    $atid = $mybb->get_input('atid', MyBB::INPUT_INT);
    $attachment_type = $db->fetch_array(
        $db->simple_select('attachtypes', '*', "atid='{$atid}'")
    );
    
    if (!$attachment_type['atid']) {
        flash_message($lang->error_invalid_mycode, 'error');
        admin_redirect('index.php?act=attachment_types');
    }
    
    $plugins->run_hooks('admin_config_attachment_types_toggle_status');
    
    $new_status = $attachment_type['enabled'] == 1 ? 0 : 1;
    $db->update_query('attachtypes', ['enabled' => $new_status], "atid='{$atid}'");
    
    $plugins->run_hooks('admin_config_attachment_types_toggle_status_commit');
    $cache->update_attachtypes();
    
    $phrase = $new_status ? 'success_activated_attachment_type' : 'success_deactivated_attachment_type';
    flash_message($phrase, 'success');
    admin_redirect('index.php?act=attachment_types');
}

/**
 * Handle list action (default)
 */
function handle_list_action(): void
{
    global $mybb, $db, $plugins, $lang;
    
    stdhead('Attachment Types');
    output_admin_resources();
    
    $sub_tabs = [
        'attachment_types' => [
            'title' => 'Attachment Types',
            'link' => "index.php?act=attachment_types",
            'description' => 'Manage attachment types for file uploads'
        ],
        'add_attachment_type' => [
            'title' => 'Add New Attachment Type',
            'link' => "index.php?act=attachment_types&amp;action=add"
        ]
    ];
    
    $plugins->run_hooks("admin_config_attachment_types_start");
    output_nav_tabs($sub_tabs, 'attachment_types');
    
    // Pagination
    $per_page = 20;
    $total_rows = (int) $db->fetch_field(
        $db->simple_select("attachtypes", "COUNT(atid) AS attachtypes"),
        "attachtypes"
    );
    
    $page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start = ($page - 1) * $per_page;
    $pages = ceil($total_rows / $per_page);
    
    // Render table
    echo render_attachment_types_table($start, $per_page);
    
    // Render pagination
    if ($pages > 1) {
        echo '<div class="container mt-3">';
        echo draw_admin_pagination($page, $per_page, $total_rows, "index.php?act=attachment_types&amp;page={page}");
        echo '</div>';
    }
    
    stdfoot();
}

/**
 * Validate attachment type input data
 */
function validate_attachment_type_input(array $input): array
{
    $errors = [];
    
    if (!trim($input['mimetype'] ?? '') && !trim($input['extension'] ?? '')) {
        $errors[] = 'You did not enter a MIME type for this attachment type';
    }
    
    if (!trim($input['extension'] ?? '') && !trim($input['mimetype'] ?? '')) {
        $errors[] = 'You did not enter a file extension for this attachment type';
    }
    
    return $errors;
}

/**
 * Prepare attachment type data for database
 */
function prepare_attachment_type_data(array $input): array
{
    global $db, $mybb;
    
    // Clean inputs
    $mimetype = $input['mimetype'] === "pic/attachtypes/" ? '' : $input['mimetype'];
    $extension = str_starts_with($input['extension'] ?? '', '.') 
        ? substr($input['extension'], 1) 
        : $input['extension'];
    
    // Process groups and forums
    $processed = [];
    foreach (['groups', 'forums'] as $key) {
        $processed[$key] = process_selection_field($input[$key] ?? '', $input['select'][$key] ?? []);
    }
    
    $maxsize = $mybb->get_input('maxsize', MyBB::INPUT_INT) ?: "";
    
    return [
        "name" => $db->escape_string($input['name'] ?? ''),
        "mimetype" => $db->escape_string($mimetype),
        "extension" => $db->escape_string($extension),
        "maxsize" => $maxsize,
        "icon" => $db->escape_string($input['icon'] ?? ''),
        'enabled' => $mybb->get_input('enabled', MyBB::INPUT_INT),
        'forcedownload' => $mybb->get_input('forcedownload', MyBB::INPUT_INT),
        'groups' => $db->escape_string($processed['groups']),
        'forums' => $db->escape_string($processed['forums']),
        'avatarfile' => $mybb->get_input('avatarfile', MyBB::INPUT_INT)
    ];
}

/**
 * Process selection field (groups/forums)
 */
function process_selection_field(string $value, $custom_values): string
{
    if ($value === 'all') {
        return '-1';
    } elseif ($value === 'custom') {
        if (is_array($custom_values)) {
            $custom_values = array_map('intval', $custom_values);
            return implode(',', array_filter($custom_values));
        }
        return '';
    }
    return '';
}

/**
 * Output admin CSS and JS resources
 */
function output_admin_resources(): void
{
    // ВЫЗЫВАЕМ ФУНКЦИЮ JavaScript ЗДЕСЬ, ЧТОБЫ ОНА БЫЛА В ШАПКЕ
    print_selection_javascript();
}

/**
 * Render attachment types table
 */
function render_attachment_types_table(int $start, int $per_page): string
{
    global $db, $lang, $mybb;
    
    $query = $db->simple_select(
        "attachtypes", 
        "*", 
        "", 
        [
            'limit_start' => $start, 
            'limit' => $per_page, 
            'order_by' => 'extension'
        ]
    );
    
    $html = <<<HTML
    <div class="container mt-3">
        <div class="card border-0 mb-4">
            <div class="card-header rounded-bottom text-19 fw-bold">
                Attachment Types
            </div>
        </div>
    </div>
    
    <div class="container mt-3">
        <div class="card">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Extension</th>
                        <th>MIME Type</th>
                        <th>Enabled</th>
                        <th>Maximum Size</th>
                        <th>Controls</th>
                    </tr>
                </thead>
                <tbody>
    HTML;
    
    while ($attachment_type = $db->fetch_array($query)) {
        process_attachment_icon($attachment_type);
        
       $status_icon = $attachment_type['enabled'] 
    ? '<i class="fas fa-toggle-on text-success" title="Enabled" style="font-size: 18px;"></i>'
    : '<i class="fas fa-toggle-off text-secondary" title="Disabled" style="font-size: 18px;"></i>';
        
        $phrase = $attachment_type['enabled'] ? 'Disable' : 'Enable';
        
        $html .= <<<HTML
        <tr>
            <td>
                {$attachment_type['icon']}
                <strong>{$attachment_type['extension']}</strong>
            </td>
            <td>{$attachment_type['mimetype']}</td>
            <td class="align_center">{$status_icon}</td>
            <td class="align_center">{$attachment_type['maxsize']}</td>
            <td>
                <div class="dropdown">
                    <a href="#" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-gear"></i> Options
                        <i class="fa-solid fa-angle-down small"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a href="index.php?act=attachment_types&action=edit&atid={$attachment_type['atid']}">Edit</a>
                        <a href="index.php?act=attachment_types&action=toggle_status&atid={$attachment_type['atid']}&my_post_key={$mybb->post_code}">{$phrase}</a>
                    </div>
                </div>
            </td>
        </tr>
        HTML;
    }
    
    $html .= <<<HTML
                </tbody>
            </table>
        </div>
    </div>
    HTML;
    
    return $html;
}