/*
 * ArtCore Gangsta — core script, loaded on every page from header.php.
 */

// Global JS lang object: pages add their own strings (lang.no_new_posts = ..., lang.add_attachment = ...),
// scripts read them (post.js, usercp.js, regvalidator.js).
var lang = window.lang || {};

// Avatar fallback: a broken author avatar is replaced with the "not found" SVG.
(function () {
    function swapToFallback(img) {
        if (!window.AVATAR_NOT_FOUND_URL || img.dataset.avatarFallback === '1') {
            return;
        }
        img.dataset.avatarFallback = '1';
        img.src = window.AVATAR_NOT_FOUND_URL;
        img.classList.remove('rounded');
        img.classList.add('avatar-ring');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.author_avatar img').forEach(function (img) {
            img.addEventListener('error', function () { swapToFallback(img); }, { once: true });

            // The image may have failed before this listener was attached (fast 404 / cached error).
            // SVGs are skipped: an SVG without intrinsic size also reports naturalWidth 0.
            if (img.complete && img.naturalWidth === 0 && !/\.svg(\?|#|$)/i.test(img.currentSrc || img.src)) {
                swapToFallback(img);
            }
        });
    });
})();