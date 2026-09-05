// zzz.js - Enhanced forum interactivity

// ========== Wait for DOM to initialize ==========
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        enhanceForumSystem();
        initForumAnimations();
        initForumPopovers();
    }, 1000);
});

// ========== Enhance forum system ==========
function enhanceForumSystem() {
    console.log('Enhancing forum system...');
    
    const markReadElements = document.querySelectorAll('.ajax_mark_read');
    
    markReadElements.forEach(element => {
        if (shouldSkipElement(element)) {
            return;
        }
        
        addCustomStyling(element);
        updateForumIcon(element);
        
        if (typeof bootstrap !== 'undefined') {
            addPopoverToElement(element);
        }
        
        enhanceClickHandler(element);
    });
    
    console.log('Enhanced', markReadElements.length, 'forum indicators');
}


function markForumReadAjax(fid, element) {
    element.classList.add('processing');

    const xhr = new XMLHttpRequest();
    xhr.open(
        'GET',
        'misc.php?action=markread&fid=' + fid + '&ajax=1&my_post_key=' + my_post_key,
        true
    );

    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        element.classList.remove('processing');
        element.classList.remove('forum-anim-click');

        if (xhr.status === 200 && xhr.responseText == 1) {
            applyForumReadState(fid, element);
        } else {
            console.error('Mark read failed for forum:', fid);
        }
    };

    xhr.send();
}


// ========== toggleForumRead Function ==========
window.toggleForumRead = function(fid) {
    const element = document.getElementById('mark_read_' + fid);
    if (!element) return;

    if (shouldSkipElement(element)) return;

    // анимация клика
    element.classList.add('forum-anim-click');

    // реальный AJAX
    markForumReadAjax(fid, element);
};


function applyForumReadState(fid, element) {
    // классы форума
    element.classList.remove(
        'forum_new',
        'forum_on',
        'forum_hot',
        'status-new',
        'status-hot'
    );

    element.classList.add(
        'forum_old',
        'forum_off',
        'status-old'
    );

    // иконка
    const icon = element.querySelector('.status-icon, i');
    if (icon) {
        icon.className = 'status-icon fa-solid fa-circle-check';
    }

    element.title = lang?.no_new_posts || 'No new posts';
    element.style.cursor = 'default';

    // анимация "успешно"
    element.classList.add('forum-anim-done');
    setTimeout(() => {
        element.classList.remove('forum-anim-done');
    }, 600);

    // popover update
    updateElementAfterClick(element);
}









// ========== Helper Functions ==========
function shouldSkipElement(element) {
    return element.classList.contains('forum_off') || 
           element.classList.contains('forum_offclose') || 
           element.classList.contains('forum_offlink') || 
           element.classList.contains('subforum_minioff') || 
           element.classList.contains('subforum_minioffclose') || 
           element.classList.contains('subforum_miniofflink') || 
           (element.title && element.title == (lang?.no_new_posts || 'No new posts'));
}

function addCustomStyling(element) {
    element.classList.add('status-circle', 'pulse-hover');
    
    if (element.classList.contains('forum_new') || element.classList.contains('forum_on')) {
        element.classList.add('status-new');
    } else if (element.classList.contains('forum_old') || element.classList.contains('forum_off')) {
        element.classList.add('status-old');
    } else if (element.classList.contains('forum_hot')) {
        element.classList.add('status-hot');
    }
    
    element.style.cursor = 'pointer';
}

function updateForumIcon(element) {
    const icon = element.querySelector('i');
    if (!icon) return;
    
    icon.classList.add('status-icon');
    
    if (element.classList.contains('forum_old') || element.classList.contains('forum_off')) {
        icon.className = 'status-icon fa-solid fa-circle-check';
    } else if (element.classList.contains('forum_hot')) {
        icon.className = 'status-icon fa-solid fa-fire';
    } else {
        icon.className = 'status-icon fa-solid fa-circle';
    }
}

function addPopoverToElement(element) {
    const fid = element.id ? element.id.replace('mark_read_', '') : '';
    const altText = element.getAttribute('data-alt') || element.title || '';
    
    const popoverContent = `
        <div class="popover-content">
            <div class="mb-2"><strong>${altText}</strong></div>
            <div class="small text-muted">Click to mark as read/unread</div>
        </div>
    `;
    
    try {
        new bootstrap.Popover(element, {
            container: 'body',
            trigger: 'click focus',
            html: true,
            placement: 'right',
            sanitize: false,
            customClass: 'forum-popover',
            delay: { show: 100, hide: 100 },
            title: '<i class="fa-solid fa-info-circle me-2"></i>Forum Status',
            content: popoverContent
        });
        
        element.addEventListener('click', function() {
            setTimeout(() => {
                updateElementAfterClick(element);
            }, 500);
        });
        
    } catch (error) {
        console.error('Error creating popover:', error);
    }
}

function enhanceClickHandler(element) {
    const originalOnClick = element.onclick;
    
    element.addEventListener('click', function(e) {
        this.style.transform = 'scale(0.95)';
        setTimeout(() => {
            this.style.transform = '';
        }, 200);
        
        if (originalOnClick) {
            originalOnClick.call(this, e);
        }
    });
}

function updateElementAfterClick(element) {
    if (typeof bootstrap !== 'undefined') {
        const popoverInstance = bootstrap.Popover.getInstance(element);
        if (popoverInstance) {
            const isRead = element.classList.contains('forum_old') || element.classList.contains('forum_off');
            const newStatus = isRead ? 'read' : 'unread';
            const newContent = `
                <div class="popover-content">
                    <div class="mb-2"><strong>Forum marked as ${newStatus}</strong></div>
                    <div class="small text-muted">Click again to change status</div>
                </div>
            `;
            
            popoverInstance.setContent({
                '.popover-body': newContent
            });
        }
    }
}

// ========== Category Toggle Function ==========
window.toggleCategory = function(fid) {
    const container = document.getElementById('cat_' + fid + '_e');
    const icon = document.getElementById('cat_' + fid + '_icon');
    
    if (!container || !icon) return;
    
    if (container.style.display === 'none' || getComputedStyle(container).display === 'none') {
        container.style.display = 'block';
        container.style.animation = 'slideIn 0.4s ease';
        icon.style.transform = 'rotate(180deg)';
    } else {
        container.style.animation = 'fadeOut 0.4s ease';
        icon.style.transform = 'rotate(0deg)';
        
        setTimeout(() => {
            container.style.display = 'none';
        }, 400);
    }
};

// ========== Forum Animations ==========
function initForumAnimations() {
    const forumItems = document.querySelectorAll('.forum-item');
    forumItems.forEach((item, i) => {
        item.style.animation = `forumAppear 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards`;
        item.style.animationDelay = `${i * 0.1}s`;
    });
    
    const categoryCards = document.querySelectorAll('.category-card');
    categoryCards.forEach((card, i) => {
        card.style.animation = `forumAppear 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards`;
        card.style.animationDelay = `${i * 0.1}s`;
    });
}

// ========== Popover Initialization ==========
function initForumPopovers() {
    if (typeof bootstrap === 'undefined') return;
    
    const otherPopovers = document.querySelectorAll('[data-bs-toggle="popover"]:not(.ajax_mark_read)');
    
    otherPopovers.forEach(element => {
        const trigger = element.getAttribute('data-bs-trigger') || 'hover';
        const placement = element.getAttribute('data-bs-placement') || 'top';
        const html = element.getAttribute('data-bs-html') === 'true';
        
        try {
            new bootstrap.Popover(element, {
                container: 'body',
                trigger: trigger,
                placement: placement,
                html: html,
                sanitize: false,
                customClass: 'forum-popover',
                delay: { show: 100, hide: 100 }
            });
        } catch (error) {
            console.error('Error creating popover:', error);
        }
    });
}

// ========== Prevent multiple executions ==========
if (!window.forumScriptsInitialized) {
    window.forumScriptsInitialized = true;
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('=== Forum Enhancement Initializing ===');
        
        setTimeout(function() {
            enhanceForumSystem();
            initForumAnimations();
            initForumPopovers();
        }, 1000);
    });
} else {
    console.log('Forum scripts already initialized');
}