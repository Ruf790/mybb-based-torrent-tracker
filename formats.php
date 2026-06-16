<?php
declare(strict_types=1);

require_once 'global.php';

gzip();

$lang->load('formats');
stdhead($lang->formats['head']);

echo $lang->formats['info'];

?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput  = document.getElementById('formatSearch');
    const clearButton  = document.getElementById('clearSearch');
    const tagLinks     = document.querySelectorAll('.tag-link');
    const formatCards  = document.querySelectorAll('[data-format]');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();

        formatCards.forEach(card => {
            const format = card.getAttribute('data-format');
            const text   = card.textContent.toLowerCase();
            const match  = searchTerm === '' || (format && format.includes(searchTerm)) || text.includes(searchTerm);

            card.style.display = match ? 'block' : 'none';
            card.classList.toggle('d-none', !match);
        });
    });

    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
        searchInput.focus();
    });

    tagLinks.forEach(tag => {
        tag.addEventListener('click', function() {
            const tagName = this.getAttribute('data-tag');
            if (tagName) {
                searchInput.value = tagName;
                searchInput.dispatchEvent(new Event('input'));
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            searchInput.focus();
        }
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
        }
    });
});
</script>
<?php

stdfoot();