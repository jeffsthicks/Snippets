<?php
    require_once __DIR__ . "/code/snippetHelpers.php";
    require_once __DIR__ . "/code/snippetCatalog.php";
    require_once __DIR__ . "/code/snippetRenderer.php";
    include("code/preambles/referenceArray.php");
    include("code/preambles/tagArray.php");

    $requestedTagName = $_GET['tag'] ?? 'main';
    $tagName = normalizeSnippetTag($requestedTagName);
    $snippetNotFound = $tagName === null || snippetFilePath($tagName) === null;

    if ($snippetNotFound) {
        http_response_code(404);
        $tagName = "main";
        $pageTitle = "Snippet not found";
        $bodyText = "<p>The requested snippet could not be found.</p>";
        $tableOfContents = array();
        $referencesHtml = "";
    }
    else {
        $renderer = new SnippetRenderer($bibArray, $tagArray);
        $renderedPage = $renderer->renderPage($tagName);
        $pageTitle = $renderedPage["title"];
        $bodyText = $renderedPage["body"];
        $tableOfContents = $renderedPage["toc"];
        $referencesHtml = $renderer->renderReferences();
    }

    $snippetCatalog = loadSnippetCatalog();
    $snippetTypes = snippetCatalogTypes($snippetCatalog);

    if (!$snippetNotFound) {
        if ($tagName === "main") {
            $bodyText .= renderSnippetAtlas($snippetCatalog);
            $bodyText .= renderSnippetBrowse($snippetCatalog);
        }
        else {
            $bodyText .= renderSnippetConnections($tagName, $snippetCatalog);
        }
    }
?>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SympSnip: <?php echo(escapeHtml($pageTitle)); ?></title>
<link rel="stylesheet" href="code/styles.css">

<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3.0.1/es5/tex-mml-chtml.js"></script>
<script defer src="code/search.js"></script>
  </head>
<body>
<?php include("code/preambles/mathpreamble.php"); ?>
<main id="snippet-resource" class="snippet-resource">
    <header class="snippet-mast">
        <p class="eyebrow">Symplectic snippets</p>
        <h1><?php echo(escapeHtml($pageTitle)); ?></h1>
    </header>

    <section class="snippet-workbench" aria-label="Snippet reader">
    <aside class="menu" aria-label="Snippet navigation">
    <div class="title">Sections</div>
    <div class="menu-tools">
        <a href="../">Jeff Hicks</a>
        <a href="main">Home</a>
        <a href="main#atlas">Index</a>
        <a href="main#browse">Browse</a>
        <input id="snippet-search" type="search" placeholder="Search snippets" aria-label="Search snippets">
        <select id="snippet-search-type" aria-label="Filter search by type">
            <?php echo(renderSnippetTypeOptions($snippetTypes)); ?>
        </select>
        <div id="snippet-search-results" class="search-results"></div>
    </div>
    <?php if (count($tableOfContents) !== 0) { ?>
    <div class="menu-section">ON THIS PAGE</div>
    <?php } ?>
<?php
    if (count($tableOfContents) !== 0) {
        echo("<ul class='toc-list'>");
        foreach($tableOfContents as $item){
            echo($item["item"]);
        }
        echo("</ul>");
    }
    if (!$snippetNotFound) {
        echo("<div class='menu-section'>ACTIONS</div>");
        echo("<ul class='menu-links'><li><a href='./downloadSnippet.php?tag=" . escapeHtml($tagName) . "'>Download .tex</a></li></ul>");
    }
?>
    </aside>

<article class="snippet-article">
   <?php
   echo($bodyText);
   echo($referencesHtml);
?>

</article>
    </section>
</main>
</body>
</html>
