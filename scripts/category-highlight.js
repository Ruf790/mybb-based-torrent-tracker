document.addEventListener('DOMContentLoaded', function () {
    const categoryLinks = document.querySelectorAll('.category-link');
    const highlightedId = localStorage.getItem('highlightedCategory');

    // Highlight previously selected category
    if (highlightedId) {
        const targetBlock = document.querySelector('.category-container[data-category-id="' + highlightedId + '"]');
        if (targetBlock) {
            targetBlock.classList.add('category-highlight');
        }
    }

    // Set new highlight on click
    categoryLinks.forEach(link => {
        link.addEventListener('click', function () {
            const catId = this.getAttribute('data-cat-id');
            localStorage.setItem('highlightedCategory', catId);
        });
    });
});

// Clear the stored category when the page is unloaded
setTimeout(() => {
    localStorage.removeItem('highlightedCategory');
}, 5000); // Clears after 5 seconds