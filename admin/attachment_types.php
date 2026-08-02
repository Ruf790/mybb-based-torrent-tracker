<?php
/**
 * MyBB 1.8 - Attachment Types Manager
 */

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger">Error! Direct initialization of this file is not allowed.</div>');
}

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    exit('<div class="alert alert-danger">Error! You do not have permission to access this page.</div>');
}

require_once INC_PATH . '/functions_multipage.php';


global $mybb, $db, $plugins, $cache, $lang;

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

function print_selection_javascript(): void
{
    static $printed = false;
    if ($printed) return;
    $printed = true;

    echo <<<'HTML'
<script>
function checkAction(id) {
    const checked = document.querySelector('.' + id + '_forums_groups_check:checked');
    if (!checked) return;
    document.querySelectorAll('.' + id + '_forums_groups').forEach(el => el.style.display = 'none');
    const target = document.getElementById(id + '_forums_groups_' + checked.value);
    if (target) target.style.display = 'block';
}
document.addEventListener('DOMContentLoaded', function () {
    ['groups', 'forums'].forEach(id => {
        document.querySelectorAll('.' + id + '_forums_groups_check').forEach(r => {
            r.addEventListener('change', () => checkAction(id));
        });
        checkAction(id);
    });
});
</script>
HTML;
}

function generate_yes_no_radio(string $name, $value = "1", bool $int = true, array $yes_options = [], array $no_options = []): string
{
    $is_no = $value === "no" || $value === '0' || $value === 0;

    $yes_options['class']   = ($yes_options['class'] ?? '') . ' radio_yes';
    $no_options['class']    = ($no_options['class']  ?? '') . ' radio_no';
    $yes_options['checked'] = $is_no ? 0 : 1;
    $no_options['checked']  = $is_no ? 1 : 0;

    $yes_value = $int ? "1" : "yes";
    $no_value  = $int ? "0" : "no";

    return generate_radio_button($name, $yes_value, 'yes', $yes_options)
         . ' '
         . generate_radio_button($name, $no_value,  'no',  $no_options);
}

function generate_yes_no_switch(string $name, $value = "1", array $options = []): string
{
    $is_no   = $value === "no" || $value === '0' || $value === 0 || $value === false;
    $checked = $is_no ? '' : 'checked';
    $id      = $options['id'] ?? $name;
    $class   = trim('form-check-input ' . ($options['class'] ?? ''));

    return <<<HTML
<input type="hidden" name="{$name}" value="0" />
<div class="form-check form-switch mb-0">
    <input type="checkbox" class="{$class}" role="switch" id="{$id}" name="{$name}" value="1" {$checked}
           style="width:2.75em;height:1.5em;cursor:pointer;">
</div>
HTML;
}

function generate_group_select(string $name, $selected = [], array $options = []): string
{
    global $cache;

    $multiple = $options['multiple'] ?? false;
    $attrs    = array_filter([
        'name'     => $name,
        'class'    => $options['class'] ?? '',
        'id'       => $options['id']    ?? '',
        'size'     => $options['size']  ?? '',
        'multiple' => $multiple ? 'multiple' : null,
    ], fn($v) => $v !== null && $v !== '');

    $attr_str = implode(' ', array_map(
        fn($k, $v) => $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES) . '"',
        array_keys($attrs), $attrs
    ));

    $select   = "<select $attr_str>\n";
    $selected = is_array($selected) ? $selected : [$selected];

    foreach ($cache->read('usergroups') ?: [] as $group) {
        $sel     = in_array($group['gid'], $selected, true) ? ' selected="selected"' : '';
        $title   = htmlspecialchars_uni($group['title'] ?? '');
        $select .= "<option value=\"{$group['gid']}\"{$sel}>{$title}</option>\n";
    }

    return $select . "</select>";
}

function build_attributes(array $attributes): string
{
    $parts = [];
    foreach ($attributes as $k => $v) {
        if ($v !== null && $v !== '') {
            $parts[] = $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES) . '"';
        }
    }
    return $parts ? ' ' . implode(' ', $parts) : '';
}

function process_attachment_icon(array &$type): void
{
    $icon = stripslashes(trim($type['icon'] ?? ''));
    $name = htmlspecialchars_uni($type['name'] ?? '');

    if (!empty($icon) && str_starts_with($icon, '<')) {
        if (!str_contains($icon, 'title=')) {
            $icon = preg_replace('/\>/', ' title="' . $name . '">', $icon, 1);
        }
        $icon = str_contains($icon, 'style=')
            ? str_replace('style="', 'style="font-size:18px; ', $icon)
            : preg_replace('/\>/', ' style="font-size:18px;">', $icon, 1);
    } else {
        $icon = '<i class="fas fa-file" title="' . $name . '" style="font-size:18px;color:#ccc;"></i>';
    }

    $type['icon'] = $icon;
}

// ── Forums/Groups selection ───────────────────────────────────

function generate_groups_selection(string $field, string $current_value, array $selected_ids): string
{
    $checked = [
        'all'    => $current_value == -1 ? 'checked="checked"' : '',
        'custom' => $current_value != '' && $current_value != -1 ? 'checked="checked"' : '',
        'none'   => $current_value == '' ? 'checked="checked"' : '',
    ];
    $label = ucfirst($field);

    $custom_select = generate_group_select(
        "select[{$field}][]",
        $selected_ids,
        ['id' => $field, 'multiple' => true, 'size' => 5]
    );

    return generate_selection_block($field, $label, $checked, $custom_select, "{$label}:");
}

function generate_forums_selection(string $current_value, array $selected_ids): string
{
    $checked = [
        'all'    => $current_value == -1 ? 'checked="checked"' : '',
        'custom' => $current_value != '' && $current_value != -1 ? 'checked="checked"' : '',
        'none'   => $current_value == '' ? 'checked="checked"' : '',
    ];

    $custom_select = generate_forum_select(
        'select[forums][]',
        $selected_ids,
        ['id' => 'forums', 'multiple' => true, 'size' => 5]
    );

    return generate_selection_block('forums', 'Forums', $checked, $custom_select, 'Forums:');
}

function generate_selection_block(string $field, string $all_label, array $checked, string $custom_select, string $select_label): string
{
    return <<<HTML
<dl style="margin-top:0;margin-bottom:0;width:100%">
    <dt><label style="display:block;">
        <input type="radio" name="{$field}" value="all" {$checked['all']}
               class="{$field}_forums_groups_check" onclick="checkAction('{$field}');" style="vertical-align:middle;" />
        <strong>All {$all_label}</strong>
    </label></dt>
    <dt><label style="display:block;">
        <input type="radio" name="{$field}" value="custom" {$checked['custom']}
               class="{$field}_forums_groups_check" onclick="checkAction('{$field}');" style="vertical-align:middle;" />
        <strong>Select {$all_label}</strong>
    </label></dt>
    <dd style="margin-top:4px;" id="{$field}_forums_groups_custom" class="{$field}_forums_groups">
        <table cellpadding="4"><tr>
            <td valign="top"><small>{$select_label}</small></td>
            <td>{$custom_select}</td>
        </tr></table>
    </dd>
    <dt><label style="display:block;">
        <input type="radio" name="{$field}" value="none" {$checked['none']}
               class="{$field}_forums_groups_check" onclick="checkAction('{$field}');" style="vertical-align:middle;" />
        <strong>None</strong>
    </label></dt>
</dl>
<script>checkAction('{$field}');</script>
HTML;
}

// ── PHP upload limits helper ──────────────────────────────────

function get_php_upload_limits_string(): string
{
    $upload = @ini_get('upload_max_filesize');
    $post   = @ini_get('post_max_size');
    if (!$upload && !$post) return '';

    $str = '<br><br>PHP limits:';
    if ($upload) $str .= '<br>Upload Max: ' . htmlspecialchars($upload);
    if ($post)   $str .= '<br>Post Max: '   . htmlspecialchars($post);
    return $str;
}

// ── Icon field description ────────────────────────────────────

function get_icon_description(bool $short = false): string
{
    if ($short) {
        return '<div class="description">HTML Font Awesome icon code with color<br>'
             . 'Example: &lt;i class="fas fa-file-pdf" style="color:#e74c3c;"&gt;&lt;/i&gt;</div>';
    }

    return <<<HTML
<div class="description">
    Font Awesome icon with color. Examples:<br>
    <code>&lt;i class="fas fa-file-pdf" style="color:#e74c3c;"&gt;&lt;/i&gt;</code> PDF<br>
    <code>&lt;i class="fas fa-file-image" style="color:#1abc9c;"&gt;&lt;/i&gt;</code> Image<br>
    <code>&lt;i class="fas fa-file-archive" style="color:#e67e22;"&gt;&lt;/i&gt;</code> Archive<br>
    <code>&lt;i class="fas fa-file-word" style="color:#2b579a;"&gt;&lt;/i&gt;</code> Word<br>
    <code>&lt;i class="fas fa-file-excel" style="color:#217346;"&gt;&lt;/i&gt;</code> Excel<br>
    <code>&lt;i class="fas fa-file-powerpoint" style="color:#d24726;"&gt;&lt;/i&gt;</code> PowerPoint
</div>
HTML;
}

// ── Shared form body renderer ─────────────────────────────────

function render_attachment_form_fields(
    array $data,
    string $form_action,
    string $title,
    array $errors = [],
    bool $is_edit = false
): void {
    global $mybb;

    $limit_string   = get_php_upload_limits_string();

    $name_f     = generate_text_box('name',      $data['name']      ?? '', ['id' => 'name']);
    $ext_f      = generate_text_box('extension', $data['extension'] ?? '', ['id' => 'extension']);
    $mime_f     = generate_text_box('mimetype',  $data['mimetype']  ?? '', ['id' => 'mimetype']);
    $maxsize_f  = generate_numeric_field('maxsize', $data['maxsize'] ?? 1024, ['id' => 'maxsize', 'min' => 0]);

    $icon_f = get_icon_description($is_edit)
            . '<div class="form_row">'
            . generate_text_box('icon', $data['icon'] ?? 'pic/attachtypes/', ['id' => 'icon', 'style' => 'width:400px;'])
            . '</div>';

    $enabled_f    = generate_yes_no_switch('enabled',       $data['enabled']       ?? 1, ['id' => 'enabled']);
    $download_f   = generate_yes_no_switch('forcedownload', $data['forcedownload'] ?? 0, ['id' => 'forcedownload']);
    $avatar_f     = generate_yes_no_switch('avatarfile',    $data['avatarfile']    ?? 0, ['id' => 'avatarfile']);

    $groups_val     = $data['groups'] ?? '';
    $selected_grps  = ($groups_val !== '' && $groups_val != -1) ? explode(',', $groups_val) : [];
    $groups_sel     = generate_groups_selection('groups', $groups_val, $selected_grps);

    $forums_val     = $data['forums'] ?? '';
    $selected_frms  = ($forums_val !== '' && $forums_val != -1) ? explode(',', $forums_val) : [];
    $forums_sel     = generate_forums_selection($forums_val, $selected_frms);

    echo <<<HTML
<form action="{$form_action}" method="post">
    <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
    <div class="container mt-3">
        <div class="card">
            <div class="card-header rounded-bottom text-19 fw-bold">{$title}</div>
            <div class="card-body">
HTML;

    if (!empty($errors)) output_inline_error($errors);

    echo <<<HTML
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <div class="description">Attachment type name</div>
                    {$name_f}
                </div>
                <div class="mb-3">
                    <label for="extension" class="form-label">File Extension *</label>
                    <div class="description">Extension without period</div>
                    {$ext_f}
                </div>
                <div class="mb-3">
                    <label for="mimetype" class="form-label">MIME Type *</label>
                    <div class="description">Server MIME type</div>
                    {$mime_f}
                </div>
                <div class="mb-3">
                    <label for="maxsize" class="form-label">Maximum Size (KB)</label>
                    <div class="description">Max file size in kilobytes{$limit_string}</div>
                    {$maxsize_f}
                </div>
                <div class="mb-3">
                    <label for="icon" class="form-label">Attachment Icon</label>
                    {$icon_f}
                </div>
                <div class="mb-3">
                    <label for="enabled" class="form-label">Enabled?</label>
                    <div class="form_row">{$enabled_f}</div>
                </div>
                <div class="mb-3">
                    <label for="forcedownload" class="form-label">Force Download</label>
                    <div class="description">Force file download</div>
                    <div class="form_row">{$download_f}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Available to Groups</label>
                    {$groups_sel}
                </div>
                <div class="mb-3">
                    <label class="form-label">Available in Forums</label>
                    {$forums_sel}
                </div>
                <div class="mb-3">
                    <label for="avatarfile" class="form-label">Avatar File</label>
                    <div class="description">Allow for avatars?</div>
                    <div class="form_row">{$avatar_f}</div>
                </div>
            </div>
            <div class="card-footer text-center">
                <input type="submit" value="Save Attachment Type" class="btn btn-primary">
            </div>
        </div>
    </div>
</form>
HTML;
}

// ── Public form renderers ─────────────────────────────────────

function render_add_form(array $errors = []): void
{
    global $mybb;

    stdhead('Attachment Types - Add New');
    output_admin_resources();
    output_nav_tabs([
        'attachment_types'    => ['title' => 'Attachment Types',    'link' => 'index.php?act=attachment_types'],
        'add_attachment_type' => ['title' => 'Add New',             'link' => 'index.php?act=attachment_types&action=add', 'description' => 'Add a new attachment type'],
    ], 'add_attachment_type');

    render_attachment_form_fields(
        $mybb->input,
        'index.php?act=attachment_types&action=add',
        'Add New Attachment Type',
        $errors,
        false
    );

    stdfoot();
}

function render_edit_form(array $attachment_type, array $errors = []): void
{
    $atid = $attachment_type['atid'];

    stdhead('Attachment Types - Edit');
    output_admin_resources();
    output_nav_tabs([
        'edit_attachment_type' => ['title' => 'Edit Attachment Type', 'link' => "index.php?act=attachment_types&action=edit&atid={$atid}"],
    ], 'edit_attachment_type');

    render_attachment_form_fields(
        $attachment_type,
        "index.php?act=attachment_types&action=edit&atid={$atid}",
        'Edit Attachment Type',
        $errors,
        true
    );

    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// ACTIONS
// ═══════════════════════════════════════════════════════════

$plugins->run_hooks("admin_config_attachment_types_begin");

switch ($mybb->input['action'] ?? '') {
    case 'add':    handle_add_action();           break;
    case 'edit':   handle_edit_action();          break;
    case 'delete': handle_delete_action();        break;
    case 'toggle_status': handle_toggle_status_action(); break;
    default:       handle_list_action();          break;
}

function handle_add_action(): void
{
    global $mybb, $db, $plugins, $cache;

    $plugins->run_hooks("admin_config_attachment_types_add");

    if ($mybb->request_method === "post") {
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            die('Invalid security token');
        }

        $errors = validate_attachment_type_input($mybb->input);
        if (empty($errors)) {
            $data         = prepare_attachment_type_data($mybb->input);
            $columns      = array_keys($data);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $db->sql_query_prepared(
                "INSERT INTO attachtypes (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                array_values($data)
            );
            $plugins->run_hooks("admin_config_attachment_types_add_commit");
            $cache->update_attachtypes();
            flash_message('success_attachment_type_created', 'success');
            admin_redirect("index.php?act=attachment_types");
        }
    }

    render_add_form($errors ?? []);
}

function handle_edit_action(): void
{
    global $mybb, $db, $plugins, $cache;

    $atid = $mybb->get_input('atid', MyBB::INPUT_INT);
    $tq   = $db->sql_query_prepared("SELECT * FROM attachtypes WHERE atid = ?", [$atid]);
    $type = $tq ? $db->fetch_array($tq) : null;

    if (!($type['atid'] ?? null)) {
        flash_message('error_invalid_attachment_type', 'error');
        admin_redirect("index.php?act=attachment_types");
    }

    $plugins->run_hooks("admin_config_attachment_types_edit");

    if ($mybb->request_method === "post") {
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            die('Invalid security token');
        }

        $errors = validate_attachment_type_input($mybb->input);
        if (empty($errors)) {
            $data   = prepare_attachment_type_data($mybb->input);
            $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
            $params = array_values($data);
            $params[] = $atid;
            $db->sql_query_prepared("UPDATE attachtypes SET {$set} WHERE atid = ?", $params);
            $plugins->run_hooks("admin_config_attachment_types_edit_commit");
            $cache->update_attachtypes();
            flash_message('success_attachment_type_updated', 'success');
            admin_redirect("index.php?act=attachment_types");
        }
    } else {
        $mybb->input = array_merge($mybb->input, $type);
    }

    render_edit_form($type, $errors ?? []);
}

function handle_delete_action(): void
{
    global $mybb, $db, $plugins, $cache, $lang;

    $atid = $mybb->get_input('atid', MyBB::INPUT_INT);
    $tq   = $db->sql_query_prepared("SELECT * FROM attachtypes WHERE atid = ?", [$atid]);
    $type = $tq ? $db->fetch_array($tq) : null;

    if (!($type['atid'] ?? null)) {
        flash_message($lang->error_invalid_attachment_type, 'error');
        admin_redirect("index.php?module=config-attachment_types");
    }

    $plugins->run_hooks("admin_config_attachment_types_delete");

    if ($mybb->request_method === "post") {
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            die('Invalid security token');
        }

        $db->sql_query_prepared("DELETE FROM attachtypes WHERE atid = ?", [$atid]);
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

function handle_toggle_status_action(): void
{
    global $mybb, $db, $plugins, $cache, $lang;

    if ($mybb->request_method !== "post" || !verify_post_check($mybb->get_input('my_post_key'))) {
        http_response_code(403);
        die('Invalid security token or request method. Please use the button on the list page.');
    }

    $atid = $mybb->get_input('atid', MyBB::INPUT_INT);
    $tq2  = $db->sql_query_prepared("SELECT * FROM attachtypes WHERE atid = ?", [$atid]);
    $type = $tq2 ? $db->fetch_array($tq2) : null;

    if (!($type['atid'] ?? null)) {
        flash_message($lang->error_invalid_mycode, 'error');
        admin_redirect('index.php?act=attachment_types');
    }

    $plugins->run_hooks('admin_config_attachment_types_toggle_status');
    $new_status = $type['enabled'] == 1 ? 0 : 1;
    $db->sql_query_prepared("UPDATE attachtypes SET enabled = ? WHERE atid = ?", [$new_status, $atid]);
    $plugins->run_hooks('admin_config_attachment_types_toggle_status_commit');
    $cache->update_attachtypes();

    flash_message($new_status ? 'success_activated_attachment_type' : 'success_deactivated_attachment_type', 'success');
    admin_redirect('index.php?act=attachment_types');
}

function handle_list_action(): void
{
    global $mybb, $db, $plugins;

    stdhead('Attachment Types');
    output_admin_resources();
    output_nav_tabs([
        'attachment_types'    => ['title' => 'Attachment Types', 'link' => 'index.php?act=attachment_types', 'description' => 'Manage attachment types'],
        'add_attachment_type' => ['title' => 'Add New',          'link' => 'index.php?act=attachment_types&amp;action=add'],
    ], 'attachment_types');

    $plugins->run_hooks("admin_config_attachment_types_start");

    $per_page   = 20;
    $trq        = $db->sql_query_prepared("SELECT COUNT(atid) AS attachtypes FROM attachtypes");
    $total_rows = $trq ? (int)$db->fetch_field($trq, "attachtypes") : 0;
    $page       = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start      = ($page - 1) * $per_page;
    $pages      = ceil($total_rows / $per_page);

    echo render_stats_cards();
    echo render_attachment_types_table($start, $per_page);

    if ($pages > 1) {
        echo '<div class="container mt-3">'
           . multipage($total_rows, $per_page, $page, "index.php?act=attachment_types&amp;page={page}")
           . '</div>';
    }
	
	

    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// DATA HELPERS
// ═══════════════════════════════════════════════════════════

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

function prepare_attachment_type_data(array $input): array
{
    global $db, $mybb;

    $mimetype  = $input['mimetype'] === "pic/attachtypes/" ? '' : $input['mimetype'];
    $extension = str_starts_with($input['extension'] ?? '', '.') ? substr($input['extension'], 1) : $input['extension'];

    $processed = [];
    foreach (['groups', 'forums'] as $key) {
        $processed[$key] = process_selection_field($input[$key] ?? '', $input['select'][$key] ?? []);
    }

    return [
        'name'          => $input['name'] ?? '',
        'mimetype'      => $mimetype,
        'extension'     => $extension,
        'maxsize'       => $mybb->get_input('maxsize', MyBB::INPUT_INT) ?: "",
        'icon'          => $input['icon'] ?? '',
        'enabled'       => $mybb->get_input('enabled',       MyBB::INPUT_INT),
        'forcedownload' => $mybb->get_input('forcedownload', MyBB::INPUT_INT),
        'groups'        => $processed['groups'],
        'forums'        => $processed['forums'],
        'avatarfile'    => $mybb->get_input('avatarfile', MyBB::INPUT_INT),
    ];
}

function process_selection_field(string $value, $custom_values): string
{
    if ($value === 'all') return '-1';
    if ($value === 'custom' && is_array($custom_values)) {
        return implode(',', array_filter(array_map('intval', $custom_values)));
    }
    return '';
}

function output_admin_resources(): void
{
    print_selection_javascript();
}

function render_stats_cards(): string
{
    global $db;

    $tq2 = $db->sql_query_prepared("SELECT COUNT(atid) AS c FROM attachtypes");
    $total    = $tq2 ? (int)$db->fetch_field($tq2, 'c') : 0;
    $eq  = $db->sql_query_prepared("SELECT COUNT(atid) AS c FROM attachtypes WHERE enabled='1'");
    $enabled  = $eq ? (int)$db->fetch_field($eq, 'c') : 0;
    $disabled = $total - $enabled;
    $aq  = $db->sql_query_prepared("SELECT AVG(maxsize) AS c FROM attachtypes WHERE maxsize > 0");
    $avgsize  = $aq ? (int)$db->fetch_field($aq, 'c') : 0;
    $avgsize  = $avgsize > 0 ? number_format($avgsize) . ' KB' : '—';

    $cards = [
        ['icon' => 'fa-layer-group',  'color' => '#3b82f6', 'label' => 'Total Types',    'value' => $total],
        ['icon' => 'fa-toggle-on',    'color' => '#22c55e', 'label' => 'Enabled',        'value' => $enabled],
        ['icon' => 'fa-toggle-off',   'color' => '#64748b', 'label' => 'Disabled',       'value' => $disabled],
        ['icon' => 'fa-weight-hanging','color' => '#f59e0b', 'label' => 'Avg. Max Size', 'value' => $avgsize],
    ];

    $html = '<div class="container mt-3"><div class="row g-3 mb-1">';
    foreach ($cards as $c) {
        $html .= <<<HTML
<div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3 py-3">
            <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                 style="width:44px;height:44px;background:{$c['color']}1a;">
                <i class="fa-solid {$c['icon']}" style="color:{$c['color']};font-size:18px;"></i>
            </div>
            <div>
                <div class="text-19 fw-bold lh-1">{$c['value']}</div>
                <div class="text-secondary" style="font-size:12px;">{$c['label']}</div>
            </div>
        </div>
    </div>
</div>
HTML;
    }
    return $html . '</div></div>';
}

function format_maxsize_badge($maxsize): string
{
    if ($maxsize === '' || $maxsize === null || (int)$maxsize === 0) {
        return '<span class="badge rounded-pill text-bg-light border">Unlimited</span>';
    }
    $kb = (int)$maxsize;
    $display = $kb >= 1024 ? number_format($kb / 1024, 1) . ' MB' : $kb . ' KB';
    return '<span class="badge rounded-pill text-bg-light border">' . htmlspecialchars($display) . '</span>';
}

function render_attachment_types_table(int $start, int $per_page): string
{
    global $db, $mybb;

    $query = $db->sql_query_prepared(
        "SELECT * FROM attachtypes ORDER BY extension LIMIT ?, ?",
        [$start, $per_page]
    );

    $html = <<<HTML
<div class="container mt-3">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between rounded-top">
            <span class="text-19 fw-bold"><i class="fa-solid fa-paperclip me-2 text-secondary"></i>Attachment Types</span>
            <a href="index.php?act=attachment_types&amp;action=add" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus me-1"></i>Add New
            </a>
        </div>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Type</th><th>MIME Type</th>
                    <th class="text-center">Status</th><th class="text-center">Max Size</th><th class="text-end">Controls</th>
                </tr>
            </thead>
            <tbody>
HTML;

    while ($query && ($type = $db->fetch_array($query))) {
        process_attachment_icon($type);

        $status = $type['enabled']
            ? '<span class="badge rounded-pill text-bg-success-subtle text-success-emphasis"><i class="fa-solid fa-circle-check me-1"></i>Enabled</span>'
            : '<span class="badge rounded-pill text-bg-secondary-subtle text-secondary-emphasis"><i class="fa-solid fa-circle-xmark me-1"></i>Disabled</span>';

        $phrase   = $type['enabled'] ? 'Disable' : 'Enable';
        $atid     = $type['atid'];
        $mimetype = $type['mimetype'] !== '' ? htmlspecialchars($type['mimetype']) : '<span class="text-secondary">—</span>';
        $sizebadge = format_maxsize_badge($type['maxsize']);

        $html .= <<<HTML
<tr>
    <td>
        <div class="d-flex align-items-center gap-2">
            <span style="width:24px;text-align:center;">{$type['icon']}</span>
            <strong>.{$type['extension']}</strong>
        </div>
    </td>
    <td><code class="small">{$mimetype}</code></td>
    <td class="text-center">{$status}</td>
    <td class="text-center">{$sizebadge}</td>
    <td class="text-end">
        <div class="dropdown">
            <a href="#" class="text-decoration-none" data-bs-toggle="dropdown">
                <i class="fa-solid fa-gear"></i> Options <i class="fa-solid fa-angle-down small"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="index.php?act=attachment_types&action=edit&atid={$atid}">
                    <i class="fa-solid fa-pen me-2 text-secondary"></i>Edit
                </a>
                <form method="post" action="index.php?act=attachment_types&action=toggle_status" class="d-inline">
                    <input type="hidden" name="my_post_key" value="{$mybb->post_code}">
                    <input type="hidden" name="atid" value="{$atid}">
                    <button type="submit" class="dropdown-item btn btn-link p-0 border-0 text-start" style="text-decoration:none;">
                        <i class="fa-solid fa-power-off me-2 text-secondary"></i>{$phrase}
                    </button>
                </form>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="index.php?act=attachment_types&action=delete&atid={$atid}">
                    <i class="fa-solid fa-trash me-2"></i>Delete
                </a>
            </div>
        </div>
    </td>
</tr>
HTML;
    }

    return $html . '</tbody></table></div></div></div>';
}