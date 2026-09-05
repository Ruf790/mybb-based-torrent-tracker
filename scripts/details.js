// ===== Peers tab switching (?tab=peers в URL) =====
document.addEventListener("DOMContentLoaded", function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get("tab") !== "peers") {
        return;
    }
    var peersTabButton = document.getElementById("peers-tab");
    if (!peersTabButton || typeof bootstrap === "undefined") {
        return;
    }
    var tab = new bootstrap.Tab(peersTabButton);
    tab.show();
    var hash = window.location.hash.replace("#", "");
    if (hash) {
        peersTabButton.addEventListener("shown.bs.tab", function () {
            var target = document.getElementById(hash);
            if (target) {
                target.scrollIntoView({ behavior: "smooth", block: "start" });
            }
        }, { once: true });
    }
});

// ===== NFO copy to clipboard =====
function copyNfo() {
    var text = document.getElementById("nfoText").textContent;
    navigator.clipboard.writeText(text).then(function() {
        showToast("NFO copied to clipboard!", "success");
    }).catch(function() {
        showToast("Copy failed", "danger");
    });
}

// ===== Torrent page init: progress bar animation, fade-in on scroll =====
document.addEventListener('DOMContentLoaded', function() {
    initTorrentPage();
    initAnimations();
});

function initTorrentPage() {
    // Initialize progress animations
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.transition = 'width 1s ease-in-out';
            bar.style.width = width;
        }, 500);
    });

    // Add intersection observer for animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate__animated', 'animate__fadeInUp');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.card, .stat-card').forEach(el => {
        observer.observe(el);
    });
}

function initAnimations() {
    // Add staggered animations to elements
    const elements = document.querySelectorAll('.stat-card, .info-item, .nav-item');
    elements.forEach((el, index) => {
        el.style.animationDelay = (index * 0.1) + 's';
    });
}

// ===== File tree expand/collapse all =====
function expandAllFiles() {
    const collapses = document.querySelectorAll('.file-tree .collapse');
    collapses.forEach((el, index) => {
        setTimeout(() => {
            if (!el.classList.contains('show')) {
                new bootstrap.Collapse(el, { show: true });
            }
        }, index * 50);
    });
}

function collapseAllFiles() {
    const collapses = document.querySelectorAll('.file-tree .collapse');
    collapses.forEach((el, index) => {
        setTimeout(() => {
            if (el.classList.contains('show')) {
                new bootstrap.Collapse(el, { hide: true });
            }
        }, index * 50);
    });
}

// ===== Smooth scrolling for anchor links =====
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// ===== Edit torrent: image previews (URL / file) + form validation =====
function previewURLImage(url) {
    const img = document.getElementById('urlImagePreview');
    if (url) {
        img.src = url;
        img.style.display = 'block';
    } else {
        img.src = '#';
        img.style.display = 'none';
    }
}

function readFileImage(input) {
    const preview = document.getElementById('fileImagePreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.src = '#';
        preview.style.display = 'none';
    }
}

function previewURLImage2(url) {
    const img = document.getElementById('urlImagePreview2');
    if (url) {
        img.src = url;
        img.style.display = 'block';
    } else {
        img.src = '#';
        img.style.display = 'none';
    }
}

function readFileImage2(input) {
    const preview = document.getElementById('fileImagePreview2');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.src = '#';
        preview.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const insertForm = document.getElementById("insert_form");
    if (!insertForm) return;

    insertForm.addEventListener("submit", function (e) {
        const name = document.getElementById("name").value.trim();
        const descr = document.getElementById("descr").value.trim();

        // Validate required fields
        if (!name || !descr) {
            alert("Please fill out all required fields.");
            e.preventDefault();
            return;
        }
    });
});

// ===== Delete torrent modal =====
document.addEventListener("DOMContentLoaded", function() {
    const deleteModal = document.getElementById("deleteTorrentModal");
    const confirmCheckbox = document.getElementById("confirmDelete");
    const confirmBtn = document.getElementById("confirmDeleteBtn");
    const torrentNamePreview = document.getElementById("torrentNamePreview");

    let currentTorrentId = null;

    // Обработчик для всех кнопок удаления
    document.addEventListener("click", function(e) {
        if (e.target.closest('[data-bs-target="#deleteTorrentModal"]')) {
            const button = e.target.closest('[data-bs-target="#deleteTorrentModal"]');
            currentTorrentId = button.getAttribute("data-torrent-id");
            const torrentName = button.getAttribute("data-torrent-name");

            // Обновляем содержимое модалки
            if (torrentNamePreview) {
                torrentNamePreview.innerHTML = `<strong>"${torrentName}"</strong>`;
            }

            // Сбрасываем подтверждение
            if (confirmCheckbox) {
                confirmCheckbox.checked = false;
            }
            if (confirmBtn) {
                confirmBtn.disabled = true;
            }
        }
    });

    // Переключение кнопки удаления в зависимости от подтверждения
    if (confirmCheckbox) {
        confirmCheckbox.addEventListener("change", function() {
            if (confirmBtn) {
                confirmBtn.disabled = !this.checked;
            }
        });
    }

    // Обработка подтверждения удаления
    if (confirmBtn) {
        confirmBtn.addEventListener("click", function() {
            if (currentTorrentId && confirmCheckbox && confirmCheckbox.checked) {
                // Показываем состояние загрузки
                confirmBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Deleting...';
                confirmBtn.disabled = true;

                // Создаем и отправляем форму POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete.php';

                // Добавляем необходимые поля
                const idInput = document.createElement('input');
                idInput.name = 'id';
                idInput.value = currentTorrentId;

                const reasonTypeInput = document.createElement('input');
                reasonTypeInput.name = 'reasontype';
                reasonTypeInput.value = '5'; // Причина: "Другое"

                const reasonInput = document.createElement('input');
                reasonInput.name = 'reason[3]';
                reasonInput.value = 'Deleted via quick delete modal';

                // CSRF-токен - без него сервер теперь отклоняет удаление
                const myPostKeyInput = document.createElement('input');
                myPostKeyInput.name = 'my_post_key';
                myPostKeyInput.value = document.getElementById('myPostKey')?.value
                    || document.querySelector('input[name="my_post_key"]')?.value
                    || '';

                form.appendChild(idInput);
                form.appendChild(reasonTypeInput);
                form.appendChild(reasonInput);
                form.appendChild(myPostKeyInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Сброс состояния модалки при закрытии
    if (deleteModal) {
        deleteModal.addEventListener("hidden.bs.modal", function() {
            currentTorrentId = null;
            if (confirmCheckbox) {
                confirmCheckbox.checked = false;
            }
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i>Delete Torrent';
            }
        });
    }
});

// ===== Magnet link modal (copy / open in client / from dropdown buttons) =====
document.addEventListener("DOMContentLoaded", function() {
  const magnetInput = document.getElementById("magnetInput");
  const copyBtn = document.getElementById("copyMagnetBtn");
  const copySuccess = document.getElementById("copySuccess");
  const openMagnetBtn = document.getElementById("openMagnetBtn");
  let modalInstance = null;

  if (!magnetInput || !copyBtn || !openMagnetBtn) {
    return;
  }

  // Функция обновления магнет ссылки
  window.updateMagnetLink = function(url) {
    magnetInput.value = url;
  };

  // Открытие магнета в клиенте
  openMagnetBtn.addEventListener("click", function() {
    const magnetUrl = magnetInput.value;

    if (!magnetUrl || !magnetUrl.startsWith("magnet:?")) {
      showToast("Invalid magnet link", "error");
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
      showToast("Could not open torrent client. Please copy the magnet link manually.", "error");
    }
  });

  // Копирование в буфер обмена
  copyBtn.addEventListener("click", function() {
    const magnetUrl = magnetInput.value;

    navigator.clipboard.writeText(magnetUrl).then(() => {
      // Показываем success
      if (copySuccess) copySuccess.classList.remove("d-none");

      const originalHtml = copyBtn.innerHTML;
      copyBtn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
      copyBtn.classList.remove("btn-primary");
      copyBtn.classList.add("btn-success");

      setTimeout(() => {
        if (copySuccess) copySuccess.classList.add("d-none");
        copyBtn.innerHTML = originalHtml;
        copyBtn.classList.remove("btn-success");
        copyBtn.classList.add("btn-primary");
      }, 2000);

    }).catch(err => {
      console.error(err);
      showToast("Could not copy to clipboard", "error");
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
            showToast("Magnet link error", "error");
            return;
          }

          // Обновляем ссылку
          magnetInput.value = magnet;

          // Создаем и показываем модалку
          const modalEl = document.getElementById("magnetModal");
          if (!modalEl) return;
          modalInstance = new bootstrap.Modal(modalEl, {
            backdrop: "static",
            keyboard: false
          });

          modalInstance.show();
        })
        .catch(err => {
          console.error(err);
          showToast("Failed to load magnet link", "error");
        });
    });
  });

  // Очистка при скрытии модалки
  const modalEl = document.getElementById("magnetModal");
  if (modalEl) {
    modalEl.addEventListener("hidden.bs.modal", function() {
      // Убираем все оверлеи
      document.querySelectorAll(".modal-backdrop").forEach(el => el.remove());
      document.body.classList.remove("modal-open");
      document.body.style.overflow = "";
      document.body.style.paddingRight = "";
      modalInstance = null;
    });
  }
});

// ===== Quick IMDb refresh (обновление IMDb-данных торрента без перезагрузки страницы) =====
// baseurl - глобальная JS-переменная, задаётся в header.php на каждой странице
function parseInteger(value, radix) {
    if (typeof value === "string") {
        const parsedNumber = parseInt(value * 1);
        if (isNaN(parsedNumber) || !isFinite(parsedNumber)) {
            return 0;
        } else {
            return parsedNumber.toString(radix || 10);
        }
    } else {
        if (typeof value === "number" && isFinite(value)) {
            return Math.floor(value);
        } else {
            return 0;
        }
    }
}

function TS_IMDB(torrentId) {
    const updateButton = document.getElementById('imdbupdatebutton');
    const imdbDetails = document.getElementById('imdbdetails');

    if (!updateButton || !imdbDetails) {
        console.error('Required elements not found');
        return;
    }

    const postData = "tid=" + parseInteger(torrentId);

    // Update button state
    updateButton.textContent = 'Please Wait...';
    updateButton.disabled = true;

    fetch(baseurl + "/ajax_imdb.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: postData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(response => {
        if (response.match(/<error>(.*)<\/error>/)) {
            const errorMatch = response.match(/<error>(.*)<\/error>/);
            const errorMessage = errorMatch[1] || 'An error occurred';

            alert('Update error: ' + errorMessage);
            updateButton.textContent = 'Refresh';
            updateButton.disabled = false;
        } else {
            imdbDetails.innerHTML = response;
            updateButton.textContent = 'Updated';
            updateButton.disabled = false;

            // Visual feedback
            const parentContainer = imdbDetails.parentElement;
            parentContainer.classList.add('bg-warning');
            parentContainer.classList.remove('bg-light');

            // Simple fade effect
            parentContainer.style.opacity = '0';
            setTimeout(() => {
                parentContainer.style.opacity = '1';
            }, 200);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('AJAX error occurred');
        updateButton.textContent = 'Refresh';
        updateButton.disabled = false;
    });
}