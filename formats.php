<?



  require_once 'global.php';
  gzip ();
 
  $lang->load ('formats');
  stdhead ($lang->formats['head']);
  
  echo $lang->formats['info'];
  
  
  echo '
<script>
document.addEventListener(\'DOMContentLoaded\', function() {
    const searchInput = document.getElementById(\'formatSearch\');
    const clearButton = document.getElementById(\'clearSearch\');
    const tagLinks = document.querySelectorAll(\'.tag-link\');
    const formatCards = document.querySelectorAll(\'[data-format]\');
    
    searchInput.addEventListener(\'input\', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        formatCards.forEach(card => {
            const format = card.getAttribute(\'data-format\');
            const text = card.textContent.toLowerCase();
            
            if (searchTerm === \'\' || (format && format.includes(searchTerm)) || text.includes(searchTerm)) {
                card.style.display = \'block\';
                card.classList.remove(\'d-none\');
            } else {
                card.style.display = \'none\';
                card.classList.add(\'d-none\');
            }
        });
    });
    
    clearButton.addEventListener(\'click\', function() {
        searchInput.value = \'\';
        searchInput.dispatchEvent(new Event(\'input\'));
        searchInput.focus();
    });
    
    tagLinks.forEach(tag => {
        tag.addEventListener(\'click\', function() {
            const tagName = this.getAttribute(\'data-tag\');
            if (tagName) {
                searchInput.value = tagName;
                searchInput.dispatchEvent(new Event(\'input\'));
            }
        });
    });
    
    // Ctrl+F focus
    document.addEventListener(\'keydown\', function(e) {
        if (e.ctrlKey && e.key === \'f\') {
            e.preventDefault();
            searchInput.focus();
        }
        if (e.key === \'Escape\' && document.activeElement === searchInput) {
            searchInput.value = \'\';
            searchInput.dispatchEvent(new Event(\'input\'));
        }
    });
});
</script>
';

  
  
  
  
  
  stdfoot ();
?>
