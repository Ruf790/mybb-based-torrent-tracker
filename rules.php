<?php
declare(strict_types=1);

/**
 * Define application version
 */
define('RULES_VERSION', '1.0');

/**
 * Include required files
 */
require_once __DIR__ . '/global.php';
$lang->load('rules');
require_once INC_PATH . '/class_parser.php';

/**
 * Placeholder formatter: substitutes {1}, {2}… and %1$s, %2$s…
 * ($lang->load() converts {N} into %N$s)
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string) $arg;
            $map['%' . $n . '$s'] = (string) $arg;
        }
        return strtr($str, $map);
    }
}

/**
 * Escaped lang strings for HTML output (lang values are plain text)
 */
$L = array_map(
    static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
    $lang->rules
);

/**
 * Enable output compression
 */
gzip();

/**
 * Initialize post parser
 */
$parser = new PostParser();

/**
 * Parser configuration
 */
$parserOptions = [
    'allow_html'      => 0,
    'allow_mycode'    => 1,
    'allow_smilies'   => 1,
    'allow_imgcode'   => 1,
    'allow_videocode' => 1,
    'filter_badwords' => 1,
];

/**
 * Usergroup display meta: [name, Font Awesome icon, soft tone]
 * IDs match UC_* constants (1 Guest … 9 Banned). Names come from lang (grp_N).
 */
$groupMeta = [
    1 => [$lang->rules['grp_1'], 'fa-user-clock',     'muted'],
    2 => [$lang->rules['grp_2'], 'fa-user',           'primary'],
    3 => [$lang->rules['grp_3'], 'fa-bolt',           'info'],
    4 => [$lang->rules['grp_4'], 'fa-crown',          'warning'],
    5 => [$lang->rules['grp_5'], 'fa-cloud-arrow-up', 'success'],
    6 => [$lang->rules['grp_6'], 'fa-shield-halved',  'primary'],
    7 => [$lang->rules['grp_7'], 'fa-user-shield',    'danger'],
    8 => [$lang->rules['grp_8'], 'fa-user-gear',      'danger'],
    9 => [$lang->rules['grp_9'], 'fa-ban',            'muted'],
];

/**
 * Check user permissions
 */
function hasRuleAccess(?array $user, string $ruleGroups): bool
{
    $ruleGroups = trim($ruleGroups);

    // Public rule
    if ($ruleGroups === '' || $ruleGroups === '0' || $ruleGroups === '[0]') {
        return true;
    }

    // No user logged in
    if (!$user || !isset($user['usergroup'])) {
        return false;
    }

    $userGroup = (string) $user['usergroup'];

    // Plain "5" or bracketed "[5][6][7]"
    return $ruleGroups === $userGroup
        || str_contains($ruleGroups, '[' . $userGroup . ']');
}

/**
 * Extract group IDs from "[5][6][7]" format
 *
 * @return int[]
 */
function rules_group_ids(string $ruleGroups): array
{
    if (!preg_match_all('/\[(\d+)\]/', $ruleGroups, $m)) {
        return ctype_digit($ruleGroups) && $ruleGroups !== '0' ? [(int) $ruleGroups] : [];
    }
    return array_values(array_filter(array_map('intval', $m[1]), static fn(int $g): bool => $g > 0));
}

/**
 * Pick an icon + soft tone for a rule by keywords in its title (English + Russian)
 *
 * @return array{0:string,1:string}
 */
function rules_icon(string $title): array
{
    $t = mb_strtolower($title, 'UTF-8');

    $map = [
        ['fa-person-running',       'danger',  ['hit and run', 'hit & run', 'h&r', 'hnr']],
        ['fa-scale-balanced',       'warning', ['ratio', 'рейтинг', 'ратио']],
        ['fa-seedling',             'success', ['seed', 'сид', 'раздач']],
        ['fa-cloud-arrow-up',       'success', ['upload', 'аплоад', 'загруз']],
        ['fa-cloud-arrow-down',     'info',    ['download', 'leech', 'скачив']],
        ['fa-user-secret',          'danger',  ['cheat', 'читер', 'обман', 'мультиакк', 'multi']],
        ['fa-crown',                'warning', ['vip']],
        ['fa-coins',                'warning', ['bonus', 'бонус', 'points']],
        ['fa-envelope-open-text',   'info',    ['invite', 'инвайт', 'приглаш']],
        ['fa-hand-holding-heart',   'primary', ['request', 'запрос', 'offer', 'предлож']],
        ['fa-magnet',               'danger',  ['torrent', 'торрент', 'релиз', 'release']],
        ['fa-comment-dots',         'info',    ['chat', 'shout', 'чат']],
        ['fa-comments',             'primary', ['forum', 'форум', 'thread', 'post']],
        ['fa-comment',              'primary', ['comment', 'коммент']],
        ['fa-envelope',             'info',    ['private message', 'личн', ' pm']],
        ['fa-image',                'info',    ['avatar', 'аватар', 'image', 'картин', 'screenshot', 'скриншот']],
        ['fa-signature',            'primary', ['signature', 'подпис']],
        ['fa-shield-halved',        'primary', ['staff', 'moderat', 'admin', 'модерат', 'администр']],
        ['fa-id-card',              'primary', ['account', 'аккаунт', 'registr', 'регистр', 'username', 'profile', 'профил']],
        ['fa-lock',                 'muted',   ['security', 'privacy', 'password', 'безопас', 'парол']],
        ['fa-triangle-exclamation', 'danger',  ['warn', 'ban', 'punish', 'предупр', 'бан', 'наказ']],
        ['fa-gavel',                'primary', ['general', 'basic', 'main', 'общ', 'основ']],
    ];

    foreach ($map as [$icon, $tone, $keywords]) {
        foreach ($keywords as $kw) {
            if (str_contains($t, $kw)) {
                return [$icon, $tone];
            }
        }
    }

    return ['fa-scroll', 'primary'];
}

/**
 * Is the current site language Russian? (rule title_ru / text_ru are used then)
 */
function rules_is_russian(): bool
{
    global $lang;

    // Primary: the lang file that was actually loaded tells us the language
    $code = $lang->rules['lang_code'] ?? null;
    if (is_string($code) && $code !== '') {
        return $code === 'ru';
    }

    // Fallback: language property / cookie
    $cur = (isset($lang->language) && is_string($lang->language))
        ? $lang->language
        : (string) ($_COOKIE['ts_language'] ?? 'english');

    return strtolower(trim($cur)) === 'russian';
}

/**
 * Rough word count (works for Cyrillic too)
 */
function rules_word_count(string $html): int
{
    return (int) preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($html));
}

/**
 * Render group badges for a rule footer
 */
function rules_group_chips(string $ruleGroups, array $groupMeta): string
{
    global $lang;

    $ids = rules_group_ids($ruleGroups);

    if (!$ids) {
        return '<span class="rls-badge rls-soft-info"><i class="fa-solid fa-globe"></i>'
             . htmlspecialchars($lang->rules['grp_all_users'], ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $html = '';
    foreach ($ids as $gid) {
        [$name, $icon, $tone] = $groupMeta[$gid] ?? [ags_fmt($lang->rules['grp_unknown'], $gid), 'fa-users', 'muted'];
        $html .= '<span class="rls-badge rls-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i>'
               . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    return $html;
}

/**
 * Fetch rules visible to the current user
 */
$rules       = [];
$loadError   = null;
$totalWords  = 0;
$publicCount = 0;
$useRu       = rules_is_russian();

try {
    $result = $db->sql_query_prepared('SELECT id, title, text, title_ru, text_ru, usergroups FROM rules ORDER BY id');

    while ($result && ($rule = $db->fetch_array($result))) {
        $ruleGroups = (string) ($rule['usergroups'] ?? '');

        if (!hasRuleAccess($CURUSER ?? null, $ruleGroups)) {
            continue;
        }

        $id       = (int) ($rule['id'] ?? 0);
        // Russian version when available, English otherwise
        $titleRu  = trim((string) ($rule['title_ru'] ?? ''));
        $textRu   = trim((string) ($rule['text_ru'] ?? ''));
        $rawTitle = ($useRu && $titleRu !== '') ? $titleRu : (string) ($rule['title'] ?? '');
        $rawText  = ($useRu && $textRu !== '')  ? (string) $rule['text_ru'] : (string) ($rule['text'] ?? '');
        $content  = $parser->parse_message($rawText, $parserOptions);
        $words    = rules_word_count($content);
        $totalWords += $words;

        if (!rules_group_ids($ruleGroups)) {
            $publicCount++;
        }

        // Icon/tone from the English title, so both languages look the same
        [$icon, $tone] = rules_icon((string) ($rule['title'] ?? '') ?: $rawTitle);

        $rules[] = [
            'id'      => $id,
            'num'     => str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'title'   => htmlspecialchars($rawTitle !== '' ? $rawTitle : $lang->rules['lbl_untitled'], ENT_QUOTES, 'UTF-8'),
            'icon'    => $icon,
            'tone'    => $tone,
            'content' => $content,
            'groups'  => $ruleGroups,
            'minutes' => max(1, (int) ceil($words / 200)),
        ];
    }

    if ($result) {
        $db->free_result($result);
    }
} catch (Throwable $e) {
    error_log("Rules display error [{$e->getFile()}:{$e->getLine()}]: " . $e->getMessage());
    $loadError = (defined('DEBUG_MODE') && DEBUG_MODE)
        ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        : $L['err_contact_admin'];
}

$ruleCount       = count($rules);
$restrictedCount = $ruleCount - $publicCount;
$totalMinutes    = max(1, (int) ceil($totalWords / 200));

// Who is viewing
$viewerGroup = isset($CURUSER['usergroup']) ? (int) $CURUSER['usergroup'] : 1;
[$viewerName, $viewerIcon, $viewerTone] = $groupMeta[$viewerGroup] ?? [ags_fmt($lang->rules['grp_unknown'], $viewerGroup), 'fa-users', 'muted'];
$viewerName = htmlspecialchars($viewerName, ENT_QUOTES, 'UTF-8');

/**
 * JS strings: js_* keys without the prefix
 */
$jsLang = [];
foreach ($lang->rules as $k => $v) {
    if (str_starts_with((string) $k, 'js_')) {
        $jsLang[substr((string) $k, 3)] = (string) $v;
    }
}

/**
 * Start output
 */
stdhead();

/**
 * Styles (all scoped under .rls-page)
 */
echo <<<'CSS'
<style>
    .rls-page { font-size: 1.055rem; }

    /* ── Soft tones ───────────────────────────────────────────────────── */
    .rls-page .rls-soft-primary { background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
    .rls-page .rls-soft-success { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
    .rls-page .rls-soft-info    { background: var(--bs-info-bg-subtle);    color: var(--bs-info-text-emphasis); }
    .rls-page .rls-soft-warning { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
    .rls-page .rls-soft-danger  { background: var(--bs-danger-bg-subtle);  color: var(--bs-danger-text-emphasis); }
    .rls-page .rls-soft-muted   { background: var(--bs-tertiary-bg);       color: var(--bs-secondary-color); }

    /* ── Cards ────────────────────────────────────────────────────────── */
    .rls-page .rls-card {
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 1rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
    }

    .rls-page .rls-head {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
    }

    .rls-page .rls-head__icon {
        flex: 0 0 auto;
        width: 3.25rem;
        height: 3.25rem;
        display: grid;
        place-items: center;
        border-radius: .9rem;
        font-size: 1.4rem;
    }

    .rls-page .rls-title {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--bs-emphasis-color);
    }

    .rls-page .rls-head p {
        margin: .15rem 0 0;
        color: var(--bs-secondary-color);
    }

    /* ── KPI tiles ────────────────────────────────────────────────────── */
    .rls-page .rls-kpi {
        display: flex;
        align-items: center;
        gap: .9rem;
        padding: 1rem 1.15rem;
        height: 100%;
    }

    .rls-page .rls-kpi__icon {
        flex: 0 0 auto;
        width: 2.75rem;
        height: 2.75rem;
        display: grid;
        place-items: center;
        border-radius: .75rem;
        font-size: 1.15rem;
    }

    .rls-page .rls-kpi__value {
        font-size: 1.45rem;
        font-weight: 700;
        line-height: 1.1;
        color: var(--bs-emphasis-color);
    }

    .rls-page .rls-kpi__label {
        font-size: .85rem;
        color: var(--bs-secondary-color);
    }

    /* ── Badges / pills / meta ────────────────────────────────────────── */
    .rls-page .rls-badge {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .25rem .65rem;
        border-radius: 999px;
        font-size: .78rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .rls-page .rls-pill { border-radius: 999px; }

    .rls-page .rls-meta {
        font-size: .8rem;
        color: var(--bs-secondary-color);
    }

    .rls-page .rls-meta i { margin-right: .3rem; }

    .rls-page .rls-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .3rem .75rem;
        border-radius: 999px;
        font-size: .82rem;
        text-decoration: none;
        border: 0;
    }

    .rls-page .rls-chip:hover { filter: brightness(.95); }

    /* ── Search card ──────────────────────────────────────────────────── */
    .rls-page .rls-search { padding: 1rem 1.25rem; }

    .rls-page .rls-search .input-group-text {
        background: var(--bs-tertiary-bg);
        color: var(--bs-secondary-color);
        border-radius: 999px 0 0 999px;
    }

    .rls-page .rls-search .form-control { border-radius: 0 999px 999px 0; }

    .rls-page .rls-search kbd {
        font-size: .72rem;
        background: var(--bs-tertiary-bg);
        color: var(--bs-secondary-color);
        border: 1px solid var(--bs-border-color);
    }

    /* ── Contents ─────────────────────────────────────────────────────── */
    .rls-page .rls-toc {
        position: sticky;
        top: 1rem;
        max-height: calc(100vh - 2rem);
        overflow-y: auto;
        padding: 1rem;
    }

    .rls-page .rls-toc__title {
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
        padding: 0 .4rem .5rem;
    }

    .rls-page .rls-toc__link {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .45rem .55rem;
        border-radius: .6rem;
        color: var(--bs-body-color);
        text-decoration: none;
        font-size: .9rem;
        transition: background .15s ease;
    }

    .rls-page .rls-toc__ico {
        flex: 0 0 auto;
        width: 1.8rem;
        height: 1.8rem;
        display: grid;
        place-items: center;
        border-radius: .5rem;
        font-size: .8rem;
    }

    .rls-page .rls-toc__text {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .rls-page .rls-toc__link:hover { background: var(--bs-tertiary-bg); }

    .rls-page .rls-toc__link.is-active {
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary-text-emphasis);
        font-weight: 600;
    }

    .rls-page .rls-toc__link[hidden] { display: none; }

    /* ── Rule card ────────────────────────────────────────────────────── */
    .rls-page .rls-rule {
        overflow: hidden;
        margin-bottom: 1rem;
        scroll-margin-top: 1rem;
        transition: border-color .2s ease, box-shadow .2s ease;
    }

    .rls-page .rls-rule:hover {
        border-color: var(--bs-primary-border-subtle);
        box-shadow: 0 8px 22px -16px rgba(0, 0, 0, .35);
    }

    .rls-page .rls-rule[hidden] { display: none; }

    .rls-page .rls-rule.is-flash { animation: rlsFlash 1.6s ease-out; }

    .rls-page .rls-rule__head {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        cursor: pointer;
        user-select: none;
    }

    .rls-page .rls-rule__icon {
        flex: 0 0 auto;
        width: 2.75rem;
        height: 2.75rem;
        display: grid;
        place-items: center;
        border-radius: .75rem;
        font-size: 1.15rem;
    }

    .rls-page .rls-rule__heading {
        flex: 1 1 auto;
        min-width: 0;
    }

    .rls-page .rls-rule__title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--bs-emphasis-color);
    }

    .rls-page .rls-rule__metaline {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem .9rem;
        margin-top: .2rem;
    }

    .rls-page .rls-rule__actions {
        flex: 0 0 auto;
        display: flex;
        gap: .4rem;
    }

    .rls-page .rls-icon-btn {
        width: 2.2rem;
        height: 2.2rem;
        display: grid;
        place-items: center;
        border: 1px solid var(--bs-border-color);
        border-radius: 50%;
        background: transparent;
        color: var(--bs-secondary-color);
        transition: color .15s ease, background .15s ease, border-color .15s ease;
    }

    .rls-page .rls-icon-btn:hover {
        color: var(--bs-primary-text-emphasis);
        background: var(--bs-primary-bg-subtle);
        border-color: var(--bs-primary-border-subtle);
    }

    .rls-page .rls-icon-btn.is-done {
        color: var(--bs-success-text-emphasis);
        background: var(--bs-success-bg-subtle);
        border-color: var(--bs-success-border-subtle);
    }

    .rls-page .rls-rule__toggle i { transition: transform .3s ease; }
    .rls-page .rls-rule.is-collapsed .rls-rule__toggle i { transform: rotate(180deg); }

    .rls-page .rls-rule__collapse {
        display: grid;
        grid-template-rows: 1fr;
        transition: grid-template-rows .3s ease;
    }

    .rls-page .rls-rule.is-collapsed .rls-rule__collapse { grid-template-rows: 0fr; }

    .rls-page .rls-rule__inner {
        min-height: 0;
        overflow: hidden;
    }

    .rls-page .rls-rule__body {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid var(--bs-border-color);
        line-height: 1.7;
        color: var(--bs-body-color);
    }

    .rls-page .rls-rule__foot {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: .5rem;
        padding: .7rem 1.5rem;
        background: var(--bs-tertiary-bg);
        border-top: 1px solid var(--bs-border-color);
    }

    /* ── Rule text typography ─────────────────────────────────────────── */
    .rls-page .rls-text > :first-child { margin-top: 0; }
    .rls-page .rls-text > :last-child  { margin-bottom: 0; }

    .rls-page .rls-text h1, .rls-page .rls-text h2,
    .rls-page .rls-text h3, .rls-page .rls-text h4 {
        font-weight: 700;
        color: var(--bs-emphasis-color);
        margin: 1.5rem 0 .75rem;
    }

    .rls-page .rls-text strong, .rls-page .rls-text b { color: var(--bs-emphasis-color); }
    .rls-page .rls-text img { max-width: 100%; height: auto; border-radius: .5rem; }

    .rls-page .rls-text ul { list-style: none; padding-left: 0; }

    .rls-page .rls-text ul li {
        position: relative;
        padding-left: 1.6rem;
        margin-bottom: .55rem;
    }

    .rls-page .rls-text ul li::before {
        content: '\f058';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        left: 0;
        top: .05rem;
        font-size: .9em;
        color: var(--bs-primary);
    }

    .rls-page .rls-text ol { counter-reset: item; list-style: none; padding-left: 0; }

    .rls-page .rls-text ol li {
        counter-increment: item;
        position: relative;
        padding-left: 2.3rem;
        margin-bottom: .6rem;
    }

    .rls-page .rls-text ol li::before {
        content: counter(item);
        position: absolute;
        left: 0;
        top: .05rem;
        width: 1.6rem;
        height: 1.6rem;
        display: grid;
        place-items: center;
        border-radius: .5rem;
        font-size: .78rem;
        font-weight: 700;
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary-text-emphasis);
    }

    .rls-page .rls-text blockquote {
        position: relative;
        margin: 1.25rem 0;
        padding: .9rem 1.25rem .9rem 3rem;
        border-radius: .75rem;
        background: var(--bs-warning-bg-subtle);
        color: var(--bs-warning-text-emphasis);
    }

    .rls-page .rls-text blockquote::before {
        content: '\f071';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        left: 1.1rem;
        top: .95rem;
    }

    .rls-page .rls-text code {
        padding: .15rem .4rem;
        border-radius: .35rem;
        background: var(--bs-tertiary-bg);
        border: 1px solid var(--bs-border-color);
        font-size: .9em;
    }

    .rls-page .rls-text pre {
        padding: 1rem;
        border-radius: .75rem;
        background: var(--bs-tertiary-bg);
        border: 1px solid var(--bs-border-color);
        overflow-x: auto;
    }

    .rls-page .rls-text table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
    .rls-page .rls-text th, .rls-page .rls-text td { border: 1px solid var(--bs-border-color); padding: .5rem .75rem; }
    .rls-page .rls-text th { background: var(--bs-tertiary-bg); }

    /* ── Empty / error ────────────────────────────────────────────────── */
    .rls-page .rls-empty {
        text-align: center;
        padding: 3rem 1.5rem;
    }

    .rls-page .rls-empty[hidden] { display: none; }

    .rls-page .rls-empty__icon {
        width: 4.5rem;
        height: 4.5rem;
        margin: 0 auto 1rem;
        display: grid;
        place-items: center;
        border-radius: 50%;
        font-size: 1.8rem;
    }

    /* ── Back to top ──────────────────────────────────────────────────── */
    .rls-page .rls-top {
        position: fixed;
        right: 1rem;
        bottom: 1rem;
        z-index: 1000;
        width: 2.9rem;
        height: 2.9rem;
        display: grid;
        place-items: center;
        border-radius: 50%;
        box-shadow: 0 10px 22px -10px rgba(0, 0, 0, .45);
        opacity: 0;
        transform: translateY(12px);
        pointer-events: none;
        transition: opacity .25s ease, transform .25s ease;
    }

    .rls-page .rls-top.is-visible { opacity: 1; transform: none; pointer-events: auto; }

    @keyframes rlsFlash {
        0%   { box-shadow: 0 0 0 0 var(--bs-primary); }
        30%  { box-shadow: 0 0 0 .35rem var(--bs-primary-bg-subtle); }
        100% { box-shadow: 0 0 0 0 transparent; }
    }

    @media (prefers-reduced-motion: reduce) {
        .rls-page *, .rls-page .rls-rule__collapse { transition: none !important; animation: none !important; }
    }

    /* ── Print ────────────────────────────────────────────────────────── */
    @media print {
        .rls-page .rls-search, .rls-page .rls-toc-col, .rls-page .rls-rule__actions,
        .rls-page .rls-top { display: none !important; }
        .rls-page .rls-main-col { width: 100% !important; }
        .rls-page .rls-rule { break-inside: avoid; box-shadow: none !important; }
        .rls-page .rls-rule[hidden] { display: block !important; }
        .rls-page .rls-rule__collapse { grid-template-rows: 1fr !important; }
    }
</style>
CSS;
?>

<div class="container mt-3 py-4 rls-page">

    <!-- Header -->
    <div class="rls-card rls-head mb-3">
        <div class="rls-head__icon rls-soft-primary"><i class="fa-solid fa-book-open"></i></div>
        <div class="flex-grow-1">
            <h1 class="rls-title"><?= $L['pane_title'] ?></h1>
            <p><?= $L['pane_subtitle'] ?></p>
        </div>
        <span class="rls-badge rls-soft-<?= $viewerTone ?> d-none d-md-inline-flex">
            <i class="fa-solid <?= $viewerIcon ?>"></i><?= ags_fmt($L['lbl_viewing_as'], $viewerName) ?>
        </span>
    </div>

    <!-- KPI tiles -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="rls-card rls-kpi">
                <div class="rls-kpi__icon rls-soft-primary"><i class="fa-solid fa-list-ol"></i></div>
                <div>
                    <div class="rls-kpi__value"><?= number_format($ruleCount) ?></div>
                    <div class="rls-kpi__label"><?= $L['kpi_rules_for_you'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="rls-card rls-kpi">
                <div class="rls-kpi__icon rls-soft-info"><i class="fa-solid fa-globe"></i></div>
                <div>
                    <div class="rls-kpi__value"><?= number_format($publicCount) ?></div>
                    <div class="rls-kpi__label"><?= $L['kpi_for_all'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="rls-card rls-kpi">
                <div class="rls-kpi__icon rls-soft-warning"><i class="fa-solid fa-user-tag"></i></div>
                <div>
                    <div class="rls-kpi__value"><?= number_format($restrictedCount) ?></div>
                    <div class="rls-kpi__label"><?= $L['kpi_group_specific'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="rls-card rls-kpi">
                <div class="rls-kpi__icon rls-soft-success"><i class="fa-regular fa-clock"></i></div>
                <div>
                    <div class="rls-kpi__value"><?= ags_fmt($L['kpi_minutes'], $totalMinutes) ?></div>
                    <div class="rls-kpi__label"><?= $L['kpi_reading_time'] ?></div>
                </div>
            </div>
        </div>
    </div>

<?php
if ($loadError !== null) {
    /* ── Error state ─────────────────────────────────────────────────── */
    echo <<<HTML
    <div class="rls-card rls-empty" role="alert">
        <div class="rls-empty__icon rls-soft-danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h4 class="rls-title">{$L['sec_error']}</h4>
        <p class="text-body-secondary mb-1">{$L['msg_error']}</p>
        <p class="rls-meta mb-3"><i class="fa-solid fa-circle-info"></i><strong>{$L['lbl_error']}</strong> {$loadError}</p>
        <div class="d-flex justify-content-center flex-wrap gap-2">
            <a href="javascript:location.reload()" class="btn btn-primary rls-pill"><i class="fa-solid fa-rotate-right me-1"></i>{$L['btn_retry']}</a>
            <a href="index.php" class="btn btn-outline-secondary rls-pill"><i class="fa-solid fa-house me-1"></i>{$L['btn_go_home']}</a>
        </div>
    </div>
    HTML;
} elseif ($ruleCount === 0) {
    /* ── Empty state ─────────────────────────────────────────────────── */
    echo <<<HTML
    <div class="rls-card rls-empty">
        <div class="rls-empty__icon rls-soft-muted"><i class="fa-solid fa-inbox"></i></div>
        <h4 class="rls-title">{$L['sec_empty']}</h4>
        <p class="text-body-secondary mb-3">{$L['msg_empty']}</p>
        <a href="index.php" class="btn btn-primary rls-pill"><i class="fa-solid fa-house me-1"></i>{$L['btn_return_home']}</a>
    </div>
    HTML;
} else {
    /* ── Search card ─────────────────────────────────────────────────── */
    $showingHtml = ags_fmt($L['lbl_showing'], '<b id="rlsShown">' . $ruleCount . '</b>', $ruleCount);
    $chipHtml    = ags_fmt($L['lbl_search_chip'], '<b id="rlsChipText"></b>');

    echo <<<HTML
    <div class="rls-card rls-search mb-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="search" class="form-control" id="rlsSearch" placeholder="{$L['ph_search']}" autocomplete="off" aria-label="{$L['aria_search']}">
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary rls-pill" id="rlsExpandAll"><i class="fa-solid fa-angles-down me-1"></i>{$L['btn_expand_all']}</button>
                <button type="button" class="btn btn-outline-secondary rls-pill" id="rlsCollapseAll"><i class="fa-solid fa-angles-up me-1"></i>{$L['btn_collapse_all']}</button>
                <button type="button" class="btn btn-primary rls-pill" id="rlsPrint"><i class="fa-solid fa-print me-1"></i>{$L['btn_print']}</button>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
            <span class="rls-meta"><i class="fa-solid fa-list"></i>{$showingHtml}</span>
            <button type="button" class="rls-chip rls-soft-primary d-none" id="rlsChip" title="{$L['tip_remove_filter']}" aria-label="{$L['tip_remove_filter']}">
                <i class="fa-solid fa-filter"></i><span>{$chipHtml}</span><i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>
    HTML;

    /* ── Contents ────────────────────────────────────────────────────── */
    $tocHtml = '';
    foreach ($rules as $r) {
        $tocHtml .= '<a href="#rule-' . $r['num'] . '" class="rls-toc__link" data-target="rule-' . $r['num'] . '">'
                  . '<span class="rls-toc__ico rls-soft-' . $r['tone'] . '"><i class="fa-solid ' . $r['icon'] . '" aria-hidden="true"></i></span>'
                  . '<span class="rls-toc__text">' . $r['title'] . '</span>'
                  . '<span class="rls-meta">' . $r['num'] . '</span>'
                  . '</a>';
    }

    echo <<<HTML
    <div class="row g-3">
        <div class="col-lg-3 d-none d-lg-block rls-toc-col">
            <nav class="rls-card rls-toc" aria-label="{$L['aria_contents']}">
                <div class="rls-toc__title"><i class="fa-solid fa-list-ul me-1"></i>{$L['sec_contents']}</div>
                {$tocHtml}
            </nav>
        </div>
        <div class="col-12 col-lg-9 rls-main-col">
    HTML;

    /* ── Rule cards ──────────────────────────────────────────────────── */
    foreach ($rules as $r) {
        $id      = $r['id'];
        $num     = $r['num'];
        $title   = $r['title'];
        $icon    = $r['icon'];
        $tone    = $r['tone'];
        $content = $r['content'];
        $minutes = $r['minutes'];
        $chips   = rules_group_chips($r['groups'], $groupMeta);
        $ruleNum = ags_fmt($L['lbl_rule_num'], $num);
        $minRead = ags_fmt($L['lbl_min_read'], $minutes);

        echo <<<HTML
            <article class="rls-card rls-rule" id="rule-{$num}" data-rule-id="{$id}">
                <header class="rls-rule__head">
                    <div class="rls-rule__icon rls-soft-{$tone}" aria-hidden="true"><i class="fa-solid {$icon}"></i></div>
                    <div class="rls-rule__heading">
                        <h2 class="rls-rule__title">{$title}</h2>
                        <div class="rls-rule__metaline">
                            <span class="rls-badge rls-soft-muted"><i class="fa-solid fa-hashtag"></i>{$ruleNum}</span>
                            <span class="rls-meta"><i class="fa-regular fa-clock"></i>{$minRead}</span>
                        </div>
                    </div>
                    <div class="rls-rule__actions">
                        <button type="button" class="rls-icon-btn rls-rule__link" title="{$L['tip_copy_link']}" aria-label="{$L['tip_copy_link']}">
                            <i class="fa-solid fa-link"></i>
                        </button>
                        <button type="button" class="rls-icon-btn rls-rule__toggle" aria-expanded="true" aria-controls="rule-body-{$id}" aria-label="{$L['js_rule_collapse']}">
                            <i class="fa-solid fa-chevron-up"></i>
                        </button>
                    </div>
                </header>
                <div class="rls-rule__collapse" id="rule-body-{$id}">
                    <div class="rls-rule__inner">
                        <div class="rls-rule__body rls-text">
                            {$content}
                        </div>
                        <footer class="rls-rule__foot">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="rls-meta"><i class="fa-solid fa-user-tag"></i>{$L['lbl_applies_to']}</span>
                                {$chips}
                            </div>
                            <span class="rls-badge rls-soft-success"><i class="fa-solid fa-circle-check"></i>{$L['lbl_applies_to_you']}</span>
                        </footer>
                    </div>
                </div>
            </article>
        HTML;
    }

    echo <<<HTML
            <div class="rls-card rls-empty" id="rlsNoMatch" hidden>
                <div class="rls-empty__icon rls-soft-warning"><i class="fa-solid fa-magnifying-glass-minus"></i></div>
                <h4 class="rls-title">{$L['sec_no_match']}</h4>
                <p class="text-body-secondary mb-3">{$L['msg_no_match']}</p>
                <button type="button" class="btn btn-outline-secondary rls-pill" id="rlsNoMatchReset"><i class="fa-solid fa-rotate-left me-1"></i>{$L['btn_reset_search']}</button>
            </div>
        </div>
    </div>

    <button type="button" class="btn btn-primary rls-top" id="rlsTop" aria-label="{$L['aria_back_to_top']}">
        <i class="fa-solid fa-arrow-up"></i>
    </button>
    HTML;
}
?>
</div>

<?php
/**
 * JavaScript
 */
?>
<script>
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php
echo <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', () => {
    /* ── i18n: t(key, fallback, ...args), substitutes {1} and %1$s ──── */
    const t = (key, fallback, ...args) => {
        let s = (typeof AGS_LANG === 'object' && AGS_LANG && typeof AGS_LANG[key] === 'string')
            ? AGS_LANG[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    };

    const rules = Array.from(document.querySelectorAll('.rls-rule'));
    if (!rules.length) return;

    const tocLinks = Array.from(document.querySelectorAll('.rls-toc__link'));
    const search   = document.getElementById('rlsSearch');
    const shownEl  = document.getElementById('rlsShown');
    const chip     = document.getElementById('rlsChip');
    const chipText = document.getElementById('rlsChipText');
    const noMatch  = document.getElementById('rlsNoMatch');
    const topBtn   = document.getElementById('rlsTop');

    /* ── Collapse / expand ─────────────────────────────────────────── */
    const setState = (rule, expanded) => {
        const toggle = rule.querySelector('.rls-rule__toggle');
        rule.classList.toggle('is-collapsed', !expanded);
        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.setAttribute('aria-label', expanded ? t('rule_collapse', 'Collapse rule') : t('rule_expand', 'Expand rule'));
        }
    };

    rules.forEach(rule => {
        const head    = rule.querySelector('.rls-rule__head');
        const linkBtn = rule.querySelector('.rls-rule__link');

        head?.addEventListener('click', e => {
            if (e.target.closest('.rls-rule__link')) return;
            setState(rule, rule.classList.contains('is-collapsed'));
        });

        /* Copy link */
        linkBtn?.addEventListener('click', async e => {
            e.stopPropagation();
            const url  = location.href.split('#')[0] + '#' + rule.id;
            const icon = linkBtn.querySelector('i');
            try {
                await navigator.clipboard.writeText(url);
            } catch (_) {
                history.replaceState(null, '', '#' + rule.id);
            }
            linkBtn.classList.add('is-done');
            icon.className = 'fa-solid fa-check';
            setTimeout(() => {
                linkBtn.classList.remove('is-done');
                icon.className = 'fa-solid fa-link';
            }, 1500);
        });
    });

    document.getElementById('rlsExpandAll')?.addEventListener('click', () => rules.forEach(r => setState(r, true)));
    document.getElementById('rlsCollapseAll')?.addEventListener('click', () => rules.forEach(r => setState(r, false)));

    document.getElementById('rlsPrint')?.addEventListener('click', () => {
        rules.forEach(r => setState(r, true));
        setTimeout(() => window.print(), 50);
    });

    /* ── Search ────────────────────────────────────────────────────── */
    const index = rules.map(r => ({
        el: r,
        text: ((r.querySelector('.rls-rule__title')?.textContent || '') + ' ' +
               (r.querySelector('.rls-text')?.textContent || '')).toLowerCase()
    }));

    const runSearch = () => {
        const raw = search.value.trim();
        const q   = raw.toLowerCase();
        let shown = 0;

        index.forEach(({ el, text }) => {
            const match = q === '' || text.includes(q);
            el.hidden = !match;
            if (match) {
                shown++;
                if (q !== '') setState(el, true);
            }
            const toc = tocLinks.find(l => l.dataset.target === el.id);
            if (toc) toc.hidden = !match;
        });

        shownEl.textContent = shown;
        chipText.textContent = raw;
        chip.classList.toggle('d-none', q === '');
        if (noMatch) noMatch.hidden = shown !== 0;
    };

    const resetSearch = () => {
        search.value = '';
        runSearch();
        search.focus();
    };

    search?.addEventListener('input', runSearch);
    chip?.addEventListener('click', resetSearch);
    document.getElementById('rlsNoMatchReset')?.addEventListener('click', resetSearch);

    document.addEventListener('keydown', e => {
        const tag = (e.target.tagName || '').toLowerCase();
        if (e.key === '/' && !['input', 'textarea', 'select'].includes(tag) && !e.target.isContentEditable) {
            e.preventDefault();
            search?.focus();
        } else if (e.key === 'Escape' && document.activeElement === search && search.value) {
            resetSearch();
        }
    });

    /* ── Scroll-spy ────────────────────────────────────────────────── */
    if ('IntersectionObserver' in window && tocLinks.length) {
        const spy = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                tocLinks.forEach(l => l.classList.toggle('is-active', l.dataset.target === entry.target.id));
            });
        }, { rootMargin: '-20% 0px -70% 0px' });
        rules.forEach(r => spy.observe(r));
    }

    /* ── Anchors + flash highlight ─────────────────────────────────── */
    const goTo = hash => {
        if (!hash || hash.length < 2) return false;
        const target = document.getElementById(decodeURIComponent(hash.slice(1)));
        if (!target) return false;
        const isRule = target.classList.contains('rls-rule');
        if (isRule) setState(target, true);
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (isRule) {
            target.classList.remove('is-flash');
            void target.offsetWidth;
            target.classList.add('is-flash');
        }
        return true;
    };

    document.querySelectorAll('.rls-toc__link, .rls-text a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const hash = a.getAttribute('href');
            if (goTo(hash)) {
                e.preventDefault();
                history.replaceState(null, '', hash);
            }
        });
    });

    if (location.hash) setTimeout(() => goTo(location.hash), 300);

    /* ── Back to top ───────────────────────────────────────────────── */
    if (topBtn) {
        const onScroll = () => topBtn.classList.toggle('is-visible', window.scrollY > 600);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        topBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }
});
</script>
JS;

/**
 * End output
 */
stdfoot();