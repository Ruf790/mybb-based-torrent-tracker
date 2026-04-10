function selectCategory(btn) {
    document.querySelectorAll('.cat-pick-btn').forEach(b => {
        b.classList.remove('active');
        b.style.borderColor = '';
    });

    btn.classList.add('active');
    document.getElementById('categorySelected').value = btn.dataset.id;
    document.getElementById('categoryError').style.display = 'none';
    document.getElementById('categoryLabel').innerHTML =
        '<i class="fas fa-check-circle text-success me-1"></i>' +
        'Selected: <strong>' + btn.dataset.name + '</strong>';
}