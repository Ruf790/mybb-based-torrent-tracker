// avatar_upload.js — единый скрипт загрузки аватарки.
// Работает и с member.php/edituser.php (разметка avatar-container/avatar-input),
// и с usercp.php (разметка avatarImage/avatarInput) — определяет, какая
// разметка есть на странице, и включает соответствующую логику. Бэкенды
// у них разные (member.php/edituser.php идут через upload_avatar(), GET-параметр
// action=upload_avatar; usercp.php — через do_avatar(), поле формы action=do_avatar,
// имя файла avatarupload), так что унификация тут не "один код на всё", а
// "один файл, две реализации, включается нужная".
//
// Тексты: window.AGS_LANG (выводит avatar_js_lang() из include/functions.php,
// ключи js_avatar_* в global.lang.php). Без него — английский fallback.

function avatarT(key, fallback, ...args) {
  const L = window.AGS_LANG || {};
  let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
  args.forEach((a, i) => {
    s = s.split('{' + (i + 1) + '}').join(a).split('%' + (i + 1) + '$s').join(a);
  });
  return s;
}

document.addEventListener('DOMContentLoaded', function () {
  const modernContainer = document.getElementById('avatar-container');
  const legacyContainer = document.getElementById('avatarImage');

  if (modernContainer) {
    initModernAvatarUpload(modernContainer);
  } else if (legacyContainer) {
    initLegacyAvatarUpload(legacyContainer);
  }
});

// ─────────────────────────────────────────────────────────────────────────
// member.php / edituser.php — разметка avatar-container / avatar-input
// ─────────────────────────────────────────────────────────────────────────
function initModernAvatarUpload(container) {
  const canChange = container.dataset.canChange === '1';
  if (!canChange) return; // чужой профиль и не мод — ничего не делаем

  const input = document.getElementById('avatar-input');
  if (!input) return;

  const overlay      = container.querySelector('.avatar-overlay');
  const progressWrap = document.getElementById('avatar-progress');
  const progressBar  = document.getElementById('avatar-progress-bar');
  let   avatarImg    = container.querySelector('img');

  if (overlay) {
    container.addEventListener('mouseenter', () => overlay.style.opacity = '1');
    container.addEventListener('mouseleave', () => overlay.style.opacity = '0');
  }

  container.addEventListener('click', () => input.click());

  // Лимит берётся из data-max-mb на контейнере (реальная настройка
  // avatarsize с сервера) — 22 запасное значение, если атрибут вдруг
  // отсутствует/некорректен.
  const MAX_MB = parseFloat(container.dataset.maxMb) || 22;

  // Эндпоинт параметризован через data-upload-url — member.php и
  // edituser.php используют разные адреса. Запасное значение (member.php)
  // — на случай, если атрибут забыли проставить на странице.
  const UPLOAD_URL = container.dataset.uploadUrl || 'member.php?action=upload_avatar';

  input.addEventListener('change', function () {
    if (!this.files || !this.files[0]) return;

    const file = this.files[0];
    if (!/\.(jpg|jpeg|png|gif|webp)$/i.test(file.name)) {
      toastError(avatarT('avatar_bad_type', 'Please select a JPG, PNG, GIF or WebP image'));
      this.value = '';
      return;
    }
    if (file.size > MAX_MB * 1024 * 1024) {
      toastError(avatarT('avatar_too_big', 'File is too big (max. {1} MB)', MAX_MB));
      this.value = '';
      return;
    }

    const id = container.dataset.uid;
    if (!id) { toastError(avatarT('avatar_no_profile', 'Profile ID not found')); return; }

    const xhr = new XMLHttpRequest();
    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('id', id);
    formData.append('my_post_key', document.querySelector('input[name="my_post_key"]')?.value || '');

    xhr.open('POST', UPLOAD_URL, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    if (progressWrap) {
      progressWrap.style.display = 'block';
      if (progressBar) progressBar.style.width = '0%';
    }

    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable && progressBar) {
        progressBar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
      }
    };

    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4) {
        if (progressWrap) progressWrap.style.display = 'none';
        input.value = '';

        // Сервер шлёт JSON с полем error даже при статусе не 2xx
        // (403/400/415 и т.д.) — читаем тело в любом случае, а не только
        // при успехе, иначе теряем реальную причину отказа.
        let res = null;
        try { res = JSON.parse(xhr.responseText); } catch (e) { /* тело не JSON — не страшно, ниже есть запасной текст */ }

        // Оба бэкенда (member.php: "ok", edituser.php: "success") теперь
        // понимаются одинаково — success считается, если истинно любое
        // из двух полей.
        const isSuccess = !!(res && (res.ok || res.success));

        if (xhr.status >= 200 && xhr.status < 300 && isSuccess) {
          if (!avatarImg) avatarImg = container.querySelector('img');
          if (!avatarImg) {
            avatarImg = document.createElement('img');
            avatarImg.className = 'rounded img-fluid';
            const wrap = container.querySelector('div');
            if (wrap) { wrap.innerHTML = ''; wrap.appendChild(avatarImg); }
          }
          const newUrl = res.url + (res.url.includes('?') ? '&' : '?') + 'v=' + Date.now();
          avatarImg.src = newUrl;
          avatarImg.alt = avatarT('avatar_alt', 'Avatar');
          toastSuccess(avatarT('avatar_updated', 'Avatar updated'));
        } else {
          toastError((res && res.error) ? res.error : avatarT('avatar_server_error', 'Server error: {1}', xhr.status));
        }
      }
    };

    xhr.send(formData);
  });
}

// ─────────────────────────────────────────────────────────────────────────
// usercp.php — разметка avatarImage / avatarInput
// ─────────────────────────────────────────────────────────────────────────
function initLegacyAvatarUpload(avatarContainerEl) {
  const avatarInput = document.getElementById('avatarInput');
  if (!avatarInput) return;

  let avatarContainer = avatarContainerEl;

  saveOriginalAvatar();
  if (avatarContainer.style.cursor !== 'pointer') {
    avatarContainer.style.cursor = 'pointer';
  }

  avatarContainer.addEventListener('click', () => avatarInput.click());
  avatarInput.addEventListener('change', handleAvatarChange);

  function saveOriginalAvatar() {
    if (avatarContainer.tagName === 'IMG') {
      avatarContainer.dataset.originalSrc = avatarContainer.src;
    } else {
      avatarContainer.dataset.originalContent = avatarContainer.innerHTML;
    }
  }

  function handleAvatarChange() {
    const file = avatarInput.files[0];
    if (!file) return;

    if (!validateFileType(file)) {
      toastError(avatarT('avatar_bad_type', 'Please select a JPG, PNG, GIF or WebP image'));
      return;
    }

    if (!validateFileSize(file)) {
      const maxMb = parseFloat(avatarContainer.dataset.maxMb) || 2;
      toastError(avatarT('avatar_too_big', 'File is too big (max. {1} MB)', maxMb));
      return;
    }

    previewAvatar(file);
    uploadAvatar(file);
  }

  function validateFileType(file) {
    // SVG сознательно не поддерживается — это XML-формат, который может
    // содержать встроенный JavaScript. Сервер (upload_avatar()) его и так
    // не принимает, а вставка содержимого файла напрямую в DOM была бы
    // самостоятельной XSS-дырой, срабатывающей ещё до отправки на сервер.
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    return allowedTypes.includes(file.type);
  }

  function validateFileSize(file) {
    // Лимит берётся из data-max-mb на контейнере (реальная настройка
    // avatarsize с сервера) — 2MB запасное значение, если атрибут
    // отсутствует/некорректен.
    const maxMb = parseFloat(avatarContainer.dataset.maxMb) || 2;
    return file.size <= maxMb * 1024 * 1024;
  }

  function previewAvatar(file) {
    const reader = new FileReader();
    reader.onload = (e) => updateAvatarPreview(e.target.result);
    reader.readAsDataURL(file);
  }

  function updateAvatarPreview(dataUrl) {
    if (avatarContainer.tagName === 'IMG') {
      avatarContainer.src = dataUrl;
    } else {
      const newImg = document.createElement('img');
      newImg.src = dataUrl;
      newImg.className = avatarContainer.className || 'rounded img-fluid';
      newImg.style.cssText = avatarContainer.style.cssText + '; object-fit: cover;';
      newImg.alt = avatarT('avatar_alt', 'Avatar');
      newImg.dataset.originalSrc = dataUrl;

      avatarContainer.parentNode.replaceChild(newImg, avatarContainer);
      avatarContainer = newImg;
      avatarContainer.addEventListener('click', () => avatarInput.click());
    }
  }

  function uploadAvatar(file) {
    const formData = new FormData();
    formData.append('avatarupload', file);
    formData.append('action', 'do_avatar');
    formData.append('my_post_key', window.my_post_key);

    toastInfo(avatarT('avatar_uploading', 'Uploading avatar...'));

    fetch('usercp.php?action=do_avatar', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          toastSuccess(avatarT('avatar_updated', 'Avatar updated'));
          if (data.avatarUrl) {
            updateGlobalAvatar(data.avatarUrl);
          }
          saveOriginalAvatar();
        } else {
          const errorMsg = data.error || avatarT('avatar_unknown_error', 'Unknown error occurred');
          toastError(avatarT('avatar_error', 'Error: {1}', errorMsg));
          restoreOriginalAvatar();
        }
      })
      .catch((error) => {
        console.error('Upload error:', error);
        toastError(avatarT('avatar_upload_error', 'Upload error: {1}', error.message));
        restoreOriginalAvatar();
      })
      .finally(() => {
        avatarInput.value = '';
      });
  }

  function restoreOriginalAvatar() {
    if (avatarContainer.tagName === 'IMG' && avatarContainer.dataset.originalSrc) {
      avatarContainer.src = avatarContainer.dataset.originalSrc;
    } else if (avatarContainer.dataset.originalContent) {
      avatarContainer.innerHTML = avatarContainer.dataset.originalContent;
    }
  }

  function updateGlobalAvatar(avatarUrl) {
    const navAvatars = document.querySelectorAll('.nav-avatar, .user-avatar, .header-avatar');
    navAvatars.forEach((avatar) => {
      if (avatar.src) {
        avatar.src = avatarUrl + (avatarUrl.includes('?') ? '&' : '?') + 't=' + new Date().getTime();
      }
    });
  }
}

// ─────────────────────────────────────────────────────────────────────────
// Общие утилиты уведомлений (обе реализации): SweetAlert-тост в правом верхнем
// углу; без Swal — showToast() из toast.js, если есть; иначе alert().
// ─────────────────────────────────────────────────────────────────────────
function avatarToast(msg, icon, timer) {
  if (window.Swal && typeof window.Swal.fire === 'function') {
    Swal.fire({ toast: true, position: 'top-end', icon: icon, title: msg, showConfirmButton: false, timer: timer });
    return;
  }
  if (typeof window.showToast === 'function') {
    window.showToast(msg, icon);
    return;
  }
  // без Swal и toast.js промежуточный статус («Загрузка...») не показываем — alert() блокирует страницу
  if (icon !== 'info') alert(msg);
}

function toastSuccess(msg) { avatarToast(msg, 'success', 1600); }
function toastError(msg)   { avatarToast(msg, 'error', 2200); }
function toastInfo(msg)    { avatarToast(msg, 'info', 1200); }
