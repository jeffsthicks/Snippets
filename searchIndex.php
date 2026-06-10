<?php

require_once __DIR__ . "/code/snippetCatalog.php";

$catalog = loadSnippetCatalog();
$items = array();

foreach ($catalog["items"] as $item) {
    $items[] = array(
        "tag" => $item["tag"],
        "label" => $item["label"],
        "type" => $item["type"],
        "name" => $item["name"],
        "url" => $item["url"],
        "excerpt" => $item["excerpt"],
        "searchText" => $item["searchText"],
    );
}

header("Content-Type: application/json; charset=utf-8");
echo json_encode(
    array(
        "count" => count($items),
        "types" => snippetCatalogTypes($catalog),
        "items" => $items,
    ),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

