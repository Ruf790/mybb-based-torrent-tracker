<?php
require_once('global.php');






$isAjax = isset($_POST['ajax']) && $_POST['ajax'] == '1';


ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $errors = [];
    if (empty($_POST['name'])) {
        $errors[] = 'The name cannot be empty';
    }
    if (empty($_POST['descr'])) {
        $errors[] = 'The name cannot be empty';
    }

    
    if (!empty($errors) && $isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => implode(', ', $errors)
        ]);
        ob_end_flush();
        exit;
    }

    // Check torent id  $torrent['id']
    if (!isset($torrent['id']) || empty($torrent['id'])) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No Torrent ID'
            ]);
            ob_end_flush();
            exit;
        } else {
            die('No Torrent ID');
        }
    }

    // Basic data
    $id = intval($torrent['id']);
    $name = htmlspecialchars($_POST['name']);
    $descr = htmlspecialchars($_POST['descr']);
    $t_image_file = $_FILES['t_image_file'] ?? [];
    $t_image_file2 = $_FILES['t_image_file2'] ?? [];
    $t_image_url = isset($_POST['t_image_url']) ? htmlspecialchars($_POST['t_image_url']) : '';
    $t_image_url2 = isset($_POST['t_image_url2']) ? htmlspecialchars($_POST['t_image_url2']) : '';
    
	
	$t_link = TS_Global('t_link');
	
    $category = intval($_POST['category'] ?? 0);
    $free = isset($_POST['free']) && $_POST['free'] == 'yes' ? 'yes' : 'no';
    $silver = isset($_POST['silver']) && $_POST['silver'] == 'yes' ? 'yes' : 'no';
    $doubleupload = isset($_POST['doubleupload']) && $_POST['doubleupload'] == 'yes' ? 'yes' : 'no';
    $allowcomments = isset($_POST['allowcomments']) && $_POST['allowcomments'] == 'no' ? 'no' : 'yes';
    $sticky = isset($_POST['sticky']) && $_POST['sticky'] == 'yes' ? 'yes' : 'no';
    $isrequest = isset($_POST['isrequest']) && $_POST['isrequest'] == 'yes' ? 'yes' : 'no';
    $isnuked = isset($_POST['isnuked']) && $_POST['isnuked'] == 'yes' ? 'yes' : 'no';
    $WhyNuked = isset($_POST['WhyNuked']) ? htmlspecialchars($_POST['WhyNuked']) : '';

    
    $UpdateSet = [
        'name' => $db->escape_string($name),
        'descr' => $db->escape_string($descr),
        'category' => $db->escape_string($category),
        'free' => $free,
        'silver' => $silver,
        'doubleupload' => $doubleupload,
        'allowcomments' => $allowcomments,
        'sticky' => $sticky,
        'isrequest' => $isrequest,
        'isnuked' => $isnuked,
        'WhyNuked' => $isnuked == 'yes' ? $db->escape_string($WhyNuked) : ''
    ];

   
    
	if (!empty($t_image_file)) 
         {
            if (((( 0 < $t_image_file['size'] AND $t_image_file['error'] === 0 ) AND $t_image_file['tmp_name']) AND $t_image_file['name'])) 
			{
               $t_image_url = fix_url($t_image_file['name']);
               $AllowedFileTypes = array('jpeg', 'jpg', 'gif', 'png', 'webp');
               $ImageExt = get_extension($t_image_url);

               if (in_array($ImageExt, $AllowedFileTypes, true)) 
			   {
                  $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
                  $ImageDetails = getimagesize($t_image_file['tmp_name']);

                  if (( $ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true ))) 
				  {
                     if ($ImageContents = file_get_contents($t_image_file['tmp_name'])) 
					 {
                        $NewImageURL = $torrent_dir . '/images/' . $id . '.' . $ImageExt;

                        if (file_exists($NewImageURL)) 
						{
                           @unlink($NewImageURL);
                        }


                        if (file_put_contents($NewImageURL, $ImageContents)) 
						{
                           $COVERIMAGEUPDATED = true;
                           
						   $update_image2 = array(
			                 "t_image" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                   );
						
						   $db->update_query("torrents", $update_image2, "id='{$id}'");
						   $cache->update_torrents();
						   
						   
                        }
                     }
                  }
               }
            }
         }
		 
		 
		 
	     if (!empty($t_image_file2)) 
         {
            if (((( 0 < $t_image_file2['size'] AND $t_image_file2['error'] === 0 ) AND $t_image_file2['tmp_name']) AND $t_image_file2['name'])) 
			{
               $t_image_url2 = fix_url($t_image_file2['name']);
               $AllowedFileTypes = array('jpeg', 'jpg', 'gif', 'png', 'webp');
               $ImageExt = get_extension( $t_image_url2 );

               if (in_array($ImageExt, $AllowedFileTypes, true)) 
			   {
                  $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
                  $ImageDetails = getimagesize($t_image_file2['tmp_name']);

                  if (( $ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true))) 
				  {
                     if ($ImageContents = file_get_contents( $t_image_file2['tmp_name'] )) 
					 {
                        $NewImageURL = $torrent_dir . '/images/' . $id . '_2.' . $ImageExt;

                        if (file_exists($NewImageURL)) 
						{
                           @unlink($NewImageURL);
                        }


                        if (file_put_contents($NewImageURL, $ImageContents)) 
						{
                           $COVERIMAGEUPDATED = true;
                           
						   $update_image22 = array(
			                 "t_image2" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                   );
						
						   $db->update_query("torrents", $update_image22, "id='{$id}'");
						   $cache->update_torrents();
						   
						   
                        }
                     }
                  }
               }
            }
         }	
		 
		 
		 
		 if (!empty($t_image_url)) 
         {
            $t_image_url = fix_url($t_image_url);
            $AllowedFileTypes = array('jpg', 'gif', 'png', 'webp');
            $ImageExt = get_extension($t_image_url);

            if (in_array($ImageExt, $AllowedFileTypes, true)) 
			{
               $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
               $ImageDetails = getimagesize($t_image_url);

               if (($ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true) )) 
			   {
                  include_once(INC_PATH . '/functions_ts_remote_connect.php');

                  if ($ImageContents = TS_Fetch_Data($t_image_url, false)) 
				  {
                     $NewImageURL = $torrent_dir . '/images/' . $id . '.' . $ImageExt;

                     if (file_exists($NewImageURL)) 
					 {
                        @unlink($NewImageURL);
                     }


                     if (file_put_contents($NewImageURL, $ImageContents)) 
					 {
                        $COVERIMAGEUPDATED = true;
                       
						$update_image = array(
			                 "t_image" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                );
						
						$db->update_query("torrents", $update_image, "id='{$id}'");
						$cache->update_torrents();
								
                     }
                  }
               }
            }
         }




        if (!empty($t_image_url2)) 
         {
            $t_image_url2 = fix_url($t_image_url2);
            $AllowedFileTypes = array('jpg', 'gif', 'png', 'webp');
            $ImageExt = get_extension($t_image_url2);

            if (in_array($ImageExt, $AllowedFileTypes, true)) 
			{
               $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
               $ImageDetails = getimagesize($t_image_url2);

               if (($ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true) )) 
			   {
                  include_once(INC_PATH . '/functions_ts_remote_connect.php');

                  if ($ImageContents = TS_Fetch_Data($t_image_url2, false)) 
				  {
                     $NewImageURL = $torrent_dir . '/images/' . $id . '_2.' . $ImageExt;

                     if (file_exists($NewImageURL)) 
					 {
                        @unlink($NewImageURL);
                     }


                     if (file_put_contents($NewImageURL, $ImageContents)) 
					 {
                        $COVERIMAGEUPDATED = true;
                       
						$update_image23 = array(
			                 "t_image2" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                );
						
						$db->update_query("torrents", $update_image23, "id='{$id}'");
						$cache->update_torrents();
								
                     }
                  }
               }
            }
         }
		 
	
       	if (empty($t_image_url)) 
        {
     
			$image_types = array ('gif', 'jpg', 'jpeg', 'png', 'webp');
            foreach ($image_types as $image)
            {
               if (@file_exists (TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image))
               {
                  @unlink (TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image);
                  continue;
               }
            }
			
			$UpdateSet['t_image'] = '';
        }
		 
		if (empty($t_image_url2)) 
        {
            
			$image_types2 = array ('gif', 'jpg', 'jpeg', 'png', 'webp');
            foreach ($image_types2 as $image2)
            {
               if (@file_exists (TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image2))
               {
                 @unlink (TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image2);
                 continue;
               }
            }
			
			$UpdateSet['t_image2'] = '';
        }
		
		
		

    
if (!empty($t_link)) {
    
    if (preg_match('@^https:\/\/www\.imdb\.com\/title\/(.*)\/$@isU', $t_link, $result)) {
        if ($result[0]) {
            $t_link = $result[0];
            include_once(INC_PATH . '/ts_imdb.php');
            $Update_tlink = array(
                "t_link" => $db->escape_string($t_link),
                "tags" => $db->escape_string($Genre ?? '')
            );
            $db->update_query("torrents", $Update_tlink, "id='{$id}'");
            unset($result);
        }
    } else {
        
        $Update_tlink = array(
            "t_link" => '',
            "tags" => ''
        );
        $db->update_query("torrents", $Update_tlink, "id='{$id}'");
    }
} else {
    
    $Update_tlink = array(
        "t_link" => '',
        "tags" => ''
    );
    $db->update_query("torrents", $Update_tlink, "id='{$id}'");
}
	
	
	
	
	
	

    
    $res = $db->update_query('torrents', $UpdateSet, "id='{$id}'");
    if ($res) {
        $cache->update_torrents();
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Data is Updated',
                'updatedData' => [
                    'name' => $name,
                    'descr' => $descr,
                    't_image' => $UpdateSet['t_image'] ?? ($torrent['t_image'] ?? ''),
                    't_image2' => $UpdateSet['t_image2'] ?? ($torrent['t_image2'] ?? ''),
                    'category' => $category
                ]
            ]);
        } else {
            header("Location: ?id=" . $id);
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error Update Data'
            ]);
        } else {
            die('Error Update Data');
        }
    }
    ob_end_flush();
    exit;
}


if ($isAjax && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    ob_end_flush();
    exit;
}




?>

<script>
function submitForm(event) {
    event.preventDefault();
    
    const submitBtn = document.getElementById('insert');
    const originalText = submitBtn.value;
    submitBtn.disabled = true;
    submitBtn.value = 'Update...';
    
    const formData = new FormData(document.getElementById('insert_form'));
    formData.append('id', '<?php echo htmlspecialchars($torrent["id"] ?? ""); ?>');
    formData.append('ajax', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            showNotification('Data updated successfully!', 'success');
            
            updatePageContent(data.updatedData);
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('add_data_Modal'));
                if (modal) modal.hide();
                // Reload page after 2 sec
                setTimeout(() => location.reload(), 2000);
            }, 1000);
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showNotification('Error сети: ' + error.message, 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.value = originalText;
    });
    
    return false;
}

function updatePageContent(updatedData) {
    
    if (updatedData.name) {
        const titleElements = document.querySelectorAll('.torrent-title, [data-torrent-name]');
        titleElements.forEach(el => el.textContent = updatedData.name);
    }
    
    if (updatedData.descr) {
        const descElements = document.querySelectorAll('.torrent-description, [data-torrent-descr]');
        descElements.forEach(el => el.textContent = updatedData.descr);
    }
    
    
}

function showNotification(message, type) {
    
    document.querySelectorAll('.ajax-notification').forEach(el => el.remove());
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed ajax-notification`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

function previewURLImage(url) {
    const preview = document.getElementById('urlImagePreview');
    if (url && url.length > 10) {
        preview.src = url;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

function readFileImage(input) {
    const preview = document.getElementById('fileImagePreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function previewURLImage2(url) {
    const preview = document.getElementById('urlImagePreview2');
    if (url && url.length > 10) {
        preview.src = url;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

function readFileImage2(input) {
    const preview = document.getElementById('fileImagePreview2');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function ChangeBox(value) {
    for (let i = 1; i <= 4; i++) {
        const element = document.getElementById('nothingtopost' + i);
        if (element) {
            element.style.display = value === i.toString() ? 'inline' : 'none';
        }
    }
}

function ShowHideField(fieldId) {
    const field = document.getElementById(fieldId);
    const checkbox = document.querySelector('input[name="isnuked"]');
    if (field && checkbox) {
        field.style.display = checkbox.checked ? 'inline' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const existingImage = '<?php echo htmlspecialchars($torrent["t_image"] ?? ""); ?>';
    if (existingImage) {
        previewURLImage(existingImage);
    }
    
    const existingImage2 = '<?php echo htmlspecialchars($torrent["t_image2"] ?? ""); ?>';
    if (existingImage2) {
        previewURLImage2(existingImage2);
    }
    
    
    const defaultRadio = document.querySelector('input[name="nothingtopost"][checked="checked"]');
    if (defaultRadio) {
        ChangeBox(defaultRadio.value);
    }
    
    
    const nukedCheckbox = document.querySelector('input[name="isnuked"]');
    if (nukedCheckbox) {
        ShowHideField('nukereason');
    }
});
</script>



<?
$t_link = $torrent['t_link'];
if (($t_link AND preg_match( '@https:\/\/www.imdb.com\/title\/(.*)\/@isU', $t_link, $result))) 
{
    $t_link = $result['0'];
    unset($result);
}

require( INC_PATH . '/functions_category.php' );
$category = intval($torrent['category']);
$caats = ts_category_list('category', (isset($category) ? $category : ''));



?>





    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-bg: #f8f9fa;
            --dark-bg: #212529;
            --success-color: #4bb543;
        }
        
        .modal-header {
            background: linear-gradient(120deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 15px 20px;
            border-bottom: none;
        }
		
		
        
        
		.image-area5 {
            text-align: center;
            margin-top: 15px;
        }
        
        .img-thumbnail {
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .img-thumbnail:hover {
            transform: scale(1.03);
        }
        
       
        
        
        
        
        
      
      
    </style>





<!-- Modal -->
<div class="modal fade" id="add_data_Modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        
		
		
		<h5 class="modal-title" id="exampleModalLabel">
                <i class="fas fa-edit me-2"></i>Edit Torrent: <?php echo htmlspecialchars($torrent['name'] ?? ''); ?>
            </h5>
		
		
		
		
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" id="insert_form" enctype="multipart/form-data" onsubmit="return submitForm(event)">
          
		  
		 
		  
		  
		   <!-- Name -->
              <div>
                <label for="name" class="form-label">Torrent Name</label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-heading"></i></span>
                    <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($torrent['name'] ?? ''); ?>" required />
                </div>
              </div>
		  
		  
		  
          <br />
          
       
          
		  
		  
		  
		  <!-- Description -->
              <div>
                <label for="descr" class="form-label">Description</label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                    <textarea style="height: 200px; resize: none" class="form-control form-control-sm border" name="descr" id="descr" required><?php echo htmlspecialchars($torrent['descr'] ?? ''); ?></textarea>
                </div>
              </div>
			  <br />
		  
		  
		  
		  
		  
		  
		  
		  
		  
          
         
		  
		  
		
		  
		  
		  
		  
		  
		   <!-- Category -->
              <div>
                <label for="category" class="form-label">Category</label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-folder"></i></span>
                    <?php echo $caats ?? '<select name="category" class="form-control"><option value="">Select category</option></select>'; ?>
                </div>
              </div>
		  
		  
		  
		  
		  
		  
		  
		  
		  
          <br />
          
         
		  
		  <!-- IMDB Link -->
              <div>
                <label for="t_link" class="form-label">IMDB Link</label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fab fa-imdb"></i></span>
                    <input type="text" class="form-control" name="t_link" size="70" value="<?php echo htmlspecialchars($t_link ?? ''); ?>" />
                </div>
              </div>
		  
		  
		  
		  
		  
          <br />
          
          
          <!-- Image Options -->
          <div class="image-options">
            <div class="form-check">
              <input type="radio" class="form-check-input" name="nothingtopost" value="1" id="option1" onclick="ChangeBox(this.value);" checked="checked" />
              <label class="form-check-label" for="option1"><?php echo $lang->upload['cover1'] ?? 'Image URL'; ?></label>
            </div>
            <div style="display: inline;" id="nothingtopost1">
              <input type="text" class="form-control mt-3" name="t_image_url" id="t_image_url" value="<?php echo htmlspecialchars($torrent['t_image'] ?? ''); ?>" oninput="previewURLImage(this.value)" />
              <div class="image-area5 mt-2">
                <img id="urlImagePreview" src="#" alt="URL Preview" style="display:none;" class="img-thumbnail">
              </div>
            </div>
            
            <div class="form-check mt-3">
              <input type="radio" class="form-check-input" name="nothingtopost" value="2" id="option2" onclick="ChangeBox(this.value);" />
              <label class="form-check-label" for="option2"><?php echo $lang->upload['cover2'] ?? 'Upload Image'; ?></label>
            </div>
            <div style="display: none;" id="nothingtopost2">
              <input type="file" class="form-control mt-3" name="t_image_file" id="t_image_file" accept="image/*" onchange="readFileImage(this)" />
              <div class="image-area5 mt-2">
                <img id="fileImagePreview" src="#" alt="File Preview" style="display:none;" class="img-thumbnail">
              </div>
            </div>
            
            <div class="form-check mt-3">
              <input type="radio" class="form-check-input" name="nothingtopost" value="3" id="option3" onclick="ChangeBox(this.value);" />
              <label class="form-check-label" for="option3"><?php echo $lang->upload['cover3'] ?? 'Second Image URL'; ?></label>
            </div>
            <div style="display: none;" id="nothingtopost3">
              <input type="text" class="form-control mt-3" name="t_image_url2" id="t_image_url2" value="<?php echo htmlspecialchars($torrent['t_image2'] ?? ''); ?>" oninput="previewURLImage2(this.value)" />
              <div class="image-area5 mt-2">
                <img id="urlImagePreview2" src="#" alt="URL Preview" style="display:none;" class="img-thumbnail">
              </div>
            </div>
            
            <div class="form-check mt-3">
              <input type="radio" class="form-check-input" name="nothingtopost" value="4" id="option4" onclick="ChangeBox(this.value);" />
              <label class="form-check-label" for="option4"><?php echo $lang->upload['cover4'] ?? 'Upload Second Image'; ?></label>
            </div>
            <div style="display: none;" id="nothingtopost4">
              <input type="file" class="form-control mt-3" name="t_image_file2" id="t_image_file2" accept="image/*" onchange="readFileImage2(this)" />
              <div class="image-area5 mt-2">
                <img id="fileImagePreview2" src="#" alt="File Preview" style="display:none;" class="img-thumbnail">
              </div>
            </div>
          </div>
		  
		  
          
          <br />
          
          <?php if ($is_mod): ?>
          <div class="moderator-options mt-4">
            <h6 class="border-bottom pb-2"><?php echo $lang->upload['moptions'] ?? 'Moderator Options'; ?></h6>
            
            <div class="row">
              <div class="col-md-6">
                
				
				<div class="form-check">
				<div class="form-check form-switch">
                  <input type="checkbox" class="form-check-input" name="free" value="yes" id="free" <?php echo ($torrent['free'] ?? '') == 'yes' ? 'checked' : ''; ?> />
                  <label class="form-check-label" for="free"><b><?php echo $lang->upload['free1'] ?? 'Free'; ?></b>: <?php echo $lang->upload['free2'] ?? 'Enable Free'; ?></label>
                </div>
				</div>
                
                <div class="form-check mt-2">
				<div class="form-check form-switch">
                  <input type="checkbox" class="form-check-input" name="silver" value="yes" id="silver" <?php echo ($torrent['silver'] ?? '') == 'yes' ? 'checked' : ''; ?> />
                  <label class="form-check-label" for="silver"><b><?php echo $lang->upload['silver1'] ?? 'Silver'; ?></b>: <?php echo $lang->upload['silver2'] ?? 'Enable Silver'; ?></label>
                </div>
				</div>
                
                <div class="form-check mt-2">
				<div class="form-check form-switch">
                  <input type="checkbox" class="form-check-input" name="doubleupload" value="yes" id="doubleupload" <?php echo ($torrent['doubleupload'] ?? '') == 'yes' ? 'checked' : ''; ?> />
                  <label class="form-check-label" for="doubleupload"><b><?php echo $lang->upload['doubleupload1'] ?? 'Double Upload'; ?></b>: <?php echo $lang->upload['doubleupload2'] ?? 'Enable Double Upload'; ?></label>
                </div>
				</div>
              </div>
              
              <div class="col-md-6">
                <div class="form-check">
				<div class="form-check form-switch">
                  <input type="checkbox" class="form-check-input" name="allowcomments" value="no" id="allowcomments" <?php echo ($torrent['allowcomments'] ?? '') == 'no' ? 'checked' : ''; ?> />
                  <label class="form-check-label" for="allowcomments"><b><?php echo $lang->upload['allowcomments1'] ?? 'Allow Comments'; ?></b>: <?php echo $lang->upload['allowcomments2'] ?? 'Disable Comments'; ?></label>
                </div>
				</div>
                
                <div class="form-check mt-2">
				<div class="form-check form-switch">
                  <input type="checkbox" class="form-check-input" name="sticky" value="yes" id="sticky" <?php echo ($torrent['sticky'] ?? '') == 'yes' ? 'checked' : ''; ?> />
                  <label class="form-check-label" for="sticky"><b><?php echo $lang->upload['sticky1'] ?? 'Sticky'; ?></b>: <?php echo $lang->upload['sticky2'] ?? 'Make Sticky'; ?></label>
                </div>
				</div>
                
                <div class="form-check mt-2">
				<div class="form-check form-switch">
                  <input type="checkbox" class="form-check-input" name="isnuked" value="yes" id="isnuked" <?php echo ($torrent['isnuked'] ?? '') == 'yes' ? 'checked' : ''; ?> onchange="ShowHideField('nukereason');" />
                  <label class="form-check-label" for="isnuked"><b><?php echo $lang->upload['nuked1'] ?? 'Nuked'; ?></b>: <?php echo $lang->upload['nuked2'] ?? 'Mark as Nuked'; ?></label>
                </div>
				</div>
                
                <div id="nukereason" style="display:<?php echo ($torrent['isnuked'] ?? '') == 'yes' ? 'block' : 'none'; ?>; margin-top: 10px;">
                  <label for="WhyNuked"><b><?php echo $lang->upload['nreason'] ?? 'Reason'; ?></b></label>
                  <input type="text" class="form-control" name="WhyNuked" id="WhyNuked" value="<?php echo htmlspecialchars($torrent['WhyNuked'] ?? ''); ?>" />
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
          
          <div class="mt-4">
            <input type="submit" name="insert" id="insert" value="Save" class="btn btn-primary" />
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>