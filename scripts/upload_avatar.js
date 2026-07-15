document.addEventListener('DOMContentLoaded', function(){
  const container    = document.getElementById('avatar-container');
  if(!container) return;

  const canChange    = container.dataset.canChange === '1';
  if (!canChange) return; // чужой профиль и не мод — ничего не делаем

  const input        = document.getElementById('avatar-input');
  if(!input) return;

  const overlay      = container.querySelector('.avatar-overlay');
  const progressWrap = document.getElementById('avatar-progress');
  const progressBar  = document.getElementById('avatar-progress-bar');
  let   avatarImg    = container.querySelector('img');

  // Ховер-эффект, если overlay существует
  if (overlay) {
    container.addEventListener('mouseenter', ()=> overlay.style.opacity = '1');
    container.addEventListener('mouseleave', ()=> overlay.style.opacity = '0');
  }

  container.addEventListener('click', () => input.click());

  // Согласуем лимит с бэком: если на сервере 2 MB, поставь 2 здесь. Если 22 — ставь 22 и там тоже.
  const MAX_MB = 22; // <= Поставь 2 или 22 и синхронизируй с PHP

  function toastSuccess(msg){ if(window.Swal){ Swal.fire({toast:true,position:'top-end',icon:'success',title:msg,showConfirmButton:false,timer:1600}); } else { alert(msg); } }
  function toastError(msg){ if(window.Swal){ Swal.fire({toast:true,position:'top-end',icon:'error',title:msg,showConfirmButton:false,timer:2200}); } else { alert(msg); } }

  input.addEventListener('change', function(){
    if(!this.files || !this.files[0]) return;

    const file = this.files[0];
    if(!/\.(jpg|jpeg|png|gif|webp)$/i.test(file.name)){
      toastError('Allowed JPG/JPEG/PNG/GIF/WebP');
      this.value = '';
      return;
    }
    if(file.size > MAX_MB*1024*1024){
      toastError('File is to big (max. ' + MAX_MB + ' MB)');
      this.value = '';
      return;
    }

    const id = container.dataset.uid;
    if(!id){ toastError('ID profile is not found'); return; }

    const xhr = new XMLHttpRequest();
    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('id', id);

	
	
	xhr.open('POST', 'member.php?action=upload_avatar', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    if(progressWrap){
      progressWrap.style.display = 'block';
      if(progressBar) progressBar.style.width = '0%';
    }

    xhr.upload.onprogress = function(e){
      if(e.lengthComputable && progressBar){
        progressBar.style.width = Math.round((e.loaded/e.total)*100) + '%';
      }
    };

    xhr.onreadystatechange = function(){
      if(xhr.readyState === 4){
        if(progressWrap) progressWrap.style.display = 'none';
        input.value = '';
        if(xhr.status >= 200 && xhr.status < 300){
          try{
            const res = JSON.parse(xhr.responseText);
            if(res && res.ok){
              // обновляем src с bust-кешем
              if (!avatarImg) avatarImg = container.querySelector('img');
              if (avatarImg) {
                const newUrl = res.url + (res.url.includes('?') ? '&' : '?') + 'v=' + Date.now();
                avatarImg.src = newUrl;
              }
              toastSuccess('Avatar Updated');
            } else {
              toastError((res && res.error) ? res.error : 'Upload Error');
            }
          } catch(e){
            toastError('Некорректный ответ сервера');
          }
        } else {
          toastError('Ошибка сервера: ' + xhr.status);
        }
      }
    };

    xhr.send(formData);
  });
});
