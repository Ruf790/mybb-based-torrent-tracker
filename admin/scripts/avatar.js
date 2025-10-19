document.addEventListener('DOMContentLoaded', function(){
  const container = document.getElementById('avatar-container');
  if(!container) return;

  const canChange = container.dataset.canChange === '1';
  if (!canChange) return;

  const input = document.getElementById('avatar-input');
  if(!input) return;

  const overlay = container.querySelector('.avatar-overlay');
  const progressWrap = document.getElementById('avatar-progress');
  const progressBar = document.getElementById('avatar-progress-bar');
  let avatarImg = container.querySelector('img');

  // Ховер-эффект
  if (overlay) {
    container.addEventListener('mouseenter', () => overlay.style.opacity = '1');
    container.addEventListener('mouseleave', () => overlay.style.opacity = '0');
  }

  container.addEventListener('click', () => input.click());

  const MAX_MB = 22;

  function toastSuccess(msg){ 
    if(window.Swal){ 
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: msg,
        showConfirmButton: false,
        timer: 1600
      }); 
    } else { 
      alert(msg); 
    } 
  }

  function toastError(msg){ 
    if(window.Swal){ 
      Swal.fire({
        toast: true,
        position: 'top-end', 
        icon: 'error',
        title: msg,
        showConfirmButton: false,
        timer: 2200
      }); 
    } else { 
      alert(msg); 
    } 
  }

  input.addEventListener('change', function(){
    if(!this.files || !this.files[0]) return;

    const file = this.files[0];
    
    // Проверка типа файла
    if(!/\.(jpg|jpeg|png|gif|webp)$/i.test(file.name)){
      toastError('Allowed formats: JPG, JPEG, PNG, GIF, WebP');
      this.value = '';
      return;
    }
    
    // Проверка размера
    if(file.size > MAX_MB * 1024 * 1024){
      toastError('File is too big (max. ' + MAX_MB + ' MB)');
      this.value = '';
      return;
    }

    const id = container.dataset.uid;
    if(!id){ 
      toastError('Profile ID not found'); 
      return; 
    }

    const xhr = new XMLHttpRequest();
    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('id', id);
    formData.append('my_post_key', document.querySelector('input[name="my_post_key"]')?.value || '');
	
	
	
	// Отладка
console.log("Sending FormData with:");
for (let [key, value] of formData.entries()) {
    console.log(key + ":", value);
}
	
	
	

    // Используем правильный URL для админки
    xhr.open('POST', 'edituser.php?action=upload_avatar&userid=' + id, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    // Показываем прогресс
    if(progressWrap){
      progressWrap.style.display = 'block';
      if(progressBar) progressBar.style.width = '0%';
    }

    xhr.upload.onprogress = function(e){
      if(e.lengthComputable && progressBar){
        progressBar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
      }
    };

    xhr.onreadystatechange = function(){
      if(xhr.readyState === 4){
        if(progressWrap) progressWrap.style.display = 'none';
        input.value = '';
        
        if(xhr.status >= 200 && xhr.status < 300){
          try{
            const res = JSON.parse(xhr.responseText);
            if(res && res.success){
              // Обновляем аватар
              if (!avatarImg) {
                // Если нет img, создаем новый
                avatarImg = document.createElement('img');
                avatarImg.className = 'rounded img-fluid';
                container.querySelector('div').innerHTML = '';
                container.querySelector('div').appendChild(avatarImg);
              }
              // Добавляем кеш-бастер
              const newUrl = res.url + (res.url.includes('?') ? '&' : '?') + 'v=' + Date.now();
              avatarImg.src = newUrl;
              avatarImg.alt = 'Avatar';
              
              toastSuccess('Avatar updated successfully!');
            } else {
              toastError(res.error || 'Upload failed');
            }
          } catch(e){
            console.error('Parse error:', e);
            toastError('Server response error');
          }
        } else {
          toastError('Server error: ' + xhr.status);
        }
      }
    };

    xhr.send(formData);
  });
});