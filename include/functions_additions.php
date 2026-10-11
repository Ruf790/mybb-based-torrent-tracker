<?php

declare(strict_types=1);

/**
 * functions_additions.php — общие хелперы, подключается через
 *   require_once INC_PATH . '/functions_additions.php';
 *
 * Сейчас: строки для JS (window.AGS_LANG) — ags_js_lang() и avatar_js_lang().
 * Нужен на usercp.php, member.php, edituser.php (scripts/avatar_upload.js).
 */

if (!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}

/**
 * Lang strings for page JS → window.AGS_LANG.
 * $section: lang section, e.g. 'global' or 'usercp' (same as $lang->load('<section>')).
 * $map:     js key (no prefix) => key in $lang->{$section}.
 * A missing key is sent as '' — the JS t() helper then shows its English fallback.
 */
if (!function_exists('ags_js_lang')) {
function ags_js_lang(string $section, array $map): string
{
    global $lang;

    $src = (isset($lang->{$section}) && is_array($lang->{$section})) ? $lang->{$section} : [];
    $out = [];
    foreach ($map as $jsKey => $langKey) {
        $out[$jsKey] = (string)($src[$langKey] ?? '');
    }

    return '<script>window.AGS_LANG = Object.assign(window.AGS_LANG || {}, '
         . json_encode($out, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
         . ');</script>';
}
}

/**
 * Strings for scripts/avatar_upload.js. Output it before the <script src="avatar_upload.js">.
 */
if (!function_exists('avatar_js_lang')) {
function avatar_js_lang(): string
{
    return ags_js_lang('global', [
        'avatar_bad_type'      => 'js_avatar_bad_type',
        'avatar_too_big'       => 'js_avatar_too_big',
        'avatar_no_profile'    => 'js_avatar_no_profile',
        'avatar_alt'           => 'js_avatar_alt',
        'avatar_updated'       => 'js_avatar_updated',
        'avatar_server_error'  => 'js_avatar_server_error',
        'avatar_uploading'     => 'js_avatar_uploading',
        'avatar_unknown_error' => 'js_avatar_unknown_error',
        'avatar_error'         => 'js_avatar_error',
        'avatar_upload_error'  => 'js_avatar_upload_error',
    ]);
}
}
