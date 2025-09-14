function popupWindow(url, options, root) {
    if (!options) options = {};
    if (root !== true) url = rootpath + url;

    $.get(url, function(html) {
        $('#dynamicModal').remove();
        $('body').append(html);

        setTimeout(() => {
            const modalElement = document.getElementById('dynamicModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement, { backdrop: 'static', keyboard: true });
                modal.show();

                modalElement.addEventListener('hidden.bs.modal', () => $('#dynamicModal').remove());

                if (typeof window.initTabs === 'function') {
                    window.initTabs(modalElement);
                }

                $(modalElement).find('#permissionTabs button').on('click', function(e) {
                    e.preventDefault();
                    $(this).tab('show');
                });
            }
        }, 50);
    });

    $(document).off('submit', '#modal_form');
    $(document).on('submit', '#modal_form', function(e) {
        e.preventDefault();
        const form = $(this);

        $.ajax({
            type: 'POST',
            url: form.attr('action'),
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('dynamicModal'));
                    if (modal) modal.hide();
                } else {
                    alert('Error: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Failed to save. Try again.');
            }
        });
    });
}
