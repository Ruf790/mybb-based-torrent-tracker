<?



$rootpath = './../';
require_once $rootpath . 'global.php';

$SmilieDir = $rootpath . $pic_base_url . 'smilies';



// Добавление нового смайлика
if ($_GET['action'] == 'add_smilie') {
    if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST') {
        $stitle = htmlspecialchars_uni($_POST['stitle']);
        $stext = htmlspecialchars_uni($_POST['stext']);
        $spath = htmlspecialchars_uni($_POST['spath']);
        $sorder = 0 + $_POST['sorder'];
        
        if ((!$stitle || !$stext) || !$spath) {
            $error = '<div class="alert alert-danger">Пожалуйста, заполните все обязательные поля!</div>';
        } else {
            if (!file_exists($SmilieDir . '/' . $spath)) {
                $error = '<div class="alert alert-danger">Этот смайлик не существует!</div>';
            } else {
                $db->sql_query('INSERT INTO ts_smilies (stitle, stext, spath, sorder) VALUES (' . $db->sqlesc($stitle) . ', ' . $db->sqlesc($stext) . ', ' . $db->sqlesc($spath) . ', \'' . $sorder . '\')');
                $cache->update_smilies();
                $message = '<div class="alert alert-success">Смайлик успешно добавлен!</div>';
                header('Location: '.$_this_script_.'&action=manage_smilies&message=' . urlencode($message));
                exit();
            }
        }
    }

     stdhead('Добавить смайлик');
	 
	 
	echo '<div class="container mt-3">';
    
    echo '
    <form method="POST" action="'.$_this_script_.'&action=add_smilie" class="smilie-form">
    <td>
        ' . ($error ? $error : '') . '
        
        <div class="form-group">
            <label for="stitle">Title:</label>
            <input type="text" class="form-control" id="stitle" name="stitle" value="' . ($stitle ? $stitle : '') . '" required>
        </div>
        
        <div class="form-group">
            <label for="stext">Text to Replace</label>
            <input type="text" class="form-control" id="stext" name="stext" value="' . ($stext ? $stext : '') . '" required>
        </div>
        
        <div class="form-group">
            <label for="spath">Image:</label>
            <div class="input-group">
                <input type="text" class="form-control" id="spath" name="spath" value="' . ($spath ? $spath : '') . '" required>
                <div class="input-group-append">
                    <span class="input-group-text"><img src="' . $SmilieDir . '/' . ($spath ? $spath : 'blank.png') . '" alt="' . ($stitle ? $stitle : 'Предпросмотр') . '" class="smilie-preview" id="smilie-preview"></span>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label for="sorder">Display Order:</label>
            <input type="number" class="form-control" id="sorder" name="sorder" value="' . ($sorder ? $sorder : 1) . '" min="0">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            <a href="'.$_this_script_.'&action=manage_smilies" class="btn btn-secondary"><i class="fas fa-times"></i> Отмена</a>
        </div>
    </td>            
    </form>
    
    <script>
    document.getElementById("spath").addEventListener("input", function() {
        var preview = document.getElementById("smilie-preview");
        preview.src = "' . $SmilieDir . '/" + this.value;
        preview.alt = document.getElementById("stitle").value || "Предпросмотр";
    });
    </script>
    ';
    
    echo '</div>';
	
    stdfoot();;
    exit();
}

// Редактирование смайлика
if ($_GET['action'] == 'edit_smilie') 
{
    $sid = intval($_GET['sid']);
    $query = $db->sql_query('SELECT stitle, stext, spath, sorder FROM ts_smilies WHERE sid = \'' . $sid . '\'');
    
    if ($db->num_rows($query) == 0) {
        exit('<div class="alert alert-danger">Неверный ID смайлика!</div>');
    }

    $sarray = mysqli_fetch_assoc($query);
    
    if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST') {
        $stitle = htmlspecialchars_uni($_POST['stitle']);
        $stext = htmlspecialchars_uni($_POST['stext']);
        $spath = htmlspecialchars_uni($_POST['spath']);
        $sorder = 0 + $_POST['sorder'];
        
        if ((!$stitle || !$stext) || !$spath) 
		{
            $error = '<div class="alert alert-danger">Пожалуйста, заполните все обязательные поля!</div>';
        } 
		else 
		{
            if (!file_exists($SmilieDir . '/' . $spath)) 
			{
                $error = '<div class="alert alert-danger">Этот смайлик не существует!</div>';
            } 
			else 
			{
                $db->sql_query('UPDATE ts_smilies SET stitle = ' . $db->sqlesc($stitle) . ', stext = ' . $db->sqlesc($stext) . ', spath = ' . $db->sqlesc($spath) . ', sorder = \'' . $sorder . '\' WHERE sid = \'' . $sid . '\'');
                $cache->update_smilies();
                $message = '<div class="alert alert-success">Смайлик успешно обновлен!</div>';
                header('Location: '.$_this_script_.'&action=manage_smilies&message=' . urlencode('Смайлик успешно обновлен!'));

			
				
				
            }
        }
    }

   stdhead('Редактировать смайлик');
   
   echo '<div class="container mt-3">';
    
    echo '
    <form method="POST" action="'.$_this_script_.'&action=edit_smilie&sid=' . $sid . '" class="smilie-form">
    <td>
        ' . ($error ? $error : '') . '
        
        <div class="form-group">
            <label for="stitle">Название:</label>
            <input type="text" class="form-control" id="stitle" name="stitle" value="' . ($stitle ? $stitle : $sarray['stitle']) . '" required>
        </div>
        
        <div class="form-group">
            <label for="stext">Текст для замены:</label>
            <input type="text" class="form-control" id="stext" name="stext" value="' . ($stext ? $stext : $sarray['stext']) . '" required>
        </div>
        
        <div class="form-group">
            <label for="spath">Изображение:</label>
            <div class="input-group">
                <input type="text" class="form-control" id="spath" name="spath" value="' . ($spath ? $spath : $sarray['spath']) . '" required>
                <div class="input-group-append">
                    <span class="input-group-text"><img src="' . $SmilieDir . '/' . ($spath ? $spath : $sarray['spath']) . '" alt="' . ($stitle ? $stitle : $sarray['stitle']) . '" class="smilie-preview" id="smilie-preview"></span>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label for="sorder">Порядок отображения:</label>
            <input type="number" class="form-control" id="sorder" name="sorder" value="' . ($sorder ? $sorder : $sarray['sorder']) . '" min="0">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button>
            <a href="'.$_this_script_.'&action=manage_smilies" class="btn btn-secondary"><i class="fas fa-times"></i> Отмена</a>
        </div>
    </td>            
    </form>
    
    <script>
    document.getElementById("spath").addEventListener("input", function() {
        var preview = document.getElementById("smilie-preview");
        preview.src = "' . $SmilieDir . '/" + this.value;
        preview.alt = document.getElementById("stitle").value || "' . $sarray['stitle'] . '";
    });
    </script>
    ';
    
   
    echo '</div>';
   
    stdfoot();;
    exit();
}

// Удаление смайлика
if ($_GET['action'] == 'delete_smilie' && is_valid_id($_GET['sid'])) 
{
    $db->sql_query('DELETE FROM ts_smilies WHERE sid = ' . intval($_GET['sid']));
    $cache->update_smilies();
    $message = '<div class="alert alert-success">Смайлик успешно удален!</div>';
    header('Location: '.$_this_script_.'&action=manage_smilies&message=' . urlencode($message));
    exit();
}

// Обновление порядка смайликов
if ($_GET['action'] == 'update_smilies_order') 
{
    if (is_array($_POST['sorder'])) 
	{
        foreach ($_POST['sorder'] as $sid => $sorder) 
		{
            if (is_valid_id($sid)) 
			{
                $sorder = 0 + $sorder;
                $db->sql_query('UPDATE ts_smilies SET sorder = \'' . $sorder . '\' WHERE sid = \'' . $sid . '\'');
            }
        }
        $cache->update_smilies();
        //$message = '<div class="alert alert-success">Порядок смайликов успешно обновлен!</div>';
        header('Location: '.$_this_script_.'&action=manage_smilies&message=' . urlencode('Порядок смайликов успешно обновлен!'));
        exit();
    }
}



?>
<style>

.smilie-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
}

.smilie-card {
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 10px; /* Уменьшил padding для компактности */
    background: #fff;
    transition: all 0.3s ease;
}

.smilie-image {
    max-width: 100%;
    max-height: 50px; /* Уменьшил высоту изображения */
}

.smilie-info h5 {
    margin: 0 0 3px 0;
    font-size: 14px; /* Уменьшил размер шрифта */
}

.smilie-order .form-control {
    width: 50px; /* Уменьшил ширину поля ввода порядка */
    padding: 2px 5px;
}

</style>

<?




// Основное управление смайликами (список)

if (!isset($_GET['action']) || $_GET['action'] == 'manage_smilies') 
{
    
	stdhead('Управление смайликами');
	
	// Показываем сообщение, если оно есть
    if (isset($_GET['message'])) {
    echo '<div class="alert alert-success text-center mt-3">'
        . htmlspecialchars(urldecode($_GET['message']))
        . '</div>';
}

    $query = $db->sql_query('SELECT sid, stitle, stext, spath, sorder FROM ts_smilies ORDER BY sorder, stitle');
    $smiliesPerRow = 4; // Изменили на 5 смайликов в ряд
    
    while ($Sa = mysqli_fetch_assoc($query)) {
        $SmilieArray[] = '
            <div class="smilie-card">
                <div class="smilie-image-container">
                    <img src="' . $SmilieDir . '/' . $Sa['spath'] . '" alt="' . $Sa['stitle'] . '" class="smilie-image">
                </div>
                <div class="smilie-info">
                    <h5>' . $Sa['stitle'] . '</h5>
                    <p><small>' . $Sa['stext'] . '</small></p>
                    <div class="smilie-actions">
                        <a href="'.$_this_script_.'&action=edit_smilie&sid=' . $Sa['sid'] . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <a href="smilies.php?action=delete_smilie&sid=' . $Sa['sid'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Вы уверены, что хотите удалить этот смайлик?\')"><i class="fas fa-trash"></i></a>
                    </div>
                    <div class="smilie-order">
                        <label>Order:</label>
                        <input type="number" class="form-control form-control-sm" name="sorder[' . $Sa['sid'] . ']" value="' . $Sa['sorder'] . '" min="0">
                    </div>
                </div>
            </div>
        ';
    }

    echo '<div class="container mt-3">';
    
    echo '
    <form method="POST" action="'.$_this_script_.'&action=update_smilies_order">
    <td>
        <div class="smilie-header-actions">
            <a href="'.$_this_script_.'&action=add_smilie" class="btn btn-success"><i class="fas fa-plus"></i> Add New Smilie</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Display Order</button>
        </div>
        
        <div class="smilie-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));">';
        // Уменьшил минимальную ширину карточки с 250px до 180px для 5 колонок
    
    foreach ($SmilieArray as $smilie) 
	{
        echo $smilie;
    }
    
    echo '
        </div>
    </td>
    </form>
    ';
    
    echo '</div>';
	
    stdfoot();
	
    exit();
}