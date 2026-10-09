<?php


declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center"><i class="fa-solid fa-circle-exclamation"></i> <b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

const MR_ASSET_VER = 1;

$lang->load('modrules');
require_once(INC_PATH.'/class_parser.php');

$parser = new postParser();
$parser_options = [
    "allow_html"      => 1,
    "allow_mycode"    => 1,
    "allow_smilies"   => 1,
    "allow_imgcode"   => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

class RuleManager
{
    private array $errors = [];

    public function __construct(private $db, private $lang, private array $usergroups, private string $actor, private int $actorId = 0) {}

    private function l(string $key, string $fallback): string
    {
        return (string)($this->lang->modrules[$key] ?? $fallback);
    }

    private function flash(string $key, string $fallback): void
    {
        if (function_exists('flash_message')) {
            flash_message($this->l($key, $fallback), 'success');
        }
    }

    public function addError(string $msg): void
    {
        $this->errors[] = $msg;
    }

    public function handleDelete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $title = '';
        $q = $this->db->sql_query_prepared("SELECT title FROM rules WHERE id = ? LIMIT 1", [$id]);
        if ($q && ($row = $this->db->fetch_array($q))) {
            $title = (string)$row['title'];
        } else {
            return false;
        }

        $this->db->sql_query_prepared("DELETE FROM rules WHERE id = ?", [$id]);
        write_log("Rule #{$id} ({$title}) was deleted by {$this->actor}");
        $this->flash('deleted_success', 'Rule deleted successfully');
        return true;
    }

    public function handleSave(array $data): bool
    {
        $id    = (int)($data['id'] ?? 0);
        $title = trim((string)($data['title'] ?? ''));
        $text  = trim((string)($data['text'] ?? ''));
        // Russian version is optional: empty => NULL => the English one is shown
        $titleRu = trim((string)($data['title_ru'] ?? ''));
        $textRu  = trim((string)($data['text_ru'] ?? ''));

        if ($title === '' || $text === '') {
            $this->errors[] = $this->l('error', 'Title and text are required');
            return false;
        }

        $groups = $data['usergroups'] ?? [];
        $ruleData = [
            "title"      => $title,
            "text"       => $text,
            "title_ru"   => $titleRu !== '' ? mb_substr($titleRu, 0, 255) : null,
            "text_ru"    => $textRu !== '' ? $textRu : null,
            "usergroups" => $this->parseUsergroups(is_array($groups) ? $groups : []),
            "last_updated_by" => $this->actorId > 0 ? $this->actorId : null,
        ];

        if ($id > 0) {
            $set      = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($ruleData)));
            $params   = array_values($ruleData);
            $params[] = $id;
            $this->db->sql_query_prepared("UPDATE rules SET {$set} WHERE id = ?", $params);
            write_log("Rule #{$id} ({$title}) was updated by {$this->actor}");
            $this->flash('updated_success', 'Rule updated successfully');
        } else {
            $columns      = array_keys($ruleData);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $this->db->sql_query_prepared(
                "INSERT INTO rules (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                array_values($ruleData)
            );
            write_log("Rule ({$title}) was created by {$this->actor}");
            $this->flash('created_success', 'Rule created successfully');
        }

        return true;
    }

    private function parseUsergroups(array $usergroups): string
    {
        $validGroups = [];
        foreach ($usergroups as $group) {
            if (is_valid_id($group)) {
                $validGroups[] = '[' . (int)$group . ']';
            }
        }

        return $validGroups ? implode('', array_unique($validGroups)) : '[0]';
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getAllRules(): array
    {
        $rules = [];
        $query = $this->db->sql_query_prepared("SELECT * FROM rules ORDER BY id DESC");

        while ($query && ($rule = $this->db->fetch_array($query))) {
            $rules[] = $rule;
        }

        return $rules;
    }

    public static function isForAll(string $usergroupsString): bool
    {
        return $usergroupsString === '' || $usergroupsString === '[0]';
    }

    public static function hasGroup(string $usergroupsString, int $gid): bool
    {
        return str_contains($usergroupsString, '[' . $gid . ']');
    }

    /** @return string[] formatted group names (HTML) */
    public function getRuleGroups(string $usergroupsString): array
    {
        $names = [];
        foreach ($this->usergroups as $group) {
            if (self::hasGroup($usergroupsString, $group['id'])) {
                $names[] = $group['namestyle'];
            }
        }
        return $names;
    }
}

// created_at is a MySQL TIMESTAMP ('Y-m-d H:i:s'); also accepts a unix int
function mr_ts(mixed $v): int
{
    if (is_int($v) || (is_string($v) && ctype_digit($v))) {
        return (int)$v;
    }
    $t = is_string($v) && $v !== '' ? strtotime($v) : false;
    return $t === false ? 0 : $t;
}

// Helper function to get usergroups list
function getUsergroupsList($db): array
{
    $groups = [];
    $query = $db->sql_query_prepared("SELECT gid, title, namestyle FROM usergroups WHERE isbannedgroup != '1'");

    while ($query && ($group = $db->fetch_array($query))) {
        $groups[] = [
            'id'        => (int)$group['gid'],
            'title'     => (string)$group['title'],
            'namestyle' => format_name(htmlspecialchars_uni((string)$group['title']), $group['namestyle'])
        ];
    }

    return $groups;
}

// Renders the usergroup checkbox grid for a form
function mr_group_picker(array $groups, string $selected, string $prefix): string
{
    $out = '<div class="mr-groups">';
    foreach ($groups as $g) {
        $gid     = (int)$g['id'];
        $inputId = $prefix . '_g' . $gid;
        $checked = RuleManager::hasGroup($selected, $gid) ? ' checked' : '';
        $out .= '<label class="mr-group" for="' . $inputId . '">'
              . '<input class="form-check-input" type="checkbox" name="usergroups[]" value="' . $gid . '" id="' . $inputId . '"' . $checked . '>'
              . '<span class="mr-group-name">' . $g['namestyle'] . '</span>'
              . '</label>';
    }
    return $out . '</div>';
}

$L = static fn(string $key, string $fallback): string => (string)($lang->modrules[$key] ?? $fallback);

// Renders the optional Russian title/text block for a form
function mr_ru_fields(callable $L, string $prefix, string $titleRu, string $textRu, bool $big = false): string
{
    $h = static fn(string $v): string => htmlspecialchars_uni($v);
    return '<div class="border rounded-3 p-3 mb-3 bg-body-tertiary">'
         . '<div class="d-flex flex-wrap align-items-center gap-2 mb-2">'
         . '<span class="badge text-bg-secondary">RU</span>'
         . '<b>' . $h($L('sec_ru_version', 'Russian version')) . '</b>'
         . '<span class="form-text m-0"><i class="fa-solid fa-circle-info me-1"></i>' . $h($L('hint_ru_fallback', 'Optional. Leave empty and the English version is shown.')) . '</span>'
         . '</div>'
         . '<div class="mb-3">'
         . '<label for="' . $prefix . '_title_ru" class="form-label"><i class="fa-solid fa-heading me-1"></i> ' . $h($L('lbl_title_ru', 'Title (Russian)')) . '</label>'
         . '<input type="text" class="form-control' . ($big ? ' form-control-lg' : '') . '" id="' . $prefix . '_title_ru" name="title_ru" maxlength="255" lang="ru" value="' . $h($titleRu) . '">'
         . '</div>'
         . '<div>'
         . '<label for="' . $prefix . '_text_ru" class="form-label"><i class="fa-solid fa-align-left me-1"></i> ' . $h($L('lbl_text_ru', 'Text (Russian)')) . '</label>'
         . '<textarea class="form-control" id="' . $prefix . '_text_ru" name="text_ru" rows="8" lang="ru">' . $h($textRu) . '</textarea>'
         . '</div>'
         . '</div>';
}

$usergroups2 = getUsergroupsList($db);
$actor       = htmlspecialchars_uni((string)($CURUSER['username'] ?? 'unknown'));
$ruleManager = new RuleManager($db, $lang, $usergroups2, $actor, (int)($CURUSER['id'] ?? 0));

$selfUrl  = (string)$_this_script_;
$redirect = html_entity_decode($selfUrl, ENT_QUOTES);

// ---------- Actions (POST only, PRG) ----------
$openEditId = 0;
$openNew    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['do'] ?? '');

    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        $ruleManager->addError($L('invalid_token', 'Invalid security token. Please reload the page and try again.'));
    } else {
        $ok = match ($action) {
            'delete'      => $ruleManager->handleDelete((int)($_POST['id'] ?? 0)),
            'save', 'new' => $ruleManager->handleSave($_POST),
            default       => false,
        };

        if ($ok) {
            header('Location: ' . $redirect);
            exit;
        }

        if ($action === 'save') {
            $openEditId = (int)($_POST['id'] ?? 0);
        } elseif ($action === 'new') {
            $openNew = true;
        }
    }
}

// ---------- Data ----------
$rules  = $ruleManager->getAllRules();
$errors = $ruleManager->getErrors();

$statTotal  = count($rules);
$statAll    = 0;
$lastCreate = 0;
foreach ($rules as $r) {
    if (RuleManager::isForAll((string)$r['usergroups'])) {
        $statAll++;
    }
    $lastCreate = max($lastCreate, mr_ts($r['created_at'] ?? null));
}
$statNoRu = 0;
foreach ($rules as $r) {
    if (trim((string)($r['title_ru'] ?? '')) === '' || trim((string)($r['text_ru'] ?? '')) === '') {
        $statNoRu++;
    }
}
$statLimited = $statTotal - $statAll;
$lastText    = $lastCreate > 0 ? date('d.m.Y', $lastCreate) : '—';

$postKey = htmlspecialchars_uni(generate_post_check());
$newVals = $openNew ? $_POST : [];
$newSel  = $openNew ? implode('', array_map(fn($g) => '[' . (int)$g . ']', (array)($_POST['usergroups'] ?? []))) : '';

// ---------- Output ----------
stdhead($lang->modrules['title']);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/modrules.css?ver=<?= MR_ASSET_VER ?>">

<div class="container mt-3 mb-4 mr"
     data-confirm-title="<?= htmlspecialchars_uni($L('confirm_title', 'Delete this rule?')) ?>"
     data-confirm-text="<?= htmlspecialchars_uni($L('confirm', 'This action cannot be undone.')) ?>"
     data-confirm-yes="<?= htmlspecialchars_uni($L('delete', 'Delete')) ?>"
     data-confirm-no="<?= htmlspecialchars_uni($L('cancel', 'Cancel')) ?>"
     data-open-edit="<?= $openEditId ?>"
     data-open-new="<?= $openNew ? '1' : '0' ?>">

    <!-- Header -->
    <div class="card mr-head mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="mr-head-icon"><i class="fa-solid fa-gavel"></i></div>
            <div class="flex-grow-1">
                <h1 class="mr-title"><?= $lang->modrules['title'] ?></h1>
                <p class="mr-sub"><?= $L('description', 'Manage forum rules and regulations') ?></p>
            </div>
            <button type="button" class="btn btn-primary mr-pill" data-mr="toggle-new">
                <i class="fa-solid fa-plus me-1"></i> <?= $lang->modrules['new'] ?>
            </button>
        </div>
    </div>

    <!-- KPI -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="mr-kpi mr-kpi--primary">
                <i class="fa-solid fa-scroll"></i>
                <div><b><?= $statTotal ?></b><span><?= $L('kpi_total', 'Total rules') ?></span></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mr-kpi mr-kpi--success">
                <i class="fa-solid fa-earth-europe"></i>
                <div><b><?= $statAll ?></b><span><?= $L('kpi_all', 'For all groups') ?></span></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mr-kpi mr-kpi--warning">
                <i class="fa-solid fa-user-lock"></i>
                <div><b><?= $statLimited ?></b><span><?= $L('kpi_limited', 'Group-restricted') ?></span></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mr-kpi mr-kpi--info">
                <i class="fa-solid fa-calendar-plus"></i>
                <div><b><?= $lastText ?></b><span><?= $L('kpi_last', 'Last added') ?></span></div>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger mr-alert d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span><?= htmlspecialchars_uni($errors[0]) ?></span>
    </div>
    <?php endif; ?>

    <!-- New Rule Form -->
    <div class="card mr-form-card mb-3" id="newRuleForm" hidden>
        <div class="card-header d-flex align-items-center gap-2">
            <span class="mr-chip-icon mr-chip-icon--primary"><i class="fa-solid fa-file-circle-plus"></i></span>
            <b><?= $lang->modrules['new'] ?></b>
        </div>
        <form method="POST" action="<?= $selfUrl ?>" id="ruleForm" class="mr-form">
            <input type="hidden" name="do" value="new">
            <input type="hidden" name="my_post_key" value="<?= $postKey ?>">

            <div class="card-body">
                <div class="mb-3">
                    <label for="title" class="form-label"><i class="fa-solid fa-heading me-1"></i> <?= $lang->modrules['title2'] ?></label>
                    <input type="text" class="form-control form-control-lg" id="title" name="title"
                           value="<?= htmlspecialchars_uni((string)($newVals['title'] ?? '')) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="text" class="form-label"><i class="fa-solid fa-align-left me-1"></i> <?= $lang->modrules['title3'] ?></label>
                    <textarea class="form-control" id="text" name="text" rows="8" required><?= htmlspecialchars_uni((string)($newVals['text'] ?? '')) ?></textarea>
                    <div class="form-text"><i class="fa-solid fa-code me-1"></i> <?= $L('formatting_hint', 'BBCode and HTML allowed') ?></div>
                </div>

                <?= mr_ru_fields($L, 'new', (string)($newVals['title_ru'] ?? ''), (string)($newVals['text_ru'] ?? ''), true) ?>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <label class="form-label mb-0"><i class="fa-solid fa-users me-1"></i> <?= $lang->modrules['title4'] ?></label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary mr-pill" data-mr="groups-all">
                            <i class="fa-solid fa-check-double me-1"></i> <?= $L('select_all', 'Select All') ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary mr-pill" data-mr="groups-none">
                            <i class="fa-solid fa-eraser me-1"></i> <?= $L('deselect_all', 'Deselect All') ?>
                        </button>
                    </div>
                </div>
                <?= mr_group_picker($usergroups2, $newSel, 'new') ?>
                <div class="form-text mt-2"><i class="fa-solid fa-circle-info me-1"></i> <?= $L('groups_hint', 'Nothing selected = rule is shown to all groups') ?></div>
            </div>

            <div class="mr-actions">
                <button type="button" class="btn btn-outline-secondary mr-pill" data-mr="toggle-new">
                    <i class="fa-solid fa-xmark me-1"></i> <?= $L('cancel', 'Cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary mr-pill">
                    <i class="fa-solid fa-floppy-disk me-1"></i> <?= $lang->modrules['save'] ?>
                </button>
            </div>
        </form>
    </div>

    <?php if (empty($rules)): ?>
    <!-- Empty state -->
    <div class="card mr-empty">
        <div class="card-body text-center py-5">
            <div class="mr-empty-icon"><i class="fa-solid fa-clipboard-list"></i></div>
            <h4 class="mt-3 mb-2"><?= $L('no_rules', 'No rules found') ?></h4>
            <p class="text-body-secondary mb-3"><?= $L('create_first', 'Click the button above to create your first rule') ?></p>
            <button type="button" class="btn btn-primary mr-pill" data-mr="toggle-new">
                <i class="fa-solid fa-plus me-1"></i> <?= $lang->modrules['new'] ?>
            </button>
        </div>
    </div>
    <?php else: ?>

    <!-- Search -->
    <div class="mr-search mb-3">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" class="form-control" id="mrSearch" placeholder="<?= htmlspecialchars_uni($L('search', 'Search rules…')) ?>" autocomplete="off">
    </div>
    <?php if ($statNoRu > 0): ?>
    <div class="alert alert-warning mr-alert d-flex align-items-center gap-2">
        <i class="fa-solid fa-language"></i>
        <span><?= htmlspecialchars_uni(str_replace(['{1}', '%1$s'], (string)$statNoRu, $L('flash_ru_missing', 'Rules without a Russian version: {1}. The English text is shown for them.'))) ?></span>
    </div>
    <?php endif; ?>
    <div class="mr-nothing text-body-secondary text-center py-4" id="mrNothing" hidden>
        <i class="fa-solid fa-filter-circle-xmark me-1"></i> <?= $L('search_empty', 'No rules match your search') ?>
    </div>

    <!-- Rules List -->
    <div id="rulesList">
        <?php foreach ($rules as $rule):
            $rid     = (int)$rule['id'];
            $ugStr   = (string)$rule['usergroups'];
            $forAll  = RuleManager::isForAll($ugStr);
            $rGroups = $forAll ? [] : $ruleManager->getRuleGroups($ugStr);
            $created = mr_ts($rule['created_at'] ?? null);
            $titleRu = (string)($rule['title_ru'] ?? '');
            $textRu  = (string)($rule['text_ru'] ?? '');
            $hasRu   = trim($titleRu) !== '' && trim($textRu) !== '';
        ?>
        <div class="card mr-rule <?= $forAll ? 'mr-rule--all' : 'mr-rule--limited' ?> mb-3" id="rule-<?= $rid ?>"
             data-search="<?= htmlspecialchars_uni(mb_strtolower($rule['title'] . ' ' . strip_tags((string)$rule['text']) . ' ' . $titleRu . ' ' . strip_tags($textRu))) ?>">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="mr-chip-icon <?= $forAll ? 'mr-chip-icon--success' : 'mr-chip-icon--warning' ?>">
                    <i class="fa-solid <?= $forAll ? 'fa-book-open' : 'fa-lock' ?>"></i>
                </span>
                <h5 class="mr-rule-title flex-grow-1"><?= $parser->parse_message($rule['title'], $parser_options) ?></h5>
                <?php if ($hasRu): ?>
                <span class="badge rounded-pill text-bg-success" title="<?= htmlspecialchars_uni($L('tip_ru_ok', 'Russian version is filled in')) ?>"><i class="fa-solid fa-language me-1"></i>RU</span>
                <?php else: ?>
                <span class="badge rounded-pill text-bg-warning" title="<?= htmlspecialchars_uni($L('tip_ru_missing', 'No Russian version — the English one is shown')) ?>"><i class="fa-solid fa-language me-1"></i>RU —</span>
                <?php endif; ?>
                <span class="mr-id">#<?= $rid ?></span>
                <div class="mr-tools">
                    <button type="button" class="btn btn-sm mr-tool mr-tool--edit" data-mr="edit" data-id="<?= $rid ?>" title="<?= htmlspecialchars_uni($lang->modrules['edit']) ?>">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm mr-tool mr-tool--del" data-mr="delete" data-id="<?= $rid ?>" title="<?= htmlspecialchars_uni($lang->modrules['delete']) ?>">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>

            <div class="card-body mr-rule-body">
                <div class="rule-content">
                    <?= $parser->parse_message($rule['text'], $parser_options) ?>
                </div>

                <div class="mr-meta">
                    <span class="mr-meta-label"><i class="fa-solid fa-eye me-1"></i> <?= $lang->modrules['title4'] ?>:</span>
                    <?php if ($forAll): ?>
                        <span class="mr-badge mr-badge--success"><i class="fa-solid fa-earth-europe me-1"></i> <?= $L('all_groups', 'All User Groups') ?></span>
                    <?php else: foreach ($rGroups as $gName): ?>
                        <span class="mr-badge"><i class="fa-solid fa-user-shield me-1"></i> <?= $gName ?></span>
                    <?php endforeach; endif; ?>
                    <?php if ($created > 0): ?>
                        <span class="mr-date ms-auto"><i class="fa-regular fa-clock me-1"></i> <?= date('d.m.Y H:i', $created) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Edit Form (Hidden) -->
            <div class="mr-edit" id="editForm-<?= $rid ?>" hidden>
                <form method="POST" action="<?= $selfUrl ?>" class="mr-form">
                    <input type="hidden" name="do" value="save">
                    <input type="hidden" name="id" value="<?= $rid ?>">
                    <input type="hidden" name="my_post_key" value="<?= $postKey ?>">

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="e<?= $rid ?>_title"><i class="fa-solid fa-heading me-1"></i> <?= $lang->modrules['title2'] ?></label>
                            <input type="text" class="form-control" id="e<?= $rid ?>_title" name="title"
                                   value="<?= htmlspecialchars_uni($rule['title']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="e<?= $rid ?>_text"><i class="fa-solid fa-align-left me-1"></i> <?= $lang->modrules['title3'] ?></label>
                            <textarea class="form-control" id="e<?= $rid ?>_text" name="text" rows="8" required><?= htmlspecialchars_uni($rule['text']) ?></textarea>
                        </div>

                        <?= mr_ru_fields($L, 'e' . $rid, $titleRu, $textRu) ?>

                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <label class="form-label mb-0"><i class="fa-solid fa-users me-1"></i> <?= $lang->modrules['title4'] ?></label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary mr-pill" data-mr="groups-all">
                                    <i class="fa-solid fa-check-double me-1"></i> <?= $L('select_all', 'Select All') ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary mr-pill" data-mr="groups-none">
                                    <i class="fa-solid fa-eraser me-1"></i> <?= $L('deselect_all', 'Deselect All') ?>
                                </button>
                            </div>
                        </div>
                        <?= mr_group_picker($usergroups2, $ugStr, 'e' . $rid) ?>
                    </div>

                    <div class="mr-actions">
                        <button type="button" class="btn btn-outline-secondary mr-pill" data-mr="cancel-edit" data-id="<?= $rid ?>">
                            <i class="fa-solid fa-xmark me-1"></i> <?= $L('cancel', 'Cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary mr-pill">
                            <i class="fa-solid fa-floppy-disk me-1"></i> <?= $lang->modrules['save'] ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Delete (POST + CSRF) -->
    <form method="POST" action="<?= $selfUrl ?>" id="mrDeleteForm" hidden>
        <input type="hidden" name="do" value="delete">
        <input type="hidden" name="id" value="0">
        <input type="hidden" name="my_post_key" value="<?= $postKey ?>">
    </form>
</div>

<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/modrules.js?ver=<?= MR_ASSET_VER ?>"></script>

<?php
stdfoot();