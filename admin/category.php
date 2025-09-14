<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


function update_categories_cache() {
    global $db;
    $query = $db->sql_query('SELECT * FROM categories WHERE type = \'c\' ORDER by name,id');
    while ($_c = $db->fetch_array($query)) {
        $_ccache[] = $_c;
    }

    $query = $db->sql_query('SELECT * FROM categories WHERE type = \'s\' ORDER by name,id');
    while ($_c = $db->fetch_array($query)) {
        $_ccache2[] = $_c;
    }

    $content = var_export($_ccache, true);
    $content2 = var_export($_ccache2, true);
    $_filename = TSDIR . '/cache/categories.php';
    $_cachefile = @fopen('' . $_filename, 'w');
    $_cachecontents = '<?php
/** TS Generated Cache#7 - Do Not Alter
 * Cache Name: Categories
 * Generated: ' . gmdate('r') . '
*/

';
    $_cachecontents .= '' . '$_categoriesC = ' . $content . ';

';
    $_cachecontents .= '' . '$_categoriesS = ' . $content2 . ';
?>';
    @fwrite($_cachefile, $_cachecontents);
    @fclose($_cachefile);
}



function get_icons($select = '') {
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
    
    $str = '<div class="input-group mb-3">
            <input type="text" class="form-control" name="icon" value="' . htmlspecialchars($select) . '" placeholder="Введите классы иконки Font Awesome">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Выбрать</button>
            <ul class="dropdown-menu dropdown-menu-end">';
    
    foreach ($icons as $icon) {
        $str .= '<li><a class="dropdown-item" href="#" onclick="document.getElementsByName(\'icon\')[0].value=\'' . $icon . '\'; return false;">' . $icon . '</a></li>';
    }
    
    $str .= '</ul>
            </div>
            <small class="text-muted">Пример: fa-solid fa-film fa-shake</small>';
    
    return $str;
}




function get_category_list($cid = 0, $selectname = 'cid') {
    global $db;
    $categories = '<select name="' . $selectname . '" class="form-select form-select-sm border pe-5 w-auto">
    <option value="0">--select category--</option>';
    ($query = $db->sql_query('SELECT id, name FROM categories WHERE type = \'c\''));
    while ($cat = $db->fetch_array($query)) {
        $categories .= '<option value="' . intval($cat['id']) . '"' . ($cid == $cat['id'] ? ' selected="selected"' : '') . '>' . htmlspecialchars_uni($cat['name']) . '</option>';
    }

    $categories .= '</select>';
    return $categories;
}

function show__errors() {
    global $_errors;
    global $lang;
    if (0 < count($_errors)) {
        $errors = implode('<br />', $_errors);
        echo '
            <div class="container mt-3">
            <div class="card">
            <div class="card-body">
            <tr>
                <td>
                    ' . $lang->global['error'] . '
                </td>
            </tr>
            <tr>
                <td>
                    <font color="red">
                        <strong>
                            ' . $errors . '
                        </strong>
                    </font>
                </td>
            </tr>
            </div>
            </div>
            </div>
            <br />
        ';
    }
}

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

define('C_VERSION', '1.0 by xam');
$do = (isset($_POST['do']) ? htmlspecialchars($_POST['do']) : (isset($_GET['do']) ? htmlspecialchars($_GET['do']) : ''));
$what = (isset($_POST['what']) ? htmlspecialchars($_POST['what']) : (isset($_GET['what']) ? htmlspecialchars($_GET['what']) : ''));
$id = (isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : ''));
$cid = (isset($_POST['cid']) ? intval($_POST['cid']) : (isset($_GET['cid']) ? intval($_GET['cid']) : ''));
$_errors = array();

if ($do == 'new') {
    if ($what == 'save') {
        $name = htmlspecialchars_uni($_POST['name']);
        $icon = htmlspecialchars_uni($_POST['icon']);
        $cat_desc = htmlspecialchars_uni($_POST['cat_desc']);
        $minclassread = intval($_POST['minclassread']);
        $type = (0 < $cid ? 's' : 'c');
        
        if (empty($name)) {
            $_errors[] = 'Don\'t leave any fields blank!';
        } else {
            ($db->sql_query('INSERT INTO categories (name, icon, cat_desc, minclassread, type, pid) VALUES (' . $db->sqlesc($name) . ',' . $db->sqlesc($icon) . ',' . $db->sqlesc($cat_desc) . ',' . $db->sqlesc($minclassread) . ',' . $db->sqlesc($type) . ',' . $db->sqlesc($cid) . ')') OR sqlerr(__FILE__, 135));
            update_categories_cache();
            redirect('admin/index.php?act=category', 'New Category has been added!');
            exit();
        }
    }

    stdhead('Manage Tracker Categories - Add Category');
    $where = array('Cancel' => $_this_script_);
    show__errors();
    
    echo '<div class="container mt-3">            
            <div class="float-end">            
            ' . jumpbutton($where) . '            
            </div>
            </div>
            </br>
            </br>';
    
    echo '<div class="container-md">
          <div class="card border-0 mb-4">
            <div class="card-header rounded-bottom text-19 fw-bold">
                Add Category
            </div>
          </div>
        </div>';
    
    echo '<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
        <input type="hidden" name="act" value="category">
        <input type="hidden" name="do" value="new">    
        <input type="hidden" name="what" value="save">';
    
    echo '<div class="container mt-3">
        <div class="card">
        <div class="card-body">
        
        <div class="mb-3">
            <label class="form-label">Category Name</label>
            <input type="text" class="form-control" name="name" value="' . $name . '">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Category Description</label>
            <input type="text" class="form-control" name="cat_desc" value="' . $cat_desc . '">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Minimum Read Class</label>
            <input type="number" class="form-control" name="minclassread" value="0" min="0">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Sub-Category</label>
            ' . get_category_list($cid) . '
        </div>
        
        <div class="mb-3">
            <label class="form-label">Category Icon</label>
            ' . get_icons($icon) . '
        </div>
        
        <div class="card-footer text-center">
            <input type="submit" class="btn btn-primary" value="Save">
            <input type="reset" class="btn btn-primary" value="Reset Fields">
        </div>
        
        </div>
        </div>
        </div>
        </form>';
    
    stdfoot();
    exit();
} elseif ($do == 'delete') {
    if ($what == 'sure') {
        $db->sql_query('DELETE FROM categories WHERE id = ' . $db->sqlesc($id) . ' LIMIT 1');
        update_categories_cache();
        redirect('admin/index.php?act=category', 'Category has been deleted!');
    } else {
        stderr('Sanity Check', 'Are you sure you want to delete this category? <a href="' . $_this_script_ . '&do=delete&id=' . $id . '&what=sure">YES</a> / <a href="' . $_this_script_ . '">NO</a>', false);
    }
} elseif ($do == 'edit') {
    if ($what == 'save') {
        $name = htmlspecialchars_uni($_POST['name']);
        $icon = htmlspecialchars_uni($_POST['icon']);
        $cat_desc = htmlspecialchars_uni($_POST['cat_desc']);
        $minclassread = intval($_POST['minclassread']);
        $type = (0 < $cid ? 's' : 'c');
        
        if (empty($name)) {
            $_errors[] = 'Don\'t leave any fields blank!';
        } else {
            $db->sql_query('UPDATE categories SET type = \'' . $type . '\', pid = \'' . $cid . '\', 
                name = ' . $db->sqlesc($name) . ', icon = ' . $db->sqlesc($icon) . ', 
                cat_desc = ' . $db->sqlesc($cat_desc) . ', minclassread = ' . $db->sqlesc($minclassread) . ' 
                WHERE id = ' . $db->sqlesc($id));
            update_categories_cache();
            redirect('index.php?act=category', 'Category has been updated!');
            exit();
        }
    }

    $query = $db->sql_query('SELECT * FROM categories WHERE id = ' . $db->sqlesc($id));
    if ($db->num_rows($query) == 0) {
        stderr('Error', 'There is no category with this ID!');
    }

    $Result = $db->fetch_array($query);
    $categoryname = $Result['name'];
    $name = (!empty($name) ? $name : $categoryname);
    $icon = (!empty($icon) ? $icon : $Result['icon']);
    $cat_desc = (!empty($cat_desc) ? $cat_desc : $Result['cat_desc']);
    $minclassread = $Result['minclassread'];
    $type = $Result['type'];
    $pid = $Result['pid'];
    
    stdhead('Manage Tracker Categories - Edit');
    $where = array('Cancel' => $_this_script_);
    show__errors();
    
    echo '<div class="container mt-3">            
            <div class="float-end">            
            ' . jumpbutton($where) . '            
            </div>
            </div>
            </br>
            </br>';
    
    echo '<div class="container-md">
          <div class="card border-0 mb-4">
            <div class="card-header rounded-bottom text-19 fw-bold">
                Edit Category "' . $categoryname . '"
            </div>
          </div>
        </div>';
    
    echo '<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
        <input type="hidden" name="act" value="category">
        <input type="hidden" name="do" value="edit">    
        <input type="hidden" name="what" value="save">
        <input type="hidden" name="id" value="' . $id . '">';
    
    echo '<div class="container mt-3">
        <div class="card">
        <div class="card-body">
        
        <div class="mb-3">
            <label class="form-label">Category Name</label>
            <input type="text" class="form-control" name="name" value="' . $name . '">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Category Description</label>
            <input type="text" class="form-control" name="cat_desc" value="' . $cat_desc . '">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Minimum Read Class</label>
            <input type="number" class="form-control" name="minclassread" value="' . $minclassread . '" min="0">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Sub-Category</label>
            ' . get_category_list(($type == 'c' ? 0 : $pid), 'cid') . '
        </div>
        
        <div class="mb-3">
            <label class="form-label">Category Icon</label>
            ' . get_icons($icon) . '
        </div>
        
        <div class="card-footer text-center">
            <input type="submit" class="btn btn-primary" value="Save">
            <input type="reset" class="btn btn-primary" value="Reset Fields">
        </div>
        
        </div>
        </div>
        </form>';
    
    stdfoot();
    exit();
} elseif ($do == 'add_subcategory') {
    if (($what == 'save' AND is_valid_id($cid))) {
        $name = htmlspecialchars_uni($_POST['name']);
        $icon = htmlspecialchars_uni($_POST['icon']);
        $cat_desc = htmlspecialchars_uni($_POST['cat_desc']);
        $minclassread = intval($_POST['minclassread']);
        $type = 's';
        
        if (empty($name)) {
            $_errors[] = 'Don\'t leave any fields blank!';
        } else {
            $db->sql_query('INSERT INTO categories (name, icon, cat_desc, minclassread, type, pid) VALUES (' . $db->sqlesc($name) . ',' . $db->sqlesc($icon) . ',' . $db->sqlesc($cat_desc) . ',' . $db->sqlesc($minclassread) . ',' . $db->sqlesc($type) . ',' . $db->sqlesc($cid) . ')');
            update_categories_cache();
            redirect('admin/index.php?act=category', 'New Sub-Category has been added!');
            exit();
        }
    }

    $query = $db->sql_query('SELECT name FROM categories WHERE type = \'c\' AND id = ' . $db->sqlesc($cid));
    if ($db->num_rows($query) == 0) {
        stderr('Error', 'There is no category with this ID!');
    }

    $Result = $db->fetch_array($query);
    $categoryname = $Result['name'];
    
    stdhead('Manage Tracker Categories - Add Sub-Category');
    $where = array('Cancel' => $_this_script_);
    show__errors();
    echo jumpbutton($where);
    
    echo '<div class="container-md">
          <div class="card border-0 mb-4">
            <div class="card-header rounded-bottom text-19 fw-bold">
                Add Sub-Category to "' . $categoryname . '"
            </div>
          </div>
        </div>';
    
    echo '<div class="container mt-3">
        <div class="card">
        <div class="card-body">          
          
        <form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
        <input type="hidden" name="act" value="category">
        <input type="hidden" name="do" value="add_subcategory">    
        <input type="hidden" name="what" value="save">
        <input type="hidden" name="cid" value="' . $cid . '">';
    
    echo '<div class="mb-3">
            <label class="form-label">Category Name</label>
            <input type="text" class="form-control" name="name" value="' . $name . '">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Category Description</label>
            <input type="text" class="form-control" name="cat_desc" value="' . $cat_desc . '">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Minimum Read Class</label>
            <input type="number" class="form-control" name="minclassread" value="0" min="0">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Category Icon</label>
            ' . get_icons($icon) . '
        </div>
        
        <div class="card-footer text-center">
            <input type="submit" class="btn btn-primary" value="Save">
            <input type="reset" class="btn btn-primary" value="Reset Fields">
        </div>
        
        </form>
        </div>
        </div>
        </div>';
    
    stdfoot();
    exit();
}

stdhead('Manage Tracker Categories');
?> 
<script>
$(document).ready(function(){
  $('[data-toggle="tooltip"]').tooltip();   
});
</script>
<?
$where = array('Create New Category' => $_this_script_ . '&do=new');

echo '<div class="container-md">
      <div class="card border-0 mb-4">
        <div class="card-header rounded-bottom text-19 fw-bold">
            Manage Tracker Categories
        </div>
      </div>
    </div>';

echo '<div class="container mt-3">
      <div class="float-end">
       '.jumpbutton($where).'
      </div>
      </br>
      </br>

      <div class="card">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Icon</th>
            <th>Description</th>
            <th>Min Class</th>
            <th>Action</th>
          </tr>
        </thead>';

$query = $db->sql_query('SELECT * FROM categories WHERE type = \'s\'');
$subcategories = array();
while ($subcat = $db->fetch_array($query)) {
    if (0 < $subcat['pid']) {
        $subcategories[$subcat['pid']] = $subcategories[$subcat['pid']] . '<tr><td align="center">' . $subcat['name'] . ' 
            <a href="' . $BASEURL . '/browse.php?cat=' . $subcat['id'] . '">
                <i class="fa-solid fa-eye fa-sm" data-toggle="tooltip" data-placement="top" style="color: #13a479;" title="View Sub-Category"></i>
            </a>&nbsp;&nbsp;
            <a href="' . $_this_script_ . '&do=edit&id=' . $subcat['id'] . '">
                <i class="fa-solid fa-pen-to-square fa-sm" data-toggle="tooltip" data-placement="top" style="color: #0658e5;" title="Edit Sub-Category"></i>
            </a>&nbsp;&nbsp;
            <a href="' . $_this_script_ . '&do=delete&id=' . $subcat['id'] . '">
                <i class="fa-solid fa-trash-can fa-sm" data-toggle="tooltip" data-placement="top" style="color: #eb0f0f;" title="Delete Sub-Category"></i>
            </a></td></tr>';
    }
}

$query = $db->sql_query('SELECT * FROM categories WHERE type=\'c\'');
if ($db->num_rows($query) == 0) {
    echo '<tr><td colspan="6">There is no registered category yet.</td></tr>';
} else {
    while ($category = $db->fetch_array($query)) {
        echo '<tr>
            <td align="center">' . $category['id'] . '</td>
            <td align="center">
                <table width="100%"><tr><td class="tborder" align="center">Main Category</td></tr><tr><td align="center">
                <b>' . $category['name'] . '</b> <a href="' . $_this_script_ . '&amp;do=add_subcategory&amp;cid=' . $category['id'] . '">[add subcategory]</a></td></tr>
                <table width="100%"><tr><td class="subheader" align="center">Sub-categories</td></tr>' . 
                ($subcategories[$category['id']] ? $subcategories[$category['id']] : '<tr><td align="center">There is no sub-category!</td></tr>') . 
                '</table>
            </td>
            <td align="center"><i class="' . $category['icon'] . ' fa-2x"></i></td>
            <td align="left">' . $category['cat_desc'] . '</td>
            <td align="center">' . $category['minclassread'] . '</td>
            <td align="center">
                <a href="' . $BASEURL . '/browse.php?cat=' . $category['id'] . '">
                    <i class="fa-solid fa-eye fa-xl" data-toggle="tooltip" data-placement="top" style="color: #13a479;" title="View Category"></i>
                </a>&nbsp;&nbsp;
                <a href="' . $_this_script_ . '&do=edit&id=' . $category['id'] . '">
                    <i class="fa-solid fa-pen-to-square fa-xl" data-toggle="tooltip" data-placement="top" style="color: #0658e5;" title="Edit Category"></i>
                </a>&nbsp;&nbsp;
                <a href="' . $_this_script_ . '&do=delete&id=' . $category['id'] . '">
                    <i class="fa-solid fa-trash-can fa-xl" data-toggle="tooltip" data-placement="top" style="color: #eb0f0f;" title="Delete Category"></i>
                </a>
            </td>
            </tr>';
    }
}

echo '</table></div></div>';

echo '<div class="container mt-3">
        <div class="float-end">
        ' . jumpbutton($where) . '
        </div>
    </div>';

stdfoot();
?>