<?php

declare(strict_types=1);

require_once INC_PATH . '/functions_faq.php';

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3" role="alert">
        <i class="fa-solid fa-ban me-2"></i><b>Error!</b> Direct initialization of this file is not allowed.
    </div>');
}

$lang->load('faq');

// Версия ассетов - поднимать вручную при правке faqmanage.css / faqmanage.js.
const FAQM_ASSET_VER = 1;

define('TSFAQMANAGE_VERSION', '2.0.0');

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

function faqm_e(mixed $value): string
{
    return htmlspecialchars_uni((string)$value);
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
    return '<button type="button" class="faqm-icon-btn is-danger" title="Delete" aria-label="Delete ' . faqm_e($name) . '"'
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

    stdhead($lang->faq['faqtitle'], true, '', '');
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
    return '<a href="' . faqm_e(faqm_url()) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>All categories</a>';
}

function faqm_flash(): void
{
    $messages = [
        'order'       => ['fa-arrow-down-1-9', 'Display order saved.'],
        'cat_created' => ['fa-folder-plus', 'Category created.'],
        'q_created'   => ['fa-circle-plus', 'Question added.'],
        'updated'     => ['fa-floppy-disk', 'Changes saved.'],
        'deleted'     => ['fa-trash-can', 'Deleted.'],
    ];

    $key = (string)($_GET['msg'] ?? '');

    if (!isset($messages[$key])) {
        return;
    }

    [$icon, $text] = $messages[$key];

    echo '<div class="faqm-flash" role="status" data-faqm-flash>
        <i class="fa-solid ' . $icon . '" aria-hidden="true"></i><span>' . faqm_e($text) . '</span>
        <button type="button" class="faqm-flash__close" aria-label="Close" data-faqm-flash-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
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
        <div><strong>' . faqm_e($lang->global['error'] ?? 'Error') . '</strong><ul>' . $items . '</ul></div>
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
        'title'    => 'Something went wrong',
        'subtitle' => 'The request could not be completed.',
        'actions'  => faqm_btn(faqm_url(), 'fa-arrow-left', 'Back to FAQ'),
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
            ' . ($cancelUrl !== '' ? faqm_btn($cancelUrl, 'fa-xmark', 'Cancel', 'link') : '') . '
            <button type="reset" class="btn btn-outline-secondary rounded-pill faqm-btn">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Undo</span>
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
        SELECT c.id, c.name, c.disporder, COUNT(i.id) AS items
        FROM faq c
        LEFT JOIN faq i ON (i.pid = c.id AND i.type = 'item')
        WHERE c.type = 'category'
        GROUP BY c.id, c.name, c.disporder
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
        'title'    => 'FAQ manager',
        'subtitle' => 'Categories and questions shown on the public FAQ page.',
        'actions'  => ($totalCats > 0 ? faqm_btn(faqm_url('do=add'), 'fa-circle-plus', 'New question', 'outline-primary') : '')
                    . faqm_btn(faqm_url('do=new'), 'fa-folder-plus', 'New category', 'primary'),
    ]);

    echo '<div class="faqm-kpis">'
        . faqm_kpi('fa-folder-tree', 'primary', $totalCats, 'Categories', 'Groups on the FAQ page')
        . faqm_kpi('fa-comments', 'success', $totalItems, 'Questions', 'Inside categories')
        . faqm_kpi('fa-folder-open', 'warning', $emptyCats, 'Empty categories', 'No questions yet')
        . faqm_kpi('fa-link-slash', count($orphans) > 0 ? 'danger' : 'secondary', count($orphans), 'Unlinked questions', 'Not in any category')
        . '</div>';

    if ($totalCats === 0) {
        echo '<div class="faqm-panel faqm-empty">
            <span class="faqm-square is-xl is-primary"><i class="fa-solid fa-folder-plus" aria-hidden="true"></i></span>
            <h2 class="faqm-empty__title">No categories yet</h2>
            <p class="faqm-empty__text">Create a category first, then add questions to it.</p>
            ' . faqm_btn(faqm_url('do=new'), 'fa-folder-plus', 'Create the first category', 'primary') . '
        </div>';
    } else {
        echo '<form method="post" action="' . faqm_e(faqm_url()) . '" class="faqm-order-form" data-faqm-order-form>
            <input type="hidden" name="do" value="savedisplayorder">
            ' . faqm_csrf_field() . '
            <section class="faqm-panel">
                <div class="faqm-panel__head">
                    <h2 class="faqm-panel__title"><i class="fa-solid fa-folder-tree" aria-hidden="true"></i>Categories</h2>
                    <span class="faqm-panel__meta">' . $totalCats . ' total</span>
                </div>
                <div class="table-responsive">
                    <table class="table faqm-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="faqm-col-order"><i class="fa-solid fa-arrow-down-1-9" aria-hidden="true"></i>Order</th>
                                <th><i class="fa-solid fa-folder" aria-hidden="true"></i>Category</th>
                                <th class="text-center"><i class="fa-solid fa-comments" aria-hidden="true"></i>Questions</th>
                                <th class="text-end"><i class="fa-solid fa-gears" aria-hidden="true"></i>Actions</th>
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
                           value="' . (int)$cat['disporder'] . '" min="0" data-faqm-order aria-label="Display order for ' . faqm_e($name) . '">
                </td>
                <td>
                    <a class="faqm-cat" href="' . faqm_e(faqm_url('do=view&id=' . $cid)) . '">
                        <span class="faqm-square is-sm is-warning"><i class="fa-solid ' . ($count > 0 ? 'fa-folder' : 'fa-folder-open') . '" aria-hidden="true"></i></span>
                        <span class="faqm-cat__name">' . faqm_e($name) . '</span>
                    </a>
                </td>
                <td class="text-center">
                    <span class="faqm-count' . ($count === 0 ? ' is-empty' : '') . '">' . $count . '</span>
                </td>
                <td class="text-end">
                    <div class="faqm-actions">
                        ' . faqm_icon_link(faqm_url('do=view&id=' . $cid), 'fa-eye', 'Open questions', 'info') . '
                        ' . faqm_icon_link(faqm_url('do=add&id=' . $cid), 'fa-circle-plus', 'Add question', 'success') . '
                        ' . faqm_icon_link(faqm_url('do=edit&id=' . $cid), 'fa-pen-to-square', 'Edit category', 'primary') . '
                        ' . faqm_delete_btn($cid, $name, 'category', $count) . '
                    </div>
                </td>
            </tr>';
        }

        echo '          </tbody>
                    </table>
                </div>
            </section>
            ' . faqm_savebar('Lower numbers appear first on the FAQ page.', 'Save order') . '
        </form>';
    }

    if ($orphans !== []) {
        echo '<section class="faqm-panel faqm-panel--danger">
            <div class="faqm-panel__head">
                <h2 class="faqm-panel__title"><i class="fa-solid fa-link-slash" aria-hidden="true"></i>Unlinked questions</h2>
                <span class="faqm-panel__meta">Open one and pick a category to show it again</span>
            </div>
            <ul class="faqm-orphans">';

        foreach ($orphans as $o) {
            $oid = (int)$o['id'];
            echo '<li>
                <span class="faqm-orphans__name"><i class="fa-regular fa-circle-question" aria-hidden="true"></i>' . faqm_e($o['name']) . '</span>
                <div class="faqm-actions">
                    ' . faqm_icon_link(faqm_url('do=edit&id=' . $oid), 'fa-pen-to-square', 'Edit question', 'primary') . '
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
        faqm_error_page($lang->faq['faqerror']);
        return;
    }

    $catQuery = $db->sql_query_prepared("SELECT id, name FROM faq WHERE id = ? AND type = 'category' LIMIT 1", [$id]);
    $category = ($catQuery && $db->num_rows($catQuery) > 0) ? $db->fetch_array($catQuery) : null;

    if (!$category) {
        faqm_error_page($lang->faq['faqerror']);
        return;
    }

    $query = $db->sql_query_prepared("
        SELECT id, name, description, disporder
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
        'subtitle' => $count === 1 ? '1 question in this category.' : $count . ' questions in this category.',
        'crumb'    => faqm_crumb_home(),
        'actions'  => faqm_btn(faqm_url('do=edit&id=' . $id), 'fa-pen-to-square', 'Edit category')
                    . faqm_btn(faqm_url('do=add&id=' . $id), 'fa-circle-plus', 'Add question', 'primary'),
    ]);

    if ($count === 0) {
        echo '<div class="faqm-panel faqm-empty">
            <span class="faqm-square is-xl is-success"><i class="fa-solid fa-comment-medical" aria-hidden="true"></i></span>
            <h2 class="faqm-empty__title">This category has no questions</h2>
            <p class="faqm-empty__text">Add a question and its answer to show the category on the FAQ page.</p>
            ' . faqm_btn(faqm_url('do=add&id=' . $id), 'fa-circle-plus', 'Add the first question', 'primary') . '
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
                    <input type="search" class="form-control" placeholder="Filter questions" data-faqm-filter aria-label="Filter questions">
                </label>
                <div class="faqm-toolbar__buttons">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill faqm-btn" data-faqm-expand="1">
                        <i class="fa-solid fa-angles-down" aria-hidden="true"></i><span>Expand all</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill faqm-btn" data-faqm-expand="0">
                        <i class="fa-solid fa-angles-up" aria-hidden="true"></i><span>Collapse all</span>
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
                       value="' . (int)$item['disporder'] . '" min="0" data-faqm-order aria-label="Display order for ' . faqm_e($name) . '">
                <button type="button" class="faqm-q__toggle collapsed" data-bs-toggle="collapse" data-bs-target="#faqmQ' . $qid . '"
                        aria-expanded="false" aria-controls="faqmQ' . $qid . '">
                    <i class="fa-regular fa-circle-question faqm-q__icon" aria-hidden="true"></i>
                    <span class="faqm-q__title">' . faqm_e($name) . '</span>
                    <i class="fa-solid fa-chevron-down faqm-q__chev" aria-hidden="true"></i>
                </button>
                <div class="faqm-actions">
                    ' . faqm_icon_link(faqm_url('do=edit&id=' . $qid), 'fa-pen-to-square', 'Edit question', 'primary') . '
                    ' . faqm_delete_btn($qid, $name, 'item') . '
                </div>
            </div>
            <div id="faqmQ' . $qid . '" class="collapse">
                <div class="faqm-q__body">' . (string)$item['description'] . '</div>
            </div>
        </article>';
    }

    echo '      <p class="faqm-nomatch" data-faqm-nomatch hidden><i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>No questions match the filter.</p>
            </div>
        </section>
        ' . faqm_savebar('Lower numbers appear first inside the category.', 'Save order') . '
    </form>';

    echo faqm_delete_form();
    faqm_page_end();
}

function handleSaveDisplayOrder(): void
{
    global $db;

    if (!faqm_csrf_ok()) {
        faqm_error_page('Security check failed. Reload the page and try again.');
        return;
    }

    $orders   = $_POST['disporder'] ?? [];
    $returnId = (int)($_POST['return_id'] ?? 0);

    if (!is_array($orders) || $orders === []) {
        faqm_error_page('No display order values were sent.');
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
        faqm_error_page('Security check failed. Reload the page and try again.');
        return;
    }

    $query = $db->sql_query_prepared("SELECT id, name, type, pid FROM faq WHERE id = ? LIMIT 1", [$id]);
    $row   = (is_valid_id($id) && $query && $db->num_rows($query) > 0) ? $db->fetch_array($query) : null;

    if (!$row) {
        faqm_error_page($lang->faq['faqerror']);
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
            $faq_errors[] = 'Security check failed. Reload the page and try again.';
        } else {
            $name        = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $disporder   = max(0, (int)($_POST['disporder'] ?? 0));

            if ($name === '') {
                $faq_errors[] = 'Enter a category name.';
            } else {
                $db->sql_query_prepared(
                    "INSERT INTO faq (type, name, description, disporder, pid) VALUES ('category', ?, ?, ?, 0)",
                    [$name, $description, $disporder]
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
        'disporder'   => (int)($_POST['disporder'] ?? 0),
        'pid'         => 0,
    ], []);
}

function handleAdd(int $id): void
{
    global $db, $faq_errors;

    $categories = faqm_categories();

    if ($categories === []) {
        faqm_error_page('Create a category before adding questions.');
        return;
    }

    if (($_POST['subdo'] ?? '') === 'save') {
        if (!faqm_csrf_ok()) {
            $faq_errors[] = 'Security check failed. Reload the page and try again.';
        } else {
            $name        = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $disporder   = max(0, (int)($_POST['disporder'] ?? 0));
            $pid         = (int)($_POST['pid'] ?? 0);

            if ($name === '') {
                $faq_errors[] = 'Enter the question.';
            }
            if ($description === '') {
                $faq_errors[] = 'Enter the answer.';
            }
            if (!faqm_category_exists($pid)) {
                $faq_errors[] = 'Choose an existing category.';
            }

            if ($faq_errors === []) {
                $db->sql_query_prepared(
                    "INSERT INTO faq (type, name, description, disporder, pid) VALUES ('item', ?, ?, ?, ?)",
                    [$name, $description, $disporder, $pid]
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
        faqm_error_page($lang->faq['faqerror']);
        return;
    }

    // Тип берём из БД, а не из скрытого поля формы.
    $isItem = faqm_is_item($row);

    if (($_POST['subdo'] ?? '') === 'save') {
        if (!faqm_csrf_ok()) {
            $faq_errors[] = 'Security check failed. Reload the page and try again.';
        } else {
            $name        = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $disporder   = max(0, (int)($_POST['disporder'] ?? 0));
            $pid         = $isItem ? (int)($_POST['pid'] ?? 0) : 0;

            if ($name === '') {
                $faq_errors[] = $isItem ? 'Enter the question.' : 'Enter a category name.';
            }
            if ($isItem && $description === '') {
                $faq_errors[] = 'Enter the answer.';
            }
            if ($isItem && !faqm_category_exists($pid)) {
                $faq_errors[] = 'Choose an existing category.';
            }

            if ($faq_errors === []) {
                $db->sql_query_prepared(
                    "UPDATE faq SET type = ?, name = ?, description = ?, disporder = ?, pid = ? WHERE id = ?",
                    [$isItem ? 'item' : 'category', $name, $description, $disporder, $pid, $id]
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
 * @param array{id:int,name:string,description:string,disporder:int,pid:int} $v
 * @param list<array{id:int,name:string}> $categories
 */
function faqm_render_form(string $mode, bool $isItem, array $v, array $categories): void
{
    $pidValid  = $isItem && in_array($v['pid'], array_column($categories, 'id'), true);
    $cancelUrl = $pidValid ? faqm_url('do=view&id=' . $v['pid']) : faqm_url();

    $head = match (true) {
        $mode === 'new' => ['fa-folder-plus', 'success', 'New category', 'Categories group questions on the FAQ page.', 'Create category'],
        $mode === 'add' => ['fa-comment-medical', 'success', 'New question', 'Add a question and its answer to a category.', 'Add question'],
        $isItem         => ['fa-pen-to-square', 'primary', 'Edit question', 'Question #' . $v['id'], 'Save changes'],
        default         => ['fa-pen-to-square', 'primary', 'Edit category', 'Category #' . $v['id'], 'Save changes'],
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
        $options = $pidValid ? '' : '<option value="" selected disabled>Choose a category</option>';
        foreach ($categories as $cat) {
            $options .= '<option value="' . $cat['id'] . '"' . ($cat['id'] === $v['pid'] ? ' selected' : '') . '>' . faqm_e($cat['name']) . '</option>';
        }

        $categoryField = '<div class="faqm-field">
            <label for="pid" class="faqm-label"><i class="fa-solid fa-folder" aria-hidden="true"></i>Category <span class="faqm-req">*</span></label>
            <select name="pid" id="pid" class="form-select" required>' . $options . '</select>
        </div>';
    }

    $nameLabel = $isItem ? 'Question' : 'Category name';
    $descLabel = $isItem ? 'Answer' : 'Description';
    $descReq   = $isItem ? ' <span class="faqm-req">*</span>' : ' <span class="faqm-optional">optional</span>';
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
                    <p class="faqm-hint"><i class="fa-solid fa-code" aria-hidden="true"></i>HTML is allowed and is shown on the FAQ page exactly as written.</p>
                </div>
            </section>

            <aside class="faqm-panel faqm-panel--pad faqm-aside">
                <h2 class="faqm-aside__title"><i class="fa-solid fa-sliders" aria-hidden="true"></i>Placement</h2>
                ' . $categoryField . '
                <div class="faqm-field">
                    <label for="disporder" class="faqm-label"><i class="fa-solid fa-arrow-down-1-9" aria-hidden="true"></i>Display order</label>
                    <input type="number" class="form-control" id="disporder" name="disporder" value="' . $v['disporder'] . '" min="0">
                    <p class="faqm-hint">Lower numbers appear first.</p>
                </div>
                <p class="faqm-shortcut"><i class="fa-regular fa-keyboard" aria-hidden="true"></i><kbd>Ctrl</kbd> + <kbd>S</kbd> saves the form</p>
            </aside>
        </div>

        ' . faqm_savebar($mode === 'edit' ? 'No unsaved changes.' : 'Fields marked * are required.', $saveLabel, $cancelUrl) . '
    </form>';

    faqm_page_end();
}