<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

define('C_VERSION', '2.2');

global $lang;
$lang->load('category');

if (!function_exists('ags_fmt')) {
    /**
     * Подстановка {1}, {2}… в строку из ланга.
     * $lang->load() превращает {1} в %1$s — поэтому заменяем оба формата.
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

/**
 * Category Management Module
 */
class CategoryManager
{
    private array $errors = [];
    private string $baseScript;
    private static bool $assetsPrinted = false;

    /** Готовые иконки для выбора (клик в панели вставляет классы в поле) */
    private const ICONS = [
        'fa-solid fa-film', 'fa-solid fa-film fa-shake', 'fa-solid fa-clapperboard', 'fa-solid fa-video',
        'fa-solid fa-photo-film', 'fa-solid fa-tv', 'fa-solid fa-satellite-dish', 'fa-solid fa-compact-disc fa-spin',
        'fa-solid fa-music', 'fa-solid fa-headphones', 'fa-solid fa-microphone', 'fa-solid fa-gamepad',
        'fa-solid fa-dice-d20', 'fa-solid fa-book', 'fa-solid fa-book-open', 'fa-solid fa-graduation-cap',
        'fa-solid fa-laptop-code', 'fa-solid fa-mobile-screen', 'fa-brands fa-windows', 'fa-brands fa-apple',
        'fa-brands fa-linux', 'fa-brands fa-android', 'fa-solid fa-image', 'fa-solid fa-palette',
        'fa-solid fa-futbol', 'fa-solid fa-child-reaching', 'fa-solid fa-dragon', 'fa-solid fa-ghost',
        'fa-solid fa-box-archive', 'fa-solid fa-star', 'fa-solid fa-fire', 'fa-solid fa-question',
    ];

    public function __construct(private $db)
    {
        $this->baseScript = $_SERVER['SCRIPT_NAME'] . '?act=category';
    }

    // ═══════════════════════════════════════════════════════════
    // CACHE
    // ═══════════════════════════════════════════════════════════

    public function updateCategoriesCache(): void
    {
        global $lang;

        $categoriesC = [];
        $categoriesS = [];

        $query = $this->db->sql_query_prepared("SELECT * FROM categories WHERE type = 'c' ORDER BY name, id");
        while ($query && ($row = $this->db->fetch_array($query))) {
            $categoriesC[] = $row;
        }
        $query = $this->db->sql_query_prepared("SELECT * FROM categories WHERE type = 's' ORDER BY name, id");
        while ($query && ($row = $this->db->fetch_array($query))) {
            $categoriesS[] = $row;
        }

        $cacheContent = '<?php
/**
 * Generated Cache#7 - Do Not Alter
 * Cache Name: Categories
 * Generated: ' . gmdate('r') . '
 */
 
$_categoriesC = ' . var_export($categoriesC, true) . ';
$_categoriesS = ' . var_export($categoriesS, true) . ';
?>';

        $filename = TSDIR . '/cache/categories.php';
        if (file_put_contents($filename, $cacheContent) === false) {
            $this->addError($lang->category['err_cache_write']);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // UI HELPERS
    // ═══════════════════════════════════════════════════════════

    private function e(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES);
    }

    /** Иконка с запасным вариантом — раньше пустое поле icon давало пустое место */
    private function icon(?string $cls, string $fallback = 'fa-solid fa-folder'): string
    {
        $cls = trim((string)$cls);
        return '<i class="' . $this->e($cls !== '' ? $cls : $fallback) . '"></i>';
    }

    /**
     * Поле иконки с живым предпросмотром и сеткой готовых иконок.
     * Раньше пункт меню делал document.querySelector('input[name=icon]') — на
     * странице три таких поля (добавление, подкатегория, редактирование), и
     * выбранная иконка всегда попадала в ПЕРВОЕ, а не в открытую форму.
     */
    public function getIconSelector(string $selected = ''): string
    {
        global $lang;

        $grid = '';
        foreach (self::ICONS as $icon) {
            $grid .= '<button type="button" class="cm-ico-opt" data-icon="' . $this->e($icon) . '" title="' . $this->e($icon) . '"><i class="' . $this->e($icon) . '"></i></button>';
        }

        return '<div class="cm-icon-picker">
            <div class="input-group">
                <span class="input-group-text cm-ico-preview">' . $this->icon($selected, 'fa-solid fa-icons') . '</span>
                <input type="text" class="form-control font-monospace" name="icon" value="' . $this->e($selected) . '" placeholder="fa-solid fa-film" autocomplete="off">
                <button class="btn btn-outline-secondary cm-ico-toggle" type="button"><i class="fa-solid fa-table-cells me-1"></i>' . $this->e($lang->category['btn_pick']) . '</button>
            </div>
            <div class="cm-ico-grid" hidden>' . $grid . '</div>
            <div class="form-text"><i class="fa-solid fa-circle-info me-1"></i>' . $lang->category['hint_icon'] . ' · <a href="https://fontawesome.com/search" target="_blank" rel="noopener">' . $this->e($lang->category['lnk_browse_icons']) . '</a></div>
        </div>';
    }

    public function getCategoryDropdown(int $selectedId = 0, string $selectName = 'cid', bool $includeAll = false, int $excludeId = 0): string
    {
        global $lang;

        $html = '<select name="' . $this->e($selectName) . '" class="form-select">';
        $html .= '<option value="0">' . $this->e($includeAll ? $lang->category['opt_all'] : $lang->category['opt_none']) . '</option>';

        $query = $this->db->sql_query_prepared("SELECT id, name FROM categories WHERE type = 'c' ORDER BY name");
        while ($query && ($cat = $this->db->fetch_array($query))) {
            if ($excludeId && (int)$cat['id'] === $excludeId) continue;
            $html .= sprintf(
                '<option value="%d"%s>%s</option>',
                (int)$cat['id'],
                $selectedId === (int)$cat['id'] ? ' selected' : '',
                $this->e($cat['name'])
            );
        }
        return $html . '</select>';
    }

    public function showErrors(): void
    {
        if (empty($this->errors)) {
            return;
        }
        echo '<div class="alert alert-danger d-flex gap-2 rounded-4"><i class="fa-solid fa-triangle-exclamation mt-1"></i><div>'
           . implode('<br>', array_map([$this, 'e'], $this->errors)) . '</div></div>';
    }

    private function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /** URL ассета с cache-busting по mtime файла */
    private function assetUrl(string $path): string
    {
        global $BASEURL;
        $file = TSDIR . $path;
        $ver  = is_file($file) ? (string)filemtime($file) : C_VERSION;
        return $this->e($BASEURL . $path . '?v=' . $ver);
    }

    private function assets(): void
    {
        if (self::$assetsPrinted) return;
        self::$assetsPrinted = true;

        global $BASEURL, $lang;

        // Строки для JS: js_xxx из ланга → AGS_LANG.xxx
        $jsLang = [];
        foreach ($lang->category as $k => $v) {
            if (str_starts_with((string)$k, 'js_')) {
                $jsLang[substr((string)$k, 3)] = $v;
            }
        }

        echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/admin_category.css">';
        ?>
<script>const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php
        echo '<script src="' . $BASEURL . '/admin/scripts/admin_category.js?ver=2"></script>';
    }

    // ═══════════════════════════════════════════════════════════
    // SAVE
    // ═══════════════════════════════════════════════════════════

    private function sanitizeInput(array $input): array
    {
        return [
            'name' => trim((string)($input['name'] ?? '')),
            'icon' => trim((string)($input['icon'] ?? '')),
            'type' => !empty($input['cid']) ? 's' : 'c',
            'pid'  => max(0, (int)($input['cid'] ?? 0)),
        ];
    }

    private function saveCategory(array $data, ?int $id = null): bool
    {
        global $lang;

        if ($data['name'] === '') {
            $this->addError($lang->category['err_name_empty']);
            return false;
        }

        if ($data['pid'] > 0) {
            // Родитель должен существовать и быть главной категорией (дерево в 2 уровня)
            if (!$this->validateCategoryId($data['pid'], 'c')) {
                $this->addError($lang->category['err_parent_missing']);
                return false;
            }
            if ($id !== null && $data['pid'] === $id) {
                $this->addError($lang->category['err_own_parent']);
                return false;
            }
            // Раньше главную категорию с подкатегориями можно было сделать подкатегорией —
            // её подкатегории оставались ссылаться на неё и получался третий уровень,
            // который browse.php и кэш не умеют показывать
            if ($id !== null && $this->getSubcategoryCount($id) > 0) {
                $this->addError($lang->category['err_has_subs_demote']);
                return false;
            }
        }

        if ($id === null) {
            $sql    = "INSERT INTO categories (name, icon, type, pid) VALUES (?, ?, ?, ?)";
            $params = [$data['name'], $data['icon'], $data['type'], (int)$data['pid']];
        } else {
            $sql    = "UPDATE categories SET name = ?, icon = ?, type = ?, pid = ? WHERE id = ?";
            $params = [$data['name'], $data['icon'], $data['type'], (int)$data['pid'], (int)$id];
        }

        if (!$this->db->sql_query_prepared($sql, $params)) {
            $this->addError($lang->category['err_db_save']);
            return false;
        }

        $this->updateCategoriesCache();
        return true;
    }

    // ═══════════════════════════════════════════════════════════
    // ROUTING
    // ═══════════════════════════════════════════════════════════

    public function handleRequest(): void
    {
        global $lang;

        $action = $_GET['do'] ?? $_POST['do'] ?? '';
        $what   = $_GET['what'] ?? $_POST['what'] ?? '';
        $id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $cid    = (int)($_GET['cid'] ?? $_POST['cid'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_post_check($_POST['my_post_key'] ?? '')) {
                http_response_code(403);
                echo $this->e($lang->category['err_token']);
                exit;
            }
        }

        switch ($action) {
            case 'new':             $this->handleNew((string)$what); break;
            case 'edit':            $this->handleEdit((string)$what, $id); break;
            case 'delete':          $this->handleDelete((string)$what, $id); break;
            case 'add_subcategory': $this->handleAddSubcategory((string)$what, $cid); break;
            case 'ajax_get_category': $this->ajaxGetCategory($id); break;
            default:                $this->showCategoryList(); break;
        }
    }

    // Раньше redirect('admin/index.php?act=category') — из /admin/ это /admin/admin/…
    private function done(string $message = ''): never
    {
        redirect($this->baseScript, $message);
        exit;
    }

    private function handleNew(string $what): void
    {
        global $lang;

        if ($what === 'save') {
            $data = $this->sanitizeInput($_POST);
            if ($this->saveCategory($data)) {
                $this->done($lang->category['flash_added']);
            }
        }
        $this->showCategoryForm($lang->category['pane_add_category']);
    }

    private function handleEdit(string $what, int $id): void
    {
        global $lang;

        if (!$this->validateCategoryId($id)) {
            stderr($lang->category['err_title'], $lang->category['err_not_found']);
            return;
        }
        if ($what === 'save') {
            $data = $this->sanitizeInput($_POST);
            if ($this->saveCategory($data, $id)) {
                $this->done($lang->category['flash_updated']);
            }
        }
        $category = $this->getCategory($id);
        $this->showCategoryForm($lang->category['pane_edit_category'], $category);
    }

    private function ajaxGetCategory(int $id): void
    {
        global $lang;

        header('Content-Type: application/json');
        if (!$this->validateCategoryId($id)) {
            echo json_encode(['error' => $lang->category['err_ajax_not_found']]);
            exit;
        }
        $category = $this->getCategory($id);
        $category['has_subs'] = $this->getSubcategoryCount($id) > 0;
        echo json_encode($category);
        exit;
    }

    private function handleDelete(string $what, int $id): void
    {
        global $mybb, $lang;

        if (!$this->validateCategoryId($id)) {
            stderr($lang->category['err_title'], $lang->category['err_not_found']);
            return;
        }

        $torrentCount = $this->getTorrentCountForCategory($id);
        if ($torrentCount > 0) {
            stderr($lang->category['err_title'], ags_fmt($lang->category['err_has_torrents'], $torrentCount));
            return;
        }
        // Раньше главную категорию можно было удалить вместе с «повисшими» подкатегориями
        $subCount = $this->getSubcategoryCount($id);
        if ($subCount > 0) {
            stderr($lang->category['err_title'], ags_fmt($lang->category['err_has_subs'], $subCount));
            return;
        }

        if ($what === 'sure' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->db->sql_query_prepared("DELETE FROM categories WHERE id = ? LIMIT 1", [$id]);
            $this->updateCategoriesCache();
            $this->done($lang->category['flash_deleted']);
        }

        $category = $this->getCategory($id);
        $this->deleteModalHtml = '
<div class="modal fade cm-modal" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="cm-mh-icon ic-red"><i class="fa-solid fa-trash"></i></span>
                <h5 class="modal-title fw-bold" id="deleteModalLabel">' . $this->e($lang->category['pane_delete']) . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $this->e($lang->category['btn_close']) . '"></button>
            </div>
            <div class="modal-body">
                <div class="cm-note mb-3"><span class="cm-mh-icon cm-mh-sm ic-blue m-0">' . $this->icon($category['icon'] ?? '') . '</span>
                    <div><div class="fw-bold">' . $this->e($category['name']) . '</div><div class="text-body-secondary small">' . $this->e(ags_fmt($lang->category['hint_delete_meta'], (int)$id)) . '</div></div></div>
                <div class="text-body-secondary"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>' . $this->e($lang->category['hint_cannot_undo']) . '</div>
            </div>
            <div class="modal-footer">
                <a href="' . $this->baseScript . '" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-xmark me-1"></i>' . $this->e($lang->category['btn_cancel']) . '</a>
                <form method="post" action="' . $this->baseScript . '" class="d-inline">
                    <input type="hidden" name="my_post_key" value="' . $this->e($mybb->post_code) . '">
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="id" value="' . (int)$id . '">
                    <input type="hidden" name="what" value="sure">
                    <button type="submit" class="btn btn-danger px-3"><i class="fa-solid fa-trash me-1"></i>' . $this->e($lang->category['btn_delete']) . '</button>
                </form>
            </div>
        </div>
    </div>
</div>';
        // Раньше модалка печаталась ДО stdhead() — то есть до <html>
        $this->showCategoryList();
    }

    private string $deleteModalHtml = '';

    private function handleAddSubcategory(string $what, int $cid): void
    {
        global $lang;

        if (!$this->validateCategoryId($cid, 'c')) {
            stderr($lang->category['err_title'], $lang->category['err_main_not_found']);
            return;
        }
        if ($what === 'save') {
            $data = $this->sanitizeInput($_POST);
            $data['type'] = 's';
            $data['pid']  = $cid;
            if ($this->saveCategory($data)) {
                $this->done($lang->category['flash_sub_added']);
            }
        }
        $this->showSubcategoryForm($this->getCategory($cid));
    }

    // ═══════════════════════════════════════════════════════════
    // STANDALONE FORMS (используются при ошибке сохранения)
    // ═══════════════════════════════════════════════════════════

    private function pageHero(string $icon, string $cls, string $title, string $sub, string $right = ''): string
    {
        return '<div class="cm-card mb-3"><div class="cm-head">'
             . '<span class="cm-head-icon ' . $cls . '"><i class="fa-solid ' . $icon . '"></i></span>'
             . '<div class="cm-minw0"><h1 class="cm-title">' . $title . '</h1><div class="cm-sub">' . $sub . '</div></div>'
             . ($right !== '' ? '<div class="ms-auto d-flex gap-2">' . $right . '</div>' : '')
             . '</div></div>';
    }

    private function showCategoryForm(string $title, ?array $category = null): void
    {
        global $mybb, $lang;

        $isEdit = ($category !== null);
        $data = $category ?? ['name' => '', 'icon' => '', 'type' => 'c', 'pid' => 0];
        if (!empty($_POST['what'])) {           // после ошибки — то, что ввёл пользователь
            $data['name'] = (string)($_POST['name'] ?? $data['name']);
            $data['icon'] = (string)($_POST['icon'] ?? $data['icon']);
            $data['pid']  = (int)($_POST['cid'] ?? $data['pid']);
        }

        stdhead(ags_fmt($lang->category['title_form'], $title));
        $this->assets();

        echo '<div class="container mt-3 mb-4 cm cm-narrow">';
        echo $this->pageHero($isEdit ? 'fa-pen-to-square' : 'fa-folder-plus', 'ic-blue', $this->e($title),
            $isEdit ? ags_fmt($this->e($lang->category['sub_edit_form']), (int)$category['id'], $this->e($category['name'])) : $this->e($lang->category['sub_new_form']),
            '<a href="' . $this->baseScript . '" class="btn btn-sm btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i>' . $this->e($lang->category['btn_back']) . '</a>');
        $this->showErrors();

        echo '<form method="post" action="' . $this->baseScript . '" class="cm-card p-3 p-md-4">
            <input type="hidden" name="my_post_key" value="' . $this->e($mybb->post_code) . '">
            <input type="hidden" name="do" value="' . ($isEdit ? 'edit' : 'new') . '">
            <input type="hidden" name="what" value="save">'
            . ($isEdit ? '<input type="hidden" name="id" value="' . (int)$category['id'] . '">' : '') . '
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label"><i class="fa-solid fa-tag"></i>' . $this->e($lang->category['lbl_name']) . ' <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="' . $this->e($data['name']) . '" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="fa-solid fa-sitemap"></i>' . $this->e($lang->category['lbl_parent']) . '</label>
                    ' . $this->getCategoryDropdown((int)$data['pid'], 'cid', false, $isEdit ? (int)$category['id'] : 0) . '
                </div>
                <div class="col-12">
                    <label class="form-label"><i class="fa-solid fa-icons"></i>' . $this->e($lang->category['lbl_icon']) . '</label>
                    ' . $this->getIconSelector((string)$data['icon']) . '
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i>' . $this->e($lang->category['btn_reset']) . '</button>
                <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i>' . $this->e($lang->category['btn_save']) . '</button>
            </div>
        </form></div>';

        stdfoot();
        exit;
    }

    private function showSubcategoryForm(array $parentCategory): void
    {
        global $mybb, $lang;

        stdhead($lang->category['title_add_sub']);
        $this->assets();

        echo '<div class="container mt-3 mb-4 cm cm-narrow">';
        echo $this->pageHero('fa-folder-plus', 'ic-green', $this->e($lang->category['pane_add_sub']),
            ags_fmt($this->e($lang->category['sub_inside']), '<strong>' . $this->e($parentCategory['name']) . '</strong>'),
            '<a href="' . $this->baseScript . '" class="btn btn-sm btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i>' . $this->e($lang->category['btn_back']) . '</a>');
        $this->showErrors();

        echo '<form method="post" action="' . $this->baseScript . '" class="cm-card p-3 p-md-4">
            <input type="hidden" name="my_post_key" value="' . $this->e($mybb->post_code) . '">
            <input type="hidden" name="do" value="add_subcategory">
            <input type="hidden" name="what" value="save">
            <input type="hidden" name="cid" value="' . (int)$parentCategory['id'] . '">
            <div class="mb-3">
                <label for="name" class="form-label"><i class="fa-solid fa-tag"></i>' . $this->e($lang->category['lbl_name']) . ' <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="' . $this->e($_POST['name'] ?? '') . '" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fa-solid fa-icons"></i>' . $this->e($lang->category['lbl_icon']) . '</label>
                ' . $this->getIconSelector((string)($_POST['icon'] ?? '')) . '
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="' . $this->baseScript . '" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-xmark me-1"></i>' . $this->e($lang->category['btn_cancel']) . '</a>
                <button type="submit" class="btn btn-success px-4"><i class="fa-solid fa-plus me-1"></i>' . $this->e($lang->category['btn_add_sub']) . '</button>
            </div>
        </form></div>';

        stdfoot();
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    // LIST
    // ═══════════════════════════════════════════════════════════

    private function modal(string $id, string $icon, string $cls, string $title, string $body, string $submit, string $formId = '', string $extraHidden = ''): string
    {
        global $mybb, $lang;
        return '
<div class="modal fade cm-modal" id="' . $id . '" tabindex="-1" aria-labelledby="' . $id . 'Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <span class="cm-mh-icon ' . $cls . '"><i class="fa-solid ' . $icon . '"></i></span>
                <h5 class="modal-title fw-bold" id="' . $id . 'Label">' . $title . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $this->e($lang->category['btn_close']) . '"></button>
            </div>
            <form method="post" action="' . $this->baseScript . '"' . ($formId ? ' id="' . $formId . '"' : '') . '>
                <input type="hidden" name="my_post_key" value="' . $this->e($mybb->post_code) . '">' . $extraHidden . '
                ' . $body . '
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>' . $this->e($lang->category['btn_cancel']) . '</button>
                    ' . $submit . '
                </div>
            </form>
        </div>
    </div>
</div>';
    }

    private function showCategoryList(): void
    {
        global $BASEURL, $lang;

        // Данные: категории, подкатегории, количество раздач (один GROUP BY)
        $categories = $subcategories = $counts = [];
        $query = $this->db->sql_query_prepared("SELECT * FROM categories WHERE type = 'c' ORDER BY name");
        while ($query && ($cat = $this->db->fetch_array($query))) {
            $categories[(int)$cat['id']] = $cat;
        }
        $query = $this->db->sql_query_prepared("SELECT * FROM categories WHERE type = 's' ORDER BY name");
        while ($query && ($sub = $this->db->fetch_array($query))) {
            $subcategories[(int)$sub['pid']][] = $sub;
        }
        $query = $this->db->sql_query_prepared("SELECT category, COUNT(*) AS n FROM torrents GROUP BY category");
        while ($query && ($r = $this->db->fetch_array($query))) {
            $counts[(int)$r['category']] = (int)$r['n'];
        }
        $n_subs     = array_sum(array_map('count', $subcategories));
        $n_torrents = array_sum($counts);
        $all_ids    = array_keys($categories);
        foreach ($subcategories as $list) {
            foreach ($list as $s) $all_ids[] = (int)$s['id'];
        }
        $n_empty    = count(array_filter($all_ids, fn($cid) => empty($counts[$cid])));

        stdhead($lang->category['title_list']);
        $this->assets();

        // Готовые куски разметки, которые повторяются в карточках
        $t_edit     = $this->e($lang->category['tip_edit']);
        $t_delete   = $this->e($lang->category['tip_delete']);
        $t_view     = $this->e($lang->category['tip_view']);
        $t_view_br  = $this->e($lang->category['tip_view_browse']);
        $t_total    = $this->e($lang->category['tip_total_torrents']);
        $t_lock_tor = $lang->category['tip_locked_torrents'];
        $t_lock_sub = $lang->category['tip_locked_subs'];
        $btn_add_cat = '<button type="button" class="btn btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addCategoryModal"><i class="fa-solid fa-plus me-1"></i>' . $this->e($lang->category['btn_add_category']) . '</button>';

        // ── Модалки ────────────────────────────────────────────
        echo $this->modal('addCategoryModal', 'fa-folder-plus', 'ic-blue', $this->e($lang->category['modal_add_category']),
            '<div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label for="modal_name" class="form-label"><i class="fa-solid fa-tag"></i>' . $this->e($lang->category['lbl_name']) . ' <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="modal_name" name="name" required></div>
                <div class="col-md-6"><label class="form-label"><i class="fa-solid fa-sitemap"></i>' . $this->e($lang->category['lbl_parent']) . '</label>' . $this->getCategoryDropdown(0, 'cid') . '
                    <div class="form-text">' . $this->e($lang->category['hint_parent_none']) . '</div></div>
                <div class="col-12"><label class="form-label"><i class="fa-solid fa-icons"></i>' . $this->e($lang->category['lbl_icon']) . '</label>' . $this->getIconSelector() . '</div>
            </div></div>',
            '<button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i>' . $this->e($lang->category['btn_save']) . '</button>',
            '', '<input type="hidden" name="do" value="new"><input type="hidden" name="what" value="save">');

        echo $this->modal('editCategoryModal', 'fa-pen-to-square', 'ic-blue', $this->e($lang->category['modal_edit_category']),
            '<div class="modal-body" id="editCategoryModalBody"><div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">' . $this->e($lang->category['lbl_loading']) . '</span></div></div></div>',
            '<button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i>' . $this->e($lang->category['btn_save_changes']) . '</button>',
            'editCategoryForm');

        echo $this->modal('addSubcategoryModal', 'fa-folder-plus', 'ic-green', $this->e($lang->category['pane_add_sub']),
            '<div class="modal-body">
                <div class="cm-note mb-3"><i class="fa-solid fa-sitemap text-success mt-1"></i><div>' . ags_fmt($this->e($lang->category['sub_inside']), '<strong id="parentCategoryName"></strong>') . '</div></div>
                <div class="mb-3"><label for="sub_name" class="form-label"><i class="fa-solid fa-tag"></i>' . $this->e($lang->category['lbl_name']) . ' <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="sub_name" name="name" required></div>
                <div><label class="form-label"><i class="fa-solid fa-icons"></i>' . $this->e($lang->category['lbl_icon']) . '</label>' . $this->getIconSelector() . '</div>
            </div>',
            '<button type="submit" class="btn btn-success px-4"><i class="fa-solid fa-plus me-1"></i>' . $this->e($lang->category['btn_add_sub']) . '</button>',
            'addSubcategoryForm',
            '<input type="hidden" name="do" value="add_subcategory"><input type="hidden" name="what" value="save"><input type="hidden" name="cid" id="parentCategoryId" value="">');

        echo $this->deleteModalHtml;

        // ── Страница ───────────────────────────────────────────
        echo '<div class="container mt-3 mb-4 cm">';
        echo $this->pageHero('fa-folder-tree', 'ic-purple', $this->e($lang->category['pane_list']), $this->e($lang->category['sub_list']), $btn_add_cat);

        $this->showErrors();

        echo '<div class="row g-3 mb-3">';
        foreach ([
            ['fa-folder',      'ic-blue',   $lang->category['stat_main'],     count($categories)],
            ['fa-sitemap',     'ic-green',  $lang->category['stat_subs'],     $n_subs],
            ['fa-magnet',      'ic-amber',  $lang->category['stat_torrents'], $n_torrents],
            ['fa-folder-open', 'ic-purple', $lang->category['stat_empty'],    $n_empty],
        ] as [$ic, $cls, $label, $val]) {
            echo '<div class="col-6 col-lg-3"><div class="cm-card cm-stat"><span class="cm-stat-icon ' . $cls . '"><i class="fa-solid ' . $ic . '"></i></span>'
               . '<div><div class="cm-stat-label">' . $this->e($label) . '</div><div class="cm-stat-value">' . number_format((int)$val) . '</div></div></div></div>';
        }
        echo '</div>';

        if (empty($categories)) {
            echo '<div class="cm-card"><div class="cm-empty"><i class="fa-solid fa-folder-plus"></i><div class="fw-semibold">' . $this->e($lang->category['empty_title']) . '</div>'
               . '<div class="small mb-3">' . $this->e($lang->category['empty_text']) . '</div>'
               . $btn_add_cat . '</div></div>';
        } else {
            echo '<div class="d-flex justify-content-end mb-3"><div class="position-relative cm-search"><i class="fa-solid fa-magnifying-glass"></i>'
               . '<input type="search" class="form-control form-control-sm" id="cmFilter" placeholder="' . $this->e($lang->category['ph_filter']) . '" aria-label="' . $this->e($lang->category['ph_filter']) . '"></div></div>';
            echo '<div class="row g-3" id="cmGrid">';

            foreach ($categories as $cid => $category) {
                $subs     = $subcategories[$cid] ?? [];
                $own      = $counts[$cid] ?? 0;
                $total    = $own + array_sum(array_map(fn($s) => $counts[(int)$s['id']] ?? 0, $subs));
                $name     = $this->e($category['name']);
                $locked   = $own > 0 || $subs;
                $lock_why = $own > 0 ? $t_lock_tor : $t_lock_sub;
                $search   = mb_strtolower($category['name'] . ' ' . implode(' ', array_column($subs, 'name')));

                echo '<div class="col-md-6 col-xl-4 cm-cat-col" data-search="' . $this->e($search) . '"><div class="cm-card cm-cat">';
                echo '<div class="cm-cat-head"><span class="cm-cat-icon">' . $this->icon($category['icon'] ?? '') . '</span>'
                   . '<div class="cm-grow"><div class="cm-cat-name">' . $name . '</div>'
                   . '<div class="d-flex flex-wrap align-items-center gap-2 mt-1"><span class="cm-id">#' . $cid . '</span>'
                   . '<span class="cm-count' . ($total ? ' has' : '') . '" title="' . $t_total . '"><i class="fa-solid fa-magnet"></i>' . number_format($total) . '</span>'
                   . '<span class="cm-count"><i class="fa-solid fa-sitemap"></i>' . count($subs) . '</span></div></div>'
                   . '<div class="d-flex flex-shrink-0">'
                   . '<a href="' . $BASEURL . '/browse.php?cat=' . $cid . '" class="cm-act" title="' . $t_view_br . '" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>'
                   . '<button type="button" class="cm-act edit-category-btn" data-id="' . $cid . '" title="' . $t_edit . '"><i class="fa-solid fa-pen"></i></button>'
                   . ($locked
                       ? '<span class="cm-act locked" title="' . $this->e($lock_why) . '"><i class="fa-solid fa-lock"></i></span>'
                       : '<a href="' . $this->baseScript . '&amp;do=delete&amp;id=' . $cid . '" class="cm-act danger" title="' . $t_delete . '"><i class="fa-solid fa-trash"></i></a>')
                   . '</div></div>';

                echo '<div class="cm-subs">';
                if ($subs) {
                    foreach ($subs as $sub) {
                        $sid = (int)$sub['id'];
                        $n   = $counts[$sid] ?? 0;
                        echo '<div class="cm-subrow"><span class="cm-sub-icon">' . $this->icon($sub['icon'] ?? '', 'fa-solid fa-folder') . '</span>'
                           . '<span class="cm-sub-name" title="' . $this->e($sub['name']) . '">' . $this->e($sub['name']) . '</span>'
                           . '<span class="cm-count' . ($n ? ' has' : '') . '"><i class="fa-solid fa-magnet"></i>' . number_format($n) . '</span>'
                           . '<a href="' . $BASEURL . '/browse.php?cat=' . $sid . '" class="cm-act" title="' . $t_view . '" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>'
                           . '<button type="button" class="cm-act edit-category-btn" data-id="' . $sid . '" title="' . $t_edit . '"><i class="fa-solid fa-pen"></i></button>'
                           . ($n > 0
                               ? '<span class="cm-act locked" title="' . $this->e($t_lock_tor) . '"><i class="fa-solid fa-lock"></i></span>'
                               : '<a href="' . $this->baseScript . '&amp;do=delete&amp;id=' . $sid . '" class="cm-act danger" title="' . $t_delete . '"><i class="fa-solid fa-trash"></i></a>')
                           . '</div>';
                    }
                } else {
                    echo '<div class="cm-subs-empty"><i class="fa-solid fa-inbox"></i>' . $this->e($lang->category['empty_no_subs']) . '</div>';
                }
                echo '</div>';

                echo '<div class="cm-cat-foot"><button type="button" class="btn btn-sm btn-outline-success w-100 add-subcategory-btn" data-id="' . $cid . '" data-name="' . $name . '">'
                   . '<i class="fa-solid fa-plus me-1"></i>' . $this->e($lang->category['btn_add_sub']) . '</button></div>';
                echo '</div></div>';
            }
            echo '</div>';
            echo '<div class="cm-card mt-3" id="cmNoMatch" hidden><div class="cm-empty"><i class="fa-solid fa-magnifying-glass"></i><div class="fw-semibold">' . $this->e($lang->category['empty_no_match']) . '</div></div></div>';
        }
        echo '</div>';
        // Данные для admin_category.js (шаблоны для модалки редактирования)
        echo '<script type="application/json" id="cmConfig">' . json_encode([
            'dropdownHtml'     => $this->getCategoryDropdown(0, 'cid'),
            'iconSelectorHtml' => $this->getIconSelector(),
            'baseScript'       => $this->baseScript,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>';
        stdfoot();
    }

    // ═══════════════════════════════════════════════════════════
    // DATA
    // ═══════════════════════════════════════════════════════════

    private function getCategory(int $id): ?array
    {
        $query = $this->db->sql_query_prepared("SELECT * FROM categories WHERE id = ?", [$id]);
        if ($query && ($row = $this->db->fetch_array($query))) {
            // prepared statements отдают int — для JSON фронтенда оставляем строки, как раньше
            if (isset($row['id']))  $row['id']  = (string)$row['id'];
            if (isset($row['pid'])) $row['pid'] = (string)$row['pid'];
            return $row;
        }
        return null;
    }

    private function getTorrentCountForCategory(int $id): int
    {
        $query = $this->db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM torrents WHERE category = ?", [$id]);
        return ($query && ($row = $this->db->fetch_array($query))) ? (int)$row['cnt'] : 0;
    }

    private function getSubcategoryCount(int $id): int
    {
        $query = $this->db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM categories WHERE type = 's' AND pid = ?", [$id]);
        return ($query && ($row = $this->db->fetch_array($query))) ? (int)$row['cnt'] : 0;
    }

    private function validateCategoryId(int $id, ?string $type = null): bool
    {
        $sql    = "SELECT id FROM categories WHERE id = ?";
        $params = [$id];
        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        $query = $this->db->sql_query_prepared($sql, $params);
        return $query ? $this->db->num_rows($query) > 0 : false;
    }
}

// Initialize and run category manager
$categoryManager = new CategoryManager($db);
$categoryManager->handleRequest();
