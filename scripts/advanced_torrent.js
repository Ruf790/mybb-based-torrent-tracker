// Advanced JavaScript

// Enhanced initialization
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

// File tree functions with enhanced UX
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

// Add smooth scrolling for anchor links
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
