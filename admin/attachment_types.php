<?php
/**
 * MyBB 1.8 - Attachment Types Manager
 */

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger">Error! Direct initialization of this file is not allowed.</div>');
}

global $mybb, $db, $plugins, $cache, $lang;

$lang->load('attachment_types');

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    exit('<div class="alert alert-danger">' . at_e($lang->attachment_types['err_no_permission']) . '</div>');
}

require_once INC_PATH . '/functions_multipage.php';

const AT_URL          = 'index.php?act=attachment_types';
const AT_DEFAULT_ICON = 'pic/attachtypes/';

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

function at_e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (!function_exists('ags_fmt')) {
    /** Подстановка {1}, {2}… в строку из ланга (понимает и вид %1$s, если загрузчик ланга его подставил) */
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

function generate_numeric_field(string $name, mixed $value = 0, array $options = []): string
{
    $val = is_numeric($value) ? (string)(float)$value : '';
    $cls = isset($options['class']) ? ' ' . $options['class'] : '';
    $id  = isset($options['id']) ? ' id="' . at_e($options['id']) . '"' : '';

    $input = '<input type="number" name="' . at_e($name) . '" value="' . at_e($val) . '" class="form-control' . $cls . '"' . $id;
    foreach (['min', 'max', 'step', 'style', 'placeholder'] as $attr) {
        if (isset($options[$attr])) $input .= ' ' . $attr . '="' . at_e($options[$attr]) . '"';
    }
    return $input . ' />';
}

function generate_text_box(string $name, string $value = '', array $options = []): string
{
    $cls = isset($options['class']) ? ' ' . $options['class'] : '';
    $id  = isset($options['id']) ? ' id="' . at_e($options['id']) . '"' : '';

    $input = '<input type="text" name="' . at_e($name) . '" value="' . htmlspecialchars_uni($value) . '" class="form-control' . $cls . '"' . $id;
    foreach (['style', 'placeholder', 'autocomplete'] as $attr) {
        if (isset($options[$attr])) $input .= ' ' . $attr . '="' . at_e($options[$attr]) . '"';
    }
    return $input . ' />';
}

/** Переключатель да/нет с иконкой и подписью */
function generate_yes_no_switch(string $name, $value = '1', array $options = []): string
{
    $is_no   = $value === 'no' || $value === '0' || $value === 0 || $value === false;
    $checked = $is_no ? '' : 'checked';
    $id      = at_e($options['id'] ?? $name);
    $n       = at_e($name);
    $icon    = at_e($options['icon'] ?? 'fa-toggle-on');
    $label   = at_e($options['label'] ?? '');
    $desc    = at_e($options['desc'] ?? '');

    return <<<HTML
<label class="at-switch" for="{$id}">
    <span class="at-switch-icon"><i class="fa-solid {$icon}"></i></span>
    <span class="at-switch-text"><span class="fw-semibold">{$label}</span><span class="at-help">{$desc}</span></span>
    <input type="hidden" name="{$n}" value="0" />
    <span class="form-check form-switch m-0">
        <input type="checkbox" class="form-check-input" role="switch" id="{$id}" name="{$n}" value="1" {$checked}>
    </span>
</label>
HTML;
}

function generate_group_select(string $name, $selected = [], array $options = []): string
{
    global $cache;

    $attrs = array_filter([
        'name'     => $name,
        'class'    => trim('form-select ' . ($options['class'] ?? '')),
        'id'       => $options['id'] ?? '',
        'size'     => $options['size'] ?? '',
        'multiple' => !empty($options['multiple']) ? 'multiple' : null,
    ], fn($v) => $v !== null && $v !== '');

    $attr_str = implode(' ', array_map(fn($k, $v) => $k . '="' . at_e($v) . '"', array_keys($attrs), $attrs));

    // Сравниваем строками: gid из кэша — int, а выбранные значения после explode() —
    // строки. Раньше in_array(..., true) никогда не совпадал, и при редактировании
    // сохранённые группы не отображались выбранными.
    $selected = array_map('strval', is_array($selected) ? $selected : [$selected]);

    $html = "<select {$attr_str}>\n";
    foreach ($cache->read('usergroups') ?: [] as $group) {
        $gid   = (string)$group['gid'];
        $sel   = in_array($gid, $selected, true) ? ' selected="selected"' : '';
        $html .= '<option value="' . at_e($gid) . '"' . $sel . '>' . htmlspecialchars_uni($group['title'] ?? '') . "</option>\n";
    }
    return $html . '</select>';
}

/** Иконка типа вложения (HTML Font Awesome из настроек или файл по умолчанию) */
function render_type_icon(array $type, string $size = '1.35rem'): string
{
    $icon = stripslashes(trim((string)($type['icon'] ?? '')));
    $name = htmlspecialchars_uni((string)($type['name'] ?? ''));

    if ($icon !== '' && str_starts_with($icon, '<')) {
        // Иконку задаёт стафф через форму — HTML допускается, как и раньше
        if (!str_contains($icon, 'title=')) {
            $icon = preg_replace('/>/', ' title="' . $name . '">', $icon, 1);
        }
        return '<span class="at-icon" style="font-size:' . $size . '">' . $icon . '</span>';
    }
    return '<span class="at-icon at-icon-empty" style="font-size:' . $size . '" title="' . $name . '"><i class="fa-solid fa-file"></i></span>';
}

// ── Все / выбранные / никто ───────────────────────────────────

function generate_selection_block(string $field, string $all_text, string $current_value, string $custom_select): string
{
    global $lang;

    $mode = $current_value == -1 ? 'all' : ($current_value !== '' ? 'custom' : 'none');
    $f    = at_e($field);
    $opt  = static function (string $value, string $icon, string $text) use ($f, $mode): string {
        $chk  = $mode === $value ? 'checked' : '';
        $text = at_e($text);
        return <<<HTML
<input type="radio" class="btn-check {$f}_forums_groups_check" name="{$f}" id="{$f}_{$value}" value="{$value}" {$chk} autocomplete="off">
<label class="btn btn-sm btn-outline-secondary rounded-pill px-3" for="{$f}_{$value}"><i class="fa-solid {$icon} me-1"></i>{$text}</label>
HTML;
    };

    return '<div class="d-flex flex-wrap gap-2">'
         . $opt('all', 'fa-globe', $all_text)
         . $opt('custom', 'fa-list-check', $lang->attachment_types['opt_selected'])
         . $opt('none', 'fa-ban', $lang->attachment_types['opt_none'])
         . '</div>'
         . '<div class="mt-2 ' . $f . '_forums_groups" id="' . $f . '_forums_groups_custom">'
         . $custom_select
         . '<div class="at-help mt-1"><i class="fa-solid fa-keyboard me-1"></i>' . at_e($lang->attachment_types['hint_multi_select']) . '</div>'
         . '</div>';
}

function generate_groups_selection(string $current_value, array $selected_ids): string
{
    global $lang;
    $select = generate_group_select('select[groups][]', $selected_ids, ['id' => 'groups', 'multiple' => true, 'size' => 6]);
    return generate_selection_block('groups', $lang->attachment_types['opt_all_groups'], $current_value, $select);
}

function generate_forums_selection(string $current_value, array $selected_ids): string
{
    global $lang;
    $select = generate_forum_select('select[forums][]', $selected_ids, ['id' => 'forums', 'multiple' => true, 'size' => 6, 'class' => 'form-select']);
    return generate_selection_block('forums', $lang->attachment_types['opt_all_forums'], $current_value, $select);
}

function get_php_upload_limits(): array
{
    global $lang;

    return array_filter([
        $lang->attachment_types['lbl_upload_max'] => (string)@ini_get('upload_max_filesize'),
        $lang->attachment_types['lbl_post_max']   => (string)@ini_get('post_max_size'),
    ]);
}

function format_size_kb($maxsize): string
{
    global $lang;

    $kb = (int)$maxsize;
    if ($kb <= 0) return at_e($lang->attachment_types['size_unlimited']);
    return $kb >= 1024
        ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.') . ' ' . at_e($lang->attachment_types['unit_mb'])
        : $kb . ' ' . at_e($lang->attachment_types['unit_kb']);
}

// ── Общие стили страницы ──────────────────────────────────────


function output_admin_resources(): void
{
    global $BASEURL;
	
	static $printed = false;
    if ($printed) return;
    $printed = true;

    //echo '<link rel="stylesheet" href="' . at_asset('/include/templates/default/style/attachment_types.css') . '">' . "\n"
    //   . '<script src="' . at_asset('/scripts/attachment_types.js') . '" defer></script>' . "\n";
	   
	echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/attachment_types.css">';
	echo '<script src="' . $BASEURL . '/admin/scripts/attachment_types.js"></script>'; 
	   
	   
}

// ── Форма добавления / редактирования ─────────────────────────

function render_attachment_form_fields(array $data, string $form_action, string $title, array $errors = [], bool $is_edit = false): void
{
    global $mybb, $lang;

    $L = array_map('at_e', $lang->attachment_types);

    $name_f    = generate_text_box('name',      (string)($data['name'] ?? ''),      ['id' => 'name', 'placeholder' => $lang->attachment_types['ph_name']]);
    $ext_f     = generate_text_box('extension', (string)($data['extension'] ?? ''), ['id' => 'extension', 'placeholder' => $lang->attachment_types['ph_extension'], 'autocomplete' => 'off']);
    $mime_f    = generate_text_box('mimetype',  (string)($data['mimetype'] ?? ''),  ['id' => 'mimetype', 'placeholder' => $lang->attachment_types['ph_mime'], 'autocomplete' => 'off']);
    $maxsize_f = generate_numeric_field('maxsize', $data['maxsize'] ?? 1024, ['id' => 'maxsize', 'min' => 0, 'placeholder' => $lang->attachment_types['ph_maxsize']]);
    $icon_val  = (string)($data['icon'] ?? AT_DEFAULT_ICON);
    $icon_f    = generate_text_box('icon', $icon_val, ['id' => 'icon', 'class' => 'font-monospace', 'autocomplete' => 'off']);
    $preview   = render_type_icon(['icon' => $icon_val, 'name' => $data['name'] ?? ''], '2rem');

    $enabled_f  = generate_yes_no_switch('enabled',       $data['enabled'] ?? 1,       ['id' => 'enabled',       'icon' => 'fa-power-off',   'label' => $lang->attachment_types['lbl_enabled'],        'desc' => $lang->attachment_types['hint_enabled']]);
    $download_f = generate_yes_no_switch('forcedownload', $data['forcedownload'] ?? 0, ['id' => 'forcedownload', 'icon' => 'fa-download',    'label' => $lang->attachment_types['lbl_force_download'], 'desc' => $lang->attachment_types['hint_force_download']]);
    $avatar_f   = generate_yes_no_switch('avatarfile',    $data['avatarfile'] ?? 0,    ['id' => 'avatarfile',    'icon' => 'fa-circle-user', 'label' => $lang->attachment_types['lbl_avatar_file'],    'desc' => $lang->attachment_types['hint_avatar_file']]);

    $groups_val = (string)($data['groups'] ?? '');
    $groups_sel = generate_groups_selection($groups_val, ($groups_val !== '' && $groups_val != -1) ? explode(',', $groups_val) : []);
    $forums_val = (string)($data['forums'] ?? '');
    $forums_sel = generate_forums_selection($forums_val, ($forums_val !== '' && $forums_val != -1) ? explode(',', $forums_val) : []);

    $limits = '';
    foreach (get_php_upload_limits() as $k => $v) {
        $limits .= '<span class="at-pill p-size"><i class="fa-solid fa-server"></i>' . at_e(ags_fmt($lang->attachment_types['pill_php_limit'], $k, $v)) . '</span>';
    }

    $presets = '';
    foreach ([
        ['fa-file-pdf', '#e74c3c', $lang->attachment_types['preset_pdf']], ['fa-file-image', '#1abc9c', $lang->attachment_types['preset_image']], ['fa-file-zipper', '#e67e22', $lang->attachment_types['preset_archive']],
        ['fa-file-word', '#2b579a', $lang->attachment_types['preset_word']], ['fa-file-excel', '#217346', $lang->attachment_types['preset_excel']], ['fa-file-powerpoint', '#d24726', $lang->attachment_types['preset_ppt']],
        ['fa-file-video', '#8e44ad', $lang->attachment_types['preset_video']], ['fa-file-audio', '#2980b9', $lang->attachment_types['preset_audio']], ['fa-file-lines', '#7f8c8d', $lang->attachment_types['preset_text']],
        ['fa-file-code', '#34495e', $lang->attachment_types['preset_code']],
    ] as [$ic, $col, $lbl]) {
        $code = '<i class="fas ' . $ic . '" style="color:' . $col . ';"></i>';
        $presets .= '<button type="button" class="at-preset" data-icon="' . at_e($code) . '"><i class="fa-solid ' . $ic . '" style="color:' . $col . '"></i>' . at_e($lbl) . '</button>';
    }

    $post_key  = at_e($mybb->post_code);
    $btn_label = $is_edit ? $L['btn_save'] : $L['btn_create'];
    $title_e   = at_e($title);
    $head_icon = $is_edit ? 'fa-pen-to-square' : 'fa-circle-plus';

    echo '<div class="container mt-3 mb-4 at">';
    echo <<<HTML
<div class="at-card mb-3">
    <div class="at-head">
        <span class="at-head-icon"><i class="fa-solid {$head_icon}"></i></span>
        <div>
            <h1 class="at-title">{$title_e}</h1>
            <div class="at-sub">{$L['sub_form']}</div>
        </div>
        <a href="index.php?act=attachment_types" class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-auto"><i class="fa-solid fa-arrow-left me-1"></i>{$L['btn_back']}</a>
    </div>
</div>
HTML;

    if (!empty($errors)) output_inline_error($errors);

    echo <<<HTML
<form action="{$form_action}" method="post">
    <input type="hidden" name="my_post_key" value="{$post_key}" />
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="at-card">
                <div class="at-sec">
                    <div class="at-sec-head"><span class="at-sec-icon ic-blue"><i class="fa-solid fa-file-circle-question"></i></span>{$L['sec_file_type']}</div>
                    <div class="mb-3">
                        <label for="name" class="form-label"><i class="fa-solid fa-tag"></i>{$L['lbl_name']}</label>
                        {$name_f}
                        <span class="at-help">{$L['hint_name']}</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-5">
                            <label for="extension" class="form-label"><i class="fa-solid fa-file-signature"></i>{$L['lbl_extension']} <span class="at-req">*</span></label>
                            <div class="input-group"><span class="input-group-text">.</span>{$ext_f}</div>
                            <span class="at-help">{$L['hint_extension']}</span>
                        </div>
                        <div class="col-sm-7">
                            <label for="mimetype" class="form-label"><i class="fa-solid fa-code"></i>{$L['lbl_mime']} <span class="at-req">*</span></label>
                            {$mime_f}
                            <span class="at-help">{$lang->attachment_types['hint_mime']}</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="maxsize" class="form-label"><i class="fa-solid fa-weight-hanging"></i>{$L['lbl_maxsize']}</label>
                        <div class="input-group at-size-group">{$maxsize_f}<span class="input-group-text">{$L['unit_kb']}</span></div>
                        <span class="at-help">{$L['hint_maxsize']}</span>
                        <div class="at-limits">{$limits}</div>
                    </div>
                </div>

                <div class="at-sec">
                    <div class="at-sec-head"><span class="at-sec-icon ic-purple"><i class="fa-solid fa-icons"></i></span>{$L['sec_icon']}</div>
                    <div class="d-flex gap-3 align-items-start">
                        <div id="atIconPreview">{$preview}</div>
                        <div class="flex-grow-1 at-minw0">
                            <label for="icon" class="form-label">{$L['lbl_icon_html']}</label>
                            {$icon_f}
                            <span class="at-help">{$lang->attachment_types['hint_icon']}</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">{$presets}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="at-card mb-3">
                <div class="at-sec">
                    <div class="at-sec-head"><span class="at-sec-icon ic-green"><i class="fa-solid fa-sliders"></i></span>{$L['sec_behaviour']}</div>
                    <div class="d-grid gap-2">{$enabled_f}{$download_f}{$avatar_f}</div>
                </div>
            </div>
            <div class="at-card">
                <div class="at-sec">
                    <div class="at-sec-head"><span class="at-sec-icon ic-amber"><i class="fa-solid fa-user-group"></i></span>{$L['sec_groups']}</div>
                    {$groups_sel}
                </div>
                <div class="at-sec">
                    <div class="at-sec-head"><span class="at-sec-icon ic-slate"><i class="fa-solid fa-comments"></i></span>{$L['sec_forums']}</div>
                    {$forums_sel}
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="index.php?act=attachment_types" class="btn btn-outline-secondary rounded-pill px-3"><i class="fa-solid fa-xmark me-1"></i>{$L['btn_cancel']}</a>
        <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-floppy-disk me-1"></i>{$btn_label}</button>
    </div>
</form>
</div>
HTML;
}

function render_add_form(array $errors = []): void
{
    global $mybb, $lang;
    stdhead($lang->attachment_types['page_title_add']);
    output_admin_resources();
    render_attachment_form_fields($mybb->input, AT_URL . '&action=add', $lang->attachment_types['head_add'], $errors, false);
    stdfoot();
}

function render_edit_form(array $attachment_type, array $errors = []): void
{
    global $mybb, $lang;
    $atid = (int)$attachment_type['atid'];
    stdhead($lang->attachment_types['page_title_edit']);
    output_admin_resources();
    // После ошибки валидации показываем то, что ввёл пользователь, а не старые данные
    $data = $errors ? array_merge($attachment_type, $mybb->input) : $attachment_type;
    render_attachment_form_fields($data, AT_URL . '&action=edit&atid=' . $atid, $lang->attachment_types['head_edit'], $errors, true);
    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// ACTIONS
// ═══════════════════════════════════════════════════════════

$plugins->run_hooks('admin_config_attachment_types_begin');

switch ($mybb->input['action'] ?? '') {
    case 'add':           handle_add_action();           break;
    case 'edit':          handle_edit_action();          break;
    case 'delete':        handle_delete_action();        break;
    case 'toggle_status': handle_toggle_status_action(); break;
    default:              handle_list_action();          break;
}

function at_require_post_key(): void
{
    global $mybb, $lang;
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        http_response_code(403);
        die(at_e($lang->attachment_types['err_token']));
    }
}

function at_fetch_type(int $atid): ?array
{
    global $db;
    $q = $db->sql_query_prepared('SELECT * FROM attachtypes WHERE atid = ?', [$atid]);
    $t = $q ? $db->fetch_array($q) : null;
    return !empty($t['atid']) ? $t : null;
}

function handle_add_action(): void
{
    global $mybb, $db, $plugins, $cache, $lang;

    $plugins->run_hooks('admin_config_attachment_types_add');
    $errors = [];

    if ($mybb->request_method === 'post') {
        at_require_post_key();
        $errors = validate_attachment_type_input($mybb->input);
        if (empty($errors)) {
            $data = prepare_attachment_type_data($mybb->input);
            $db->sql_query_prepared(
                'INSERT INTO attachtypes (`' . implode('`,`', array_keys($data)) . '`) VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')',
                array_values($data)
            );
            $plugins->run_hooks('admin_config_attachment_types_add_commit');
            $cache->update_attachtypes();
            flash_message($lang->attachment_types['flash_created'], 'success');
            admin_redirect(AT_URL);
        }
    }

    render_add_form($errors);
}

function handle_edit_action(): void
{
    global $mybb, $db, $plugins, $cache, $lang;

    $atid = $mybb->get_input('atid', MyBB::INPUT_INT);
    $type = at_fetch_type($atid);
    if (!$type) {
        flash_message($lang->attachment_types['flash_invalid'], 'error');
        admin_redirect(AT_URL);
    }

    $plugins->run_hooks('admin_config_attachment_types_edit');
    $errors = [];

    if ($mybb->request_method === 'post') {
        at_require_post_key();
        $errors = validate_attachment_type_input($mybb->input);
        if (empty($errors)) {
            $data   = prepare_attachment_type_data($mybb->input);
            $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
            $params = [...array_values($data), $atid];
            $db->sql_query_prepared("UPDATE attachtypes SET {$set} WHERE atid = ?", $params);
            $plugins->run_hooks('admin_config_attachment_types_edit_commit');
            $cache->update_attachtypes();
            flash_message($lang->attachment_types['flash_updated'], 'success');
            admin_redirect(AT_URL);
        }
    }

    render_edit_form($type, $errors);
}

/**
 * Удаление — только POST из модалки на странице списка.
 * Раньше GET-ветка вызывала $page->output_confirm_action() — $page здесь не
 * определён (fatal error), а редирект вёл на MyBB-админку module=config-…
 */
function handle_delete_action(): void
{
    global $mybb, $db, $plugins, $cache, $lang;

    if ($mybb->request_method !== 'post') {
        admin_redirect(AT_URL);
    }
    at_require_post_key();

    $atid = $mybb->get_input('atid', MyBB::INPUT_INT);
    if (!at_fetch_type($atid)) {
        flash_message($lang->attachment_types['flash_invalid'], 'error');
        admin_redirect(AT_URL);
    }

    $plugins->run_hooks('admin_config_attachment_types_delete');
    $db->sql_query_prepared('DELETE FROM attachtypes WHERE atid = ?', [$atid]);
    $plugins->run_hooks('admin_config_attachment_types_delete_commit');
    $cache->update_attachtypes();
    flash_message($lang->attachment_types['flash_deleted'], 'success');
    admin_redirect(AT_URL);
}

function handle_toggle_status_action(): void
{
    global $mybb, $db, $plugins, $cache, $lang;

    if ($mybb->request_method !== 'post') {
        admin_redirect(AT_URL);
    }
    at_require_post_key();

    $atid = $mybb->get_input('atid', MyBB::INPUT_INT);
    $type = at_fetch_type($atid);
    if (!$type) {
        flash_message($lang->attachment_types['flash_invalid'], 'error');
        admin_redirect(AT_URL);
    }

    $plugins->run_hooks('admin_config_attachment_types_toggle_status');
    $new_status = (int)$type['enabled'] === 1 ? 0 : 1;
    $db->sql_query_prepared('UPDATE attachtypes SET enabled = ? WHERE atid = ?', [$new_status, $atid]);
    $plugins->run_hooks('admin_config_attachment_types_toggle_status_commit');
    $cache->update_attachtypes();

    flash_message($new_status ? $lang->attachment_types['flash_enabled'] : $lang->attachment_types['flash_disabled'], 'success');
    admin_redirect(AT_URL . '&page=' . max(1, $mybb->get_input('page', MyBB::INPUT_INT)));
}

function handle_list_action(): void
{
    global $mybb, $db, $plugins, $lang;

    stdhead($lang->attachment_types['page_title']);
    output_admin_resources();
    $plugins->run_hooks('admin_config_attachment_types_start');

    // Статистика одним запросом (было три)
    $sq = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(enabled = 1), 0) AS enabled,
                COALESCE(SUM(avatarfile = 1), 0) AS avatars,
                COALESCE(AVG(NULLIF(maxsize, 0)), 0) AS avgsize
         FROM attachtypes"
    );
    $st       = $sq ? $db->fetch_array($sq) : [];
    $total    = (int)($st['total'] ?? 0);
    $enabled  = (int)($st['enabled'] ?? 0);
    $avatars  = (int)($st['avatars'] ?? 0);
    $avgsize  = (int)($st['avgsize'] ?? 0);

    $per_page = 30;
    $pages    = max(1, (int)ceil($total / $per_page));
    $page     = min(max(1, $mybb->get_input('page', MyBB::INPUT_INT)), $pages);
    $start    = ($page - 1) * $per_page;

    echo '<div class="container mt-3 mb-4 at">';
    echo render_list_header();
    echo render_stats_cards($total, $enabled, $avatars, $avgsize);
    echo render_attachment_types_table($start, $per_page, $page);

    if ($pages > 1) {
        echo '<div class="d-flex justify-content-center mt-3">'
           . multipage($total, $per_page, $page, AT_URL . '&amp;page={page}')
           . '</div>';
    }
    echo '</div>';

    echo render_delete_modal();
    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// DATA HELPERS
// ═══════════════════════════════════════════════════════════

function validate_attachment_type_input(array $input): array
{
    // Оба поля обязательны. Раньше условия были перепутаны: ошибка появлялась
    // только если пустые ОБА, и тип без MIME или без расширения сохранялся.
    global $lang;

    $errors = [];
    if (trim((string)($input['extension'] ?? '')) === '' || trim((string)($input['extension'] ?? ''), '. ') === '') {
        $errors[] = $lang->attachment_types['err_no_extension'];
    }
    if (trim((string)($input['mimetype'] ?? '')) === '') {
        $errors[] = $lang->attachment_types['err_no_mime'];
    }
    return $errors;
}

function prepare_attachment_type_data(array $input): array
{
    global $mybb;

    $extension = strtolower(ltrim(trim((string)($input['extension'] ?? '')), '.'));
    $icon      = trim((string)($input['icon'] ?? ''));
    // Путь-заглушку по умолчанию не сохраняем. Раньше это сравнение ошибочно
    // делалось с MIME-типом, а не с иконкой.
    if ($icon === AT_DEFAULT_ICON) {
        $icon = '';
    }

    $processed = [];
    foreach (['groups', 'forums'] as $key) {
        $processed[$key] = process_selection_field((string)($input[$key] ?? ''), $input['select'][$key] ?? []);
    }

    return [
        'name'          => trim((string)($input['name'] ?? '')),
        'mimetype'      => trim((string)($input['mimetype'] ?? '')),
        'extension'     => $extension,
        // Раньше «?: ""» — пустая строка в INT-колонку (ошибка в strict-режиме MySQL)
        'maxsize'       => max(0, $mybb->get_input('maxsize', MyBB::INPUT_INT)),
        'icon'          => $icon,
        'enabled'       => $mybb->get_input('enabled', MyBB::INPUT_INT) ? 1 : 0,
        'forcedownload' => $mybb->get_input('forcedownload', MyBB::INPUT_INT) ? 1 : 0,
        'groups'        => $processed['groups'],
        'forums'        => $processed['forums'],
        'avatarfile'    => $mybb->get_input('avatarfile', MyBB::INPUT_INT) ? 1 : 0,
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

// ═══════════════════════════════════════════════════════════
// LIST RENDERING
// ═══════════════════════════════════════════════════════════

function render_list_header(): string
{
    global $lang;
    $L = array_map('at_e', $lang->attachment_types);

    return <<<HTML
<div class="at-card mb-3">
    <div class="at-head">
        <span class="at-head-icon"><i class="fa-solid fa-paperclip"></i></span>
        <div>
            <h1 class="at-title">{$L['page_title']}</h1>
            <div class="at-sub">{$L['sub_list']}</div>
        </div>
        <a href="index.php?act=attachment_types&amp;action=add" class="btn btn-primary rounded-pill px-3 ms-auto"><i class="fa-solid fa-plus me-1"></i>{$L['btn_add']}</a>
    </div>
</div>
HTML;
}

function render_stats_cards(int $total, int $enabled, int $avatars, int $avgsize): string
{
    global $lang;

    $cards = [
        ['fa-layer-group',    'ic-blue',   $lang->attachment_types['stat_total'],     number_format($total)],
        ['fa-toggle-on',      'ic-green',  $lang->attachment_types['stat_enabled'],   number_format($enabled)],
        ['fa-toggle-off',     'ic-slate',  $lang->attachment_types['stat_disabled'],  number_format($total - $enabled)],
        ['fa-weight-hanging', 'ic-amber',  $lang->attachment_types['stat_avg_limit'], $avgsize > 0 ? format_size_kb($avgsize) : '—'],
    ];
    $html = '<div class="row g-3 mb-3">';
    foreach ($cards as [$ic, $cls, $label, $val]) {
        $html .= '<div class="col-6 col-md-3"><div class="at-card at-stat"><span class="at-stat-icon ' . $cls . '"><i class="fa-solid ' . $ic . '"></i></span>'
               . '<div><div class="at-stat-label">' . at_e($label) . '</div><div class="at-stat-value">' . $val . '</div></div></div></div>';
    }
    return $html . '</div>';
}

/** Возвращает [иконка, текст]; текст — «сырой», экранировать при выводе */
function render_scope(string $value, string $all, string $none, string $count_tpl): array
{
    if ($value === '-1') return ['fa-globe', $all];
    if ($value === '')   return ['fa-ban', $none];
    return ['fa-list-check', ags_fmt($count_tpl, count(array_filter(explode(',', $value))))];
}

function render_attachment_types_table(int $start, int $per_page, int $page): string
{
    global $db, $mybb, $lang;

    $L = array_map('at_e', $lang->attachment_types);

    $query = $db->sql_query_prepared('SELECT * FROM attachtypes ORDER BY extension LIMIT ?, ?', [$start, $per_page]);
    $key   = at_e($mybb->post_code);

    $html = <<<HTML
<div class="at-card overflow-hidden">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-bottom">
        <span class="fw-bold"><i class="fa-solid fa-table-list me-2 text-body-secondary"></i>{$L['sec_all_types']}</span>
        <div class="position-relative at-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" class="form-control form-control-sm" id="atFilter" placeholder="{$L['ph_filter']}">
        </div>
    </div>
    <div class="table-responsive">
    <table class="table at-table">
        <thead>
            <tr>
                <th><i class="fa-solid fa-file"></i>{$L['th_type']}</th>
                <th><i class="fa-solid fa-code"></i>{$L['th_mime']}</th>
                <th class="text-center"><i class="fa-solid fa-power-off"></i>{$L['th_status']}</th>
                <th class="text-center"><i class="fa-solid fa-weight-hanging"></i>{$L['th_max_size']}</th>
                <th><i class="fa-solid fa-user-group"></i>{$L['th_access']}</th>
                <th class="text-center"><i class="fa-solid fa-flag"></i>{$L['th_flags']}</th>
                <th class="text-end"><i class="fa-solid fa-bolt"></i>{$L['th_actions']}</th>
            </tr>
        </thead>
        <tbody>
HTML;

    $rows = 0;
    while ($query && ($type = $db->fetch_array($query))) {
        $rows++;
        $atid = (int)$type['atid'];
        $on   = (int)$type['enabled'] === 1;
        $ext  = at_e($type['extension'] ?? '');
        $name = htmlspecialchars_uni((string)($type['name'] ?? ''));
        $mime = (string)($type['mimetype'] ?? '') !== '' ? '<span class="at-mime">' . at_e($type['mimetype']) . '</span>' : '<span class="text-body-secondary">—</span>';
        $icon = render_type_icon($type);
        $size = format_size_kb($type['maxsize'] ?? 0);

        [$gi, $gt] = render_scope((string)($type['groups'] ?? ''), $lang->attachment_types['scope_all_groups'], $lang->attachment_types['scope_none_groups'], $lang->attachment_types['scope_n_groups']);
        [$fi, $ft] = render_scope((string)($type['forums'] ?? ''), $lang->attachment_types['scope_all_forums'], $lang->attachment_types['scope_none_forums'], $lang->attachment_types['scope_n_forums']);

        $status = $on
            ? '<span class="at-pill p-on"><i class="fa-solid fa-circle-check"></i>' . $L['status_enabled'] . '</span>'
            : '<span class="at-pill p-off"><i class="fa-solid fa-circle-xmark"></i>' . $L['status_disabled'] . '</span>';

        $fd = (int)($type['forcedownload'] ?? 0) === 1;
        $av = (int)($type['avatarfile'] ?? 0) === 1;
        $flags = '<span class="at-flags">'
               . '<span class="at-flag ic-blue' . ($fd ? '' : ' is-muted') . '" title="' . ($fd ? $L['tip_force_download'] : $L['tip_opens_browser']) . '"><i class="fa-solid fa-download"></i></span>'
               . '<span class="at-flag ic-purple' . ($av ? '' : ' is-muted') . '" title="' . ($av ? $L['tip_avatar_yes'] : $L['tip_avatar_no']) . '"><i class="fa-solid fa-circle-user"></i></span>'
               . '</span>';

        $toggle_icon  = $on ? 'fa-toggle-on' : 'fa-toggle-off';
        $toggle_title = $on ? $L['tip_disable'] : $L['tip_enable'];
        $search = strtolower($ext . ' ' . strip_tags($name) . ' ' . (string)($type['mimetype'] ?? ''));

        $html .= '<tr class="' . ($on ? '' : 'is-off') . '" data-search="' . at_e($search) . '">'
            . '<td><div class="d-flex align-items-center gap-2">' . $icon . '<div><div class="at-ext">.' . $ext . '</div>'
            . ($name !== '' ? '<div class="at-help">' . $name . '</div>' : '') . '</div></div></td>'
            . '<td>' . $mime . '</td>'
            . '<td class="text-center">' . $status . '</td>'
            . '<td class="text-center"><span class="at-pill p-size"><i class="fa-solid fa-hard-drive"></i>' . $size . '</span></td>'
            . '<td><div class="at-help"><i class="fa-solid ' . $gi . ' me-1"></i>' . at_e($gt) . '</div><div class="at-help"><i class="fa-solid ' . $fi . ' me-1"></i>' . at_e($ft) . '</div></td>'
            . '<td class="text-center">' . $flags . '</td>'
            . '<td class="text-end text-nowrap">'
            .   '<a class="at-act" href="index.php?act=attachment_types&amp;action=edit&amp;atid=' . $atid . '" title="' . $L['tip_edit'] . '"><i class="fa-solid fa-pen"></i></a>'
            .   '<form method="post" action="index.php?act=attachment_types&amp;action=toggle_status" class="d-inline">'
            .     '<input type="hidden" name="my_post_key" value="' . $key . '"><input type="hidden" name="atid" value="' . $atid . '"><input type="hidden" name="page" value="' . $page . '">'
            .     '<button type="submit" class="at-act' . ($on ? '' : ' power-on') . '" title="' . $toggle_title . '"><i class="fa-solid ' . $toggle_icon . '"></i></button>'
            .   '</form>'
            .   '<button type="button" class="at-act danger" title="' . $L['tip_delete'] . '" data-atid="' . $atid . '" data-ext=".' . $ext . '" data-bs-toggle="modal" data-bs-target="#atDeleteModal"><i class="fa-solid fa-trash"></i></button>'
            . '</td></tr>';
    }

    if ($rows === 0) {
        $html .= '<tr><td colspan="7"><div class="at-empty"><i class="fa-solid fa-paperclip"></i><div class="fw-semibold">' . $L['empty_title'] . '</div>'
               . '<div class="small mb-3">' . $L['empty_text'] . '</div>'
               . '<a href="index.php?act=attachment_types&amp;action=add" class="btn btn-sm btn-primary rounded-pill px-3"><i class="fa-solid fa-plus me-1"></i>' . $L['btn_add'] . '</a></div></td></tr>';
    }

    $html .= '<tr id="atNoMatch" hidden><td colspan="7"><div class="at-empty"><i class="fa-solid fa-magnifying-glass"></i><div class="fw-semibold">' . $L['no_match'] . '</div></div></td></tr>';
    $html .= '</tbody></table></div></div>';

    return $html;
}

function render_delete_modal(): string
{
    global $mybb, $lang;
    $key = at_e($mybb->post_code);
    $L   = array_map('at_e', $lang->attachment_types);

    // Расширение подставляет JS в <span id="atDeleteExt">
    $delete_q = ags_fmt($L['modal_delete_q'], '<span class="font-monospace" id="atDeleteExt"></span>');

    return <<<HTML
<div class="modal fade at" id="atDeleteModal" tabindex="-1" aria-labelledby="atDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 rounded-4 overflow-hidden" method="post" action="index.php?act=attachment_types&amp;action=delete">
            <input type="hidden" name="my_post_key" value="{$key}">
            <input type="hidden" name="atid" id="atDeleteId" value="">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="atDeleteModalLabel"><i class="fa-solid fa-trash me-2"></i>{$L['modal_delete_title']}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{$L['aria_close']}"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-3 align-items-start">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger flex-shrink-0 at-del-icon"><i class="fa-solid fa-file-circle-xmark"></i></span>
                    <div>
                        <div class="fw-semibold">{$delete_q}</div>
                        <div class="small text-body-secondary">{$L['modal_delete_text']}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>{$L['btn_cancel']}</button>
                <button type="submit" class="btn btn-danger rounded-pill px-3"><i class="fa-solid fa-trash me-1"></i>{$L['btn_delete']}</button>
            </div>
        </form>
    </div>
</div>
HTML;
}
