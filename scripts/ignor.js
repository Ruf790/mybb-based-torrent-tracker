function slideToggle(element, show) {
    if (show) {
        element.style.display = "block";
        const height = element.scrollHeight + "px";
        element.style.height = "0px";
        requestAnimationFrame(() => {
            element.style.transition = "height 0.3s ease";
            element.style.height = height;
        });
        setTimeout(() => {
            element.style.height = "";
            element.style.transition = "";
        }, 300);
    } else {
        element.style.height = element.scrollHeight + "px";
        requestAnimationFrame(() => {
            element.style.transition = "height 0.3s ease";
            element.style.height = "0px";
        });
        setTimeout(() => {
            element.style.display = "none";
            element.style.height = "";
            element.style.transition = "";
        }, 300);
    }
}

function showIgnoredPost(pid) {
    const ignoredPost = document.getElementById("ignored_post_" + pid);
    const normalPost  = document.getElementById("post_" + pid);
    const button      = document.querySelector("#show_ignored_link_" + pid + " button");
    const icon        = button.querySelector("i");
    const text        = button.querySelector("span");
    const showLabel   = button.dataset.showLabel;
    const hideLabel   = button.dataset.hideLabel;

    const ignoredVisible = ignoredPost.style.display !== "none";

    if (ignoredVisible) {
        slideToggle(ignoredPost, false);
        slideToggle(normalPost, true);
        icon.classList.replace("bi-eye", "bi-eye-slash");
        text.textContent = hideLabel;
        button.classList.replace("btn-outline-primary", "btn-outline-secondary");
    } else {
        slideToggle(ignoredPost, true);
        slideToggle(normalPost, false);
        icon.classList.replace("bi-eye-slash", "bi-eye");
        text.textContent = showLabel;
        button.classList.replace("btn-outline-secondary", "btn-outline-primary");
    }
}