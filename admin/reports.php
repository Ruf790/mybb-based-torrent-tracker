<?php

declare(strict_types=1);

/**
 * reports.php - Report Management System (Staff Panel)
 */

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

// В админке init может не определять эти константы
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS', 86400);
defined('TIMENOW')        || define('TIMENOW', time());

require_once(INC_PATH . '/class_parser.php');

$parser = new postParser();
$parser_options = [
    'allow_html'       => 0,
    'allow_mycode'     => 1,
    'allow_smilies'    => 1,
    'allow_imgcode'    => 1,
    'allow_videocode'  => 1,
    'filter_badwords'  => 1,
];

$action    = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'list';
$report_id = (int)($_GET['id'] ?? 0);

// ==================== КОНСТАНТЫ ====================

const REPORT_TYPES = ['torrent', 'user', 'comment', 'forumpost'];

const RULES_MAP = [
    'rule_1' => ['text' => 'Rule 1: No spamming or advertising',    'color' => 'bg-danger',  'icon' => 'fa-bullhorn'],
    'rule_2' => ['text' => 'Rule 2: No offensive language',         'color' => 'bg-danger',  'icon' => 'fa-comment-slash'],
    'rule_3' => ['text' => 'Rule 3: No harassment or bullying',     'color' => 'bg-danger',  'icon' => 'fa-users-slash'],
    'rule_4' => ['text' => 'Rule 4: Stay on topic',                 'color' => 'bg-warning', 'icon' => 'fa-signs-post'],
    'rule_5' => ['text' => 'Rule 5: No warez or illegal content',   'color' => 'bg-danger',  'icon' => 'fa-ban'],
    'rule_6' => ['text' => 'Rule 6: Respect other members',         'color' => 'bg-warning', 'icon' => 'fa-handshake'],
    'rule_7' => ['text' => 'Rule 7: No double posting',             'color' => 'bg-info',    'icon' => 'fa-copy'],
    'rule_8' => ['text' => 'Rule 8: Use appropriate language',      'color' => 'bg-warning', 'icon' => 'fa-language'],
];

const REASON_RECOMMENDATIONS = [
    'comment' => [
        'spam'          => 'Consider deleting the comment and warning the user about spam policies.',
        'offensive'     => 'Review the language used and consider deletion with a user warning.',
        'harassment'    => 'Immediate action recommended. Delete comment and consider user ban.',
        'hate_speech'   => 'Zero tolerance policy. Delete immediately and consider permanent ban.',
        'inappropriate' => 'Review content against community guidelines. Edit or delete as needed.',
        'spoiler'       => 'Consider adding spoiler tags or moving to appropriate section.',
        'misinformation'=> 'Verify information and add correction notice if false.',
        'off_topic'     => 'Move to appropriate thread or delete if completely irrelevant.',
        'personal_info' => 'Delete immediately. Do not share personal information.',
        'other'         => 'Review based on description provided.',
    ],
    'torrent' => [
        'copyright'     => 'Verify copyright claim. Remove torrent if infringement is confirmed.',
        'malware'       => 'Scan files for malware. Remove immediately if infected.',
        'fake'          => 'Verify content authenticity. Remove if fake or mislabeled.',
        'broken'        => 'Check tracker and seed status. Mark as broken if dead.',
        'inappropriate' => 'Review against content policies. Remove if violates guidelines.',
        'other'         => 'Review based on description provided.',
    ],
];

const REPORT_MESSAGES = [
    'success' => [
        'resolved'        => 'Report marked as resolved',
        'deleted'         => 'Report deleted',
        'comment_deleted' => 'Comment deleted and report resolved',
        'post_deleted'    => 'Forum post deleted and report resolved',
        'post_gone'       => 'Forum post was already gone, report resolved',
        'ignored'         => 'Report ignored and closed',
        'cleared'         => 'Old resolved reports cleared',
    ],
    'error' => [
        'invalid_id'        => 'Invalid report ID',
        'not_found'         => 'Report not found',
        'invalid_action'    => 'Invalid action',
        'no_user'           => 'This report has no user to act on',
        'csrf'              => 'Security token expired, reload the page and try again',
        'bad_method'        => 'Actions must be submitted from the panel',
        'wrong_type'        => 'This action does not match the report type',
        'comment_not_found' => 'Comment not found',
        'post_error'        => 'Could not delete the forum post, check the error log',
    ],
];

// ==================== МАППИНГИ ПРИЧИН ====================

function get_report_reasons_map(?string $type = null): array
{
    static $maps = null;

    if ($maps === null) {
        $maps = [
            'comment' => [
                'spam'          => ['text' => 'Spam / Advertising',           'color' => 'bg-danger',    'icon' => 'fa-bullhorn',            'severity' => 'high',    'category' => 'Content Issues'],
                'offensive'     => ['text' => 'Offensive / Abusive Language', 'color' => 'bg-danger',    'icon' => 'fa-comment-slash',       'severity' => 'high',    'category' => 'Content Issues'],
                'harassment'    => ['text' => 'Harassment / Bullying',        'color' => 'bg-danger',    'icon' => 'fa-user-slash',          'severity' => 'high',    'category' => 'Content Issues'],
                'hate_speech'   => ['text' => 'Hate Speech / Discrimination', 'color' => 'bg-danger',    'icon' => 'fa-triangle-exclamation','severity' => 'high',    'category' => 'Content Issues'],
                'inappropriate' => ['text' => 'Inappropriate Content',        'color' => 'bg-warning',   'icon' => 'fa-eye-slash',           'severity' => 'medium',  'category' => 'Content Issues'],
                'spoiler'       => ['text' => 'Spoiler / Leaked Content',     'color' => 'bg-info',      'icon' => 'fa-mask',                'severity' => 'low',     'category' => 'Other Issues'],
                'misinformation'=> ['text' => 'Misinformation / Fake News',   'color' => 'bg-warning',   'icon' => 'fa-circle-exclamation',  'severity' => 'medium',  'category' => 'Other Issues'],
                'off_topic'     => ['text' => 'Off Topic / Irrelevant',       'color' => 'bg-secondary', 'icon' => 'fa-signs-post',          'severity' => 'low',     'category' => 'Other Issues'],
                'personal_info' => ['text' => 'Personal Information',         'color' => 'bg-danger',    'icon' => 'fa-id-card',             'severity' => 'high',    'category' => 'Other Issues'],
                'other'         => ['text' => 'Other Reason',                 'color' => 'bg-dark',      'icon' => 'fa-ellipsis',            'severity' => 'unknown', 'category' => 'Other Issues'],
            ],
            'torrent' => [
                'copyright'     => ['text' => 'Copyright Infringement',  'color' => 'bg-danger',  'icon' => 'fa-copyright',  'severity' => 'high',    'category' => 'Legal Issues'],
                'malware'       => ['text' => 'Malware/Virus',           'color' => 'bg-danger',  'icon' => 'fa-bug',        'severity' => 'high',    'category' => 'Security Issues'],
                'fake'          => ['text' => 'Fake/Incorrect Content',  'color' => 'bg-warning', 'icon' => 'fa-ban',        'severity' => 'medium',  'category' => 'Content Issues'],
                'broken'        => ['text' => 'Broken/Dead Torrent',     'color' => 'bg-info',    'icon' => 'fa-link-slash', 'severity' => 'low',     'category' => 'Technical Issues'],
                'inappropriate' => ['text' => 'Inappropriate Content',   'color' => 'bg-warning', 'icon' => 'fa-eye-slash',  'severity' => 'medium',  'category' => 'Content Issues'],
                'other'         => ['text' => 'Other Reason',            'color' => 'bg-dark',    'icon' => 'fa-ellipsis',   'severity' => 'unknown', 'category' => 'Other Issues'],
            ],
            'forumpost' => [
                'spam'           => ['text' => 'Spam / Advertising',           'color' => 'bg-danger',    'icon' => 'fa-bullhorn',            'severity' => 'high',    'category' => 'Content Violations'],
                'offensive'      => ['text' => 'Offensive / Abusive Language', 'color' => 'bg-danger',    'icon' => 'fa-comment-slash',       'severity' => 'high',    'category' => 'Content Violations'],
                'harassment'     => ['text' => 'Harassment / Bullying',        'color' => 'bg-danger',    'icon' => 'fa-user-slash',          'severity' => 'high',    'category' => 'Content Violations'],
                'hate_speech'    => ['text' => 'Hate Speech / Discrimination', 'color' => 'bg-danger',    'icon' => 'fa-triangle-exclamation','severity' => 'high',    'category' => 'Content Violations'],
                'explicit'       => ['text' => 'Explicit / Adult Content',     'color' => 'bg-danger',    'icon' => 'fa-eye-slash',           'severity' => 'high',    'category' => 'Content Violations'],
                'illegal'        => ['text' => 'Illegal Content / Warez',      'color' => 'bg-danger',    'icon' => 'fa-ban',                 'severity' => 'high',    'category' => 'Content Violations'],
                'off_topic'      => ['text' => 'Off Topic / Wrong Forum',      'color' => 'bg-warning',   'icon' => 'fa-signs-post',          'severity' => 'medium',  'category' => 'Forum Rules'],
                'double_post'    => ['text' => 'Double Post / Cross-Posting',  'color' => 'bg-info',      'icon' => 'fa-copy',                'severity' => 'low',     'category' => 'Forum Rules'],
                'flame'          => ['text' => 'Flaming / Trolling',           'color' => 'bg-warning',   'icon' => 'fa-fire',                'severity' => 'medium',  'category' => 'Forum Rules'],
                'personal_attack'=> ['text' => 'Personal Attack',              'color' => 'bg-danger',    'icon' => 'fa-user-slash',          'severity' => 'high',    'category' => 'Forum Rules'],
                'spoiler'        => ['text' => 'Unmarked Spoilers',            'color' => 'bg-warning',   'icon' => 'fa-mask',                'severity' => 'medium',  'category' => 'Forum Rules'],
                'copyright'      => ['text' => 'Copyright Infringement',       'color' => 'bg-danger',    'icon' => 'fa-copyright',           'severity' => 'high',    'category' => 'Other Issues'],
                'personal_info'  => ['text' => 'Personal Information',         'color' => 'bg-danger',    'icon' => 'fa-id-card',             'severity' => 'high',    'category' => 'Other Issues'],
                'malware'        => ['text' => 'Malware Link',                 'color' => 'bg-danger',    'icon' => 'fa-bug',                 'severity' => 'high',    'category' => 'Other Issues'],
                'scam'           => ['text' => 'Scam / Fraud',                 'color' => 'bg-danger',    'icon' => 'fa-skull-crossbones',    'severity' => 'high',    'category' => 'Other Issues'],
                'other'          => ['text' => 'Other Reason',                 'color' => 'bg-dark',      'icon' => 'fa-ellipsis',            'severity' => 'unknown', 'category' => 'Other Issues'],
                // Forum rules
                'rule_1' => array_merge(RULES_MAP['rule_1'], ['severity' => 'high',   'category' => 'Forum Rules']),
                'rule_2' => array_merge(RULES_MAP['rule_2'], ['severity' => 'high',   'category' => 'Forum Rules']),
                'rule_3' => array_merge(RULES_MAP['rule_3'], ['severity' => 'high',   'category' => 'Forum Rules']),
                'rule_4' => array_merge(RULES_MAP['rule_4'], ['severity' => 'medium', 'category' => 'Forum Rules']),
                'rule_5' => array_merge(RULES_MAP['rule_5'], ['severity' => 'high',   'category' => 'Forum Rules']),
                'rule_6' => array_merge(RULES_MAP['rule_6'], ['severity' => 'medium', 'category' => 'Forum Rules']),
                'rule_7' => array_merge(RULES_MAP['rule_7'], ['severity' => 'low',    'category' => 'Forum Rules']),
                'rule_8' => array_merge(RULES_MAP['rule_8'], ['severity' => 'medium', 'category' => 'Forum Rules']),
            ],
            'user' => [
                'spam'          => ['text' => 'Spam Account',            'color' => 'bg-danger',  'icon' => 'fa-user-slash',       'severity' => 'high',    'category' => 'Account Issues',  'description' => 'User is posting spam content',                       'recommended_action' => 'Review user posts and consider temporary suspension'],
                'harassment'    => ['text' => 'Harassment/Bullying',     'color' => 'bg-danger',  'icon' => 'fa-ban',              'severity' => 'high',    'category' => 'Behavior Issues', 'description' => 'User is harassing or bullying others',               'recommended_action' => 'Immediate warning or temporary ban'],
                'fake'          => ['text' => 'Fake Account',            'color' => 'bg-warning', 'icon' => 'fa-mask',             'severity' => 'medium',  'category' => 'Account Issues',  'description' => 'User is pretending to be someone else',              'recommended_action' => 'Verify identity and take appropriate action'],
                'impersonation' => ['text' => 'Impersonation',           'color' => 'bg-danger',  'icon' => 'fa-id-badge',         'severity' => 'high',    'category' => 'Account Issues',  'description' => 'User is impersonating another user',                 'recommended_action' => 'Immediate account suspension'],
                'inappropriate' => ['text' => 'Inappropriate Profile',   'color' => 'bg-warning', 'icon' => 'fa-eye-slash',        'severity' => 'medium',  'category' => 'Content Issues',  'description' => 'User has inappropriate profile content',             'recommended_action' => 'Request profile cleanup or temporary restriction'],
                'scam'          => ['text' => 'Scam/Fraud',              'color' => 'bg-danger',  'icon' => 'fa-skull-crossbones', 'severity' => 'high',    'category' => 'Legal Issues',    'description' => 'User is involved in scams or fraud',                 'recommended_action' => 'Immediate ban and report if necessary'],
                'copyright'     => ['text' => 'Copyright Infringement',  'color' => 'bg-danger',  'icon' => 'fa-copyright',        'severity' => 'high',    'category' => 'Legal Issues',    'description' => 'User is sharing copyrighted content',                'recommended_action' => 'Remove infringing content and issue warning'],
                'malware'       => ['text' => 'Malware Distribution',    'color' => 'bg-danger',  'icon' => 'fa-bug',              'severity' => 'high',    'category' => 'Security Issues', 'description' => 'User is distributing malware/viruses',               'recommended_action' => 'Immediate ban and content removal'],
                'racism'        => ['text' => 'Racism/Hate Speech',      'color' => 'bg-danger',  'icon' => 'fa-comment-slash',    'severity' => 'high',    'category' => 'Behavior Issues', 'description' => 'User is posting racist or hateful content',          'recommended_action' => 'Immediate suspension or ban'],
                'threats'       => ['text' => 'Threats/Violence',        'color' => 'bg-danger',  'icon' => 'fa-triangle-exclamation', 'severity' => 'high', 'category' => 'Behavior Issues', 'description' => 'User is making threats or promoting violence',      'recommended_action' => 'Immediate permanent ban'],
                'underage'      => ['text' => 'Underage User',           'color' => 'bg-warning', 'icon' => 'fa-child',            'severity' => 'medium',  'category' => 'Account Issues',  'description' => 'User appears to be underage',                       'recommended_action' => 'Suspend until age verification'],
                'cheating'      => ['text' => 'Cheating/Gaming System',  'color' => 'bg-warning', 'icon' => 'fa-gamepad',          'severity' => 'medium',  'category' => 'Behavior Issues', 'description' => 'User is cheating or exploiting the system',          'recommended_action' => 'Reset stats and issue warning'],
                'other'         => ['text' => 'Other Reason',            'color' => 'bg-dark',    'icon' => 'fa-ellipsis',         'severity' => 'unknown', 'category' => 'Other Issues',    'description' => 'Select for other reasons',                           'recommended_action' => 'Review report description carefully'],
            ],
        ];
    }

    if ($type === null) {
        return $maps;
    }

    // Алиасы
    $type = ($type === 'forum_post') ? 'forumpost' : $type;

    return $maps[$type] ?? [];
}

// ==================== ХЕЛПЕРЫ ====================

function rp_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rp_get(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? $v : '';
}

function rp_post_key(): string
{
    return (string)generate_post_check();
}

function truncateString(string $string, int $length = 30): string
{
    return mb_strlen($string) > $length
        ? mb_substr($string, 0, $length) . '...'
        : $string;
}

function getTypeColor(string $type): string
{
    return match ($type) {
        'torrent'   => 'primary',
        'comment'   => 'info',
        'user'      => 'warning',
        'forumpost' => 'success',
        default     => 'secondary',
    };
}

function getTypeIcon(string $type): string
{
    return match ($type) {
        'torrent'   => 'fa-magnet',
        'comment'   => 'fa-comment',
        'user'      => 'fa-user',
        'forumpost' => 'fa-comments',
        default     => 'fa-file',
    };
}

function getTypeLabel(string $type): string
{
    return match ($type) {
        'torrent'   => 'Torrent',
        'comment'   => 'Comment',
        'user'      => 'User',
        'forumpost' => 'Forum post',
        default     => ucfirst($type),
    };
}

/** bg-danger → danger, bg-dark → secondary */
function rp_tone(string $bg_class): string
{
    $tone = str_replace('bg-', '', $bg_class);
    return in_array($tone, ['primary', 'success', 'warning', 'danger', 'info', 'secondary'], true) ? $tone : 'secondary';
}

function rp_severity_tone(string $sev): string
{
    return match ($sev) { 'high' => 'danger', 'medium' => 'warning', 'low' => 'info', default => 'secondary' };
}

function rp_severity_icon(string $sev): string
{
    return match ($sev) { 'high' => 'fa-fire', 'medium' => 'fa-hourglass-half', 'low' => 'fa-circle-info', default => 'fa-circle-question' };
}

function rp_type_chip(string $type): string
{
    return '<span class="rp-chip rp-tone-' . getTypeColor($type) . '"><i class="fa-solid ' . getTypeIcon($type) . '"></i>' . rp_h(getTypeLabel($type)) . '</span>';
}

function rp_reason_chip(?array $reason_data, string $raw_reason, int $max = 0): string
{
    if (!$reason_data) {
        $text = $max > 0 ? truncateString($raw_reason, $max) : $raw_reason;
        return '<span class="rp-chip rp-tone-secondary" title="' . rp_h($raw_reason) . '"><i class="fa-solid fa-circle-question"></i>' . rp_h($text) . '</span>';
    }

    $text = $max > 0 ? truncateString($reason_data['text'], $max) : $reason_data['text'];
    return '<span class="rp-chip rp-tone-' . rp_tone($reason_data['color']) . '" title="' . rp_h($reason_data['text']) . '">'
        . '<i class="fa-solid ' . rp_h($reason_data['icon']) . '"></i>' . rp_h($text) . '</span>';
}

function rp_severity_chip(string $sev): string
{
    if ($sev === 'unknown' || $sev === '') {
        return '';
    }
    return '<span class="rp-chip rp-chip-outline rp-tone-' . rp_severity_tone($sev) . '"><i class="fa-solid ' . rp_severity_icon($sev) . '"></i>' . ucfirst($sev) . ' priority</span>';
}

function renderStatusBadge(bool $resolved): string
{
    return $resolved
        ? '<span class="rp-chip rp-tone-success"><i class="fa-solid fa-circle-check"></i>Resolved</span>'
        : '<span class="rp-chip rp-tone-warning"><i class="fa-solid fa-clock"></i>Pending</span>';
}

function rp_user_cell(int $id, ?string $name, string $tone, string $empty_label, string $empty_icon = 'fa-user-secret'): string
{
    if ($id <= 0) {
        return '<span class="rp-muted"><i class="fa-solid ' . $empty_icon . ' me-1"></i>' . rp_h($empty_label) . '</span>';
    }

    $name    = ($name !== null && $name !== '') ? $name : 'User #' . $id;
    $initial = mb_strtoupper(mb_substr($name, 0, 1));

    return '<a class="rp-user" href="user-' . $id . '.html" target="_blank" rel="noopener">'
        . '<span class="rp-avatar rp-tone-' . $tone . '">' . rp_h($initial) . '</span>'
        . '<span class="rp-user-name">' . rp_h($name) . '</span></a>';
}

function rp_confirm_attrs(array $c): string
{
    $map = ['title' => 'confirm-title', 'text' => 'confirm-text', 'icon' => 'confirm-icon', 'btn' => 'confirm-btn', 'variant' => 'confirm-variant'];
    $out = '';
    foreach ($map as $k => $attr) {
        if (isset($c[$k]) && $c[$k] !== '') {
            $out .= ' data-' . $attr . '="' . rp_h($c[$k]) . '"';
        }
    }
    return $out;
}

/** Hidden fields that tell the handler where to redirect after the action (PRG). */
function rp_return_fields(string $return): string
{
    $out = '<input type="hidden" name="return" value="' . rp_h($return) . '">';
    foreach (['type', 'status', 'search', 'priority', 'page'] as $k) {
        $v = rp_get($k);
        if ($v !== '') {
            $out .= '<input type="hidden" name="rq[' . $k . ']" value="' . rp_h($v) . '">';
        }
    }
    return $out;
}

/**
 * Small POST form with a single button (resolve / delete / etc.).
 * All state-changing actions go through POST + CSRF.
 */
function rp_action_button(string $do, int $id, array $o = []): string
{
    global $_this_script_;

    $o += [
        'return'    => 'list',
        'label'     => '',
        'icon'      => 'fa-check',
        'tone'      => 'primary',
        'solid'     => false,
        'small'     => true,
        'icon_only' => false,
        'confirm'   => null,
        'ajax'      => false,
        'class'     => '',
        'btn_class' => '',
    ];

    $form_class = trim('rp-inline-form ' . ($o['ajax'] ? 'rp-ajax ' : '') . $o['class']);
    $attrs      = $o['confirm'] ? ' data-confirm="1"' . rp_confirm_attrs($o['confirm']) : '';

    $btn_class = 'rp-btn rp-tone-' . $o['tone']
        . ($o['solid'] ? ' rp-btn-solid' : '')
        . ($o['small'] ? ' rp-btn-sm' : '')
        . ($o['icon_only'] ? ' rp-btn-icon' : '')
        . ($o['btn_class'] ? ' ' . $o['btn_class'] : '');

    $label = $o['icon_only'] ? '' : '<span>' . rp_h($o['label']) . '</span>';
    $aria  = $o['icon_only'] ? ' title="' . rp_h($o['label']) . '" aria-label="' . rp_h($o['label']) . '"' : '';

    return '<form method="post" action="' . rp_h($_this_script_) . '&amp;action=takeaction" class="' . $form_class . '"' . $attrs . '>'
        . '<input type="hidden" name="my_post_key" value="' . rp_h(rp_post_key()) . '">'
        . '<input type="hidden" name="do" value="' . rp_h($do) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . rp_return_fields($o['return'])
        . '<button type="submit" class="' . $btn_class . '" data-id="' . $id . '"' . $aria . '>'
        . '<i class="fa-solid ' . $o['icon'] . '"></i>' . $label . '</button></form>';
}

function rp_link_button(string $href, string $label, string $icon, string $tone = 'secondary', array $o = []): string
{
    $o += ['solid' => false, 'small' => true, 'icon_only' => false, 'blank' => false, 'confirm' => null, 'class' => ''];

    $class = 'rp-btn rp-tone-' . $tone
        . ($o['solid'] ? ' rp-btn-solid' : '')
        . ($o['small'] ? ' rp-btn-sm' : '')
        . ($o['icon_only'] ? ' rp-btn-icon' : '')
        . ($o['class'] ? ' ' . $o['class'] : '');

    $attrs = $o['blank'] ? ' target="_blank" rel="noopener"' : '';
    if ($o['confirm']) {
        $attrs .= ' data-confirm="1"' . rp_confirm_attrs($o['confirm']);
    }
    if ($o['icon_only']) {
        $attrs .= ' title="' . rp_h($label) . '" aria-label="' . rp_h($label) . '"';
    }

    return '<a href="' . rp_h($href) . '" class="' . $class . '"' . $attrs . '><i class="fa-solid ' . $icon . '"></i>'
        . ($o['icon_only'] ? '' : '<span>' . rp_h($label) . '</span>') . '</a>';
}

function rp_fact(string $icon, string $label, string $value_html, string $tone = 'secondary'): string
{
    return '<div class="rp-fact"><span class="rp-ico rp-ico-sm rp-tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<div class="rp-fact-body"><div class="rp-fact-lbl">' . rp_h($label) . '</div><div class="rp-fact-val">' . $value_html . '</div></div></div>';
}

function rp_card_head(string $icon, string $tone, string $title, string $right_html = '', string $subtitle = ''): string
{
    return '<div class="rp-card-head"><span class="rp-ico rp-tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<div class="rp-card-heading"><h2 class="rp-card-title">' . $title . '</h2>'
        . ($subtitle !== '' ? '<div class="rp-card-sub">' . $subtitle . '</div>' : '') . '</div>'
        . ($right_html !== '' ? '<div class="rp-card-tools">' . $right_html . '</div>' : '') . '</div>';
}

function rp_empty(string $icon, string $tone, string $title, string $text = '', string $extra_html = ''): string
{
    return '<div class="rp-empty"><span class="rp-ico rp-ico-lg rp-tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<h3>' . rp_h($title) . '</h3>' . ($text !== '' ? '<p>' . rp_h($text) . '</p>' : '') . $extra_html . '</div>';
}

/** Splits "--- ADMIN NOTES ---" blocks appended by markReportResolved() off the description. */
function rp_split_notes(string $description): array
{
    $parts = preg_split('/\R\R--- ADMIN NOTES ---\R/u', $description) ?: [$description];
    $main  = (string)array_shift($parts);
    return [$main, array_values(array_filter(array_map('trim', $parts), static fn($n) => $n !== ''))];
}

function rp_safe_url(string $url): ?string
{
    return preg_match('~^https?://~i', $url) ? $url : null;
}

/** SQL condition matching "high" severity reasons across all report types. */
function buildHighSeveritySql(string $alias = ''): array
{
    $parts  = [];
    $params = [];

    foreach (get_report_reasons_map() as $type => $reasons) {
        $high = array_keys(array_filter($reasons, static fn($r) => $r['severity'] === 'high'));
        if (!$high) {
            continue;
        }
        $parts[] = '(' . $alias . 'type = ? AND ' . $alias . 'reason IN (' . implode(',', array_fill(0, count($high), '?')) . '))';
        $params[] = $type;
        array_push($params, ...$high);
    }

    return [$parts ? '(' . implode(' OR ', $parts) . ')' : '0', $params];
}

function isAjaxRequest(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function sendResponse(string $code, bool $success = true, array $redirect = ['action' => 'list']): never
{
    global $_this_script_;

    $message = REPORT_MESSAGES[$success ? 'success' : 'error'][$code] ?? ($success ? 'Done' : 'Something went wrong');

    if (isAjaxRequest()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message, 'code' => $code], JSON_THROW_ON_ERROR);
        exit;
    }

    $redirect[$success ? 'success' : 'error'] = $code;

    header('Location: ' . $_this_script_ . '&' . http_build_query($redirect));
    exit;
}

function rp_return_target(int $report_id): array
{
    $ret = is_string($_POST['return'] ?? null) ? $_POST['return'] : 'list';

    $target = match (true) {
        $ret === 'view' && $report_id > 0                       => ['action' => 'view', 'id' => $report_id],
        in_array($ret, ['list', 'pending', 'resolved', 'stats'], true) => ['action' => $ret],
        default                                                 => ['action' => 'list'],
    };

    if ($target['action'] !== 'view' && is_array($_POST['rq'] ?? null)) {
        foreach (['type', 'status', 'search', 'priority', 'page'] as $k) {
            $v = $_POST['rq'][$k] ?? '';
            if (is_string($v) && $v !== '') {
                $target[$k] = $v;
            }
        }
    }

    return $target;
}

function getReportFromDb(int $report_id): array|false
{
    global $db;
    $stmt = $db->sql_query_prepared("SELECT * FROM reports WHERE id = ?", [$report_id]);
    $row  = $stmt ? $db->fetch_array($stmt) : false;
    return $row ?: false;
}

function markReportResolved(int $report_id, string $notes = ''): void
{
    global $db, $CURUSER;

    $suffix = $notes !== '' ? "\n\n--- ADMIN NOTES ---\n" . $notes : '';

    $db->sql_query_prepared(
        "UPDATE reports SET dealtwith = 1, dealtby = ?, updated_at = ?,
         description = CONCAT(COALESCE(description, ''), ?)
         WHERE id = ?",
        [$CURUSER['id'], TIMENOW, $suffix, $report_id]
    );
}

function rp_log(string $text): void
{
    global $CURUSER;
    write_log($text . ' by ' . ($CURUSER['username'] ?? ('User #' . ($CURUSER['id'] ?? 0))));
}

// ==================== ОБРАБОТЧИКИ ДЕЙСТВИЙ ====================

function handleAction(): never
{
    $report_id = (int)($_POST['id'] ?? 0);
    $target    = rp_return_target($report_id);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        sendResponse('bad_method', false, ['action' => 'list']);
    }

    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        sendResponse('csrf', false, $target);
    }

    $do = is_string($_POST['do'] ?? null) ? $_POST['do'] : '';

    if ($do === 'clearold') {
        handleClearOld();
    }

    if ($report_id <= 0) {
        sendResponse('invalid_id', false, $target);
    }

    $report = getReportFromDb($report_id);

    if (!$report) {
        sendResponse('not_found', false, ['action' => 'list']);
    }

    match ($do) {
        'resolve'         => handleResolve($report_id, $target),
        'ignore'          => handleIgnore($report_id, $target),
        'delete'          => handleDelete($report_id, $target),
        'deletecomment'   => handleDeleteComment($report_id, $report, $target),
        'deleteforumpost' => handleDeleteForumPost($report_id, $report, $target),
        'warn_user'       => handleUserRedirect($report, 'warn', $target),
        'ban_user'        => handleUserRedirect($report, 'ban', $target),
        default           => sendResponse('invalid_action', false, $target),
    };
}

function handleResolve(int $report_id, array $target): never
{
    $notes = trim((string)($_POST['notes'] ?? ''));
    markReportResolved($report_id, $notes);
    rp_log("Report #$report_id resolved");
    sendResponse('resolved', true, $target);
}

function handleIgnore(int $report_id, array $target): never
{
    $notes = trim((string)($_POST['notes'] ?? ''));
    markReportResolved($report_id, '[Ignored]' . ($notes !== '' ? ' ' . $notes : ''));
    rp_log("Report #$report_id ignored");
    sendResponse('ignored', true, $target);
}

function handleDelete(int $report_id, array $target): never
{
    global $db;
    $db->sql_query_prepared("DELETE FROM reports WHERE id = ?", [$report_id]);
    rp_log("Report #$report_id deleted");

    // Report no longer exists, never go back to its view
    if (($target['action'] ?? '') === 'view') {
        $target = ['action' => 'list'];
    }
    sendResponse('deleted', true, $target);
}

function handleClearOld(): never
{
    global $db;

    $cutoff = TIMENOW - 30 * DAY_IN_SECONDS;
    $db->sql_query_prepared("DELETE FROM reports WHERE dealtwith = 1 AND updated_at < ?", [$cutoff]);
    $count = (int)$db->affected_rows();

    rp_log("Cleared $count resolved reports older than 30 days");
    sendResponse('cleared', true, ['action' => 'stats']);
}

function handleUserRedirect(array $report, string $kind, array $target): never
{
    $uid = (int)($report['reported_user_id'] ?? 0);

    if ($uid <= 0) {
        sendResponse('no_user', false, $target);
    }

    $url = $kind === 'warn'
        ? 'warn.php?uid=' . $uid . '&reason=' . urlencode('Report #' . $report['id'] . ': ' . $report['reason'])
        : 'bans.php?action=add&uid=' . $uid;

    header('Location: ' . $url);
    exit;
}

function handleDeleteComment(int $report_id, array $report, array $target): never
{
    global $db, $kpscomment;

    if ($report['type'] !== 'comment') {
        sendResponse('wrong_type', false, $target);
    }

    $comment_id = (int)$report['reported_id'];

    $res          = $db->sql_query_prepared('SELECT torrent, user FROM comments WHERE id = ?', [$comment_id]);
    $comment_data = $res ? $db->fetch_array($res) : null;

    if (!$comment_data) {
        sendResponse('comment_not_found', false, $target);
    }

    $torrent_id = (int)$comment_data['torrent'];
    $user_id    = (int)$comment_data['user'];

    // Удаляем вложенные файлы
    $files = $db->simple_select('comment_files', '*', 'comment_id = ' . $comment_id);
    while ($file = $db->fetch_array($files)) {
        if (!empty($file['file_path']) && is_file($file['file_path'])) {
            @unlink($file['file_path']);
        }
    }
    $db->delete_query('comment_files', 'comment_id = ' . $comment_id);
    $db->delete_query('comments', 'id = ' . $comment_id);

    if ($torrent_id > 0 && $db->affected_rows() > 0) {
        $db->sql_query_prepared('UPDATE torrents SET comments = IF(comments > 0, comments - 1, 0) WHERE id = ?', [$torrent_id]);
        if ($user_id > 0) {
            $db->sql_query_prepared('UPDATE users SET comms = IF(comms > 0, comms - 1, 0) WHERE id = ?', [$user_id]);
        }
    }

    if (isset($kpscomment) && $user_id > 0) {
        kps('-', $kpscomment, $user_id);
    }

    markReportResolved($report_id);
    rp_log("Comment #$comment_id deleted via report #$report_id");
    sendResponse('comment_deleted', true, $target);
}

function handleDeleteForumPost(int $report_id, array $report, array $target): never
{
    global $db;

    if ($report['type'] !== 'forumpost') {
        sendResponse('wrong_type', false, $target);
    }

    $post_id    = (int)$report['reported_id'];
    $post_check = $db->sql_query_prepared("SELECT pid FROM posts WHERE pid = ?", [$post_id]);

    if (!$post_check || $db->num_rows($post_check) === 0) {
        markReportResolved($report_id);
        rp_log("Report #$report_id resolved (forum post #$post_id already gone)");
        sendResponse('post_gone', true, $target);
    }

    if (!class_exists('Moderation')) {
        require_once INC_PATH . '/class_moderation.php';
    }

    try {
        $moderation = new Moderation();
        $moderation->delete_post($post_id);
    } catch (Throwable $e) {
        error_log("Error deleting forum post #$post_id: " . $e->getMessage());
        sendResponse('post_error', false, $target);
    }

    markReportResolved($report_id);
    rp_log("Forum post #$post_id deleted via report #$report_id");
    sendResponse('post_deleted', true, $target);
}

// ==================== ДАННЫЕ ====================

function getHeaderStats(): array
{
    global $db;

    [$high_sql, $high_params] = buildHighSeveritySql();

    $res = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(dealtwith = 0), 0)                        AS pending,
                COALESCE(SUM(dealtwith = 0 AND $high_sql), 0)          AS high_pending,
                COALESCE(SUM(added > ?), 0)                            AS new_24h,
                COALESCE(SUM(dealtwith = 1 AND updated_at > ?), 0)     AS resolved_30
         FROM reports",
        [...$high_params, TIMENOW - DAY_IN_SECONDS, TIMENOW - 30 * DAY_IN_SECONDS]
    );

    $row = $res ? $db->fetch_array($res) : null;
    if ($res) {
        $db->free_result($res);
    }

    return array_map('intval', array_merge(
        ['total' => 0, 'pending' => 0, 'high_pending' => 0, 'new_24h' => 0, 'resolved_30' => 0],
        is_array($row) ? $row : []
    ));
}

function getForumPostData(int $post_id, array $report): ?array
{
    global $db;

    $result = $db->sql_query_prepared(
        "SELECT p.*, t.subject AS thread_subject, t.tid AS thread_id, t.views AS thread_views,
                f.name AS forum_name, f.fid AS forum_id,
                u.username AS author_name, u.id AS author_id
         FROM posts p
         LEFT JOIN threads t ON p.tid = t.tid
         LEFT JOIN forums  f ON p.fid = f.fid
         LEFT JOIN users   u ON p.uid = u.id
         WHERE p.pid = ?",
        [$post_id]
    );

    if (!$result || $db->num_rows($result) === 0) {
        return null;
    }

    $data = $db->fetch_array($result);
    $db->free_result($result);

    $data['report_forum_id']  = $report['forum_id']       ?? 0;
    $data['report_thread_id'] = $report['thread_id']      ?? 0;
    $data['rule_violation']   = $report['rule_violation'] ?? '';

    return $data;
}

function parseUserReportDescription(string $description): array
{
    $result = ['formatted_description' => $description, 'additional_info' => '', 'evidence_links' => ''];

    if (!str_contains($description, '===== USER REPORT =====')) {
        return $result;
    }

    foreach (explode('=====', $description) as $section) {
        $section = trim($section);
        if (str_contains($section, 'DESCRIPTION'))            $result['formatted_description'] = trim(str_replace('DESCRIPTION =====', '', $section));
        if (str_contains($section, 'ADDITIONAL INFORMATION')) $result['additional_info']       = trim(str_replace('ADDITIONAL INFORMATION =====', '', $section));
        if (str_contains($section, 'EVIDENCE LINKS'))         $result['evidence_links']        = trim(str_replace('EVIDENCE LINKS =====', '', $section));
    }

    return $result;
}

function buildReportWhereClause(string $type, string $status, string $search, string $priority = ''): array
{
    $where_parts = [];
    $params      = [];

    if ($type !== '' && in_array($type, REPORT_TYPES, true)) {
        $where_parts[] = "r.type = ?";
        $params[]      = $type;
    }

    if ($status === 'pending') {
        $where_parts[] = "r.dealtwith = 0";
    } elseif ($status === 'resolved') {
        $where_parts[] = "r.dealtwith = 1";
    }

    if ($priority === 'high') {
        [$high_sql, $high_params] = buildHighSeveritySql('r.');
        $where_parts[] = $high_sql;
        array_push($params, ...$high_params);
    }

    if ($search !== '') {
        $like          = '%' . $search . '%';
        $where_parts[] = "(r.reason LIKE ? OR r.description LIKE ? OR u1.username LIKE ? OR u2.username LIKE ?)";
        array_push($params, $like, $like, $like, $like);
    }

    $where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

    return [$where_sql, $params];
}

function exportReportsCsv(): never
{
    global $db;

    [$where_sql, $params] = buildReportWhereClause(rp_get('type'), rp_get('status'), trim(rp_get('search')), rp_get('priority'));

    $res = $db->sql_query_prepared(
        "SELECT r.id, r.type, r.reason, r.reported_id, r.description, r.added, r.dealtwith, r.updated_at,
                u1.username AS reporter_name, u2.username AS reported_user_name, u3.username AS dealtby_name
         FROM reports r
         LEFT JOIN users u1 ON r.addedby = u1.id
         LEFT JOIN users u2 ON r.reported_user_id = u2.id
         LEFT JOIN users u3 ON r.dealtby = u3.id
         $where_sql
         ORDER BY r.added DESC LIMIT 10000",
        $params
    );

    // Защита от CSV/formula injection в Excel
    $cell = static function (mixed $v): string {
        $v = (string)$v;
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reports-' . date('Ymd-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Type', 'Reason', 'Item ID', 'Reporter', 'Reported user', 'Filed', 'Status', 'Resolved by', 'Resolved at', 'Description'], ',', '"', '');

    while ($res && ($row = $db->fetch_array($res))) {
        $reason_data = get_report_reasons_map((string)$row['type'])[$row['reason']] ?? null;
        fputcsv($out, array_map($cell, [
            $row['id'],
            getTypeLabel((string)$row['type']),
            $reason_data['text'] ?? $row['reason'],
            $row['reported_id'],
            $row['reporter_name'] ?? '',
            $row['reported_user_name'] ?? '',
            date('Y-m-d H:i', (int)$row['added']),
            $row['dealtwith'] ? 'Resolved' : 'Pending',
            $row['dealtby_name'] ?? '',
            $row['dealtwith'] ? date('Y-m-d H:i', (int)$row['updated_at']) : '',
            preg_replace('/\s+/u', ' ', (string)($row['description'] ?? '')),
        ]), ',', '"', '');
    }

    fclose($out);
    exit;
}

// ==================== ТОЧКА ВХОДА ====================

if ($action === 'takeaction') {
    handleAction();
}

if (rp_get('export') === 'csv') {
    exportReportsCsv();
}

$header_stats = getHeaderStats();

stdhead("Report Management - Admin Panel");
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">';
echo '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>';
echo '<script src="' . $BASEURL . '/scripts/toast.js"></script>';
renderPageStyles();

$tabs = [
    'list'     => ['All reports', 'fa-layer-group', null],
    'pending'  => ['Pending',     'fa-clock',       $header_stats['pending']],
    'resolved' => ['Resolved',    'fa-circle-check', null],
    'stats'    => ['Statistics',  'fa-chart-column', null],
];

?>

<div class="rp-page container mt-3 mb-4">

    <section class="rp-card rp-hero">
        <div class="rp-hero-top">
            <span class="rp-ico rp-ico-lg rp-tone-danger"><i class="fa-solid fa-flag"></i></span>
            <div class="rp-hero-text">
                <h1>Report Management</h1>
                <p>Member reports on torrents, comments, profiles and forum posts</p>
            </div>
            <?php if ($header_stats['high_pending'] > 0): ?>
            <a class="rp-alert-pill rp-tone-danger" href="<?= rp_h($_this_script_) ?>&amp;action=pending&amp;priority=high">
                <span class="rp-dot"></span><?= $header_stats['high_pending'] ?> high priority waiting
            </a>
            <?php endif; ?>
        </div>
        <nav class="rp-tabs" aria-label="Report views">
            <?php foreach ($tabs as $act => [$label, $icon, $count]): ?>
            <a href="<?= rp_h($_this_script_) ?>&amp;action=<?= $act ?>"
               class="rp-tab<?= $action === $act ? ' is-active' : '' ?>"<?= $action === $act ? ' aria-current="page"' : '' ?>>
                <i class="fa-solid <?= $icon ?>"></i><?= $label ?>
                <?php if ($count): ?><span class="rp-count"><?= number_format($count) ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </section>

    <?php if ($action !== 'stats'): ?>
    <div class="rp-kpis">
        <?= rp_kpi('warning', 'fa-inbox',        $header_stats['pending'],      'Pending reports',       $_this_script_ . '&action=pending') ?>
        <?= rp_kpi('danger',  'fa-fire',         $header_stats['high_pending'], 'High priority pending', $_this_script_ . '&action=pending&priority=high') ?>
        <?= rp_kpi('info',    'fa-bolt',         $header_stats['new_24h'],      'New in last 24 hours',  $_this_script_ . '&action=list') ?>
        <?= rp_kpi('success', 'fa-circle-check', $header_stats['resolved_30'],  'Resolved in 30 days',   $_this_script_ . '&action=resolved') ?>
    </div>
    <?php endif; ?>

    <?php
    match ($action) {
        'view'     => showReportDetails($report_id),
        'pending'  => showReportList('pending'),
        'resolved' => showReportList('resolved'),
        'stats'    => showStatistics(),
        default    => showReportList('list'),
    };
    ?>
</div>

<?php renderPageAssets(); ?>

<?php stdfoot(); ?>

<?php
// ==================== ОТОБРАЖЕНИЕ ====================

function rp_kpi(string $tone, string $icon, int $value, string $label, ?string $href = null, string $hint = ''): string
{
    $tag   = $href ? 'a' : 'div';
    $attrs = $href ? ' href="' . rp_h($href) . '"' : '';

    return '<' . $tag . $attrs . ' class="rp-card rp-kpi rp-tone-' . $tone . '">'
        . '<span class="rp-ico rp-ico-md"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<span class="rp-kpi-text"><span class="rp-kpi-val">' . number_format($value) . '</span>'
        . '<span class="rp-kpi-lbl">' . rp_h($label) . '</span>'
        . ($hint !== '' ? '<span class="rp-kpi-hint">' . rp_h($hint) . '</span>' : '')
        . '</span></' . $tag . '>';
}

function showReportList(string $mode): void
{
    global $db, $_this_script_;

    $page    = max(1, (int)rp_get('page'));
    $perpage = 25;
    $offset  = ($page - 1) * $perpage;

    $type     = rp_get('type');
    $status   = $mode === 'list' ? rp_get('status') : $mode;
    $search   = trim(rp_get('search'));
    $priority = rp_get('priority') === 'high' ? 'high' : '';

    [$where_sql, $params] = buildReportWhereClause($type, $status, $search, $priority);

    $total_result = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total FROM reports r
         LEFT JOIN users u1 ON r.addedby = u1.id
         LEFT JOIN users u2 ON r.reported_user_id = u2.id
         $where_sql",
        $params
    );
    $total = $total_result ? (int)($db->fetch_array($total_result)['total'] ?? 0) : 0;

    $order = $mode === 'resolved' ? 'r.updated_at DESC' : 'r.added DESC';

    $result = $db->sql_query_prepared(
        "SELECT r.*, u1.username AS reporter_name, u2.username AS reported_user_name,
                u3.username AS dealtby_name
         FROM reports r
         LEFT JOIN users u1 ON r.addedby = u1.id
         LEFT JOIN users u2 ON r.reported_user_id = u2.id
         LEFT JOIN users u3 ON r.dealtby = u3.id
         $where_sql
         ORDER BY $order LIMIT ?, ?",
        [...$params, $offset, $perpage]
    );

    [$title, $icon, $tone] = match ($mode) {
        'pending'  => ['Pending reports',  'fa-clock',        'warning'],
        'resolved' => ['Resolved reports', 'fa-circle-check', 'success'],
        default    => ['All reports',      'fa-layer-group',  'primary'],
    };

    $filter_params = array_filter(['type' => $type, 'status' => $mode === 'list' ? $status : '', 'search' => $search, 'priority' => $priority]);
    $export_params = array_filter(['type' => $type, 'status' => $status, 'search' => $search, 'priority' => $priority, 'export' => 'csv']);
    $has_filters   = $filter_params !== [];

    // GET-форма теряет query string из action, поэтому переносим его в hidden-поля
    $form_path   = strtok((string)$_this_script_, '?') ?: '';
    $form_hidden = [];
    parse_str((string)parse_url((string)$_this_script_, PHP_URL_QUERY), $form_hidden);
    $form_hidden['action'] = $mode;

    ?>
    <section class="rp-card rp-mb">
        <form method="get" action="<?= rp_h($form_path) ?>" class="rp-filters">
            <?php foreach ($form_hidden as $k => $v): if (!is_string($v)) continue; ?>
            <input type="hidden" name="<?= rp_h($k) ?>" value="<?= rp_h($v) ?>">
            <?php endforeach; ?>

            <div class="rp-field rp-field-wide">
                <label class="rp-label" for="rp-search"><i class="fa-solid fa-magnifying-glass"></i>Search</label>
                <div class="rp-input-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" id="rp-search" name="search" class="form-control"
                           placeholder="Reason, description, username..." value="<?= rp_h($search) ?>">
                </div>
            </div>
            <div class="rp-field">
                <label class="rp-label" for="rp-type"><i class="fa-solid fa-shapes"></i>Type</label>
                <select id="rp-type" name="type" class="form-select">
                    <option value="">All types</option>
                    <?php foreach (REPORT_TYPES as $v): ?>
                    <option value="<?= $v ?>" <?= $type === $v ? 'selected' : '' ?>><?= getTypeLabel($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($mode === 'list'): ?>
            <div class="rp-field">
                <label class="rp-label" for="rp-status"><i class="fa-solid fa-signal"></i>Status</label>
                <select id="rp-status" name="status" class="form-select">
                    <option value="">Any status</option>
                    <option value="pending"  <?= $status === 'pending'  ? 'selected' : '' ?>>Pending</option>
                    <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="rp-field">
                <label class="rp-label" for="rp-priority"><i class="fa-solid fa-fire"></i>Priority</label>
                <select id="rp-priority" name="priority" class="form-select">
                    <option value="">Any priority</option>
                    <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High only</option>
                </select>
            </div>
            <div class="rp-field rp-field-actions">
                <button type="submit" class="rp-btn rp-btn-solid rp-tone-primary">
                    <i class="fa-solid fa-filter"></i><span>Apply</span>
                </button>
                <?php if ($has_filters): ?>
                <?= rp_link_button($_this_script_ . '&action=' . $mode, 'Reset filters', 'fa-rotate-left', 'secondary', ['small' => false, 'icon_only' => true]) ?>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="rp-card">
        <?= rp_card_head(
            $icon,
            $tone,
            rp_h($title) . ' <span class="rp-count rp-count-soft">' . number_format($total) . '</span>',
            rp_link_button($_this_script_ . '&' . http_build_query($export_params), 'Export CSV', 'fa-file-csv', 'secondary'),
            $has_filters ? 'Filtered view' : ''
        ) ?>

        <?php if ($total > 0 && $result): ?>
        <div class="rp-scroll">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-hashtag"></i>ID</th>
                        <th><i class="fa-solid fa-shapes"></i>Type</th>
                        <th><i class="fa-solid fa-triangle-exclamation"></i>Reason</th>
                        <th><i class="fa-solid fa-user-pen"></i>Reporter</th>
                        <th><i class="fa-solid fa-user-xmark"></i>Reported user</th>
                        <th><i class="fa-solid fa-calendar-day"></i><?= $mode === 'resolved' ? 'Resolved' : 'Filed' ?></th>
                        <th><i class="fa-solid fa-signal"></i>Status</th>
                        <th class="rp-th-actions"><i class="fa-solid fa-gavel"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $db->fetch_array($result)):
                    $rid         = (int)$row['id'];
                    $done        = (bool)$row['dealtwith'];
                    $reason_data = get_report_reasons_map((string)$row['type'])[$row['reason']] ?? null;
                    $sev         = $reason_data['severity'] ?? 'unknown';
                    $ts          = $mode === 'resolved' ? $row['updated_at'] : $row['added'];
                ?>
                <tr class="<?= $done ? 'rp-row-done' : 'rp-sev-' . $sev ?>">
                    <td><a class="rp-id" href="<?= rp_h($_this_script_) ?>&amp;action=view&amp;id=<?= $rid ?>">#<?= $rid ?></a></td>
                    <td><?= rp_type_chip((string)$row['type']) ?></td>
                    <td>
                        <div class="rp-reason-cell">
                            <?= rp_reason_chip($reason_data, (string)$row['reason'], 26) ?>
                            <?php if ($sev === 'high' && !$done): ?>
                            <span class="rp-dot" title="High priority" aria-label="High priority"></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?= rp_user_cell((int)$row['addedby'], $row['reporter_name'] ?? null, 'info', 'Guest') ?></td>
                    <td><?= rp_user_cell((int)$row['reported_user_id'], $row['reported_user_name'] ?? null, 'danger', 'None', 'fa-minus') ?></td>
                    <td class="rp-nowrap" title="<?= date('Y-m-d H:i', (int)$ts) ?>">
                        <i class="fa-solid fa-clock rp-muted me-1"></i><?= my_datee('relative', $ts) ?>
                    </td>
                    <td>
                        <?= renderStatusBadge($done) ?>
                        <?php if ($done && !empty($row['dealtby_name'])): ?>
                        <div class="rp-sub"><i class="fa-solid fa-user-shield me-1"></i><?= rp_h($row['dealtby_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="rp-row-actions">
                            <?= rp_link_button($_this_script_ . '&action=view&id=' . $rid, 'View details', 'fa-eye', 'primary', ['icon_only' => true]) ?>
                            <?php if (!$done): ?>
                            <?= rp_action_button('resolve', $rid, [
                                'return' => $mode, 'label' => 'Mark as resolved', 'icon' => 'fa-check', 'tone' => 'success',
                                'icon_only' => true, 'btn_class' => 'resolve-report',
                            ]) ?>
                            <?php endif; ?>
                            <?= rp_action_button('delete', $rid, [
                                'return' => $mode, 'label' => 'Delete report', 'icon' => 'fa-trash-can', 'tone' => 'danger',
                                'icon_only' => true, 'ajax' => true, 'btn_class' => 'delete-report',
                                'confirm' => ['title' => 'Delete report #' . $rid . '?', 'text' => 'This cannot be undone.', 'btn' => 'Delete', 'variant' => 'danger'],
                            ]) ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > $perpage): ?>
        <div class="rp-card-foot">
            <?php renderPagination($page, $total, $perpage, ['action' => $mode] + $filter_params); ?>
        </div>
        <?php endif; ?>

        <?php elseif ($mode === 'pending' && !$has_filters): ?>
            <?= rp_empty('fa-mug-hot', 'success', 'Inbox zero', 'Every report has been handled. Nice work.') ?>
        <?php else: ?>
            <?= rp_empty('fa-inbox', 'secondary', 'No reports found',
                $has_filters ? 'Nothing matches these filters.' : 'There is nothing here yet.',
                $has_filters ? rp_link_button($_this_script_ . '&action=' . $mode, 'Clear filters', 'fa-rotate-left', 'primary') : '') ?>
        <?php endif; ?>
    </section>
    <?php
    if ($result) $db->free_result($result);
}

function renderPagination(int $page, int $total, int $perpage, array $extra_params): void
{
    global $_this_script_;

    $totalPages = (int)ceil($total / $perpage);
    $base       = $_this_script_ . '&' . http_build_query($extra_params) . '&page=';

    $link = static fn(int $p, string $inner, string $label = '') =>
        '<a href="' . rp_h($base . $p) . '"' . ($label !== '' ? ' aria-label="' . $label . '"' : '') . '>' . $inner . '</a>';

    echo '<nav class="rp-pager" aria-label="Pagination">';

    if ($page > 1) {
        echo $link($page - 1, '<i class="fa-solid fa-chevron-left"></i>', 'Previous page');
    }

    $from = max(1, $page - 2);
    $to   = min($totalPages, $page + 2);

    if ($from > 1) {
        echo $link(1, '1');
        if ($from > 2) echo '<span class="rp-pager-gap">&hellip;</span>';
    }

    for ($i = $from; $i <= $to; $i++) {
        echo $i === $page
            ? '<span class="is-active" aria-current="page">' . $i . '</span>'
            : $link($i, (string)$i);
    }

    if ($to < $totalPages) {
        if ($to < $totalPages - 1) echo '<span class="rp-pager-gap">&hellip;</span>';
        echo $link($totalPages, (string)$totalPages);
    }

    if ($page < $totalPages) {
        echo $link($page + 1, '<i class="fa-solid fa-chevron-right"></i>', 'Next page');
    }

    echo '</nav>';
}

function showStatistics(): void
{
    global $db, $_this_script_;

    $thirty_days_ago = TIMENOW - 30 * DAY_IN_SECONDS;

    $stats_result = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(dealtwith = 1), 0) AS resolved,
                COALESCE(SUM(dealtwith = 0), 0) AS pending,
                COUNT(DISTINCT addedby) AS unique_reporters,
                COUNT(DISTINCT reported_user_id) AS unique_reported_users
         FROM reports WHERE added > ?",
        [$thirty_days_ago]
    );

    $stats = array_map('intval', array_merge(
        ['total' => 0, 'resolved' => 0, 'pending' => 0, 'unique_reporters' => 0, 'unique_reported_users' => 0],
        ($stats_result ? $db->fetch_array($stats_result) : null) ?: []
    ));

    $rate = $stats['total'] > 0 ? (int)round($stats['resolved'] / $stats['total'] * 100) : 0;

    $type_stats_result = $db->sql_query_prepared(
        "SELECT type, COUNT(*) AS count, COALESCE(SUM(dealtwith = 1), 0) AS resolved
         FROM reports WHERE added > ? GROUP BY type ORDER BY count DESC",
        [$thirty_days_ago]
    );

    $top_reported_result = $db->sql_query_prepared(
        "SELECT r.reported_user_id, MAX(u.username) AS username, COUNT(*) AS report_count
         FROM reports r LEFT JOIN users u ON r.reported_user_id = u.id
         WHERE r.reported_user_id > 0 GROUP BY r.reported_user_id ORDER BY report_count DESC LIMIT 10"
    );

    $top_reporters_result = $db->sql_query_prepared(
        "SELECT r.addedby, MAX(u.username) AS username, COUNT(*) AS report_count
         FROM reports r LEFT JOIN users u ON r.addedby = u.id
         WHERE r.addedby > 0 GROUP BY r.addedby ORDER BY report_count DESC LIMIT 10"
    );

    ?>
    <div class="rp-kpis">
        <?= rp_kpi('primary', 'fa-flag',          $stats['total'],            'Reports in 30 days') ?>
        <?= rp_kpi('success', 'fa-circle-check',  $stats['resolved'],         'Resolved', null, $rate . '% resolution rate') ?>
        <?= rp_kpi('warning', 'fa-clock',         $stats['pending'],          'Still pending', $_this_script_ . '&action=pending') ?>
        <?= rp_kpi('info',    'fa-users',         $stats['unique_reporters'], 'Unique reporters', null, $stats['unique_reported_users'] . ' users reported') ?>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="rp-card h-100">
                <?= rp_card_head('fa-chart-pie', 'primary', 'Reports by type', '', 'Last 30 days') ?>
                <div class="rp-card-body">
                <?php if ($type_stats_result && $db->num_rows($type_stats_result) > 0): ?>
                    <ul class="rp-typebars">
                    <?php while ($row = $db->fetch_array($type_stats_result)):
                        $count    = (int)$row['count'];
                        $resolved = (int)$row['resolved'];
                        $pending  = $count - $resolved;
                        $percent  = $count > 0 ? (int)round($resolved / $count * 100) : 0;
                    ?>
                        <li>
                            <div class="rp-typebar-top">
                                <?= rp_type_chip((string)$row['type']) ?>
                                <span class="rp-typebar-nums">
                                    <span title="Total"><i class="fa-solid fa-layer-group"></i><?= $count ?></span>
                                    <span class="rp-fg-success" title="Resolved"><i class="fa-solid fa-check"></i><?= $resolved ?></span>
                                    <span class="rp-fg-warning" title="Pending"><i class="fa-solid fa-clock"></i><?= $pending ?></span>
                                </span>
                            </div>
                            <div class="rp-bar" role="progressbar" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                <span style="width:<?= $percent ?>%"></span>
                            </div>
                            <div class="rp-sub"><?= $percent ?>% resolved</div>
                        </li>
                    <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <?= rp_empty('fa-chart-pie', 'secondary', 'No data for this period') ?>
                <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <?= renderTopUsersTable($top_reported_result, 'Most reported users', 'reported_user_id', 'fa-user-slash', 'danger') ?>
        </div>

        <div class="col-lg-6">
            <?= renderTopUsersTable($top_reporters_result, 'Top reporters', 'addedby', 'fa-user-check', 'info') ?>
        </div>

        <div class="col-lg-6">
            <section class="rp-card h-100">
                <?= rp_card_head('fa-bolt', 'warning', 'Quick actions') ?>
                <div class="rp-card-body rp-stack">
                    <?= rp_link_button($_this_script_ . '&action=pending', 'Review pending reports', 'fa-clock', 'warning', ['small' => false, 'class' => 'rp-btn-block']) ?>
                    <?= rp_link_button($_this_script_ . '&action=pending&priority=high', 'High priority only', 'fa-fire', 'danger', ['small' => false, 'class' => 'rp-btn-block']) ?>
                    <?= rp_link_button($_this_script_ . '&export=csv', 'Export all reports (CSV)', 'fa-file-csv', 'secondary', ['small' => false, 'class' => 'rp-btn-block']) ?>
                    <?= rp_action_button('clearold', 0, [
                        'return' => 'stats', 'label' => 'Clear resolved reports older than 30 days', 'icon' => 'fa-broom',
                        'tone' => 'danger', 'small' => false, 'btn_class' => 'rp-btn-block', 'class' => 'rp-block-form',
                        'confirm' => ['title' => 'Clear old resolved reports?', 'text' => 'Resolved reports older than 30 days will be deleted permanently.', 'btn' => 'Clear them', 'variant' => 'danger'],
                    ]) ?>
                </div>
            </section>
        </div>
    </div>
    <?php
    foreach ([$stats_result, $type_stats_result, $top_reported_result, $top_reporters_result] as $r) {
        if ($r) $db->free_result($r);
    }
}

function renderTopUsersTable($result, string $title, string $id_field, string $icon, string $tone): string
{
    global $db, $_this_script_;

    ob_start(); ?>
    <section class="rp-card h-100">
        <?= rp_card_head($icon, $tone, rp_h($title), '', 'All time, top 10') ?>
        <div class="rp-card-body">
        <?php if ($result && $db->num_rows($result) > 0): ?>
            <ol class="rp-rank">
            <?php $n = 0; while ($row = $db->fetch_array($result)): $n++; ?>
                <li>
                    <span class="rp-rank-n"><?= $n ?></span>
                    <span class="rp-rank-user"><?= rp_user_cell((int)$row[$id_field], $row['username'] ?? null, $tone, 'Unknown') ?></span>
                    <span class="rp-chip rp-tone-<?= $tone ?>"><i class="fa-solid fa-flag"></i><?= (int)$row['report_count'] ?></span>
                    <?= rp_link_button($_this_script_ . '&action=list&search=' . urlencode((string)($row['username'] ?? '')), 'Show reports', 'fa-magnifying-glass', 'primary', ['icon_only' => true]) ?>
                </li>
            <?php endwhile; ?>
            </ol>
        <?php else: ?>
            <?= rp_empty('fa-users', 'secondary', 'No data yet') ?>
        <?php endif; ?>
        </div>
    </section>
    <?php return (string)ob_get_clean();
}

function showReportDetails(int $report_id): void
{
    global $db, $_this_script_, $BASEURL;

    if ($report_id <= 0) {
        echo '<section class="rp-card">' . rp_empty('fa-circle-exclamation', 'danger', 'Invalid report ID') . '</section>';
        return;
    }

    $result = $db->sql_query_prepared(
        "SELECT r.*,
                u1.username AS reporter_name, u1.email AS reporter_email,
                u2.username AS reported_user_name, u2.email AS reported_user_email,
                u3.username AS dealtby_name,
                t.name AS torrent_name,
                c.text AS comment_text, c.torrent AS comment_torrent_id,
                f.name AS forum_name, f.fid AS forum_db_id,
                th.subject AS thread_subject, th.tid AS thread_db_id
         FROM reports r
         LEFT JOIN users u1     ON r.addedby = u1.id
         LEFT JOIN users u2     ON r.reported_user_id = u2.id
         LEFT JOIN users u3     ON r.dealtby = u3.id
         LEFT JOIN torrents t   ON r.type = 'torrent'   AND r.reported_id = t.id
         LEFT JOIN comments c   ON r.type = 'comment'   AND r.reported_id = c.id
         LEFT JOIN forums f     ON r.type = 'forumpost' AND r.forum_id = f.fid
         LEFT JOIN threads th   ON r.type = 'forumpost' AND r.thread_id = th.tid
         WHERE r.id = ?",
        [$report_id]
    );

    $report = $result ? $db->fetch_array($result) : null;

    if (!$report) {
        echo '<section class="rp-card">' . rp_empty('fa-magnifying-glass', 'secondary', 'Report not found', 'It may have been deleted already.',
            rp_link_button($_this_script_ . '&action=list', 'Back to reports', 'fa-arrow-left', 'primary')) . '</section>';
        return;
    }

    $rid      = (int)$report['id'];
    $done     = (bool)$report['dealtwith'];
    $item_url = rp_item_url($report);

    ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <?= renderReportDetails($report) ?>
            <?php
            if ($report['type'] === 'forumpost') echo renderForumPostDetails($report);
            if ($report['type'] === 'user')      echo renderUserReportDetails($report);
            ?>
        </div>

        <div class="col-lg-4">
            <div class="rp-side">
                <section class="rp-card rp-mb">
                    <?= rp_card_head('fa-gavel', 'primary', 'Take action', '', $done ? 'This report is already closed' : 'Pick what to do with it') ?>
                    <div class="rp-card-body"><?= renderActionForm($rid, (string)$report['type'], (int)$report['reported_user_id'], $done) ?></div>
                </section>
                <?php if ((int)$report['reported_user_id'] > 0): ?>
                <?= renderUserReportStats((int)$report['reported_user_id'], $report['reported_user_name'] ?? null) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="rp-actionbar">
        <span class="rp-actionbar-info">
            <i class="fa-solid <?= getTypeIcon((string)$report['type']) ?>"></i>
            Report #<?= $rid ?> is <?= $done ? 'resolved' : 'pending' ?>
        </span>
        <?= rp_link_button($_this_script_ . '&action=list', 'Back to list', 'fa-arrow-left', 'secondary', ['small' => false]) ?>
        <?php if ($item_url): ?>
        <?= rp_link_button($item_url, 'Open reported item', 'fa-arrow-up-right-from-square', 'primary', ['small' => false, 'blank' => true]) ?>
        <?php endif; ?>
        <?php if (!$done): ?>
        <?= rp_action_button('resolve', $rid, ['return' => 'view', 'label' => 'Mark as resolved', 'icon' => 'fa-check', 'tone' => 'success', 'solid' => true, 'small' => false]) ?>
        <?php endif; ?>
        <?= rp_action_button('delete', $rid, [
            'return' => 'list', 'label' => 'Delete', 'icon' => 'fa-trash-can', 'tone' => 'danger', 'small' => false,
            'confirm' => ['title' => 'Delete report #' . $rid . '?', 'text' => 'This cannot be undone.', 'btn' => 'Delete', 'variant' => 'danger'],
        ]) ?>
    </div>
    <?php

    $db->free_result($result);
}

function rp_item_url(array $report): ?string
{
    global $BASEURL;

    $id = (int)$report['reported_id'];

    return match ($report['type']) {
        'torrent'   => !empty($report['torrent_name']) ? $BASEURL . '/' . get_torrent_link($id) : null,
        'comment'   => !empty($report['comment_torrent_id']) ? $BASEURL . '/' . get_comment_link($id, $report['comment_torrent_id']) . '#pid' . $id : null,
        'forumpost' => !empty($report['thread_db_id']) ? $BASEURL . '/' . get_post_link($id, $report['thread_db_id']) . '#pid' . $id : null,
        'user'      => (int)$report['reported_user_id'] > 0 ? 'user-' . (int)$report['reported_user_id'] . '.html' : null,
        default     => null,
    };
}

function renderReportDetails(array $report): string
{
    global $parser, $parser_options, $_this_script_;

    $type        = (string)$report['type'];
    $reason_data = get_report_reasons_map($type)[$report['reason']] ?? null;
    $sev         = $reason_data['severity'] ?? 'unknown';
    $done        = (bool)$report['dealtwith'];
    $item_url    = rp_item_url($report);

    [$description, $notes] = rp_split_notes((string)($report['description'] ?? ''));

    // Reported item
    $item_label = match ($type) {
        'torrent'   => !empty($report['torrent_name']) ? $report['torrent_name'] : 'Torrent #' . $report['reported_id'] . ' (deleted)',
        'comment'   => 'Comment #' . $report['reported_id'] . (empty($report['comment_text']) ? ' (deleted)' : ''),
        'forumpost' => !empty($report['thread_subject']) ? $report['thread_subject'] : 'Post #' . $report['reported_id'],
        'user'      => $report['reported_user_name'] ?? ('User #' . $report['reported_id']),
        default     => '#' . $report['reported_id'],
    };
    $item_html = $item_url
        ? '<a href="' . rp_h($item_url) . '" target="_blank" rel="noopener">' . rp_h($item_label) . ' <i class="fa-solid fa-arrow-up-right-from-square rp-ext"></i></a>'
        : rp_h($item_label);

    $reporter_html = rp_user_cell((int)$report['addedby'], $report['reporter_name'] ?? null, 'info', 'Guest')
        . (!empty($report['reporter_email']) ? '<div class="rp-sub"><i class="fa-solid fa-envelope me-1"></i><a href="mailto:' . rp_h($report['reporter_email']) . '">' . rp_h($report['reporter_email']) . '</a></div>' : '');

    $reported_html = rp_user_cell((int)$report['reported_user_id'], $report['reported_user_name'] ?? null, 'danger', 'No user attached', 'fa-minus')
        . (!empty($report['reported_user_email']) ? '<div class="rp-sub"><i class="fa-solid fa-envelope me-1"></i><a href="mailto:' . rp_h($report['reported_user_email']) . '">' . rp_h($report['reported_user_email']) . '</a></div>' : '');

    $filed_html = my_datee('relative', $report['added']) . '<div class="rp-sub">' . date('Y-m-d H:i', (int)$report['added']) . '</div>';

    $ip_html = !empty($report['ip_address'])
        ? '<code class="rp-code">' . rp_h($report['ip_address']) . '</code> '
          . rp_link_button($_this_script_ . '&action=iplookup&ip=' . urlencode((string)$report['ip_address']), 'Look up IP', 'fa-magnifying-glass-location', 'info', ['icon_only' => true])
        : '<span class="rp-muted">Not recorded</span>';

    ob_start(); ?>
    <section class="rp-card rp-mb">
        <?= rp_card_head(
            getTypeIcon($type),
            getTypeColor($type),
            'Report #' . (int)$report['id'],
            renderStatusBadge($done),
            rp_h(getTypeLabel($type)) . ' report'
        ) ?>
        <div class="rp-card-body">
            <div class="rp-summary">
                <?= rp_reason_chip($reason_data, (string)$report['reason']) ?>
                <?= rp_severity_chip($sev) ?>
                <?php if ($reason_data): ?>
                <span class="rp-chip rp-chip-outline rp-tone-secondary"><i class="fa-solid fa-tag"></i><?= rp_h($reason_data['category']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($reason_data && !$done): ?>
            <?= renderPriorityAlert($reason_data, $report) ?>
            <?php endif; ?>

            <div class="rp-facts">
                <?= rp_fact('fa-user-pen',   'Reporter',       $reporter_html, 'info') ?>
                <?= rp_fact('fa-user-xmark', 'Reported user',  $reported_html, 'danger') ?>
                <?= rp_fact(getTypeIcon($type), 'Reported item', $item_html, getTypeColor($type)) ?>
                <?= rp_fact('fa-calendar-day', 'Filed',        $filed_html, 'secondary') ?>
                <?= rp_fact('fa-network-wired', 'Reporter IP', $ip_html, 'secondary') ?>
                <?php if ($done): ?>
                <?= rp_fact('fa-user-shield', 'Resolved',
                    my_datee('relative', $report['updated_at'])
                    . (!empty($report['dealtby_name']) ? '<div class="rp-sub">by ' . rp_h($report['dealtby_name']) . '</div>' : ''),
                    'success') ?>
                <?php endif; ?>
            </div>

            <?php if ($type !== 'user'): ?>
            <h3 class="rp-section-title"><i class="fa-solid fa-quote-left"></i>What the reporter wrote</h3>
            <?php if (trim($description) !== ''): ?>
            <div class="rp-quote"><?= $parser->parse_message($description, $parser_options) ?></div>
            <?php else: ?>
            <div class="rp-quote rp-muted">No details provided.</div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($notes): ?>
            <h3 class="rp-section-title"><i class="fa-solid fa-note-sticky"></i>Staff notes</h3>
            <?php foreach ($notes as $note): ?>
            <div class="rp-note rp-tone-success"><i class="fa-solid fa-user-shield"></i><div><?= nl2br(rp_h($note)) ?></div></div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($type === 'comment' && !empty($report['comment_text'])): ?>
            <?= renderCommentContent($report, $reason_data, $item_url) ?>
            <?php endif; ?>
        </div>
    </section>
    <?php return (string)ob_get_clean();
}

function renderPriorityAlert(array $reason_data, array $report): string
{
    $sev      = $reason_data['severity'];
    $tone     = rp_severity_tone($sev);
    $headline = match ($sev) { 'high' => 'High priority, act now', 'medium' => 'Medium priority, review within 24 hours', default => 'Standard review' };

    $recommendation = $reason_data['recommended_action']
        ?? REASON_RECOMMENDATIONS[$report['type']][$report['reason']]
        ?? 'Review based on provided information.';

    return '<div class="rp-callout rp-tone-' . $tone . '">'
        . '<span class="rp-ico rp-ico-md rp-callout-ico"><i class="fa-solid ' . rp_severity_icon($sev) . '"></i></span>'
        . '<div><div class="rp-callout-title">' . rp_h($headline) . '</div>'
        . '<div class="rp-callout-text"><i class="fa-solid fa-lightbulb me-1"></i>' . rp_h($recommendation) . '</div></div></div>';
}

function renderCommentContent(array $report, ?array $reason_data, ?string $commentlink): string
{
    global $parser, $parser_options, $_this_script_;

    $rid      = (int)$report['id'];
    $severity = $reason_data['severity'] ?? 'low';
    $tone     = $severity === 'high' ? 'danger' : 'warning';

    ob_start(); ?>
    <h3 class="rp-section-title"><i class="fa-solid fa-comment-dots"></i>Reported comment</h3>
    <div class="rp-quote rp-quote-<?= $tone ?>">
        <?= $parser->parse_message((string)$report['comment_text'], $parser_options) ?>
    </div>
    <div class="rp-btnrow">
        <?php if ($commentlink): ?>
        <?= rp_link_button($commentlink, 'View in context', 'fa-arrow-up-right-from-square', 'primary', ['blank' => true]) ?>
        <?php endif; ?>
        <?php if (!$report['dealtwith']): ?>
        <?= rp_action_button('deletecomment', $rid, [
            'return' => 'view', 'label' => 'Delete comment', 'icon' => 'fa-trash-can', 'tone' => 'danger',
            'solid' => in_array($severity, ['high', 'medium'], true),
            'confirm' => ['title' => 'Delete this comment?', 'text' => 'The comment and its attachments will be removed and the report resolved.', 'btn' => 'Delete comment', 'variant' => 'danger'],
        ]) ?>
        <?php endif; ?>
        <?php if ((int)$report['reported_user_id'] > 0 && $severity === 'high'): ?>
        <?= rp_link_button('warn.php?uid=' . (int)$report['reported_user_id'] . '&reason=' . urlencode($reason_data['text'] ?? ''), 'Warn author', 'fa-triangle-exclamation', 'warning', ['blank' => true]) ?>
        <?php endif; ?>
    </div>
    <?php return (string)ob_get_clean();
}

function renderForumPostDetails(array $report): string
{
    global $BASEURL, $parser, $parser_options;

    $post_id   = (int)$report['reported_id'];
    $post_data = getForumPostData($post_id, $report);

    if (!$post_data) {
        return '<section class="rp-card rp-mb">' . rp_empty('fa-ghost', 'warning', 'Forum post not found', 'The post may have been deleted already.') . '</section>';
    }

    $pid       = (int)$post_data['pid'];
    $postlink  = $BASEURL . '/' . get_post_link($pid, $post_data['thread_id']) . '#pid' . $pid;
    $rule_code = (string)($post_data['rule_violation'] ?? '');
    $rule_data = RULES_MAP[$rule_code] ?? null;

    $visible_map = [0 => ['Deleted / hidden', 'danger', 'fa-eye-slash'], 1 => ['Visible', 'success', 'fa-eye'], 2 => ['Awaiting approval', 'warning', 'fa-hourglass-half']];
    [$vis_text, $vis_tone, $vis_icon] = isset($post_data['visible'])
        ? ($visible_map[(int)$post_data['visible']] ?? ['Unknown', 'secondary', 'fa-circle-question'])
        : ['Unknown', 'secondary', 'fa-circle-question'];

    ob_start(); ?>
    <section class="rp-card rp-mb">
        <?= rp_card_head('fa-comments', 'success', 'Forum post #' . $pid,
            '<span class="rp-chip rp-tone-' . $vis_tone . '"><i class="fa-solid ' . $vis_icon . '"></i>' . $vis_text . '</span>',
            !empty($post_data['subject']) ? rp_h($post_data['subject']) : '') ?>
        <div class="rp-card-body">
            <div class="rp-facts">
                <?= rp_fact('fa-user', 'Author', rp_user_cell((int)$post_data['author_id'], $post_data['author_name'] ?? null, 'danger', 'Unknown'), 'danger') ?>
                <?= rp_fact('fa-calendar-day', 'Posted', my_datee('relative', $post_data['dateline']), 'secondary') ?>
                <?= rp_fact('fa-folder-open', 'Forum',
                    '<a href="forumdisplay.php?fid=' . (int)$post_data['forum_id'] . '" target="_blank" rel="noopener">' . rp_h($post_data['forum_name'] ?? 'Unknown forum') . '</a>', 'success') ?>
                <?= rp_fact('fa-comments', 'Thread',
                    '<a href="showthread.php?tid=' . (int)$post_data['thread_id'] . '" target="_blank" rel="noopener">' . rp_h($post_data['thread_subject'] ?? 'Unknown thread') . '</a>'
                    . '<div class="rp-sub"><i class="fa-solid fa-eye me-1"></i>' . number_format((int)($post_data['thread_views'] ?? 0)) . ' views</div>', 'success') ?>
                <?php if ($rule_data): ?>
                <?= rp_fact('fa-scale-balanced', 'Rule broken',
                    '<span class="rp-chip rp-tone-' . rp_tone($rule_data['color']) . '"><i class="fa-solid ' . $rule_data['icon'] . '"></i>' . rp_h($rule_data['text']) . '</span>', 'warning') ?>
                <?php endif; ?>
                <?php if (!empty($post_data['moderated'])): ?>
                <?= rp_fact('fa-shield-halved', 'Moderated', rp_h($post_data['moderated']), 'secondary') ?>
                <?php endif; ?>
            </div>

            <h3 class="rp-section-title"><i class="fa-solid fa-comment-dots"></i>Post content</h3>
            <?php if (!empty($post_data['message'])): ?>
            <div class="rp-quote rp-quote-warning"><?= $parser->parse_message((string)$post_data['message'], $parser_options) ?></div>
            <?php else: ?>
            <div class="rp-quote rp-muted">The post is empty.</div>
            <?php endif; ?>

            <div class="rp-btnrow">
                <?= rp_link_button($postlink, 'View in forum', 'fa-arrow-up-right-from-square', 'primary', ['blank' => true]) ?>
                <?= rp_link_button('editpost.php?pid=' . $pid, 'Edit post', 'fa-pen-to-square', 'secondary', ['blank' => true]) ?>
                <?php if ((int)$post_data['author_id'] > 0): ?>
                <?= rp_link_button('warn.php?uid=' . (int)$post_data['author_id'], 'Warn author', 'fa-triangle-exclamation', 'warning', [
                    'blank' => true,
                    'confirm' => ['title' => 'Warn the author?', 'text' => 'The warning form opens in a new tab.', 'btn' => 'Open warning form', 'variant' => 'warning'],
                ]) ?>
                <?php endif; ?>
                <?php if (!$report['dealtwith']): ?>
                <?= rp_action_button('deleteforumpost', (int)$report['id'], [
                    'return' => 'view', 'label' => 'Delete post', 'icon' => 'fa-trash-can', 'tone' => 'danger', 'solid' => true,
                    'confirm' => ['title' => 'Delete this forum post?', 'text' => 'The post is removed permanently and the report resolved.', 'btn' => 'Delete post', 'variant' => 'danger'],
                ]) ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php return (string)ob_get_clean();
}

function renderActionForm(int $report_id, string $report_type, int $reported_user_id, bool $done): string
{
    global $_this_script_;

    $choices = [
        ['resolve', 'Mark as resolved', 'Close the report, nothing else changes', 'fa-circle-check', 'success',
            ['title' => 'Resolve this report?', 'icon' => 'question', 'btn' => 'Resolve', 'variant' => 'success']],
    ];

    if ($report_type === 'forumpost') {
        $choices[] = ['deleteforumpost', 'Delete forum post', 'Remove the post and resolve', 'fa-trash-can', 'danger',
            ['title' => 'Delete this forum post?', 'text' => 'This cannot be undone.', 'btn' => 'Delete post', 'variant' => 'danger']];
    } elseif ($report_type === 'comment') {
        $choices[] = ['deletecomment', 'Delete comment', 'Remove the comment and resolve', 'fa-trash-can', 'danger',
            ['title' => 'Delete this comment?', 'text' => 'This cannot be undone.', 'btn' => 'Delete comment', 'variant' => 'danger']];
    }

    if ($reported_user_id > 0) {
        $choices[] = ['warn_user', 'Warn reported user', 'Opens the warning form', 'fa-triangle-exclamation', 'warning',
            ['title' => 'Go to the warning form?', 'icon' => 'question', 'btn' => 'Continue', 'variant' => 'warning']];
        $choices[] = ['ban_user', 'Ban reported user', 'Opens the ban form', 'fa-ban', 'danger',
            ['title' => 'Go to the ban form?', 'text' => 'You can still review the details before banning.', 'btn' => 'Continue', 'variant' => 'danger']];
    }

    $choices[] = ['ignore', 'Ignore report', 'Close it as not actionable', 'fa-eye-slash', 'secondary',
        ['title' => 'Ignore this report?', 'icon' => 'question', 'btn' => 'Ignore', 'variant' => 'secondary']];

    ob_start(); ?>
    <form method="post" action="<?= rp_h($_this_script_) ?>&amp;action=takeaction" data-confirm="choice" class="rp-action-form">
        <input type="hidden" name="my_post_key" value="<?= rp_h(rp_post_key()) ?>">
        <input type="hidden" name="id" value="<?= $report_id ?>">
        <input type="hidden" name="return" value="view">

        <div class="rp-choices" role="radiogroup" aria-label="Action">
            <?php foreach ($choices as $i => [$value, $label, $hint, $icon, $tone, $confirm]): ?>
            <label class="rp-choice rp-tone-<?= $tone ?>">
                <input type="radio" name="do" value="<?= $value ?>" <?= $i === 0 && !$done ? 'checked' : '' ?><?= rp_confirm_attrs($confirm) ?>>
                <span class="rp-ico rp-ico-sm"><i class="fa-solid <?= $icon ?>"></i></span>
                <span class="rp-choice-text"><strong><?= rp_h($label) ?></strong><small><?= rp_h($hint) ?></small></span>
                <i class="fa-solid fa-circle-check rp-choice-mark"></i>
            </label>
            <?php endforeach; ?>
        </div>

        <label class="rp-label mt-3" for="rp-notes"><i class="fa-solid fa-note-sticky"></i>Notes (optional)</label>
        <textarea id="rp-notes" name="notes" class="form-control" rows="3" placeholder="How was this report handled?"></textarea>

        <button type="submit" class="rp-btn rp-btn-solid rp-tone-primary rp-btn-block mt-3">
            <i class="fa-solid fa-paper-plane"></i><span>Apply action</span>
        </button>
    </form>
    <?php return (string)ob_get_clean();
}

function renderUserReportStats(int $user_id, ?string $username): string
{
    global $db, $_this_script_;

    $r = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total_reports,
                COALESCE(SUM(dealtwith = 1), 0) AS resolved,
                COALESCE(SUM(dealtwith = 0), 0) AS pending
         FROM reports WHERE reported_user_id = ?",
        [$user_id]
    );

    $s = array_map('intval', array_merge(['total_reports' => 0, 'resolved' => 0, 'pending' => 0], ($r ? $db->fetch_array($r) : null) ?: []));
    if ($r) $db->free_result($r);

    ob_start(); ?>
    <section class="rp-card">
        <?= rp_card_head('fa-clock-rotate-left', 'danger', 'User report history', '', rp_h($username ?? ('User #' . $user_id))) ?>
        <div class="rp-card-body">
            <div class="rp-mini">
                <div class="rp-tone-primary"><strong><?= $s['total_reports'] ?></strong><span><i class="fa-solid fa-flag"></i>Total</span></div>
                <div class="rp-tone-success"><strong><?= $s['resolved'] ?></strong><span><i class="fa-solid fa-check"></i>Resolved</span></div>
                <div class="rp-tone-warning"><strong><?= $s['pending'] ?></strong><span><i class="fa-solid fa-clock"></i>Pending</span></div>
            </div>
            <?= rp_link_button($_this_script_ . '&action=list&search=' . urlencode($username ?? ''), 'All reports for this user', 'fa-list', 'primary', ['class' => 'rp-btn-block mt-3']) ?>
        </div>
    </section>
    <?php return (string)ob_get_clean();
}

function renderUserReportDetails(array $report): string
{
    global $parser, $parser_options, $_this_script_, $db;

    $user_id = (int)$report['reported_user_id'];
    $rid     = (int)$report['id'];

    $user_info = null;
    $user_result = null;
    if ($user_id > 0) {
        $user_result = $db->sql_query_prepared(
            "SELECT u.*,
                    COUNT(r2.id)                                  AS total_reports,
                    COUNT(CASE WHEN r2.dealtwith = 1 THEN 1 END)  AS resolved_reports,
                    COUNT(CASE WHEN r2.dealtwith = 0 THEN 1 END)  AS pending_reports
             FROM users u LEFT JOIN reports r2 ON u.id = r2.reported_user_id
             WHERE u.id = ? GROUP BY u.id",
            [$user_id]
        );
        $user_info = $user_result ? ($db->fetch_array($user_result) ?: null) : null;
    }

    $recent_result = $user_id > 0 ? $db->sql_query_prepared(
        "SELECT r.*, u.username AS reporter_name FROM reports r
         LEFT JOIN users u ON r.addedby = u.id
         WHERE r.reported_user_id = ? AND r.id != ?
         ORDER BY r.added DESC LIMIT 5",
        [$user_id, $rid]
    ) : null;

    [$description] = rp_split_notes((string)($report['description'] ?? ''));
    $parsed_data   = parseUserReportDescription($description);
    $main_text     = $parsed_data['formatted_description'] ?: $description;

    ob_start(); ?>
    <section class="rp-card rp-mb">
        <?= rp_card_head('fa-file-lines', 'info', 'Report contents', '', 'Submitted through the user report form') ?>
        <div class="rp-card-body">
            <h3 class="rp-section-title rp-mt0"><i class="fa-solid fa-quote-left"></i>What the reporter wrote</h3>
            <?php if (trim($main_text) !== ''): ?>
            <div class="rp-quote"><?= $parser->parse_message($main_text, $parser_options) ?></div>
            <?php else: ?>
            <div class="rp-quote rp-muted">No details provided.</div>
            <?php endif; ?>

            <?php if ($parsed_data['additional_info'] !== ''): ?>
            <h3 class="rp-section-title"><i class="fa-solid fa-circle-info"></i>Additional information</h3>
            <div class="rp-quote"><?= nl2br(rp_h($parsed_data['additional_info'])) ?></div>
            <?php endif; ?>

            <?php if ($parsed_data['evidence_links'] !== ''): ?>
            <h3 class="rp-section-title"><i class="fa-solid fa-link"></i>Evidence links</h3>
            <ul class="rp-links">
                <?php foreach (array_filter(array_map('trim', explode("\n", $parsed_data['evidence_links']))) as $link):
                    $safe = rp_safe_url($link); ?>
                <li>
                    <?php if ($safe): ?>
                    <a href="<?= rp_h($safe) ?>" target="_blank" rel="noopener noreferrer nofollow">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i><?= rp_h(truncateString($link, 70)) ?>
                    </a>
                    <?php else: ?>
                    <span class="rp-muted" title="Not a http(s) link, shown as text"><i class="fa-solid fa-link-slash"></i><?= rp_h(truncateString($link, 70)) ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($user_info):
        $uname = (string)($user_info['username'] ?? 'Unknown');
        $days  = max(1, (int)floor((TIMENOW - (int)$user_info['added']) / DAY_IN_SECONDS));
        $rate  = number_format((int)($user_info['total_reports'] ?? 0) / $days, 2);
        $enabled = ($user_info['enabled'] ?? '') === 'yes';
    ?>
    <section class="rp-card rp-mb">
        <?= rp_card_head('fa-user-large', 'warning', rp_user_cell($user_id, $uname, 'danger', 'Unknown'),
            $enabled
                ? '<span class="rp-chip rp-tone-success"><i class="fa-solid fa-user-check"></i>Active</span>'
                : '<span class="rp-chip rp-tone-danger"><i class="fa-solid fa-user-lock"></i>Disabled</span>',
            'Reported account') ?>
        <div class="rp-card-body">
            <div class="rp-facts">
                <?= rp_fact('fa-id-badge', 'User ID', (string)$user_id, 'secondary') ?>
                <?= rp_fact('fa-envelope', 'Email', !empty($user_info['email'])
                    ? '<a href="mailto:' . rp_h($user_info['email']) . '">' . rp_h($user_info['email']) . '</a>'
                    : '<span class="rp-muted">Not available</span>', 'secondary') ?>
                <?= rp_fact('fa-calendar-plus', 'Registered', my_datee('relative', $user_info['added']), 'secondary') ?>
                <?= rp_fact('fa-gauge-high', 'Report rate', rp_h($rate) . ' per day', 'warning') ?>
            </div>

            <h3 class="rp-section-title"><i class="fa-solid fa-shield-halved"></i>Moderation</h3>
            <div class="rp-btnrow">
                <?= rp_link_button('warn.php?uid=' . $user_id . '&reason=' . rawurlencode('Report #' . $rid . ': ' . $report['reason']), 'Issue warning', 'fa-triangle-exclamation', 'warning', ['blank' => true]) ?>
                <?= rp_link_button('edituser.php?action=edituser&userid=' . $user_id, 'Edit user', 'fa-user-pen', 'info', ['blank' => true]) ?>
                <?= rp_link_button('staff.php?act=users&do=suspend&uid=' . $user_id, 'Suspend', 'fa-user-clock', 'danger', [
                    'blank' => true,
                    'confirm' => ['title' => 'Suspend ' . $uname . '?', 'text' => 'The suspension form opens in a new tab.', 'btn' => 'Continue', 'variant' => 'danger'],
                ]) ?>
                <?= rp_link_button('bans.php?action=add&uid=' . $user_id, 'Ban user', 'fa-ban', 'danger', [
                    'solid' => true, 'blank' => true,
                    'confirm' => ['title' => 'Ban ' . $uname . '?', 'text' => 'The ban form opens in a new tab.', 'btn' => 'Continue', 'variant' => 'danger'],
                ]) ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($user_id > 0): ?>
    <section class="rp-card rp-mb">
        <?= rp_card_head('fa-clock-rotate-left', 'secondary', 'Other reports about this user') ?>
        <?php if ($recent_result && $db->num_rows($recent_result) > 0): ?>
        <div class="rp-scroll">
            <table class="rp-table rp-table-compact">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-calendar-day"></i>Date</th>
                        <th><i class="fa-solid fa-shapes"></i>Type</th>
                        <th><i class="fa-solid fa-triangle-exclamation"></i>Reason</th>
                        <th><i class="fa-solid fa-user-pen"></i>Reporter</th>
                        <th><i class="fa-solid fa-signal"></i>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($r = $db->fetch_array($recent_result)):
                    $rd = get_report_reasons_map((string)$r['type'])[$r['reason']] ?? null; ?>
                <tr>
                    <td class="rp-nowrap"><?= date('Y-m-d', (int)$r['added']) ?></td>
                    <td><?= rp_type_chip((string)$r['type']) ?></td>
                    <td><?= rp_reason_chip($rd, (string)$r['reason'], 22) ?></td>
                    <td><?= rp_user_cell((int)$r['addedby'], $r['reporter_name'] ?? null, 'info', 'Guest') ?></td>
                    <td><?= renderStatusBadge((bool)$r['dealtwith']) ?></td>
                    <td><?= rp_link_button($_this_script_ . '&action=view&id=' . (int)$r['id'], 'View', 'fa-eye', 'primary', ['icon_only' => true]) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <?= rp_empty('fa-circle-info', 'info', 'This is the only report about this user') ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php
    if ($user_result)   $db->free_result($user_result);
    if ($recent_result) $db->free_result($recent_result);

    return (string)ob_get_clean();
}

// ==================== СТИЛИ И СКРИПТЫ ====================

function rp_asset(string $rel): string
{
    global $BASEURL;

    $root = defined('TSDIR') ? rtrim((string)TSDIR, '/\\') : dirname(__DIR__);
    $file = $root . '/' . ltrim($rel, '/');
    $ver  = is_file($file) ? '?v=' . filemtime($file) : '';

    return rp_h($BASEURL . '/' . ltrim($rel, '/') . $ver);
}

function renderPageStyles(): void
{
   global $BASEURL;
   
	echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/reports.css?ver=2">';
}

function renderPageAssets(): void
{
    global $BASEURL;
	
	$messages = json_encode(REPORT_MESSAGES, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    ?>
<script type="application/json" id="rp-messages"><?= $messages ?></script>
<script src="<?= $BASEURL ?>/admin/scripts/reports.js?ver=2"></script>
    <?php
}