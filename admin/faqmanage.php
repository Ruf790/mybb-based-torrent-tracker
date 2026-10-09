<?php

declare(strict_types=1);

require_once INC_PATH . '/functions_faq.php';

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3" role="alert">
        <i class="fa-solid fa-ban me-2"></i><b>Error!</b> Direct initialization of this file is not allowed.
    </div>');
}

$lang->load('faqmanage');

// Версия ассетов - поднимать вручную при правке faqmanage.css / faqmanage.js.
const FAQM_ASSET_VER = 2;

define('TSFAQMANAGE_VERSION', '2.0.0');

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

function faqm_e(mixed $value): string
{
    return htmlspecialchars_uni((string)$value);
}

if (!function_exists('ags_fmt')) {
    /**
     * Подстановка {1}, {2}… (и %1$s — так их переписывает $lang->load()).
     */
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

/** Lang string (plain text, escape on output) with optional {1}, {2}… values. */
function faqm_t(string $key, string|int|float ...$args): string
{
    global $lang;

    $str = (string)($lang->faqmanage[$key] ?? $key);
    return $args ? ags_fmt($str, ...$args) : $str;
}

/** Count with the right plural form: <base>_one / _few / _many (ru: 1 вопрос, 2 вопроса, 5 вопросов). */
function faqm_plural(int $n, string $base): string
{
    if (faqm_t('lang_code') === 'ru') {
        $m10 = $n % 10;
        $m100 = $n % 100;
        $form = ($m10 === 1 && $m100 !== 11) ? 'one'
              : (($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) ? 'few' : 'many');
    } else {
        $form = $n === 1 ? 'one' : 'many';
    }
    return faqm_t($base . '_' . $form, $n);
}

/** Small RU badge: filled in or missing. */
function faqm_ru_badge(array $row): string
{
    $ok = trim((string)($row['name_ru'] ?? '')) !== ''
       && (!faqm_is_item($row) || trim((string)($row['description_ru'] ?? '')) !== '');

    return $ok
        ? '<span class="badge rounded-pill text-bg-success ms-2" title="' . faqm_e(faqm_t('tip_ru_ok')) . '"><i class="fa-solid fa-language me-1" aria-hidden="true"></i>RU</span>'
        : '<span class="badge rounded-pill text-bg-warning ms-2" title="' . faqm_e(faqm_t('tip_ru_missing')) . '"><i class="fa-solid fa-language me-1" aria-hidden="true"></i>RU —</span>';
}

/** Empty string => NULL, so the public page falls back to English. */
function faqm_null_if_empty(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : $value;
}

function faqm_url(string $query = ''): string
{
    global $_this_script_;

    return $_this_script_ . ($query !== '' ? '&' . $query : '');
}

/** Post/Redirect/Get: после любого изменения - редирект, F5 ничего не повторит. */
function faqm_redirect(string $query = ''): never
{
    header('Location: ' . faqm_url($query));
    exit;
}

function faqm_log(string $message): void
{
    global $mybb;

    write_log('FAQ: ' . $message . ' by ' . (string)($mybb->user['username'] ?? 'unknown'));
}

function faqm_csrf_ok(): bool
{
    global $mybb;

    return $_SERVER['REQUEST_METHOD'] === 'POST'
        && verify_post_check($mybb->get_input('my_post_key'), true);
}

function faqm_csrf_field(): string
{
    global $mybb;

    return '<input type="hidden" name="my_post_key" value="' . htmlspecialchars((string)$mybb->post_code, ENT_QUOTES) . '">';
}

/** Старые строки могли хранить type числом (1/2) - учитываем оба варианта. */
function faqm_is_item(array $row): bool
{
    return in_array((string)$row['type'], ['item', '2'], true);
}

/** @return list<array{id:int,name:string}> */
function faqm_categories(): array
{
    global $db;

    $out   = [];
    $query = $db->sql_query_prepared("SELECT id, name FROM faq WHERE type = 'category' ORDER BY disporder ASC, id ASC");

    while ($query && ($row = $db->fetch_array($query))) {
        $out[] = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
    }

    return $out;
}

function faqm_category_exists(int $id): bool
{
    global $db;

    if ($id <= 0) {
        return false;
    }

    $query = $db->sql_query_prepared("SELECT id FROM faq WHERE id = ? AND type = 'category' LIMIT 1", [$id]);

    return $query && $db->num_rows($query) > 0;
}

// ═══════════════════════════════════════════════════════════
// UI BUILDING BLOCKS
// ═══════════════════════════════════════════════════════════

function faqm_assets(): void
{
    global $BASEURL;

    $base = faqm_e($BASEURL);
    $ver  = FAQM_ASSET_VER;

    // JS strings: js_* keys without the prefix, plus lang_code for plural forms
    global $lang;
    $js = ['lang_code' => faqm_t('lang_code')];
    foreach ($lang->faqmanage as $k => $v) {
        if (str_starts_with((string)$k, 'js_')) {
            $js[substr((string)$k, 3)] = (string)$v;
        }
    }
    echo '<script>const AGS_LANG = ' . json_encode($js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';

    echo <<<HTML
    <link rel="stylesheet" href="{$base}/include/templates/default/style/sweetalert2.min.css">
    <link rel="stylesheet" href="{$base}/admin/templates/faqmanage.css?ver={$ver}">
    <script src="{$base}/scripts/sweetalert2.min.js" defer></script>
    <script src="{$base}/admin/scripts/faqmanage.js?ver={$ver}" defer></script>
    HTML;
}

function faqm_btn(string $href, string $icon, string $label, string $variant = 'outline-secondary'): string
{
    return '<a href="' . faqm_e($href) . '" class="btn btn-' . $variant . ' rounded-pill faqm-btn">'
        . '<i class="fa-solid ' . $icon . '" aria-hidden="true"></i><span>' . faqm_e($label) . '</span></a>';
}

function faqm_icon_link(string $href, string $icon, string $title, string $tone): string
{
    return '<a href="' . faqm_e($href) . '" class="faqm-icon-btn is-' . $tone . '" title="' . faqm_e($title) . '" aria-label="' . faqm_e($title) . '">'
        . '<i class="fa-solid ' . $icon . '" aria-hidden="true"></i></a>';
}

/**
 * Кнопка удаления. Раньше у каждой кнопки была своя <form> - а внутри формы
 * порядка сортировки это давало вложенные формы: браузер выкидывал внутренний
 * тег <form>, и скрытые do=delete/id уходили вместе с "Save Display Order",
 * удаляя последнюю категорию. Теперь одна общая форма вне таблицы (faqm_delete_form),
 * а кнопка лишь передаёт id через data-атрибуты.
 */
function faqm_delete_btn(int $id, string $name, string $kind, int $children = 0): string
{
    return '<button type="button" class="faqm-icon-btn is-danger" title="' . faqm_e(faqm_t('tip_delete')) . '" aria-label="' . faqm_e(faqm_t('aria_delete', $name)) . '"'
        . ' data-faqm-delete="' . $id . '" data-faqm-name="' . faqm_e($name) . '"'
        . ' data-faqm-kind="' . $kind . '" data-faqm-children="' . $children . '">'
        . '<i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>';
}

function faqm_delete_form(): string
{
    return '<form id="faqmDeleteForm" method="post" action="' . faqm_e(faqm_url()) . '" hidden>'
        . '<input type="hidden" name="do" value="delete">'
        . '<input type="hidden" name="id" value="" id="faqmDeleteId">'
        . faqm_csrf_field()
        . '</form>';
}

function faqm_kpi(string $icon, string $tone, int $value, string $label, string $hint): string
{
    return '<div class="faqm-kpi">'
        . '<span class="faqm-square is-' . $tone . '"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i></span>'
        . '<div class="faqm-kpi__body">'
        . '<div class="faqm-kpi__value">' . number_format($value) . '</div>'
        . '<div class="faqm-kpi__label">' . faqm_e($label) . '</div>'
        . '<div class="faqm-kpi__hint">' . faqm_e($hint) . '</div>'
        . '</div></div>';
}

/**
 * @param array{icon:string,tone:string,title:string,subtitle:string,actions?:string,crumb?:string} $head
 */
function faqm_page_start(array $head): void
{
    global $lang;

    stdhead(faqm_t('pane_title'), true, '', '');
    faqm_assets();

    $crumb   = $head['crumb'] ?? '';
    $actions = $head['actions'] ?? '';

    echo '<div class="faqm">
        <header class="faqm-head">
            <span class="faqm-square is-lg is-' . $head['tone'] . '"><i class="fa-solid ' . $head['icon'] . '" aria-hidden="true"></i></span>
            <div class="faqm-head__text">
                ' . ($crumb !== '' ? '<div class="faqm-head__crumb">' . $crumb . '</div>' : '') . '
                <h1 class="faqm-head__title">' . faqm_e($head['title']) . '</h1>
                <p class="faqm-head__subtitle">' . faqm_e($head['subtitle']) . '</p>
            </div>
            ' . ($actions !== '' ? '<div class="faqm-head__actions">' . $actions . '</div>' : '') . '
        </header>';

    faqm_flash();
    faqm_errors();
}

function faqm_page_end(): void
{
    echo '</div>';
}

function faqm_crumb_home(): string
{
    return '<a href="' . faqm_e(faqm_url()) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>' . faqm_e(faqm_t('lnk_all_categories')) . '</a>';
}

function faqm_flash(): void
{
    $messages = [
        'order'       => ['fa-arrow-down-1-9', faqm_t('flash_order')],
        'cat_created' => ['fa-folder-plus', faqm_t('flash_cat_created')],
        'q_created'   => ['fa-circle-plus', faqm_t('flash_q_created')],
        'updated'     => ['fa-floppy-disk', faqm_t('flash_updated')],
        'deleted'     => ['fa-trash-can', faqm_t('flash_deleted')],
    ];

    $key = (string)($_GET['msg'] ?? '');

    if (!isset($messages[$key])) {
        return;
    }

    [$icon, $text] = $messages[$key];

    echo '<div class="faqm-flash" role="status" data-faqm-flash>
        <i class="fa-solid ' . $icon . '" aria-hidden="true"></i><span>' . faqm_e($text) . '</span>
        <button type="button" class="faqm-flash__close" aria-label="' . faqm_e(faqm_t('aria_close')) . '" data-faqm-flash-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>';
}

function faqm_errors(): void
{
    global $faq_errors, $lang;

    if (empty($faq_errors)) {
        return;
    }

    $items = implode('', array_map(static fn($e) => '<li>' . faqm_e($e) . '</li>', $faq_errors));

    echo '<div class="faqm-alert" role="alert">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div><strong>' . faqm_e(faqm_t('lbl_error')) . '</strong><ul>' . $items . '</ul></div>
    </div>';
}

/** Полноценная страница ошибки - в том же оформлении, с кнопкой возврата. */
function faqm_error_page(string $message): void
{
    global $faq_errors;

    $faq_errors[] = $message;

    faqm_page_start([
        'icon'     => 'fa-circle-exclamation',
        'tone'     => 'danger',
        'title'    => faqm_t('sec_error_page'),
        'subtitle' => faqm_t('msg_error_page'),
        'actions'  => faqm_btn(faqm_url(), 'fa-arrow-left', faqm_t('btn_back_to_faq')),
    ]);
    faqm_page_end();
}

function faqm_savebar(string $idleText, string $saveLabel, string $cancelUrl = ''): string
{
    return '<div class="faqm-savebar" data-faqm-savebar>
        <div class="faqm-savebar__status">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span data-faqm-status data-idle="' . faqm_e($idleText) . '">' . faqm_e($idleText) . '</span>
        </div>
        <div class="faqm-savebar__actions">
            ' . ($cancelUrl !== '' ? faqm_btn($cancelUrl, 'fa-xmark', faqm_t('btn_cancel'), 'link') : '') . '
            <button type="reset" class="btn btn-outline-secondary rounded-pill faqm-btn">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>' . faqm_e(faqm_t('btn_undo')) . '</span>
            </button>
            <button type="submit" class="btn btn-primary rounded-pill faqm-btn faqm-btn--save">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span>' . faqm_e($saveLabel) . '</span>
            </button>
        </div>
    </div>';
}

// ═══════════════════════════════════════════════════════════
// ROUTING
// ═══════════════════════════════════════════════════════════

$do = (string)($_GET['do'] ?? $_POST['do'] ?? '');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$faq_errors = [];

match ($do) {
    'view'             => handleView($id),
    'savedisplayorder' => handleSaveDisplayOrder(),
    'delete'           => handleDelete($id),
    'new'              => handleNew(),
    'add'              => handleAdd($id),
    'edit'             => handleEdit($id),
    default            => handleDefault(),
};

stdfoot();

// ═══════════════════════════════════════════════════════════
// HANDLERS
// ═══════════════════════════════════════════════════════════

function handleDefault(): void
{
    global $db;

    $query = $db->sql_query_prepared("
        SELECT c.id, c.name, c.name_ru, c.type, c.disporder, COUNT(i.id) AS items
        FROM faq c
        LEFT JOIN faq i ON (i.pid = c.id AND i.type = 'item')
        WHERE c.type = 'category'
        GROUP BY c.id, c.name, c.name_ru, c.type, c.disporder
        ORDER BY c.disporder ASC, c.id ASC
    ");

    $categories = [];
    while ($query && ($row = $db->fetch_array($query))) {
        $categories[] = $row;
    }

    // Вопросы, чей pid не указывает на существующую категорию - на публичной
    // странице они не видны нигде, поэтому выводим их отдельно.
    $orphanQuery = $db->sql_query_prepared("
        SELECT i.id, i.name
        FROM faq i
        LEFT JOIN faq c ON (c.id = i.pid AND c.type = 'category')
        WHERE i.type = 'item' AND c.id IS NULL
        ORDER BY i.id ASC
    ");

    $orphans = [];
    while ($orphanQuery && ($row = $db->fetch_array($orphanQuery))) {
        $orphans[] = $row;
    }

    $totalCats  = count($categories);
    $totalItems = array_sum(array_map(static fn($c) => (int)$c['items'], $categories));
    $emptyCats  = count(array_filter($categories, static fn($c) => (int)$c['items'] === 0));

    faqm_page_start([
        'icon'     => 'fa-circle-question',
        'tone'     => 'primary',
        'title'    => faqm_t('pane_title'),
        'subtitle' => faqm_t('pane_subtitle'),
        'actions'  => ($totalCats > 0 ? faqm_btn(faqm_url('do=add'), 'fa-circle-plus', faqm_t('btn_new_question'), 'outline-primary') : '')
                    . faqm_btn(faqm_url('do=new'), 'fa-folder-plus', faqm_t('btn_new_category'), 'primary'),
    ]);

    echo '<div class="faqm-kpis">'
        . faqm_kpi('fa-folder-tree', 'primary', $totalCats, faqm_t('kpi_categories'), faqm_t('kpi_categories_hint'))
        . faqm_kpi('fa-comments', 'success', $totalItems, faqm_t('kpi_questions'), faqm_t('kpi_questions_hint'))
        . faqm_kpi('fa-folder-open', 'warning', $emptyCats, faqm_t('kpi_empty'), faqm_t('kpi_empty_hint'))
        . faqm_kpi('fa-link-slash', count($orphans) > 0 ? 'danger' : 'secondary', count($orphans), faqm_t('kpi_unlinked'), faqm_t('kpi_unlinked_hint'))
        . '</div>';

    if ($totalCats === 0) {
        echo '<div class="faqm-panel faqm-empty">
            <span class="faqm-square is-xl is-primary"><i class="fa-solid fa-folder-plus" aria-hidden="true"></i></span>
            <h2 class="faqm-empty__title">' . faqm_e(faqm_t('sec_no_categories')) . '</h2>
            <p class="faqm-empty__text">' . faqm_e(faqm_t('msg_no_categories')) . '</p>
            ' . faqm_btn(faqm_url('do=new'), 'fa-folder-plus', faqm_t('btn_first_category'), 'primary') . '
        </div>';
    } else {
        echo '<form method="post" action="' . faqm_e(faqm_url()) . '" class="faqm-order-form" data-faqm-order-form>
            <input type="hidden" name="do" value="savedisplayorder">
            ' . faqm_csrf_field() . '
            <section class="faqm-panel">
                <div class="faqm-panel__head">
                    <h2 class="faqm-panel__title"><i class="fa-solid fa-folder-tree" aria-hidden="true"></i>' . faqm_e(faqm_t('sec_categories')) . '</h2>
                    <span class="faqm-panel__meta">' . faqm_e(faqm_t('lbl_total', $totalCats)) . '</span>
                </div>
                <div class="table-responsive">
                    <table class="table faqm-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="faqm-col-order"><i class="fa-solid fa-arrow-down-1-9" aria-hidden="true"></i>' . faqm_e(faqm_t('col_order')) . '</th>
                                <th><i class="fa-solid fa-folder" aria-hidden="true"></i>' . faqm_e(faqm_t('col_category')) . '</th>
                                <th class="text-center"><i class="fa-solid fa-comments" aria-hidden="true"></i>' . faqm_e(faqm_t('col_questions')) . '</th>
                                <th class="text-end"><i class="fa-solid fa-gears" aria-hidden="true"></i>' . faqm_e(faqm_t('col_actions')) . '</th>
                            </tr>
                        </thead>
                        <tbody>';

        foreach ($categories as $cat) {
            $cid   = (int)$cat['id'];
            $name  = (string)$cat['name'];
            $count = (int)$cat['items'];

            echo '<tr>
                <td class="faqm-col-order">
                    <input type="number" class="form-control form-control-sm faqm-order" name="disporder[' . $cid . ']"
                           value="' . (int)$cat['disporder'] . '" min="0" data-faqm-order aria-label="' . faqm_e(faqm_t('aria_order_for', $name)) . '">
                </td>
                <td>
                    <a class="faqm-cat" href="' . faqm_e(faqm_url('do=view&id=' . $cid)) . '">
                        <span class="faqm-square is-sm is-warning"><i class="fa-solid ' . ($count > 0 ? 'fa-folder' : 'fa-folder-open') . '" aria-hidden="true"></i></span>
                        <span class="faqm-cat__name">' . faqm_e($name) . '</span>' . faqm_ru_badge($cat) . '
                    </a>
                </td>
                <td class="text-center">
                    <span class="faqm-count' . ($count === 0 ? ' is-empty' : '') . '">' . $count . '</span>
                </td>
                <td class="text-end">
                    <div class="faqm-actions">
                        ' . faqm_icon_link(faqm_url('do=view&id=' . $cid), 'fa-eye', faqm_t('tip_open_questions'), 'info') . '
                        ' . faqm_icon_link(faqm_url('do=add&id=' . $cid), 'fa-circle-plus', faqm_t('tip_add_question'), 'success') . '
                        ' . faqm_icon_link(faqm_url('do=edit&id=' . $cid), 'fa-pen-to-square', faqm_t('tip_edit_category'), 'primary') . '
                        ' . faqm_delete_btn($cid, $name, 'category', $count) . '
                    </div>
                </td>
            </tr>';
        }

        echo '          </tbody>
                    </table>
                </div>
            </section>
            ' . faqm_savebar(faqm_t('hint_order_categories'), faqm_t('btn_save_order')) . '
        </form>';
    }

    if ($orphans !== []) {
        echo '<section class="faqm-panel faqm-panel--danger">
            <div class="faqm-panel__head">
                <h2 class="faqm-panel__title"><i class="fa-solid fa-link-slash" aria-hidden="true"></i>' . faqm_e(faqm_t('sec_unlinked')) . '</h2>
                <span class="faqm-panel__meta">' . faqm_e(faqm_t('hint_unlinked')) . '</span>
            </div>
            <ul class="faqm-orphans">';

        foreach ($orphans as $o) {
            $oid = (int)$o['id'];
            echo '<li>
                <span class="faqm-orphans__name"><i class="fa-regular fa-circle-question" aria-hidden="true"></i>' . faqm_e($o['name']) . '</span>
                <div class="faqm-actions">
                    ' . faqm_icon_link(faqm_url('do=edit&id=' . $oid), 'fa-pen-to-square', faqm_t('tip_edit_question'), 'primary') . '
                    ' . faqm_delete_btn($oid, (string)$o['name'], 'item') . '
                </div>
            </li>';
        }

        echo '</ul></section>';
    }

    echo faqm_delete_form();
    faqm_page_end();
}

function handleView(int $id): void
{
    global $db, $lang;

    if (!is_valid_id($id)) {
        faqm_error_page(faqm_t('err_not_found'));
        return;
    }

    $catQuery = $db->sql_query_prepared("SELECT id, name FROM faq WHERE id = ? AND type = 'category' LIMIT 1", [$id]);
    $category = ($catQuery && $db->num_rows($catQuery) > 0) ? $db->fetch_array($catQuery) : null;

    if (!$category) {
        faqm_error_page(faqm_t('err_not_found'));
        return;
    }

    $query = $db->sql_query_prepared("
        SELECT id, type, name, name_ru, description, description_ru, disporder
        FROM faq
        WHERE type = 'item' AND pid = ?
        ORDER BY disporder ASC, id ASC
    ", [$id]);

    $items = [];
    while ($query && ($row = $db->fetch_array($query))) {
        $items[] = $row;
    }

    $count = count($items);

    faqm_page_start([
        'icon'     => 'fa-folder-open',
        'tone'     => 'warning',
        'title'    => (string)$category['name'],
        'subtitle' => faqm_plural($count, 'lbl_in_category'),
        'crumb'    => faqm_crumb_home(),
        'actions'  => faqm_btn(faqm_url('do=edit&id=' . $id), 'fa-pen-to-square', faqm_t('btn_edit_category'))
                    . faqm_btn(faqm_url('do=add&id=' . $id), 'fa-circle-plus', faqm_t('btn_add_question'), 'primary'),
    ]);

    if ($count === 0) {
        echo '<div class="faqm-panel faqm-empty">
            <span class="faqm-square is-xl is-success"><i class="fa-solid fa-comment-medical" aria-hidden="true"></i></span>
            <h2 class="faqm-empty__title">' . faqm_e(faqm_t('sec_cat_empty')) . '</h2>
            <p class="faqm-empty__text">' . faqm_e(faqm_t('msg_cat_empty')) . '</p>
            ' . faqm_btn(faqm_url('do=add&id=' . $id), 'fa-circle-plus', faqm_t('btn_first_question'), 'primary') . '
        </div>';
        echo faqm_delete_form();
        faqm_page_end();
        return;
    }

    echo '<form method="post" action="' . faqm_e(faqm_url()) . '" data-faqm-order-form>
        <input type="hidden" name="do" value="savedisplayorder">
        <input type="hidden" name="return_id" value="' . $id . '">
        ' . faqm_csrf_field() . '
        <section class="faqm-panel">
            <div class="faqm-panel__head faqm-toolbar">
                <label class="faqm-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" class="form-control" placeholder="' . faqm_e(faqm_t('ph_filter')) . '" data-faqm-filter aria-label="' . faqm_e(faqm_t('ph_filter')) . '">
                </label>
                <div class="faqm-toolbar__buttons">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill faqm-btn" data-faqm-expand="1">
                        <i class="fa-solid fa-angles-down" aria-hidden="true"></i><span>' . faqm_e(faqm_t('btn_expand_all')) . '</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill faqm-btn" data-faqm-expand="0">
                        <i class="fa-solid fa-angles-up" aria-hidden="true"></i><span>' . faqm_e(faqm_t('btn_collapse_all')) . '</span>
                    </button>
                </div>
            </div>
            <div class="faqm-qlist">';

    foreach ($items as $item) {
        $qid  = (int)$item['id'];
        $name = (string)$item['name'];

        echo '<article class="faqm-q" data-faqm-q>
            <div class="faqm-q__head">
                <input type="number" class="form-control form-control-sm faqm-order" name="disporder[' . $qid . ']"
                       value="' . (int)$item['disporder'] . '" min="0" data-faqm-order aria-label="' . faqm_e(faqm_t('aria_order_for', $name)) . '">
                <button type="button" class="faqm-q__toggle collapsed" data-bs-toggle="collapse" data-bs-target="#faqmQ' . $qid . '"
                        aria-expanded="false" aria-controls="faqmQ' . $qid . '">
                    <i class="fa-regular fa-circle-question faqm-q__icon" aria-hidden="true"></i>
                    <span class="faqm-q__title">' . faqm_e($name) . '</span>' . faqm_ru_badge($item) . '
                    <i class="fa-solid fa-chevron-down faqm-q__chev" aria-hidden="true"></i>
                </button>
                <div class="faqm-actions">
                    ' . faqm_icon_link(faqm_url('do=edit&id=' . $qid), 'fa-pen-to-square', faqm_t('tip_edit_question'), 'primary') . '
                    ' . faqm_delete_btn($qid, $name, 'item') . '
                </div>
            </div>
            <div id="faqmQ' . $qid . '" class="collapse">
                <div class="faqm-q__body">' . (string)$item['description'] . '</div>
            </div>
        </article>';
    }

    echo '      <p class="faqm-nomatch" data-faqm-nomatch hidden><i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>' . faqm_e(faqm_t('msg_no_match')) . '</p>
            </div>
        </section>
        ' . faqm_savebar(faqm_t('hint_order_questions'), faqm_t('btn_save_order')) . '
    </form>';

    echo faqm_delete_form();
    faqm_page_end();
}

function handleSaveDisplayOrder(): void
{
    global $db;

    if (!faqm_csrf_ok()) {
        faqm_error_page(faqm_t('err_csrf'));
        return;
    }

    $orders   = $_POST['disporder'] ?? [];
    $returnId = (int)($_POST['return_id'] ?? 0);

    if (!is_array($orders) || $orders === []) {
        faqm_error_page(faqm_t('err_no_order'));
        return;
    }

    $changed = 0;
    foreach ($orders as $rowId => $order) {
        $db->sql_query_prepared("UPDATE faq SET disporder = ? WHERE id = ?", [max(0, (int)$order), (int)$rowId]);
        $changed++;
    }

    faqm_log('display order saved for ' . $changed . ' entries' . ($returnId > 0 ? ' in category #' . $returnId : ''));

    faqm_redirect(($returnId > 0 ? 'do=view&id=' . $returnId . '&' : '') . 'msg=order');
}

function handleDelete(int $id): void
{
    global $db, $lang;

    // Только POST + CSRF: удаление по GET-ссылке позволяло сторонней странице
    // незаметно заставить залогиненного админа удалить пункт.
    if (!faqm_csrf_ok()) {
        http_response_code(403);
        faqm_error_page(faqm_t('err_csrf'));
        return;
    }

    $query = $db->sql_query_prepared("SELECT id, name, type, pid FROM faq WHERE id = ? LIMIT 1", [$id]);
    $row   = (is_valid_id($id) && $query && $db->num_rows($query) > 0) ? $db->fetch_array($query) : null;

    if (!$row) {
        faqm_error_page(faqm_t('err_not_found'));
        return;
    }

    $isItem = faqm_is_item($row);

    $db->sql_query_prepared("DELETE FROM faq WHERE id = ?", [$id]);

    if ($isItem) {
        faqm_log('question "' . $row['name'] . '" (#' . $id . ') deleted');
        $pid = (int)$row['pid'];
        faqm_redirect((faqm_category_exists($pid) ? 'do=view&id=' . $pid . '&' : '') . 'msg=deleted');
    }

    $db->sql_query_prepared("DELETE FROM faq WHERE pid = ?", [$id]);
    $children = (int)$db->affected_rows();

    faqm_log('category "' . $row['name'] . '" (#' . $id . ') deleted with ' . $children . ' questions');
    faqm_redirect('msg=deleted');
}

function handleNew(): void
{
    global $db, $faq_errors;

    if (($_POST['subdo'] ?? '') === 'save') {
        if (!faqm_csrf_ok()) {
            $faq_errors[] = faqm_t('err_csrf');
        } else {
            $name        = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $nameRu      = faqm_null_if_empty((string)($_POST['name_ru'] ?? ''));
            $descRu      = faqm_null_if_empty((string)($_POST['description_ru'] ?? ''));
            $disporder   = max(0, (int)($_POST['disporder'] ?? 0));

            if ($name === '') {
                $faq_errors[] = faqm_t('err_category_name');
            } else {
                $db->sql_query_prepared(
                    "INSERT INTO faq (type, name, name_ru, description, description_ru, disporder, pid) VALUES ('category', ?, ?, ?, ?, ?, 0)",
                    [$name, $nameRu, $description, $descRu, $disporder]
                );
                faqm_log('category "' . $name . '" (#' . (int)$db->insert_id() . ') created');
                faqm_redirect('msg=cat_created');
            }
        }
    }

    faqm_render_form('new', false, [
        'id'          => 0,
        'name'        => (string)($_POST['name'] ?? ''),
        'description' => (string)($_POST['description'] ?? ''),
        'name_ru'        => (string)($_POST['name_ru'] ?? ''),
        'description_ru' => (string)($_POST['description_ru'] ?? ''),
        'disporder'   => (int)($_POST['disporder'] ?? 0),
        'pid'         => 0,
    ], []);
}

function handleAdd(int $id): void
{
    global $db, $faq_errors;

    $categories = faqm_categories();

    if ($categories === []) {
        faqm_error_page(faqm_t('err_category_first'));
        return;
    }

    if (($_POST['subdo'] ?? '') === 'save') {
        if (!faqm_csrf_ok()) {
            $faq_errors[] = faqm_t('err_csrf');
        } else {
            $name        = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $nameRu      = faqm_null_if_empty((string)($_POST['name_ru'] ?? ''));
            $descRu      = faqm_null_if_empty((string)($_POST['description_ru'] ?? ''));
            $disporder   = max(0, (int)($_POST['disporder'] ?? 0));
            $pid         = (int)($_POST['pid'] ?? 0);

            if ($name === '') {
                $faq_errors[] = faqm_t('err_question');
            }
            if ($description === '') {
                $faq_errors[] = faqm_t('err_answer');
            }
            if (!faqm_category_exists($pid)) {
                $faq_errors[] = faqm_t('err_choose_category');
            }

            if ($faq_errors === []) {
                $db->sql_query_prepared(
                    "INSERT INTO faq (type, name, name_ru, description, description_ru, disporder, pid) VALUES ('item', ?, ?, ?, ?, ?, ?)",
                    [$name, $nameRu, $description, $descRu, $disporder, $pid]
                );
                faqm_log('question "' . $name . '" (#' . (int)$db->insert_id() . ') added to category #' . $pid);
                faqm_redirect('do=view&id=' . $pid . '&msg=q_created');
            }
        }
    }

    $pid = (int)($_POST['pid'] ?? $id);

    faqm_render_form('add', true, [
        'id'          => $id,
        'name'        => (string)($_POST['name'] ?? ''),
        'description' => (string)($_POST['description'] ?? ''),
        'name_ru'        => (string)($_POST['name_ru'] ?? ''),
        'description_ru' => (string)($_POST['description_ru'] ?? ''),
        'disporder'   => (int)($_POST['disporder'] ?? 0),
        'pid'         => $pid,
    ], $categories);
}

function handleEdit(int $id): void
{
    global $db, $lang, $faq_errors;

    $query = is_valid_id($id) ? $db->sql_query_prepared("SELECT * FROM faq WHERE id = ? LIMIT 1", [$id]) : false;
    $row   = ($query && $db->num_rows($query) > 0) ? $db->fetch_array($query) : null;

    if (!$row) {
        faqm_error_page(faqm_t('err_not_found'));
        return;
    }

    // Тип берём из БД, а не из скрытого поля формы.
    $isItem = faqm_is_item($row);

    if (($_POST['subdo'] ?? '') === 'save') {
        if (!faqm_csrf_ok()) {
            $faq_errors[] = faqm_t('err_csrf');
        } else {
            $name        = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $nameRu      = faqm_null_if_empty((string)($_POST['name_ru'] ?? ''));
            $descRu      = faqm_null_if_empty((string)($_POST['description_ru'] ?? ''));
            $disporder   = max(0, (int)($_POST['disporder'] ?? 0));
            $pid         = $isItem ? (int)($_POST['pid'] ?? 0) : 0;

            if ($name === '') {
                $faq_errors[] = $isItem ? faqm_t('err_question') : faqm_t('err_category_name');
            }
            if ($isItem && $description === '') {
                $faq_errors[] = faqm_t('err_answer');
            }
            if ($isItem && !faqm_category_exists($pid)) {
                $faq_errors[] = faqm_t('err_choose_category');
            }

            if ($faq_errors === []) {
                $db->sql_query_prepared(
                    "UPDATE faq SET type = ?, name = ?, name_ru = ?, description = ?, description_ru = ?, disporder = ?, pid = ? WHERE id = ?",
                    [$isItem ? 'item' : 'category', $name, $nameRu, $description, $descRu, $disporder, $pid, $id]
                );
                faqm_log(($isItem ? 'question' : 'category') . ' "' . $name . '" (#' . $id . ') edited');
                faqm_redirect(($isItem ? 'do=view&id=' . $pid . '&' : '') . 'msg=updated');
            }
        }
    }

    faqm_render_form('edit', $isItem, [
        'id'          => $id,
        'name'        => (string)($_POST['name'] ?? $row['name']),
        'description' => (string)($_POST['description'] ?? $row['description']),
        'name_ru'        => (string)($_POST['name_ru'] ?? $row['name_ru'] ?? ''),
        'description_ru' => (string)($_POST['description_ru'] ?? $row['description_ru'] ?? ''),
        'disporder'   => (int)($_POST['disporder'] ?? $row['disporder']),
        'pid'         => (int)($_POST['pid'] ?? $row['pid']),
    ], $isItem ? faqm_categories() : []);
}

// ═══════════════════════════════════════════════════════════
// SHARED FORM
// ═══════════════════════════════════════════════════════════

/**
 * Одна форма на все три режима (new = категория, add = вопрос, edit = любое).
 *
 * @param array{id:int,name:string,description:string,name_ru:string,description_ru:string,disporder:int,pid:int} $v
 * @param list<array{id:int,name:string}> $categories
 */
function faqm_render_form(string $mode, bool $isItem, array $v, array $categories): void
{
    $pidValid  = $isItem && in_array($v['pid'], array_column($categories, 'id'), true);
    $cancelUrl = $pidValid ? faqm_url('do=view&id=' . $v['pid']) : faqm_url();

    $head = match (true) {
        $mode === 'new' => ['fa-folder-plus', 'success', faqm_t('form_new_category'), faqm_t('form_new_category_sub'), faqm_t('btn_create_category')],
        $mode === 'add' => ['fa-comment-medical', 'success', faqm_t('form_new_question'), faqm_t('form_new_question_sub'), faqm_t('btn_add_question')],
        $isItem         => ['fa-pen-to-square', 'primary', faqm_t('form_edit_question'), faqm_t('form_edit_question_sub', $v['id']), faqm_t('btn_save_changes')],
        default         => ['fa-pen-to-square', 'primary', faqm_t('form_edit_category'), faqm_t('form_edit_category_sub', $v['id']), faqm_t('btn_save_changes')],
    };
    [$icon, $tone, $title, $subtitle, $saveLabel] = $head;

    faqm_page_start([
        'icon'     => $icon,
        'tone'     => $tone,
        'title'    => $title,
        'subtitle' => $subtitle,
        'crumb'    => $pidValid
            ? '<a href="' . faqm_e($cancelUrl) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>'
              . faqm_e($categories[array_search($v['pid'], array_column($categories, 'id'), true)]['name']) . '</a>'
            : faqm_crumb_home(),
    ]);

    $categoryField = '';
    if ($isItem) {
        $options = $pidValid ? '' : '<option value="" selected disabled>' . faqm_e(faqm_t('opt_choose_category')) . '</option>';
        foreach ($categories as $cat) {
            $options .= '<option value="' . $cat['id'] . '"' . ($cat['id'] === $v['pid'] ? ' selected' : '') . '>' . faqm_e($cat['name']) . '</option>';
        }

        $categoryField = '<div class="faqm-field">
            <label for="pid" class="faqm-label"><i class="fa-solid fa-folder" aria-hidden="true"></i>' . faqm_e(faqm_t('lbl_category')) . ' <span class="faqm-req">*</span></label>
            <select name="pid" id="pid" class="form-select" required>' . $options . '</select>
        </div>';
    }

    $nameLabel   = faqm_e($isItem ? faqm_t('lbl_question') : faqm_t('lbl_category_name'));
    $descLabel   = faqm_e($isItem ? faqm_t('lbl_answer') : faqm_t('lbl_description'));
    $nameLabelRu = faqm_e($isItem ? faqm_t('lbl_question_ru') : faqm_t('lbl_category_name_ru'));
    $descLabelRu = faqm_e($isItem ? faqm_t('lbl_answer_ru') : faqm_t('lbl_description_ru'));
    $descReq     = $isItem ? ' <span class="faqm-req">*</span>' : ' <span class="faqm-optional">' . faqm_e(faqm_t('lbl_optional')) . '</span>';
    $rows      = $isItem ? 14 : 5;

    echo '<form method="post" action="' . faqm_e(faqm_url()) . '" class="faqm-form" data-faqm-guard>
        <input type="hidden" name="do" value="' . faqm_e($mode) . '">
        <input type="hidden" name="subdo" value="save">
        ' . ($v['id'] > 0 ? '<input type="hidden" name="id" value="' . $v['id'] . '">' : '') . '
        ' . faqm_csrf_field() . '

        <div class="faqm-form-grid">
            <section class="faqm-panel faqm-panel--pad">
                <div class="faqm-field">
                    <label for="name" class="faqm-label"><i class="fa-solid fa-heading" aria-hidden="true"></i>' . $nameLabel . ' <span class="faqm-req">*</span></label>
                    <input type="text" class="form-control form-control-lg" id="name" name="name" value="' . faqm_e($v['name']) . '" required autofocus>
                </div>

                <div class="faqm-field">
                    <div class="faqm-label-row">
                        <label for="description" class="faqm-label"><i class="fa-solid fa-align-left" aria-hidden="true"></i>' . $descLabel . $descReq . '</label>
                        <span class="faqm-counter" data-faqm-counter="description"></span>
                    </div>
                    <textarea class="form-control faqm-textarea" id="description" name="description" rows="' . $rows . '"' . ($isItem ? ' required' : '') . '>'
                        . faqm_e($v['description']) . '</textarea>
                    <p class="faqm-hint"><i class="fa-solid fa-code" aria-hidden="true"></i>' . faqm_e(faqm_t('hint_html')) . '</p>
                </div>

                <div class="border rounded-3 p-3 mt-3 bg-body-tertiary">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge text-bg-secondary">RU</span>
                        <b>' . faqm_e(faqm_t('sec_ru_version')) . '</b>
                        <span class="faqm-hint m-0"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>' . faqm_e(faqm_t('hint_ru_fallback')) . '</span>
                    </div>
                    <div class="faqm-field">
                        <label for="name_ru" class="faqm-label"><i class="fa-solid fa-heading" aria-hidden="true"></i>' . $nameLabelRu . '</label>
                        <input type="text" class="form-control" id="name_ru" name="name_ru" maxlength="255" lang="ru" value="' . faqm_e($v['name_ru']) . '">
                    </div>
                    <div class="faqm-field mb-0">
                        <div class="faqm-label-row">
                            <label for="description_ru" class="faqm-label"><i class="fa-solid fa-align-left" aria-hidden="true"></i>' . $descLabelRu . '</label>
                            <span class="faqm-counter" data-faqm-counter="description_ru"></span>
                        </div>
                        <textarea class="form-control faqm-textarea" id="description_ru" name="description_ru" rows="' . $rows . '" lang="ru">'
                            . faqm_e($v['description_ru']) . '</textarea>
                    </div>
                </div>
            </section>

            <aside class="faqm-panel faqm-panel--pad faqm-aside">
                <h2 class="faqm-aside__title"><i class="fa-solid fa-sliders" aria-hidden="true"></i>' . faqm_e(faqm_t('sec_placement')) . '</h2>
                ' . $categoryField . '
                <div class="faqm-field">
                    <label for="disporder" class="faqm-label"><i class="fa-solid fa-arrow-down-1-9" aria-hidden="true"></i>' . faqm_e(faqm_t('lbl_disporder')) . '</label>
                    <input type="number" class="form-control" id="disporder" name="disporder" value="' . $v['disporder'] . '" min="0">
                    <p class="faqm-hint">' . faqm_e(faqm_t('hint_disporder')) . '</p>
                </div>
                <p class="faqm-shortcut"><i class="fa-regular fa-keyboard" aria-hidden="true"></i>' . ags_fmt(faqm_e(faqm_t('hint_shortcut')), '<kbd>Ctrl</kbd> + <kbd>S</kbd>') . '</p>
            </aside>
        </div>

        ' . faqm_savebar($mode === 'edit' ? faqm_t('status_no_changes') : faqm_t('status_required'), $saveLabel, $cancelUrl) . '
    </form>';

    faqm_page_end();
}