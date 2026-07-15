// tooltip_image.js
document.addEventListener("DOMContentLoaded", function() {
    const imageElements = document.querySelectorAll('[data-img][data-toggle="popover"]');
    imageElements.forEach(function(element) {
        const imgSrc = element.getAttribute('data-img');
        
        element.setAttribute('data-bs-toggle', 'popover');
        element.setAttribute('data-bs-content', 
            `<img class="rounded" border="0" width="250" src="${imgSrc}" />`
        );
        element.setAttribute('data-bs-html', 'true');
        element.setAttribute('data-bs-trigger', 'hover');
        element.setAttribute('data-bs-placement', 'right');
        
       
        new bootstrap.Popover(element, {
            trigger: 'hover',
            placement: 'right',
            customClass: 'image-popover'
        });
    });
});