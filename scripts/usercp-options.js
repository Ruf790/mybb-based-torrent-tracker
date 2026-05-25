function toggleCatSub(btn) {
    btn.classList.toggle("active");
    const selected = [...document.querySelectorAll("#catSubsPicker .cat-pick-btn.active")]
        .map(b => "[cat" + b.dataset.id + "]").join("");
    document.getElementById("catSubsSelected").value = selected;
}