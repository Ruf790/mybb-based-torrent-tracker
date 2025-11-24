document.addEventListener("DOMContentLoaded", function () {
  const input = document.getElementById("torrent-search");
  const results = document.getElementById("autocomplete-results");
  let debounceTimer;

  input.addEventListener("input", function () {
    const query = input.value.trim();

    clearTimeout(debounceTimer);
    if (query.length < 3) {
      results.classList.remove("show");
      results.innerHTML = '';
      return;
    }

    debounceTimer = setTimeout(() => {
      fetch("xmlhttp.php?action=search_torrents&input=" + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
          results.innerHTML = '';

          if (!Array.isArray(data) || data.length === 0) {
            results.innerHTML = '<a class="dropdown-item disabled">No results found</a>';
            results.classList.add("show");
            return;
          }

          data.forEach(item => {
            if (!item.name || !item.id) return;
            const img = item.image_url ? `<img src="${item.image_url}" alt="" style="width:40px;height:auto;margin-right:10px;">` : "";
            const option = document.createElement("a");
            option.classList.add("dropdown-item", "d-flex", "align-items-center");
            option.href = "details.php?id=" + item.id;
            option.innerHTML = img + `<span>${item.name}</span>`;
            results.appendChild(option);
          });

          results.classList.add("show");
        })
        .catch(() => {
          results.innerHTML = '<a class="dropdown-item disabled">Error retrieving results</a>';
          results.classList.add("show");
        });
    }, 300); // Debounce delay
  });

  // Hide dropdown when clicking outside
  document.addEventListener("click", function (e) {
    if (!e.target.closest("#torrent-search, #autocomplete-results")) {
      results.classList.remove("show");
      results.innerHTML = '';
    }
  });
});
