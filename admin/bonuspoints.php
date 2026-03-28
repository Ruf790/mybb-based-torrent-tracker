<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger text-center"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

require_once INC_PATH . '/functions_multipage.php';



function stderr222(string $error = "", string $title = "", string $type = "error", bool $show_navigation = true, bool $custom_layout = false): void
{
    global $SITENAME, $BASEURL, $header, $footer, $theme, $headerinclude, $db, $templates, $lang, $mybb, $plugins;

    if(!$error) {
        $error = 'unknown_error';
    }

    if(!$title) {
        $title = $SITENAME;
    }

    $timenow = my_datee('relative', TIMENOW);
    stdhead();

    // Если используется кастомный layout, просто выводим переданный контент
    if ($custom_layout) {
        echo $error;
        stdfoot();
        exit;
    }

    // Определяем стили в зависимости от типа сообщения
    $icon = '';
    $header_class = '';
    $alert_class = 'alert-danger';
    
    switch($type) {
        case 'success':
            $icon = 'bi-check-circle-fill';
            $header_class = 'success';
            $alert_class = 'alert-success';
            break;
        case 'warning':
            $icon = 'bi-exclamation-triangle-fill';
            $header_class = 'warning';
            $alert_class = 'alert-warning';
            break;
        case 'info':
            $icon = 'bi-info-circle-fill';
            $header_class = 'info';
            $alert_class = 'alert-info';
            break;
        case 'confirm':
            $icon = 'bi-shield-exclamation';
            $header_class = 'warning';
            $alert_class = 'alert-warning';
            break;
        default: // error
            $icon = 'bi-exclamation-triangle-fill';
            $header_class = 'error';
            $alert_class = 'alert-danger';
            break;
    }

    // Навигационные кнопки
    $navigation_buttons = '';
    if ($show_navigation) {
        $navigation_buttons = '
        <div class="d-flex flex-column flex-sm-row gap-3 mt-3">
            <button onclick="history.back()" class="btn btn-outline-secondary flex-grow-1">
                <i class="bi bi-arrow-left me-2"></i> Go Back
            </button>
            <a href="'.$BASEURL.'/" class="btn btn-primary flex-grow-1">
                <i class="bi bi-house me-2"></i> Home Page
            </a>
        </div>';
    }

    // Для подтверждающих сообщений используем специальный layout
    if ($type === 'confirm') {
        $errorpage = '
<html>
<head>
  <title>'.$title.'</title>
  <link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
  <link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-3">
        <div class="card error-card">
            <div class="card-header22 '.$header_class.'">
                <i class="bi '.$icon.' me-2" style="font-size:2rem;"></i>
                <div>
                    <h2 class="mb-0">'.ucfirst($type).'</h2>
                    <p class="mb-0 opacity-75">Confirmation required</p>
                </div>
            </div>
            <div class="card-body text-center">
                '.$error.'
            </div>
        </div>
    </div>
</body>
</html>';
    } else {
        $errorpage = '
<html>
<head>
  <title>'.$title.'</title>
  <link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
  <link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-3">
        <div class="card error-card">
            <div class="card-header22 '.$header_class.'">
                <i class="bi '.$icon.' me-2" style="font-size:2rem;"></i>
                <div>
                    <h2 class="mb-0">'.ucfirst($type).'</h2>
                    <p class="mb-0 opacity-75">'.($type == 'success' ? 'Operation completed successfully' : 'A problem occurred while processing your request').'</p>
                </div>
            </div>
            <div class="card-body">
                <div class="alert '.$alert_class.'" role="alert">
                    '.$error.'
                </div>
                '.$navigation_buttons.'
            </div>
        </div>
    </div>
</body>
</html>';
    }

    echo $errorpage;
    stdfoot();
    exit;
}






final class BonusPointsManager
{
    private const BS_VERSION = 'v0.3 by xam';
    private const ALLOWED_ACTIONS = [
        'showlist', 'edituser', 'updateuser', 'updatebonussystem', 
        'updatebonussystemsave', 'adminpanel', 'add', 'add_save', 'resetall', 'reset'
    ];

    public function __construct(
        private object $db,
        private string $baseUrl,
        private int $perPage = 20
    ) {}

    public function handleRequest(): void
    {
        $action = $this->getValidatedAction();
        $this->executeAction($action);
    }

    private function getValidatedAction(): string
    {
        $action = $_POST['action'] ?? $_GET['action'] ?? 'adminpanel';
        $action = htmlspecialchars((string)$action);
        
        return in_array($action, self::ALLOWED_ACTIONS) ? $action : 'adminpanel';
    }

    private function executeAction(string $action): void
    {
        match ($action) {
            'showlist' => $this->showUserList(),
            'edituser' => $this->editUser(),
            'updateuser' => $this->updateUser(),
            'updatebonussystem' => $this->updateBonusSystem(),
            'updatebonussystemsave' => $this->updateBonusSystemSave(),
            'adminpanel' => $this->showAdminPanel(),
            'add' => $this->addBonus(),
            'add_save' => $this->addBonusSave(),
            'resetall' => $this->resetAllPoints(),
            'reset' => $this->resetPointsForm(),
            default => $this->showAdminPanel()
        };
    }

    private function showUserList(): void
    {
        stdhead('Bonus Points ' . self::BS_VERSION . ' - User List');
        
        $totalUsers = $this->getTotalUsersWithBonus();
        $pagination = $this->getPagination($totalUsers);
        
        echo $this->renderHeader('User List', 'bi-people-fill');
        echo $pagination;
        echo $this->renderUserTable();
        echo $pagination;
        echo $this->renderNavigation();
        
        stdfoot();
    }

    private function getTotalUsersWithBonus(): int
    {
        $result = $this->db->sql_query("SELECT COUNT(*) as total FROM users WHERE seedbonus > 0");
        $row = $this->db->fetch_array($result);
        return (int)($row['total'] ?? 0);
    }

    private function getPagination(int $totalItems): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $start = ($page - 1) * $this->perPage;
        $totalPages = max(1, ceil($totalItems / $this->perPage));
        
        $pageUrl = $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=showlist&';
        return multipage($totalItems, $this->perPage, $page, $pageUrl);
    }

    private function renderUserTable(): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $start = ($page - 1) * $this->perPage;
        
        $result = $this->db->sql_query(
            "SELECT id, username, seedbonus, bonuscomment, uploaded 
             FROM users 
             WHERE seedbonus > 0 
             ORDER BY seedbonus DESC 
             LIMIT {$start}, {$this->perPage}"
        );

        $rows = '';
        while ($user = $this->db->fetch_array($result)) {
            $profileLink = $this->baseUrl . '/' . get_profile_link($user['id']);
            $uploadedSize = mksize($user['uploaded']);
            
            $rows .= <<<HTML
                <tr>
                    <td class="text-center">
                        <a href="{$profileLink}" class="text-decoration-none">
                            <i class="bi bi-person-badge me-1 text-muted"></i>{$user['id']}
                        </a>
                    </td>
                    <td class="text-center">
                        <a href="{$profileLink}" class="text-decoration-none fw-bold">
                            <i class="bi bi-person-circle me-1 text-primary"></i>{$user['username']}
                        </a>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success fs-6">
                            <i class="bi bi-coin me-1"></i>{$user['seedbonus']} points
                        </span>
                    </td>
                    <td>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">
                                <i class="bi bi-chat-text"></i>
                            </span>
                            <textarea class="form-control" rows="2" readonly>{$user['bonuscomment']}</textarea>
                        </div>
                    </td>
                    <td class="text-end">
                        <i class="bi bi-cloud-upload text-info me-1"></i>{$uploadedSize}
                    </td>
                    <td class="text-center">
                        <a href="{$_SERVER['SCRIPT_NAME']}?act=bonuspoints&action=edituser&id={$user['id']}" 
                           class="btn btn-sm btn-primary">
                           <i class="bi bi-pencil-square me-1"></i>Edit
                        </a>
                    </td>
                </tr>
            HTML;
        }

        return <<<HTML
    <div class="container mt-3">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-people-fill me-2"></i>Users with Bonus Points
                    <span class="badge bg-light text-primary ms-2 fs-6">
                        <i class="bi bi-coin me-1"></i>{$this->getTotalUsersWithBonus()} Users
                    </span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-primary">
                            <tr>
                                <th class="text-center">
                                    <i class="bi bi-hash me-1"></i>User ID
                                </th>
                                <th class="text-center">
                                    <i class="bi bi-person me-1"></i>Username
                                </th>
                                <th class="text-center">
                                    <i class="bi bi-coin me-1"></i>Bonus Points
                                </th>
                                <th class="text-center">
                                    <i class="bi bi-chat-text me-1"></i>Bonus Comment
                                </th>
                                <th class="text-center">
                                    <i class="bi bi-cloud-upload me-1"></i>Total Uploaded
                                </th>
                                <th class="text-center">
                                    <i class="bi bi-gear me-1"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>{$rows}</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
HTML;
    }

    private function editUser(): void
    {
        $userId = $this->getValidatedUserId();
        $user = $this->getUserById($userId);
        
        if (!$user) {
            stderr('Error', '<i class="bi bi-exclamation-triangle-fill me-2"></i>User not found!');
            return;
        }

        stdhead('Bonus Points ' . self::BS_VERSION . " - Edit User ({$user['username']})");
        
        echo '<div class="container-fluid mt-3">';
        echo $this->renderHeader("Edit User: {$user['username']}", 'bi-person-gear');
        echo $this->renderEditUserForm($user);
        echo $this->renderNavigation();
        echo '</div>';
        
        stdfoot();
    }

    private function renderEditUserForm(array $user): string
    {
        $profileLink = $this->baseUrl . '/userdetails.php?id=' . $user['id'];
        
        return <<<HTML
    <div class="container mt-3">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-person-gear me-2"></i>Edit User Points
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="{$_SERVER['SCRIPT_NAME']}">
                    <input type="hidden" name="act" value="bonuspoints">
                    <input type="hidden" name="action" value="updateuser">
                    <input type="hidden" name="id" value="{$user['id']}">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-person-badge me-1"></i>User ID
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="bi bi-hash"></i>
                                    </span>
                                    <div class="form-control">
                                        <a href="{$profileLink}" class="text-decoration-none fw-bold">
                                            {$user['id']}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-person-circle me-1"></i>Username
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="bi bi-at"></i>
                                    </span>
                                    <div class="form-control">
                                        <a href="{$profileLink}" class="text-decoration-none fw-bold">
                                            {$user['username']}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="seedbonus" class="form-label fw-bold">
                                    <i class="bi bi-coin me-1"></i>Bonus Points
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="bi bi-coin"></i>
                                    </span>
                                    <input type="number" class="form-control form-control-lg" 
                                           id="seedbonus" name="seedbonus" value="{$user['seedbonus']}" 
                                           min="0" step="0.1" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-check-circle me-2"></i>Update Points
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
HTML;
		
		
    }

    private function updateUser(): void
{
    $userId = $this->getValidatedUserId();
    $bonusPoints = (float)$_POST['seedbonus'];
    
    $result = $this->db->sql_query(
        "UPDATE users SET seedbonus = " . $this->db->sqlesc($bonusPoints) . 
        " WHERE id = " . $this->db->sqlesc($userId)
    );
    
    if ($result) {
        stderr(
            'User ID: ' . $userId . ' successfully updated',
            'Success',
            200,  // Код успеха
            'general'
        );
    } else {
        stderr(
            'Unable to update user: ' . $userId,
            'Error',
            500,  // Код ошибки сервера
            'general'
        );
    }
}

    private function updateBonusSystem(): void
    {
        $bonusId = $this->getValidatedBonusId();
        $bonus = $this->getBonusById($bonusId);
        
        if (!$bonus) {
            stderr('Error', '<i class="bi bi-exclamation-triangle-fill me-2"></i>Bonus item not found!');
            return;
        }

        stdhead('Bonus Points ' . self::BS_VERSION . " - Update Bonus ({$bonus['bonusname']})");
        
        echo '<div class="container-fluid mt-3">';
        echo $this->renderHeader("Update Bonus: {$bonus['bonusname']}", 'bi-pencil-square');
        echo $this->renderUpdateBonusForm($bonus);
        echo $this->renderNavigation();
        echo '</div>';
        
        stdfoot();
    }

    private function renderUpdateBonusForm(array $bonus): string
    {
        
		return <<<HTML
    <div class="container mt-3">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pencil-square me-2"></i>Update Bonus Item
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="{$_SERVER['SCRIPT_NAME']}">
                    <input type="hidden" name="act" value="bonuspoints">
                    <input type="hidden" name="action" value="updatebonussystemsave">
                    <input type="hidden" name="id" value="{$bonus['id']}">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bonusname" class="form-label fw-bold">
                                    <i class="bi bi-tag me-1"></i>Bonus Name
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="bi bi-tag"></i>
                                    </span>
                                    <input type="text" class="form-control" id="bonusname" name="bonusname" 
                                           value="{$bonus['bonusname']}" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="points" class="form-label fw-bold">
                                    <i class="bi bi-coin me-1"></i>Points
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="bi bi-coin"></i>
                                    </span>
                                    <input type="number" class="form-control" id="points" name="points" 
                                           value="{$bonus['points']}" min="0" step="0.1" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">
                            <i class="bi bi-text-paragraph me-1"></i>Description
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white">
                                <i class="bi bi-chat-text"></i>
                            </span>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="4" required>{$bonus['description']}</textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="menge" class="form-label fw-bold">
                            <i class="bi bi-hdd me-1"></i>Amount (in bytes)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white">
                                <i class="bi bi-hdd"></i>
                            </span>
                            <input type="number" class="form-control" id="menge" name="menge" 
                                   value="{$bonus['menge']}">
                        </div>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i><strong>Common values:</strong><br>
                            <i class="bi bi-arrow-right me-2"></i>1 GB = 1073741824<br>
                            <i class="bi bi-arrow-right me-2"></i>2.5 GB = 2684354560<br>
                            <i class="bi bi-arrow-right me-2"></i>5 GB = 5368709120<br>
                            <i class="bi bi-arrow-right me-2"></i>10 GB = 10737418240
                        </div>
                    </div>
                    
                    <div class="alert alert-warning border-warning">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="delete" value="1" id="deleteCheck">
                            <label class="form-check-label fw-bold text-danger" for="deleteCheck">
                                <i class="bi bi-trash-fill me-1"></i>Delete this bonus item permanently
                            </label>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg me-3 px-4">
                            <i class="bi bi-check-circle me-2"></i>Update Bonus
                        </button>
                        <a href="{$_SERVER['SCRIPT_NAME']}?act=bonuspoints" class="btn btn-secondary btn-lg px-4">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
HTML;
		
		
		
    
	
	}







private function updateBonusSystemSave(): void
{
    $bonusId = $this->getValidatedBonusId();
    $delete = isset($_POST['delete']) && $_POST['delete'] == '1';
    $sure = isset($_GET['sure']) ? (int)$_GET['sure'] : 0;

    if ($delete || $sure) {
        if (!$sure) {
            $confirmUrl = $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=updatebonussystemsave&id=' . $bonusId . '&sure=1';
            stderr222(
                "<div class='text-center'>
                    <i class='bi bi-exclamation-triangle-fill text-warning display-4 mb-3'></i>
                    <h4>Confirm Deletion</h4>
                    <p class='lead'>You are about to delete this bonus item permanently.</p>
                    <div class='mt-4'>
                        <a href='{$confirmUrl}' class='btn btn-danger btn-lg me-3'>
                            <i class='bi bi-trash-fill me-2'></i>Confirm Delete
                        </a>
                        <a href='{$_SERVER['SCRIPT_NAME']}?act=bonuspoints' class='btn btn-secondary btn-lg'>
                            <i class='bi bi-x-circle me-2'></i>Cancel
                        </a>
                    </div>
                </div>",
                "Delete Confirmation",
                "confirm"
            );
            return;
        } else {
            // Выполняем удаление
            $result = $this->db->sql_query('DELETE FROM bonus WHERE id = ' . $this->db->sqlesc($bonusId));
            if ($result) {
                stderr222(
                    'Bonus item successfully deleted.' . $this->renderNavigation(),
                    'Success',
                    'success'
                );
            } else {
                stderr(
                    'Unable to delete bonus item.',
                    'Error',
                    'error'
                );
            }
            return;
        }
    }

    // Если не удаление, то обновление
    $bonusName = $this->db->sqlesc($_POST['bonusname'] ?? '');
    $points = $this->db->sqlesc(empty($_POST['points']) ? 0 : (float)$_POST['points']);
    $description = $this->db->sqlesc($_POST['description'] ?? '');
    $menge = $this->db->sqlesc(empty($_POST['menge']) ? 0 : (int)$_POST['menge']);

    $result = $this->db->sql_query(
        "UPDATE bonus SET bonusname = {$bonusName}, points = {$points}, 
         description = {$description}, menge = {$menge} 
         WHERE id = " . $this->db->sqlesc($bonusId)
    );

    if ($result) {
        stderr222(
            'Bonus item successfully updated.' . $this->renderNavigation(),
            'Success',
            'success'
        );
    } else {
        stderr(
            'Unable to update bonus item.',
            'Error',
            'error'
        );
    }
}







    private function showAdminPanel(): void
    {
        stdhead('Bonus Points ' . self::BS_VERSION . ' - Admin Panel');
        
        echo '<div class="container-fluid mt-3">';
        echo $this->renderHeader('Bonus System Configuration', 'bi-gear-fill');
        echo $this->renderBonusList();
        echo $this->renderNavigation();
        echo '</div>';
        
        stdfoot();
    }

    private function renderBonusList(): string
    {
        $result = $this->db->sql_query('SELECT * FROM bonus ORDER BY id ASC');
        
        $rows = '';
        while ($bonus = $this->db->fetch_array($result)) {
            $editUrl = $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=updatebonussystem&id=' . $bonus['id'];
            
            $rows .= <<<HTML
                <tr>
                    <td class="text-center fw-bold">
                        <i class="bi bi-hash text-muted me-1"></i>{$bonus['id']}
                    </td>
                    <td class="fw-bold text-primary">
                        <i class="bi bi-tag me-1"></i>{$bonus['bonusname']}
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success fs-6">
                            <i class="bi bi-coin me-1"></i>{$bonus['points']}
                        </span>
                    </td>
                    <td>
                        <div class="alert alert-info mb-0 p-2 small">
                            <i class="bi bi-info-circle me-1"></i>{$bonus['description']}
                        </div>
                    </td>
                    <td class="text-center">
                        <a href="{$editUrl}" class="btn btn-sm btn-primary"
                           data-bs-toggle="tooltip" title="Edit Bonus">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                    </td>
                </tr>
            HTML;
        }

        return <<<HTML
    <div class="container mt-3">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-check me-2"></i>Bonus Items
                    <span class="badge bg-light text-primary ms-2 fs-6">
                        <i class="bi bi-collection me-1"></i>{$this->db->num_rows($result)} Items
                    </span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-primary">
                            <tr>
                                <th class="text-center">
                                    <i class="bi bi-hash me-1"></i>ID
                                </th>
                                <th>
                                    <i class="bi bi-tag me-1"></i>Name
                                </th>
                                <th class="text-center">
                                    <i class="bi bi-coin me-1"></i>Points
                                </th>
                                <th>
                                    <i class="bi bi-text-paragraph me-1"></i>Description
                                </th>
                                <th class="text-center">
                                    <i class="bi bi-tools me-1"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>{$rows}</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
HTML;

    }

    private function addBonus(): void
    {
        stdhead('Bonus Points ' . self::BS_VERSION . ' - Add Bonus');
        
        echo '<div class="container-fluid mt-3">';
        echo $this->renderHeader('Add New Bonus Item', 'bi-plus-circle');
        echo $this->renderAddBonusForm();
        echo $this->renderNavigation();
        echo '</div>';
        
        stdfoot();
    }

    private function renderAddBonusForm(): string
    {
       
	   
	   return <<<HTML
    <div class="container mt-3">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-plus-circle me-2"></i>Add New Bonus
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="{$_SERVER['SCRIPT_NAME']}">
                    <input type="hidden" name="act" value="bonuspoints">
                    <input type="hidden" name="action" value="add_save">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bonusname" class="form-label fw-bold">
                                    <i class="bi bi-tag me-1"></i>Bonus Name
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="bi bi-tag"></i>
                                    </span>
                                    <input type="text" class="form-control" id="bonusname" name="bonusname" 
                                           placeholder="Enter bonus name" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="points" class="form-label fw-bold">
                                    <i class="bi bi-coin me-1"></i>Points
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="bi bi-coin"></i>
                                    </span>
                                    <input type="number" class="form-control" id="points" name="points" 
                                           placeholder="Enter points" min="0" step="0.1" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">
                            <i class="bi bi-text-paragraph me-1"></i>Description
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white">
                                <i class="bi bi-chat-text"></i>
                            </span>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="4" placeholder="Enter bonus description" required></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="menge" class="form-label fw-bold">
                            <i class="bi bi-hdd me-1"></i>Amount (in bytes)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white">
                                <i class="bi bi-hdd"></i>
                            </span>
                            <input type="number" class="form-control" id="menge" name="menge" 
                                   placeholder="e.g., 1073741824 for 1GB">
                        </div>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i><strong>Common values:</strong><br>
                            <i class="bi bi-arrow-right me-2"></i>1 GB = 1073741824<br>
                            <i class="bi bi-arrow-right me-2"></i>2.5 GB = 2684354560<br>
                            <i class="bi bi-arrow-right me-2"></i>5 GB = 5368709120<br>
                            <i class="bi bi-arrow-right me-2"></i>10 GB = 10737418240
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-plus-circle me-2"></i>Add Bonus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
HTML;
	   
	   
	   
	   
	   
    }

    private function addBonusSave(): void
    {
        $bonusName = $this->db->sqlesc($_POST['bonusname']);
        $points = $this->db->sqlesc($_POST['points']);
        $description = $this->db->sqlesc($_POST['description']);
        $menge = $this->db->sqlesc($_POST['menge']);

        $result = $this->db->sql_query(
            "INSERT INTO bonus (bonusname, points, description, menge, art) 
             VALUES ({$bonusName}, {$points}, {$description}, {$menge}, 'traffic')"
        );

        if ($result) {
           
			
			
			
			 stderr222(
                    'New bonus successfully added.' . $this->renderNavigation(),
                    'Success',
                    'success'
                );
			
			
			
			
			
			
			
			
			
			
			
        } else {
            stderr(
                'Error', 
                '<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>' . 
                'Unable to add bonus!'
            );
        }
    }

    private function resetPointsForm(): void
    {
        stdhead('Bonus Points ' . self::BS_VERSION . ' - Reset Points');
        
        echo '<div class="container-fluid mt-3">';
        echo $this->renderHeader('Reset User Points', 'bi-arrow-clockwise');
        echo $this->renderResetForm();
        echo $this->renderNavigation();
        echo '</div>';
        
        stdfoot();
    }

    private function renderResetForm(): string
    {
        $userGroups = _selectbox_('Usergroup', 'usergroup');
        
     return <<<HTML
    <div class="container mt-3">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Reset Bonus Points
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-danger border-danger">
                    <h6 class="alert-heading">
                        <i class="bi bi-exclamation-octagon-fill me-2"></i>Warning!
                    </h6>
                    <p class="mb-0">
                        <i class="bi bi-shield-exclamation me-1"></i>
                        This action cannot be undone. All bonus points will be permanently reset.
                    </p>
                </div>
                
                <form method="post" action="{$_SERVER['SCRIPT_NAME']}">
                    <input type="hidden" name="act" value="bonuspoints">
                    <input type="hidden" name="action" value="resetall">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-people-fill me-1"></i>Select User Group
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="bi bi-funnel"></i>
                                    </span>
                                    {$userGroups}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-danger w-100 py-2"
                                    onclick="return confirm('Are you sure you want to reset ALL bonus points? This action cannot be undone!')">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset Points
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
HTML;
    
	
	}

    private function resetAllPoints(): void
    {
        $userGroup = $this->getValidatedUserGroup();
        $sure = $_GET['sure'] ?? 0;

        if (!$sure) {
            $userGroupName = $userGroup ? '[' . get_user_class_name($userGroup) . ']' : '[ALL User Groups]';
            $confirmUrl = $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=resetall&sure=1' . 
                         ($userGroup ? '&usergroup=' . $userGroup : '');
            
           
			
			
			
			
			
			 stderr222(
                "<div class='text-center'>
                    <i class='bi bi-exclamation-triangle-fill text-warning display-4 mb-3'></i>
                    <h4>Reset Confirmation</h4>
                     <p class='lead'>You are about to reset all bonus points for: <b>{$userGroupName}</b></p>
                    <div class='mt-4'>
                        <a href='{$confirmUrl}' class='btn btn-danger btn-lg me-3'>
                            <i class='bi bi-trash-fill me-2'></i>Confirm Reset
                        </a>
                        <a href='{$_SERVER['SCRIPT_NAME']}?act=bonuspoints' class='btn btn-secondary btn-lg'>
                            <i class='bi bi-x-circle me-2'></i>Cancel
                        </a>
                    </div>
                </div>",
                "Reset Confirmation",
                "confirm"
            );
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
            return;
        }

        $query = "UPDATE users SET seedbonus = 0.0 WHERE enabled = 'yes' AND ustatus = 'confirmed'";
        if ($userGroup) {
            $query .= " AND usergroup = " . $this->db->sqlesc($userGroup);
        }

        $result = $this->db->sql_query($query);
        
        if ($result) 
		{
           
			
			
			 stderr222(
                    'All bonus points have been successfully reset.' . $this->renderNavigation(),
                    'Success',
                    'success'
                );
			
			
			
			
        } else {
            stderr(
                'Error', 
                '<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>' . 
                'Unable to reset bonus points.' . $this->renderNavigation(), 
                false
            );
        }
    }

    private function getValidatedUserId(): int
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        return is_valid_id($id) ? $id : 0;
    }

    private function getValidatedBonusId(): int
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        return $id > 0 ? $id : 0;
    }

    private function getValidatedUserGroup(): int
    {
        $group = (int)($_POST['usergroup'] ?? $_GET['usergroup'] ?? 0);
        return $group > 0 ? $group : 0;
    }

    private function getUserById(int $userId): ?array
    {
        $result = $this->db->sql_query('SELECT id, username, seedbonus FROM users WHERE id = ' . $this->db->sqlesc($userId));
        return $this->db->num_rows($result) > 0 ? $this->db->fetch_array($result) : null;
    }

    private function getBonusById(int $bonusId): ?array
    {
        $result = $this->db->sql_query('SELECT * FROM bonus WHERE id = ' . $this->db->sqlesc($bonusId));
        return $this->db->num_rows($result) > 0 ? $this->db->fetch_array($result) : null;
    }

    private function renderHeader(string $title, string $icon): string
    {
        $totalUsers = $this->getTotalUsersWithBonus();
        
        return <<<HTML
            <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded">
                <h2 class="text-primary mb-0">
                    <i class="bi {$icon} me-2"></i>{$title}
                </h2>
                <span class="badge bg-primary fs-6">
                    <i class="bi bi-people-fill me-1"></i>{$totalUsers} Users
                </span>
            </div>
        HTML;
    }

    private function renderNavigation(): string
    {
        return <<<HTML
            <div class="mt-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-3">
                        <div class="btn-group btn-group-lg" role="group">
                            <a href="{$_SERVER['SCRIPT_NAME']}?act=bonuspoints" 
                               class="btn btn-primary">
                                <i class="bi bi-speedometer2 me-2"></i>Admin Panel
                            </a>
                            <a href="{$_SERVER['SCRIPT_NAME']}?act=bonuspoints&action=add" 
                               class="btn btn-success">
                                <i class="bi bi-plus-circle me-2"></i>Add Bonus
                            </a>
                            <a href="{$_SERVER['SCRIPT_NAME']}?act=bonuspoints&action=showlist" 
                               class="btn btn-info">
                                <i class="bi bi-people-fill me-2"></i>User List
                            </a>
                            <a href="{$_SERVER['SCRIPT_NAME']}?act=bonuspoints&action=reset" 
                               class="btn btn-warning">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset Points
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }
}

// Initialize and run the bonus points manager
$bonusManager = new BonusPointsManager($db, $BASEURL);
$bonusManager->handleRequest();

?>