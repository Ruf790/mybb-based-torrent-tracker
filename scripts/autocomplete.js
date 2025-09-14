$(document).ready(function () {
  const $input = $("#torrent-search");
  const $results = $("#autocomplete-results");

  let debounceTimer;

  $input.on("input", function () {
    const query = $(this).val().trim();

    clearTimeout(debounceTimer);
    if (query.length < 3) {
      $results.removeClass("show").empty();
      return;
    }

    debounceTimer = setTimeout(() => {
      $.ajax({
        url: "xmlhttp.php",
        dataType: "json",
        data: { action: "search_torrents", input: query },
        success: function (data) {
          $results.empty();

          if (!Array.isArray(data) || data.length === 0) {
            $results.append('<a class="dropdown-item disabled">No results found</a>').addClass("show");
            return;
          }

          data.forEach(item => {
            if (!item.name || !item.id) return;
            const img = item.image_url ? `<img src="${item.image_url}" alt="" style="width:40px;height:auto;margin-right:10px;">` : "";
            const $option = $(`<a class="dropdown-item d-flex align-items-center" href="details.php?id=${item.id}">
                                ${img}<span>${item.name}</span>
                               </a>`);
            $results.append($option);
          });

          $results.addClass("show");
        },
        error: function () {
          $results.html('<a class="dropdown-item disabled">Error retrieving results</a>').addClass("show");
        }
      });
    }, 300); // Debounce delay
  });

  // Hide dropdown when clicking outside
  $(document).on("click", function (e) {
    if (!$(e.target).closest("#torrent-search, #autocomplete-results").length) {
      $results.removeClass("show").empty();
    }
  });
});