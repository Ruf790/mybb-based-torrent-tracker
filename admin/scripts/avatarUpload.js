(function(){
  const fileInput = document.getElementById('avatarUploadInput');
  const UPLOAD_URL = 'index.php?act=usersearch&action=upload_avatar';
  let targetCell = null, targetUid = null;

  document.addEventListener('click', (e) => {
    const cell = e.target.closest('td[data-avatar-cell]');
    if (!cell) return;
    targetCell = cell;
    targetUid  = cell.dataset.uid;
    fileInput.value = '';
    fileInput.click();
  });

  fileInput.addEventListener('change', () => {
    if (!fileInput.files || !fileInput.files[0] || !targetUid) return;

    const fd = new FormData();
    fd.append('avatar', fileInput.files[0]);
    fd.append('id', targetUid);

    const box  = targetCell;
    const prev = box.innerHTML;
    box.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:50px;width:50px;font-size:12px;color:#666;">Uploading…</div>';

    fetch(UPLOAD_URL, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(r => r.json())
      .then(j => {
        if (!j.ok) throw new Error(j.error || 'Upload failed');
        const url = (j.href || j.url) + '?v=' + Date.now();
        box.innerHTML = '<img src="'+url+'" alt="avatar" class="rounded" width="50">';
      })
      .catch(err => { alert(err.message || 'Upload error'); box.innerHTML = prev; })
      .finally(() => { targetCell = null; targetUid = null; });
  });
})();