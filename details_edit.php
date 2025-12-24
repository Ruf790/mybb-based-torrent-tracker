<?php
// Проверяем, определены ли необходимые переменные
if (!isset($Torrent) || !is_array($Torrent)) {
    return; // Прекращаем выполнение если переменные не определены
}

// Подготавливаем данные для формы
$t_link = $Torrent['t_link'] ?? '';
if ($t_link && preg_match('@https:\/\/www\.imdb\.com\/title\/(.*)\/@isU', $t_link, $result)) {
    $t_link = $result[0];
}

// Получаем список категорий
require(INC_PATH . '/functions_category.php');
$category = intval($Torrent['category'] ?? 0);
$caats = ts_category_list('category', $category);

// Проверяем права модератора
$is_mod = $is_mod ?? false;
?>

<script>
function submitForm(event) {
    event.preventDefault();
    
    const submitBtn = document.getElementById('insert');
    const originalText = submitBtn.value;
    submitBtn.disabled = true;
    submitBtn.value = 'Update...';
    
    const formData = new FormData(document.getElementById('insert_form'));
    formData.append('id', '<?php echo htmlspecialchars($Torrent["id"] ?? ""); ?>');
    formData.append('ajax', '1');
    
    console.log('Sending edit request...');
    
    fetch('<?php echo $BASEURL; ?>/xmlhttp.php?action=edit_torrent', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response status:', response.status);
        
        // Получаем текст для отладки
        return response.text().then(text => {
            console.log('Raw response:', text.substring(0, 200)); // первые 200 символов
            
            try {
                const data = JSON.parse(text);
                return data;
            } catch (e) {
                console.error('JSON parse error:', e);
                // Если это HTML ошибка, покажем пользователю
                if (text.includes('<') && text.includes('>')) {
                    throw new Error('Server error: Please check server logs');
                } else {
                    throw new Error('Invalid server response: ' + text.substring(0, 100));
                }
            }
        });
    })
    .then(data => {
        console.log('Parsed data:', data);
        
        if (data.success) {
            showToast('Torrent updated successfully!', 'success');
            
            // Обновляем контент на странице
            updatePageContent(data.updatedData);
            
            // Закрываем модалку через 1 секунду
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('add_data_Modal'));
                if (modal) {
                    modal.hide();
                    console.log('Modal closed');
                }
                
                // Обновляем страницу через 2 секунды
                setTimeout(() => {
                    console.log('Reloading page...');
                    window.location.reload();
                }, 1000);
                
            }, 1000);
            
        } else {
            showToast('Error: ' + (data.message || 'Unknown error'), 'error');
            submitBtn.disabled = false;
            submitBtn.value = originalText;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showToast('Error: ' + error.message, 'error');
        submitBtn.disabled = false;
        submitBtn.value = originalText;
    });
    
    return false;
}

function updatePageContent(updatedData) {
    console.log('Updating page content:', updatedData);
    
    // Обновляем заголовок торрента
    if (updatedData.name) {
        const titleElements = document.querySelectorAll('.torrent-title, h1, [data-torrent-name]');
        titleElements.forEach(el => {
            if (el.textContent.includes('<?php echo htmlspecialchars($Torrent["name"] ?? ""); ?>')) {
                el.textContent = el.textContent.replace(
                    '<?php echo htmlspecialchars($Torrent["name"] ?? ""); ?>', 
                    updatedData.name
                );
            }
        });
    }
    
    // Обновляем описание
    if (updatedData.descr) {
        const descElements = document.querySelectorAll('.torrent-description, [data-torrent-descr]');
        descElements.forEach(el => {
            el.textContent = updatedData.descr;
        });
    }
    
    // Обновляем изображения если нужно
    if (updatedData.t_image) {
        const imgElements = document.querySelectorAll('img[src*="<?php echo htmlspecialchars($Torrent["t_image"] ?? ""); ?>"]');
        imgElements.forEach(img => {
            img.src = updatedData.t_image;
        });
    }
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
    const existingImage = '<?php echo htmlspecialchars($Torrent["t_image"] ?? ""); ?>';
    if (existingImage) {
        previewURLImage(existingImage);
    }
    
    const existingImage2 = '<?php echo htmlspecialchars($Torrent["t_image2"] ?? ""); ?>';
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

<style>
:root {
    --primary-color: #4361ee;
    --secondary-color: #3f37c9;
    --accent-color: #4cc9f0;
    --light-bg: #f8f9fa;
    --dark-bg: #212529;
    --success-color: #4bb543;
}

.modal-headers {
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
      <div class="modal-headers">
        <h5 class="modal-title" id="exampleModalLabel">
            <i class="fas fa-edit me-2"></i>Edit Torrent: <?php echo htmlspecialchars($Torrent['name'] ?? ''); ?>
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
                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($Torrent['name'] ?? ''); ?>" required />
            </div>
          </div>
          
          <br />
          
          <!-- Description -->
          <div>
            <label for="descr" class="form-label">Description</label>
            <div class="input-group mb-3">
                <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                <textarea style="height: 300px; resize: none" class="form-control form-control-sm border" name="descr" id="descr" required><?php echo htmlspecialchars($Torrent['descr'] ?? ''); ?></textarea>
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
              <div class="input-group mt-3">
                <span class="input-group-text"><i class="fas fa-link"></i></span>
                <input type="text" class="form-control" name="t_image_url" id="t_image_url" value="<?php echo htmlspecialchars($Torrent['t_image'] ?? ''); ?>" oninput="previewURLImage(this.value)" />
              </div>
              <div class="image-area5 mt-2">
                <img id="urlImagePreview" src="#" alt="URL Preview" style="display:none;" class="img-thumbnail">
              </div>
            </div>
            
            <div class="form-check mt-3">
              <input type="radio" class="form-check-input" name="nothingtopost" value="2" id="option2" onclick="ChangeBox(this.value);" />
              <label class="form-check-label" for="option2"><?php echo $lang->upload['cover2'] ?? 'Upload Image'; ?></label>
            </div>
            <div style="display: none;" id="nothingtopost2">
              <div class="input-group mt-3">
                <span class="input-group-text"><i class="fas fa-upload"></i></span>
                <input type="file" class="form-control" name="t_image_file" id="t_image_file" accept="image/*" onchange="readFileImage(this)" />
              </div>
              <div class="image-area5 mt-2">
                <img id="fileImagePreview" src="#" alt="File Preview" style="display:none;" class="img-thumbnail">
              </div>
            </div>
            
            <div class="form-check mt-3">
              <input type="radio" class="form-check-input" name="nothingtopost" value="3" id="option3" onclick="ChangeBox(this.value);" />
              <label class="form-check-label" for="option3"><?php echo $lang->upload['cover3'] ?? 'Second Image URL'; ?></label>
            </div>
            <div style="display: none;" id="nothingtopost3">
              <div class="input-group mt-3">
                <span class="input-group-text"><i class="fas fa-link"></i></span>
                <input type="text" class="form-control" name="t_image_url2" id="t_image_url2" value="<?php echo htmlspecialchars($Torrent['t_image2'] ?? ''); ?>" oninput="previewURLImage2(this.value)" />
              </div>
              <div class="image-area5 mt-2">
                <img id="urlImagePreview2" src="#" alt="URL Preview" style="display:none;" class="img-thumbnail">
              </div>
            </div>
            
            <div class="form-check mt-3">
              <input type="radio" class="form-check-input" name="nothingtopost" value="4" id="option4" onclick="ChangeBox(this.value);" />
              <label class="form-check-label" for="option4"><?php echo $lang->upload['cover4'] ?? 'Upload Second Image'; ?></label>
            </div>
            <div style="display: none;" id="nothingtopost4">
              <div class="input-group mt-3">
                <span class="input-group-text"><i class="fas fa-upload"></i></span>
                <input type="file" class="form-control" name="t_image_file2" id="t_image_file2" accept="image/*" onchange="readFileImage2(this)" />
              </div>
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
                    <input type="checkbox" class="form-check-input" name="free" value="yes" id="free" <?php echo ($Torrent['free'] ?? '') == 'yes' ? 'checked' : ''; ?> />
                    <label class="form-check-label" for="free"><b><?php echo $lang->upload['free1'] ?? 'Free'; ?></b>: <?php echo $lang->upload['free2'] ?? 'Enable Free'; ?></label>
                  </div>
                </div>
                
                <div class="form-check mt-2">
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="silver" value="yes" id="silver" <?php echo ($Torrent['silver'] ?? '') == 'yes' ? 'checked' : ''; ?> />
                    <label class="form-check-label" for="silver"><b><?php echo $lang->upload['silver1'] ?? 'Silver'; ?></b>: <?php echo $lang->upload['silver2'] ?? 'Enable Silver'; ?></label>
                  </div>
                </div>
                
                <div class="form-check mt-2">
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="doubleupload" value="yes" id="doubleupload" <?php echo ($Torrent['doubleupload'] ?? '') == 'yes' ? 'checked' : ''; ?> />
                    <label class="form-check-label" for="doubleupload"><b><?php echo $lang->upload['doubleupload1'] ?? 'Double Upload'; ?></b>: <?php echo $lang->upload['doubleupload2'] ?? 'Enable Double Upload'; ?></label>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="form-check">
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="allowcomments" value="no" id="allowcomments" <?php echo ($Torrent['allowcomments'] ?? '') == 'no' ? 'checked' : ''; ?> />
                    <label class="form-check-label" for="allowcomments"><b><?php echo $lang->upload['allowcomments1'] ?? 'Allow Comments'; ?></b>: <?php echo $lang->upload['allowcomments2'] ?? 'Disable Comments'; ?></label>
                  </div>
                </div>
                
                <div class="form-check mt-2">
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="sticky" value="yes" id="sticky" <?php echo ($Torrent['sticky'] ?? '') == 'yes' ? 'checked' : ''; ?> />
                    <label class="form-check-label" for="sticky"><b><?php echo $lang->upload['sticky1'] ?? 'Sticky'; ?></b>: <?php echo $lang->upload['sticky2'] ?? 'Make Sticky'; ?></label>
                  </div>
                </div>
                
                <div class="form-check mt-2">
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="isnuked" value="yes" id="isnuked" <?php echo ($Torrent['isnuked'] ?? '') == 'yes' ? 'checked' : ''; ?> onchange="ShowHideField('nukereason');" />
                    <label class="form-check-label" for="isnuked"><b><?php echo $lang->upload['nuked1'] ?? 'Nuked'; ?></b>: <?php echo $lang->upload['nuked2'] ?? 'Mark as Nuked'; ?></label>
                  </div>
                </div>
                
                <div id="nukereason" style="display:<?php echo ($Torrent['isnuked'] ?? '') == 'yes' ? 'block' : 'none'; ?>; margin-top: 10px;">
                  <label for="WhyNuked"><b><?php echo $lang->upload['nreason'] ?? 'Reason'; ?></b></label>
                  <input type="text" class="form-control" name="WhyNuked" id="WhyNuked" value="<?php echo htmlspecialchars($Torrent['WhyNuked'] ?? ''); ?>" />
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