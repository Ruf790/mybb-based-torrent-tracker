<?php
declare(strict_types=1);



if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

define('C_VERSION', '2.0');

/**
 * Category Management Module
 */
class CategoryManager
{
    private array $errors = [];
    private string $baseScript;
    
    public function __construct(private $db)
    {
        $this->baseScript = $_SERVER['SCRIPT_NAME'] . '?act=category';
    }
    
    /**
     * Update categories cache
     */
    public function updateCategoriesCache(): void
    {
        $categoriesC = [];
        $categoriesS = [];
        
        // Fetch main categories
        $query = $this->db->sql_query("SELECT * FROM categories WHERE type = 'c' ORDER BY name, id");
        while ($row = $this->db->fetch_array($query)) {
            $categoriesC[] = $row;
        }
        
        // Fetch subcategories
        $query = $this->db->sql_query("SELECT * FROM categories WHERE type = 's' ORDER BY name, id");
        while ($row = $this->db->fetch_array($query)) {
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
            $this->addError('Failed to write cache file');
        }
    }
    
    /**
     * Get icon selector HTML
     */
    public function getIconSelector(string $selected = ''): string
    {
        $icons = [
            'fa-solid fa-film fa-shake',
            'fa-solid fa-compact-disc fa-spin',
            'fa-solid fa-satellite-dish',
            'fa-solid fa-clapperboard',
            'fa-solid fa-tv',
            'fa-solid fa-question',
            'fa-solid fa-video',
            'fa-solid fa-photo-film',
            'fa-solid fa-music',
            'fa-solid fa-gamepad'
        ];
        
        $html = '<div class="input-group mb-3">
            <input type="text" class="form-control" name="icon" value="' . htmlspecialchars($selected, ENT_QUOTES) . '" 
                   placeholder="Enter Font Awesome icon classes">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
                    data-bs-toggle="dropdown" aria-expanded="false">
                Select Icon
            </button>
            <ul class="dropdown-menu dropdown-menu-end">';
        
        foreach ($icons as $icon) {
            $html .= '<li>
                <a class="dropdown-item" href="#" 
                   onclick="document.querySelector(\'input[name=icon]\').value=\'' . htmlspecialchars($icon, ENT_QUOTES) . '\'; return false;">
                   <i class="' . htmlspecialchars($icon, ENT_QUOTES) . ' me-2"></i>' . htmlspecialchars($icon, ENT_QUOTES) . '
                </a>
            </li>';
        }
        
        $html .= '</ul>
            </div>
            <small class="text-muted">Example: fa-solid fa-film fa-shake. Use <a href="https://fontawesome.com/search" target="_blank">Font Awesome</a> to find icons.</small>';
        
        return $html;
    }
    
    /**
     * Get category dropdown list
     */
    public function getCategoryDropdown(int $selectedId = 0, string $selectName = 'cid', bool $includeAll = false): string
    {
        $html = '<select name="' . htmlspecialchars($selectName, ENT_QUOTES) . '" 
                class="form-select form-select-sm border pe-5 w-auto">';
        
        if ($includeAll) {
            $html .= '<option value="0">-- All Categories --</option>';
        } else {
            $html .= '<option value="0">-- Select Category --</option>';
        }
        
        $query = $this->db->sql_query("SELECT id, name FROM categories WHERE type = 'c' ORDER BY name");
        while ($cat = $this->db->fetch_array($query)) {
            $selected = ($selectedId == (int)$cat['id']) ? ' selected' : '';
            $html .= sprintf(
                '<option value="%d"%s>%s</option>',
                (int)$cat['id'],
                $selected,
                htmlspecialchars($cat['name'], ENT_QUOTES)
            );
        }
        
        $html .= '</select>';
        return $html;
    }
    
    /**
     * Display errors
     */
    public function showErrors(): void
    {
        global $lang;
        
        if (empty($this->errors)) {
            return;
        }
        
        $errorHtml = implode('<br>', array_map('htmlspecialchars', $this->errors));
        
        echo '
        <div class="container mt-3">
            <div class="alert alert-danger" role="alert">
                <h5 class="alert-heading">
                    <i class="fas fa-exclamation-triangle me-2"></i>' . htmlspecialchars($lang->global['error'] ?? 'Error') . '
                </h5>
                <hr>
                <p class="mb-0">' . $errorHtml . '</p>
            </div>
        </div>';
    }
    
    /**
     * Add error message
     */
    private function addError(string $message): void
    {
        $this->errors[] = $message;
    }
    
    /**
     * Validate and sanitize input
     */
    private function sanitizeInput(array $input): array
    {
        return [
            'name' => trim(htmlspecialchars($input['name'] ?? '', ENT_QUOTES)),
            'icon' => trim(htmlspecialchars($input['icon'] ?? '', ENT_QUOTES)),
            'type' => !empty($input['cid']) ? 's' : 'c',
            'pid' => max(0, (int)($input['cid'] ?? 0))
        ];
    }
    
    /**
     * Save category
     */
    private function saveCategory(array $data, ?int $id = null): bool
    {
        if (empty($data['name'])) {
            $this->addError('Category name cannot be empty');
            return false;
        }
        
        if ($id === null) {
            // Insert new category
            $sql = "INSERT INTO categories (name, icon, type, pid) 
                    VALUES (" . $this->db->sqlesc($data['name']) . ", 
                            " . $this->db->sqlesc($data['icon']) . ",                         
                            " . $this->db->sqlesc($data['type']) . ", 
                            " . (int)$data['pid'] . ")";
        } else {
            // Update existing category
            $sql = "UPDATE categories SET 
                    name = " . $this->db->sqlesc($data['name']) . ", 
                    icon = " . $this->db->sqlesc($data['icon']) . ",              
                    type = " . $this->db->sqlesc($data['type']) . ", 
                    pid = " . (int)$data['pid'] . " 
                    WHERE id = " . (int)$id;
        }
        
        $result = $this->db->sql_query($sql);
        if (!$result) {
            $this->addError('Database error while saving category');
            return false;
        }
        
        $this->updateCategoriesCache();
        return true;
    }
    
    /**
     * Handle form submission
     */
    public function handleRequest(): void
    {
        $action = $_GET['do'] ?? $_POST['do'] ?? '';
        $what = $_GET['what'] ?? $_POST['what'] ?? '';
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $cid = (int)($_GET['cid'] ?? $_POST['cid'] ?? 0);
        
        switch ($action) {
            case 'new':
                $this->handleNew($what);
                break;
            case 'edit':
                $this->handleEdit($what, $id);
                break;
            case 'delete':
                $this->handleDelete($what, $id);
                break;
            case 'add_subcategory':
                $this->handleAddSubcategory($what, $cid);
                break;
            case 'ajax_get_category':
                $this->ajaxGetCategory($id);
                break;
            default:
                $this->showCategoryList();
                break;
        }
    }
    
    /**
     * Handle new category creation
     */
    private function handleNew(string $what): void
    {
        if ($what === 'save') {
            $data = $this->sanitizeInput($_POST);
            
            if ($this->saveCategory($data)) {
                redirect('admin/index.php?act=category', 'New category has been successfully added!');
                exit;
            }
        }
        
        $this->showCategoryForm('Add Category');
    }
    
    /**
     * Handle category editing
     */
    private function handleEdit(string $what, int $id): void
    {
        if (!$this->validateCategoryId($id)) {
            stderr('Error', 'Category with this ID was not found!');
            return;
        }
        
        if ($what === 'save') {
            $data = $this->sanitizeInput($_POST);
            
            if ($this->saveCategory($data, $id)) {
                redirect($this->baseScript);
                exit;
            }
        }
        
        $category = $this->getCategory($id);
        $this->showCategoryForm('Edit Category', $category);
    }
    
    /**
     * AJAX: Get category data
     */
    private function ajaxGetCategory(int $id): void
    {
        if (!$this->validateCategoryId($id)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Category not found']);
            exit;
        }
        
        $category = $this->getCategory($id);
        header('Content-Type: application/json');
        echo json_encode($category);
        exit;
    }
    
    /**
     * Handle category deletion
     */
    private function handleDelete(string $what, int $id): void
    {
        if (!$this->validateCategoryId($id)) {
            stderr('Error', 'Category with this ID was not found!');
            return;
        }
        
        if ($what === 'sure') {
            $this->db->sql_query("DELETE FROM categories WHERE id = " . (int)$id . " LIMIT 1");
            $this->updateCategoriesCache();
            redirect('admin/index.php?act=category', 'Category has been successfully deleted!');
        } else {
            $category = $this->getCategory($id);
            
            echo '<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="deleteModalLabel">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Confirmation
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning">
                                    <i class="fas fa-warning me-2"></i>
                                    <strong>Warning!</strong> This action cannot be undone.
                                </div>
                                <p>Are you sure you want to delete the category:</p>
                                <div class="alert alert-light">
                                    <h6 class="mb-1"><i class="' . htmlspecialchars($category['icon']) . ' me-2"></i>' . htmlspecialchars($category['name']) . '</h6>
                                    <p class="mb-0 text-muted small">' . htmlspecialchars($category['cat_desc']) . '</p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                                <a href="' . $this->baseScript . '&do=delete&id=' . $id . '&what=sure" class="btn btn-danger">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>';
				
				
				echo '
<script>
document.addEventListener("DOMContentLoaded", function () {
    var modalEl = document.getElementById("deleteModal");
    if (modalEl) {
        var deleteModal = new bootstrap.Modal(modalEl);
        deleteModal.show();
    }
});
</script>
';
				
				
            
            // Return to category list
            $this->showCategoryList();
        }
    }
    
    /**
     * Handle subcategory addition
     */
    private function handleAddSubcategory(string $what, int $cid): void
    {
        if (!$this->validateCategoryId($cid, 'c')) {
            stderr('Error', 'Main category with this ID was not found!');
            return;
        }
        
        if ($what === 'save') {
            $data = $this->sanitizeInput($_POST);
            $data['type'] = 's';
            $data['pid'] = $cid;
            
            if ($this->saveCategory($data)) {
                redirect('admin/index.php?act=category', 'New subcategory has been successfully added!');
                exit;
            }
        }
        
        $parentCategory = $this->getCategory($cid);
        $this->showSubcategoryForm($parentCategory);
    }
    
    /**
     * Show category form (for standalone pages)
     */
    private function showCategoryForm(string $title, array $category = null): void
    {
        global $lang;
        
        $isEdit = ($category !== null);
        $data = $category ?? [
            'name' => '',
            'icon' => '',
            'type' => 'c',
            'pid' => 0
        ];
        
        stdhead('Manage Categories - ' . $title);
        
        echo '<div class="container mt-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="admin/index.php?act=category">Categories</a></li>
                    <li class="breadcrumb-item active">' . htmlspecialchars($title) . '</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-folder' . ($isEdit ? '-open' : '-plus') . ' me-2"></i>' . htmlspecialchars($title) . '
                </h1>
                <a href="' . $this->baseScript . '" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
            </div>';
        
        $this->showErrors();
        
        echo '<div class="card shadow-sm">
                <div class="card-body">
                    <form method="post" action="' . $this->baseScript . '" novalidate>
                        <input type="hidden" name="do" value="' . ($isEdit ? 'edit' : 'new') . '">
                        <input type="hidden" name="what" value="save">';
        
        if ($isEdit) {
            echo '<input type="hidden" name="id" value="' . (int)$category['id'] . '">';
        }
        
        echo '<div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Category Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="' . htmlspecialchars($data['name']) . '" required>
                                <div class="invalid-feedback">Please enter category name</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="minclassread" class="form-label">Minimum Read Class</label>
                                <input type="number" class="form-control" id="minclassread" name="minclassread" 
                                       value="' . (int)$data['minclassread'] . '" min="0" max="255">
                            </div>
                            
                            <div class="col-12">
                                <label for="cat_desc" class="form-label">Category Description</label>
                                <textarea class="form-control" id="cat_desc" name="cat_desc" 
                                          rows="2">' . htmlspecialchars($data['cat_desc']) . '</textarea>
                            </div>';
        
        if ($isEdit || !$data['pid']) {
            echo '<div class="col-md-6">
                                <label class="form-label">Parent Category</label>
                                ' . $this->getCategoryDropdown((int)$data['pid']) . '
                            </div>';
        }
        
        echo '<div class="col-md-6">
                                <label class="form-label">Category Icon</label>
                                ' . $this->getIconSelector($data['icon']) . '
                            </div>
                            
                            <div class="col-12">
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-2"></i>Save
                                    </button>
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-undo me-2"></i>Reset Fields
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>';
        
        stdfoot();
        exit;
    }
    
    /**
     * Show subcategory form
     */
    private function showSubcategoryForm(array $parentCategory): void
    {
        stdhead('Add Subcategory');
        
        echo '<div class="container mt-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="admin/index.php?act=category">Categories</a></li>
                    <li class="breadcrumb-item active">Add Subcategory</li>
                </ol>
            </nav>
            
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-folder-plus me-2"></i>
                        Add Subcategory to "' . htmlspecialchars($parentCategory['name']) . '"
                    </h5>
                </div>
                <div class="card-body">';
        
        $this->showErrors();
        
        echo '<form method="post" action="' . $this->baseScript . '">
                        <input type="hidden" name="do" value="add_subcategory">
                        <input type="hidden" name="what" value="save">
                        <input type="hidden" name="cid" value="' . (int)$parentCategory['id'] . '">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Subcategory Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cat_desc" class="form-label">Description</label>
                            <input type="text" class="form-control" id="cat_desc" name="cat_desc">
                        </div>
                        
                        <div class="mb-3">
                            <label for="minclassread" class="form-label">Minimum Read Class</label>
                            <input type="number" class="form-control" id="minclassread" name="minclassread" value="0" min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Icon</label>
                            ' . $this->getIconSelector() . '
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Add Subcategory
                            </button>
                            <a href="' . $this->baseScript . '" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>';
        
        stdfoot();
        exit;
    }
    
    /**
     * Show category list with modals
     */
    private function showCategoryList(): void
    {
        global $BASEURL;
        
        stdhead('Manage Tracker Categories');
        
        // Add Category Modal
        echo '<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="addCategoryModalLabel">
                                <i class="fas fa-plus-circle me-2"></i>Add New Category
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" action="' . $this->baseScript . '">
                            <div class="modal-body">
                                <input type="hidden" name="do" value="new">
                                <input type="hidden" name="what" value="save">
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="modal_name" class="form-label">Category Name *</label>
                                        <input type="text" class="form-control" id="modal_name" name="name" required>
                                    </div>
                                    
                                    
                                    
                                    <div class="col-12">
                                        <label for="modal_cat_desc" class="form-label">Category Description</label>
                                        <textarea class="form-control" id="modal_cat_desc" name="cat_desc" rows="2"></textarea>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Parent Category</label>
                                        ' . $this->getCategoryDropdown(0, 'cid') . '
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Category Icon</label>
                                        ' . $this->getIconSelector() . '
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>';
        
        // Edit Category Modal
        echo '<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="editCategoryModalLabel">
                                <i class="fas fa-edit me-2"></i>Edit Category
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" action="' . $this->baseScript . '" id="editCategoryForm">
                            <div class="modal-body" id="editCategoryModalBody">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-3">Loading category data...</p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>';
        
        // Add Subcategory Modal
        echo '<div class="modal fade" id="addSubcategoryModal" tabindex="-1" aria-labelledby="addSubcategoryModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="addSubcategoryModalLabel">
                                <i class="fas fa-plus-circle me-2"></i>Add Subcategory
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" action="' . $this->baseScript . '" id="addSubcategoryForm">
                            <div class="modal-body">
                                <input type="hidden" name="do" value="add_subcategory">
                                <input type="hidden" name="what" value="save">
                                <input type="hidden" name="cid" id="parentCategoryId" value="">
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Adding subcategory to: <strong id="parentCategoryName"></strong>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="sub_name" class="form-label">Subcategory Name *</label>
                                        <input type="text" class="form-control" id="sub_name" name="name" required>
                                    </div>
                                    
                                   
                                    
                                    <div class="col-12">
                                        <label for="sub_cat_desc" class="form-label">Subcategory Description</label>
                                        <textarea class="form-control" id="sub_cat_desc" name="cat_desc" rows="2"></textarea>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="form-label">Subcategory Icon</label>
                                        ' . $this->getIconSelector() . '
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus-circle me-1"></i>Add Subcategory
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>';
        
        echo '<div class="container mt-3">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-folder-tree me-2"></i>Manage Categories
                    </h1>
                    <p class="text-muted mb-0">Manage tracker categories and subcategories</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-plus-circle me-2"></i>Add Category
                    </button>
                </div>
            </div>';
        
        $this->showErrors();
        
        // Fetch categories with their subcategories
        $categories = [];
        $subcategories = [];
        
        // Get main categories
        $query = $this->db->sql_query("SELECT * FROM categories WHERE type = 'c' ORDER BY name");
        while ($cat = $this->db->fetch_array($query)) {
            $categories[$cat['id']] = $cat;
        }
        
        // Get subcategories grouped by parent
        $query = $this->db->sql_query("SELECT * FROM categories WHERE type = 's' ORDER BY name");
        while ($sub = $this->db->fetch_array($query)) {
            $subcategories[$sub['pid']][] = $sub;
        }
        
        if (empty($categories)) {
            echo '<div class="alert alert-info">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle fa-2x me-3"></i>
                        <div>
                            <h5 class="alert-heading">No categories created yet</h5>
                            <p class="mb-0">Start by adding your first category using the button above.</p>
                        </div>
                    </div>
                </div>';
        } else {
            echo '<div class="row">';
            
            foreach ($categories as $category) {
                echo '<div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="' . htmlspecialchars($category['icon'] ?? 'fas fa-folder') . ' me-2"></i>
                                    <strong>' . htmlspecialchars($category['name']) . '</strong>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <a href="' . $BASEURL . '/browse.php?cat=' . (int)$category['id'] . '" 
                                       class="btn btn-outline-success" title="View" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-primary edit-category-btn" 
                                            data-id="' . (int)$category['id'] . '" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="' . $this->baseScript . '&do=delete&id=' . (int)$category['id'] . '" 
                                       class="btn btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <p class="card-text text-muted small mb-3">
                                    ' . htmlspecialchars($category['cat_desc'] ?: 'No description') . '
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-user-shield me-1"></i>Class: ' . (int)$category['minclassread'] . '
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-success add-subcategory-btn" 
                                            data-id="' . (int)$category['id'] . '" data-name="' . htmlspecialchars($category['name'], ENT_QUOTES) . '">
                                        <i class="fas fa-plus me-1"></i>Add Subcategory
                                    </button>
                                </div>';
                
                // Show subcategories
                if (!empty($subcategories[$category['id']])) {
                    echo '<div class="border-top pt-3">
                            <h6 class="mb-3">
                                <i class="fas fa-sitemap me-2"></i>Subcategories
                                <span class="badge bg-primary rounded-pill ms-2">' . count($subcategories[$category['id']]) . '</span>
                            </h6>
                            <div class="list-group list-group-flush">';
                    
                    foreach ($subcategories[$category['id']] as $sub) {
                        echo '<div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="' . htmlspecialchars($sub['icon'] ?? 'fas fa-folder') . ' me-2"></i>
                                    ' . htmlspecialchars($sub['name']) . '
                                    <br>
                                    <small class="text-muted">' . htmlspecialchars($sub['cat_desc'] ?: '') . '</small>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <a href="' . $BASEURL . '/browse.php?cat=' . (int)$sub['id'] . '" 
                                       class="btn btn-sm btn-outline-success" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-category-btn" 
                                            data-id="' . (int)$sub['id'] . '">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="' . $this->baseScript . '&do=delete&id=' . (int)$sub['id'] . '" 
                                       class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                              </div>';
                    }
                    
                    echo '</div></div>';
                } else {
                    echo '<div class="text-center py-3 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p class="mb-0">No subcategories</p>
                          </div>';
                }
                
                echo '</div></div></div>';
            }
            
            echo '</div>';
        }
        
        echo '</div>'; // container
    


	
		
		
		
        
        // JavaScript for modal windows
        ?>
        <script>
document.addEventListener('DOMContentLoaded', function () {

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.querySelectorAll('.edit-category-btn').forEach(function(btn) {
        btn.addEventListener('click', function () {
            const categoryId = this.dataset.id;
            const modalEl = document.getElementById('editCategoryModal');
            const modalBody = document.getElementById('editCategoryModalBody');
            const modalLabel = document.getElementById('editCategoryModalLabel');

            const editModal = new bootstrap.Modal(modalEl);
            editModal.show();

            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading category data...</p>
                </div>
            `;

            fetch('<?php echo $this->baseScript; ?>&do=ajax_get_category&id=' + categoryId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<div class="alert alert-danger">${escapeHtml(data.error)}</div>`;
                        return;
                    }

                    let formHtml = `
                        <input type="hidden" name="do" value="edit">
                        <input type="hidden" name="what" value="save">
                        <input type="hidden" name="id" value="${escapeHtml(data.id)}">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Category Name *</label>
                                <input type="text" class="form-control" name="name" value="${escapeHtml(data.name)}" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Category Description</label>
                                <textarea class="form-control" name="cat_desc" rows="2">${escapeHtml(data.cat_desc || '')}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Parent Category</label>
                                <?php echo addslashes($this->getCategoryDropdown(0, 'cid')); ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category Icon</label>
                                <?php echo addslashes($this->getIconSelector()); ?>
                            </div>
                        </div>
                    `;

                    modalBody.innerHTML = formHtml;

                    const selectCid = modalBody.querySelector('select[name="cid"]');
                    if (selectCid) selectCid.value = data.pid || 0;

                    const inputIcon = modalBody.querySelector('input[name="icon"]');
                    if (inputIcon) inputIcon.value = data.icon || '';

                    modalLabel.innerHTML = `<i class="fas fa-edit me-2"></i>Edit Category "${escapeHtml(data.name)}"`;
                })
                .catch(err => {
                    modalBody.innerHTML = `<div class="alert alert-danger">Error loading category data</div>`;
                    console.error(err);
                });
        });
    });
});
</script>
        <?php
        
        stdfoot();
    }
    
    /**
     * Get category by ID
     */
    private function getCategory(int $id): ?array
    {
        $sql = "SELECT * FROM categories WHERE id = " . (int)$id;
        $query = $this->db->sql_query($sql);
        if ($row = $this->db->fetch_array($query)) {
            return $row;
        }
        return null;
    }
    
    /**
     * Validate category ID
     */
    private function validateCategoryId(int $id, string $type = null): bool
    {
        $sql = "SELECT id FROM categories WHERE id = " . (int)$id;
        if ($type) {
            $sql .= " AND type = " . $this->db->sqlesc($type);
        }
        
        $query = $this->db->sql_query($sql);
        return $this->db->num_rows($query) > 0;
    }
}

// Initialize and run category manager
$categoryManager = new CategoryManager($db);
$categoryManager->handleRequest();