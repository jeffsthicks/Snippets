<?php

require_once __DIR__ . "/snippetCatalog.php";

class SnippetRenderer {
    private $bibArray;
    private $tagArray;
    private $sectionCounter = array(0, 0, 0);
    private $footnoteCounter = 0;
    private $theoremCounter = 0;
    private $labelArray = array();
    private $sectionDepth = -1;
    private $referenceKeys = array();
    private $tableOfContents = array();
    private $pageTitle = "";
    private $pageSource = "";
    private $pageType = "";

    public function __construct($bibArray, $tagArray) {
        $this->bibArray = $bibArray;
        $this->tagArray = $tagArray;
    }

    public function renderPage($tagName) {
        $bodyText = $this->renderSnippet($tagName);
        $bodyText = $this->resolveCrossReferences($bodyText);

        return array(
            "title" => $this->pageTitle,
            "body" => $bodyText,
            "toc" => $this->tableOfContents,
            "references" => $this->referenceKeys,
        );
    }

    public function renderReferences() {
        $referenceKeys = $this->referenceKeys;
        sort($referenceKeys);

        if (count($referenceKeys) === 0) {
            return "";
        }

        $html = "<h1> References </h1>\n<table>\n";
        foreach ($referenceKeys as $key) {
            $keyText = $this->bibArray["#" . $key] ?? escapeHtml($key);
            $entry = $this->bibArray[$key] ?? "";
            $html .= "<tr id=\"" . escapeHtml($key) . "\"> <td>[$keyText]</td><td>$entry</td></tr>\n";
        }
        $html .= "</table>";

        return $html;
    }

    private function renderSnippet($tagName) {
        $this->sectionDepth++;
        $tagLocation = snippetFilePath($tagName);

        if ($tagLocation === null) {
            $this->sectionDepth--;
            return "<p><b>could not find file " . escapeHtml($tagName) . "</b></p>";
        }

        list($metadata, $body) = readSnippetMetadataAndBody($tagLocation);
        $metadata = $this->withMetadataDefaults($metadata);
        if ($this->sectionDepth === 0) {
            $this->pageSource = $metadata["source"];
            $this->pageType = $metadata["type"];
        }
        $bodyText = "";

        if (!in_array($metadata["type"], array("figure", "diagram"))) {
            foreach (preg_split('/\R/', $body) as $line) {
                $bodyText .= $this->renderLine($line . "\n");
            }
        }
        else {
            $svgFilename = substr($tagName, 4);
            $bodyText = "<img src='tags/" . escapeHtml($metadata["type"]) . "s/" . escapeHtml($svgFilename) . ".svg' alt='" . escapeHtml($metadata["name"]) . "'>";
        }

        $environment = $this->renderEnvironment($metadata, $tagName);
        $this->labelArray[$metadata["label"]] = $environment["index"];
        $this->sectionDepth--;

        return $environment["open"] . $bodyText . $environment["close"];
    }

    private function withMetadataDefaults($metadata) {
        return array(
            "name" => $this->mathText($metadata["name"] ?? "NONAME"),
            "type" => $metadata["type"] ?? "NOTYPE",
            "label" => $metadata["label"] ?? "NOLABEL",
            "caption" => $this->mathText($metadata["caption"] ?? "WARNING:NOCAPTION"),
            "source" => $metadata["source"] ?? "",
            "sourceDetail" => $metadata["sourceDetail"] ?? "",
        );
    }

    private function mathText($text) {
        return preg_replace("/\\$([^\\$]*)\\$/", "\\($1\\)", $text);
    }

    private function renderEnvironment($metadata, $tagName) {
        $type = $metadata["type"];
        $label = $metadata["label"];
        $name = $metadata["name"];
        $caption = $metadata["caption"];
        $source = $this->renderSource($metadata);
        $thisIndex = "";
        $envOpen = "";
        $envClose = "";

        if (in_array($type, array("article", "exposition", "construction"))) {
            if (!isset($this->sectionCounter[$this->sectionDepth])) {
                $this->sectionCounter[$this->sectionDepth] = 0;
            }
            $this->sectionCounter[$this->sectionDepth] = $this->sectionCounter[$this->sectionDepth] + 1;
            $this->sectionCounter[$this->sectionDepth + 1] = 0;
            $this->sectionCounter[$this->sectionDepth + 2] = 0;
            $this->theoremCounter = 0;
            $thisIndex = (string)$this->sectionCounter[1];

            for ($i = 2; $i < $this->sectionDepth + 1; $i++) {
                $thisIndex = "$thisIndex." . $this->sectionCounter[$i];
            }

            if ($this->sectionDepth > 0) {
                $headerSize = $this->sectionDepth + 2;
                $envOpen = "<span class='anchor' id='" . escapeHtml($label) . "'></span>\n";
                $envOpen .= "<h$headerSize index='" . escapeHtml($thisIndex) . "'> " . escapeHtml($thisIndex) . ": " . escapeHtml($name) . " </h$headerSize>";
                $this->tableOfContents[] = array(
                    "depth" => $this->sectionDepth,
                    "item" => "<li> <a href='#" . escapeHtml($label) . "'> " . escapeHtml($thisIndex) . ": " . escapeHtml($name) . " </a> </li>",
                );
            }
            else {
                $this->pageTitle = $name;
            }
        }
        elseif (in_array($type, array("theorem", "definition", "proposition", "lemma", "example", "exercise", "application"))) {
            $this->theoremCounter++;
            $thisIndex = "$type " . $this->sectionCounter[1] . "." . $this->sectionCounter[2] . "." . $this->theoremCounter;
            $envOpen = "<span class='anchor' id='" . escapeHtml($label) . "'></span>\n";
            $envOpen .= "<mathEnvironment class='" . escapeHtml($type) . "' index='" . escapeHtml($thisIndex) . "'>\n";
            $envOpen .= "<h2 class='" . escapeHtml($type) . "'>" . escapeHtml($thisIndex) . " $source</h2>  ";
            $envClose = "</mathEnvironment>";

            if ($type === "exercise") {
                $solName = "sol" . substr($tagName, 3);
                if (snippetFilePath($solName) !== null) {
                    $envClose = "\n <p><a href='index.php?tag=" . escapeHtml($solName) . "'>Click here</a> to view solution.</p>\n</mathEnvironment>\n";
                }
            }
        }
        elseif ($type === "figure") {
            $this->theoremCounter++;
            $thisIndex = "$type " . $this->sectionCounter[1] . "." . $this->sectionCounter[2] . "." . $this->theoremCounter;
            $envOpen = "<span class='anchor' id='" . escapeHtml($label) . "'></span>\n";
            $envOpen .= "<figure class='" . escapeHtml($type) . "' index='" . escapeHtml($thisIndex) . "'>\n";
            $envClose = "<figcaption>" . escapeHtml($thisIndex) . ":" . escapeHtml($caption) . "</figcaption></figure>";
        }

        if ($this->sectionDepth === 0 && $this->pageTitle === "") {
            $this->pageTitle = $name;
        }

        return array("open" => $envOpen, "close" => $envClose, "index" => $thisIndex);
    }

    private function renderSource($metadata) {
        $sourceTag = $metadata["source"];
        if ($sourceTag === "") {
            return "";
        }

        if ($this->sectionDepth > 0 && in_array($this->pageType, array("article", "exposition", "construction")) && $sourceTag === $this->pageSource) {
            return "";
        }

        $sourceIsBibliographyKey = isset($this->bibArray[$sourceTag]);
        $keyText = $this->bibArray["#" . $sourceTag] ?? $sourceTag;
        $label = $metadata["sourceDetail"] === "" ? $keyText : $metadata["sourceDetail"] . " of " . $keyText;

        if (!$sourceIsBibliographyKey) {
            return "<span class='source-note'>[" . escapeHtml($label) . "]</span>";
        }

        $this->addReferenceKey($sourceTag);
        return "<a href='#" . escapeHtml($sourceTag) . "'>[$label]</a>";
    }

    private function renderLine($line) {
        if (preg_match('/\\\\input\{([^\}]*)\}/', $line, $matches) === 1) {
            $inputTag = normalizeSnippetTag(str_replace(".tex", "", $matches[1]));
            if ($inputTag !== null && snippetFilePath($inputTag) !== null) {
                return $this->renderSnippet($inputTag);
            }
            return "<p><b>could not find file " . escapeHtml($matches[1]) . "</b></p>";
        }

        if ($line === "/n") {
            return "</p><p>";
        }

        $line = preg_replace('/(?<!\\\\)%.*/', '', $line);
        $line = $this->mathText($line);
        $line = $this->protectInlineHtml($line);
        $line = escapeHtml($line);
        $line = $this->renderStructuralTex($line);
        $line = $this->restoreInlineHtml($line);

        return $line;
    }

    private $inlineHtml = array();

    private function protectInlineHtml($line) {
        $this->inlineHtml = array();

        $line = preg_replace_callback('/\\\\href\{([^\}]*)\}\{([^\}]*)\}/', function ($matches) {
            return $this->storeInlineHtml("<a href='" . $this->safeHref($matches[1]) . "'>" . escapeHtml($matches[2]) . "</a>");
        }, $line);

        $line = preg_replace_callback('/\\\\snip\{([^\}]*)\}\{([^\}]*)\}/', function ($matches) {
            $tag = normalizeSnippetTag($matches[2]);
            $href = $tag === null ? "#" : snippetUrlTag($tag);
            return $this->storeInlineHtml("<a href='" . escapeHtml($href) . "'>" . escapeHtml($matches[1]) . "</a>");
        }, $line);

        $line = preg_replace_callback('/\\\\emph\{([^\}]*)\}/', function ($matches) {
            return $this->storeInlineHtml("<em>" . escapeHtml($matches[1]) . "</em>");
        }, $line);

        $line = preg_replace_callback('/\\\\footnote\{([^\}]*)\}/', function ($matches) {
            $this->footnoteCounter++;
            return $this->storeInlineHtml("<span title='" . escapeHtml($matches[1]) . "'><sup>" . escapeHtml($this->footnoteCounter) . "</sup></span>");
        }, $line);

        $line = preg_replace_callback('/\\\\[cC]ite(?:\[[^\]]*\])?\{([^\}]*)\}/', function ($matches) {
            return $this->storeInlineHtml($this->renderCitationList($matches[1]));
        }, $line);

        $line = preg_replace_callback('/\\\\"([A-Za-z])/', function ($matches) {
            return $this->storeInlineHtml("&" . $matches[1] . "uml;");
        }, $line);

        return $line;
    }

    private function renderStructuralTex($line) {
        $line = preg_replace('/\\\\begin\{itemize\}/', '<ul>', $line);
        $line = preg_replace('/\\\\end\{itemize\}/', '</ul>', $line);
        $line = preg_replace('/\\\\begin\{enumerate\}/', '<ol>', $line);
        $line = preg_replace('/\\\\end\{enumerate\}/', '</ol>', $line);
        $line = preg_replace('/\\\\begin\{description\}/', '<ul>', $line);
        $line = preg_replace('/\\\\end\{description\}/', '</ul>', $line);
        $line = preg_replace('/\\\\begin\{(?:figure|subfigure|definition|exposition|proposition|conjecture|proof|lemma|article|remark|corollary|exercise|example|theorem|construction|application)\}(?:\{[^\}]*\})?/', '', $line);
        $line = preg_replace('/\\\\end\{(?:figure|subfigure|definition|exposition|proposition|conjecture|proof|lemma|article|remark|corollary|exercise|example|theorem|construction|application)\}/', '', $line);
        $line = preg_replace('/\\\\item\b/', '<li>', $line);
        $line = preg_replace('/\\\\intertext\{([^\}]*)\}/', '\\\\end{align*}$1\\\\begin{align*}', $line);
        $line = preg_replace('/\\\\caption.*/', '', $line);
        $line = preg_replace('/\\\\label\{([^\}]*)\}/', '', $line);
        $line = preg_replace('/\\\\subsection\*\{([^\}]*)\}/', '<h3>$1</h3>', $line);
        $line = preg_replace('/\\\\section\{[^\}]*\}/', '', $line);
        $line = preg_replace('/\\\\centering/', '', $line);
        return $line;
    }

    private function resolveCrossReferences($bodyText) {
        foreach ($this->labelArray as $label => $index) {
            $regExp = "/\\\\cref\{" . preg_quote($label, "/") . "\}/";
            $bodyText = preg_replace($regExp, "<a href='#" . escapeHtml($label) . "'>" . escapeHtml($index) . "</a>", $bodyText);
        }

        foreach ($this->tagArray as $label => $name) {
            $regExp = "/\\\\cref\{" . preg_quote($label, "/") . "\}/";
            $urlLabel = snippetUrlTag($label);
            $bodyText = preg_replace($regExp, "<a href='" . escapeHtml($urlLabel) . "'>(" . escapeHtml($name) . ")</a>", $bodyText);
        }

        return preg_replace('/\\\\cref\{([^\}]*)\}/', '<b>Missing Label ($1)!</b>', $bodyText);
    }

    private function renderCitationList($rawKeys) {
        $links = array();
        foreach (explode(",", $rawKeys) as $key) {
            $key = trim($key);
            if ($key === "") {
                continue;
            }
            $this->addReferenceKey($key);
            $keyText = $this->bibArray["#" . $key] ?? escapeHtml($key);
            $links[] = "<a href='#" . escapeHtml($key) . "'>$keyText</a>";
        }

        if ($links === array()) {
            return "";
        }

        return "[" . implode(", ", $links) . "]";
    }

    private function addReferenceKey($key) {
        if (!in_array($key, $this->referenceKeys)) {
            $this->referenceKeys[] = $key;
        }
    }

    private function safeHref($url) {
        $url = trim($url);
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return "#";
        }

        if (preg_match('/\Ahttps?:\/\//i', $url) === 1 || str_starts_with($url, "#") || preg_match('/\A[A-Za-z0-9_\/.\-?=&%#]+\z/', $url) === 1) {
            return escapeHtml($url);
        }

        return "#";
    }

    private function storeInlineHtml($html) {
        $token = "%%SNIPPET_HTML_" . count($this->inlineHtml) . "%%";
        $this->inlineHtml[$token] = $html;
        return $token;
    }

    private function restoreInlineHtml($line) {
        return strtr($line, $this->inlineHtml);
    }
}
