<?php

declare(strict_types=1);

require_once INC_PATH . '/functions_faq.php';

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3" role="alert">
        <i class="fas fa-ban me-2"></i><b>Error!</b> Direct initialization of this file is not allowed.
    </div>');
}

$lang->load('faq');

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

function show_faq_errors(): void
{
    global $faq_errors, $lang;

    if (empty($faq_errors)) {
        return;
    }

    $errors = implode('<br />', array_map('htmlspecialchars_uni', $faq_errors));

    echo '
    <div class="container mt-3">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>' . $lang->global['error'] . '</h5>
            <hr>
            <p class="mb-0"><strong>' . $errors . '</strong></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>';
}

/**
 * Общие стили страницы - Oswald + primary-акцент, тот же язык, что и на
 * остальных переоформленных страницах. Плюс общий JS (подтверждение
 * удаления) - раньше был раскидан по разным обработчикам вперемешку
 * с SweetAlert2 (внешний CDN) и обычным confirm() одновременно.
 * Теперь один единообразный confirm() везде, без внешних зависимостей.
 */
function render_faqmanage_styles(): void
{
    echo '
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --fm-accent: var(--bs-primary, #0d6efd);
            --fm-accent-strong: var(--bs-primary-text-emphasis, #0a58ca);
            --fm-accent-soft: var(--bs-primary-bg-subtle, rgba(13,110,253,.1));
        }
        .fm-masthead {
            padding: 1.6rem 1.75rem;
            margin-bottom: 1.25rem;
            background: var(--bs-body-bg, #fff);
            border: 1px solid var(--bs-border-color, #e9ecef);
            border-radius: .9rem;
        }
        .fm-masthead__eyebrow {
            display: inline-block;
            font-family: "Oswald", sans-serif;
            font-weight: 600;
            font-size: .72rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--fm-accent-strong);
            background: var(--fm-accent-soft);
            border: 1px solid var(--fm-accent);
            border-radius: 999px;
            padding: .3rem .85rem;
            margin-bottom: .7rem;
        }
        .fm-masthead__title {
            font-family: "Oswald", sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            font-size: clamp(1.35rem, 2.8vw, 1.8rem);
            margin: 0;
            color: var(--bs-emphasis-color, #212529);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
        }
        .fm-masthead__title i { color: var(--fm-accent); }

        .fm-panel {
            border: 1px solid var(--bs-border-color, #e9ecef) !important;
            border-radius: .9rem !important;
            overflow: hidden;
        }
        .fm-panel .card-header {
            background: var(--bs-tertiary-bg, #f8f9fa) !important;
            color: var(--bs-emphasis-color, #212529) !important;
            border-bottom: 1px solid var(--bs-border-color, #e9ecef);
            border-left: 4px solid var(--fm-accent);
        }
        .fm-panel .card-header h4,
        .fm-panel .card-header h5 {
            font-family: "Oswald", sans-serif;
            font-weight: 600;
            font-size: 1.05rem;
        }
        .fm-panel table thead th {
            font-family: "Oswald", sans-serif;
            font-size: .74rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--bs-secondary-color, #6c757d);
            background: var(--bs-tertiary-bg, #f8f9fa) !important;
        }
        .fm-panel table tbody tr:hover { background-color: var(--fm-accent-soft); }

        .fm-form-label {
            font-family: "Oswald", sans-serif;
            font-weight: 500;
            font-size: .85rem;
            letter-spacing: .01em;
        }

        .accordion-button:not(.collapsed) {
            background: var(--fm-accent-soft);
            color: var(--fm-accent-strong);
        }
        .accordion-button:focus { box-shadow: 0 0 0 .2rem var(--fm-accent-soft); }
    </style>
    <script>
    function faqConfirmDelete(formId) {
        if (confirm("This FAQ item will be permanently deleted. Are you sure?")) {
            document.getElementById(formId).submit();
        }
        return false;
    }
    </script>';
}

/**
 * Форма удаления одной кнопкой - POST + CSRF-токен, вместо голой
 * GET-ссылки, что была раньше. Каждая кнопка удаления - отдельная
 * крошечная форма с уникальным id, отправляемая через faqConfirmDelete().
 */
function faq_delete_button(int $id, string $extraClass = ''): string
{
    global $_this_script_, $mybb;

    $formId = 'faqDeleteForm' . $id;

    return '
        <form id="' . $formId . '" method="post" action="' . $_this_script_ . '" class="d-inline">
            <input type="hidden" name="do" value="delete">
            <input type="hidden" name="id" value="' . $id . '">
            <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code, ENT_QUOTES) . '">
        </form>
        <a href="#" onclick="return faqConfirmDelete(\'' . $formId . '\')"
           class="btn btn-sm btn-outline-danger ' . $extraClass . '" title="Delete">
            <i class="fas fa-trash"></i>
        </a>';
}

// ═══════════════════════════════════════════════════════════
// ROUTING
// ═══════════════════════════════════════════════════════════

define('TSFAQMANAGE_VERSION', '1.3.2 by xam');

$do    = htmlspecialchars_uni($_GET['do'] ?? $_POST['do'] ?? '');
$subdo = htmlspecialchars_uni($_GET['subdo'] ?? $_POST['subdo'] ?? '');
$id    = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

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

function handleView(int $id): void
{
    global $db, $lang, $faq_errors, $_this_script_;

    if (!is_valid_id($id)) {
        $faq_errors[] = $lang->faq['faqerror'];
        stdhead($lang->faq['faqtitle'], true, '', '');
        show_faq_errors();
        return;
    }

    $query = $db->sql_query_prepared("
        SELECT a.id, a.name, a.description, b.name AS title
        FROM faq a
        LEFT JOIN faq b ON (a.pid = b.id)
        WHERE a.type = 'item' AND a.pid = ?
        ORDER BY a.disporder ASC
    ", [$id]);

    if (!$query) {
        die('SQL ERROR: ' . $db->error());
    }

    if ($db->num_rows($query) === 0) {
        $faq_errors[] = $lang->faq['faqerror'];
        stdhead($lang->faq['faqtitle'], true, '', '');
        show_faq_errors();
        return;
    }

    stdhead($lang->faq['faqtitle'], true, '', '');
    render_faqmanage_styles();

    echo '
    <div class="fm-masthead">
        <span class="fm-masthead__eyebrow">Admin / FAQ</span>
        <h1 class="fm-masthead__title"><i class="fas fa-question-circle me-2"></i>' . $lang->faq['faqtitle'] . '</h1>
    </div>
    <div class="container mt-3">
        <div class="card fm-panel shadow-sm">
            <div class="card-body">
                <div class="accordion" id="faqAccordion">';

    $currentTitle = '';

    while ($faq = $db->fetch_array($query)) {
        if ($currentTitle !== $faq['title']) {
            $currentTitle = $faq['title'];
            echo '
            <h5 class="mt-4 mb-3" style="font-family:\'Oswald\',sans-serif;font-weight:600;color:var(--fm-accent-strong);">
                <i class="fas fa-folder me-2"></i>
                ' . htmlspecialchars_uni($currentTitle) . '
            </h5>';
        }

        $collapseId = 'collapse' . $faq['id'];

        echo '
        <div class="accordion-item mb-2">
            <h2 class="accordion-header" id="heading' . $faq['id'] . '">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#' . $collapseId . '">
                    <strong class="text-danger">' . htmlspecialchars_uni($faq['name']) . '</strong>

                    <span class="ms-auto">
                        <a href="' . $_this_script_ . '&do=edit&id=' . $faq['id'] . '" class="btn btn-sm btn-outline-primary me-1">
                            <i class="fas fa-edit"></i>
                        </a>
                        ' . faq_delete_button((int)$faq['id']) . '
                    </span>
                </button>
            </h2>

            <div id="' . $collapseId . '" class="accordion-collapse collapse">
                <div class="accordion-body bg-light">
                    ' . $faq['description'] . '
                </div>
            </div>
        </div>';
    }

    echo '
                </div>
            </div>
        </div>
    </div>';
}

function handleSaveDisplayOrder(): void
{
    global $db, $faq_errors, $mybb, $_this_script_;

    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        $faq_errors[] = 'Security check failed. Please try again.';
        stdhead('FAQ');
        show_faq_errors();
        return;
    }

    $orders = $_POST['disporder'] ?? [];

    if (!is_array($orders)) {
        $faq_errors[] = 'Empty FAQ order(s)!';
        stdhead('FAQ');
        show_faq_errors();
        return;
    }

    foreach ($orders as $id => $order) {
        $db->sql_query_prepared("UPDATE faq SET disporder = ? WHERE id = ?", [(int)$order, (int)$id]);
    }

    header('Location: ' . $_this_script_);
    exit;
}

function handleDelete(int $id): void
{
    global $db, $lang, $faq_errors, $mybb, $_this_script_;

    if (!is_valid_id($id)) {
        $faq_errors[] = $lang->faq['faqerror'];
        stdhead($lang->faq['faqtitle'], true, '', '');
        show_faq_errors();
        return;
    }

    // Раньше удаление шло по голой GET-ссылке, без проверки метода и без
    // CSRF-токена вообще - сторонняя страница могла обманом заставить
    // залогиненного админа удалить FAQ-пункт незаметно. Теперь только POST.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($mybb->get_input('my_post_key'), true)) {
        http_response_code(403);
        die('Invalid security token or request method.');
    }

    $db->sql_query_prepared("DELETE FROM faq WHERE id = ?", [$id]);
    $db->sql_query_prepared("DELETE FROM faq WHERE pid = ?", [$id]);

    header('Location: ' . $_this_script_);
    exit;
}

function handleNew(): void
{
    global $db, $lang, $faq_errors, $mybb, $_this_script_;

    if (($_POST['subdo'] ?? '') === 'save') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            $faq_errors[] = 'Security check failed. Please try again.';
        } else {
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $disporder   = (int)($_POST['disporder'] ?? 0);

            if (empty($name)) {
                $faq_errors[] = 'Please fill all fields!';
            } else {
                $db->sql_query_prepared(
                    "INSERT INTO faq (type, name, description, disporder) VALUES ('1', ?, ?, ?)",
                    [$name, $description, $disporder]
                );
                header('Location: ' . $_this_script_);
                exit;
            }
        }
    }

    $name        = htmlspecialchars_uni($_POST['name'] ?? '');
    $description = htmlspecialchars_uni($_POST['description'] ?? '');
    $disporder   = (int)($_POST['disporder'] ?? 0);

    $where = ['Cancel' => $_this_script_];

    stdhead($lang->faq['faqtitle'], true, '', '');
    render_faqmanage_styles();
    show_faq_errors();

    echo '
    <div class="fm-masthead">
        <span class="fm-masthead__eyebrow">Admin / FAQ</span>
        <h1 class="fm-masthead__title"><i class="fas fa-plus-circle me-2"></i>Add New FAQ Item</h1>
    </div>
    <form method="post" action="' . $_this_script_ . '">
    <input type="hidden" name="do" value="new">
    <input type="hidden" name="subdo" value="save">
    <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code, ENT_QUOTES) . '">

    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12 text-end">
                ' . jumpbutton($where) . '
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card fm-panel shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="name" class="fm-form-label form-label">
                                    <i class="fas fa-heading me-1"></i>
                                    Title <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="name"
                                       name="name"
                                       value="' . $name . '"
                                       required>
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="fm-form-label form-label">
                                    <i class="fas fa-align-left me-1"></i>
                                    Description
                                </label>
                                <textarea class="form-control"
                                          id="description"
                                          name="description"
                                          rows="8">' . $description . '</textarea>
                            </div>

                            <div class="col-md-3">
                                <label for="disporder" class="fm-form-label form-label">
                                    <i class="fas fa-sort-numeric-up me-1"></i>
                                    Display Order
                                </label>
                                <input type="number"
                                       class="form-control"
                                       id="disporder"
                                       name="disporder"
                                       value="' . $disporder . '"
                                       min="0">
                            </div>

                            <div class="col-12 mt-4">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-2"></i>
                                        Save FAQ Item
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-2"></i>
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </form>';
}

function handleAdd(int $id): void
{
    global $db, $lang, $faq_errors, $mybb, $_this_script_;

    if (!is_valid_id($id)) {
        $faq_errors[] = $lang->faq['faqerror'];
        stdhead($lang->faq['faqtitle'], true, '', '');
        show_faq_errors();
        return;
    }

    if (($_POST['subdo'] ?? '') === 'save') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            $faq_errors[] = 'Security check failed. Please try again.';
        } else {
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $disporder   = (int)($_POST['disporder'] ?? 0);
            $pid         = (int)($_POST['pid'] ?? 0);

            if (empty($name)) {
                $faq_errors[] = 'Please fill all fields!';
            } else {
                $db->sql_query_prepared(
                    "INSERT INTO faq (type, name, description, disporder, pid) VALUES ('2', ?, ?, ?, ?)",
                    [$name, $description, $disporder, $pid]
                );
                header('Location: ' . $_this_script_);
                exit;
            }
        }
    }

    $query = $db->sql_query_prepared("SELECT * FROM faq WHERE type = 'category'");
    if (!$query || $db->num_rows($query) === 0) {
        $faq_errors[] = $lang->faq['faqerror'];
        stdhead($lang->faq['faqtitle'], true, '', '');
        show_faq_errors();
        return;
    }

    stdhead($lang->faq['faqtitle'], true, '', '');
    render_faqmanage_styles();
    show_faq_errors();

    $categories = '<select name="pid" class="form-select">';
    while ($faq = $db->fetch_array($query)) {
        $selected = ($id === (int)$faq['id']) ? ' selected="selected"' : '';
        $categories .= '<option value="' . $faq['id'] . '"' . $selected . '>' . htmlspecialchars_uni($faq['name']) . '</option>';
    }
    $categories .= '</select>';

    $name        = htmlspecialchars_uni($_POST['name'] ?? '');
    $description = htmlspecialchars_uni($_POST['description'] ?? '');
    $disporder   = (int)($_POST['disporder'] ?? 0);

    $where = ['Cancel' => $_this_script_];

    echo '
    <div class="fm-masthead">
        <span class="fm-masthead__eyebrow">Admin / FAQ</span>
        <h1 class="fm-masthead__title"><i class="fas fa-plus-circle me-2"></i>Add Child FAQ Item</h1>
    </div>
    <form method="post" action="' . $_this_script_ . '">
    <input type="hidden" name="do" value="add">
    <input type="hidden" name="subdo" value="save">
    <input type="hidden" name="id" value="' . $id . '">
    <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code, ENT_QUOTES) . '">

    <div class="container mt-3">
        <div class="row mb-3">
            <div class="col-12 text-end">
                ' . jumpbutton($where) . '
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card fm-panel shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="pid" class="fm-form-label form-label">
                                    <i class="fas fa-folder me-1"></i>
                                    Category
                                </label>
                                ' . $categories . '
                            </div>

                            <div class="col-md-12">
                                <label for="name" class="fm-form-label form-label">
                                    <i class="fas fa-heading me-1"></i>
                                    Title <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="name"
                                       name="name"
                                       value="' . $name . '"
                                       required>
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="fm-form-label form-label">
                                    <i class="fas fa-align-left me-1"></i>
                                    Description
                                </label>
                                <textarea class="form-control"
                                          id="description"
                                          name="description"
                                          rows="8">' . $description . '</textarea>
                            </div>

                            <div class="col-md-3">
                                <label for="disporder" class="fm-form-label form-label">
                                    <i class="fas fa-sort-numeric-up me-1"></i>
                                    Display Order
                                </label>
                                <input type="number"
                                       class="form-control"
                                       id="disporder"
                                       name="disporder"
                                       value="' . $disporder . '"
                                       min="0">
                            </div>

                            <div class="col-12 mt-4">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-2"></i>
                                        Save Child FAQ Item
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-2"></i>
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </form>';
}

function handleEdit(int $id): void
{
    global $db, $lang, $faq_errors, $mybb, $_this_script_;

    if (!is_valid_id($id)) {
        $faq_errors[] = $lang->faq['faqerror'];
        stdhead($lang->faq['faqtitle'], true, '', '');
        render_faqmanage_styles();
        show_faq_errors();
        return;
    }

    if (($_POST['subdo'] ?? '') === 'save') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            $faq_errors[] = 'Security check failed. Please try again.';
        } else {
            $type        = $_POST['type'] ?? 'category';
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $disporder   = (int)($_POST['disporder'] ?? 0);
            $pid         = (int)($_POST['pid'] ?? 0);

            // Старые формы могли присылать type числом (1/2) - конвертируем в enum.
            $type = match ((string)$type) {
                '1' => 'category',
                '2' => 'item',
                default => $type,
            };

            if (empty($name) || ($type === 'item' && empty($description))) {
                $faq_errors[] = 'Please fill all fields!';
            } else {
                $db->sql_query_prepared(
                    "UPDATE faq SET type = ?, name = ?, description = ?, disporder = ?, pid = ? WHERE id = ?",
                    [$type, $name, $description, $disporder, $pid, $id]
                );
                header('Location: ' . $_this_script_);
                exit;
            }
        }
    }

    $firstquery = $db->sql_query_prepared("SELECT * FROM faq WHERE id = ?", [$id]);
    if (!$firstquery || $db->num_rows($firstquery) === 0) {
        $faq_errors[] = $lang->faq['faqerror'];
        stdhead($lang->faq['faqtitle'], true, '', '');
        render_faqmanage_styles();
        show_faq_errors();
        return;
    }

    $editfaq = $db->fetch_array($firstquery);

    stdhead($lang->faq['faqtitle'], true, '', '');
    render_faqmanage_styles();
    show_faq_errors();

    if ((int)$editfaq['type'] === 2 || $editfaq['type'] === 'item') {
        $query2 = $db->sql_query_prepared("SELECT * FROM faq WHERE type = '1' ORDER BY disporder ASC");
        $categories = '
            <div class="col-md-12">
                <label for="pid" class="fm-form-label form-label">
                    <i class="fas fa-folder me-1"></i>
                    Category
                </label>
                <select name="pid" class="form-select">';

        while ($query2 && ($cat = $db->fetch_array($query2))) {
            $selected = ((int)$editfaq['pid'] === (int)$cat['id']) ? ' selected="selected"' : '';
            $categories .= '<option value="' . $cat['id'] . '"' . $selected . '>' . htmlspecialchars_uni($cat['name']) . '</option>';
        }

        $categories .= '</select>
            </div>';
    } else {
        $categories = '<input type="hidden" name="pid" value="' . (int)$editfaq['pid'] . '">';
    }

    $nameValue        = htmlspecialchars_uni($_POST['name'] ?? $editfaq['name']);
    $descriptionValue = isset($_POST['description']) ? htmlspecialchars_uni($_POST['description']) : $editfaq['description'];
    $disporderValue   = (int)($_POST['disporder'] ?? $editfaq['disporder']);

    $where = ['Cancel' => $_this_script_];

    echo '
    <div class="fm-masthead">
        <span class="fm-masthead__eyebrow">Admin / FAQ</span>
        <h1 class="fm-masthead__title"><i class="fas fa-edit me-2"></i>Edit FAQ Item: ' . htmlspecialchars_uni($editfaq['name']) . '</h1>
    </div>
    <form method="post" action="' . $_this_script_ . '">
    <input type="hidden" name="do" value="edit">
    <input type="hidden" name="subdo" value="save">
    <input type="hidden" name="id" value="' . $id . '">
    <input type="hidden" name="type" value="' . htmlspecialchars_uni((string)$editfaq['type']) . '">
    <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code, ENT_QUOTES) . '">

    <div class="container mt-3">
        <div class="row mb-3">
            <div class="col-12 text-end">
                ' . jumpbutton($where) . '
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card fm-panel shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            ' . $categories . '

                            <div class="col-md-12">
                                <label for="name" class="fm-form-label form-label">
                                    <i class="fas fa-heading me-1"></i>
                                    Title <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="name"
                                       name="name"
                                       value="' . $nameValue . '"
                                       required>
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="fm-form-label form-label">
                                    <i class="fas fa-align-left me-1"></i>
                                    Description
                                </label>
                                <textarea class="form-control"
                                          id="description"
                                          name="description"
                                          rows="8">' . $descriptionValue . '</textarea>
                            </div>

                            <div class="col-md-3">
                                <label for="disporder" class="fm-form-label form-label">
                                    <i class="fas fa-sort-numeric-up me-1"></i>
                                    Display Order
                                </label>
                                <input type="number"
                                       class="form-control"
                                       id="disporder"
                                       name="disporder"
                                       value="' . $disporderValue . '"
                                       min="0">
                            </div>

                            <div class="col-12 mt-4">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-2"></i>
                                        Save Changes
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-2"></i>
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </form>';
}

function handleDefault(): void
{
    global $db, $lang, $mybb, $_this_script_;

    stdhead($lang->faq['faqtitle'], true, '', '');
    render_faqmanage_styles();
    show_faq_errors();

    $where = ['Add New FAQ Item' => $_this_script_ . '&do=new'];

    $query = $db->sql_query_prepared("SELECT disporder, id, name FROM faq WHERE type = 'category' ORDER BY disporder ASC");

    if (!$query || $db->num_rows($query) === 0) {
        echo '
        <div class="container mt-3">
            <div class="alert alert-info text-center">
                <h4 class="alert-heading">
                    <i class="fas fa-info-circle me-2"></i>
                    No FAQ Items Found
                </h4>
                <p class="mb-3">There are no FAQ items created yet.</p>
                <a href="' . $_this_script_ . '&amp;do=new" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>
                    Create First FAQ Item
                </a>
            </div>
        </div>';
        return;
    }

    echo '
    <div class="fm-masthead">
        <span class="fm-masthead__eyebrow">Admin / FAQ</span>
        <h1 class="fm-masthead__title">
            <span><i class="fas fa-question-circle me-2"></i>' . $lang->faq['faqtitle'] . '</span>
            <span class="fs-6">' . jumpbutton($where) . '</span>
        </h1>
    </div>
    <div class="container mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card fm-panel shadow-sm">
                    <div class="card-body p-0">
                        <form method="post" action="' . $_this_script_ . '">
                            <input type="hidden" name="do" value="savedisplayorder">
                            <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code, ENT_QUOTES) . '">

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">
                                                <i class="fas fa-heading me-2"></i>
                                                Title
                                            </th>
                                            <th class="text-center" style="width: 150px;">
                                                <i class="fas fa-sort-numeric-up me-2"></i>
                                                Display Order
                                            </th>
                                            <th class="text-center" style="width: 250px;">
                                                <i class="fas fa-cogs me-2"></i>
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>';

    while ($faq = $db->fetch_array($query)) {
        echo '
        <tr>
            <td class="ps-4">
                <a href="' . $_this_script_ . '&amp;do=view&amp;id=' . $faq['id'] . '"
                   class="text-decoration-none text-dark fw-bold">
                    <i class="fas fa-folder text-warning me-2"></i>
                    ' . htmlspecialchars_uni($faq['name']) . '
                </a>
            </td>
            <td class="text-center">
                <input type="number"
                       class="form-control form-control-sm text-center"
                       name="disporder[' . $faq['id'] . ']"
                       value="' . (int)$faq['disporder'] . '"
                       min="0"
                       style="width: 80px;">
            </td>
            <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                    <a href="' . $_this_script_ . '&do=view&amp;id=' . $faq['id'] . '"
                       class="btn btn-outline-info"
                       title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="' . $_this_script_ . '&do=edit&amp;id=' . $faq['id'] . '"
                       class="btn btn-outline-primary"
                       title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="' . $_this_script_ . '&do=add&amp;id=' . $faq['id'] . '"
                       class="btn btn-outline-success"
                       title="Add Child Item">
                        <i class="fas fa-plus"></i>
                    </a>
                    ' . faq_delete_button((int)$faq['id']) . '
                </div>
            </td>
        </tr>';
    }

    echo '
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-center py-3">
                                                <button type="submit" class="btn btn-primary px-4">
                                                    <i class="fas fa-save me-2"></i>
                                                    Save Display Order
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}