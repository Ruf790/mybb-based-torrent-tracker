<?php


declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center"><i class="fa-solid fa-circle-exclamation"></i> <b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

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
    
    public function __construct(private $db, private $lang, private array $usergroups) {}
    
    public function handleDelete(int $id): void
    {
        if ($id > 0) {
            $this->db->delete_query('rules', "id='{$id}'");
            if (function_exists('flash_message')) {
                flash_message($this->lang->modrules['deleted_success'] ?? 'Rule deleted successfully', 'success');
            }
        }
    }
    
    public function handleSave(array $data): bool
    {
        $id = (int)($data['id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $text = trim($data['text'] ?? '');
        
        if (empty($title) || empty($text)) {
            $this->errors[] = $this->lang->modrules['error'] ?? 'Title and text are required';
            return false;
        }
        
        $usergroups = $this->parseUsergroups($data['usergroups'] ?? []);
        
        $ruleData = [
            "title"      => $this->db->escape_string($title),
            "text"       => $this->db->escape_string($text),
            "usergroups" => $this->db->escape_string($usergroups)
        ];
        
        if ($id > 0) {
            $this->db->update_query('rules', $ruleData, "id='{$id}'");
            if (function_exists('flash_message')) {
                flash_message($this->lang->modrules['updated_success'] ?? 'Rule updated successfully', 'success');
            }
        } else {
            $ruleData['created_at'] = TIMENOW;
            $this->db->insert_query('rules', $ruleData);
            if (function_exists('flash_message')) {
                flash_message($this->lang->modrules['created_success'] ?? 'Rule created successfully', 'success');
            }
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
        
        return $validGroups ? implode('', $validGroups) : '[0]';
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function getAllRules(): array
    {
        $rules = [];
        $query = $this->db->simple_select('rules', '*', '', ['order_by' => 'id DESC']);
        
        while ($rule = $this->db->fetch_array($query)) {
            $rules[] = $rule;
        }
        
        return $rules;
    }
    
    public function getUsergroupsPreview(string $usergroupsString): string
    {
        if ($usergroupsString === '[0]') {
            return $this->lang->modrules['all_groups'] ?? 'All User Groups';
        }
        
        $groupNames = [];
        foreach ($this->usergroups as $group) {
            if (strpos($usergroupsString, '[' . $group['id'] . ']') !== false) {
                $groupNames[] = $group['namestyle'];
            }
        }
        
        return implode(', ', $groupNames);
    }
}

// Helper function to get usergroups list
function getUsergroupsList($db): array
{
    $groups = [];
    $query = $db->simple_select('usergroups', 'gid, title, namestyle', "isbannedgroup != '1'");
    
    while ($group = $db->fetch_array($query)) {
        $groups[] = [
            'id'        => (int)$group['gid'],
            'title'     => $group['title'],
            'namestyle' => get_user_color($group['title'], $group['namestyle'])
        ];
    }
    
    return $groups;
}

// Get usergroups
$usergroups2 = getUsergroupsList($db);

// Initialize Rule Manager
$ruleManager = new RuleManager($db, $lang, $usergroups2);

// Process actions
$action = $_GET['do'] ?? $_POST['do'] ?? '';

switch (true) {
    case ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'delete'):
        $id = (int)($_GET['id'] ?? 0);
        $ruleManager->handleDelete($id);
        break;
        
    case ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['save', 'new'])):
        $ruleManager->handleSave($_POST);
        break;
}

// Get data
$rules = $ruleManager->getAllRules();
$errors = $ruleManager->getErrors();

// Display page
stdhead($lang->modrules['title']);
?>

<!-- Main Container -->
<div class="container mt-3">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fa-solid fa-gavel me-2"></i>
                <?= $lang->modrules['title'] ?>
            </h1>
            <p class="text-muted mb-0"><?= $lang->modrules['description'] ?? 'Manage forum rules and regulations' ?></p>
        </div>
        
        <button type="button" class="btn btn-primary" onclick="toggleNewRuleForm()">
            <i class="fa-solid fa-plus me-2"></i>
            <?= $lang->modrules['new'] ?>
        </button>
    </div>
    
    <!-- New Rule Form (Collapsible) -->
    <div class="card shadow-sm mb-4" id="newRuleForm" style="display: none;">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fa-solid fa-file-circle-plus me-2"></i>
                <?= $lang->modrules['new'] ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= $_this_script_ ?>" id="ruleForm">
                <input type="hidden" name="do" value="new">
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <?= htmlspecialchars_uni($errors[0]) ?>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="title" class="form-label">
                            <i class="fa-solid fa-heading me-1"></i>
                            <?= $lang->modrules['title2'] ?>
                        </label>
                        <input type="text" class="form-control form-control-lg" 
                               id="title" name="title" 
                               value="<?= htmlspecialchars_uni($_POST['title'] ?? '') ?>" 
                               required>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label for="text" class="form-label">
                            <i class="fa-solid fa-align-left me-1"></i>
                            <?= $lang->modrules['title3'] ?>
                        </label>
                        <textarea class="form-control" id="text" name="text" 
                                  rows="6" required><?= htmlspecialchars_uni($_POST['text'] ?? '') ?></textarea>
                        <div class="form-text"><?= $lang->modrules['formatting_hint'] ?? 'BBCode and HTML allowed' ?></div>
                    </div>
                    
                    <div class="col-md-12 mb-4">
                        <label class="form-label mb-3">
                            <i class="fa-solid fa-users me-1"></i>
                            <?= $lang->modrules['title4'] ?>
                        </label>
                        
                        <div class="row">
                            <?php foreach ($usergroups2 as $group): ?>
                            <div class="col-md-3 col-sm-4 col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="usergroups[]" 
                                           value="<?= $group['id'] ?>" 
                                           id="group_<?= $group['id'] ?>">
                                    <label class="form-check-label" for="group_<?= $group['id'] ?>">
                                        <?= $group['namestyle'] ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllGroups()">
                                <?= $lang->modrules['select_all'] ?? 'Select All' ?>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllGroups()">
                                <?= $lang->modrules['deselect_all'] ?? 'Deselect All' ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" onclick="toggleNewRuleForm()">
                        <?= $lang->modrules['cancel'] ?? 'Cancel' ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-2"></i>
                        <?= $lang->modrules['save'] ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Rules List -->
    <?php if (empty($rules)): ?>
    <div class="text-center py-5">
        <div class="mb-4">
            <i class="fa-solid fa-clipboard-list fa-4x text-muted"></i>
        </div>
        <h4 class="text-muted mb-3"><?= $lang->modrules['no_rules'] ?? 'No rules found' ?></h4>
        <p class="text-muted"><?= $lang->modrules['create_first'] ?? 'Click the button above to create your first rule' ?></p>
    </div>
    <?php else: ?>
    
    <div class="row" id="rulesList">
        <?php foreach ($rules as $rule): ?>
        <div class="col-md-12 mb-4" id="rule-<?= $rule['id'] ?>">
            <div class="card shadow-sm h-100 rule-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-file-lines me-2"></i>
                        <?= $parser->parse_message($rule['title'], $parser_options) ?>
                    </h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                type="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item text-primary" href="#" 
                                   onclick="editRule(<?= $rule['id'] ?>)">
                                    <i class="fa-solid fa-pen-to-square me-2"></i>
                                    <?= $lang->modrules['edit'] ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="#" 
                                   onclick="deleteRule(<?= $rule['id'] ?>)">
                                    <i class="fa-solid fa-trash-can me-2"></i>
                                    <?= $lang->modrules['delete'] ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="rule-content">
                        <?= $parser->parse_message($rule['text'], $parser_options) ?>
                    </div>
                    
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted">
                            <i class="fa-solid fa-user-group me-1"></i>
                            <?= $ruleManager->getUsergroupsPreview($rule['usergroups']) ?>
                        </small>
                    </div>
                </div>
                
                <!-- Edit Form (Hidden) -->
                <div class="card-footer" id="editForm-<?= $rule['id'] ?>" style="display: none;">
                    <form method="POST" action="<?= $_this_script_ ?>">
                        <input type="hidden" name="do" value="save">
                        <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label"><?= $lang->modrules['title2'] ?></label>
                            <input type="text" class="form-control" name="title" 
                                   value="<?= htmlspecialchars_uni($rule['title']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?= $lang->modrules['title3'] ?></label>
                            <textarea class="form-control" name="text" 
                                      rows="4" required><?= htmlspecialchars_uni($rule['text']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?= $lang->modrules['title4'] ?></label>
                            <div class="row">
                                <?php foreach ($usergroups2 as $group): ?>
                                <div class="col-md-3 col-sm-4 col-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="usergroups[]" 
                                               value="<?= $group['id'] ?>"
                                               <?= preg_match('#\['.$group['id'].'\]#', $rule['usergroups']) ? 'checked' : '' ?>>
                                        <label class="form-check-label">
                                            <?= $group['namestyle'] ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" 
                                    onclick="cancelEdit(<?= $rule['id'] ?>)">
                                <?= $lang->modrules['cancel'] ?? 'Cancel' ?>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <?= $lang->modrules['save'] ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script>
// Toggle new rule form
function toggleNewRuleForm() {
    const form = document.getElementById('newRuleForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Group selection helpers
function selectAllGroups() {
    document.querySelectorAll('input[name="usergroups[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllGroups() {
    document.querySelectorAll('input[name="usergroups[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
}

// Rule management functions
function editRule(ruleId) {
    const ruleCard = document.getElementById(`rule-${ruleId}`);
    const editForm = document.getElementById(`editForm-${ruleId}`);
    const cardBody = ruleCard.querySelector('.card-body');
    
    cardBody.style.display = 'none';
    editForm.style.display = 'block';
}

function cancelEdit(ruleId) {
    const ruleCard = document.getElementById(`rule-${ruleId}`);
    const editForm = document.getElementById(`editForm-${ruleId}`);
    const cardBody = ruleCard.querySelector('.card-body');
    
    editForm.style.display = 'none';
    cardBody.style.display = 'block';
}

function deleteRule(ruleId) {
    if (confirm(`<?= $lang->modrules['confirm'] ?>`)) {
        window.location.href = `<?= $_this_script_ ?>&do=delete&id=${ruleId}`;
    }
}

// Initialize form display if there were errors
<?php if (!empty($errors)): ?>
document.addEventListener('DOMContentLoaded', function() {
    toggleNewRuleForm();
});
<?php endif; ?>
</script>

<!-- CSS Styles -->
<style>
.rule-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.rule-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important;
}

.rule-content {
    line-height: 1.6;
}

.rule-content img {
    max-width: 100%;
    height: auto;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
}

.form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
}
</style>

<?php
stdfoot();