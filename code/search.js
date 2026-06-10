(function () {
  function normalize(value) {
    return (value || "").toLowerCase().trim();
  }

  function resultMarkup(item) {
    var excerpt = item.excerpt ? "<span>" + escapeHtml(item.excerpt) + "</span>" : "";
    return (
      "<a class=\"search-result\" href=\"" + escapeHtml(item.url) + "\">" +
      "<strong>" + escapeHtml(item.name) + "</strong>" +
      "<small>" + escapeHtml(item.type) + " / " + escapeHtml(item.label) + "</small>" +
      excerpt +
      "</a>"
    );
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function matches(item, query, type) {
    if (type && item.type !== type) {
      return false;
    }
    if (!query) {
      return true;
    }
    return item.searchText.indexOf(query) !== -1;
  }

  function initMenuSearch(index) {
    var queryInput = document.getElementById("snippet-search");
    var typeSelect = document.getElementById("snippet-search-type");
    var results = document.getElementById("snippet-search-results");
    if (!queryInput || !typeSelect || !results) {
      return;
    }

    function render() {
      var query = normalize(queryInput.value);
      var type = typeSelect.value;
      if (!query && !type) {
        results.innerHTML = "<p>Search titles, labels, and snippet text.</p>";
        return;
      }

      var hits = index.items.filter(function (item) {
        return matches(item, query, type);
      }).slice(0, 12);

      if (!hits.length) {
        results.innerHTML = "<p>No matches.</p>";
        return;
      }

      results.innerHTML = hits.map(resultMarkup).join("");
    }

    queryInput.addEventListener("input", render);
    typeSelect.addEventListener("change", render);
    render();
  }

  function initBrowseFilter() {
    var queryInput = document.getElementById("browse-query");
    var typeSelect = document.getElementById("browse-type");
    var entries = Array.prototype.slice.call(document.querySelectorAll(".snippet-entry"));
    var groups = Array.prototype.slice.call(document.querySelectorAll(".browse-group"));
    if (!queryInput || !typeSelect || !entries.length) {
      return;
    }

    function render() {
      var query = normalize(queryInput.value);
      var type = typeSelect.value;

      entries.forEach(function (entry) {
        var visible = (!type || entry.dataset.type === type) &&
          (!query || normalize(entry.dataset.search).indexOf(query) !== -1);
        entry.hidden = !visible;
      });

      groups.forEach(function (group) {
        var visibleEntries = group.querySelectorAll(".snippet-entry:not([hidden])").length;
        group.hidden = visibleEntries === 0;
        if ((query || type) && visibleEntries > 0) {
          group.open = true;
        }
      });
    }

    queryInput.addEventListener("input", render);
    typeSelect.addEventListener("change", render);
    render();
  }

  fetch("searchIndex.php")
    .then(function (response) {
      return response.json();
    })
    .then(function (index) {
      initMenuSearch(index);
      initBrowseFilter();
    })
    .catch(function () {
      var results = document.getElementById("snippet-search-results");
      if (results) {
        results.innerHTML = "<p>Search is unavailable.</p>";
      }
      initBrowseFilter();
    });
})();
