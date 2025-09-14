<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

if (!defined('IN_ADMIN_PANEL')) 
{
    exit('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}


  function scan_image ($image)
  {
    global $_adir;
    $image = trim (file_get_contents ($_adir . $image));
    if (!$image)
    {
      return false;
    }

    if (preg_match ('#(onblur|onchange|onclick|onfocus|onload|onmouseover|onmouseup|onmousedown|onselect|onsubmit|onunload|onkeypress|onkeydown|onkeyup|onresize|alert|applet|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|layer|link|meta|object|plaintext|style|script|textarea|title)#is', $image))
    {
      return false;
    }

    return true;
  }

  function get_image_contents ($image)
  {
    global $_adir;
    $image = getimagesize ($_adir . $image);
    if (!$image)
    {
      return false;
    }

    return array ('width' => $image['0'], 'height' => $image['1'], 'mime' => $image['mime']);
  }

  if (!defined ('IN_ADMIN_PANEL'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('M_AVATARS', 'v.1.2 by xam');
  $_adir = TSDIR . '/uploads/avatars/';
  $_filetypes = array ('gif', 'jpg', 'png', 'jpeg', 'webp');
  $_avatars = array ();
  if (((strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST' AND 0 < count ($_POST['avatars'])) AND in_array ($_POST['action_type'], array ('resize', 'delete'), true)))
  {
    
	
	
	
	
$action_avatars = $_POST['avatars'] ?? [];
$action_type    = $_POST['action_type'] ?? '';


$ok = $skipped_shared = $not_found = $unlink_failed = [];
$show_swal = false;


if ($action_type == 'delete')
{



require_once INC_PATH . '/functions_upload.php';

$_adir_real = realpath($_adir) . DIRECTORY_SEPARATOR;

// Построим карту: basename(avatar) -> [user_ids...]
$map = [];
$res = $db->sql_query("SELECT id, avatar FROM users WHERE avatar <> ''");
while ($row = $db->fetch_array($res)) {
    $k = strtolower(basename($row['avatar']));
    $map[$k][] = (int)$row['id']; // поменяй на 'uid', если у тебя uid
}






foreach (array_unique($action_avatars) as $delete_avatar) {
    $base = strtolower(basename($delete_avatar));
    $ids  = $map[$base] ?? [];

    // fallback: вытащим id из имени файла avatar_123.jpg
    if (!$ids && preg_match('/_(\d+)\.(gif|jpe?g|png|webp)$/i', $base, $m)) {
        $ids = [(int)$m[1]];
    }

    // Сначала чистим профиль(и)
    if ($ids) {
        foreach ($ids as $uid) {
            $db->update_query('users', [
                'avatar'           => '',
                'avatardimensions' => '',
                'avatartype'       => ''
            ], "id=".(int)$uid); // замени id на uid при необходимости

            remove_avatars($uid); // чистка кэшей/превью, если так реализовано
        }
    } else {
        $not_found[] = $delete_avatar;
        continue;
    }

    // Если файл шарится несколькими юзерами — не трогаем физически
    if (!empty($map[$base]) && count($map[$base]) > 1) {
        $skipped_shared[] = $delete_avatar;
        continue;
    }

    // Безопасная проверка пути
    $full = $_adir . $delete_avatar;
    $real = realpath($full);
    if ($real === false || strpos($real, $_adir_real) !== 0 || !is_file($real)) {
        $not_found[] = $delete_avatar;
        continue;
    }

    if (@unlink($real)) {
        $ok[] = $delete_avatar;
    } else {
        $unlink_failed[] = $delete_avatar;
    }
}



// >>> ВСТАВЬ СЮДА: флаг, что есть что показать в Swal
$show_swal = !empty($ok) || !empty($unlink_failed) || !empty($skipped_shared) || !empty($not_found);


}

// при желании — выведи флеши
// flash_success("Удалено: ".implode(', ', $ok));
// flash_warning("Пропущены (shared): ".implode(', ', $skipped_shared));
// flash_info("Не найдены: ".implode(', ', $not_found));
// flash_error("Не удалось удалить: ".implode(', ', $unlink_failed));

	
	
	
	
	
	
	
	
	
	
    else
    {
      if ($action_type == 'resize')
      {
        require INC_PATH . '/readconfig_forumcp.php';
        $width = $f_avatar_maxwidth;
        $height = $f_avatar_maxheight;
        foreach ($action_avatars as $filename)
        {
          $exti = get_extension ($filename);
          $filename = $_adir . $filename;
          list ($width_orig, $height_orig) = getimagesize ($filename);
          $ratio_orig = $width_orig / $height_orig;
          if ($ratio_orig < $width / $height)
          {
            $width = $height * $ratio_orig;
          }
          else
          {
            $height = $width / $ratio_orig;
          }

          $image_p = imagecreatetruecolor ($width, $height);
          if ($exti == 'jpg')
          {
            $image = imagecreatefromjpeg ($filename);
          }
          else
          {
            if ($exti == 'gif')
            {
              $image = imagecreatefromgif ($filename);
            }
            else
            {
              if ($exti == 'png')
              {
                $image = imagecreatefrompng ($filename);
              }
            }
          }

          imagecopyresampled ($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
          ob_start ();
          if ($exti == 'jpg')
          {
            imagejpeg ($image_p, null, 100);
          }
          else
          {
            if ($exti == 'gif')
            {
              imagegif ($image_p);
            }
            else
            {
              if ($exti == 'png')
              {
                imagepng ($image_p);
              }
            }
          }

          $image = ob_get_contents ();
          ob_end_clean ();
          $fp = fopen ($filename, 'w');
          fwrite ($fp, $image);
          fclose ($fp);
        }
      }
    }
  }




// === LOAD AVATAR FILES ===
if ($handle = opendir($_adir)) 
{
    while (false !== ($file = readdir($handle))) 
	{
        if ($file !== '.' && $file !== '..' && in_array(get_extension($file), $_filetypes, true)) 
		{
            $_avatars[] = $file;
        }
    }
    closedir($handle);
}



// Сортируем и делаем пагинацию
natsort($_avatars);
$_avatars = array_values($_avatars); // переиндексация после sort

$per_page = 20;
$total    = count($_avatars);
$page     = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$pages    = max(1, (int)ceil($total / $per_page));
if ($page > $pages) { $page = $pages; }

$offset        = ($page - 1) * $per_page;
$avatars_page  = array_slice($_avatars, $offset, $per_page);




// === LOAD USERS with avatars ===
$avatar_to_user = [];

$sql = "SELECT id, username, usergroup, avatar
        FROM users
        WHERE avatar <> '' AND avatar REGEXP '\\.(gif|jpe?g|png|webp)$'";
$res = $db->sql_query($sql);

while ($u = $db->fetch_array($res)) 
{
    $key = strtolower(basename($u['avatar'])); // нормализиране на ключа
    $avatar_to_user[$key] = '<a href="' . htmlspecialchars($BASEURL) . '/'.get_profile_link($u['id']) . '">'
        . format_name($u['username'], $u['usergroup']) . '</a>';
}






// === PAGE HEADER ===
stdhead('Manage Avatars - ' . M_AVATARS);


echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

?>

<style>
.card {
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
.card.border-primary {
    box-shadow: 0 0 12px 3px rgba(13, 110, 253, 0.5);
}
</style>

<div class="container-md my-4">



<?php if ($show_swal): ?>
<script>
Swal.fire({
  title: 'Done',
  icon: 'success',
  html: `
    <div class="text-start">
      <div><b>Deleted:</b> <?= count($ok) ?></div>
      <?php if (!empty($ok)): ?><small><?= implode(', ', array_map('htmlspecialchars', $ok)) ?></small><br><?php endif; ?>

      <div class="mt-2"><b>Skipped (shared):</b> <?= count($skipped_shared) ?></div>
      <?php if (!empty($skipped_shared)): ?><small><?= implode(', ', array_map('htmlspecialchars', $skipped_shared)) ?></small><br><?php endif; ?>

      <div class="mt-2"><b>Not Found:</b> <?= count($not_found) ?></div>
      <?php if (!empty($not_found)): ?><small><?= implode(', ', array_map('htmlspecialchars', $not_found)) ?></small><br><?php endif; ?>

      <div class="mt-2 text-danger"><b>Cant Deleted:</b> <?= count($unlink_failed) ?></div>
      <?php if (!empty($unlink_failed)): ?><small><?= implode(', ', array_map('htmlspecialchars', $unlink_failed)) ?></small><?php endif; ?>
    </div>
  `,
  confirmButtonText: 'Ок'
});
</script>
<?php endif; ?>








    <div class="card shadow-sm">
       <div class="card-header bg-light text-dark fw-bold fs-4 d-flex align-items-center justify-content-between">


            Manage Avatars
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="select_all" onchange="toggleAll(this.checked)">
                <label class="form-check-label fw-normal" for="select_all">Select All</label>
            </div>
        </div>
        <form method="post" action="<?= $_this_script_; ?>&p=<?= (int)$page; ?>">
            <div class="card-body">
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
                    
					
					<?php foreach ($avatars_page as $avatar): // <-- было $_avatars ?>
                <?php
                $_exp = explode('_', preg_replace('/\.(gif|jpg|jpeg|png|webp)$/i', '', $avatar));
                $_userid = isset($_exp[1]) ? (int)$_exp[1] : 0;

                $_ad = get_image_contents($avatar);
                $passed = scan_image($avatar) ? '<span class="text-success fw-bold">Passed</span>' : '<span class="text-danger fw-bold">Possible Hack!</span>';
                $size = file_exists($_adir . $avatar) ? mksize(filesize($_adir . $avatar)) : 'Unknown size';

                $key = strtolower(basename($avatar));
                $owner = $avatar_to_user[$key] ?? '<em>Unknown</em>';

                $cardId = 'card_' . md5($avatar);
                $checkboxId = 'avatar_' . md5($avatar);
               
                    
					
					
					?>
                    <div class="col">
                        <div class="card h-100 shadow-sm" id="<?= $cardId; ?>">
                            
							
							<img  src="<?= htmlspecialchars($BASEURL . '/uploads/avatars/' . $avatar); ?>"class="rounded img-fluid" alt="Avatar <?= htmlspecialchars($avatar); ?>"style="width:120px; object-fit:cover;">
                            
							
							<div class="card-body">
                                <h5 class="card-title small text-truncate"><?= htmlspecialchars($avatar); ?></h5>
                                <p class="card-text mb-1">
                                    Size: <?= $size; ?><br>
                                    Dimensions: <?= $_ad ? $_ad['width'] . 'x' . $_ad['height'] : 'N/A'; ?><br>
                                    Scan: <?= $passed; ?><br>
                                    Owner: <?= $owner; ?>
                                </p>
                            </div>
                            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                                <div>
                                    <input class="form-check-input" type="checkbox" name="avatars[]" id="<?= $checkboxId; ?>" value="<?= htmlspecialchars($avatar); ?>" onchange="toggleCardBorder('<?= $cardId; ?>', this.checked)">
                                    <label class="form-check-label" for="<?= $checkboxId; ?>">Select</label>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showImage('<?= htmlspecialchars($BASEURL . '/uploads/avatars/' . $avatar); ?>', '<?= htmlspecialchars($avatar); ?>')">Preview</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                <div>
                    <select name="action_type" class="form-select form-select-sm d-inline-block w-auto" required>
                        <option value="" disabled selected>Choose action</option>
                        <option value="resize">Resize Selected</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                    <button type="submit" value="do it" class="btn btn-primary btn-sm ms-2">Apply</button>
                </div>
                
				<small class="text-muted">
                Total Avatars: <?= (int)$total; ?> • Showing <?= (int)$from; ?>–<?= (int)$to; ?> • Page <?= (int)$page; ?>/<?= (int)$pages; ?>
                </small>
				
				
            </div>
        </form>	
		
    </div>
</div>








<style>
.pagination .page-link {
    margin: 0 2px;
    min-width: 38px;
    text-align: center;
    color: #495057;
    border: 1px solid #dee2e6;
    transition: all 0.3s ease;
}

.pagination .page-link:hover {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    color: #0d6efd;
}

.pagination .page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
}

.pagination .page-item.disabled .page-link {
    color: #6c757d;
    pointer-events: none;
    background-color: #f8f9fa;
}

.pagination .page-link[aria-label="Previous"],
.pagination .page-link[aria-label="Next"] {
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}
</style>



<?php if ($pages > 1): ?>
<nav aria-label="Avatars pagination" class="mt-4">
  <ul class="pagination justify-content-center mb-0">
    <!-- Previous Button -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link rounded-start" href="<?= $page <= 1 ? '#' : htmlspecialchars($_this_script_ . '&p=' . ($page - 1)); ?>" aria-label="Previous">
        <span aria-hidden="true">&laquo;</span>
        <span class="ms-1 d-none d-sm-inline">Previous</span>
      </a>
    </li>

    <?php
    $window = 2;
    $last_printed = 0;
    
    for ($i = 1; $i <= $pages; $i++) {
        if ($i <= 2 || $i > $pages - 2 || abs($i - $page) <= $window) {
            // Add ellipsis if there's a gap
            if ($i - $last_printed > 1 && $last_printed > 0) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            
            $active = $i === $page ? 'active' : '';
            echo '<li class="page-item ' . $active . '">';
            echo '<a class="page-link" href="' . htmlspecialchars($_this_script_ . '&p=' . $i) . '">' . $i . '</a>';
            echo '</li>';
            
            $last_printed = $i;
        }
    }
    ?>

    <!-- Next Button -->
    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
      <a class="page-link rounded-end" href="<?= $page >= $pages ? '#' : htmlspecialchars($_this_script_ . '&p=' . ($page + 1)); ?>" aria-label="Next">
        <span class="me-1 d-none d-sm-inline">Next</span>
        <span aria-hidden="true">&raquo;</span>
      </a>
    </li>
  </ul>
</nav>
<?php endif; ?>




















<!-- Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0">
     <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="avatarModalLabel">Avatar Preview</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" id="modalImage" alt="Avatar Preview" class="img-fluid rounded shadow-sm" style="max-height: 70vh;">
      </div>
    </div>
  </div>
</div>

<script>
function toggleCardBorder(cardId, isChecked) {
    const card = document.getElementById(cardId);
    if (card) {
        card.classList.toggle('border-primary', isChecked);
    }
}

function toggleAll(checked) {
    const checkboxes = document.querySelectorAll('input[name="avatars[]"]');
    checkboxes.forEach(cb => {
        cb.checked = checked;
        const cardId = cb.closest('.card').id;
        toggleCardBorder(cardId, checked);
    });
}

function showImage(src, title) {
    const modal = new bootstrap.Modal(document.getElementById('avatarModal'));
    const modalTitle = document.getElementById('avatarModalLabel');
    const modalImage = document.getElementById('modalImage');
    modalTitle.textContent = title;
    modalImage.src = src;
    modal.show();
}
</script>

<?php stdfoot(); ?>
