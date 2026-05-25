function toggleType() {
    var moveCopyRadio = document.getElementById('type_movecopythread');
    var moveCopySection = document.getElementById('type_movecopythread_expanded');
    var mergeRadio = document.getElementById('type_merge');
    var mergeSection = document.getElementById('type_merge_expanded');
    
    if (moveCopySection) {
        if (moveCopyRadio && moveCopyRadio.checked) {
            moveCopySection.style.display = 'block';
            if (mergeSection) mergeSection.style.display = 'none';
        } else if (mergeRadio && mergeRadio.checked) {
            if (moveCopySection) moveCopySection.style.display = 'none';
            if (mergeSection) mergeSection.style.display = 'block';
        } else {
            if (moveCopySection) moveCopySection.style.display = 'none';
            if (mergeSection) mergeSection.style.display = 'none';
        }
    } else if (mergeSection) {
        if (mergeRadio && mergeRadio.checked) mergeSection.style.display = 'block';
        else mergeSection.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var typeRadios = document.querySelectorAll('input[name="type"]');
    typeRadios.forEach(function(radio) {
        radio.addEventListener('change', toggleType);
    });
    toggleType();
});