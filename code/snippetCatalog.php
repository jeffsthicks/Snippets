<?php

require_once __DIR__ . "/snippetHelpers.php";

function readSnippetMetadataAndBody($path) {
    $metadata = array();
    $bodyLines = array();
    $inHeader = true;
    $handle = fopen($path, "r");

    while (($line = fgets($handle)) !== false) {
        if ($inHeader && str_starts_with($line, "%")) {
            if (preg_match('/^%([A-Za-z]+):"(.*)"\s*$/', rtrim($line, "\r\n"), $matches) === 1) {
                $metadata[$matches[1]] = $matches[2];
            }
            continue;
        }

        $inHeader = false;
        $bodyLines[] = $line;
    }

    fclose($handle);
    return array($metadata, implode("", $bodyLines));
}

function snippetPlainText($text) {
    $text = preg_replace('/%.*$/m', ' ', $text);
    $text = preg_replace('/\\\\(input|label|cite|Cite|cref|Cref)\{[^\}]*\}/', ' ', $text);
    $text = preg_replace('/\\\\snip\{([^\}]*)\}\{[^\}]*\}/', '$1', $text);
    $text = preg_replace('/\\\\href\{[^\}]*\}\{([^\}]*)\}/', '$1', $text);
    $text = preg_replace('/\\\\[A-Za-z]+\*?(?:\[[^\]]*\])?\{([^\}]*)\}/', '$1', $text);
    $text = preg_replace('/\\\\[A-Za-z]+/', ' ', $text);
    $text = str_replace(array('$', '{', '}', '[', ']', '\\'), ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function snippetExcerpt($text, $length = 180) {
    $text = snippetPlainText($text);
    if (strlen($text) <= $length) {
        return $text;
    }

    return rtrim(substr($text, 0, $length - 1)) . "...";
}

function snippetTargetsFromBody($body) {
    $targets = array();

    foreach (array('/\\\\input\{([^\}]*)\}/', '/\\\\snip\{[^\}]*\}\{([^\}]*)\}/') as $pattern) {
        if (preg_match_all($pattern, $body, $matches) !== false) {
            foreach ($matches[1] as $target) {
                $target = normalizeSnippetTag($target);
                if ($target !== null) {
                    $targets[] = $target;
                }
            }
        }
    }

    if (preg_match_all('/\\\\[cC]ref\{([^\}]*)\}/', $body, $matches) !== false) {
        foreach ($matches[1] as $target) {
            $target = trim($target);
            if ($target !== "") {
                $targets[] = $target;
                $targets[] = snippetUrlTag($target);
            }
        }
    }

    return array_values(array_unique($targets));
}

function loadSnippetCatalog() {
    static $catalog = null;

    if ($catalog !== null) {
        return $catalog;
    }

    $items = array();
    $byTag = array();
    $byLabel = array();

    foreach (glob(SNIPPET_TAG_DIR . DIRECTORY_SEPARATOR . "*.tex") as $path) {
        $tag = basename($path, ".tex");
        list($metadata, $body) = readSnippetMetadataAndBody($path);
        $label = $metadata["label"] ?? str_replace("_", ":", $tag);
        $type = $metadata["type"] ?? "unknown";
        $name = $metadata["name"] ?? $tag;

        $item = array(
            "tag" => $tag,
            "label" => $label,
            "type" => $type,
            "name" => $name,
            "caption" => $metadata["caption"] ?? "",
            "parent" => $metadata["parent"] ?? "",
            "source" => $metadata["source"] ?? "",
            "url" => snippetUrlTag($tag),
            "excerpt" => snippetExcerpt($body),
            "browseText" => strtolower($tag . " " . $label . " " . $type . " " . $name . " " . snippetExcerpt($body)),
            "searchText" => strtolower($tag . " " . $label . " " . $type . " " . $name . " " . snippetPlainText($body)),
            "targets" => snippetTargetsFromBody($body),
        );

        $items[] = $item;
        $byTag[$tag] = $item;
        $byLabel[$label] = $tag;
    }

    usort($items, function ($left, $right) {
        $typeCompare = strcmp($left["type"], $right["type"]);
        if ($typeCompare !== 0) {
            return $typeCompare;
        }
        return strcmp(strtolower($left["name"]), strtolower($right["name"]));
    });

    $catalog = array(
        "items" => $items,
        "byTag" => $byTag,
        "byLabel" => $byLabel,
    );

    return $catalog;
}

function snippetCatalogTypes($catalog) {
    $types = array();
    foreach ($catalog["items"] as $item) {
        $type = $item["type"];
        $types[$type] = ($types[$type] ?? 0) + 1;
    }
    ksort($types);
    return $types;
}

function snippetBacklinks($tag, $catalog) {
    if (!isset($catalog["byTag"][$tag])) {
        return array();
    }

    $current = $catalog["byTag"][$tag];
    $targets = array($tag, $current["label"], snippetUrlTag($current["label"]));
    $backlinks = array();

    foreach ($catalog["items"] as $item) {
        if ($item["tag"] === $tag) {
            continue;
        }

        if (array_intersect($targets, $item["targets"]) !== array()) {
            $backlinks[] = $item;
        }
    }

    usort($backlinks, function ($left, $right) {
        $articleScore = function ($item) {
            return $item["type"] === "article" ? 0 : 1;
        };
        $scoreCompare = $articleScore($left) <=> $articleScore($right);
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }
        return strcmp(strtolower($left["name"]), strtolower($right["name"]));
    });

    return $backlinks;
}

function renderSnippetTypeOptions($types) {
    $html = '<option value="">All types</option>';
    foreach ($types as $type => $count) {
        $html .= '<option value="' . escapeHtml($type) . '">' . escapeHtml($type) . ' (' . escapeHtml($count) . ')</option>';
    }
    return $html;
}

function renderSnippetBrowse($catalog) {
    $types = snippetCatalogTypes($catalog);
    $html = "<section class='browse-panel' id='browse'>\n";
    $html .= "<h2>Browse snippets</h2>\n";
    $html .= "<div class='browse-controls'>\n";
    $html .= "<input id='browse-query' type='search' placeholder='Filter snippets' aria-label='Filter snippets'>\n";
    $html .= "<select id='browse-type' aria-label='Filter by type'>" . renderSnippetTypeOptions($types) . "</select>\n";
    $html .= "</div>\n";

    foreach ($types as $type => $count) {
        $open = in_array($type, array("article", "definition", "theorem")) ? " open" : "";
        $html .= "<details class='browse-group' data-type='" . escapeHtml($type) . "'$open>\n";
        $html .= "<summary>" . escapeHtml($type) . " <span>" . escapeHtml($count) . "</span></summary>\n";
        $html .= "<ul class='snippet-list'>\n";

        foreach ($catalog["items"] as $item) {
            if ($item["type"] !== $type) {
                continue;
            }
            $html .= "<li class='snippet-entry' data-type='" . escapeHtml($item["type"]) . "' data-search='" . escapeHtml($item["browseText"]) . "'>";
            $html .= "<a href='" . escapeHtml($item["url"]) . "'>" . escapeHtml($item["name"]) . "</a>";
            $html .= " <span class='snippet-label'>" . escapeHtml($item["label"]) . "</span>";
            $html .= "</li>\n";
        }

        $html .= "</ul>\n</details>\n";
    }

    $html .= "</section>\n";
    return $html;
}

function renderSnippetBacklinks($tag, $catalog) {
    $backlinks = snippetBacklinks($tag, $catalog);
    if ($backlinks === array()) {
        return "";
    }

    $html = "<section class='related-snippets' id='used-in'>\n";
    $html .= "<h2>Used in</h2>\n<ul>\n";
    foreach (array_slice($backlinks, 0, 16) as $item) {
        $html .= "<li><a href='" . escapeHtml($item["url"]) . "'>" . escapeHtml($item["name"]) . "</a>";
        $html .= " <span class='snippet-label'>" . escapeHtml($item["type"]) . " / " . escapeHtml($item["label"]) . "</span></li>\n";
    }
    $html .= "</ul>\n</section>\n";
    return $html;
}
