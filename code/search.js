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

  function score(item, query) {
    if (!query) {
      return item.type === "article" ? 2 : 1;
    }

    var name = normalize(item.name);
    var label = normalize(item.label);
    var tag = normalize(item.tag);
    if (name === query || label === query || tag === query) {
      return 100;
    }
    if (name.indexOf(query) === 0 || label.indexOf(query) === 0 || tag.indexOf(query) === 0) {
      return 70;
    }
    if (name.indexOf(query) !== -1 || label.indexOf(query) !== -1 || tag.indexOf(query) !== -1) {
      return 45;
    }
    return item.type === "article" ? 12 : 8;
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
      }).sort(function (left, right) {
        return score(right, query) - score(left, query);
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
    var buttons = Array.prototype.slice.call(document.querySelectorAll("[data-type-filter]"));
    var jumps = Array.prototype.slice.call(document.querySelectorAll("[data-type-jump]"));
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

      buttons.forEach(function (button) {
        button.classList.toggle("is-active", button.dataset.typeFilter === type);
      });
    }

    queryInput.addEventListener("input", render);
    typeSelect.addEventListener("change", render);
    buttons.forEach(function (button) {
      button.addEventListener("click", function () {
        typeSelect.value = button.dataset.typeFilter;
        render();
      });
    });
    jumps.forEach(function (jump) {
      jump.addEventListener("click", function () {
        typeSelect.value = jump.dataset.typeJump;
        render();
      });
    });
    render();
  }

  function initKeyboardShortcuts() {
    var menuSearch = document.getElementById("snippet-search");
    var browseSearch = document.getElementById("browse-query");
    document.addEventListener("keydown", function (event) {
      var targetName = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : "";
      var isTyping = targetName === "input" || targetName === "select" || targetName === "textarea";
      if (event.key === "/" && !isTyping) {
        event.preventDefault();
        if (menuSearch) {
          menuSearch.focus();
        }
      }
      if (event.key === "Escape") {
        if (menuSearch && document.activeElement === menuSearch) {
          menuSearch.value = "";
          menuSearch.dispatchEvent(new Event("input"));
          menuSearch.blur();
        }
        if (browseSearch && document.activeElement === browseSearch) {
          browseSearch.value = "";
          browseSearch.dispatchEvent(new Event("input"));
          browseSearch.blur();
        }
      }
    });
  }

  fetch("searchIndex.php")
    .then(function (response) {
      return response.json();
    })
    .then(function (index) {
      initMenuSearch(index);
      initBrowseFilter();
      initKeyboardShortcuts();
    })
    .catch(function () {
      var results = document.getElementById("snippet-search-results");
      if (results) {
        results.innerHTML = "<p>Search is unavailable.</p>";
      }
      initBrowseFilter();
      initKeyboardShortcuts();
    });
})();
