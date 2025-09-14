// === BBCode Tools Bundle ===

function charCountInit(textareaId, counterId) {
  const textarea = document.getElementById(textareaId);
  const charCount = document.getElementById(counterId);
  if (textarea && charCount) {
    textarea.addEventListener("input", () => {
      const length = textarea.value.length;
      charCount.textContent = `${length} / 500`;
    });
  }
}

function insertBBCode(openTag, closeTag, textareaId) {
  const textarea = document.getElementById(textareaId);
  if (!textarea) return;
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const text = textarea.value;
  const selectedText = text.substring(start, end);
  textarea.value = text.substring(0, start) + openTag + selectedText + closeTag + text.substring(end);
  const newCursor = selectedText.length === 0
    ? start + openTag.length
    : end + openTag.length + closeTag.length;
  textarea.setSelectionRange(newCursor, newCursor);
  textarea.focus();
}

function initColorPalette() {
  const colors = [
    '#000000','#444444','#666666','#999999','#cccccc','#ffffff',
    '#ff0000','#ff9900','#ffff00','#00ff00','#00ffff','#0000ff',
    '#9900ff','#ff00ff','#ff66cc','#996600','#ff6600','#cc3333',
    '#ffcc00','#0099cc','#66ccff','#99cc00','#669900','#339966',
    '#33cccc','#3366ff','#6633cc','#993366','#ffcccc','#ccffcc'
  ];
  document.querySelectorAll('.bbcode-color-btn').forEach(btn => {
    const palette = btn.nextElementSibling;
    Object.assign(palette.style, {
      display: 'none',
      position: 'absolute',
      background: '#fff',
      border: '1px solid #ccc',
      padding: '4px',
      zIndex: 999,
      gridTemplateColumns: 'repeat(8, 1fr)',
      gridGap: '4px'
    });
    palette.classList.remove('d-none');
    if (palette.childElementCount === 0) {
      colors.forEach(hex => {
        const box = document.createElement('div');
        Object.assign(box.style, {
          backgroundColor: hex,
          width: '20px',
          height: '20px',
          cursor: 'pointer',
          display: 'inline-block',
          border: '1px solid #ccc',
          margin: '2px'
        });
        box.title = hex;
        box.onclick = () => {
          const textareaId = btn.getAttribute('data-textarea');
          insertBBCode(`[color=${hex}]`, `[/color]`, textareaId);
          palette.style.display = 'none';
        };
        palette.appendChild(box);
      });
    }
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      document.querySelectorAll('.color-palette').forEach(p => {
        if (p !== palette) p.style.display = 'none';
      });
      palette.style.display = palette.style.display === 'grid' ? 'none' : 'grid';
    });
  });
  document.addEventListener("click", () => {
    document.querySelectorAll('.color-palette').forEach(palette => {
      palette.style.display = 'none';
    });
  });
}











function initViewSource(textareaId, btnId) {
  const textarea = document.getElementById(textareaId);
  const toggleBtn = document.getElementById(btnId);
  if (!textarea || !toggleBtn) return;
  
  let previewMode = false;
  let bbcodeBackup = "";
  
  toggleBtn.addEventListener("click", function () {
    if (!previewMode) {
      // Сохраняем оригинальный BBCode
      bbcodeBackup = textarea.value;
      
      // Создаем контейнер для превью
      const previewDiv = document.createElement("div");
      previewDiv.id = textareaId + "_preview";
      previewDiv.className = "form-control mt-2";
      previewDiv.style.minHeight = textarea.offsetHeight + "px";
      previewDiv.style.whiteSpace = "pre-wrap";
      previewDiv.style.overflowY = "auto";
      
      // Обрабатываем BBCode с учетом пробелов
      let html = bbcodeBackup
        // Основные теги (добавлены \s* для пробелов)
        .replace(/\[b\]\s*(.*?)\s*\[\/b\]/gis, "<strong>$1</strong>")
        .replace(/\[i\]\s*(.*?)\s*\[\/i\]/gis, "<em>$1</em>")
        .replace(/\[u\]\s*(.*?)\s*\[\/u\]/gis, "<u>$1</u>")
        .replace(/\[s\]\s*(.*?)\s*\[\/s\]/gis, "<s>$1</s>")
        
        // Ссылки и изображения
        .replace(/\[url\]\s*(.*?)\s*\[\/url\]/gis, '<a href="$1" target="_blank">$1</a>')
        .replace(/\[img\]\s*(.*?)\s*\[\/img\]/gis, '<img src="$1" class="rounded" style="max-width:400px; width:100%">')

		
		
        
        // Теги с атрибутами (цвет, размер, шрифт)
        .replace(/\[color=(.*?)\]\s*(.*?)\s*\[\/color\]/gis, '<span style="color:$1">$2</span>')
        .replace(/\[size=(.*?)\]\s*(.*?)\s*\[\/size\]/gis, '<span style="font-size:$1px">$2</span>')
        .replace(/\[font=(.*?)\]\s*(.*?)\s*\[\/font\]/gis, '<span style="font-family:$1">$2</span>')
        
        // Выравнивание
        .replace(/\[center\]\s*(.*?)\s*\[\/center\]/gis, '<div style="text-align:center;">$1</div>')
        .replace(/\[left\]\s*(.*?)\s*\[\/left\]/gis, '<div style="text-align:left;">$1</div>')
        .replace(/\[right\]\s*(.*?)\s*\[\/right\]/gis, '<div style="text-align:right;">$1</div>')
        
        // Цитаты и код
        .replace(/\[quote\]\s*(.*?)\s*\[\/quote\]/gis, '<blockquote class="mycode_quote">$1</blockquote>')
        .replace(/\[code\]\s*(.*?)\s*\[\/code\]/gis, '<pre>$1</pre>')
        
        // Списки
        .replace(/\[list\]\s*(.*?)\s*\[\/list\]/gis, '<ul>$1</ul>')
        .replace(/\[list=1\]\s*(.*?)\s*\[\/list\]/gis, '<ol>$1</ol>')
        .replace(/\[\*\]\s*(.*?)(\r?\n|$)/g, '<li>$1</li>')
        
        // Специальные теги
        .replace(/\[spoiler\]\s*(.*?)\s*\[\/spoiler\]/gis, '<details><summary>Spoiler</summary>$1</details>')
        .replace(/\[video=youtube\]\s*(.*?)\s*\[\/video\]/gi, '<iframe width="300" height="200" src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe>');
      
      // Обработка смайликов
      if (typeof smilies !== "undefined") {
        for (const code in smilies) {
          const escaped = code.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
          const regex = new RegExp(escaped, 'g');
          html = html.replace(regex, `<img src="/pic/smilies/${smilies[code]}" alt="${code}" style="vertical-align:middle; max-height:20px;">`);
        }
      }
      
      // Показываем превью
      previewDiv.innerHTML = html;
      textarea.style.display = "none";
      textarea.insertAdjacentElement("afterend", previewDiv);
      toggleBtn.textContent = "Edit BBCode";
      toggleBtn.classList.add("btn-dark");
      previewMode = true;
    } else {
      // Возвращаемся к редактированию
      const previewDiv = document.getElementById(textareaId + "_preview");
      if (previewDiv) previewDiv.remove();
      textarea.style.display = "";
      textarea.value = bbcodeBackup;
      toggleBtn.textContent = "Preview";
      toggleBtn.classList.remove("btn-dark");
      previewMode = false;
    }
  });
}











function initSmileyPanel(btnId, panelId, textareaId) {
  const btn = document.getElementById(btnId);
  const panel = document.getElementById(panelId);
  const textarea = document.getElementById(textareaId);
  if (!btn || !panel || !textarea || typeof smilies === "undefined") return;
  for (const code in smilies) {
    const img = document.createElement("img");
    img.src = "/pic/smilies/" + smilies[code];
    img.alt = code;
    img.title = code;
    img.style.cursor = "pointer";
    img.style.margin = "4px";
    img.style.verticalAlign = "middle";
    img.addEventListener("click", (e) => {
      e.preventDefault();
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const text = textarea.value;
      const newText = text.slice(0, start) + ` ${code} ` + text.slice(end);
      textarea.value = newText;
      textarea.focus();
      textarea.setSelectionRange(start + code.length + 2, start + code.length + 2);
    });
    panel.appendChild(img);
  }
  btn.addEventListener("click", (e) => {
    e.preventDefault();
    panel.classList.toggle("d-none");
  });
  document.addEventListener("click", (e) => {
    if (!panel.contains(e.target) && !btn.contains(e.target)) {
      panel.classList.add("d-none");
    }
  });
}














// === Font Picker Initialization ===
function initFontPicker() {
  //const fonts = [
    //'Arial', 'Verdana', 'Helvetica', 'Tahoma', 'Georgia', 
    //'Times New Roman', 'Courier New', 'Comic Sans MS', 
    //'Lucida Console', 'Impact', 'Trebuchet MS', 'Consolas'
  //];
  
  const fonts = [
    'Arial', 'Verdana', 'Helvetica', 'Tahoma', 'Georgia', 
    'Times New Roman', 'Courier New', 'Comic Sans MS', 
    'Lucida Console', 'Impact', 'Trebuchet MS', 'Consolas',
    'Tahoma', 'Palatino', 'Bookman', 'Arial Black', 'Lucida Sans', 
    'Century Gothic', 'Futura', 'Garamond', 'Frank Ruhl', 'Zapfino',
    'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Oswald', 'Raleway', 
    'Poppins', 'Ubuntu', 'Quicksand', 'Merriweather', 'Playfair Display', 
    'Source Sans Pro', 'Noto Sans', 'Droid Sans', 'PT Sans', 'Lora', 
    'Bitter', 'Indie Flower', 'Bungee', 'Tangerine', 'Shadows Into Light'
  ];

  

  document.querySelectorAll('.font-picker-btn').forEach(btn => {
    const textareaId = btn.getAttribute('data-textarea');
    const menu = btn.nextElementSibling;

    // Заполняем меню только один раз
    if (menu && menu.children.length === 0) {
      fonts.forEach(font => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'dropdown-item text-start';
        item.style.fontFamily = font;
        item.textContent = font;
        
        item.addEventListener('click', (e) => {
          e.preventDefault();
          insertBBCode(`[font=${font}]`, `[/font]`, textareaId);
          menu.classList.remove('show');
        });
        
        menu.appendChild(item);
      });
    }

    // Обработчик клика по кнопке
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      // Закрываем все другие меню
      document.querySelectorAll('.font-menu').forEach(m => {
        if (m !== menu) m.classList.remove('show');
      });
      
      // Переключаем текущее меню
      menu.classList.toggle('show');
      
      // Позиционируем меню
      if (menu.classList.contains('show')) {
        const rect = btn.getBoundingClientRect();
        menu.style.left = 'auto';
        menu.style.right = '0';
        menu.style.top = '100%';
        menu.style.bottom = 'auto';
      }
    });
  });

  // Закрытие при клике вне меню
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.font-menu') && !e.target.closest('.font-picker-btn')) {
      document.querySelectorAll('.font-menu').forEach(menu => {
        menu.classList.remove('show');
      });
    }
  });
}










// === Size Picker Initialization ===
function initSizePicker() {
  const sizes = [
    {name: 'XX-Small', value: 'xx-small', size: '0.6em'},
    {name: 'X-Small', value: 'x-small', size: '0.75em'},
    {name: 'Small', value: 'small', size: '0.9em'},
    {name: 'Medium', value: 'medium', size: '1em'},
    {name: 'Large', value: 'large', size: '1.2em'},
    {name: 'X-Large', value: 'x-large', size: '1.5em'},
    {name: 'XX-Large', value: 'xx-large', size: '2em'}
  ];

  document.querySelectorAll('.size-picker-btn').forEach(btn => {
    const textareaId = btn.getAttribute('data-textarea');
    const menu = btn.nextElementSibling;

    // Заполняем меню только один раз
    if (menu && menu.children.length === 0) {
      sizes.forEach(size => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'dropdown-item';
        item.style.cssText = `
          font-size: ${size.size};
          padding: 0.25rem 1rem;
          text-align: left;
        `;
        item.textContent = size.name;
        
        item.addEventListener('click', (e) => {
          e.preventDefault();
          insertBBCode(`[size=${size.value}]`, `[/size]`, textareaId);
          menu.classList.remove('show');
        });
        
        menu.appendChild(item);
      });
    }

    // Обработчик клика по кнопке
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      // Закрываем все другие меню
      document.querySelectorAll('.size-menu').forEach(m => {
        if (m !== menu) m.classList.remove('show');
      });
      
      // Переключаем текущее меню
      menu.classList.toggle('show');
      
      // Позиционируем меню
      if (menu.classList.contains('show')) {
        const rect = btn.getBoundingClientRect();
        menu.style.left = 'auto';
        menu.style.right = '0';
        menu.style.top = '100%';
      }
    });
  });

  // Закрытие при клике вне меню
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.size-menu') && !e.target.closest('.size-picker-btn')) {
      document.querySelectorAll('.size-menu').forEach(menu => {
        menu.classList.remove('show');
      });
    }
  });
}






document.addEventListener("DOMContentLoaded", function () {
  // Инициализация выбора размера и шрифта
  initSizePicker();
  initFontPicker();

  // Счётчики символов
  charCountInit("commentText", "charCount");
  charCountInit("description", "charCount2");
  charCountInit("message", "charCount3");
  charCountInit("newsMessage", "charCount4");
  charCountInit("staffMessage", "charCount5");

  // Цветовые палитры
  initColorPalette();

  // Смайлики
  initSmileyPanel("smileyBtn", "smileyPanel", "commentText");
  initSmileyPanel("smileyBtn2", "smileyPanel2", "description");
  initSmileyPanel("smileyBtn3", "smileyPanel3", "message");
  initSmileyPanel("smileyBtn4", "smileyPanel4", "newsMessage");
  initSmileyPanel("smileyBtn5", "smileyPanel5", "staffMessage");
  
 

  // BBCode превью
  initViewSource("commentText", "togglePreviewBtn");
  initViewSource("description", "togglePreviewBtn2");
  initViewSource("message", "togglePreviewBtn3");
  initViewSource("newsMessage", "togglePreviewBtn4");
  initViewSource("staffMessage", "togglePreviewBtn5");
  
  // --- Привязка file_ids к форме комментария ---
  const fileIdsContainer = document.getElementById('fileIdsContainer');
  
  

  

  // ========== Начало кода для загрузки изображений ==========
  const imageUploadBtn = document.getElementById('imageUploadBtn');
  const insertImageBtn = document.getElementById('insertImageBtn');
  const imageUpload = document.getElementById('imageUpload');
  const progressBar = document.querySelector('#uploadProgress .progress-bar');
  const progressContainer = document.getElementById('uploadProgress');

  /**
   * Вставляет изображение по URL в редактор BB-кода
   */
  function insertImageFromModal() {
    const urlInput = document.getElementById('imageUrl5');
    const widthInput = document.getElementById('imageWidth');
    const heightInput = document.getElementById('imageHeight');
    
    if (!urlInput || !widthInput || !heightInput) {
      console.error('Элементы формы не найдены');
      return;
    }

    const url = urlInput.value.trim();
    const width = widthInput.value.trim();
    const height = heightInput.value.trim();

    // Валидация URL
    if (!url) {
      alert('Пожалуйста, введите URL изображения');
      urlInput.focus();
      return;
    }

    // Формирование BB-кода
    let bbCode;
    if (width || height) {
      if ((width && isNaN(width)) || (height && isNaN(height))) {
        alert('Ширина и высота должны быть числами');
        return;
      }
      bbCode = `[img=${width || ''}${height ? 'x' + height : ''}]${url}[/img]`;
    } else {
      bbCode = `[img]${url}[/img]`;
    }

    // Вставка в редактор
    insertBBCode(bbCode, '', 'description');
	insertBBCode(bbCode, '', 'message');
	insertBBCode(bbCode, '', 'commentText');
	insertBBCode(bbCode, '', 'newsMessage');
	
	
	
	
	
    
    // Закрытие модального окна
    const modal = bootstrap.Modal.getInstance(document.getElementById('imageUploadModal'));
    if (modal) modal.hide();
    
    // Очистка полей
    urlInput.value = '';
    widthInput.value = '';
    heightInput.value = '';
  }

  /**
   * Загружает изображение на сервер и вставляет BB-код
   */
  async function uploadAndInsertImage() {
    if (!imageUpload.files || imageUpload.files.length === 0) {
      alert('Пожалуйста, выберите изображение');
      return;
    }

    const file = imageUpload.files[0];
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
    
    if (!validTypes.includes(file.type)) {
      alert('Допустимые форматы: JPG, PNG, GIF, WEBP');
      return;
    }

    // Показываем индикатор загрузки
    insertImageBtn.disabled = true;
    progressContainer.classList.remove('d-none');
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';

    try {
      const formData = new FormData();
      formData.append('image', file);
      formData.append('upload_type', 'editor_image');
	  
	  

      //const response = await fetch('upload_image.php', {
	  const response = await fetch(`${baseurl}/upload_image.php`, {
        method: 'POST',
        body: formData,
      });

      const result = await response.json();
      
      if (!result.success || result.type !== 'editor_image') {
        throw new Error(result.error || 'Ошибка загрузки');
      }

      // Вставляем BB-код
      insertBBCode(`[img]${result.url}[/img]`, '', 'description');
	  insertBBCode(`[img]${result.url}[/img]`, '', 'message');
	  insertBBCode(`[img]${result.url}[/img]`, '', 'commentText');
	  insertBBCode(`[img]${result.url}[/img]`, '', 'newsMessage');
	  

	
      //добавляем скрытый input с file_id
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'file_ids[]';
      hidden.value = result.file_id;
      fileIdsContainer.appendChild(hidden);
	  
	  
	
	  
	  
	  
      
      // Закрываем и очищаем форму
      bootstrap.Modal.getInstance(document.getElementById('imageUploadModal')).hide();
      imageUpload.value = '';
      document.getElementById('uploadPreview').innerHTML = '';
      
    } catch (error) {
      console.error('Ошибка загрузки:', error);
      alert(`Ошибка: ${error.message}`);
    } finally {
      progressContainer.classList.add('d-none');
      insertImageBtn.disabled = false;
    }
  }

  // Инициализация обработчиков событий
  if (insertImageBtn) {
    insertImageBtn.addEventListener('click', function() {
      const activeTab = document.querySelector('#imageUploadModal .tab-pane.active');
      
      if (activeTab.id === 'tab-url') {
        insertImageFromModal();
      } else if (activeTab.id === 'tab-upload') {
        uploadAndInsertImage();
      }
    });
  }

  if (imageUpload) {
    imageUpload.addEventListener('change', function(e) {
      if (e.target.files.length) {
        const file = e.target.files[0];
        const reader = new FileReader();
        
        reader.onload = function(event) {
          const preview = document.getElementById('uploadPreview');
          if (preview) {
            preview.innerHTML = `
              <img src="${event.target.result}" class="img-thumbnail" style="max-height: 150px;">
              <div class="mt-2 small">${file.name} (${(file.size/1024).toFixed(1)} KB)</div>
            `;
          }
        };
        reader.readAsDataURL(file);
      }
    });
  }
  // ========== Конец кода для загрузки изображений ==========
});