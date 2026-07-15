document.addEventListener("DOMContentLoaded", function() {
  const magnetInput = document.getElementById("magnetInput");
  const copyBtn = document.getElementById("copyMagnetBtn");
  const copySuccess = document.getElementById("copySuccess");
  const openMagnetBtn = document.getElementById("openMagnetBtn");
  let modalInstance = null;

  // Функция обновления магнет ссылки
  window.updateMagnetLink = function(url) {
    magnetInput.value = url;
  };

  // Открытие магнета в клиенте
  openMagnetBtn.addEventListener("click", function() {
    const magnetUrl = magnetInput.value;
    
    if (!magnetUrl || !magnetUrl.startsWith("magnet:?")) {
      showToast("Invalid magnet link", "error");  // Используем ваш showToast
      return;
    }
    
    // Пробуем открыть магнет ссылку
    try {
      window.location.href = magnetUrl;
      
      // Показываем уведомление с тоастом
      showToast("Opening torrent client...", "info");
      
      // Закрываем модалку и убираем оверлей
      if (modalInstance) {
        modalInstance.hide();
        // Принудительно убираем оверлей
        setTimeout(() => {
          document.querySelectorAll(".modal-backdrop").forEach(el => el.remove());
          document.body.classList.remove("modal-open");
          document.body.style.overflow = "";
          document.body.style.paddingRight = "";
        }, 100);
      }
      
    } catch (e) {
      console.error(e);
      showToast("Could not open torrent client. Please copy the magnet link manually.", "error"); // Используем ваш showToast
    }
  });

  // Копирование в буфер обмена
  copyBtn.addEventListener("click", function() {
    const magnetUrl = magnetInput.value;
    
    navigator.clipboard.writeText(magnetUrl).then(() => {
      // Показываем success
      copySuccess.classList.remove("d-none");
      
      const originalHtml = copyBtn.innerHTML;
      copyBtn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
      copyBtn.classList.remove("btn-primary");
      copyBtn.classList.add("btn-success");
      
      setTimeout(() => {
        copySuccess.classList.add("d-none");
        copyBtn.innerHTML = originalHtml;
        copyBtn.classList.remove("btn-success");
        copyBtn.classList.add("btn-primary");
      }, 2000);
      
    }).catch(err => {
      console.error(err);
      showToast("Could not copy to clipboard", "error"); // Используем ваш showToast
    });
  });

  // Клик по Magnet в dropdown
  document.querySelectorAll(".magnet-btn").forEach(btn => {
    btn.addEventListener("click", function(e) {
      e.preventDefault();

      const torrentId = this.dataset.magnetId;

      fetch("download.php?type=magnet&id=" + torrentId)
        .then(res => res.text())
        .then(magnet => {
          magnet = magnet.trim();

          if (!magnet.startsWith("magnet:?")) {
            showToast("Magnet link error", "error"); // Используем ваш showToast
            return;
          }

          // Обновляем ссылку
          magnetInput.value = magnet;

          // Создаем и показываем модалку
          const modalEl = document.getElementById("magnetModal");
          modalInstance = new bootstrap.Modal(modalEl, {
            backdrop: "static",
            keyboard: false
          });
          
          modalInstance.show();
        })
        .catch(err => {
          console.error(err);
          showToast("Failed to load magnet link", "error"); // Используем ваш showToast
        });
    });
  });

  // Очистка при скрытии модалки
  const modalEl = document.getElementById("magnetModal");
  modalEl.addEventListener("hidden.bs.modal", function() {
    // Убираем все оверлеи
    document.querySelectorAll(".modal-backdrop").forEach(el => el.remove());
    document.body.classList.remove("modal-open");
    document.body.style.overflow = "";
    document.body.style.paddingRight = "";
    modalInstance = null;
  });
});
