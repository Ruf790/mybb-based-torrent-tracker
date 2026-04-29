<?php


declare(strict_types=1);

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

define('MC_VERSION', 'New Manage Countries Mod v.0.2 by xam');

class CountryManager
{
    private $db;
    private $baseUrl;
    private $picBaseUrl;
    
    public function __construct($db, string $baseUrl, string $picBaseUrl)
    {
        $this->db = $db;
        $this->baseUrl = $baseUrl;
        $this->picBaseUrl = $picBaseUrl;
    }
    
    private function getCountryData(int $id): array
    {
        $query = $this->db->sql_query('SELECT * FROM countries WHERE id = ' . $this->db->sqlesc($id));
        
        if ($this->db->num_rows($query) === 0) {
            stderr('Error', 'No country with this ID!');
        }
        
        return mysqli_fetch_assoc($query);
    }
    
    private function validateInput(array $data): void
    {
        if (empty($data['name']) || empty($data['flagpic'])) {
            stderr('Error', 'Please fill in all required fields!');
        }
        
        $path = TSDIR . '/' . $this->picBaseUrl . 'flag/' . $data['flagpic'];
        if (!file_exists($path)) {
            stderr('Error', sprintf('Country flag not found: "%s"', $path));
        }
    }
    
    public function handleRequest(): void
    {
        $action = htmlspecialchars($_POST['action'] ?? $_GET['action'] ?? 'show');
        $do = htmlspecialchars($_POST['do'] ?? $_GET['do'] ?? '');
        $name = htmlspecialchars($_POST['name'] ?? $_GET['name'] ?? '');
        $flagpic = htmlspecialchars($_POST['flagpic'] ?? $_GET['flagpic'] ?? '');
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        
        if ($action !== 'show' && $action !== 'new' && $id <= 0) {
            int_check($id, true);
        }
        
        switch (true) {
            case ($action === 'edit' && empty($do)):
                $this->showEditForm($id);
                break;
                
            case ($action === 'edit' && $do === 'save'):
                $this->updateCountry($id, $name, $flagpic);
                break;
                
            case ($action === 'delete' && empty($do)):
                $this->confirmDelete($id);
                break;
                
            case ($action === 'delete' && $do === 'delete'):
                $this->deleteCountry($id);
                break;
                
            case ($action === 'new' && $do === 'save'):
                $this->createCountry($name, $flagpic);
                break;
                
            case ($action === 'new' && empty($do)):
                $this->showCreateForm();
                break;
                
            default:
                $this->showCountries();
                break;
        }
    }
    
    private function showEditForm(int $id): void
    {
        $country = $this->getCountryData($id);
        stdhead('Edit Country: ' . htmlspecialchars($country['name']));
        
        echo <<<HTML
        <div class="container-md">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white rounded-top">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>
                        Edit Country: {$country['name']}
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="id" value="{$id}">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="do" value="save">
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Country ID</label>
                            <div class="form-control-plaintext bg-light p-2 rounded">{$id}</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-bold">Country Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="{$country['name']}" required>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Current Flag</label>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img src="{$this->baseUrl}/{$this->picBaseUrl}flag/{$country['flagpic']}" 
                                     class="img-thumbnail" style="max-height: 40px" 
                                     alt="{$country['name']}" title="{$country['name']}">
                                <span class="text-muted">{$country['flagpic']}</span>
                            </div>
                            
                            <label for="flagpic" class="form-label fw-bold">Flag Filename *</label>
                            <input type="text" class="form-control" id="flagpic" name="flagpic" 
                                   value="{$country['flagpic']}" required 
                                   placeholder="e.g., us.gif, gb.png">
                            <div class="form-text">Enter the filename of the flag image</div>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                                <a href="admin/index.php?act=country" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        HTML;
        
        stdfoot();
    }
    
    private function updateCountry(int $id, string $name, string $flagpic): void
    {
        $this->validateInput(['name' => $name, 'flagpic' => $flagpic]);
        
        $this->db->sql_query(sprintf(
            "UPDATE countries SET name = %s, flagpic = %s WHERE id = %s",
            $this->db->sqlesc($name),
            $this->db->sqlesc($flagpic),
            $this->db->sqlesc($id)
        ));
        
        redirect('admin/index.php?act=country&#c' . $id);
    }
    
    private function confirmDelete(int $id): void
    {
        $country = $this->getCountryData($id);
        
        echo <<<HTML
        <div class="container-md">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Deletion
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="alert alert-warning">
                        <h4 class="alert-heading">Warning!</h4>
                        <p>Are you sure you want to delete the country:</p>
                        <h5 class="text-danger my-3">{$country['name']}</h5>
                        <p>This action cannot be undone.</p>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <a href="{$_SERVER['PHP_SELF']}?act=country&action=delete&id={$id}&do=delete" 
                           class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Yes, Delete It
                        </a>
                        <a href="admin/index.php?act=country" class="btn btn-success">
                            <i class="fas fa-times me-1"></i> No, Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
        HTML;
    }
    
    private function deleteCountry(int $id): void
    {
        $this->db->sql_query('DELETE FROM countries WHERE id = ' . $this->db->sqlesc($id) . ' LIMIT 1');
        redirect('admin/index.php?act=country');
    }
    
    private function showCreateForm(): void
    {
        stdhead('Register New Country');
        
        echo <<<HTML
        <div class="container-md">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white rounded-top">
                    <h5 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>
                        Register New Country
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="new">
                        <input type="hidden" name="do" value="save">
                        
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-bold">Country Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   required placeholder="Enter country name">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="flagpic" class="form-label fw-bold">Flag Filename *</label>
                            <input type="text" class="form-control" id="flagpic" name="flagpic" 
                                   required placeholder="e.g., us.gif, gb.png">
                            <div class="form-text">Filename must exist in flags directory</div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-info-circle me-1"></i> Available Flags
                                    </h6>
                                    <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-2">
        HTML;
        
        $flagsDir = TSDIR . '/' . $this->picBaseUrl . 'flag/';
        if (is_dir($flagsDir)) {
            $flags = glob($flagsDir . '*.{gif,jpg,jpeg,png}', GLOB_BRACE);
            foreach (array_slice($flags, 0, 12) as $flag) {
                $filename = basename($flag);
                echo <<<HTML
                <div class="col">
                    <div class="border rounded p-1 text-center">
                        <img src="{$this->baseUrl}/{$this->picBaseUrl}flag/{$filename}" 
                             class="img-fluid mb-1" style="max-height: 20px">
                        <small class="d-block text-truncate">{$filename}</small>
                    </div>
                </div>
                HTML;
            }
        }
        
        echo <<<HTML
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Create Country
                                </button>
                                <a href="admin/index.php?act=country" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        HTML;
        
        stdfoot();
    }
    
    private function createCountry(string $name, string $flagpic): void
    {
        $this->validateInput(['name' => $name, 'flagpic' => $flagpic]);
        
        $this->db->sql_query(sprintf(
            "INSERT INTO countries (name, flagpic) VALUES (%s, %s)",
            $this->db->sqlesc($name),
            $this->db->sqlesc($flagpic)
        ));
        
        $newId = $this->db->insert_id();
        redirect('admin/index.php?act=country&#c' . $newId);
    }
    
    private function showCountries(): void
    {
        stdhead('Manage Countries');
        
        echo <<<HTML
        <div class="container mt-3">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">
                    <i class="fas fa-globe-americas me-2"></i>Manage Countries
                </h1>
                <a href="{$_SERVER['PHP_SELF']}?act=country&action=new" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i> Add New Country
                </a>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-1"></i>
                        Registered Countries
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="10%" class="text-center">ID</th>
                                    <th width="30%">Country Name</th>
                                    <th width="20%" class="text-center">Flag</th>
                                    <th width="40%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
        HTML;
        
        $query = $this->db->sql_query('SELECT * FROM countries ORDER BY name ASC');
        while ($country = mysqli_fetch_assoc($query)) {
            $flagUrl = $this->baseUrl . '/' . $this->picBaseUrl . 'flag/' . $country['flagpic'];
            
            echo <<<HTML
                            <tr id="c{$country['id']}">
                                <td class="text-center align-middle">
                                    <span class="badge bg-secondary">{$country['id']}</span>
                                </td>
                                <td class="align-middle">
                                    <strong>{$country['name']}</strong>
                                </td>
                                <td class="text-center align-middle">
                                    <img src="{$flagUrl}" 
                                         class="img-thumbnail" style="max-height: 30px" 
                                         alt="{$country['name']}" title="{$country['name']}">
                                </td>
                                <td class="text-center align-middle">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{$_SERVER['PHP_SELF']}?act=country&action=edit&id={$country['id']}" 
                                           class="btn btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </a>
                                        <a href="{$_SERVER['PHP_SELF']}?act=country&action=delete&id={$country['id']}" 
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Delete {$country['name']}?')">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
            HTML;
        }
        
        echo <<<HTML
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white py-3">
                    <div class="text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        Total: {$this->db->num_rows($query)} countries registered
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-4">
                <h6><i class="fas fa-lightbulb me-2"></i>Quick Tips:</h6>
                <ul class="mb-0">
                    <li>Click on a country flag to preview it</li>
                    <li>Use descriptive country names for better organization</li>
                    <li>Ensure flag images are uploaded to the flags directory</li>
                    <li>Recommended flag size: 16x11 pixels</li>
                </ul>
            </div>
        </div>
        HTML;
        
        stdfoot();
    }
}

// Инициализация
$countryManager = new CountryManager($db, $BASEURL, $pic_base_url);
$countryManager->handleRequest();

?>