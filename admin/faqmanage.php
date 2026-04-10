<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

/**
 * Display FAQ error messages
 */
function show_faq_errors()
{
    global $faq_errors, $lang;
    
    if (empty($faq_errors) || count($faq_errors) === 0) {
        return;
    }
    
    $errors = implode('<br />', $faq_errors);
    
	stdhead($lang->faq['faqtitle'], true, '', '');
	
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

// Security check
if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger m-3" role="alert">
        <i class="fas fa-ban me-2"></i><b>Error!</b> Direct initialization of this file is not allowed.
    </div>');
}

// Constants and initialization
define('TSFAQMANAGE_VERSION', '1.3.2 by xam');

$lang->load('faq');

// Sanitize input
$do = isset($_GET['do']) ? htmlspecialchars_uni($_GET['do']) : (isset($_POST['do']) ? htmlspecialchars_uni($_POST['do']) : '');
$subdo = isset($_GET['subdo']) ? htmlspecialchars_uni($_GET['subdo']) : (isset($_POST['subdo']) ? htmlspecialchars_uni($_POST['subdo']) : '');
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

$faq_errors = array();

// Start output



echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

// Main routing - восстановим оригинальную логику
if ($do == 'view') {
    handleView($id);
} elseif ($do == 'savedisplayorder') {
    handleSaveDisplayOrder();
} elseif ($do == 'delete') {
    handleDelete($id);
} elseif ($do == 'new') {
    handleNew();
} elseif ($do == 'add') {
    handleAdd($id);
} elseif ($do == 'edit') {
    handleEdit($id);
} else {
    handleDefault();
}

stdfoot();

/**
 * Handle viewing FAQ items
 */
 
 

 
 
 
 
 
 
function handleView($id)
{
    global $db, $lang, $faq_errors, $_this_script_;

    if (!is_valid_id($id)) {
        $faq_errors[] = $lang->faq['faqerror'];
        show_faq_errors();
        return;
    }

    $query = $db->sql_query("
    SELECT a.id, a.name, a.description, b.name AS title
    FROM faq a
    LEFT JOIN faq b ON (a.pid = b.id)
    WHERE a.type = 'item' AND a.pid = " . (int)$id . "
    ORDER BY a.disporder ASC
    ");

    if (!$query) {
        die('SQL ERROR: ' . $db->error());
    }

    if ($db->num_rows($query) == 0) {
        $faq_errors[] = $lang->faq['faqerror'];
        show_faq_errors();
        return;
    }

    echo '
    <div class="container mt-3">
        <div class="card shadow border-0">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-question-circle me-2"></i>
                    ' . $lang->faq['faqtitle'] . '
                </h4>
            </div>
            <div class="card-body">
                <div class="accordion" id="faqAccordion">';

    $currentTitle = '';

    while ($faq = $db->fetch_array($query)) {

        if ($currentTitle !== $faq['title']) {
            $currentTitle = $faq['title'];
            echo '
            <h5 class="text-primary mt-4 mb-3">
                <i class="fas fa-folder me-2"></i>
                ' . htmlspecialchars($currentTitle) . '
            </h5>';
        }

        $collapseId = 'collapse' . (int)$faq['id'];

        stdhead ($lang->faq['faqtitle'], true, '', '');
		
		
		echo '
        <div class="accordion-item mb-2">
            <h2 class="accordion-header" id="heading' . $faq['id'] . '">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#' . $collapseId . '">
                    <strong class="text-danger">' . htmlspecialchars($faq['name']) . '</strong>

                    <span class="ms-auto">
                        <a href="' . $_this_script_ . '&do=edit&id=' . (int)$faq['id'] . '" class="btn btn-sm btn-outline-primary me-1">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="' . $_this_script_ . '&do=delete&id=' . (int)$faq['id'] . '" 
                           onclick="return confirm(\'Delete this item?\')"
                           class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-trash"></i>
                        </a>
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


/**
 * Handle saving display order
 */
function handleSaveDisplayOrder()
{
    global $db, $faq_errors;
    
    $orders = isset($_POST['disporder']) ? $_POST['disporder'] : array();
    
    if (!is_array($orders)) {
        $faq_errors[] = 'Empty FAQ order(s)!';
        show_faq_errors();
        return;
    }
    
    foreach ($orders as $id => $order) {
        $db->sql_query("UPDATE faq SET disporder = '" . $db->escape_string($order) . "' WHERE id = '" . $db->escape_string($id) . "'");
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/**
 * Handle deletion
 */
function handleDelete($id)
{
    global $db, $lang, $faq_errors, $_this_script_;
    
    if (!is_valid_id($id)) {
        $faq_errors[] = $lang->faq['faqerror'];
        show_faq_errors();
        return;
    }
    
    $db->sql_query("DELETE FROM faq WHERE id = '" . $db->escape_string($id) . "'");
    $db->sql_query("DELETE FROM faq WHERE pid = '" . $db->escape_string($id) . "'");
    
    // Redirect after deletion
    header('Location: ' . $_this_script_);
    exit;
}

/**
 * Handle new FAQ creation
 */
function handleNew()
{
    global $db, $lang, $faq_errors, $_this_script_;
    
    if (isset($_POST['subdo']) && $_POST['subdo'] == 'save') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $disporder = isset($_POST['disporder']) ? (int)$_POST['disporder'] : 0;
        
        if (empty($name)) {
            $faq_errors[] = 'Please fill all fields!';
        } else {
            $db->sql_query("INSERT INTO faq (type,name,description,disporder) VALUES ('1','" . $db->escape_string($name) . "','" . $db->escape_string($description) . "','" . $db->escape_string($disporder) . "')");
            header('Location: ' . $_this_script_);
            exit;
        }
        
        show_faq_errors();
    }
    
    $name = isset($_POST['name']) ? htmlspecialchars_uni($_POST['name']) : '';
    $description = isset($_POST['description']) ? htmlspecialchars_uni($_POST['description']) : '';
    $disporder = isset($_POST['disporder']) ? (int)$_POST['disporder'] : 0;
    
    $where = array('Cancel' => $_this_script_);
	
	stdhead($lang->faq['faqtitle'], true, '', '');
    
    echo '
    <form method="post" action="' . $_this_script_ . '">
    <input type="hidden" name="do" value="new">
    <input type="hidden" name="subdo" value="save">
    
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="text-primary">
                        <i class="fas fa-plus-circle me-2"></i>
                        Add New FAQ Item
                    </h3>
                    <div>
                        ' . jumpbutton($where) . '
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="name" class="form-label">
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
                                <label for="description" class="form-label">
                                    <i class="fas fa-align-left me-1"></i>
                                    Description
                                </label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="8">' . $description . '</textarea>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="disporder" class="form-label">
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
    
    stdfoot();
    exit;
}

/**
 * Handle adding child FAQ item
 */
function handleAdd($id)
{
    global $db, $lang, $faq_errors, $_this_script_;
    
    if (!is_valid_id($id)) {
        $faq_errors[] = $lang->faq['faqerror'];
        show_faq_errors();
        return;
    }
    
    if (isset($_POST['subdo']) && $_POST['subdo'] == 'save') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $disporder = isset($_POST['disporder']) ? (int)$_POST['disporder'] : 0;
        $pid = isset($_POST['pid']) ? (int)$_POST['pid'] : 0;
        
        if (empty($name)) {
            $faq_errors[] = 'Please fill all fields!';
        } else {
            $db->sql_query("INSERT INTO faq (type,name,description,disporder,pid) VALUES ('2','" . $db->escape_string($name) . "','" . $db->escape_string($description) . "','" . $db->escape_string($disporder) . "','" . $db->escape_string($pid) . "')");
            header('Location: ' . $_this_script_);
            exit;
        }
    }
    
    $query = $db->sql_query("SELECT * FROM faq WHERE type = 'category'");
    if ($db->num_rows($query) == 0) {
        $faq_errors[] = $lang->faq['faqerror'];
        show_faq_errors();
    } else {
        show_faq_errors();
        
        $categories = '<select name="pid" class="form-select">';
        while ($faq = $db->fetch_array($query)) {
            $categories .= '<option value="' . $faq['id'] . '"' . ($id == $faq['id'] ? ' selected="selected"' : '') . '>' . $faq['name'] . '</option>';
        }
        $categories .= '</select>';
        
        $name = isset($_POST['name']) ? htmlspecialchars_uni($_POST['name']) : '';
        $description = isset($_POST['description']) ? htmlspecialchars_uni($_POST['description']) : '';
        $disporder = isset($_POST['disporder']) ? (int)$_POST['disporder'] : 0;
        
        $where = array('Cancel' => $_this_script_);
        
        stdhead($lang->faq['faqtitle'], true, '', '');
		
		echo '
        <form method="post" action="' . $_this_script_ . '">
        <input type="hidden" name="do" value="add">
        <input type="hidden" name="subdo" value="save">
        <input type="hidden" name="id" value="' . $id . '">
        
        <div class="container mt-3">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="text-primary">
                            <i class="fas fa-plus-circle me-2"></i>
                            Add Child FAQ Item
                        </h3>
                        <div>
                            ' . jumpbutton($where) . '
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="pid" class="form-label">
                                        <i class="fas fa-folder me-1"></i>
                                        Category
                                    </label>
                                    ' . $categories . '
                                </div>
                                
                                <div class="col-md-12">
                                    <label for="name" class="form-label">
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
                                    <label for="description" class="form-label">
                                        <i class="fas fa-align-left me-1"></i>
                                        Description
                                    </label>
                                    <textarea class="form-control" 
                                              id="description" 
                                              name="description" 
                                              rows="8">' . $description . '</textarea>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="disporder" class="form-label">
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
    
   
}

/**
 * Handle editing FAQ item - ВОССТАНОВИМ ОРИГИНАЛЬНУЮ ЛОГИКУ
 */
function handleEdit($id)
{
    global $db, $lang, $faq_errors, $_this_script_;
    
    if (!is_valid_id($id)) {
        $faq_errors[] = $lang->faq['faqerror'];
        stdhead($lang->faq['faqtitle']);
        show_faq_errors();
        stdfoot();
        return;
    }
    
    if (isset($_POST['subdo']) && $_POST['subdo'] == 'save' && is_valid_id($id)) {
    $type = isset($_POST['type']) ? $_POST['type'] : 'category'; // получаем как строку
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $disporder = isset($_POST['disporder']) ? (int)$_POST['disporder'] : 0;
    $pid = isset($_POST['pid']) ? (int)$_POST['pid'] : 0;

    // Если type передан как число (старый код), конвертируем в enum
    if ($type === '1' || $type === 1) {
        $type = 'category';
    } elseif ($type === '2' || $type === 2) {
        $type = 'item';
    }

    if (empty($name) || ($type == 'item' && empty($description))) {
        $faq_errors[] = 'Please fill all fields!';
    } else {
        $db->sql_query("UPDATE faq SET 
            type = '" . $db->escape_string($type) . "', 
            name = '" . $db->escape_string($name) . "', 
            description = '" . $db->escape_string($description) . "', 
            disporder = '" . $db->escape_string($disporder) . "', 
            pid = '" . $db->escape_string($pid) . "' 
        WHERE id = '" . $db->escape_string($id) . "'");

        header('Location: ' . $_this_script_);
        exit;
    }
}

    
    $firstquery = $db->sql_query("SELECT * FROM faq WHERE id = '" . $db->escape_string($id) . "'");
    if ($db->num_rows($firstquery) == 0) {
        $faq_errors[] = $lang->faq['faqerror'];
        show_faq_errors();
    } else {
        $editfaq = $db->fetch_array($firstquery);
        show_faq_errors();
        
        if ($editfaq['type'] == 2) {
            $query2 = $db->sql_query("SELECT * FROM faq WHERE type = '1' ORDER BY disporder ASC");
            $categories = '
                <div class="col-md-12">
                    <label for="pid" class="form-label">
                        <i class="fas fa-folder me-1"></i>
                        Category
                    </label>
                    <select name="pid" class="form-select">';
            
            while ($cat = $db->fetch_array($query2)) {
                $categories .= '<option value="' . $cat['id'] . '"' . ($editfaq['pid'] == $cat['id'] ? ' selected="selected"' : '') . '>' . $cat['name'] . '</option>';
            }
            
            $categories .= '</select>
                </div>';
        } else {
            $categories = '<input type="hidden" name="pid" value="' . $editfaq['pid'] . '">';
        }
        
        $nameValue = isset($_POST['name']) ? htmlspecialchars_uni($_POST['name']) : htmlspecialchars_uni($editfaq['name']);
        $descriptionValue = isset($_POST['description']) ? htmlspecialchars_uni($_POST['description']) : $editfaq['description'];
        $disporderValue = isset($_POST['disporder']) ? htmlspecialchars_uni($_POST['disporder']) : $editfaq['disporder'];
        
        $where = array('Cancel' => $_this_script_);
		
		stdhead($lang->faq['faqtitle'], true, '', '');
        
        echo '
        <form method="post" action="' . $_this_script_ . '">
        <input type="hidden" name="do" value="edit">
        <input type="hidden" name="subdo" value="save">
        <input type="hidden" name="id" value="' . $id . '">
        <input type="hidden" name="type" value="' . $editfaq['type'] . '">
        
        <div class="container mt-3">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="text-primary">
                            <i class="fas fa-edit me-2"></i>
                            Edit FAQ Item: ' . htmlspecialchars_uni($editfaq['name']) . '
                        </h3>
                        <div>
                            ' . jumpbutton($where) . '
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-body">
                            <div class="row g-3">
                                ' . $categories . '
                                
                                <div class="col-md-12">
                                    <label for="name" class="form-label">
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
                                    <label for="description" class="form-label">
                                        <i class="fas fa-align-left me-1"></i>
                                        Description
                                    </label>
                                    <textarea class="form-control" 
                                              id="description" 
                                              name="description" 
                                              rows="8">' . $descriptionValue . '</textarea>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="disporder" class="form-label">
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
    
    
}

/**
 * Handle default view (FAQ list)
 */
function handleDefault()
{
    global $db, $lang, $_this_script_;
	
	stdhead($lang->faq['faqtitle'], true, '', '');
    
    show_faq_errors();
    
    $where = array('Add New FAQ Item' => $_this_script_ . '&do=new');
    
    $query = $db->sql_query("SELECT disporder, id, name FROM faq WHERE type = 'category' ORDER BY disporder ASC");
    
    if ($db->num_rows($query) == 0) {
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
    
    
	
	?>
	<script>
        function confirmDeleteSwal(url) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This FAQ item will be permanently deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Перенаправляем на удаление
            window.location.href = url;
        }
    });
    return false; // Чтобы не происходил переход по ссылке
}



// Уведомление после сохранения/редактирования/добавления
function showSavedSwal(message = "Saved successfully!") {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        timer: 1800,
        timerProgressBar: true,
        showConfirmButton: false
    });
}





    </script>
	<?
	
	
	echo '
    
    
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="text-primary">
                        <i class="fas fa-question-circle me-2"></i>
                        ' . $lang->faq['faqtitle'] . '
                    </h3>
                    <div>
                        ' . jumpbutton($where) . '
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body p-0">
                        <form method="post" action="' . $_this_script_ . '">
                            <input type="hidden" name="do" value="savedisplayorder">
                            
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
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
                    ' . $faq['name'] . '
                </a>
            </td>
            <td class="text-center">
                <input type="number" 
                       class="form-control form-control-sm text-center" 
                       name="disporder[' . $faq['id'] . ']" 
                       value="' . $faq['disporder'] . '" 
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
                    <a href="#" 
   onclick="return confirmDeleteSwal(\'' . $_this_script_ . '&do=delete&id=' . $faq['id'] . '\')" 
   class="btn btn-outline-danger" 
   title="Delete">
    <i class="fas fa-trash"></i>
</a>

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
?>