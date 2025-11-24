document.addEventListener('DOMContentLoaded', function() {
    const tabContainers = document.querySelectorAll('ul.tabs');
    
    tabContainers.forEach(function(tabContainer) {
        // Проверяем, были ли табы уже обработаны
        if (tabContainer.dataset.rendered) {
            return;
        }
        
        tabContainer.dataset.rendered = 'yes';
        
        const links = tabContainer.querySelectorAll('a');
        let activeTab, activeContent;
        
        // Находим активную вкладку (из hash или первую)
        const hashTab = Array.from(links).find(link => link.getAttribute('href') === location.hash);
        activeTab = hashTab || links[0];
        activeContent = document.querySelector(activeTab.getAttribute('href'));
        
        // Активируем выбранную вкладку
        activeTab.classList.add('active');
        if (activeContent) {
            activeContent.style.display = 'block';
        }
        
        // Скрываем остальной контент
        links.forEach(function(link) {
            if (link !== activeTab) {
                const content = document.querySelector(link.getAttribute('href'));
                if (content) {
                    content.style.display = 'none';
                }
            }
        });
        
        // Обработчик кликов по табам
        tabContainer.addEventListener('click', function(e) {
            if (e.target.tagName === 'A') {
                e.preventDefault();
                
                // Деактивируем текущую вкладку
                activeTab.classList.remove('active');
                if (activeContent) {
                    activeContent.style.display = 'none';
                }
                
                // Активируем новую вкладку
                activeTab = e.target;
                activeContent = document.querySelector(activeTab.getAttribute('href'));
                
                // Обновляем hash в адресной строке
                if (activeTab.getAttribute('href')) {
                    window.location.hash = activeTab.getAttribute('href');
                }
                
                activeTab.classList.add('active');
                if (activeContent) {
                    activeContent.style.display = 'block';
                }
            }
        });
    });
});