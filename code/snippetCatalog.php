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

function snippetAtlasTopics($catalog) {
    $topicDefinitions = array(
        "Symplectic Geometry" => array("symplectic", "hamiltonian", "moser", "darboux", "weinstein"),
        "Lagrangians" => array("lagrangian", "surgery", "cobordism", "thimble"),
        "Floer Theory" => array("floer", "heegaard", "holomorphic", "strip"),
        "Symplectic Cohomology" => array("symplectic cohomology", "viterbo", "reeb", "liouville"),
        "Tropical Geometry" => array("tropical", "polyhedral", "chow", "rational equivalence"),
        "Categories" => array("category", "module", "twisted", "infinity", "triangulated"),
        "Mirror Symmetry" => array("mirror", "fukaya", "seidel", "landau", "fano"),
    );

    $topics = array();
    foreach ($topicDefinitions as $topic => $keywords) {
        $matches = array();
        foreach ($catalog["items"] as $item) {
            foreach ($keywords as $keyword) {
                if (str_contains($item["searchText"], strtolower($keyword))) {
                    $matches[] = $item;
                    break;
                }
            }
        }

        usort($matches, function ($left, $right) {
            $score = function ($item) {
                if ($item["type"] === "article") {
                    return 0;
                }
                if (in_array($item["type"], array("definition", "theorem"))) {
                    return 1;
                }
                return 2;
            };

            $scoreCompare = $score($left) <=> $score($right);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }
            return strcmp(strtolower($left["name"]), strtolower($right["name"]));
        });

        $topics[$topic] = $matches;
    }

    return $topics;
}

function snippetLearningTrails() {
    return array(
        array(
            "name" => "First Contact",
            "description" => "Start with the local models and core vocabulary.",
            "tags" => array("art_basicSymplectic", "def_symplecticManifold", "def_lagrangianSubmanifold", "thm_weinsteinNeighborhood"),
        ),
        array(
            "name" => "Lagrangian Toolkit",
            "description" => "Move from examples to surgery and cobordisms.",
            "tags" => array("art_lagrangianSubmanifolds", "exm_lagrangiansFromConormals", "con_polterovichSurgery", "art_lagrangianCobordisms"),
        ),
        array(
            "name" => "Floer Corridor",
            "description" => "Follow the bridge from symmetric products to Heegaard Floer theory.",
            "tags" => array("art_heegaardFloer", "art_heegaardFloerConstruction", "def_heegaardDiagram", "thm_invarianceOfHeegaardFloer"),
        ),
        array(
            "name" => "Cohomology Engine",
            "description" => "Trace Liouville domains, Reeb flow, and Viterbo restriction.",
            "tags" => array("art_symplecticCohomologyExposition", "def_liouvilleDomain", "def_reebVectorField", "thm_viterboRestriction"),
        ),
        array(
            "name" => "Tropical Wing",
            "description" => "Read the tropical material through cycles and rational equivalence.",
            "tags" => array("art_tropicalGeometryIntroduction", "def_TropicalChowGroup", "prp_TropicalPushforward", "art_RationalEquivalenceInTropicalGeometry"),
        ),
    );
}

function renderSnippetAtlas($catalog) {
    $types = snippetCatalogTypes($catalog);
    $topics = snippetAtlasTopics($catalog);
    $total = count($catalog["items"]);
    $articleCount = $types["article"] ?? 0;
    $definitionCount = $types["definition"] ?? 0;
    $theoremCount = $types["theorem"] ?? 0;

    $html = "<section class='atlas-panel' id='atlas'>\n";
    $html .= "<div class='atlas-header'>\n";
    $html .= "<p class='kicker'>Symplectic Snippets Atlas</p>\n";
    $html .= "<h2>Paths, shelves, and cross-links through the notes</h2>\n";
    $html .= "</div>\n";

    $html .= "<div class='atlas-stats'>\n";
    $html .= renderAtlasStat($total, "snippets", "whole corpus");
    $html .= renderAtlasStat($articleCount, "articles", "guided routes");
    $html .= renderAtlasStat($definitionCount, "definitions", "vocabulary");
    $html .= renderAtlasStat($theoremCount, "theorems", "anchors");
    $html .= "</div>\n";

    $html .= "<div class='type-ribbon' aria-label='Snippet type counts'>\n";
    foreach ($types as $type => $count) {
        $width = max(4, round(($count / max(1, $total)) * 100));
        $html .= "<a href='#browse' class='type-chip' data-type-jump='" . escapeHtml($type) . "' style='--w:" . escapeHtml($width) . "%'>";
        $html .= "<span>" . escapeHtml($type) . "</span><b>" . escapeHtml($count) . "</b></a>\n";
    }
    $html .= "</div>\n";

    $html .= "<div class='trail-grid'>\n";
    foreach (snippetLearningTrails() as $trail) {
        $html .= "<section class='trail-card'>\n";
        $html .= "<h3>" . escapeHtml($trail["name"]) . "</h3>\n";
        $html .= "<p>" . escapeHtml($trail["description"]) . "</p>\n<ol>\n";
        foreach ($trail["tags"] as $tag) {
            if (!isset($catalog["byTag"][$tag])) {
                continue;
            }
            $item = $catalog["byTag"][$tag];
            $html .= "<li><a href='" . escapeHtml($item["url"]) . "'>" . escapeHtml($item["name"]) . "</a>";
            $html .= "<span>" . escapeHtml($item["type"]) . "</span></li>\n";
        }
        $html .= "</ol>\n</section>\n";
    }
    $html .= "</div>\n";

    $html .= "<div class='topic-board'>\n";
    foreach ($topics as $topic => $items) {
        if ($items === array()) {
            continue;
        }
        $html .= "<section class='topic-lane'>\n";
        $html .= "<h3>" . escapeHtml($topic) . " <span>" . escapeHtml(count($items)) . "</span></h3>\n<ul>\n";
        foreach (array_slice($items, 0, 5) as $item) {
            $html .= "<li><a href='" . escapeHtml($item["url"]) . "'>" . escapeHtml($item["name"]) . "</a>";
            $html .= "<span>" . escapeHtml($item["type"]) . "</span></li>\n";
        }
        $html .= "</ul>\n</section>\n";
    }
    $html .= "</div>\n";
    $html .= "</section>\n";

    return $html;
}

function renderAtlasStat($number, $label, $description) {
    return "<div class='atlas-stat'><strong>" . escapeHtml($number) . "</strong><span>" . escapeHtml($label) . "</span><small>" . escapeHtml($description) . "</small></div>\n";
}

function renderSnippetBrowse($catalog) {
    $types = snippetCatalogTypes($catalog);
    $html = "<section class='browse-panel' id='browse'>\n";
    $html .= "<h2>Browse snippets</h2>\n";
    $html .= "<div class='browse-controls'>\n";
    $html .= "<input id='browse-query' type='search' placeholder='Filter snippets' aria-label='Filter snippets'>\n";
    $html .= "<select id='browse-type' aria-label='Filter by type'>" . renderSnippetTypeOptions($types) . "</select>\n";
    $html .= "</div>\n";
    $html .= "<div class='quick-filters' aria-label='Quick type filters'>\n";
    foreach (array("article", "definition", "theorem", "example", "exercise", "figure") as $type) {
        if (!isset($types[$type])) {
            continue;
        }
        $html .= "<button type='button' data-type-filter='" . escapeHtml($type) . "'>" . escapeHtml($type) . "</button>\n";
    }
    $html .= "<button type='button' data-type-filter=''>all</button>\n";
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

function renderSnippetConnections($tag, $catalog) {
    if (!isset($catalog["byTag"][$tag])) {
        return "";
    }

    $current = $catalog["byTag"][$tag];
    $uses = array();
    foreach ($current["targets"] as $target) {
        $targetTag = $target;
        if (!isset($catalog["byTag"][$targetTag]) && isset($catalog["byLabel"][$target])) {
            $targetTag = $catalog["byLabel"][$target];
        }
        if (isset($catalog["byTag"][$targetTag]) && $targetTag !== $tag) {
            $uses[$targetTag] = $catalog["byTag"][$targetTag];
        }
    }

    $backlinks = snippetBacklinks($tag, $catalog);
    if ($uses === array() && $backlinks === array()) {
        return "";
    }

    $html = "<section class='connection-panel' id='connections'>\n";
    $html .= "<h2>Connections</h2>\n";
    $html .= "<div class='connection-grid'>\n";
    $html .= renderConnectionColumn("Uses", array_values($uses));
    $html .= renderConnectionColumn("Used in", $backlinks);
    $html .= "</div>\n</section>\n";

    return $html;
}

function renderConnectionColumn($title, $items) {
    $html = "<div class='connection-column'><h3>" . escapeHtml($title) . "</h3>\n";
    if ($items === array()) {
        $html .= "<p>No direct links found.</p></div>\n";
        return $html;
    }

    $html .= "<ul>\n";
    foreach (array_slice($items, 0, 10) as $item) {
        $html .= "<li><a href='" . escapeHtml($item["url"]) . "'>" . escapeHtml($item["name"]) . "</a>";
        $html .= "<span>" . escapeHtml($item["type"]) . " / " . escapeHtml($item["label"]) . "</span></li>\n";
    }
    $html .= "</ul></div>\n";
    return $html;
}
